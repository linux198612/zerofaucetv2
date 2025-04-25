<?php

// Inicializálás
$userId = Core::sanitizeInput($user['id']);
$user = new User($mysqli, $userId);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Ellenőrizzük, hogy a felhasználó be van-e jelentkezve
    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized access.");
    }

    $userId = $_SESSION['user_id'];

    // Frissítendő mezők
    $walletAddress = isset($_POST['wallet_address']) ? $mysqli->real_escape_string($_POST['wallet_address']) : null;
    $fpAddress = isset($_POST['fp_address']) ? $mysqli->real_escape_string($_POST['fp_address']) : null;

    // Validáció
    if ($walletAddress !== null && $walletAddress !== '' && !preg_match('/^t1[a-zA-Z0-9]{0,}$/', $walletAddress)) {
        $errorMessage = "Invalid ZeroCoin address.";
    } elseif ($fpAddress !== null && $fpAddress !== '') {
        if (!filter_var($fpAddress, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Invalid FaucetPay email address.";
        } else {
            // FaucetPay API ellenőrzés
            $apiKey = $config->get('faucetpay_api_key'); // Javítva: a Config osztály get metódusát használjuk
            $apiUrl = 'https://faucetpay.io/api/v1/checkaddress';
            $postData = [
                'api_key' => $apiKey,
                'address' => $fpAddress
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);

            $responseData = json_decode($response, true);

            if ($responseData['status'] !== 200) {
                $errorMessage = "The FaucetPay email address does not belong to any user.";
            } else {
                // Frissítsük a felhasználó tárca címét és FaucetPay e-mail címét
                $stmt = $mysqli->prepare("UPDATE users SET address = ?, fp_address = ? WHERE id = ?");
                $stmt->bind_param("ssi", $walletAddress, $fpAddress, $userId);
                if ($stmt->execute()) {
                    $successMessage = "Settings updated successfully.";
                } else {
                    $errorMessage = "Failed to update settings.";
                }
                $stmt->close();
            }
        }
    }
}

// Lekérdezzük a felhasználó aktuális beállításait
$userId = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT address, fp_address FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$walletAddress = $userData['address'] ?? '';
$fpAddress = $userData['fp_address'] ?? '';

$currentAddress = $user->getUserData('address');

include("header.php");
?>


<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h2 class="mb-0">User Settings</h2>
        </div>
        <div class="card-body">
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?= $successMessage; ?></div>
            <?php elseif (isset($errorMessage)): ?>
                <div class="alert alert-danger"><?= $errorMessage; ?></div>
            <?php endif; ?>

            <form method="post" action="settings">
                <div class="form-group mb-3">
                    <label for="wallet_address" class="form-label">ZeroCoin Address:</label>
                    <input type="text" class="form-control" id="wallet_address" name="wallet_address" value="<?= htmlspecialchars($walletAddress); ?>">
                </div>
                <div class="form-group mb-3">
                    <label for="fp_address" class="form-label">FaucetPay Email Address:</label>
                    <input type="email" class="form-control" id="fp_address" name="fp_address" value="<?= htmlspecialchars($fpAddress) ?>">
                </div>
                <button type="submit" class="btn btn-success w-100">Save</button>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>
