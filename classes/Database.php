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
            // Ha a kapcsolat nem sikerül, átirányítunk a karbantartási oldalra
            header("Location: /views/maintenance.php");
            exit();
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

        // Az adatbázis név lekérése
        public function getDbName() {
            return $this->database;
        }

}
?>
