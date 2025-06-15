<?php $pageTitle = 'Database Explorer'; ?>

<div class="database">
    <div class="page-header">
        <div class="header-left">
            <h1>🗄️ Database Explorer</h1>
            <p>Browse your PostgreSQL database structure and vector embeddings</p>
        </div>
        <div class="header-right">
            <div class="database-info">
                <?php if ($vectorSupport): ?>
                    <span class="status-badge status-success">🚀 Vector Support Enabled</span>
                <?php else: ?>
                    <span class="status-badge status-warning">⚠️ Vector Support Unavailable</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="database-tabs">
        <button class="tab-button active" onclick="showTab('schemas')">
            📋 Database Schemas
        </button>
        <button class="tab-button" onclick="showTab('embeddings')">
            🧠 Vector Embeddings
        </button>
        <button class="tab-button" onclick="showTab('query')">
            💾 SQL Query
        </button>
    </div>

    <!-- Schemas Tab -->
    <div id="schemas-tab" class="tab-content active">
        <div class="schemas-section">
            <h2>Database Schemas</h2>
            
            <?php if (empty($schemas)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🗄️</div>
                    <p>No schemas found or unable to access database.</p>
                </div>
            <?php else: ?>
                <div class="schemas-list">
                    <?php foreach ($schemas as $schema): ?>
                        <div class="schema-card">
                            <div class="schema-header" onclick="toggleSchema('<?= htmlspecialchars($schema['schema_name']) ?>')">
                                <h3>
                                    <span class="toggle-icon">▶</span>
                                    <?= htmlspecialchars($schema['schema_name']) ?>
                                </h3>
                                <div class="schema-actions">
                                    <button class="btn btn-sm btn-outline" onclick="event.stopPropagation(); refreshSchema('<?= htmlspecialchars($schema['schema_name']) ?>')">
                                        🔄 Refresh
                                    </button>
                                </div>
                            </div>
                            
                            <div class="schema-content" id="schema-<?= htmlspecialchars($schema['schema_name']) ?>" style="display: none;">
                                <div class="loading">Loading tables...</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Embeddings Tab -->
    <div id="embeddings-tab" class="tab-content">
        <div class="embeddings-section">
            <h2>Vector Embeddings</h2>
            
            <?php if (!$vectorSupport): ?>
                <div class="alert alert-warning">
                    <h4>⚠️ Vector Support Not Available</h4>
                    <p>The pgvector extension is not installed or enabled. Vector embeddings functionality will be limited.</p>
                    <p>To enable vector support, install the pgvector extension in your PostgreSQL database.</p>
                </div>
            <?php endif; ?>
            
            <div class="embeddings-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🧠</div>
                        <div class="stat-content">
                            <h3 id="embeddings-count">-</h3>
                            <p>Total Embeddings</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📱</div>
                        <div class="stat-content">
                            <h3 id="apps-with-embeddings">-</h3>
                            <p>Apps with Embeddings</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <h3 id="embedding-dimension">-</h3>
                            <p>Vector Dimension</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="embeddings-list">
                <div class="list-header">
                    <h3>Recent Embeddings</h3>
                    <div class="list-controls">
                        <select id="app-filter" onchange="filterEmbeddings()">
                            <option value="">All Applications</option>
                        </select>
                        <button class="btn btn-sm btn-primary" onclick="refreshEmbeddings()">
                            🔄 Refresh
                        </button>
                    </div>
                </div>
                
                <div id="embeddings-table" class="embeddings-table">
                    <div class="loading">Loading embeddings...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SQL Query Tab -->
    <div id="query-tab" class="tab-content">
        <div class="query-section">
            <h2>SQL Query Interface</h2>
            <p class="warning-text">⚠️ Use with caution. Only SELECT queries are recommended for safety.</p>
            
            <div class="query-interface">
                <div class="query-input">
                    <textarea id="sql-query" placeholder="Enter your SQL query here...
Example: SELECT * FROM applications LIMIT 10;"></textarea>
                    <div class="query-actions">
                        <button class="btn btn-primary" onclick="executeQuery()">
                            ▶️ Execute Query
                        </button>
                        <button class="btn btn-secondary" onclick="clearQuery()">
                            🗑️ Clear
                        </button>
                        <div class="query-templates">
                            <select onchange="loadQueryTemplate(this.value)">
                                <option value="">Load Template...</option>
                                <option value="apps">List All Applications</option>
                                <option value="embeddings">Show Embeddings</option>
                                <option value="deployments">Recent Deployments</option>
                                <option value="activity">Activity Log</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="query-results">
                    <div class="results-header">
                        <h3>Query Results</h3>
                        <span id="results-info"></span>
                    </div>
                    <div id="query-output" class="query-output">
                        <p class="placeholder">Execute a query to see results here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentTab = 'schemas';

function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
    
    currentTab = tabName;
    
    // Load tab content if needed
    if (tabName === 'embeddings' && !document.getElementById('embeddings-loaded')) {
        loadEmbeddingsData();
    }
}

