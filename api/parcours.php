<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// Verwijder een bg_image_path bestand op disk, maar ALLEEN als geen ander
// parcours of versie-snapshot ernaar verwijst. Beschermt tegen het wegvegen
// van een file die nog in gebruik is door bv. een gedupliceerd parcours of
// een herstellen-bare oudere versie.
//   $excludeParcoursId: vergeet dit ID niet mee te tellen — ben jij zelf,
//     je hebt de oude waarde net overschreven en je staat dus niet meer in
//     de lijst van gebruikers.
function cleanup_bg_path_if_orphan(string $path, int $excludeParcoursId): void {
    // Check pe_parcours
    $check = db()->prepare('SELECT 1 FROM pe_parcours WHERE bg_image_path = ? AND id <> ? LIMIT 1');
    $check->execute([$path, $excludeParcoursId]);
    if ($check->fetchColumn()) return;
    
    // Check snapshots — zijn JSON, dus we doen een goedkope LIKE-substring check
    // op de raw kolom. Eventuele false positives leiden tot file-behoud (veilig).
    try {
        $check2 = db()->prepare('SELECT 1 FROM pe_parcours_versions WHERE snapshot_json LIKE ? LIMIT 1');
        // Zoek naar de exacte JSON-string-vorm "bg_image_path":"<path>"
        $needle = '%"bg_image_path":' . json_encode($path) . '%';
        $check2->execute([$needle]);
        if ($check2->fetchColumn()) return;
    } catch (Throwable $e) {
        // Tabel bestaat misschien niet — geen probleem, ga door
    }
    
    safe_unlink_upload($path);
}

/**
 * Derive the season id that matches a YYYY-MM-DD race date.
 *
 * Cycling-cross seasons run from September of year X to end of August
 * of year X+1. So a race on 24-jan-2026 belongs to season 2025-2026
 * (start_year=2025, end_year=2026), NOT 2026-2027.
 *
 * Looks up the matching pe_seasons row by start_year/end_year. If none
 * exists yet, creates one and marks it not-current. Returns the season_id.
 */
function derive_season_id($race_date) {
    if (!preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $race_date, $m)) {
        return 0;
    }
    $year  = (int)$m[1];
    $month = (int)$m[2];
    if ($month >= 9) {
        $start = $year; $end = $year + 1;
    } else {
        $start = $year - 1; $end = $year;
    }
    $label = sprintf('%d-%d', $start, $end);
    
    $stmt = db()->prepare('SELECT id FROM pe_seasons WHERE start_year = ? AND end_year = ? LIMIT 1');
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['id'];
    
    // Auto-create the missing season so the parcours can be saved.
    $stmt = db()->prepare('INSERT INTO pe_seasons (label, start_year, end_year, is_current) VALUES (?, ?, ?, 0)');
    $stmt->execute([$label, $start, $end]);
    return (int)db()->lastInsertId();
}

/**
 * Vervang de set toegewezen gebruikers van een parcours.
 * Gebruikers met has_full_access = 1 worden stilzwijgend overgeslagen — zij
 * zien het parcours sowieso, dus een rij toevoegen heeft geen effect en zou
 * later voor verwarring zorgen als hun vlag teruggeschakeld wordt.
 * Wordt aangeroepen binnen een al-actieve transactie in PUT.
 */
