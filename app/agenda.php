<?php
declare(strict_types=1);

function agendaContext(array $source): array {
    $context=[];
    $date=input($source,'date');
    if (validDate($date)) $context['date']=$date;
    $mode=input($source,'mode');
    if (in_array($mode,['day','week','month'],true)) $context['mode']=$mode;
    $employee=input($source,'employee');
    if (ctype_digit($employee) && strlen($employee)<11 && (int)$employee>0) $context['employee']=$employee;
    $status=input($source,'status');
    if (isset(appointmentStatuses()[$status])) $context['status']=$status;
    return $context;
}

function appointmentDraft(string $token): ?array {
    $draft=$_SESSION['appointment_drafts'][$token]??null;
    if (!is_array($draft) || $draft['created']<time()-3600) return null;
    return $draft;
}

function appointmentDraftTarget(array $draft, string $token): array {
    return ['page'=>$draft['id']===null?'appointment-new':'appointment-edit', 'params'=>['draft'=>$token]+($draft['id']===null?[]:['id'=>$draft['id']])];
}

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
