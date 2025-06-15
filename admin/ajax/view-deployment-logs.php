<?php
/**
 * AJAX Endpoint - View Deployment Logs
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
    
    // Get latest deployment for this app
    $stmt = $db->prepare("
        SELECT * FROM deployments 
        WHERE application_id = ? 
        ORDER BY started_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$appId]);
    $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deployment) {
        echo json_encode([
            'success' => true,
            'message' => 'No deployment found for this application',
            'deployment' => null,
            'log' => 'No deployment logs available.',
            'logFile' => null
        ]);
        exit;
    }
    
    // Try to read the deployment log file
    $appDirectory = "/app/code/apps/{$app['directory']}";
    $logFile = "{$appDirectory}/deployment.log";
    $fileLog = '';
    
    if (file_exists($logFile)) {
        $fileLog = file_get_contents($logFile);
    }
    
    // Combine database log and file log
    $combinedLog = $deployment['log'] ?? '';
    if (!empty($fileLog) && $fileLog !== $combinedLog) {
        $combinedLog .= "\n\n--- Live Deployment Log ---\n" . $fileLog;
    }
    
    if (empty($combinedLog)) {
        $combinedLog = "No deployment logs available for this application.";
    }
    
    echo json_encode([
        'success' => true,
        'deployment' => $deployment,
        'log' => $combinedLog,
        'logFile' => $logFile,
        'app_name' => $app['name'],
        'status' => $deployment['status'],
        'started_at' => $deployment['started_at'],
        'completed_at' => $deployment['completed_at']
    ]);
    
} catch (Exception $e) {
    error_log("View deployment logs error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 