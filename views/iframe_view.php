<?php

$userId = $_SESSION['user_id'];
$adId = (int)$_GET['ad_id'];

// Ellenőrizzük, hogy a hirdetés létezik-e
$ptc = new PTC($mysqli, new User($mysqli, $userId), $config);
$ad = $ptc->getAdById($adId);

if (!$ad || $ad['ad_type'] !== 'iframe') {
    die("Invalid ad.");
}

$durationSeconds = (int)$ad['duration_seconds']; // Hirdetés megtekintési ideje
if ($durationSeconds <= 0) {
    die("Invalid ad duration.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ad</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background-color: #000; /* Fekete háttér */
        }
        .countdown-container {
            height: 60px; /* Magasság csökkentése 60px-re */
            line-height: 60px;
            text-align: center;
            background-color: #121212;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            position: relative;
        }
        .back-button {
            position: absolute;
            left: 20px;
            top: 10px;
            background-color: #ffc107;
            color: black;
            border: none;
            padding: 5 5px; /* Csökkentett belső margó */
            font-size: 16px; /* Csökkentett betűméret */
            font-weight: bold;
            border-radius: 5px;
            line-height: 1; /* Csökkentett sor magasság */
            height: auto; /* Automatikus magasság */
            cursor: pointer;
        }
        iframe {
            width: 100%;
            height: calc(100vh - 60px); /* Az iframe magassága a visszaszámlálás sáv magasságától függ */
            border: none;
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-header {
            background-color: #121212;
            color: white;
        }
        .modal-footer {
            justify-content: center;
        }
        .focus-warning {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #ffc107;
            color: black;
            text-align: center;
            padding: 10px;
            font-weight: bold;
            z-index: 1000;
        }
        .banner-container {
            position: absolute;
            top: 0;
            right: 10px; /* Csökkentett érték a teljes megjelenéshez */
            width: 468px; /* Banner szélessége */
            height: 60px; /* Banner magassága */
            overflow: hidden; /* Biztosítja, hogy semmi ne lógjon ki */
        }
    </style>
    <script>
        let timeLeft = <?= $durationSeconds ?>;
        let interval;

        function startCountdown() {
            const countdownElement = document.getElementById('countdown');
            const focusWarningElement = document.getElementById('focus-warning');
            const modalElement = document.getElementById('complete-modal');

            interval = setInterval(() => {
                if (document.hasFocus()) {
                    focusWarningElement.style.display = 'none';
                    if (timeLeft > 0) {
                        countdownElement.innerText = `${timeLeft} seconds remaining`;
                        timeLeft--;
                    } else {
                        clearInterval(interval);
                        modalElement.classList.add('show');
                        modalElement.style.display = 'block';
                    }
                } else {
                    focusWarningElement.style.display = 'block';
                }
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            startCountdown();
        });
    </script>
</head>
<body>

<div class="countdown-container">
    <button class="back-button" onclick="window.location.href='ptc';">Back to PTC</button>
    <p id="countdown"><?= $durationSeconds ?> seconds remaining</p>
    <div class="banner-container">
        <iframe src="https://zerads.com/ad/ad.php?width=468&ref=4652" marginwidth="0" marginheight="0" width="468" height="60" scrolling="no" border="0" frameborder="0"></iframe>
    </div>
</div>

<div id="focus-warning" class="focus-warning">
    Please keep the page in focus to complete the ad view.
</div>

<iframe src="<?= htmlspecialchars($ad['url']) ?>" sandbox="allow-scripts allow-same-origin"></iframe>

<div class="modal fade" id="complete-modal" tabindex="-1" aria-labelledby="completeModalLabel" aria-hidden="true" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completeModalLabel">Ad View Complete</h5>
            </div>
            <div class="modal-body text-center">
                <p>You have completed viewing the ad. Click the button below to claim your reward.</p>
                <form method="POST" action="ptc">
                    <input type="hidden" name="ad_id" value="<?= $adId ?>">
                    <input type="hidden" name="claim_reward" value="1">
                    <button type="submit" class="btn btn-success mt-3">Claim Reward</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
