<?php
/**
 * Database Configuration and Connection Management
 * Supports PostgreSQL with vector embeddings via pgvector extension
 */

function getDbConnection() {
    static $connection = null;
    
    if ($connection === null) {
        // Debug: Log environment variables for troubleshooting
        error_log("Database connection attempt - Environment variables check:");
        error_log("CLOUDRON_POSTGRESQL_HOST: " . ($_ENV['CLOUDRON_POSTGRESQL_HOST'] ?? 'NOT_SET'));
        error_log("CLOUDRON_POSTGRESQL_PORT: " . ($_ENV['CLOUDRON_POSTGRESQL_PORT'] ?? 'NOT_SET'));
        
        // Use Cloudron environment variables with better fallback handling
        $host = getenv('CLOUDRON_POSTGRESQL_HOST') ?: $_ENV['CLOUDRON_POSTGRESQL_HOST'] ?? $_ENV['POSTGRESQL_HOST'] ?? 'postgresql';
        $port = getenv('CLOUDRON_POSTGRESQL_PORT') ?: $_ENV['CLOUDRON_POSTGRESQL_PORT'] ?? $_ENV['POSTGRESQL_PORT'] ?? '5432';
        $database = getenv('CLOUDRON_POSTGRESQL_DATABASE') ?: $_ENV['CLOUDRON_POSTGRESQL_DATABASE'] ?? $_ENV['POSTGRESQL_DATABASE'] ?? 'app';
        $username = getenv('CLOUDRON_POSTGRESQL_USERNAME') ?: $_ENV['CLOUDRON_POSTGRESQL_USERNAME'] ?? $_ENV['POSTGRESQL_USERNAME'] ?? 'postgres';
        $password = getenv('CLOUDRON_POSTGRESQL_PASSWORD') ?: $_ENV['CLOUDRON_POSTGRESQL_PASSWORD'] ?? $_ENV['POSTGRESQL_PASSWORD'] ?? '';
        
        error_log("Attempting connection to: {$host}:{$port} database={$database} username={$username}");
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        
        try {
            $connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10, // Longer timeout for Cloudron
            ]);
            
            error_log("Database connection successful!");
            
            // Initialize database schema if needed
            initializeDatabase($connection);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            error_log("DSN used: " . $dsn);
            
            // For health checks and CLI, return null rather than throwing
            if (php_sapi_name() === 'cli' || 
                strpos($_SERVER['REQUEST_URI'] ?? '', 'health') !== false ||
                strpos($_SERVER['SCRIPT_NAME'] ?? '', 'health') !== false) {
                return null;
            }
            
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $connection;
}

function initializeDatabase($db) {
    try {
        // Enable pgvector extension if available
        try {
            $db->exec("CREATE EXTENSION IF NOT EXISTS vector");
        } catch (PDOException $e) {
            error_log("pgvector extension not available: " . $e->getMessage());
        }
        
        // Create admin_users table
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP
            )
        ");
        
        // Create applications table
        $db->exec("
            CREATE TABLE IF NOT EXISTS applications (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                repository VARCHAR(500) NOT NULL,
                branch VARCHAR(100) DEFAULT 'main',
                directory VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'inactive',
                deployed BOOLEAN DEFAULT false,
                last_deploy TIMESTAMP,
                config JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create deployments table for deployment history
        $db->exec("
            CREATE TABLE IF NOT EXISTS deployments (
                id SERIAL PRIMARY KEY,
                application_id INTEGER REFERENCES applications(id) ON DELETE CASCADE,
                commit_hash VARCHAR(40),
                status VARCHAR(50) DEFAULT 'pending',
                log TEXT,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP
            )
        ");
        
        // Create database_schemas table for schema tracking
        $db->exec("
            CREATE TABLE IF NOT EXISTS database_schemas (
                id SERIAL PRIMARY KEY,
                application_id INTEGER REFERENCES applications(id) ON DELETE CASCADE,
                schema_name VARCHAR(255) NOT NULL,
                table_name VARCHAR(255) NOT NULL,
                column_info JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create embeddings table for vector storage
        $db->exec("
            CREATE TABLE IF NOT EXISTS embeddings (
                id SERIAL PRIMARY KEY,
                application_id INTEGER REFERENCES applications(id) ON DELETE CASCADE,
                content TEXT NOT NULL,
                metadata JSONB,
                embedding vector(1536),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create indexes for better performance
        $db->exec("CREATE INDEX IF NOT EXISTS idx_applications_status ON applications(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_applications_deployed ON applications(deployed)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_deployments_app_id ON deployments(application_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_embeddings_app_id ON embeddings(application_id)");
        
        // Try to create vector similarity index if pgvector is available
        try {
            $db->exec("CREATE INDEX IF NOT EXISTS idx_embeddings_vector ON embeddings USING ivfflat (embedding vector_cosine_ops)");
        } catch (PDOException $e) {
            // Vector index creation failed, probably no pgvector extension
            error_log("Vector index creation failed: " . $e->getMessage());
        }
        
        // Create settings table
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key VARCHAR(255) PRIMARY KEY,
                value TEXT,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default settings if they don't exist
        $defaultSettings = [
            'app_name' => 'PHP Git App Manager',
            'max_deployments' => '10',
            'enable_vector_search' => 'true',
            'vector_dimension' => '1536',
            'embedding_model' => 'text-embedding-3-small'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT (key) DO NOTHING");
            $stmt->execute([$key, $value]);
        }
        
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        throw new Exception("Database initialization failed");
    }
}

function checkVectorSupport($db) {
    try {
        $stmt = $db->query("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'vector') as has_vector");
        $result = $stmt->fetch();
        return $result['has_vector'] ?? false;
    } catch (PDOException $e) {
        return false;
    }
}

function getTableStructure($db, $tableName, $schemaName = 'public') {
    try {
        $stmt = $db->prepare("
            SELECT 
                column_name,
                data_type,
                is_nullable,
                column_default,
                character_maximum_length,
                numeric_precision,
                numeric_scale
            FROM information_schema.columns 
            WHERE table_schema = ? AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$schemaName, $tableName]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get table structure: " . $e->getMessage());
        return [];
    }
}

function getAllTables($db, $schemaName = 'public') {
    try {
        $stmt = $db->prepare("
            SELECT 
                table_name,
                table_type
            FROM information_schema.tables 
            WHERE table_schema = ?
            ORDER BY table_name
        ");
        $stmt->execute([$schemaName]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get tables: " . $e->getMessage());
        return [];
    }
}

function getAllSchemas($db) {
    try {
        $stmt = $db->query("
            SELECT schema_name 
            FROM information_schema.schemata 
            WHERE schema_name NOT IN ('information_schema', 'pg_catalog', 'pg_toast', 'pg_temp_1', 'pg_toast_temp_1')
            ORDER BY schema_name
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get schemas: " . $e->getMessage());
        return [];
    }
}
?> 