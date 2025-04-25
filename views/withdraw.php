<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$withdraw = new Withdraw($mysqli, $user, $config);

$alertMessage = ""; // Üzenet tárolása

$alertMessage = $_SESSION['alertMessage'] ?? "";
unset($_SESSION['alertMessage']);


if (
    ($_SERVER['REQUEST_METHOD'] === 'POST') || 
    (isset($_GET['withdr']) && $_GET['withdr'] === 'fp')
) {
    try {
        Core::checkCsrfToken();
        error_log("Withdraw Request: " . json_encode($_POST));

        $withdrawResult = $withdraw->requestWithdrawal();
        $_SESSION['alertMessage'] = Core::alert(
            $withdrawResult['success'] ? "success" : "danger",
            $withdrawResult['message']
        );
    } catch (Exception $e) {
        error_log("CSRF Error: " . $e->getMessage());
        $_SESSION['alertMessage'] = Core::alert("danger", "Invalid CSRF token. Please try again.");
    }

    header("Location: withdraw");
    exit();
}

$zerPrice = (float)$config->get('currency_value'); // ZER árfolyam a settings táblából
$userBalance = $withdraw->getUserBalance(); // Felhasználó ZER egyenlege

// FaucetPay valuták kártyái
$faucetPayCards = [];
if ($config->get('faucetpay_mode') === 'on') {
    $prices = Core::getCurrencyPrices($mysqli); // Árfolyamok lekérdezése
    foreach ($prices as $code => $price) {
        if ($code !== 'ZER') { // Csak más valuták esetében számolunk
            // Lekérdezzük a minimum withdrawal értéket a currencies táblából
            $stmt = $mysqli->prepare("SELECT minimum_withdrawal FROM currencies WHERE code = ?");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $stmt->bind_result($minWithdrawal);
            $stmt->fetch();
            $stmt->close();

            $faucetPayCards[] = [
                'name' => strtoupper($code),
                'code' => $code,
                'price' => $price,
                'equivalent' => round($userBalance * $zerPrice / $price, 8), // Átváltott érték kiszámítása
                'min_withdrawal' => $minWithdrawal ?: 0 // Ha nincs beállítva, alapértelmezett 0
            ];
        }
    }
}

include("header.php");
?>

<div class="container mt-4">

<?php
$withdrawLimitHour = (int)$config->get("withdrawlimithour");

function getIntervalText($hours) {
    if ($hours === 0) {
        return "There is no waiting time between withdrawals.";
    }

    $h = floor($hours);
    $m = ($hours - $h) * 60;
    $parts = [];

    if ($h > 0) {
        $parts[] = "$h hour" . ($h > 1 ? "s" : "");
    }

    if ($m > 0) {
        $parts[] = round($m) . " minute" . (round($m) > 1 ? "s" : "");
    }

    return "You need to wait at least " . implode(" and ", $parts) . " between withdrawal requests.";
}

$infoText = getIntervalText($withdrawLimitHour);

if ($withdraw->isManual()) {
    $infoText .= "<br>Manual withdrawals are currently active. Approval may take up to 12–24 hours.";
} else {
    $infoText .= "<br>If an instant withdrawal fails, your request will be marked as <strong>Pending</strong> for manual review.";
}
?>

<!-- Info box csak a várakozási időről -->
<div class="alert alert-secondary text-center mt-3">
    <?= $infoText ?>
