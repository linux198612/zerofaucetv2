<?php

class Achievements {
    private $mysqli;
    private $user;
    private $currentDate;

    public function __construct($mysqli, $user) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->currentDate = date("Y-m-d");
    }

    // 🔹 Lekéri az összes achievementet és a felhasználó állapotát
    public function getAllAchievements() {
        $stmt = $this->mysqli->prepare("SELECT * FROM achievements");
        $stmt->execute();
        $achievementsResult = $stmt->get_result();
        $achievements = [];

        while ($achievement = $achievementsResult->fetch_assoc()) {
            $achievementId = $achievement['id'];
            $achievementType = $achievement['type'];
            $achievementCondition = $achievement['condition'];
            $achievementReward = $achievement['reward'];

            // 🔹 Felhasználó teljesítményének lekérése
            $userAchievementCount = $this->getUserAchievementProgress($achievementType);

            // 🔹 Ellenőrizzük, hogy claimelte-e már
            $alreadyClaimed = $this->isAchievementClaimed($achievementId);

            // 🔹 Claim gomb feltételeinek meghatározása
            $canClaim = ($userAchievementCount >= $achievementCondition && !$alreadyClaimed);

            $achievements[] = [
                'id' => $achievementId,
                'type' => $achievementType,
                'condition' => $achievementCondition,
                'reward' => $achievementReward,
                'progress' => $userAchievementCount,
                'already_claimed' => $alreadyClaimed,
                'can_claim' => $canClaim
            ];
        }

        $stmt->close();
        return $achievements;
    }

    // 🔹 Felhasználó adott típusú teljesítményének lekérése
    private function getUserAchievementProgress($type) {
        $stmt = $this->mysqli->prepare("SELECT COUNT(id) as count FROM transactions WHERE userid = ? AND type = ? AND DATE(FROM_UNIXTIME(timestamp)) = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("iss", $userId, $type, $this->currentDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['count'] ?? 0;
    }

    // 🔹 Ellenőrzi, hogy a felhasználó már claimelte-e az achievementet
    private function isAchievementClaimed($achievementId) {
        $stmt = $this->mysqli->prepare("SELECT COUNT(id) as count FROM achievement_history WHERE achievement_id = ? AND user_id = ? AND DATE(FROM_UNIXTIME(claim_time)) = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("iis", $achievementId, $userId, $this->currentDate);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ($result['count'] > 0);
    }

    // 🔹 Jutalom claimálása
    public function claimAchievement($achievementId, $claimedReward) {
        $userId = $this->user->getUserData('id');

        // 🔹 Ellenőrizzük, hogy a felhasználó jogosult-e a claimre
        if ($this->isAchievementClaimed($achievementId)) {
            return ["success" => false, "message" => "Achievement already claimed."];
        }

        // 🔹 Egyenleg frissítése
        $stmt = $this->mysqli->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $claimedReward, $userId);
        $stmt->execute();
        $stmt->close();

        // 🔹 Achievement claim mentése
        $timestamp = time();
        $stmt = $this->mysqli->prepare("INSERT INTO achievement_history (achievement_id, user_id, claim_time, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $achievementId, $userId, $timestamp, $claimedReward);
        $stmt->execute();
        $stmt->close();

        return ["success" => true, "message" => "Claim successful! You earned $claimedReward ZER."];
    }
}
?>
