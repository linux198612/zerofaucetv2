<?php
$auth = new Auth($mysqli, $config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $result = $auth->sendPasswordRecoveryEmail($email);
    $message = $result ? "Password recovery email sent!" : "Failed to send email. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery</title>
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
    <h2 class="text-center text-success mb-4">Password Recovery</h2>
    <?php if (isset($message)): ?>
        <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">Enter your registered email:</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send Recovery Email</button>
    </form>
    <div class="links mt-3 text-center">
        <p>Already have an account? <a href="login">Login here</a></p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
