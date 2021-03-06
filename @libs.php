<?php
/*
PURPOSE: define locations for libraries using modloader.php, and load parts of Ferreteria that are always needed
FILE SET: VbzCart libraries
HISTORY:
  2013-08-29 created
  2014-01-20 moved MediaWiki-specific files into separate config-libs.php
  2018-01-31 now assuming that Ferreteria base has already been loaded
*/
//fcCodeLibrary::Load_byName('ferreteria');
fcCodeLibrary::Load_byName('ferreteria.db.2');

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'app.php');
  $om->AddClass('vtApp');
$om = new fcCodeModule(__FILE__, 'globals.php');
  $om->AddClass('vcGlobals');
$om = new fcCodeModule(__FILE__, 'session.php');
  $om->AddClass('vcUserSessions');
$om = new fcCodeModule(__FILE__, 'settings.php');
  $om->AddClass('vctSettings');
$om = new fcCodeModule(__FILE__, 'user.php');
  $om->AddClass('vcUserTable');
  $om->AddClass('vcUserRecord');
  $om->AddClass('clsEmailAuth');
$om = new fcCodeModule(__FILE__, 'vbz-crypt.php');
  $om->AddClass('vcCipher');

$om = new fcCodeModule(__FILE__, 'vbz-data.php');
  $om->AddClass('vcDBOFactory');
  $om->AddClass('vcBasicTable');
  $om->AddClass('vcShopTable');
  

// do this more elegantly later:
require_once('cart/@libs.php');
require_once('logic/@lib.php');
require_once('page/@libs.php');
require_once('shop/@lib.php');
//require_once('skin/@libs.php');
