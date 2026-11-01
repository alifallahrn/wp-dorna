<?php

namespace WPDorna\Core;

use WPDorna\Api\Client;
use WPDorna\Connect\ConnectionService;
use WPDorna\Connect\Heartbeat;
use WPDorna\Import\ImportService;
use WPDorna\Orders\InvoiceService;
use WPDorna\Orders\RetryService;
use WPDorna\Support\Lock;
use WPDorna\Support\Logger;
use WPDorna\Sync\FullSync;
use WPDorna\Sync\IncrementalSync;
use WPDorna\Sync\ProductWriter;
use WPDorna\Sync\ScheduledFullSync;
use WPDorna\Sync\SingleSync;

/**
 * The plugin's collaborators, wired once. Plain composition: each class receives
 * what it needs through its constructor.
 */
class Services
{
    public $settings;
    public $connection;
    public $wizard;
    public $heartbeat;
    public $connect;
    public $scheduled;
    public $logger;
    public $lock;
    public $client;
    public $writer;
    public $full_sync;
    public $incremental;
    public $single;
    public $invoices;
    public $retry;
    public $importer;

    public function __construct()
    {
        $this->settings    = new Settings();
        $this->connection  = new Connection($this->settings);
        $this->wizard      = new Wizard($this->settings, $this->connection);
        $this->logger      = new Logger(function () {
            return array($this->settings->api_key());
        });
        $this->lock        = new Lock();
        $this->client      = new Client($this->settings, $this->connection);
        $this->writer      = new ProductWriter($this->logger);
        $this->full_sync   = new FullSync($this->client, $this->writer, $this->lock, $this->logger);
        $this->incremental = new IncrementalSync($this->client, $this->writer, $this->full_sync, $this->lock, $this->logger);
        $this->single      = new SingleSync($this->client, $this->writer);
        $this->invoices    = new InvoiceService($this->client, $this->lock, $this->logger);
        $this->retry       = new RetryService($this->invoices, $this->lock);
        $this->importer    = new ImportService($this->client, $this->writer, $this->logger);
        $this->heartbeat   = new Heartbeat($this->client, $this->connection, $this->settings, $this->logger);
        $this->connect     = new ConnectionService($this->client, $this->settings, $this->connection, $this->heartbeat, $this->wizard, $this->logger);
        $this->scheduled   = new ScheduledFullSync($this->settings, $this->connection, $this->full_sync, $this->logger);
    }
}
