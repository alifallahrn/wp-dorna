<?php
/**
 * Unit tests for the pure helpers. No WordPress needed:
 *
 *   php tests/run.php
 */

define('DAY_IN_SECONDS', 86400);

require __DIR__ . '/../src/Support/Sku.php';
require __DIR__ . '/../src/Support/Money.php';
require __DIR__ . '/../src/Support/Backoff.php';
require __DIR__ . '/../src/Support/SchedulePlan.php';
require __DIR__ . '/../src/Support/Site.php';

use WPDorna\Support\Backoff;
use WPDorna\Support\Money;
use WPDorna\Support\SchedulePlan;
use WPDorna\Support\Site;
use WPDorna\Support\Sku;

$failures = 0;

function check($name, $ok)
{
    global $failures;
    echo ($ok ? "PASS " : "FAIL ") . $name . "\n";
    $failures += $ok ? 0 : 1;
}

// SKU normalization
check('sku: trims and lowercases', Sku::normalize('  AB-12 ') === 'ab-12');
check('sku: persian digits', Sku::normalize('ک۱۲۳') === 'ک123');
check('sku: arabic digits', Sku::normalize('A٤٥٦') === 'a456');
check('sku: empty stays empty', Sku::normalize('   ') === '');
check('sku: null is safe', Sku::normalize(null) === '');

// Money: Toman <-> Dorna
check('money: IRT to dorna x10', Money::to_dorna(1000, 'IRT') === 10000);
check('money: other currency untouched', Money::to_dorna(1000, 'IRR') === 1000);
check('money: from dorna /10', Money::from_dorna(10000, 'IRT') === 1000);
check('money: from dorna keeps null', Money::from_dorna(null, 'IRT') === null);
check('money: from dorna other currency untouched', Money::from_dorna(500, 'USD') === 500);

// Invoice retry backoff: 5 min, doubling, capped at 6 hours
check('backoff: no attempts -> no wait', Backoff::invoice_delay(0) === 0);
check('backoff: 1st failure 5 min', Backoff::invoice_delay(1) === 300);
check('backoff: 3rd failure 20 min', Backoff::invoice_delay(3) === 1200);
check('backoff: capped at 6h', Backoff::invoice_delay(30) === 21600);

// Full sync error pause: 30s, 60s ... 5 min
check('error delay: 1st 30s', Backoff::error_delay(1) === 30);
check('error delay: 3rd 120s', Backoff::error_delay(3) === 120);
check('error delay: capped at 300s', Backoff::error_delay(10) === 300);
check('error delay: 0 treated as first', Backoff::error_delay(0) === 30);

// Site origin: scheme + host (+ non-default port), never a path
check('site: strips path and query', Site::origin_of('https://Shop.Example.ir/blog/wp-admin?x=1') === 'https://shop.example.ir');
check('site: keeps a port', Site::origin_of('http://127.0.0.1:8083/wp') === 'http://127.0.0.1:8083');
check('site: refuses non http(s)', Site::origin_of('ftp://example.ir') === '' && Site::origin_of('javascript:alert(1)') === '' && Site::origin_of('') === '');

// Scheduled full sync: next due time
$tz  = new DateTimeZone('Asia/Tehran');
$at  = function ($text) use ($tz) { return new DateTimeImmutable($text, $tz); };
check('schedule: nightly before the hour is today', SchedulePlan::next_due($at('2026-11-02 01:00'), 'nightly', 3)->format('Y-m-d H:i') === '2026-11-02 03:00');
check('schedule: nightly after the hour is tomorrow', SchedulePlan::next_due($at('2026-11-02 03:00'), 'nightly', 3)->format('Y-m-d H:i') === '2026-11-03 03:00');
check('schedule: nightly crosses the month end', SchedulePlan::next_due($at('2026-11-30 22:00'), 'nightly', 3)->format('Y-m-d H:i') === '2026-12-01 03:00');
// 2026-11-02 is a Monday (w=1); Saturday = 6
check('schedule: weekly picks the coming weekday', SchedulePlan::next_due($at('2026-11-02 10:00'), 'weekly', 4, 6)->format('Y-m-d H:i') === '2026-11-07 04:00');
check('schedule: weekly on the same weekday after the hour waits a week', SchedulePlan::next_due($at('2026-11-07 05:00'), 'weekly', 4, 6)->format('Y-m-d H:i') === '2026-11-14 04:00');
check('schedule: weekly on the same weekday before the hour is today', SchedulePlan::next_due($at('2026-11-07 01:00'), 'weekly', 4, 6)->format('Y-m-d H:i') === '2026-11-07 04:00');
check('schedule: hour is clamped', SchedulePlan::next_due($at('2026-11-02 00:00'), 'nightly', 99)->format('H') === '23');
check('schedule: window accepts a late start', SchedulePlan::in_window($at('2026-11-02 05:00'), $at('2026-11-02 03:00')));
check('schedule: window refuses a start more than 6h late', !SchedulePlan::in_window($at('2026-11-02 10:00'), $at('2026-11-02 03:00')));
check('schedule: window refuses an early start', !SchedulePlan::in_window($at('2026-11-02 02:59'), $at('2026-11-02 03:00')));

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit($failures ? 1 : 0);
