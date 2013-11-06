<?php
/*
PURPOSE: define locations for libraries using modloader.php
FILE SET: VbzCart libraries
HISTORY:
  2013-08-29 created
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
/*
$om = new clsModule(__FILE__, 'admin.user.php');
  $om->AddClass('clsVbzUserRecs_admin');
*/
$om = new clsModule(__FILE__, 'base.admin.php');
  $om->AddClass('clsVbzAdminData');
$om = new clsModule(__FILE__, 'base.cat.php');
  $om->AddClass('clsSuppliers');
  $om->AddClass('clsItems');
$om = new clsModule(__FILE__, 'base.cust.php');
  $om->AddClass('clsCusts');
  $om->AddClass('clsCustCards');
  $om->AddClass('clsCustEmails');
$om = new clsModule(__FILE__, 'base.stock.php');
  $om->AddClass('clsStkItems');
$om = new clsModule(__FILE__, 'cart.php');
  $om->AddClass('clsShopCarts');
$om = new clsModule(__FILE__, 'cart-data.php');
  $om->AddClass('clsCartVars');
$om = new clsModule(__FILE__, 'cart-lines.php');
  $om->AddClass('clsShopCartLines');
$om = new clsModule(__FILE__, 'data.titles.php');
  $om->AddClass('clsVbzTitles');
  $om->AddClass('clsVbzTitle');
$om = new clsModule(__FILE__, 'orders.php');
  $om->AddClass('clsOrders');
  $om->AddClass('clsOrderLines');
$om = new clsModule(__FILE__, 'page-acct.php');
  $om->AddClass('clsPageAdmin_Acct');
$om = new clsModule(__FILE__, 'page-admin.php');
  $om->AddClass('clsPageAdmin');
  $om->AddClass('clsVbzPage_Admin');
$om = new clsModule(__FILE__, 'page-cart.php');
  $om->AddClass('clsPageBrowse_Cart');
  $om->AddClass('clsPageAdmin_Cart');
  $om->AddClass('clsSessions_StoreUI');
$om = new clsModule(__FILE__, 'page-cat.php');
  $om->AddClass('clsPageCat');
  $om->AddClass('clsTitles_StoreUI');
$om = new clsModule(__FILE__, 'page-ckout.php');
  $om->AddClass('clsPageCkout');
$om = new clsModule(__FILE__, 'page-topic.php');
  $om->AddClass('clsTopics_StoreUI');
$om = new clsModule(__FILE__, 'page-user.php');
  $om->AddClass('clsPageUser');
$om = new clsModule(__FILE__, 'pages.php');
  $om->AddClass('clsVbzPage');
  $om->AddClass('clsVbzPage_Browse');
$om = new clsModule(__FILE__, 'skins.php');
  $om->AddClass('clsVbzSkin');
  $om->AddClass('clsVbzSkin_browse');
  $om->AddClass('clsVbzSkin_admin');
$om = new clsModule(__FILE__, 'shop.php');
  $om->AddClass('clsShopSessions');
  $om->AddClass('clsVbzData_Shop');
$om = new clsModule(__FILE__, 'store.php');
  $om->AddClass('clsVbzData');
$om = new clsModule(__FILE__, 'topic.php');
  $om->AddClass('clsTopics');
  $om->AddClass('clsTitleTopic_Topics');
$om = new clsModule(__FILE__, 'user.php');
  //$om->AddClass('clsVbzUser');
  $om->AddClass('clsEmailAuth');
$om = new clsModule(__FILE__, 'vbz-data.php');
  $om->AddClass('clsVbzTable');
  $om->AddClass('clsVbzRecs');

