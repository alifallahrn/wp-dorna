<?php

/**
 * One slow pass over every product Dorna has, applying price and stock to the
 * matching WooCommerce products. Meant to heal any drift the incremental sync
 * could have missed, without putting load on Dorna:
 *
 * - one request (100 products) at a time, DEFAULT_DELAY seconds apart;
 * - backoff after an error, and a stop after MAX_CONSECUTIVE_ERRORS;
 * - state lives in an option, so it survives closing the browser, pausing or a
 *   server restart. WP-Cron drives it in the background, and the open admin
 *   page speeds it up (the lock keeps the two from overlapping).
 */
class WP_Dorna_Full_Sync
{
    const STATE_OPTION  = 'wp_dorna_full_sync';
    const SEEN_OPTION   = 'wp_dorna_full_sync_seen';
    const REPORT_OPTION = 'wp_dorna_full_sync_report';
    const CRON_HOOK     = 'wp_dorna_full_sync_tick';
    const LOCK          = 'full_sync';

    const DEFAULT_DELAY         = 3;
    const CRON_BUDGET           = 40;
    const CRON_PAGES            = 12;
    const MAX_CONSECUTIVE_ERRORS = 5;
    const MAX_PAGES             = 2000;
    const REPORT_LIMIT          = 500;

    private $core;

    public function __construct()
    {
        $this->core = new WP_Dorna();
    }

