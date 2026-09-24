<?php
// Profile management: switch on creator mode, edit profile details.
require_once __DIR__ . '/db.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);
$uid = $_SESSION['uid'];

$b = json_decode(file_get_contents('php://input'), true) ?: [];

try {
  // Become a creator (unlocks the Creator Studio + uploads)
  if (!empty($b['become_creator'])) {
    db_query('UPDATE profiles SET is_creator = 1 WHERE id = ?', [$uid]);
  }

  // Optional profile edits
  if (isset($b['display_name'])) {
    $name = trim($b['display_name']);
    if ($name !== '') db_query('UPDATE profiles SET display_name = ? WHERE id = ?', [mb_substr($name, 0, 60), $uid]);
  }
  if (isset($b['bio'])) {
    db_query('UPDATE profiles SET bio = ? WHERE id = ?', [mb_substr(trim($b['bio']), 0, 160), $uid]);
  }
  if (isset($b['handle'])) {
    $h = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $b['handle']));
    if (strlen($h) < 3) json_out(['error' => 'handle_too_short'], 400);
    $taken = db_query('SELECT id FROM profiles WHERE handle = ? AND id != ?', [$h, $uid]);
    if ($taken) json_out(['error' => 'handle_taken'], 409);
    db_query('UPDATE profiles SET handle = ? WHERE id = ?', [$h, $uid]);
  }

  $rows = db_query('SELECT id, email, handle, display_name, bio, avatar_url, is_creator FROM profiles WHERE id = ?', [$uid]);
  json_out(['ok' => true, 'user' => $rows[0] ?? null]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
