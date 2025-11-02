<?php

function parseEnvFile($filePath) {
    $env = [];
    if (file_exists($filePath)) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value);
            }
        }
    }
    return $env;
}

function tableExists($conn, $tableName) {
    $escapedTableName = $conn->quote($tableName);
    $escapedTableName = substr($escapedTableName, 1, -1);
    
    $result = $conn->query("SHOW TABLES LIKE '{$escapedTableName}'");
    return $result->rowCount() > 0;
}

function migrationExists($conn, $migrationName) {
    try {
        $stmt = $conn->prepare("SELECT id FROM migrations WHERE migration = :migration");
        $stmt->execute(['migration' => $migrationName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function registerMigration($conn, $migrationName) {
    try {
        $stmt = $conn->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
        $stmt->execute(['migration' => $migrationName]);
        return true;
    } catch (PDOException $e) {
        echo "Error registering migration: " . $e->getMessage() . PHP_EOL;
        return false;
    }
}

function executeSqlFile($conn, $filePath, $migrationName = null) {
    $sql = file_get_contents($filePath);
    $queries = explode(';', $sql);

    try {
        $containsDDL = false;
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $upperQuery = strtoupper($query);
                if (preg_match('/^\s*(ALTER|CREATE|DROP)\s+/i', $upperQuery)) {
                    $containsDDL = true;
                    break;
                }
            }
        }
        
        if (!$containsDDL) {
            $conn->beginTransaction();
        }
        
        foreach ($queries as $index => $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $conn->exec($query . ';');
                } catch (PDOException $queryEx) {
                    // Show which query failed for debugging
                    echo "Query #" . ($index + 1) . " failed: " . substr($query, 0, 100) . "..." . PHP_EOL;
                    throw $queryEx;
                }
            }
        }
        
        if (!$containsDDL) {
            $conn->commit();
        }
        
        // Register migration if name provided
        if ($migrationName !== null && tableExists($conn, 'migrations')) {
            registerMigration($conn, $migrationName);
        }
        
        return true;
    } catch (PDOException $e) {
        if (!$containsDDL) {
            try {
                $conn->rollBack();
            } catch (PDOException $rollbackEx) {
                // Ignore rollback errors (e.g., if no active transaction)
            }
        }
        echo "Error executing query: " . $e->getMessage() . PHP_EOL;
        return false;
    }
}

try {
    $envFile = '/app/.env';
    $env = parseEnvFile($envFile);
    
    if (empty($env)) {
        throw new Exception("Failed to parse .env file or file is empty");
    }

    $dbHost = $env['LERAMA_DB_HOST'] ?? 'localhost';
    $dbName = $env['LERAMA_DB_NAME'] ?? '';
    $dbUser = $env['LERAMA_DB_USER'] ?? '';
    $dbPass = $env['LERAMA_DB_PASS'] ?? '';
    $dbPort = $env['LERAMA_DB_PORT'] ?? 3306;
    
    if (empty($dbName) || empty($dbUser)) {
        throw new Exception("Database configuration is incomplete in .env file");
    }

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        $conn = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $e) {
        throw new Exception("Connection failed: " . $e->getMessage());
    }
    
    echo "Connected to database successfully" . PHP_EOL;

    // Check if base tables exist, if not run migration-initial.sql
    $feedsTableExists = tableExists($conn, 'feeds');
    $feedItemsTableExists = tableExists($conn, 'feed_items');
    
    if (!$feedsTableExists || !$feedItemsTableExists) {
        echo "Database tables do not exist. Running migration-initial.sql..." . PHP_EOL;

        $schemaFile = __DIR__ . '/migration-initial.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: {$schemaFile}");
        }
        
        if (executeSqlFile($conn, $schemaFile)) {
            echo "Schema executed successfully" . PHP_EOL;
        } else {
            throw new Exception("Failed to execute schema");
        }
    }
    
    // Define migrations to run in order
    $migrations = [
        'migration-20250527.sql' => '2025-05-27',
        'migration-20251029.sql' => '2025-10-29'
    ];

    echo "Registering previously applied migrations..." . PHP_EOL;
    
    $stmt = $conn->query("SHOW COLUMNS FROM feeds LIKE 'retry_count'");
    if ($stmt->rowCount() > 0) {
        registerMigration($conn, '2025-05-27');
        echo "Registered migration: 2025-05-27-retry-fields (already applied)" . PHP_EOL;
    }
    
    if (tableExists($conn, 'categories') && tableExists($conn, 'tags') && tableExists($conn, 'migrations')) {
        registerMigration($conn, '2025-10-29');
        echo "Registered migration: 2025-10-29 (already applied)" . PHP_EOL;
    }
    
    // Run pending migrations
    foreach ($migrations as $file => $name) {
        if (!migrationExists($conn, $name)) {
            $migrationFile = __DIR__ . '/' . $file;
            
            if (!file_exists($migrationFile)) {
                echo "Migration file not found: {$migrationFile}" . PHP_EOL;
                continue;
            }
            
            echo "Running migration: {$name}..." . PHP_EOL;
            
            if (executeSqlFile($conn, $migrationFile, $name)) {
                echo "Migration {$name} executed successfully" . PHP_EOL;
            } else {
                throw new Exception("Failed to execute migration: {$name}");
            }
        } else {
            echo "Migration {$name} already applied" . PHP_EOL;
        }
    }
    
    echo "Database check completed successfully" . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}