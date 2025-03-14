<?php

class AutoFaucet {
    private $mysqli;
    private $user;
    private $config;
    private $realIpAddress;

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config;
        $this->realIpAddress = $_SERVER['REMOTE_ADDR'];
       
        // **Token generálása az első indításkor, ha nincs beállítva**
        $userId = $this->user->getUserData('id');
        $stmt = $this->mysqli->prepare("SELECT auto_token FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!isset($_SESSION['auto_token']) || empty($result['auto_token'])) {
            $this->generateToken();
        } else {
            $_SESSION['auto_token'] = $result['auto_token'];
        }
    }

    private function generateToken() {
        $newToken = bin2hex(random_bytes(16));
        $_SESSION['auto_token'] = $newToken;

        $stmt = $this->mysqli->prepare("UPDATE users SET auto_token = ? WHERE id = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("si", $newToken, $userId);
        if (!$stmt->execute()) {
            error_log("SQL Végrehajtási Hiba: " . $stmt->error);
        }
        $stmt->close();
    }

    public function canClaim() {
        $timeAuto = $this->config->get('autofaucet_interval') ?? 30;
        $userId = $this->user->getUserData('id');

        $stmt = $this->mysqli->prepare("SELECT IFNULL(TIMESTAMPDIFF(SECOND, last_autofaucet, NOW()), ?) AS seconds_since_last FROM users WHERE id = ?");
        $stmt->bind_param("ii", $timeAuto, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ($result['seconds_since_last'] >= $timeAuto);
    }

    public function claimReward() {
        if (!$this->canClaim()) {
            return ["success" => false, "message" => "Please wait before claiming again."];
        }

        $userId = $this->user->getUserData('id');

        // **Token ellenőrzése az adatbázis alapján**
        $stmt = $this->mysqli->prepare("SELECT auto_token FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!isset($_SESSION['auto_token']) || $_SESSION['auto_token'] !== $result['auto_token']) {
            $this->generateToken();
            return ["success" => false, "message" => "Invalid token. AutoFaucet restarted. Try again."];
        }

        // **Jutalom kiszámítása és frissítése**
        $rewardAmount = $this->config->get('autofaucet_reward') ?? 0.00001000;
        $energyReward = $this->config->get('rewardEnergy') ?? 1;
        $refRewardPercent = ($this->config->get('referral_percent') ?? 10) / 100;

        // **Felhasználói egyenleg és energia frissítése**
        $this->user->updateBalance($rewardAmount);
        $stmt = $this->mysqli->prepare("UPDATE users SET energy = energy + ?, last_autofaucet = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $energyReward, $userId);
        $stmt->execute();
        $stmt->close();

        // **Referral jutalom**
        $referrerId = $this->user->getUserData('referred_by');
        if ($referrerId && $referrerId != 0) {
            $referralBonus = $rewardAmount * $refRewardPercent;
            $referrer = new User($this->mysqli, $referrerId);
            $referrer->updateBalance($referralBonus);
            $stmt = $this->mysqli->prepare("UPDATE users SET referral_earnings = referral_earnings + ? WHERE id = ?");
            $stmt->bind_param("di", $referralBonus, $referrerId);
            $stmt->execute();
            $stmt->close();
        }

        // **Új token generálása claim után**
        $this->generateToken();

        return ["success" => true, "message" => "Claim successful! You earned $rewardAmount ZER and $energyReward Energy."];
    }
}

?>



