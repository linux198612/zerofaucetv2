<?php
require_once("../classes/User.php");
require_once("../classes/Config.php");
require_once("../classes/Core.php");
require_once("../classes/Database.php");


$db = Database::getInstance();
$mysqli = $db->getConnection();

$core = new Core($mysqli);

// 🔹 Retrieve Secret Key from database
$stmt = $mysqli->prepare("SELECT value FROM settings WHERE name = 'bitcotasks_secret_key' LIMIT 1");
if (!$stmt) {

    exit("ERROR: SQL error.");
}
$stmt->execute();
$stmt->bind_result($secret);
$stmt->fetch();
$stmt->close();


$subId      = urldecode($_REQUEST['subId'] ?? '');
$transId    = urldecode($_REQUEST['transId'] ?? '');
$offer_type = urldecode($_REQUEST['offer_type'] ?? '');
$offer_name = urldecode($_REQUEST['offer_name'] ?? '');
$reward = number_format(floatval($_REQUEST['reward'] ?? ''), 5, '.', '');
$signature  = urldecode($_REQUEST['signature'] ?? '');

// 🔹 Validate if all required parameters are present
if (empty($subId) || empty($transId) || empty($reward) || empty($signature)) {

    exit("ERROR: Missing parameters.");
}

// 🔹 Debug Signature Calculation
$expectedSignature = md5($subId . $transId . $reward . $secret);


// 🔹 Validate Signature
if ($expectedSignature !== $signature) {

    exit("ERROR: Signature doesn't match");
}



// 🔹 Instantiate User
$user = new User($mysqli, $subId);


// 🔹 Retrieve ZER exchange rate
$stmt = $mysqli->prepare("SELECT value FROM settings WHERE name = 'currency_value' LIMIT 1");
if (!$stmt) {
    exit("ERROR: SQL error.");
}
$stmt->execute();
$stmt->bind_result($exchangeRate);
$stmt->fetch();
$stmt->close();


// 🔹 Convert reward to ZER
$rewardZER = $reward / floatval($exchangeRate);

// 🔹 Update User Balance
$user->updateBalance($rewardZER, 1);

// 🔹 Log Transaction
$timestamp = time();
$stmt = $mysqli->prepare("INSERT INTO offerwalls_history (userid, offerwalls, offerwalls_name, type, amount, timestamp) 
                          VALUES (?, 'BitcoTasks', ?, ?, ?, ?)");
$stmt->bind_param("isssd", $subId, $offer_name, $offer_type, $rewardZER, $timestamp);
$stmt->execute();
$stmt->close();


// 🔹 Success Response
exit("200");

?>

