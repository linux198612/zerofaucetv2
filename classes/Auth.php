<?php

class Auth {
    private $mysqli;
    private $config;
    private $realIpAddress;

    public function __construct($mysqli, $config) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(); // Munkamenet indítása, ha még nem történt meg
        }
        $this->mysqli = $mysqli;
        $this->config = $config;
        $this->realIpAddress = $_SERVER['REMOTE_ADDR'];
    }

    public function verifyCaptcha($response) {
        $captchaVerifyUrl = "https://hcaptcha.com/siteverify";
        $captchaData = [
            'secret' => $this->config->get('hcaptcha_sec_key'),
            'response' => $response,
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($captchaData),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($captchaVerifyUrl, false, $context);
        $captchaResult = json_decode($result, true);

        return $captchaResult['success'] ?? false;
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

    public function login($username, $password) {
    	
    	    if (!isset($_POST['h-captcha-response']) || !$this->verifyCaptcha($_POST['h-captcha-response'])) {
        return ['status' => 'error', 'message' => 'Captcha verification failed. Please try again.'];
    }
        // Ellenőrizzük, hogy a felhasználó bannolva van-e
        $stmt = $this->mysqli->prepare("SELECT 1 FROM banned_username WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return "Account Banned."; // Bannolt felhasználó hibaüzenet
        }
        $stmt->close();

        $stmt = $this->mysqli->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($userId, $passwordHash);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($password, $passwordHash)) {
            $_SESSION['user_id'] = $userId;

            // Frissítjük az IP-címet és a last_activity mezőt az adatbázisban
            $ipAddress = $this->realIpAddress;
            $currentTimestamp = time(); // Aktuális Unix timestamp
            $updateStmt = $this->mysqli->prepare("UPDATE users SET ip_address = ?, last_activity = ? WHERE id = ?");
            $updateStmt->bind_param("sii", $ipAddress, $currentTimestamp, $userId);
            $updateStmt->execute();
            $updateStmt->close();

            return true;
        }
        return false;
    }

public function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Több IP-cím esetén az első a kliens IP-je
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}


public function register($username, $email, $password) {
    // HoneyPot check
    if (!empty($_POST['honeypot'])) {
        return ['status' => 'error', 'message' => 'Bot detected. Registration failed.'];
    }

    // hCaptcha check
    if (!isset($_POST['h-captcha-response']) || !$this->verifyCaptcha($_POST['h-captcha-response'])) {
        return ['status' => 'error', 'message' => 'Captcha verification failed. Please try again.'];
    }

    // Username length check
    if (strlen($username) < 8) {
        return ['status' => 'error', 'message' => 'Username must be at least 8 characters long.'];
    }

    // Username cannot be an email
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'message' => 'Username cannot be an email address.'];
    }

    // Email format check
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'message' => 'Please provide a valid email address.'];
    }

    // Check if username or email already exists
    $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Username or email already exists.'];
    }
    $stmt->close();

    // IP-based registration limitation
    $ipAddress = $this->getClientIP();
    $timeLimit = time() - 12 * 60 * 60; // 12 hours

    $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE ip_address = ? AND joined >= ?");
    $stmt->bind_param("si", $ipAddress, $timeLimit);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        return ['status' => 'error', 'message' => 'Double registration blocked.'];
    }
    $stmt->close();

    // Hashing the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $joined = time();

    // Adding the user to the database
    $stmt = $this->mysqli->prepare("INSERT INTO users (username, email, password_hash, ip_address, joined) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $email, $hashedPassword, $ipAddress, $joined);

    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Registration successful.'];
    } else {
        return ['status' => 'error', 'message' => 'Registration failed. Please try again later.'];
    }
}

    public function sendPasswordRecoveryEmail($email) {
        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($userId);
        $stmt->fetch();
        $stmt->close();

        if (!$userId) {
            return false; // Email not found
        }

        // Generate a new password
        $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20); // 20-character random password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update the user's password in the database
        $stmt = $this->mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $newPasswordHash, $userId);
        $stmt->execute();
        $stmt->close();

        // Lekérjük az SMTP adatokat a settings táblából
        $smtpServer = $this->getSetting('smtp_server');
        $smtpPort = $this->getSetting('smtp_port');
        $smtpUser = $this->getSetting('smtp_user');
        $smtpPass = $this->getSetting('smtp_pass');
        $smtpSSL = $this->getSetting('smtp_ssl');

        if (!$smtpServer || !$smtpPort || !$smtpUser || !$smtpPass) {
            error_log("SMTP settings are incomplete.");
            return false;
        }

        // Send the recovery email using fsockopen
        $socket = fsockopen(($smtpSSL === 'on' ? "ssl://$smtpServer" : $smtpServer), $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            error_log("Failed to connect to SMTP server: $errstr ($errno)");
            return false;
        }

        // SMTP kommunikáció
        fwrite($socket, "EHLO $smtpServer\r\n");
        fgets($socket, 512);

        fwrite($socket, "AUTH LOGIN\r\n");
        fgets($socket, 512);

        fwrite($socket, base64_encode($smtpUser) . "\r\n");
        fgets($socket, 512);

        fwrite($socket, base64_encode($smtpPass) . "\r\n");
        fgets($socket, 512);

        fwrite($socket, "MAIL FROM: <$smtpUser>\r\n");
        fgets($socket, 512);

        fwrite($socket, "RCPT TO: <$email>\r\n");
        fgets($socket, 512);

        fwrite($socket, "DATA\r\n");
        fgets($socket, 512);

        $subject = "Password Recovery";
        $message = "Your password has been reset. Your new password is: $newPassword";

        $emailContent = "Subject: $subject\r\n";
        $emailContent .= "To: <$email>\r\n";
        $emailContent .= "From: <$smtpUser>\r\n";
        $emailContent .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $emailContent .= $message . "\r\n.\r\n";

        fwrite($socket, $emailContent);
        fgets($socket, 512);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private function getSetting($name) {
        $stmt = $this->mysqli->prepare("SELECT value FROM settings WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->bind_result($value);
        $stmt->fetch();
        $stmt->close();
        return $value;
    }
}
?>