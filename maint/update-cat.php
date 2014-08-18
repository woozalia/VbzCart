<?php
/*
 NAME: update-cat
 PURPOSE: maintenance script for updating item status in catalog
 AUTHOR: Woozle (Nick) Staddon
 REQUIRES: data.php, site.php, store.php
 VERSION:
  2010-06-27 Excerpting relevant code from SpecialVbzAdmin
  2010-11-10 "data_tables" has been renamed "cache_tables"
  2010-12-29 added ID_Supp to 1.3
  2011-01-02 added update of cat_items.Descr
*/
//define('KFP_LIB','../../');
/*
require_once('../../server.php');
require_once('../site.php');
require_once('../../cache.php');
require_once('../shop.php');
require_once('../ckout.php');
require_once('../base.admin.php');
require_once('../admin.cache.php');
require_once('../../data.php');
require_once('../store.php');
*/
require_once '/var/www/vbz/local.php';				// basic library paths
require_once(KFP_LIB_VBZ.'/config-libs.php');

function VbzDb() {
  static $objDb;

    if (!isset($objDb)) {
	$objDb = new clsVbzAdminData(KS_DB_VBZCART);
	$objDb->Open();
    }
    return $objDb;
}
function Write($iText) {
    echo $iText;
}
function WriteLn($iText=NULL) {
    echo $iText."\n";
}
WriteLn('>>>>> UPDATE-CAT START <<<<<');
WriteLn('Checking catalog tables...');
$db = VbzDb();
$mgr = $db->CacheMgr();
$tblTbls = $mgr->Tables;
$tblTbls->UpdatedTargets();
WriteLn('<<<<< UPDATE-CAT DONE >>>>>');
