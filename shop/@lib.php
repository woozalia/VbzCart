<?php

define('KS_CLASS_SHOP_SUPPLIERS','vctSuppliers_shop');
define('KS_CLASS_SHOP_DEPARTMENTS','vctDepts_shop');

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'dept.php');
  $om->AddClass(KS_CLASS_SHOP_DEPARTMENTS);
  $om->AddClass('vcrDept_shop');
$om = new fcCodeModule(__FILE__, 'image.php');
  $om->AddClass('vctImages_StoreUI');
$om = new fcCodeModule(__FILE__, 'supp.php');
  $om->AddClass(KS_CLASS_SHOP_SUPPLIERS);
$om = new fcCodeModule(__FILE__, 'supp.trait.php');
  $om->AddClass('vtrSupplierShop');
$om = new fcCodeModule(__FILE__, 'title.php');
  $om->AddClass('vtTableAccess_Title_shop');
  $om->AddClass('vctShopTitles');
  $om->AddClass('vtrTitle_shop');
$om = new fcCodeModule(__FILE__, 'title.dept.info.php');
  $om->AddClass('vcqtTitlesInfo_forDept_shop');
$om = new fcCodeModule(__FILE__, 'title.topic.info.php');
  $om->AddClass('vcqtTitlesInfo_forTopic_shop');
$om = new fcCodeModule(__FILE__, 'title-topic.php');
  $om->AddClass('vctTitlesTopics_shop');
$om = new fcCodeModule(__FILE__, 'topic.php');
  $om->AddClass('vctShopTopics');
  $om->AddClass('vcrShopTopic');
$om = new fcCodeModule(__FILE__, 'widgets.php');
  $om->AddClass('vcHideableSection');