<?php

// Inicializáljuk a User és PTC osztályokat
$user = new User($mysqli, $user['id']);
$userId = $user->getUserData('id'); // Javítva: Az id lekérése az objektumból
$advertise = new Advertise($mysqli);
$ptc = new PTC($mysqli, $user, $config);
$depositStatus = $config->get('deposit_status');

// Hirdetési csomagok lekérése
$packages = $advertise->getPackages();

// Felhasználó egyenlegének lekérése
$balances = $advertise->getUserBalances($userId);
$balance = (float)$balances['balance'];
$deposit = (float)$balances['deposit'];

// Kredit érték lekérése
$creditValue = $advertise->getCreditValue();
$maxCreditsFromBalance = floor($balance / $creditValue);
$maxCreditsFromDeposit = floor($deposit / $creditValue);

// Felhasználó krediteinek lekérése
$currentCredits = $advertise->getUserCredits($userId);

// Hirdetések kezelése
$messages = []; // Üzenetek tárolása

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_ad') {
            try {
                $title = htmlspecialchars($_POST['title']);
                $url = $_POST['url']; 
                $packageId = (int)$_POST['package_id'];
                $views = max(10, (int)$_POST['views']); // Minimum 10 megtekintés
                $adType = htmlspecialchars($_POST['ad_type']); // Az ad_type mező értékének lekérése
        
                $advertise->createAd($userId, $title, $url, $packageId, $views, $adType);
                $messages[] = "<div class='alert alert-success'>Ad created successfully with $views views!</div>";
            } catch (Exception $e) {
                $messages[] = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
            }
        } elseif ($action === 'convert_balance') {
            try {
                $amount = (float)$_POST['amount'];
                $source = $_POST['source']; // 'internal' vagy 'deposit'

                $conversionResult = $advertise->convertBalanceToCredits($userId, $amount, $source);
                 $messages[] = "<div class='alert alert-success'>Successfully converted $amount ZER to {$conversionResult['creditsAdded']} credits. </div>";
            } catch (Exception $e) {
                $messages[] = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
            }
        } elseif ($action === 'add_views') {
            try {
                $adId = (int)$_POST['ad_id'];
                $views = max(10, (int)$_POST['views']); // Minimum 10 megtekintés

                $advertise->addViewsToAd($userId, $adId, $views);
                $messages[] = "<div class='alert alert-success'>Successfully added $views views to the ad!</div>";
            } catch (Exception $e) {
                $messages[] = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
            }
        } elseif ($action === 'delete_ad') {
            try {
                $adId = (int)$_POST['ad_id'];

                $refundResult = $advertise->deleteAd($userId, $adId);
                $messages[] = "<div class='alert alert-success'>Ad deleted successfully. Refunded {$refundResult['creditsRefunded']} credits.</div>";
            } catch (Exception $e) {
                $messages[] = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
            }
        } elseif ($action === 'edit_ad_title') {
            try {
                $adId = (int)$_POST['ad_id'];
                $newTitle = htmlspecialchars($_POST['new_title']);
                $advertise->updateAdTitle($userId, $adId, $newTitle);
                $messages[] = "<div class='alert alert-success'>Ad title updated successfully!</div>";
            } catch (Exception $e) {
                $messages[] = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
            }
        }
    }
}

// Felhasználó hirdetéseinek lekérése
$ads = $advertise->getUserAds($userId);

include("header.php");
?>

