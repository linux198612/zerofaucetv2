<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$shortlink = new Shortlink($mysqli, $user, $config);

// Shortlink látogatás kezelése
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

// Shortlink megtekintés utáni jutalom kezelése
$viewKey = filter_input(INPUT_GET, 'viewed', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!empty($viewKey)) {
    $rewardResult = $shortlink->rewardShortlink($viewKey);
    $alertSL = Core::alert($rewardResult['success'] ? "success" : "danger", htmlspecialchars($rewardResult['message'], ENT_QUOTES, 'UTF-8'));
}

// Elérhető shortlinkek és összegző adatok lekérése
$shortlinkData = $shortlink->getAvailableShortlinks();
$availableShortlinks = $shortlinkData["shortlinks"];
$totalShortlinks = (int) $shortlinkData["totalShortlinks"];
$totalRewards = htmlspecialchars(number_format($shortlinkData["totalRewards"], 8), ENT_QUOTES, 'UTF-8');

// Lekérdezzük az utolsó 10 megtekintett shortlinket
$lastViewedShortlinks = $shortlink->getLastViewedShortlinks();

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
                    <p class="card-text h5">Available Shortlinks: <?= htmlspecialchars($totalShortlinks, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-header">
                    <h6>Total Earnings</h6>
                </div>
                <div class="card-body">
                    <p class="card-text h5">Rewards: <?= htmlspecialchars($totalRewards, ENT_QUOTES, 'UTF-8') ?> ZER</p>
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


    <!-- Last 10 Views Log -->
    <div class="row mt-5">
        <div class="col-md-12">
            <h5 class="text-center">Your Last 10 Views</h5>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Shortlink Name</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lastViewedShortlinks)): ?>
                        <?php foreach ($lastViewedShortlinks as $view): ?>
                            <tr>
                                <td><?= htmlspecialchars($view['timestamp'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($view['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($view['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No views found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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



