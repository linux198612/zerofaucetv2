<?php
// ——————————————————————————————
// 1) Mielőtt bármi kimenet lenne (őrlap, HTML, whitespace stb.), állítsd be a fejléceket:
// ——————————————————————————————
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
// Ha már kizárólag HTTPS-en fut az oldal, engedélyezheted az HSTS-t is:
// (ez csak akkor érvényes, ha biztosan minden URL-re HTTPS megy)
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// ——————————————————————————————
// 2) Utána jöhet a session és a többi inicializáció
// ——————————————————————————————
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

require_once "autoload.php";

$db = Database::getInstance();
$mysqli = $db->getConnection();

$core = new Core($mysqli);
$session = new Session($mysqli);
$user = $session->getUser();

$core->updateCoingeckoPrice();
$core->updateCurrenciesPrices(); // Árfolyamok frissítése a currencies táblában
$core->updateUserLevelAndXP($user['id']);

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Adatbázis kapcsolat (feltételezve, hogy már létezik $mysqli)
$config = new Config($mysqli);

// Router példányosítása
$router = new Router($user, $config);

$websiteUrl = $config->get('website_url');

// Karbantartás ellenőrzése
if ($config->get('maintenance') === 'on') {
    $pageTitle = 'Maintenance';
    include 'views/maintenance.php';
    exit;
}

// Oldal kiválasztása az URL alapján
$page = isset($_GET['page']) ? htmlspecialchars(trim($_GET['page'], '/'), ENT_QUOTES, 'UTF-8') : 'home';

// Dinamikus oldal betöltés
$pageTitle = $router->getPageTitle($page);
$templatePath = $router->getPage($page);

include($templatePath);