<?php
/*
  FILE: data.titles.php -- VbzCart data-handling classes: titles
  HISTORY:
    2013-02-09 created; splitting off Title-related classes from base.cat
    2013-11-14 renamed from data.titles.php to vbz-cat-title.php
  CLASSES:
    clsVbzTitles
    clsVbzTitle
    clsTitleIttyp
*/
require_once('vbz-fx.php');

class clsVbzTitles extends clsVbzTable {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_titles');
	  $this->KeyName('ID');
	  $this->ClassSng('clsVbzTitle');
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return 'clsItems';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ SEARCHING ++ //

    protected function Search_forText_SQL($iFind) {
	return '(Name LIKE "%'.$iFind.'%") OR (`Desc` LIKE "%'.$iFind.'%")';
    }
    public function Search_forText($iFind) {
	$sqlFilt = $this->Search_forText_SQL($iFind);
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }

    // -- SEARCHING -- //
}
class clsVbzTitle extends clsDataSet {
// object cache
    private $rcDept;
    private $rcSupp;

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

    static private $oStat = NULL;
    static protected function Stats() {
	if (is_null(self::$oStat)) {
	    self::$oStat = new clsStatsMgr('clsItemsStat');
	}
	return self::$oStat;
    }

    // -- STATIC -- //
    // ++ VALUE ACCESS ++ //

    /*----
      USAGE: Used for showing titles in lists
    */
    public function NameStr() {
	return $this->Value('Name');
    }
    public function NameText() {
	return $this->Value('Name');
    }
    /*----
      USAGE: Used by page display object to determine what title to show
    */
    public function TitleStr() {
	return $this->Value('Name');
    }
    /*----
      RETURNS: String to use as title for images of this Title
    */
    public function ImageTitle() {
	return $this->CatNum().' '.$this->TitleStr().': '.$this->ItemSpecString();
    }
    /*----
      RETURNS: string summarizing item information:
	* item type(s), variants
	* cost range
	* quantity in stock
    */
    protected function ItemSpecString() {
	$sStats = $this->ItemTable()->StatString_forTitle($this->KeyValue());
	return $sStats;
    }
    /*----
      RETURNS: ID of this title's supplier
      HISTORY:
	2011-09-28 revised to get ID directly from the new ID_Supp field
	  instead of having to look up the Dept and get it from there.
    */
    public function Supplier_ID() {
	$idSupp = $this->Value('ID_Supp');
	return $idSupp;
    }
    public function DeptID() {
	return $this->Value('ID_Dept');
    }
    public function CatKey() {
	return $this->Value('CatKey');
    }
    /*----
      HISTORY:
	2010-10-19 added optimization to fetch answer from CatKey field if it exists.
	  This may cause future problems. Remove $iSep field and create individual functions
	  if so.
	2012-02-02 allowed bypass of Dept if it isn't set
    */
    public function CatNum($iSep='-') {
	if (empty($this->Row['CatNum'])) {

	    $rcDept = $this->DepartmentRecord();
	    $rcSupp = $this->SupplierRecord();
	    if (is_object($rcDept)) {
		$strDeptKey = $rcDept->CatKey();
		$strOut = $rcSupp->CatKey();
		if ($strDeptKey) {
		  $strOut .= $iSep.$strDeptKey;
		}
	    } else {
		if (is_object($rcSupp)) {
		    $strOut = $rcSupp->CatKey();
		} else {
		    $strOut = '?';
		}
	    }
	    $strOut .= $iSep.$this->CatKey();
	} else {
	    $strOut = $this->CatNum();
	}
	return strtoupper($strOut);
    }

    // -- VALUE ACCESS -- //
    // ++ STATUS ACCESS ++ //

    public function StatThis() {
	$id = $this->KeyValue();
	if (!self::Stats()->IndexExists($id)) {
	    $rs = $this->ItemRecords();	// item records for this title
	    self::Stats()->StatFor($id)->SumItems($rs);	// calculate stats
	}
	return self::Stats()->StatFor($id);
    }
    public function ItemsForSale() {
	return $this->StatThis()->ItemsForSale();
    }
    public function IsForSale() {
	return ($this->ItemsForSale() > 0);
    }

