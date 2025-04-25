<?php

session_start(); // Munkamenet indítása

$auth = new Auth($mysqli, $config);
$hcaptchaPubKey = Core::sanitizeOutput($config->get("hcaptcha_pub_key"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $loginResult = $auth->login($username, $password);
if ($loginResult === true) {
    header("Location: dashboard");
    exit;
} elseif ($loginResult === "Account Banned.") {
    $error = "Your account has been banned.";
} elseif (is_array($loginResult) && isset($loginResult['status']) && $loginResult['status'] === 'error') {
    $error = $loginResult['message'];
} else {
    $error = "Invalid username or password.";
}

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= $websiteUrl ?>favicon.png" type="image/png">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Courier New', Courier, monospace;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            background-color: #1e1e1e;
            border: 2px solid #4caf50;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        }
        .btn-primary {
            background-color: #4caf50;
            border: none;
        }
        .btn-primary:hover {
            background-color: #76ff03;
        }
        .form-control {
            background-color: #2b2b2b;
            border: 1px solid #4caf50;
            color: #e0e0e0;
        }
        .form-control:focus {
            border-color: #76ff03;
            box-shadow: 0 0 5px #76ff03;
        }
        .links a {
            color: #4caf50;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .links p {
            color: #e0e0e0; /* Szöveg színe */
        }
        .form-label {
    color: #ffffff;
}

    </style>
</head>
<body>
<div class="card p-4">
    <h2 class="text-center text-success mb-4">Login</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username:</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
                <div class="h-captcha" data-sitekey="<?= $hcaptchaPubKey ?>"></div>
		  <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <div class="links mt-3 text-center">
        <p>Don't have an account? <a href="register">Register here</a></p>
        <p>Forgot your password? <a href="password_recovery">Request a new one here</a></p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
