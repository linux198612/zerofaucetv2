<?php

$deposit = new Deposit($mysqli, $config->get('api_key'));
$deposits = $deposit->getUserDeposits($user['id']);

include("header.php");
?>

<div class="container mt-4">
    <h3>Deposit History</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Address</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deposits as $deposit): ?>
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
