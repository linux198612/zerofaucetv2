<?php


$user = new User($mysqli, $user['id']);
$autofaucet = new AutoFaucet($mysqli, $user, $config);

$timeAuto = Core::sanitizeOutput($config->get('autofaucet_interval') ?? 30);
$rewardAmount = Core::sanitizeOutput(number_format($config->get('autofaucet_reward') ?? 0.00001000, 8));
$energyReward = Core::sanitizeOutput($config->get('rewardEnergy') ?? 1);
$focusAuto = Core::sanitizeOutput($config->get('autofocus') ?? "off");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_button'])) {
    Core::checkCsrfToken(); // ✅ CSRF védelem

    $claimResult = $autofaucet->claimReward();
    $_SESSION['autofaucet_message'] = $claimResult;

    header("Location: autofaucet");
    exit();
}

$claimMessage = $_SESSION['autofaucet_message'] ?? null;
unset($_SESSION['autofaucet_message']);

include("header.php");
?>

<div class="container text-center mt-4">
    <?php if ($claimMessage): ?>
        <div class="alert alert-<?= $claimMessage['success'] ? 'success' : 'danger' ?>" role="alert">
            <?= Core::sanitizeOutput($claimMessage['message']) ?>
        </div>
    <?php endif; ?>

    <h1>AutoFaucet</h1>
    <p>Earn free ZER automatically every <?= Core::sanitizeOutput($timeAuto) ?> seconds.</p>

<div class="row">
<div class="col-12 col-md-3 text-center p-3">

      </div>
        <div class="col-12 col-md-6 text-center p-3">
              <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Reward per <?= Core::sanitizeOutput($timeAuto) ?> seconds</div>
                <div class="card-body">
                    <p class="card-text"><?= Core::sanitizeOutput($rewardAmount) ?> ZER</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Energy Reward</div>
                <div class="card-body">
                    <p class="card-text"><?= Core::sanitizeOutput($energyReward) ?> Energy</p>
                </div>
            </div>
        </div>
    </div>

    <div class="progress mt-4">
        <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
            <?= Core::sanitizeOutput($timeAuto) ?> seconds
        </div>
    </div>

    <form id="collect-form" method="POST" action="autofaucet">
        <input type="hidden" name="csrf_token" value="<?= Core::generateCsrfToken(); ?>"> <!-- ✅ CSRF token -->
        <input type="hidden" name="claim_button" value="1">
    </form>
        </div>
        <div class="col-12 col-md-3 text-center p-3">

        </div>
    </div>
    <div class="text-center">

    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let timeLeft = <?= json_encode($timeAuto) ?>;
    let progressBar = document.getElementById("progress-bar");
    let focusRequired = <?= json_encode($focusAuto === "on") ?>;
    
    let interval = setInterval(() => {
        if (!focusRequired || document.hasFocus()) { 
            if (timeLeft <= 0) {
                clearInterval(interval);
                document.getElementById("collect-form").submit();
            } else {
                timeLeft--;
                progressBar.style.width = (timeLeft / <?= json_encode($timeAuto) ?>) * 100 + "%";
                progressBar.innerText = timeLeft + " seconds";
            }
        }
    }, 1000);
});
</script>

<?php include("footer.php"); ?>


