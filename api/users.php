<?php
require __DIR__ . '/_bootstrap.php';

// User-beheer is admin-only.
$me = require_role('admin');

$method = $_SERVER['REQUEST_METHOD'];

const VALID_ROLES = ['admin', 'editor', 'tv', 'viewer'];

/**
 * Valideer en normaliseer een e-mailadres.
 * Returnt lowercase email of null op fout (foutmelding wordt direct gestuurd).
 */
function validate_email_or_fail(string $email): string {
    $email = trim($email);
    if ($email === '') {
        json_error('E-mailadres is verplicht.');
    }
    if (mb_strlen($email) > 190) {
        json_error('E-mailadres te lang (max 190 tekens).');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Ongeldig e-mailadres.');
    }
    return mb_strtolower($email);
}

if ($method === 'GET') {
    $rows = db()->query('SELECT id, username, email, display_name, role, has_full_access, created_at FROM pe_users ORDER BY display_name ASC')->fetchAll();
    foreach ($rows as &$r) {
        $r['id']              = (int)$r['id'];
        $r['has_full_access'] = (int)$r['has_full_access'];
    }
    json_response(['users' => $rows]);
}

if ($method === 'POST') {
    $in = json_input();
    $username     = trim((string)($in['username'] ?? ''));
    $email        = (string)($in['email'] ?? '');
    $display_name = trim((string)($in['display_name'] ?? ''));
    $password     = (string)($in['password'] ?? '');
    $role         = (string)($in['role'] ?? 'editor');
    // Default: nieuwe gebruikers krijgen volledige toegang. De admin moet
    // expliciet uitvinken om iemand per-parcours te beperken. Admins zijn
    // sowieso onbeperkt — vlag wordt afgedwongen op 1.
    $hasFullAccess = array_key_exists('has_full_access', $in)
        ? (int)!!$in['has_full_access']
        : 1;
    if ($role === 'admin') $hasFullAccess = 1;

    if ($username === '' || $display_name === '' || strlen($password) < 6) {
        json_error('Gebruikersnaam, volledige naam en paswoord (min. 6 tekens) zijn verplicht.');
    }

    // Username regex: alleen letters, cijfers, punt, onderstreep, koppelteken
    if (!preg_match('/^[A-Za-z0-9._-]{2,60}$/', $username)) {
        json_error('Gebruikersnaam: 2-60 tekens, alleen letters, cijfers, punt, onderstreep, koppelteken.');
    }

    // Email is verplicht voor nieuwe accounts
    $email = validate_email_or_fail($email);

    if (!in_array($role, VALID_ROLES, true)) {
        json_error('Ongeldige rol.');
    }

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO pe_users (username, email, password_hash, display_name, role, has_full_access) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, $email, $hash, $display_name, $role, $hasFullAccess]);
        $id = (int)db()->lastInsertId();
        json_response(['id' => $id, 'username' => $username, 'email' => $email, 'display_name' => $display_name, 'role' => $role, 'has_full_access' => $hasFullAccess]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // Welke unique constraint? Username of email?
            // We doen een snelle check om een nuttige foutmelding te geven.
            $check = db()->prepare('SELECT username, email FROM pe_users WHERE username = ? OR email = ? LIMIT 1');
            $check->execute([$username, $email]);
            $existing = $check->fetch();
            if ($existing && $existing['username'] === $username) {
                json_error('Een gebruiker met die gebruikersnaam bestaat al.', 409);
            }
            if ($existing && $existing['email'] === $email) {
                json_error('Een gebruiker met dat e-mailadres bestaat al.', 409);
            }
            json_error('Conflict bij aanmaken — probeer andere waardes.', 409);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');

    // Reset password?
    if (!empty($in['password'])) {
        if (strlen($in['password']) < 6) json_error('Paswoord moet minstens 6 tekens lang zijn.');
        $hash = password_hash($in['password'], PASSWORD_DEFAULT);
        db()->prepare('UPDATE pe_users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        // Invalidate other sessions/tokens for this user (not strictly required, but safer)
        db()->prepare('DELETE FROM pe_login_tokens WHERE user_id = ?')->execute([$id]);
    }

    if (array_key_exists('display_name', $in)) {
        $name = trim((string)$in['display_name']);
        if ($name === '') json_error('Volledige naam mag niet leeg zijn.');
        db()->prepare('UPDATE pe_users SET display_name = ? WHERE id = ?')->execute([$name, $id]);
    }

    if (array_key_exists('email', $in)) {
        $email = validate_email_or_fail((string)$in['email']);
        try {
            db()->prepare('UPDATE pe_users SET email = ? WHERE id = ?')->execute([$email, $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json_error('Een andere gebruiker heeft dat e-mailadres al.', 409);
            }
            throw $e;
        }
    }

    if (array_key_exists('role', $in)) {
        $newRole = (string)$in['role'];
        if (!in_array($newRole, VALID_ROLES, true)) {
            json_error('Ongeldige rol.');
        }
        // Veiligheidscheck: de huidige admin mag zichzelf niet downgraden als
        // hij de laatste admin is — anders zit niemand er nog in om users te beheren.
        if ($id === (int)$me['id'] && $newRole !== 'admin') {
            $adminCount = (int)db()->query("SELECT COUNT(*) FROM pe_users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                json_error('Je kan jezelf niet degraderen — je bent de laatste admin.', 400);
            }
        }
        // Als iemand admin wordt: full access wordt geforceerd op 1 (admin
        // ziet sowieso alles). Andersom forceren we niets — laat de
        // has_full_access-vlag staan zoals hij was, of zoals hij in dit
        // request expliciet meegestuurd wordt.
        if ($newRole === 'admin') {
            db()->prepare('UPDATE pe_users SET role = ?, has_full_access = 1 WHERE id = ?')->execute([$newRole, $id]);
        } else {
            db()->prepare('UPDATE pe_users SET role = ? WHERE id = ?')->execute([$newRole, $id]);
        }
    }

    if (array_key_exists('has_full_access', $in)) {
        $newFlag = (int)!!$in['has_full_access'];
        // Haal de huidige rol op (kan net gewijzigd zijn hierboven).
        $stmt = db()->prepare('SELECT role FROM pe_users WHERE id = ?');
        $stmt->execute([$id]);
        $curRole = (string)$stmt->fetchColumn();
        if ($curRole === 'admin' && $newFlag === 0) {
            json_error('Een admin moet altijd volledige toegang hebben.', 400);
        }
        db()->prepare('UPDATE pe_users SET has_full_access = ? WHERE id = ?')->execute([$newFlag, $id]);
        // Cache in eigen sessie verversen zodat de wijziging direct doorwerkt
        // als de admin zichzelf bewerkt (zeldzaam, maar voorkomt verwarring).
        if ($id === (int)$me['id']) {
            $_SESSION['has_full_access'] = $newFlag;
        }
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    if ($id === (int)$me['id']) {
        json_error('Je kan jezelf niet verwijderen.', 400);
    }
    // Check this isn't the last user
    $count = (int)db()->query('SELECT COUNT(*) FROM pe_users')->fetchColumn();
    if ($count <= 1) {
        json_error('Kan de laatste gebruiker niet verwijderen.', 400);
    }
    // Check this isn't the last admin
    $stmt = db()->prepare('SELECT role FROM pe_users WHERE id = ?');
    $stmt->execute([$id]);
    $targetRole = (string)$stmt->fetchColumn();
    if ($targetRole === 'admin') {
        $adminCount = (int)db()->query("SELECT COUNT(*) FROM pe_users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            json_error('Kan de laatste admin niet verwijderen.', 400);
        }
    }
    db()->prepare('DELETE FROM pe_users WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_error('Methode niet ondersteund.', 405);
