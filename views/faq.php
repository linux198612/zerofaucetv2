<?php

// Beállítások lekérése
$faucetName = Core::sanitizeOutput($config->get('faucet_name'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $faucetName ?> - FAQ</title>
    <!-- Favicon -->
    <link rel="icon" href="<?= $websiteUrl ?>favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Courier New', Courier, monospace;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            margin-top: auto;
            margin-bottom: auto;
        }
        .section {
            padding: 2rem;
            background-color: #1e1e1e;
            border: 2px solid #4caf50;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        }
        .navbar {
            background-color: rgba(0, 0, 0, 0.3);
        }
        .navbar-brand, .nav-link {
            color: #4caf50 !important;
        }
        .navbar-brand:hover, .nav-link:hover {
            color: #76ff03 !important;
        }
        h1, h3 {
            color: #76ff03;
        }
        .btn-primary {
            background-color: #4caf50;
            border: none;
        }
        .btn-primary:hover {
            background-color: #76ff03;
        }
        .accordion-button {
            background-color: #2b2b2b;
            color: #e0e0e0;
            border: 1px solid #4caf50;
        }
        .accordion-button:not(.collapsed) {
            background-color: #4caf50;
            color: #121212;
        }
        .accordion-body {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #e0e0e0;
        }
        .footer a {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/"><?= $faucetName ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq">FAQ</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container text-center">
        <!-- Header -->
        <div class="section">
            <div class="text-center">
                <h1><?= $faucetName ?> FAQ</h1>
                <p>Frequently Asked Questions about ZeroCoin and how to use it</p>
            </div>

            <div class="accordion" id="faqAccordion">
                <!-- Question 1 -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faq1">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqContent1" aria-expanded="true" aria-controls="faqContent1">
                            What is ZeroCoin?
                        </button>
                    </h2>
                    <div id="faqContent1" class="accordion-collapse collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            ZeroCoin is a cryptocurrency designed for fast and secure transactions. It focuses on privacy and ease of use, making it ideal for microtransactions and everyday payments.
                        </div>
                    </div>
                </div>

                <!-- Question 2 -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faq2">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqContent2" aria-expanded="false" aria-controls="faqContent2">
                            How can I use this site without a ZeroCoin address?
                        </button>
                    </h2>
                    <div id="faqContent2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            To use this site, you need a ZeroCoin wallet address. If you don't have one yet, you can create it using <a href="https://zerochain.info" target="_blank">zerochain.info</a>. 
                            After registering on the site, log in to your account, and your ZeroCoin wallet address will be displayed in your profile. Note that your registered username is not your wallet address; you need to log in to view your ZeroCoin address for receiving funds.
                        </div>
                    </div>
                </div>

                <!-- Question 3 -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faq3">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqContent3" aria-expanded="false" aria-controls="faqContent3">
                            Where can I get ZeroCoin?
                        </button>
                    </h2>
                    <div id="faqContent3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You can obtain ZeroCoin by purchasing it on cryptocurrency exchanges, earning it through faucets, or trading with other users. Popular exchanges like XYZ Exchange or DEF Platform offer ZeroCoin trading pairs.
                        </div>
                    </div>
                </div>

                <!-- Question 4 -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faq4">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqContent4" aria-expanded="false" aria-controls="faqContent4">
                            Can I swap ZeroCoin to Litecoin?
                        </button>
                    </h2>
                    <div id="faqContent4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, you can swap your collected ZeroCoin to Litecoin on <a href="https://zerochain.info" target="_blank">zerochain.info</a>. This feature allows you to easily convert your earnings into a widely accepted cryptocurrency like Litecoin.
                        </div>
                    </div>
                </div>

                <!-- Question 5 -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faq5">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqContent5" aria-expanded="false" aria-controls="faqContent5">
                            Rules for using this site
                        </button>
                    </h2>
                    <div id="faqContent5" class="accordion-collapse collapse" aria-labelledby="faq5" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            - Double registration is strictly prohibited.<br>
                            - Any fraudulent activities will result in account suspension.<br>
                            - Respect the faucet's cooldown time and claim limits.<br>
                            - Always use a valid ZeroCoin wallet address for claiming rewards.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?= date('Y') ?> <a href="./"><?= $faucetName ?></a>. All Rights Reserved. Version: <?= Core::sanitizeOutput($core->getVersion()) ?><br> Powered by <a href="https://coolscript.hu">CoolScript</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
