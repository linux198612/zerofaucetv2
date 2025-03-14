<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$energyShop = new EnergyShop($mysqli, $user);

// Lekérjük a felhasználó energiáját és az elérhető csomagokat
$userEnergy = Core::sanitizeOutput($energyShop->getUserEnergy());
$packages = $energyShop->getPackages();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['packageId'])) {
    Core::checkCsrfToken(); // ✅ CSRF védelem

    $packageId = isset($_POST['packageId']) ? (int) $_POST['packageId'] : 0;
    if ($packageId > 0 && $energyShop->isValidPackage($packageId)) { // Ellenőrizzük, hogy a packageId érvényes-e
        $claimResult = $energyShop->buyPackage($packageId);
        $_SESSION['energyshop_message'] = $claimResult;
    } else {
        $_SESSION['energyshop_message'] = ['success' => false, 'message' => 'Invalid package selected.'];
    }

    header("Location: energyshop");
    exit();
}

// Üzenet kiolvasása session-ből
$shopMessage = $_SESSION['energyshop_message'] ?? null;
unset($_SESSION['energyshop_message']);

include("header.php");
?>

<div class="container mt-5">
    <h1 class="mb-4 text-center text-primary fw-bold">Energy Shop</h1>

    <?php if ($shopMessage): ?>
        <div class="alert alert-<?= $shopMessage['success'] ? 'success' : 'danger' ?> text-center" role="alert">
            <?= Core::sanitizeOutput($shopMessage['message']) ?>
        </div>
    <?php endif; ?>

    <div class="text-center mb-4">
        <h5>Your Energy:</h5>
        <p class="display-6 text-success fw-bold"> <?= Core::sanitizeOutput($userEnergy) ?> ⚡</p>
    </div>

    <div class="row g-4">
        <?php foreach ($packages as $package): ?>
            <div class="col-md-4">
                <div class="card border-0 rounded-3 h-100 text-center p-3">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <h5 class="card-title text-primary fw-bold"> <?= Core::sanitizeOutput($package['name']) ?> </h5>
                        <p class="text-muted">Cost: <strong><?= Core::sanitizeOutput($package['energy_cost']) ?> Energy</strong></p>
                        <p class="text-success fw-bold">Reward: <?= Core::sanitizeOutput(number_format($package['zero_amount'], 4)) ?> ZER</p>
                        <?php if ($userEnergy >= $package['energy_cost']): ?>
                            <form method="post" action="energyshop" onsubmit="disableButton(<?= Core::sanitizeOutput($package['id']) ?>)">
                                <input type="hidden" name="csrf_token" value="<?= Core::generateCsrfToken(); ?>"> <!-- ✅ CSRF védelem -->
                                <input type="hidden" name="packageId" value="<?= Core::sanitizeOutput($package['id']) ?>">
                                <button type="submit" id="buyButton-<?= Core::sanitizeOutput($package['id']) ?>" class="btn btn-primary w-100">Buy</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>Not Enough Energy</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function disableButton(packageId) {
    document.getElementById('buyButton-' + packageId).disabled = true;
    document.getElementById('buyButton-' + packageId).innerHTML = 'Processing...';
}
</script>

<?php include("footer.php"); ?>

