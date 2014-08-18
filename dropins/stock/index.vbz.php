<?php
/*
  PURPOSE: VbzCart drop-in descriptor for stock management
  HISTORY:
    2013-11-28 started
    2013-12-15 mostly working (haven't tested editing)
*/

// CONSTANTS

define('KS_ACTION_STOCK_PLACE','plc');
define('KS_ACTION_STOCK_BIN','bin');
define('KS_ACTION_STOCK_LINE','sitem');

define('KS_CLASS_STOCK_LINES','VCT_StkLines');
define('KS_CLASS_STOCK_LINE_INFO','VCR_StkLine_info');
define('KS_CLASS_STOCK_BINS','VCM_StockBins');
define('KS_CLASS_STOCK_LINE_LOG','clsStkLog');
define('KS_CLASS_STOCK_BIN_LOG','VbzStockBinLog');
define('KS_CLASS_STOCK_PLACES','VCM_StockPlaces');

define('KS_TBL_STOCK_LINES','stk_items');

// MENU ADDITIONS

$om = new clsMenuFolder(NULL, '*stk','Stock','Stock & Inventory','stock/inventory functions');
  $om->NeedPermission(KS_PERM_STOCK_VIEW);
  $omi = new clsMenuLink($om,KS_ACTION_STOCK_PLACE,'Places','Stock Places','places where bins (stock boxes) may be found');
    $omi->Controller(KS_CLASS_STOCK_PLACES);
    $omi->NeedPermission(KS_PERM_STOCK_ADMIN);
  $omi = new clsMenuLink($om,KS_ACTION_STOCK_BIN,'Bins','Stock Bins','boxes for stock');
    $omi->Controller(KS_CLASS_STOCK_BINS);
    $omi->NeedPermission(KS_PERM_STOCK_VIEW);
  $omi = new clsMenuHidden($om,KS_ACTION_STOCK_LINE,'Stock Lines');
    $omi->Controller(KS_CLASS_STOCK_LINES);
    $omi->NeedPermission(KS_PERM_STOCK_VIEW);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.stock',
  'descr'	=> 'stock/inventory management functions',
  'version'	=> '0.0',
  'date'	=> '2014-03-22',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'bin.php'	=> array(KS_CLASS_STOCK_BINS),
    'line.php'	=> array(KS_CLASS_STOCK_LINES),
    'line-info.php'	=> array(KS_CLASS_STOCK_LINE_INFO),
    'place.php'	=> array(KS_CLASS_STOCK_PLACES),
    'log.php'	=> array(KS_CLASS_STOCK_LINE_LOG,KS_CLASS_STOCK_BIN_LOG)
     ),
  'menu'	=> $om,
  'permit'	=> array('admin'),	// groups who are allowed access
  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );
