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

function columnExists($conn, $tableName, $columnName) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE :columnName");
        $stmt->execute(['columnName' => $columnName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function executeSqlFile($conn, $filePath) {
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
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $conn->exec($query . ';');
            }
        }
        
        if (!$containsDDL) {
            $conn->commit();
        }
        
        return true;
    } catch (PDOException $e) {
        if (!$containsDDL) {
            $conn->rollBack();
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

    $feedsTableExists = tableExists($conn, 'feeds');
    $feedItemsTableExists = tableExists($conn, 'feed_items');
    
    if ($feedsTableExists && $feedItemsTableExists) {
        echo "Database tables already exist. No action needed." . PHP_EOL;
    } else {
        echo "Database tables do not exist. Running schema.sql..." . PHP_EOL;

        $schemaFile = '/setup/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: {$schemaFile}");
        }
        
        if (executeSqlFile($conn, $schemaFile)) {
            echo "Schema executed successfully" . PHP_EOL;
        } else {
            throw new Exception("Failed to execute schema");
        }
    }
    
    if ($feedsTableExists) {
        $migrationNeeded = !columnExists($conn, 'feeds', 'retry_count') ||
                          !columnExists($conn, 'feeds', 'retry_proxy') ||
                          !columnExists($conn, 'feeds', 'paused_at');
        
        if ($migrationNeeded) {
            echo "Migration needed. Running migration-20250527.sql..." . PHP_EOL;
            
            $migrationFile = '/setup/migration-20250527.sql';
            if (!file_exists($migrationFile)) {
                throw new Exception("Migration file not found: {$migrationFile}");
            }
            
            if (executeSqlFile($conn, $migrationFile)) {
                echo "Migration executed successfully" . PHP_EOL;
            } else {
                throw new Exception("Failed to execute migration");
            }
        } else {
            echo "Migration already applied. No action needed." . PHP_EOL;
        }
    }
    
    echo "Database check completed successfully" . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}