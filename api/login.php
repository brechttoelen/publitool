<?php
require __DIR__ . '/_bootstrap.php';
require_method('POST');

$in = json_input();
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');
$remember = !empty($in['remember']);

if ($username === '' || $password === '') {
    json_error('Gebruikersnaam en paswoord verplicht.', 400);
}

// Matchen op username OF e-mail. Het loginscherm accepteert beide; laat
// MySQL kiezen welke kolom hit. Beide zijn UNIQUE, dus max 1 resultaat.
// Email matchen we case-insensitive (e-mailadressen worden lowercase
// opgeslagen door validate_email_or_fail in users.php).
$stmt = db()->prepare('SELECT id, username, password_hash, display_name FROM pe_users WHERE username = ? OR email = LOWER(?) LIMIT 1');
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    // Korte vertraging tegen brute-force
    usleep(400000);
    json_error('Ongeldige login.', 401);
}

start_session();
session_regenerate_id(true);
$_SESSION['user_id']      = (int)$user['id'];
$_SESSION['username']     = $user['username'];
$_SESSION['display_name'] = $user['display_name'];

if ($remember) {
    global $CONFIG;
    $tokenSecret = bin2hex(random_bytes(32));
    $tokenHash   = password_hash($tokenSecret, PASSWORD_DEFAULT);
    $expires     = (new DateTime('+' . $CONFIG['remember_days'] . ' days'))->format('Y-m-d H:i:s');

    $stmt = db()->prepare('INSERT INTO pe_login_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([(int)$user['id'], $tokenHash, $expires]);
    $tokenId = (int)db()->lastInsertId();

    setcookie('PE_REMEMBER', "$tokenId:$tokenSecret", [
        'expires'  => time() + $CONFIG['remember_days'] * 86400,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Opruim verlopen tokens
db()->exec('DELETE FROM pe_login_tokens WHERE expires_at < NOW()');

json_response([
    'user' => [
        'id'           => (int)$user['id'],
        'username'     => $user['username'],
        'display_name' => $user['display_name'],
    ],
]);
