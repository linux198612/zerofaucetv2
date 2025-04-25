<?php

// Inicializálás
$userId = Core::sanitizeInput($user['id']);
$user = new User($mysqli, $userId);
$dashboard = new Dashboard($mysqli, $config);

$currencyValue = $dashboard->getCurrencyValue();
$lastCheckTime = $dashboard->getLastCheckTime();

$levelSystemEnabled = $config->get('level_system') === "on";
$depositStatus = $config->get('deposit_status') === "on";
$faucetpayMode = $config->get('faucetpay_mode') === "on";
$userDeposit = $depositStatus ? Core::sanitizeOutput(number_format($user->getUserData('deposit'), 8)) : null;

// Szintlépés adatok
$currentLevel = (int)$user->getUserData('level');
$currentXP = (int)$user->getUserData('xp'); // Javítva: hozzáadva a $ jel
$xpThreshold = (int)$config->get('bonuslevelxp'); 
$maxLevel = (int)$config->get('bonusmaxlevel');
$coingecko_status = $config->get('coingecko_status');
$faucetBonus = $currentLevel * 0.1;

if ($currentLevel >= $maxLevel) {
    $xpToNextLevelText = "Maximum level reached.";
    $progressPercentage = 100;
} else {
    $xpToNextLevel = $xpThreshold - ($currentXP % $xpThreshold);
    $xpToNextLevelText = "<strong>" . Core::sanitizeOutput($xpToNextLevel) . " XP</strong>"; // Output sanitization
    $progressPercentage = ($currentXP % $xpThreshold) / $xpThreshold * 100;
}

$strokeDashOffset = 314 - (314 * $progressPercentage / 100);

$totalWithdrawals = Core::sanitizeOutput(number_format($user->getUserData('total_withdrawals'), 8));
$referralCount = Core::sanitizeOutput($dashboard->getReferralCount($user->getUserData('id')));
$referralEarnings = Core::sanitizeOutput(number_format($dashboard->getReferralEarnings($user->getUserData('id')), 8));
$userBalance = Core::sanitizeOutput(number_format($user->getUserData('balance'), 8));
$userEnergy = Core::sanitizeOutput($user->getUserData('energy'));

$prices = Core::getCurrencyPrices($mysqli);
$zerBalance = $user->getUserData('balance');

// Eltávolítjuk a $currencyOptions tömböt és a hozzá tartozó SQL-lekérdezést

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate_data'])) {
    // Adatok migrációja az oldusers táblából
    $stmt = $mysqli->prepare("SELECT address, balance, level, xp, joined, energy FROM oldusers WHERE address = ? LIMIT 1");
    $stmt->bind_param("s", $user->getUserData('address')); // Helyes metódushívás az address eléréséhez
    $stmt->execute();
    $oldUserData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($oldUserData) {
        // Lekérjük a jelenlegi balance, xp és energy értékeket
        $currentBalance = $user->getUserData('balance');
        $currentXP = $user->getUserData('xp');
        $currentEnergy = $user->getUserData('energy');

        $newBalance = $currentBalance + $oldUserData['balance'];
        $newXP = $currentXP + $oldUserData['xp'];
        $newEnergy = $currentEnergy + $oldUserData['energy'];

        $stmt = $mysqli->prepare("
            UPDATE users 
            SET balance = ?, level = ?, xp = ?, joined = ?, energy = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "diiidi",
            $newBalance, // Az új balance érték
            $oldUserData['level'],
            $newXP, // Az új XP érték
            $oldUserData['joined'],
            $newEnergy, // Az új energy érték
            $user->getUserData('id') // Helyes metódushívás az ID eléréséhez
        );
        $stmt->execute();
        $stmt->close();
    
        // Frissítjük az oldusers táblában az address-t
        $userAddress = $user->getUserData('address'); // Helyes metódushívás az address eléréséhez
        $stmt = $mysqli->prepare("UPDATE oldusers SET address = CONCAT(address, '-migrated') WHERE address = ?");
        $stmt->bind_param("s", $userAddress);
        $stmt->execute();
        $stmt->close();
    
        $_SESSION['success_message'] = "Data migrated successfully!";
        header("Location: dashboard");
        exit;
    }
}

// Ellenőrizzük, hogy van-e az oldusers tábla, és hogy van-e még nem migrált cím
$oldUserData = null;
$userAddress = $user->getUserData('address');

