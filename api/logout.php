<?php
require __DIR__ . '/_bootstrap.php';
require_method('POST');

start_session();

// Verwijder remember-token uit DB als die er is
if (!empty($_COOKIE['PE_REMEMBER'])) {
    $parts = explode(':', $_COOKIE['PE_REMEMBER'], 2);
    if (count($parts) === 2) {
        $stmt = db()->prepare('DELETE FROM pe_login_tokens WHERE id = ?');
        $stmt->execute([(int)$parts[0]]);
    }
    clear_remember_cookie();
}

$_SESSION = [];
session_destroy();
json_response(['ok' => true]);
