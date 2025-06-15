<?php
/**
 * AJAX Endpoint - Delete Application
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
    
    // Get application data before deletion
    $stmt = $db->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        throw new Exception('Application not found');
    }
    
    // Check if there are any running deployments
    $stmt = $db->prepare("SELECT id FROM deployments WHERE application_id = ? AND status = 'running'");
    $stmt->execute([$appId]);
    if ($stmt->fetch()) {
        throw new Exception('Cannot delete application with running deployments. Please wait for deployment to complete.');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete associated embeddings (if vector support is available)
        $vectorSupport = checkVectorSupport($db);
        if ($vectorSupport) {
            $stmt = $db->prepare("DELETE FROM embeddings WHERE application_id = ?");
            $stmt->execute([$appId]);
        }
        
        // Delete deployment records
        $stmt = $db->prepare("DELETE FROM deployments WHERE application_id = ?");
        $stmt->execute([$appId]);
        
        // Delete application
        $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$appId]);
        
        // Commit transaction
        $db->commit();
        
        // Log the activity
        logActivity($db, 'app_deleted', "Deleted application: {$app['name']}", $_SESSION['user_id']);
        
        // Clean up physical directory
        $appDirectory = "/app/code/apps/{$app['directory']}";
        if (is_dir($appDirectory)) {
            // Use a background process to delete directory to avoid timeout
            $command = "rm -rf " . escapeshellarg($appDirectory) . " > /dev/null 2>&1 &";
            exec($command);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Application deleted successfully',
            'app_name' => $app['name']
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Delete application error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 