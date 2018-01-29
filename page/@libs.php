<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'vbz-page.php');
  $om->AddClass('vcTag_html');
  $om->AddClass('vcPage');
  $om->AddClass('vcPageHeader');
  $om->AddClass('vcPageContent');
  /* 2016-12-16 Should be unneeded now.
$om = new fcCodeModule(__FILE__, 'vbz-page-acct.php');
  $om->AddClass('vcPageAdmin_Acct'); */
$om = new fcCodeModule(__FILE__, 'vbz-page-admin.php');
  $om->AddClass('vcPageAdmin');
$om = new fcCodeModule(__FILE__, 'vbz-page-ajax.php');
  $om->AddClass('vcPageAJAX');
  $om->AddClass('vcPage_AJAX_ProcessStatus');
$om = new fcCodeModule(__FILE__, 'vbz-page-cart.php');
  $om->AddClass('vcPageBrowse_Cart');
  $om->AddClass('clsSessions_StoreUI');
$om = new fcCodeModule(__FILE__, 'vbz-page-cat.php');
  $om->AddClass('vcCatalogPage');
$om = new fcCodeModule(__FILE__, 'vbz-page-ckout.php');
  $om->AddClass('vcPageCkout');
/* 2016-12-20 obsolete
$om = new fcCodeModule(__FILE__, 'vbz-page-login.php');
  $om->AddClass('clsVbzPageLogin'); */
$om = new fcCodeModule(__FILE__, 'vbz-page-search.php');
  $om->AddClass('vcPageSearch');
$om = new fcCodeModule(__FILE__, 'vbz-page-shop.php');
  $om->AddClass('vcPage_shop');
  $om->AddClass('vcTag_body_shop');
  $om->AddClass('vcPageContent_shop');
$om = new fcCodeModule(__FILE__, 'vbz-page-topic.php');
  $om->AddClass('vcPageTopic');
