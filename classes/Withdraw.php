<?php

class Withdraw {
    private $mysqli;
    private $user;
    private $minWithdraw;
    private $manualWithdrawStatus;
    private $config;

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config;
        $this->minWithdraw = $this->config->get('min_withdrawal_gateway');
        $this->manualWithdrawStatus = $this->config->get('manual_withdraw');
    }

    public function toSatoshi($amount){
        return $amount * 100000000;
    }

    public function getMinWithdraw() {
        return $this->minWithdraw;
    }

    public function getUserBalance() {
        return $this->user->getUserData('balance');
    }

    public function canWithdraw() {
        return $this->toSatoshi($this->getUserBalance()) >= $this->minWithdraw;
    }

    public function requestWithdrawal() {
        if (!$this->canWithdraw()) {
            return ["success" => false, "message" => "Withdrawal threshold not reached."];
        }

        $currency = isset($_POST['currency']) ? htmlspecialchars($_POST['currency'], ENT_QUOTES, 'UTF-8') : 'ZER';

        return ($this->manualWithdrawStatus == 'on') 
            ? $this->processManualWithdrawal($currency) 
            : $this->processInstantWithdrawal($currency);
    }

    public function isManual() {
        return $this->manualWithdrawStatus === 'on';
    }

    private function processManualWithdrawal($currency) {
        if (isset($_SESSION['withdraw_requested'])) {
            return ["success" => false, "message" => "Withdrawal already requested."];
        }

        $_SESSION['withdraw_requested'] = true;
        $withdrawStatus = 'Pending';
        $txid = "";

        $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, requested_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $userId = $this->user->getUserData('id');
        $userBalance = $this->user->getUserData('balance');

        // **Javítás**: amount típusa "d" (decimal), nem "s"
        $stmt->bind_param("idsss", $userId, $userBalance, $txid, $withdrawStatus, $currency);
        
        if (!$stmt->execute()) {
            return ["success" => false, "message" => "Database error: " . $stmt->error];
        }
        
        $stmt->close();
        $this->user->updateBalance(-$userBalance);
        unset($_SESSION['withdraw_requested']);

        return ["success" => true, "message" => "Withdrawal request is pending."];
    }

    private function processInstantWithdrawal($currency) {
        $apiKey = $this->config->get('zerochain_api');
        $privateKey = $this->config->get('zerochain_privatekey');
        $userAddress = $this->user->getUserData('address');
        $balance = $this->getUserBalance();

        $result = file_get_contents("https://zerochain.info/api/rawtxbuild/{$privateKey}/{$userAddress}/{$balance}/0/1/{$apiKey}");

        $txid = "";
        if (strpos($result, '"txid":"') !== false) {
            $pieces = explode('"txid":"', $result);
            $txid = explode('"', $pieces[1])[0];
        }

        if ($txid != "") {
            $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, requested_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $userId = $this->user->getUserData('id');
            $status = "Paid";
            
            // **Javítás**: "idss" megfelelő típusokkal
            $stmt->bind_param("idsss", $userId, $balance, $txid, $status, $currency);
            
            if (!$stmt->execute()) {
                return ["success" => false, "message" => "Database error: " . $stmt->error];
            }

            $stmt->close();
            
            $this->updateTotalWithdrawals($userId, $balance);
            $this->user->updateBalance(-$balance);
            
            unset($_SESSION['withdraw_requested']);

            return ["success" => true, "message" => "Successful payment: $balance ZER"];
        } else {
            return ["success" => false, "message" => "Transaction failed."];
        }
    }

    private function updateUserBalance($amount) {
        $stmt = $this->mysqli->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        $stmt->close();
    }

    private function updateTotalWithdrawals($userId, $amount) {
        $stmt = $this->mysqli->prepare("UPDATE users SET total_withdrawals = total_withdrawals + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        $stmt->close();
    }

    public function getWithdrawHistory($limit = 20) {
        $stmt = $this->mysqli->prepare("SELECT id, user_id, amount, txid, status, requested_at FROM withdrawals WHERE user_id = ? ORDER BY requested_at DESC LIMIT ?");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    public function getTotalWithdraw() {
        $stmt = $this->mysqli->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'Paid'");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($totalWithdrawn);
        $stmt->fetch();
        $stmt->close();
        
        return $totalWithdrawn ? $totalWithdrawn : 0; 
    }
}

?>