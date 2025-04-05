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

$stmt = $mysqli->query("SELECT * FROM deposits WHERE status = 'Pending'");
while ($row = $stmt->fetch_assoc()) {
    $balance = $deposit->checkAddressBalance($row['address']); // Lekérdezzük az egyenleget
    error_log("Address: {$row['address']}, Balance in zatoshis: $balance");

    $balanceInZER = $balance / 100000000; // Átváltás Zatoshi-ról ZER-re
    error_log("Address: {$row['address']}, Balance in ZER: $balanceInZER");

    if ($balanceInZER > 0) {
        $deposit->creditUserDeposit($row['user_id'], $balanceInZER);

        // Frissítjük a befizetés állapotát "Completed"-re
        $updateStmt = $mysqli->prepare("UPDATE deposits SET amount = ?, status = 'Completed', updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("di", $balanceInZER, $row['id']);
        $updateStmt->execute();
        $updateStmt->close();

        error_log("Deposit ID {$row['id']} updated to Completed with amount $balanceInZER");
    } elseif (new DateTime($row['expires_at']) < $now) {
        // Ha lejárt a cím érvényessége, frissítjük a státuszt "Rejected"-re
        $updateStmt = $mysqli->prepare("UPDATE deposits SET status = 'Rejected', updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();

        error_log("Deposit ID {$row['id']} updated to Rejected");
    }
}
?>
