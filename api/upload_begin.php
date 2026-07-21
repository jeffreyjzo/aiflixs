<?php
// Creates the video slot in Bunny Stream and returns presigned TUS upload
// credentials so the creator's browser uploads the 20 GB file DIRECTLY to
// Bunny — the video never touches this server.
require_once __DIR__ . '/db.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);
if (BUNNY_STREAM_LIBRARY_ID === '' || BUNNY_STREAM_API_KEY === '') {
  json_out(['error' => 'stream_not_configured'], 503);
}

try {
  $film = db_query("SELECT id, title, bunny_video_id FROM films WHERE creator_id = ? AND status = 'pending_upload'", [$_SESSION['uid']]);
  if (!$film) json_out(['error' => 'no_pending_film'], 404);
  $film = $film[0];

  // Create (or reuse) the Bunny Stream video object
  $videoId = $film['bunny_video_id'];
  if (!$videoId) {
    $ch = curl_init('https://video.bunnycdn.com/library/' . BUNNY_STREAM_LIBRARY_ID . '/videos');
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_HTTPHEADER => ['AccessKey: ' . BUNNY_STREAM_API_KEY, 'Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode(['title' => $film['title']]),
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $videoId = $res['guid'] ?? null;
    if (!$videoId) json_out(['error' => 'bunny_create_failed'], 502);
    db_query('UPDATE films SET bunny_video_id = ? WHERE id = ?', [$videoId, (int) $film['id']]);
  }

  $expiration = time() + 6 * 3600; // 6h window for big uploads
  $signature = hash('sha256', BUNNY_STREAM_LIBRARY_ID . BUNNY_STREAM_API_KEY . $expiration . $videoId);

  json_out([
    'libraryId'  => BUNNY_STREAM_LIBRARY_ID,
    'videoId'    => $videoId,
    'expiration' => $expiration,
    'signature'  => $signature,
    'endpoint'   => 'https://video.bunnycdn.com/tusupload',
  ]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
