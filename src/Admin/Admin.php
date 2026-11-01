<?php

namespace WPDorna\Admin;

use WPDorna\Admin\Pages\DashboardPage;
use WPDorna\Admin\Pages\ImportPage;
use WPDorna\Admin\Pages\LogsPage;
use WPDorna\Admin\Pages\OrdersPage;
use WPDorna\Admin\Pages\Page;
use WPDorna\Admin\Pages\SettingsPage;
use WPDorna\Admin\Pages\SetupPage;
use WPDorna\Admin\Pages\SyncPage;
use WPDorna\Core\Services;

class Admin
{
    private $services;

    /** @var Page[] */
    private $pages;

    private $settings_page;
    private $setup_page;

    public function __construct(Services $services)
    {
        $this->services      = $services;
        $this->settings_page = new SettingsPage($services);
        $this->setup_page    = new SetupPage($services);
        $this->pages         = array(
            new DashboardPage($services),
            $this->setup_page,
            new SyncPage($services),
            new ImportPage($services),
            new OrdersPage($services),
            new LogsPage($services),
            $this->settings_page,
        );
    }

    public function register()
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_filter('admin_body_class', array($this, 'body_class'));

        $this->settings_page->register();
        $this->setup_page->register();
        (new ConnectionNotices($this->services, $this))->register();

        (new Assets($this->services, $this))->register();
        (new Ajax($this->services))->register();
        (new OrderIntegration($this->services))->register();
        (new ProductIntegration())->register();
    }

    public function add_menu()
    {
        add_menu_page(
            __('اتصال به درنا', 'wp-dorna'),
            __('درنا', 'wp-dorna'),
            'manage_woocommerce',
            $this->pages[0]->slug(),
            '', // the first submenu carries the callback, or the screen would render twice
            $this->menu_icon(),
            56
        );

        foreach ($this->pages as $page) {
            // A finished checklist leaves the menu, but its URL keeps working ("run the setup again").
            if (!$page->visible() && !$this->is_current($page)) {
                continue;
            }

            add_submenu_page(
                $this->pages[0]->slug(),
                $page->title(),
                $page->label(),
                $page->capability(),
                $page->slug(),
                function () use ($page) {
                    $this->render($page);
                }
            );
        }
    }

    private function render(Page $page)
    {
        $pages = array_filter($this->pages, function (Page $item) use ($page) {
            return current_user_can($item->capability()) && ($item->visible() || $item === $page);
        });

        View::render('layout', array(
            'pages'   => $pages,
            'current' => $page,
            'content' => function () use ($page) {
                $page->render();
            },
        ));
    }

    private function is_current(Page $page)
    {
        $current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        return $page->slug() === $current;
    }

    public function is_plugin_screen()
    {
        foreach ($this->pages as $item) {
            if ($this->is_current($item)) {
                return true;
            }
        }

        return false;
    }

    public function body_class($classes)
    {
        return $this->is_plugin_screen() ? $classes . ' wpd-screen' : $classes;
    }

    private function menu_icon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h6a8 8 0 0 1 0 16H5z"/><path d="M9 9v6"/></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
