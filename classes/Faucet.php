<?php

class Faucet {
    private $mysqli;
    private $config;
    private $user;
    private $realIpAddress;

    public function __construct($mysqli, $config, $user) {
        $this->mysqli = $mysqli;
        $this->config = $config;
        $this->user = $user;
        $this->realIpAddress = $_SERVER['REMOTE_ADDR'];
    }

public function getDailyClaims() {
    $stmt = $this->mysqli->prepare("SELECT COUNT(id) AS claim_count FROM transactions WHERE userid = ? AND type = 'Faucet' AND DATE(FROM_UNIXTIME(timestamp)) = CURDATE()");
    
    $userId = $this->user->getUserData('id'); // 🔹 Először változóba tároljuk
    $stmt->bind_param("i", $userId);
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $claimCount = $result['claim_count'] ?? 0; // Ha nincs találat, akkor 0-t adunk vissza
    
    $stmt->close();
    return $claimCount;
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

    public function isBanned() {
        $stmt = $this->mysqli->prepare("SELECT COUNT(id) FROM banned_ip WHERE ip_address = ?");
        $stmt->bind_param("s", $this->realIpAddress);
        $stmt->execute();
        $ipBanned = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        $stmt = $this->mysqli->prepare("SELECT COUNT(id) FROM banned_address WHERE address = ?");
        $stmt->bind_param("s", $this->user->getUserData('address'));
        $stmt->execute();
        $addressBanned = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        return ($ipBanned > 0 || $addressBanned > 0);
    }

public function calculateRewardDetails() {
    $baseReward = $this->config->get('reward');
    $bonusAmount = 0; // Alapértelmezett bónusz 0
    
    // Csak akkor számolja a bónuszt, ha a level_system be van kapcsolva
    if ($this->config->get('level_system') === "on") {
        $bonusLevelValue = floatval($this->config->get('bonuslevelvalue'));
        $level = $this->user->getUserData('level');
        $bonusAmount = $baseReward * ($level * $bonusLevelValue / 100);
    }

    $totalReward = $baseReward + $bonusAmount;

    return [
        "total" => $totalReward,
        "base" => $baseReward,
        "bonus" => $bonusAmount
    ];
}



    public function calculateReward() {
        $baseReward = $this->config->get('reward');
        $bonusLevelValue = floatval($this->config->get('bonuslevelvalue'));
        $level = $this->user->getUserData('level');

        return $baseReward + ($baseReward * ($level * $bonusLevelValue / 100));
    }

    public function canClaim() {
        $timer = $this->config->get('timer');
        $lastClaim = $this->user->getUserData('last_claim');
        $waitTime = $lastClaim + $timer - time();
        return $waitTime <= 0;
    }
    
public function claimReward() {
    if (!$this->canClaim()) {
        return ["success" => false, "message" => "You need to wait before claiming again."];
    }

    if ($this->isBanned()) {
        return ["success" => false, "message" => "Your Address and/or IP is banned from this service."];
    }

    if (!$this->verifyCaptcha($_POST['h-captcha-response'])) {
        return ["success" => false, "message" => "Captcha is incorrect. Try again."];
    }

    $rewardDetails = $this->calculateRewardDetails();
    $rewardAmount = $rewardDetails['total']; // Faucet jutalom
    $userId = $this->user->getUserData('id');
    $timestamp = time();

    // Szintlépési rendszer ellenőrzése
    $levelSystemEnabled = $this->config->get('level_system') === "on";
    $xpReward = $levelSystemEnabled ? (int)$this->config->get('xpreward') : 0;

    // Tranzakció mentése
    $stmt = $this->mysqli->prepare("INSERT INTO transactions (userid, type, amount, timestamp) VALUES (?, 'Faucet', ?, ?)");
    $stmt->bind_param("idi", $userId, $rewardAmount, $timestamp);
    $stmt->execute();
    $stmt->close();

    // Felhasználói egyenleg, XP és last_claim frissítése
    $this->user->updateBalance($rewardAmount, $xpReward);

    // 🔹 Referral Jutalom kezelése
    $referralPercent = (float)$this->config->get('referral_percent'); // Referral százalék
    $referralReward = $rewardAmount * ($referralPercent / 100); // Referral jutalom kiszámítása

    if ($referralReward > 0) {
        // Lekérdezzük, hogy a usernek van-e ajánlója
        $stmt = $this->mysqli->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($referrerId);
        $stmt->fetch();
        $stmt->close();

        // Ha van ajánló (referrer)
        if ($referrerId) {
            // 🔹 Az ajánló balance-hoz adjuk a jutalmat
            $referrer = new User($this->mysqli, $this->config, $referrerId);
            $referrer->updateBalance($referralReward);

            // 🔹 A meghívott (user) referral_earnings mezőjébe is mentjük a jutalmat
            $stmt = $this->mysqli->prepare("UPDATE users SET referral_earnings = referral_earnings + ? WHERE id = ?");
            $stmt->bind_param("di", $referralReward, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Felhasználó last_claim frissítése
    $stmt = $this->mysqli->prepare("UPDATE users SET last_claim = ? WHERE id = ?");
    $stmt->bind_param("ii", $timestamp, $userId);
    $stmt->execute();
    $stmt->close();

    // 🔹 **Frissítjük a User osztály példányát is!**
    $this->user->refreshUserData();

    return ["success" => true, "message" => "Claim successful! You earned $rewardAmount ZER" . ($xpReward > 0 ? " and $xpReward XP!" : "")];
}


}
