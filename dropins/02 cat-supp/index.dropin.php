<?php
/*
  PURPOSE: VbzCart drop-in descriptor for supplier catalog management
  HISTORY:
    2013-12-15 adapting for drop-in system
    2016-12-11 adapting to revised dropin system
    2017-01-01 adapting again
*/

// CONSTANTS

define('KS_CLASS_SUPPCAT_SUPPLIERS','vctaSCSuppliers');
  define('KS_CLASS_SUPPCAT_SUPPLIER','vcraSCSupplier');
define('KS_CLASS_SUPPCAT_SOURCES','vctaSCSources');
  define('KS_CLASS_SUPPCAT_SOURCE','vcraSCSource');
define('KS_CLASS_SUPPCAT_GROUPS','vctaSCGroups');
  define('KS_CLASS_SUPPCAT_GROUP','vcraSCGroup');
define('KS_CLASS_SUPPCAT_TITLES','vctaSCTitles');
  define('KS_CLASS_SUPPCAT_TITLE','vcraSCTitle');
define('KS_CLASS_SUPPCAT_ITEMS','vctaSCItems');
  define('KS_CLASS_SUPPCAT_ITEM','vcraSCItem');
// -- queries
define('KS_QUERY_CLASS_SUPPCAT_SOURCES_WITH_SUPPLIERS','vcqtaSCSources_wSupplier');
define('KS_QUERY_CLASS_SUPPCAT_BUILDER','vctCatalogBuilder');
// -- managers
define('KS_CLASS_TITLE_ENTRY_MANAGER','vcTitleEntryManager');

define('KS_ACTION_SUPPCAT_SUPPLIER','scsupp');
define('KS_ACTION_SUPPCAT_SOURCE','scsrce');
define('KS_ACTION_SUPPCAT_GROUP','scg');
define('KS_ACTION_SUPPCAT_TITLE','sct');
define('KS_ACTION_SUPPCAT_ITEM','sci');
define('KS_ACTION_SUPPCAT_BUILD','scbuild');

// MENU ADDITIONS

$om = $oRoot->SetNode(new fcMenuFolder('Their Catalogs'));
  
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_SUPPLIER,
    KS_CLASS_SUPPCAT_SUPPLIERS,
    'Suppliers',
    'suppliers who provide catalogs'));

    //$omi->SetPageTitle('Catalog Suppliers');
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);

  // SC SOURCES (catalogs)
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_SOURCE,
    KS_CLASS_SUPPCAT_SOURCES,
    'Sources',
    'catalogs provided by suppliers'));
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);

  // SC GROUPS
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_GROUP,
    KS_CLASS_SUPPCAT_GROUPS,
    'Groups',
    'collections of common item feature-sets'));
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);

  // SC TITLES
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_TITLE,
    KS_CLASS_SUPPCAT_TITLES,
    'Titles',
    'titles listed in supplier catalogs'));
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);
  
  // SC ITEMS
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_ITEM,
    KS_CLASS_SUPPCAT_ITEMS,
    'Items',
    'sets of item features'));
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);

  // BUILDER
  $omi = $om->SetNode(new fcDropinLink(
    KS_ACTION_SUPPCAT_BUILD,
    KS_QUERY_CLASS_SUPPCAT_BUILDER,
    'Build',
    'build the local catalog from supplier catalogs'));

    //$omi->SetPageTitle('SCM build process');
    $omi->SetRequiredPrivilege(KS_PERM_SCAT_ADMIN);

/* 2016-12-11 old dropin system

$om = new fcMenuFolder($oRoot, '*scat','Their Catalogs','Supplier Catalogs','supplier catalog entry functions');
  $om->NeedPermission(KS_PERM_SCAT_ADMIN);
  
  $omi = new fcMenuLink($om,KS_ACTION_SUPPCAT_SUPPLIER,'Suppliers','Catalog Suppliers',
    'suppliers who provide catalogs');
    $omi->Controller(KS_CLASS_SUPPCAT_SUPPLIERS);
    $omi->NeedPermission(KS_PERM_SCAT_ADMIN);
  
  $omi = new fcMenuLink($om,KS_ACTION_SUPPCAT_SOURCE,'Sources','Source Catalogs','catalogs provided by suppliers');
    $omi->Controller(KS_CLASS_SUPPCAT_SOURCES);
    $omi->NeedPermission(KS_PERM_SCAT_ADMIN);
    
  $omi = new fcMenuLink($om,KS_ACTION_SUPPCAT_GROUP,'Groups','SCM Groups','collections of common item feature-sets');
    $omi->Controller(KS_CLASS_SUPPCAT_GROUPS);
    $omi->NeedPermission(KS_PERM_SCAT_ADMIN);

  $omi = new fcMenuLink($om,KS_ACTION_SUPPCAT_ITEM,'Items','SCM Group Items','sets of item features');
    $omi->Controller(KS_CLASS_SUPPCAT_ITEMS);
    $omi->NeedPermission(KS_PERM_SCAT_ADMIN);
    
  $omi = new fcMenuLink($om,KS_ACTION_SUPPCAT_BUILD,'Build',
    'SCM build process','build the local catalog from supplier catalogs');
    $omi->Controller(KS_QUERY_CLASS_SUPPCAT_BUILDER);
    $omi->NeedPermission(KS_PERM_SCAT_ADMIN);
*/

// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.scat',
  'descr'	=> 'supplier catalog entry functions',
  'version'	=> '0.0',
  'date'	=> '2017-06-12',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
      'build.php'	=> KS_QUERY_CLASS_SUPPCAT_BUILDER,
      'group.php'	=> KS_CLASS_SUPPCAT_GROUPS,
      'item.php'	=> KS_CLASS_SUPPCAT_ITEMS,
      'qry.source-info.php'	=> KS_QUERY_CLASS_SUPPCAT_SOURCES_WITH_SUPPLIERS,
      'source.php'	=> array(KS_CLASS_SUPPCAT_SOURCES,'vctaSCSources_base'),
      'source.entry.php'=> KS_CLASS_TITLE_ENTRY_MANAGER,
      'supp.php'	=> KS_CLASS_SUPPCAT_SUPPLIERS,
      'title.php'	=> KS_CLASS_SUPPCAT_TITLES,
     ),
  'menu'	=> $om,
  );