<div class="container mt-4">
    <h1 class="text-center mb-4">Advertise</h1>

    <!-- Üzenetek megjelenítése -->
    <?php foreach ($messages as $message): ?>
        <?= $message ?>
    <?php endforeach; ?>

    <!-- Felhasználó aktuális krediteinek megjelenítése -->
    <div class="alert alert-info text-center">
        <strong>Your Current Credits:</strong> <?= number_format($currentCredits) ?> Credits
    </div>

    <!-- Hirdetési kreditek konvertálása -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h3>Convert Balance to Credits</h3>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <input type="hidden" name="action" value="convert_balance">
                <div class="form-group">
                    <label for="amount">Amount to Convert (ZER):</label>
                    <input type="number" step="0.00000001" class="form-control" id="amount" name="amount" required>
                </div>
                <div class="form-group mt-3">
                    <label for="source">Source:</label>
                    <select class="form-control" id="source" name="source">
                        <option value="internal">Internal Balance (<?= number_format($balance, 8) ?> ZER)</option>
                        <?php if ($depositStatus === "on"): ?>
                        <option value="deposit">Deposit Balance (<?= number_format($deposit, 8) ?> ZER)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group mt-3">
                    <label for="credits">Credits to Receive:</label>
                    <input type="text" class="form-control" id="credits" readonly>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Convert</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('amount').addEventListener('input', calculateCredits);

    function calculateCredits() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const creditValue = <?= json_encode((float)$creditValue) ?>;
        const credits = Math.floor(amount / creditValue); // Round down to the nearest whole number

        if (!isNaN(credits)) {
            document.getElementById('credits').value = credits + ' Credits';
        } else {
            document.getElementById('credits').value = 'Invalid input';
        }
    }
    </script>

    <!-- Új hirdetés létrehozása -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h3>Create New Ad</h3>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <input type="hidden" name="action" value="create_ad">
                <div class="form-group">
                    <label for="title">Ad Title:</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="form-group mt-3">
                    <label for="url">Ad URL:</label>
                    <input type="url" class="form-control" id="url" name="url" required>
                </div>
                <div class="form-group mt-3">
                    <label for="package_id">Package:</label>
                    <select class="form-control" id="package_id" name="package_id" required>
                        <?php 
                        usort($packages, function($a, $b) {
                            return $a['duration_seconds'] - $b['duration_seconds'];
                        });
                        foreach ($packages as $package): ?>
                            <option value="<?= $package['id'] ?>" data-credit-cost="<?= htmlspecialchars($package['zer_cost'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($package['duration_seconds'], ENT_QUOTES, 'UTF-8') ?> seconds
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mt-3">
                    <label for="ad_type">Ad Type:</label>
                    <select class="form-control" id="ad_type" name="ad_type" required>
                        <option value="window">Window</option>
                        <option value="iframe">Iframe</option>
                    </select>
                </div>
                <div class="form-group mt-3">
                    <label for="views">Number of Views (minimum 10):</label>
                    <input type="number" class="form-control" id="views" name="views" min="10" required>
                </div>
                <div class="form-group mt-3">
                    <label for="total_cost">Total Cost (Credits):</label>
                    <input type="text" class="form-control" id="total_cost" readonly>
                </div>
                <button type="submit" class="btn btn-success mt-3">Create Ad</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('package_id').addEventListener('change', calculateTotalCost);
    document.getElementById('views').addEventListener('input', calculateTotalCost);

    function calculateTotalCost() {
        const packageSelect = document.getElementById('package_id');
        const selectedOption = packageSelect.options[packageSelect.selectedIndex];
        const zerCost = parseFloat(selectedOption.getAttribute('data-credit-cost')) || 0;
        const creditValue = <?= json_encode((float)$creditValue) ?>;
        const views = parseInt(document.getElementById('views').value) || 0;

        const totalCredits = zerCost / creditValue * views;

        if (!isNaN(totalCredits)) {
            document.getElementById('total_cost').value = totalCredits.toFixed(2) + ' Credits';
        } else {
            document.getElementById('total_cost').value = 'Invalid input';
        }
    }
    </script>

    <!-- Meglévő hirdetések -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h3>Your Ads</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($ads)): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Views Remaining</th>
                            <th>Views Count</th>
                            <th>Type</th> <!-- Új oszlop a hirdetés típusához -->
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ads as $ad): 
                            $viewCount = $advertise->getAdViewCount($ad['id']); // Megtekintések számának lekérése
                        ?>
                            <tr>
                                <td>
                                    <form method="post" action="" class="d-inline">
                                        <input type="hidden" name="action" value="edit_ad_title">
                                        <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                        <input type="text" name="new_title" value="<?= htmlspecialchars($ad['title']) ?>" class="form-control mb-2" required>
                                        <button type="submit" class="btn btn-secondary btn-sm">Update Title</button>
                                    </form>
                                </td>
                                <td><a href="<?= htmlspecialchars($ad['url']) ?>" target="_blank"><?= htmlspecialchars($ad['url']) ?></a></td>
                                <td><?= $ad['views_remaining'] ?></td>
                                <td><?= $viewCount ?></td>
                                <td><?= htmlspecialchars($ad['ad_type']) ?></td> <!-- Hirdetés típusa -->
                                <td><?= $ad['status'] ?></td>
                                <td>
                                    <form method="post" action="" class="d-inline">
                                        <input type="hidden" name="action" value="add_views">
                                        <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                        <input type="number" name="views" min="10" placeholder="Add Views" class="form-control mb-2" required>
                                        <button type="submit" class="btn btn-primary btn-sm">Add Views</button>
                                    </form>
                                    <form method="post" action="" class="d-inline">
                                        <input type="hidden" name="action" value="delete_ad">
                                        <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table> <!-- Zárjuk le a táblázatot -->
            <?php else: ?>
                <p>No ads found.</p>
            <?php endif; ?>
        </div> <!-- Zárjuk le a card-body divet -->
    </div> <!-- Zárjuk le a card divet -->
</div>

<!-- Statisztikák megjelenítése grafikonként -->
<div class="card mt-4">
    <div class="card-header bg-secondary text-white">
        <h3>Ad Statistics (Last 7 Days)</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($ads)): ?>
            <div class="row">
                <?php foreach ($ads as $index => $ad): 
                    $viewStats = $advertise->getAdViewStats($ad['id']); // Fetch 7-day stats
                    $labels = json_encode(array_column($viewStats, 'view_date') ?: []);
                    $data = json_encode(array_column($viewStats, 'view_count') ?: []);
                ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title text-center"><?= htmlspecialchars($ad['title']) ?></h5>
                            </div>
                            <div class="card-body">
                                <div style="width: 100%; height: 250px; margin: 0 auto;">
                                    <canvas id="chart-<?= $ad['id'] ?>"></canvas>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const ctx = document.getElementById('chart-<?= $ad['id'] ?>').getContext('2d');
                                        new Chart(ctx, {
                                            type: 'bar',
                                            data: {
                                                labels: <?= $labels ?>,
                                                datasets: [{
                                                    label: 'Views',
                                                    data: <?= $data ?>,
                                                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                                    borderColor: 'rgba(54, 162, 235, 1)',
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                scales: {
                                                    y: {
                                                        beginAtZero: true
                                                    }
                                                }
                                            }
                                        });
                                    });
                                </script>
                            </div>
                        </div>
                    </div>
                    <?php if (($index + 1) % 3 === 0): ?>
                        </div><div class="row">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No ads found to display statistics.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include("footer.php"); ?>
