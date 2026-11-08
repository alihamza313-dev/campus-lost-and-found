<?php
declare(strict_types=1);

/** Reads a GET field as a trimmed string. Arrays become '' instead of causing errors. */
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Builds /path?a=1&b=2 and drops empty values, so URLs stay short. */
function page_url(string $path, array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return url($path) . ($params ? '?' . http_build_query($params) : '');
}

/** "claim_pending" -> "Claim pending". */
function ui_label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

/** "2026-11-08" -> "8 Nov 2026". Falls back to the raw text if it cannot be parsed. */
function ui_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? $date : date('j M Y', $ts);
}

/** One-sentence plain-English meaning of an item status, for the item page sidebar. */
function ui_item_status_help(string $status, string $type): string
{
    return match ($status) {
        'open'               => $type === 'found'
            ? 'Waiting for its owner. Anyone who thinks it is theirs can submit a claim.'
            : 'Still missing. If you have seen it, message the poster.',
        'claim_pending'      => 'Someone has claimed this item. A custodian is checking the claim.',
        'ready_for_handover' => 'A claim was approved. The item is waiting for an in-person handover.',
        'recovered'          => 'This item is back with its owner.',
        'closed'             => 'This listing was closed by its poster.',
        'removed'            => 'This listing was removed by an administrator.',
        default              => '',
    };
}

/**
 * Progress steps for a claim.
 * Returns a list of [label, note, state] where state is done | current | todo | stop.
 */
function ui_claim_steps(string $claimStatus, ?array $handover): array
{
    $hs = $handover['handover_status'] ?? '';
    $steps = [
        ['Submitted',    'Your statement was sent.',          'done'],
        ['Under review', 'A custodian checks your details.',  'todo'],
        ['Approved',     'Your claim was accepted.',          'todo'],
        ['Handover',     'Collect it with your student ID.',  'todo'],
    ];

    switch ($claimStatus) {
        case 'pending':
        case 'under_review':
            $steps[1][2] = 'current';
            break;
        case 'approved':
            $steps[1][2] = 'done';
            $steps[2][2] = 'done';
            if ($hs === 'completed') {
                $steps[3][2] = 'done';
                $steps[3][1] = 'Item handed over.';
            } else {
                $steps[3][2] = 'current';
                $steps[3][1] = $hs === 'scheduled' ? 'Pickup is scheduled.' : 'A custodian will arrange pickup.';
            }
            break;
        case 'rejected':
            $steps[1][2] = 'stop';
            $steps[1][0] = 'Not approved';
            $steps[1][1] = 'See the decision below.';
            $steps[2][0] = $steps[3][0] = '';
            $steps = array_values(array_filter($steps, fn ($s) => $s[0] !== ''));
            break;
        case 'withdrawn':
            $steps[0][2] = 'done';
            $steps[1][2] = 'stop';
            $steps[1][0] = 'Withdrawn';
            $steps[1][1] = 'You withdrew this claim.';
            $steps = array_slice($steps, 0, 2);
            break;
    }
    return $steps;
}

/** Short "what happens next" line for a claim, shown in lists. */
function ui_claim_next(string $claimStatus): string
{
    return match ($claimStatus) {
        'pending', 'under_review' => 'Waiting for a custodian to review.',
        'approved'                => 'Approved. Bring your student ID for pickup.',
        'rejected'                => 'Not approved. Open it to read why.',
        'withdrawn'               => 'You withdrew this claim.',
        default                   => '',
    };
}
