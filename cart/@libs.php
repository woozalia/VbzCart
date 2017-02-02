<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'cart.data.fg.php');
  $om->AddClass('vcCartDataFieldGroup');
$om = new fcCodeModule(__FILE__, 'cart.data.fg-ep.php');
  $om->AddClass('vtCartData_EmailPhone');
$om = new fcCodeModule(__FILE__, 'cart.data.fg-na.php');
  $om->AddClass('vcCartData_NameAddress');
$om = new fcCodeModule(__FILE__, 'cart.data.fg.buyer.php');
  $om->AddClass('vcCartData_Buyer');
$om = new fcCodeModule(__FILE__, 'cart.data.fg.recip.php');
  $om->AddClass('vcCartData_Recip');
$om = new fcCodeModule(__FILE__, 'cart.data.mgr.php');
  $om->AddClass('vcCartDataManager');
$om = new fcCodeModule(__FILE__, 'cart.logic.php');
  $om->AddClass('vctShopCarts');
  $om->AddClass('vcrShopCart');
$om = new fcCodeModule(__FILE__, 'cart.shop.php');
  $om->AddClass('vctCarts_ShopUI');
  $om->AddClass('vcrCart_ShopUI');
$om = new fcCodeModule(__FILE__, 'cart-display.php');
  $om->AddClass('vcCartDisplay');
  $om->AddClass('vcCartDisplay_full');
  $om->AddClass('vcCartDisplay_full_shop');
  $om->AddClass('vcCartDisplay_full_ckout');
  $om->AddClass('vcCartDisplay_full_TEXT');
$om = new fcCodeModule(__FILE__, 'cart-display-line.php');
  $om->AddClass('vcCartLine_form');
  $om->AddClass('vcCartLine_static');
  $om->AddClass('vcCartTotal_admin');
  $om->AddClass('vcCartLine_text');
$om = new fcCodeModule(__FILE__, 'cart-lines.php');
  $om->AddClass('vctShopCartLines');
$om = new fcCodeModule(__FILE__, 'cart-log.php');
  $om->AddClass('clsCartLog');
