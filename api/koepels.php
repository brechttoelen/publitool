<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET mag iedereen (om koepel-namen/logos te tonen).
// Schrijfacties (POST/PUT/DELETE) = admin only.
if ($method !== 'GET' && !role_is_admin($me['role'])) {
    json_error('Alleen een admin kan koepels beheren.', 403);
}

if ($method === 'GET') {
    $koepels = db()->query('SELECT id, name, short_name, color, sort_order FROM pe_koepels ORDER BY sort_order ASC, name ASC')->fetchAll();

    // Haal partners per koepel op
    $partners_by_koepel = [];
    $stmt = db()->query('SELECT kp.koepel_id, kp.partner_id, kp.sort_order, p.name, p.color, p.logo_path
                          FROM pe_koepel_partners kp
                          JOIN pe_partners p ON p.id = kp.partner_id
                          ORDER BY kp.koepel_id, kp.sort_order, p.name');
    while ($row = $stmt->fetch()) {
        $kid = (int)$row['koepel_id'];
        if (!isset($partners_by_koepel[$kid])) $partners_by_koepel[$kid] = [];
        $partners_by_koepel[$kid][] = [
            'id'        => (int)$row['partner_id'],
            'name'      => $row['name'],
            'color'     => $row['color'],
            'logo_path' => $row['logo_path'],
        ];
    }

    foreach ($koepels as &$k) {
        $k['id']         = (int)$k['id'];
        $k['sort_order'] = (int)$k['sort_order'];
        $k['partners']   = $partners_by_koepel[(int)$k['id']] ?? [];
    }
    json_response(['koepels' => $koepels]);
}

if ($method === 'POST') {
    $in = json_input();
    $name = trim((string)($in['name'] ?? ''));
    if ($name === '') json_error('Naam is verplicht.');
    $stmt = db()->prepare('INSERT INTO pe_koepels (name, short_name, color, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $name,
        $in['short_name'] ?? null,
        $in['color'] ?? '#1c1917',
        (int)($in['sort_order'] ?? 0),
    ]);
    json_response(['id' => (int)db()->lastInsertId()]);
}

if ($method === 'PUT') {
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');

    // Update partner-koppelingen voor deze koepel?
    if (isset($in['partner_ids']) && is_array($in['partner_ids'])) {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare('DELETE FROM pe_koepel_partners WHERE koepel_id = ?');
            $stmt->execute([$id]);
            $stmt = db()->prepare('INSERT INTO pe_koepel_partners (koepel_id, partner_id, sort_order) VALUES (?, ?, ?)');
            foreach ($in['partner_ids'] as $i => $pid) {
                $stmt->execute([$id, (int)$pid, $i]);
            }
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
    }

    $fields = [];
    $values = [];
    foreach (['name', 'short_name', 'color'] as $f) {
        if (array_key_exists($f, $in)) {
            $fields[] = "$f = ?";
            $values[] = $in[$f];
        }
    }
    if ($fields) {
        $values[] = $id;
        db()->prepare('UPDATE pe_koepels SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    }

    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    db()->prepare('DELETE FROM pe_koepels WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_error('Methode niet ondersteund.', 405);
