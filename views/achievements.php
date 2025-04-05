<?php

// Inicializálás
$user = new User($mysqli, $user['id']);
$achievements = new Achievements($mysqli, $user);

// Ha a felhasználó claimelni akar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_button'])) {
    Core::checkCsrfToken(); // ✅ CSRF token ellenőrzés

    $achievementId = isset($_POST['achievement_id']) ? (int) $_POST['achievement_id'] : 0;
    $claimReward = isset($_POST['claim_reward']) ? (float) $_POST['claim_reward'] : 0;

    $claimResult = $achievements->claimAchievement($achievementId, $claimReward);
    $_SESSION['claim_message'] = $claimResult; // Üzenet mentése SESSION-be

    header("Location: achievements");
    exit;
}

// Jutalomüzenet
$claimMessage = $_SESSION['claim_message'] ?? null;
unset($_SESSION['claim_message']); // Törlés, hogy frissítéskor ne ismétlődjön

$achievementList = $achievements->getAllAchievements();

include("header.php");
?>

<div class="container">
    <?php if ($claimMessage): ?>
        <div class="alert alert-<?= $claimMessage['success'] ? 'success' : 'danger' ?>" role="alert">
            <?= Core::sanitizeOutput($claimMessage['message']) ?>
        </div>
    <?php endif; ?>

    <table class='table table-striped'>
        <thead>
            <tr>
                <th>Type</th>
                <th>Reward</th>
                <th>Your Progress</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($achievementList as $achievement): ?>
                <tr>
                    <td><?= Core::sanitizeOutput($achievement['condition']) . ' ' . Core::sanitizeOutput($achievement['type']) ?></td>
                    <td><?= Core::sanitizeOutput($achievement['reward']) ?> ZER</td>
                    <td><?= Core::sanitizeOutput($achievement['progress']) . " / " . Core::sanitizeOutput($achievement['condition']) ?></td>
                    <td>
                        <?php if ($achievement['can_claim']): ?>
                            <form method="POST" action="achievements">
                                <input type="hidden" name="csrf_token" value="<?= Core::generateCsrfToken(); ?>"> <!-- ✅ CSRF token -->
                                <input type="hidden" name="claim_reward" value="<?= Core::sanitizeOutput($achievement['reward']) ?>">
                                <input type="hidden" name="achievement_id" value="<?= Core::sanitizeOutput($achievement['id']) ?>">
                                <button type="submit" class="btn btn-success" name="claim_button">Claim</button>
                            </form>
                        <?php elseif ($achievement['already_claimed']): ?>
                            <button type="button" class="btn btn-secondary" disabled>Claimed</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger" disabled>Not Yet</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include("footer.php"); ?>


