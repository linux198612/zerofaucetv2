<?php

// Inicializáljuk a User és PTC osztályokat
$user = new User($mysqli, $user['id']);
$userId = $user->getUserData('id'); // Javítva: Az id lekérése az objektumból
$ptc = new PTC($mysqli, $user, $config);

// Lekérjük az aktív hirdetéseket
$ads = $ptc->getActiveAds($userId); // Javítva: $userId átadása a metódusnak

// Kategorizáljuk a hirdetéseket
$iframeAds = array_filter($ads, fn($ad) => $ad['ad_type'] === 'iframe');
$windowAds = array_filter($ads, fn($ad) => $ad['ad_type'] === 'window');

$iframeCount = count($iframeAds);
$windowCount = count($windowAds);

// Lekérjük a statisztikákat
$stats = $ptc->getAdsStatistics($userId);

// Kezeljük a POST kérést a "Claim Reward" gomb megnyomásakor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_reward'])) {
    $adId = (int)$_POST['ad_id'];

    try {
        $reward = $ptc->rewardUserForView($userId, $adId); // Jutalom jóváírása
        $_SESSION['successMessage'] = "Reward successfully claimed: " . number_format($reward, 8) . " ZER.";
        header("Location: ptc");
        exit;
    } catch (Exception $e) {
        $_SESSION['errorMessage'] = htmlspecialchars($e->getMessage());
        header("Location: ptc");
        exit;
    }
}

// Üzenetek megjelenítése és törlése a session-ből
$successMessage = $_SESSION['successMessage'] ?? null;
$errorMessage = $_SESSION['errorMessage'] ?? null;
unset($_SESSION['successMessage'], $_SESSION['errorMessage']);

