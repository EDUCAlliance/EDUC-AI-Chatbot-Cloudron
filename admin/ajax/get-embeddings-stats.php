<?php
/**
 * AJAX Endpoint - Get Embeddings Statistics
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
    $apps = [];
    
    // Check if vector extension is available
    $vectorSupport = checkVectorSupport($db);
    
    if ($vectorSupport) {
        // Get total embeddings count
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM embeddings");
            $stats['total_embeddings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            $stats['total_embeddings'] = 0;
        }
        
        // Get number of apps with embeddings
        try {
            $stmt = $db->query("
                SELECT COUNT(DISTINCT application_id) as count 
                FROM embeddings 
                WHERE application_id IS NOT NULL
            ");
            $stats['apps_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            $stats['apps_count'] = 0;
        }
        
        // Get vector dimension (from first embedding)
        try {
            $stmt = $db->query("
                SELECT array_length(embedding, 1) as dimension 
                FROM embeddings 
                WHERE embedding IS NOT NULL 
                LIMIT 1
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['dimension'] = $result ? $result['dimension'] : null;
        } catch (Exception $e) {
            $stats['dimension'] = null;
        }
        
        // Get applications that have embeddings
        try {
            $stmt = $db->query("
                SELECT DISTINCT a.id, a.name
                FROM applications a
                INNER JOIN embeddings e ON a.id = e.application_id
                ORDER BY a.name
            ");
            $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $apps = [];
        }
        
    } else {
        $stats = [
            'total_embeddings' => 0,
            'apps_count' => 0,
            'dimension' => null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'apps' => $apps,
        'vector_support' => $vectorSupport
    ]);
    
} catch (Exception $e) {
    error_log("Get embeddings stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'stats' => [
            'total_embeddings' => 0,
            'apps_count' => 0,
            'dimension' => null
        ],
        'apps' => []
    ]);
}
?> 