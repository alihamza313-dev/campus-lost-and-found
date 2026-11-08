<?php $user = current_user(); ?>
<section class="hero-home">
    <p class="eyebrow">Campus Lost &amp; Found</p>
    <h1>Lost something on campus? Found something? Start here.</h1>
    <p class="lead">Post what you lost or found, search what others have posted, and get belongings back to their real owners, safely and with a clear record.</p>
    <div class="hero-actions">
        <?php if ($user): ?>
            <a class="btn btn-lg" href="<?= e(url('/items/new?type=lost')) ?>">I lost something</a>
            <a class="btn btn-lg btn-secondary" href="<?= e(url('/items/new?type=found')) ?>">I found something</a>
        <?php else: ?>
            <a class="btn btn-lg" href="<?= e(url('/register')) ?>">Create a free account</a>
            <a class="btn btn-lg btn-secondary" href="<?= e(url('/login')) ?>">Log in</a>
        <?php endif; ?>
    </div>
    <p class="muted">Open to students and staff with a university email.</p>
</section>

<section class="section">
    <h2 class="section-title">Two situations, two short paths</h2>
    <p class="section-sub">Pick the one that matches you. Each takes a few minutes.</p>
    <div class="tracks">
        <div class="track track-lost">
            <h3>If you lost something</h3>
            <ol class="steps-list">
                <li><strong>Search first</strong><span>Someone may already have handed it in.</span></li>
                <li><strong>Report it</strong><span>Add a description, where and when, and a photo if you have one.</span></li>
                <li><strong>Watch your alerts</strong><span>You are told when someone posts a possible match or writes to you.</span></li>
            </ol>
            <a class="btn btn-secondary" href="<?= e(url($user ? '/items?type=found' : '/login')) ?>">Search found items</a>
        </div>
        <div class="track">
            <h3>If you found something</h3>
            <ol class="steps-list">
                <li><strong>Post it</strong><span>Describe it and where you found it. Keep one detail private to check the owner later.</span></li>
                <li><strong>A custodian reviews claims</strong><span>Owners describe the item. Staff compare it with your private detail.</span></li>
                <li><strong>Handover in person</strong><span>The owner shows student ID to a custodian and collects the item.</span></li>
            </ol>
            <a class="btn btn-secondary" href="<?= e(url($user ? '/items/new?type=found' : '/register')) ?>">Post a found item</a>
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Built so items go to the right person</h2>
    <div class="trust">
        <div><h3>Verified university emails</h3><p>Only verified accounts can post, claim or message.</p></div>
        <div><h3>Proof before handover</h3><p>Claims are reviewed by staff and a student ID is checked in person.</p></div>
        <div><h3>Private details stay private</h3><p>Contact details and ownership proof are never shown on public pages.</p></div>
    </div>
</section>

<section class="cta-band">
    <div>
        <h2>Not sure how it works?</h2>
        <p>Read the short guide, then jump in.</p>
    </div>
    <a class="btn" href="<?= e(url('/help')) ?>">How it works</a>
</section>
