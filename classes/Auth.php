<?php

class Auth {
    private $mysqli;
    private $realIpAddress;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->realIpAddress = $_SERVER['REMOTE_ADDR'];
    }

public function generateToken() {
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = md5(uniqid(mt_rand(), true));
    }

    // Ha $_POST['token'] nincs beállítva, akkor ne próbálja összehasonlítani, csak ha létezik
    if (isset($_POST['token']) && $_POST['token'] !== $_SESSION['token']) {
        unset($_SESSION['token']);
        $_SESSION['token'] = md5(uniqid(mt_rand(), true));
    }
}


    public function validateAddress($address) {
        if (strlen($address) < 25 || strlen($address) > 80) {
            return "The Zero address doesn't look valid.";
        }
        if (substr($address, 0, 2) !== 't1') {
            return "Error. Only zerocoin addresses are allowed.";
        }
        return '';
    }

public function registerOrLoginUser($address) {
    $address = $this->mysqli->real_escape_string(trim($address));

    // Ellenőrizzük, hogy a felhasználó létezik-e
    $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE LOWER(address) = LOWER(?) LIMIT 1");
    $stmt->bind_param("s", $address);
    $stmt->execute();
    $stmt->store_result();
    $userExists = $stmt->num_rows;
    $stmt->bind_result($userID);
    $stmt->fetch();
    $stmt->close();

    $timestamp = time();
    $ip = $this->realIpAddress;
    $referredBy = isset($_SESSION['referral_id']) ? intval($_SESSION['referral_id']) : 0;

if ($userExists) {
    $_SESSION['address'] = $userID;
    
    $updateStmt = $this->mysqli->prepare("UPDATE users SET last_activity = ?, ip_address = ? WHERE id = ?");
    $updateStmt->bind_param("isi", $timestamp, $ip, $userID);
    
    $updateStmt->close();
    header("Location: dashboard");
    exit;
} else {
        // Ha nincs referral ID a session-ben, nézzük meg az URL-t
        if (isset($_GET['ref']) && is_numeric($_GET['ref'])) {
            $referredBy = intval($_GET['ref']);
            $_SESSION['referral_id'] = $referredBy; // Elmentjük a session-be
        }

        // Új felhasználó beszúrása
        $insertStmt = $this->mysqli->prepare("INSERT INTO users (address, ip_address, balance, joined, last_activity, referred_by, last_claim) VALUES (?, ?, 0, ?, ?, ?, 0)");
        $insertStmt->bind_param("ssiii", $address, $ip, $timestamp, $timestamp, $referredBy);
        $insertStmt->execute();
        $_SESSION['address'] = $insertStmt->insert_id;
        $insertStmt->close();
        header("Location: dashboard");
        exit;
    }
}


    public function handleRegistration() {
        if (!isset($_POST['address'])) {
            return '';
        }

        $this->generateToken();
        $address = trim($_POST['address']);
        
        if (empty($address)) {
            return "The Zero address field can't be blank.";
        }

        $alertMessage = $this->validateAddress($address);
        if (!empty($alertMessage)) {
            return $alertMessage;
        }

        return $this->registerOrLoginUser($address);
    }
}