    // -- STATUS ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function SuppliersClass() {
	return 'clsSuppliers';
    }
    protected function DepartmentsClass() {
	return 'clsDepts';
    }
    protected function ItemsClass() {
	return 'clsItems';
    }
    protected function ImagesClass() {
	return 'clsImages';
    }
    protected function TopicsClass() {
	return 'clsTopics';
    }
    protected function TitlesTopicsClass() {
	return 'clsTitlesTopics';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function SupplierTable($id=NULL) {
	return $this->Engine()->Make($this->SuppliersClass(),$id);
    }
    protected function DepartmentTable($id=NULL) {
	return $this->Engine()->Make($this->DepartmentsClass(),$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    protected function ImageTable($id=NULL) {
	return $this->Engine()->Make($this->ImagesClass(),$id);
    }
    protected function TopicTable($id=NULL) {
	return $this->Engine()->Make($this->TopicsClass(),$id);
    }
    protected function TitleTopicTable() {
	return $this->Engine()->Make($this->TitlesTopicsClass());
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    protected function ItemRecords() {
	$id = $this->KeyValue();
	return $this->ItemTable()->GetData('ID_Title='.$id);
    }
    public function Dept() {
	throw new exception ('Dept() is deprecated; use DepartmentRecord().');
    }
    public function DepartmentRecord() {
	$doLoad = FALSE;
	if (is_null($this->rcDept)) {
	    $doLoad = TRUE;
	} else if (is_object($this->rcDept)) {
	    if ($this->Value('ID_Dept') != $this->rcDept->KeyValue()) {
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
    public function SuppObj() {
	throw new exception('SuppObj() is deprecated; use SupplierRecord().');
    }
    public function SupplierRecord() {
	$doLoad = FALSE;
	if (is_null($this->rcSupp)) {
	    $doLoad = TRUE;
	} else if (is_object($this->rcSupp)) {
	    if ($this->Supplier_ID() != $this->rcSupp->ID) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idSupp = $this->Supplier_ID();
	    if (empty($idSupp)) {
		$rcSupp = NULL;
	    } else {
		$rcSupp = $this->SupplierTable($idSupp);
	    }
	    $this->rcSupp = $rcSupp;
	}
	return $this->rcSupp;
    }
    /*----
      PUBLIC because clsTopic::FigurePage() calls it
    */
    public function ImageRecords($sSize) {
	$tImgs = $this->ImageTable();
	$rsImgs = $tImgs->Records_forTitles_SQL($this->KeyListSQL(),$sSize);
	return $rsImgs;
    }
    public function ImageRecords_thumb() {
/*
	$tImgs = $this->ImageTable();
	$rsImgs = $tImgs->Records_forTitle($this->KeyValue(),clsImages::SIZE_THUMB);
	return $rsImgs;
	*/
	return $this->ImageRecords(clsImages::SIZE_THUMB);
    }
    public function ImageRecords_small() {
    /*
	$tImgs = $this->ImageTable();
	$rsImgs = $tImgs->Records_forTitle($this->KeyValue(),clsImages::SIZE_SMALL);
	return $rsImgs;
	*/
	return $this->ImageRecords(clsImages::SIZE_SMALL);
    }
    /*----
      RETURNS: recordset of ItemType stats for this title
      HISTORY:
	2013-11-17 written as a rewrite of DataSet_ItTyps()
	  using live data instead of caches
    */
    public function Data_ItTyp_stats() {
	$idTitle = $this->KeyValue();
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
	$rs = $this->Engine()->DataSet($sql);
	return $rs;
    }
    /*----
      RETURNS: dataset of item types for this title
      USES: _title_ittyps (cached table)
      HISTORY:
	2011-01-19 written
      DEPRECATED unless it turns out to be needed
    */
    public function DataSet_ItTyps() {
	$sql = 'SELECT * FROM _title_ittyps WHERE ID_Title='.$this->KeyValue();
	$obj = $this->Engine()->DataSet($sql,'clsTitleIttyp');
	return $obj;
    }
    public function Items() {
	if (is_null($this->KeyValue())) {
	    throw new exception('Row has no ID');
	}
	$sqlFilt = 'ID_Title='.$this->KeyValue();
	$objTbl = $this->Engine()->Items();
	$objRows = $objTbl->GetData($sqlFilt);
	return $objRows;
    }
    public function Topics() {
	$objTbl = $this->Engine()->TitleTopic_Topics();
	$objRows = $objTbl->GetTitle($this->KeyValue());
	return $objRows;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ FIGURING ++ //

    /*----
      RETURNS: Array containing summary information about this title
      HISTORY:
	2013-11-17 created as rewrite of Indicia() -- should not include
	  any HTML.
    */
    public function FigureCounts() {
	$rsItems = $this->Items();
	$qActive = 0;
	$qRetired = 0;
	while ($rsItems->NextRow()) {
	    if ($rsItems->Value('isForSale')) {
		$qActive++;
	    } else {
		$qRetired++;
	    }
	}

	$arOut = array(
	  'cnt.act' => $qActive,
	  'cnt.ret' => $qRetired,
	  );

	return $arOut;
    }
    /*----
      USAGE: This is probably used by the Title display page and
	possibly by Supplier pages, but it has not been tested yet
      RETURNS: information about item types for this title
	One element for each item type found
	One element for sums
      HISTORY:
	2013-11-17 started as rewrite of Summary_ItTyps() with no HTML
    */
    public function FigureItTyps() {
	$rs = $this->Data_ItTyp_stats();
	$qSumRow = 0;
	$qSumStk = 0;
	while ($rs->NextRow()) {
	    $qType = $rs->Value('cntForSale');

	    $qStk = $rs->Value('qtyInStock');
	    $sSng = $rs->Value('NameSng');
	    $sPlr = $rs->Value('NamePlr');
	    $sType = Pluralize($qType,$sSng,$sPlr);

	    $arRow = array(
	      'qRow' => $qType,		// number of rows for this type
	      'qStk' => $qStk,		// quantity in stock for this type
	      'sSng' => $sSng,		// singular type name
	      'sPlr' => $sPlr,		// plural type name
	      'sTyp' => $sType		// type name to use given # of rows
	      );

	    $qSumRow += $qType;
	    $qSumStk += $qStk;
	    $id = $rs->Value('ID_ItTyp');
	    $ar[$id] = $arRow;
	}
	$ar['sum'] = array(
	  'qRow' => $qSumRow,		// number of rows across all types
	  'qStk' => $qSumStk		// quantity in stock across all types
	  );
	return $ar;
    }
    /*----
      RETURNS: Array containing summaries of ItTyps in which this Title is available
	array['text.!num'] = plaintext version with no numbers (types only)
	array['text.cnt'] = plaintext version with line counts
	array['html.cnt'] = HTML version with line counts
	array['html.qty'] = HTML version with stock quantities
      HISTORY:
	2011-01-23 written
      DEPRECATED
    */
    public function Summary_ItTyps($iSep=', ') {
	$dsRows = $this->DataSet_ItTyps();
	$outTextNoQ = $outTextType = $outTextCnt = $outHTMLCnt = $outHTMLQty = NULL;
	if ($dsRows->HasRows()) {
	    $isFirst = TRUE;
	    while ($dsRows->NextRow()) {
		$cntType = $dsRows->Value('cntForSale');
		if ($cntType > 0) {
		    $qtyStk = $dsRows->Value('qtyInStock');
		    $txtSng = $dsRows->Value('ItTypNameSng');
		    $txtPlr = $dsRows->Value('ItTypNamePlr');
		    $strType = Pluralize($cntType,$txtSng,$txtPlr);
		    if ($isFirst) {
			$isFirst = FALSE;
		    } else {
			$outTextType .= $iSep;
			$outTextCnt .= $iSep;
			$outHTMLCnt .= $iSep;
			if (!is_null($outHTMLQty)) {
			    $outHTMLQty .= $iSep;
			}
		    }
		    $outTextType .= $txtSng;
		    $outTextCnt .= $cntType.' '.$strType;
		    $outHTMLCnt .= '<b>'.$cntType.'</b> '.$strType;
		    if (!empty($qtyStk)) {
			$outHTMLQty .= '<b>'.$qtyStk.'</b> '.Pluralize($qtyStk,$txtSng,$txtPlr);
		    }
		}
	    }
	}
	$arOut['text.!num'] = $outTextType;
	$arOut['text.cnt'] = $outTextCnt;
	$arOut['html.cnt'] = $outHTMLCnt;
	$arOut['html.qty'] = $outHTMLQty;
	return $arOut;
    }

    // -- FIGURING -- //
}

/*====
  PURPOSE: TITLE/ITTYP hybrid
  TABLE: _title_ittyps
*/
class clsTitleIttyp extends clsDataSet {
// object cache
  private $objIttyp;

  public function Ittyp() {
    if (is_null($this->objIttyp)) {
      $this->objIttyp = VbzClasses::ItTyps()->GetItem($this->ID_ItTyp);
    }
    return $this->objIttyp;
  }
}

class clsStatsMgr {
    private $arStat;
    private $sStatClass;

    public function __construct($sStatClass) {
	$this->arStat = array();
	$this->sStatClass = $sStatClass;
    }
    public function IndexExists($id) {
	return array_key_exists($id,$this->arStat);
    }
    public function StatFor($id) {
	if (!$this->IndexExists($id)) {
	    $obj = new $this->sStatClass;
	    $this->arStat[$id] = $obj;
	}
	return $this->arStat[$id];
    }
}

class clsItemsStat {
    private $qItemsForSale;

    public function __construct() {
	$this->qItemsForSale = NULL;
    }
    protected function SumItem(clsItem $rc) {
	$this->qItemsForSale += ($rc->IsForSale()?1:0);
    }
    public function SumItems(clsItem $rs) {
	while ($rs->NextRow()) {
	    $this->SumItem($rs);
	}
    }
    public function ItemsForSale() {
	return $this->qItemsForSale;
    }
}