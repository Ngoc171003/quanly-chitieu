<?php
// Database Connection Class
class Database {
    private $connection;
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $name = DB_NAME;
    
    public function connect() {
        $this->connection = new mysqli(
            $this->host,
            $this->user,
            $this->pass,
            $this->name
        );
        
        if ($this->connection->connect_error) {
            die("Connection Error: " . $this->connection->connect_error);
        }
        
        $this->connection->set_charset("utf8mb4");
        return $this->connection;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute query with prepared statements for security
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @param string $types Type string (s=string, i=int, d=double, b=blob) - auto-detect if not provided
     * @return mysqli_result|false
     */
    public function execute($sql, $params = [], $types = '') {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("SQL Prepare Error: " . $this->connection->error);
            }

            if (!empty($params)) {
                // Auto-detect types if not provided
                if (empty($types)) {
                    $types = $this->detectTypes($params);
                }
                
                // Bind parameters with type checking
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new Exception("Bind Param Error: " . $stmt->error);
                }
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute Error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            return $result ? $result : $stmt;
            
        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());
            // Return empty result for INSERT/UPDATE/DELETE
            if (preg_match('/^(INSERT|UPDATE|DELETE)/i', $sql)) {
                return false;
            }
            // Return empty result set for SELECT
            return $this->connection->query("SELECT 1 WHERE 0");
        }
    }
    
    /**
     * Auto-detect parameter types
     * @param array $params
     * @return string Type string
     */
    private function detectTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
    
    /**
     * Legacy method - NOT recommended, for backward compatibility only
     */
    public function quote($value) {
        return "'" . $this->connection->real_escape_string($value) . "'";
    }
    
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function affectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Create global database instance
$db = new Database();
$db->connect();