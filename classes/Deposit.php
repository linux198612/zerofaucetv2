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
        $stmt = $this->mysqli->prepare("SELECT id, address FROM wallet_addresses WHERE status = 'active' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();
        $stmt->close();

        if (!$wallet) {
            throw new Exception("Currently, there are no available wallet addresses for deposits. Please try requesting a wallet address later for your deposit.");
        }

        $expiresAt = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');
        $stmt = $this->mysqli->prepare("INSERT INTO deposits (user_id, wallet_id, address, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $userId, $wallet['id'], $wallet['address'], $expiresAt);
        $stmt->execute();
        $stmt->close();

        $stmt = $this->mysqli->prepare("UPDATE wallet_addresses SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $wallet['id']);
        $stmt->execute();
        $stmt->close();

        return $wallet['address'];
    }

    public function checkDeposits() {
        $now = new DateTime();
        $threshold = (clone $now)->modify('-24 hours');

        $stmt = $this->mysqli->query("SELECT d.id, d.wallet_id, d.address, d.created_at FROM deposits d WHERE d.status = 'Pending'");
        while ($deposit = $stmt->fetch_assoc()) {
            $balance = $this->checkAddressBalance($deposit['address']);
            if ($balance > 0) {
                $balanceInZER = $balance / 100000000;
                $updateStmt = $this->mysqli->prepare("UPDATE deposits SET amount = ?, status = 'Completed', updated_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("di", $balanceInZER, $deposit['id']);
                $updateStmt->execute();
                $updateStmt->close();
            } elseif (new DateTime($deposit['created_at']) < $threshold) {
                $updateStmt = $this->mysqli->prepare("DELETE FROM deposits WHERE id = ?");
                $updateStmt->bind_param("i", $deposit['id']);
                $updateStmt->execute();
                $updateStmt->close();

                $walletUpdateStmt = $this->mysqli->prepare("UPDATE wallet_addresses SET status = 'active' WHERE id = ?");
                $walletUpdateStmt->bind_param("i", $deposit['wallet_id']);
                $walletUpdateStmt->execute();
                $walletUpdateStmt->close();
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
