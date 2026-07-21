<?php
// Public film catalogue
require_once __DIR__ . '/db.php';

try {
  $rows = db_query(
    "SELECT f.id, f.title, f.synopsis, f.price_cents, f.status, f.views, f.created_at,
            p.handle, p.display_name, p.avatar_url
     FROM films f JOIN profiles p ON p.id = f.creator_id
     WHERE f.status = 'approved'
     ORDER BY f.created_at DESC LIMIT 50"
  );
  json_out(['films' => $rows]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
