<?php
/**
 * Settings Template - System Configuration
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $error = null;
    
    try {
        // Handle custom environment variable operations
        if (isset($_POST['env_action'])) {
            switch ($_POST['env_action']) {
                case 'add':
                    $key = sanitizeInput($_POST['env_key'] ?? '');
                    $value = $_POST['env_value'] ?? '';
                    $description = sanitizeInput($_POST['env_description'] ?? '');
                    $isSensitive = isset($_POST['env_sensitive']);
                    
                    if (!empty($key) && !empty($value)) {
                        // Validate key format (must be valid environment variable name)
                        if (preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
                            if (addCustomEnvVar($db, strtoupper($key), $value, $description, $isSensitive)) {
                                logActivity($db, 'env_var_add', "Added custom env var: {$key}", $_SESSION['user_id']);
                                $success = "Environment variable '{$key}' added successfully!";
                            } else {
                                $error = 'Failed to add environment variable. Key may already exist.';
                            }
                        } else {
                            $error = 'Invalid key format. Use uppercase letters, numbers, and underscores only.';
                        }
                    } else {
                        $error = 'Key and value are required.';
                    }
                    break;
                    
                case 'update':
                    $id = (int)($_POST['env_id'] ?? 0);
                    $key = sanitizeInput($_POST['env_key'] ?? '');
                    $value = $_POST['env_value'] ?? '';
                    $description = sanitizeInput($_POST['env_description'] ?? '');
                    $isSensitive = isset($_POST['env_sensitive']);
                    
                    if ($id > 0 && !empty($key) && !empty($value)) {
                        if (preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
                            if (updateCustomEnvVar($db, $id, strtoupper($key), $value, $description, $isSensitive)) {
                                logActivity($db, 'env_var_update', "Updated custom env var: {$key}", $_SESSION['user_id']);
                                $success = "Environment variable '{$key}' updated successfully!";
                            } else {
                                $error = 'Failed to update environment variable.';
                            }
                        } else {
                            $error = 'Invalid key format. Use uppercase letters, numbers, and underscores only.';
                        }
                    } else {
                        $error = 'Invalid data provided for update.';
                    }
                    break;
                    
                case 'delete':
                    $id = (int)($_POST['env_id'] ?? 0);
                    if ($id > 0) {
                        // Get the key name for logging
                        $stmt = $db->prepare("SELECT var_key FROM custom_env_vars WHERE id = ?");
                        $stmt->execute([$id]);
                        $envVar = $stmt->fetch();
                        
                        if ($envVar && deleteCustomEnvVar($db, $id)) {
                            logActivity($db, 'env_var_delete', "Deleted custom env var: {$envVar['var_key']}", $_SESSION['user_id']);
                            $success = "Environment variable '{$envVar['var_key']}' deleted successfully!";
                        } else {
                            $error = 'Failed to delete environment variable.';
                        }
                    }
                    break;
            }
        } else {
            // Handle settings update
            $updates = [];
            
            if (isset($_POST['app_name'])) {
                $stmt = $db->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'app_name'");
                $stmt->execute([sanitizeInput($_POST['app_name'])]);
                $updates[] = 'Application Name';
            }
            
            if (isset($_POST['max_deployments'])) {
                $maxDep = (int)$_POST['max_deployments'];
                if ($maxDep >= 1 && $maxDep <= 50) {
                    $stmt = $db->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'max_deployments'");
                    $stmt->execute([$maxDep]);
                    $updates[] = 'Max Deployments';
                }
            }
            
            if (isset($_POST['enable_vector_search'])) {
                $stmt = $db->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'enable_vector_search'");
                $stmt->execute([$_POST['enable_vector_search'] === 'true' ? 'true' : 'false']);
                $updates[] = 'Vector Search';
            }
            
            if (isset($_POST['vector_dimension'])) {
                $dimension = (int)$_POST['vector_dimension'];
                if (in_array($dimension, [384, 768, 1536, 3072])) {
                    $stmt = $db->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'vector_dimension'");
                    $stmt->execute([$dimension]);
                    $updates[] = 'Vector Dimension';
                }
            }
            
            if (!empty($updates)) {
                logActivity($db, 'settings_update', 'Updated: ' . implode(', ', $updates), $_SESSION['user_id']);
                $success = 'Settings updated successfully!';
            }
        }
        
    } catch (Exception $e) {
        $error = 'Failed to process request: ' . $e->getMessage();
        error_log($error);
    }
}

// Get current settings
$settings = getSystemSettings($db);

// Get custom environment variables
$customEnvVars = getCustomEnvVars($db);

// Get system information
$systemInfo = [
    'php_version' => phpversion(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'vector_support' => checkVectorSupport($db),
];

// Get database stats
$dbStats = [];
try {
    $stmt = $db->query("SELECT schemaname, tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $tables = $stmt->fetchAll();
    $dbStats['total_tables'] = count($tables);
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM applications");
    $dbStats['applications'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM deployments");
    $dbStats['deployments'] = $stmt->fetch()['count'];
    
    if (checkVectorSupport($db)) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM embeddings");
        $dbStats['embeddings'] = $stmt->fetch()['count'];
    }
    
    // Get database size
    $stmt = $db->query("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
    $dbStats['database_size'] = $stmt->fetch()['size'];
    
} catch (Exception $e) {
    $dbStats['error'] = $e->getMessage();
}
?>

<div class="settings-container">
    <div class="page-header">
        <h1>⚙️ System Settings</h1>
        <p>Configure your PHP Git App Manager system preferences and review system information.</p>
    </div>

    <?php if (isset($success) && $success): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="settings-grid">
        <!-- Application Settings -->
        <div class="settings-card">
            <h2>🚀 Application Settings</h2>
            <form method="POST" class="settings-form">
                <div class="form-group">
                    <label for="app_name">Application Name</label>
                    <input type="text" id="app_name" name="app_name" 
                           value="<?= htmlspecialchars($settings['app_name'] ?? 'PHP Git App Manager') ?>"
                           placeholder="Enter application name">
                    <small>This name appears in the dashboard header and notifications.</small>
                </div>

                <div class="form-group">
                    <label for="max_deployments">Maximum Deployments per App</label>
                    <select id="max_deployments" name="max_deployments">
                        <?php 
                        $maxDep = (int)($settings['max_deployments'] ?? 10);
                        for ($i = 1; $i <= 20; $i++): 
                        ?>
                            <option value="<?= $i ?>" <?= $i === $maxDep ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <small>Limits the number of deployment history records kept per application.</small>
                </div>

                <button type="submit" class="btn btn-primary">💾 Save Application Settings</button>
            </form>
        </div>

        <!-- Vector Database Settings -->
        <div class="settings-card">
            <h2>🧠 Vector Database Settings</h2>
            <?php if ($systemInfo['vector_support']): ?>
                <div class="vector-status enabled">
                    <span class="status-indicator"></span>
                    <strong>pgvector Extension Enabled</strong>
                </div>
                
                <form method="POST" class="settings-form">
                    <div class="form-group">
                        <label for="enable_vector_search">Enable Vector Search</label>
                        <select id="enable_vector_search" name="enable_vector_search">
                            <option value="true" <?= ($settings['enable_vector_search'] ?? 'true') === 'true' ? 'selected' : '' ?>>Enabled</option>
                            <option value="false" <?= ($settings['enable_vector_search'] ?? 'true') === 'false' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <small>Allow applications to use vector embeddings for AI/ML features.</small>
                    </div>

                    <div class="form-group">
                        <label for="vector_dimension">Default Vector Dimension</label>
                        <select id="vector_dimension" name="vector_dimension">
                            <?php 
                            $currentDim = (int)($settings['vector_dimension'] ?? 1536);
                            $dimensions = [384 => '384 (OpenAI text-embedding-3-small)', 768 => '768 (Standard)', 1536 => '1536 (OpenAI text-embedding-3-large)', 3072 => '3072 (High-dimensional)'];
                            foreach ($dimensions as $dim => $label): 
                            ?>
                                <option value="<?= $dim ?>" <?= $dim === $currentDim ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Dimension size for new vector embeddings. Existing embeddings are not affected.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">🔬 Save Vector Settings</button>
                </form>
            <?php else: ?>
                <div class="vector-status disabled">
                    <span class="status-indicator"></span>
                    <strong>pgvector Extension Not Available</strong>
                    <p>Vector embeddings are not supported on this PostgreSQL instance.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Custom Environment Variables -->
        <div class="settings-card wide-card">
            <h2>🌍 Custom Environment Variables</h2>
            <p>Define global environment variables that will be available to all deployed applications. Based on <a href="https://forum.cloudron.io/topic/7378/configuring-environment-variables" target="_blank">Cloudron's environment variable configuration</a> patterns.</p>
            
            <!-- Add New Environment Variable Form -->
            <div class="env-add-form">
                <h3>Add New Environment Variable</h3>
                <form method="POST" class="env-form">
                    <input type="hidden" name="env_action" value="add">
                    <div class="env-form-grid">
                        <div class="form-group">
                            <label for="env_key">Variable Name *</label>
                            <input type="text" id="env_key" name="env_key" required 
                                   placeholder="MY_CUSTOM_VAR" 
                                   pattern="[A-Za-z][A-Za-z0-9_]*"
                                   title="Use letters, numbers, and underscores only">
                            <small>Uppercase letters, numbers, and underscores only</small>
                        </div>
                        <div class="form-group">
                            <label for="env_value">Value *</label>
                            <input type="text" id="env_value" name="env_value" required 
                                   placeholder="your_value_here">
                        </div>
                        <div class="form-group">
                            <label for="env_description">Description</label>
                            <input type="text" id="env_description" name="env_description" 
                                   placeholder="Optional description">
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="env_sensitive"> 
                                Sensitive (hidden in logs)
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">➕ Add Variable</button>
                </form>
            </div>

            <!-- Current Environment Variables -->
            <div class="env-variables-list">
                <h3>Current Custom Variables (<?= count($customEnvVars) ?>)</h3>
                
                <?php if (empty($customEnvVars)): ?>
                    <div class="empty-state">
                        <p>No custom environment variables defined yet.</p>
                        <small>Add variables above to make them available to all deployed applications.</small>
                    </div>
                <?php else: ?>
                    <div class="env-variables-table">
                        <?php foreach ($customEnvVars as $envVar): ?>
                            <div class="env-variable-item" data-id="<?= $envVar['id'] ?>">
                                <div class="env-var-header">
                                    <div class="env-var-name">
                                        <strong><?= htmlspecialchars($envVar['var_key']) ?></strong>
                                        <?php if ($envVar['is_sensitive']): ?>
                                            <span class="sensitive-badge">🔒 Sensitive</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="env-var-actions">
                                        <button class="btn-small btn-edit" onclick="editEnvVar(<?= $envVar['id'] ?>)">✏️ Edit</button>
                                        <button class="btn-small btn-danger" onclick="deleteEnvVar(<?= $envVar['id'] ?>, '<?= htmlspecialchars($envVar['var_key']) ?>')">🗑️ Delete</button>
                                    </div>
                                </div>
                                <div class="env-var-details">
                                    <div class="env-var-value">
                                        <strong>Value:</strong> 
                                        <?php if ($envVar['is_sensitive']): ?>
                                            <span class="sensitive-value">••••••••</span>
                                        <?php else: ?>
                                            <code><?= htmlspecialchars($envVar['var_value']) ?></code>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($envVar['description']): ?>
                                        <div class="env-var-description">
                                            <strong>Description:</strong> <?= htmlspecialchars($envVar['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="env-var-meta">
                                        Created: <?= date('M j, Y g:i A', strtotime($envVar['created_at'])) ?>
                                        <?php if ($envVar['updated_at'] !== $envVar['created_at']): ?>
                                            | Updated: <?= date('M j, Y g:i A', strtotime($envVar['updated_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Edit Form (Hidden by default) -->
                                <div class="env-edit-form" id="edit-form-<?= $envVar['id'] ?>" style="display: none;">
                                    <form method="POST" class="env-form">
                                        <input type="hidden" name="env_action" value="update">
                                        <input type="hidden" name="env_id" value="<?= $envVar['id'] ?>">
                                        <div class="env-form-grid">
                                            <div class="form-group">
                                                <label>Variable Name *</label>
                                                <input type="text" name="env_key" required 
                                                       value="<?= htmlspecialchars($envVar['var_key']) ?>"
                                                       pattern="[A-Za-z][A-Za-z0-9_]*">
                                            </div>
                                            <div class="form-group">
                                                <label>Value *</label>
                                                <input type="text" name="env_value" required 
                                                       value="<?= htmlspecialchars($envVar['var_value']) ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Description</label>
                                                <input type="text" name="env_description" 
                                                       value="<?= htmlspecialchars($envVar['description'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" name="env_sensitive" 
                                                           <?= $envVar['is_sensitive'] ? 'checked' : '' ?>> 
                                                    Sensitive
                                                </label>
                                            </div>
                                        </div>
                                        <div class="edit-form-actions">
                                            <button type="submit" class="btn btn-primary">💾 Update</button>
                                            <button type="button" class="btn btn-secondary" onclick="cancelEdit(<?= $envVar['id'] ?>)">❌ Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="env-usage-info">
                <h4>💡 Usage in Deployed Applications</h4>
                <p>Custom environment variables are automatically available in all deployed PHP applications:</p>
                <pre><code><?php
echo '$customVar = getenv(\'MY_CUSTOM_VAR\');
$customVar = $_ENV[\'MY_CUSTOM_VAR\'] ?? \'default_value\';

// Example usage
$apiKey = getenv(\'API_KEY\') ?: \'development_key\';
$appMode = $_ENV[\'APP_MODE\'] ?? \'production\';';
?></code></pre>
            </div>
        </div>

        <!-- System Information -->
        <div class="settings-card">
            <h2>💻 System Information</h2>
            <div class="system-info">
                <div class="info-grid">
                    <div class="info-item">
                        <label>PHP Version</label>
                        <value><?= $systemInfo['php_version'] ?></value>
                    </div>
                    <div class="info-item">
                        <label>Memory Limit</label>
                        <value><?= $systemInfo['memory_limit'] ?></value>
                    </div>
                    <div class="info-item">
                        <label>Execution Time</label>
                        <value><?= $systemInfo['max_execution_time'] ?>s</value>
                    </div>
                    <div class="info-item">
                        <label>Upload Limit</label>
                        <value><?= $systemInfo['upload_max_filesize'] ?></value>
                    </div>
                    <div class="info-item">
                        <label>POST Limit</label>
                        <value><?= $systemInfo['post_max_size'] ?></value>
                    </div>
                    <div class="info-item">
                        <label>Vector Support</label>
                        <value class="<?= $systemInfo['vector_support'] ? 'enabled' : 'disabled' ?>">
                            <?= $systemInfo['vector_support'] ? '✅ Enabled' : '❌ Disabled' ?>
                        </value>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Statistics -->
        <div class="settings-card">
            <h2>🗄️ Database Statistics</h2>
            <?php if (isset($dbStats['error'])): ?>
                <div class="alert alert-warning">
                    <strong>Database Error:</strong> <?= htmlspecialchars($dbStats['error']) ?>
                </div>
            <?php else: ?>
                <div class="db-stats">
                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= $dbStats['total_tables'] ?? 0 ?></div>
                            <div class="stat-label">Total Tables</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $dbStats['applications'] ?? 0 ?></div>
                            <div class="stat-label">Applications</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $dbStats['deployments'] ?? 0 ?></div>
                            <div class="stat-label">Deployments</div>
                        </div>
                        <?php if (isset($dbStats['embeddings'])): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?= $dbStats['embeddings'] ?></div>
                                <div class="stat-label">Vector Embeddings</div>
                            </div>
                        <?php endif; ?>
                        <div class="stat-item wide">
                            <div class="stat-value"><?= $dbStats['database_size'] ?? 'Unknown' ?></div>
                            <div class="stat-label">Database Size</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Environment Information -->
        <div class="settings-card">
            <h2>🌍 Environment Variables</h2>
            <div class="env-info">
                <?php
                $envVars = [
                    'CLOUDRON_APP_DOMAIN' => 'Application Domain',
                    'CLOUDRON_APP_ORIGIN' => 'Application Origin',
                    'CLOUDRON_POSTGRESQL_HOST' => 'PostgreSQL Host',
                    'CLOUDRON_POSTGRESQL_PORT' => 'PostgreSQL Port',
                    'CLOUDRON_POSTGRESQL_DATABASE' => 'Database Name',
                ];
                
                foreach ($envVars as $var => $label):
                    $value = getenv($var) ?: $_ENV[$var] ?? null;
                ?>
                    <div class="env-item">
                        <label><?= $label ?></label>
                        <value><?= $value ? htmlspecialchars($value) : '<span class="not-set">Not Set</span>' ?></value>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.settings-container { padding: 20px; }
.settings-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); 
    gap: 20px; 
    margin-top: 20px; 
}
.settings-card { 
    background: white; 
    border-radius: 8px; 
    padding: 25px; 
    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
    border: 1px solid #e1e5e9; 
}
.settings-card h2 { 
    margin: 0 0 20px 0; 
    color: #2c3e50; 
    font-size: 1.3em; 
    padding-bottom: 10px; 
    border-bottom: 2px solid #f8f9fa; 
}
.settings-form .form-group { margin-bottom: 20px; }
.settings-form label { 
    display: block; 
    margin-bottom: 5px; 
    font-weight: 600; 
    color: #555; 
}
.settings-form input, .settings-form select { 
    width: 100%; 
    padding: 10px; 
    border: 1px solid #ddd; 
    border-radius: 4px; 
    font-size: 14px; 
}
.settings-form small { 
    display: block; 
    margin-top: 5px; 
    color: #666; 
    font-size: 12px; 
}
.vector-status { 
    display: flex; 
    align-items: center; 
    padding: 10px; 
    border-radius: 4px; 
    margin-bottom: 20px; 
}
.vector-status.enabled { background: #d4edda; color: #155724; }
.vector-status.disabled { background: #f8d7da; color: #721c24; }
.status-indicator { 
    width: 12px; 
    height: 12px; 
    border-radius: 50%; 
    margin-right: 10px; 
}
.vector-status.enabled .status-indicator { background: #28a745; }
.vector-status.disabled .status-indicator { background: #dc3545; }
.info-grid, .stat-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
    gap: 15px; 
}
.info-item, .stat-item { 
    padding: 12px; 
    background: #f8f9fa; 
    border-radius: 4px; 
    text-align: center; 
}
.stat-item.wide { grid-column: span 2; }
.info-item label, .stat-label { 
    display: block; 
    font-size: 12px; 
    color: #666; 
    margin-bottom: 5px; 
    font-weight: 600; 
}
.info-item value, .stat-value { 
    font-weight: bold; 
    color: #2c3e50; 
}
.stat-value { font-size: 1.5em; margin-bottom: 5px; }
.env-item { 
    display: flex; 
    justify-content: space-between; 
    padding: 8px 0; 
    border-bottom: 1px solid #eee; 
}
.env-item:last-child { border-bottom: none; }
.env-item label { font-weight: 600; color: #555; }
.env-item .not-set { color: #dc3545; font-style: italic; }
.enabled { color: #28a745; }
.disabled { color: #dc3545; }

/* Custom Environment Variables Styles */
.wide-card { grid-column: 1 / -1; }
.env-add-form { 
    background: #f8f9fa; 
    padding: 20px; 
    border-radius: 6px; 
    margin-bottom: 30px; 
    border: 1px solid #e9ecef; 
}
.env-add-form h3 { 
    margin: 0 0 15px 0; 
    color: #495057; 
}
.env-form-grid { 
    display: grid; 
    grid-template-columns: 2fr 2fr 2fr 1fr; 
    gap: 15px; 
    margin-bottom: 15px; 
}
.env-form .form-group { margin-bottom: 0; }
.checkbox-label { 
    display: flex; 
    align-items: center; 
    font-weight: 600; 
    color: #555; 
}
.checkbox-label input { 
    width: auto; 
    margin-right: 8px; 
}
.env-variables-list h3 { 
    color: #495057; 
    margin-bottom: 15px; 
    padding-bottom: 8px; 
    border-bottom: 1px solid #dee2e6; 
}
.empty-state { 
    text-align: center; 
    padding: 40px; 
    color: #6c757d; 
    background: #f8f9fa; 
    border-radius: 6px; 
}
.env-variable-item { 
    background: #fff; 
    border: 1px solid #dee2e6; 
    border-radius: 6px; 
    margin-bottom: 15px; 
    overflow: hidden; 
}
.env-var-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 15px 20px; 
    background: #f8f9fa; 
    border-bottom: 1px solid #dee2e6; 
}
.env-var-name { 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}
.sensitive-badge { 
    background: #ffc107; 
    color: #212529; 
    padding: 2px 8px; 
    border-radius: 12px; 
    font-size: 11px; 
    font-weight: 600; 
}
.env-var-actions { 
    display: flex; 
    gap: 8px; 
}
.btn-small { 
    padding: 5px 10px; 
    font-size: 12px; 
    border: none; 
    border-radius: 4px; 
    cursor: pointer; 
    text-decoration: none; 
    display: inline-block; 
}
.btn-edit { 
    background: #007bff; 
    color: white; 
}
.btn-edit:hover { background: #0056b3; }
.btn-danger { 
    background: #dc3545; 
    color: white; 
}
.btn-danger:hover { background: #c82333; }
.env-var-details { 
    padding: 15px 20px; 
}
.env-var-value { 
    margin-bottom: 10px; 
}
.env-var-value code { 
    background: #e9ecef; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Monaco', 'Consolas', monospace; 
}
.sensitive-value { 
    color: #6c757d; 
    font-family: monospace; 
}
.env-var-description { 
    margin-bottom: 10px; 
    color: #6c757d; 
}
.env-var-meta { 
    font-size: 12px; 
    color: #868e96; 
}
.env-edit-form { 
    background: #fff3cd; 
    padding: 15px 20px; 
    border-top: 1px solid #ffeaa7; 
}
.edit-form-actions { 
    display: flex; 
    gap: 10px; 
    margin-top: 15px; 
}
.env-usage-info { 
    background: #e7f3ff; 
    padding: 20px; 
    border-radius: 6px; 
    margin-top: 20px; 
    border-left: 4px solid #007bff; 
}
.env-usage-info h4 { 
    margin: 0 0 10px 0; 
    color: #004085; 
}
.env-usage-info pre { 
    background: #f8f9fa; 
    padding: 15px; 
    border-radius: 4px; 
    overflow-x: auto; 
    margin: 10px 0 0 0; 
}
.env-usage-info code { 
    font-family: 'Monaco', 'Consolas', monospace; 
    font-size: 13px; 
    line-height: 1.4; 
 }
</style>

<script>
function editEnvVar(id) {
    // Hide the details and show the edit form
    const item = document.querySelector(`[data-id="${id}"]`);
    const details = item.querySelector('.env-var-details');
    const editForm = document.getElementById(`edit-form-${id}`);
    
    details.style.display = 'none';
    editForm.style.display = 'block';
}

function cancelEdit(id) {
    // Show the details and hide the edit form
    const item = document.querySelector(`[data-id="${id}"]`);
    const details = item.querySelector('.env-var-details');
    const editForm = document.getElementById(`edit-form-${id}`);
    
    details.style.display = 'block';
    editForm.style.display = 'none';
}

function deleteEnvVar(id, varName) {
    if (confirm(`Are you sure you want to delete the environment variable "${varName}"?\n\nThis action cannot be undone and may affect deployed applications.`)) {
        // Create a hidden form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="env_action" value="delete">
            <input type="hidden" name="env_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-convert variable names to uppercase
document.addEventListener('DOMContentLoaded', function() {
    const envKeyInputs = document.querySelectorAll('input[name="env_key"]');
    envKeyInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
        });
    });
});
</script> 