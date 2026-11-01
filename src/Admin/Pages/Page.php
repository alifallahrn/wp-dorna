<?php

namespace WPDorna\Admin\Pages;

use WPDorna\Core\Services;

abstract class Page
{
    protected $services;

    public function __construct(Services $services)
    {
        $this->services = $services;
    }

    abstract public function slug();

    abstract public function label();

    abstract public function icon();

    abstract public function title();

    /** One sentence that says what the screen is for. */
    abstract public function purpose();

    abstract public function render();

    public function capability()
    {
        return 'manage_woocommerce';
    }

    public function url(array $args = array())
    {
        return add_query_arg(array_merge(array('page' => $this->slug()), $args), admin_url('admin.php'));
    }
}
