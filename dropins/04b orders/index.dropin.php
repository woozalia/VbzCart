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
define('KS_ACTION_KEY_ORDER_HOLD',	'ord-hold');

define('KS_CLASS_ORDERS',		'vctAdminOrders');
define('KS_CLASS_ORDER_CHARGES',	'vctAdminOrderCharges');
define('KS_CLASS_ORDER_LINES',		'vctAdminOrderLines');
define('KS_CLASS_ORDER_MSGS',		'vctAdminOrderMsgs');
define('KS_CLASS_ORDER_MSG_MEDIA',	'vctaOrderMessageMedia');
define('KS_CLASS_ORDER_HOLDS',		'vctOrderHolds');
define('KS_CLASS_ORDER_HOLD_TYPES',	'vctOrderHoldTypes');
//define('KS_CLASS_ORDER_PULLS',	'vctAdminOrderPulls');
//define('KS_CLASS_ORDER_PULL_TYPES',	'vctOrderPullTypes');
define('KS_CLASS_ORDER_TRXS',		'vctOrderTrxacts');
define('KS_CLASS_ORDER_TRX_TYPES',	'vctOrderTrxTypes');
define('KS_CLASS_ORDER_TRX_TYPE',	'vcraOrderTrxType');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Orders'));

  $omi = $om->SetNode(new fcDropinLink(
    KS_PAGE_KEY_ORDER,
    KS_CLASS_ORDERS,
    'Orders',
    'management of customer orders'));

    $omi->SetRequiredPrivilege(KS_PERM_ORDER_PROCESS);

  $omi = $om->SetNode(new fcDropinAction(
    KS_PAGE_KEY_ORDER_LINE,
    KS_CLASS_ORDER_LINES));

    $omi->SetRequiredPrivilege(KS_PERM_ORDER_PROCESS);

  $omi = $om->SetNode(new fcDropinLink(
    KS_PAGE_KEY_ORDER_MSG,
    KS_CLASS_ORDER_MSGS,
    'Messages',
    'messages attached to customer orders'));

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_KEY_ORDER_HOLD,
    KS_CLASS_ORDER_HOLDS,
    'Order Holds'));

//    $omi->SetPageTitle('Order Messages');
    $omi->SetRequiredPrivilege(KS_PERM_ORDER_PROCESS);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.orders',
  'descr'	=> 'stock/inventory management functions',
  'version'	=> '0.0',
  'date'	=> '2017-06-04',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'order.php'		=> array(KS_CLASS_ORDERS),
    'charge.php'	=> array(KS_CLASS_ORDER_CHARGES),
    'line.php'		=> array(KS_CLASS_ORDER_LINES),
    'media.php'		=> array(KS_CLASS_ORDER_MSG_MEDIA),
    'msg.php'		=> array(KS_CLASS_ORDER_MSGS),
    'hold.php'		=> array(KS_CLASS_ORDER_HOLDS),
    'hold.form.php'	=> array('vcOrderHoldsForm'),
    'hold-type.php'	=> array(KS_CLASS_ORDER_HOLD_TYPES),
    'trxact.php'	=> array(KS_CLASS_ORDER_TRXS),
    'trxact-type.php'	=> array(KS_CLASS_ORDER_TRX_TYPES,KS_CLASS_ORDER_TRX_TYPE),
     ),
  'menu'	=> $om,
  );
