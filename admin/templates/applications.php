<?php $pageTitle = 'Applications'; ?>

<div class="applications">
    <div class="page-header">
        <div class="header-left">
            <h1>📱 Applications</h1>
            <p>Manage your deployed PHP applications from Git repositories</p>
        </div>
        <div class="header-right">
            <button class="btn btn-primary" onclick="showCreateAppModal()">
                ➕ Add New Application
            </button>
        </div>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <div class="empty-icon">📱</div>
            <h2>No Applications Yet</h2>
            <p>Start by adding your first Git repository to deploy as a PHP application.</p>
            <button class="btn btn-primary" onclick="showCreateAppModal()">
                ➕ Add Your First Application
            </button>
        </div>
    <?php else: ?>
        <div class="applications-grid">
            <?php foreach ($applications as $app): ?>
                <div class="app-card">
                    <div class="app-header">
                        <h3><?= htmlspecialchars($app['name']) ?></h3>
                        <div class="app-status">
                            <?php if ($app['deployed']): ?>
                                <span class="status-badge status-success">🚀 Deployed</span>
                            <?php else: ?>
                                <span class="status-badge status-warning">⏳ Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="app-content">
                        <p class="app-description">
                            <?= htmlspecialchars($app['description'] ?: 'No description provided') ?>
                        </p>

                        <div class="app-meta">
                            <div class="meta-item">
                                <strong>Repository:</strong><br>
                                <a href="<?= htmlspecialchars($app['repository']) ?>" target="_blank" class="repo-link">
                                    <?= htmlspecialchars($app['repository']) ?>
                                </a>
                            </div>
                            <div class="meta-item">
                                <strong>Branch:</strong> <?= htmlspecialchars($app['branch']) ?>
                            </div>
                            <div class="meta-item">
                                <strong>Directory:</strong> <?= htmlspecialchars($app['directory']) ?>
                            </div>
                            <div class="meta-item">
                                <strong>Deployments:</strong> <?= $app['deployment_count'] ?>
                            </div>
                        </div>

                        <?php if ($app['last_deploy']): ?>
                            <div class="app-deploy-info">
                                <small>Last deployed: <?= timeAgo($app['last_deploy']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="app-actions">
                        <?php if ($app['deployed']): ?>
                            <a href="/apps/<?= htmlspecialchars($app['directory']) ?>/" 
                               class="btn btn-primary btn-sm" target="_blank">
                                🔗 Open App
                            </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-info btn-sm" 
                                onclick="viewDeploymentLogs(<?= $app['id'] ?>)">
                            📋 View Logs
                        </button>
                        
                        <button class="btn btn-secondary btn-sm" 
                                onclick="deployApp(<?= $app['id'] ?>)">
                            🔄 Redeploy
                        </button>
                        
                        <button class="btn btn-outline btn-sm" 
                                onclick="editApp(<?= $app['id'] ?>)">
                            ✏️ Edit
                        </button>
                        
                        <button class="btn btn-danger btn-sm" 
                                onclick="deleteApp(<?= $app['id'] ?>)">
                            🗑️ Delete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Flash Messages Container -->
<div id="flash-messages" class="flash-messages"></div>

<!-- Create/Edit Application Modal -->
<div id="app-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">➕ Add New Application</h2>
            <button class="modal-close" onclick="closeAppModal()">&times;</button>
        </div>
        
        <form id="app-form" class="modal-body">
            <input type="hidden" id="app-id" name="app_id">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="form-group">
                <label for="app-name">Application Name *</label>
                <input type="text" id="app-name" name="name" required 
                       placeholder="My Awesome App">
                <small>A friendly name for your application</small>
            </div>
            
            <div class="form-group">
                <label for="app-description">Description</label>
                <textarea id="app-description" name="description" rows="3"
                          placeholder="Brief description of your application"></textarea>
            </div>
            
            <div class="form-group">
                <label for="app-repository">Git Repository URL *</label>
                <input type="url" id="app-repository" name="repository" required 
                       placeholder="https://github.com/username/repository.git">
                <small>Must be a publicly accessible Git repository</small>
            </div>
            
            <div class="form-group">
                <label for="app-branch">Branch</label>
                <div class="branch-input-container">
                    <input type="text" id="app-branch" name="branch" value="main" 
                           placeholder="Enter branch name" list="branch-suggestions">
                    <datalist id="branch-suggestions">
                        <option value="main">
                        <option value="master">
                        <option value="develop">
                        <option value="staging">
                        <option value="production">
                        <option value="dev">
                        <option value="feature">
                        <option value="release">
                        <option value="hotfix">
                    </datalist>
                </div>
                <small>Git branch to deploy from. You can type any custom branch name or select from common suggestions.</small>
            </div>
            
            <div class="form-group">
                <label for="app-directory">Directory Name</label>
                <input type="text" id="app-directory" name="directory" 
                       placeholder="Auto-generated from app name">
                <small>Directory where the app will be stored (auto-generated if empty)</small>
            </div>
        </form>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAppModal()">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="saveApp()">
                <span id="save-btn-text">Create Application</span>
            </button>
        </div>
    </div>
</div>

<!-- Deployment Status Modal -->
<div id="deploy-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🚀 Deployment Status</h2>
            <button class="modal-close" onclick="closeDeployModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="deploy-status" class="deploy-status">
                <div class="deploy-step active">
                    <div class="step-icon">📥</div>
                    <div class="step-content">
                        <h4>Fetching Repository</h4>
                        <p>Cloning from Git repository...</p>
                    </div>
                </div>
                
                <div class="deploy-step">
                    <div class="step-icon">🔧</div>
                    <div class="step-content">
                        <h4>Setting Up Application</h4>
                        <p>Installing dependencies and configuring...</p>
                    </div>
                </div>
                
                <div class="deploy-step">
                    <div class="step-icon">✅</div>
                    <div class="step-content">
                        <h4>Deployment Complete</h4>
                        <p>Application is ready and accessible!</p>
                    </div>
                </div>
            </div>
            
            <div id="deploy-log" class="deploy-log">
                <h4>Deployment Log</h4>
                <pre id="deploy-log-content"></pre>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeployModal()">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function showCreateAppModal() {
    document.getElementById('modal-title').textContent = '➕ Add New Application';
    document.getElementById('save-btn-text').textContent = 'Create Application';
    document.getElementById('app-form').reset();
    document.getElementById('app-id').value = '';
    document.getElementById('app-modal').style.display = 'flex';
}

