<?php

class Config {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function get($name) {
        $stmt = $this->mysqli->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['value'] ?? null;
    }
    
    public function set($name, $value) {
        $stmt = $this->mysqli->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->bind_param("sss", $name, $value, $value);
        return $stmt->execute();
    }
}
