<?php 
$pageTitle = 'System Debug';
$action = 'debug'; // Ensure navigation highlighting works
?>

<div class="debug-info">
    <div class="page-header">
        <h1>🔧 System Debug Information</h1>
        <p>Diagnostic information for system troubleshooting</p>
        <div class="alert alert-warning">
            <strong>⚠️ Sensitive Information:</strong> This page contains system internals that should never be exposed publicly.
        </div>
    </div>

    <div class="debug-sections">
        <!-- Server Environment -->
        <div class="debug-card">
            <h2>Server Environment</h2>
            <pre class="debug-output"><?php
            echo "PHP Version: " . phpversion() . "\n";
            echo "Web Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
            echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
            echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
            echo "Memory Limit: " . ini_get('memory_limit') . "\n";
            echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
            ?></pre>
        </div>

        <!-- Database Status -->
        <div class="debug-card">
            <h2>Database Connection</h2>
            <pre class="debug-output"><?php
            try {
                require_once __DIR__ . '/../../public/config/database.php';
                $debugDb = getDbConnection();
                
                if ($debugDb) {
                    echo "<span class='status-success'>✓ Database connection successful</span>\n";
                    
                    // Test vector extension
                    try {
                        $stmt = $debugDb->query("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'vector') as has_vector");
                        $result = $stmt->fetch();
                        if ($result['has_vector']) {
                            echo "<span class='status-success'>✓ pgvector extension available</span>\n";
                        } else {
                            echo "<span class='status-warning'>⚠ pgvector extension not installed</span>\n";
                        }
                    } catch (Exception $e) {
                        echo "<span class='status-warning'>⚠ Could not check pgvector</span>\n";
                    }
                    
                    // Check table count
                    try {
                        $stmt = $debugDb->query("SELECT COUNT(*) as table_count FROM pg_tables WHERE schemaname = 'public'");
                        $result = $stmt->fetch();
                        echo "Tables in public schema: " . $result['table_count'] . "\n";
                    } catch (Exception $e) {
                        echo "<span class='status-warning'>⚠ Could not count tables</span>\n";
                    }
                    
                } else {
                    echo "<span class='status-error'>✗ Database connection failed</span>\n";
                }
            } catch (Exception $e) {
                echo "<span class='status-error'>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
            }
            ?></pre>
        </div>

        <!-- PHP Extensions -->
        <div class="debug-card">
            <h2>PHP Extensions</h2>
            <pre class="debug-output"><?php
            $required = ['pgsql', 'pdo_pgsql', 'curl', 'json', 'mbstring', 'xml', 'zip', 'gd'];
            foreach ($required as $ext) {
                $loaded = extension_loaded($ext);
                $status = $loaded ? 'LOADED' : 'MISSING';
                $class = $loaded ? 'status-success' : 'status-error';
                echo "<span class='{$class}'>{$ext}</span>: {$status}\n";
            }
            ?></pre>
        </div>

        <!-- File System Permissions -->
        <div class="debug-card">
            <h2>File System</h2>
            <pre class="debug-output"><?php
            $paths = [
                '/app/code' => 'Application directory',
                '/app/code/public' => 'Web root',
                '/app/code/admin' => 'Admin panel',
                '/app/code/apps' => 'Deployed apps',
                '/app/data' => 'Data directory',
                '/run/app/sessions' => 'Session directory'
            ];

            foreach ($paths as $path => $description) {
                $exists = file_exists($path);
                $writable = $exists ? is_writable($path) : false;
                $status = $exists ? ($writable ? 'OK' : 'READ-ONLY') : 'MISSING';
                $class = $exists ? ($writable ? 'status-success' : 'status-warning') : 'status-error';
                echo "<span class='{$class}'>{$path}</span> ({$description}): {$status}\n";
            }
            ?></pre>
        </div>

        <!-- Environment Variables (Sanitized) -->
        <div class="debug-card">
            <h2>Environment Variables (Sanitized)</h2>
            <pre class="debug-output"><?php
            // Only show non-sensitive Cloudron variables
            $safeVars = [
                'CLOUDRON_POSTGRESQL_HOST',
                'CLOUDRON_POSTGRESQL_PORT',
                'CLOUDRON_POSTGRESQL_DATABASE',
                'CLOUDRON_POSTGRESQL_USERNAME',
                'CLOUDRON_APP_DOMAIN',
                'CLOUDRON_ENVIRONMENT'
            ];
            
            echo "=== Safe Environment Variables ===\n";
            foreach ($safeVars as $var) {
                $value = getenv($var);
                if ($value !== false) {
                    // Hide sensitive parts
                    if (strpos($var, 'USERNAME') !== false || strpos($var, 'DATABASE') !== false) {
                        $value = substr($value, 0, 8) . '...[REDACTED]';
                    }
                    echo "{$var}: {$value}\n";
                } else {
                    echo "<span class='status-error'>{$var}: NOT_SET</span>\n";
                }
            }
            
            echo "\n=== Sensitive Variables ===\n";
            echo "CLOUDRON_POSTGRESQL_PASSWORD: [REDACTED]\n";
            echo "CLOUDRON_POSTGRESQL_URL: [REDACTED]\n";
            ?></pre>
        </div>

        <!-- System Statistics -->
        <div class="debug-card">
            <h2>System Statistics</h2>
            <pre class="debug-output"><?php
            try {
                $stats = [];
                
                // Memory usage
                $stats['memory_usage'] = memory_get_usage(true);
                $stats['memory_peak'] = memory_get_peak_usage(true);
                
                // Disk usage for apps directory  
                $appsDir = '/app/code/apps';
                if (is_dir($appsDir)) {
                    $stats['apps_disk_usage'] = getDirSize($appsDir);
                }
                
                function getDirSize($dir) {
                    $size = 0;
                    if (is_dir($dir)) {
                        $objects = scandir($dir);
                        foreach ($objects as $object) {
                            if ($object != "." && $object != "..") {
                                $path = $dir . "/" . $object;
                                if (filetype($path) == "dir") {
                                    $size += getDirSize($path);
                                } else {
                                    $size += filesize($path);
                                }
                            }
                        }
                    }
                    return $size;
                }
                
                echo "Memory Usage: " . formatBytes($stats['memory_usage']) . "\n";
                echo "Peak Memory: " . formatBytes($stats['memory_peak']) . "\n";
                if (isset($stats['apps_disk_usage'])) {
                    echo "Apps Directory Size: " . formatBytes($stats['apps_disk_usage']) . "\n";
                }
                
                // Database stats
                if (isset($db) && $db) {
                    $stmt = $db->query("SELECT COUNT(*) as app_count FROM applications");
                    $appCount = $stmt->fetch()['app_count'];
                    echo "Deployed Applications: {$appCount}\n";
                    
                    $stmt = $db->query("SELECT COUNT(*) as deployment_count FROM deployments");
                    $deploymentCount = $stmt->fetch()['deployment_count'];
                    echo "Total Deployments: {$deploymentCount}\n";
                }
                
            } catch (Exception $e) {
                echo "<span class='status-error'>Error getting stats: " . htmlspecialchars($e->getMessage()) . "</span>\n";
            }
            
            // Helper functions moved to avoid conflicts - using global functions
            ?></pre>
        </div>
    </div>
</div>

<style>
.debug-info { padding: 20px; }
.debug-sections { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); 
    gap: 20px; 
    margin-top: 20px; 
}
.debug-card { 
    background: white; 
    border-radius: 8px; 
    padding: 20px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    border: 1px solid #e1e5e9; 
}
.debug-card h2 { 
    margin: 0 0 15px 0; 
    color: #2c3e50; 
    font-size: 1.2em; 
}
.debug-output { 
    background: #1a1a1a; 
    color: #00ff00; 
    padding: 15px; 
    border-radius: 4px; 
    font-family: 'Monaco', 'Consolas', monospace; 
    font-size: 12px; 
    line-height: 1.4; 
    overflow-x: auto; 
    white-space: pre-wrap; 
}
.status-success { color: #00ff00; }
.status-warning { color: #ffaa00; }
.status-error { color: #ff0000; }
.alert { 
    padding: 15px; 
    margin-bottom: 20px; 
    border: 1px solid transparent; 
    border-radius: 4px; 
}
.alert-warning { 
    color: #856404; 
    background-color: #fff3cd; 
    border-color: #ffeaa7; 
}
</style> 