<?php
$operationKind=$m['kind'];
$operationId=$operationKind==='purchase'?$m['purchase_id']:($m['operation_id']??$m['id']);
$operationPermission=match($operationKind){'purchase'=>'purchases','transfer'=>'transfers',default=>'production'};
?>
<div class="actions">
<?php if($operationKind!=='purchase'||can('purchases.view')): ?><a class="button small" href="<?= e(url($operationKind.'/'.$operationId)) ?>"><?= e(t('Details')) ?></a><?php endif; ?>
<?php if(can($operationPermission.'.edit')): ?><a class="button small" href="<?= e(url($operationKind.'/'.$operationId.'/edit')) ?>"><?= e(t('Edit')) ?></a><?php endif; ?>
<?php if(can($operationPermission.'.delete') && isset($m['operation_version'])): ?><form method="post" action="<?= e(url($operationKind.'/'.$operationId.'/delete')) ?>" data-confirm="<?= e(t('Delete this entire {operation} record and reverse all its rice items? This cannot be undone.',['operation'=>t(ucfirst($operationKind))])) ?>"><?= csrf_field() ?><input type="hidden" name="version" value="<?= e($m['operation_version']) ?>"><button class="button small danger" type="submit"><?= e(t('Delete')) ?></button></form><?php endif; ?>
</div>
