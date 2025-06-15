<?php
// Prevent direct access
if (!defined('ADMIN_SESSION_TIMEOUT')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Panel' ?> - PHP Git App Manager</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/admin.js" defer></script>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="header-container">
            <div class="header-left">
                <h1 class="admin-title">
                    <a href="/server-admin/">🚀 PHP Git App Manager</a>
                </h1>
            </div>
            
            <nav class="admin-nav">
                <a href="/server-admin/" class="nav-item <?= ($action ?? '') === 'dashboard' ? 'active' : '' ?>">
                    📊 Dashboard
                </a>
                <a href="/server-admin/?action=applications" class="nav-item <?= ($action ?? '') === 'applications' ? 'active' : '' ?>">
                    📱 Applications
                </a>
                <a href="/server-admin/?action=database" class="nav-item <?= ($action ?? '') === 'database' ? 'active' : '' ?>">
                    🗄️ Database
                </a>
                <a href="/server-admin/?action=settings" class="nav-item <?= ($action ?? '') === 'settings' ? 'active' : '' ?>">
                    ⚙️ Settings
                </a>
                <a href="/server-admin/?action=debug" class="nav-item <?= ($action ?? '') === 'debug' ? 'active' : '' ?>">
                    🔧 Debug
                </a>
            </nav>
            
            <div class="header-right">
                <div class="user-menu">
                    <span class="username">👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                    <div class="user-menu-dropdown">
                        <a href="/server-admin/?action=logout" class="logout-link">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="admin-main">
        <div class="admin-container"> 