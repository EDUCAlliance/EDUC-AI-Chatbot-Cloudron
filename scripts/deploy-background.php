#!/usr/bin/env php
<?php
/**
 * Background Deployment Script
 * Handles Git cloning and application deployment
 */

require_once __DIR__ . '/../public/config/database.php';
require_once __DIR__ . '/../public/config/config.php';

// Get deployment ID from command line
$deploymentId = $argv[1] ?? null;

if (!$deploymentId) {
    error_log("Deployment script: No deployment ID provided");
    exit(1);
}

try {
    $db = getDbConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get deployment and application data
    $stmt = $db->prepare("
        SELECT d.*, a.name, a.repository, a.branch, a.directory
        FROM deployments d
        JOIN applications a ON d.application_id = a.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deploymentId]);
    $deployment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deployment) {
        throw new Exception('Deployment not found');
    }
    
    // Update status to running
    updateDeploymentStatus($db, $deploymentId, 'running', "Starting deployment...\n");
    
    $appDirectory = "/app/code/apps/{$deployment['directory']}";
    $logFile = "{$appDirectory}/deployment.log";
    
    // Create app directory if it doesn't exist
    if (!is_dir($appDirectory)) {
        mkdir($appDirectory, 0755, true);
    }
    
    // Initialize log
    file_put_contents($logFile, "Deployment started at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    
    // Step 1: Clean existing files (except logs)
    appendLog($logFile, "Cleaning existing files...\n");
    $cleanCommand = "find {$appDirectory} -mindepth 1 -not -name '*.log' -delete 2>&1";
    $cleanOutput = shell_exec($cleanCommand);
    appendLog($logFile, "Clean output: {$cleanOutput}\n");
    
    // Step 2: Clone repository
    appendLog($logFile, "Cloning repository: {$deployment['repository']}\n");
    appendLog($logFile, "Branch: {$deployment['branch']}\n");
    
    $cloneCommand = "cd {$appDirectory} && git clone --depth 1 --branch {$deployment['branch']} {$deployment['repository']} temp 2>&1";
    $cloneOutput = shell_exec($cloneCommand);
    appendLog($logFile, "Clone output: {$cloneOutput}\n");
    
    if (!is_dir("{$appDirectory}/temp")) {
        throw new Exception("Failed to clone repository");
    }
    
    // Step 3: Move files from temp to main directory
    appendLog($logFile, "Moving files to application directory...\n");
    $moveCommand = "cd {$appDirectory}/temp && cp -r . ../ && cd .. && rm -rf temp 2>&1";
    $moveOutput = shell_exec($moveCommand);
    appendLog($logFile, "Move output: {$moveOutput}\n");
    
    // Step 4: Set permissions
    appendLog($logFile, "Setting file permissions...\n");
    $permCommand = "chown -R www-data:www-data {$appDirectory} && chmod -R 755 {$appDirectory} 2>&1";
    $permOutput = shell_exec($permCommand);
    appendLog($logFile, "Permissions output: {$permOutput}\n");
    
    // Step 5: Check for composer.json and install dependencies
    $composerFile = "{$appDirectory}/composer.json";
    if (file_exists($composerFile)) {
        appendLog($logFile, "Found composer.json, installing dependencies...\n");
        $composerCommand = "cd {$appDirectory} && composer install --no-dev --optimize-autoloader 2>&1";
        $composerOutput = shell_exec($composerCommand);
        appendLog($logFile, "Composer output: {$composerOutput}\n");
    }
    
    // Step 6: Check for package.json and install npm dependencies
    $packageFile = "{$appDirectory}/package.json";
    if (file_exists($packageFile)) {
        appendLog($logFile, "Found package.json, installing npm dependencies...\n");
        $npmCommand = "cd {$appDirectory} && npm install --production 2>&1";
        $npmOutput = shell_exec($npmCommand);
        appendLog($logFile, "NPM output: {$npmOutput}\n");
    }
    
    // Step 7: Create .htaccess for PHP apps if needed
    $htaccessFile = "{$appDirectory}/.htaccess";
    if (!file_exists($htaccessFile)) {
        appendLog($logFile, "Creating default .htaccess file...\n");
        $htaccessContent = "RewriteEngine On\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $htaccessContent .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
        file_put_contents($htaccessFile, $htaccessContent);
    }
    
    // Step 8: Create custom environment file for deployed app
    appendLog($logFile, "Setting up custom environment variables...\n");
    createCustomEnvFile($db, $appDirectory, $deployment, $logFile);
    
    // Step 9: Mark application as deployed
    $stmt = $db->prepare("UPDATE applications SET deployed = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$deployment['application_id']]);
    
    // Complete deployment
    appendLog($logFile, "Deployment completed successfully at " . date('Y-m-d H:i:s') . "\n");
    updateDeploymentStatus($db, $deploymentId, 'completed', file_get_contents($logFile));
    
    error_log("Deployment {$deploymentId} completed successfully");
    
} catch (Exception $e) {
    $errorMessage = "Deployment failed: " . $e->getMessage();
    error_log("Deployment {$deploymentId} failed: " . $e->getMessage());
    
    // Update deployment status to failed
    if (isset($db) && $db) {
        $logContent = '';
        if (isset($logFile) && file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
        }
        $logContent .= "\nERROR: {$errorMessage}\n";
        
        updateDeploymentStatus($db, $deploymentId, 'failed', $logContent);
    }
}

function updateDeploymentStatus($db, $deploymentId, $status, $log) {
    $completedAt = ($status === 'completed' || $status === 'failed') ? 'CURRENT_TIMESTAMP' : 'NULL';
    
    $stmt = $db->prepare("
        UPDATE deployments 
        SET status = ?, log = ?, completed_at = {$completedAt}, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$status, $log, $deploymentId]);
}

