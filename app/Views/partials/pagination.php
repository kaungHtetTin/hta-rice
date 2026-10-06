<?php
$first=$pagination->count?($pagination->page-1)*$pagination->size+1:0;
$last=min($pagination->count,$pagination->page*$pagination->size);
$visible=array_unique([1,...range(max(1,$pagination->page-2),min($pagination->pages,$pagination->page+2)),$pagination->pages]);
sort($visible);
?>
<div class="panel-foot list-pagination">
<span><?= e(t('Showing {first}–{last} of {count} records · Page {page} of {pages}',['first'=>$first,'last'=>$last,'count'=>$pagination->count,'page'=>$pagination->page,'pages'=>$pagination->pages])) ?></span>
<nav class="list-page-links" aria-label="<?= e(t('List pagination')) ?>">
<?php if($pagination->page>1): ?><a class="button small" rel="prev" href="<?= e($pagination->link($pagination->page-1)) ?>"><?= e(t('Previous')) ?></a><?php else: ?><span class="button small" aria-disabled="true"><?= e(t('Previous')) ?></span><?php endif; ?>
<?php $previous=0; foreach($visible as $number): ?>
<?php if($previous && $number>$previous+1): ?><span class="page-gap" aria-hidden="true">…</span><?php endif; ?>
<?php if($number===$pagination->page): ?><span class="button small primary" aria-current="page" aria-label="<?= e(t('Page {number}',['number'=>$number])) ?>"><?= $number ?></span><?php else: ?><a class="button small" aria-label="<?= e(t('Page {number}',['number'=>$number])) ?>" href="<?= e($pagination->link($number)) ?>"><?= $number ?></a><?php endif; ?>
<?php $previous=$number; endforeach; ?>
<?php if($pagination->page<$pagination->pages): ?><a class="button small" rel="next" href="<?= e($pagination->link($pagination->page+1)) ?>"><?= e(t('Next')) ?></a><?php else: ?><span class="button small" aria-disabled="true"><?= e(t('Next')) ?></span><?php endif; ?>
</nav></div>
