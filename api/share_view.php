<?php
// =================================================================
// Public read-only share view. No login required.
// Looks up a parcours by share-token and returns its public data.
// =================================================================

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Methode niet ondersteund.', 405);
}

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') json_error('token is verplicht.', 400);

// Look up token
$stmt = db()->prepare('SELECT parcours_id, allowed_layers FROM pe_share_tokens WHERE token = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row) json_error('Deze deel-link is ongeldig of ingetrokken.', 404);

$parcours_id = (int)$row['parcours_id'];
$allowed_layers = $row['allowed_layers'] ? json_decode($row['allowed_layers'], true) : null;

// Fetch the parcours (mirrors the auth'd parcours.php fetch but only what's safe to share)
$stmt = db()->prepare('
    SELECT p.id, p.location_name, p.race_date, p.data_json,
           p.bg_image_path, p.bg_width, p.bg_height,
           s.label AS season_label,
           k.name  AS koepel_name
    FROM pe_parcours p
    LEFT JOIN pe_seasons s ON s.id = p.season_id
    LEFT JOIN pe_koepels k ON k.id = p.koepel_id
    WHERE p.id = ?
    LIMIT 1
');
$stmt->execute([$parcours_id]);
$par = $stmt->fetch();
if (!$par) json_error('Parcours niet gevonden.', 404);

// Fetch only the partners that are linked to THIS parcours (no global partner DB exposed)
$pstmt = db()->prepare('
    SELECT pa.id, pa.name, pa.color, pa.logo_path
    FROM pe_partners pa
    INNER JOIN pe_parcours_partners pp ON pp.partner_id = pa.id
    WHERE pp.parcours_id = ?
    ORDER BY pa.name
');
$pstmt->execute([$parcours_id]);
$partners = $pstmt->fetchAll();

// Build absolute URL prefix for logos
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uploads_url = $CONFIG['uploads_url'] ?? '';

foreach ($partners as &$p) {
    $p['id'] = (int)$p['id'];
    if (!empty($p['logo_path'])) {
        $p['logoUrl'] = $uploads_url . '/' . ltrim($p['logo_path'], '/');
    } else {
        $p['logoUrl'] = null;
    }
    unset($p['logo_path']);
}

// Decode parcours data
$data = null;
if (!empty($par['data_json'])) {
    $data = json_decode($par['data_json'], true);
}

json_response([
    'parcours' => [
        'id'             => (int)$par['id'],
        'location_name'  => $par['location_name'],
        'race_date'      => $par['race_date'],
        'season_label'   => $par['season_label'],
        'koepel_name'    => $par['koepel_name'],
        'bg_image_path'  => $par['bg_image_path'],
        'bg_width'       => $par['bg_width'] ? (int)$par['bg_width'] : null,
        'bg_height'      => $par['bg_height'] ? (int)$par['bg_height'] : null,
        'data'           => $data,
    ],
    'partners'       => $partners,
    'allowed_layers' => $allowed_layers,
]);
