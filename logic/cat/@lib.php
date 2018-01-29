<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

define('KS_LOGIC_CLASS_LC_SUPPLIERS','vctSuppliers');
define('KS_LOGIC_CLASS_LC_ITEM_TYPES','vctItTyps');
define('KS_LOGIC_CLASS_LC_ITEM_OPTIONS','vctItemOptions');

$om = new fcCodeModule(__FILE__, 'dept.logic.php');
  $om->AddClass('vctDepts');
  $om->AddClass('vtTableAccess_Department');
  $om->AddClass('vcrDept_shop');

$om = new fcCodeModule(__FILE__, 'image.php');
  $om->AddClass('vctImages');
$om = new fcCodeModule(__FILE__, 'image-info.php');
  $om->AddClass('vcqtImagesInfo');
  $om->AddClass('vtTableAccess_ImagesInfo');

$om = new fcCodeModule(__FILE__, 'item.php');
  $om->AddClass('vcrItem');
  $om->AddClass('vctItems');
  $om->AddClass('vtTableAccess_Item');
$om = new fcCodeModule(__FILE__, 'item-type.php');
  $om->AddClass(KS_LOGIC_CLASS_LC_ITEM_TYPES);
  $om->AddClass('vtTableAccess_ItemType');
$om = new fcCodeModule(__FILE__, 'item-info.php');
  $om->AddClass('vcqtItemsInfo');
$om = new fcCodeModule(__FILE__, 'item-opt.php');
  $om->AddClass(KS_LOGIC_CLASS_LC_ITEM_OPTIONS);
    $om->AddClass('vtTableAccess_ItemOption');

$om = new fcCodeModule(__FILE__, 'ship-cost.php');
  $om->AddClass('clsShipCosts');
$om = new fcCodeModule(__FILE__, 'stats.php');
  $om->AddClass('clsStatsMgr');

$om = new fcCodeModule(__FILE__, 'supp.logic.php');
  $om->AddClass(KS_LOGIC_CLASS_LC_SUPPLIERS);
  $om->AddClass('vtTableAccess_Supplier');
$om = new fcCodeModule(__FILE__, 'supp.ittyps.php');
  $om->AddClass('vcqtSuppliertItemTypes');

$om = new fcCodeModule(__FILE__, 'title.logic.php');
  $om->AddClass('vctTitles');
  $om->AddClass('vcrTitle');
  $om->AddClass('vtTableAccess_Title');
$om = new fcCodeModule(__FILE__, 'title.info.php');
  $om->AddClass('vtQueryableTable_Titles');
  $om->AddClass('vcqtTitlesInfo');
$om = new fcCodeModule(__FILE__, 'title-topic.php');
  $om->AddClass('vctTitlesTopics');

$om = new fcCodeModule(__FILE__, 'topic.php');
  $om->AddClass('vctTopics');