function replace_parcours_users(int $parcoursId, array $userIds): void {
    db()->prepare('DELETE FROM pe_parcours_users WHERE parcours_id = ?')->execute([$parcoursId]);
    if (!$userIds) return;
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = db()->prepare("INSERT INTO pe_parcours_users (parcours_id, user_id)
                           SELECT ?, id FROM pe_users
                           WHERE id IN ($placeholders) AND has_full_access = 0");
    $stmt->execute(array_merge([$parcoursId], $userIds));
}

if ($method === 'GET') {
    // Lijst of detail — iedereen die ingelogd is mag dit zien (incl. viewer/tv).
    if (!empty($_GET['id'])) {
        // Detail
        $id = (int)$_GET['id'];
        // Toegangscheck — gebruikers zonder full access kunnen enkel parcoursen
        // openen waar ze expliciet aan toegevoegd zijn.
        require_parcours_access($me, $id);
        $stmt = db()->prepare('SELECT p.*, s.label AS season_label, k.name AS koepel_name
                               FROM pe_parcours p
                               JOIN pe_seasons s ON s.id = p.season_id
                               LEFT JOIN pe_koepels k ON k.id = p.koepel_id
                               WHERE p.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Parcours niet gevonden.', 404);

        $row['id']        = (int)$row['id'];
        $row['season_id'] = (int)$row['season_id'];
        $row['koepel_id'] = $row['koepel_id'] !== null ? (int)$row['koepel_id'] : null;
        $row['bg_width']  = $row['bg_width']  !== null ? (int)$row['bg_width']  : null;
        $row['bg_height'] = $row['bg_height'] !== null ? (int)$row['bg_height'] : null;
        $row['bg_min_lat'] = isset($row['bg_min_lat']) && $row['bg_min_lat'] !== null ? (float)$row['bg_min_lat'] : null;
        $row['bg_max_lat'] = isset($row['bg_max_lat']) && $row['bg_max_lat'] !== null ? (float)$row['bg_max_lat'] : null;
        $row['bg_min_lng'] = isset($row['bg_min_lng']) && $row['bg_min_lng'] !== null ? (float)$row['bg_min_lng'] : null;
        $row['bg_max_lng'] = isset($row['bg_max_lng']) && $row['bg_max_lng'] !== null ? (float)$row['bg_max_lng'] : null;

        // data_json -> object
        $row['data'] = $row['data_json'] ? json_decode($row['data_json'], true) : null;
        unset($row['data_json']);

        // Welke partners zijn gekoppeld?
        $stmt = db()->prepare('SELECT p.id, p.name, p.color, p.logo_path, p.is_local
                               FROM pe_parcours_partners pp
                               JOIN pe_partners p ON p.id = pp.partner_id
                               WHERE pp.parcours_id = ?
                               ORDER BY p.is_local ASC, p.name ASC');
        $stmt->execute([$id]);
        $partners = $stmt->fetchAll();
        foreach ($partners as &$p) {
            $p['id']       = (int)$p['id'];
            $p['is_local'] = (bool)$p['is_local'];
        }
        $row['partners'] = $partners;

        // Welke gebruikers (zonder full access) hebben expliciet toegang?
        // Alleen voor admins zichtbaar — anderen hebben hier niets aan en
        // we lekken zo niet wie er allemaal mee kan kijken.
        if (($me['role'] ?? '') === 'admin') {
            $stmt = db()->prepare('SELECT user_id FROM pe_parcours_users WHERE parcours_id = ?');
            $stmt->execute([$id]);
            $row['allowed_user_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        json_response(['parcours' => $row]);
    }

    // Lijst
    $where = [];
    $params = [];
    if (!empty($_GET['season_id'])) {
        $where[] = 'p.season_id = ?';
        $params[] = (int)$_GET['season_id'];
    }
    if (!empty($_GET['koepel_id'])) {
        $where[] = 'p.koepel_id = ?';
        $params[] = (int)$_GET['koepel_id'];
    }
    // Toegangsfilter: gebruikers zonder full access zien alleen hun toegewezen parcoursen.
    $vis = parcours_visibility_clause($me, 'p');
    if ($vis['sql'] !== '') {
        $where[] = $vis['sql'];
        $params  = array_merge($params, $vis['params']);
    }
    $sql = 'SELECT p.id, p.season_id, p.koepel_id, p.location_name, p.race_date,
                   p.bg_image_path, p.updated_at,
                   s.label AS season_label, k.name AS koepel_name, k.color AS koepel_color
            FROM pe_parcours p
            JOIN pe_seasons s ON s.id = p.season_id
            LEFT JOIN pe_koepels k ON k.id = p.koepel_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    // Aankomende events eerst (oplopend), voorbije events daarna (aflopend op datum)
    $sql .= ' ORDER BY (p.race_date < CURDATE()) ASC, ' .
            ' CASE WHEN p.race_date >= CURDATE() THEN p.race_date END ASC, ' .
            ' CASE WHEN p.race_date <  CURDATE() THEN p.race_date END DESC, ' .
            ' p.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']        = (int)$r['id'];
        $r['season_id'] = (int)$r['season_id'];
        $r['koepel_id'] = $r['koepel_id'] !== null ? (int)$r['koepel_id'] : null;
    }
    json_response(['parcours' => $rows]);
}

if ($method === 'POST') {
    $in = json_input();

    $action = (string)($in['action'] ?? 'create');

    // Aanmaken/dupliceren mag alleen admin + editor.
    // TV en viewer kunnen geen nieuwe parcoursen starten.
    if (!role_can_manage_parcours($me['role'])) {
        json_error('Geen rechten om een parcours aan te maken of te dupliceren.', 403);
    }

    if ($action === 'create') {
        $location  = trim((string)($in['location_name'] ?? ''));
        $race_date = (string)($in['race_date'] ?? '');
        if ($location === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $race_date)) {
            json_error('Locatie en datum (YYYY-MM-DD) zijn verplicht.');
        }
        // Always derive the season from the race date — ignore any client-supplied
        // season_id. Prevents UI mistakes where the user picked the wrong filter.
        $season_id = derive_season_id($race_date);
        if (!$season_id) json_error('Kon seizoen niet bepalen uit datum.');
        $koepel_id = !empty($in['koepel_id']) ? (int)$in['koepel_id'] : null;
        // Lijst van gebruikers (zonder full access) die expliciet toegang
        // krijgen. Alleen admins mogen dit zetten — editors hebben geen
        // gebruikersbeheer-rechten en zouden anders willekeurige user_ids
        // kunnen koppelen. Editors zonder admin-rol negeren we stilzwijgend.
        $allowedUserIds = [];
        if (($me['role'] ?? '') === 'admin' && isset($in['allowed_user_ids']) && is_array($in['allowed_user_ids'])) {
            $allowedUserIds = array_values(array_unique(array_filter(array_map('intval', $in['allowed_user_ids']))));
        }

        db()->beginTransaction();
        try {
            $stmt = db()->prepare('INSERT INTO pe_parcours (season_id, koepel_id, location_name, race_date, data_json)
                                   VALUES (?, ?, ?, ?, NULL)');
            $stmt->execute([$season_id, $koepel_id, $location, $race_date]);
            $newId = (int)db()->lastInsertId();

            // Koppel automatisch alle partners van de gekozen koepel
            if ($koepel_id) {
                $stmt = db()->prepare('INSERT INTO pe_parcours_partners (parcours_id, partner_id)
                                       SELECT ?, partner_id FROM pe_koepel_partners WHERE koepel_id = ?');
                $stmt->execute([$newId, $koepel_id]);
            }
            // Expliciete user-toegang. We negeren stilzwijgend gebruikers met
            // has_full_access = 1 (toevoegen heeft geen effect, en zo
            // voorkomen we onnodige rijen) en gebruikers die niet bestaan.
            if ($allowedUserIds) {
                $placeholders = implode(',', array_fill(0, count($allowedUserIds), '?'));
                $stmt = db()->prepare("INSERT INTO pe_parcours_users (parcours_id, user_id)
                                       SELECT ?, id FROM pe_users
                                       WHERE id IN ($placeholders) AND has_full_access = 0");
                $stmt->execute(array_merge([$newId], $allowedUserIds));
            }
            // Als de creator zelf geen full access heeft, voeg hem toe —
            // anders verliest hij meteen zijn eigen pas-aangemaakte parcours.
            // (Onmogelijk in praktijk omdat alleen admin/editor mag aanmaken
            // en admins altijd full access hebben, maar voor editors die
            // beperkt werden ingesteld is dit relevant.)
            if (empty($me['has_full_access']) && ($me['role'] ?? '') !== 'admin') {
                $stmt = db()->prepare('INSERT IGNORE INTO pe_parcours_users (parcours_id, user_id) VALUES (?, ?)');
                $stmt->execute([$newId, (int)$me['id']]);
            }
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
        json_response(['id' => $newId]);
    }

    if ($action === 'duplicate') {
        $src_id    = (int)($in['source_id'] ?? 0);
        $location  = trim((string)($in['location_name'] ?? ''));
        $race_date = (string)($in['race_date'] ?? '');
        $copy_zones    = !empty($in['copy_zones']);
        $copy_markers  = !empty($in['copy_markers']);
        $copy_partners = !empty($in['copy_partners']);
        // (parcours-vorm + achtergrond gaan altijd mee)

        if (!$src_id || $location === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $race_date)) {
            json_error('Bron, locatie en datum zijn verplicht.');
        }
        // Toegangscheck: een editor zonder full access mag een parcours alleen
        // dupliceren als hij ook toegang had tot de bron. Anders zou hij via
        // dupliceren stiekem nieuwe parcoursen kunnen onderscheppen.
        require_parcours_access($me, $src_id);
        // Derive season from the new race-date — same logic as 'create'.
        $season_id = derive_season_id($race_date);
        if (!$season_id) json_error('Kon seizoen niet bepalen uit datum.');

        $stmt = db()->prepare('SELECT * FROM pe_parcours WHERE id = ?');
        $stmt->execute([$src_id]);
        $src = $stmt->fetch();
        if (!$src) json_error('Bronparcours niet gevonden.', 404);

        $data = $src['data_json'] ? json_decode($src['data_json'], true) : [];
        if (!$copy_zones) {
            $data['zones'] = [];
            $data['logoPlacements'] = [];
        }
        if (!$copy_markers) {
            $data['markers'] = [];
        }

        $koepel_id = array_key_exists('koepel_id', $in) ? (!empty($in['koepel_id']) ? (int)$in['koepel_id'] : null) : ($src['koepel_id'] ? (int)$src['koepel_id'] : null);

        db()->beginTransaction();
        try {
            $stmt = db()->prepare('INSERT INTO pe_parcours (season_id, koepel_id, location_name, race_date,
                                       bg_image_path, bg_width, bg_height,
                                       bg_min_lat, bg_max_lat, bg_min_lng, bg_max_lng,
                                       data_json)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $season_id,
                $koepel_id,
                $location,
                $race_date,
                $src['bg_image_path'],
                $src['bg_width'],
                $src['bg_height'],
                $src['bg_min_lat'] ?? null,
                $src['bg_max_lat'] ?? null,
                $src['bg_min_lng'] ?? null,
                $src['bg_max_lng'] ?? null,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $newId = (int)db()->lastInsertId();

            // Partners overnemen indien gevraagd
            if ($copy_partners) {
                $stmt = db()->prepare('INSERT INTO pe_parcours_partners (parcours_id, partner_id)
                                       SELECT ?, partner_id FROM pe_parcours_partners WHERE parcours_id = ?');
                $stmt->execute([$newId, $src_id]);
            } elseif ($koepel_id) {
                // Anders alleen koepel-partners
                $stmt = db()->prepare('INSERT INTO pe_parcours_partners (parcours_id, partner_id)
                                       SELECT ?, partner_id FROM pe_koepel_partners WHERE koepel_id = ?');
                $stmt->execute([$newId, $koepel_id]);
            }
            // User-toegangen overnemen van de bron, zodat het nieuwe parcours
            // dezelfde set "beperkte" gebruikers heeft (handig: vorige editie
            // dupliceren naar dit jaar zonder opnieuw iedereen aan te vinken).
            $stmt = db()->prepare('INSERT IGNORE INTO pe_parcours_users (parcours_id, user_id)
                                   SELECT ?, user_id FROM pe_parcours_users WHERE parcours_id = ?');
            $stmt->execute([$newId, $src_id]);
            // Creator zelf koppelen indien beperkt — zelfde reden als bij create.
            if (empty($me['has_full_access']) && ($me['role'] ?? '') !== 'admin') {
                $stmt = db()->prepare('INSERT IGNORE INTO pe_parcours_users (parcours_id, user_id) VALUES (?, ?)');
                $stmt->execute([$newId, (int)$me['id']]);
            }
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
        json_response(['id' => $newId]);
    }

    json_error('Onbekende actie.');
}

if ($method === 'PUT') {
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');

    // Toegangscheck — vóór elke andere check, anders zou een viewer-error
    // bewijzen dat dit parcours-ID bestaat.
    require_parcours_access($me, $id);

    // Viewer mag helemaal niets PUT'en.
    if ($me['role'] === 'viewer') {
        json_error('Viewer-rol kan niets wijzigen.', 403);
    }
    // TV-rol mag alleen camera-data wijzigen, geen meta-velden zoals locatie,
    // datum, koepel, achtergrond, of partner-koppelingen.
    if ($me['role'] === 'tv') {
        $allowedKeys = ['id', 'data'];
        foreach (array_keys($in) as $k) {
            if (!in_array($k, $allowedKeys, true)) {
                json_error('TV-rol kan alleen camera-data wijzigen, niet "' . $k . '".', 403);
            }
        }
    }

    $fields = [];
    $values = [];
    foreach (['location_name', 'race_date'] as $f) {
        if (array_key_exists($f, $in)) {
            $fields[] = "$f = ?";
            $values[] = $in[$f];
        }
    }
    if (array_key_exists('koepel_id', $in)) {
        $fields[] = 'koepel_id = ?';
        $values[] = $in['koepel_id'] ? (int)$in['koepel_id'] : null;
    }
    // If race_date is changing, re-derive the season — keeps the parcours in
    // the right filter bucket regardless of what the client sends.
    if (array_key_exists('race_date', $in) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$in['race_date'])) {
        $derived = derive_season_id((string)$in['race_date']);
        if ($derived) {
            $fields[] = 'season_id = ?';
            $values[] = $derived;
        }
    } elseif (array_key_exists('season_id', $in)) {
        // Manual override only when race_date isn't changing
        $fields[] = 'season_id = ?';
        $values[] = (int)$in['season_id'];
    }
    if (array_key_exists('bg_image_path', $in)) {
        // Als de bg vervangen wordt: oude file van disk halen na de UPDATE.
        // We slaan het oude pad nu op zodat we het later kunnen unlinken; we
        // doen het hieronder pas, na de succesvolle UPDATE.
        $oldBgStmt = db()->prepare('SELECT bg_image_path FROM pe_parcours WHERE id = ?');
        $oldBgStmt->execute([$id]);
        $oldBgPath = $oldBgStmt->fetchColumn();
        $fields[] = 'bg_image_path = ?';
        $values[] = $in['bg_image_path'];
    } else {
        $oldBgPath = null;
    }
    if (array_key_exists('bg_width', $in)) {
        $fields[] = 'bg_width = ?';
        $values[] = (int)$in['bg_width'];
    }
    if (array_key_exists('bg_height', $in)) {
        $fields[] = 'bg_height = ?';
        $values[] = (int)$in['bg_height'];
    }
    foreach (['bg_min_lat', 'bg_max_lat', 'bg_min_lng', 'bg_max_lng'] as $boundCol) {
        if (array_key_exists($boundCol, $in)) {
            $fields[] = "$boundCol = ?";
            $values[] = $in[$boundCol] !== null ? (float)$in[$boundCol] : null;
        }
    }
    if (array_key_exists('data', $in)) {
        // Bepaal de data die opgeslagen wordt.
        $incomingData = is_string($in['data'])
            ? json_decode($in['data'], true)
            : $in['data'];
        if (!is_array($incomingData)) {
            json_error('Ongeldige data-structuur.');
        }

        // TV-rol: filter zodat alleen camera-markers + cameraCounter doorkomen.
        // De rest van de parcours-data wordt overgenomen uit de DB-versie.
        if ($me['role'] === 'tv') {
            $existingStmt = db()->prepare('SELECT data_json FROM pe_parcours WHERE id = ?');
            $existingStmt->execute([$id]);
            $existingRaw = $existingStmt->fetchColumn();
            $existingData = $existingRaw ? json_decode($existingRaw, true) : null;
            $incomingData = tv_filter_data($incomingData, $existingData);
        }

        $fields[] = 'data_json = ?';
        $values[] = json_encode($incomingData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // Toegewezen gebruikers? Alleen admins mogen dit wijzigen — anders zou
    // een editor zichzelf of anderen kunnen toevoegen aan parcoursen waar
    // hij niets te zoeken heeft.
    $updateAllowedUsers = false;
    $newAllowedUserIds  = [];
    if (array_key_exists('allowed_user_ids', $in)) {
        if (($me['role'] ?? '') !== 'admin') {
            json_error('Alleen admins kunnen toegangstoewijzingen wijzigen.', 403);
        }
        if (is_array($in['allowed_user_ids'])) {
            $updateAllowedUsers = true;
            $newAllowedUserIds = array_values(array_unique(array_filter(array_map('intval', $in['allowed_user_ids']))));
        }
    }

    // Partner-koppelingen?
    if (array_key_exists('partner_ids', $in) && is_array($in['partner_ids'])) {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare('DELETE FROM pe_parcours_partners WHERE parcours_id = ?');
            $stmt->execute([$id]);
            $stmt = db()->prepare('INSERT INTO pe_parcours_partners (parcours_id, partner_id) VALUES (?, ?)');
            foreach ($in['partner_ids'] as $pid) {
                $stmt->execute([$id, (int)$pid]);
            }
            if ($updateAllowedUsers) {
                replace_parcours_users($id, $newAllowedUserIds);
            }
            if ($fields) {
                $values[] = $id;
                db()->prepare('UPDATE pe_parcours SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
            }
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
        // Oude bg-file opruimen als die net vervangen werd door een nieuwe upload.
        // Eerst checken of het oude pad nog door iets anders gebruikt wordt
        // (snapshots of ander parcours), anders zou restore breken.
        if (!empty($oldBgPath) && $oldBgPath !== ($in['bg_image_path'] ?? null)) {
            cleanup_bg_path_if_orphan($oldBgPath, $id);
        }
        json_response(['ok' => true]);
    }

    // Geen partner-koppelingen, maar misschien wel user-toegangen.
    if ($updateAllowedUsers) {
        db()->beginTransaction();
        try {
            replace_parcours_users($id, $newAllowedUserIds);
            if ($fields) {
                $values[] = $id;
                db()->prepare('UPDATE pe_parcours SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
            }
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            throw $e;
        }
        if (!empty($oldBgPath) && $oldBgPath !== ($in['bg_image_path'] ?? null)) {
            cleanup_bg_path_if_orphan($oldBgPath, $id);
        }
        json_response(['ok' => true]);
    }

    if (!$fields) json_error('Niets te updaten.');
    $values[] = $id;
    db()->prepare('UPDATE pe_parcours SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    if (!empty($oldBgPath) && $oldBgPath !== ($in['bg_image_path'] ?? null)) {
        cleanup_bg_path_if_orphan($oldBgPath, $id);
    }
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    if (!role_can_manage_parcours($me['role'])) {
        json_error('Geen rechten om een parcours te verwijderen.', 403);
    }
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id is verplicht.');
    // Toegangscheck — een editor zonder toegang mag niets verwijderen,
    // ook al is hij role-wise gerechtigd.
    require_parcours_access($me, $id);
    
    // Verzamel ALLE bg_image_paths die bij dit parcours horen voor we het rij
    // verwijderen — zowel het huidige als die in versie-snapshots.
    // Dat doen we in één query (current row) + één query (snapshots).
    // We slaan ze op in een array en lopen ze daarna stuk per stuk af voor unlink.
    $bgPaths = [];
    
    // 1. Huidige bg
    $stmt = db()->prepare('SELECT bg_image_path FROM pe_parcours WHERE id = ?');
    $stmt->execute([$id]);
    $cur = $stmt->fetchColumn();
    if ($cur) $bgPaths[] = $cur;
    
    // 2. Snapshot-bg's — staan in snapshot_json onder de key "bg_image_path"
    try {
        $stmt = db()->prepare('SELECT snapshot_json FROM pe_parcours_versions WHERE parcours_id = ?');
        $stmt->execute([$id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $snap = json_decode($row['snapshot_json'] ?? '', true);
            if (is_array($snap) && !empty($snap['bg_image_path'])) {
                $bgPaths[] = $snap['bg_image_path'];
            }
        }
    } catch (Throwable $e) {
        // Tabel bestaat misschien nog niet (oude installs zonder migratie) — geen probleem
    }
    
    // 3. Veiligheidsfilter: een ander parcours kan nog steeds dezelfde bg gebruiken
    //    (bv. via Duplicate-flow). Voor elke unieke kandidaat checken of er nog een
    //    andere parcours-rij naar verwijst. Anders zou we het bestand stelen van
    //    een levend parcours.
    $unique = array_values(array_unique(array_filter($bgPaths)));
    $toUnlink = [];
    if ($unique) {
        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $params = array_merge($unique, [$id]);
        $check = db()->prepare("SELECT bg_image_path FROM pe_parcours WHERE bg_image_path IN ($placeholders) AND id <> ?");
        $check->execute($params);
        $stillUsed = [];
        while ($p = $check->fetchColumn()) $stillUsed[$p] = true;
        foreach ($unique as $p) {
            if (!isset($stillUsed[$p])) $toUnlink[] = $p;
        }
    }
    
    // 4. DB-rij weg (CASCADE zorgt voor pe_parcours_partners + pe_parcours_versions)
    db()->prepare('DELETE FROM pe_parcours WHERE id = ?')->execute([$id]);
    
    // 5. Files weg — pas NA succesvolle DB-delete. Falen logged maar blokkeert niets.
    $deletedFiles = 0;
    foreach ($toUnlink as $p) {
        if (safe_unlink_upload($p)) $deletedFiles++;
    }
    
    json_response(['ok' => true, 'files_deleted' => $deletedFiles, 'files_attempted' => count($toUnlink)]);
}

json_error('Methode niet ondersteund.', 405);
