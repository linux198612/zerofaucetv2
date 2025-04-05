<?php

class Auth {
    private $mysqli;
    private $realIpAddress;

    public function __construct($mysqli) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(); // Munkamenet indítása, ha még nem történt meg
        }
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
        // Tárca cím alapú belépés logikája
        // Ezt a metódust már nem használjuk az új username/password belépéshez.
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
        $stmt = $this->mysqli->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($userId, $passwordHash);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($password, $passwordHash)) {
            $_SESSION['user_id'] = $userId;

            // Frissítjük az IP-címet az adatbázisban
            $ipAddress = $this->realIpAddress;
            $updateStmt = $this->mysqli->prepare("UPDATE users SET ip_address = ? WHERE id = ?");
            $updateStmt->bind_param("si", $ipAddress, $userId);
            $updateStmt->execute();
            $updateStmt->close();

            return true;
        }
        return false;
    }

    public function register($username, $email, $password) {
        // Ellenőrizzük, hogy a username már létezik-e
        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return "The username is already in use."; // Megszakítjuk a regisztrációt
        }
        $stmt->close();

        // Ellenőrizzük, hogy az email már létezik-e
        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return "The email address is already in use."; // Megszakítjuk a regisztrációt
        }
        $stmt->close();

        // Ha minden rendben, regisztráljuk a felhasználót
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->mysqli->prepare("INSERT INTO users (username, email, password_hash, address, joined) VALUES (?, ?, ?, '', UNIX_TIMESTAMP())");
        $stmt->bind_param("sss", $username, $email, $passwordHash);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $stmt->close();
            return true;
        }
        $stmt->close();
        return "An error occurred during registration.";
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
