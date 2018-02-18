<?php
/*
  FILE: data.titles.php -- VbzCart data-handling classes: titles
  HISTORY:
    2013-02-09 created; splitting off Title-related classes from base.cat
    2013-11-14 renamed from data.titles.php to vbz-cat-title.php
    2016-01-23 significant rewrite to make URL-to-object more sensible
    2016-12-03 trait for easier Table access in other classes
*/
trait vtTableAccess_Title {
    protected function TitlesClass() {
	return 'vctTitles';
    }
    protected function TitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TitlesClass(),$id);
    }
}

class vctTitles extends vcBasicTable {

    // ++ SETUP ++ //

    protected function TableName() {
	return 'cat_titles';
    }
    protected function SingularName() {
	return 'vcrTitle';
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return 'vctItems';
    }
    protected function DepartmentsClass() {
	return 'vctDepts';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    protected function DepartmentTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->DepartmentsClass(),$id);
    }
    // PUBLIC so records can use it too
    public function ItemInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtItemsInfo');
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      NOTES:
	* SanitizeAndQuote() apparently doesn't quote if it sees a number, which means that
	  "002" doesn't get quoted, which means that the query would see it as equal to "2", "02",
	  "0002", etc. So I just sanitize it and then force the quote.
	* If one Department has a PageKey and another one doesn't, and they both have a Title with
	  the same CatKey, then just searching the Titles for a matching CatKey will return two
	  records. SO: If we're searching Titles for a CatKey with no Department specified, we have
	  to assume we're only looking at Departments with no PageKey. This is why we have to JOIN
	  with the Departments table and specify "AND (d.PageKey IS NULL)".
      HISTORY:
	2016-03-22 Changed Dept.CatKey to Dept.PageKey in notes above and in SQL. Dept.PageKey is what
	  determines the Dept's URL fragment; Dept.CatKey only affects the catalog #. There probably
	  ought to be a wiki page about this, to help keep it straight (and to remind myself of why
	  I even do it).
	2016-11-28 We have to look up the Department, if there is one, because Departments can exist
	  even if they're not used to build the catalog number. Dept.CatKey = NULL will also satisfy the search.
	  (This will also be true if Title.ID_Dept is NULL, so no need to test that separately.)
      RULES: Title URLs always follow the catalog # format, even if there are Folder Departments.
    */
    public function GetRecord_bySupplier_andCatKey($idSupp,$sKey) {
	$sqlCatKey = $this->GetConnection()->Sanitize_andQuote(strtoupper($sKey));
	$sqlThis = $this->TableName_Cooked();
	$sqlDept = $this->DepartmentTable()->TableName_Cooked();

	// new SQL
	$sql = <<<__END__
SELECT t.*
  FROM $sqlThis AS t LEFT JOIN $sqlDept AS d ON t.ID_Dept=d.ID
WHERE
    (t.ID_Supp=$idSupp) AND (t.CatKey=$sqlCatKey) AND (d.CatKey IS NULL)
__END__;
	
	/* 2016-11-07 This wasn't returning a result. I don't see why Depts were included in the search, either.
	$sqlDepts = $this->DepartmentTable()->TableName_Cooked();
	$sql = <<<__END__
SELECT t.*
  FROM $sqlThis AS t
  LEFT JOIN $sqlDepts AS d ON t.ID_Dept=d.ID
WHERE
    (ID_Supp=$idSupp) AND (t.CatKey=$sqlCatKey) and (d.CatKey IS NULL)
__END__;
*/
	$rc = $this->FetchRecords($sql);
	$nRows = $rc->RowCount();
	if ($nRows == 1) {
	    $rc->NextRow();
	} elseif ($nRows > 1) {
	    $sMsg = "VbzCart data error: $nRows titles found for code '$sKey' in supplier ID $idSupp."
	      .' SQL=['.$this->sql.']'
	      ;
	    throw new exception($sMsg);
	}
	return $rc;
    }
    public function GetRecord_byDepartment_andCatKey($idDept,$sKey) {
	$sqlCatKey = $this->GetConnection()->SanitizeString(strtoupper($sKey));
	
	$sqlFilt = "(ID_Dept=$idDept) AND (CatKey='$sKey')";
	$rc = $this->SelectRecords($sqlFilt);
	$nRows = $rc->RowCount();
	if ($nRows == 1) {
	    $rc->NextRow();
	} elseif ($nRows > 1) {
	    $sMsg = "VbzCart data error: $nRows titles found for code '$sKey' in department ID $idDept."
	      .' SQL=['.$rc->sqlMake.']'
	      ;
	    throw new exception($sMsg);
	}
	return $rc;
    }

    
    // -- RECORDS -- //
    // ++ SEARCHING ++ //

    protected function Search_forText_SQL($sFind) {
	return <<<__END__
(`Name` LIKE '%$sFind%') OR
(`Desc` LIKE '%$sFind%') OR
(`CatKey` LIKE '%$sFind%') OR
(`Search` LIKE '%$sFind%')
__END__;
    }
    public function Search_forText($sFind) {
	$sqlFind = $this->GetConnection()->SanitizeString($sFind);
	$sqlFilt = $this->Search_forText_SQL($sqlFind);
	$rs = $this->SelectRecords($sqlFilt);
	return $rs;
    }

    // -- SEARCHING -- //
    // ++ FIGURING ++ //

    private $arStats;
    /*----
      RETURNS: array of statistics for the current title
      USAGE: results should probably be cached, as they require summing across multiple Item and Stock records
      PUBLIC because Items and Topics need to call it in order to display images with full information
    */
    /* use item.info.php instead
    public function StatsArray($idTitle) {
	$sqlFilt = "(ID_Title=$idTitle) AND (isAvail OR (QtyTotal>0))";
	$rsStats = $this->ItemInfoQuery()->GetRecords_forStats('ID_ItTyp',$sqlFilt);
	$arStats = NULL;
	$sItTyps = NULL;
	if ($rsStats->RowCount() == 0) {
	    $sSummary = 'not available';
	} else {
	    while ($rsStats->NextRow()) {
		$idItTyp = $rsStats->ItemTypeID();
		if (is_null($idItTyp)) {
		    // there will be only one row where this happens
		    $sOpts = $rsStats->Value('SfxList');
		    $prcMin = $rsStats->Value('PriceBuy_min');
		    $prcMax = $rsStats->Value('PriceBuy_max');
		    // also get stock count -- for now just use calculated field
		    $qtyStock = $rsStats->Value('QtyTotal');
		} else {
		    $rsItTyp = $rsStats->ItemTypeRecord();
		    if (!is_null($sItTyps)) {
			$sItTyps .= ', ';
		    }
		    $sItTyps .= $rsItTyp->Name();
		}
	    }
	    $arStats['price-min'] = $prcMin;
	    $arStats['price-max'] = $prcMax;
	    $arStats['opt-list'] = $sOpts;
	    $arStats['stock-qty'] = $qtyStock;
	    $arStats['types'] = $sItTyps;
	    $sPrcMin = clsMoney::Format_withSymbol($prcMin);
	    $sPrcMax = clsMoney::Format_withSymbol($prcMax);
	    if ($sPrcMin == $sPrcMax) {
		$sPrc = $sPrcMin;
	    } else {
		$sPrc = $sPrcMin.' - '.$sPrcMax;
	    }
	    if ($qtyStock == 0) {
		$sStock = 'out of stock';
	    } else {
		$sStock = $qtyStock.' in stock';
	    }
	    $sSummary = "$sItTyps: $sOpts @ $sPrc ($sStock)";
	}
	$arStats['summary'] = $sSummary;
	return $arStats;
    } //*/
    /*----
      FUTURE: This might be revised to calculate a more thorough listing,
	e.g. it could show options and prices for each item type.
	For now it just compiles a list of all item types, a list of
	all options (for all types), and low and high prices (across
	all types).
      USAGE: This is currently *only* called by StatString_forTitle(),
	so the figuring and storage format can be tweaked according to
	whatever that function needs. Right now it only uses ['summary'],
	so anything else can be reworked as needed.
    *//* 2016-02-12 not doing the caching here anymore, so just call StatsArray() directly
    protected function StatsFor_Title($idTitle) {
	$arStats = $this->StatsFor_Title_cached($idTitle);
	if (is_null($arStats)) {
	    $arStats = $this->StatsArray($idTitle);
	    $this->StatsFor_Title_cached($idTitle,$arStats);	// save stats
	}
	return $arStats;
    } //*/
    public function StatString_forTitle($idTitle) {
      throw new exception('StatString_forTitle() - does anything still call this?');
	return 'StatString_forTitle() - to be written';
	//$arStats = $this->StatsFor_Title($idTitle);
	$arStats = $this->ItemInfoQuery()->StatsArray('ID_ItTyp','ID_Title='.$idTitle);
	return $arStats['summary'];
    } //*/

    // -- FIGURING -- //

}
// PURPOSE: basic Title methods common to different Title class-families
trait vtrTitle {
    use vtTableAccess_Supplier;

