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

function executeSqlFile($conn, $filePath) {
    $sql = file_get_contents($filePath);
    $queries = explode(';', $sql);

    try {
        $conn->beginTransaction();
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $conn->exec($query . ';');
            }
        }
        
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "Error executing query: " . $e->getMessage() . PHP_EOL;
        return false;
    }
}

try {
    $currentDir = __DIR__;
    $rootDir = dirname($currentDir);
    
    $envFile = '/app/.env';
    $env = parseEnvFile($envFile);
    
    if (empty($env)) {
        throw new Exception("Failed to parse .env file or file is empty");
    }

    $dbHost = $env['DB_HOST'] ?? 'localhost';
    $dbName = $env['DB_NAME'] ?? '';
    $dbUser = $env['DB_USER'] ?? '';
    $dbPass = $env['DB_PASS'] ?? '';
    $dbPort = $env['DB_PORT'] ?? '3306';
    
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

        $schemaFile = $rootDir . '/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: {$schemaFile}");
        }
        
        if (executeSqlFile($conn, $schemaFile)) {
            echo "Schema executed successfully" . PHP_EOL;
        } else {
            throw new Exception("Failed to execute schema");
        }
    }
    
    echo "Database check completed successfully" . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}