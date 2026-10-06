<?php $filters=$pagination->filters; ?>
<?php foreach($pagination->errors as $error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
<form class="list-filters" method="get" action="<?= e(url($pagination->route)) ?>" aria-label="<?= e(t('List filters')) ?>">
<div class="list-filter-scroll"><div class="list-filter-fields">
<label class="list-filter-search"><?= e(t('Search')) ?><input type="search" name="q" value="<?= e($filters['q']??'') ?>" placeholder="<?= e(match($pagination->route){'purchases'=>t('Voucher, supplier or rice type'),'suppliers'=>t('Name, phone or address'),default=>t('Rice type, voucher, staff or notes')}) ?>"></label>
<?php if($pagination->route!=='suppliers'): ?>
<label><?= e(t('From date')) ?><input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
<label><?= e(t('To date')) ?><input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
<?php foreach(['warehouse'=>[t('Warehouse'),$warehouses],'rice_type'=>[t('Rice type'),$riceTypes]] + ($pagination->route==='purchases'?['supplier'=>[t('Supplier'),$suppliers]]:[]) as $key=>[$label,$options]): ?>
<label><?= e(t($label)) ?><select name="<?= e($key) ?>"><option value="0"><?= e(t(match($key){'warehouse'=>'All warehouses','supplier'=>'All suppliers',default=>'All rice types'})) ?></option><?php foreach($options as $option): ?><option value="<?= e($option['id']) ?>" <?= (int)$filters[$key]===(int)$option['id']?'selected':'' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select></label>
<?php endforeach; ?>
<?php if($pagination->route==='stock-movements'): ?><label><?= e(t('Operation')) ?><select name="kind"><option value=""><?= e(t('All operations')) ?></option><?php foreach(['purchase','transfer','production'] as $kind): ?><option value="<?= e($kind) ?>" <?= $filters['kind']===$kind?'selected':'' ?>><?= e(t(ucfirst($kind))) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<?php elseif(can('suppliers.credit')): ?><label><?= e(t('Credit balance')) ?><select name="balance"><option value=""><?= e(t('All suppliers')) ?></option><option value="outstanding" <?= $filters['balance']==='outstanding'?'selected':'' ?>><?= e(t('Outstanding balance')) ?></option><option value="settled" <?= $filters['balance']==='settled'?'selected':'' ?>><?= e(t('No balance to pay')) ?></option></select></label><?php endif; ?>
<label class="list-filter-size"><?= e(t('Rows per page')) ?><select name="per_page"><?php foreach([10,25,50,100] as $size): ?><option value="<?= $size ?>" <?= $pagination->size===$size?'selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label>
</div></div>
<div class="list-filter-actions"><button class="button primary" type="submit"><?= e(t('Apply filters')) ?></button><a class="button" href="<?= e(url($pagination->route)) ?>"><?= e(t('Reset')) ?></a></div>
</form>
