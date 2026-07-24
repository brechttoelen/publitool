<?php
// Diagnose-endpoint v2 voor bounds-problemen.
// GET /api/_diag_bounds.php?id=12         → toont DB-staat + simuleert UPDATE met dummy bounds
// GET /api/_diag_bounds.php?id=12&clean=1 → wist dummy bounds (zet ze terug naar null)

require __DIR__ . '/_bootstrap.php';
require_auth();

// Force errors visible — anders krijgen we 500 zonder uitleg
ini_set('display_errors', '1');
error_reporting(E_ALL);

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_error('id verplicht');

$out = [];

// 1. Huidige DB-rij
try {
    $stmt = db()->prepare('SELECT id, bg_image_path, bg_width, bg_height, bg_min_lat, bg_max_lat, bg_min_lng, bg_max_lng FROM pe_parcours WHERE id = ?');
    $stmt->execute([$id]);
    $out['current_row'] = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['current_row_error'] = $e->getMessage();
}

// 2. Kolom-definities
try {
    $colStmt = db()->query("SHOW COLUMNS FROM pe_parcours WHERE Field LIKE 'bg_%'");
    $out['columns'] = $colStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['columns_error'] = $e->getMessage();
}

// 3. Test schrijven met dummy waarden via GET (eenvoudiger te triggeren)
if (!isset($_GET['clean'])) {
    $testLat = 50.123456;
    $testLng = 4.987654;
    try {
        $upd = db()->prepare('UPDATE pe_parcours SET bg_min_lat = ?, bg_max_lat = ?, bg_min_lng = ?, bg_max_lng = ? WHERE id = ?');
        $upd->execute([$testLat, $testLat + 0.01, $testLng, $testLng + 0.01, $id]);
        $out['test_write'] = ['affected_rows' => $upd->rowCount(), 'ok' => true];

        $stmt2 = db()->prepare('SELECT bg_min_lat, bg_max_lat, bg_min_lng, bg_max_lng FROM pe_parcours WHERE id = ?');
        $stmt2->execute([$id]);
        $out['after_test_write'] = $stmt2->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $out['test_write'] = ['ok' => false, 'error' => $e->getMessage()];
    }
} else {
    // Cleanup-modus: zet bounds terug op null
    try {
        $clr = db()->prepare('UPDATE pe_parcours SET bg_min_lat = NULL, bg_max_lat = NULL, bg_min_lng = NULL, bg_max_lng = NULL WHERE id = ?');
        $clr->execute([$id]);
        $out['cleaned'] = true;
    } catch (Throwable $e) {
        $out['clean_error'] = $e->getMessage();
    }
}

// 4. Lees parcours.php zelf in en check of de bounds-update-tak erin staat.
// Dit pakt het exacte bestand op de server zelf.
$parcoursPhp = file_get_contents(__DIR__ . '/parcours.php');
$out['parcours_php_size'] = strlen($parcoursPhp);
$out['parcours_php_has_bounds_update'] = strpos($parcoursPhp, "'bg_min_lat', 'bg_max_lat', 'bg_min_lng', 'bg_max_lng'") !== false;

json_response($out);
