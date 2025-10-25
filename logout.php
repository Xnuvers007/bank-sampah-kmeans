<?php
// Start the session
session_start();

// Regenerate session ID to prevent session fixation attacks
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to the login page with a success message
header("Location: index.php?success=Anda telah berhasil logout.");
exit;
?>