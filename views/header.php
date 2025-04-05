<?php
// Ha nincs bejelentkezve, átirányítás a home oldalra
if (empty($user) || !is_object($user) || !$user->getUserData('id')) {
    header("Location: home");
    exit;
}

$faucetName = Core::sanitizeOutput($config->get('faucet_name'));  // ✅ XSS védelem

// Beállítások lekérése OOP módon
$offerwalls_status = $config->get('offerwalls_status');
$claimStatus = $config->get('claim_enabled');
$ptcStatus = $config->get('zeradsptc_status');
$zerads_id = Core::sanitizeOutput($config->get('zerads_id'));
$shortlink_status = $config->get('shortlink_status');
$achievements_status = $config->get('achievements_status');
$dailybonus_status = $config->get('dailybonus_status');
$autofaucet_status = $config->get('autofaucet_status');
$energyshop_status = $config->get('energyshop_status');
$bitcotasks_ptc_status = $config->get('bitcotasks_ptc_status');
$bitcotasks_shortlink_status = $config->get('bitcotasks_shortlink_status');
$depositStatus = $config->get('deposit_status');
$ptc_status = $config->get('ptc_status');

$userId = Core::sanitizeOutput($user->getUserData('id'));
$websiteUrl = Core::sanitizeOutput($config->get('website_url'));
$pageTitle = Core::sanitizeOutput($pageTitle);

$pages = [
    'autofaucet' => ['status' => $autofaucet_status, 'icon' => 'bi-arrow-repeat', 'name' => 'AutoFaucet'],
    'energyshop' => ['status' => $energyshop_status, 'icon' => 'bi-shop', 'name' => 'EnergyShop'],
    'achievements' => ['status' => $achievements_status, 'icon' => 'bi-trophy', 'name' => 'Achievements'],
    'daily_bonus' => ['status' => $dailybonus_status, 'icon' => 'bi-calendar-check', 'name' => 'Daily Bonus'],
    'faucet' => ['status' => $claimStatus, 'icon' => 'bi-droplet', 'name' => 'Faucet'],
    'offerwalls' => ['status' => $offerwalls_status, 'icon' => 'bi-cash-stack', 'name' => 'Offerwalls'],
    'zeradsptc' => ['status' => $ptcStatus, 'icon' => 'bi-megaphone', 'name' => 'PTC (Zerads)', 'external' => true, 'url' => "https://zerads.com/ptc.php?ref=$zerads_id&user=$userId"],
    'ptc' => ['status' => $ptc_status, 'icon' => 'bi-cash-stack', 'name' => 'PTC #1'],
    'bitcotasks_ptc' => ['status' => $bitcotasks_ptc_status, 'icon' => 'bi-cash-stack', 'name' => 'PTC #2'],
    'shortlink' => ['status' => $shortlink_status, 'icon' => 'bi-link-45deg', 'name' => 'Shortlinks #1'],
    'bitcotasks_shortlinks' => ['status' => $bitcotasks_shortlink_status, 'icon' => 'bi-link', 'name' => 'Shortlinks #2']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
	 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="<?= $websiteUrl ?>favicon.png" type="image/png">

<style>

/* Breadcrumb Stílus - Középre igazítva és fix szélességben */
.breadcrumb-container {
    max-width: 100%; /* Ne lógjon ki az oldalról */
    padding-left: 15px; /* Sidebar miatt bal oldalon térköz */
    padding-right: 15px; /* Ne érjen ki a képernyő szélére */
}

/* Breadcrumb megjelenés */
.breadcrumb {
    background: rgba(240, 240, 240, 0.8); /* Lágy szürke háttér */
    border-radius: 8px; /* Lekerekített sarkok */
    padding: 10px 15px; /* Szellős padding */
    font-size: 14px; /* Szövegméret */
    font-weight: 500;
    display: flex;
    align-items: center;
    box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1); /* Finom árnyék */
    max-width: 100%; /* Ne lépje túl a tartalom szélességét */
    overflow-x: auto; /* Ha szükséges, görgethető legyen */
}

/* Breadcrumb elemek */
.breadcrumb-item {
    color: #555; /* Lágy sötétszürke szín */
}

