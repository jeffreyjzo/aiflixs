<?php
// Watch access: returns film info and, if the viewer is allowed
// (free film, valid 7-day unlock, or the creator themselves), the player URL.
require_once __DIR__ . '/db.php';
start_session();

$filmId = (int) ($_GET['film'] ?? 0);
if (!$filmId) json_out(['error' => 'missing_film'], 400);

try {
  $rows = db_query(
    "SELECT f.id, f.title, f.synopsis, f.price_cents, f.status, f.bunny_video_id, f.views, f.creator_id,
            f.width, f.height, p.handle, p.display_name, p.avatar_url
     FROM films f JOIN profiles p ON p.id = f.creator_id WHERE f.id = ?",
    [$filmId]
  );
  if (!$rows) json_out(['error' => 'film_not_found'], 404);
  $f = $rows[0];

  $uid = $_SESSION['uid'] ?? null;
  $isCreator = $uid && $uid === $f['creator_id'];
  if ($f['status'] !== 'approved' && !$isCreator) json_out(['error' => 'not_available'], 403);

  $canWatch = false;
  $expiresAt = null;
  if ((int) $f['price_cents'] === 0 || $isCreator) {
    $canWatch = true;
  } elseif ($uid) {
    $p = db_query(
      "SELECT datetime(created_at, '+' || ? || ' days') AS expires_at FROM purchases
       WHERE user_id = ? AND film_id = ? AND created_at > datetime('now', '-' || ? || ' days')",
      [UNLOCK_DAYS, $uid, $filmId, UNLOCK_DAYS]
    );
    if ($p) { $canWatch = true; $expiresAt = $p[0]['expires_at']; }
  }

  $out = [
    'film' => [
      'id' => (int) $f['id'], 'title' => $f['title'], 'synopsis' => $f['synopsis'],
      'price_cents' => (int) $f['price_cents'], 'status' => $f['status'],
      'handle' => $f['handle'], 'display_name' => $f['display_name'], 'views' => (int) $f['views'],
      'width' => (int) $f['width'], 'height' => (int) $f['height'],
      'orientation' => ((int) $f['height'] > (int) $f['width']) ? 'vertical' : 'horizontal',
    ],
    'signed_in' => (bool) $uid,
    'can_watch' => $canWatch,
    'expires_at' => $expiresAt,
    'unlock_days' => UNLOCK_DAYS,
  ];
  if ($canWatch && $f['bunny_video_id']) {
    $out['embed_url'] = 'https://iframe.mediadelivery.net/embed/' . BUNNY_STREAM_LIBRARY_ID . '/' . $f['bunny_video_id'];
    db_query('UPDATE films SET views = views + 1 WHERE id = ?', [$filmId]);
    // Member watch history (feeds the member page + creator analytics)
    if ($uid) {
      db_query('INSERT OR REPLACE INTO watch_history (user_id, film_id) VALUES (?, ?)', [$uid, $filmId]);
    }
  }
  json_out($out);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
