<?php
/*
  PURPOSE: VbzCart drop-in descriptor for local catalog management
  HISTORY:
    2013-12-15 adapting for drop-in system
    2016-01-31 "maintenance" option UI is working, but now it looks like
      it's not the way to do things -- so commenting it out.
    2016-02-01 moved price-funcs here -- constants need to be renamed (from *SUPPCAT*)
*/

// CONSTANTS

// -- tables
define('KS_CLASS_CATALOG_SUPPLIERS','VCA_Suppliers');
define('KS_CLASS_CATALOG_DEPARTMENTS','VCTA_Depts');
define('KS_CLASS_CATALOG_TITLES','VCTA_Titles');
define('KS_CLASS_CATALOG_TITLE','VCRA_Title');
define('KS_LOGIC_CLASS_LC_ITEMS','clsItems');
define('KS_ADMIN_CLASS_LC_ITEMS','VCA_Items');
define('KS_LOGIC_CLASS_LC_ITEM_TYPES','vctItTyps');
define('KS_ADMIN_CLASS_LC_ITEM_TYPES','vctaItemTypes');
define('KS_LOGIC_CLASS_LC_ITEM_OPTIONS','clsItOpts');
define('KS_ADMIN_CLASS_LC_ITEM_OPTIONS','vtItemOpts_admin');
define('KS_CLASS_CATALOG_IMAGES','VCTA_Images');
define('KS_CLASS_CATALOG_IMAGE','VCRA_Image');
define('KS_CLASS_FOLDERS','vctaFolders');
define('KS_ADMIN_CLASS_SHIP_COSTS','vctaShipCosts');

//define('KS_CLASS_CATALOG_MAINTENANCE','vcCatalogMaintenance');
define('KS_CLASS_CATALOG_TITLES_TOPICS','VCTA_TitlesTopics');
define('KS_CLASS_CATALOG_TOPICS','VCTA_Topics');
define('KS_CLASS_CATALOG_TOPIC','VCRA_Topic');
define('KS_CLASS_SUPPCAT_PRICES','vctaPriceFx');
  define('KS_CLASS_SUPPCAT_PRICE','vcraPriceFx');
// -- JOIN queries
define('KS_CLASS_JOIN_LCITEM_ORDERS','vctaLCItemOrders');
define('KS_CLASS_JOIN_TITLES','vcqtaTitlesInfo');
define('KS_CLASS_JOIN_TITLE','vcqraTitleInfo');
define('KS_CLASS_JOIN_TOPICS','vcqtTopicsInfo');

define('KS_ACTION_CATALOG_ITEM','item');
define('KS_ACTION_CATALOG_ITEM_TYPE','ittyp');
define('KS_ACTION_CATALOG_ITEM_OPTION','itopt');
define('KS_ACTION_CATALOG_DEPARTMENT','dept');
define('KS_ACTION_CATALOG_TITLE','title');
define('KS_ACTION_CATALOG_SUPPLIER','supp');
define('KS_ACTION_CATALOG_IMAGE','img');
define('KS_ACTION_FOLDER','fldr');
define('KS_ACTION_CATALOG_TOPIC','tpc');
define('KS_ACTION_SUPPCAT_PRICE','lcp');
define('KS_ACTION_SHIPCOST','shpcst');

// MENU

$om = new fcMenuFolder($oRoot, '*lcat','Our Catalog','Local Catalog','local catalog maintenance functions');
  $om->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,'supp','Suppliers','Catalog Suppliers');
    $omi->Controller(KS_CLASS_CATALOG_SUPPLIERS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,'dept','Departments','Catalog Departments');
    $omi->Controller(KS_CLASS_CATALOG_DEPARTMENTS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_CATALOG_TITLE,'Titles','Catalog Titles');
    $omi->Controller(KS_CLASS_CATALOG_TITLES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_CATALOG_ITEM,'Items','Catalog Items');
    $omi->Controller(KS_ADMIN_CLASS_LC_ITEMS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_CATALOG_ITEM_TYPE,'Item Types','Catalog Item Types');
    $omi->Controller(KS_ADMIN_CLASS_LC_ITEM_TYPES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_CATALOG_ITEM_OPTION,'Item Options','Catalog Item Options');
    $omi->Controller(KS_ADMIN_CLASS_LC_ITEM_OPTIONS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_CATALOG_IMAGE,'Images','Catalog Images');
    $omi->Controller(KS_CLASS_CATALOG_IMAGES);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
    
  $omi = new fcMenuLink($om,KS_ACTION_FOLDER,'Folders','Image Folders');
    $omi->Controller(KS_CLASS_FOLDERS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_CATALOG_TOPIC,'Topics','Catalog Topics');
    $omi->Controller(KS_CLASS_CATALOG_TOPICS);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN);
    
  $omi = new fcMenuLink($om,KS_ACTION_SUPPCAT_PRICE,'Prices','Price Functions','functions for setting retail prices');
    $omi->Controller(KS_CLASS_SUPPCAT_PRICES);
    $omi->NeedPermission(KS_PERM_SCAT_ADMIN);
/*
  $omi = new fcMenuLink($om,'maint','Maintenance','Catalog Maintenance');
    $omi->Controller(KS_CLASS_CATALOG_MAINTENANCE);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN); */

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.lcat',
  'descr'	=> 'local catalog maintenance functions',
  'version'	=> '0.2',
  'date'	=> '2016-03-05',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'dept.php'		=> array(KS_CLASS_CATALOG_DEPARTMENTS),
    'folder.php'	=> KS_CLASS_FOLDERS,
    'image.php'		=> array(KS_CLASS_CATALOG_IMAGES),
    'item.php'		=> array(KS_ADMIN_CLASS_LC_ITEMS),
    'item-opt.php'	=> array(KS_ADMIN_CLASS_LC_ITEM_OPTIONS),
    'item-type.php'	=> array(KS_ADMIN_CLASS_LC_ITEM_TYPES),
    //'maint.php'		=> array(KS_CLASS_CATALOG_MAINTENANCE),
    'price.php'		=> KS_CLASS_SUPPCAT_PRICES,
    'item-ord.info.php'	=> array(KS_CLASS_JOIN_LCITEM_ORDERS),
    'supp.php'		=> array(KS_CLASS_CATALOG_SUPPLIERS),
    'title.php'		=> array(KS_CLASS_CATALOG_TITLES,KS_CLASS_CATALOG_TITLE),
    'title-topic.php'	=> array(KS_CLASS_CATALOG_TITLES_TOPICS),
    'title.info.php'	=> array(KS_CLASS_JOIN_TITLES,KS_CLASS_JOIN_TITLE),
    'topic.php'		=> KS_CLASS_CATALOG_TOPICS,
    'topic.info.php'	=> array(KS_CLASS_JOIN_TOPICS),
    'ship-cost.php'	=> KS_ADMIN_CLASS_SHIP_COSTS
     ),
  'menu'	=> $om,
  'permit'	=> array('admin'),	// groups who are allowed access
  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );
