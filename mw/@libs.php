<?php
/*
PURPOSE: define locations for libraries using modloader.php
FILE SET: VbzCart MediaWiki libraries
HISTORY:
  2013-08-29 created
  2014-01-20 split off MW-specific files from other VbzCart libraries
*/
//require('site.php');

$fp = dirname( __FILE__ );
clsModule::BasePath($fp.'/');

$om = new clsModule(__FILE__, 'admin.cat.php');
  $om->AddClass('VbzAdminCatalogs');
$om = new clsModule(__FILE__, 'admin.cart.php');
  $om->AddClass('VbzAdminCarts');
$om = new clsModule(__FILE__, 'admin.cust.php');
  $om->AddClass('VbzAdminCusts');
  $om->AddClass('clsAdminCustAddrs');
  $om->AddClass('clsAdminCustEmails');
$om = new clsModule(__FILE__, 'admin.ord.php');
  $om->AddClass('VbzAdminOrderChgs');
$om = new clsModule(__FILE__, 'admin.pkg.php');
  $om->AddClass('clsPackages');
$om = new clsModule(__FILE__, 'admin.rstk.php');
  $om->AddClass('clsAdminRstkReqs');
$om = new clsModule(__FILE__, 'admin.sess.php');
  $om->AddClass('VbzAdminSessions');
$om = new clsModule(__FILE__, 'admin.stock.php');
  $om->AddClass('VbzAdminStkItems');
$om = new clsModule(__FILE__, 'base.admin.php');
  $om->AddClass('clsVbzAdminData');

$om = new clsModule(__FILE__, 'vbz-mw-dept.php');
  $om->AddClass('VbzAdminDepts');
$om = new clsModule(__FILE__, 'vbz-mw-image.php');
  $om->AddClass('clsAdminImages');
$om = new clsModule(__FILE__, 'vbz-mw-item.php');
  $om->AddClass('VbzAdminItems');
$om = new clsModule(__FILE__, 'vbz-mw-ship.php');
  $om->AddClass('clsShipments');
$om = new clsModule(__FILE__, 'vbz-mw-supp.php');
  $om->AddClass('VbzAdminSuppliers');
$om = new clsModule(__FILE__, 'vbz-mw-title.php');
  $om->AddClass('VbzAdminTitles');
$om = new clsModule(__FILE__, 'vbz-mw-title-topic.php');
  $om->AddClass('clsAdminTitleTopic_Topics');
$om = new clsModule(__FILE__, 'vbz-mw-topic.php');
  $om->AddClass('clsAdminTopics');