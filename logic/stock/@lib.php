<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

$om = new fcCodeModule(__FILE__, 'stock.php');
  $om->AddClass('vctStockLines');
$om = new fcCodeModule(__FILE__, 'stock-bin.info.php');
  $om->AddClass('vcqtStockBinsInfo');
$om = new fcCodeModule(__FILE__, 'stock-bin.logic.php');
  $om->AddClass('vctStockBins');
  $om->AddClass('vcrStockBin');
$om = new fcCodeModule(__FILE__, 'stock-line.info.php');
  $om->AddClass('vcqtStockLinesInfo');
$om = new fcCodeModule(__FILE__, 'stock-item.info.php');
  $om->AddClass('vcqtStockItemsInfo');
