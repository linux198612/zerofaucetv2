<?php

class Advertise {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function getUserBalances($userId) {
        $stmt = $this->mysqli->prepare("SELECT balance, deposit FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $balances = $result->fetch_assoc();
        $stmt->close();
        return $balances;
    }

    public function getCreditValue() {
        $stmt = $this->mysqli->query("SELECT value FROM settings WHERE name = 'credit_value'");
        return (float)$stmt->fetch_assoc()['value'];
    }

    public function convertBalanceToCredits($userId, $amount, $source) {
        $balances = $this->getUserBalances($userId);
        $creditValue = $this->getCreditValue();

        if ($source === 'internal' && $amount > $balances['balance']) {
            throw new Exception("Insufficient internal balance.");
        } elseif ($source === 'deposit' && $amount > $balances['deposit']) {
            throw new Exception("Insufficient deposit balance.");
        }

        $creditsToAdd = floor($amount / $creditValue);
        $remainingAmount = $amount - ($creditsToAdd * $creditValue);

        if ($source === 'internal') {
            $stmt = $this->mysqli->prepare("UPDATE users SET ads_credit = ads_credit + ?, balance = balance - ? + ? WHERE id = ?");
            $stmt->bind_param("iddi", $creditsToAdd, $amount, $remainingAmount, $userId);
        } elseif ($source === 'deposit') {
            $stmt = $this->mysqli->prepare("UPDATE users SET ads_credit = ads_credit + ?, deposit = deposit - ? + ? WHERE id = ?");
            $stmt->bind_param("iddi", $creditsToAdd, $amount, $remainingAmount, $userId);
        }
        $stmt->execute();
        $stmt->close();

        return [
            'creditsAdded' => $creditsToAdd,
            'remainingBalance' => $remainingAmount
        ];
    }

