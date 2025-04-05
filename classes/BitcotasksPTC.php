<?php

class BitcotasksPTC {
    private $mysqli;
    private $user;
    private $config;

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
            "Authorization: Bearer $token"
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("Bitcotasks API request failed: " . curl_error($ch));
            $response = false;
        }
        curl_close($ch);
        return $response;
    }

    public function getPTCCampaigns() {
        $apiKey = $this->config->get('bitcotasks_api_key');
        $bearerToken = $this->config->get('bitcotasks_bearer_token');

        // 🔹 Ellenőrizzük, hogy az API adatok nem üresek
        if (empty($apiKey)) {
            return ['status' => 500, 'message' => 'Missing API Key (bitcotasks_api).'];
        }
        if (empty($bearerToken)) {
            return ['status' => 500, 'message' => 'Missing Bearer Token (bitcotasks_bearer_token).'];
        }

        $userIp = $_SERVER['REMOTE_ADDR'];
        $userId = $this->user->getUserData('id');
        $url = "https://bitcotasks.com/api/$apiKey/$userId/$userIp";

        $response = $this->requestWithCurl($url, $bearerToken);

        if ($response) {
            return json_decode($response, true);
        }

    }
}
?>

