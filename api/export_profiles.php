<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET mag iedereen (TV/viewer mogen wel exports gebruiken om ze te genereren).
// Wijzigen van profielen = admin + editor.
if ($method !== 'GET' && !in_array($me['role'], ['admin', 'editor'], true)) {
    json_error('Geen rechten om export-profielen te beheren.', 403);
}

const ALLOWED_FORMATS = ['pdf', 'png', 'svg'];
// Whitelisted layer keys — same set as ALLOWED_LAYER_KEYS in share.php, plus the
// inflateLabels and legend toggles that are export-specific.
const ALLOWED_PROFILE_LAYER_KEYS = [
    'background', 'parcours', 'zones', 'ratings', 'logos',
    'camera', 'led', 'heras',
    'inflate', 'inflateLabels', 'legend',
    'start', 'finish',
];

function sanitize_profile_layers($value): array {
    $out = [];
    foreach (ALLOWED_PROFILE_LAYER_KEYS as $k) {
        $out[$k] = is_array($value) && isset($value[$k]) ? (bool)$value[$k] : true;
    }
    return $out;
}

function profile_row_to_api($row) {
    return [
        'id'               => (int)$row['id'],
        'name'             => $row['name'],
        'description'      => $row['description'],
        'format'           => $row['format'],
        'layers'           => $row['layers'] ? json_decode($row['layers'], true) : null,
        'include_led_page' => (bool)$row['include_led_page'],
        'created_by_name'  => $row['created_by_name'] ?? null,
        'created_at'       => $row['created_at'],
        'updated_at'       => $row['updated_at'],
    ];
}

if ($method === 'GET') {
    $stmt = db()->query('
        SELECT p.id, p.name, p.description, p.format, p.layers, p.include_led_page,
               p.created_at, p.updated_at, u.display_name AS created_by_name
        FROM pe_export_profiles p
        LEFT JOIN pe_users u ON u.id = p.created_by
        ORDER BY p.name ASC
    ');
    $rows = $stmt->fetchAll();
    $profiles = array_map('profile_row_to_api', $rows);
    json_response(['profiles' => $profiles]);
}

if ($method === 'POST') {
    $in = json_input();
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') json_error('Naam is verplicht.');
    if (mb_strlen($name) > 120) $name = mb_substr($name, 0, 120);
    
    $description = isset($in['description']) ? trim((string)$in['description']) : '';
    if ($description === '') $description = null;
    if ($description !== null && mb_strlen($description) > 300) $description = mb_substr($description, 0, 300);
    
    $format = (string)($in['format'] ?? 'pdf');
    if (!in_array($format, ALLOWED_FORMATS, true)) $format = 'pdf';
    
    $layers = sanitize_profile_layers($in['layers'] ?? []);
    $include_led_page = !empty($in['include_led_page']) ? 1 : 0;
    
    db()->prepare('
        INSERT INTO pe_export_profiles (name, description, format, layers, include_led_page, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([$name, $description, $format, json_encode($layers), $include_led_page, (int)$me['id']]);
    
    $id = (int)db()->lastInsertId();
    json_response(['id' => $id, 'ok' => true]);
}

if ($method === 'PUT') {
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    
    $fields = [];
    $values = [];
    if (array_key_exists('name', $in)) {
        $name = trim((string)$in['name']);
        if ($name === '') json_error('Naam is verplicht.');
        if (mb_strlen($name) > 120) $name = mb_substr($name, 0, 120);
        $fields[] = 'name = ?';
        $values[] = $name;
    }
    if (array_key_exists('description', $in)) {
        $description = trim((string)$in['description']);
        if ($description === '') $description = null;
        if ($description !== null && mb_strlen($description) > 300) $description = mb_substr($description, 0, 300);
        $fields[] = 'description = ?';
        $values[] = $description;
    }
    if (array_key_exists('format', $in)) {
        $format = (string)$in['format'];
        if (!in_array($format, ALLOWED_FORMATS, true)) $format = 'pdf';
        $fields[] = 'format = ?';
        $values[] = $format;
    }
    if (array_key_exists('layers', $in)) {
        $fields[] = 'layers = ?';
        $values[] = json_encode(sanitize_profile_layers($in['layers']));
    }
    if (array_key_exists('include_led_page', $in)) {
        $fields[] = 'include_led_page = ?';
        $values[] = !empty($in['include_led_page']) ? 1 : 0;
    }
    if (empty($fields)) json_error('Geen wijzigingen.', 400);
    
    $values[] = $id;
    db()->prepare('UPDATE pe_export_profiles SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    db()->prepare('DELETE FROM pe_export_profiles WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_error('Methode niet ondersteund.', 405);
