<?php

class Session {
    private $mysqli;
    private $user;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->checkSession();
    }

    private function checkSession() {
        // Ellenőrizzük a MySQL kapcsolatot
        if (!$this->mysqli->ping()) {
            $this->mysqli->close();
            $this->mysqli = Database::getInstance()->getConnection();
        }

        if (!isset($_SESSION['user_id'])) { // Az address helyett az ID-t ellenőrizzük
            return;
        }

        $userId = $_SESSION['user_id']; // Az ID-t használjuk
        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ?");
        if (!$stmt) {
            // Hiba esetén újra létrehozzuk a kapcsolatot
            $this->mysqli = Database::getInstance()->getConnection();
            $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ?");
        }
        $stmt->bind_param("i", $userId); // Az ID-t használjuk
        $stmt->execute();
        $result = $stmt->get_result();
        $this->user = $result->fetch_assoc();
        $stmt->close();

        if (!$this->user) {
            unset($_SESSION['user_id']); // Az ID-t töröljük
            header("Location: index.php");
            exit;
        }
    }

    public function getUser() {
        return $this->user;
    }
}
