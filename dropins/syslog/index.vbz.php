<?php
/*
  PURPOSE: VbzCart drop-in descriptor for stock management
  TODO: menu
  HISTORY:
    2013-12-07 started
*/

// CONSTANTS

define('KS_CLASS_EVENT_LOG','VCM_Syslog');

// MENU

$om = new clsMenuLink(NULL, 'syslog','Syslog','System Log','system event log management');
  $om->Controller(KS_CLASS_EVENT_LOG);
  $om->NeedPermission(KS_PERM_ORDER_ADMIN);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.syslog',
  'descr'	=> 'system event logging',
  'version'	=> '0.0',
  'date'	=> '2013-12-07',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'syslog.php'	=> array(KS_CLASS_EVENT_LOG)
     ),
  'menu'	=> $om,
  'permit'	=> array('admin')	// groups who are allowed access via the menu
  );
