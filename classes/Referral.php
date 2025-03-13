<?php

class Referral {
    private $mysqli;
    private $user;
    private $websiteUrl;
    private $config;

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config;
        $this->websiteUrl = $config->get('website_url');
    }

    // 🔹 Lekéri a referral százalékot az adatbázisból
    public function getReferralPercent() {
        return $this->config->get("referral_percent");
    }

    // 🔹 Generálja a referral linket
    public function getReferralLink() {
        return $this->websiteUrl . "?ref=" . $this->user->getUserData('id');
    }

    // 🔹 Lekéri a meghívottak referral earnings összegét (összesítve)
    public function getTotalReferralEarnings() {
        $stmt = $this->mysqli->prepare("SELECT SUM(referral_earnings) FROM users WHERE referred_by = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($totalEarnings);
        $stmt->fetch();
        $stmt->close();
        
        return $totalEarnings ? $totalEarnings : 0;
    }

    // 🔹 Lekéri az ajánlott felhasználókat, az utolsó aktivitásukat és referral earnings értékeiket
    public function getReferredUsers() {
        $stmt = $this->mysqli->prepare("SELECT id, address, last_activity, referral_earnings FROM users WHERE referred_by = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $maskedAddress = substr($row['address'], 0, -5) . str_repeat('*', 5);
            $lastActivity = date("d-m-Y H:i:s", $row['last_activity']);
            
            $users[] = [
                'masked_address' => $maskedAddress,
                'last_activity' => $lastActivity,
                'referral_earnings' => $row['referral_earnings']
            ];
        }

        $stmt->close();
        return $users;
    }
}
