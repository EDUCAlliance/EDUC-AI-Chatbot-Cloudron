<?php
/**
 * PHP Git App Manager - Main Index
 * Manages deployed Git applications and provides entry point
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

// Initialize database connection with error handling
$db = null;
$dbError = null;

try {
    $db = getDbConnection();
} catch (Exception $e) {
    $dbError = $e->getMessage();
    error_log("Main application database connection failed: " . $dbError);
}

// Handle database connection errors
if ($dbError) {
    showDatabaseErrorPage($dbError);
    exit;
}

// Check if admin is set up (only if database is available)
$setupComplete = $db ? checkAdminSetup($db) : false;

if (!$setupComplete) {
    // Redirect to admin setup if not configured
    header('Location: /server-admin/');
    exit;
}

// Get active applications (only if database is available)
$apps = $db ? getActiveApplications($db) : [];

if (empty($apps)) {
    // No apps deployed, show welcome page
    showWelcomePage();
} else if (count($apps) === 1) {
    // Single app, serve it directly
    $app = $apps[0];
    serveApplication($app);
} else {
    // Multiple apps, show app selection
    showAppSelection($apps);
}

function checkAdminSetup($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM admin_users WHERE active = true");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getActiveApplications($db) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM applications 
            WHERE status = 'active' AND deployed = true 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function serveApplication($app) {
    $appPath = '/app/code/apps/' . $app['directory'];
    $indexFile = $appPath . '/index.php';
    
    if (file_exists($indexFile)) {
        // Change to app directory and include
        $originalDir = getcwd();
        chdir($appPath);
        
        // Set app context
        $_ENV['APP_NAME'] = $app['name'];
        $_ENV['APP_ID'] = $app['id'];
        
        try {
            include $indexFile;
        } finally {
            chdir($originalDir);
        }
    } else {
        http_response_code(503);
        echo '<h1>Application Unavailable</h1>';
        echo '<p>The application "' . htmlspecialchars($app['name']) . '" is not properly deployed.</p>';
        echo '<p><a href="/server-admin/">Go to Admin Panel</a></p>';
    }
}

function showDatabaseErrorPage($error) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error - PHP Git App Manager</title>
        <link rel="stylesheet" href="/assets/css/style.css">
        <style>
            .error-container { 
                max-width: 800px; 
                margin: 50px auto; 
                padding: 30px; 
                background: #f8f9fa; 
                border-left: 5px solid #dc3545; 
                border-radius: 5px;
            }
            .error-title { color: #dc3545; margin-bottom: 20px; }
            .error-details { 
                background: #fff; 
                padding: 15px; 
                border-radius: 3px; 
                margin: 15px 0; 
                font-family: monospace; 
                border: 1px solid #dee2e6;
                word-break: break-all;
            }
            .help-links { margin-top: 20px; }
            .help-links a { 
                display: inline-block; 
                margin-right: 15px; 
                color: #007bff; 
                text-decoration: none; 
                padding: 8px 15px; 
                border: 1px solid #007bff; 
                border-radius: 3px; 
                margin-bottom: 10px;
            }
            .help-links a:hover { background: #007bff; color: white; }
            .status-indicator { 
                display: inline-block; 
                width: 12px; 
                height: 12px; 
                border-radius: 50%; 
                background: #dc3545; 
                margin-right: 8px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1 class="error-title">
                <span class="status-indicator"></span>
                Database Connection Error
            </h1>
            <p><strong>The application cannot connect to the PostgreSQL database.</strong></p>
            
            <div class="error-details">
                <strong>Error Details:</strong><br>
                <?= htmlspecialchars($error) ?>
            </div>
            
            <h3>🔍 Possible Causes:</h3>
            <ul>
                <li><strong>PostgreSQL addon is still initializing</strong> (most common - wait 2-3 minutes)</li>
                <li>Cloudron environment variables not properly set</li>
                <li>Network connectivity issues between containers</li>
                <li>PostgreSQL service not running</li>
            </ul>
            
            <h3>🛠️ What to do:</h3>
            <ol>
                <li><strong>Wait 2-3 minutes</strong> for PostgreSQL to fully initialize after deployment</li>
                <li>Refresh this page to retry the connection</li>
                <li>Check the debug page for more detailed information</li>
                <li>If the issue persists for more than 5 minutes, check Cloudron logs</li>
            </ol>
            
            <div class="help-links">
                <a href="javascript:location.reload()">🔄 Retry Connection</a>
                <a href="/health.php">❤️ Health Check</a>
                <a href="/debug.php">🔧 Debug Information</a>
            </div>
            
            <p><em>💡 This page will automatically work once the database connection is established.</em></p>
            
            <script>
                // Auto-refresh every 30 seconds to retry connection
                setTimeout(function() {
                    location.reload();
                }, 30000);
                
                // Show countdown
                let countdown = 30;
                const countdownElement = document.createElement('p');
                countdownElement.style.textAlign = 'center';
                countdownElement.style.marginTop = '20px';
                countdownElement.style.color = '#666';
                document.querySelector('.error-container').appendChild(countdownElement);
                
                const updateCountdown = () => {
                    countdownElement.innerHTML = `🔄 Auto-refresh in ${countdown} seconds...`;
                    countdown--;
                    if (countdown >= 0) {
                        setTimeout(updateCountdown, 1000);
                    }
                };
                updateCountdown();
            </script>
        </div>
    </body>
    </html>
    <?php
}

function showWelcomePage() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PHP Git App Manager</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <div class="container">
            <div class="welcome-card">
                <h1>🚀 PHP Git App Manager</h1>
                <p>Welcome to your PHP Git Application Manager. No applications are currently deployed.</p>
                
                <div class="actions">
                    <a href="/server-admin/" class="btn btn-primary">
                        🔧 Go to Admin Panel
                    </a>
                </div>
                
                <div class="info">
                    <h3>Getting Started</h3>
                    <ol>
                        <li>Access the admin panel to configure your first Git repository</li>
                        <li>The system will clone and deploy your PHP application</li>
                        <li>Your app will be available right here!</li>
                    </ol>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function showAppSelection($apps) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Select Application</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <div class="container">
            <div class="app-selection">
                <h1>📱 Select Application</h1>
                <p>Multiple applications are available. Choose one to access:</p>
                
                <div class="app-grid">
                    <?php foreach ($apps as $app): ?>
                        <div class="app-card">
                            <h3><?= htmlspecialchars($app['name']) ?></h3>
                            <p><?= htmlspecialchars($app['description'] ?? 'No description available') ?></p>
                            <div class="app-meta">
                                <small>
                                    📦 <?= htmlspecialchars($app['repository']) ?><br>
                                    🌿 <?= htmlspecialchars($app['branch']) ?>
                                </small>
                            </div>
                            <a href="/apps/<?= htmlspecialchars($app['directory']) ?>/" class="btn btn-primary">
                                Open App
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="admin-link">
                    <a href="/server-admin/" class="btn btn-secondary">🔧 Admin Panel</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>