function toggleSchema(schemaName) {
    const content = document.getElementById('schema-' + schemaName);
    const icon = content.previousElementSibling.querySelector('.toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
        loadSchemaContent(schemaName);
    } else {
        content.style.display = 'none';
        icon.textContent = '▶';
    }
}

function loadSchemaContent(schemaName) {
    const container = document.getElementById('schema-' + schemaName);
    container.innerHTML = '<div class="loading">Loading tables...</div>';
    
    fetch(`/server-admin/ajax/get-schema-tables.php?schema=${encodeURIComponent(schemaName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySchemaContent(container, data.tables);
            } else {
                container.innerHTML = '<div class="alert alert-error">Failed to load schema content</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-error">Error loading schema content</div>';
        });
}

function displaySchemaContent(container, tables) {
    if (tables.length === 0) {
        container.innerHTML = '<p>No tables found in this schema.</p>';
        return;
    }
    
    let html = '<div class="tables-list">';
    tables.forEach(table => {
        html += `
            <div class="table-item">
                <div class="table-header" onclick="toggleTable('${table.table_name}')">
                    <h4>
                        <span class="toggle-icon">▶</span>
                        ${table.table_name}
                        <span class="table-type">(${table.table_type})</span>
                    </h4>
                </div>
                <div class="table-content" id="table-${table.table_name}" style="display: none;">
                    <div class="loading">Loading columns...</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function toggleTable(tableName) {
    const content = document.getElementById('table-' + tableName);
    const icon = content.previousElementSibling.querySelector('.toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
        loadTableStructure(tableName);
    } else {
        content.style.display = 'none';
        icon.textContent = '▶';
    }
}

function loadTableStructure(tableName) {
    const container = document.getElementById('table-' + tableName);
    
    fetch(`/server-admin/ajax/get-table-structure.php?table=${encodeURIComponent(tableName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTableStructure(container, data.columns);
            } else {
                container.innerHTML = '<div class="alert alert-error">Failed to load table structure</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-error">Error loading table structure</div>';
        });
}

function displayTableStructure(container, columns) {
    if (columns.length === 0) {
        container.innerHTML = '<p>No columns found.</p>';
        return;
    }
    
    let html = '<table class="table-structure"><thead><tr>';
    html += '<th>Column</th><th>Type</th><th>Nullable</th><th>Default</th>';
    html += '</tr></thead><tbody>';
    
    columns.forEach(column => {
        html += `
            <tr>
                <td><strong>${column.column_name}</strong></td>
                <td>${column.data_type}</td>
                <td>${column.is_nullable === 'YES' ? '✅' : '❌'}</td>
                <td>${column.column_default || '-'}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function loadEmbeddingsData() {
    fetch('/server-admin/ajax/get-embeddings-stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('embeddings-count').textContent = data.stats.total_embeddings;
                document.getElementById('apps-with-embeddings').textContent = data.stats.apps_count;
                document.getElementById('embedding-dimension').textContent = data.stats.dimension || 'N/A';
                
                // Populate app filter
                const appFilter = document.getElementById('app-filter');
                data.apps.forEach(app => {
                    const option = document.createElement('option');
                    option.value = app.id;
                    option.textContent = app.name;
                    appFilter.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Failed to load embeddings stats:', error);
        });
    
    refreshEmbeddings();
    document.getElementById('embeddings-loaded') = true;
}

function refreshEmbeddings() {
    const appId = document.getElementById('app-filter').value;
    const container = document.getElementById('embeddings-table');
    container.innerHTML = '<div class="loading">Loading embeddings...</div>';
    
    const url = `/server-admin/ajax/get-embeddings.php${appId ? '?app_id=' + appId : ''}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEmbeddings(container, data.embeddings);
            } else {
                container.innerHTML = '<div class="alert alert-error">Failed to load embeddings</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="alert alert-error">Error loading embeddings</div>';
        });
}

function displayEmbeddings(container, embeddings) {
    if (embeddings.length === 0) {
        container.innerHTML = '<p>No embeddings found.</p>';
        return;
    }
    
    let html = '<table class="embeddings-table-content"><thead><tr>';
    html += '<th>ID</th><th>Application</th><th>Content Preview</th><th>Metadata</th><th>Created</th>';
    html += '</tr></thead><tbody>';
    
    embeddings.forEach(embedding => {
        const contentPreview = embedding.content.length > 100 
            ? embedding.content.substring(0, 100) + '...' 
            : embedding.content;
        
        html += `
            <tr>
                <td>${embedding.id}</td>
                <td>${embedding.app_name || 'Unknown'}</td>
                <td class="content-preview">${contentPreview}</td>
                <td>${embedding.metadata ? JSON.stringify(JSON.parse(embedding.metadata), null, 2) : '-'}</td>
                <td>${new Date(embedding.created_at).toLocaleString()}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function executeQuery() {
    const query = document.getElementById('sql-query').value.trim();
    if (!query) {
        alert('Please enter a SQL query');
        return;
    }
    
    const output = document.getElementById('query-output');
    const info = document.getElementById('results-info');
    
    output.innerHTML = '<div class="loading">Executing query...</div>';
    info.textContent = '';
    
    fetch('/server-admin/ajax/execute-query.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            query: query,
            csrf_token: window.AdminConfig.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayQueryResults(output, data.results, data.columns);
            info.textContent = `${data.rows_affected} rows returned`;
        } else {
            output.innerHTML = `<div class="alert alert-error">${data.message}</div>`;
            info.textContent = 'Query failed';
        }
    })
    .catch(error => {
        output.innerHTML = '<div class="alert alert-error">Error executing query</div>';
        info.textContent = 'Query failed';
    });
}

function displayQueryResults(container, results, columns) {
    if (!results || results.length === 0) {
        container.innerHTML = '<p>Query executed successfully but returned no results.</p>';
        return;
    }
    
    let html = '<table class="query-results-table"><thead><tr>';
    columns.forEach(column => {
        html += `<th>${column}</th>`;
    });
    html += '</tr></thead><tbody>';
    
    results.forEach(row => {
        html += '<tr>';
        columns.forEach(column => {
            let value = row[column];
            if (value === null) value = 'NULL';
            else if (typeof value === 'object') value = JSON.stringify(value);
            html += `<td>${value}</td>`;
        });
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function clearQuery() {
    document.getElementById('sql-query').value = '';
    document.getElementById('query-output').innerHTML = '<p class="placeholder">Execute a query to see results here.</p>';
    document.getElementById('results-info').textContent = '';
}

function loadQueryTemplate(template) {
    const textarea = document.getElementById('sql-query');
    
    const templates = {
        'apps': 'SELECT id, name, repository, branch, deployed, created_at FROM applications ORDER BY created_at DESC;',
        'embeddings': 'SELECT id, application_id, content, metadata, created_at FROM embeddings LIMIT 20;',
        'deployments': 'SELECT d.id, a.name as app_name, d.status, d.started_at, d.completed_at FROM deployments d JOIN applications a ON d.application_id = a.id ORDER BY d.started_at DESC LIMIT 10;',
        'activity': 'SELECT al.action, al.details, au.username, al.created_at FROM activity_log al LEFT JOIN admin_users au ON al.user_id = au.id ORDER BY al.created_at DESC LIMIT 20;'
    };
    
    if (templates[template]) {
        textarea.value = templates[template];
    }
}

function refreshSchema(schemaName) {
    const content = document.getElementById('schema-' + schemaName);
    if (content.style.display !== 'none') {
        loadSchemaContent(schemaName);
    }
}

function filterEmbeddings() {
    refreshEmbeddings();
}

// Initialize the first tab
document.addEventListener('DOMContentLoaded', function() {
    // Auto-expand the first schema if there's only one
    const schemas = document.querySelectorAll('.schema-card');
    if (schemas.length === 1) {
        const schemaName = schemas[0].querySelector('.schema-header h3').textContent.trim();
        toggleSchema(schemaName);
    }
});
</script> 