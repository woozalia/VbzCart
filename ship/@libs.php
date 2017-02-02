<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'ship-zone.php');
  $om->AddClass('vcShipCountry');
