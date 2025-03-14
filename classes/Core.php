<?php

class Core {
    private $mysqli;
    private $sessionTimeout = 1800; // 30 perc session timeout
    private $version = "1.30.3";
    private $config;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->config = new Config($mysqli);
        $this->checkPhpVersion();
        $this->startSession();
        $this->handleSessionTimeout();
        $this->initializeToken();
    }

    private function checkPhpVersion() {
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            die('This script requires PHP 8.2.0 or higher. Your version: ' . PHP_VERSION);
        }
    }

    private function startSession() {
        ini_set('session.gc_maxlifetime', $this->sessionTimeout);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function handleSessionTimeout() {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->sessionTimeout)) {
            session_unset();
            session_destroy();
            header("Location: ./");
            exit();
        }
        $_SESSION['last_activity'] = time();
    }

    private function initializeToken() {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = md5(uniqid(mt_rand(), true));
        }
    }

    // ✅ XSS elleni védelem (Sanitize Output)
    public static function sanitizeOutput($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    // ✅ CSRF token generálása
    public static function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // ✅ CSRF ellenőrzés minden POST kérés előtt
    public static function checkCsrfToken() {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die("CSRF támadás észlelve!");
            }
        }
    }

    // ✅ SQL Injection elleni védelem (real_escape_string csak ha szükséges)
    public function sanitizeInput($input) {
        return $this->mysqli->real_escape_string($input);
    }

    public function updateUserLevelAndXP($userId) {
        $levelStatus = $this->config->get('level_system'); // 🔹 ÚJ! A `Config` osztályból kérjük le

        if ($levelStatus !== "on") {
            return;
        }

        $stmt = $this->mysqli->prepare("SELECT xp, level FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return;
        }

        $currentXP = (int)$user['xp'];
        $currentLevel = (int)$user['level'];
        $xpThreshold = (int)$this->config->get('bonuslevelxp'); // 🔹 Innen is már a `Config` osztályt használjuk
        $maxLevel = (int)$this->config->get('bonusmaxlevel');

        if ($xpThreshold == 0) {
            error_log("XP threshold is zero for user ID: $userId");
            return;
        }

        $newLevel = min(floor($currentXP / $xpThreshold), $maxLevel);

        if ($newLevel !== $currentLevel) {
            $stmt = $this->mysqli->prepare("UPDATE users SET level = ? WHERE id = ?");
            $stmt->bind_param("ii", $newLevel, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function updateCoingeckoPrice() {
        $coingecko_status = $this->config->get('coingecko_status');

        if ($coingecko_status !== "on") {
            return;
        }

        $lastCheck = (int)$this->config->get('reward_last_check');

        if ($lastCheck > time() - 3600) {
            return;
        }

        $apiUrl = "https://api.coingecko.com/api/v3/simple/price?ids=zero&vs_currencies=usd";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            error_log("Failed to fetch Coingecko price.");
            return;
        }

        $data = json_decode($response, true);
        if (!isset($data['zero']['usd'])) {
            error_log("Invalid Coingecko response.");
            return;
        }

        $price = round($data['zero']['usd'], 5);

        $stmt = $this->mysqli->prepare("UPDATE settings SET value = ? WHERE name = 'currency_value'");
        $stmt->bind_param('s', $price);
        $stmt->execute();
        $stmt->close();

        $timeNow = time();
        $stmt = $this->mysqli->prepare("UPDATE settings SET value = ? WHERE name = 'reward_last_check'");
        $stmt->bind_param('s', $timeNow);
        $stmt->execute();
        $stmt->close();
    }

    public function getVersion() {
        return $this->version;
    }
    
        public static function alert($type, $content) {
        return "<div class='alert alert-$type' role='alert'>$content</div>";
    }

    public static function toSatoshi($amount) {
        return $amount * 100000000;
    }

    public static function checkDirtyIp($ip, $apiKey) {
        $ch = curl_init("http://v2.api.iphub.info/ip/$ip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Key: $apiKey"]);
        $response = curl_exec($ch);
        curl_close($ch);
        $iphub = json_decode($response);
        return isset($iphub->block) && $iphub->block >= 1;
    }
    
public static function findTimeAgo($timestamp) {
    // Ha a timestamp nem szám, próbáljuk meg dátumként átalakítani UNIX timestamp-re
    if (!is_numeric($timestamp)) {
        $timestamp = strtotime($timestamp);
    }

    // Ha az átalakítás után sem szám, akkor még mindig rossz adatunk van
    if (!is_numeric($timestamp) || empty($timestamp) || $timestamp < 1000000000) {
        return "Never Active";
    }

    $timeDifference = time() - intval($timestamp);

    if ($timeDifference < 60) {
        return "$timeDifference seconds ago";
    } elseif ($timeDifference < 3600) {
        return floor($timeDifference / 60) . " minutes ago";
    } elseif ($timeDifference < 86400) {
        return floor($timeDifference / 3600) . " hours ago";
    } else {
        return floor($timeDifference / 86400) . " days ago";
    }
}



}
