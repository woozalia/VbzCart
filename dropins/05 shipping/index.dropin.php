<?php
/*
  PURPOSE: VbzCart drop-in descriptor for managing packages
  HISTORY:
    2013-12-15 adapting for drop-in system
    2014-01-24 renamed from packages (vbz.pkgs) to shipping (vbz.ship)
*/

// CONSTANTS
define('KS_CLASS_PACKAGES','vctAdminPackages');
define('KS_CLASS_PACKAGE_LINES','clsPkgLines');
define('KS_CLASS_SHIPMENTS','vctAdminShipments');

define('KS_ACTION_PACKAGE','pkg');
define('KS_ACTION_PKG_LINE','pkg-line');
define('KS_ACTION_SHIPMENT','shp');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Shipping','Shippping Management','manage packages and shipments'));

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_PACKAGE,
      KS_CLASS_PACKAGES,
      'Packages',
      'management of shipment packages')
      );

    //$omi->SetPageTitle('Package Management');
    $omi->SetRequiredPrivilege(KS_PERM_SHIP_ADMIN);

  $omi = $om->SetNode(
    new fcDropinAction(
      KS_ACTION_PKG_LINE,
      KS_CLASS_PACKAGE_LINES)
    );
    $omi->SetRequiredPrivilege(KS_PERM_SHIP_ADMIN);

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_SHIPMENT,
      KS_CLASS_SHIPMENTS,
      'Shipments',
      'management of shipment batches')
    );
    //$omi->SetPageTitle('Shipment Management');
    $omi->SetRequiredPrivilege(KS_PERM_SHIP_ADMIN);

/* 2016-12-11 old dropin version
$om = new fcMenuFolder($oRoot, '*ship','Shipping','Shippping Management','manage packages and shipments');
  $om->NeedPermission(KS_PERM_SHIP_ADMIN);
  $omi = new fcMenuLink($om,'pkg','Packages','package management','management of shipment packages');
    $omi->Controller(KS_CLASS_PACKAGES);
    $omi->NeedPermission(KS_PERM_SHIP_ADMIN);
  $omi = new fcMenuHidden($om,KS_ACTION_PKG_LINE,'Package Lines');
    $omi->Controller(KS_CLASS_PACKAGE_LINES);
    $omi->NeedPermission(KS_PERM_SHIP_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_SHIPMENT,'Shipments','shipment management','management of shipment batches');
    $omi->Controller(KS_CLASS_SHIPMENTS);
    $omi->NeedPermission(KS_PERM_SHIP_ADMIN);
*/
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
    'pkg-total.php'	=> array('vcPackageTotal'),
    'ship.php'		=> array(KS_CLASS_SHIPMENTS),
     ),
  'menu'	=> $om,
  );
