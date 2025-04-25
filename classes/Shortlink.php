<?php

class Shortlink {
    private $mysqli;
    private $user;
    private $realIpAddress;
    private $websiteUrl;

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->realIpAddress = $_SERVER['REMOTE_ADDR'];
        $this->websiteUrl = $config->get('website_url');
    }

    public function visitShortlink($shortlinkId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM shortlinks_list WHERE id = ?");
        $stmt->bind_param("i", $shortlinkId);
        $stmt->execute();
        $shortlink = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$shortlink) {
            return ["success" => false, "message" => "Shortlink not found."];
        }

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

    public function getAvailableShortlinks() {
        $stmt = $this->mysqli->prepare("
            SELECT sllist.*, 
            (SELECT COUNT(id) 
             FROM shortlinks_viewed 
             WHERE (userid = ? OR ip_address = ?) 
               AND slid = sllist.id 
               AND timestamp_expiry > UNIX_TIMESTAMP(NOW())) AS active_views
            FROM shortlinks_list AS sllist 
            ORDER BY reward DESC
        ");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("ss", $userId, $this->realIpAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        $shortlinks = [];
    
        $totalShortlinks = 0;
        $totalRewards = 0.0;
    
        while ($row = $result->fetch_assoc()) {
            $dailyRemainingViews = $row['limit_view'] - $row['active_views'];
    
            // Ha az aktív megtekintések száma 0, akkor újra engedélyezzük az összes
            if ($row['active_views'] == 0) {
                $dailyRemainingViews = $row['limit_view'];
            }
    
            $isAvailable = ($dailyRemainingViews > 0);
    
            if ($isAvailable) {
                $shortlinks[] = [
                    "id" => $row['id'],
                    "name" => $row['name'],
                    "reward" => $row['reward'],
                    "remaining_views" => $dailyRemainingViews
                ];
                $totalShortlinks += $dailyRemainingViews;
                $totalRewards += $row['reward'] * $dailyRemainingViews;
            }
        }
    
        $stmt->close();
        return [
            "shortlinks" => $shortlinks,
            "totalShortlinks" => $totalShortlinks,
            "totalRewards" => $totalRewards
        ];
    }
    
    public function getLastViewedShortlinks($limit = 10) {
        $stmt = $this->mysqli->prepare("
            SELECT slv.timestamp, sl.name, slv.ip_address 
            FROM shortlinks_viewed AS slv
            INNER JOIN shortlinks_list AS sl
            ON slv.slid = sl.id
            WHERE slv.userid = ?
            ORDER BY slv.timestamp DESC
            LIMIT ?
        ");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $lastViewed = [];

        while ($row = $result->fetch_assoc()) {
            $lastViewed[] = [
                "timestamp" => date('Y-m-d H:i:s', $row['timestamp']),
                "name" => $row['name'],
                "ip_address" => $row['ip_address']
            ];
        }

        $stmt->close();
        return $lastViewed;
    }

    private function hasUserViewedShortlink($shortlinkId) {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(id) AS view_count 
            FROM shortlinks_viewed 
            WHERE (userid = ? OR ip_address = ?) 
              AND slid = ? 
              AND timestamp_expiry > UNIX_TIMESTAMP(NOW())
        ");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("isi", $userId, $this->realIpAddress, $shortlinkId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result['view_count'] > 0;
    }

    private function fetchUrl($url) {
        return file_get_contents($url);
    }

    private function generateKey() {
        return bin2hex(random_bytes(5));
    }
}
?>