if (!empty($userAddress) && strpos($userAddress, '-migrated') === false) {
    $table_check = $mysqli->query("SHOW TABLES LIKE 'oldusers'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $mysqli->prepare("SELECT address, balance, level, xp, joined, energy FROM oldusers WHERE address = ? LIMIT 1");
        $stmt->bind_param("s", $userAddress);
        $stmt->execute();
        $oldUserData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}


include("header.php");
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Welcome Section -->
    <div class="welcome-section text-center mb-4">
        <h1 class="fw-bold">Welcome, <?= Core::sanitizeOutput($user->getUserData('username')) ?>!</h1>
        <p class="text-muted">Here is your dashboard overview.</p>
    </div>

    <!-- Main Grid Layout -->
    <div class="dashboard-grid">
        <!-- Left Column -->
        <div class="dashboard-left">
            <div class="currency-info">
                <h5>Currency Info</h5>
                <div class="zer-currency">
                    <img src="images/ZER.png" alt="ZER" class="currency-logo">
                    <div class="currency-details">
                        <p class="currency-name">ZER</p>
                        <p class="value"><?= Core::sanitizeOutput(number_format($currencyValue, 5)) ?> USD</p>
                        <?php if ($coingecko_status == "on"): ?>
                            <p class="last-update">Last Update: <?= Core::sanitizeOutput($lastCheckTime) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($faucetpayMode): ?>
                    <?php
                    // Faucetpay mód aktív valuták megjelenítése
                    $stmt = $mysqli->prepare("SELECT currency_name, code, price FROM currencies WHERE status = 'on' AND wallet = 'faucetpay'");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $faucetpayCurrencies = [];
                    while ($row = $result->fetch_assoc()) {
                        $row['price'] = rtrim(rtrim(number_format($row['price'], 6), '0'), '.'); // Nullák eltávolítása
                        $faucetpayCurrencies[] = $row;
                    }
                    $stmt->close();
                    ?>

                    <?php if (!empty($faucetpayCurrencies)): ?>
                        <div class="faucetpay-currencies">
                            <h6>FaucetPay Currencies</h6>
                            <div class="currency-list">
                                <?php foreach ($faucetpayCurrencies as $currency): ?>
                                    <div class="currency-item">
                                        <img src="images/<?= Core::sanitizeOutput($currency['code']) ?>.png" alt="<?= Core::sanitizeOutput($currency['currency_name']) ?>" class="currency-logo">
                                        <div class="currency-details">
                                            <span class="currency-name"><?= Core::sanitizeOutput($currency['currency_name']) ?></span>
                                            <span class="currency-price"><?= Core::sanitizeOutput($currency['price']) ?> USD</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($levelSystemEnabled): ?>
            <div class="level-info">
                <h5 class="d-flex align-items-center justify-content-between">
                    Level Information
                    <button class="info-button" data-bs-toggle="modal" data-bs-target="#levelInfoModal">
                        <i class="bi bi-info-lg"></i>
                    </button>
                </h5>
                <div class="progress-ring">
                    <svg width="120" height="120" viewBox="0 0 120 120">
                        <circle class="progress-ring-bg" cx="60" cy="60" r="50"></circle>
                        <circle class="progress-ring-fill" cx="60" cy="60" r="50" stroke-dasharray="314" stroke-dashoffset="<?= $strokeDashOffset ?>" transform="rotate(-90 60 60)"></circle>
                        <text x="60" y="55" class="progress-ring-text"><?= $currentLevel ?></text>
                        <text x="60" y="72" class="progress-ring-label">Level</text>
                    </svg>
                </div>
                <p>Faucet Bonus: <strong><?= rtrim(rtrim(number_format($faucetBonus, 1), '0'), '.') ?>%</strong></p>
                <p>XP: <strong><?= $currentXP ?></strong></p>
                <p>Next Level: <b><?= $xpToNextLevelText ?></b></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="dashboard-right">
            <div class="balance-info">
                <h5>Balance</h5>
                <p class="value"><?= $userBalance ?> ZER</p>
                <p class="usd-value">≈ <?= Core::sanitizeOutput(number_format($userBalance * $currencyValue, 5)) ?> USD</p>
            </div>

            <?php if ($depositStatus): ?>
            <div class="deposit-info">
                <h5>Deposit Balance</h5>
                <p class="value"><?= $userDeposit ?> ZER</p>
            </div>
            <?php endif; ?>

            <div class="energy-info">
                <h5>Energy</h5>
                <p class="value"><?= $userEnergy ?> ⚡</p>
            </div>

            <div class="withdrawals-info">
                <h5>Total Withdrawals</h5>
                <p class="value"><?= $totalWithdrawals ?> ZER</p>
            </div>

            <div class="referrals-info">
                <h5 class="d-flex align-items-center justify-content-between">
                    Referral Earnings
                    <button class="info-button" data-bs-toggle="modal" data-bs-target="#referralInfoModal">
                        <i class="bi bi-info-lg"></i>
                    </button>
                </h5>
                <p class="value"><?= $referralEarnings ?> ZER</p>
            </div>
        </div>
    </div>

    <!-- Migration Section -->
    <?php if (!empty($userAddress) && $oldUserData): ?>
        <div class="migration-section">
            <h5>Data Migration</h5>
            <p>Data found for this address in the old system:</p>
            <ul>
                <li>Balance: <?= number_format($oldUserData['balance'], 8) ?> ZER</li>
                <li>Level: <?= $oldUserData['level'] ?></li>
                <li>XP: <?= $oldUserData['xp'] ?></li>
                <li>Joined: <?= date('Y-m-d', $oldUserData['joined']) ?></li>
                <li>Energy: <?= $oldUserData['energy'] ?></li>
            </ul>
            <form method="POST">
                <input type="hidden" name="migrate_data" value="1">
                <button type="submit" class="btn btn-primary">Migrate Data</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<div class="modal fade" id="levelInfoModal" tabindex="-1" aria-labelledby="levelInfoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="levelInfoLabel">Level Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Max Level:</strong> <?= $maxLevel ?></p>
                <p><strong>XP Needed per Level:</strong> <?= $xpThreshold ?> XP</p>
                <p><strong>Faucet Bonus Increase per Level:</strong> 0.1%</p>
                <p><strong>Max Faucet Bonus at Level <?= $maxLevel ?>:</strong> <?= rtrim(rtrim(number_format($maxLevel * 0.1, 1), '0'), '.') ?>%</p>
                <p><strong>XP System:</strong></p>
                <ul>
                    <li>Daily Bonus: +10 XP</li>
                    <li>Faucet Claim: +1 XP</li>
                    <li>Offerwalls PTC Ad View: +1 XP</li>
                    <li>Offerwalls Shortlink Completion: +1 XP</li>
                    <li>Offerwall other tasks: +1 XP</li>
                    <li>Zerads PTC: +1 XP (if applicable)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="referralInfoModal" tabindex="-1" aria-labelledby="referralInfoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="referralInfoLabel">Referral Earnings Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>You earn referral rewards from:</strong></p>
                <ul>
                    <li>Faucet Claims</li>
                </ul>
                <p>Referral rewards are automatically credited to your account.</p>
            </div>
        </div>
    </div>
</div>

<style>
/* General Styles */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.dashboard-left, .dashboard-right {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.welcome-section h1 {
    color: #333;
}
.currency-info, .level-info, .balance-info, .deposit-info, .energy-info, .withdrawals-info, .referrals-info, .migration-section {
    background: #f9f9f9;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
.currency-info h5, .level-info h5, .balance-info h5, .deposit-info h5, .energy-info h5, .withdrawals-info h5, .referrals-info h5, .migration-section h5 {
    margin-bottom: 10px;
    color: #555;
}
.value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
}
.usd-value, .last-update {
    font-size: 0.9rem;
    color: #888;
}

/* Info Button */
.info-button {
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
}
.info-button:hover {
    background-color: #0056b3;
    transform: scale(1.1);
}
.info-button i {
    font-size: 18px;
}

/* Level Progress Ring */
.progress-ring {
    width: 240px;
    height: 240px;
    margin: auto;
    position: relative;
}
.progress-ring svg {
    width: 100%;
    height: 100%;
}
.progress-ring-bg {
    fill: none;
    stroke: #e6e6e6;
    stroke-width: 12;
}
.progress-ring-fill {
    fill: none;
    stroke: #007bff;
    stroke-width: 12;
    transition: stroke-dashoffset 1s ease-in-out;
}
.progress-ring-text {
    font-family: Arial, sans-serif;
    font-size: 24px;
    font-weight: bold;
    fill: #333;
    text-anchor: middle;
    dominant-baseline: middle;
}
.progress-ring-label {
    font-family: Arial, sans-serif;
    font-size: 12px;
    fill: #666;
    text-anchor: middle;
}

/* FaucetPay Currencies Styles */
.faucetpay-currencies {
    margin-top: 20px;
}
.currency-list {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}
.currency-item {
    display: flex;
    align-items: center;
    background: #f9f9f9;
    border-radius: 8px;
    padding: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    flex: 1 1 calc(33.333% - 15px); /* Három elem egy sorban */
    max-width: calc(33.333% - 15px);
}
.currency-logo {
    width: 40px;
    height: 40px;
    object-fit: contain;
    margin-right: 10px;
}
.currency-details {
    display: flex;
    flex-direction: column;
}
.currency-name {
    font-weight: bold;
    color: #333;
}
.currency-price {
    font-size: 0.9rem;
    color: #888;
}

/* ZER Currency Styles */
.zer-currency {
    display: flex;
    align-items: center;
    background: #f9f9f9;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    width: 100%; /* Kitölti a rendelkezésre álló helyet */
    margin-bottom: 20px;
}
.zer-currency .currency-logo {
    width: 40px;
    height: 40px;
    object-fit: contain;
    margin-right: 20px;
}
.zer-currency .currency-details {
    display: flex;
    flex-direction: column;
    flex-grow: 1; /* A szöveg kitölti a helyet */
}
.zer-currency .currency-name {
    font-weight: bold;
    font-size: 1rem;
    color: #333;
    margin-bottom: 10px;
}
.zer-currency .value {
    font-size: 1.2rem;
    font-weight: bold;
    color: #007bff;
}
.zer-currency .last-update {
    font-size: 1rem;
    color: #888;
}
</style>

<?php include("footer.php"); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>






