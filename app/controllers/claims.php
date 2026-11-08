<?php
declare(strict_types=1);

/* ---------- Helpers ---------- */

function require_staff(): array
{
    $user = require_role('custodian', 'admin');
    if (!user_can_act($user)) {
        abort(403);
    }
    return $user;
}

/** Why this user may not claim this item, or null if they may. $item comes from item_find_detail(). */
function claim_eligibility(array $user, array $item): ?string
{
    if (!user_can_act($user)) {
        return 'You need a verified email and an active account to claim an item.';
    }
    if ($user['role'] !== 'student') {
        return 'Staff accounts review claims and cannot submit them.';
    }
    if ($item['report_type'] !== 'found') {
        return 'Only found items can be claimed. If you found a lost item, message the poster instead.';
    }
    if (!in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true)) {
        return 'This item is no longer open for claims.';
    }
    if ((int) $item['reporter_id'] === (int) $user['user_id']) {
        return 'You cannot claim your own listing.';
    }
    if (claim_has((int) $item['item_id'], (int) $user['user_id']) !== null) {
        return 'You already submitted a claim for this item.';
    }
    return null;
}

function claim_render_form(array $item, array $old, array $errors): void
{
    view('claims/form', ['title' => 'Claim this item', 'item' => $item, 'old' => $old, 'errors' => $errors]);
}

/* ---------- Submit ---------- */

function claim_form(int $id): void
{
    $user = require_verified();
    $item = item_find_detail($id);
    if ($item === null || !in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true)) {
        abort(404);
    }
    if ($err = claim_eligibility($user, $item)) {
        flash_set('error', $err);
        redirect('/items/' . $id);
    }
    claim_render_form($item, ['ownership_details' => '', 'proof_description' => ''], []);
}

function claim_submit(int $id): void
{
    $user = require_verified();                       // T-03: suspended or unverified users stop here
    require_csrf();
    $uid = (int) $user['user_id'];

    if (!form_token_consume()) {
        flash_set('warning', 'That form was already submitted or has expired. Check "My claims" to see whether it went through.');
        redirect('/my/claims');
    }

    $item = item_find_detail($id);
    if ($item === null || !in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true)) {
        abort(404);
    }
    if ($err = claim_eligibility($user, $item)) {     // T-05, T-06
        flash_set('error', $err);
        redirect('/items/' . $id);
    }

    $old = ['ownership_details' => post_str('ownership_details'), 'proof_description' => post_str('proof_description')];
    $errors = [];
    $len = mb_strlen($old['ownership_details']);
    if ($len < 10 || $len > 2000) {
        $errors['ownership_details'] = 'Describe how you can prove it is yours (10 to 2000 characters).';
    }
    if (mb_strlen($old['proof_description']) > 500) {
        $errors['proof_description'] = 'Keep the file description under 500 characters.';
    }
    $proof = proof_collect_upload();
    if ($proof !== null && ($e = proof_validate($proof))) {
        $errors['proof'] = $e;
    }

    $status = 422;
    if (!$errors && rate_limited('claim', 'claim:user:' . $uid)) {
        $errors['form'] = 'You have reached the daily limit of ' . config('limits.claim.max') . ' claims. Please try again tomorrow.';
        $status = 429;
    }
    if ($errors) {
        http_response_code($status);
        claim_render_form($item, $old, $errors);
        return;
    }

    $stored = null;
    try {
        if ($proof !== null) {
            $stored = proof_store($proof);
        }
        $desc   = $old['proof_description'] !== '' ? $old['proof_description'] : 'Proof file attached by the claimant';
        $result = claim_create($id, $uid, $old['ownership_details'], $stored, $desc);
    } catch (RuntimeException $e) {
        if ($stored !== null) { proof_delete($stored); }
        http_response_code(422);
        claim_render_form($item, $old, ['proof' => $e->getMessage()]);
        return;
    } catch (Throwable $e) {
        if ($stored !== null) { proof_delete($stored); }
        throw $e;
    }

    if (!$result['ok']) {
        if ($stored !== null) { proof_delete($stored); }
        flash_set('error', $result['message']);
        redirect('/items/' . $id);
    }

    rate_hit('claim', 'claim:user:' . $uid);
    flash_set('success', 'Your claim was submitted. A custodian will review it.');
    redirect('/claims/' . $result['claim_id']);
}

/* ---------- My claims, claim page, withdraw ---------- */

function my_claims_index(): void
{
    $user = require_login();
    view('claims/mine', ['title' => 'My claims', 'claims' => claims_by_claimant((int) $user['user_id'])]);
}

function claim_show(int $id): void
{
    $user  = require_login();
    $claim = claim_find_detail($id);
    if ($claim === null) {
        abort(404);
    }
    $uid     = (int) $user['user_id'];
    $isOwner = (int) $claim['claimant_id'] === $uid;
    $isStaff = in_array($user['role'], ['custodian', 'admin'], true) && user_can_act($user);
    $isPoster = (int) $claim['reporter_id'] === $uid;

    // The poster never sees claims on their own item, even if they are staff (BR-5).
    if ($isPoster || (!$isOwner && !$isStaff)) {
        abort(404);
    }

    view('claims/show', [
        'title'       => 'Claim details',
        'claim'       => $claim,
        'isOwner'     => $isOwner,
        'proofId'     => claim_proof_id($id),
        'canWithdraw' => $isOwner && in_array($claim['claim_status'], ['pending', 'under_review'], true),
        'handover' => handover_find_by_claim($id),
        'isStaff'  => $isStaff,
    ]);
}

