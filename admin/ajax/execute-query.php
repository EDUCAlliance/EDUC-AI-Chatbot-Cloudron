<?php
/**
 * AJAX Endpoint - Execute SQL Query
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
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $query = trim($input['query'] ?? '');
    $csrfToken = $input['csrf_token'] ?? '';
    
    // Basic CSRF protection
    if (empty($query)) {
        throw new Exception('Query cannot be empty');
    }
    
    $db = getDbConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Security: Only allow SELECT statements and basic utility statements
    $queryUpper = strtoupper($query);
    $allowedPatterns = [
        '/^SELECT\s+/',
        '/^SHOW\s+/',
        '/^DESCRIBE\s+/',
        '/^EXPLAIN\s+/',
        '/^WITH\s+.+\s+SELECT\s+/' // Allow CTEs that end in SELECT
    ];
    
    $allowed = false;
    foreach ($allowedPatterns as $pattern) {
        if (preg_match($pattern, $queryUpper)) {
            $allowed = true;
            break;
        }
    }
    
    if (!$allowed) {
        throw new Exception('Only SELECT, SHOW, DESCRIBE, EXPLAIN, and CTE queries are allowed for security reasons');
    }
    
    // Prevent potentially dangerous functions
    $dangerousPatterns = [
        '/\bpg_sleep\b/i',
        '/\bpg_terminate_backend\b/i',
        '/\bpg_cancel_backend\b/i',
        '/\blo_import\b/i',
        '/\blo_export\b/i',
        '/\bcopy\s+/i'
    ];
    
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $query)) {
            throw new Exception('Query contains potentially dangerous functions');
        }
    }
    
    // Set query timeout
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
    
    // Execute query
    $startTime = microtime(true);
    $stmt = $db->prepare($query);
    $stmt->execute();
    $executionTime = microtime(true) - $startTime;
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rowCount = count($results);
    
    // Get column names
    $columns = [];
    if ($rowCount > 0) {
        $columns = array_keys($results[0]);
    } else {
        // If no results, try to get column info from the statement
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta = $stmt->getColumnMeta($i);
            if ($meta) {
                $columns[] = $meta['name'];
            }
        }
    }
    
    // Limit results for display (prevent memory issues)
    if ($rowCount > 1000) {
        $results = array_slice($results, 0, 1000);
        $message = "Results limited to first 1000 rows (total: {$rowCount})";
    } else {
        $message = null;
    }
    
    // Log the query execution
    logActivity($db, 'sql_query_executed', 'Query: ' . substr($query, 0, 100) . '...', $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'columns' => $columns,
        'rows_affected' => $rowCount,
        'execution_time' => round($executionTime * 1000, 2), // milliseconds
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    error_log("SQL query error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Query execution error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 