function appendLog($logFile, $message) {
    file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
}

function createCustomEnvFile($db, $appDirectory, $deployment, $logFile) {
    try {
        // Get custom environment variables from database
        $stmt = $db->query("SELECT var_key, var_value FROM custom_env_vars ORDER BY var_key");
        $customEnvVars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($customEnvVars)) {
            appendLog($logFile, "No custom environment variables defined.\n");
            return;
        }
        
        // Create custom-env.php file for the deployed application
        $envFile = "{$appDirectory}/custom-env.php";
        $envContent = "<?php\n";
        $envContent .= "/**\n";
        $envContent .= " * Custom Environment Variables\n";
        $envContent .= " * Auto-generated during deployment\n";
        $envContent .= " * DO NOT EDIT MANUALLY\n";
        $envContent .= " */\n\n";
        
        foreach ($customEnvVars as $envVar) {
            $key = $envVar['var_key'];
            $value = $envVar['var_value'];
            
            // Escape value for PHP string
            $escapedValue = addslashes($value);
            
            // Add to both $_ENV and putenv() for compatibility
            $envContent .= "// {$key}\n";
            $envContent .= "\$_ENV['{$key}'] = '{$escapedValue}';\n";
            $envContent .= "putenv('{$key}={$escapedValue}');\n\n";
        }
        
        $envContent .= "?>\n";
        
        file_put_contents($envFile, $envContent);
        appendLog($logFile, "Created custom environment file with " . count($customEnvVars) . " variables.\n");
        
        // Create auto-loader include file
        $includeFile = "{$appDirectory}/auto-include.php";
        $includeContent = "<?php\n";
        $includeContent .= "/**\n";
        $includeContent .= " * Auto-include for deployed applications\n";
        $includeContent .= " * Include this at the top of your application's index.php\n";
        $includeContent .= " */\n\n";
        $includeContent .= "// Load custom environment variables\n";
        $includeContent .= "if (file_exists(__DIR__ . '/custom-env.php')) {\n";
        $includeContent .= "    require_once __DIR__ . '/custom-env.php';\n";
        $includeContent .= "}\n\n";
        $includeContent .= "// Set application context\n";
        $includeContent .= "\$_ENV['APP_NAME'] = '" . addslashes($deployment['name']) . "';\n";
        $includeContent .= "\$_ENV['APP_ID'] = '{$deployment['application_id']}';\n";
        $includeContent .= "\$_ENV['APP_DIRECTORY'] = '" . addslashes($deployment['directory']) . "';\n";
        $includeContent .= "putenv('APP_NAME=" . addslashes($deployment['name']) . "');\n";
        $includeContent .= "putenv('APP_ID={$deployment['application_id']}');\n";
        $includeContent .= "putenv('APP_DIRECTORY=" . addslashes($deployment['directory']) . "');\n\n";
        $includeContent .= "?>\n";
        
        file_put_contents($includeFile, $includeContent);
        appendLog($logFile, "Created auto-include file for easy integration.\n");
        
        // Update the app's index.php to auto-include custom environment
        $indexFile = "{$appDirectory}/index.php";
        if (file_exists($indexFile)) {
            $indexContent = file_get_contents($indexFile);
            
            // Check if auto-include is already present
            if (strpos($indexContent, "auto-include.php") === false) {
                // Add auto-include at the beginning after opening PHP tag
                $autoIncludeStatement = "\n// Auto-include custom environment variables\nrequire_once __DIR__ . '/auto-include.php';\n";
                
                if (strpos($indexContent, '<?php') === 0) {
                    $indexContent = str_replace('<?php', '<?php' . $autoIncludeStatement, $indexContent);
                } else {
                    $indexContent = '<?php' . $autoIncludeStatement . "\n?>" . $indexContent;
                }
                
                file_put_contents($indexFile, $indexContent);
                appendLog($logFile, "Updated index.php to auto-load custom environment variables.\n");
            } else {
                appendLog($logFile, "index.php already includes auto-include.php.\n");
            }
        }
        
    } catch (Exception $e) {
        appendLog($logFile, "Error creating custom environment file: " . $e->getMessage() . "\n");
        error_log("Error creating custom environment file: " . $e->getMessage());
    }
}
?> 