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

$totalClicks = isset($totalClicks) ? $totalClicks : 0;
$totalReward = isset($totalReward) ? $totalReward : 0;
include("header.php");
?>

<div class="container">

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
    <?php endif; ?>


        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-header">
                        <h6>Total Clickable Shortlinks</h6>
                    </div>
                    <div class="card-body">
                        <p class="card-text h5">Available Shortlinks:</strong> <?= Core::sanitizeOutput($totalClicks); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-header">
                        <h6>Total Available Reward</h6>
                    </div>
                    <div class="card-body">
                        <p class="card-text h5">Total Rewards:</strong> <?= Core::sanitizeOutput(number_format($totalReward, 5)); ?> ZER</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($shortlinks)): ?>
        <div class="row">
            <?php foreach ($shortlinks as $index => $link): ?>
                <?php 
                    $reward = $link['reward'];
                    $shortlinkUrl = filter_var($link['url'], FILTER_VALIDATE_URL) ? $link['url'] : '#'; // ✅ Biztonságos URL ellenőrzés
                ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= Core::sanitizeOutput($link['title']) ?></h5>
                            <p class="card-text">
                                <strong>Reward:</strong> <?= Core::sanitizeOutput(number_format($reward, 5)) ?> ZER<br>
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