function closeAppModal() {
    document.getElementById('app-modal').style.display = 'none';
}

function editApp(appId) {
    // Load application data and show edit modal
    fetch(`/server-admin/ajax/get-app.php?id=${appId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modal-title').textContent = '✏️ Edit Application';
                document.getElementById('save-btn-text').textContent = 'Update Application';
                document.getElementById('app-id').value = data.app.id;
                document.getElementById('app-name').value = data.app.name;
                document.getElementById('app-description').value = data.app.description || '';
                document.getElementById('app-repository').value = data.app.repository;
                document.getElementById('app-branch').value = data.app.branch;
                document.getElementById('app-directory').value = data.app.directory;
                document.getElementById('app-modal').style.display = 'flex';
            }
        })
        .catch(error => {
            showAlert('Failed to load application data', 'error');
        });
}

function saveApp() {
    const formData = new FormData(document.getElementById('app-form'));
    const appId = document.getElementById('app-id').value;
    const url = appId ? '/server-admin/ajax/update-app.php' : '/server-admin/ajax/create-app.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeAppModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('Failed to save application', 'error');
    });
}

function viewDeploymentLogs(appId) {
    document.getElementById('deploy-modal').style.display = 'flex';
    
    // Reset deployment status for viewing logs
    const steps = document.querySelectorAll('.deploy-step');
    steps.forEach(step => {
        step.classList.remove('active', 'completed', 'error');
    });
    
    // Set modal title for viewing logs
    document.querySelector('#deploy-modal .modal-header h2').textContent = '📋 Deployment Logs';
    
    document.getElementById('deploy-log-content').textContent = 'Loading deployment logs...\n';
    
    // Fetch deployment logs
    fetch('/server-admin/ajax/view-deployment-logs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            app_id: appId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update deployment steps based on status
            if (data.deployment) {
                updateStepsForStatus(data.deployment.status);
            }
            
            // Show the logs
            document.getElementById('deploy-log-content').textContent = data.log;
            
            // Update modal title with app name and status
            const statusIcon = data.deployment?.status === 'completed' ? '✅' : 
                              data.deployment?.status === 'failed' ? '❌' : 
                              data.deployment?.status === 'running' ? '🔄' : '📋';
            document.querySelector('#deploy-modal .modal-header h2').textContent = 
                `${statusIcon} Deployment Logs - ${data.app_name}`;
        } else {
            document.getElementById('deploy-log-content').textContent = 
                'Error loading logs: ' + data.message;
        }
    })
    .catch(error => {
        document.getElementById('deploy-log-content').textContent = 
            'Failed to load deployment logs: ' + error.message;
    });
}

function updateStepsForStatus(status) {
    const steps = document.querySelectorAll('.deploy-step');
    
    switch (status) {
        case 'completed':
            steps.forEach(step => step.classList.add('completed'));
            break;
        case 'failed':
            steps[0].classList.add('error');
            break;
        case 'running':
            steps[0].classList.add('completed');
            steps[1].classList.add('active');
            break;
        default:
            steps[0].classList.add('active');
    }
}

function deployApp(appId) {
    document.getElementById('deploy-modal').style.display = 'flex';
    
    // Reset deployment status
    const steps = document.querySelectorAll('.deploy-step');
    steps.forEach((step, index) => {
        step.classList.remove('active', 'completed', 'error');
        if (index === 0) step.classList.add('active');
    });
    
    // Reset modal title
    document.querySelector('#deploy-modal .modal-header h2').textContent = '🚀 Deployment Status';
    
    document.getElementById('deploy-log-content').textContent = 'Starting deployment...\n';
    
    // Start deployment
    fetch('/server-admin/ajax/deploy-app.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            app_id: appId,
            csrf_token: window.AdminConfig.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateDeploymentStatus(data.deployment_id);
        } else {
            showDeploymentError(data.message);
        }
    })
    .catch(error => {
        showDeploymentError('Failed to start deployment');
    });
}

function updateDeploymentStatus(deploymentId) {
    const checkStatus = () => {
        fetch(`/server-admin/ajax/deployment-status.php?id=${deploymentId}`)
            .then(response => response.json())
            .then(data => {
                updateDeploymentUI(data);
                
                if (data.status === 'running') {
                    setTimeout(checkStatus, 2000);
                } else if (data.status === 'completed') {
                    showAlert('Deployment completed successfully!', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else if (data.status === 'failed') {
                    showDeploymentError(data.log);
                }
            })
            .catch(error => {
                showDeploymentError('Failed to check deployment status');
            });
    };
    
    checkStatus();
}

function updateDeploymentUI(data) {
    const logContent = document.getElementById('deploy-log-content');
    logContent.textContent = data.log || 'No log available';
    
    // Update step status based on deployment progress
    const steps = document.querySelectorAll('.deploy-step');
    steps.forEach((step, index) => {
        step.classList.remove('active', 'completed', 'error');
        
        if (data.status === 'completed') {
            step.classList.add('completed');
        } else if (data.status === 'failed') {
            if (index === 0) step.classList.add('error');
        } else {
            if (index === 0) step.classList.add('active');
        }
    });
}

function showDeploymentError(message) {
    const steps = document.querySelectorAll('.deploy-step');
    steps[0].classList.add('error');
    showAlert(`Deployment failed: ${message}`, 'error');
}

function closeDeployModal() {
    document.getElementById('deploy-modal').style.display = 'none';
}

function deleteApp(appId) {
    if (confirm('Are you sure you want to delete this application? This action cannot be undone.')) {
        fetch('/server-admin/ajax/delete-app.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                app_id: appId,
                csrf_token: window.AdminConfig.csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Application deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            showAlert('Failed to delete application', 'error');
        });
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.getElementById('flash-messages');
    container.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script> 