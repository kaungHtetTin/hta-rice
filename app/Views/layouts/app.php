<?php $company=\App\Services\VoucherSettings::get(); $companyLogo=\App\Services\VoucherSettings::logoUrl($company); ?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t($title ?? config('app.name'))) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/voucher.css')) ?>">
<?= locale_scripts() ?>
</head>
<body class="admin-root" data-theme="light" data-density="compact">
<a href="#main" class="skip-link"><?= e(t('Skip to content')) ?></a>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url()) ?>"><?php if($companyLogo): ?><img class="brand-logo" src="<?= e($companyLogo) ?>" alt=""><?php else: ?><span class="brand-mark">稲</span><?php endif; ?><span><?= e($company['business_name']) ?><small><?= e(t('STOCK & OPERATIONS')) ?></small></span></a>
    <nav aria-label="<?= e(t('Main navigation')) ?>">
    <?php
    $navigationGroups = [
        'Overview'=>[['',t('Overview'),'◫','inventory.view']],
        'Stock operations'=>[
            ['inventory',t('Inventory balances'),'▦','inventory.view'],
            ['stock-movements',t('Stock movements'),'≡','inventory.view'],
            ['transfer',t('Stock transfers'),'⇄','transfers.create'],
            ['production',t('Production'),'⚙','production.create'],
            ['warehouses',t('Warehouses'),'⌂','settings.manage'],
            ['rice-types',t('Rice types'),'▧','settings.manage'],
        ],
        'Purchasing'=>[
            ['purchase/new',t('New purchase'),'＋','purchases.create'],
            ['purchases',t('Purchase register'),'▤','purchases.view'],
            ['suppliers',t('Suppliers'),'♧','settings.manage'],
            ['supplier-credits',t('Supplier credit'),'▤','suppliers.credit'],
            ['reports',t('Purchase reports'),'▥','reports.view'],
        ],
        'Administration'=>[
            ['voucher-settings',t('Voucher settings'),'▤','settings.manage'],
            ['users',t('Team & permissions'),'♙','users.manage'],
        ],
        'My account'=>[['profile',t('Profile settings'),'♙',null]],
    ];
    $creditDetail=(bool)preg_match('#/suppliers/\d+/credit/?$#',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'');
    $groupIndex=0;
    foreach($navigationGroups as $groupLabel=>$items):
        $items=array_values(array_filter($items,fn($item)=>$item[3]===null || can($item[3])));
        if(!$items)continue;
        $groupId='navigation-group-'.(++$groupIndex);
    ?>
    <section class="nav-group" aria-labelledby="<?= e($groupId) ?>"><div class="nav-label" id="<?= e($groupId) ?>"><?= e(t($groupLabel)) ?></div>
    <?php foreach($items as [$path,$label,$icon,$permission]):
        $active=$path===''?(trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/')===trim(parse_url(url(),PHP_URL_PATH)??'','/')?'is-active':''):active_nav($path);
        if($creditDetail && $path==='suppliers')$active='';
        if($creditDetail && $path==='supplier-credits')$active='is-active';
        if($path==='purchases' && active_nav('voucher'))$active='is-active';
    ?>
    <a class="nav-item <?= $active ?>" href="<?= e(url($path)) ?>" aria-label="<?= e(t($label)) ?>" title="<?= e(t($label)) ?>" <?= $active?'aria-current="page"':'' ?>><span aria-hidden="true"><?= $icon ?></span><span class="nav-copy"><?= e(t($label)) ?></span></a>
    <?php endforeach; ?></section>
    <?php endforeach; ?>
    </nav>
    <div class="sidebar-note"><span class="status-dot"></span> <?= e(t('Your rice, accounted for.')) ?><small><?= e(t('Inventory measured in တင်း')) ?></small></div>
</aside>
<button class="nav-overlay" aria-label="<?= e(t('Close navigation')) ?>" hidden></button>
<div class="shell">
    <header class="topbar"><div class="topbar-left"><button class="icon-button" id="nav-toggle" aria-label="<?= e(t('Toggle navigation')) ?>" aria-expanded="false" aria-controls="sidebar">☰</button><span class="breadcrumb"><?= e(t('Workspace')) ?> <span>/</span> <strong><?= e(t($title)) ?></strong></span></div><div class="topbar-actions"><?php require __DIR__.'/../partials/language-switch.php'; ?><button class="icon-button" id="density-toggle" aria-label="<?= e(t('Toggle comfortable density')) ?>" title="<?= e(t('Toggle density')) ?>">↕</button><button class="icon-button" id="theme-toggle" aria-label="<?= e(t('Toggle dark theme')) ?>" title="<?= e(t('Toggle theme')) ?>">◐</button><span class="avatar"><?= e(strtoupper(mb_substr(current_user()['name'],0,1,'UTF-8'))) ?></span><span class="profile"><?= e(current_user()['name']) ?><small><?= current_user()['role']==='owner'?t('Owner / Super admin'):t('Staff') ?></small></span><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="text-button" type="submit"><?= e(t('Sign out')) ?></button></form></div></header>
    <main id="main"><div class="page-heading"><div><div class="eyebrow"><?= e($company['business_name']) ?> / <?= e(locale_date('d M Y')) ?></div><h1><?= e(t($title)) ?></h1><p><?= e(['Overview'=>t('A clear picture of your rice business.'),'Inventory'=>t('Live stock balances across your warehouses.'),'Purchases'=>t('Every purchase, from supplier to warehouse.'),'Purchase reports'=>t('Understand your purchase spending over time.'),'Team & permissions'=>t('Give each staff member the access they need.'),'Business settings'=>t('Manage the foundations of your business.'),'New purchase'=>t('Receive rice and record your purchase amount.'),'Transfer stock'=>t('Move rice between warehouses.'),'Go to production'=>t('Record rice used for production.'),'Purchase voucher'=>t('A printable record of your rice purchase.')][$title]??'') ?></p></div><?php if(in_array($title,['Overview','Purchases','Inventory']) && can('purchases.create')): ?><a class="button primary" href="<?= e(url('purchase/new')) ?>"><?= e(t('＋ New purchase')) ?></a><?php endif; ?></div>
    <?php if(isset($_SESSION['flash'])): $flash=$_SESSION['flash']; unset($_SESSION['flash']); ?><div class="flash <?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div><?php endif; ?>
    <?= $content ?>
    <footer class="page-footer"><?= e($company['business_name']) ?> <span><?= e(t('Stock unit: တင်း · Currency: MMK · Asia/Rangoon')) ?></span></footer></main>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
