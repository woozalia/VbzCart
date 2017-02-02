<?php
/*
  PURPOSE: VbzCart drop-in descriptor for stock management
  HISTORY:
    2013-11-28 started
*/

// CONSTANTS

// -- restocks needed
define('KS_CLASS_RESTOCKS_NEEDED','VCM_RstksNeeded');
// -- restock requests
define('KS_LOGIC_CLASS_RESTOCK_REQUESTS','vctRstkReqs');
define('KS_ADMIN_CLASS_RESTOCK_REQUESTS','VCT_RstkReqs');
define('KS_LOGIC_CLASS_RESTOCK_REQ_ITEMS','vctlRstkReqItems');
define('KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS','vctaRstkReqItems');
// -- restocks received
define('KS_LOGIC_CLASS_RESTOCKS_RECEIVED','vctRstksRcvd');
define('KS_ADMIN_CLASS_RESTOCKS_RECEIVED','vctaRstksRcvd');
define('KS_LOGIC_CLASS_RESTOCK_LINES_RECEIVED','vctlRstkRcdLines');
define('KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED','vctaRstkRcdLines');
// -- queries
define('KS_QUERY_CLASS_RESTOCK_ITEMS_EXPECTED','vctaRRQIs_exp');

define('KS_ACTION_RESTOCK_NEED','rstk.need');
define('KS_ACTION_RESTOCK_REQUEST','rstk-req');
define('KS_ACTION_RESTOCK_REQUEST_ITEM','rqi');
define('KS_ACTION_RESTOCK_EXPECTED','rstk-exp');
define('KS_ACTION_RESTOCK_RECEIVED','rstk-rcv');
define('KS_ACTION_RESTOCK_RECEIVED_LINE','rcl');

define('KS_TABLE_RESTOCK_REQUEST','rstk_req');
define('KS_TABLE_RESTOCK_RECEIVED','rstk_rcd');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Restocks'));
  
  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_RESTOCK_NEED,'Needed','items that we need to restock'));
    $omi->SetPageTitle('Needed Restocks');
    $omi->SetActionClass(KS_CLASS_RESTOCKS_NEEDED);
    $omi->SetRequiredPrivilege(KS_PERM_RSTK_VIEW);
    
  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_RESTOCK_REQUEST,'Requests','all restocks ever requested'));
    $omi->SetPageTitle('Restock Requests');
    $omi->SetActionClass(KS_ADMIN_CLASS_RESTOCK_REQUESTS);
    $omi->SetRequiredPrivilege(KS_PERM_RSTK_VIEW);
    
  $omi = $om->SetNode(new fcDropinAction(KS_ACTION_RESTOCK_REQUEST_ITEM));	// restock request items
    $omi->SetActionClass(KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS);
    $omi->SetRequiredPrivilege(KS_PERM_RSTK_VIEW);
    
  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_RESTOCK_RECEIVED,'Received','all restocks ever received'));
    $omi->SetPageTitle('Restocks Received');
    $omi->SetActionClass(KS_ADMIN_CLASS_RESTOCKS_RECEIVED);
    $omi->SetRequiredPrivilege(KS_PERM_RSTK_VIEW);

  $omi = $om->SetNode(new fcDropinAction(KS_ACTION_RESTOCK_RECEIVED_LINE));	// received restock lines
    $omi->SetActionClass(KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED);
    $omi->SetRequiredPrivilege(KS_PERM_RSTK_VIEW);
    
  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_RESTOCK_EXPECTED,'Expected','open restock requests'));
    $omi->SetPageTitle('Expected Restocks');
    $omi->SetActionClass(KS_ADMIN_CLASS_RESTOCK_REQUESTS);
    $omi->SetRequiredPrivilege(KS_PERM_RSTK_VIEW);

/* 2016-12-11 old dropin version
$om = new fcMenuFolder($oRoot, 'rstk','Restocks','Restocking','managment of wholesale (restock) orders');
  $om->NeedPermission(KS_PERM_RSTK_VIEW);
  
  $omi = new fcMenuLink($om,KS_ACTION_RESTOCK_NEED,'Needed','Needed Restocks','items that we need to restock');
    $omi->Controller(KS_CLASS_RESTOCKS_NEEDED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
    
  $omi = new fcMenuLink($om,KS_ACTION_RESTOCK_REQUEST,'Requests','Restock Requests','all restocks ever requested');
    $omi->Controller(KS_ADMIN_CLASS_RESTOCK_REQUESTS);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
    
  $omi = new fcMenuHidden($om,KS_ACTION_RESTOCK_REQUEST_ITEM,'Restock Request Item');
    $omi->Controller(KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
    
  $omi = new fcMenuLink($om,KS_ACTION_RESTOCK_RECEIVED,'Received','Restocks Received','all restocks ever received');
    $omi->Controller(KS_ADMIN_CLASS_RESTOCKS_RECEIVED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);

  $omi = new fcMenuHidden($om,KS_ACTION_RESTOCK_RECEIVED_LINE,'Received Restock Lines');
    $omi->Controller(KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
    
  $omi = new fcMenuLink($om,KS_ACTION_RESTOCK_EXPECTED,'Expected','Expected Restocks','open restock requests');
    $omi->Controller(KS_ADMIN_CLASS_RESTOCK_REQUESTS);
    $omi->NeedPermission(KS_PERM_RSTK_VIEW);
*/

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.restock',
  'descr'	=> 'managment of restock (wholesale) orders',
  'version'	=> '0.0',
  'date'	=> '2016-01-12',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'needed.php'		=> array(KS_CLASS_RESTOCKS_NEEDED),
    'qry.rrqi-exp.php'		=> array(KS_QUERY_CLASS_RESTOCK_ITEMS_EXPECTED),
    'restock.traits.php'	=> array('vtRestockTable_admin','vtRestockTable_logic'),
    'restock-line.traits.php'	=> array('vtRestockLines'),
    'request.logic.php'		=> array(KS_LOGIC_CLASS_RESTOCK_REQUESTS),	// business logic class pair
    'request.admin.php'		=> array(KS_ADMIN_CLASS_RESTOCK_REQUESTS),
    'request-item.logic.php'	=> array(KS_LOGIC_CLASS_RESTOCK_REQ_ITEMS),
    'request-item.admin.php'	=> array(KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS),
    'received.logic.php'	=> array(KS_LOGIC_CLASS_RESTOCKS_RECEIVED),
    'received.admin.php'	=> array(KS_ADMIN_CLASS_RESTOCKS_RECEIVED),
    'received-line.logic.php'	=> array(KS_LOGIC_CLASS_RESTOCK_LINES_RECEIVED),
    'received-line.admin.php'	=> array(KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED),
     ),
  'menu'	=> $om,
  );
