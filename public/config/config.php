<?php
/**
 * Application Configuration
 */

// Security settings
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes

// Application settings
define('MAX_REPO_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_BRANCHES', ['main', 'master', 'develop', 'staging', 'production']);
define('DEPLOYMENT_TIMEOUT', 300); // 5 minutes

// Vector embedding settings
define('DEFAULT_VECTOR_DIMENSION', 1536);
define('MAX_EMBEDDING_LENGTH', 8000);

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isHttps());
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
}

function isHttps() {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateGitUrl($url) {
    // Basic validation for Git URLs
    $patterns = [
        '/^https:\/\/github\.com\/[\w\-\.]+\/[\w\-\.]+(?:\.git)?$/',
        '/^https:\/\/gitlab\.com\/[\w\-\.]+\/[\w\-\.]+(?:\.git)?$/',
        '/^https:\/\/bitbucket\.org\/[\w\-\.]+\/[\w\-\.]+(?:\.git)?$/',
        '/^https:\/\/[a-zA-Z0-9\-\.]+\/[\w\-\.\/]+(?:\.git)?$/' // Generic Git hosting
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }
    
    return false;
}

function validateBranchName($branch) {
    // Git branch name validation
    return preg_match('/^[a-zA-Z0-9\/_\-\.]+$/', $branch) && 
           strlen($branch) <= 100 &&
           !str_starts_with($branch, '.') &&
           !str_ends_with($branch, '.') &&
           !str_contains($branch, '..');
}

function generateAppDirectory($name) {
    $clean = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $name);
    $clean = strtolower(trim($clean, '_'));
    return $clean . '_' . substr(md5($name . time()), 0, 8);
}

function isValidAppDirectory($directory) {
    return preg_match('/^[a-zA-Z0-9_\-]+$/', $directory) && 
           strlen($directory) <= 50;
}

function logActivity($db, $action, $details = '', $userId = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function checkRateLimit($db, $action, $limit = 10, $window = 300) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM activity_log 
            WHERE ip_address = ? 
            AND action = ? 
            AND created_at > NOW() - INTERVAL ? SECOND
        ");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', $action, $window]);
        $result = $stmt->fetch();
        
        return ($result['count'] ?? 0) < $limit;
    } catch (PDOException $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Allow on error
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Initialize activity log table if needed
function initializeActivityLog($db) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS activity_log (
                id SERIAL PRIMARY KEY,
                user_id INTEGER,
                action VARCHAR(255) NOT NULL,
                description TEXT,
                ip_address INET,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_log_action ON activity_log(action)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_log_ip ON activity_log(ip_address)");
        
    } catch (PDOException $e) {
        error_log("Failed to initialize activity log: " . $e->getMessage());
    }
}
?> 