<?php
/**
 * AJAX Endpoint - Get Embeddings Data
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
    
    $appId = $_GET['app_id'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 records
    $offset = (int)($_GET['offset'] ?? 0);
    
    // Check if vector extension is available
    $vectorSupport = checkVectorSupport($db);
    
    if (!$vectorSupport) {
        echo json_encode([
            'success' => true,
            'embeddings' => [],
            'message' => 'Vector support not available'
        ]);
        exit;
    }
    
    // Build query
    $sql = "
        SELECT 
            e.id,
            e.application_id,
            e.content,
            e.metadata,
            e.created_at,
            a.name as app_name
        FROM embeddings e
        LEFT JOIN applications a ON e.application_id = a.id
    ";
    
    $params = [];
    
    if ($appId && is_numeric($appId)) {
        $sql .= " WHERE e.application_id = ?";
        $params[] = $appId;
    }
    
    $sql .= " ORDER BY e.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $embeddings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process embeddings for display
    foreach ($embeddings as &$embedding) {
        // Truncate content for display
        if (strlen($embedding['content']) > 200) {
            $embedding['content'] = substr($embedding['content'], 0, 200) . '...';
        }
        
        // Parse metadata if it's JSON
        if ($embedding['metadata']) {
            try {
                $metadata = json_decode($embedding['metadata'], true);
                $embedding['metadata'] = $metadata;
            } catch (Exception $e) {
                // Keep as string if not valid JSON
            }
        }
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM embeddings";
    $countParams = [];
    
    if ($appId && is_numeric($appId)) {
        $countSql .= " WHERE application_id = ?";
        $countParams[] = $appId;
    }
    
    $stmt = $db->prepare($countSql);
    $stmt->execute($countParams);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'embeddings' => $embeddings,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + $limit) < $total
    ]);
    
} catch (Exception $e) {
    error_log("Get embeddings error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'embeddings' => []
    ]);
}
?> 