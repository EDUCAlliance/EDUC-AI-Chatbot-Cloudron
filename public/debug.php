<?php
/**
 * Debug Information Page
 * Helps diagnose deployment and configuration issues
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Information</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #333; background: #2a2a2a; }
        .ok { color: #00ff00; }
        .error { color: #ff0000; }
        .warning { color: #ffaa00; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Debug Information</h1>
    
    <div class="section">
        <h2>Server Environment</h2>
        <pre><?php
        echo "PHP Version: " . phpversion() . "\n";
        echo "Web Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "\n";
        echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' . "\n";
        echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Server Name: " . $_SERVER['SERVER_NAME'] ?? 'Unknown' . "\n";
        echo "Server Port: " . $_SERVER['SERVER_PORT'] ?? 'Unknown' . "\n";
        ?></pre>
    </div>

    <div class="section">
        <h2>Environment Variables</h2>
        <pre><?php
        // Check both $_ENV and getenv for Cloudron variables
        $cloudronVars = array_filter($_ENV, function($key) {
            return strpos($key, 'CLOUDRON') === 0;
        }, ARRAY_FILTER_USE_KEY);
        
        // Also check getenv() for PostgreSQL vars
        $postgresqlVars = [
            'CLOUDRON_POSTGRESQL_HOST',
            'CLOUDRON_POSTGRESQL_PORT',
            'CLOUDRON_POSTGRESQL_DATABASE',
            'CLOUDRON_POSTGRESQL_USERNAME',
            'CLOUDRON_POSTGRESQL_PASSWORD',
            'CLOUDRON_POSTGRESQL_URL'
        ];
        
        echo "=== Environment Variables via \$_ENV ===\n";
        if (empty($cloudronVars)) {
            echo "<span class='warning'>No Cloudron environment variables found in \$_ENV</span>\n";
        } else {
            foreach ($cloudronVars as $key => $value) {
                if (strpos($key, 'PASSWORD') !== false) {
                    $value = '[HIDDEN]';
                }
                echo "{$key}: {$value}\n";
            }
        }
        
        echo "\n=== Environment Variables via getenv() ===\n";
        foreach ($postgresqlVars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                if (strpos($var, 'PASSWORD') !== false) {
                    $value = '[HIDDEN]';
                }
                echo "{$var}: {$value}\n";
            } else {
                echo "<span class='error'>{$var}: NOT_SET</span>\n";
            }
        }
        
        echo "\n=== Final Connection Parameters ===\n";
        $host = getenv('CLOUDRON_POSTGRESQL_HOST') ?: $_ENV['CLOUDRON_POSTGRESQL_HOST'] ?? $_ENV['POSTGRESQL_HOST'] ?? 'postgresql';
        $port = getenv('CLOUDRON_POSTGRESQL_PORT') ?: $_ENV['CLOUDRON_POSTGRESQL_PORT'] ?? $_ENV['POSTGRESQL_PORT'] ?? '5432';
        $database = getenv('CLOUDRON_POSTGRESQL_DATABASE') ?: $_ENV['CLOUDRON_POSTGRESQL_DATABASE'] ?? $_ENV['POSTGRESQL_DATABASE'] ?? 'app';
        $username = getenv('CLOUDRON_POSTGRESQL_USERNAME') ?: $_ENV['CLOUDRON_POSTGRESQL_USERNAME'] ?? $_ENV['POSTGRESQL_USERNAME'] ?? 'postgres';
        
        echo "Host: {$host}\n";
        echo "Port: {$port}\n";
        echo "Database: {$database}\n";
        echo "Username: {$username}\n";
        echo "Password: [HIDDEN]\n";
        ?></pre>
    </div>

    <div class="section">
        <h2>File System</h2>
        <pre><?php
        $paths = [
            '/app/code' => 'Application directory',
            '/app/code/public' => 'Web root',
            '/app/code/admin' => 'Admin panel',
            '/app/data' => 'Data directory',
            '/run/app/sessions' => 'Session directory'
        ];

        foreach ($paths as $path => $description) {
            $exists = file_exists($path);
            $writable = $exists ? is_writable($path) : false;
            $status = $exists ? ($writable ? 'OK' : 'READ-ONLY') : 'MISSING';
            $class = $exists ? ($writable ? 'ok' : 'warning') : 'error';
            echo "<span class='{$class}'>{$path}</span> ({$description}): {$status}\n";
        }
        ?></pre>
    </div>

    <div class="section">
        <h2>Database Connection</h2>
        <pre><?php
        try {
            require_once __DIR__ . '/config/database.php';
            $db = getDbConnection();
            
            if ($db) {
                echo "<span class='ok'>✓ Database connection successful</span>\n";
                
                // Test vector extension
                try {
                    $stmt = $db->query("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'vector') as has_vector");
                    $result = $stmt->fetch();
                    if ($result['has_vector']) {
                        echo "<span class='ok'>✓ pgvector extension available</span>\n";
                    } else {
                        echo "<span class='warning'>⚠ pgvector extension not installed</span>\n";
                    }
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠ Could not check pgvector: " . $e->getMessage() . "</span>\n";
                }
                
                // Check tables
                try {
                    $stmt = $db->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo "Tables: " . implode(', ', $tables) . "\n";
                } catch (Exception $e) {
                    echo "<span class='warning'>⚠ Could not list tables: " . $e->getMessage() . "</span>\n";
                }
                
            } else {
                echo "<span class='error'>✗ Database connection failed</span>\n";
            }
        } catch (Exception $e) {
            echo "<span class='error'>✗ Database error: " . $e->getMessage() . "</span>\n";
        }
        ?></pre>
    </div>

    <div class="section">
        <h2>PHP Extensions</h2>
        <pre><?php
        $required = ['pgsql', 'pdo_pgsql', 'curl', 'json', 'mbstring', 'xml', 'zip'];
        foreach ($required as $ext) {
            $loaded = extension_loaded($ext);
            $status = $loaded ? 'LOADED' : 'MISSING';
            $class = $loaded ? 'ok' : 'error';
            echo "<span class='{$class}'>{$ext}</span>: {$status}\n";
        }
        ?></pre>
    </div>

    <div class="section">
        <h2>Application Status</h2>
        <pre><?php
        try {
            if (file_exists(__DIR__ . '/index.php')) {
                echo "<span class='ok'>✓ Main application file exists</span>\n";
            } else {
                echo "<span class='error'>✗ Main application file missing</span>\n";
            }
            
            if (file_exists(__DIR__ . '/../admin/index.php')) {
                echo "<span class='ok'>✓ Admin panel exists</span>\n";
            } else {
                echo "<span class='error'>✗ Admin panel missing</span>\n";
            }
            
            echo "Current working directory: " . getcwd() . "\n";
            echo "Script filename: " . $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown' . "\n";
            
        } catch (Exception $e) {
            echo "<span class='error'>Error checking application: " . $e->getMessage() . "</span>\n";
        }
        ?></pre>
    </div>

    <div class="section">
        <p>
            <a href="/" style="color: #00aaff;">← Back to Application</a> | 
            <a href="/server-admin/" style="color: #00aaff;">Admin Panel</a> |
            <a href="/health.php" style="color: #00aaff;">Health Check</a>
        </p>
    </div>
</body>
</html> 