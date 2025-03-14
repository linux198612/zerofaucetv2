<?php

class DailyBonus {
    private $mysqli;
    private $user;
    private $config;
    private $currentDate;

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config;
        $this->currentDate = date("Y-m-d");
    }

    // 🔹 Lekéri, hogy a felhasználó már claimelte-e a napi bónuszt
    private function hasClaimedBonus() {
        $stmt = $this->mysqli->prepare("SELECT 1 FROM bonus_history WHERE user_id = ? AND bonus_date = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("is", $userId, $this->currentDate);
        $stmt->execute();
        $claimed = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $claimed;
    }

    // 🔹 Felhasználó faucet tranzakcióinak száma
    private function getFaucetCount() {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) AS faucet_count FROM transactions WHERE userid = ? AND type = 'Faucet' AND DATE(FROM_UNIXTIME(timestamp)) = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("is", $userId, $this->currentDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return intval($result['faucet_count'] ?? 0);
    }

    // 🔹 Napi bónusz állapotának lekérése
    public function getBonusStatus() {
        $alreadyClaimed = $this->hasClaimedBonus();
        $faucetCount = $this->getFaucetCount();
        $requiredFaucet = (int) $this->config->get('bonus_faucet_require');
        $reward = (float) $this->config->get('bonus_reward_coin');
        $xpReward = (int) $this->config->get('bonus_reward_xp');

        return [
            'already_claimed' => $alreadyClaimed,
            'can_claim' => ($faucetCount >= $requiredFaucet && !$alreadyClaimed),
            'faucet_count' => $faucetCount,
            'required_faucet' => $requiredFaucet,
            'reward' => $reward,
            'xp' => $xpReward
        ];
    }

    // 🔹 Napi bónusz claimelése
    public function claimBonus($hCaptchaResponse) {
        if (!$hCaptchaResponse) {
            return ["success" => false, "message" => "Please complete the hCaptcha."];
        }

        // Ellenőrizzük a Captcha-t
        $hCaptchaPrivKey = $this->config->get('hcaptcha_sec_key');
        $verifyResponse = file_get_contents("https://hcaptcha.com/siteverify?secret={$hCaptchaPrivKey}&response={$hCaptchaResponse}");
        $responseData = json_decode($verifyResponse);

        if (!$responseData?->success) {
            return ["success" => false, "message" => "hCaptcha verification failed. Please try again."];
        }

        // Ellenőrizzük, hogy már claimelte-e
        if ($this->hasClaimedBonus()) {
            return ["success" => false, "message" => "You have already claimed today's bonus."];
        }

        // Frissítjük az egyenleget és XP-t
        $userId = $this->user->getUserData('id');
        $reward = (float) $this->config->get('bonus_reward_coin');
        $xpReward = (int) $this->config->get('bonus_reward_xp');

        $this->user->updateBalance($reward, $xpReward);

        // Rögzítjük a claimet
        $stmt = $this->mysqli->prepare("INSERT INTO bonus_history (user_id, bonus_date) VALUES (?, ?)");
        $stmt->bind_param("is", $userId, $this->currentDate);
        $stmt->execute();
        $stmt->close();

        return ["success" => true, "message" => "Bonus successfully claimed!"];
    }
}
?>
