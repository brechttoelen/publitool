<?php
// =========================================================
// PASWOORD INSTELLEN voor de admin-gebruiker.
// 
// Gebruik deze pagina ÉÉN KEER na installatie om het admin-
// paswoord te zetten of te wijzigen. VERWIJDER dit bestand
// daarna van de server (of bescherm met .htaccess).
// =========================================================

require __DIR__ . '/_bootstrap.php';

// Geen JSON deze keer — gewoon een HTML-pagina.
header('Content-Type: text/html; charset=utf-8');

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? 'admin'));
    $password = (string)($_POST['password'] ?? '');
    
    if (strlen($password) < 6) {
        $err = 'Paswoord moet minstens 6 tekens lang zijn.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Check of user bestaat
        $stmt = db()->prepare('SELECT id FROM pe_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stmt = db()->prepare('UPDATE pe_users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            $msg = "Paswoord van '$username' is gewijzigd. Je kan nu inloggen op de hoofdpagina. Verwijder dit bestand (install_password.php) van de server!";
        } else {
            // Maak nieuwe user aan
            $stmt = db()->prepare('INSERT INTO pe_users (username, password_hash, display_name) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hash, ucfirst($username)]);
            $msg = "Nieuwe gebruiker '$username' aangemaakt. Je kan nu inloggen op de hoofdpagina. Verwijder dit bestand!";
        }
    }
}
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Paswoord instellen — Parcours Editor</title>
<style>
body { font-family: -apple-system, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px; }
.box { background: #fff; border-radius: 8px; padding: 30px; max-width: 420px; width: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
h1 { font-size: 18px; margin: 0 0 10px; }
.intro { color: #666; font-size: 13px; margin-bottom: 20px; line-height: 1.55; }
label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #888; margin-bottom: 4px; letter-spacing: 0.4px; }
input { width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box; margin-bottom: 14px; }
button { width: 100%; padding: 10px; background: #000; color: #fff; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: pointer; }
.msg { background: #ecfdf5; color: #065f46; padding: 12px; border-radius: 5px; margin-bottom: 16px; font-size: 13px; border-left: 3px solid #10b981; }
.err { background: #fef2f2; color: #c0392b; padding: 12px; border-radius: 5px; margin-bottom: 16px; font-size: 13px; border-left: 3px solid #c0392b; }
.warn { background: #fef3c7; color: #92400e; padding: 12px; border-radius: 5px; margin-top: 20px; font-size: 12px; border-left: 3px solid #f59e0b; }
</style>
</head>
<body>
<div class="box">
  <h1>Paswoord instellen</h1>
  <div class="intro">Gebruik dit formulier om het paswoord van de admin-gebruiker te zetten of te wijzigen. Je kan ook een nieuwe gebruikersnaam aanmaken.</div>
  
  <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  
  <form method="POST">
    <label>Gebruikersnaam</label>
    <input type="text" name="username" value="admin" required>
    
    <label>Nieuw paswoord</label>
    <input type="password" name="password" required minlength="6">
    
    <button type="submit">Paswoord instellen</button>
  </form>
  
  <div class="warn">
    <strong>BELANGRIJK:</strong> verwijder dit bestand <code>install_password.php</code> van de server zodra je klaar bent!
  </div>
</div>
</body>
</html>
