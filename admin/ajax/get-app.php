<?php
/**
 * AJAX Endpoint - Get Application Data
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
    
    $appId = $_GET['id'] ?? '';
    
    if (!$appId || !is_numeric($appId)) {
        throw new Exception('Invalid application ID');
    }
    
    $stmt = $db->prepare("
        SELECT 
            a.*,
            COUNT(d.id) as deployment_count,
            MAX(d.completed_at) as last_deploy
        FROM applications a
        LEFT JOIN deployments d ON a.id = d.application_id AND d.status = 'completed'
        WHERE a.id = ?
        GROUP BY a.id
    ");
    $stmt->execute([$appId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        throw new Exception('Application not found');
    }
    
    // Get recent deployments
    $stmt = $db->prepare("
        SELECT id, status, started_at, completed_at, log
        FROM deployments 
        WHERE application_id = ?
        ORDER BY started_at DESC
        LIMIT 5
    ");
    $stmt->execute([$appId]);
    $recentDeployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'app' => $app,
        'recent_deployments' => $recentDeployments
    ]);
    
} catch (Exception $e) {
    error_log("Get application error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 