    public function init()
    {
        add_action(self::CRON_HOOK, array($this, 'cron_tick'));
        add_action('wp_ajax_wp_dorna_full_sync', array($this, 'ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Survives deactivate / reactivate while a run is in progress.
        if (self::is_running()) {
            $this->schedule_cron();
        }
    }

    public static function defaults()
    {
        return array(
            'status'             => 'idle', // idle | running | paused | completed | cancelled | failed
            'run_id'             => '',
            'started_at'         => null,
            'finished_at'        => null,
            'server_time'        => null,
            'page'               => 1,
            'last_page'          => 0,
            'total'              => 0,
            'checked'            => 0,
            'updated'            => 0,
            'unchanged'          => 0,
            'failed'             => 0,
            'stock_held'         => 0,
            'inactive'           => 0,
            'missing'            => 0,
            'not_in_dorna'       => 0,
            'errors'             => array(),
            'last_error'         => null,
            'consecutive_errors' => 0,
            'next_allowed_at'    => 0,
        );
    }

    public static function get_state()
    {
        $state = get_option(self::STATE_OPTION, array());

        return array_merge(self::defaults(), is_array($state) ? $state : array());
    }

    public static function is_running()
    {
        $state = get_option(self::STATE_OPTION, array());

        return is_array($state) && isset($state['status']) && $state['status'] === 'running';
    }

    private function save_state(array $state)
    {
        update_option(self::STATE_OPTION, $state, true);
    }

    private function delay()
    {
        return max(1, (int) apply_filters('wp_dorna_full_sync_delay', self::DEFAULT_DELAY));
    }

    private function schedule_cron()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + 30, self::CRON_HOOK);
        }
    }

    // ---------------------------------------------------------------- control

    public function start()
    {
        $state = self::get_state();

        if ($state['status'] === 'running') {
            return new WP_Error('running', 'سینک کامل در حال اجرا است.');
        }

        if ($state['status'] === 'paused') {
            return new WP_Error('paused', 'یک سینک کامل متوقف‌شده وجود دارد؛ آن را ادامه دهید یا لغو کنید.');
        }

        $state = array_merge(self::defaults(), array(
            'status'     => 'running',
            'run_id'     => wp_generate_password(8, false),
            'started_at' => (new DateTimeImmutable('now', wp_timezone()))->format(DATE_ATOM),
        ));

        delete_option(self::SEEN_OPTION);
        delete_option(self::REPORT_OPTION);

        $this->save_state($state);
        $this->schedule_cron();
        WP_Dorna::write_log('WP Dorna full sync started (run ' . $state['run_id'] . ').');

        return $state;
    }

    public function pause()
    {
        return $this->transition(array('running'), 'paused');
    }

    public function resume()
    {
        $state = self::get_state();

        if (!in_array($state['status'], array('paused', 'failed'), true)) {
            return $state;
        }

        $state['status']             = 'running';
        $state['consecutive_errors'] = 0;
        $state['next_allowed_at']    = 0;
        $this->save_state($state);
        $this->schedule_cron();

        return $state;
    }

    public function cancel()
    {
        $state = $this->transition(array('running', 'paused', 'failed'), 'cancelled');

        delete_option(self::SEEN_OPTION);
        wp_clear_scheduled_hook(self::CRON_HOOK);

        return $state;
    }

    private function transition(array $from, $to)
    {
        $state = self::get_state();

        if (in_array($state['status'], $from, true)) {
            $state['status'] = $to;
            if ($to === 'cancelled') {
                $state['finished_at'] = (new DateTimeImmutable('now', wp_timezone()))->format(DATE_ATOM);
            }
            $this->save_state($state);
        }

        return $state;
    }

    // ------------------------------------------------------------------ ticks

    public function cron_tick()
    {
        $state = $this->tick(self::CRON_PAGES, true, self::CRON_BUDGET);

        if ($state['status'] === 'running') {
            $this->schedule_cron();
        }
    }

    /**
     * @param int  $max_pages  pages to process at most in this call
     * @param bool $may_sleep  wait for the pacing delay (cron) or return at once (browser)
     * @param int  $budget     seconds after which no new page is started
     */
    public function tick($max_pages = 1, $may_sleep = false, $budget = 20)
    {
        if (!self::is_running()) {
            return self::get_state();
        }

        if (!$this->core->acquire_lock(self::LOCK, 5 * MINUTE_IN_SECONDS)) {
            return self::get_state();
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $began = microtime(true);

        try {
            for ($done = 0; $done < $max_pages; $done++) {
                $state = self::get_state();
                if ($state['status'] !== 'running') {
                    break;
                }

                $wait = $state['next_allowed_at'] - microtime(true);
                if ($wait > 0) {
                    if (!$may_sleep || (microtime(true) - $began) + $wait > $budget) {
                        break;
                    }
                    usleep((int) ($wait * 1000000));
                }

                $this->process_page($state);

                if ((microtime(true) - $began) > $budget) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $state = self::get_state();
            $this->register_error($state, 'Unexpected error: ' . $e->getMessage());
            $this->save_progress($state);
        } finally {
            $this->core->release_lock(self::LOCK);
        }

        return self::get_state();
    }

    private function register_error(array &$state, $message)
    {
        $state['consecutive_errors']++;
        $state['last_error'] = $message;
        $state['errors']     = array_slice(array_merge($state['errors'], array(
            wp_date('H:i:s') . ' — ' . sprintf('صفحه %d: %s', $state['page'], $message),
        )), -10);

        // 30s, 60s, 120s ... capped at 5 minutes
        $backoff                  = min(300, 30 * pow(2, $state['consecutive_errors'] - 1));
        $state['next_allowed_at'] = microtime(true) + $backoff;

        WP_Dorna::write_log(sprintf('WP Dorna full sync error (page %d, attempt %d): %s', $state['page'], $state['consecutive_errors'], $message));

        if ($state['consecutive_errors'] >= self::MAX_CONSECUTIVE_ERRORS) {
            $state['status'] = 'failed';
        }
    }

    /**
     * A pause / cancel clicked while a page was being processed must win over the
     * state this request started with.
     */
    private function save_progress(array $state)
    {
        $fresh = self::get_state();

        if ($fresh['status'] !== 'running' && $state['status'] === 'running') {
            $state['status'] = $fresh['status'];
        }

        if ($fresh['run_id'] !== $state['run_id'] || $fresh['status'] === 'cancelled') {
            return;
        }

        $this->save_state($state);
    }

    private function process_page(array $state)
    {
        $api      = new WP_Dorna_API();
        $response = $api->get_data($api::PRODUCTS_ENDPOINT . '?' . http_build_query(array('page' => $state['page'], 'include_inactive' => 1)));

        if (!is_wp_error($response) && (!isset($response['data']) || !is_array($response['data']))) {
            $response = new WP_Error('api_error', 'فرمت پاسخ درنا نامعتبر است.');
        }

        if (is_wp_error($response)) {
            $this->register_error($state, $response->get_error_message());
            $this->save_progress($state);
            return;
        }

        $state['consecutive_errors'] = 0;

        if (empty($state['server_time'])) {
            $state['server_time'] = !empty($response['server_time'])
                ? $response['server_time']
                : (new DateTimeImmutable('now', wp_timezone()))->format(DATE_ATOM);
        }

        $state['total']     = isset($response['total']) ? (int) $response['total'] : $state['total'];
        $state['last_page'] = isset($response['last_page']) ? (int) $response['last_page'] : $state['last_page'];

        $products = array();
        $inactive = array();

        foreach ($response['data'] as $product) {
            $key = WP_Dorna::normalize_sku($product['sku'] ?? '');
            if ($key === '') {
                continue;
            }

            if (isset($product['is_active']) && !$product['is_active']) {
                $inactive[$key] = $product['sku'];
                continue;
            }

            $products[$key] = $product;
        }

        $result = $this->core->apply_dorna_products($products, $inactive);

        $state['checked']    += count($response['data']);
        $state['updated']    += $result['updated'];
        $state['unchanged']  += $result['unchanged'];
        $state['failed']     += $result['failed'];
        $state['stock_held'] += $result['stock_held'];
        $state['inactive']   += $result['inactive'];
        $state['missing']    += $result['missing'];

        $seen = get_option(self::SEEN_OPTION, array());
        update_option(self::SEEN_OPTION, array_merge(is_array($seen) ? $seen : array(), $result['matched']), false);

        $has_more = isset($response['current_page'], $response['last_page']) && $response['current_page'] < $response['last_page'];

        if ($has_more && $state['page'] >= self::MAX_PAGES) {
            $state['status']     = 'failed';
            $state['last_error'] = 'تعداد صفحه‌ها بیش از حد مجاز است.';
            $has_more            = false;
        }

        if ($has_more) {
            $state['page']++;
            $state['next_allowed_at'] = microtime(true) + $this->delay();
        } elseif ($state['status'] === 'running') {
            $this->finish($state);
        }

        $this->save_progress($state);
    }

    private function finish(array &$state)
    {
        $state['status']      = 'completed';
        $state['finished_at'] = (new DateTimeImmutable('now', wp_timezone()))->format(DATE_ATOM);

        // Whatever changed in Dorna while this pass was running is picked up by
        // the next minute sync, which starts from when the pass began.
        if (!empty($state['server_time'])) {
            update_option(WP_Dorna::LAST_UPDATE_OPTION, $state['server_time']);
        }

        // Store products Dorna did not list at all (not an error: the store may
        // sell items that are not managed in Dorna).
        $seen   = get_option(self::SEEN_OPTION, array());
        $unseen = array_keys(array_diff_key($this->core->get_woo_ids_by_sku(), array_flip(is_array($seen) ? $seen : array())));
        sort($unseen);

        $state['not_in_dorna'] = count($unseen);
        update_option(self::REPORT_OPTION, array_slice($unseen, 0, self::REPORT_LIMIT), false);
        delete_option(self::SEEN_OPTION);

        wp_clear_scheduled_hook(self::CRON_HOOK);

        WP_Dorna::write_log(sprintf(
            'WP Dorna full sync finished (run %s): checked %d, updated %d, unchanged %d, failed %d, not in the store %d, not in Dorna %d.',
            $state['run_id'],
            $state['checked'],
            $state['updated'],
            $state['unchanged'],
            $state['failed'],
            $state['missing'],
            $state['not_in_dorna']
        ));
    }

    // -------------------------------------------------------------------- UI

    public function ajax()
    {
        check_ajax_referer('wp_dorna_full_sync_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی کافی ندارید.'), 403);
        }

        $command = isset($_POST['command']) ? sanitize_key($_POST['command']) : 'status';

        switch ($command) {
            case 'start':
                $result = $this->start();
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }
                break;
            case 'pause':
                $this->pause();
                break;
            case 'resume':
                $this->resume();
                break;
            case 'cancel':
                $this->cancel();
                break;
            case 'tick':
                $this->tick(1, false);
                break;
        }

        wp_send_json_success($this->payload());
    }

    public function payload()
    {
        $state = self::get_state();

        if ($state['status'] === 'completed') {
            $percent = 100;
        } elseif ($state['last_page'] > 0) {
            $percent = (int) floor((($state['page'] - 1) / $state['last_page']) * 100);
        } else {
            $percent = 0;
        }

        $state['percent']          = $percent;
        $state['pages_left']       = max(0, $state['last_page'] - $state['page'] + 1);
        $state['delay']            = $this->delay();
        $state['not_in_dorna_skus'] = $state['status'] === 'completed' ? (array) get_option(self::REPORT_OPTION, array()) : array();
        unset($state['next_allowed_at']);

        return $state;
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'settings_page_wp-dorna-settings') {
            return;
        }

        wp_enqueue_style('wp-dorna-full-sync', WP_DORNA_PLUGIN_URL . 'assets/css/full-sync.css', array(), WP_DORNA_VERSION);
        wp_enqueue_script('wp-dorna-full-sync', WP_DORNA_PLUGIN_URL . 'assets/js/full-sync.js', array('jquery'), WP_DORNA_VERSION, true);
        wp_localize_script('wp-dorna-full-sync', 'wpDornaFullSync', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wp_dorna_full_sync_nonce'),
            'state'   => $this->payload(),
        ));
    }

    public function render_tab()
    {
        ?>
        <div id="tab-full-sync" class="wp-dorna-tab-content" style="display:none;">
            <h2>سینک کامل</h2>
            <p>همه‌ی کالاهای درنا را یک‌بار، آرام و صفحه‌به‌صفحه بررسی می‌کند و قیمت و موجودی کالاهای هم‌کد (SKU) در سایت را با درنا یکی می‌کند. این کار فشار کمی به درنا می‌آورد (هر چند ثانیه یک درخواست) و با بستن صفحه هم در پس‌زمینه ادامه پیدا می‌کند. در طول اجرا، بروزرسانی خودکار هر دقیقه متوقف است.</p>

            <div class="wp-dorna-box" id="wp-dorna-fs">
                <div class="wp-dorna-fs-head">
                    <strong id="wp-dorna-fs-status">—</strong>
                    <span id="wp-dorna-fs-eta"></span>
                </div>

                <div class="wp-dorna-fs-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="wp-dorna-fs-bar-fill" id="wp-dorna-fs-fill"></div>
                    <span id="wp-dorna-fs-percent">0٪</span>
                </div>

                <div class="wp-dorna-fs-stats">
                    <div><span id="wp-dorna-fs-checked">0</span><small>بررسی‌شده از <b id="wp-dorna-fs-total">0</b></small></div>
                    <div><span id="wp-dorna-fs-updated">0</span><small>بروزرسانی‌شده</small></div>
                    <div><span id="wp-dorna-fs-unchanged">0</span><small>بدون تغییر</small></div>
                    <div><span id="wp-dorna-fs-missing">0</span><small>در سایت نیست</small></div>
                    <div><span id="wp-dorna-fs-failed">0</span><small>خطا</small></div>
                </div>

                <p class="wp-dorna-fs-note" id="wp-dorna-fs-note"></p>

                <div class="wp-dorna-fs-actions">
                    <button type="button" class="button button-primary" data-fs="start">شروع سینک کامل</button>
                    <button type="button" class="button" data-fs="pause">توقف</button>
                    <button type="button" class="button button-primary" data-fs="resume">ادامه</button>
                    <button type="button" class="button" data-fs="cancel">لغو</button>
                </div>

                <ul class="wp-dorna-fs-errors" id="wp-dorna-fs-errors"></ul>

                <details id="wp-dorna-fs-report" style="display:none;">
                    <summary>کالاهای سایت که در درنا پیدا نشدند (<span id="wp-dorna-fs-report-count">0</span>)</summary>
                    <p>این کالاها در درنا وجود ندارند یا غیرفعال‌اند و تغییری در آن‌ها داده نشده است.</p>
                    <textarea readonly rows="8" id="wp-dorna-fs-report-list" dir="ltr"></textarea>
                </details>
            </div>
        </div>
        <?php
    }
}
