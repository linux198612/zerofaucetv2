<?php



// Auth osztály inicializálása
$auth = new Auth($mysqli);
$alertForm = $auth->handleRegistration();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $faucetName ?></title>
    <!-- Favicon -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">   
    <link rel="icon" href="<?= $websiteUrl ?>favicon.png" type="image/png">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            font-family: 'Roboto', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            margin-top: auto;
            margin-bottom: auto;
        }
        .section {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .navbar {
            background-color: rgba(0, 0, 0, 0.3);
        }
        .navbar-brand, .nav-link {
            color: #fff !important;
        }
        .navbar-brand:hover, .nav-link:hover {
            color: #ff7b00 !important;
        }
        h1, h3 {
            color: #f8f9fa;
        }
        .btn-primary {
            background-color: #ff7b00;
            border: none;
        }
        .btn-primary:hover {
            background-color: #e66a00;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #ddd;
        }
        table td {
            color: #fff !important;
            background: #1e3c72 !important;
            border: none;
        }
        table th {
            background-color: #2a5298 !important;
            color: #fff !important;
            border: none;
        }
        .footer a {
            color: white;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.1);
        }
        .orange { color: orange; }
        .green { color: green; }
        .red { color: red; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
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

    <!-- Main Container -->
    <div class="container text-center">
        <div class="section mb-4">
            <h1 class="display-5 fw-bold">Welcome to <?= $faucetName ?></h1>
            <p class="lead">Start collecting zerocoin today!</p>

            <div class="d-flex justify-content-center">
                <a href="login" class="btn btn-primary mx-2">Login</a>
                <a href="register" class="btn btn-secondary mx-2">Register</a>
            </div>
        </div>
        <div class="text-center">

    </div>
        <div class="section mb-4">
            <h3 class="mb-4">Important Announcement</h3>
            <div class="alert alert-warning text-start" style="background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid #ff7b00;">
                <p>Dear users,</p>
                <p>We kindly ask everyone to re-register on the site. However, your previous progress has not been lost. Once you provide your ZeroCoin wallet address on the dashboard page, the system will offer to restore your data from the old database.</p>
                <p><strong>Note:</strong> The wallet address cannot be modified after submission; it will remain permanent. If anyone encounters issues with the restoration process, please report it in the chat.</p>
                <p>Thank you for your patience and understanding.</p>
            </div>
        </div>
        <div class="row text-center">
            <div class="col-md-4">
                <div class="section">
                    <h5>Registered Users</h5>
                    <p class="display-6 fw-bold"><?= Core::sanitizeOutput($userCount) ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="section">
                    <h5>Total Collected</h5>
                    <p class="display-6 fw-bold"><?= Core::sanitizeOutput($totalCollected) ?> ZER</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="section">
                    <h5>Total Withdrawals</h5>
                    <p class="display-6 fw-bold"><?= Core::sanitizeOutput($totalWithdrawn) ?> ZER</p>
                </div>
            </div>
        </div>
<div class="row">
    <h3 class="mb-4">Earn Money with Us!</h3>
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-gear-wide-connected" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Autofaucet</h5>
                    <p class="card-text">Automatically claim Zerocoins.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-droplet-half" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Faucet</h5>
                    <p class="card-text">Claim Zerocoins by performing tasks.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-award" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Achievements</h5>
                    <p class="card-text">Complete achievements to earn extra rewards.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-check" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Daily Bonus</h5>
                    <p class="card-text">Claim your daily reward every 24 hours.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-lightning-charge" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Energy Shop</h5>
                    <p class="card-text">Exchange energy for Zerocoins.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-bar-chart-line" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Level System</h5>
                    <p class="card-text">Earn XP and increase your Faucet rewards.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-cursor-fill" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">PTC</h5>
                    <p class="card-text">Pay-to-Click ads for quick earnings.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-link-45deg" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Shortlinks</h5>
                    <p class="card-text">Visit shortlinks to earn rewards.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white" style="background: rgba(255, 255, 255, 0.1);">
                <div class="card-body text-center">
                    <i class="bi bi-stack" style="font-size: 2rem; color: #ff7b00;"></i>
                    <h5 class="card-title">Offerwalls</h5>
                    <p class="card-text">Complete offers to earn Zerocoins.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="text-center">
</div>
<div class="transactions">
    <h3>Latest 10 Transactions</h3>
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
        echo '<div class="alert alert-danger">There are no transactions yet.</div>';
    } else {
        echo '<table class="table table-striped">
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
                'Pending' => 'orange',
                'Paid' => 'green',
                'Rejected' => 'red',
                default => 'grey',
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
