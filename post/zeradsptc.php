<?php

require_once("../classes/User.php"); 
require_once("../classes/Config.php");// User osztály betöltése
require_once("../classes/Core.php");
require_once("../classes/Database.php");

$db = Database::getInstance();
$mysqli = $db->getConnection();

$core = new Core($mysqli);

if (!empty($_GET['pwd']) && $_GET['pwd'] === "xxxxxx") {
    $userId = $_GET['user'] ?? null;
    $amount = $_GET['amount'] ?? 0;
    //$clicks = $_GET['clicks'] ?? 1; // Ha nincs megadva, alapértelmezetten 1 XP jár

    if ($userId) {
        $user = new User($mysqli, $userId);

        $topay = floatval($amount) * 0.8; // Jutalom átszámítása
        //$xpEarned = intval($clicks); // XP a kattintások számától függően

        // Frissítjük a felhasználó egyenlegét és XP-jét
        $user->updateBalance($topay, 1);

        // Tranzakció naplózása
        $timestamp = time();
        $stmt = $mysqli->prepare("INSERT INTO transactions (userid, type, amount, timestamp) VALUES (?, 'PTC ZerAds', ?, ?)");
        $stmt->bind_param("idi", $userId, $topay, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}
?>
