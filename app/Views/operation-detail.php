<?php $permission=match($kind){'purchase'=>'purchases', 'transfer'=>'transfers', default=>'production'}; ?>
<section class="panel">
<div class="panel-heading"><div><div class="eyebrow"><?= e(t(ucfirst($kind).' record')) ?></div><h2><?= e($record['reference']??t(ucfirst($kind)).' #'.$record['id']) ?></h2></div><div class="actions">
<a class="button" href="<?= e(url($kind==='purchase'?'purchases':'stock-movements')) ?>"><?= e(t('Back to list')) ?></a>
<?php if($kind==='purchase'): ?><a class="button" href="<?= e(url('voucher/'.$record['id'])) ?>"><?= e(t('View / Print voucher')) ?></a><?php endif; ?>
<?php if(can($permission.'.edit')): ?><a class="button primary" href="<?= e(url($kind.'/'.$record['id'].'/edit')) ?>"><?= e(t('Edit')) ?></a><?php endif; ?>
<?php if(can($permission.'.delete')): ?><form method="post" action="<?= e(url($kind.'/'.$record['id'].'/delete')) ?>" data-confirm="<?= e(t('Delete this entire {operation} record and reverse its stock effects? This cannot be undone.',['operation'=>t(ucfirst($kind))])) ?>"><?= csrf_field() ?><input type="hidden" name="version" value="<?= e($record['version']) ?>"><button class="button danger" type="submit"><?= e(t('Delete')) ?></button></form><?php endif; ?>
</div></div>
<div class="form-grid operation-metadata">
<div><span class="muted"><?= e(t('Date')) ?></span><p><?= e($record['purchased_on']??$record['occurred_on']) ?></p></div>
<div><span class="muted"><?= $kind==='purchase'?t('Receiving warehouse'):t('Source warehouse') ?></span><p><?= e($warehouse) ?></p></div>
<?php if($destination): ?><div><span class="muted"><?= e(t('Destination warehouse')) ?></span><p><?= e($destination) ?></p></div><?php endif; ?>
<?php if($supplier): ?><div><span class="muted"><?= e(t('Supplier')) ?></span><p><?= e($supplier) ?></p></div><?php endif; ?>
<div><span class="muted"><?= e(t('Recorded by')) ?></span><p><?= e($recordedBy) ?> · <?= e($record['created_at']) ?></p></div>
<div><span class="muted"><?= e(t('Total quantity')) ?></span><p><strong><?= e(num($total)) ?> <?= e(t('တင်း')) ?></strong> · <?= count($items) ?> <?= e(t('rice items')) ?></p></div>
<?php if($kind==='purchase'): ?><div><span class="muted"><?= e(t('Purchase amount / paid at purchase')) ?></span><p><?= e(money($record['amount'])) ?> / <?= e(money($record['amount_paid'])) ?> <?= e(t('MMK')) ?></p></div><?php endif; ?>
</div>
<div class="table-wrap"><table><thead><tr><th><?= e(t('No.')) ?></th><th><?= e(t('Rice type')) ?></th><th class="number"><?= e(t('Quantity (တင်း)')) ?></th><?php if($kind==='purchase'): ?><th class="number"><?= e(t('Weight (ပေါင်)')) ?></th><th class="number"><?= e(t('Unit price (MMK)')) ?></th><th class="number"><?= e(t('Amount (MMK)')) ?></th><?php endif; ?></tr></thead><tbody>
<?php foreach($items as $index=>$item): ?><tr><td><?= $index+1 ?></td><td><?= e($item['rice']) ?></td><td class="number"><?= e(num($item['quantity'])) ?></td><?php if($kind==='purchase'): ?><td class="number"><?= $item['weight_lb']===null?'—':e(num($item['weight_lb'])) ?></td><td class="number"><?= e(money($item['unit_price'])) ?></td><td class="number"><?= e(money($item['amount'])) ?></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div>
<?php if($record['notes']): ?><div class="panel-foot" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= e($record['notes']) ?></div><?php endif; ?>
</section>
