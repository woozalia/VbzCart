<?php
/*
 FILE: store.php
 PURPOSE: vbz class library - should eventually be subdivided
 HISTORY:
  2009-07-11 (Wzl) Lots of cleanup; added types and methods needed for shopping cart page
  2009-10-06 (Wzl) Changed most clsTitle* classes to clsVbzTitle* (conflict with something - w3tpl?)
    Additional changes needed so it would work on Rizzo
  2010-06-12 (Wzl) A little cleanup; clsTopic::Titles()
  2010-06-14 (Wzl) clsVbzTable::GetItem() handles non-numeric IDs by creating new object and passing along ID
  2011-01-18 (Wzl) moved clsTopic(s) to topic.php
  2011-01-25 (Wzl) extracted clsVbzTable and clsVbzRecs to vbz-data.php
  2012-04-18 (Wzl) significant rewriting of cart access
    Removing globals: $fpTools, $fwpLogo
*/
define('kEmbeddedPagePrefix','embed:');

define('EN_PGTYPE_NOTFND',-1);	// requested item (supp/dept/title) not found
define('EN_PGTYPE_HOME',1);	// catalog home page
define('EN_PGTYPE_SUPP',2);	// supplier page
define('EN_PGTYPE_DEPT',3);	// department page, or possibly title for keyless dept
define('EN_PGTYPE_TITLE',4);	// title page
// table names
// - stock
//define('ksTbl_stock_places','stk_places');
//define('ksTbl_stock_bins','stk_bins');
//define('ksQry_stock_bins_wInfo','qryStk_Bins_w_info');
//define('ksTbl_stock_items','stk_items');
define('ksTbl_stock_hist_items','stk_history');
define('ksTbl_stock_hist_bins','stk_bin_history');
// - browsing
define('ksTbl_title_topics','brs_titles_x_topics');

$intCallDepth = 0;

// CALCULATED GLOBALS
//$fpTools = '/tools';
$fpPages = '';
/*
$fwpAbsPages = 'http://'.KS_PAGE_SERVER.$fpPages;
//$fwpAbsTools = 'http://'.KS_TOOLS_SERVER.$fpTools;
$fwpCart = $fwpAbsPages.'/cart/';
clsModule::LoadFunc('NzArray');	// make sure this function is loaded
$strCurServer = NzArray($_SERVER,'SERVER_NAME');

// SET UP DEPENDENT VALUES
if ($strCurServer != KS_TOOLS_SERVER) {
  $fpTools = $fwpAbsTools;
  $fpPages = $fwpAbsPages;
}
*/
//$fwpLogo = $fpTools.'/img/logos/v/';

/* ======================
 GLOBAL VARIABLES CLASS
*/
class clsGlobalVars extends clsTable {
    const TableName='var_global';
    private $strOld;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('Name');
//	  $this->ClassSng('clsGlobalVar');
    }
    public function Exists($iName) {
	$sqlFilt = 'Name="'.$iName.'"';
	$objRow = $this->GetData($sqlFilt);
	if ($objRow->hasRows()) {
	    $objRow->NextRow();
	    $this->strOld = $objRow->Value;
	    return TRUE;
	} else {
	    unset($this->strOld);
	    return FALSE;
	}
    }
    private function GetVar($iName) {
	if ($this->Exists($iName)) {
	    return $this->strOld;
	} else {
	    return NULL;
	}
    }
    private function SetVar($iName, $iValue) {
	$strWhere = __METHOD__;		// should return class::function
	$sqlVal = SQLValue($iValue);
	$sqlName = SQLValue($iName);

	if ($this->Exists($iName)) {
	    $this->objDB->LogEvent($strWhere,
	      '|name='.$iName.'|old='.$this->strOld.'|new='.$sqlVal,
	      'global updated: '.$iName,
	      'VAR-U',FALSE,FALSE);

	    $arUpd = array(
	      'Value' => $sqlVal,
	      'WhenUpdated' => 'NOW()'
	      );
	    $this->Update($arUpd,'Name='.$sqlName);
	} else {
	    $this->objDB->LogEvent($strWhere,
	      '|name='.$iName.'|val='.$sqlVal,
	      'global added: '.$iName,
	      'VAR-I',FALSE,FALSE);
	    $arIns = array(
	      'Name' => $sqlName,
	      'Value' => $sqlVal,
	      'WhenCreated' => 'NOW()'
	      );
	    $this->Insert($arIns);
	}
    }
    public function Val($iName,$iValue=NULL) {
	if (is_null($iValue)) {
	    return $this->GetVar($iName);
	} else {
	    return $this->SetVar($iName,$iValue);
	}
    }
}
/* ==================== *\
    UTILITY FUNCTIONS
\* ==================== */

/* 2013-11-25 these don't seem to be used anymore
function DataCents($iCents,$iPfx='$') {
  $out = $iPfx.sprintf("%01.2f",$iCents/100);
  return $out;
}
function DataDateTime($iDate) {
    if (is_string($iDate)) {
	if ($iDate == '') {
	    $out = '-';
	} else {
	    $objDate = new DateTime($iDate);
	    $out = $objDate->format('Y-m-d H:i');
	}
    } else {
      $out = '';
    }
    return $out;
}
function IsEmail($iAddr) {
    $ok = preg_match('/^[0-9,a-z,\-,\.]{1,}@([0-9,a-z,\-][\.]{0,1}){2,}$/i', $iAddr );
    return $ok;
}
*/