.breadcrumb-item.active {
    font-weight: 600;
    color: #333; /* Sötétebb kiemelt szöveg */
}

/* Elválasztó */
.breadcrumb-item + .breadcrumb-item::before {
    content: "›"; /* Modern nyíl az elválasztáshoz */
    color: #777;
    padding: 0 8px;
}

/* ✅ Sidebar (Alapértelmezett: Látható PC-n, Mobilon rejtett) */
.sidebar {
    width: 250px;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #343a40;
    color: white;
    transition: left 0.3s ease-in-out;
    z-index: 1000;
}

/* ✅ Tartalom, ami igazodik a sidebarhoz (PC-n eltolva, Mobilon teljes szélesség) */
.content {
    transition: margin-left 0.3s ease-in-out;
}

/* Sidebar fejléc (oldalnév) */
.sidebar-header {
    padding: 20px 15px;
    text-align: center;
    background: #343a40;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

/* Cím stílus és animáció */
.sidebar-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #ffffff;
    display: inline-block;
    transition: transform 0.3s ease, color 0.3s ease;
}

.sidebar-title:hover {
    transform: scale(1.1);
    color: #f8f9fa;
}

/* Menü elválasztó vonal */
.sidebar hr {
    margin: 10px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

/* Menü elemek */
.sidebar .nav-link {
    padding: 5px 15px;
    font-size: 15px;
    color: rgba(255, 255, 255, 0.75);
    display: flex;
    align-items: center;
}

.sidebar .nav-link i {
    margin-right: 10px;
}

.sidebar .nav-link:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}

/* ✅ PC-n a sidebar mindig látszik, mobilon alapból rejtett */
@media (min-width: 992px) {
    .content {
        margin-left: 250px;
    }
}

@media (max-width: 992px) {
    .sidebar {
        left: -250px;
    }
    .sidebar.active {
        left: 0;
    }
    .content {
        margin-left: 0;
    }
}

.footer {
background: #343a40;
}

/* Navbar toggle ikon mindig a menü fölött */
#sidebarToggle {
    z-index: 1100; /* Magasabb, mint a sidebar z-index */
}

</style>

</head>

<body>

<!-- Navbar (Mobilra toggle gomb) -->
<nav class="navbar navbar-dark bg-dark d-lg-none">
    <div class="container-fluid">
        <button class="btn btn-outline-light" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<!-- Sidebar -->
<nav class="sidebar">
    <!-- ✅ Oldal neve (Sidebar tetején) -->
<div class="sidebar-header">
    <span class="sidebar-title"><?= $faucetName ?></span>
</div>

    <!-- Menü -->
<!-- Menü -->
<ul class="nav flex-column p-3">
    <hr>
    <li class="nav-item">
        <a class="nav-link" href="<?= $websiteUrl ?>dashboard">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $websiteUrl ?>referral">
            <i class="bi bi-people"></i> Referral
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $websiteUrl ?>withdraw">
            <i class="bi bi-wallet2"></i> Withdraw
        </a>
    </li>
    <?php if ($depositStatus === "on"): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?= $websiteUrl ?>deposit">
            <i class="bi bi-wallet2"></i> Deposit
        </a>
    </li>
    <?php endif; ?>
    <hr>
    <?php foreach ($pages as $page => $data) {
        if ($data['status'] == "on") { ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= $data['external'] ? $data['url'] : $websiteUrl . $page ?>" <?= $data['external'] ? 'target="_blank"' : '' ?>>
                    <i class="bi <?= $data['icon'] ?>"></i> <?= $data['name'] ?>
                </a>
            </li>
    <?php } } ?>
    <hr>
    <li class="nav-item">
        <a class="nav-link" href="<?= $websiteUrl ?>advertise">
            <i class="bi bi-megaphone"></i> Advertise
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $websiteUrl ?>settings">
            <i class="bi bi-gear"></i> Settings
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-danger" href="<?= $websiteUrl ?>logout">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </li>
</ul>

</nav>


<!-- Tartalom -->
<main class="content">
    <article class="container-fluid">
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mt-3">
        <li class="breadcrumb-item active" aria-current="page"><?= $pageTitle ?></li>
    </ol>
</nav>

        <section>
        <div class="text-center">
</div>