    // ++ FIELD VALUES ++ //
    
    /*----
      RETURNS: ID of this title's supplier
      HISTORY:
	2011-09-28 revised to get ID directly from the new ID_Supp field
	  instead of having to look up the Dept and get it from there.
	2015-11-12 Renamed Supplier_ID() to SupplierID() but added Supplier_ID()
	  as an alias so I can tidy up the code LATER.
	2018-02-10 removed Supplier_ID() and moved SupplierID() from vcrTitle to vtrTitle
    */
    public function SupplierID() {
	return $this->GetFieldValue('ID_Supp');
    }
    public function DeptID() {
	return $this->GetFieldValue('ID_Dept');
    }
    public function CatKey() {
	return $this->GetFieldValue('CatKey');
    }
    // 2018-02-10 made PUBLIC, renamed from TitleString() to NameString(), and moved from vcqrTitleInfo to vtrTitle
    public function NameString() {
	return $this->GetFieldValue('Name');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      NOTE: CatPage is NOT the same thing as CatNum separated by slashes instead of dashes.
	The Department URL fragment can be different from the Department CatKey. Maybe this
	should change in the future (maybe Departments actually should go away), but for now
	that's the rule.

      USED BY: shopping descendant class
      TODO: 
	* should probably use $rcDept->ShopURL() for department portion of URL
	* should probably be renamed ShopURL()
	* maybe should be moved into shopping class?
    */
    protected function CatPage() {
	$rcDept = $this->DepartmentRecord();
	$rcSupp = $this->SupplierRecord();
	if (is_object($rcDept)) {
	    $sDeptKey = $rcDept->PageKey_toUse();
	} else {
	    $sDeptKey = '-D!';
	}
	if (is_object($rcSupp)) {
	    $sSuppKey = $rcSupp->CatKey();
	} else {
	    $sSuppKey = '-S!';
	}
	$sTitleKey = $this->CatKey();
	$sCatPage = fcString::ConcatArray('/',array($sSuppKey,$sDeptKey,$sTitleKey));
	return $sCatPage;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ CLASSES ++ //

    protected function DepartmentsClass() {
	return 'vctDepts';
    }
    abstract protected function ImagesClass();

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function DepartmentTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->DepartmentsClass(),$id);
    }
    protected function ImageTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ImagesClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    private $rcSupp;
    public function SupplierRecord() {
	$doLoad = FALSE;
	if (is_null($this->rcSupp)) {
	    $doLoad = TRUE;
	} else if (is_object($this->rcSupp)) {
	    if ($this->SupplierID() != $this->rcSupp->GetKeyValue()) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idSupp = $this->SupplierID();
	    if (empty($idSupp)) {
		$rcSupp = NULL;
	    } else {
		$rcSupp = $this->SupplierTable($idSupp);
	    }
	    $this->rcSupp = $rcSupp;
	}
	return $this->rcSupp;
    }
    // TODO: compare load times for cached and uncached versions
    private $rcDept;
    public function DepartmentRecord() {
	$doLoad = FALSE;
	if (is_null($this->rcDept)) {
	    $doLoad = TRUE;
	} else if (is_object($this->rcDept)) {
	    if ($this->DeptID() != $this->rcDept->GetKeyValue()) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idDept = $this->DeptID();
	    if (empty($idDept)) {
		$rcDept = NULL;
	    } else {
		$rcDept = $this->DepartmentTable($idDept);
	    }
	    $this->rcDept = $rcDept;
	}
	return $this->rcDept;
    }
    public function ImageRecords_forRow($sSize) {
	$tImgs = $this->ImageTable();
	$rsImgs = $tImgs->ActiveRecords_forTitle($this->GetKeyValue(),$sSize);
	return $rsImgs;
    }
    
