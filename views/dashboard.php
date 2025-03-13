<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$dashboard = new Dashboard($mysqli, $config);

$currencyValue = $dashboard->getCurrencyValue();
$lastCheckTime = $dashboard->getLastCheckTime();

$levelSystemEnabled = $config->get('level_system') === "on";

// Szintlépés adatok
$currentLevel = (int)$user->getUserData('level');
$currentXP = (int)$user->getUserData('xp');
$xpThreshold = (int)$config->get('bonuslevelxp'); 
$maxLevel = (int)$config->get('bonusmaxlevel');
$coingecko_status = $config->get('coingecko_status');
$faucetBonus = $currentLevel * 0.1;

if ($currentLevel >= $maxLevel) {
    $xpToNextLevelText = "Maximum level reached.";
    $progressPercentage = 100;
} else {
    $xpToNextLevel = $xpThreshold - ($currentXP % $xpThreshold);
    $xpToNextLevelText = "<strong>$xpToNextLevel XP</strong>";
    $progressPercentage = ($currentXP % $xpThreshold) / $xpThreshold * 100;
}

$strokeDashOffset = 314 - (314 * $progressPercentage / 100);

$totalWithdrawals = Core::sanitizeOutput(number_format($user->getUserData('total_withdrawals'), 8));
$referralCount = Core::sanitizeOutput($dashboard->getReferralCount($user->getUserData('id')));
$referralEarnings = Core::sanitizeOutput(number_format($dashboard->getReferralEarnings($user->getUserData('id')), 8));
$userBalance = Core::sanitizeOutput(number_format($user->getUserData('balance'), 8));
$userEnergy = Core::sanitizeOutput($user->getUserData('energy'));

include("header.php");
?>

<div class="container mt-4">
    <div class="row">
        <!-- Bal oszlop -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="bi bi-currency-exchange me-2"></i>
                    <h5 class="mb-0">Currency Info</h5>
                </div>
                <div class="card-body text-center">
                    <p class="fw-bold text-primary"><?= Core::sanitizeOutput(number_format($currencyValue, 5)) ?> USD</p>
                        <?php if($coingecko_status == "on"){ ?>
                    <p class="text-muted">(Last Update: <?= Core::sanitizeOutput($lastCheckTime) ?>)</p>
           				   <?php } ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-person-circle me-2"></i>
                    <h5 class="mb-0">User Info</h5>
                </div>
                <div class="card-body text-center">
                    <p><strong>User ID:</strong> <?= Core::sanitizeOutput($user->getUserData('id')) ?></p>
                    <p><strong>Address:</strong> <?= Core::sanitizeOutput($user->getUserData('address')) ?></p>
                </div>
            </div>

            <?php if ($levelSystemEnabled): ?>
            <div class="card shadow-sm border-0 mt-3">             
    <div class="card-header bg-info text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
                <i class="bi bi-bar-chart-line me-2"></i>
                    <h5 class="mb-0">Level Information</h5>
        </div>
        <button class="btn btn-light btn-sm rounded-circle p-1 d-flex align-items-center justify-content-center"
                style="width: 24px; height: 24px;"
                data-bs-toggle="modal" data-bs-target="#levelInfoModal">
            <b><i class="bi bi-info text-primary"></i></b>
        </button>
    </div>                         
                <div class="card-body text-center">
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
            </div>
            <?php endif; ?>
        </div>

        <!-- Jobb oszlop -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="bi bi-wallet2 me-2"></i>
                    <h5 class="mb-0">Balance</h5>
                </div>
                <div class="card-body text-center">
                    <p class="fw-bold text-success"><?= $userBalance ?> ZER</p>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-secondary text-white d-flex align-items-center">
                    <i class="bi bi-lightning me-2"></i>
                    <h5 class="mb-0">Energy</h5>
                </div>
                <div class="card-body text-center">
                    <p class="text-warning"><?= $userEnergy ?> ⚡</p>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-warning text-white d-flex align-items-center">
                    <i class="bi bi-cash-stack me-2"></i>
                    <h5 class="mb-0">Total Withdrawals</h5>
                </div>
                <div class="card-body text-center">
                    <p><?= $totalWithdrawals ?> ZER</p>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-people me-2"></i>
                    <h5 class="mb-0">Referrals</h5>
                </div>
                <div class="card-body text-center">
                    <p><?= $referralCount ?> Users</p>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <i class="bi bi-gift me-2"></i>
            <h5 class="mb-0">Referral Earnings</h5>
        </div>
        <button class="btn btn-light btn-sm rounded-circle p-1 d-flex align-items-center justify-content-center"
                style="width: 24px; height: 24px;"
                data-bs-toggle="modal" data-bs-target="#referralInfoModal">
            <b><i class="bi bi-info text-primary"></i></b>
        </button>
    </div>
                <div class="card-body text-center">
                    <p><?= $referralEarnings ?> ZER</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Level Info Modal -->
<div class="modal fade" id="levelInfoModal" tabindex="-1" aria-labelledby="levelInfoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="levelInfoLabel">Level System Information</h5>
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


<!-- Referral Info Modal -->
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
/* Modern Progress Ring */
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
    stroke: #ddd;
    stroke-width: 12;
}
.progress-ring-fill {
    fill: none;
    stroke: url(#gradient);
    stroke-width: 12;
    transition: stroke-dashoffset 1s ease-in-out;
}
.progress-ring-text {
    font-family: Arial, sans-serif;
    font-size: 28px;
    font-weight: bold;
    fill: #333;
    text-anchor: middle;
    dominant-baseline: middle;
}

/* Level szöveg formázása */
.progress-ring-label {
    font-family: Arial, sans-serif;
    font-size: 10px;
    fill: #666;
    text-anchor: middle;
}
</style>

<!-- SVG Gradient Defs -->
<svg width="0" height="0">
    <defs>
        <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#28a745"></stop>
            <stop offset="100%" stop-color="#ffc107"></stop>
        </linearGradient>
    </defs>
</svg>

<?php include("footer.php"); ?>






