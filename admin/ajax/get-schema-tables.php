<?php
/**
 * AJAX Endpoint - Get Tables for Schema
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
    
    $schema = $_GET['schema'] ?? 'public';
    
    // Validate schema name (security)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
        throw new Exception('Invalid schema name');
    }
    
    $stmt = $db->prepare("
        SELECT table_name, table_type
        FROM information_schema.tables 
        WHERE table_schema = ?
        ORDER BY table_name
    ");
    $stmt->execute([$schema]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'schema' => $schema
    ]);
    
} catch (Exception $e) {
    error_log("Get schema tables error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 