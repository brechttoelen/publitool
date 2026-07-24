<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();

// Share-links aanmaken/intrekken hoort bij parcours-werk = admin + editor.
// (share_view.php werkt op publieke tokens zonder login en wordt niet beperkt.)
if (!in_array($me['role'], ['admin', 'editor'], true)) {
    json_error('Geen rechten om share-links te beheren.', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

// Whitelist of layer keys we accept. Anything not in here is silently dropped.
const ALLOWED_LAYER_KEYS = ['background', 'parcours', 'zones', 'ratings', 'logos', 'camera', 'led', 'heras', 'inflate', 'start', 'finish'];

function sanitize_allowed_layers($value): ?array {
    if (!is_array($value)) return null;
    $out = [];
    foreach (ALLOWED_LAYER_KEYS as $k) {
        // Default to true (visible) if key is missing, so partial input is treated as "all on".
        $out[$k] = isset($value[$k]) ? (bool)$value[$k] : true;
    }
    return $out;
}

if ($method === 'GET') {
    $parcours_id = (int)($_GET['parcours_id'] ?? 0);
    if (!$parcours_id) json_error('parcours_id is verplicht.');
    require_parcours_access($me, $parcours_id);
    
    $exists = (int)db()->query('SELECT COUNT(*) FROM pe_parcours WHERE id = ' . $parcours_id)->fetchColumn();
    if (!$exists) json_error('Parcours niet gevonden.', 404);
    
    $stmt = db()->prepare('
        SELECT t.id, t.token, t.created_at, t.is_active, t.allowed_layers, t.label, u.display_name AS created_by_name
        FROM pe_share_tokens t
        LEFT JOIN pe_users u ON u.id = t.created_by
        WHERE t.parcours_id = ? AND t.is_active = 1
        ORDER BY t.created_at DESC
    ');
    $stmt->execute([$parcours_id]);
    $tokens = $stmt->fetchAll();
    foreach ($tokens as &$t) {
        $t['id'] = (int)$t['id'];
        $t['allowed_layers'] = $t['allowed_layers'] ? json_decode($t['allowed_layers'], true) : null;
    }
    json_response(['tokens' => $tokens]);
}

if ($method === 'POST') {
    $in = json_input();
    $parcours_id = (int)($in['parcours_id'] ?? 0);
    if (!$parcours_id) json_error('parcours_id is verplicht.');
    require_parcours_access($me, $parcours_id);
    
    $exists = (int)db()->query('SELECT COUNT(*) FROM pe_parcours WHERE id = ' . $parcours_id)->fetchColumn();
    if (!$exists) json_error('Parcours niet gevonden.', 404);
    
    $allowed = sanitize_allowed_layers($in['allowed_layers'] ?? null);
    $allowed_json = $allowed !== null ? json_encode($allowed) : null;
    $label = isset($in['label']) ? trim((string)$in['label']) : '';
    if ($label === '') $label = null;
    if ($label !== null && mb_strlen($label) > 120) $label = mb_substr($label, 0, 120);
    
    $raw = random_bytes(32);
    $token = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    
    db()->prepare('INSERT INTO pe_share_tokens (parcours_id, token, created_by, allowed_layers, label) VALUES (?, ?, ?, ?, ?)')
        ->execute([$parcours_id, $token, (int)$me['id'], $allowed_json, $label]);
    
    $id = (int)db()->lastInsertId();
    json_response(['id' => $id, 'token' => $token, 'allowed_layers' => $allowed, 'label' => $label]);
}

if ($method === 'PUT') {
    // Update the allowed_layers and/or label of an existing token without breaking the URL.
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    
    // Resolve het bovenliggende parcours en check toegang.
    $stmt = db()->prepare('SELECT parcours_id FROM pe_share_tokens WHERE id = ?');
    $stmt->execute([$id]);
    $tokenRow = $stmt->fetch();
    if (!$tokenRow) json_error('Token niet gevonden.', 404);
    require_parcours_access($me, (int)$tokenRow['parcours_id']);
    
    $fields = [];
    $values = [];
    if (array_key_exists('allowed_layers', $in)) {
        $allowed = sanitize_allowed_layers($in['allowed_layers']);
        $fields[] = 'allowed_layers = ?';
        $values[] = $allowed !== null ? json_encode($allowed) : null;
    }
    if (array_key_exists('label', $in)) {
        $label = trim((string)$in['label']);
        if ($label === '') $label = null;
        if ($label !== null && mb_strlen($label) > 120) $label = mb_substr($label, 0, 120);
        $fields[] = 'label = ?';
        $values[] = $label;
    }
    if (empty($fields)) json_error('Geen wijzigingen.', 400);
    
    $values[] = $id;
    db()->prepare('UPDATE pe_share_tokens SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    
    // Resolve het bovenliggende parcours en check toegang.
    $stmt = db()->prepare('SELECT parcours_id FROM pe_share_tokens WHERE id = ?');
    $stmt->execute([$id]);
    $tokenRow = $stmt->fetch();
    if (!$tokenRow) json_error('Token niet gevonden.', 404);
    require_parcours_access($me, (int)$tokenRow['parcours_id']);
    
    db()->prepare('DELETE FROM pe_share_tokens WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_error('Methode niet ondersteund.', 405);
