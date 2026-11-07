<?php
declare(strict_types=1);

function custodian_dashboard(): void
{
    $user = require_staff();
    $uid  = (int) $user['user_id'];
    view('custodian/dashboard', [
        'title'     => 'Custodian dashboard',
        'queue'     => review_queue($uid),
        'handovers' => handovers_dashboard($uid),
    ]);
}

function handover_page(int $claimId): void
{
    $user = require_staff();
    $uid  = (int) $user['user_id'];
    $info = handover_page_data($claimId);

    if ($info === null || $info['report_type'] !== 'found' || $info['claim_status'] !== 'approved'
        || (int) $info['reporter_id'] === $uid || (int) $info['claimant_id'] === $uid) {
        abort(404);
    }

    view('handovers/show', [
        'title'      => 'Handover',
        'info'       => $info,
        'handover'   => handover_find_by_claim($claimId),
        'needsNotes' => (int) $info['is_high_value'] === 1 || (int) $info['claims_filed'] > 1,
        'minWhen'    => now_local()->format('Y-m-d\TH:i'),
    ]);
}

function handover_finish(int $claimId, array $result, string $successRedirect): void
{
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($result['ok'] ? $successRedirect : '/claims/' . $claimId . '/handover');
}

function handover_schedule_submit(int $id): void
{
    $user = require_staff();
    require_csrf();
    handover_finish($id, handover_schedule($id, (int) $user['user_id'], post_str('scheduled_at')), '/claims/' . $id . '/handover');
}

function handover_complete_submit(int $id): void
{
    $user = require_staff();
    require_csrf();
    handover_finish($id, handover_complete($id, (int) $user['user_id'], post_str('id_verified') === '1', post_str('notes')), '/custodian');
}

function handover_revoke_submit(int $id): void
{
    $user = require_staff();
    require_csrf();
    handover_finish($id, handover_revoke($id, (int) $user['user_id'], post_str('reason')), '/custodian');
}