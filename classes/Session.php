<?php

class Session {
    private $mysqli;
    private $user;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->checkSession();
    }

    private function checkSession() {
        if (!isset($_SESSION['address'])) {
            return;
        }

        $address = $_SESSION['address'];
        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("s", $address);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->user = $result->fetch_assoc();
        $stmt->close();

        if (!$this->user) {
            unset($_SESSION['address']);
            header("Location: index.php");
            exit;
        }
    }

    public function getUser() {
        return $this->user;
    }
}
