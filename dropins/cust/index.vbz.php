<?php
/*
  PURPOSE: VbzCart drop-in descriptor for customer administration
  HISTORY:
    2014-01-16 started
*/

// CONSTANTS

// -- classes
define('KS_CLASS_ADMIN_CUSTOMERS','VCT_Custs');
define('KS_CLASS_CUST_CARDS','VCT_CustCards');
define('KS_CLASS_MAIL_ADDRS','VCT_MailAddrs');
define('KS_CLASS_EMAIL_ADDRS','VCT_EmailAddrs');
define('KS_CLASS_CUST_NAMES','VCT_CustNames');

define('KS_ACTION_CUST_CARD','card');

// MENU ADDITIONS

$om = new clsMenuFolder(NULL, '*cust','Customers','Customer Data','manage customer data');
  $om->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,'cust','Customer','Customer Records');
    $omi->Controller(KS_CLASS_ADMIN_CUSTOMERS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,'addr','Address','Address Records');
    $omi->Controller(KS_CLASS_MAIL_ADDRS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,KS_ACTION_CUST_CARD,'Cards','Payment Cards');
    $omi->Controller(KS_CLASS_CUST_CARDS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.custs',
  'descr'	=> 'customer administration',
  'version'	=> '0.0',
  'date'	=> '2014-09-01',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'addr.php'		=> array(KS_CLASS_MAIL_ADDRS),
    'card.php'		=> array(KS_CLASS_CUST_CARDS),
    'cust.php'		=> array(KS_CLASS_ADMIN_CUSTOMERS),
    'email.php'		=> array(KS_CLASS_EMAIL_ADDRS),
    'name.php'		=> array(KS_CLASS_CUST_NAMES),
     ),
  'menu'	=> $om,
//  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );