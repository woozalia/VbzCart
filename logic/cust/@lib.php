<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'cust.php');
  $om->AddClass('vctCusts');
$om = new fcCodeModule(__FILE__, 'cust-addr.php');
  $om->AddClass('vctMailAddrs');
$om = new fcCodeModule(__FILE__, 'cust-card.php');
  $om->AddClass('vctCustCards');
  $om->AddClass('vctCustCards_dyn');
$om = new fcCodeModule(__FILE__, 'cust-email.php');
  $om->AddClass('vctCustEmails');
$om = new fcCodeModule(__FILE__, 'cust-name.php');
  $om->AddClass('vctCustNames');
$om = new fcCodeModule(__FILE__, 'cust-phone.php');
  $om->AddClass('vctCustPhones');

