<?php
/**
 * AJAX Endpoint - Get Dashboard Statistics
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
    
    $stats = [];
    
    // Total applications
    $stmt = $db->query("SELECT COUNT(*) as count FROM applications");
    $stats['total_applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Deployed applications
    $stmt = $db->query("SELECT COUNT(*) as count FROM applications WHERE deployed = true");
    $stats['deployed_applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total deployments
    $stmt = $db->query("SELECT COUNT(*) as count FROM deployments");
    $stats['total_deployments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent deployments (last 24 hours)
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM deployments 
        WHERE started_at >= NOW() - INTERVAL '24 hours'
    ");
    $stats['recent_deployments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Successful deployments
    $stmt = $db->query("SELECT COUNT(*) as count FROM deployments WHERE status = 'completed'");
    $stats['successful_deployments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Failed deployments
    $stmt = $db->query("SELECT COUNT(*) as count FROM deployments WHERE status = 'failed'");
    $stats['failed_deployments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Running deployments
    $stmt = $db->query("SELECT COUNT(*) as count FROM deployments WHERE status = 'running'");
    $stats['running_deployments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Vector embeddings count (if available)
    $vectorSupport = checkVectorSupport($db);
    if ($vectorSupport) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM embeddings");
            $stats['total_embeddings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            $stats['total_embeddings'] = 0;
        }
    } else {
        $stats['total_embeddings'] = 0;
    }
    
    // System storage usage (approximate)
    $appsPath = '/app/code/apps';
    $stats['storage_used'] = 0;
    if (is_dir($appsPath)) {
        $stats['storage_used'] = folderSize($appsPath);
    }
    
    // Recent activity count
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM activity_log 
        WHERE created_at >= NOW() - INTERVAL '7 days'
    ");
    $stats['recent_activity'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Average deployment time (in minutes)
    $stmt = $db->query("
        SELECT AVG(EXTRACT(EPOCH FROM (completed_at - started_at)) / 60) as avg_time
        FROM deployments 
        WHERE status = 'completed' 
        AND completed_at IS NOT NULL 
        AND started_at IS NOT NULL
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['avg_deployment_time'] = $result['avg_time'] ? round($result['avg_time'], 1) : 0;
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Get dashboard stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'stats' => []
    ]);
}

function folderSize($dir) {
    $size = 0;
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") {
                    $size += folderSize($dir."/".$object);
                } else {
                    $size += filesize($dir."/".$object);
                }
            }
        }
    }
    return $size;
}
?> 