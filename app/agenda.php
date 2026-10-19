<?php
declare(strict_types=1);

function agendaPeriod(string $date, string $mode): array {
    $anchor = new DateTimeImmutable($date);
    $start = match ($mode) {
        'week' => $anchor->modify('-'.((int)$anchor->format('N')-1).' days'),
        'month' => $anchor->modify('first day of this month'),
        default => $anchor,
    };
    $step = match ($mode) { 'week' => 'week', 'month' => 'month', default => 'day' };
    return ['from'=>$start->format('Y-m-d'), 'until'=>$start->modify('+1 '.$step)->format('Y-m-d'),
        'previous'=>$start->modify('-1 '.$step)->format('Y-m-d'), 'next'=>$start->modify('+1 '.$step)->format('Y-m-d')];
}
