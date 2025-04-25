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

    public function toSatoshi($amount) {
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

private function hasToWaitForNextWithdraw(&$message) {
    $userId = $this->user->getUserData('id');

    $limitHour = $this->config->get('withdrawlimithour');

    if (!$limitHour || $limitHour <= 0) {
        return false; // nincs korlátozás
    }

    // Utolsó kifizetés időpontja
    $stmt = $this->mysqli->prepare("
        SELECT requested_at 
        FROM withdrawals 
        WHERE user_id = ? 
        ORDER BY requested_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($lastWithdrawal);
    $stmt->fetch();
    $stmt->close();

    if (!$lastWithdrawal) {
        return false; // még nem volt kifizetés
    }

    $lastTime = strtotime($lastWithdrawal);
    $now = time();
    $limitInSeconds = $limitHour * 3600;
    $timeLeft = $lastTime + $limitInSeconds - $now;

    if ($timeLeft > 0) {
        $hours = floor($timeLeft / 3600);
        $minutes = floor(($timeLeft % 3600) / 60);

        if ($hours > 0) {
            $message = "You must wait {$hours}h " . ($minutes > 0 ? "{$minutes}m " : "") . "before making another withdrawal.";
        } else {
            $message = "You must wait {$minutes} minute(s) before making another withdrawal.";
        }

        return true;
    }

    return false;
}



    public function requestWithdrawal() {
        error_log("Request Withdrawal Called");

        if (!$this->canWithdraw()) {
            error_log("Withdrawal threshold not reached.");
            return ["success" => false, "message" => "Withdrawal threshold not reached."];
        }
    $waitMessage = "";
    if ($this->hasToWaitForNextWithdraw($waitMessage)) {
        return ["success" => false, "message" => $waitMessage];
    }
        $currency = isset($_POST['currency']) ? htmlspecialchars($_POST['currency'], ENT_QUOTES, 'UTF-8') : 'ZER';
        $toAddress = isset($_POST['toAddress']) ? htmlspecialchars($_POST['toAddress'], ENT_QUOTES, 'UTF-8') : null;

        error_log("Currency: $currency, To Address: $toAddress");

        if ($this->config->get('faucetpay_mode') === 'on' && $currency !== 'ZER') {
            $result = $this->processFaucetPayWithdrawal($currency, $toAddress);
            error_log("FaucetPay Withdrawal Result: " . json_encode($result));
            return $result;
        } else {
            $result = ($this->manualWithdrawStatus === 'on') 
                ? $this->processManualWithdrawal($currency) 
                : $this->processInstantWithdrawal($currency);
            error_log("Withdrawal Result: " . json_encode($result));
            return $result;
        }
    }

    private function processManualWithdrawal($currency) {

    $waitMessage = "";
    if ($this->hasToWaitForNextWithdraw($waitMessage)) {
        return ["success" => false, "message" => $waitMessage];
    }    	
    	
        $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, zer_value, requested_at) VALUES (?, ?, '', 'Pending', ?, ?, NOW())");
        $userId = $this->user->getUserData('id');
        $userBalance = $this->user->getUserData('balance');
        $zerValue = $this->getUserBalance(); // ZER equivalent
        $stmt->bind_param("ddss", $userId, $userBalance, $currency, $zerValue); // Javítás: "idssd" helyett "ddss"

        if (!$stmt->execute()) {
            return ["success" => false, "message" => "Database error: " . $stmt->error];
        }

        $stmt->close();
        $this->user->updateBalance(-$userBalance);

        return ["success" => true, "message" => "Withdrawal request is pending."];
    }

private function processInstantWithdrawal($currency) {
    $waitMessage = "";
    if ($this->hasToWaitForNextWithdraw($waitMessage)) {
        return ["success" => false, "message" => $waitMessage];
    }

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

    if ($txid) {
        // Frissítve: hozzáadjuk a `zer_value`-t
        $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, requested_at, zer_value) VALUES (?, ?, ?, 'Paid', ?, NOW(), ?)");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("idsss", $userId, $balance, $txid, $currency, $balance); // A `zer_value` megegyezik a balance-szal

        if (!$stmt->execute()) {
            return ["success" => false, "message" => "Database error: " . $stmt->error];
        }

        $stmt->close();

        // Frissítjük a total_withdrawals mezőt az aktuális egyenleg alapján
        $updateStmt = $this->mysqli->prepare("UPDATE users SET total_withdrawals = total_withdrawals + ? WHERE id = ?");
        $updateStmt->bind_param("di", $balance, $userId);
        if (!$updateStmt->execute()) {
            error_log("Database Error in updating total_withdrawals: " . $updateStmt->error);
        }
        $updateStmt->close();

        $this->user->updateBalance(-$balance);

        return ["success" => true, "message" => "Successful payment: $balance ZER"];
    } else {
        // Instant sikertelen -> Pending-re rakjuk
        $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, requested_at, zer_value) VALUES (?, ?, '', 'Pending', ?, NOW(), ?)");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("idss", $userId, $balance, $currency, $balance); // A `zer_value` megegyezik a balance-szal

        if (!$stmt->execute()) {
            return ["success" => false, "message" => "Database error: " . $stmt->error];
        }

        $stmt->close();
        $this->user->updateBalance(-$balance);

        return ["success" => false, "message" => "Instant payment failed. Withdrawal request has been set to pending for manual processing."];
    }
}


    private function processFaucetPayWithdrawal($currency, $toAddress) {
    	
    	    $waitMessage = "";
    if ($this->hasToWaitForNextWithdraw($waitMessage)) {
        return ["success" => false, "message" => $waitMessage];
    }
    
        $apiKey = $this->config->get('faucetpay_api_key');
        $convertedAmount = isset($_POST['convertedAmount']) ? (float)$_POST['convertedAmount'] : 0;

        // Ha a convertedAmount 0 vagy nem megfelelő, számoljuk újra a currencies tábla alapján
        if ($convertedAmount <= 0) {
            $stmt = $this->mysqli->prepare("SELECT price FROM currencies WHERE code = ?");
            $stmt->bind_param("s", $currency);
            $stmt->execute();
            $stmt->bind_result($currencyPrice);
            $stmt->fetch();
            $stmt->close();

            if ($currencyPrice > 0) {
                $userBalance = $this->getUserBalance();
                $zerPrice = (float)$this->config->get('currency_value'); // ZER árfolyam
                $convertedAmount = round($userBalance * $zerPrice / $currencyPrice, 8);
            }
        }

        if ($convertedAmount <= 0) {
            error_log("Invalid converted amount after recalculation: $convertedAmount"); // Hibakeresési napló
            return ["success" => false, "message" => "Invalid converted amount."];
        }

        // Lekérdezzük a minimum withdrawal értéket a currencies táblából
        $stmt = $this->mysqli->prepare("SELECT minimum_withdrawal FROM currencies WHERE code = ?");
        $stmt->bind_param("s", $currency);
        $stmt->execute();
        $stmt->bind_result($minWithdrawal);
        $stmt->fetch();
        $stmt->close();

        if (!$minWithdrawal) {
            return ["success" => false, "message" => "Currency not found or minimum withdrawal not set."];
        }

        // Ellenőrizzük, hogy a felhasználó egyenlege eléri-e a minimum withdrawal értéket
        $userBalance = $this->getUserBalance();
        if ($userBalance < $minWithdrawal) {
            return ["success" => false, "message" => "Your balance must be at least $minWithdrawal ZER to withdraw $currency."];
        }

        if ($this->manualWithdrawStatus === 'on') {
            // Manuális mód esetén "Pending" státusz
            $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, zer_value, requested_at) VALUES (?, ?, '', 'Pending', ?, ?, NOW())");
            $userId = $this->user->getUserData('id');
            $zerValue = $this->getUserBalance(); // ZER equivalent
            $stmt->bind_param("idss", $userId, $convertedAmount, $currency, $zerValue);

            if (!$stmt->execute()) {
                return ["success" => false, "message" => "Database error occurred. Please contact support."];
            }

            $stmt->close();
            $this->user->updateBalance(-$this->getUserBalance());

            return ["success" => true, "message" => "Withdrawal request is pending."];
        }

        $amountInSatoshis = $this->toSatoshi($convertedAmount); // Átváltás satoshira

        $postData = [
            'api_key' => $apiKey,
            'amount' => $amountInSatoshis,
            'to' => $toAddress,
            'currency' => $currency,
        ];

        $ch = curl_init("https://faucetpay.io/api/v1/send");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Naplózzuk a válaszokat és hibákat
        error_log("FaucetPay API Request: " . json_encode($postData));
        error_log("FaucetPay API Response: $response");
        error_log("FaucetPay API HTTP Code: $httpCode");
        if ($curlError) {
            error_log("FaucetPay API CURL Error: $curlError");
        }

				$responseData = json_decode($response, true);
				
				if (json_last_error() !== JSON_ERROR_NONE) {
				    error_log("FaucetPay API JSON Decode Error: " . json_last_error_msg());
				    return ["success" => false, "message" => "Invalid response from FaucetPay API."];
				}
				
				if ($httpCode !== 200 || $responseData['status'] !== 200) {
				    error_log("FaucetPay withdrawal failed, fallback to manual (Pending): " . ($responseData['message'] ?? "Unknown error"));
				
				    $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, zer_value, requested_at) VALUES (?, ?, '', 'Pending', ?, ?, NOW())");
				    $userId = $this->user->getUserData('id');
				    $zerValue = $this->getUserBalance();
				    $stmt->bind_param("idss", $userId, $convertedAmount, $currency, $zerValue);
				
				    if (!$stmt->execute()) {
				        return ["success" => false, "message" => "FaucetPay failed and database fallback also failed: " . $stmt->error];
				    }
				
				    $stmt->close();
				    $this->user->updateBalance(-$this->getUserBalance());
				
				    return ["success" => false, "message" => "FaucetPay withdrawal failed. Request has been marked as pending for manual review."];
				}

        if ($responseData['status'] !== 200) {
            error_log("FaucetPay API Error: " . ($responseData['message'] ?? "Unknown error"));
            return ["success" => false, "message" => $responseData['message'] ?? "Unknown error occurred during FaucetPay withdrawal."];
        }

        // Naplózzuk a kifizetést a withdrawals táblába (txid mindig üres)
        $stmt = $this->mysqli->prepare("INSERT INTO withdrawals (user_id, amount, txid, status, currency, zer_value, requested_at) VALUES (?, ?, '', 'Paid', ?, ?, NOW())");
        $userId = $this->user->getUserData('id');
        $zerValue = $this->getUserBalance(); // ZER equivalent
        $stmt->bind_param("idss", $userId, $convertedAmount, $currency, $zerValue);
        if (!$stmt->execute()) {
            return ["success" => false, "message" => "Database error occurred. Please contact support."];
        }
        $stmt->close();

        // Frissítsük a total_withdrawals mezőt az aktuális egyenleg alapján
        $currentBalance = $this->getUserBalance(); // Lekérdezzük az aktuális egyenleget
        $updateStmt = $this->mysqli->prepare("UPDATE users SET total_withdrawals = total_withdrawals + ? WHERE id = ?");
        $updateStmt->bind_param("di", $currentBalance, $userId);
        if (!$updateStmt->execute()) {
            error_log("Database Error in updating total_withdrawals: " . $updateStmt->error);
        }
        $updateStmt->close();

        $this->user->updateBalance(-$this->getUserBalance());

        return ["success" => true, "message" => "FaucetPay withdrawal successful!"];
    }

    public function processManualFaucetPayWithdraw($withdrawId, $currency, $txid) {
    	
    	    $waitMessage = "";
    if ($this->hasToWaitForNextWithdraw($waitMessage)) {
        return ["success" => false, "message" => $waitMessage];
    }
        $stmt = $this->mysqli->prepare("UPDATE withdrawals SET status = 'Paid', txid = ? WHERE id = ? AND currency = ?");
        $stmt->bind_param("sis", $txid, $withdrawId, $currency);
        if ($stmt->execute()) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Failed to update withdrawal status.'];
        }
    }

    public function getWithdrawHistory($limit = 20) {
        $stmt = $this->mysqli->prepare("
            SELECT id, amount, currency, txid, status, requested_at 
            FROM withdrawals 
            WHERE user_id = ? 
            ORDER BY requested_at DESC 
            LIMIT ?
        ");
        if (!$stmt) {
            error_log("SQL Error in getWithdrawHistory: " . $this->mysqli->error);
            return false;
        }

        $userId = $this->user->getUserData('id');
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    public function getTotalWithdraw($currency = 'ZER') {
        $stmt = $this->mysqli->prepare("
            SELECT SUM(amount) 
            FROM withdrawals 
            WHERE user_id = ? AND status = 'Paid' AND currency = ?
        ");
        $userId = $this->user->getUserData('id');
        $stmt->bind_param("is", $userId, $currency);
        $stmt->execute();
        $stmt->bind_result($totalWithdrawn);
        $stmt->fetch();
        $stmt->close();

        return $totalWithdrawn ? $totalWithdrawn : 0;
    }

    public function isManual() {
        return $this->manualWithdrawStatus === 'on';
    }
}

?>