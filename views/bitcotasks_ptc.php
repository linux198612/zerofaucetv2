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
}


$totalCampaigns = isset($totalCampaigns) ? $totalCampaigns : 0;


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
                    <h6>Total Ads</h6>
                </div>
                <div class="card-body">
                <p class="card-text h5">Available Ads:</strong> <?= Core::sanitizeOutput($totalCampaigns); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-header">
                    <h6>Total Reward</h6>
                </div>
                <div class="card-body">
                    <p class="card-text h5">Total Rewards:</strong> <?= Core::sanitizeOutput(number_format($totalRewards, 5)); ?> ZER</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($campaigns)): ?>
        <div class="row row-cols-1 row-cols-md-3 g-4" id="campaigns-list">
            <?php foreach ($campaigns as $index => $campaign): 
                $reward = $campaign['reward'];
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
                            <p><strong>Reward:</strong> <?= Core::sanitizeOutput(number_format($reward, 5)); ?> ZER</p>
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

