<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$referral = new Referral($mysqli, $user, $config);

// 📌 Adatok betöltése
$referralPercent = (float) $referral->getReferralPercent();
$referralLink = htmlspecialchars(Core::sanitizeOutput($referral->getReferralLink()), ENT_QUOTES, 'UTF-8');
$totalReferralEarnings = htmlspecialchars(Core::sanitizeOutput(number_format($referral->getTotalReferralEarnings(), 8)), ENT_QUOTES, 'UTF-8'); // Összes referral earnings
$referredUsers = $referral->getReferredUsers();

include("header.php");
?>

<div class="container mt-5">
    <?php if ($referralPercent > 0): ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 rounded-3 h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <h5 class="card-title text-primary fw-bold">Your Referral Link</h5>
                        <div class="input-group mt-3">
                            <input type="text" class="form-control text-center" id="refLink" value="<?= $referralLink ?>" readonly>
                            <button class="btn btn-primary" onclick="copyRefLink()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 rounded-3 h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <h5 class="card-title text-success fw-bold">Earn Rewards</h5>
                        <p class="card-text mt-2">Share your link and earn <strong><?= htmlspecialchars(Core::sanitizeOutput($referralPercent), ENT_QUOTES, 'UTF-8') ?>%</strong> commission!</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-5 text-center">
        <h4 class="fw-bold text-secondary">Total Referral Earnings</h4>
        <p class="display-5 text-success fw-bold"> <?= $totalReferralEarnings ?> ZER</p>
    </div>

    <?php if (!empty($referredUsers)): ?>
        <div class="table-responsive mt-4">
            <table class="table table-hover table-striped align-middle rounded">
                <thead class="table-dark">
                    <tr>
                        <th>Username</th>
                        <th>Last Activity</th>
                        <th>Referral Earnings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referredUsers as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars(Core::sanitizeOutput($user['username']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(Core::findTimeAgo($user['last_activity']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-success fw-bold"><?= htmlspecialchars(Core::sanitizeOutput(number_format($user['referral_earnings'], 8)), ENT_QUOTES, 'UTF-8') ?> ZER</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4 text-center" role="alert">
            No referrals found.
        </div>
    <?php endif; ?>
</div>

<script>
function copyRefLink() {
    let refLink = document.getElementById("refLink");
    refLink.select();
    refLink.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(refLink.value).then(() => {
        let btn = document.querySelector(".btn-primary");
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Copied!';
        setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy', 2000);
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>

<?php include("footer.php"); ?>