<?php
/*
  PURPOSE: VbzCart drop-in descriptor for stock management
  HISTORY:
    2013-11-28 started
*/

// CONSTANTS

define('KS_CLASS_RESTOCKS_NEEDED','VCM_RstksNeeded');
define('KS_CLASS_RESTOCKS_REQUESTED','VCT_RstkReqs');
define('KS_CLASS_RESTOCK_REQ_ITEMS','VCT_RstkReqItems');
define('KS_CLASS_RESTOCKS_RECEIVED','VCM_RstksRcvd');

define('KS_ACTION_RESTOCK_NEED','rstk.need');
define('KS_ACTION_RESTOCK_REQUEST_ITEM','rri');

define('KS_TABLE_RESTOCK_REQUEST','rstk_req');
define('KS_TABLE_RESTOCK_RECEIVED','rstk_rcd');

// MENU ADDITIONS

$om = new clsMenuFolder(NULL, 'rstk','Restocks','Restocking','managment of wholesale (restock) orders');
  $om->NeedPermission(KS_PERM_RSTK_VIEW);
  $omi = new clsMenuLink($om,KS_ACTION_RESTOCK_NEED,'Needed','Needed Restocks','items that we need to restock');
    $omi->Controller(KS_CLASS_RESTOCKS_NEEDED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
  $omi = new clsMenuLink($om,'rstk.wait','Expected','Expected Restocks','restocks that we are expecting to receive');
    $omi->Controller(KS_CLASS_RESTOCKS_REQUESTED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
  $omi = new clsMenuLink($om,'rstk.past','Past','Past Restocks','restocks received in the past');
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
  $omi = new clsMenuHidden($om,'rstk-req','Restock Requests');
    $omi->Controller(KS_CLASS_RESTOCKS_REQUESTED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
  $omi = new clsMenuHidden($om,KS_ACTION_RESTOCK_REQUEST_ITEM,'Restock Request Item');
    $omi->Controller(KS_CLASS_RESTOCK_REQ_ITEMS);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
  $omi = new clsMenuHidden($om,'rstk-rcd','Restocks Received');
    $omi->Controller(KS_CLASS_RESTOCKS_RECEIVED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.restock',
  'descr'	=> 'managment of wholesale (restock) orders',
  'version'	=> '0.0',
  'date'	=> '2014-03-09',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'needed.php'	=> array(KS_CLASS_RESTOCKS_NEEDED),
    'request.php'	=> array(KS_CLASS_RESTOCKS_REQUESTED),
    'request-item.php'	=> array(KS_CLASS_RESTOCK_REQ_ITEMS),
    'received.php'	=> array(KS_CLASS_RESTOCKS_RECEIVED)
     ),
  'menu'	=> $om,
  'permit'	=> array('admin'),	// groups who are allowed access
  'requires'	=> array('vbz.syslog','vbz.orders','vbz.stock')	// other drop-ins this drop-in needs
  );