include("header.php");
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-header">
                    <h6>Total Ads</h6>
                </div>
                <div class="card-body">
                    <p class="card-text h5"><?= $stats['total_ads'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-header">
                    <h6>Total Reward</h6>
                </div>
                <div class="card-body">
                    <p class="card-text h5"><?= number_format($stats['total_reward'], 8) ?> ZER</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php elseif (!empty($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <!-- Visszaszámlálás szekció -->
    <div id="countdown-section" style="display: none;" class="alert alert-info text-center">
        <p id="countdown-message">Time Remaining: <span id="countdown-timer"></span> seconds</p>
        <form method="post" id="claim-form" style="display: none;">
            <input type="hidden" name="ad_id" id="ad-id">
            <button type="submit" name="claim_reward" class="btn btn-success">Claim Reward</button>
        </form>
    </div>

  
    <!-- Nav-pill menü -->
    <ul class="nav nav-pills mb-4 justify-content-center">
        <li class="nav-item">
            <a class="nav-link active" id="iframe-tab" data-bs-toggle="pill" href="#iframe-ads">
                Iframe Ads (<?= $iframeCount ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="window-tab" data-bs-toggle="pill" href="#window-ads">
                Window Ads (<?= $windowCount ?>)
            </a>
        </li>
    </ul>

    <!-- Tab tartalom -->
    <div class="tab-content">
        <!-- Iframe hirdetések -->
        <div class="tab-pane fade show active" id="iframe-ads">
            <?php if (!empty($iframeAds)): ?>
                <div class="row row-cols-1 row-cols-md-4 g-4">
                    <?php foreach ($iframeAds as $ad): ?>
                        <div class="col">
                            <div class="card">
                                <div class="card-header text-center">
                                    <h5><?= htmlspecialchars($ad['title']) ?></h5>
                                </div>
                                <div class="card-body text-center">
                                    <p class="card-text">Reward: <strong><?= number_format($ad['reward'], 8) ?> ZER</strong></p>
                                    <p class="card-text">Duration: <strong><?= $ad['duration_seconds'] ?> seconds</strong></p>
                                    <a href="iframe_view?ad_id=<?= $ad['id'] ?>" class="btn btn-primary">View in Iframe</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="alert alert-info text-center">No iframe ads available at the moment.</p>
            <?php endif; ?>
        </div>

        <!-- Window hirdetések -->
        <div class="tab-pane fade" id="window-ads">
            <?php if (!empty($windowAds)): ?>
                <div class="row row-cols-1 row-cols-md-4 g-4">
                    <?php foreach ($windowAds as $ad): ?>
                        <div class="col">
                            <div class="card">
                                <div class="card-header text-center">
                                    <h5><?= htmlspecialchars($ad['title']) ?></h5>
                                </div>
                                <div class="card-body text-center">
                                    <p class="card-text">Reward: <strong><?= number_format($ad['reward'], 8) ?> ZER</strong></p>
                                    <p class="card-text">Duration: <strong><?= $ad['duration_seconds'] ?> seconds</strong></p>
                                    <a href="<?= htmlspecialchars($ad['url']) ?>" target="_blank" class="btn btn-primary" onclick="startCountdown(<?= $ad['id'] ?>, <?= $ad['duration_seconds'] ?>, <?= number_format($ad['reward'], 8) ?>)">Visit</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="alert alert-info text-center">No window ads available at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let activeAdId = null;
let countdownInterval = null;
const originalTitle = document.title; // Eredeti cím mentése

function startCountdown(adId, duration, reward) {
    if (activeAdId !== null) {
        alert("You must complete the current ad before starting a new one.");
        return;
    }

    activeAdId = adId;
    const countdownSection = document.getElementById('countdown-section');
    const countdownMessage = document.getElementById('countdown-message');
    const countdownTimer = document.getElementById('countdown-timer');
    const claimForm = document.getElementById('claim-form');
    const adIdInput = document.getElementById('ad-id');

    countdownSection.style.display = "block";
    claimForm.style.display = "none";
    adIdInput.value = adId;

    let remainingTime = duration;

    countdownInterval = setInterval(() => {
        if (remainingTime <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            countdownMessage.innerHTML = `Reward: ${reward} ZER`; // Jutalom megjelenítése
            claimForm.style.display = "block"; // "Claim Reward" gomb megjelenítése
            document.title = "Ready to Claim!"; // Visszaszámlálás vége
        } else {
            countdownTimer.textContent = remainingTime;
            document.title = `${remainingTime} seconds remaining`; // Csak a visszaszámlálás jelenik meg
            remainingTime--;
        }
    }, 1000);
}

function startIframeCountdown(adId, duration, reward) {
    if (activeAdId !== null) {
        alert("You must complete the current ad before starting a new one.");
        return;
    }

    activeAdId = adId;
    const countdownSection = document.getElementById('countdown-section');
    const countdownMessage = document.getElementById('countdown-message');
    const countdownTimer = document.getElementById('countdown-timer');
    const claimForm = document.getElementById('claim-form');
    const adIdInput = document.getElementById('ad-id');

    countdownSection.style.display = "block";
    claimForm.style.display = "none";
    adIdInput.value = adId;

    let remainingTime = duration;

    countdownInterval = setInterval(() => {
        if (remainingTime <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            countdownMessage.innerHTML = `Reward: ${reward} ZER`; // Jutalom megjelenítése
            claimForm.style.display = "block"; // "Claim Reward" gomb megjelenítése
            document.title = "Ready to Claim!"; // Visszaszámlálás vége
        } else {
            countdownTimer.textContent = remainingTime;
            document.title = `${remainingTime} seconds remaining`; // Csak a visszaszámlálás jelenik meg
            remainingTime--;
        }
    }, 1000);
}

window.addEventListener('focus', () => {
    if (countdownInterval && document.getElementById('countdown-timer').textContent !== "0") {
        clearInterval(countdownInterval);
        countdownInterval = null;
        const countdownSection = document.getElementById('countdown-section');
        countdownSection.style.display = "none";
        alert("You did not complete the ad viewing. Please start again.");
        activeAdId = null; // Reset active ad
        document.title = originalTitle; // Visszaállítjuk az eredeti címet
    }
});
</script>

<?php include("footer.php"); ?>
