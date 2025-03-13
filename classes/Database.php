<?php

class Database {
    private static $instance = null;
    private $mysqli;
    private $host = "localhost";
    private $username = "";
    private $password = "";
    private $database = "";

    private function __construct() {
        $this->mysqli = new mysqli($this->host, $this->username, $this->password, $this->database);
        
        if ($this->mysqli->connect_error) {
            die("Database connection failed: " . $this->mysqli->connect_error);
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->mysqli;
    }

}
?>
