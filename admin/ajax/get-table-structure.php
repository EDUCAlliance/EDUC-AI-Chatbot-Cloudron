<?php
/**
 * AJAX Endpoint - Get Table Structure
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
    
    $table = $_GET['table'] ?? '';
    $schema = $_GET['schema'] ?? 'public';
    
    // Validate table name (security)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new Exception('Invalid table name');
    }
    
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
        throw new Exception('Invalid schema name');
    }
    
    $stmt = $db->prepare("
        SELECT 
            column_name,
            data_type,
            is_nullable,
            column_default,
            character_maximum_length,
            numeric_precision,
            numeric_scale,
            ordinal_position
        FROM information_schema.columns 
        WHERE table_schema = ? AND table_name = ?
        ORDER BY ordinal_position
    ");
    $stmt->execute([$schema, $table]);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get table row count
    $stmt = $db->prepare("SELECT COUNT(*) as row_count FROM \"{$schema}\".\"{$table}\"");
    $stmt->execute();
    $rowCount = $stmt->fetch(PDO::FETCH_ASSOC)['row_count'];
    
    // Get indexes
    $stmt = $db->prepare("
        SELECT 
            i.relname as index_name,
            a.attname as column_name,
            ix.indisunique as is_unique,
            ix.indisprimary as is_primary
        FROM 
            pg_class t,
            pg_class i,
            pg_index ix,
            pg_attribute a,
            pg_namespace n
        WHERE 
            t.oid = ix.indrelid
            AND i.oid = ix.indexrelid
            AND a.attrelid = t.oid
            AND a.attnum = ANY(ix.indkey)
            AND t.relkind = 'r'
            AND n.oid = t.relnamespace
            AND n.nspname = ?
            AND t.relname = ?
        ORDER BY i.relname, a.attname
    ");
    $stmt->execute([$schema, $table]);
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'row_count' => $rowCount,
        'indexes' => $indexes,
        'table' => $table,
        'schema' => $schema
    ]);
    
} catch (Exception $e) {
    error_log("Get table structure error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 