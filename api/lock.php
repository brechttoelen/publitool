<?php
/**
 * Lock endpoint — beheert wie er momenteel bezig is met een parcours.
 * Voorkomt dat 2 personen tegelijk bewerken zonder elkaars werk te
 * overschrijven.
 *
 * Een lock vervalt automatisch na 2 minuten zonder heartbeat —
 * dat handelen we af door bij elke aanvraag eerst stale locks te
 * wissen, vóór we een nieuwe claim/check doen.
 *
 * Endpoints:
 *   GET  /api/lock.php?parcours_id=N
 *        → { lock: { user_id, user_name, acquired_at } | null,
 *            held_by_me: bool }
 *
 *   POST /api/lock.php?parcours_id=N
 *        body: { force?: bool }
 *        → { ok: true, lock: {...}, held_by_me: true }
 *        Of bij conflict zonder force:
 *        → 409 { error: "...", lock: {...} }
 *
 *   PUT  /api/lock.php?parcours_id=N        (heartbeat)
 *        → { ok: true, held_by_me: bool, lock: {...} }
 *        Bij verlies (iemand heeft overgenomen):
 *        → 409 { error: "Lock verloren", lock: {...} }
 *
 *   DELETE /api/lock.php?parcours_id=N
 *        Vrijgeven (alleen door huidige holder).
 *        → { ok: true }
 */

require __DIR__ . '/_bootstrap.php';
require_auth();

$LOCK_TIMEOUT_SECONDS = 120; // 2 minuten — match met front-end heartbeat van 30s × 4

/** Verwijder verlopen locks (geen heartbeat in de laatste N seconden). */
function cleanup_stale_locks($timeoutSec) {
    $stmt = db()->prepare('DELETE FROM pe_locks
                           WHERE last_heartbeat_at < (NOW() - INTERVAL ? SECOND)');
    $stmt->execute([$timeoutSec]);
}

/** Lees de lock voor een parcours, of null. */
function read_lock($parcours_id) {
    $stmt = db()->prepare('SELECT parcours_id, user_id, user_name, acquired_at, last_heartbeat_at
                           FROM pe_locks WHERE parcours_id = ?');
    $stmt->execute([$parcours_id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'parcours_id'       => (int)$row['parcours_id'],
        'user_id'           => (int)$row['user_id'],
        'user_name'         => $row['user_name'],
        'acquired_at'       => $row['acquired_at'],
        'last_heartbeat_at' => $row['last_heartbeat_at'],
    ];
}

$parcours_id = (int)($_GET['parcours_id'] ?? 0);
if (!$parcours_id) json_error('parcours_id is verplicht.');

$user = current_user();
if (!$user) json_error('Niet ingelogd.', 401);

// Toegangscheck — gebruikers zonder toegang tot dit parcours mogen er ook
// geen lock op claimen of bekijken. We retourneren 404 zodat het bestaan
// van het parcours niet lekt.
require_parcours_access($user, $parcours_id);

$method = $_SERVER['REQUEST_METHOD'];

cleanup_stale_locks($LOCK_TIMEOUT_SECONDS);

if ($method === 'GET') {
    $lock = read_lock($parcours_id);
    json_response([
        'lock' => $lock,
        'held_by_me' => $lock && $lock['user_id'] === (int)$user['id'],
    ]);
}

if ($method === 'POST') {
    // Claim a lock. With force=true we kick out whoever currently holds it.
    $in = json_input();
    $force = !empty($in['force']);
    
    $existing = read_lock($parcours_id);
    if ($existing && $existing['user_id'] !== (int)$user['id'] && !$force) {
        json_response([
            'error' => 'Bezet door ' . $existing['user_name'],
            'lock' => $existing,
        ], 409);
    }
    
    // Insert or update — ON DUPLICATE KEY makes the takeover atomic.
    $userName = $user['display_name'] ?: ($user['username'] ?? 'Onbekend');
    $stmt = db()->prepare('INSERT INTO pe_locks (parcours_id, user_id, user_name)
                           VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                             user_id = VALUES(user_id),
                             user_name = VALUES(user_name),
                             acquired_at = CURRENT_TIMESTAMP,
                             last_heartbeat_at = CURRENT_TIMESTAMP');
    $stmt->execute([$parcours_id, (int)$user['id'], $userName]);
    
    $lock = read_lock($parcours_id);
    json_response(['ok' => true, 'lock' => $lock, 'held_by_me' => true]);
}

if ($method === 'PUT') {
    // Heartbeat — only succeeds if the requesting user still holds the lock.
    $existing = read_lock($parcours_id);
    if (!$existing) {
        // Our lock vanished — typically because someone else took it and we
        // missed the takeover. Don't auto-recreate; ask the front-end to handle it.
        json_response(['error' => 'Lock verloren', 'lock' => null], 409);
    }
    if ($existing['user_id'] !== (int)$user['id']) {
        json_response(['error' => 'Lock overgenomen door ' . $existing['user_name'], 'lock' => $existing], 409);
    }
    // Touch the row to bump last_heartbeat_at
    $stmt = db()->prepare('UPDATE pe_locks SET last_heartbeat_at = CURRENT_TIMESTAMP WHERE parcours_id = ? AND user_id = ?');
    $stmt->execute([$parcours_id, (int)$user['id']]);
    json_response(['ok' => true, 'held_by_me' => true, 'lock' => read_lock($parcours_id)]);
}

if ($method === 'DELETE') {
    // Release — only the current holder may release.
    $stmt = db()->prepare('DELETE FROM pe_locks WHERE parcours_id = ? AND user_id = ?');
    $stmt->execute([$parcours_id, (int)$user['id']]);
    json_response(['ok' => true]);
}

json_error('Onbekende methode.', 405);
