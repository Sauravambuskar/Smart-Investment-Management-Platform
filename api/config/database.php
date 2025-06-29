<?php
/**
 * Database Configuration
 * SJA Foundation Investment Management Platform
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Load configuration from config file
        $config = $this->loadConfig();
        
        $this->host = $config['host'] ?? 'localhost';
        $this->port = $config['port'] ?? 3306;
        $this->db_name = $config['database'] ?? 'sja_foundation';
        $this->username = $config['username'] ?? 'root';
        $this->password = $config['password'] ?? '';
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/../../config/database.json';
        
        if (file_exists($configFile)) {
            $configData = file_get_contents($configFile);
            return json_decode($configData, true);
        }
        
        // Return default configuration if file doesn't exist
        return [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'sja_foundation',
            'username' => 'root',
            'password' => ''
        ];
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }

    public function testConnection($config) {
        try {
            $dsn = "mysql:host=" . $config['host'] . ";port=" . $config['port'] . ";charset=utf8mb4";
            
            $conn = new PDO($dsn, $config['user'], $config['password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Test if database exists, create if not
            $stmt = $conn->prepare("CREATE DATABASE IF NOT EXISTS `" . $config['name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt->execute();
            
            // Test connection to the specific database
            $dsn = "mysql:host=" . $config['host'] . ";port=" . $config['port'] . ";dbname=" . $config['name'] . ";charset=utf8mb4";
            $testConn = new PDO($dsn, $config['user'], $config['password']);
            $testConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            return true;
            
        } catch(PDOException $exception) {
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }
    }

    public function createDatabase($config) {
        try {
            // First connect without database name
            $dsn = "mysql:host=" . $config['host'] . ";port=" . $config['port'] . ";charset=utf8mb4";
            $conn = new PDO($dsn, $config['user'], $config['password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $stmt = $conn->prepare("CREATE DATABASE IF NOT EXISTS `" . $config['name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt->execute();
            
            return true;
            
        } catch(PDOException $exception) {
            throw new Exception("Database creation failed: " . $exception->getMessage());
        }
    }

    public function executeSchema($config) {
        try {
            $dsn = "mysql:host=" . $config['host'] . ";port=" . $config['port'] . ";dbname=" . $config['name'] . ";charset=utf8mb4";
            $conn = new PDO($dsn, $config['user'], $config['password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Read and execute schema file
            $schemaFile = __DIR__ . '/../../database/schema.sql';
            
            if (!file_exists($schemaFile)) {
                throw new Exception("Schema file not found");
            }
            
            $schema = file_get_contents($schemaFile);
            
            // Remove comments and split into statements
            $schema = preg_replace('/--.*$/m', '', $schema);
            $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
            
            // Split by semicolon but be careful with compound statements
            $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $schema)));
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !preg_match('/^(SET|START|COMMIT)/', $statement)) {
                    try {
                        $conn->exec($statement . ';');
                    } catch (PDOException $e) {
                        // Log the error but continue with other statements
                        error_log("SQL Error in statement: " . substr($statement, 0, 100) . "... Error: " . $e->getMessage());
                        // Only throw if it's a critical error
                        if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
                            throw $e;
                        }
                    }
                }
            }
            
            return true;
            
        } catch(PDOException $exception) {
            throw new Exception("Schema execution failed: " . $exception->getMessage());
        }
    }

    public function saveConfig($config) {
        $configDir = __DIR__ . '/../../config';
        $configFile = $configDir . '/database.json';
        
        // Create config directory if it doesn't exist
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $configData = [
            'host' => $config['host'],
            'port' => (int)$config['port'],
            'database' => $config['name'],
            'username' => $config['user'],
            'password' => $config['password']
        ];
        
        $result = file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            throw new Exception("Failed to save database configuration");
        }
        
        return true;
    }
}
?> 