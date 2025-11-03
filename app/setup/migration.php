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
    
    $queries = [];
    $currentQuery = '';
    $inTrigger = false;
    
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        if (empty($trimmedLine) || strpos($trimmedLine, '--') === 0) {
            continue;
        }
        
        if (preg_match('/CREATE\s+(TRIGGER|PROCEDURE|FUNCTION)/i', $trimmedLine)) {
            $inTrigger = true;
        }
        
        $currentQuery .= $line . "\n";
        
        if ($inTrigger) {
            if (preg_match('/END\s*;/i', $trimmedLine)) {
                $queries[] = trim($currentQuery);
                $currentQuery = '';
                $inTrigger = false;
            }
        } else {
            if (substr($trimmedLine, -1) === ';') {
                $queries[] = trim($currentQuery);
                $currentQuery = '';
            }
        }
    }
    
    if (!empty(trim($currentQuery))) {
        $queries[] = trim($currentQuery);
    }

    try {
        $containsDDL = false;
        foreach ($queries as $query) {
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
            if (!empty($query)) {
                try {
                    $conn->exec($query);
                } catch (PDOException $queryEx) {
                    echo "Query #" . ($index + 1) . " failed: " . substr($query, 0, 100) . "..." . PHP_EOL;
                    throw $queryEx;
                }
            }
        }
        
        if (!$containsDDL) {
            $conn->commit();
        }
        
        if ($migrationName !== null && tableExists($conn, 'migrations')) {
            registerMigration($conn, $migrationName);
        }
        
        return true;
    } catch (PDOException $e) {
        if (!$containsDDL) {
            try {
                $conn->rollBack();
            } catch (PDOException $rollbackEx) {
                // Ignore rollback errors
            }
        }
        echo "Error executing query: " . $e->getMessage() . PHP_EOL;
        return false;
    }
}

try {
    $envFile = '../.env';
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

    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    $hasAnyTables = count($tables) > 0;
    
    if (!$hasAnyTables) {
        echo "No migrations registered. Running initial.sql..." . PHP_EOL;
        
        $initialFile = __DIR__ . '/initial.sql';
        if (!file_exists($initialFile)) {
            throw new Exception("Initial schema file not found: {$initialFile}");
        }
        
        if (executeSqlFile($conn, $initialFile)) {
            echo "Initial schema executed successfully" . PHP_EOL;
        } else {
            throw new Exception("Failed to execute initial schema");
        }
    }
    
    $migrationFiles = glob(__DIR__ . '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].sql');
    
    if (empty($migrationFiles)) {
        echo "No migration files found" . PHP_EOL;
    } else {
        sort($migrationFiles);
        
        echo "Found " . count($migrationFiles) . " migration file(s)" . PHP_EOL;
        
        foreach ($migrationFiles as $filePath) {
            $fileName = basename($filePath);
            $migrationKey = pathinfo($fileName, PATHINFO_FILENAME);
            
            if (!migrationExists($conn, $migrationKey)) {
                echo "Running migration: {$migrationKey}..." . PHP_EOL;
                
                if (executeSqlFile($conn, $filePath, $migrationKey)) {
                    echo "Migration {$migrationKey} executed successfully" . PHP_EOL;
                } else {
                    throw new Exception("Failed to execute migration: {$migrationKey}");
                }
            } else {
                echo "Migration {$migrationKey} already applied" . PHP_EOL;
            }
        }
    }
    
    echo "Database migration completed successfully" . PHP_EOL;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}