<?php
// BunnyDB (libSQL over HTTP) query helper
require_once __DIR__ . '/config.php';

/** Run one SQL statement with ? placeholders; returns rows as assoc arrays. */
function db_query(string $sql, array $args = []): array {
  $stmtArgs = array_map(function ($v) {
    if (is_int($v))   return ['type' => 'integer', 'value' => (string) $v];
    if (is_float($v)) return ['type' => 'float', 'value' => $v];
    if (is_null($v))  return ['type' => 'null'];
    return ['type' => 'text', 'value' => (string) $v];
  }, $args);

  $payload = json_encode(['requests' => [
    ['type' => 'execute', 'stmt' => ['sql' => $sql, 'args' => $stmtArgs]],
    ['type' => 'close'],
  ]]);

  $ch = curl_init(BUNNYDB_URL);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . BUNNYDB_TOKEN, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
  ]);
  $raw = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);
  if ($raw === false) throw new RuntimeException('DB connection failed: ' . $err);

  $res = json_decode($raw, true);
  $first = $res['results'][0] ?? null;
  if (!$first || $first['type'] !== 'ok') {
    $msg = $first['error']['message'] ?? 'unknown DB error';
    throw new RuntimeException('DB error: ' . $msg);
  }

  $result = $first['response']['result'];
  $cols = array_map(fn($c) => $c['name'], $result['cols']);
  $rows = [];
  foreach ($result['rows'] as $r) {
    $row = [];
    foreach ($r as $i => $cell) {
      $row[$cols[$i]] = $cell['type'] === 'null' ? null : $cell['value'];
    }
    $rows[] = $row;
  }
  return $rows;
}
