<?php

define('KS_CLASS_SHOP_SUPPLIERS','vctSuppliers_shop');
define('KS_CLASS_SHOP_DEPARTMENTS','vctDepts_shop');

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'dept.shop.php');
  $om->AddClass(KS_CLASS_SHOP_DEPARTMENTS);
  $om->AddClass('vcrDept_shop');
$om = new fcCodeModule(__FILE__, 'image.shop.php');
  $om->AddClass('vctImages_StoreUI');
$om = new fcCodeModule(__FILE__, 'supp.shop.php');
  $om->AddClass(KS_CLASS_SHOP_SUPPLIERS);
$om = new fcCodeModule(__FILE__, 'supp.shop.trait.php');
  $om->AddClass('vtrSupplierShop');
$om = new fcCodeModule(__FILE__, 'title.shop.php');
  $om->AddClass('vtTableAccess_Title_shop');
  $om->AddClass('vctShopTitles');
  $om->AddClass('vtrTitle_shop');
$om = new fcCodeModule(__FILE__, 'title-topic-ui.php');
  $om->AddClass('vctTitlesTopics_shop');
$om = new fcCodeModule(__FILE__, 'topic-ui.php');
  $om->AddClass('vctShopTopics');
  $om->AddClass('vcrShopTopic');
$om = new fcCodeModule(__FILE__, 'widgets.php');
  $om->AddClass('vcHideableSection');