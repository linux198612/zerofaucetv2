<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$withdraw = new Withdraw($mysqli, $user, $config);

$alertMessage = ""; // Üzenet tárolása

// Ellenőrizzük, hogy van-e sessionban tárolt üzenet
$alertMessage = isset($_SESSION['alertMessage']) ? $_SESSION['alertMessage'] : "";
unset($_SESSION['alertMessage']); // Üzenet törlése a sessionból, hogy ne jelenjen meg újra

// Ellenőrizzük, hogy a felhasználó megadta-e a ZeroCoin tárca címét
if (empty($user->getAddress())) {
    $alertMessage = Core::alert("warning", "Please add your ZeroCoin wallet address on the settings page before requesting a withdrawal.");
    $alertMessage .= '<div class="mt-3"><a href="settings" class="btn btn-primary">Go to Settings</a></div>';
}

if (isset($_GET['withdr'])) {
    $withdrawRequest = htmlspecialchars($_GET['withdr'], ENT_QUOTES, 'UTF-8');

    if ($withdrawRequest === "fp") {
        Core::checkCsrfToken(); // ✅ CSRF védelem
        $withdrawResult = $withdraw->requestWithdrawal();
        $_SESSION['alertMessage'] = Core::alert($withdrawResult['success'] ? "success" : "danger", $withdrawResult['message']);
        header("Location: withdraw"); // Redirect, hogy frissítse az oldalt és megjelenjen az üzenet
        exit();
    }
}

include("header.php");
?>

<div class="container mt-4">
    <?php if (!empty($alertMessage)): ?>
        <div class="alert-container mt-3">
            <?= $alertMessage; ?>
        </div>
    <?php endif; ?>

    <div class="row">
    <?php if (!empty($user->getAddress())): ?>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Stats</h3>
                </div>
                <div class="card-body">
                    <h4 class="text-muted">Balance</h4>
                    <p class="lead font-weight-bold"><?= Core::sanitizeOutput($withdraw->getUserBalance()) ?> ZER</p>

                    <?php
                    $balance = $withdraw->getUserBalance() * 100000000; // ZER -> Zatoshi konvertálás
                    $minWithdraw = $withdraw->getMinWithdraw(); // Min withdraw már Zatoshi
                    
                    // Ellenőrizzük, hogy ne legyen 0-val osztás!
                    if ($minWithdraw > 0) {
                        $progressWithdraw = min(100, max(0, ($balance / $minWithdraw) * 100));
                    } else {
                        $progressWithdraw = 0; // Ha a minimum withdraw 0 lenne, akkor inkább 0% marad
                    }
                    ?>
                    <div class="progress mt-3" style="height: 18px; border-radius: 8px; overflow: hidden;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar"
                            style="width: <?= htmlspecialchars($progressWithdraw, ENT_QUOTES, 'UTF-8') ?>%;" 
                            aria-valuenow="<?= htmlspecialchars($progressWithdraw, ENT_QUOTES, 'UTF-8') ?>" 
                            aria-valuemin="0" 
                            aria-valuemax="100">
                            <?= round($progressWithdraw, 2) ?>%
                        </div>
                    </div>

                    <p class="mt-3"><strong>Total Withdraw:</strong> <?= Core::sanitizeOutput(number_format($withdraw->getTotalWithdraw(), 8)) ?> ZER</p>
                </div>
            </div>
        </div>

        
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white">
                    <h3 class="mb-0">Withdraw</h3>
                </div>
                <div class="card-body text-center">
                    <p class="mb-3">
                        <strong>Withdrawal Mode:</strong> 
                        <?= $withdraw->isManual() ? '<span class="text-warning">Manual</span>' : '<span class="text-success">Instant</span>' ?>
                    </p>
                </div>
                <script>
                function startWithdraw() {
                    let button = document.getElementById("withdraw-btn");
                    button.innerHTML = "Processing...";
                    button.disabled = true;

                    // Biztonságosabb módon kezeljük a CSRF tokent
                    let csrfToken = '<?= Core::generateCsrfToken(); ?>';
                    let url = new URL(window.location.href);
                    url.searchParams.set('withdr', 'fp');
                    url.searchParams.set('csrf_token', csrfToken);

                    window.location.href = url.toString();
                }
                </script>
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <p class="mb-3">Minimum withdraw: <strong><?= Core::sanitizeOutput($withdraw->getMinWithdraw()) ?> Zatoshi</strong></p>
                    <form method="POST" action="withdraw">
                        <input type="hidden" name="currency" value="ZER">
                        <button id="withdraw-btn" class="btn btn-lg btn-primary <?= $withdraw->canWithdraw() ? '' : 'disabled' ?>" <?= $withdraw->canWithdraw() ? '' : 'disabled' ?>>
                            Withdraw
                        </button>
                    </form>
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

                        echo "<tr>
                            <td class='align-middle'>" . Core::sanitizeOutput($row['id']) . "</td>
                            <td class='align-middle'>" . Core::sanitizeOutput($row['amount']) . " {$currencyLogo}</td>
                            <td class='align-middle'>" . date("d-m-Y H:i:s", strtotime(Core::sanitizeOutput($row['requested_at']))) . "</td>
                            <td class='align-middle'><a href='https://zerochain.info/tx/" . Core::sanitizeOutput($row['txid']) . "' target='_blank' class='text-primary'>" . Core::sanitizeOutput($row['txid']) . "</a></td>
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

<?php include("footer.php"); ?>