    // -- RECORDS -- //
}
class vcrTitle extends vcBasicRecordset {
    use vtrTitle;

// object cache
    private $oStats;

// options
    public $hideImgs;

    // ++ SETUP ++ //

    protected function InitVars() {
	$this->oStats = NULL;
	$this->rcDept = NULL;
	$this->rcSupp = NULL;
    }

    // -- SETUP -- //
    // ++ STATIC ++ //

/* 2018-02-07 Seems this is no longer in use.
    // CALLED BY: Topic exhibit page
    static private $oStat = NULL;
    static protected function Stats() {
	throw new exception('2018-02-07 Does anyone actually call this?');
	if (is_null(self::$oStat)) {
	    self::$oStat = new fcTreeStatsMgr('vctItemsStat');
	}
	return self::$oStat;
    }
*/
    // -- STATIC -- //
    // ++ FIELD VALUES ++ //

    // the next four methods should either be consolidated or clearly differentiated

    /*----
      USAGE: Used for showing titles in lists
    */
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    public function NameText() {
	return $this->GetFieldValue('Name');
    }
    public function SearchString() {
	return $this->Value('Search');
    }
    public function Description() {
	return $this->Value('Desc');
    }
    public function NotesString() {
	return $this->Value('Notes');
    }
    public function DateAdded() {
	return $this->Value('DateAdded');
    }
    public function DateChecked() {
	return $this->Value('DateChecked');
    }
    public function DateUnavailable() {
	return $this->Value('DateUnavail');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      CALLED BY: vcrShopTitle->URL_part()
      HISTORY:
	2010-10-19 added optimization to fetch answer from CatKey field if it exists.
	  This may cause future problems. Remove $iSep field and create individual functions
	  if so.
	2012-02-02 allowed bypass of Dept if it isn't set
    */
    public function CatNum() {
	if (fcArray::Exists($this->GetFieldValues(),'CatNum')) {
	    throw new exception('TODO: we need a separate class to handle Title recordsets that include a CatNum field.');
	}

	$rcDept = $this->DepartmentRecord();
	$rcSupp = $this->SupplierRecord();
	if (is_object($rcDept)) {
	    $sDeptKey = $rcDept->CatKey();
	} else {
	    $sDeptKey = '?d';
	}
	if (is_object($rcSupp)) {
	    $sSuppKey = $rcSupp->CatKey();
	} else {
	    $sSuppKey = '?s';
	}
	$sTitleKey = $this->CatKey();
	$sCatNum = fcString::ConcatArray('-',array($sSuppKey,$sDeptKey,$sTitleKey));
	return $sCatNum;
    }
    protected function Supplier_CatNum() {
	return $this->Value('Supplier_CatNum');
    }

    // -- FIELD CALCULATIONS -- //
    // ++ RECORDSET CALCULATIONS ++ //

    /*----
      USED BY: Topic pages
      HISTORY:
	2014-08-19 a note on this date asks "is this redundant now?"
	2016-02-10 Sadly, no; it's used by Topic pages. Maybe there's a better way?
    */
    /* 2018-02-08 This seems to be unused.
    public function StatThis() {
	throw new exception('2018-02-07 Does anything still call this?');
	$id = $this->GetKeyValue();
	if (!self::Stats()->IndexExists($id)) {
	    $rs = $this->ItemRecords();	// item records for this title
	    self::Stats()->StatFor($id)->SumItems($rs);	// calculate stats
	}
	return self::Stats()->StatFor($id);
    } */

    // -- STATUS -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return 'vctItems';
    }
    protected function TopicsClass() {
	return 'vctTopics';
    }
    protected function XTopicsClass() {
	return 'vctTitlesTopics';
    }
    protected function ImagesClass() {
	return 'vctImages';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function ItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ItemsClass(),$id);
    }
    protected function TopicTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TopicsClass(),$id);
    }
    protected function XTopicTable() {
	return $this->GetConnection()->MakeTableWrapper($this->XTopicsClass());
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    // PUBLIC so Supplier Catalog build process can use it
    public function ItemRecords() {
	$id = $this->GetKeyValue();
	return $this->ItemTable()->SelectRecords('ID_Title='.$id);
    }
    /*----
      PUBLIC because vcrTopic::FigurePage() calls it
    */
    public function ImageRecords($sSize) {
	throw new exception('ImageRecords() has been renamed ImageRecords_forRows().');
    }
    public function ImageRecords_forRows($sSize) {
	$tImgs = $this->ImageTable();
	$rsImgs = $tImgs->Records_forTitles_SQL($this->FetchKeyValues_asSQL(),$sSize);
	return $rsImgs;
    }
    public function ImageRecords_forRows_thumb() {
	return $this->ImageRecords_forRows(vctImages::SIZE_THUMB);
    }
    public function ImageRecords_forRow_small() {
	return $this->ImageRecords_forRow(vctImages::SIZE_SMALL);
    }
    /*----
      RETURNS: recordset of ItemType stats for this title
      HISTORY:
	2013-11-17 written as a rewrite of DataSet_ItTyps() (now deleted)
	  using live data instead of caches
    */
    public function Data_ItTyp_stats() {
	throw new exception('2017-03-16 This will need fixing.');
	$idTitle = $this->GetKeyValue();
	$sql = <<<__END__
SELECT ig.*, NameSng, NamePlr FROM (
  SELECT
    ID_ItTyp,
    COUNT(ID) AS cntLines,
    SUM(IF(isCurrent,1,0)) AS cntCurrent,
    SUM(IF(isInPrint,1,0)) AS cntInPrint,
    SUM(IF(isForSale,1,0)) AS cntForSale,
    SUM(IF(iq.qtyForSale>0,1,0)) AS cntInStock,
    SUM(iq.qtyForSale) AS qtyInStock,
    MIN(PriceSell) AS currMinPrice,
    MAX(PriceSell) AS currMaxPrice
  FROM cat_items AS i LEFT JOIN qryStk_items_remaining AS iq ON i.ID=iq.ID_Item
  WHERE isForSale AND (ID_Title=$idTitle)
  GROUP BY ID_Title, ID_ItTyp
  ) AS ig
LEFT JOIN cat_ittyps AS it ON ig.ID_ItTyp=it.ID
__END__;
	//$rs = $this->Engine()->DataSet($sql);
	$rs = $this->DataSQL();
	return $rs;
    }
    // TODO: rename to ItemRecords()
    public function Items() {
	if (is_null($this->GetKeyValue())) {
	    throw new exception('Row has no ID');
	}
	$sqlFilt = 'ID_Title='.$this->GetKeyValue();
	$tbl = $this->ItemTable();
	$rs = $tbl->SelectRecords($sqlFilt);
	return $rs;
    }
    // TODO: rename to TopicRecords()
    public function Topics() {
	$tbl = $this->XTopicTable();
	$rs = $tbl->TopicRecords_forID($this->GetKeyValue());
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //

}

