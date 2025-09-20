<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();

// Redirect to enhanced profile
header('Location: profile-enhanced.php');
exit;
?>