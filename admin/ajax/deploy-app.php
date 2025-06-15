<?php
/**
 * AJAX Endpoint - Deploy Application
 */

require_once __DIR__ . '/../../public/config/database.php';
require_once __DIR__ . '/../../public/config/config.php';

startSecureSession();

// Set JSON content type
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    $db = getDbConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $appId = $input['app_id'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';
    
    if (empty($appId) || !is_numeric($appId)) {
        throw new Exception('Invalid application ID');
    }
    
    // Get application data
    $stmt = $db->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        throw new Exception('Application not found');
    }
    
    // Check if there's already a running deployment for this app
    $stmt = $db->prepare("SELECT id FROM deployments WHERE application_id = ? AND status = 'running'");
    $stmt->execute([$appId]);
    if ($stmt->fetch()) {
        throw new Exception('A deployment is already running for this application');
    }
    
    // Create deployment record
    $stmt = $db->prepare("
        INSERT INTO deployments (application_id, status, started_at, log)
        VALUES (?, 'pending', CURRENT_TIMESTAMP, 'Deployment initiated...\n')
        RETURNING id
    ");
    $stmt->execute([$appId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $deploymentId = $result['id'];
    
    // Log the activity
    logActivity($db, 'deployment_started', "Started deployment for: {$app['name']}", $_SESSION['user_id']);
    
    // Start background deployment process
    $deploymentScript = __DIR__ . '/../../scripts/deploy-background.php';
    $logFile = "/app/code/apps/{$app['directory']}/deployment.log";
    
    // Create app directory if it doesn't exist
    $appDirectory = "/app/code/apps/{$app['directory']}";
    if (!is_dir($appDirectory)) {
        mkdir($appDirectory, 0755, true);
    }
    
    // Find PHP binary path
    $phpPaths = ['/usr/local/bin/php', '/usr/bin/php', 'php'];
    $phpBinary = 'php'; // fallback
    
    foreach ($phpPaths as $path) {
        if (file_exists($path)) {
            $phpBinary = $path;
            break;
        }
    }
    
    // Execute background script with proper logging
    $command = "{$phpBinary} {$deploymentScript} {$deploymentId} >> {$logFile} 2>&1 &";
    
    // Log the command being executed for debugging
    error_log("Executing deployment command: {$command}");
    
    exec($command, $output, $return_var);
    
    // Log execution result
    if ($return_var !== 0) {
        error_log("Failed to start background deployment. Return code: {$return_var}");
        error_log("Output: " . implode("\n", $output));
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Deployment started',
        'deployment_id' => $deploymentId,
        'app_name' => $app['name']
    ]);
    
} catch (Exception $e) {
    error_log("Deploy application error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 