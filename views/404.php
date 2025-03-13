<?php
include("header.php");
?>
    <style>
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #dc3545;
        }
        .error-message {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .btn-home {
            font-size: 18px;
            padding: 10px 20px;
        }
    </style>

    <div class="container">
        <div class="error-code">404</div>
        <div class="error-message">Oops! The page you're looking for doesn't exist.</div>
        <a href="/" class="btn btn-primary btn-home">Go Back to Homepage</a>
    </div>

<?php include("footer.php"); ?>