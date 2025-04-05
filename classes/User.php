<?php

class User {
    private $mysqli;
    private $id;
    private $data;
    private $config;

    public function __construct($mysqli, $userId) {
        $this->mysqli = $mysqli;
        $this->id = $userId;
        $this->loadUserData();
        $this->config = new Config($mysqli); 
    }

    private function loadUserData() {
        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->data = $result->fetch_assoc();
        $stmt->close();

        if (!$this->data) {
            error_log("Failed to load user data for user ID: {$this->id}"); // Debug log
        }
    }

    public function getUserData($key) {
        return $this->data[$key] ?? null;
    }

    
    // 📌 ÚJ! Frissíti az adatokat a memóriában is
    public function refreshUserData() {
        $this->loadUserData($this->data['id']);
    }
    
public function updateBalance($amount, $xp = 0) {
    $levelSystemEnabled = $this->config->get('level_system');

    // Ha OFF, akkor az XP-t 0-ra állítjuk
    if (!$levelSystemEnabled) {
        $xp = 0;
    }

    // Frissítjük az egyenleget és XP-t (ha van)
    $stmt = $this->mysqli->prepare("UPDATE users SET balance = balance + ?, xp = xp + ? WHERE id = ?");
    $stmt->bind_param("dii", $amount, $xp, $this->data['id']);
    $stmt->execute();
    $stmt->close();
}

    public function updateAddress($address) {
        $stmt = $this->mysqli->prepare("UPDATE users SET address = ? WHERE id = ?");
        $stmt->bind_param("si", $address, $this->id);
        $stmt->execute();
        $stmt->close();
    }

    public function getAddress() {
        return $this->data['address'] ?? null;
    }

}

