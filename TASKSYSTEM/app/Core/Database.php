<?php

class Database
{
    // Singleton instance
    private static ?Database $instance = null;

    // mysqli connection
    private mysqli $connection;

    // Private constructor (only this class can create instance)
    private function __construct()
    {
        // Load DB config
        $config = require __DIR__ . '/../Config/database.php';

        // Create MySQLi connection
        $this->connection = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port'] ?? 3306
        );

        // Check for connection errors
        if ($this->connection->connect_error) {
            die('Database connection failed: ' . $this->connection->connect_error);
        }

        // Set charset
        $this->connection->set_charset('utf8mb4');
    }

    /**
     * Get the single Database instance
     * @return mysqli
     */
    public static function getInstance(): mysqli
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserializing
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton Database");
    }
}