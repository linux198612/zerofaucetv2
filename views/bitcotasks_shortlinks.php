<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$bitcotasksSL = new BitcotasksShortlinks($mysqli, $user, $config);

// API kampányok lekérése
$shortlinksData = $bitcotasksSL->getShortlinkCampaigns();

if ($shortlinksData['status'] !== '200') {
    $error = Core::sanitizeOutput($shortlinksData['message'] ?? 'Failed to retrieve shortlinks.');
    $shortlinks = [];
    $totalClicks = 0;
    $totalReward = 0;
} else {
    $shortlinks = $shortlinksData['data'];
    $totalClicks = array_sum(array_column($shortlinks, 'available'));
    $totalReward = array_reduce($shortlinks, function ($carry, $link) {
        return $carry + ($link['available'] * $link['reward']);
    }, 0);
}

// Zero konverzió
$currencyValue = $config->get('currency_value');
$totalRewardInUSD = $totalReward;
$totalRewardInZero = $totalRewardInUSD / $currencyValue;

include("header.php");
?>

<div class="container">
    <h1 class="my-4 page-title">Bitcotasks Shortlinks</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($shortlinks)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <p><strong>Total Clickable Shortlinks:</strong> <?= Core::sanitizeOutput($totalClicks) ?></p>
                        <p><strong>Total Available Reward in Zero:</strong> <?= Core::sanitizeOutput(number_format($totalRewardInZero, 8)) ?> Zero</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach ($shortlinks as $index => $link): ?>
                <?php 
                    $rewardInUSD = $link['reward'];
                    $rewardInZero = $rewardInUSD / $currencyValue;
                    $shortlinkUrl = filter_var($link['url'], FILTER_VALIDATE_URL) ? $link['url'] : '#'; // ✅ Biztonságos URL ellenőrzés
                ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= Core::sanitizeOutput($link['title']) ?></h5>
                            <p class="card-text">
                                <strong>Reward in Zero:</strong> <?= Core::sanitizeOutput(number_format($rewardInZero, 8)) ?> Zero<br>
                                <strong>Available:</strong> <?= Core::sanitizeOutput($link['available']) ?><br>
                                <strong>Limit:</strong> <?= Core::sanitizeOutput($link['limit']) ?>
                            </p>
                        </div>
                        <div class="card-footer text-center">
                            <a href="<?= Core::sanitizeOutput($shortlinkUrl) ?>" class="btn btn-primary" target="_blank">Visit</a>
                        </div>
                    </div>
                </div>

                <?php if (($index + 1) % 4 == 0): ?>
                    </div><div class="row">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center">No shortlinks available.</p>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>

