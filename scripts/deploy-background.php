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
    // Log script start
    error_log("Background deployment script started for deployment ID: {$deploymentId}");
    
    $db = getDbConnection();
    
    if (!$db) {
        error_log("Background deployment script: Database connection failed");
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
        error_log("Background deployment script: Deployment {$deploymentId} not found in database");
        throw new Exception('Deployment not found');
    }
    
    error_log("Background deployment script: Found deployment for app '{$deployment['name']}' - {$deployment['repository']}");
    
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
        
        // Try different composer paths
        $composerPaths = ['/usr/local/bin/composer', '/usr/bin/composer', 'composer'];
        $composerPath = null;
        
        foreach ($composerPaths as $path) {
            if (shell_exec("which {$path}") || file_exists($path)) {
                $composerPath = $path;
                break;
            }
        }
        
        if ($composerPath) {
            appendLog($logFile, "Using composer at: {$composerPath}\n");
            
            // Set proper environment variables for Composer
            $homeDir = "/tmp/composer_home";
            if (!is_dir($homeDir)) {
                mkdir($homeDir, 0755, true);
            }
            
            $composerCommand = "cd {$appDirectory} && HOME={$homeDir} COMPOSER_HOME={$homeDir} {$composerPath} install --no-dev --optimize-autoloader --no-interaction 2>&1";
            $composerOutput = shell_exec($composerCommand);
            appendLog($logFile, "Composer output: {$composerOutput}\n");
            
            // Check if vendor directory was created
            if (is_dir("{$appDirectory}/vendor")) {
                appendLog($logFile, "✅ Composer dependencies installed successfully\n");
            } else {
                appendLog($logFile, "❌ Composer installation failed - vendor directory not created\n");
                // Try alternative installation with proper HOME set
                $fallbackCommand = "cd {$appDirectory} && HOME={$homeDir} COMPOSER_HOME={$homeDir} curl -sS https://getcomposer.org/installer | php && HOME={$homeDir} COMPOSER_HOME={$homeDir} php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1";
                $fallbackOutput = shell_exec($fallbackCommand);
                appendLog($logFile, "Fallback composer output: {$fallbackOutput}\n");
                
                // Check again if vendor directory was created
                if (is_dir("{$appDirectory}/vendor")) {
                    appendLog($logFile, "✅ Composer dependencies installed successfully via fallback\n");
                }
            }
        } else {
            appendLog($logFile, "❌ Composer not found, attempting to install...\n");
            
            // Set proper environment variables for fallback installation
            $homeDir = "/tmp/composer_home";
            if (!is_dir($homeDir)) {
                mkdir($homeDir, 0755, true);
            }
            
            $installCommand = "cd {$appDirectory} && HOME={$homeDir} COMPOSER_HOME={$homeDir} curl -sS https://getcomposer.org/installer | php && HOME={$homeDir} COMPOSER_HOME={$homeDir} php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1";
            $installOutput = shell_exec($installCommand);
            appendLog($logFile, "Composer install output: {$installOutput}\n");
            
            // Check if vendor directory was created
            if (is_dir("{$appDirectory}/vendor")) {
                appendLog($logFile, "✅ Composer dependencies installed successfully\n");
            }
        }
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
        $htaccessContent = "# Generated by PHP Git App Manager\n";
        $htaccessContent .= "RewriteEngine On\n\n";
        
        // Security: Block access to sensitive directories and files
        $htaccessContent .= "# Block access to sensitive directories\n";
        $htaccessContent .= "RedirectMatch 404 /\\.git\n";
        $htaccessContent .= "RedirectMatch 404 /vendor\n";
        $htaccessContent .= "RedirectMatch 404 /node_modules\n";
        $htaccessContent .= "RedirectMatch 404 /\\.env\n";
        $htaccessContent .= "RedirectMatch 404 /deployment\\.log\n";
        $htaccessContent .= "RedirectMatch 404 /auto-include\\.php\n";
        $htaccessContent .= "RedirectMatch 404 /custom-env\\.php\n";
        $htaccessContent .= "RedirectMatch 404 /composer\\.(json|lock)\n";
        $htaccessContent .= "RedirectMatch 404 /package(-lock)?\\.json\n\n";
        
        // URL rewriting for PHP apps
        $htaccessContent .= "# URL Rewriting\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $htaccessContent .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
        
        file_put_contents($htaccessFile, $htaccessContent);
    }
    
    // Step 7.5: Create index.php if it doesn't exist (for webhook/API-only apps)
    $indexFile = "{$appDirectory}/index.php";
    if (!file_exists($indexFile)) {
        appendLog($logFile, "No index.php found, creating application landing page...\n");
        createAppLandingPage($appDirectory, $deployment, $logFile);
    }
    
    // Step 8: Create custom environment file for deployed app
    appendLog($logFile, "Setting up custom environment variables...\n");
    createCustomEnvFile($db, $appDirectory, $deployment, $logFile);
    
    // Step 9: Mark application as deployed
    $stmt = $db->prepare("UPDATE applications SET deployed = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$deployment['application_id']]);
    
    // FINAL ULTRA DEEP VERIFICATION
    appendLog($logFile, "\n🔍 FINAL VERIFICATION - Complete directory listing:\n");
    $allFiles = scandir($appDirectory);
    foreach ($allFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        $fullPath = $appDirectory . '/' . $file;
        $isDir = is_dir($fullPath);
        $size = $isDir ? 0 : filesize($fullPath);
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        $type = $isDir ? 'DIR' : 'FILE';
        appendLog($logFile, "📄 {$type}: {$file} ({$size} bytes, perms: {$perms})\n");
    }
    
    // Specific check for our critical files
    $criticalFiles = ['custom-env.php', 'auto-include.php', '.htaccess', 'index.php'];
    appendLog($logFile, "\n🔍 Critical files check:\n");
    foreach ($criticalFiles as $file) {
        $fullPath = $appDirectory . '/' . $file;
        $exists = file_exists($fullPath);
        $readable = $exists ? is_readable($fullPath) : false;
        $status = $exists ? ($readable ? 'EXISTS+READABLE' : 'EXISTS+UNREADABLE') : 'MISSING';
        appendLog($logFile, "📋 {$file}: {$status}\n");
    }
    
    // Complete deployment
    appendLog($logFile, "\nDeployment completed successfully at " . date('Y-m-d H:i:s') . "\n");
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
        SET status = ?, log = ?, completed_at = {$completedAt}
        WHERE id = ?
    ");
    $stmt->execute([$status, $log, $deploymentId]);
}

