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
        INSERT INTO deployments (application_id, status, started_at, log, created_at)
        VALUES (?, 'pending', CURRENT_TIMESTAMP, 'Deployment initiated...\n', CURRENT_TIMESTAMP)
        RETURNING id
    ");
    $stmt->execute([$appId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $deploymentId = $result['id'];
    
    // Log the activity
    logActivity($db, 'deployment_started', "Started deployment for: {$app['name']}", $_SESSION['user_id']);
    
    // Start background deployment process
    $deploymentScript = __DIR__ . '/../../scripts/deploy-background.php';
    $command = "php {$deploymentScript} {$deploymentId} > /dev/null 2>&1 &";
    exec($command);
    
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