<?php
session_start();

include("../classes/Database.php");
include("../classes/AdminConfig.php");

$db = Database::getInstance();
$mysqli = $db->getConnection();
$config = new AdminConfig($mysqli);

// Ellenőrizzük, hogy be van-e jelentkezve az admin
if (!isset($_SESSION['admin_username'])) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $mysqli->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $mysqli->query("SELECT * FROM admin_users WHERE username = '$username' LIMIT 1");

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['admin_username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $login_error = "Incorrect username or password.";
        }
    } else {
        $login_error = "Incorrect username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    <div class="card p-4" style="width: 400px;">
        <h3 class="text-center">Admin Login</h3>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <?php if (isset($login_error)): ?>
                <div class="alert alert-danger"><?= $login_error ?></div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>
<?php
    exit();
}


// Oldal kiválasztása
$page = isset($_GET['page']) ? $_GET['page'] : 'settings';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
        }

        #sidebar {
            width: 250px;
            height: 100vh;
            background: #343a40;
            padding-top: 20px;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            transition: all 0.3s;
        }

        #sidebar .nav-link {
            color: white;
            padding: 10px 20px;
            display: block;
        }

        #sidebar .nav-link:hover {
            background: #495057;
        }

        #content {
            margin-left: 250px;
            padding: 20px;
            width: 100%;
        }

        @media (max-width: 768px) {
            #sidebar {
                width: 200px;
            }
            #content {
                margin-left: 200px;
            }
        }
    </style>
</head>
<body>

<div id="sidebar">
    <h4 class="text-center">Admin Panel</h4>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="index.php?page=settings">⚙️ Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?page=password">🔑 Change Password</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?page=backup">📦 Database Backup</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?page=logout">🚪 Logout</a></li>
    </ul>
</div>

<div id="content">
<div class="container mt-4">
    <?php
    // A megfelelő oldal betöltése
    switch ($page) {
        case 'settings':
           if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $config->set('faucet_name', $_POST['faucet_name']);
    $config->set('website_url', $_POST['website_url']);
    echo "<div class='alert alert-success'>Settings updated successfully!</div>";
}

$faucet_name = $config->get('faucet_name');
$website_url = $config->get('website_url');
?>

<h1>Settings</h1>
<form method="post">
    <div class="mb-3">
        <label class="form-label">Faucet Name</label>
        <input type="text" name="faucet_name" class="form-control" value="<?= htmlspecialchars($faucet_name) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Website URL</label>
        <input type="text" name="website_url" class="form-control" value="<?= htmlspecialchars($website_url) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
</form>
<?php
            break;

case 'backup':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Adatbázis kapcsolat
        $db = Database::getInstance();
        $mysqli = $db->getConnection();

        // Az adatbázis neve, amit a Database osztályból olvassuk ki
        $dbname = $db->getDbName();

        // Fájlnév generálása
        $backupFile = $dbname . "_" . date("Y-m-d_H-i-s") . ".sql";

        // Fejlécek beállítása a letöltéshez
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backupFile . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // SQL dump generálása és kiíratása
        $tables = array();
        $result = $mysqli->query("SHOW TABLES");

        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $sqlScript = "";

        // Minden táblát mentünk
        foreach ($tables as $table) {
            // CREATE TABLE query
            $result = $mysqli->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            $sqlScript .= "\n\n" . $row[1] . ";\n\n";

            // Az adatok lekérése a táblából
            $result = $mysqli->query("SELECT * FROM `$table`");
            while ($row = $result->fetch_assoc()) {
                $sqlScript .= "INSERT INTO `$table` (" . implode(", ", array_keys($row)) . ") VALUES ('" . implode("', '", array_map([$mysqli, 'real_escape_string'], array_values($row))) . "');\n";
            }

            $sqlScript .= "\n\n";
        }

        // Az SQL dump kiírása
        echo $sqlScript;
        exit();
    }
    ?>

    <h2>Database Backup</h2>
    <p>Click the button to save the database!</p>
    <form method="post">
        <button type="submit" class="btn btn-primary">📦 Backup Now</button>
    </form>
    <?php
    break;

            
            
        case 'password':
      if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $username = $_SESSION['admin_username'];

    if ($config->changePassword($username, $current_password, $new_password)) {
        echo "<div class='alert alert-success'>Password changed successfully!</div>";
    } else {
        echo "<div class='alert alert-danger'>Incorrect current password!</div>";
    }
}
?>

<h1>Change Password</h1>
<form method="post">
    <div class="mb-3">
        <label class="form-label">Current Password</label>
        <input type="password" name="current_password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Change</button>
</form>
<?php
            break;
      
        case 'logout':
            session_unset();
            session_destroy();
            header("Location: index.php");
            exit();
        default:
            echo "<h1>404 - Page not found</h1>";
            break;
    }
    ?>
</div>

</div> <!-- Content vége -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

