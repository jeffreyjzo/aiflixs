<?php
// Review dashboard backend. Only accounts whose email is in ADMIN_EMAILS.
//  GET  -> films awaiting review
//  POST {film_id, action: "approve"|"reject"} -> update status
require_once __DIR__ . '/db.php';
start_session();

if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);

try {
  $me = db_query('SELECT email FROM profiles WHERE id = ?', [$_SESSION['uid']]);
  if (!$me || !in_array(strtolower($me[0]['email']), array_map('strtolower', ADMIN_EMAILS), true)) {
    json_out(['error' => 'forbidden'], 403);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db_query(
      "SELECT f.id, f.title, f.synopsis, f.price_cents, f.status, f.width, f.height, f.size_bytes,
              f.bunny_video_id, f.created_at, p.handle, p.display_name, p.email
       FROM films f JOIN profiles p ON p.id = f.creator_id
       WHERE f.status IN ('pending_review', 'pending_upload')
       ORDER BY f.created_at ASC"
    );
    foreach ($rows as &$r) {
      $r['preview_url'] = $r['bunny_video_id']
        ? 'https://iframe.mediadelivery.net/embed/' . BUNNY_STREAM_LIBRARY_ID . '/' . $r['bunny_video_id']
        : null;
    }
    json_out(['queue' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $filmId = (int) ($b['film_id'] ?? 0);
    $action = $b['action'] ?? '';
    if (!$filmId || !in_array($action, ['approve', 'reject'], true)) json_out(['error' => 'bad_request'], 400);
    $status = $action === 'approve' ? 'approved' : 'rejected';
    db_query('UPDATE films SET status = ? WHERE id = ?', [$status, $filmId]);
    json_out(['ok' => true, 'status' => $status]);
  }

  json_out(['error' => 'method_not_allowed'], 405);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
