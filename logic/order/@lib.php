<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'orders.php');
  $om->AddClass('vctOrders');
$om = new fcCodeModule(__FILE__, 'order-line.php');
  $om->AddClass('vctOrderLines');
$om = new fcCodeModule(__FILE__, 'order-msg.php');
  $om->AddClass('vctOrderMsgs');
