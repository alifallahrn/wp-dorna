<?php
/**
 * Integration tests: they load a real WordPress + WooCommerce site and use it
 * (temporary products and orders, options restored, no network: every HTTP call to
 * Dorna is answered by a fake). Run them on a disposable development site only:
 *
 *   WP_DORNA_TESTS=1 php tests/integration.php [/path/to/wp-load.php]
 */

if (getenv('WP_DORNA_TESTS') !== '1') {
    fwrite(STDERR, "Refusing to run: set WP_DORNA_TESTS=1 (disposable dev site only).\n");
    exit(2);
}

define('WP_USE_THEMES', false);
define('DOING_AJAX', true);
require isset($argv[1]) ? $argv[1] : __DIR__ . '/../../../../wp-load.php';

use WPDorna\Admin\Admin;
use WPDorna\Admin\Ajax;
use WPDorna\Admin\ConnectionNotices;
use WPDorna\Connect\ConnectionService;
use WPDorna\Core\Connection;
use WPDorna\Core\Services;
use WPDorna\Orders\InvoiceState;
use WPDorna\Support\Money;
use WPDorna\Support\Site;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\Status;

$failures = 0;
function check($name, $ok)
{
    global $failures;
    echo ($ok ? "PASS " : "FAIL ") . $name . "\n";
    $failures += $ok ? 0 : 1;
}

// ---- sandbox: logs in a temp dir, options backed up, Dorna faked
$tmp = sys_get_temp_dir() . '/wp-dorna-test-' . uniqid();
add_filter('upload_dir', function ($d) use ($tmp) { return array_merge($d, array('basedir' => $tmp)); });

$option_keys = array(WP_DORNA_OPTION_NAME, Status::OPTION, Status::LAST_UPDATE_OPTION, FullSync::STATE_OPTION, FullSync::SEEN_OPTION, FullSync::REPORT_OPTION,
    'wp_dorna_lock_products', 'wp_dorna_lock_retry', 'wp_dorna_lock_full_sync', Connection::OPTION, 'wp_dorna_wizard');
$backup = array();
foreach ($option_keys as $key) { $backup[$key] = get_option($key, '__none__'); }

remove_all_actions('woocommerce_order_status_processing');
remove_all_actions('woocommerce_order_status_completed');

$requests = array();
$fake     = function ($method, $url, $body) { return array(404, array()); };
add_filter('pre_http_request', function ($pre, $args, $url) use (&$requests, &$fake) {
    $method = $args['method'] ?? 'GET';
    $requests[] = array('method' => $method, 'url' => $url, 'body' => isset($args['body']) ? json_decode($args['body'], true) : null, 'timeout' => $args['timeout'], 'headers' => isset($args['headers']) ? $args['headers'] : array());
    list($code, $body) = call_user_func($fake, $method, $url, isset($args['body']) ? json_decode($args['body'], true) : null);

    return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => $code, 'message' => ''), 'cookies' => array(), 'filename' => null);
}, 10, 3);

update_option(WP_DORNA_OPTION_NAME, array('api_key' => 'test-token-123456'));
update_option(Status::LAST_UPDATE_OPTION, '2026-10-01T00:00:00+03:30');
delete_option(FullSync::STATE_OPTION);

$services = new Services();
$cleanup  = array();

