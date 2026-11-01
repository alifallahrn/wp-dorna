<?php

namespace WPDorna\Core;

use WPDorna\Sync\FullSync;

/**
 * The first-run checklist: connect, first full sync, import, nightly schedule.
 * Installs that existed before the wizard (no option stored) count as set up.
 */
class Wizard
{
    const OPTION = 'wp_dorna_wizard';

    private $settings;
    private $connection;

    public function __construct(Settings $settings, Connection $connection)
    {
        $this->settings   = $settings;
        $this->connection = $connection;
    }

    /** Called on activation: a fresh install (no token yet) gets the wizard. */
    public function start_if_new()
    {
        if (get_option(self::OPTION, null) === null && !$this->settings->has_key()) {
            update_option(self::OPTION, array('finished' => false), false);
        }
    }

    private function state()
    {
        $state = get_option(self::OPTION, null);

        if (!is_array($state)) {
            return array('finished' => true);
        }

        return $state;
    }

    private function save(array $changes)
    {
        update_option(self::OPTION, array_merge($this->state(), $changes), false);
    }

    public function finished()
    {
        return !empty($this->state()['finished']);
    }

    public function mark($step)
    {
        if (in_array($step, array('import', 'schedule'), true)) {
            $this->save(array($step . '_done' => true));
        }
    }

    public function finish()
    {
        $this->save(array('finished' => true));
    }

    public function reopen()
    {
        $this->save(array('finished' => false));
    }

    /**
     * @return array list of {key, done, optional}
     */
    public function steps()
    {
        $state = $this->state();

        return array(
            array('key' => 'connect', 'done' => $this->settings->has_key() && $this->connection->is_usable(), 'optional' => false),
            array('key' => 'full_sync', 'done' => FullSync::get_state()['status'] === 'completed', 'optional' => false),
            array('key' => 'import', 'done' => !empty($state['import_done']), 'optional' => true),
            array('key' => 'schedule', 'done' => !empty($state['schedule_done']) || $this->settings->schedule()['mode'] !== 'off', 'optional' => true),
        );
    }

    public function done_count()
    {
        return count(array_filter($this->steps(), function ($step) {
            return $step['done'];
        }));
    }

    /** The two required steps are done: the rest can be skipped. */
    public function ready()
    {
        foreach ($this->steps() as $step) {
            if (!$step['optional'] && !$step['done']) {
                return false;
            }
        }

        return true;
    }
}
