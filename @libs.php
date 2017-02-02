<?php
/*
PURPOSE: define locations for libraries using modloader.php, and load parts of Ferreteria that are always needed
FILE SET: VbzCart libraries
HISTORY:
  2013-08-29 created
  2014-01-20 moved MediaWiki-specific files into separate config-libs.php
*/
fcCodeLibrary::Load_byName('ferreteria');
fcCodeLibrary::Load_byName('ferreteria.db.2');

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'orders.php');
  $om->AddClass('vctOrders');
$om = new fcCodeModule(__FILE__, 'order-line.php');
  $om->AddClass('vctOrderLines');
$om = new fcCodeModule(__FILE__, 'order-msg.php');
  $om->AddClass('vctOrderMsgs');
$om = new fcCodeModule(__FILE__, 'session.php');
  $om->AddClass('cVbzSessions');
$om = new fcCodeModule(__FILE__, 'settings.php');
  $om->AddClass('vctSettings');
$om = new fcCodeModule(__FILE__, 'user.php');
  $om->AddClass('vcUserTable');
  $om->AddClass('vcUserRecord');
  $om->AddClass('clsEmailAuth');
$om = new fcCodeModule(__FILE__, 'vbz-app.php');
  $om->AddClass('vcApp');
$om = new fcCodeModule(__FILE__, 'vbz-app-admin.php');
  $om->AddClass('vcAppAdmin');
$om = new fcCodeModule(__FILE__, 'vbz-app-shop.php');
  $om->AddClass('vcAppShop_catalog');
  $om->AddClass('vcAppShop_search');
$om = new fcCodeModule(__FILE__, 'vbz-crypt.php');
  $om->AddClass('vcCipher');


//$om = new fcCodeModule(__FILE__, 'vbz-crypt.php');
//  $om->AddClass('vbzCipher');
$om = new fcCodeModule(__FILE__, 'vbz-data.php');
  $om->AddClass('vcDBOFactory');

// do this more elegantly later:
require_once('cart/@libs.php');
require_once('cat/@libs.php');
require_once('cust/@libs.php');
require_once('page/@libs.php');
require_once('ship/@libs.php');
//require_once('skin/@libs.php');