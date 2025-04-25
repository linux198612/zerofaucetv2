<?php

class AdminConfig {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    // Beállítások lekérése
    public function get($name) {
        $stmt = $this->mysqli->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['value'] ?? null;
    }

    // Beállítások mentése
    public function set($name, $value) {
        $stmt = $this->mysqli->prepare("UPDATE settings SET value = ? WHERE name = ?");
        $stmt->bind_param("ss", $value, $name);
        $stmt->execute();
        $stmt->close();
    }

    // Admin jelszó módosítása
    public function changePassword($username, $currentPassword, $newPassword) {
        $stmt = $this->mysqli->prepare("SELECT password_hash FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            if (password_verify($currentPassword, $row['password_hash'])) {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $this->mysqli->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
                $stmt->bind_param("ss", $newPasswordHash, $username);
                $stmt->execute();
                $stmt->close();
                return true; // Sikeres jelszócsere
            }
        }
        return false; // Sikertelen jelszócsere
    }
}
?>
