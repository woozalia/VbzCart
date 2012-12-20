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
  2012-04-18 (Wzl) significant rewriting of cart access
    Removing globals: $fpTools, $fwpLogo
*/
define('kEmbeddedPagePrefix','embed:');

/*
if (defined( '__DIR__' )) {
  $fpThis = __DIR__;
} else {
  $fpThis = dirname(__FILE__);
}
*/
if (!defined('LIBMGR')) {
    require(KFP_LIB.'/libmgr.php');
}
clsLibMgr::Add('strings',	KFP_LIB.'/strings.php',__FILE__,__LINE__);
clsLibMgr::Add('string.tplt',	KFP_LIB.'/StringTemplate.php',__FILE__,__LINE__);
clsLibMgr::Add('cache'	,	KFP_LIB.'/cache.php',__FILE__,__LINE__);
clsLibMgr::Add('dtree',		KFP_LIB.'/dtree.php',__FILE__,__LINE__);
clsLibMgr::Add('events',	KFP_LIB.'/events.php',__FILE__,__LINE__);
clsLibMgr::Add('vbz.data',	KFP_LIB_VBZ.'/vbz-data.php',__FILE__,__LINE__);
clsLibMgr::Add('vbz.shop',	KFP_LIB_VBZ.'/shop.php',__FILE__,__LINE__);
clsLibMgr::Add('vbz.cat',	KFP_LIB_VBZ.'/base.cat.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsCatPages','vbz.cat');
  clsLibMgr::AddClass('clsCatPage','vbz.cat');
clsLibMgr::Add('vbz.cat.page',	KFP_LIB_VBZ.'/page-cat.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsTitles_StoreUI','vbz.cat.page');
clsLibMgr::Add('topic',		KFP_LIB_VBZ.'/topic.php',__FILE__,__LINE__);

clsLibMgr::Load('strings',__FILE__,__LINE__);
clsLibMgr::Load('string.tplt',__FILE__,__LINE__);
clsLibMgr::Load('cache',__FILE__,__LINE__);
clsLibMgr::Load('dtree',__FILE__,__LINE__);
clsLibMgr::Load('events',__FILE__,__LINE__);
clsLibMgr::Load('vbz.data',__FILE__,__LINE__);
clsLibMgr::Load('topic',__FILE__,__LINE__);

define('EN_PGTYPE_NOTFND',-1);	// requested item (supp/dept/title) not found
define('EN_PGTYPE_HOME',1);	// catalog home page
define('EN_PGTYPE_SUPP',2);	// supplier page
define('EN_PGTYPE_DEPT',3);	// department page, or possibly title for keyless dept
define('EN_PGTYPE_TITLE',4);	// title page
// table names
// - stock
define('ksTbl_stock_places','stk_places');
define('ksTbl_stock_bins','stk_bins');
//define('ksQry_stock_bins_wInfo','qryStk_Bins_w_info');
define('ksTbl_stock_items','stk_items');
define('ksTbl_stock_hist_items','stk_history');
define('ksTbl_stock_hist_bins','stk_bin_history');
// - browsing
define('ksTbl_title_topics','brs_titles_x_topics');

global $vbgImgSize;
$vbgImgSize = array(
    'th'	=> 'thumbnail',
    'sm'	=> 'small',
    'big' 	=> 'large',
    'huge'	=> 'huge',
    'zoom'	=> 'detail');

$intCallDepth = 0;

// CALCULATED GLOBALS
//$fpTools = '/tools';
$fpPages = '';
$fwpAbsPages = 'http://'.KS_PAGE_SERVER.$fpPages;
//$fwpAbsTools = 'http://'.KS_TOOLS_SERVER.$fpTools;
$fwpCart = $fwpAbsPages.'/cart/';
$strCurServer = NzArray($_SERVER,'SERVER_NAME');

// SET UP DEPENDENT VALUES
/*
if ($strCurServer != KS_TOOLS_SERVER) {
  $fpTools = $fwpAbsTools;
  $fpPages = $fwpAbsPages;
}
*/
//$fwpLogo = $fpTools.'/img/logos/v/';

