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
        'logout' => 'Logout',
        'deposit' => 'Deposit',
        'ptc' => 'PTC Ads',
        'advertise' => 'Advertise',
        'settings' => 'Settings',
        'iframe_view' => 'PTC'
    ];

    private $publicPages = [
        'home' => 'Home',
        'faq' => 'FAQ',
        'login' => 'Login',
        'register' => 'Register',
        'password_recovery' => 'Password Recovery'
    ];

    public function __construct($user, $config) {
        $this->user = $user;
        $this->config = $config;
    }

    public function getPageTitle($page) {
        $page = htmlspecialchars($page, ENT_QUOTES, 'UTF-8');
        if (array_key_exists($page, $this->publicPages)) {
            return $this->publicPages[$page];
        } elseif ($this->user && array_key_exists($page, $this->loggedInPages)) {
            return $this->loggedInPages[$page];
        }
        return 'Page Not Found';
    }

    public function getPage($page) {
        $page = htmlspecialchars($page, ENT_QUOTES, 'UTF-8');
        // Ha publikus oldal, betöltjük
        if (array_key_exists($page, $this->publicPages)) {
            return "views/{$page}.php";
        }

        // Ha a user be van jelentkezve és a védett oldal létezik, betöltjük
        if ($this->user && array_key_exists($page, $this->loggedInPages)) {
            return "views/{$page}.php";
        }

        // Ha nincs jogosultság, átirányítás a login oldalra
        if (!$this->user) {
            header("Location: login");
            exit;
        }

        error_log("Access denied or page not found for: {$page}");
        // Ha egyik sem létezik → 404
        return "views/404.php";
    }

}
?>