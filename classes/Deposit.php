<?php

class Deposit {
    private $mysqli;
    private $apiKey;

    public function __construct($mysqli, $apiKey) {
        $this->mysqli = $mysqli;
        $this->apiKey = $apiKey;
    }

    public function getActiveDepositAddress($userId) {
        $stmt = $this->mysqli->prepare("SELECT address, expires_at FROM deposits WHERE user_id = ? AND status = 'Pending' AND expires_at > NOW() LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $deposit = $result->fetch_assoc();
        $stmt->close();
        return $deposit;
    }

    private function callZeroChainApi($endpoint, $params = []) {
        $url = "https://zerochain.info/api/$endpoint/{$this->apiKey}";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("ZeroChain API error: HTTP $httpCode, Response: $response");
            throw new Exception("ZeroChain API error: HTTP $httpCode");
        }

        if ($curlError) {
            error_log("cURL error: $curlError");
            throw new Exception("cURL error: $curlError");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (strpos($response, ':') !== false) {
                list($address, $privateKey) = explode(':', $response, 2);
                return ['address' => $address, 'private_key' => $privateKey];
            }

            error_log("Invalid JSON response: $response");
            throw new Exception("Invalid JSON response from ZeroChain API");
        }

        return $data;
    }

    public function generateNewAddress($userId) {
        $stmt = $this->mysqli->prepare("SELECT status FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lastDeposit = $result->fetch_assoc();
        $stmt->close();

        if ($lastDeposit && $lastDeposit['status'] === 'Pending') {
            throw new Exception("You already have a pending deposit address.");
        }

        $existingDeposit = $this->getActiveDepositAddress($userId);
        if ($existingDeposit) {
            return $existingDeposit['address'];
        }

        try {
            $data = $this->callZeroChainApi('getnewaddress');
            if (isset($data['address']) && isset($data['private_key'])) {
                $address = $data['address'];
                $privateKey = $data['private_key'];
                $expiresAt = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');

                $stmt = $this->mysqli->prepare("INSERT INTO deposits (user_id, address, private_key, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $userId, $address, $privateKey, $expiresAt);
                $stmt->execute();
                $stmt->close();

                return $address;
            } else {
                throw new Exception("Failed to retrieve address or private key from ZeroChain API");
            }
        } catch (Exception $e) {
            error_log("Error generating new address for user $userId: " . $e->getMessage());
            throw new Exception("Error generating new address: " . $e->getMessage());
        }
    }

    public function checkDeposits() {
        $urlTemplate = "https://zerochain.info/api/addressbalance/%s/{$this->apiKey}";
        $now = new DateTime();
        $threshold = (clone $now)->modify('-24 hours');

        $stmt = $this->mysqli->query("SELECT * FROM deposits WHERE status = 'Pending'");
        while ($deposit = $stmt->fetch_assoc()) {
            $url = sprintf($urlTemplate, $deposit['address']);
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            $balance = $data['balance'] ?? 0;

            if ($balance > 0) {
                $balanceInZER = $balance / 100000000; // Átváltás Zatoshi-ról ZER-re
                $updateStmt = $this->mysqli->prepare("UPDATE deposits SET amount = ?, status = 'Completed', updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("di", $balanceInZER, $deposit['id']);
                $updateStmt->execute();
                $updateStmt->close();
                $this->creditUserDeposit($deposit['user_id'], $balanceInZER);
            } elseif (new DateTime($deposit['created_at']) < $threshold) {
                $updateStmt = $this->mysqli->prepare("UPDATE deposits SET status = 'Rejected', updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $deposit['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    }

    public function getUserDeposits($userId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $deposits = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $deposits;
    }

    public function creditUserDeposit($userId, $amount) {
        $stmt = $this->mysqli->prepare("UPDATE users SET deposit = deposit + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $userId);
        if ($stmt->execute()) {
            error_log("User ID $userId credited with $amount ZER");
        } else {
            error_log("Failed to credit user ID $userId with $amount ZER: " . $stmt->error);
        }
        $stmt->close();
    }

    public function checkAddressBalance($address) {
        $url = "https://zerochain.info/api/addressbalance/$address/{$this->apiKey}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("ZeroChain API response for address $address: $response");

        if ($httpCode !== 200) {
            error_log("ZeroChain API error: HTTP $httpCode, Response: $response");
            throw new Exception("ZeroChain API error: HTTP $httpCode");
        }

        if ($curlError) {
            error_log("cURL error: $curlError");
            throw new Exception("cURL error: $curlError");
        }
        $trimmedResponse = trim($response);

        // Ha a válasz egy szám, akkor közvetlenül visszaadjuk
        if (is_numeric($trimmedResponse)) {
            return (int)$trimmedResponse;
        }
        
        // Ha JSON, akkor ellenőrizzük a struktúrát
        $data = json_decode($trimmedResponse, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['balance']) && is_numeric($data['balance'])) {
            return (int)$data['balance'];
        }
        
        // Ha egyik sem, akkor hiba
        error_log("Invalid API response: $response");
        throw new Exception("Invalid balance data in ZeroChain API response");
        
    }

    public function transferFunds($fromAddress, $privateKey, $amount, $toAddress) {
        $fees = 0.00000000; // Tranzakciós díj ZER-ben, lehet 0 is
        $amountSend = number_format(($amount), 8, '.', ''); 

        if ($amountSend <= 0) {
            throw new Exception("Amount after fees is too small to send.");
        }

        $send = 1; // 1: Azonnal elküldi a tranzakciót és visszaadja a TxID-t

        $url = "https://zerochain.info/api/rawtxbuild/{$privateKey}/{$toAddress}/{$amountSend}/{$fees}/{$send}/{$this->apiKey}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("Transfer request URL: $url");
        error_log("Transfer response: $response");

        if ($httpCode !== 200) {
            error_log("ZeroChain API transfer error: HTTP $httpCode, Response: $response");
            throw new Exception("ZeroChain API transfer error: HTTP $httpCode");
        }

        if ($curlError) {
            error_log("cURL error during transfer: $curlError");
            throw new Exception("cURL error during transfer: $curlError");
        }

        // Ellenőrizzük, hogy a válasz tartalmazza-e a TxID-t
        if (strpos($response, '"txid":"') !== false) {
            $pieces = explode('"txid":"', $response);
            $txid = explode('"', $pieces[1])[0];
            error_log("Transaction successful. TxID: $txid");
            return $txid;
        } else {
            error_log("Invalid transfer response: $response");
            throw new Exception("Invalid transfer response from ZeroChain API");
        }
    }
}
?>
