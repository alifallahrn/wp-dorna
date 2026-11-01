<?php

namespace WPDorna\Core;

/**
 * How this site is linked to Dorna. "connect" = the button flow (a token Dorna issued
 * for this site), "manual" = a token pasted by hand (older installs).
 *
 * Status: none (no token) | connected | manual | rejected (Dorna refused the token:
 * revoked or replaced) | mismatch (the token belongs to another site address, i.e.
 * this looks like a copy of the site).
 */
class Connection
{
    const OPTION = 'wp_dorna_connection';

    const NONE      = 'none';
    const CONNECTED = 'connected';
    const MANUAL    = 'manual';
    const REJECTED  = 'rejected';
    const MISMATCH  = 'mismatch';

    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function data()
    {
        $data = get_option(self::OPTION, array());

        return array_merge(array(
            'source'            => '',
            'status'            => '',
            'business_name'     => '',
            'business_username' => '',
            'site'              => '',
            'connected_at'      => '',
            'status_at'         => '',
        ), is_array($data) ? $data : array());
    }

    public function status()
    {
        if (!$this->settings->has_key()) {
            return self::NONE;
        }

        $status = $this->data()['status'];

        return in_array($status, array(self::CONNECTED, self::REJECTED, self::MISMATCH), true) ? $status : self::MANUAL;
    }

    /** True when requests to Dorna are expected to be accepted. */
    public function is_usable()
    {
        return in_array($this->status(), array(self::CONNECTED, self::MANUAL), true);
    }

    /** Connected through the button flow (not a hand-pasted token). */
    public function is_linked()
    {
        return $this->data()['source'] === 'connect' && $this->settings->has_key();
    }

    public function needs_attention()
    {
        return in_array($this->status(), array(self::REJECTED, self::MISMATCH), true);
    }

    /**
     * @param array $exchange the answer of Dorna's connect/exchange
     */
    public function save_connected(array $exchange)
    {
        update_option(self::OPTION, array(
            'source'            => 'connect',
            'status'            => self::CONNECTED,
            'business_name'     => isset($exchange['business']['name']) ? sanitize_text_field($exchange['business']['name']) : '',
            'business_username' => isset($exchange['business']['username']) ? sanitize_text_field($exchange['business']['username']) : '',
            'site'              => isset($exchange['site']) ? esc_url_raw($exchange['site']) : '',
            'connected_at'      => gmdate('c'),
            'status_at'         => gmdate('c'),
        ), false);
    }

    public function set_manual()
    {
        update_option(self::OPTION, array('source' => 'manual', 'status' => self::MANUAL, 'status_at' => gmdate('c')), false);
    }

    /**
     * Records that Dorna refused the token (only writes when the status changes, so a
     * failing minute-sync does not hit the database every time).
     */
    public function mark($status)
    {
        $data = $this->data();

        if ($data['status'] === $status) {
            return;
        }

        $data['status']    = $status;
        $data['status_at'] = gmdate('c');
        update_option(self::OPTION, $data, false);
    }

    /** A request succeeded again: forget an earlier "rejected" / "mismatch". */
    public function mark_ok()
    {
        $status = $this->data()['status'];

        if ($status === self::REJECTED || $status === self::MISMATCH) {
            $data           = $this->data();
            $data['status'] = $data['source'] === 'connect' ? self::CONNECTED : self::MANUAL;
            update_option(self::OPTION, $data, false);
        }
    }

    public function clear()
    {
        delete_option(self::OPTION);
    }
}
