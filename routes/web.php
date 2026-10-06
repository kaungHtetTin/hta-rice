<?php

use App\Controllers\AuthController as Auth;
use App\Controllers\AppController as App;
use App\Controllers\MasterDataController as Master;
use App\Controllers\VoucherSettingsController as VoucherSettings;
use App\Controllers\ProfileController as Profile;
$router->get('/login',[Auth::class,'login']);
$router->get('/company-logo',[VoucherSettings::class,'logo']);
$router->post('/login',[Auth::class,'authenticate']);
$router->get('/setup',[Auth::class,'setup']);
$router->post('/setup',[Auth::class,'createOwner']);
$router->post('/logout',[Auth::class,'logout'],true);
$router->get('/profile',[Profile::class,'index'],true);
$router->post('/profile',[Profile::class,'save'],true);
$router->post('/profile/password',[Profile::class,'password'],true);
$router->get('/',[App::class,'dashboard'],true);
$router->get('/inventory',[App::class,'inventory'],true,'inventory.view');
$router->get('/stock-movements',[App::class,'stockMovements'],true,'inventory.view');
$router->get('/purchases',[App::class,'purchases'],true,'purchases.view');
$router->get('/purchase/new',[App::class,'purchaseForm'],true,'purchases.create');
$router->post('/purchase/new',[App::class,'savePurchase'],true,'purchases.create');
$router->get('/voucher/{id}',[App::class,'voucher'],true,'purchases.view');
$router->get('/transfer',[App::class,'transferForm'],true,'transfers.create');
$router->post('/transfer',[App::class,'saveTransfer'],true,'transfers.create');
$router->get('/production',[App::class,'productionForm'],true,'production.create');
$router->post('/production',[App::class,'saveProduction'],true,'production.create');
$router->get('/reports',[App::class,'reports'],true,'reports.view');
$router->get('/settings',[App::class,'settings'],true,'settings.manage');
$router->post('/settings',[App::class,'saveSetting'],true,'settings.manage');
$router->get('/voucher-settings',[VoucherSettings::class,'index'],true,'settings.manage');
$router->post('/voucher-settings',[VoucherSettings::class,'save'],true,'settings.manage');
$router->get('/voucher-settings/preview',[VoucherSettings::class,'preview'],true,'settings.manage');
foreach(['warehouses','rice-types','suppliers'] as $type) {
    $router->get('/'.$type,fn()=>(new Master())->index($type),true,'settings.manage');
    $router->get('/'.$type.'/{id}/edit',fn($id)=>(new Master())->index($type,$id),true,'settings.manage');
    $router->post('/'.$type,fn()=>(new Master())->save($type),true,'settings.manage');
    $router->post('/'.$type.'/{id}/delete',fn($id)=>(new Master())->delete($type,$id),true,'settings.manage');
}
$router->get('/supplier-credits',[Master::class,'credits'],true,'suppliers.credit');
$router->get('/suppliers/{id}/credit',[Master::class,'credit'],true,'suppliers.credit');
$router->post('/suppliers/{id}/payments',[Master::class,'payment'],true,'suppliers.credit');
$router->get('/users',[App::class,'users'],true,'users.manage');
$router->post('/users',[App::class,'saveUser'],true,'users.manage');

foreach(['purchase'=>'purchases','transfer'=>'transfers','production'=>'production'] as $kind=>$permission) {
    $router->get('/'.$kind.'/{id}',fn($id)=>(new \App\Controllers\OperationController())->detail($kind,$id),true,$kind==='purchase'?'purchases.view':'inventory.view');
    $router->get('/'.$kind.'/{id}/edit',fn($id)=>(new \App\Controllers\OperationController())->edit($kind,$id),true,$permission.'.edit');
    $router->post('/'.$kind.'/{id}/edit',fn($id)=>(new \App\Controllers\OperationController())->update($kind,$id),true,$permission.'.edit');
    $router->post('/'.$kind.'/{id}/delete',fn($id)=>(new \App\Controllers\OperationController())->delete($kind,$id),true,$permission.'.delete');
}

$router->post('/locale',[\App\Controllers\LocaleController::class,'change']);
