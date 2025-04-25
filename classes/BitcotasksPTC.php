<?php

class BitcotasksPTC {
    private $mysqli;
    private $user;
    private $config;
    private $cachedCampaigns = null; // 🔒 Cache-elt válasz

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config;
    }

    private function requestWithCurl($url, $token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        
        error_log("Bitcotasks CURL request URL: $url");
        error_log("Bitcotasks CURL Bearer Token: $token");

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            error_log("Bitcotasks API request failed (cURL error): " . $curlError);
            $response = false;
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log("Bitcotasks API HTTP Code: $httpCode");
            error_log("Bitcotasks API Response: " . $response);
        }

        curl_close($ch);
        return $response;
    }
    
    public function getPTCCampaigns() {
        // 🔄 Ha már van válasz, azt adjuk vissza (API hívás nélkül)
        if ($this->cachedCampaigns !== null) {
            return $this->cachedCampaigns;
        }

        $apiKey = $this->config->get('bitcotasks_api_key');
        $bearerToken = $this->config->get('bitcotasks_bearer_token');
    
        if (empty($apiKey)) {
            return $this->cachedCampaigns = ['status' => 500, 'message' => 'Missing API Key (bitcotasks_api_key).'];
        }
        if (empty($bearerToken)) {
            return $this->cachedCampaigns = ['status' => 500, 'message' => 'Missing Bearer Token (bitcotasks_bearer_token).'];
        }
    
        $userIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
        $userId = $this->user->getUserData('id');

        $url = "https://bitcotasks.com/api/$apiKey/$userId/$userIp";

        error_log("Bitcotasks final constructed URL: $url");
        error_log("Bitcotasks user ID: $userId, IP: $userIp");

        $response = $this->requestWithCurl($url, $bearerToken);
    
        if ($response) {
            $data = json_decode($response, true);

            if (!is_array($data)) {
                return $this->cachedCampaigns = ['status' => 500, 'message' => 'Invalid JSON response from Bitcotasks API.'];
            }

            if (isset($data['status']) && $data['status'] == 200 && isset($data['data']) && is_array($data['data'])) {
                return $this->cachedCampaigns = $data['data'];
            }

            return $this->cachedCampaigns = [
                'status' => $data['status'] ?? 500,
                'message' => $data['message'] ?? 'Unknown API error.'
            ];
        }
    
        return $this->cachedCampaigns = ['status' => 500, 'message' => 'API request failed.'];
    }
}

?>


