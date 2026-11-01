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

use WPDorna\Support\Backoff;
use WPDorna\Support\Money;
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

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit($failures ? 1 : 0);
