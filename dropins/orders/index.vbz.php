<?php
/*
  PURPOSE: VbzCart drop-in descriptor for order management
  HISTORY:
    2013-11-28 started
*/

// CONSTANTS

define('KS_PAGE_KEY_ORDER',		'ord');
define('KS_PAGE_KEY_ORDER_MSG',	'omsg');

define('KS_CLASS_ORDERS',		'VCM_Orders');
define('KS_CLASS_ORDER_CHARGES',	'VCT_OrderChgs');
define('KS_CLASS_ORDER_ITEMS',		'VCA_OrderItems');
define('KS_CLASS_ORDER_MSGS',		'VCT_OrderMsgs');
define('KS_CLASS_ORDER_PULLS',		'VCT_OrderPulls');
define('KS_CLASS_ORDER_TRXS',		'VCT_OrderTrxacts');
define('KS_CLASS_ORDER_TRX_TYPES',	'VCT_OrderTrxTypes');

// MENU

$om = new clsMenuFolder(NULL, '*ord','Orders','Customer Orders','manage customer orders');
  $om->NeedPermission(KS_PERM_ORDER_ADMIN);
  $omi = new clsMenuLink($om, KS_PAGE_KEY_ORDER,'Orders','Order Management','management of customer orders');
    $omi->Controller(KS_CLASS_ORDERS);
    $omi->NeedPermission(KS_PERM_ORDER_ADMIN);
  $omi = new clsMenuLink($om, KS_PAGE_KEY_ORDER_MSG,'Messages','Order Messages','messages attached to customer orders');
    $omi->Controller(KS_CLASS_ORDER_MSGS);
    $omi->NeedPermission(KS_PERM_ORDER_ADMIN);
/*
$om = new clsMenuFolder(NULL, '*ord','Orders','Order Management','management of customer orders');
  $omi = new clsMenuLink($om,KS_ACTION_STOCK_PLACE,'Places','Stock Places','places where bins (stock boxes) may be found');
    $omi->Controller('VCM_StockPlaces');
  $omi = new clsMenuLink($om,KS_ACTION_STOCK_BIN,'Bins','Stock Bins','boxes for stock');
    $omi->Controller('VCM_StockBins');

	      $objRow->Add(new clsMenuItem('carts','cart'));
	      $objRow->Add(new clsMenuItem('orders',KS_PAGE_KEY_ORDER));
	      $objRow->Add(new clsMenuItem('charges','chg'));
*/

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.orders',
  'descr'	=> 'stock/inventory management functions',
  'version'	=> '0.0',
  'date'	=> '2014-02-22',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'order.php'		=> array(KS_CLASS_ORDERS),
    'charge.php'	=> array(KS_CLASS_ORDER_CHARGES),
    'item.php'		=> array(KS_CLASS_ORDER_ITEMS),
    'msg.php'		=> array(KS_CLASS_ORDER_MSGS),
    'pull.php'		=> array(KS_CLASS_ORDER_PULLS),
    'trxact.php'	=> array(KS_CLASS_ORDER_TRXS),
     ),
  'menu'	=> $om,
  'permit'	=> array('admin'),	// groups who are allowed access
  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );
