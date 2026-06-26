<?php
/**
 * Session Configuration for Member Registration
 * Ensures sessions persist properly through payment redirects
 */

// Set session cookie parameters BEFORE session_start()
if (!headers_sent()) {
    // Session timeout: 2 hours
    ini_set('session.gc_maxlifetime', 7200);
    
    // Set secure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Regenerate session ID for security on sensitive operations
if (!function_exists('regenerate_session_secure')) {
    function regenerate_session_secure() {
        if (!isset($_SESSION['session_init'])) {
            session_regenerate_id(true);
            $_SESSION['session_init'] = true;
        }
    }
}
?>
