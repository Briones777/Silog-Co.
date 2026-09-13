<?php require_once __DIR__.'/../includes/auth.php'; verify_csrf(); $_SESSION=[];session_destroy();header('Location: ../vendor_login.php');exit;
