<h1>Health check</h1>
<table class="table">
    <tr><th scope="row">PHP version</th><td><?= e($php) ?></td></tr>
    <tr><th scope="row">MySQL version</th><td><?= e($version) ?></td></tr>
    <tr><th scope="row">Tables in database</th><td><?= e($tables) ?> (expected 0 until Module 2)</td></tr>
</table> 