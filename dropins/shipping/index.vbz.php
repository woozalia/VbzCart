<?php
/*
  PURPOSE: VbzCart drop-in descriptor for managing packages
  HISTORY:
    2013-12-15 adapting for drop-in system
    2014-01-24 renamed from packages (vbz.pkgs) to shipping (vbz.ship)
*/

// CONSTANTS
define('KS_CLASS_PACKAGES','clsPackages');
define('KS_CLASS_PACKAGE_LINES','clsPkgLines');
define('KS_CLASS_SHIPMENTS','VCT_Shipments');

define('KS_ACTION_SHIPMENT','shp');

// MENU ADDITIONS

$om = new clsMenuFolder(NULL, '*ship','Shipping','Shippping Management','manage packages and shipments');
  $om->NeedPermission(KS_PERM_SHIP_ADMIN);
  $omi = new clsMenuLink($om,'pkg','Packages','package management','management of shipment packages');
    $omi->Controller(KS_CLASS_PACKAGES);
    $omi->NeedPermission(KS_PERM_SHIP_ADMIN);
  $omi = new clsMenuLink($om,KS_ACTION_SHIPMENT,'Shipments','shipment management','management of shipment batches');
    $omi->Controller(KS_CLASS_SHIPMENTS);
    $omi->NeedPermission(KS_PERM_SHIP_ADMIN);

// MODULE DEFINITION

$arDropin = array(
  'name'	=> 'vbz.ship',
  'descr'	=> 'shipment-package management functions',
  'version'	=> '0.0',
  'date'	=> '2013-12-15',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'pkg.php'		=> array(KS_CLASS_PACKAGES),
    'pkg-line.php'	=> array(KS_CLASS_PACKAGE_LINES),
    'pkg-total.php'	=> array('clsPackageTotal'),
    'ship.php'		=> array(KS_CLASS_SHIPMENTS),
     ),
  'menu'	=> $om,
  'permit'	=> array('admin','shipping'),	// groups who are allowed access
//  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );
