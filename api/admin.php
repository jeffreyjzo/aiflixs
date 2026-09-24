<?php
// Review dashboard backend. Only accounts whose email is in ADMIN_EMAILS.
//  GET  -> films awaiting review
//  POST {film_id, action: "approve"|"reject"} -> update status
require_once __DIR__ . '/db.php';
start_session();

if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);

/** One call to Bunny Stream -> map of videoId => encoding info. */
function bunny_video_map(): array {
  if (BUNNY_STREAM_LIBRARY_ID === '' || BUNNY_STREAM_API_KEY === '') return [];
  $ch = curl_init('https://video.bunnycdn.com/library/' . BUNNY_STREAM_LIBRARY_ID . '/videos?page=1&itemsPerPage=100&orderBy=date');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['AccessKey: ' . BUNNY_STREAM_API_KEY, 'Accept: application/json'],
  ]);
  $raw = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code !== 200 || !$raw) return [];
  $data = json_decode($raw, true);
  $map = [];
  foreach (($data['items'] ?? []) as $v) {
    if (empty($v['guid'])) continue;
    $map[$v['guid']] = [
      'encode_status'   => (int) ($v['status'] ?? 0),
      'encode_progress' => (int) ($v['encodeProgress'] ?? 0),
      'length_seconds'  => (int) ($v['length'] ?? 0),
      'stream_width'    => (int) ($v['width'] ?? 0),
      'stream_height'   => (int) ($v['height'] ?? 0),
      'stream_views'    => (int) ($v['views'] ?? 0),
      'thumbnail_file'  => $v['thumbnailFileName'] ?? null,
    ];
  }
  return $map;
}

/** Attach playable URLs + encoding state to a film row. */
function decorate_video(array &$r, array $map): void {
  $guid = $r['bunny_video_id'] ?? null;
  $r['playable'] = false;
  $r['embed_url'] = null;
  $r['thumbnail_url'] = null;
  $r['encode_label'] = 'Not uploaded yet';
  if (!$guid) return;

  $r['embed_url'] = 'https://iframe.mediadelivery.net/embed/' . BUNNY_STREAM_LIBRARY_ID . '/' . $guid . '?autoplay=false&preload=false';
  if (defined('BUNNY_STREAM_CDN_HOST') && BUNNY_STREAM_CDN_HOST !== '') {
    $r['thumbnail_url'] = 'https://' . BUNNY_STREAM_CDN_HOST . '/' . $guid . '/' .
      (!empty($map[$guid]['thumbnail_file']) ? $map[$guid]['thumbnail_file'] : 'thumbnail.jpg');
  }

  if (!isset($map[$guid])) {
    // Bunny metadata unavailable — assume playable so review isn't blocked
    $r['playable'] = true;
    $r['encode_label'] = 'Ready';
    return;
  }
  $info = $map[$guid];
  $r = array_merge($r, $info);
  $labels = [0 => 'Queued', 1 => 'Uploaded', 2 => 'Processing', 3 => 'Transcoding',
             4 => 'Ready', 5 => 'Encode failed', 6 => 'Upload failed', 7 => 'Job failed'];
  $s = $info['encode_status'];
  $r['encode_label'] = $labels[$s] ?? ('Status ' . $s);
  if ($s >= 2 && $s <= 3 && $info['encode_progress'] > 0) {
    $r['encode_label'] .= ' ' . $info['encode_progress'] . '%';
  }
  $r['playable'] = ($s === 4) || ($s === 3 && $info['encode_progress'] >= 80);
}

