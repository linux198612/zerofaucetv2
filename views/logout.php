<?php
// Ellenőrizzük, hogy a session már el van-e indítva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Munkamenet törlése a felhasználó kijelentkeztetésekor
session_unset();
session_destroy();

// Átirányítás a home oldalra
header("Location: ./");
exit;
?>
