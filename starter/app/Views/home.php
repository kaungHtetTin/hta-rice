<h1><?= e(config('app.name')) ?></h1>
<p>Your Mini PHP application is ready.</p>
<?php if (isset($name)): ?><p>Hello, <?= e($name) ?>!</p><?php endif; ?>
<form action="<?= e(url('welcome')) ?>" method="post">
    <?= csrf_field() ?>
    <label for="name">Your name</label>
    <input id="name" name="name" value="<?= e(old('name')) ?>" required>
    <?php if (isset($_SESSION['errors']['name'])): ?><p role="alert"><?= e($_SESSION['errors']['name']) ?></p><?php endif; ?>
    <button type="submit">Say hello</button>
</form>
<p>Start with <code>routes/web.php</code>, <code>app/Controllers</code>, and <code>app/Views</code>.</p>
