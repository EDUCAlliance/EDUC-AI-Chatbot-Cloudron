<?php
/**
 * AJAX Endpoint - Get Deployment Status
 */

require_once __DIR__ . '/../../public/config/database.php';
require_once __DIR__ . '/../../public/config/config.php';

// Set JSON content type
header('Content-Type: application/json');

try {
    $db = getDbConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $deploymentId = $_GET['id'] ?? '';
    
    if (!$deploymentId || !is_numeric($deploymentId)) {
        throw new Exception('Invalid deployment ID');
    }
    
    $stmt = $db->prepare("
        SELECT 
            d.*,
            a.name as app_name,
            a.directory as app_directory
        FROM deployments d
        LEFT JOIN applications a ON d.application_id = a.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deploymentId]);
    $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deployment) {
        throw new Exception('Deployment not found');
    }
    
    // Read deployment log file if it exists
    $logFile = "/app/code/apps/{$deployment['app_directory']}/deployment.log";
    $log = '';
    if (file_exists($logFile)) {
        $log = file_get_contents($logFile);
    }
    
    // Calculate progress based on status
    $progress = 0;
    switch ($deployment['status']) {
        case 'pending':
            $progress = 0;
            break;
        case 'running':
            $progress = 50;
            break;
        case 'completed':
            $progress = 100;
            break;
        case 'failed':
            $progress = 0;
            break;
    }
    
    echo json_encode([
        'success' => true,
        'deployment' => $deployment,
        'status' => $deployment['status'],
        'log' => $log,
        'progress' => $progress,
        'started_at' => $deployment['started_at'],
        'completed_at' => $deployment['completed_at'],
        'app_name' => $deployment['app_name']
    ]);
    
} catch (Exception $e) {
    error_log("Get deployment status error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'status' => 'failed'
    ]);
}
?> 