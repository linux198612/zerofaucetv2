<?php

class Dashboard {
    private $config;
	 private $mysqli;

    public function __construct($mysqli, $config) {
    	  $this->mysqli = $mysqli;
        $this->config = $config;
    }

    public function getCurrencyValue() {
        return floatval($this->config->get('currency_value')) ?? 0;
    }

    public function getLastCheckTime() {
        $lastCheck = $this->config->get('reward_last_check');

        return (is_numeric($lastCheck) && strlen($lastCheck) == 10) 
            ? date('Y-m-d H:i:s', $lastCheck)
            : date('Y-m-d H:i:s', strtotime($lastCheck));
    }
    
public function getReferralCount($userId) {
    $stmt = $this->mysqli->prepare("SELECT COUNT(id) FROM users WHERE referred_by = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($referralCount);
    $stmt->fetch();
    $stmt->close();

    return $referralCount ? $referralCount : 0;
}


public function getReferralEarnings($userId) {
    $stmt = $this->mysqli->prepare("SELECT SUM(referral_earnings) FROM users WHERE referred_by = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($referralEarnings);
    $stmt->fetch();
    $stmt->close();

    return $referralEarnings ? $referralEarnings : 0;
}



}
