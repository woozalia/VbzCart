<?php
/*
  PURPOSE: VbzCart drop-in descriptor for customer administration
  HISTORY:
    2014-01-16 started
*/

// CONSTANTS

// -- classes
define('KS_CLASS_ADMIN_CUSTOMERS','vctaCusts');
define('KS_CLASS_CUST_CARDS','VCT_CustCards');
define('KS_CLASS_MAIL_ADDRS','VCT_MailAddrs');
define('KS_CLASS_CUST_NAMES','VCT_CustNames');
define('KS_CLASS_EMAIL_ADDRS','VCT_EmailAddrs');
define('KS_CLASS_CUST_PHONES','clsAdminCustPhones');

define('KS_ACTION_CUSTOMER','cust');
define('KS_ACTION_CUST_NAME','c-name');
define('KS_ACTION_CUST_ADDR','c-addr');
define('KS_ACTION_CUST_CARD','c-card');
define('KS_ACTION_CUST_EMAIL','c-email');
define('KS_ACTION_CUST_PHONE','c-phone');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Customers','Customer Data','manage customer data'));

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_CUSTOMER,'Customer'));
    $omi->SetPageTitle('Customer Records');
    $omi->SetActionClass(KS_CLASS_ADMIN_CUSTOMERS);
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_CUST_NAME,'Name'));
    $omi->SetPageTitle('Name Records');
    $omi->SetActionClass(KS_CLASS_CUST_NAMES);
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_CUST_ADDR,'Address'));
    $omi->SetPageTitle('Address Records');
    $omi->SetActionClass(KS_CLASS_MAIL_ADDRS);
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_CUST_CARD,'Cards'));
    $omi->SetPageTitle('Payment Cards');
    $omi->SetActionClass(KS_CLASS_CUST_CARDS);
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_CUST_EMAIL,'Emails'));
    $omi->SetPageTitle('Email Addresses');
    $omi->SetActionClass(KS_CLASS_EMAIL_ADDRS);
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(KS_ACTION_CUST_PHONE,'Phones'));
    $omi->SetPageTitle('Phone Numbers');
    $omi->SetActionClass(KS_CLASS_CUST_PHONES);
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

/* 2016-12-11 old dropin version
$om = new fcMenuFolder($oRoot, '*cust','Customers','Customer Data','manage customer data');
  $om->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUSTOMER,'Customer','Customer Records');
    $omi->Controller(KS_CLASS_ADMIN_CUSTOMERS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_NAME,'Name','Name Records');
    $omi->Controller(KS_CLASS_CUST_NAMES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_ADDR,'Address','Address Records');
    $omi->Controller(KS_CLASS_MAIL_ADDRS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_CARD,'Cards','Payment Cards');
    $omi->Controller(KS_CLASS_CUST_CARDS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_EMAIL,'Emails','Email Addresses');
    $omi->Controller(KS_CLASS_EMAIL_ADDRS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_PHONE,'Phones','Phone Numbers');
    $omi->Controller(KS_CLASS_CUST_PHONES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
*/
// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.custs',
  'descr'	=> 'customer administration',
  'version'	=> '0.01',
  'date'	=> '2016-07-10',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'addr.php'		=> array(KS_CLASS_MAIL_ADDRS),
    'card.php'		=> array(KS_CLASS_CUST_CARDS),
    'cust.php'		=> array(KS_CLASS_ADMIN_CUSTOMERS),
    'email.php'		=> array(KS_CLASS_EMAIL_ADDRS),
    'name.php'		=> array(KS_CLASS_CUST_NAMES),
    'phone.php'		=> array(KS_CLASS_CUST_PHONES),
     ),
  'menu'	=> $om,
  );