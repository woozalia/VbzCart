<?php
/*
  PURPOSE: VbzCart drop-in descriptor for local catalog management
  HISTORY:
    2013-12-15 adapting for drop-in system
    2016-01-31 "maintenance" option UI is working, but now it looks like
      it's not the way to do things -- so commenting it out.
    2016-02-01 moved price-funcs here -- constants need to be renamed (from *SUPPCAT*)
    2016-12-11 adapted to revised dropin system
*/

// CONSTANTS

// -- tables
define('KS_CLASS_CATALOG_SUPPLIERS','vctAdminSuppliers');
define('KS_CLASS_CATALOG_DEPARTMENTS','vctAdminDepts');
define('KS_CLASS_CATALOG_TITLES','vctAdminTitles');
define('KS_CLASS_CATALOG_TITLE','vcrAdminTitle');
define('KS_LOGIC_CLASS_LC_ITEMS','vctItems');
define('KS_ADMIN_CLASS_LC_ITEMS','vctAdminItems');
define('KS_ADMIN_CLASS_LC_ITEM_TYPES','vctaItemTypes');
define('KS_ADMIN_CLASS_LC_ITEM_OPTIONS','vtItemOpts_admin');
define('KS_CLASS_CATALOG_IMAGES','vctAdminImages');
define('KS_CLASS_CATALOG_IMAGE','vcrAdminImage');
define('KS_CLASS_FOLDERS_ADMIN','vctaFolders');
define('KS_CLASS_FOLDERS_ADMIN_INFO','vctaFoldersInfo');
define('KS_ADMIN_CLASS_SHIP_COSTS','vctaShipCosts');

//define('KS_CLASS_CATALOG_MAINTENANCE','vcCatalogMaintenance');
define('KS_CLASS_CATALOG_TITLES_TOPICS','vctTitlesTopics_admin');
define('KS_CLASS_CATALOG_TOPICS','vctAdminTopics');
define('KS_CLASS_CATALOG_TOPIC','vcrAdminTopic');
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
define('KS_ACTION_CATALOG_TOPIC','topic');
define('KS_ACTION_SUPPCAT_PRICE','lcp');
define('KS_ACTION_SHIPCOST','shpcst');

// MENU

$om = $oRoot->SetNode(new fcMenuFolder('Our Catalog'));

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_SUPPLIER,
    KS_CLASS_CATALOG_SUPPLIERS,
    'Suppliers','suppliers for the stuff we buy'));

    //$omi->SetPageTitle('Catalog Suppliers');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_DEPARTMENT,
    KS_CLASS_CATALOG_DEPARTMENTS,
    'Departments','sections in supplier catalogs'));

    //$omi->SetPageTitle('Local Catalog Departments');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_TITLE,
    KS_CLASS_CATALOG_TITLES,
    'Titles'));

    //$omi->SetPageTitle('Local Catalog Titles');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_ITEM,
    KS_ADMIN_CLASS_LC_ITEMS,
    'Items'));

    //$omi->SetPageTitle('Local Catalog Items');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_ITEM_TYPE,
    KS_ADMIN_CLASS_LC_ITEM_TYPES,
    'Item Types'));

    //$omi->SetPageTitle('Local Catalog Item Types');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_ITEM_OPTION,
    KS_ADMIN_CLASS_LC_ITEM_OPTIONS,
    'Item Options'));

    //$omi->SetPageTitle('Local Catalog Item Options');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_IMAGE,
    KS_CLASS_CATALOG_IMAGES,
    'Images'));

    //$omi->SetPageTitle('Catalog Images');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);
    
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_FOLDER,
    KS_CLASS_FOLDERS_ADMIN_INFO,
    'Folders'));

    //$omi->SetPageTitle('Image Folders');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);

  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_CATALOG_TOPIC,
    KS_CLASS_CATALOG_TOPICS,
    'Topics'));

    //$omi->SetPageTitle('Catalog Topics');
    $omi->SetRequiredPrivilege(KS_PERM_LCAT_ADMIN);
    
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_PRICE,
    KS_CLASS_SUPPCAT_PRICES,
    'Price Fx','functions for setting retail prices'));

    //$omi->SetPageTitle('Price Functions');
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);

/*
  $omi = new fcMenuLink($om,'maint','Maintenance','Catalog Maintenance');
    $omi->Controller(KS_CLASS_CATALOG_MAINTENANCE);
    $omi->NeedPermission(KS_PERM_LCAT_ADMIN); */

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.lcat',
  'descr'	=> 'local catalog maintenance functions',
  'version'	=> '0.3',
  'date'	=> '2017-03-24',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
    'dept.php'		=> array(KS_CLASS_CATALOG_DEPARTMENTS),
    'folder.php'	=> KS_CLASS_FOLDERS_ADMIN,
    'folder-info.php'	=> KS_CLASS_FOLDERS_ADMIN_INFO,
    'image.php'		=> array(KS_CLASS_CATALOG_IMAGES),
    'item.php'		=> array(KS_ADMIN_CLASS_LC_ITEMS),
    'item-opt.php'	=> array(KS_ADMIN_CLASS_LC_ITEM_OPTIONS,'vtAdminTableAccess_ItemOption'),
    'item-type.php'	=> array(KS_ADMIN_CLASS_LC_ITEM_TYPES,'vtAdminTableAccess_ItemType'),
    //'maint.php'		=> array(KS_CLASS_CATALOG_MAINTENANCE),
    'price.php'		=> KS_CLASS_SUPPCAT_PRICES,
    'item-ord.info.php'	=> array(KS_CLASS_JOIN_LCITEM_ORDERS),
    'supp.php'		=> array(KS_CLASS_CATALOG_SUPPLIERS,'vtTableAccess_Supplier_admin'),
    'title.php'		=> array(KS_CLASS_CATALOG_TITLES,KS_CLASS_CATALOG_TITLE),
    'title-topic.php'	=> array(KS_CLASS_CATALOG_TITLES_TOPICS),
    'title.info.php'	=> array(KS_CLASS_JOIN_TITLES,KS_CLASS_JOIN_TITLE),
    'topic.php'		=> KS_CLASS_CATALOG_TOPICS,
    'topic.info.php'	=> array(KS_CLASS_JOIN_TOPICS),
    'ship-cost.php'	=> KS_ADMIN_CLASS_SHIP_COSTS
     ),
  'menu'	=> $om,
  );
