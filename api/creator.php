<?php
// Public creator page data: profile + their published films.
require_once __DIR__ . '/db.php';

$handle = trim($_GET['handle'] ?? '');
if ($handle === '') json_out(['error' => 'missing_handle'], 400);

try {
  $prof = db_query(
    'SELECT id, handle, display_name, bio, avatar_url, created_at FROM profiles WHERE handle = ?',
    [$handle]
  );
  if (!$prof) json_out(['error' => 'creator_not_found'], 404);
  $p = $prof[0];

  $films = db_query(
    "SELECT id, title, synopsis, price_cents, views, created_at
     FROM films WHERE creator_id = ? AND status = 'approved' ORDER BY created_at DESC",
    [$p['id']]
  );
  $followers = db_query('SELECT COUNT(*) AS n FROM follows WHERE creator_id = ?', [$p['id']]);
  $views = 0;
  foreach ($films as $f) $views += (int) $f['views'];

  json_out([
    'creator' => [
      'handle' => $p['handle'], 'display_name' => $p['display_name'],
      'bio' => $p['bio'], 'avatar_url' => $p['avatar_url'], 'joined' => $p['created_at'],
    ],
    'films' => $films,
    'stats' => ['films' => count($films), 'followers' => (int) ($followers[0]['n'] ?? 0), 'views' => $views],
  ]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
