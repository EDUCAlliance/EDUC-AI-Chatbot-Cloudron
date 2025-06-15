<?php $pageTitle = 'Dashboard'; ?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>📊 Dashboard</h1>
        <p>Welcome to your PHP Git App Manager administration panel</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📱</div>
            <div class="stat-content">
                <h3><?= $stats['total_apps'] ?? 0 ?></h3>
                <p>Total Applications</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🚀</div>
            <div class="stat-content">
                <h3><?= $stats['deployed_apps'] ?? 0 ?></h3>
                <p>Deployed Apps</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">⚡</div>
            <div class="stat-content">
                <h3><?= $stats['recent_deployments'] ?? 0 ?></h3>
                <p>Recent Deployments</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🧠</div>
            <div class="stat-content">
                <h3><?= $stats['embeddings'] ?? 0 ?></h3>
                <p>Vector Embeddings</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="dashboard-section">
        <h2>🔧 Quick Actions</h2>
        <div class="quick-actions">
            <a href="/server-admin/?action=applications&create=1" class="action-card">
                <div class="action-icon">➕</div>
                <h3>Add New Application</h3>
                <p>Deploy a new PHP application from Git repository</p>
            </a>

            <a href="/server-admin/?action=database" class="action-card">
                <div class="action-icon">🗄️</div>
                <h3>Database Explorer</h3>
                <p>Browse database structure and vector embeddings</p>
            </a>

            <a href="/server-admin/?action=settings" class="action-card">
                <div class="action-icon">⚙️</div>
                <h3>System Settings</h3>
                <p>Configure system parameters and preferences</p>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="dashboard-section">
        <h2>📋 Recent Activity</h2>
        <div class="activity-list">
            <?php
            try {
                $stmt = $db->prepare("
                    SELECT al.*, au.username 
                    FROM activity_log al 
                    LEFT JOIN admin_users au ON al.user_id = au.id 
                    ORDER BY al.created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute();
                $activities = $stmt->fetchAll();

                if (empty($activities)):
            ?>
                <div class="no-activity">
                    <p>No recent activity to display.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php
                            $icon = match($activity['action']) {
                                'login_success' => '🔓',
                                'admin_setup' => '🔧',
                                'app_deploy' => '🚀',
                                'app_create' => '➕',
                                'database_query' => '🗄️',
                                'settings_update' => '⚙️',
                                default => '📋'
                            };
                            echo $icon;
                            ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description">
                                <strong><?= htmlspecialchars($activity['action']) ?></strong>
                                <?php if (!empty($activity['details'])): ?>
                                    - <?= htmlspecialchars($activity['details']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="activity-meta">
                                <?php if ($activity['username']): ?>
                                    by <?= htmlspecialchars($activity['username']) ?>
                                <?php endif; ?>
                                • <?= timeAgo($activity['created_at']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php
            } catch (PDOException $e) {
                echo '<div class="alert alert-error">Failed to load recent activity.</div>';
            }
            ?>
        </div>
    </div>

    <!-- System Status -->
    <div class="dashboard-section">
        <h2>🔍 System Status</h2>
        <div class="status-grid">
            <div class="status-item">
                <div class="status-label">Database Connection</div>
                <div class="status-value status-success">✅ Connected</div>
            </div>

            <div class="status-item">
                <div class="status-label">Vector Support</div>
                <div class="status-value <?= checkVectorSupport($db) ? 'status-success' : 'status-warning' ?>">
                    <?= checkVectorSupport($db) ? '✅ Enabled' : '⚠️ Unavailable' ?>
                </div>
            </div>

            <div class="status-item">
                <div class="status-label">Git</div>
                <div class="status-value <?= shell_exec('which git') ? 'status-success' : 'status-error' ?>">
                    <?= shell_exec('which git') ? '✅ Available' : '❌ Not Found' ?>
                </div>
            </div>

            <div class="status-item">
                <div class="status-label">Disk Space</div>
                <div class="status-value status-info">
                    <?php
                    $freeBytes = disk_free_space('/app/code');
                    $totalBytes = disk_total_space('/app/code');
                    $usedPercent = $freeBytes ? round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1) : 0;
                    echo $usedPercent . '% used';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Storage Usage -->
    <div class="dashboard-section">
        <h2>💾 Storage Usage</h2>
        <div class="storage-info">
            <div class="storage-item">
                <div class="storage-label">Applications</div>
                <div class="storage-value">
                    <?php
                    $appSize = 0;
                    if (is_dir('/app/code/apps')) {
                        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/app/code/apps'));
                        foreach ($iterator as $file) {
                            if ($file->isFile()) {
                                $appSize += $file->getSize();
                            }
                        }
                    }
                    echo formatBytes($appSize);
                    ?>
                </div>
            </div>

            <div class="storage-item">
                <div class="storage-label">Database</div>
                <div class="storage-value">
                    <?php
                    try {
                        $stmt = $db->query("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
                        $result = $stmt->fetch();
                        echo $result['size'] ?? 'Unknown';
                    } catch (PDOException $e) {
                        echo 'Unknown';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div> 