function claim_withdraw_submit(int $id): void
{
    $user = require_login();
    require_csrf();
    $result = claim_withdraw($id, (int) $user['user_id']);
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($result['ok'] ? '/my/claims' : '/claims/' . $id);
}

/* ---------- Staff review ---------- */

function review_queue_index(): void
{
    $user = require_staff();
    view('review/queue', ['title' => 'Review claims', 'items' => review_queue((int) $user['user_id'])]);
}

function review_item(int $id): void
{
    $user = require_staff();
    $item = item_find_detail($id);
    if ($item === null || $item['report_type'] !== 'found') {
        abort(404);
    }
    if ((int) $item['reporter_id'] === (int) $user['user_id']) {
        flash_set('error', 'You posted this item, so another staff member must review its claims.');
        redirect('/review');
    }

    claim_mark_under_review($id);                    // "pending -> under_review when a reviewer opens it"

    view('review/item', [
        'title'    => 'Review claims',
        'item'     => $item,
        'claims'   => claims_for_review($id),
        'detailId' => evidence_item_detail_id($id),
        'me'       => $user,
    ]);
}

function claim_decision(int $id, string $decision): void
{
    $user = require_staff();
    require_csrf();
    $itemId = claim_lookup_item_id($id);
    if ($itemId === null) {
        abort(404);
    }

    $reason = post_str('reason');
    $result = $decision === 'approve'
        ? claim_approve($id, (int) $user['user_id'], $reason, post_str('high_value') === '1')
        : claim_reject($id, (int) $user['user_id'], $reason);

    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('/review/items/' . $itemId);
}

function claim_approve_submit(int $id): void { claim_decision($id, 'approve'); }
function claim_reject_submit(int $id): void  { claim_decision($id, 'reject'); }

/* ---------- Private evidence (BR-5) ---------- */

/** The exact viewing rules. Everything not allowed here is denied. */
function evidence_user_may_view(array $user, array $ev): bool
{
    $uid      = (int) $user['user_id'];
    $isStaff  = in_array($user['role'], ['custodian', 'admin'], true) && user_can_act($user);
    $isPoster = (int) $ev['reporter_id'] === $uid;
    $myClaim  = claim_has((int) $ev['item_id'], $uid);

    if ($ev['evidence_type'] === 'item_detail') {
        // Finder's hidden detail: poster, custodians, admins. Never a claimant.
        if ($myClaim !== null) {
            return false;
        }
        return $isPoster || $isStaff;
    }

    // Claim proof: its claimant, custodians, admins. Never the poster, never another claimant.
    if ($isPoster) {
        return false;
    }
    if ($myClaim !== null && (int) $ev['claimant_id'] !== $uid) {
        return false;
    }
    return (int) $ev['claimant_id'] === $uid || $isStaff;
}

function evidence_show(int $id): void
{
    $user = require_login();
    $ev   = evidence_find($id);
    if ($ev === null || !evidence_user_may_view($user, $ev)) {
        abort(404);                                   // same answer for "missing" and "not yours"
    }

    // Log BEFORE showing anything. If this insert fails, nothing is displayed (fail closed).
    audit_log((int) $user['user_id'], 'evidence_viewed', 'evidence', $id, 'page');

    $isStaff = in_array($user['role'], ['custodian', 'admin'], true);
    if ($isStaff) {
        $back = ['/review/items/' . (int) $ev['item_id'], 'Back to claim review'];
    } elseif ($ev['evidence_type'] === 'claim_proof') {
        $back = ['/claims/' . (int) $ev['claim_id'], 'Back to your claim'];
    } else {
        $back = ['/items/' . (int) $ev['item_id'] . '/edit', 'Back to your listing'];
    }

    header('Cache-Control: no-store');
    view('evidence/show', ['title' => 'Private evidence', 'ev' => $ev, 'back' => $back]);
}

function evidence_file(int $id): void
{
    $user = require_login();
    $ev   = evidence_find($id);
    if ($ev === null || empty($ev['file_reference']) || !evidence_user_may_view($user, $ev)) {
        abort(404);
    }

    $path = evidence_dir() . '/' . basename((string) $ev['file_reference']);   // basename() blocks ../ tricks
    if (!is_file($path)) {
        abort(404);
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $mime  = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!isset($types[$mime])) {
        abort(404);
    }

    audit_log((int) $user['user_id'], 'evidence_viewed', 'evidence', $id, 'file');   // before any output
    session_write_close();

    $disposition = $mime === 'application/pdf' ? 'attachment' : 'inline';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="evidence-' . $id . '.' . $types[$mime] . '"');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}