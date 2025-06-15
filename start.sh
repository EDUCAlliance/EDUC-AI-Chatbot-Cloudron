#!/bin/bash

set -eu

echo "🚀 Starting PHP Git App Manager..."

# Create necessary directories for Cloudron
mkdir -p /run/app/sessions
mkdir -p /run/php/sessions
mkdir -p /app/code/apps
mkdir -p /app/code/admin
mkdir -p /app/code/assets
mkdir -p /app/data

# Set permissions (required for Apache+PHP on Cloudron)
chmod -R 775 /app/code/
chown -R www-data:www-data /app/code/
chown -R www-data:www-data /app/data/
chown -R www-data:www-data /run/app/sessions
chown -R www-data:www-data /run/php/sessions

# Configure git
git config --global --add safe.directory /app/code/public
git config --global user.email "admin@cloudron.app"
git config --global user.name "Cloudron App Manager"

# Initialize database connection check
echo "🗄️ Checking database connectivity..."
php -r "
try {
    require_once '/app/code/public/config/database.php';
    \$db = getDbConnection();
    echo 'Database connection successful!' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . PHP_EOL;
    echo 'Continuing startup - database will be initialized on first request.' . PHP_EOL;
}
"

echo "🌐 Starting Apache server..."

# Start Apache (following Cloudron documentation pattern)
APACHE_CONFDIR="" source /etc/apache2/envvars
rm -f "${APACHE_PID_FILE}"

# Test Apache configuration
echo "Testing Apache configuration..."
/usr/sbin/apache2 -t

echo "Apache configuration is valid. Starting server..."
exec /usr/sbin/apache2 -DFOREGROUND
