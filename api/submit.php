<?php
// Film submission: stores the film record and enforces platform rules
// server-side (1 film per creator, size and resolution limits).
// The actual video bytes go creator's browser -> Bunny Stream once the
// Stream library is configured (BUNNY_STREAM_* in config.php).
require_once __DIR__ . '/db.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);

$b = json_decode(file_get_contents('php://input'), true) ?: [];
$title    = trim($b['title'] ?? '');
$synopsis = trim($b['synopsis'] ?? '');
$price    = (int) ($b['price_cents'] ?? 0);
$width    = (int) ($b['width'] ?? 0);
$height   = (int) ($b['height'] ?? 0);
$size     = (int) ($b['size_bytes'] ?? 0);

if ($title === '' || $synopsis === '') json_out(['error' => 'missing_fields'], 400);
if ($price !== 0 && ($price < PRICE_MIN_CENTS || $price > PRICE_MAX_CENTS)) json_out(['error' => 'invalid_price'], 400);
if ($size <= 0 || $size > MAX_BYTES) json_out(['error' => 'file_too_large'], 400);

$shortSide = min($width, $height);
if ($shortSide < MIN_RES) json_out(['error' => 'resolution_too_low'], 400);
if ($shortSide > MAX_RES) json_out(['error' => 'resolution_too_high'], 400);

try {
  // Per-account film slots (admins can raise this from the dashboard)
  $prof = db_query('SELECT COALESCE(film_slots, 1) AS slots, COALESCE(suspended, 0) AS suspended FROM profiles WHERE id = ?', [$_SESSION['uid']]);
  if (!$prof) json_out(['error' => 'no_profile'], 404);
  if ((int) $prof[0]['suspended'] === 1) json_out(['error' => 'account_suspended'], 403);

  $slots = max(1, (int) $prof[0]['slots']);
  $used  = (int) (db_query('SELECT COUNT(*) AS n FROM films WHERE creator_id = ?', [$_SESSION['uid']])[0]['n'] ?? 0);
  if ($used >= $slots) json_out(['error' => 'slot_limit', 'slots' => $slots, 'used' => $used], 409);

  db_query(
    'INSERT INTO films (creator_id, title, synopsis, price_cents, status, width, height, size_bytes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [$_SESSION['uid'], $title, $synopsis, $price, 'pending_upload', $width, $height, $size]
  );
  db_query('UPDATE profiles SET is_creator = 1 WHERE id = ?', [$_SESSION['uid']]);

  json_out(['ok' => true, 'status' => 'pending_upload',
    'note' => BUNNY_STREAM_LIBRARY_ID === ''
      ? 'Film registered. Video ingest activates once Bunny Stream is connected.'
      : 'Film registered. Upload will begin.']);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
