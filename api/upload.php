<?php
require __DIR__ . '/_bootstrap.php';
$me = require_auth();
require_method('POST');

// Uploads (KMZ-renders, logo's) horen bij parcours-werk.
// TV en viewer kunnen geen bestanden uploaden.
if (!in_array($me['role'], ['admin', 'editor'], true)) {
    json_error('Geen rechten om bestanden te uploaden.', 403);
}

global $CONFIG;

if (empty($_FILES['file'])) {
    json_error('Geen bestand ontvangen.');
}

$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    json_error('Upload mislukt (foutcode ' . $f['error'] . ').');
}
if ($f['size'] > $CONFIG['max_upload_size']) {
    json_error('Bestand te groot (max ' . round($CONFIG['max_upload_size']/1024/1024, 1) . ' MB).');
}

$kind = (string)($_POST['kind'] ?? 'maps'); // 'maps' of 'logos'
if (!in_array($kind, ['maps', 'logos'], true)) {
    json_error('Ongeldig type.');
}

// Validate MIME
$allowed_mime = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $f['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed_mime, true)) {
    json_error('Bestandstype niet toegestaan: ' . $mime);
}

$ext = match ($mime) {
    'image/png'        => 'png',
    'image/jpeg', 'image/jpg' => 'jpg',
    'image/svg+xml'    => 'svg',
    'image/webp'       => 'webp',
    default            => 'bin',
};

$dir = ensure_uploads_dir($kind);
$safeOrig = safe_filename(pathinfo($f['name'], PATHINFO_FILENAME));
$filename = $safeOrig . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest = $dir . '/' . $filename;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    json_error('Kon bestand niet opslaan.', 500);
}

$rel_url = rtrim($CONFIG['uploads_url'], '/') . '/' . $kind . '/' . $filename;

json_response([
    'url'      => $rel_url,
    'filename' => $filename,
    'size'     => $f['size'],
    'mime'     => $mime,
]);
