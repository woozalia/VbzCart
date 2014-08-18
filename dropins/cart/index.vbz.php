<?php
/*
  PURPOSE: VbzCart drop-in descriptor for user access management
  HISTORY:
    2013-12-18 started
*/

// CONSTANTS

// -- action keys
define('KS_PAGE_KEY_CART','cart');

// -- classes
define('KS_CLASS_ADMIN_CARTS',		'VC_Carts');
define('KS_CLASS_ADMIN_CART_LINES',	'VCT_CartLines_admin');
define('KS_CLASS_ADMIN_CART_FIELDS',	'VCT_CartFields_admin');
define('KS_CLASS_ADMIN_CART_EVENTS',	'VCT_CartLog_admin');
define('KS_CLASS_ADMIN_CART_EVENT',	'VCR_CartEvent_admin');

// MENU

$om = new clsMenuLink(NULL, KS_PAGE_KEY_CART,'Carts','Shopping cart management','management of shopping carts');
  $om->Controller(KS_CLASS_ADMIN_CARTS);
  $om->NeedPermission(KS_PERM_CART_ADMIN);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.carts',
  'descr'	=> 'shopping cart administration',
  'version'	=> '0.0',
  'date'	=> '2014-01-20',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'cart.php'			=> array(KS_CLASS_ADMIN_CARTS),
    'lines.php'			=> array(KS_CLASS_ADMIN_CART_LINES),
    'fields.php'		=> array(KS_CLASS_ADMIN_CART_FIELDS),
    'log.php'			=> array(KS_CLASS_ADMIN_CART_EVENTS,KS_CLASS_ADMIN_CART_EVENT),
     ),
  'menu'	=> $om,
//  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
//  'features'	=> array(),
  );
