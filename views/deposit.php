<?php

// Inicializáljuk a User és PTC osztályokat
$user = new User($mysqli, $user['id']);
$userId = $user->getUserData('id'); // Javítva: Az id lekérése az objektumból

$deposit = new Deposit($mysqli, $config->get('api_key'));
$activeDeposit = $deposit->getActiveDepositAddress($userId);
$depositAddress = $activeDeposit['address'] ?? null;

$allDeposits = $deposit->getUserDeposits($userId);

// Új deposit address kérés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$depositAddress || $activeDeposit['status'] !== 'Pending')) {
    try {
        $depositAddress = $deposit->generateNewAddress($userId);
        $_SESSION['success_message'] = "New deposit address generated: $depositAddress";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    header("Location: deposit");
    exit;
}

include("header.php");
?>

<div class="container mt-4">
    <h3>Deposit</h3>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if ($depositAddress): ?>
        <p>Your deposit address is:</p>
        <div class="alert alert-info"><?= htmlspecialchars($depositAddress) ?></div>
        <p>This address is valid until: <?= htmlspecialchars($activeDeposit['expires_at']) ?></p>
        <p><strong>Note:</strong> If no deposit is made within 24 hours, the address will become available for others.</p>
    <?php else: ?>
        <div class="alert alert-info">
            Click the "Request Deposit Address" button to receive a deposit address for sending Zero Coin to the platform. There is no minimum or maximum limit for deposits. The amount you send will be credited to your deposit balance.<br>
            <strong>Important:</strong> We reuse wallet addresses, so never send funds to an address you have previously used out of habit. Always request a new deposit address before making a deposit. Each requested address can only be used for one deposit.<br>
            <strong>Note:</strong> It is possible to receive a wallet address that you have already used for a previous deposit. This happens because, after processing the deposited amount, we make the wallet address reusable.
        </div>
        <form method="POST">
            <button type="submit" class="btn btn-primary">Request Deposit Address</button>
        </form>
    <?php endif; ?>

    <h4>Your Deposit History</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Address</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allDeposits as $deposit): ?>
                <tr>
                    <td><?= htmlspecialchars($deposit['address']) ?></td>
                    <td><?= htmlspecialchars($deposit['amount']) ?> ZER</td>
                    <td><?= htmlspecialchars($deposit['status']) ?></td>
                    <td><?= htmlspecialchars($deposit['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include("footer.php"); ?>
