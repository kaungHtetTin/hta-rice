<nav class="purchase-steps" aria-label="<?= e(t($wizardAria)) ?>">
<ol><?php foreach($wizardLabels as $step=>$label): ?>
<li><button class="wizard-step <?= $step===0?'is-active':'' ?>" type="button" data-go-step="<?= $step ?>" data-step-label="<?= e(t($label)) ?>" aria-label="<?= e(t($label)) ?>. <?= $step===0?t('Current step'):t('Upcoming step') ?>" <?= $step===0?'aria-current="step"':'' ?>>
<span class="wizard-step-circle" aria-hidden="true"><span class="wizard-step-number"><?= $step+1 ?></span></span>
<span class="wizard-step-copy"><span class="wizard-step-label"><?= e(t($label)) ?></span><span class="wizard-step-short" aria-hidden="true"><?= [t('Details'),t('Rice items'),t('Review')][$step] ?></span><small class="wizard-step-status"><?= $step===0?t('Current'):t('Next') ?></small></span>
</button></li><?php endforeach; ?></ol>
</nav>
