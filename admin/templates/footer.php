        </div>
    </main>

    <footer class="admin-footer">
        <div class="footer-container">
            <div class="footer-left">
                <p>&copy; <?= date('Y') ?> PHP Git App Manager. Built with ❤️ for developers.</p>
            </div>
            <div class="footer-right">
                <div class="system-info">
                    <?php if (checkVectorSupport($db ?? null)): ?>
                        <span class="status-badge status-success">🚀 Vector Search Enabled</span>
                    <?php else: ?>
                        <span class="status-badge status-warning">⚠️ Vector Search Unavailable</span>
                    <?php endif; ?>
                    <span class="status-badge status-info">PHP <?= PHP_VERSION ?></span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Processing...</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="flash-messages" class="flash-messages"></div>

    <script>
        // Global JavaScript variables
        window.AdminConfig = {
            baseUrl: '/server-admin/',
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        };

        // Initialize admin panel
        document.addEventListener('DOMContentLoaded', function() {
            initializeAdminPanel();
        });
    </script>
</body>
</html> 