<?php
// ===================================================
// Shared bootstrap. Elke endpoint includes dit eerst.
// ===================================================

declare(strict_types=1);

// CORS: same-origin verwacht, dus geen CORS-headers nodig.
// Wel: alleen JSON output.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Errors niet als HTML naar de browser sturen — wel loggen.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Config laden
$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server niet geconfigureerd: config.php ontbreekt.']);
    exit;
}
$CONFIG = require $config_path;

// ---------- Database ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $CONFIG;
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            $CONFIG['db_host'], $CONFIG['db_name'], $CONFIG['db_charset']);
        try {
            $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connect failed: ' . $e->getMessage());
            json_error('Kan niet met database verbinden.', 500);
        }
    }
    return $pdo;
}

// ---------- JSON helpers ----------
function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $msg, int $status = 400, array $extra = []): void {
    json_response(array_merge(['error' => $msg], $extra), $status);
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_error('Methode niet toegestaan.', 405);
    }
}

// ---------- Authenticatie ----------
function start_session(): void {
    global $CONFIG;
    if (session_status() === PHP_SESSION_NONE) {
        session_name($CONFIG['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function current_user(): ?array {
    global $CONFIG;
    start_session();
    if (!empty($_SESSION['user_id'])) {
        // Rol + has_full_access zitten niet altijd in sessie (oudere sessies van
        // vóór een migratie): dan hier opnieuw uit DB halen en cachen.
        if (empty($_SESSION['role']) || !array_key_exists('has_full_access', $_SESSION)) {
            $stmt = db()->prepare('SELECT role, has_full_access FROM pe_users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$_SESSION['user_id']]);
            $row = $stmt->fetch();
            $_SESSION['role']            = (string)($row['role'] ?? 'editor');
            $_SESSION['has_full_access'] = (int)($row['has_full_access'] ?? 1);
        }
        return [
            'id'              => (int)$_SESSION['user_id'],
            'username'        => $_SESSION['username'] ?? '',
            'display_name'    => $_SESSION['display_name'] ?? '',
            'role'            => (string)($_SESSION['role'] ?? 'editor'),
            'has_full_access' => (int)($_SESSION['has_full_access'] ?? 1),
        ];
    }
    // Probeer token-cookie
    if (!empty($_COOKIE['PE_REMEMBER'])) {
        $parts = explode(':', $_COOKIE['PE_REMEMBER'], 2);
        if (count($parts) === 2) {
            [$tokenId, $tokenSecret] = $parts;
            $stmt = db()->prepare('SELECT t.id, t.user_id, t.token_hash, t.expires_at,
                                          u.username, u.display_name, u.role, u.has_full_access
                                   FROM pe_login_tokens t JOIN pe_users u ON u.id = t.user_id
                                   WHERE t.id = ? AND t.expires_at > NOW() LIMIT 1');
            $stmt->execute([$tokenId]);
            $row = $stmt->fetch();
            if ($row && password_verify($tokenSecret, $row['token_hash'])) {
                $_SESSION['user_id']         = (int)$row['user_id'];
                $_SESSION['username']        = $row['username'];
                $_SESSION['display_name']    = $row['display_name'];
                $_SESSION['role']            = (string)($row['role'] ?? 'editor');
                $_SESSION['has_full_access'] = (int)($row['has_full_access'] ?? 1);
                return [
                    'id'              => (int)$row['user_id'],
                    'username'        => $row['username'],
                    'display_name'    => $row['display_name'],
                    'role'            => (string)($row['role'] ?? 'editor'),
                    'has_full_access' => (int)($row['has_full_access'] ?? 1),
                ];
            }
        }
    }
    return null;
}

function require_auth(): array {
    $u = current_user();
    if (!$u) {
        json_error('Niet ingelogd.', 401);
    }
    return $u;
}

/**
 * Vereis een specifieke rol of een rol-set.
 *
 * Rollen (van veel naar weinig rechten):
 *   admin > editor > tv > viewer
 *
 * Gebruik:
 *   require_role('admin')
 *   require_role(['admin','editor'])
 */
function require_role($roles): array {
    $u = require_auth();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($u['role'], $allowed, true)) {
        json_error('Geen rechten voor deze actie.', 403);
    }
    return $u;
}

/** Mag deze rol parcours-data wijzigen? Viewer = nee. */
function role_can_write(string $role): bool {
    return in_array($role, ['admin', 'editor', 'tv'], true);
}

/** Mag deze rol nieuwe parcoursen aanmaken / dupliceren / verwijderen? */
function role_can_manage_parcours(string $role): bool {
    return in_array($role, ['admin', 'editor'], true);
}

/** Mag deze rol gebruikers/koepels/seizoenen beheren? */
function role_is_admin(string $role): bool {
    return $role === 'admin';
}

/**
 * TV-rol filter: bij het opslaan van parcours-data mag een TV-user
 * uitsluitend camera-markers wijzigen. Deze functie merget de
 * inkomende data bovenop de bestaande DB-versie en accepteert alleen
 * camera-markers (type === 'camera') + cameraCounter.
 *
 * Zo dwingen we het ook server-side af — niet alleen via UI.
 *
 * @param array $incoming   Door TV-user gestuurde data (al gedecodeerd).
 * @param array|null $existing Bestaande data uit DB (al gedecodeerd, of null).
 * @return array De gemergede data die mag opgeslagen worden.
 */
function tv_filter_data(array $incoming, ?array $existing): array {
    // Een TV-user mag geen leeg parcours initialiseren.
    if ($existing === null) {
        json_error('TV-rol kan geen leeg parcours initialiseren.', 403);
    }

    // Start van de bestaande data — alles blijft zoals het was.
    $merged = $existing;

    $incomingMarkers = isset($incoming['markers']) && is_array($incoming['markers'])
        ? $incoming['markers'] : [];
    $existingMarkers = isset($existing['markers']) && is_array($existing['markers'])
        ? $existing['markers'] : [];

    // Niet-camera markers blijven 1-op-1 zoals in DB. Camera-markers worden
    // overschreven met de versie uit incoming.
    $nonCamera = [];
    foreach ($existingMarkers as $m) {
        if (!is_array($m)) continue;
        if (($m['type'] ?? null) !== 'camera') {
            $nonCamera[] = $m;
        }
    }
    $newCameras = [];
    foreach ($incomingMarkers as $m) {
        if (!is_array($m)) continue;
        if (($m['type'] ?? null) === 'camera') {
            $newCameras[] = $m;
        }
    }
    $merged['markers'] = array_merge($nonCamera, $newCameras);

    // cameraCounter mag mee — die telt alleen camera's.
    if (array_key_exists('cameraCounter', $incoming)) {
        $merged['cameraCounter'] = $incoming['cameraCounter'];
    }

    return $merged;
}

// ---------- Toegangscontrole per parcours ----------
//
// Sinds we per-parcours gebruikertoegang invoerden geldt:
//   - Admin                   → ziet en mag alles
//   - has_full_access = 1     → ziet alle parcours (rol bepaalt wat hij/zij
//                               daarbinnen mag wijzigen)
//   - has_full_access = 0     → ziet alleen de parcours waarvoor hij/zij
//                               in pe_parcours_users staat
//
// Beide helpers verwachten een user-array zoals require_auth() dat teruggeeft.

/**
 * Mag deze gebruiker dit specifieke parcours zien?
 * Gebruikt voor detail-endpoints (parcours.php?id=X, lock, versions, share).
 */
function user_can_access_parcours(array $user, int $parcoursId): bool {
    if ($parcoursId <= 0) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    if (!empty($user['has_full_access'])) return true;
    $stmt = db()->prepare('SELECT 1 FROM pe_parcours_users
                           WHERE parcours_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$parcoursId, (int)$user['id']]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Voeg een WHERE-clause toe aan een lijstquery zodat deze gebruiker alleen
 * parcoursen ziet waar hij toegang toe heeft. Returnt een array met:
 *   - 'sql':    een fragment dat met AND aan een bestaande WHERE gehangen
 *               kan worden, of een lege string als geen filter nodig is
 *   - 'params': de bijbehorende positional params
 *
 * Verwacht dat de hoofdtabel als alias 'p' wordt gebruikt (zoals
 * parcours.php doet). Pas zo nodig de alias aan.
 */
function parcours_visibility_clause(array $user, string $alias = 'p'): array {
    if (($user['role'] ?? '') === 'admin') return ['sql' => '', 'params' => []];
    if (!empty($user['has_full_access'])) return ['sql' => '', 'params' => []];
    // Beperk tot parcoursen waar deze user expliciet toegang heeft.
    $sql = "EXISTS (SELECT 1 FROM pe_parcours_users pu
                    WHERE pu.parcours_id = {$alias}.id AND pu.user_id = ?)";
    return ['sql' => $sql, 'params' => [(int)$user['id']]];
}

/**
 * Vereis dat de huidige gebruiker dit parcours mag zien — anders 403/404.
 * We geven 404 zodat een gebruiker zonder toegang niet kan deduceren of een
 * parcours met dat ID überhaupt bestaat.
 */
function require_parcours_access(array $user, int $parcoursId): void {
    if (!user_can_access_parcours($user, $parcoursId)) {
        json_error('Parcours niet gevonden.', 404);
    }
}

function clear_remember_cookie(): void {
    if (isset($_COOKIE['PE_REMEMBER'])) {
        setcookie('PE_REMEMBER', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

// ---------- File upload helper ----------
function ensure_uploads_dir(string $sub): string {
    global $CONFIG;
    $path = rtrim($CONFIG['uploads_dir'], '/') . '/' . trim($sub, '/');
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    if (!is_writable($path)) {
        json_error("Upload-map niet schrijfbaar: $path", 500);
    }
    return $path;
}

function safe_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return substr($name, 0, 80);
}