try {
    $currency = get_woocommerce_currency();

    // ---------------------------------------------------------------- settings
    check('settings: key is masked', $services->settings->masked_key() === '••••••••3456');
    $services->settings->save('', true);
    check('settings: empty token keeps the stored one', $services->settings->api_key() === 'test-token-123456');
    $services->settings->save('new-token-abcdef', false);
    check('settings: new token replaces it', $services->settings->api_key() === 'new-token-abcdef' && !$services->settings->remove_data_on_uninstall());
    $services->settings->save('test-token-123456', false);

    // ----------------------------------------------------------------- logger
    $services->logger->error('boom', array('auth' => 'Bearer test-token-123456'));
    $lines = $services->logger->tail(wp_date('Y-m-d'));
    check('logger: written and token redacted', count($lines) === 1 && strpos($lines[0], 'test-token-123456') === false && strpos($lines[0], '***') !== false);
    check('logger: tail rejects unknown / traversal dates', $services->logger->tail('../../wp-config') === array() && $services->logger->tail('2001-01-01') === array());
    check('logger: directory closed to the web', file_exists($services->logger->directory() . '.htaccess'));

    // ------------------------------------------------------------ locks / cron
    check('lock: second acquire refused, release frees it', $services->lock->acquire('t', 60) && !$services->lock->acquire('t', 60) && !$services->lock->release('t') && $services->lock->acquire('t', 60));
    $services->lock->release('t');
    foreach (array('wp_dorna_update_products_event', 'wp_dorna_retry_failed_orders_event', 'wp_dorna_purge_logs_event') as $hook) {
        check('cron scheduled: ' . $hook, (bool) wp_next_scheduled($hook));
    }

    // ------------------------------------------------------- incremental sync
    $product = new WC_Product_Simple();
    $product->set_name('ZZ temp');
    $product->set_sku('ZZ-Test-1');
    $product->set_regular_price(1);
    $product->set_manage_stock(true);
    $product->set_stock_quantity(1);
    $product->save();
    $cleanup[] = function () use ($product) { $product->delete(true); };

    $page = function ($data, $current = 1, $last = 1, $time = '2026-11-01T12:00:00+03:30') {
        return array('current_page' => $current, 'last_page' => $last, 'total' => 3, 'server_time' => $time, 'data' => $data);
    };
    $item = array('sku' => ' zz-test-1 ', 'stock' => 7, 'sale_price' => 20000, 'original_price' => 20000, 'is_active' => true);

    $fake = function ($m, $u) { return array(500, array()); };
    $requests = array();
    $services->incremental->run();
    check('incremental: error does not advance the synced time', Status::synced_until() === '2026-10-01T00:00:00+03:30');
    check('incremental: GET retried 3 times with a 30s timeout', count($requests) === 3 && $requests[0]['timeout'] === 30);
    check('incremental: since has the overlap, include_inactive asked', strpos($requests[0]['url'], 'since=2026-09-30T23%3A55%3A00%2B03%3A30') !== false && strpos($requests[0]['url'], 'include_inactive=1') !== false);
    check('incremental: error recorded in friendly words', strpos((string) Status::get()['last_error'], 'درنا') !== false);

    $fake = function ($m, $u) use ($page, $item) {
        return strpos($u, 'page=2') !== false ? array(502, array()) : array(200, $page(array($item), 1, 2));
    };
    $services->incremental->run();
    check('incremental: failing page 2 does not advance the time', Status::synced_until() === '2026-10-01T00:00:00+03:30');

    $fake = function ($m, $u) use ($page, $item) { return array(200, $page(array($item))); };
    $services->incremental->run();
    check('incremental: success stores Dorna server_time', Status::synced_until() === '2026-11-01T12:00:00+03:30');
    check('incremental: stock + price written via normalized SKU', (int) wc_get_product($product->get_id())->get_stock_quantity() === 7);
    $stats = Status::get()['last_stats'];
    check('incremental: stats saved', $stats['updated'] === 1 && $stats['failed'] === 0);

    $services->incremental->run();
    check('incremental: same data again is "unchanged"', Status::get()['last_stats']['unchanged'] === 1 && Status::get()['last_stats']['updated'] === 0);

    // ------------------------------------------- stock held for unsent orders
    $order = wc_create_order(array('status' => 'processing'));
    $order->add_product(wc_get_product($product->get_id()), 2);
    $order->calculate_totals();
    $order->save();
    $cleanup[] = function () use ($order) { $order->delete(true); };

    $fake = function ($m, $u) use ($page, $item) { return array(200, $page(array(array_merge($item, array('stock' => 99))))); };
    $services->incremental->run();
    check('stock held: Dorna stock ignored while the order has no invoice in Dorna', (int) wc_get_product($product->get_id())->get_stock_quantity() !== 99
        && Status::get()['last_stats']['stock_held'] === 1);

    // ---------------------------------------------------------------- invoices
    $body_of = function () use (&$requests) { return $requests[count($requests) - 1]['body']; };
    $reset = function ($id) {
        $fresh = wc_get_order($id);
        foreach (array(InvoiceState::SENT, InvoiceState::INVOICE_ID, InvoiceState::ERROR, InvoiceState::ATTEMPTS, InvoiceState::LAST_ATTEMPT) as $key) { $fresh->delete_meta_data($key); }
        $fresh->save();
    };

    $requests = array();
    $fake = function ($m, $u, $b) { return array(200, array('invoice_id' => 77)); };
    $result = $services->invoices->send($order->get_id());
    $sent   = wc_get_order($order->get_id());
    check('invoice: sent and stored', $result === true && $sent->get_meta(InvoiceState::SENT) && $sent->get_meta(InvoiceState::INVOICE_ID) == 77);
    $payload = $body_of();
    check('invoice: payload has order id, SKU and converted amounts', $payload['order_id'] === $order->get_id() && $payload['items'][0]['sku'] === 'ZZ-Test-1'
        && $payload['total'] == Money::to_dorna($order->get_total(), $currency));
    check('invoice: no customer data written to the log', strpos(implode("\n", $services->logger->tail(wp_date('Y-m-d'))), (string) $order->get_billing_email()) === false || $order->get_billing_email() === '');

    $count = count($requests);
    $services->invoices->send($order->get_id());
    check('invoice: an already sent order makes no request', count($requests) === $count);

    $reset($order->get_id());

    $fake = function ($m, $u, $b) { return array(400, array('error' => 'Sale with this order ID already exists')); };
    check('invoice: old-Dorna 400 "already exists" counts as sent', $services->invoices->send($order->get_id()) === true && wc_get_order($order->get_id())->get_meta(InvoiceState::SENT));

    $reset($order->get_id());
    $fake = function ($m, $u, $b) { return array(200, array('message' => 'exists', 'invoice_id' => 55, 'duplicate' => true)); };
    check('invoice: duplicate answer keeps the existing invoice id', $services->invoices->send($order->get_id()) === true && wc_get_order($order->get_id())->get_meta(InvoiceState::INVOICE_ID) == 55);

    $reset($order->get_id());
    $fake = function ($m, $u, $b) { return array(422, array('errors' => array('items.0.sku' => array('The selected items.0.sku is invalid.')))); };
    $result = $services->invoices->send($order->get_id());
    $state  = InvoiceState::describe(wc_get_order($order->get_id()));
    check('invoice: 422 is an error, reason + attempt stored, not marked sent', is_wp_error($result) && !$state['sent'] && $state['attempts'] === 1 && strpos($state['error'], 'items.0.sku') !== false);

    $fake = function ($m, $u, $b) { return array(401, array('message' => 'Unauthenticated.')); };
    $services->invoices->send($order->get_id());
    check('invoice: 401 shows a Persian message, not the raw English one', strpos(InvoiceState::describe(wc_get_order($order->get_id()))['error'], 'Unauthenticated') === false);

    $nosku = new WC_Product_Simple();
    $nosku->set_name('No SKU');
    $nosku->set_regular_price(5);
    $nosku->save();
    $cleanup[] = function () use ($nosku) { $nosku->delete(true); };
    $order2 = wc_create_order(array('status' => 'processing'));
    $order2->add_product(wc_get_product($nosku->get_id()), 1);
    $order2->calculate_totals();
    $order2->save();
    $cleanup[] = function () use ($order2) { $order2->delete(true); };
    $requests = array();
    $services->invoices->send($order2->get_id());
    check('invoice: item without SKU is refused before any request', !$requests && strpos(InvoiceState::describe(wc_get_order($order2->get_id()))['error'], 'SKU') !== false);

    // -------------------------------------------------------------- retry gate
    $fresh = wc_get_order($order->get_id());
    $fresh->update_meta_data(InvoiceState::ATTEMPTS, 1);
    $fresh->update_meta_data(InvoiceState::LAST_ATTEMPT, time() - 60);
    check('retry: not due 1 min after the first failure', !\WPDorna\Orders\RetryService::is_due($fresh));
    $fresh->update_meta_data(InvoiceState::LAST_ATTEMPT, time() - 400);
    check('retry: due after 5 minutes', \WPDorna\Orders\RetryService::is_due($fresh));

    // --------------------------------------------------------------- full sync
    $fs = $services->full_sync;
    $fake = function ($m, $u) use ($page, $item) {
        if (strpos($u, 'page=2') !== false) {
            return array(200, $page(array(array('sku' => 'ZZ-NOT-IN-STORE', 'stock' => 9, 'sale_price' => 5000, 'is_active' => true)), 2, 2, '2026-11-01T13:00:00+03:30'));
        }
        return array(200, $page(array($item, array('sku' => 'ZZ-INACTIVE', 'stock' => 1, 'sale_price' => 1, 'is_active' => false)), 1, 2));
    };
    check('full sync: start', $fs->start()['status'] === 'running' && is_wp_error($fs->start()));
    check('full sync: minute sync yields while it runs', (function () use ($services, &$requests) { $n = count($requests); $services->incremental->run(); return count($requests) === $n; })());

    $state = $fs->tick(1, false);
    check('full sync: page 1 done, moved to page 2', $state['page'] === 2 && $state['checked'] === 2);
    $n = count($requests);
    $fs->tick(1, false);
    check('full sync: pacing blocks an immediate second call', count($requests) === $n);

    $saved = get_option(FullSync::STATE_OPTION); $saved['next_allowed_at'] = 0; update_option(FullSync::STATE_OPTION, $saved);
    $good = $fake; $fake = function ($m, $u) { return array(503, array()); };
    $state = $fs->tick(1, false);
    $saved = get_option(FullSync::STATE_OPTION);
    check('full sync: error keeps the page and pauses with backoff', $state['page'] === 2 && $state['consecutive_errors'] === 1 && $saved['next_allowed_at'] - microtime(true) > 25);

    $fs->pause();
    $n = count($requests);
    $fs->tick(1, false);
    check('full sync: paused makes no request', count($requests) === $n && FullSync::get_state()['status'] === 'paused');
    check('full sync: resume', $fs->resume()['status'] === 'running');
    $fake = $good;
    $state = $fs->tick(1, false);
    check('full sync: completed and counted', $state['status'] === 'completed' && $state['checked'] === 3 && $state['updated'] <= 1 && $state['missing'] === 1);
    check('full sync: minute sync restarts from when the pass began', Status::synced_until() === '2026-11-01T12:00:00+03:30');
    check('full sync: no cron left, report ready', !wp_next_scheduled(FullSync::CRON_HOOK) && is_array($fs->payload()['not_in_dorna_skus']) && $fs->payload()['percent'] === 100);
    $fs->start();
    check('full sync: cancel', $fs->cancel()['status'] === 'cancelled' && !wp_next_scheduled(FullSync::CRON_HOOK));

    // ------------------------------------------------------------------ import
    $fake = function ($m, $u) use ($page) {
        return array(200, $page(array(
            array('sku' => 'ZZ-Test-1', 'name' => 'exists already', 'stock' => 1, 'sale_price' => 100, 'is_active' => true),
            array('sku' => 'zz-imp-9', 'name' => 'ZZ imported', 'stock' => 4, 'sale_price' => 2500, 'original_price' => 2500, 'is_active' => true),
        )));
    };
    wp_set_current_user(get_users(array('role' => 'administrator', 'number' => 1))[0]->ID);
    $scan = $services->importer->scan_page(1);
    check('import: scan queues only products the store lacks', $scan['found'] === 1 && $scan['queued'] === 1);
    check('import: unknown SKU is refused (browser supplies no data)', is_wp_error($services->importer->import('NOT-QUEUED')));
    $name = $services->importer->import('ZZ-IMP-9');
    $imported = wc_get_product(wc_get_product_id_by_sku('zz-imp-9'));
    check('import: created as a draft with Dorna stock', $name === 'ZZ imported' && $imported && $imported->get_status() === 'draft' && (int) $imported->get_stock_quantity() === 4);
    $imported && $cleanup[] = function () use ($imported) { $imported->delete(true); };
    check('import: a second import of the same SKU is refused', is_wp_error($services->importer->import('zz-imp-9')));


    // ------------------------------------------------- connection state / headers
    $conn = $services->connection;
    $conn->clear();
    check('connection: a pasted token counts as manual', $conn->status() === Connection::MANUAL && $conn->is_usable() && !$conn->is_linked());

    $requests = array();
    $fake = function ($m, $u) { return array(200, array('data' => array())); };
    $services->client->get_data('products-list');
    $sent_headers = $requests[0]['headers'];
    check('client: every request names the site (X-Dorna-Site) and the plugin version', $sent_headers['X-Dorna-Site'] === Site::origin() && $sent_headers['X-Dorna-Plugin'] === WP_DORNA_VERSION && strpos($sent_headers['Authorization'], 'Bearer ') === 0);
    check('client: the site header is an origin, never a path', strpos(parse_url($sent_headers['X-Dorna-Site'], PHP_URL_PATH) ?: '', '/') === false);

    $fake = function ($m, $u) { return array(401, array('message' => 'Unauthenticated.')); };
    $services->client->get_data('products-list');
    check('client: a 401 marks the connection rejected', $conn->status() === Connection::REJECTED && $conn->needs_attention() && !$conn->is_usable());

    $fake = function ($m, $u) { return array(200, array('data' => array())); };
    $services->client->get_data('products-list');
    check('client: a good answer clears the rejection', $conn->status() === Connection::MANUAL);

    $fake = function ($m, $u) { return array(403, array('error' => 'site_mismatch', 'message' => 'x')); };
    $err = $services->client->get_data('products-list');
    check('client: 403 site_mismatch marks it mismatched, in Persian, without retries', $conn->status() === Connection::MISMATCH && is_wp_error($err) && strpos($err->get_error_message(), 'آدرس دیگری') !== false);
    $fake = function ($m, $u) { return array(403, array('error' => 'token_not_connected')); };
    $services->client->get_data('products-list');
    check('client: 403 token_not_connected means rejected', $conn->status() === Connection::REJECTED);
    $conn->clear();

    // ---------------------------------------------------------- connect flow
    $url = $services->connect->authorize_url();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    check('connect: authorize URL points at Dorna with site, return, state and challenge', strpos($url, ConnectionService::app_url() . 'connect/woocommerce?') === 0
        && $q['site'] === Site::origin() && $q['return'] === ConnectionService::return_url() && strlen($q['challenge']) === 43 && strlen($q['state']) >= 16 && !isset($q['token']));
    $pending = get_transient('wp_dorna_connect_' . $q['state']);
    check('connect: the verifier is kept server-side and matches the challenge (PKCE)', is_array($pending)
        && rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '=') === $q['challenge']);
    delete_transient('wp_dorna_connect_' . $q['state']);

    $services->settings->clear_api_key();
    $requests = array();
    $fake = function ($m, $u, $b) {
        if (strpos($u, 'connect/exchange') !== false) {
            return array(200, array('token' => '7|brand-new-token-0123456789', 'business' => array('name' => 'فروشگاه نمونه', 'username' => 'demo'), 'site' => Site::origin(), 'connected_at' => gmdate('c')));
        }
        return array(200, array('ok' => true));
    };
    $result = $services->connect->connect(str_repeat('a', 48), 'verifier-value');
    $exchange = array_values(array_filter($requests, function ($r) { return strpos($r['url'], 'connect/exchange') !== false; }))[0];
    check('connect: the exchange sends the code, the verifier and the site, with no token', $exchange['body']['code'] === str_repeat('a', 48) && $exchange['body']['verifier'] === 'verifier-value'
        && $exchange['body']['site'] === Site::origin() && !isset($exchange['headers']['Authorization']));
    check('connect: token saved, status connected, business remembered', $services->settings->api_key() === '7|brand-new-token-0123456789' && $conn->status() === Connection::CONNECTED
        && $conn->is_linked() && $conn->data()['business_name'] === 'فروشگاه نمونه' && $conn->data()['business_username'] === 'demo');
    check('connect: a heartbeat goes out right away so Dorna shows the site at once', count(array_filter($requests, function ($r) { return strpos($r['url'], 'connect/heartbeat') !== false; })) === 1);

    $before = $services->settings->api_key();
    check('connect: a malformed code is refused without calling Dorna', is_wp_error($services->connect->connect('short', 'v')) && $services->settings->api_key() === $before);
    $fake = function ($m, $u) { return array(422, array('error' => 'invalid_code')); };
    $bad = $services->connect->connect(str_repeat('b', 48), 'v');
    check('connect: an expired code gives a clear Persian message and keeps the old token', is_wp_error($bad) && strpos($bad->get_error_message(), 'منقضی') !== false && $services->settings->api_key() === $before);

    // ------------------------------------------------------------- heartbeat
    $payload = $services->heartbeat->payload();
    check('heartbeat: carries versions, sync, full sync, orders and schedule', isset($payload['plugin_version'], $payload['wp_version'], $payload['product_sync']['last_success'], $payload['full_sync']['status'], $payload['orders']['unsent'], $payload['schedule']['mode']));
    check('heartbeat: no customer, order or product data in it', strpos(wp_json_encode($payload), '@') === false && !array_key_exists('items', $payload) && !array_key_exists('customer', $payload));
    $requests = array();
    $fake = function ($m, $u) { return array(200, array('ok' => true)); };
    check('heartbeat: sent as a health report', $services->heartbeat->send() === true && $requests[0]['body']['health']['plugin_version'] === WP_DORNA_VERSION);
    $conn->mark(Connection::REJECTED);
    $requests = array();
    check('heartbeat: nothing is sent while Dorna refuses the token', $services->heartbeat->send() === null && !$requests);

    // ------------------------------------------------------------ disconnect
    $conn->mark_ok();
    $requests = array();
    $fake = function ($m, $u) { return array(200, array('ok' => true)); };
    $services->connect->disconnect();
    check('disconnect: Dorna is told, the token and the connection are forgotten', count(array_filter($requests, function ($r) { return strpos($r['url'], 'connect/disconnect') !== false; })) === 1
        && !$services->settings->has_key() && $conn->status() === Connection::NONE);

    // -------------------------------------------------------- admin notices
    $services->settings->set_api_key('test-token-123456');
    $conn->mark(Connection::MISMATCH);
    wp_set_current_user(get_users(array('role' => 'administrator', 'number' => 1))[0]->ID);
    $notices = new ConnectionNotices($services, new Admin($services));
    ob_start();
    $notices->render();
    $html = ob_get_clean();
    check('notice: a mismatched connection is announced on admin screens', strpos($html, 'notice-error') !== false && strpos($html, 'کپی') !== false && strpos($html, 'wp-dorna-settings') !== false);
    $conn->clear();
    ob_start();
    $notices->render();
    check('notice: nothing is shown when the connection is fine', ob_get_clean() === '');

    // ---------------------------------------------------------------- wizard
    delete_option('wp_dorna_wizard');
    check('wizard: an install that existed before the wizard counts as set up', $services->wizard->finished());
    $services->settings->clear_api_key();
    $services->wizard->start_if_new();
    check('wizard: a fresh install without a token gets the checklist', !$services->wizard->finished());
    check('wizard: required steps pending, optional ones are optional', !$services->wizard->ready() && count(array_filter($services->wizard->steps(), function ($step) { return $step['optional']; })) === 2);
    $services->settings->set_api_key('test-token-123456');
    $services->wizard->mark('import');
    $services->wizard->mark('bogus');
    $done_keys = array_column(array_filter($services->wizard->steps(), function ($step) { return $step['done']; }), 'key');
    check('wizard: connect and import are done, an unknown step is ignored', in_array('connect', $done_keys, true) && in_array('import', $done_keys, true) && !in_array('bogus', $done_keys, true));
    $services->wizard->finish();
    check('wizard: finish and reopen', $services->wizard->finished() && ($services->wizard->reopen() || true) && !$services->wizard->finished());

    // -------------------------------------------------------------- order stats
    $stats = \WPDorna\Orders\OrderStats::counts();
    check('order stats: counts shaped for the overview and the heartbeat', isset($stats['sent'], $stats['unsent'], $stats['max_attempts']) && array_key_exists('oldest_unsent_at', $stats));
    delete_transient(\WPDorna\Orders\OrderStats::CACHE_KEY);

    // ------------------------------------------------ scheduled full sync
    $sched = $services->scheduled;
    $tz    = wp_timezone();
    $now   = new DateTimeImmutable('2026-11-02 03:20:00', $tz);
    $conn->clear();
    $services->settings->save_schedule(array('mode' => 'off'));
    check('schedule: off does nothing', $sched->run($now) === 'idle');

    $services->settings->save_schedule(array('mode' => 'nightly', 'hour' => 3, 'next_due' => ''));
    $fs->cancel();
    delete_option(FullSync::STATE_OPTION);
    $conn->set_manual();
    check('schedule: first run only plans the next slot', $sched->run($now) === 'not_due' && $services->settings->schedule()['next_due'] !== '');

    $services->settings->save_schedule(array('next_due' => '2026-11-02T03:00:00+03:30'));
    $fake = function ($m, $u) { return array(200, array('current_page' => 1, 'last_page' => 1, 'total' => 0, 'data' => array())); };
    check('schedule: due and inside the night window starts a scheduled full sync', $sched->run($now) === 'started'
        && FullSync::get_state()['status'] === 'running' && FullSync::get_state()['trigger'] === 'scheduled');
    $next = new DateTimeImmutable($services->settings->schedule()['next_due']);
    check('schedule: the next slot moves to tomorrow 03:00', $next->format('Y-m-d H:i') === '2026-11-03 03:00');
    check('schedule: nothing is due right after', $sched->run($now) === 'not_due');
    $fs->cancel();

    $services->settings->save_schedule(array('next_due' => '2026-11-02T03:00:00+03:30'));
    check('schedule: a pass that began less than 12h ago is not repeated', $sched->run($now) === 'recent' || $sched->run($now) === 'not_due');

    delete_option(FullSync::STATE_OPTION);
    $services->settings->save_schedule(array('next_due' => '2026-11-01T20:00:00+03:30'));
    check('schedule: a due time missed by more than 6h is skipped, not started by day', $sched->run($now) === 'missed' && FullSync::get_state()['status'] === 'idle');

    $services->settings->save_schedule(array('next_due' => '2026-11-02T03:00:00+03:30'));
    $conn->mark(Connection::REJECTED);
    check('schedule: no full sync while Dorna refuses the token', $sched->run($now) === 'no_connection');
    $conn->clear();
    $services->settings->save_schedule(array('mode' => 'off', 'next_due' => ''));

    // ------------------------------------------------- ajax: nonce + capability
    $ajax = new Ajax($services);
    add_filter('wp_die_ajax_handler', function () { return function ($message) { throw new RuntimeException('die'); }; });
    $call = function ($method, $user_id, $nonce, array $extra = array()) use ($ajax) {
        wp_set_current_user($user_id);
        $_POST = array_merge(array('nonce' => $nonce, 'order_id' => 0), $extra);
        $_REQUEST = $_POST;
        ob_start();
        try { $ajax->$method(); } catch (RuntimeException $e) { }
        $out = json_decode(ob_get_clean(), true);

        return $out;
    };
    $admin_id = get_users(array('role' => 'administrator', 'number' => 1))[0]->ID;
    $subscriber = wp_insert_user(array('user_login' => 'zz_sub_' . uniqid(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber'));
    $cleanup[] = function () use ($subscriber) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($subscriber); };

    check('ajax: no nonce is refused', $call('send_order', $admin_id, '')['success'] === false);
    $good_nonce = wp_create_nonce(Ajax::NONCE);
    check('ajax: a subscriber with a valid nonce is refused', $call('send_order', $subscriber, wp_create_nonce(Ajax::NONCE))['success'] === false);
    check('ajax: settings-level action needs manage_options', $call('clear_logs', $subscriber, wp_create_nonce(Ajax::NONCE))['success'] === false);
    wp_set_current_user($admin_id);
    $good_nonce = wp_create_nonce(Ajax::NONCE);
    $fake = function () { return array(404, array('error' => 'Product not found')); };
    $ok = $call('test_connection', $admin_id, $good_nonce);
    check('ajax: admin with nonce passes (connection test says OK on 404 = valid token)', $ok['success'] === true);
    $fake = function () { return array(401, array()); };
    $bad = $call('test_connection', $admin_id, wp_create_nonce(Ajax::NONCE));
    $_POST = array();
    wp_set_current_user($admin_id);
    $sched_ok = $call('save_schedule', $admin_id, wp_create_nonce(Ajax::NONCE), array('mode' => 'weekly', 'hour' => '4', 'weekday' => '6'));
    $saved = $services->settings->schedule();
    check('ajax: schedule saved with a next run', $sched_ok['success'] === true && $saved['mode'] === 'weekly' && $saved['hour'] === 4 && $saved['weekday'] === 6 && $saved['next_due'] !== '');
    check('ajax: an unknown schedule mode is refused', $call('save_schedule', $admin_id, wp_create_nonce(Ajax::NONCE), array('mode' => 'hourly'))['success'] === false);
    check('ajax: a subscriber cannot change the schedule', $call('save_schedule', $subscriber, wp_create_nonce(Ajax::NONCE), array('mode' => 'nightly'))['success'] === false);
    check('ajax: wizard step needs a nonce', $call('wizard_step', $admin_id, '', array('step' => 'import'))['success'] === false);
    $services->settings->save_schedule(array('mode' => 'off', 'next_due' => ''));
    check('ajax: rejected token is reported in Persian', $bad['success'] === false && strpos($bad['data']['message'], 'توکن') !== false);
} finally {
    foreach (array_reverse($cleanup) as $undo) { try { $undo(); } catch (Throwable $e) { } }
    foreach ($backup as $key => $value) { $value === '__none__' ? delete_option($key) : update_option($key, $value); }
    foreach (array('wp_dorna_import_' . get_current_user_id()) as $t) { delete_transient($t); }
    foreach ((array) glob($tmp . '/wp-dorna-logs/{*,.htaccess}', GLOB_BRACE) as $f) { @unlink($f); }
    @rmdir($tmp . '/wp-dorna-logs');
    @rmdir($tmp);
}

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit($failures ? 1 : 0);
