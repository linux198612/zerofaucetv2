<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
<?php

$auth = new Auth($mysqli, $config);
$hcaptchaPubKey = Core::sanitizeOutput($config->get("hcaptcha_pub_key"));

$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $result = $auth->register($username, $email, $password);
    if ($result === true) {
        $successMessage = "Registration successful! You can now log in.";
    } else {
        $error = $result; // A hibaüzenetet közvetlenül a metódus visszatérési értékéből vesszük
    }
}
?>

<div class="card p-4">
    <h2 class="text-center text-success mb-4">Register</h2>
<?php if (isset($result) && is_array($result)): ?>
    <?php if ($result['status'] === 'success'): ?>
        <div class="alert alert-success text-center">
            <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php elseif ($result['status'] === 'error'): ?>
        <div class="alert alert-danger text-center">
            <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username:</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
            <div style="display:none;">
        <label for="honeypot"></label>
        <input type="text" name="honeypot" id="honeypot" value="">
    </div>
        <div class="h-captcha" data-sitekey="<?= $hcaptchaPubKey ?>"></div>
		  <script src="https://js.hcaptcha.com/1/api.js" async defer></script>

        <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>
    <div class="links mt-3 text-center">
        <p>Already have an account? <a href="login">Login here</a></p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
