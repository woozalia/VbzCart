
<?php

define('KS_CLASS_SHOP_SUPPLIERS','vctSuppliers_shop');
define('KS_CLASS_SHOP_DEPARTMENTS','vctDepts_shop');

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'dept.logic.php');
  $om->AddClass('clsDepts');
  $om->AddClass('vtTableAccess_Department');
$om = new fcCodeModule(__FILE__, 'dept.shop.php');
  $om->AddClass(KS_CLASS_SHOP_DEPARTMENTS);
  $om->AddClass('vcrDept_shop');
$om = new fcCodeModule(__FILE__, 'folder.logic.php');
  $om->AddClass('clsVbzFolders');
$om = new fcCodeModule(__FILE__, 'image.php');
  $om->AddClass('clsImages');
$om = new fcCodeModule(__FILE__, 'image-info.php');
  $om->AddClass('vcqtImagesInfo');
$om = new fcCodeModule(__FILE__, 'image.shop.php');
  $om->AddClass('clsImages_StoreUI');
$om = new fcCodeModule(__FILE__, 'item.php');
  $om->AddClass('clsItem');
  $om->AddClass('clsItems');
  $om->AddClass('vtTableAccess_Item');
$om = new fcCodeModule(__FILE__, 'item-type.php');
  $om->AddClass('vctItTyps');
  $om->AddClass('vtTableAccess_ItemType');
$om = new fcCodeModule(__FILE__, 'item-info.php');
  $om->AddClass('vcqtItemsInfo');
$om = new fcCodeModule(__FILE__, 'item-opt.php');
  $om->AddClass('clsItOpts');
/* 2017-01-08 I *think* this file is no longer used.
$om = new fcCodeModule(__FILE__, 'page.php');
  $om->AddClass('clsCatPages');*/
$om = new fcCodeModule(__FILE__, 'ship-cost.php');
  $om->AddClass('clsShipCosts');
$om = new fcCodeModule(__FILE__, 'stats.php');
  $om->AddClass('clsStatsMgr');
$om = new fcCodeModule(__FILE__, 'stock.php');
  $om->AddClass('vctStockLines');
$om = new fcCodeModule(__FILE__, 'stock.info.php');
  $om->AddClass('vcqtStockLinesInfo');
$om = new fcCodeModule(__FILE__, 'supp.ittyps.php');
  $om->AddClass('vcqtSuppliertItemTypes');
$om = new fcCodeModule(__FILE__, 'supp.logic.php');
  $om->AddClass('vctSuppliers');
  $om->AddClass('vtTableAccess_Supplier');
$om = new fcCodeModule(__FILE__, 'supp.shop.php');
  $om->AddClass(KS_CLASS_SHOP_SUPPLIERS);
$om = new fcCodeModule(__FILE__, 'supp.shop.trait.php');
  $om->AddClass('vtrSupplierShop');
$om = new fcCodeModule(__FILE__, 'title.info.php');
  $om->AddClass('vtQueryableTable_Titles');
  $om->AddClass('vcqtTitlesInfo');
$om = new fcCodeModule(__FILE__, 'title.logic.php');
  $om->AddClass('vctTitles');
  $om->AddClass('vcrTitle');
  $om->AddClass('vtTableAccess_Title');
$om = new fcCodeModule(__FILE__, 'title.shop.php');
  $om->AddClass('vctShopTitles');
$om = new fcCodeModule(__FILE__, 'title-topic.php');
  $om->AddClass('vctTitlesTopics');
$om = new fcCodeModule(__FILE__, 'title-topic-ui.php');
  $om->AddClass('vctTitlesTopics_shop');
$om = new fcCodeModule(__FILE__, 'topic.php');
  $om->AddClass('clsTopics');
//  $om->AddClass('clsTitleTopic_Topics');
$om = new fcCodeModule(__FILE__, 'topic-ui.php');
  $om->AddClass('clsTopics_StoreUI');
  $om->AddClass('clsTopic_StoreUI');