    public function getPackages() {
        $stmt = $this->mysqli->query("SELECT * FROM ptc_packages ORDER BY duration_seconds ASC");
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    public function createAd($userId, $title, $url, $packageId, $views, $adType) {
        $package = $this->getPackageById($packageId);
        $zerCost = $package['zer_cost'];
        $creditCostPerView = $zerCost / $this->getCreditValue(); // Hány kreditbe kerül egy megtekintés
        $totalCost = ceil($creditCostPerView * $views); // Összes szükséges kredit

        // Ellenőrizzük, hogy van-e elég hirdetési kredit
        $stmt = $this->mysqli->prepare("SELECT ads_credit FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($adsCredit);
        $stmt->fetch();
        $stmt->close();

        if ($adsCredit < $totalCost) {
            throw new Exception("Insufficient advertising credits. You need $totalCost credits for $views views.");
        }

        // Levonjuk a hirdetési krediteket
        $stmt = $this->mysqli->prepare("UPDATE users SET ads_credit = ads_credit - ? WHERE id = ?");
        $stmt->bind_param("ii", $totalCost, $userId);
        $stmt->execute();
        $stmt->close();

        // Létrehozzuk a hirdetést
        $stmt = $this->mysqli->prepare("INSERT INTO user_ads (user_id, title, url, package_id, views_remaining, status, ad_type) VALUES (?, ?, ?, ?, ?, 'Active', ?)");
        $stmt->bind_param("issiis", $userId, $title, $url, $packageId, $views, $adType);
        $stmt->execute();
        $stmt->close();
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

    public function getUserCredits($userId) {
        $stmt = $this->mysqli->prepare("SELECT ads_credit FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($credits);
        $stmt->fetch();
        $stmt->close();
        return (int)$credits; // Biztosítjuk, hogy egész számként térjen vissza
    }

    public function addViewsToAd($userId, $adId, $views) {
        // Ellenőrizzük, hogy a hirdetés a felhasználóhoz tartozik-e
        $stmt = $this->mysqli->prepare("SELECT package_id, views_remaining FROM user_ads WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $adId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ad = $result->fetch_assoc();
        $stmt->close();

        if (!$ad) {
            throw new Exception("Ad not found or does not belong to the user.");
        }

        // Lekérjük a csomaghoz tartozó zer_cost értéket
        $stmt = $this->mysqli->prepare("SELECT zer_cost FROM ptc_packages WHERE id = ?");
        $stmt->bind_param("i", $ad['package_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $package = $result->fetch_assoc();
        $stmt->close();

        if (!$package) {
            throw new Exception("Package not found for the ad.");
        }

        $zerCost = (float)$package['zer_cost']; // Egy nézet ZER költsége
        $creditValue = $this->getCreditValue(); // 1 kredit ZER értéke

        if ($creditValue <= 0) {
            throw new Exception("Invalid credit value configuration.");
        }

        // Egy nézet költsége kreditben
        $costPerViewInCredits = $zerCost / $creditValue;

        // Teljes költség a nézetek számával
        $requiredCredits = ceil($views * $costPerViewInCredits);
        $userCredits = $this->getUserCredits($userId);

        if ($userCredits < $requiredCredits) {
            throw new Exception("Insufficient credits. You need " . number_format($requiredCredits, 0) . " credits, but you only have " . number_format($userCredits, 0) . " credits.");
        }

        // Tranzakció indítása
        $this->mysqli->begin_transaction();

        try {
            // Frissítjük a hirdetés nézeteit
            $stmt = $this->mysqli->prepare("UPDATE user_ads SET views_remaining = views_remaining + ? WHERE id = ?");
            $stmt->bind_param("ii", $views, $adId);
            $stmt->execute();
            $stmt->close();

            // Frissítjük a felhasználó krediteit
            $stmt = $this->mysqli->prepare("UPDATE users SET ads_credit = ads_credit - ? WHERE id = ?");
            $stmt->bind_param("ii", $requiredCredits, $userId);
            $stmt->execute();
            $stmt->close();

            // Ha a nézetek hozzáadása sikeres, állítsuk vissza a hirdetés státuszát "Active"-ra
            $stmt = $this->mysqli->prepare("UPDATE user_ads SET status = 'Active' WHERE id = ?");
            $stmt->bind_param("i", $adId);
            $stmt->execute();
            $stmt->close();

            // Tranzakció elkötelezése
            $this->mysqli->commit();
        } catch (Exception $e) {
            // Tranzakció visszavonása hiba esetén
            $this->mysqli->rollback();
            throw $e;
        }
    }

    public function deleteAd($userId, $adId) {
        // Ellenőrizzük, hogy a hirdetés a felhasználóhoz tartozik-e
        $stmt = $this->mysqli->prepare("SELECT package_id, views_remaining FROM user_ads WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $adId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ad = $result->fetch_assoc();
        $stmt->close();

        if (!$ad) {
            throw new Exception("Ad not found or does not belong to the user.");
        }

        // Lekérjük a csomaghoz tartozó zer_cost értéket
        $stmt = $this->mysqli->prepare("SELECT zer_cost FROM ptc_packages WHERE id = ?");
        $stmt->bind_param("i", $ad['package_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $package = $result->fetch_assoc();
        $stmt->close();

        if (!$package) {
            throw new Exception("Package not found for the ad.");
        }

        $zerCost = (float)$package['zer_cost']; // Egy nézet ZER költsége
        $creditValue = $this->getCreditValue(); // 1 kredit ZER értéke

        if ($creditValue <= 0) {
            throw new Exception("Invalid credit value configuration.");
        }

        // Egy nézet költsége kreditben
        $costPerViewInCredits = $zerCost / $creditValue;

        // Visszajáró kreditek kiszámítása
        $creditsRefunded = ceil($ad['views_remaining'] * $costPerViewInCredits);

        // Tranzakció indítása
        $this->mysqli->begin_transaction();

        try {
            // Visszatérítjük a fennmaradó krediteket
            $stmt = $this->mysqli->prepare("UPDATE users SET ads_credit = ads_credit + ? WHERE id = ?");
            $stmt->bind_param("ii", $creditsRefunded, $userId);
            $stmt->execute();
            $stmt->close();

            // Töröljük a hirdetést
            $stmt = $this->mysqli->prepare("DELETE FROM user_ads WHERE id = ?");
            $stmt->bind_param("i", $adId);
            $stmt->execute();
            $stmt->close();

            // Tranzakció elkötelezése
            $this->mysqli->commit();
        } catch (Exception $e) {
            // Tranzakció visszavonása hiba esetén
            $this->mysqli->rollback();
            throw $e;
        }

        return ['creditsRefunded' => $creditsRefunded];
    }

    public function updateAdTitle($userId, $adId, $newTitle) {
        $stmt = $this->mysqli->prepare("UPDATE user_ads SET title = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $newTitle, $adId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update ad title or no changes made.");
        }
        $stmt->close();
    }

    public function updateAdType($userId, $adId, $newAdType) {
        $stmt = $this->mysqli->prepare("UPDATE user_ads SET ad_type = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $newAdType, $adId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update ad type or no changes made.");
        }
        $stmt->close();
    }

    public function getAdViewCount($adId) {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) AS view_count FROM ptc_history WHERE ad_id = ?");
        $stmt->bind_param("i", $adId);
        $stmt->execute();
        $stmt->bind_result($viewCount);
        $stmt->fetch();
        $stmt->close();
        return $viewCount;
    }

    public function getAdViewStats($adId) {
        $stmt = $this->mysqli->prepare("
            SELECT DATE(viewed_at) as view_date, COUNT(*) as view_count
            FROM ptc_history
            WHERE ad_id = ? AND viewed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(viewed_at)
            ORDER BY view_date ASC
        ");
        $stmt->bind_param("i", $adId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        $stmt->close();

        return $stats ?: []; // Ensure stats is always an array
    }

    private function getPackageById($packageId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM ptc_packages WHERE id = ?");
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $package = $result->fetch_assoc();
        $stmt->close();
        return $package;
    }
}
?>