function appendLog($logFile, $message) {
    file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
}

function createCustomEnvFile($db, $appDirectory, $deployment, $logFile) {
    try {
        appendLog($logFile, "Checking for custom environment variables...\n");
        
        // Get custom environment variables from database
        $stmt = $db->query("SELECT var_key, var_value, is_sensitive FROM custom_env_vars ORDER BY var_key");
        $customEnvVars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        appendLog($logFile, "Found " . count($customEnvVars) . " custom environment variables.\n");
        
        if (empty($customEnvVars)) {
            appendLog($logFile, "No custom environment variables defined, skipping custom-env.php creation.\n");
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
            $isSensitive = $envVar['is_sensitive'] ?? false;
            
            // Log variable being added (hide sensitive values)
            $logValue = $isSensitive ? '[SENSITIVE]' : $value;
            appendLog($logFile, "Adding environment variable: {$key} = {$logValue}\n");
            
            // Escape value for PHP string
            $escapedValue = addslashes($value);
            
            // Add to both $_ENV and putenv() for compatibility
            $envContent .= "// {$key}" . ($isSensitive ? ' (sensitive)' : '') . "\n";
            $envContent .= "\$_ENV['{$key}'] = '{$escapedValue}';\n";
            $envContent .= "putenv('{$key}={$escapedValue}');\n\n";
        }
        
        $envContent .= "?>\n";
        
        // ULTRA DEEP FILE CREATION DEBUGGING
        appendLog($logFile, "🔍 ULTRA DEBUG: About to create custom environment file\n");
        appendLog($logFile, "📂 Target directory: {$appDirectory}\n");
        appendLog($logFile, "📂 Directory exists: " . (is_dir($appDirectory) ? 'YES' : 'NO') . "\n");
        appendLog($logFile, "📂 Directory writable: " . (is_writable($appDirectory) ? 'YES' : 'NO') . "\n");
        appendLog($logFile, "📄 Target file: {$envFile}\n");
        appendLog($logFile, "📄 File content length: " . strlen($envContent) . " bytes\n");
        
        // Write the custom environment file
        $writeResult = file_put_contents($envFile, $envContent);
        if ($writeResult === false) {
            appendLog($logFile, "❌ Failed to write custom environment file: {$envFile}\n");
            appendLog($logFile, "🔍 Last error: " . error_get_last()['message'] ?? 'No error details' . "\n");
            throw new Exception("Failed to write custom environment file");
        } else {
            appendLog($logFile, "✅ file_put_contents returned: {$writeResult} bytes\n");
            
            // IMMEDIATE verification
            if (file_exists($envFile)) {
                $actualSize = filesize($envFile);
                $actualPerms = substr(sprintf('%o', fileperms($envFile)), -4);
                $actualOwner = fileowner($envFile);
                $actualGroup = filegroup($envFile);
                
                appendLog($logFile, "✅ File verification: EXISTS\n");
                appendLog($logFile, "📊 Actual size: {$actualSize} bytes\n");
                appendLog($logFile, "🔐 Permissions: {$actualPerms}\n");
                appendLog($logFile, "👤 Owner: {$actualOwner}, Group: {$actualGroup}\n");
                appendLog($logFile, "📄 Readable: " . (is_readable($envFile) ? 'YES' : 'NO') . "\n");
                
                // Try to read it back
                $readBack = file_get_contents($envFile);
                if ($readBack === false) {
                    appendLog($logFile, "⚠️ Cannot read file back after creation\n");
                } else {
                    appendLog($logFile, "✅ File read back successfully: " . strlen($readBack) . " bytes\n");
                }
            } else {
                appendLog($logFile, "❌ CRITICAL: File does not exist immediately after creation!\n");
                appendLog($logFile, "🔍 Current working directory: " . getcwd() . "\n");
                appendLog($logFile, "🔍 Absolute path check: " . realpath($envFile) . "\n");
            }
        }
        
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
        
        // Write the auto-include file
        $includeResult = file_put_contents($includeFile, $includeContent);
        if ($includeResult === false) {
            appendLog($logFile, "❌ Failed to write auto-include file: {$includeFile}\n");
            throw new Exception("Failed to write auto-include file");
        } else {
            appendLog($logFile, "✅ Created auto-include file: {$includeFile} (" . strlen($includeContent) . " bytes)\n");
        }
        
        // Update the app's index.php to auto-include custom environment
        $indexFile = "{$appDirectory}/index.php";
        if (file_exists($indexFile)) {
            appendLog($logFile, "🔍 ULTRA DEBUG: Analyzing index.php for auto-include integration\n");
            
            $indexContent = file_get_contents($indexFile);
            $fileSize = strlen($indexContent);
            $isAutoGenerated = strpos($indexContent, 'Auto-generated landing page') !== false;
            
            appendLog($logFile, "📄 index.php analysis:\n");
            appendLog($logFile, "   📊 File size: {$fileSize} bytes\n");
            appendLog($logFile, "   🏭 Auto-generated: " . ($isAutoGenerated ? 'YES' : 'NO') . "\n");
            
            // Check if auto-include is already present
            $hasAutoInclude = strpos($indexContent, "auto-include.php") !== false;
            $hasRequireOnce = strpos($indexContent, "require_once __DIR__ . '/auto-include.php'") !== false;
            
            appendLog($logFile, "   🔗 Contains 'auto-include.php': " . ($hasAutoInclude ? 'YES' : 'NO') . "\n");
            appendLog($logFile, "   ✅ Has proper require_once: " . ($hasRequireOnce ? 'YES' : 'NO') . "\n");
            
            if (!$hasAutoInclude) {
                // Add auto-include at the beginning after opening PHP tag
                $autoIncludeStatement = "\n// Auto-include custom environment variables\nrequire_once __DIR__ . '/auto-include.php';\n";
                
                appendLog($logFile, "🔧 Adding auto-include to existing index.php...\n");
                
                if (strpos($indexContent, '<?php') === 0) {
                    $indexContent = str_replace('<?php', '<?php' . $autoIncludeStatement, $indexContent);
                } else {
                    $indexContent = '<?php' . $autoIncludeStatement . "\n?>" . $indexContent;
                }
                
                $updateResult = file_put_contents($indexFile, $indexContent);
                if ($updateResult === false) {
                    appendLog($logFile, "❌ Failed to update index.php with auto-include\n");
                } else {
                    $newSize = filesize($indexFile);
                    appendLog($logFile, "✅ Successfully added auto-include to index.php\n");
                    appendLog($logFile, "📊 Updated file size: {$newSize} bytes\n");
                }
            } else {
                if ($isAutoGenerated) {
                    appendLog($logFile, "✅ Auto-include already built into auto-generated landing page\n");
                } else {
                    appendLog($logFile, "ℹ️ index.php already includes auto-include.php (user-created file)\n");
                }
            }
        } else {
            appendLog($logFile, "⚠️ index.php not found - this should not happen after landing page creation\n");
        }
        
    } catch (Exception $e) {
        appendLog($logFile, "Error creating custom environment file: " . $e->getMessage() . "\n");
        error_log("Error creating custom environment file: " . $e->getMessage());
    }
}

