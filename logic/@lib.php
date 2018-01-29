<?php

$fp = dirname( __FILE__ );
fcCodeModule::BasePath($fp.'/');

define('KS_CLASS_FOLDERS','vctFolders');

$om = new fcCodeModule(__FILE__, 'folder.logic.php');
  $om->AddClass(KS_CLASS_FOLDERS);

require_once('cat/@lib.php');
require_once('cust/@lib.php');
require_once('order/@lib.php');
require_once('stock/@lib.php');
