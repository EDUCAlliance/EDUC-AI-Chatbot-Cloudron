<?php
/**
 * AJAX Endpoint - Create New Application
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
    $db = getDbConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get POST data
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $repository = sanitizeInput($_POST['repository'] ?? '');
    $branch = sanitizeInput($_POST['branch'] ?? 'main');
    $directory = sanitizeInput($_POST['directory'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Basic validation
    if (empty($name)) {
        throw new Exception('Application name is required');
    }
    
    if (empty($repository)) {
        throw new Exception('Repository URL is required');
    }
    
    // Validate repository URL
    if (!filter_var($repository, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid repository URL');
    }
    
    // Generate directory name if not provided
    if (empty($directory)) {
        $directory = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
        $directory = trim($directory, '-');
    }
    
    // Validate directory name
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $directory)) {
        throw new Exception('Directory name can only contain letters, numbers, hyphens, and underscores');
    }
    
    // Check if directory name is already taken
    $stmt = $db->prepare("SELECT id FROM applications WHERE directory = ?");
    $stmt->execute([$directory]);
    if ($stmt->fetch()) {
        throw new Exception('Directory name is already taken');
    }
    
    // Check if repository URL is already used
    $stmt = $db->prepare("SELECT id FROM applications WHERE repository = ?");
    $stmt->execute([$repository]);
    if ($stmt->fetch()) {
        throw new Exception('Repository URL is already registered');
    }
    
    // Insert new application
    $stmt = $db->prepare("
        INSERT INTO applications (name, description, repository, branch, directory, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        RETURNING id
    ");
    $stmt->execute([$name, $description, $repository, $branch, $directory]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $appId = $result['id'];
    
    // Log the activity
    logActivity($db, 'app_created', "Created application: {$name}", $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Application created successfully',
        'app_id' => $appId,
        'directory' => $directory
    ]);
    
} catch (Exception $e) {
    error_log("Create application error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 