function createAppLandingPage($appDirectory, $deployment, $logFile) {
    try {
        $indexFile = "{$appDirectory}/index.php";
        
        // Detect application type
        $appType = detectApplicationType($appDirectory);
        
        $indexContent = "<?php\n";
        $indexContent .= "/**\n";
        $indexContent .= " * Auto-generated landing page for: {$deployment['name']}\n";
        $indexContent .= " * Generated by PHP Git App Manager\n";
        $indexContent .= " */\n\n";
        
        // Include auto-generated environment variables
        $indexContent .= "// Load auto-generated environment variables\n";
        $indexContent .= "if (file_exists(__DIR__ . '/auto-include.php')) {\n";
        $indexContent .= "    require_once __DIR__ . '/auto-include.php';\n";
        $indexContent .= "}\n\n";
        
        $indexContent .= "?>\n";
        $indexContent .= "<!DOCTYPE html>\n";
        $indexContent .= "<html lang=\"en\">\n";
        $indexContent .= "<head>\n";
        $indexContent .= "    <meta charset=\"UTF-8\">\n";
        $indexContent .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $indexContent .= "    <title>" . htmlspecialchars($deployment['name']) . "</title>\n";
        $indexContent .= "    <style>\n";
        $indexContent .= "        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 40px; background: #f5f7fa; }\n";
        $indexContent .= "        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }\n";
        $indexContent .= "        .header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef; }\n";
        $indexContent .= "        .app-title { color: #2c3e50; margin: 0 0 10px 0; font-size: 2.5em; font-weight: 300; }\n";
        $indexContent .= "        .app-subtitle { color: #7f8c8d; font-size: 1.1em; }\n";
        $indexContent .= "        .section { margin: 30px 0; }\n";
        $indexContent .= "        .section-title { color: #34495e; font-size: 1.3em; margin-bottom: 15px; font-weight: 600; }\n";
        $indexContent .= "        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }\n";
        $indexContent .= "        .info-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #3498db; }\n";
        $indexContent .= "        .info-label { font-weight: 600; color: #2c3e50; margin-bottom: 5px; }\n";
        $indexContent .= "        .info-value { color: #7f8c8d; word-break: break-all; }\n";
        $indexContent .= "        .file-list { background: #f8f9fa; padding: 15px; border-radius: 6px; max-height: 300px; overflow-y: auto; }\n";
        $indexContent .= "        .file-item { padding: 5px 0; color: #495057; }\n";
        $indexContent .= "        .file-item a { color: #007bff; text-decoration: none; }\n";
        $indexContent .= "        .file-item a:hover { text-decoration: underline; }\n";
        $indexContent .= "        .status-badge { background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.9em; }\n";
        $indexContent .= "        .admin-link { text-align: center; margin-top: 30px; }\n";
        $indexContent .= "        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; transition: background 0.2s; }\n";
        $indexContent .= "        .btn:hover { background: #0056b3; }\n";
        $indexContent .= "        .app-type { background: #e7f3ff; color: #004085; padding: 8px 16px; border-radius: 16px; font-size: 0.9em; display: inline-block; margin-bottom: 20px; }\n";
        $indexContent .= "    </style>\n";
        $indexContent .= "</head>\n";
        $indexContent .= "<body>\n";
        $indexContent .= "    <div class=\"container\">\n";
        $indexContent .= "        <div class=\"header\">\n";
        $indexContent .= "            <h1 class=\"app-title\">" . htmlspecialchars($deployment['name']) . "</h1>\n";
        $indexContent .= "            <p class=\"app-subtitle\">Deployed PHP Application</p>\n";
        $indexContent .= "            <div class=\"app-type\">📦 {$appType}</div>\n";
        $indexContent .= "            <span class=\"status-badge\">✅ Successfully Deployed</span>\n";
        $indexContent .= "        </div>\n\n";
        
        $indexContent .= "        <div class=\"section\">\n";
        $indexContent .= "            <h2 class=\"section-title\">📋 Application Information</h2>\n";
        $indexContent .= "            <div class=\"info-grid\">\n";
        $indexContent .= "                <div class=\"info-card\">\n";
        $indexContent .= "                    <div class=\"info-label\">Repository</div>\n";
        $indexContent .= "                    <div class=\"info-value\">" . htmlspecialchars($deployment['repository']) . "</div>\n";
        $indexContent .= "                </div>\n";
        $indexContent .= "                <div class=\"info-card\">\n";
        $indexContent .= "                    <div class=\"info-label\">Branch</div>\n";
        $indexContent .= "                    <div class=\"info-value\">" . htmlspecialchars($deployment['branch']) . "</div>\n";
        $indexContent .= "                </div>\n";
        $indexContent .= "                <div class=\"info-card\">\n";
        $indexContent .= "                    <div class=\"info-label\">Directory</div>\n";
        $indexContent .= "                    <div class=\"info-value\">/apps/" . htmlspecialchars($deployment['directory']) . "/</div>\n";
        $indexContent .= "                </div>\n";
        $indexContent .= "                <div class=\"info-card\">\n";
        $indexContent .= "                    <div class=\"info-label\">Deployed</div>\n";
        $indexContent .= "                    <div class=\"info-value\"><?= date('Y-m-d H:i:s') ?></div>\n";
        $indexContent .= "                </div>\n";
        $indexContent .= "            </div>\n";
        $indexContent .= "        </div>\n\n";
        
        // Add application files section
        $indexContent .= "        <div class=\"section\">\n";
        $indexContent .= "            <h2 class=\"section-title\">📁 Application Files</h2>\n";
        $indexContent .= "            <div class=\"file-list\">\n";
        $indexContent .= "                <?php\n";
        $indexContent .= "                \$files = scandir(__DIR__);\n";
        $indexContent .= "                \$hiddenDirs = ['.git', 'vendor', 'node_modules', '.env', '.htaccess', 'deployment.log', 'auto-include.php', 'custom-env.php'];\n";
        $indexContent .= "                \$allowedFiles = ['README.md', 'DEPLOYMENT-GUIDE.md', 'LICENSE', 'composer.json', 'package.json'];\n";
        $indexContent .= "                foreach (\$files as \$file) {\n";
        $indexContent .= "                    if (\$file === '.' || \$file === '..' || \$file === 'index.php') continue;\n";
        $indexContent .= "                    if (in_array(\$file, \$hiddenDirs)) continue;\n";
        $indexContent .= "                    if (str_starts_with(\$file, '.') && !in_array(\$file, \$allowedFiles)) continue;\n";
        $indexContent .= "                    \$isDir = is_dir(__DIR__ . '/' . \$file);\n";
        $indexContent .= "                    \$icon = \$isDir ? '📁' : '📄';\n";
        $indexContent .= "                    echo '<div class=\"file-item\">';\n";
        $indexContent .= "                    if (\$isDir) {\n";
        $indexContent .= "                        echo \$icon . ' <a href=\"' . htmlspecialchars(\$file) . '/\">' . htmlspecialchars(\$file) . '/</a>';\n";
        $indexContent .= "                    } else {\n";
        $indexContent .= "                        echo \$icon . ' ' . htmlspecialchars(\$file);\n";
        $indexContent .= "                    }\n";
        $indexContent .= "                    echo '</div>';\n";
        $indexContent .= "                }\n";
        $indexContent .= "                ?>\n";
        $indexContent .= "            </div>\n";
        $indexContent .= "        </div>\n\n";
        
        // Add quick access section for important files
        $indexContent .= "        <div class=\"section\">\n";
        $indexContent .= "            <h2 class=\"section-title\">🔗 Quick Access</h2>\n";
        $indexContent .= "            <div style=\"display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;\">\n";
        $indexContent .= "                <?php\n";
        $indexContent .= "                \$quickAccess = [\n";
        $indexContent .= "                    'admin/' => ['🎛️ Admin Panel', 'Application admin interface'],\n";
        $indexContent .= "                    'connect.php' => ['🔌 Webhook Endpoint', 'API connection point'],\n";
        $indexContent .= "                    'src/' => ['📂 Source Code', 'Application source files'],\n";
        $indexContent .= "                    'README.md' => ['📖 Documentation', 'Project documentation']\n";
        $indexContent .= "                ];\n";
        $indexContent .= "                foreach (\$quickAccess as \$path => [\$name, \$desc]) {\n";
        $indexContent .= "                    if (file_exists(__DIR__ . '/' . \$path)) {\n";
        $indexContent .= "                        echo '<div style=\"background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;\">';\n";
        $indexContent .= "                        echo '<div style=\"font-weight: 600; margin-bottom: 5px;\"><a href=\"' . htmlspecialchars(\$path) . '\" style=\"color: #28a745; text-decoration: none;\">' . \$name . '</a></div>';\n";
        $indexContent .= "                        echo '<div style=\"color: #6c757d; font-size: 0.9em;\">' . \$desc . '</div>';\n";
        $indexContent .= "                        echo '</div>';\n";
        $indexContent .= "                    }\n";
        $indexContent .= "                }\n";
        $indexContent .= "                ?>\n";
        $indexContent .= "            </div>\n";
        $indexContent .= "        </div>\n\n";
        
        // Add environment info
        $indexContent .= "        <div class=\"section\">\n";
        $indexContent .= "            <h2 class=\"section-title\">🔧 Environment</h2>\n";
        $indexContent .= "            <div class=\"info-grid\">\n";
        $indexContent .= "                <div class=\"info-card\">\n";
        $indexContent .= "                    <div class=\"info-label\">PHP Version</div>\n";
        $indexContent .= "                    <div class=\"info-value\"><?= phpversion() ?></div>\n";
        $indexContent .= "                </div>\n";
        $indexContent .= "                <div class=\"info-card\">\n";
        $indexContent .= "                    <div class=\"info-label\">Application ID</div>\n";
        $indexContent .= "                    <div class=\"info-value\"><?= \$_ENV['APP_ID'] ?? 'Not Set' ?></div>\n";
        $indexContent .= "                </div>\n";
        $indexContent .= "            </div>\n";
        $indexContent .= "        </div>\n\n";
        
        $indexContent .= "        <div class=\"admin-link\">\n";
        $indexContent .= "            <a href=\"/server-admin/\" class=\"btn\">🔧 Admin Panel</a>\n";
        $indexContent .= "        </div>\n";
        $indexContent .= "    </div>\n";
        $indexContent .= "</body>\n";
        $indexContent .= "</html>\n";
        
        file_put_contents($indexFile, $indexContent);
        appendLog($logFile, "Created landing page at index.php\n");
        
    } catch (Exception $e) {
        appendLog($logFile, "Error creating landing page: " . $e->getMessage() . "\n");
        error_log("Error creating landing page: " . $e->getMessage());
    }
}

function detectApplicationType($appDirectory) {
    // Check for common application patterns
    if (file_exists("{$appDirectory}/composer.json")) {
        $composerData = json_decode(file_get_contents("{$appDirectory}/composer.json"), true);
        if (isset($composerData['type'])) {
            return "Composer Package ({$composerData['type']})";
        }
        return "PHP Application with Composer";
    }
    
    if (file_exists("{$appDirectory}/connect.php")) {
        return "Webhook/API Application";
    }
    
    if (file_exists("{$appDirectory}/admin") && is_dir("{$appDirectory}/admin")) {
        return "Application with Admin Panel";
    }
    
    if (file_exists("{$appDirectory}/src") && is_dir("{$appDirectory}/src")) {
        return "Structured PHP Application";
    }
    
    return "PHP Application";
}
?> 