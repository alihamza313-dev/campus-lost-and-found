<section class="hero-home">
    <div>
        <p class="eyebrow">Campus Lost &amp; Found</p>
        <h1>Lost it on campus? Someone may have already handed it in.</h1>
        <p class="lead">Search what has been found, report what you lost, and get things back to the right person. Staff check every claim before anything is handed over.</p>
        <div class="hero-actions">
            <a class="btn btn-lg" href="<?= e(url('/register')) ?>">Create a free account</a>
            <a class="btn btn-lg btn-secondary" href="<?= e(url('/login')) ?>">Log in</a>
        </div>
        <p class="muted">You need a university email to sign up.</p>
    </div>

    <div aria-hidden="true">
        <div class="tag-sample">
            <span class="badge badge-found">Found</span>
            <h2>Black Casio calculator</h2>
            <p class="meta">Library, 2nd floor &middot; 6 Nov 2026</p>
            <ol>
                <li class="is-done">Posted by the person who found it</li>
                <li class="is-done">Owner sends a claim</li>
                <li class="is-now">Custodian checks the details</li>
                <li>Owner shows student ID and collects it</li>
            </ol>
        </div>
        <p class="tag-caption">Every item follows this same path.</p>
    </div>
</section>

<section class="section">
    <h2 class="section-title">What do you need to do?</h2>
    <p class="section-sub">Pick the one that matches your situation.</p>
    <div class="tracks">
        <div class="track track-lost">
            <h3>I lost something</h3>
            <ol class="steps-list">
                <li><strong>Search the found items</strong><span>Someone may have handed it in already.</span></li>
                <li><strong>Report it if you do not see it</strong><span>Add what it looks like, where you lost it and when.</span></li>
                <li><strong>Wait for an alert</strong><span>We tell you when someone posts a possible match or writes to you.</span></li>
            </ol>
            <a class="btn btn-lost" href="<?= e(url('/register')) ?>">Start searching</a>
        </div>
        <div class="track">
            <h3>I found something</h3>
            <ol class="steps-list">
                <li><strong>Post it</strong><span>Say what it is and where you found it. Add one private detail so the real owner can be checked.</span></li>
                <li><strong>Staff review the claims</strong><span>You do not have to judge who is the owner.</span></li>
                <li><strong>Handover</strong><span>The owner shows student ID to a custodian and collects the item.</span></li>
            </ol>
            <a class="btn" href="<?= e(url('/register')) ?>">Post a found item</a>
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">How we keep it safe</h2>
    <div class="trust">
        <div><h3>Only university members</h3><p>Accounts must be verified with a university email before they can post, claim or message.</p></div>
        <div><h3>Proof before handover</h3><p>A custodian reviews each claim, and the student ID is checked in person.</p></div>
        <div><h3>Contact details stay private</h3><p>Phone numbers and emails never appear on public pages. People talk through in-app messages.</p></div>
    </div>
</section>

<section class="cta-band">
    <div>
        <h2>Want to see the full process first?</h2>
        <p>The guide takes two minutes to read.</p>
    </div>
    <a class="btn" href="<?= e(url('/help')) ?>">Read how it works</a>
</section>