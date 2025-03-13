<?php

class Shortlink {
    private $mysqli;
    private $user;
    private $realIpAddress;
    private $websiteUrl;

    public function __construct($mysqli, $user, $config) { // 🔹 Hozzáadjuk a $config paramétert
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->realIpAddress = $_SERVER['REMOTE_ADDR'];
        $this->websiteUrl = $config->get('website_url'); // 🔹 Most az adatbázisból kapott URL-t használjuk
    }

    // Shortlink meglátogatása
    public function visitShortlink($shortlinkId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM shortlinks_list WHERE id = ?");
        $stmt->bind_param("i", $shortlinkId);
        $stmt->execute();
        $shortlink = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$shortlink) {
            return ["success" => false, "message" => "Shortlink not found."];
        }

        // Ellenőrizzük, hogy a felhasználó már látta-e, és maradt-e elérhető megtekintés
        if ($this->hasUserViewedShortlink($shortlinkId)) {
            return ["success" => false, "message" => "You reached the limit for this shortlink."];
        }

        $claimKey = $this->generateKey();
        $targetUrl = urlencode($this->websiteUrl . "shortlink?viewed=" . $claimKey);
        $apiUrl = str_replace("{url}", $targetUrl, $shortlink['url']);
        $apiUrl = trim($apiUrl);

        $shortlinkResponse = json_decode($this->fetchUrl($apiUrl), true);

        if (!empty($shortlinkResponse['status']) && $shortlinkResponse['status'] == 'success') {
            $stmt = $this->mysqli->prepare("INSERT INTO shortlinks_views (userid, slid, claim_key, shortlink) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $this->user->getUserData('id'), $shortlinkId, $claimKey, $shortlinkResponse['shortenedUrl']);
            $stmt->execute();
            $stmt->close();

            return ["success" => true, "redirect_url" => $shortlinkResponse['shortenedUrl']];
        } else {
            return ["success" => false, "message" => "An error occurred when generating the short link."];
        }
    }

    // Jutalom a shortlink megtekintéséért
    public function rewardShortlink($claimKey) {
        $stmt = $this->mysqli->prepare("SELECT sl_list.reward, sl_views.slid 
                                        FROM shortlinks_views AS sl_views 
                                        INNER JOIN shortlinks_list AS sl_list 
                                        ON sl_views.slid = sl_list.id 
                                        WHERE sl_views.userid = ? AND sl_views.claim_key = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("is", $userId, $claimKey);
        $stmt->execute();
        $shortlinkData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$shortlinkData) {
            return ["success" => false, "message" => "Invalid Shortlink key."];
        }

        $rewardAmount = $shortlinkData['reward'];

        // Töröljük a shortlinks_views táblából
        $stmt = $this->mysqli->prepare("DELETE FROM shortlinks_views WHERE userid = ? AND claim_key = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("is", $userId, $claimKey);
        $stmt->execute();
        $stmt->close();

        // Frissítjük az egyenleget
        $stmt = $this->mysqli->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("di", $rewardAmount, $userId);
        $stmt->execute();
        $stmt->close();

        // Mentjük a megtekintést az shortlinks_viewed táblába
        $timestampExpiry = time() + 86400; // 24 óra után lejár
        $stmt = $this->mysqli->prepare("INSERT INTO shortlinks_viewed (userid, slid, ip_address, timestamp, timestamp_expiry) VALUES (?, ?, ?, UNIX_TIMESTAMP(NOW()), ?)");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("iisi", $userId, $shortlinkData['slid'], $this->realIpAddress, $timestampExpiry);
        $stmt->execute();
        $stmt->close();

        return ["success" => true, "message" => "You received {$rewardAmount} ZER for visiting the shortlink."];
    }

    // Elérhető shortlinkek listája és összesített adatok
    public function getAvailableShortlinks() {
        $stmt = $this->mysqli->prepare("
            SELECT sllist.*, 
            (SELECT COUNT(id) FROM shortlinks_viewed WHERE userid = ? OR ip_address = ? AND timestamp_expiry > UNIX_TIMESTAMP(NOW()) AND slid = sllist.id) as viewed_count
            FROM shortlinks_list AS sllist 
            WHERE sllist.limit_view > (SELECT COUNT(id) FROM shortlinks_viewed WHERE userid = ? OR ip_address = ? AND timestamp_expiry > UNIX_TIMESTAMP(NOW()))
            ORDER BY reward DESC
        ");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("ssss", $userId, $this->realIpAddress, $userId, $this->realIpAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        $shortlinks = [];

        $totalShortlinks = 0; // Összes megtekinthető shortlink
        $totalRewards = 0.0;  // Összes megszerezhető ZER

        while ($row = $result->fetch_assoc()) {
            $remainingViews = $row['limit_view'] - $row['viewed_count'];
            if ($remainingViews > 0) {
                $shortlinks[] = [
                    "id" => $row['id'],
                    "name" => $row['name'],
                    "reward" => $row['reward'],
                    "remaining_views" => $remainingViews
                ];
                $totalShortlinks += $remainingViews;
                $totalRewards += $row['reward'] * $remainingViews;
            }
        }

        $stmt->close();
        return ["shortlinks" => $shortlinks, "totalShortlinks" => $totalShortlinks, "totalRewards" => $totalRewards];
    }

    // Segédfüggvények
    private function hasUserViewedShortlink($shortlinkId) {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(id) AS view_count, 
                   (SELECT limit_view FROM shortlinks_list WHERE id = ?) AS max_views 
            FROM shortlinks_viewed 
            WHERE (userid = ? OR ip_address = ?) AND slid = ? AND timestamp_expiry > UNIX_TIMESTAMP(NOW())
        ");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("iisi", $shortlinkId, $userId, $this->realIpAddress, $shortlinkId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['view_count'] >= $result['max_views']; // Ha a megtekintések száma elérte a limitet, tiltás
    }

    private function fetchUrl($url) {
        return file_get_contents($url);
    }

    private function generateKey() {
        return bin2hex(random_bytes(5));
    }
}
?>



