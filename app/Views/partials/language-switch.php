<form class="language-switch" method="post" action="<?= e(url('locale')) ?>">
<?= csrf_field() ?><input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI']??url()) ?>">
<label><span class="sr-only"><?= e(t('Language')) ?></span><select name="locale" aria-label="<?= e(t('Language')) ?>">
<?php foreach(\App\Services\Locale::SUPPORTED as $code=>$label): ?><option value="<?= e($code) ?>" <?= locale()===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
</select></label><button class="button small" type="submit"><?= e(t('Apply')) ?></button>
</form>
