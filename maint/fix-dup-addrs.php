<?php
/*
 NAME: fix-dup-addrs
 PURPOSE: maintenance script for finding and fixing duplicate address records
 AUTHOR: Woozle (Nick) Staddon
 HISTORY:
	2013-10-23 started
*/

define('KFP_DATA','/var/www/data');

require_once('../site.php');
require_once('../../config-libs.php');
require_once('../config-libs.php');

function VbzDb() {
  static $objDb;

    if (!isset($objDb)) {
	$objDb = new clsDatabase(KS_DB_VBZCART);
	$objDb->Open();
    }
    return $objDb;
}

function Write($iText) {
    echo $iText;
}
function WriteLn($iText) {
    echo $iText."\n";
}

function doFix() {
    // STEP 1: Look for records that are effectively blank
    $rs = VbzDb()->Make('clsAdminCustAddrs')->GetData();	// get all address records
    while ($rs->NextRow()) {
	$sCalc = $rs->SearchString();
	$sSaved = $rs->Value('Search');
	if ($sCalc != $sSaved) {
	    WriteLn('Address ID='.$rs->KeyValue());
	    WriteLn(' - CURRENTLY: ['.$sSaved.']');
	    WriteLn(' - SHOULD BE: ['.$sCalc.']');
	}
    }
}

WriteLn('Fixing addresses...');
doFix();
WriteLn('Done fixing.');