class clsCacheFile_vbz extends clsCacheFile {
    public function __construct() {
	parent::__construct(KFP_CACHE);
    }
}
class clsVbzData extends clsDatabase {
    protected $objApp;
//    protected $objPages;	// who uses this?

    public function __construct($iSpec) {
	parent::__construct($iSpec);
	$this->Open();

	clsLibMgr::AddClass('clsSuppliers_StoreUI','vbz.cat');
    }
    public function App(clsVbzApp $iApp=NULL) {
	if (!is_null($iApp)) {
	    $this->objApp = $iApp;
	}
	return $this->objApp;
    }
// cache manager
    protected function CacheMgr_empty() {
	return new clsCacheMgr($this);
    }
    public function CacheMgr() {
	if (empty($this->objCacheMgr)) {
	    $objCache = $this->CacheMgr_empty();
	    $objCache->SetTables('cache_tables','cache_queries','cache_flow','cache_log');
	    $this->objCacheMgr = $objCache;
	}
	return $this->objCacheMgr;
    }
// table-specific functions
    public function Pages($id=NULL) {
	return $this->Make('clsCatPages',$id);
    }
    public function Suppliers($id=NULL) {
	return $this->Make('clsSuppliers_StoreUI',$id);
    }
    public function Depts($id=NULL) {
	return $this->Make('clsDepts',$id);
    }
    public function Titles($id=NULL) {
	return $this->Make('clsTitles_StoreUI',$id);
    }
    public function Items($id=NULL) {
	return $this->Make('clsItems',$id);
    }
    public function Items_Stock($id=NULL) {
	return $this->Make('clsItems_Stock',$id);
    }
    public function Items_Cat($id=NULL) {
	return $this->Make('clsItems_info_Cat',$id);
    }
    public function ItTyps($id=NULL) {
	return $this->Make('clsItTyps',$id);
    }
    public function ItOpts($id=NULL) {
	return $this->Make('clsItOpts',$id);
    }
    public function ShipCosts($id=NULL) {
	return $this->Make('clsShipCosts',$id);
    }
    public function Folders($id=NULL) {
	return $this->Make('clsVbzFolders',$id);
    }
    public function Images($id=NULL) {
	return $this->Make('clsImages_StoreUI',$id);
    }
    public function StkItems($id=NULL) {
	return $this->Make('clsStkItems',$id);
    }
    public function Topics($iID=NULL) {
	return $this->Make('clsTopics_StoreUI',$iID);
    }
    public function TitleTopic_Titles() {
	return $this->Make('clsTitleTopic_Titles');
    }
    public function TitleTopic_Topics() {
	return $this->Make('clsTitleTopic_Topics');
    }
    public function VarsGlobal($id=NULL) {
	return $this->Make('clsGlobalVars',$id);
    }
    public function Events($id=NULL) {
	return $this->Make('clsEvents',$id);
    }
    public function LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere) {
	return $this->Events()->LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere);
    }
