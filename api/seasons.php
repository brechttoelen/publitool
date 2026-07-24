<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET mag iedereen, schrijven = admin only.
if ($method !== 'GET' && !role_is_admin($me['role'])) {
    json_error('Alleen een admin kan seizoenen beheren.', 403);
}

if ($method === 'GET') {
    $rows = db()->query('SELECT id, label, start_year, end_year, is_current FROM pe_seasons ORDER BY start_year DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['id']         = (int)$r['id'];
        $r['start_year'] = (int)$r['start_year'];
        $r['end_year']   = (int)$r['end_year'];
        $r['is_current'] = (bool)$r['is_current'];
    }
    json_response(['seasons' => $rows]);
}

if ($method === 'POST') {
    $in = json_input();
    $start = (int)($in['start_year'] ?? 0);
    $end   = (int)($in['end_year'] ?? 0);
    if ($start < 2000 || $end < 2000 || $end < $start) {
        json_error('Ongeldige jaartallen.');
    }
    $label = $start . '-' . $end;
    try {
        $stmt = db()->prepare('INSERT INTO pe_seasons (label, start_year, end_year, is_current) VALUES (?, ?, ?, 0)');
        $stmt->execute([$label, $start, $end]);
        $id = (int)db()->lastInsertId();
        json_response(['id' => $id, 'label' => $label, 'start_year' => $start, 'end_year' => $end, 'is_current' => false]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_error('Dat seizoen bestaat al.', 409);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!empty($in['set_current'])) {
        db()->beginTransaction();
        try {
            db()->exec('UPDATE pe_seasons SET is_current = 0');
            $stmt = db()->prepare('UPDATE pe_seasons SET is_current = 1 WHERE id = ?');
            $stmt->execute([$id]);
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
        json_response(['ok' => true]);
    }
    json_error('Niets te updaten.');
}

json_error('Methode niet ondersteund.', 405);