</div>


    <?php if (!empty($alertMessage)): ?>
        <div class="alert-container mt-3">
            <?= $alertMessage; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- ZER kártya -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Withdraw ZER</h3>
                </div>
                <div class="card-body text-center">
                    <div class="alert alert-info text-center">
                        Withdrawal Mode: <strong><?= $withdraw->isManual() ? "Manual" : "Instant"; ?></strong>
                    </div>
                    <p class="lead font-weight-bold"><?= Core::sanitizeOutput(number_format($userBalance, 8)) ?> ZER</p>
                    <p>Minimum withdraw: <strong><?= Core::sanitizeOutput($withdraw->getMinWithdraw()) ?> Zatoshi</strong></p>
                    <?php if (empty($user->getAddress())): ?>
                        <div class="alert alert-warning">
                            Please add your ZeroCoin wallet address on the settings page before requesting a withdrawal.
                            <div class="mt-3"><a href="settings" class="btn btn-primary">Go to Settings</a></div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="withdraw">
                            <input type="hidden" name="csrf_token" value="<?= Core::generateCsrfToken(); ?>">
                            <input type="hidden" name="currency" value="ZER">
                            <input type="hidden" id="convertedAmount" name="convertedAmount" value="<?= Core::sanitizeOutput($userBalance); ?>"> <!-- Helyes érték -->
                            <button class="btn btn-lg btn-primary <?= $withdraw->canWithdraw() ? '' : 'disabled' ?>" <?= $withdraw->canWithdraw() ? '' : 'disabled' ?>>
                                Withdraw ZER
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FaucetPay összesített kártya -->
        <?php if ($config->get('faucetpay_mode') === 'on' && !empty($faucetPayCards)): ?>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="mb-0">Withdraw to FaucetPay</h3>
                    </div>
                    <div class="card-body text-center">
                        <div class="alert alert-info text-center">
                            Withdrawal Mode: <strong><?= $withdraw->isManual() ? "Manual" : "Instant"; ?></strong>
                        </div>
                        <?php if (empty($user->getUserData('fp_address'))): ?>
                            <div class="alert alert-warning">
                                Please add your FaucetPay email address on the settings page before requesting a withdrawal.
                                <div class="mt-3"><a href="settings" class="btn btn-primary">Go to Settings</a></div>
                            </div>
                        <?php else: ?>
                            <p>Withdraw your balance to FaucetPay in your preferred currency.</p>
                            <form method="POST" action="withdraw">
                                <input type="hidden" name="csrf_token" value="<?= Core::generateCsrfToken(); ?>">
                                <div class="form-group">
                                    <label for="currency">Select Currency:</label>
                                    <select class="form-control" id="currency" name="currency" required onchange="updateConvertedAmountAndCheckMin()">
                                        <?php foreach ($faucetPayCards as $card): ?>
                                            <option value="<?= Core::sanitizeOutput($card['code']) ?>" 
                                                    data-equivalent="<?= Core::sanitizeOutput($card['equivalent']) ?>" 
                                                    data-min-withdrawal="<?= Core::sanitizeOutput($card['min_withdrawal']) ?>">
                                                <?= Core::sanitizeOutput($card['name']) ?> (<?= Core::sanitizeOutput(number_format($card['equivalent'], 8)) ?> <?= Core::sanitizeOutput($card['code']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mt-3">
                                    <label for="toAddress">FaucetPay Address:</label>
                                    <input type="text" class="form-control" id="toAddress" name="toAddress" value="<?= htmlspecialchars($user->getUserData('fp_address')) ?>" readonly>
                                </div>
                                <div id="minWithdrawalMessage" class="alert alert-warning d-none"></div> <!-- Üzenet helye -->
                                <input type="hidden" id="convertedAmount" name="convertedAmount" value="">
                                <button id="withdrawButton" class="btn btn-lg btn-warning" disabled>
                                    Withdraw to FaucetPay
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <h2 class="mt-5 text-center">Last 10 Payments</h2>
    <div class="table-responsive">
        <table class="table table-hover table-bordered shadow-sm">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Transaction ID</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = $withdraw->getWithdrawHistory();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $statusColor = match ($row['status']) {
                            'Pending' => 'text-warning',
                            'Paid' => 'text-success',
                            'Rejected' => 'text-danger',
                            default => 'text-secondary',
                        };

                        $currencyLogo = "<img src='images/{$row['currency']}.png' alt='{$row['currency']}' style='width:24px; height: 24px;'>";

                        $txidDisplay = empty($row['txid']) ? "Transaction ID not available" : "<a href='https://zerochain.info/tx/" . Core::sanitizeOutput($row['txid']) . "' target='_blank' class='text-primary'>" . Core::sanitizeOutput($row['txid']) . "</a>";

                        echo "<tr>
                            <td class='align-middle'>" . Core::sanitizeOutput($row['id']) . "</td>
                            <td class='align-middle'>" . Core::sanitizeOutput($row['amount']) . " {$currencyLogo}</td>
                            <td class='align-middle'>" . date("d-m-Y H:i:s", strtotime(Core::sanitizeOutput($row['requested_at']))) . "</td>
                            <td class='align-middle'>{$txidDisplay}</td>
                            <td class='align-middle'><span class='{$statusColor} font-weight-bold'>" . Core::sanitizeOutput($row['status']) . "</span></td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center text-muted'>No payments found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Modernizált stílus */
.card {
    border-radius: 10px;
}

.table {
    border-radius: 10px;
    overflow: hidden;
}

/* Progress bar */
.progress {
    border-radius: 8px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.5s ease-in-out;
}

/* Táblázat modernizálása */
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.05);
}
</style>

<script>
function updateConvertedAmount() {
    const currencySelect = document.getElementById('currency');
    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
    const equivalent = selectedOption.getAttribute('data-equivalent');
    document.getElementById('convertedAmount').value = equivalent;
}
document.addEventListener('DOMContentLoaded', updateConvertedAmount);

function updateConvertedAmountAndCheckMin() {
    const currencySelect = document.getElementById('currency');
    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
    const equivalent = parseFloat(selectedOption.getAttribute('data-equivalent')) || 0; // Biztosítsuk, hogy lebegőpontos szám legyen
    const minWithdrawal = parseFloat(selectedOption.getAttribute('data-min-withdrawal')) || 0; // Biztosítsuk, hogy lebegőpontos szám legyen
    const userBalance = <?= json_encode($userBalance); ?>;

    // Frissítsük a convertedAmount mezőt lebegőpontos formátumban
    document.getElementById('convertedAmount').value = equivalent.toFixed(8);

    const minWithdrawalMessage = document.getElementById('minWithdrawalMessage');
    const withdrawButton = document.getElementById('withdrawButton');

    if (userBalance < minWithdrawal) {
        minWithdrawalMessage.textContent = `Your balance must be at least ${minWithdrawal.toFixed(2)} ZER to withdraw ${selectedOption.value}.`;
        minWithdrawalMessage.classList.remove('d-none');
        withdrawButton.disabled = true;
    } else {
        minWithdrawalMessage.classList.add('d-none');
        withdrawButton.disabled = false;
    }
}

// Győződjünk meg arról, hogy a convertedAmount mező mindig frissül betöltéskor
document.addEventListener('DOMContentLoaded', updateConvertedAmountAndCheckMin);
</script>

<?php include("footer.php"); ?>