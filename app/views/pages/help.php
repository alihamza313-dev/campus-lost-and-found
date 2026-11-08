<div class="help-layout">
    <h1>How Campus Lost &amp; Found works</h1>
    <p class="lead">Every item follows one path: a <strong>listing</strong>, then a <strong>claim</strong>, then a <strong>handover</strong>.</p>

    <ol class="steps-list">
        <li><strong>Listing</strong><span>Someone reports a lost or found item. Found items are public so owners can find them.</span></li>
        <li><strong>Claim</strong><span>The owner describes details only they would know. A custodian reviews it.</span></li>
        <li><strong>Handover</strong><span>If the claim is approved, a custodian arranges a pickup. The owner shows student ID and collects the item.</span></li>
    </ol>

    <h2>Which one are you?</h2>
    <div class="tracks">
        <div class="track track-lost">
            <h3>I lost something</h3>
            <p>Search the found items first. If it is not there, report it so finders can message you.</p>
            <a class="btn btn-lost" href="<?= e(url('/items?type=found')) ?>">Search found items</a>
        </div>
        <div class="track">
            <h3>I found something</h3>
            <p>Post it and keep one detail private. Custodians will check who the real owner is.</p>
            <a class="btn" href="<?= e(url('/items/new?type=found')) ?>">Post a found item</a>
        </div>
    </div>

    <h2 id="staff">What custodians do</h2>
    <p>Custodians are university staff. They read every claim, compare it with the finder&rsquo;s private detail, approve or reject it with a written reason, and hand the item over after checking student ID. A custodian can never review their own claim or a listing they posted.</p>

    <h2 id="faq">Common questions</h2>

    <details class="faq"><summary>Why can&rsquo;t I post or claim yet?</summary>
        <div><p>You need to verify your university email first. Use the yellow bar at the top of any page to send a new link.</p></div></details>
    <details class="faq"><summary>What should I write in a claim?</summary>
        <div><p>Contents, marks, stickers, serial numbers or anything a stranger could not guess. You can attach a receipt or photo as private proof.</p></div></details>
    <details class="faq"><summary>Who can see my contact details?</summary>
        <div><p>Nobody on public pages. Use Messages to talk to a poster or claimant without sharing your phone or email.</p></div></details>
    <details class="faq"><summary>My claim says &ldquo;under review&rdquo;. What now?</summary>
        <div><p>A custodian is comparing your statement with the finder&rsquo;s private detail. You get an alert when there is a decision.</p></div></details>
    <details class="faq"><summary>My claim was approved. What next?</summary>
        <div><p>A custodian arranges the handover. Bring your student ID. The item is only handed over after the ID is checked.</p></div></details>
    <details class="faq"><summary>Can I change or delete a listing?</summary>
        <div><p>You can edit a listing while it is still open, or close it. Open &ldquo;My listings&rdquo; to manage yours.</p></div></details>
    <details class="faq"><summary>Someone posted something inappropriate.</summary>
        <div><p>Use &ldquo;Report this listing&rdquo; on the item page. An administrator will review it.</p></div></details>
</div>