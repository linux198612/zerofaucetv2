<?php

// Inicializálás
$userId = Core::sanitizeInput($user['id']);
$user = new User($mysqli, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address'])) {
    $address = Core::sanitizeInput($_POST['address']);
    $userId = $user->getUserData('id');

    if (!empty($address)) {
        $stmt = $mysqli->prepare("UPDATE users SET address = ? WHERE id = ?");
        $stmt->bind_param("si", $address, $userId);
        if ($stmt->execute()) {
            $successMessage = "Address updated successfully!";
        } else {
            $errorMessage = "Failed to update address.";
        }
        $stmt->close();
    } else {
        $errorMessage = "Address cannot be empty.";
    }
}

$currentAddress = $user->getUserData('address');

include("header.php");
?>

<div class="container mt-5">
    <h1 class="text-center">Settings</h1>
    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php elseif (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-4">
        <div class="form-group">
            <label for="address">Your Address:</label>
            <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($currentAddress) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Save</button>
    </form>
</div>

<?php include("footer.php"); ?>
