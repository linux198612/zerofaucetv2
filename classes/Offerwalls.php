<?php

class Offerwalls {
    private $mysqli;
    private $user;
    private $config;
    private $offerwallsList = [
        'bitcotasks' => 'https://bitcotasks.com//offerwall/'
    ];

    public function __construct($mysqli, $user, $config) {
        $this->mysqli = $mysqli;
        $this->user = $user;
        $this->config = $config; 
    }

    // 🔹 Lekéri az elérhető offerwallokat és API kulcsaikat
    public function getOfferwallsData() {
        $offerwallsData = [];

        foreach ($this->offerwallsList as $name => $baseUrl) {
            $apiKey = $this->config->get("{$name}_api_key");
            $status = $this->config->get("{$name}_status");

            if ($apiKey) {
                $offerwallsData[$name] = [
                    'status' => $status,
                    'url' => "{$baseUrl}{$apiKey}/{$this->user->getUserData('id')}"
                ];
            }
        }

        return $offerwallsData;
    }


}
?>
