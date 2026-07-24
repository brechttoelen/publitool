<?php
// API voor parcours-versies (snapshots).
//
// GET    ?parcours_id=X                      → lijst van versies (zonder snapshot_json)
// GET    ?id=Y                               → één versie inclusief snapshot_json
// POST   { parcours_id, label?, kind? }      → maak snapshot van huidige parcours-data
// DELETE ?id=Y                               → verwijder één versie
//
// Snapshots zijn deep-copies van data_json. Restoren = de huidige
// data_json overschrijven met de snapshot. Dat wordt gewoon door de
// frontend gedaan via parcoursUpdate; geen aparte restore-endpoint nodig.

require __DIR__ . '/_bootstrap.php';
$user = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET = lijst snapshots ophalen, mag iedereen zien.
// POST/DELETE = snapshot maken/verwijderen — niet voor TV en viewer.
// (Een TV-crew die per ongeluk een oude versie herstelt zou alle
// camera-werk wegblazen; en restoren betekent een PUT op parcours.php
// die toch al door tv_filter_data wordt afgewezen.)
if ($method !== 'GET' && !in_array($user['role'], ['admin', 'editor'], true)) {
    json_error('Geen rechten om versies te wijzigen.', 403);
}

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = db()->prepare(
            'SELECT id, parcours_id, snapshot_json, label, kind, created_at, created_by_user_id, created_by_name ' .
            'FROM pe_parcours_versions WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Versie niet gevonden.', 404);
        // Toegangscheck via het bovenliggende parcours.
        require_parcours_access($user, (int)$row['parcours_id']);
        $row['id'] = (int)$row['id'];
        $row['parcours_id'] = (int)$row['parcours_id'];
        $row['snapshot'] = $row['snapshot_json'] ? json_decode($row['snapshot_json'], true) : null;
        unset($row['snapshot_json']);
        json_response(['version' => $row]);
    }
    
    $pid = (int)($_GET['parcours_id'] ?? 0);
    if (!$pid) json_error('parcours_id verplicht');
    require_parcours_access($user, $pid);
    $stmt = db()->prepare(
        'SELECT id, parcours_id, label, kind, created_at, created_by_user_id, created_by_name ' .
        'FROM pe_parcours_versions WHERE parcours_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$pid]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['parcours_id'] = (int)$r['parcours_id'];
        if ($r['created_by_user_id'] !== null) $r['created_by_user_id'] = (int)$r['created_by_user_id'];
    }
    json_response(['versions' => $rows]);
}

if ($method === 'POST') {
    $in = json_input();
    $pid = (int)($in['parcours_id'] ?? 0);
    if (!$pid) json_error('parcours_id verplicht');
    require_parcours_access($user, $pid);
    
    // Haal huidige data_json + bg-info op (we slaan beide op zodat een restore
    // de volledige visuele staat herstelt — niet alleen markers/zones).
    $stmt = db()->prepare('SELECT data_json, bg_image_path, bg_width, bg_height, bg_min_lat, bg_max_lat, bg_min_lng, bg_max_lng FROM pe_parcours WHERE id = ?');
    $stmt->execute([$pid]);
    $par = $stmt->fetch();
    if (!$par) json_error('Parcours niet gevonden.', 404);
    
    $snapshot = [
        'data' => $par['data_json'] ? json_decode($par['data_json'], true) : null,
        'bg_image_path' => $par['bg_image_path'],
        'bg_width' => $par['bg_width'] !== null ? (int)$par['bg_width'] : null,
        'bg_height' => $par['bg_height'] !== null ? (int)$par['bg_height'] : null,
        'bg_min_lat' => $par['bg_min_lat'] !== null ? (float)$par['bg_min_lat'] : null,
        'bg_max_lat' => $par['bg_max_lat'] !== null ? (float)$par['bg_max_lat'] : null,
        'bg_min_lng' => $par['bg_min_lng'] !== null ? (float)$par['bg_min_lng'] : null,
        'bg_max_lng' => $par['bg_max_lng'] !== null ? (float)$par['bg_max_lng'] : null,
    ];
    
    $label = isset($in['label']) ? trim((string)$in['label']) : null;
    if ($label === '') $label = null;
    $kind = isset($in['kind']) && in_array($in['kind'], ['manual', 'auto']) ? $in['kind'] : 'manual';
    
    // Auto-snapshots ontdubbelen: als er vandaag al een auto-snapshot is voor
    // dit parcours, niet nog eens een toevoegen.
    if ($kind === 'auto') {
        $check = db()->prepare(
            'SELECT id FROM pe_parcours_versions ' .
            'WHERE parcours_id = ? AND kind = ? AND DATE(created_at) = CURDATE() ' .
            'LIMIT 1'
        );
        $check->execute([$pid, 'auto']);
        if ($check->fetchColumn()) {
            json_response(['skipped' => true, 'reason' => 'auto-snapshot voor vandaag bestaat al']);
        }
    }
    
    $ins = db()->prepare(
        'INSERT INTO pe_parcours_versions (parcours_id, snapshot_json, label, kind, created_by_user_id, created_by_name) ' .
        'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $pid,
        json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $label,
        $kind,
        $user['id'] ?? null,
        $user['display_name'] ?? null,
    ]);
    json_response(['id' => (int)db()->lastInsertId()]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id verplicht');
    
    // Voor we de versie-rij verwijderen: haal het snapshot-bg-pad op zodat we
    // het achteraf kunnen unlinken indien geen ander parcours/snapshot er nog
    // naar verwijst.
    $stmt = db()->prepare('SELECT parcours_id, snapshot_json FROM pe_parcours_versions WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $bgPath = null;
    $parcoursId = 0;
    if ($row) {
        $parcoursId = (int)$row['parcours_id'];
        // Toegangscheck via parcours — pas zinnig nadat we het ID kennen.
        require_parcours_access($user, $parcoursId);
        $snap = json_decode($row['snapshot_json'] ?? '', true);
        if (is_array($snap) && !empty($snap['bg_image_path'])) {
            $bgPath = $snap['bg_image_path'];
        }
    }
    
    db()->prepare('DELETE FROM pe_parcours_versions WHERE id = ?')->execute([$id]);
    
    // Cleanup: alleen als geen ander parcours of snapshot dit pad nog gebruikt.
    if ($bgPath) {
        $check = db()->prepare('SELECT 1 FROM pe_parcours WHERE bg_image_path = ? LIMIT 1');
        $check->execute([$bgPath]);
        $stillReferencedByParcours = (bool)$check->fetchColumn();
        $stillReferencedBySnapshot = false;
        if (!$stillReferencedByParcours) {
            $check2 = db()->prepare('SELECT 1 FROM pe_parcours_versions WHERE snapshot_json LIKE ? LIMIT 1');
            $check2->execute(['%"bg_image_path":' . json_encode($bgPath) . '%']);
            $stillReferencedBySnapshot = (bool)$check2->fetchColumn();
        }
        if (!$stillReferencedByParcours && !$stillReferencedBySnapshot) {
            safe_unlink_upload($bgPath);
        }
    }
    
    json_response(['ok' => true]);
}

json_error('Methode niet ondersteund.', 405);
