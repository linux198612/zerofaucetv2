<?php
require_once("../classes/User.php");
require_once("../classes/Config.php");
require_once("../classes/Core.php");
require_once("../classes/Database.php");
require_once("../classes/Deposit.php");

$db = Database::getInstance();
$mysqli = $db->getConnection();
$config = new Config($mysqli); // Initialize Config

$deposit = new Deposit($mysqli, $config->get('zerochain_api'));
$now = new DateTime();

$stmt = $mysqli->query("SELECT d.id, d.wallet_id, d.address, d.user_id, d.created_at FROM deposits d WHERE d.status = 'Pending'");
while ($row = $stmt->fetch_assoc()) {
    $balance = $deposit->checkAddressBalance($row['address']);
    if ($balance > 0) {
        $balanceInZER = $balance / 100000000;

        $updateStmt = $mysqli->prepare("UPDATE deposits SET amount = ?, status = 'Completed', updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("di", $balanceInZER, $row['id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Frissítsük a felhasználó deposit egyenlegét
        $updateUserStmt = $mysqli->prepare("UPDATE users SET deposit = deposit + ? WHERE id = ?");
        $updateUserStmt->bind_param("di", $balanceInZER, $row['user_id']);
        $updateUserStmt->execute();
        $updateUserStmt->close();
    } elseif ((new DateTime($row['created_at'])) < $now->modify('-24 hours')) {
        $deleteStmt = $mysqli->prepare("DELETE FROM deposits WHERE id = ?");
        $deleteStmt->bind_param("i", $row['id']);
        $deleteStmt->execute();
        $deleteStmt->close();

        $walletUpdateStmt = $mysqli->prepare("UPDATE wallet_addresses SET status = 'active' WHERE id = ?");
        $walletUpdateStmt->bind_param("i", $row['wallet_id']);
        $walletUpdateStmt->execute();
        $walletUpdateStmt->close();
    }
}
?>
