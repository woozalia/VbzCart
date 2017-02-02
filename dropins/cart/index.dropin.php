<?php
/*
  PURPOSE: VbzCart drop-in descriptor for user access management
  HISTORY:
    2013-12-18 started
*/
// CONSTANTS

// -- action keys
define('KS_PAGE_KEY_CART','cart');
define('KS_PAGE_KEY_CART_LINE','cart-line');
define('KS_PAGE_KEY_CART_FIELD','cart-field');

// -- classes
define('KS_CLASS_ADMIN_CARTS',		'VC_Carts');
define('KS_CLASS_ADMIN_CART_LINES',	'VCT_CartLines_admin');
define('KS_CLASS_ADMIN_CART_EVENTS',	'VCT_CartLog_admin');
define('KS_CLASS_ADMIN_CART_EVENT',	'VCR_CartEvent_admin');

// MENU

$om = $oRoot->SetNode(new fcDropinLink(KS_PAGE_KEY_CART,'Carts','management of shopping carts'));
  $om->SetPageTitle('Shopping Cart management');
  $om->SetActionClass(KS_CLASS_ADMIN_CARTS);
  $om->SetRequiredPrivilege(KS_PERM_CART_ADMIN);

/* 2016-12-11 old dropin version
$om = new fcMenuLink($oRoot, KS_PAGE_KEY_CART,'Carts','Shopping cart management','management of shopping carts');
  $om->Controller(KS_CLASS_ADMIN_CARTS);
  $om->NeedPermission(KS_PERM_CART_ADMIN);
*/
// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.carts',
  'descr'	=> 'shopping cart administration',
  'version'	=> '0.0',
  'date'	=> '2017-01-06',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'cart.php'			=> array(KS_CLASS_ADMIN_CARTS),
    'lines.php'			=> array(KS_CLASS_ADMIN_CART_LINES),
    'log.php'			=> array(KS_CLASS_ADMIN_CART_EVENTS,KS_CLASS_ADMIN_CART_EVENT),
     ),
  'menu'	=> $om,
  );
