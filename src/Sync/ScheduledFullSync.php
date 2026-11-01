<?php

namespace WPDorna\Sync;

use DateTimeImmutable;
use WPDorna\Core\Connection;
use WPDorna\Core\Settings;
use WPDorna\Support\Logger;
use WPDorna\Support\SchedulePlan;

/**
 * Starts the full sync by itself at the hour the owner picked (nightly or weekly).
 * WP-Cron only runs when the site gets a visit, so a due time is honoured late, but
 * only inside a night-sized window: a pass is never started in the middle of the day.
 */
class ScheduledFullSync
{
    private $settings;
    private $connection;
    private $full_sync;
    private $logger;

    public function __construct(Settings $settings, Connection $connection, FullSync $full_sync, Logger $logger)
    {
        $this->settings   = $settings;
        $this->connection = $connection;
        $this->full_sync  = $full_sync;
        $this->logger     = $logger;
    }

    /** The next run for a (new) schedule, as stored in the settings. */
    public function plan(array $schedule, DateTimeImmutable $now = null)
    {
        $now = $now ?: new DateTimeImmutable('now', wp_timezone());

        return $schedule['mode'] === 'off'
            ? ''
            : SchedulePlan::next_due($now, $schedule['mode'], $schedule['hour'], $schedule['weekday'])->format(DATE_ATOM);
    }

    public function save(array $schedule)
    {
        $schedule['next_due'] = $this->plan(array_merge($this->settings->schedule(), $schedule));
        $this->settings->save_schedule($schedule);
    }

    /**
     * @return string started | idle | not_due | missed | busy | recent | no_connection
     */
    public function run(DateTimeImmutable $now = null)
    {
        $now      = $now ?: new DateTimeImmutable('now', wp_timezone());
        $schedule = $this->settings->schedule();

        if ($schedule['mode'] === 'off') {
            return 'idle';
        }

        if (!$this->connection->is_usable()) {
            return 'no_connection';
        }

        if ($schedule['next_due'] === '') {
            $this->settings->save_schedule(array('next_due' => $this->plan($schedule, $now)));

            return 'not_due';
        }

        $due = new DateTimeImmutable($schedule['next_due']);

        if ($now < $due) {
            return 'not_due';
        }

        // Whatever happens below, the next run is the next slot after now.
        $this->settings->save_schedule(array('next_due' => $this->plan($schedule, $now)));

        if (!SchedulePlan::in_window($now, $due)) {
            $this->logger->info('زمان سینک شبانه از دست رفت؛ نوبت بعدی.', array('due' => $schedule['next_due']));

            return 'missed';
        }

        $state = FullSync::get_state();

        if (in_array($state['status'], array('running', 'paused'), true)) {
            return 'busy';
        }

        $started = $state['started_at'] ? strtotime($state['started_at']) : 0;
        if ($started && ($now->getTimestamp() - $started) < SchedulePlan::MIN_GAP_SECONDS) {
            return 'recent';
        }

        $result = $this->full_sync->start('scheduled');

        if (is_wp_error($result)) {
            return 'busy';
        }

        $this->logger->info('سینک کامل زمان‌بندی‌شده شروع شد.');

        return 'started';
    }
}
