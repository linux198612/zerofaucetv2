<?php

// Beállítások lekérése
$faucetName = Core::sanitizeOutput($config->get('faucet_name'));

// Ellenőrizzük, hogy van-e referral paraméter az URL-ben
if (isset($_GET['ref']) && is_numeric($_GET['ref'])) {
    $_SESSION['referral_id'] = intval($_GET['ref']);
}

// Statisztikák lekérése (biztonságos SQL)
$stmt = $mysqli->prepare("SELECT COUNT(id) AS user_count, SUM(total_withdrawals) AS total_withdrawn, SUM(balance) + SUM(total_withdrawals) AS total_collected FROM users");
$stmt->execute();
$stmt->bind_result($userCount, $totalWithdrawn, $totalCollected);
$stmt->fetch();
$stmt->close();

$userCount = $userCount ?? 0;
$totalWithdrawn = $totalWithdrawn ?? 0;
$totalCollected = $totalCollected ?? 0;

// FaucetPay valuták lekérdezése, ha a faucetpay_mode be van kapcsolva
$faucetPayCurrencies = [];
if ($config->get('faucetpay_mode') === 'on') {
    $stmt = $mysqli->prepare("SELECT code FROM currencies WHERE status = 'on'");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $faucetPayCurrencies[] = $row['code'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $faucetName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= $websiteUrl ?>favicon.png" type="image/png">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Courier New', Courier, monospace;
            min-height: 100vh;
        }
        .navbar-brand, .nav-link {
            color: #4caf50 !important;
        }
        .navbar-brand:hover, .nav-link:hover {
            color: #76ff03 !important;
        }
        .section {
            background-color: #1e1e1e;
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        }
        .stat-box {
            background-color: #2b2b2b;
            border: 2px solid #4caf50;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-box h5 {
            color: #76ff03;
        }
        .stat-box p {
            font-size: 24px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            padding: 10px;
            background-color: #1e1e1e;
            border-top: 2px solid #4caf50;
            color: #e0e0e0;
        }
        .footer a {
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/"><?= $faucetName ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq">FAQ</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="section text-center">
            <h1>Welcome to <?= $faucetName ?></h1>
            <p>Start collecting Zerocoins today!</p>
            <div>
                <a href="login" class="btn btn-success">Login</a>
                <a href="register" class="btn btn-success">Register</a>
            </div>
        </div>

        <div class="section">
            <h3 class="text-center">Supported Currencies and Processors</h3>
            <div class="row">
                <div class="col-md-6">
                    <h4 class="text-center">Supported Currencies</h4>
                    <div class="d-flex flex-wrap justify-content-center">
                        <div class="text-center m-2">
                            <img src="images/ZER.png" alt="ZeroCoin" style="width: 48px; height: 48px;">
                            <p>ZER</p>
                        </div>
                        <?php if (!empty($faucetPayCurrencies)): ?>
                            <?php foreach ($faucetPayCurrencies as $currencyCode): ?>
                                <div class="text-center m-2">
                                    <img src="images/<?= Core::sanitizeOutput($currencyCode) ?>.png" alt="<?= Core::sanitizeOutput($currencyCode) ?>" style="width: 48px; height: 48px;">
                                    <p><?= Core::sanitizeOutput($currencyCode) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <h4 class="text-center">Supported Processors</h4>
                    <div class="d-flex flex-wrap justify-content-center">
                        <div class="text-center m-2">
                            <img src="images/zerochain.png" alt="ZeroChain" style="height: 70px;">
                        </div>
                        <?php if ($config->get('faucetpay_mode') === 'on'): ?>
                            <div class="text-center m-2">
                                <img src="images/faucetpay.png" alt="FaucetPay" style="height: 70px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row text-center">
            <div class="col-md-4">
                <div class="stat-box">
                    <h5>Registered Users</h5>
                    <p><?= Core::sanitizeOutput($userCount) ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <h5>Total Collected</h5>
                    <p><?= Core::sanitizeOutput($totalCollected) ?> ZER</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <h5>Total Withdrawals</h5>
                    <p><?= Core::sanitizeOutput($totalWithdrawn) ?> ZER</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="text-center text-success">Earn Money with Us!</h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-gear-wide-connected" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Autofaucet</h5>
                            <p class="card-text">Automatically claim Zerocoins.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-droplet-half" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Faucet</h5>
                            <p class="card-text">Claim Zerocoins by performing tasks.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-award" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Achievements</h5>
                            <p class="card-text">Complete achievements to earn extra rewards.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-check" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Daily Bonus</h5>
                            <p class="card-text">Claim your daily reward every 24 hours.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-lightning-charge" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Energy Shop</h5>
                            <p class="card-text">Exchange energy for Zerocoins.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-bar-chart-line" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Level System</h5>
                            <p class="card-text">Earn XP and increase your Faucet rewards.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-cursor-fill" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">PTC</h5>
                            <p class="card-text">Pay-to-Click ads for quick earnings.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-link-45deg" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Shortlinks</h5>
                            <p class="card-text">Visit shortlinks to earn rewards.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-stack" style="font-size: 2rem; color: #ff7b00;"></i>
                            <h5 class="card-title text-warning">Offerwalls</h5>
                            <p class="card-text">Complete offers to earn Zerocoins.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="section">
            <h3 class="text-center text-success">Latest 10 Transactions</h3>
            <?php
            $stmt = $mysqli->prepare("
                SELECT w.id, u.username, w.amount, w.currency, w.requested_at, w.status 
                FROM withdrawals w
                JOIN users u ON w.user_id = u.id
                ORDER BY w.id DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo '<div class="alert alert-danger text-center">There are no transactions yet.</div>';
            } else {
                echo '<table class="table table-dark text-center">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Amount</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
                while ($row = $result->fetch_assoc()) {
                    $timeAgo = Core::findTimeAgo($row['requested_at']);
                    $statusClass = match ($row['status']) {
                        'Pending' => 'text-warning',
                        'Paid' => 'text-success',
                        'Rejected' => 'text-danger',
                        default => 'text-muted',
                    };
                    $currencyLogo = "<img src='images/{$row['currency']}.png' alt='{$row['currency']}' style='width: 24px; height: 24px;'>";

                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['username']}</td>
                        <td>" . Core::sanitizeOutput($row['amount']) . " {$currencyLogo}</td>
                        <td>{$timeAgo}</td>
                        <td><span class='{$statusClass}'>{$row['status']}</span></td>
                    </tr>";
                }
                echo '</tbody></table>';
            }
            ?>
        </div>


    </div>

    <div class="footer">
    <p>&copy; <?= date('Y') ?> <a href="./"><?= $faucetName ?></a>. All Rights Reserved. Version: <?= Core::sanitizeOutput($core->getVersion()) ?><br> Powered by <a href="https://coolscript.hu">CoolScript</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

