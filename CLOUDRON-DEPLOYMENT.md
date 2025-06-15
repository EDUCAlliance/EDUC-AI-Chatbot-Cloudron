# PHP Git App Manager - Cloudron Deployment

## 🔧 Recent Fixes Applied

The following critical issues were resolved to ensure proper Cloudron deployment:

### ✅ **Port Configuration**
- **Fixed**: Changed from port 3000 to Cloudron's standard port 8000
- **Files Updated**: `CloudronManifest.json`, `apache/app.conf`, `Dockerfile`

### ✅ **Apache Configuration** 
- **Fixed**: Updated to follow [Cloudron's official Apache guidelines](https://docs.cloudron.io/packaging/cheat-sheet/#apache)
- **Changes**:
  - Error logs now go to `/dev/stderr` (not `/var/log/apache2/error.log`)
  - Set `MaxSpareServers` to 5 for optimal performance
  - Removed read-only filesystem conflicts

### ✅ **PHP Session Handling**
- **Fixed**: Moved PHP sessions from `/var/lib/php/sessions` to `/run/php/sessions`
- **Reason**: Cloudron containers have read-only filesystems except for `/tmp`, `/run`, and `/app/data`

### ✅ **Database Connection Resilience**
- **Fixed**: Database connection now handles startup scenarios gracefully
- **Features**:
  - Uses Cloudron environment variables (`CLOUDRON_POSTGRESQL_*`)
  - Health checks don't fail if database isn't ready immediately
  - Timeout protection for startup scenarios

### ✅ **Health Check System**
- **Added**: Fast health check endpoint at `/health.php`
- **Added**: Comprehensive debug page at `/debug.php`
- **Benefit**: Cloudron can verify app health without waiting for full application startup

## 🚀 **Deployment Process**

### 1. Pre-Deployment Verification
```bash
# Run the built-in test script
./test-cloudron.sh
```

### 2. Build & Push Docker Image
```bash
# Build the image
docker build -t your-registry/php-git-manager:latest .

# Push to your registry
docker push your-registry/php-git-manager:latest
```

### 3. Deploy to Cloudron
- Upload the `CloudronManifest.json` 
- Set your Docker image URL
- Install via Cloudron dashboard

## 🔗 **Access Points**

After successful deployment:

| URL | Purpose | Description |
|-----|---------|-------------|
| `/` | Main Application | Entry point - shows deployed apps or setup wizard |
| `/server-admin/` | Admin Panel | Configure Git repositories and manage deployments |
| `/health.php` | Health Check | Quick status check for Cloudron monitoring |
| `/debug.php` | Debug Info | Comprehensive system diagnostics |

## 📊 **System Requirements**

- **Memory**: 524MB (configured in manifest)
- **Addons**: PostgreSQL, Local Storage
- **Port**: 8000 (Cloudron standard)
- **PHP Extensions**: pgsql, curl, mbstring, xml, zip, gd

## 🗃️ **Database Features**

- **PostgreSQL** with vector embeddings support (pgvector)
- **Auto-initialization** of schema on first run
- **Multi-application** support with isolated deployments
- **Activity logging** and deployment history

## 🐛 **Troubleshooting**

If deployment fails:

1. **Check logs**: `cloudron logs`
2. **Debug mode**: `cloudron debug` (enables read-write filesystem)
3. **Health check**: Visit `/health.php` to see system status
4. **Debug info**: Visit `/debug.php` for comprehensive diagnostics

### Common Issues:

- **Database connection errors**: Usually resolve after a few minutes as PostgreSQL addon initializes
- **Permission errors**: Fixed by proper `/run` directory setup in `start.sh`
- **Apache startup errors**: Check `/debug.php` for configuration validation

## 🎯 **Application Features**

- **Git Repository Management**: Deploy PHP apps from any Git repository
- **Branch Selection**: Choose specific branches for deployment
- **Database Explorer**: Browse PostgreSQL schemas and data
- **Vector Embeddings**: Built-in support for AI/ML applications
- **Activity Monitoring**: Track deployments and system activity
- **Multi-App Support**: Deploy and manage multiple applications

## 📝 **Configuration**

The application configures itself automatically using Cloudron environment variables:
- `CLOUDRON_POSTGRESQL_*` - Database connection
- `CLOUDRON_APP_DOMAIN` - Application domain
- File storage in `/app/data` (backed up by Cloudron)

---

*This application follows Cloudron best practices for security, logging, and filesystem usage.* 