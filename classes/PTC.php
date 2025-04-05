<?php

class PTC {
    private $mysqli;
    private $user;
    private $config;

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config; 
    }

    public function getActiveAds($userId) {
        $stmt = $this->mysqli->prepare("
            SELECT ua.*, pp.duration_seconds, pp.reward 
            FROM user_ads ua
            JOIN ptc_packages pp ON ua.package_id = pp.id
            WHERE ua.status = 'Active' 
              AND ua.views_remaining > 0
              AND ua.id NOT IN (
                  SELECT ad_id 
                  FROM ptc_history 
                  WHERE user_id = ? 
                    AND DATE(viewed_at) = CURDATE()
              )
            ORDER BY pp.reward DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ads = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $ads;
    }

    public function rewardUserForView($userId, $adId) {
        // Ellenőrizzük, hogy a felhasználó már megtekintette-e a hirdetést az adott napon
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) 
            FROM ptc_history 
            WHERE user_id = ? AND ad_id = ? AND DATE(viewed_at) = CURDATE()
        ");
        $stmt->bind_param("ii", $userId, $adId);
        $stmt->execute();
        $stmt->bind_result($viewCount);
        $stmt->fetch();
        $stmt->close();

        if ($viewCount > 0) {
            throw new Exception("You have already viewed this ad today.");
        }

        // Lekérdezzük a hirdetés adatait
        $stmt = $this->mysqli->prepare("SELECT ua.views_remaining, pp.reward 
                                        FROM user_ads ua 
                                        JOIN ptc_packages pp ON ua.package_id = pp.id 
                                        WHERE ua.id = ?");
        $stmt->bind_param("i", $adId);
        $stmt->execute();
        $stmt->bind_result($viewsRemaining, $reward);
        $stmt->fetch();
        $stmt->close();

        if ($viewsRemaining <= 0) {
            throw new Exception("No views remaining for this ad.");
        }

        // Jóváírjuk a felhasználó egyenlegébe
        $stmt = $this->mysqli->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $reward, $userId);
        $stmt->execute();
        $stmt->close();

        // Csökkentjük a megtekintések számát
        $stmt = $this->mysqli->prepare("UPDATE user_ads SET views_remaining = views_remaining - 1 WHERE id = ?");
        $stmt->bind_param("i", $adId);
        $stmt->execute();
        $stmt->close();

        // Ha elfogytak a megtekintések, inaktiváljuk a hirdetést
        if ($viewsRemaining - 1 <= 0) {
            $stmt = $this->mysqli->prepare("UPDATE user_ads SET status = 'Completed' WHERE id = ?");
            $stmt->bind_param("i", $adId);
            $stmt->execute();
            $stmt->close();
        }

        // Rögzítjük a történetet
        $stmt = $this->mysqli->prepare("INSERT INTO ptc_history (user_id, ad_id, reward, viewed_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iid", $userId, $adId, $reward);
        $stmt->execute();
        $stmt->close();

        return $reward; // Jutalom visszaadása
    }

    public function getUserAds($userId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM user_ads WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ads = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $ads;
    }

    public function getAdsStatistics($userId) {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) AS total_ads, SUM(pp.reward) AS total_reward
            FROM user_ads ua
            JOIN ptc_packages pp ON ua.package_id = pp.id
            WHERE ua.status = 'Active' 
              AND ua.views_remaining > 0
              AND ua.id NOT IN (
                  SELECT ad_id 
                  FROM ptc_history 
                  WHERE user_id = ? 
                    AND DATE(viewed_at) = CURDATE()
              )
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($totalAds, $totalReward);
        $stmt->fetch();
        $stmt->close();

        return [
            'total_ads' => $totalAds,
            'total_reward' => $totalReward
        ];
    }

    public function getAdById($adId) {
        $stmt = $this->mysqli->prepare("
            SELECT ua.*, pp.duration_seconds 
            FROM user_ads ua
            JOIN ptc_packages pp ON ua.package_id = pp.id
            WHERE ua.id = ?
        ");
        $stmt->bind_param("i", $adId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ad = $result->fetch_assoc();
        $stmt->close();

        if (!$ad) {
            throw new Exception("Ad not found.");
        }

        return $ad;
    }
}
?>
