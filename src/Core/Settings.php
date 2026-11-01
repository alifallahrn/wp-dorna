<?php

namespace WPDorna\Core;

/**
 * The token can be fixed in wp-config.php with define('WP_DORNA_API_KEY', '...'),
 * which wins over the stored value and keeps it out of the database.
 */
class Settings
{
    private function all()
    {
        $options = get_option(WP_DORNA_OPTION_NAME, array());

        return is_array($options) ? $options : array();
    }

    public function api_key()
    {
        if ($this->key_from_constant()) {
            return trim((string) WP_DORNA_API_KEY);
        }

        $options = $this->all();

        return isset($options['api_key']) ? trim((string) $options['api_key']) : '';
    }

    public function key_from_constant()
    {
        return defined('WP_DORNA_API_KEY') && trim((string) WP_DORNA_API_KEY) !== '';
    }

    public function has_key()
    {
        return $this->api_key() !== '';
    }

    /**
     * Last 4 characters only: enough to recognise the token, useless to steal.
     */
    public function masked_key()
    {
        $key = $this->api_key();

        return $key === '' ? '' : '••••••••' . substr($key, -4);
    }

    public function remove_data_on_uninstall()
    {
        $options = $this->all();

        return !empty($options['remove_data']);
    }

    public function set_api_key($api_key)
    {
        $options            = $this->all();
        $options['api_key'] = trim(sanitize_text_field((string) $api_key));

        update_option(WP_DORNA_OPTION_NAME, $options, false);
    }

    public function clear_api_key()
    {
        $options            = $this->all();
        $options['api_key'] = '';

        update_option(WP_DORNA_OPTION_NAME, $options, false);
    }

    /**
     * Scheduled full sync: off | nightly | weekly, at an hour of the site's day.
     */
    public function schedule()
    {
        $options  = $this->all();
        $schedule = isset($options['schedule']) && is_array($options['schedule']) ? $options['schedule'] : array();

        return array(
            'mode'     => isset($schedule['mode']) && in_array($schedule['mode'], \WPDorna\Support\SchedulePlan::MODES, true) ? $schedule['mode'] : 'off',
            'hour'     => isset($schedule['hour']) ? max(0, min(23, (int) $schedule['hour'])) : 3,
            'weekday'  => isset($schedule['weekday']) ? max(0, min(6, (int) $schedule['weekday'])) : 6,
            'next_due' => isset($schedule['next_due']) ? (string) $schedule['next_due'] : '',
        );
    }

    public function save_schedule(array $schedule)
    {
        $options             = $this->all();
        $options['schedule'] = array_merge($this->schedule(), $schedule);

        update_option(WP_DORNA_OPTION_NAME, $options, false);
    }

    /**
     * An empty token field keeps the stored one (the field is never pre-filled).
     */
    public function save($api_key, $remove_data)
    {
        $options = $this->all();
        $api_key = trim(sanitize_text_field((string) $api_key));

        if ($api_key !== '') {
            $options['api_key'] = $api_key;
        }

        $options['remove_data'] = $remove_data ? 1 : 0;

        update_option(WP_DORNA_OPTION_NAME, $options, false);
    }
}
