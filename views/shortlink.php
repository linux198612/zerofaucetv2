<?php


// Inicializálás
$user = new User($mysqli, $user['id']);
$shortlink = new Shortlink($mysqli, $user, $config);

// 📌 Shortlink látogatás kezelése
$alertSL = '';
$shortlinkId = filter_input(INPUT_GET, 'visit_shortlink', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!empty($shortlinkId)) {
    $visitResult = $shortlink->visitShortlink($shortlinkId);
    if ($visitResult['success']) {
        header("Location: " . Core::sanitizeOutput($visitResult['redirect_url']));
        exit;
    } else {
        $alertSL = Core::alert("danger", $visitResult['message']);
    }
}

// 📌 Shortlink megtekintés utáni jutalom kezelése
$viewKey = filter_input(INPUT_GET, 'viewed', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!empty($viewKey)) {
    $rewardResult = $shortlink->rewardShortlink($viewKey);
    $alertSL = Core::alert($rewardResult['success'] ? "success" : "danger", $rewardResult['message']);
}

// 📌 Elérhető shortlinkek és összegző adatok lekérése
$shortlinkData = $shortlink->getAvailableShortlinks();
$availableShortlinks = $shortlinkData["shortlinks"];
$totalShortlinks = (int) $shortlinkData["totalShortlinks"];
$totalRewards = Core::sanitizeOutput(number_format($shortlinkData["totalRewards"], 8));

include("header.php");
?>

<div class="container mt-4">
    <!-- Összegző kártya -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card text-white bg-info mb-3">
                <div class="card-header text-center"><h5>Shortlink Earnings Summary</h5></div>
                <div class="card-body text-center">
                    <p class="card-text"><strong>Available Shortlinks:</strong> <?= $totalShortlinks ?></p>
                    <p class="card-text"><strong>Potential Earnings:</strong> <?= $totalRewards ?> ZER</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Visszajelzés üzenetek -->
    <div class="row">
        <div class="col-md-12 text-center">
            <?= $alertSL ?? '' ?>
        </div>
    </div>

    <?php if (!empty($availableShortlinks)): ?>
        <div class="row text-center">
            <?php foreach ($availableShortlinks as $sl): ?>
                <div class="col-md-3 mb-4">
                    <div class="card border-dark">
                        <div class="card-header"><?= Core::sanitizeOutput($sl['name']) ?></div>
                        <div class="card-body text-dark">
                            <p class="card-text">Views: <span id="views-<?= Core::sanitizeOutput($sl['id']) ?>"><?= Core::sanitizeOutput($sl['remaining_views']) ?></span></p>
                            <p class="card-text"><?= Core::sanitizeOutput($sl['reward']) ?> ZER</p>
                            <button class="btn btn-primary" onclick="visitShortlink('<?= Core::sanitizeOutput($sl['id']) ?>')">Visit</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="alert alert-danger text-center">No shortlinks available.</p>
    <?php endif; ?>
</div>

<script>
function visitShortlink(shortlinkId) {
    let button = event.target;
    button.disabled = true;
    button.innerHTML = "Please wait...";
    setTimeout(() => {
        window.location.href = 'shortlink?visit_shortlink=' + shortlinkId;
    }, 1000);
}
</script>

<?php include("footer.php"); ?>



