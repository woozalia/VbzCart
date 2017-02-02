<?php
/*
  PURPOSE: VbzCart drop-in descriptor for order management
  HISTORY:
    2013-11-28 started
    2016-12-11 adapting to revised dropin system
*/

// CONSTANTS

define('KS_PAGE_KEY_ORDER',		'ord');
define('KS_PAGE_KEY_ORDER_MSG',		'ord-msg');
define('KS_PAGE_KEY_ORDER_MSG_MEDIUM',	'ord-msg-type');
define('KS_PAGE_KEY_ORDER_LINE',	'ord-line');
define('KS_PAGE_KEY_ORDER_TRX_TYPE',	'otrxty');

define('KS_CLASS_ORDERS',		'VCM_Orders');
define('KS_CLASS_ORDER_CHARGES',	'VCT_OrderChgs');
define('KS_CLASS_ORDER_LINES',		'VCA_OrderLines');
define('KS_CLASS_ORDER_MSGS',		'VCT_OrderMsgs');
define('KS_CLASS_ORDER_MSG_MEDIA',	'vctaOrderMessageMedia');
define('KS_CLASS_ORDER_PULLS',		'VCT_OrderPulls');
define('KS_CLASS_ORDER_PULL_TYPES',	'vctOrderPullTypes');
define('KS_CLASS_ORDER_TRXS',		'VCT_OrderTrxacts');
define('KS_CLASS_ORDER_TRX_TYPES',	'VCT_OrderTrxTypes');
define('KS_CLASS_ORDER_TRX_TYPE',	'vcraOrderTrxType');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Orders'));

  $omi = $om->SetNode(new fcDropinLink(KS_PAGE_KEY_ORDER,'Orders','management of customer orders'));
    $omi->SetPageTitle('Order Management');
    $omi->SetActionClass(KS_CLASS_ORDERS);
    $omi->SetRequiredPrivilege(KS_PERM_ORDER_PROCESS);

  $omi = $om->SetNode(new fcDropinAction(KS_PAGE_KEY_ORDER_LINE));	// Order Lines
    $omi->SetActionClass(KS_CLASS_ORDER_LINES);
    $omi->SetRequiredPrivilege(KS_PERM_ORDER_PROCESS);

  $omi = $om->SetNode(new fcDropinLink(KS_PAGE_KEY_ORDER_MSG,'Messages','messages attached to customer orders'));
    $omi->SetPageTitle('Order Messages');
    $omi->SetActionClass(KS_CLASS_ORDER_MSGS);
    $omi->SetRequiredPrivilege(KS_PERM_ORDER_PROCESS);

/* 2016-12-11 old dropin version
$om = new fcMenuFolder($oRoot,'*ord','Orders','Customer Orders','manage customer orders');
  $om->NeedPermission(KS_PERM_ORDER_PROCESS);
  $omi = new fcMenuLink($om, KS_PAGE_KEY_ORDER,'Orders','Order Management','management of customer orders');
    $omi->Controller(KS_CLASS_ORDERS);
    $omi->NeedPermission(KS_PERM_ORDER_PROCESS);
  $omi = new fcMenuHidden($om,KS_PAGE_KEY_ORDER_LINE,'Order Lines');
    $omi->Controller(KS_CLASS_ORDER_LINES);
    $omi->NeedPermission(KS_PERM_ORDER_PROCESS);
  $omi = new fcMenuLink($om, KS_PAGE_KEY_ORDER_MSG,'Messages','Order Messages','messages attached to customer orders');
    $omi->Controller(KS_CLASS_ORDER_MSGS);
    $omi->NeedPermission(KS_PERM_ORDER_PROCESS);
//  $omi = new fcMenuHidden($om,KS_PAGE_KEY_ORDER_MSG_MEDIUM,'Order Message Media');
//    $omi->Controller(KS_CLASS_ORDER_MSG_MEDIA);
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
    'line.php'		=> array(KS_CLASS_ORDER_LINES),
    'media.php'		=> array(KS_CLASS_ORDER_MSG_MEDIA),
    'msg.php'		=> array(KS_CLASS_ORDER_MSGS),
    'pull.php'		=> array(KS_CLASS_ORDER_PULLS),
    'pull-type.php'	=> array(KS_CLASS_ORDER_PULL_TYPES),
    'trxact.php'	=> array(KS_CLASS_ORDER_TRXS),
    'trxact-type.php'	=> array(KS_CLASS_ORDER_TRX_TYPES,KS_CLASS_ORDER_TRX_TYPE),
     ),
  'menu'	=> $om,
  );
