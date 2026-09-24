<?php
// Creator dashboard: profile, films, slot allowance, and analytics
// (views, unique viewers, unlocks, revenue, followers, recent unlocks).
require_once __DIR__ . '/db.php';
start_session();

if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);
$uid = $_SESSION['uid'];

try {
  $prof = db_query(
    'SELECT id, handle, display_name, bio, avatar_url, created_at,
            COALESCE(is_creator,0) AS is_creator,
            COALESCE(film_slots,1) AS film_slots,
            COALESCE(suspended,0) AS suspended
     FROM profiles WHERE id = ?', [$uid]
  );
  if (!$prof) json_out(['error' => 'no_profile'], 404);
  $p = $prof[0];

  $films = db_query(
    'SELECT id, title, synopsis, price_cents, status, width, height, size_bytes, views, bunny_video_id, created_at
     FROM films WHERE creator_id = ? ORDER BY created_at DESC', [$uid]
  );

  $ids = [];
  foreach ($films as &$f) {
    $f['orientation'] = ((int) $f['height'] > (int) $f['width']) ? 'vertical' : 'horizontal';
    $ids[] = (int) $f['id'];
  }
  unset($f);

  $followers = db_query('SELECT COUNT(*) AS n FROM follows WHERE creator_id = ?', [$uid]);

  $analytics = ['views' => 0, 'unique_viewers' => 0, 'unlocks' => 0, 'revenue_cents' => 0];
  $recent = [];
  if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    foreach ($films as $f) $analytics['views'] += (int) $f['views'];
    $uv = db_query("SELECT COUNT(DISTINCT user_id) AS n FROM watch_history WHERE film_id IN ($in)");
    $analytics['unique_viewers'] = (int) ($uv[0]['n'] ?? 0);
    $pu = db_query("SELECT COUNT(*) AS n, COALESCE(SUM(amount_cents),0) AS rev FROM purchases WHERE film_id IN ($in)");
    $analytics['unlocks'] = (int) ($pu[0]['n'] ?? 0);
    $analytics['revenue_cents'] = (int) ($pu[0]['rev'] ?? 0);
    $recent = db_query(
      "SELECT pr.handle, pu.amount_cents, pu.created_at, f.title
       FROM purchases pu
       JOIN profiles pr ON pr.id = pu.user_id
       JOIN films f ON f.id = pu.film_id
       WHERE pu.film_id IN ($in) ORDER BY pu.created_at DESC LIMIT 10"
    );
  }

  $slots = max(1, (int) $p['film_slots']);
  json_out([
    'profile' => $p,
    'films' => $films,
    'slots' => ['total' => $slots, 'used' => count($films), 'remaining' => max(0, $slots - count($films))],
    'followers' => (int) ($followers[0]['n'] ?? 0),
    'analytics' => $analytics,
    'recent_unlocks' => $recent,
  ]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
