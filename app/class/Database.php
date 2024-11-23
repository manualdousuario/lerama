<?php
namespace Src;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct($config)
    {
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->connection = new PDO($dsn, $config['username'], $config['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Database Connection Failed: ' . $e->getMessage());
        }
    }

    public static function getInstance($config)
    {
        if (self::$instance === null) {
            self::$instance = new Database($config);
        }
        return self::$instance->getConnection();
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
