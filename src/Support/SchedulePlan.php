<?php

namespace WPDorna\Support;

use DateTimeImmutable;

/**
 * When the scheduled full sync is due. Pure date maths (the caller passes times
 * in the site's timezone), so it is easy to test.
 */
class SchedulePlan
{
    const MODES = array('off', 'nightly', 'weekly');

    // A due time missed by more than this (site was down / nobody visited) is skipped:
    // a full pass must not start in the middle of the day.
    const WINDOW_SECONDS = 21600;

    // Do not start another pass when one began this recently.
    const MIN_GAP_SECONDS = 43200;

    /**
     * The first run strictly after $after: today (or the next day) at $hour:00 for
     * nightly, or that hour on $weekday (0 = Sunday ... 6 = Saturday) for weekly.
     */
    public static function next_due(DateTimeImmutable $after, $mode, $hour, $weekday = 6)
    {
        $hour = max(0, min(23, (int) $hour));
        $slot = $after->setTime($hour, 0, 0);

        if ($mode === 'weekly') {
            $weekday = max(0, min(6, (int) $weekday));
            $slot    = $slot->modify('+' . (($weekday - (int) $slot->format('w') + 7) % 7) . ' days');
        }

        $step = $mode === 'weekly' ? '+7 days' : '+1 day';

        while ($slot <= $after) {
            $slot = $slot->modify($step);
        }

        return $slot;
    }

    public static function in_window(DateTimeImmutable $now, DateTimeImmutable $due)
    {
        $late = $now->getTimestamp() - $due->getTimestamp();

        return $late >= 0 && $late <= self::WINDOW_SECONDS;
    }
}
