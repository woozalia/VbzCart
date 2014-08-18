<?php
/*
  PURPOSE: VbzCart drop-in descriptor for supplier catalog management
  HISTORY:
    2013-12-15 adapting for drop-in system
*/

// CONSTANTS

define('KS_CLASS_SUPPCAT_SOURCES','VCTA_SCSources');
define('KS_CLASS_SUPPCAT_SOURCE','VCRA_SCSource');
define('KS_CLASS_SUPPCAT_GROUPS','VCTA_SCGroups');
define('KS_CLASS_SUPPCAT_TITLES','VCTA_SCTitles');
define('KS_CLASS_SUPPCAT_ITEMS','VCTA_SCItems');

// MENU ADDITIONS

$om = new clsMenuFolder(NULL, '*scat','Catalogs','Supplier Catalogs','supplier catalog entry functions');
/*
  $omi = new clsMenuLink($om,KS_ACTION_STOCK_PLACE,'Places','Stock Places','places where bins (stock boxes) may be found');
    $omi->Controller('VCM_StockPlaces');
  $omi = new clsMenuLink($om,KS_ACTION_STOCK_BIN,'Bins','Stock Bins','boxes for stock');
    $omi->Controller('VCM_StockBins');

	      $objRow->Add(new clsMenuItem('suppliers','supp'));
	      $objRow->Add(new clsMenuItem('images','cat.img'));
	      $objRow->Add(new clsMenuItem('topics','topic'));
	      $objRow->Add(new clsMenuItem('search','cat.search'));
*/
// MODULE SPEC ARRAY

$arDropin = array(
  'name'	=> 'vbz.scat',
  'descr'	=> 'supplier catalog entry functions',
  'version'	=> '0.0',
  'date'	=> '2014-03-24',
  'URL'		=> NULL,	// nothing yet
  'classes'	=> array(	// list of files and the classes they contain
      'source.php'	=> KS_CLASS_SUPPCAT_SOURCES,
      'group.php'	=> KS_CLASS_SUPPCAT_GROUPS,
      'item.php'	=> KS_CLASS_SUPPCAT_ITEMS,
      'title.php'	=> KS_CLASS_SUPPCAT_TITLES,
     ),
  'menu'	=> $om,
  'permit'	=> array('admin'),	// groups who are allowed access
  'requires'	=> array('vbz.syslog')	// other drop-ins this drop-in uses
  );
