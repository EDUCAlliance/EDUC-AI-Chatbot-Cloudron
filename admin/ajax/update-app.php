<?php
/**
 * AJAX Endpoint - Update Application
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
    $appId = $_POST['app_id'] ?? '';
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $repository = sanitizeInput($_POST['repository'] ?? '');
    $branch = sanitizeInput($_POST['branch'] ?? 'main');
    $directory = sanitizeInput($_POST['directory'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Basic validation
    if (empty($appId) || !is_numeric($appId)) {
        throw new Exception('Invalid application ID');
    }
    
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
    
    // Check if application exists
    $stmt = $db->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$appId]);
    $existingApp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingApp) {
        throw new Exception('Application not found');
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
    
    // Check if directory name is already taken by another app
    if ($directory !== $existingApp['directory']) {
        $stmt = $db->prepare("SELECT id FROM applications WHERE directory = ? AND id != ?");
        $stmt->execute([$directory, $appId]);
        if ($stmt->fetch()) {
            throw new Exception('Directory name is already taken');
        }
    }
    
    // Check if repository URL is already used by another app
    if ($repository !== $existingApp['repository']) {
        $stmt = $db->prepare("SELECT id FROM applications WHERE repository = ? AND id != ?");
        $stmt->execute([$repository, $appId]);
        if ($stmt->fetch()) {
            throw new Exception('Repository URL is already registered');
        }
    }
    
    // Update application
    $stmt = $db->prepare("
        UPDATE applications 
        SET name = ?, description = ?, repository = ?, branch = ?, directory = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$name, $description, $repository, $branch, $directory, $appId]);
    
    // If directory changed, we need to move/rename the physical directory
    $directoryChanged = ($directory !== $existingApp['directory']);
    $repoChanged = ($repository !== $existingApp['repository']);
    $branchChanged = ($branch !== $existingApp['branch']);
    
    // Log the activity
    $changes = [];
    if ($name !== $existingApp['name']) $changes[] = 'name';
    if ($description !== $existingApp['description']) $changes[] = 'description';
    if ($repoChanged) $changes[] = 'repository';
    if ($branchChanged) $changes[] = 'branch';
    if ($directoryChanged) $changes[] = 'directory';
    
    if (!empty($changes)) {
        logActivity($db, 'app_updated', "Updated application: {$name} (" . implode(', ', $changes) . ")", $_SESSION['user_id']);
    }
    
    // Mark as not deployed if critical changes were made
    if ($repoChanged || $branchChanged || $directoryChanged) {
        $stmt = $db->prepare("UPDATE applications SET deployed = false WHERE id = ?");
        $stmt->execute([$appId]);
        
        // Optionally clean up old directory if it changed
        if ($directoryChanged) {
            $oldPath = "/app/code/apps/{$existingApp['directory']}";
            $newPath = "/app/code/apps/{$directory}";
            
            if (is_dir($oldPath) && !is_dir($newPath)) {
                rename($oldPath, $newPath);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Application updated successfully',
        'directory_changed' => $directoryChanged,
        'needs_redeployment' => ($repoChanged || $branchChanged || $directoryChanged)
    ]);
    
} catch (Exception $e) {
    error_log("Update application error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 