<?php
/*
  PURPOSE: VbzCart drop-in descriptor for stock management
  HISTORY:
    2013-11-28 started
    2013-12-15 mostly working (haven't tested editing)
    2016-01-09 adding Warehouse classes
      We probably need a more specific term to replace "Places". "Rooms"? "Areas"?
    2016-12-11 adapting to revised dropin system
    2017-01-01 adapting again
*/

// CONSTANTS

define('KS_ACTION_STOCK_WAREHOUSE','whse');
define('KS_ACTION_STOCK_PLACE','plc');
define('KS_ACTION_STOCK_BIN','bin');
define('KS_ACTION_STOCK_LINE','sitem');
define('KS_ACTION_STOCK_LINE_LOG','sllog');

define('KS_CLASS_STOCK_LINES','VCT_StkLines');
define('KS_CLASS_STOCK_LINES_INFO','vctqaStockLinesInfo');
define('KS_CLASS_STOCK_LINE_INFO','vcrqaStockLineInfo');
define('KS_CLASS_STOCK_BINS','VCM_StockBins');
define('KS_CLASS_STOCK_LINE_LOG','clsStkLog');
define('KS_CLASS_STOCK_BIN_LOG','VbzStockBinLog');
define('KS_CLASS_STOCK_PLACES','VCM_StockPlaces');
define('KS_LOGIC_CLASS_STOCK_WAREHOUSES','vctlWarehouses');
define('KS_ADMIN_CLASS_STOCK_WAREHOUSES','vctaWarehouses');

define('KS_TBL_STOCK_LINES','stk_items');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Stock'));
  
  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_STOCK_WAREHOUSE,'Warehouses','buildings containing stock'));
    $omi->SetPageTitle('Stock Warehouses');
    $omi->SetActionClass(KS_ADMIN_CLASS_STOCK_WAREHOUSES);
    $omi->SetRequiredPrivilege(KS_PERM_STOCK_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_STOCK_PLACE,'Places','places where bins may be found'));
    $omi->SetPageTitle('Stock Places');
    $omi->SetActionClass(KS_CLASS_STOCK_PLACES);
    $omi->SetRequiredPrivilege(KS_PERM_STOCK_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_STOCK_BIN,'Bins','boxes for stock'));
    $omi->SetPageTitle('Stock Bins');
    $omi->SetActionClass(KS_CLASS_STOCK_BINS);
    $omi->SetRequiredPrivilege(KS_PERM_STOCK_VIEW);

  // maybe this should be hidden?
  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_STOCK_LINE,'Stock Lines','individual stock entries'));
    $omi->SetActionClass(KS_CLASS_STOCK_LINES);
    $omi->SetRequiredPrivilege(KS_PERM_STOCK_VIEW);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_STOCK_LINE_LOG,'Line Log','stock line log'));
    $omi->SetPageTitle('Stock Line event log');
    $omi->SetActionClass(KS_CLASS_STOCK_LINE_LOG);
    $omi->SetRequiredPrivilege(KS_PERM_STOCK_VIEW);

/* 2016-12-11 old dropin system
$om = new fcMenuFolder($oRoot,'*stk','Stock','Stock & Inventory','stock/inventory functions');
  $om->NeedPermission(KS_PERM_STOCK_VIEW);
  $omi = new fcMenuLink($om,KS_ACTION_STOCK_WAREHOUSE,'Warehouses','Stock Warehouses','buildings containing stock');
    $omi->Controller(KS_ADMIN_CLASS_STOCK_WAREHOUSES);
    $omi->NeedPermission(KS_PERM_STOCK_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_STOCK_PLACE,'Places','Stock Places','places where bins may be found');
    $omi->Controller(KS_CLASS_STOCK_PLACES);
    $omi->NeedPermission(KS_PERM_STOCK_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_STOCK_BIN,'Bins','Stock Bins','boxes for stock');
    $omi->Controller(KS_CLASS_STOCK_BINS);
    $omi->NeedPermission(KS_PERM_STOCK_VIEW);
  $omi = new fcMenuHidden($om,KS_ACTION_STOCK_LINE,'Stock Lines');
    $omi->Controller(KS_CLASS_STOCK_LINES);
    $omi->NeedPermission(KS_PERM_STOCK_VIEW);
  $omi = new fcMenuLink($om,KS_ACTION_STOCK_LINE_LOG,'Line Log','stock line log');
    $omi->Controller(KS_CLASS_STOCK_LINE_LOG);
    $omi->NeedPermission(KS_PERM_STOCK_VIEW);
*/
    
// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.stock',
  'descr'	=> 'stock/inventory management functions',
  'version'	=> '0.0',
  'date'	=> '2016-01-09',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'bin.php'		=> array(KS_CLASS_STOCK_BINS),
    'bin.form.php'	=> 'fcForm_Bin',
    'line.php'		=> array(KS_CLASS_STOCK_LINES),
    'line.form.php'	=> 'fcForm_StockLine',
    'line-info.php'	=> array(KS_CLASS_STOCK_LINES_INFO,KS_CLASS_STOCK_LINE_INFO),
    'place.php'		=> array(KS_CLASS_STOCK_PLACES),
    'whse.logic.php'	=> array(KS_LOGIC_CLASS_STOCK_WAREHOUSES),
    'whse.admin.php'	=> array(KS_ADMIN_CLASS_STOCK_WAREHOUSES),
    'log.php'		=> array(KS_CLASS_STOCK_LINE_LOG,KS_CLASS_STOCK_BIN_LOG)
     ),
  'menu'	=> $om,
  );
