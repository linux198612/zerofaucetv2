<?php

// Inicializálás
if (!isset($user['id'])) {
    die('User ID is not set.');
}
$user = new User($mysqli, $user['id']);
$bitcotasks = new BitcotasksPTC($mysqli, $user, $config);

// API-ból kampányok lekérése
$campaignsData = $bitcotasks->getPTCCampaigns();

if (isset($campaignsData['status']) && $campaignsData['status'] == '200') {
    $campaigns = $campaignsData['data'];
    $totalCampaigns = count($campaigns);
    $totalRewards = array_sum(array_column($campaigns, 'reward'));
} else {
    $error = Core::sanitizeOutput($campaignsData['message'] ?? 'Failed to retrieve PTC campaigns.');
    $campaigns = [];
    $totalCampaigns = 0;
    $totalRewards = 0;
}

// USD és ZER konverzió
$currencyValue = $config->get('currency_value');
if ($currencyValue == 0) {
    die('Currency value cannot be zero.');
}
$totalRewardsInUSD = $totalRewards;
$totalRewardsInZero = $totalRewardsInUSD / $currencyValue;

include("header.php");
?>

<div class="container">
    <h1 class="mb-4 page-title">Bitcotasks PTC</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($campaigns)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <p><strong>Total Available Ads:</strong> <?= Core::sanitizeOutput($totalCampaigns); ?></p>
                        <p><strong>Total Rewards:</strong> <?= Core::sanitizeOutput(number_format($totalRewardsInZero, 8)); ?> Zero</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4" id="campaigns-list">
            <?php foreach ($campaigns as $index => $campaign): 
                $rewardInUSD = $campaign['reward'];
                $rewardInZero = $rewardInUSD / $currencyValue;
                $campaignUrl = filter_var($campaign['url'], FILTER_VALIDATE_URL) ? $campaign['url'] : '#'; // ✅ Biztonságos URL ellenőrzés
                if ($campaignUrl === '#') {
                    $error = 'Invalid campaign URL.';
                }
            ?>
                <div class="col campaign-card" id="campaign-<?= Core::sanitizeOutput($index); ?>">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= Core::sanitizeOutput($campaign['title']); ?></h5>
                            <p class="card-text"><?= Core::sanitizeOutput($campaign['description']); ?></p>
                            <p><strong>Reward:</strong> <?= Core::sanitizeOutput(number_format($rewardInZero, 8)); ?> Zero</p>
                            <p><strong>Duration:</strong> <?= Core::sanitizeOutput($campaign['duration']) . ' seconds'; ?></p>
                            <a href="<?= Core::sanitizeOutput($campaignUrl); ?>" target="_blank" class="btn btn-primary btn-sm mt-auto" onclick="hideCampaign(<?= Core::sanitizeOutput($index); ?>)">View Ad</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="page-title text-center">No available PTC ads.</p>
    <?php endif; ?>
</div>

<script>
function hideCampaign(index) {
    document.getElementById(`campaign-${index}`).style.display = 'none';
}
</script>

<?php include("footer.php"); ?>

