<?php
/*
  PURPOSE: VbzCart drop-in descriptor for user access management
  HISTORY:
    2013-12-18 started
*/
// CONSTANTS

// -- actions
define('KS_ACTION_VIEW_CASHFLOW',	'cflow');

// -- classes
define('KS_CLASS_ADMIN_CASHFLOW',	'vcqtCashflow');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Miscellaneous'));	// anything that doesn't fit elsewhere

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_VIEW_CASHFLOW,
      KS_CLASS_ADMIN_CASHFLOW,
      'Cashflow',
      'revenue and expenses'));
    //$omi->SetPageTitle('Cashflow Over Time');
//    $omi->SetRequiredPrivilege(KS_PERM_SITE_VIEW_CONFIG);
    $omi->SetRequiredPrivilege(NULL);

/* 2016-12-11 old dropin version
$om = new fcMenuFolder($oRoot, '*misc','Miscellaneous','Miscellaneous Stuff','whatever doesn\t fit elsewhere');
  $om->NeedPermission(KS_PERM_SITE_VIEW_CONFIG);	// until we have a more specific permission

  $omi = new fcMenuLink($om,KS_ACTION_VIEW_CASHFLOW,'Cashflow','Cashflow Over Time','revenues and expenses');
    $omi->Controller(KS_CLASS_ADMIN_CASHFLOW);
    $omi->NeedPermission(KS_PERM_SITE_VIEW_CONFIG);
*/

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.misc',
  'descr'	=> 'miscellaneous functions',
  'version'	=> '0.0',
  'date'	=> '2016-02-02',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'cashflow.php'			=> array(KS_CLASS_ADMIN_CASHFLOW),
     ),
  'menu'	=> $om,
  );
