<?php
// includes/finance_helper.php
// Server-Side Late Penalty Engine & Invoice Balance Calculator

/**
 * Calculates server-side late penalty respecting grace period, frequency, and maximum penalty cap.
 */
function calculateLatePenaltyAmount(
    float $baseAmount,
    string $dueDate,
    string $asOfDate,
    string $penaltyType,
    string $penaltyFrequency,
    float $penaltyValue,
    int $graceDays,
    float $maxPenalty
): float {
    if ($penaltyType === 'None' || $penaltyValue <= 0) {
        return 0.00;
    }

    $dueTs = strtotime($dueDate);
    $asOfTs = strtotime($asOfDate);
    $graceLimitTs = $dueTs + ($graceDays * 86400);

    if ($asOfTs <= $graceLimitTs) {
        return 0.00;
    }

    $periods = 1;
    if ($penaltyFrequency === 'Monthly') {
        $daysOver = (int)floor(($asOfTs - $graceLimitTs) / 86400);
        $periods = max(1, (int)ceil($daysOver / 30));
    }

    $perPeriod = ($penaltyType === 'Percentage')
        ? round($baseAmount * ($penaltyValue / 100), 2)
        : round($penaltyValue, 2);

    $total = round($perPeriod * $periods, 2);
    if ($maxPenalty > 0) {
        $total = min($total, $maxPenalty);
    }
    return max(0.00, $total);
}
