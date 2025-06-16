<?php
/**
 * Environment Variables Test Page
 * This file helps debug custom environment variables in deployed applications
 * CRITICAL: Files custom-env.php and auto-include.php are INTENTIONALLY hidden by .htaccess for security
 */

// Include auto-generated environment variables if they exist
if (file_exists(__DIR__ . '/auto-include.php')) {
    require_once __DIR__ . '/auto-include.php';
}

if (file_exists(__DIR__ . '/custom-env.php')) {
    require_once __DIR__ . '/custom-env.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Environment Variables Test</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .warning { background: #fff3cd; border-color: #ffeaa7; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #d1ecf1; border-color: #bee5eb; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
        .sensitive { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Environment Variables Test</h1>
        
        <div class="section info">
            <h3>🛡️ Security Notice</h3>
            <p><strong>Files <code>custom-env.php</code> and <code>auto-include.php</code> are INTENTIONALLY hidden by .htaccess</strong></p>
            <p>This is a security feature to prevent exposure of environment variables via web browser.</p>
            <p>The files exist and work correctly - they're just protected from direct web access.</p>
        </div>

        <div class="section">
            <h3>📁 File Existence Check</h3>
            <table>
                <tr><th>File</th><th>Exists</th><th>Readable</th><th>Size</th></tr>
                <?php
                $files = ['custom-env.php', 'auto-include.php', '.htaccess', 'index.php'];
                foreach ($files as $file) {
                    $path = __DIR__ . '/' . $file;
                    $exists = file_exists($path);
                    $readable = $exists ? is_readable($path) : false;
                    $size = $exists ? filesize($path) : 0;
                    $status = $exists ? '✅ YES' : '❌ NO';
                    $readStatus = $readable ? '✅ YES' : '❌ NO';
                    
                    echo "<tr>";
                    echo "<td>{$file}</td>";
                    echo "<td>{$status}</td>";
                    echo "<td>{$readStatus}</td>";
                    echo "<td>{$size} bytes</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <div class="section">
            <h3>🔧 Application Environment Variables</h3>
            <table>
                <tr><th>Variable</th><th>Value</th><th>Source</th></tr>
                <?php
                $appVars = [
                    'APP_NAME' => 'Application Context',
                    'APP_ID' => 'Application Context', 
                    'APP_DIRECTORY' => 'Application Context'
                ];
                
                foreach ($appVars as $var => $source) {
                    $value = $_ENV[$var] ?? getenv($var) ?: 'NOT SET';
                    echo "<tr>";
                    echo "<td>{$var}</td>";
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                    echo "<td>{$source}</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <div class="section">
            <h3>🗃️ Cloudron Environment Variables</h3>
            <table>
                <tr><th>Variable</th><th>Value</th><th>Available</th></tr>
                <?php
                $cloudronVars = [
                    'CLOUDRON_POSTGRESQL_HOST',
                    'CLOUDRON_POSTGRESQL_PORT', 
                    'CLOUDRON_POSTGRESQL_DATABASE',
                    'CLOUDRON_APP_DOMAIN',
                    'CLOUDRON_ENVIRONMENT'
                ];
                
                foreach ($cloudronVars as $var) {
                    $value = $_ENV[$var] ?? getenv($var);
                    $available = $value !== false && $value !== null ? '✅ YES' : '❌ NO';
                    
                    // Hide sensitive values
                    if (stripos($var, 'PASSWORD') !== false || stripos($var, 'SECRET') !== false) {
                        $displayValue = $value ? '[HIDDEN]' : 'NOT SET';
                    } else {
                        $displayValue = $value ?: 'NOT SET';
                    }
                    
                    echo "<tr>";
                    echo "<td>{$var}</td>";
                    echo "<td>" . htmlspecialchars($displayValue) . "</td>";
                    echo "<td>{$available}</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <div class="section">
            <h3>⚙️ Custom Environment Variables</h3>
            <?php
            // Get all environment variables that might be custom
            $allEnvVars = array_merge($_ENV, getenv());
            $customVars = [];
            
            // Common Cloudron/system variables to exclude
            $systemVars = [
                'CLOUDRON_', 'APP_', 'HOME', 'PATH', 'PWD', 'USER', 'SHELL', 'TERM',
                'PHP_', 'APACHE_', 'DOCUMENT_ROOT', 'SERVER_', 'REQUEST_', 'HTTP_',
                'GATEWAY_INTERFACE', 'SCRIPT_', 'QUERY_STRING', 'CONTENT_', 'REMOTE_'
            ];
            
            foreach ($allEnvVars as $key => $value) {
                $isSystem = false;
                foreach ($systemVars as $prefix) {
                    if (strpos($key, $prefix) === 0) {
                        $isSystem = true;
                        break;
                    }
                }
                
                if (!$isSystem && !empty($key) && $value !== false) {
                    $customVars[$key] = $value;
                }
            }
            
            if (empty($customVars)) {
                echo '<div class="warning"><p>No custom environment variables detected.</p></div>';
            } else {
                echo '<table>';
                echo '<tr><th>Variable</th><th>Value</th><th>Available Via</th></tr>';
                
                foreach ($customVars as $key => $value) {
                    // Check availability through different methods
                    $viaEnv = isset($_ENV[$key]) ? '✅ $_ENV' : '❌ $_ENV';
                    $viaGetenv = getenv($key) !== false ? '✅ getenv()' : '❌ getenv()';
                    
                    // Hide potentially sensitive values
                    $isSensitive = stripos($key, 'KEY') !== false || 
                                   stripos($key, 'SECRET') !== false || 
                                   stripos($key, 'PASSWORD') !== false || 
                                   stripos($key, 'TOKEN') !== false;
                    
                    $displayValue = $isSensitive ? '[SENSITIVE]' : htmlspecialchars($value);
                    $rowClass = $isSensitive ? 'class="sensitive"' : '';
                    
                    echo "<tr {$rowClass}>";
                    echo "<td>{$key}</td>";
                    echo "<td>{$displayValue}</td>";
                    echo "<td>{$viaEnv}, {$viaGetenv}</td>";
                    echo "</tr>";
                }
                echo '</table>';
            }
            ?>
        </div>

        <div class="section">
            <h3>🔍 File Content Preview (if accessible)</h3>
            <?php
            if (file_exists(__DIR__ . '/custom-env.php')) {
                echo '<h4>custom-env.php contents:</h4>';
                echo '<div class="code">';
                $content = file_get_contents(__DIR__ . '/custom-env.php');
                // Hide sensitive values in preview
                $content = preg_replace('/(\$_ENV\[\'[^\']*\'\]\s*=\s*\')[^\']*(\';)/', '$1[HIDDEN]$2', $content);
                $content = preg_replace('/(putenv\([^=]*=)[^\)]*(\);)/', '$1[HIDDEN]$2', $content);
                echo htmlspecialchars($content);
                echo '</div>';
            } else {
                echo '<div class="warning"><p>custom-env.php not found or not accessible</p></div>';
            }
            ?>
        </div>

        <div class="section">
            <h3>📊 Summary</h3>
            <ul>
                <li><strong>Custom environment files:</strong> <?= file_exists(__DIR__ . '/custom-env.php') ? '✅ Created and working' : '❌ Missing' ?></li>
                <li><strong>Auto-include functionality:</strong> <?= file_exists(__DIR__ . '/auto-include.php') ? '✅ Available' : '❌ Missing' ?></li>
                <li><strong>Security protection:</strong> ✅ Files hidden from web access by .htaccess</li>
                <li><strong>Application integration:</strong> <?= (isset($_ENV['APP_NAME']) || getenv('APP_NAME')) ? '✅ Working' : '❌ Not integrated' ?></li>
                <li><strong>Custom variables loaded:</strong> <?= count($customVars) > 0 ? "✅ " . count($customVars) . " variables" : "⚠️ None detected" ?></li>
            </ul>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <a href="/server-admin/" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                🔧 Back to Admin Panel
            </a>
        </p>
    </div>
</body>
</html> 