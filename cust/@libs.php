<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'cust.php');
  $om->AddClass('clsCusts');
$om = new fcCodeModule(__FILE__, 'cust-addr.php');
  $om->AddClass('vctCustAddrs');
$om = new fcCodeModule(__FILE__, 'cust-card.php');
  $om->AddClass('clsCustCards');
  $om->AddClass('clsCustCards_dyn');
$om = new fcCodeModule(__FILE__, 'cust-email.php');
  $om->AddClass('clsCustEmails');
$om = new fcCodeModule(__FILE__, 'cust-name.php');
  $om->AddClass('clsCustNames');
$om = new fcCodeModule(__FILE__, 'cust-phone.php');
  $om->AddClass('clsCustPhones');

