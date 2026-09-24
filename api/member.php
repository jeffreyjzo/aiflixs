<?php
// Member page: profile + active unlocks + watch history + suggestions.
require_once __DIR__ . '/db.php';
start_session();

if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);
$uid = $_SESSION['uid'];

try {
  $prof = db_query('SELECT id, handle, display_name, bio, avatar_url, is_creator, created_at FROM profiles WHERE id = ?', [$uid]);
  if (!$prof) json_out(['error' => 'no_profile'], 404);

  $unlocks = db_query(
    "SELECT f.id, f.title, f.price_cents, p.handle, datetime(pu.created_at, '+' || ? || ' days') AS expires_at
     FROM purchases pu JOIN films f ON f.id = pu.film_id JOIN profiles p ON p.id = f.creator_id
     WHERE pu.user_id = ? AND pu.created_at > datetime('now', '-' || ? || ' days')
     ORDER BY pu.created_at DESC",
    [UNLOCK_DAYS, $uid, UNLOCK_DAYS]
  );

  $history = db_query(
    "SELECT f.id, f.title, f.price_cents, f.views, p.handle, w.watched_at
     FROM watch_history w JOIN films f ON f.id = w.film_id JOIN profiles p ON p.id = f.creator_id
     WHERE w.user_id = ? AND f.status = 'approved'
     ORDER BY w.watched_at DESC LIMIT 24",
    [$uid]
  );

  $suggested = db_query(
    "SELECT f.id, f.title, f.price_cents, f.views, p.handle
     FROM films f JOIN profiles p ON p.id = f.creator_id
     WHERE f.status = 'approved'
       AND f.id NOT IN (SELECT film_id FROM watch_history WHERE user_id = ?)
     ORDER BY f.views DESC, f.created_at DESC LIMIT 8",
    [$uid]
  );

  json_out([
    'profile' => $prof[0],
    'unlocks' => $unlocks,
    'history' => $history,
    'suggested' => $suggested,
  ]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