try {
  $me = db_query('SELECT email FROM profiles WHERE id = ?', [$_SESSION['uid']]);
  if (!$me || !in_array(strtolower($me[0]['email']), array_map('strtolower', ADMIN_EMAILS), true)) {
    json_out(['error' => 'forbidden'], 403);
  }

  // Accounts drill-down: every account, its films, and its slot allowance
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accounts'] ?? '') === '1') {
    $filter = $_GET['filter'] ?? 'all';
    $where = $filter === 'creators' ? 'WHERE p.is_creator = 1'
           : ($filter === 'watchers' ? 'WHERE p.is_creator = 0 OR p.is_creator IS NULL' : '');
    $accounts = db_query(
      "SELECT p.id, p.email, p.handle, p.display_name, p.avatar_url, p.created_at,
              COALESCE(p.is_creator,0) AS is_creator,
              COALESCE(p.film_slots,1) AS film_slots,
              COALESCE(p.suspended,0) AS suspended,
              (SELECT COUNT(*) FROM films f WHERE f.creator_id = p.id) AS films_used,
              (SELECT COUNT(*) FROM purchases pu WHERE pu.user_id = p.id) AS purchases,
              (SELECT COALESCE(SUM(pu.amount_cents),0) FROM purchases pu WHERE pu.user_id = p.id) AS spent_cents,
              (SELECT COUNT(*) FROM watch_history w WHERE w.user_id = p.id) AS watched,
              (SELECT COUNT(*) FROM follows fo WHERE fo.creator_id = p.id) AS followers
       FROM profiles p $where ORDER BY p.created_at DESC LIMIT 200"
    );
    $films = db_query(
      "SELECT id, creator_id, title, status, price_cents, views, width, height, size_bytes, bunny_video_id, created_at
       FROM films ORDER BY created_at DESC"
    );
    $map = bunny_video_map();
    $byCreator = [];
    foreach ($films as $f) {
      $f['orientation'] = ((int) $f['height'] > (int) $f['width']) ? 'vertical' : 'horizontal';
      decorate_video($f, $map);
      $byCreator[$f['creator_id']][] = $f;
    }
    foreach ($accounts as &$a) {
      $a['films'] = $byCreator[$a['id']] ?? [];
    }
    json_out(['accounts' => $accounts, 'filter' => $filter]);
  }

  // Backend video dashboard: every film on the platform + where it lives
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['all'] ?? '') === '1') {
    $rows = db_query(
      "SELECT f.id, f.title, f.price_cents, f.status, f.width, f.height, f.size_bytes,
              f.views, f.bunny_video_id, f.created_at, p.handle, p.display_name, p.email
       FROM films f JOIN profiles p ON p.id = f.creator_id
       ORDER BY f.created_at DESC"
    );
    $map = bunny_video_map();
    foreach ($rows as &$r) {
      $r['orientation'] = ((int) $r['height'] > (int) $r['width']) ? 'vertical' : 'horizontal';
      $r['storage'] = $r['bunny_video_id']
        ? 'Bunny Stream library ' . BUNNY_STREAM_LIBRARY_ID . ' · video ' . $r['bunny_video_id']
        : 'not uploaded yet';
      decorate_video($r, $map);
    }
    json_out(['videos' => $rows, 'library_id' => BUNNY_STREAM_LIBRARY_ID]);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db_query(
      "SELECT f.id, f.title, f.synopsis, f.price_cents, f.status, f.width, f.height, f.size_bytes,
              f.bunny_video_id, f.created_at, p.handle, p.display_name, p.email
       FROM films f JOIN profiles p ON p.id = f.creator_id
       WHERE f.status IN ('pending_review', 'pending_upload')
       ORDER BY f.created_at ASC"
    );
    $map = bunny_video_map();
    foreach ($rows as &$r) {
      $r['orientation'] = ((int) $r['height'] > (int) $r['width']) ? 'vertical' : 'horizontal';
      decorate_video($r, $map);
    }
    json_out(['queue' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $b['action'] ?? '';

    // ---- Per-account controls ----
    if (in_array($action, ['set_slots', 'set_creator', 'set_suspended'], true)) {
      $userId = trim((string) ($b['user_id'] ?? ''));
      if ($userId === '') json_out(['error' => 'bad_request'], 400);

      if ($action === 'set_slots') {
        $slots = (int) ($b['film_slots'] ?? 1);
        if ($slots < 1 || $slots > 100) json_out(['error' => 'slots_out_of_range'], 400);
        $used = (int) (db_query('SELECT COUNT(*) AS n FROM films WHERE creator_id = ?', [$userId])[0]['n'] ?? 0);
        if ($slots < $used) json_out(['error' => 'below_current_usage', 'used' => $used], 409);
        db_query('UPDATE profiles SET film_slots = ? WHERE id = ?', [$slots, $userId]);
        json_out(['ok' => true, 'film_slots' => $slots]);
      }
      if ($action === 'set_creator') {
        $v = !empty($b['is_creator']) ? 1 : 0;
        db_query('UPDATE profiles SET is_creator = ? WHERE id = ?', [$v, $userId]);
        json_out(['ok' => true, 'is_creator' => $v]);
      }
      $v = !empty($b['suspended']) ? 1 : 0;
      db_query('UPDATE profiles SET suspended = ? WHERE id = ?', [$v, $userId]);
      json_out(['ok' => true, 'suspended' => $v]);
    }

    // ---- Film approve / reject ----
    $filmId = (int) ($b['film_id'] ?? 0);
    if (!$filmId || !in_array($action, ['approve', 'reject'], true)) json_out(['error' => 'bad_request'], 400);
    $status = $action === 'approve' ? 'approved' : 'rejected';
    db_query('UPDATE films SET status = ? WHERE id = ?', [$status, $filmId]);
    json_out(['ok' => true, 'status' => $status]);
  }

  json_out(['error' => 'method_not_allowed'], 405);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
