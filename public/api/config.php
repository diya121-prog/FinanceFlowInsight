<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $database_url = getenv('DATABASE_URL');
            
            if ($database_url && !empty(trim($database_url))) {
                if (strpos($database_url, 'postgresql://') === 0 || strpos($database_url, 'postgres://') === 0) {
                    $parsed = parse_url($database_url);
                    $host = $parsed['host'] ?? 'localhost';
                    $port = $parsed['port'] ?? 5432;
                    $dbname = ltrim($parsed['path'] ?? '', '/');
                    $username = $parsed['user'] ?? 'postgres';
                    $password = $parsed['pass'] ?? '';
                    
                    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                    $this->conn = new PDO($dsn, $username, $password);
                } else {
                    $this->conn = new PDO($database_url);
                }
            } else {
                $host = getenv('PGHOST') ?: 'localhost';
                $db_name = getenv('PGDATABASE') ?: 'postgres';
                $username = getenv('PGUSER') ?: 'postgres';
                $password = getenv('PGPASSWORD') ?: '';
                $port = getenv('PGPORT') ?: '5432';
                
                $dsn = "pgsql:host={$host};port={$port};dbname={$db_name}";
                $this->conn = new PDO($dsn, $username, $password);
            }
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            throw $e;
        }
        return $this->conn;
    }
}

class Config {
    public static $JWT_SECRET;
    public static $UPLOAD_DIR = __DIR__ . '/../uploads/';
    
    public static function init() {
        self::$JWT_SECRET = getenv('SESSION_SECRET') ?: 'your-secret-key-change-in-production';
        
        if (!file_exists(self::$UPLOAD_DIR)) {
            mkdir(self::$UPLOAD_DIR, 0777, true);
        }
    }
}

Config::init();

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function sendError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit();
}
