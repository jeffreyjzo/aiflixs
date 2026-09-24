<?php
// Business metrics for the admin dashboard. Admin-only.
require_once __DIR__ . '/db.php';
start_session();

if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);

function one($sql, $args = [], $col = 'n', $default = 0) {
  $r = db_query($sql, $args);
  return isset($r[0][$col]) ? (int) $r[0][$col] : $default;
}

try {
  $me = db_query('SELECT email FROM profiles WHERE id = ?', [$_SESSION['uid']]);
  if (!$me || !in_array(strtolower($me[0]['email']), array_map('strtolower', ADMIN_EMAILS), true)) {
    json_out(['error' => 'forbidden'], 403);
  }

  // ---- People ----
  $people = [
    'total_accounts'   => one('SELECT COUNT(*) AS n FROM profiles'),
    'creators'         => one('SELECT COUNT(*) AS n FROM profiles WHERE is_creator = 1'),
    'watchers'         => one('SELECT COUNT(*) AS n FROM profiles WHERE is_creator = 0 OR is_creator IS NULL'),
    'new_today'        => one("SELECT COUNT(*) AS n FROM profiles WHERE created_at >= date('now')"),
    'new_7d'           => one("SELECT COUNT(*) AS n FROM profiles WHERE created_at > datetime('now','-7 days')"),
    'active_viewers'   => one('SELECT COUNT(DISTINCT user_id) AS n FROM watch_history'),
    'followers_total'  => one('SELECT COUNT(*) AS n FROM follows'),
    'paying_customers' => one('SELECT COUNT(DISTINCT user_id) AS n FROM purchases'),
  ];

  // ---- Content ----
  $content = [
    'total_films'       => one('SELECT COUNT(*) AS n FROM films'),
    'approved'          => one("SELECT COUNT(*) AS n FROM films WHERE status = 'approved'"),
    'pending_review'    => one("SELECT COUNT(*) AS n FROM films WHERE status = 'pending_review'"),
    'pending_upload'    => one("SELECT COUNT(*) AS n FROM films WHERE status = 'pending_upload'"),
    'rejected'          => one("SELECT COUNT(*) AS n FROM films WHERE status = 'rejected'"),
    'uploaded_today'    => one("SELECT COUNT(*) AS n FROM films WHERE created_at >= date('now')"),
    'uploaded_7d'       => one("SELECT COUNT(*) AS n FROM films WHERE created_at > datetime('now','-7 days')"),
    'vertical'          => one('SELECT COUNT(*) AS n FROM films WHERE height > width'),
    'horizontal'        => one('SELECT COUNT(*) AS n FROM films WHERE height <= width'),
    'free_films'        => one('SELECT COUNT(*) AS n FROM films WHERE price_cents = 0'),
    'paid_films'        => one('SELECT COUNT(*) AS n FROM films WHERE price_cents > 0'),
    'storage_bytes'     => one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM films'),
  ];
  $needs_action = $content['pending_review'] + $content['pending_upload'];

  // ---- Engagement ----
  $engagement = [
    'total_views'    => one('SELECT COALESCE(SUM(views),0) AS n FROM films'),
    'watch_events'   => one('SELECT COUNT(*) AS n FROM watch_history'),
    'views_today'    => one("SELECT COUNT(*) AS n FROM watch_history WHERE watched_at >= date('now')"),
    'views_7d'       => one("SELECT COUNT(*) AS n FROM watch_history WHERE watched_at > datetime('now','-7 days')"),
  ];

  // ---- Revenue ----
  $revenue = [
    'gross_cents'      => one('SELECT COALESCE(SUM(amount_cents),0) AS n FROM purchases'),
    'unlocks_total'    => one('SELECT COUNT(*) AS n FROM purchases'),
    'unlocks_today'    => one("SELECT COUNT(*) AS n FROM purchases WHERE created_at >= date('now')"),
    'cents_today'      => one("SELECT COALESCE(SUM(amount_cents),0) AS n FROM purchases WHERE created_at >= date('now')"),
    'unlocks_30d'      => one("SELECT COUNT(*) AS n FROM purchases WHERE created_at > datetime('now','-30 days')"),
    'cents_30d'        => one("SELECT COALESCE(SUM(amount_cents),0) AS n FROM purchases WHERE created_at > datetime('now','-30 days')"),
    'active_unlocks'   => one("SELECT COUNT(*) AS n FROM purchases WHERE created_at > datetime('now','-' || " . UNLOCK_DAYS . " || ' days')"),
  ];
  $revenue['arpu_cents'] = $people['paying_customers'] > 0
    ? (int) round($revenue['gross_cents'] / $people['paying_customers']) : 0;
  $revenue['conversion_pct'] = $people['total_accounts'] > 0
    ? round($people['paying_customers'] / $people['total_accounts'] * 100, 1) : 0;

  // ---- Top films ----
  $top = db_query(
    "SELECT f.id, f.title, f.views, f.price_cents, p.handle,
            (SELECT COUNT(*) FROM purchases pu WHERE pu.film_id = f.id) AS unlocks
     FROM films f JOIN profiles p ON p.id = f.creator_id
     WHERE f.status = 'approved' ORDER BY f.views DESC LIMIT 5"
  );

  // ---- Signup trend (last 14 days) ----
  $trend = db_query(
    "SELECT date(created_at) AS d, COUNT(*) AS n FROM profiles
     WHERE created_at > datetime('now','-14 days') GROUP BY date(created_at) ORDER BY d"
  );

  // ---- Real watch time from Bunny Stream ----
  $stream = ['available' => false, 'watch_time_seconds' => 0, 'views' => 0];
  if (BUNNY_STREAM_LIBRARY_ID !== '' && BUNNY_STREAM_API_KEY !== '') {
    $ch = curl_init('https://video.bunnycdn.com/library/' . BUNNY_STREAM_LIBRARY_ID . '/statistics');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 12,
      CURLOPT_HTTPHEADER => ['AccessKey: ' . BUNNY_STREAM_API_KEY, 'Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $raw) {
      $s = json_decode($raw, true);
      if (is_array($s)) {
        $stream['available'] = true;
        if (!empty($s['watchTimeChart']) && is_array($s['watchTimeChart'])) {
          $stream['watch_time_seconds'] = (int) array_sum(array_values($s['watchTimeChart']));
        }
        if (!empty($s['viewsChart']) && is_array($s['viewsChart'])) {
          $stream['views'] = (int) array_sum(array_values($s['viewsChart']));
        }
        if (isset($s['engagementScore'])) $stream['engagement_score'] = $s['engagementScore'];
      }
    }
  }

  json_out([
    'generated_at' => gmdate('Y-m-d H:i:s') . ' UTC',
    'needs_action' => $needs_action,
    'people' => $people,
    'content' => $content,
    'engagement' => $engagement,
    'revenue' => $revenue,
    'top_films' => $top,
    'signup_trend' => $trend,
    'stream' => $stream,
  ]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
