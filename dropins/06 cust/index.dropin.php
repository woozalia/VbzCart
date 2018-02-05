<?php
/*
  PURPOSE: VbzCart drop-in descriptor for customer administration
  HISTORY:
    2014-01-16 started
*/

// CONSTANTS

// -- classes
define('KS_CLASS_ADMIN_CUSTOMERS','vctaCusts');
define('KS_CLASS_CUST_CARDS_ADMIN','vctAdminCustCards');
define('KS_CLASS_MAIL_ADDRS_ADMIN','vctAdminMailAddrs');
define('KS_CLASS_CUST_NAMES_ADMIN','vctAdminCustNames');
define('KS_CLASS_EMAIL_ADDRS_ADMIN','vctAdminEmailAddrs');
define('KS_CLASS_CUST_PHONES_ADMIN','vctAdminCustPhones');

define('KS_ACTION_CUSTOMER','cust');
define('KS_ACTION_CUST_NAME','c-name');
define('KS_ACTION_CUST_ADDR','c-addr');
define('KS_ACTION_CUST_CARD','c-card');
define('KS_ACTION_CUST_EMAIL','c-email');
define('KS_ACTION_CUST_PHONE','c-phone');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Customers','Customer Data','manage customer data'));

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_CUSTOMER,
      KS_CLASS_ADMIN_CUSTOMERS,
      'Customer')
    );

    //$omi->SetPageTitle('Customer Records');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_CUST_NAME,
      KS_CLASS_CUST_NAMES_ADMIN,
      'Name')
    );
    //$omi->SetPageTitle('Name Records');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_CUST_ADDR,
      KS_CLASS_MAIL_ADDRS_ADMIN,
      'Address')
    );
    //$omi->SetPageTitle('Address Records');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_CUST_CARD,
      KS_CLASS_CUST_CARDS_ADMIN,
      'Cards')
    );
    //$omi->SetPageTitle('Payment Cards');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_CUST_EMAIL,
      KS_CLASS_EMAIL_ADDRS_ADMIN,
      'Emails')
    );
    //$omi->SetPageTitle('Email Addresses');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(
    new fcDropinLink(
      KS_ACTION_CUST_PHONE,
      KS_CLASS_CUST_PHONES_ADMIN,
      'Phones')
    );
    //$omi->SetPageTitle('Phone Numbers');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

/* 2016-12-11 old dropin version
$om = new fcMenuFolder($oRoot, '*cust','Customers','Customer Data','manage customer data');
  $om->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUSTOMER,'Customer','Customer Records');
    $omi->Controller(KS_CLASS_ADMIN_CUSTOMERS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_NAME,'Name','Name Records');
    $omi->Controller(KS_CLASS_CUST_NAMES_ADMIN);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_ADDR,'Address','Address Records');
    $omi->Controller(KS_CLASS_MAIL_ADDRS_ADMIN);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_CARD,'Cards','Payment Cards');
    $omi->Controller(KS_CLASS_CUST_CARDS_ADMIN);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_EMAIL,'Emails','Email Addresses');
    $omi->Controller(KS_CLASS_EMAIL_ADDRS_ADMIN);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new fcMenuLink($om,KS_ACTION_CUST_PHONE,'Phones','Phone Numbers');
    $omi->Controller(KS_CLASS_CUST_PHONES_ADMIN);
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
    'addr.php'		=> array(KS_CLASS_MAIL_ADDRS_ADMIN),
    'card.php'		=> array(KS_CLASS_CUST_CARDS_ADMIN),
    'cust.php'		=> array(KS_CLASS_ADMIN_CUSTOMERS),
    'email.php'		=> array(KS_CLASS_EMAIL_ADDRS_ADMIN),
    'name.php'		=> array(KS_CLASS_CUST_NAMES_ADMIN),
    'phone.php'		=> array(KS_CLASS_CUST_PHONES_ADMIN),
     ),
  'menu'	=> $om,
  );