// Page output routines
/*
    public function Formatter() {
	if (!isset($this->objFmt)) {
	    $this->objFmt = new clsPageOutput();
	}
	return $this->objFmt;
    }
    public function SectionHdr($iTitle) {
	$this->Formatter()->Clear();
	return $this->Formatter()->SectionHdr($iTitle);
    }
*/
    /*----
      NOTES:
	2011-02-02 This really should somehow provide a more general service. Right now, it's only used for displaying
	  department pages -- but topic pages and searches could make use of it as well, if there were some tidy way
	  to abstract the collecting of the price and stock data. This will probably necessitate adding hi/low price
	  data to _titles and possibly _dept_ittyps (it's already in _title_ittyps).
      HISTORY:
	2011-02-02
	  * Removed assertion that $objImgs->Res is a resource, because if there are no thumbnails for this title,
	    then apparently it isn't, and apparently RowCount() handles this properly.
	  * Also fixed references to qtyForSale -- should be qtyInStock.
    */
    public function ShowTitles($iHdrText,$iList,$objNoImgSect) {
	$cntImgs = 0;
	$outImgs = '';
	foreach ($iList as $i => $row) {
	    $objTitle = $this->Titles()->GetItem($row['ID']);
	    $objImgs = $objTitle->ListImages('th');
	    //assert('is_resource($objImgs->Res); /* TYPE='.get_class($objImgs).' SQL='.$objImgs->sqlMake.' */');
	    $currMinPrice = $row['currMinPrice'];
	    $currMaxPrice = $row['currMaxPrice'];
	    $strPrice = DataCurr($currMinPrice);
	    if ($currMinPrice != $currMaxPrice) {
	      $strPrice .= '-'.DataCurr($currMaxPrice);
	    }
	    if ($objImgs->RowCount()) {
	      $cntImgs++;
	      $strCatNum = $objTitle->CatNum();
	      $strTitleTag = '&quot;'.$objTitle->Name.'&quot; ('.$strCatNum.')';
	      $strTitleLink = $objTitle->Link();
	      while ($objImgs->NextRow()) {
		$strImgTag = $strTitleTag.' - '.$strPrice;
		$qtyStk = $row['qtyInStock'];
		if ($qtyStk) {
		  $strImgTag .= ' - '.$qtyStk.' in stock';
		}
		$outImgs .= $strTitleLink.'<img class="thumb" src="'.$objImgs->WebSpec().'" title="'.$strImgTag.'"></a>';
	      }
	    } else {
		if (!$objNoImgSect->inTbl) {
		    $objNoImgSect->StartTable('titles without images:');
		    $objNoImgSect->AddText('<tr class=main><th>Cat. #</th><th>Title</th><th>Price<br>Range</th><th>to<br>order</th><th>status</th></tr>');
		}
		$objNoImgSect->RowStart();
		$objNoImgSect->ColAdd('<b>'.$objTitle->CatNum().'</b>');
		$objNoImgSect->ColAdd($objTitle->Name);
		$objNoImgSect->ColAdd($strPrice);
		$objNoImgSect->ColAdd('<b>[</b>'.$objTitle->Link().'order</a><b>]</b>');
		$qtyStk = $row['qtyInStock'];
		if ($qtyStk) {
		    $strStock = '<b>'.$qtyStk.'</b> in stock';
		    if ($row['cntInPrint'] == 0) {
			$strStock .= ' - OUT OF PRINT!';
		    }
		    $objNoImgSect->ColAdd($strStock);
		} else {
		    $objNoImgSect->ColAdd('<a title="explanation..." href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'">available, not in stock</a>');
	// Debugging:
	//          $objNoImgSect->ColAdd('ID_Title='.$objTitle->ID.' ID_ItTyp='.$objTitle->idItTyp);
		}
		$objNoImgSect->RowStop();
	    }
	}
	$out = '';
	if ($cntImgs) {
	    $out .= $this->SectionHdr($iHdrText);
	    $out .= $outImgs;
	}
	return $out;
    }
}

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
/*
    public function __set($iName, $iValue) {
	$this->SetVar($iName,$iValue);
    }
    public function __get($iName) {
	return $this->GetVar($iName);
    }
*/
}
/* ==================== *\
    UTILITY FUNCTIONS
\* ==================== */

function DataCents($iCents,$iPfx='$') {
  $out = $iPfx.sprintf("%01.2f",$iCents/100);
  return $out;
}
function DataCurr($iCurr,$iPfx='$') {
    if (is_null($iCurr)) {
	return NULL;
    } else {
	$out = $iPfx.sprintf("%01.2f",$iCurr);
	return $out;
    }
}
function DataDate($iDate) {
    if (is_string($iDate)) {
      $objDate = new DateTime($iDate);
//  if ($iDate == 0) {
//    $out = '';
//  } else {
//    $out = date('Y-m-d',$iDate);
      $out = $objDate->format('Y-m-d');
    } else {
      $out = '';
    }
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
function ArrayToAttrs(array $iarAttr=NULL) {
    $htAttr = '';
    if (is_array($iarAttr)) {
	foreach ($iarAttr as $key => $val) {
	    $htAttr .= ' '.$key.'="'.$val.'"';
	}
    }
    return $htAttr;
}

/* ==================== *\
    MISSING FUNCTIONS
\* ==================== */
if (!function_exists('http_redirect')) {
    function http_redirect($iURL) {
	header('Status: 301 Moved Permanently',TRUE);
	header('Location: '.$iURL,TRUE);
    }
}
