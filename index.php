<?php
/**
 * PHP Git App Manager - Main Index
 * Manages deployed Git applications and provides entry point
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

// Initialize database connection
$db = getDbConnection();

// Check if admin is set up
$setupComplete = checkAdminSetup($db);

if (!$setupComplete) {
    // Redirect to admin setup if not configured
    header('Location: /server-admin/');
    exit;
}

// Get active applications
$apps = getActiveApplications($db);

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




