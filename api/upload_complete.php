<?php
// Marks the creator's film as uploaded -> queued for review.
require_once __DIR__ . '/db.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);

try {
  db_query("UPDATE films SET status = 'pending_review' WHERE creator_id = ? AND status = 'pending_upload'", [$_SESSION['uid']]);
  json_out(['ok' => true, 'status' => 'pending_review']);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
