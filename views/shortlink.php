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
        header("Location: " . htmlspecialchars($visitResult['redirect_url'], ENT_QUOTES, 'UTF-8'));
        exit;
    } else {
        $alertSL = Core::alert("danger", htmlspecialchars($visitResult['message'], ENT_QUOTES, 'UTF-8'));
    }
}

// 📌 Shortlink megtekintés utáni jutalom kezelése
$viewKey = filter_input(INPUT_GET, 'viewed', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!empty($viewKey)) {
    $rewardResult = $shortlink->rewardShortlink($viewKey);
    $alertSL = Core::alert($rewardResult['success'] ? "success" : "danger", htmlspecialchars($rewardResult['message'], ENT_QUOTES, 'UTF-8'));
}

// 📌 Elérhető shortlinkek és összegző adatok lekérése
$shortlinkData = $shortlink->getAvailableShortlinks();
$availableShortlinks = $shortlinkData["shortlinks"];
$totalShortlinks = (int) $shortlinkData["totalShortlinks"];
$totalRewards = htmlspecialchars(number_format($shortlinkData["totalRewards"], 8), ENT_QUOTES, 'UTF-8');

include("header.php");
?>

<div class="container mt-4">
    <!-- Összegző kártya -->
    <div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-header">
                <h6>Total Shortlinks</h6>
            </div>
            <div class="card-body">
                <p class="card-text h5">Available Shortlinks:</strong> <?= $totalShortlinks ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-header">
                <h6>Total Earnings</h6>
            </div>
            <div class="card-body">
                <p class="card-text h5">Rewards:</strong> <?= $totalRewards ?> ZER</p>
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
                        <div class="card-header"><?= htmlspecialchars($sl['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="card-body text-dark">
                            <p class="card-text">Views: <span id="views-<?= htmlspecialchars($sl['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sl['remaining_views'], ENT_QUOTES, 'UTF-8') ?></span></p>
                            <p class="card-text"><?= htmlspecialchars($sl['reward'], ENT_QUOTES, 'UTF-8') ?> ZER</p>
                            <button class="btn btn-primary" onclick="visitShortlink('<?= htmlspecialchars($sl['id'], ENT_QUOTES, 'UTF-8') ?>')">Visit</button>
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
        window.location.href = 'shortlink?visit_shortlink=' + encodeURIComponent(shortlinkId);
    }, 1000);
}
</script>

<?php include("footer.php"); ?>



