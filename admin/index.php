<?php
/**
 * Admin Panel - Main Entry Point
 * Handles authentication, initial setup, and routing
 */

require_once __DIR__ . '/../public/config/database.php';
require_once __DIR__ . '/../public/config/config.php';

startSecureSession();

// Initialize database and activity log
$db = getDbConnection();
initializeActivityLog($db);

// Check if this is the initial setup
$isInitialSetup = !hasAdminUsers($db);

// Handle different actions
$action = $_GET['action'] ?? 'dashboard';

if ($isInitialSetup && $action !== 'setup') {
    $action = 'setup';
}

// Authentication check (except for setup and login)
if (!$isInitialSetup && !in_array($action, ['setup', 'login', 'logout']) && !isLoggedIn()) {
    $action = 'login';
}

// Route to appropriate handler
switch ($action) {
    case 'setup':
        handleInitialSetup();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'dashboard':
        showDashboard();
        break;
    case 'applications':
        showApplications();
        break;
    case 'database':
        showDatabase();
        break;
    case 'settings':
        showSettings();
        break;
    case 'deploy':
        handleDeploy();
        break;
    default:
        showDashboard();
}

function hasAdminUsers($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE active = true");
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['login_time']) && 
           (time() - $_SESSION['login_time'] < ADMIN_SESSION_TIMEOUT);
}

function handleInitialSetup() {
    global $db;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = sanitizeInput($_POST['email'] ?? '');
        
        $errors = [];
        
        if (empty($username) || strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters long';
        }
        
        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO admin_users (username, password_hash, email, active, created_at) 
                    VALUES (?, ?, ?, true, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email]);
                
                logActivity($db, 'admin_setup', 'Initial admin user created');
                
                $_SESSION['setup_success'] = true;
                header('Location: /server-admin/?action=login');
                exit;
                
            } catch (PDOException $e) {
                $errors[] = 'Failed to create admin user: ' . $e->getMessage();
            }
        }
    }
    
    showSetupForm($errors ?? []);
}

function handleLogin() {
    global $db;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!checkRateLimit($db, 'login_attempt', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
            $error = 'Too many login attempts. Please wait before trying again.';
        } else {
            try {
                $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ? AND active = true");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $stmt = $db->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    logActivity($db, 'login_success', '', $user['id']);
                    
                    header('Location: /server-admin/');
                    exit;
                } else {
                    logActivity($db, 'login_attempt', 'Failed login: ' . $username);
                    $error = 'Invalid username or password';
                }
            } catch (PDOException $e) {
                $error = 'Login failed. Please try again.';
            }
        }
    }
    
    showLoginForm($error ?? null, $_SESSION['setup_success'] ?? false);
    unset($_SESSION['setup_success']);
}

function handleLogout() {
    global $db;
    
    if (isset($_SESSION['user_id'])) {
        logActivity($db, 'logout', '', $_SESSION['user_id']);
    }
    
    session_destroy();
    header('Location: /server-admin/?action=login');
    exit;
}

function showSetupForm($errors = []) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Initial Setup - PHP Git App Manager</title>
        <link rel="stylesheet" href="/assets/css/admin.css">
    </head>
    <body class="setup-page">
        <div class="setup-container">
            <div class="setup-card">
                <h1>🔧 Initial Setup</h1>
                <p>Welcome to PHP Git App Manager! Let's create your admin account.</p>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="setup-form">
                    <div class="form-group">
                        <label for="username">Admin Username *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Enter admin username">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="Enter email (optional)">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Minimum 6 characters">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-large">
                        Create Admin Account
                    </button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function showLoginForm($error = null, $setupSuccess = false) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - PHP Git App Manager</title>
        <link rel="stylesheet" href="/assets/css/admin.css">
    </head>
    <body class="login-page">
        <div class="login-container">
            <div class="login-card">
                <h1>🔐 Admin Login</h1>
                <p>PHP Git App Manager Admin Panel</p>
                
                <?php if ($setupSuccess): ?>
                    <div class="alert alert-success">
                        Admin account created successfully! Please log in.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Enter username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-large">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function showDashboard() {
    global $db;
    
    // Get statistics
    $stats = getDashboardStats($db);
    
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/dashboard.php';
    include __DIR__ . '/templates/footer.php';
}

function showApplications() {
    global $db;
    
    $applications = getAllApplications($db);
    
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/applications.php';
    include __DIR__ . '/templates/footer.php';
}

function showDatabase() {
    global $db;
    
    $schemas = getAllSchemas($db);
    $vectorSupport = checkVectorSupport($db);
    
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/database.php';
    include __DIR__ . '/templates/footer.php';
}

function showSettings() {
    global $db;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle settings update
        handleSettingsUpdate($db);
    }
    
    $settings = getSystemSettings($db);
    
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/settings.php';
    include __DIR__ . '/templates/footer.php';
}

// Helper functions
function getDashboardStats($db) {
    try {
        $stats = [];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM applications");
        $stats['total_apps'] = $stmt->fetch()['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM applications WHERE deployed = true");
        $stats['deployed_apps'] = $stmt->fetch()['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM deployments WHERE status = 'completed' AND created_at > NOW() - INTERVAL '24 hours'");
        $stats['recent_deployments'] = $stmt->fetch()['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM embeddings");
        $stats['embeddings'] = $stmt->fetch()['count'];
        
        return $stats;
    } catch (PDOException $e) {
        return ['total_apps' => 0, 'deployed_apps' => 0, 'recent_deployments' => 0, 'embeddings' => 0];
    }
}

function getAllApplications($db) {
    try {
        $stmt = $db->query("
            SELECT a.*, 
                   (SELECT COUNT(*) FROM deployments d WHERE d.application_id = a.id) as deployment_count,
                   (SELECT status FROM deployments d WHERE d.application_id = a.id ORDER BY started_at DESC LIMIT 1) as last_deployment_status
            FROM applications a 
            ORDER BY a.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getSystemSettings($db) {
    try {
        $stmt = $db->query("SELECT key, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

function handleSettingsUpdate($db) {
    // Implementation for settings update
    // This would handle POST data for settings updates
}

function handleDeploy() {
    // Implementation for deployment handling
    // This would be called via AJAX for deployment operations
}
?> 