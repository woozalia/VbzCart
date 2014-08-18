<?php
/*
PURPOSE: define locations for libraries using modloader.php
FILE SET: VbzCart libraries
HISTORY:
  2013-08-29 created
  2014-01-20 moved MediaWiki-specific files into separate config-libs.php
*/
//require('site.php');

$fp = dirname( __FILE__ );
clsModule::BasePath($fp.'/');

$om = new clsModule(__FILE__, 'base.cust.php');
  $om->AddClass('clsCusts');
  $om->AddClass('clsCustAddrs');
  $om->AddClass('clsCustCards');
  $om->AddClass('clsCustCards_dyn');
  $om->AddClass('clsCustEmails');
$om = new clsModule(__FILE__, 'cart.php');
  $om->AddClass('clsShopCarts');
$om = new clsModule(__FILE__, 'cart-data.php');
  $om->AddClass('clsCartVars');
$om = new clsModule(__FILE__, 'cart-lines.php');
  $om->AddClass('clsShopCartLines');
$om = new clsModule(__FILE__, 'cart-log.php');
  $om->AddClass('clsCartLog');
$om = new clsModule(__FILE__, 'orders.php');
  $om->AddClass('clsOrders');
  $om->AddClass('clsOrderLines');
$om = new clsModule(__FILE__, 'session.php');
  $om->AddClass('cVbzSessions');
$om = new clsModule(__FILE__, 'shop.php');
  $om->AddClass('clsVbzData_Shop');
  $om->AddFunc('FormatMoney');
$om = new clsModule(__FILE__, 'store.php');
  $om->AddFunc('DataCurr');
$om = new clsModule(__FILE__, 'user.php');
  $om->AddClass('clsVbzUserTable');
  $om->AddClass('clsVbzUserRec');
  $om->AddClass('clsEmailAuth');
$om = new clsModule(__FILE__, 'vbz-app.php');
  $om->AddClass('clsVbzApp');

$om = new clsModule(__FILE__, 'vbz-cat-dept.php');
  $om->AddClass('clsDepts');
$om = new clsModule(__FILE__, 'vbz-cat-image.php');
  $om->AddClass('clsImages');
$om = new clsModule(__FILE__, 'vbz-cat-image-ui.php');
  $om->AddClass('clsImages_StoreUI');
$om = new clsModule(__FILE__, 'vbz-cat-item.php');
  $om->AddClass('clsItem');
  $om->AddClass('clsItems');
$om = new clsModule(__FILE__, 'vbz-cat-item-type.php');
  $om->AddClass('clsItTyps');
  $om->AddClass('clsItTyp');
$om = new clsModule(__FILE__, 'vbz-cat-item-opt.php');
  $om->AddClass('clsItOpts');
$om = new clsModule(__FILE__, 'vbz-cat-page.php');
  $om->AddClass('clsCatPages');
$om = new clsModule(__FILE__, 'vbz-cat-ship-cost.php');
  $om->AddClass('clsShipCosts');
$om = new clsModule(__FILE__, 'vbz-cat-supp.php');
  $om->AddClass('clsSuppliers');
$om = new clsModule(__FILE__, 'vbz-cat-supp-ui.php');
  $om->AddClass('clsSuppliers_StoreUI');
$om = new clsModule(__FILE__, 'vbz-cat-title.php');
  $om->AddClass('clsVbzTitles');
  $om->AddClass('clsVbzTitle');
$om = new clsModule(__FILE__, 'vbz-cat-title-topic.php');
  $om->AddClass('clsTitlesTopics');
$om = new clsModule(__FILE__, 'vbz-cat-title-topic-ui.php');
  $om->AddClass('clsTitlesTopics_shopUI');
$om = new clsModule(__FILE__, 'vbz-cat-title-ui.php');
  $om->AddClass('clsTitles_StoreUI');
$om = new clsModule(__FILE__, 'vbz-cat-topic.php');
  $om->AddClass('clsTopics');
//  $om->AddClass('clsTitleTopic_Topics');
$om = new clsModule(__FILE__, 'vbz-cat-topic-ui.php');
  $om->AddClass('clsTopics_StoreUI');
  $om->AddClass('clsTopic_StoreUI');
$om = new clsModule(__FILE__, 'vbz-data.php');
  $om->AddClass('clsVbzData');
  $om->AddClass('clsVbzTable');
  $om->AddClass('clsVbzRecs');

$om = new clsModule(__FILE__, 'vbz-fx.php');
//  $om->AddFunc('http_redirect');	USE clsHTTP
  $om->AddFunc('TimeStamp_HideTime');
$om = new clsModule(__FILE__, 'vbz-page.php');
  $om->AddClass('clsVbzPage');
$om = new clsModule(__FILE__, 'vbz-page-acct.php');
  $om->AddClass('clsPageAdmin_Acct');
$om = new clsModule(__FILE__, 'vbz-page-admin.php');
  $om->AddClass('clsPageAdmin');
  $om->AddClass('clsVbzPage_Admin');
$om = new clsModule(__FILE__, 'vbz-page-browse.php');
  $om->AddClass('clsVbzPage_Browse');
$om = new clsModule(__FILE__, 'vbz-page-cart.php');
  $om->AddClass('clsPageBrowse_Cart');
  $om->AddClass('clsPageAdmin_Cart');
  $om->AddClass('clsSessions_StoreUI');
$om = new clsModule(__FILE__, 'vbz-page-cat.php');
  $om->AddClass('clsVbzPage_Cat');
$om = new clsModule(__FILE__, 'vbz-page-ckout.php');
  $om->AddClass('clsPageCkout');
$om = new clsModule(__FILE__, 'vbz-page-login.php');
  $om->AddClass('clsVbzPageLogin');
$om = new clsModule(__FILE__, 'vbz-page-topic.php');
  $om->AddClass('clsPageTopic');
$om = new clsModule(__FILE__, 'vbz-page-search.php');
  $om->AddClass('clsPageSearch');

$om = new clsModule(__FILE__, 'vbz-skin.php');
  $om->AddClass('clsVbzSkin');
$om = new clsModule(__FILE__, 'vbz-skin-browse.php');
  $om->AddClass('clsVbzSkin_browse');
$om = new clsModule(__FILE__, 'vbz-skin-admin.php');
  $om->AddClass('clsVbzSkin_admin');
