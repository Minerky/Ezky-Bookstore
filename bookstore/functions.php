<?php
// functions.php - Common functions included with include_once

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>
