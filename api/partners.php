<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();

$method = $_SERVER['REQUEST_METHOD'];
global $CONFIG;

// GET mag iedereen (TV/viewer moeten partner-namen kunnen zien op het parcours).
// Schrijven = admin + editor. TV en viewer kunnen geen partners wijzigen.
if ($method !== 'GET' && !in_array($me['role'], ['admin', 'editor'], true)) {
    json_error('Geen rechten om partners te beheren.', 403);
}

if ($method === 'GET') {
    $stmt = db()->query('SELECT id, name, color, logo_path, is_local, notes FROM pe_partners ORDER BY is_local ASC, name ASC');
    $rows = $stmt->fetchAll();

    // Haal alle koepel-koppelingen op in één query — dat is veel efficiënter
    // dan per partner een aparte query te firen. We groeperen ze daarna per
    // partner_id en hangen ze in 'koepels' aan elke rij.
    $kStmt = db()->query(
        'SELECT kp.partner_id, kp.koepel_id, k.name AS koepel_name, k.color AS koepel_color ' .
        'FROM pe_koepel_partners kp ' .
        'INNER JOIN pe_koepels k ON k.id = kp.koepel_id ' .
        'ORDER BY k.name'
    );
    $byPartner = [];
    foreach ($kStmt->fetchAll() as $kr) {
        $pid = (int)$kr['partner_id'];
        if (!isset($byPartner[$pid])) $byPartner[$pid] = [];
        $byPartner[$pid][] = [
            'id'    => (int)$kr['koepel_id'],
            'name'  => $kr['koepel_name'],
            'color' => $kr['koepel_color'],
        ];
    }

    foreach ($rows as &$r) {
        $r['id']       = (int)$r['id'];
        $r['is_local'] = (bool)$r['is_local'];
        $r['koepels']  = $byPartner[(int)$r['id']] ?? [];
    }
    json_response(['partners' => $rows]);
}

if ($method === 'POST') {
    $in = json_input();
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') json_error('Naam is verplicht.');

    $is_local = !empty($in['is_local']) ? 1 : 0;
    $koepel_ids = [];
    if (!$is_local && isset($in['koepel_ids']) && is_array($in['koepel_ids'])) {
        foreach ($in['koepel_ids'] as $kid) {
            $kid = (int)$kid;
            if ($kid > 0) $koepel_ids[] = $kid;
        }
        $koepel_ids = array_values(array_unique($koepel_ids));
    }

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('INSERT INTO pe_partners (name, color, is_local, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $name,
            $in['color'] ?? '#888888',
            $is_local,
            $in['notes'] ?? null,
        ]);
        $id = (int)db()->lastInsertId();

        if ($koepel_ids) {
            // Koppel partner aan elke gekozen koepel.
            $insK = db()->prepare('INSERT INTO pe_koepel_partners (koepel_id, partner_id, sort_order) VALUES (?, ?, ?)');
            foreach ($koepel_ids as $kid) {
                // Bepaal sort_order = max + 1 voor nette volgorde binnen die koepel
                $maxStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next FROM pe_koepel_partners WHERE koepel_id = ?');
                $maxStmt->execute([$kid]);
                $nextSort = (int)$maxStmt->fetchColumn();
                $insK->execute([$kid, $id, $nextSort]);
            }

            // Auto-link aan alle bestaande parcoursen die tot deze koepels behoren.
            // INSERT IGNORE zou veiliger zijn, maar pe_parcours_partners heeft al een UNIQUE
            // constraint (parcours_id, partner_id), dus we doen het via NOT EXISTS-subquery.
            $placeholders = implode(',', array_fill(0, count($koepel_ids), '?'));
            $linkStmt = db()->prepare(
                'INSERT INTO pe_parcours_partners (parcours_id, partner_id) ' .
                'SELECT p.id, ? FROM pe_parcours p ' .
                'WHERE p.koepel_id IN (' . $placeholders . ') ' .
                'AND NOT EXISTS (SELECT 1 FROM pe_parcours_partners pp WHERE pp.parcours_id = p.id AND pp.partner_id = ?)'
            );
            $params = array_merge([$id], $koepel_ids, [$id]);
            $linkStmt->execute($params);
        }

        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        throw $e;
    }
    json_response(['id' => $id]);
}

if ($method === 'PUT') {
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');

    $fields = [];
    $values = [];
    foreach (['name', 'color', 'notes'] as $f) {
        if (array_key_exists($f, $in)) {
            $fields[] = "$f = ?";
            $values[] = $in[$f];
        }
    }
    if (array_key_exists('is_local', $in)) {
        $fields[] = 'is_local = ?';
        $values[] = !empty($in['is_local']) ? 1 : 0;
    }
    if (array_key_exists('logo_path', $in)) {
        $fields[] = 'logo_path = ?';
        $values[] = $in['logo_path'] ?: null;
    }
    if (!$fields) json_error('Niets te updaten.');
    $values[] = $id;
    db()->prepare('UPDATE pe_partners SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    db()->prepare('DELETE FROM pe_partners WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_error('Methode niet ondersteund.', 405);
