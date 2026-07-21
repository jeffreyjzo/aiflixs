<?php
// Google sign-in: the browser sends a Google ID token, we verify it with
// Google, then create/load the user's profile and start a session.
require_once __DIR__ . '/db.php';
start_session();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  if (empty($_SESSION['uid'])) json_out(['user' => null]);
  try {
    $rows = db_query('SELECT id, email, handle, display_name, avatar_url, is_creator FROM profiles WHERE id = ?', [$_SESSION['uid']]);
    json_out(['user' => $rows[0] ?? null]);
  } catch (Throwable $e) {
    json_out(['user' => null, 'error' => 'db_unavailable'], 500);
  }
}

if ($method === 'DELETE') {
  session_destroy();
  json_out(['ok' => true]);
}

if ($method !== 'POST') json_out(['error' => 'method_not_allowed'], 405);

if (GOOGLE_CLIENT_ID === '') json_out(['error' => 'google_not_configured'], 503);

$body = json_decode(file_get_contents('php://input'), true);
$idToken = $body['credential'] ?? '';
if (!$idToken) json_out(['error' => 'missing_credential'], 400);

// Verify the ID token with Google
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$raw = curl_exec($ch);
curl_close($ch);
$info = json_decode($raw, true);

if (!$info || ($info['aud'] ?? '') !== GOOGLE_CLIENT_ID || ($info['email_verified'] ?? '') !== 'true') {
  json_out(['error' => 'invalid_token'], 401);
}

$uid   = $info['sub'];
$email = $info['email'];
$name  = $info['name'] ?? explode('@', $email)[0];
$pic   = $info['picture'] ?? null;

try {
  $existing = db_query('SELECT id FROM profiles WHERE id = ?', [$uid]);
  if (!$existing) {
    $handle = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0])) . substr($uid, -4);
    db_query('INSERT INTO profiles (id, email, handle, display_name, avatar_url) VALUES (?, ?, ?, ?, ?)',
      [$uid, $email, $handle, $name, $pic]);
  }
  $_SESSION['uid'] = $uid;
  $rows = db_query('SELECT id, email, handle, display_name, avatar_url, is_creator FROM profiles WHERE id = ?', [$uid]);
  json_out(['user' => $rows[0]]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
