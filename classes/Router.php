<?php

class Router {
    private $user;
    private $config;
    private $loggedInPages = [
        'dashboard' => 'Dashboard',
        'faucet' => 'Faucet',
        'autofaucet' => 'AutoFaucet',
        'energyshop' => 'Energy Shop',
        'withdraw' => 'Withdraw',
        'offerwalls' => 'OfferWalls',
        'daily_bonus' => 'Daily Bonus',
        'bitcotasks_ptc' => 'Bitcotasks Offerwall PTC',
        'bitcotasks_shortlinks' => 'Bitcotasks Offerwall Shortlinks',
        'achievements' => 'Achievements',
        'shortlink' => 'Shortlinks',
        'referral' => 'Referral',
        'logout' => 'Logout'
    ];

    private $publicPages = [
        'home' => 'Home',
        'faq' => 'FAQ'
    ];

    public function __construct($user, $config) {
        $this->user = $user;
        $this->config = $config;
    }

    public function getPageTitle($page) {
        if (array_key_exists($page, $this->publicPages)) {
            return $this->publicPages[$page];
        } elseif ($this->user && array_key_exists($page, $this->loggedInPages)) {
            return $this->loggedInPages[$page];
        }
        return 'Page Not Found';
    }

    public function getPage($page) {
        // Ha publikus oldal, betöltjük
        if (array_key_exists($page, $this->publicPages)) {
            return "views/{$page}.php";
        }

        // Ha a user be van jelentkezve és a védett oldal létezik, betöltjük
        if ($this->user && array_key_exists($page, $this->loggedInPages)) {
            return "views/{$page}.php";
        }

        // Ha egyik sem létezik → 404
        return "views/404.php";
    }
}
?>
