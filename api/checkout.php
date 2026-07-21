<?php
// Pay-per-view checkout via Stripe Checkout.
//  POST {film_id}            -> creates a Checkout Session, returns {url}
//  GET ?verify=1&sid=..&film=.. -> Stripe success redirect: verifies payment
//                                  with Stripe, records the purchase, redirects.
require_once __DIR__ . '/db.php';
start_session();

function stripe_call(string $method, string $path, array $params = []) {
  $ch = curl_init('https://api.stripe.com/v1/' . $path);
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
  ];
  if ($method === 'POST') {
    $opts[CURLOPT_POST] = true;
    $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
  }
  curl_setopt_array($ch, $opts);
  $res = json_decode(curl_exec($ch), true);
  curl_close($ch);
  return $res;
}

// ---- Success-redirect verification ----
if (($_GET['verify'] ?? '') === '1') {
  $sid    = $_GET['sid'] ?? '';
  $filmId = (int) ($_GET['film'] ?? 0);
  if (STRIPE_SECRET_KEY === '' || !$sid || !$filmId || empty($_SESSION['uid'])) {
    header('Location: ' . SITE_URL . '/watch.html?film=' . $filmId . '&purchase=error');
    exit;
  }
  $session = stripe_call('GET', 'checkout/sessions/' . urlencode($sid));
  if (($session['payment_status'] ?? '') === 'paid') {
    try {
      // OR REPLACE: re-purchasing after a 7-day unlock expires refreshes created_at
      db_query(
        'INSERT OR REPLACE INTO purchases (user_id, film_id, amount_cents, stripe_session_id) VALUES (?, ?, ?, ?)',
        [$_SESSION['uid'], $filmId, (int) $session['amount_total'], $sid]
      );
    } catch (Throwable $e) { /* purchase is still valid in Stripe; admin can reconcile */ }
    header('Location: ' . SITE_URL . '/watch.html?film=' . $filmId . '&purchase=success');
  } else {
    header('Location: ' . SITE_URL . '/watch.html?film=' . $filmId . '&purchase=incomplete');
  }
  exit;
}

// ---- Create a Checkout Session ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'method_not_allowed'], 405);
if (empty($_SESSION['uid'])) json_out(['error' => 'not_signed_in'], 401);
if (STRIPE_SECRET_KEY === '') json_out(['error' => 'stripe_not_configured'], 503);

$b = json_decode(file_get_contents('php://input'), true) ?: [];
$filmId = (int) ($b['film_id'] ?? 0);

try {
  $film = db_query("SELECT id, title, price_cents FROM films WHERE id = ? AND price_cents > 0", [$filmId]);
  if (!$film) json_out(['error' => 'film_not_found'], 404);
  $film = $film[0];

  $owned = db_query(
    "SELECT id FROM purchases WHERE user_id = ? AND film_id = ? AND created_at > datetime('now', '-' || ? || ' days')",
    [$_SESSION['uid'], $filmId, UNLOCK_DAYS]
  );
  if ($owned) json_out(['error' => 'already_owned'], 409);

  $session = stripe_call('POST', 'checkout/sessions', [
    'mode' => 'payment',
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => 'Unlock: ' . $film['title'],
    'line_items[0][price_data][unit_amount]' => (int) $film['price_cents'],
    'line_items[0][quantity]' => 1,
    'success_url' => SITE_URL . '/api/checkout.php?verify=1&film=' . $filmId . '&sid={CHECKOUT_SESSION_ID}',
    'cancel_url'  => SITE_URL . '/watch.html?film=' . $filmId . '&purchase=cancelled',
  ]);

  if (empty($session['url'])) {
    json_out(['error' => 'stripe_error', 'detail' => $session['error']['message'] ?? 'unknown'], 502);
  }
  json_out(['url' => $session['url']]);
} catch (Throwable $e) {
  json_out(['error' => 'db_error'], 500);
}
