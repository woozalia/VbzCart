<?php
/*
  PURPOSE: VbzCart drop-in descriptor for local catalog management
  HISTORY:
    2013-12-15 adapting for drop-in system
*/

// CONSTANTS

define('KS_CLASS_CATALOG_SUPPLIERS','VCA_Suppliers');
define('KS_CLASS_CATALOG_DEPARTMENTS','VCTA_Depts');
define('KS_CLASS_CATALOG_TITLES','VCTA_Titles');
define('KS_CLASS_CATALOG_TITLE','VCRA_Title');
define('KS_CLASS_CATALOG_ITEMS','VCA_Items');
define('KS_CLASS_CATALOG_IMAGES','VCTA_Images');
define('KS_CLASS_CATALOG_IMAGE','VCRA_Image');
//define('KS_CLASS_CATALOG_TITLE_TOPIC_TITLES','VCTA_TitleTopic_Titles');
//define('KS_CLASS_CATALOG_TITLE_TOPIC_TOPICS','VCTA_TitleTopic_Topics');
define('KS_CLASS_CATALOG_TITLES_TOPICS','clsTitlesTopics');	// using base class for now
define('KS_CLASS_CATALOG_TOPICS','VCTA_Topics');
define('KS_CLASS_CATALOG_TOPIC','VCRA_Topic');

define('KS_ACTION_CATALOG_IMAGE','img');
define('KS_ACTION_CATALOG_TOPIC','tpc');

// MENU

$om = new clsMenuFolder(NULL, '*lcat','Our Catalog','Local Catalog','local catalog maintenance functions');
  $om->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,'supp','Suppliers','Catalog Suppliers');
    $omi->Controller(KS_CLASS_CATALOG_SUPPLIERS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,'title','Titles','Catalog Titles');
    $omi->Controller(KS_CLASS_CATALOG_TITLES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,'item','Items','Catalog Items');
    $omi->Controller(KS_CLASS_CATALOG_ITEMS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,KS_ACTION_CATALOG_IMAGE,'Images','Catalog Images');
    $omi->Controller(KS_CLASS_CATALOG_IMAGES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
  $omi = new clsMenuLink($om,KS_ACTION_CATALOG_TOPIC,'Topics','Catalog Topics');
    $omi->Controller(KS_CLASS_CATALOG_TOPICS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.lcat',
  'descr'	=> 'local catalog maintenance functions',
  'version'	=> '0.0',
  'date'	=> '2013-12-15',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'dept.php'		=> array(KS_CLASS_CATALOG_DEPARTMENTS),
    'image.php'		=> array(KS_CLASS_CATALOG_IMAGES),
    'item.php'		=> array(KS_CLASS_CATALOG_ITEMS),
    'supp.php'		=> array(KS_CLASS_CATALOG_SUPPLIERS),
    'title.php'		=> array(KS_CLASS_CATALOG_TITLES,KS_CLASS_CATALOG_TITLE),
    'topic.php'		=> KS_CLASS_CATALOG_TOPICS,
     ),
  'menu'	=> $om,
  'permit'	=> array('admin'),	// groups who are allowed access
  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );
