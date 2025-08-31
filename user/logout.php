<?php
// // logout.php
// session_start();
// session_unset();
// session_destroy();
// header('Location: user_login.php');
// exit();
// logout.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1) Clear session variables
$_SESSION = []; // (same effect as session_unset) :contentReference[oaicite:1]{index=1}

// 2) Delete the session cookie (if cookies are used)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
    );
}

// 3) Destroy session storage and close
session_destroy();          // remove server-side data (doesn't delete cookie) :contentReference[oaicite:2]{index=2}
session_write_close();      // ensure it can't be reused in this request :contentReference[oaicite:3]{index=3}

header('Location: user_login.php');
exit;
