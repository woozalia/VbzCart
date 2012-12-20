<?php
/*
  FILE: base.cat.php -- VbzCart catalog classes
  HISTORY:
    2012-05-08 split off from store.php
    2012-05-13 moved clsCatPages here from pages.php
*/
clsLibMgr::Add('vbz.cat.page',	KFP_LIB_VBZ.'/page-cat.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsSuppliers_StoreUI','vbz.cat.page');

class clsCatPages extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_pages');	// cache
	  //$this->Name('qryCat_pages');	// live data
	  $this->KeyName('AB');
	  $this->ClassSng('clsCatPage');
    }
    public function GetItem_byKey($iKey) {
	CallEnter($this,__LINE__,__CLASS__.'.GetItem_byKey('.$iKey.')');
	$strKey = trim($iKey,'/');
	$strKey = str_replace('-','/',$strKey);
	$sqlCatKey = $this->objDB->SafeParam($strKey);
// This function is named wrong, and needs to be rewritten anyway
//	$this->Touch('clsCatPages.GetItem_byKey('.$iKey.')');
	$objItem = $this->GetData('Path="'.$sqlCatKey.'"');
    //    $objRec = $this->objDB->Query($sql);
	assert('is_object($objItem)');
	if ($objItem->NextRow()) {
	    DumpValue('objItem NumRows',$objItem->hasRows());
	    CallExit('clsCatPages.GetItem_byKey('.$iKey.') -> Page '.$objItem->AB);
	} else {
	    CallExit('clsCatPages.GetItem_byKey('.$iKey.') -> no data');
	}
	return $objItem;
    }
}
// just for paral;ellism, at this point
class clsCatPage extends clsDataSet {
    /*----
      RETURNS: an object of the appropriate type, as determined by what the current page information record indicates
    */
    public function ItemObj() {
	$id = $this->Row['ID_Row'];
	$objData = $this->Engine();
	switch ($this->Type) {
	  case 'S':
	    $rs = $objData->Suppliers();
	    break;
	  case 'D':
	    $rs = $objData->Depts();
	    break;
	  case 'T':
	    $rs = $objData->Titles();
	    break;
	  case 'I':
	    $rs = $objData->Images();
	    break;
	  default:
	    $rs = NULL;
	}
	if (is_null($rs)) {
	    $rc = NULL;
	} else {
	    $rc = $rs->GetItem($id);
	}
	return $rc;
    }
}

class clsSuppliers extends clsVbzTable {
// ==== STATIC SECTION

// ==== DYNAMIC SECTION
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_supp');
	  $this->KeyName('ID');
	  $this->ClassSng('clsSupplier');
	  $this->ActionKey('supp');
    }
    public function GetItem_byKey($iKey) {
	CallEnter($this,__LINE__,__CLASS__.'.GetItem_byKey('.$iKey.')');
	$sqlCatKey = $this->objDB->SafeParam($iKey);
	$objItem = $this->GetData('CatKey="'.$sqlCatKey.'"');
	CallExit(__CLASS__.'.GetItem_byKey('.$iKey.') -> new supplier');
	return $objItem;
    }
    /*----
      HISTORY
	2010-11-12 disabled automatic cache update
    */
    protected function DataSet_forStore($iClass=NULL) {
	//$objCache = $this->objDB->CacheMgr();
	//$objCache->Update_byName('_supplier_ittyps','clsSuppliers.DoHomePage()');
	$sql = 'SELECT * FROM _supplier_ittyps ORDER BY Name, ItemCount DESC';
	$objRows = $this->objDB->DataSet($sql,$iClass);

	return $objRows;
    }
}

class clsSupplier extends clsDataSet {
    /*----
      ACTION: Finds the Item for this Supplier with the given supplier CatNum
      RETURNS: object of type requested by user; defaults to clsItem. NULL if not found.
      DEPRECATED -- use GetItem_bySCatNum()
    */
    public function GetItem_bySuppCatNum($iCatNum,$iClass=NULL) {
	return $this->GetItem_bySCatNum($iCatNum);
    }
    /*----
      ACTION: Checks the given catalog number to see if it corresponds to a given item for the current supplier
      INPUT: supplier catalog number
      OUTPUT: item object (if found) or NULL (if not found)
      HISTORY:
	2011-01-09 Moved here from VbzAdminSupplier; replaces GetItem_bySuppCatNum()
    */
    public function GetItem_bySCatNum($iSCat) {
	$objTblItems = $this->objDB->Items();

	$sqlFind = '(ID_Supp='.$this->ID.') AND (Supp_CatNum="'.$iSCat.'")';
	$objItem = $objTblItems->GetData($sqlFind);
	if ($objItem->HasRows()) {
	    $objItem->NextRow();
	    return $objItem;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Finds the Title for this Supplier with the given CatKey
      RETURNS: object of type requested by user; defaults to clsVbzTitle. NULL if not found.
      HISTORY:
	2010-11-07 Created for Title editing page -- need to check for duplicate CatKey before saving.
    */
    public function GetTitle_byCatKey($iCatKey,$iClass='clsVbzTitle') {
	$sqlCatKey = $this->objDB->SafeParam($iCatKey);
	$sqlFilt = '(ID_Supplier='.$this->ID.') AND (CatKey="'.$sqlCatKey.'")';
	$objTitle = $this->objDB->Titles_Cat()->GetData($sqlFilt,$iClass);
	if ($objTitle->HasRows()) {
	    $objTitle->NextRow();
	    return $objTitle;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Searches for Titles whose CatKeys include the given string
      PURPOSE: used during renaming of supplier-recycled catalog numbers, so we can see if that number
	has been recycled before and avoid having to repeatedly try new names
      HISTORY:
	2010-11-08 Created for title editing page
    */
    public function GetTitles_byCatKey($iCatKey,$iClass='clsVbzTitle') {
	$sqlCatKey = $this->objDB->SafeParam($iCatKey);
	$sqlFilt = '(ID_Supplier='.$this->ID.') AND (CatKey LIKE "%'.$sqlCatKey.'%")';
	$objTitle = $this->objDB->Titles_Cat()->GetData($sqlFilt,$iClass);
	if ($objTitle->HasRows()) {
	    $objTitle->NextRow();
	    return $objTitle;
	} else {
	    return NULL;
	}
    }
    protected function DeptsData_forCount($iClass='clsDept') {
	$objTbl = $this->objDB->Depts();
	$objRows = $objTbl->GetData('isActive AND (ID_Supplier='.$this->ID.')',$iClass,'Sort');
	return $objRows;
    }
    /*----
      ACTION: builds an array of item type data for the supplier, broken down by department.
	Caches the results in memory.
      USED BY: $this->DoPiece_ItTyp_Summary(), $this->DoPiece_Dept_ItTyps()
      RETURNS: array of data for the current supplier
	array[rows] = source dataset -- each row is an ItTyp within a Department
	array[depts][ID_Dept][ID_ItTyp] = count of items for sale by department and item type
	array[supp][ID_ItTyp] = count of items for sale by item type
      HISTORY:
	2011-02-02 switched data source from qryItTypsDepts_ItTyps to _dept_ittyps
	  Page was not displaying at all. Some additional changes were necessary.
    */
    protected function DeptsData_forStore() {
	if (is_null($this->arDeptsData)) {
	    //$objRows = $this->objDB->DataSet('SELECT * FROM qryItTypsDepts_ItTyps WHERE ID_Supplier='.$this->ID);
	    $objRows = $this->objDB->DataSet('SELECT * FROM _dept_ittyps WHERE ID_Supp='.$this->ID);
	    while ($objRows->NextRow()) {
		$idItTyp = $objRows->ID_ItTyp;
		$intCntForSale = $objRows->cntForSale;

		if (!isset($arObjs[$idItTyp])) {
		    $objItTyp = $this->Engine()->ItTyps()->SpawnItem();
		    $arObjs[$idItTyp] = $objItTyp;

		    $objItTyp->Row['NameSng'] = $objRows->Value('ItTypNameSng');
		    $objItTyp->Row['NamePlr'] = $objRows->Value('ItTypNamePlr');
		    $objItTyp->Row['cntForSale'] = 0;	// initialize the count
		}
    // accumulate the list of everything this supplier has:
		$idSupp = $objRows->ID_Supplier;
		$objItTyp->Row['cntForSale'] += $intCntForSale;
    // accumulate the department listing:
		$idDept = $objRows->Value('ID_Dept');
		$arDeptCntForSale[$idDept][$idItTyp] = $intCntForSale;
	    }
	    $arOut['rows'] = $objRows;
	    $arOut['depts'] = $arDeptCntForSale;
	    $arOut['supp'] = $arObjs;
	    $this->arDeptsData = $arOut;
	}
	return $this->arDeptsData;
    }
    /*----
      ACTION: Generates the item-type-count summary for the Supplier's index page
    */
    public function DoPiece_ItTyp_Summary() {
	$arData = $this->DeptsData_forStore();
	$arObjs = $arData['supp'];

	$outRow = '';
	foreach ($arObjs as $id=>$obj) {
	    $objTyp = $obj;
	    $cnt = $objTyp->Value('cntForSale');
	    if ($cnt > 0) {
		$strType = $objTyp->Name($cnt);
		if ($outRow != '') {
		    $outRow .= ', ';
		}
		$outRow .= '<b>'.$cnt.'</b> '.$strType;
	    }
	}
	$out = '<span class=catalog-summary>'.$outRow.'</span>';
	return $out;
    }
    public function DoPage() {
	$out = '';
	assert('$this->ID');

    // first, check how many departments supplier has:
	//$objDeptTbl = $this->objDB->Depts();
	//$objDepts = $objDeptTbl->GetData('isActive AND (ID_Supplier='.$this->ID.')','clsDept','Sort');
	$objDepts = $this->DeptsData_forCount();
	$intRows = $objDepts->RowCount();

	if ($intRows == 1) {
    // if there's only one department, display that instead of a department listing
	    $objDepts->NextRow();	// get the first/only dept
	    $out = $objDepts->DoPage();
	} else {
	    $out .= $this->DeptsPage_forStore();
	}

	return $out;
    }

    public function ShopLink($iText=NULL) {
	if (is_null($iText)) {
	    $strText = $this->Name;
	} else {
	    $strText = $iText;
	}
	$out = '<a href="'.$this->URL().'">'.$strText.'</a>';
	return $out;
    }
    public function Link() { return $this->ShopLink(); }
    public function URL() {
	return KWP_CAT_REL.strtolower($this->CatKey).'/';
    }
}


class clsDepts extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_depts');
	  $this->KeyName('ID');
	  $this->ClassSng('clsDept');
	  $this->ActionKey('dept');
    }
    protected function _newItem() {
	CallStep('clsDepts._newItem()');
	return new clsDept($this);
    }
}
class clsDept extends clsDataSet {
// object cache
    private $objSupp;

    public function SuppObj() {
	if (is_object($this->objSupp)) {
	    return $this->objSupp;
	} else {
	    $idSupp = $this->ID_Supplier;
	    if ($idSupp) {
		$this->objSupp = $this->objDB->Suppliers()->GetItem($idSupp);
		return $this->objSupp;
	    } else {
		return NULL;
	    }
	}
    }
    // DEPRECATED -- use SuppObj()
    public function Supplier() {
	return $this->SuppObj();
    }
    public function PageKey() {
	if ($this->PageKey) {
	    return $this->PageKey;
	} else {
	    return $this->CatKey;
	}
    }
    /*----
      PURPOSE: loads data needed to display catalog views for this department
      HISTORY
	2010-11-12 disabled automatic cache update
	2010-11-16 changed sorting field from cntInPrint to cntForSale
	2011-02-02 using _dept_ittyps now instead of qryItTypsDepts_ItTyps
	  Also added "AND (cntForSale)" to WHERE clause -- not listing titles with nothing to sell
    */
    protected function Data_forStore() {	// was GetDeptData()
	//$objCache = $this->objDB->CacheMgr();
	//$objCache->Update_byName('_dept_ittyps','clsDept.DoListing() for ID='.$this->ID);
	//$sql = 'SELECT * FROM qryItTypsDepts_ItTyps WHERE (ID_Dept='.$this->ID.') ORDER BY cntForSale DESC';
	$sql = 'SELECT * FROM _dept_ittyps WHERE (ID_Dept='.$this->ID.') AND (cntForSale) ORDER BY cntForSale DESC';
	$objItTyps = $this->objDB->DataSet($sql,'clsItTyp');
	return $objItTyps;
    }
    /*-----
      PURPOSE: Print this department's information as part of department list
      HISTORY:
	2010-11-16 $cntAvail is now cntForSale, not cntInPrint+qtyInStock
    */
    public function DoListing() {
	assert('$this->ID');
	$objItTyps = $this->Data_forStore();
	$isFirst = true;
	$out = '';
	while ($objItTyps->NextRow()) {
	    if ($isFirst) {
		$isFirst = false;
	    } else {
		$out .= ', ';
	    }
	    $cntInPrint = $objItTyps->cntInPrint;
	    $qtyInStock = $objItTyps->qtyForSale;
	    //$cntAvail = $cntInPrint + $qtyInStock;
	    $cntForSale = $objItTyps->cntForSale;
	    if ($cntAvail == 1) {
		$strName = $objItTyps->ItTyp_Sng;
	    } else {
		$strName = $objItTyps->ItTyp_Plr;
	    }
	    $out .= ' <b>'.$cntAvail.'</b> '.$strName;
	}
	return $out;
    }
    /*----
      PURPOSE: Print page for current department
      ACTION:
	* Iterates through item types available for this department.
	* For each item type, prints header and then a list of titles.
      HISTORY:
	2010-11-?? Started using cached table _title_ittyps instead of qryTitles_ItTyps_Titles
	2010-11-16 $cntAvail is now cntForSale, not cntInPrint+qtyInStock
	2011-02-02 $qtyInStock now set to Row['qtyInStock'], not Row['qtyForSale'] which didn't make sense anyway
    */
    public function DoPage() {
	$out = '';
	$idDept = $this->ID;
	if (empty($idDept)) {
	    throw new exception('Department object has no ID');
	}
	$objSection = new clsPageOutput();
	// calculate the list of item types available in this department
	$objItTyps = $this->Data_forStore();
	$objTitles = new clsVbzTitle($this->objDB);
	$objNoImgSect = new clsPageOutput();
	$cntSections = 0;
	while ($objItTyps->NextRow()) {
	    $cntInPrint = $objItTyps->Row['cntInPrint'];
	    $qtyInStock = $objItTyps->Row['qtyInStock'];
	    $cntAvail = $objItTyps->Row['cntForSale'];
	    if ($cntAvail) {
		$cntSections++;
		$idItTyp = $objItTyps->Row['ID_ItTyp'];
	        $sql = 'SELECT *, ID_Title AS ID, TitleName AS Name, cntInStock FROM _title_ittyps WHERE ((cntForSale) AND (ID_ItTyp='.$idItTyp.') AND (ID_Dept='.$idDept.'));';
		//$sql = 'SELECT t.ID_Title AS ID, t.* FROM qryTitles_ItTyps_Titles AS t WHERE (ID_ItTyp='.$idItTyp.') AND (ID_Dept='.$idDept.');';
		$objTitles->Query($sql);
	
		$arTitles = NULL;
		if ($objTitles->hasRows()) {
		    while ($objTitles->NextRow()) {
			// add title to display list
			$arTitles[] = $objTitles->Values();	// save it in a list
		    }
		    assert('is_array($arTitles)');

  // We've generated the list of titles for this section; now display the section header and titles:
		    $out .= $this->objDB->ShowTitles($objItTyps->Row['ItTypNamePlr'].':',$arTitles,$objNoImgSect);
		} else {
		    echo 'ERROR: No titles found! SQL='.$sql;
		}
		$objSection->Clear();
	    } else {
		$out .= '<span class=main>Small coding error: this line should never happen.</span>'; // TO DO: log an error
	    }
	}
	if (!$cntSections) {
	    $out .= '<span class=main>This department appears to have been emptied of all leftover stock. (Eventually there will be a way to see what items used to be here.)</span>';
	}
	if ($objNoImgSect->inTbl) {
	    $objNoImgSect->EndTable();
	    $objSection->AddText($objNoImgSect->out);
	    $objSection->EndTable();
	    $out .= $objSection->out;
	}
	return $out;
    }
    public function URL_Rel() {
	$strURL = $this->Supplier()->URL();
	$strKey = $this->PageKey();
	if ($strKey) {
	    $strURL .= strtolower($strKey).'/';
	}
	return $strURL;
    }
    public function URL_Abs() {
	return KWP_ROOT.$this->URL_Rel();
    }
    public function LinkName() {
	$strURL = $this->URL_Rel();
	return '<a href="'.$strURL.'">'.$this->Name.'</a>';
    }
    /*-----
      RETURNS: The string which, when prepended to a Title's CatKey, would form the Title's catalog number
    */
    public function CatPfx() {
	$strFull = strtoupper($this->Supplier()->CatKey);
	if ($this->AffectsCatNum()) {
	    $strFull .= '-'.strtoupper($this->CatKey);
	}
	return $strFull.'-';
    }
    /*-----
      RETURNS: TRUE if this department affects the catalog number (i.e. if CatKey is non-blank)
    */
    public function AffectsCatNum() {
	return ($this->CatKey != '');
    }
}

class clsVbzTitles extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_titles');
	  $this->KeyName('ID');
	  $this->ClassSng('clsVbzTitle');
    }
    public function Search_forText_SQL($iFind) {
	return '(Name LIKE "%'.$iFind.'%") OR (`Desc` LIKE "%'.$iFind.'%")';
    }
    public function Search_forText($iFind) {
	$sqlFilt = $this->Search_forText_SQL($iFind);
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }
/*
 ACTION: Finds a Title from a CatNum and returns an object for it
 TO DO:
    Rename to Get_byCatNum()
    Stop using v_titles
*/
/* 2010-11-07 Is anything actually using this?
  public function GetItem_byCatNum($iCatNum) {
    global $objDataMgr;

    CallEnter($this,__LINE__,__CLASS__.'.GetItem_byCatNum('.$iCatNum.')');
    assert('is_object($this->objDB)');
    $sqlCatNum = strtoupper(str_replace('.','-',$iCatNum));
    $sqlCatNum = $this->objDB->SafeParam($sqlCatNum);
    $sql = 'SELECT * FROM v_titles WHERE CatNum="'.$sqlCatNum.'"';
//    $objTitle = new clsVbzTitleExt($this);
    $objTitle = new clsVbzTitle($this);
    // make sure _titles (part of v_titles) is up-to-date
    //$objDataMgr->Update_byName('_titles','GetItem_byCatNum('.$iCatNum.')');
    $objDataMgr->Update_byName('_depts','GetItem_byCatNum('.$iCatNum.')');
    // get data from v_titles
    $objTitle->Query($sql);
    $idTitle = $objTitle->ID;
    assert('is_resource($objTitle->Res)');
    if ($objTitle->RowCount()) {
      assert('$idTitle');
      $sql = 'SELECT * FROM titles WHERE ID='.$idTitle;
      $objTitle->dontLoadBasic = true;
      $objTitle->Query($sql);
      CallExit('clsVbzTitles.GetItem_byCatNum() -> ok');
      return $objTitle;
    } else {
      CallExit('clsVbzTitles.GetItem_byCatNum() -> NULL');
      return NULL;
    }
  }
*/
}
class clsVbzTitle extends clsDataSet {
// object cache
    private $objDept;
    private $objSupp;
// options
    public $hideImgs;

    public function Dept() {
	$doLoad = FALSE;
	if (empty($this->objDept)) {
	    $doLoad = TRUE;
	} else if (is_object($this->objDept)) {
	    if ($this->ID_Dept != $this->objDept->ID) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idDept = $this->ID_Dept;
	    if (empty($idDept)) {
		$objDept = NULL;
	    } else {
		$objDept = $this->objDB->Depts()->GetItem($idDept);
		assert('is_object($objDept)');
	    }
	    $this->objDept = $objDept;
	}
	return $this->objDept;
    }
    /*----
      RETURNS: ID of this title's supplier
      HISTORY:
	2011-09-28 revised to get ID directly from the new ID_Supp field
	  instead of having to look up the Dept and get it from there.
    */
    public function Supplier_ID() {
/*
	$objDept = $this->Dept();
	$idSupp = $objDept->ID_Supplier;
*/
	$idSupp = $this->Value('ID_Supp');
	return $idSupp;
    }
    // DEPRECATED -- use SuppObj()
    public function Supplier() {
	return $this->SuppObj();
    }
    public function SuppObj() {
	$doLoad = FALSE;
	if (empty($this->objSupp)) {
	    $doLoad = TRUE;
	} else if (is_object($this->objSupp)) {
	    if ($this->ID_Supplier != $this->objSupp->ID) {
		$doLoad = TRUE;
	    }
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $idSupp = $this->Supplier_ID();
	    if (empty($idSupp)) {
		$objSupp = NULL;
	    } else {
		$objSupp = $this->objDB->Suppliers()->GetItem($idSupp);
		assert('is_object($objSupp)');
	    }
	    $this->objSupp = $objSupp;
	}
	return $this->objSupp;
    }
    public function Items() {
	$sqlFilt = 'ID_Title='.$this->ID;
	$objTbl = $this->objDB->Items();
	$objRows = $objTbl->GetData($sqlFilt);
	return $objRows;
    }
    public function Topics() {
	$objTbl = $this->Engine()->TitleTopic_Topics();
	$objRows = $objTbl->GetTitle($this->KeyValue());
	return $objRows;
    }
    /*----
      RETURNS: Array containing summary information about this title
    */
    public function Indicia(array $iarAttr=NULL) {
	$objItems = $this->Items();
	$intActive = 0;
	$intRetired = 0;
	if ($objItems->HasRows()) {
	    while ($objItems->NextRow()) {
		if ($objItems->isForSale) {
		    $intActive++;
		} else {
		    $intRetired++;
		}
	    }
	}
	// "dark-bg" brings up link colors for a dark background
	$arLink = array('class'=>'dark-bg');
	// merge in any overrides or additions from iarAttr:
	if (is_array($iarAttr)) {
	    $arLink = array_merge($arLink,$iarAttr);
	}
	$htLink = $this->Link($arLink);
	$txtCatNum = $this->CatNum();
	$txtName = $this->Name;

	$arOut['cnt.active'] = $intActive;
	$arOut['cnt.retired'] = $intRetired;
	$arOut['txt.cat.num'] = $txtCatNum;
	$arOut['ht.link.open'] = $htLink;
	$arOut['ht.cat.line'] = $htLink.$txtCatNum.'</a> '.$txtName;

	return $arOut;
    }
    /*----
      RETURNS: Array containing summaries of ItTyps in which this Title is available
	array['text.!num'] = plaintext version with no numbers (types only)
	array['text.cnt'] = plaintext version with line counts
	array['html.cnt'] = HTML version with line counts
	array['html.qty'] = HTML version with stock quantities
      HISTORY:
	2011-01-23 written
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
// LATER: change name to DataSet_Images() to clarify that this returns a dataset, not a text list or array
    public function ListImages($iSize) {
	$sqlFilt = '(ID_Title='.$this->ID.') AND (Ab_Size="'.$iSize.'") AND isActive';
	$objImgs = $this->objDB->Images()->GetData($sqlFilt,'clsImage','AttrSort');
	return $objImgs;
    }
    /*----
      RETURNS: dataset of item types for this title
      USES: _title_ittyps (cached table)
      HISTORY:
	2011-01-19 written
    */
    public function DataSet_ItTyps() {
	$sql = 'SELECT * FROM _title_ittyps WHERE ID_Title='.$this->KeyValue();
	$obj = $this->Engine()->DataSet($sql,'clsTitleIttyp');
	return $obj;
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

	  $objDept = $this->Dept();
	  $objSupp = $this->SuppObj();
	  if (is_object($objDept)) {
	      $strDeptKey = $objDept->CatKey;
	      $strOut = $objSupp->CatKey;
	      if ($strDeptKey) {
		$strOut .= $iSep.$strDeptKey;
	      }
	  } else {
	      if (is_object($objSupp)) {
		  $strOut = $objSupp->CatKey;
	      } else {
		  $strOut = '?';
	      }
	  }
	  $strOut .= $iSep.$this->CatKey;
      } else {
	  $strOut = $this->CatNum;
      }
      return strtoupper($strOut);
    }
  public function URL_part() {
    return strtolower($this->CatNum('/'));
  }
  public function URL($iBase=KWP_CAT_REL) {
    return $iBase.$this->URL_part();
  }
    public function Link(array $iarAttr=NULL) {
	$strURL = $this->URL();
	$htAttr = ArrayToAttrs($iarAttr);
	return '<a'.$htAttr.' href="'.$strURL.'">';
    }
  public function LinkAbs() {
    $strURL = $this->URL(KWP_CAT);
    return '<a href="'.$strURL.'">';
  }
  public function LinkName() {
    return $this->Link().$this->Name.'</a>';
  }
/* 2010-11-06 if this is needed, use a method in SpecialVbzAdmin
  public function LinkName_wt() {
// TO DO: make this more configurable
    $out = '[[vbznet:cat/'.$this->URL_part().'|'.$this->Name.']]';
    return $out;
  }
*/
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
/* -------------------- *\
    ITEM classes
\* -------------------- */
class clsItems extends clsVbzTable {

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_items');
	  $this->KeyName('ID');
	  $this->ClassSng('clsItem');
    }
    /*----
      ACTION: Finds the Item with the given CatNum, and returns a clsItem object
    */
    public function Get_byCatNum($iCatNum) {
	$sqlCatNum = $this->objDB->SafeParam(strtoupper($iCatNum));
	$objItem = $this->GetData('CatNum="'.$sqlCatNum.'"');
	if ($objItem->HasRows()) {
	    $objItem->NextRow();
	    return $objItem;
	} else {
	    return NULL;
	}
    }
    public function Search_byCatNum($iCatNum) {
	$sqlCatNum = $this->objDB->SafeParam(strtoupper($iCatNum));
	$objItem = $this->GetData('CatNum LIKE "%'.$sqlCatNum.'%"');
	if ($objItem->HasRows()) {
	    return $objItem;
	} else {
	    return NULL;
	}
    }
    /*----
      RETURNS: Table header for list of available items on catalog Title pages
      HISTORY:
	2011-01-24 created/corrected from code in Title page-display function
    */
    public function Render_TableHdr() {
	return '<tr>'
	  .'<th align=left>Option</th>'
	  .'<th>Status</th>'
	  .'<th align=center><i>List<br>Price</th>'
	  .'<th align=center class=title-price>Our<br>Price</th>'
	  .'<th align=center class=orderQty>Order<br>Qty.</th>'
	  .'</tr>';
    }
}
/* ===============
 CLASS: clsItem
 NOTES:
  * "in stock" always refers to stock for sale, not stock which has already been purchased
  * 2009-12-03: The above note does not clarify anything.
  * Four methods were moved here from clsShopCartLine in shop.php: ItemSpecs(), ItemDesc(), ItemDesc_ht(), ItemDesc_wt()
    They are used for displaying a full description of an item, in both shop.php and SpecialVbzAdmin
*/
class clsItem extends clsDataSet {
// object cache
    private $objTitle;
    private $objItTyp;
    private $objItOpt;

    public function CatNum() {
	return $this->Value('CatNum');
    }
    public function DescSpecs(array $iSpecs=NULL) {
	if (is_null($iSpecs)) {
	    $this->objTitle	= $this->Title();
	    $this->objItTyp	= $this->ItTyp();
	    $this->objItOpt	= $this->ItOpt();

	    $out['tname']	= $this->objTitle->Name;
	    $out['ittyp']	= $this->objItTyp->Name($this->Qty);
	    $out['itopt']	= $this->objItOpt->Descr;
	    return $out;
	} else {
	    return $iSpecs;
	}
    }
    public function DescLong(array $iSpecs=NULL) {	// plaintext
	if (is_null($this->Value('Descr'))) {
	    $sp = $this->DescSpecs($iSpecs);

	    $strItOpt = $sp['itopt'];

	    $out = '"'.$sp['tname'].'" ('.$sp['ittyp'];
	    if (!is_null($strItOpt)) {
		$out .= ' - '.$strItOpt;
	    }
	    $out .= ')';
	} else {
	    $out = $this->Value('Descr');
	}

	return $out;
    }
    public function DescLong_ht(array $iSpecs=NULL) {	// as HTML
	$sp = $this->DescSpecs($iSpecs);

	$htTitleName = '<i>'.$this->Title()->LinkName().'</i>';
	$strItOpt = $sp['itopt'];

	$out = $htTitleName.' ('.$sp['ittyp'];
	if (!is_null($strItOpt)) {
	    $out .= ' - '.$strItOpt;
	}
	$out .= ')';

	return $out;
    }
    /*-----
      ASSUMES:
	  This item is ForSale, so isForSale = true and (qtyForSale>0 || isInPrint) = true
      HISTORY:
	  2011-01-24 Renamed Print_TableRow() -> Render_TableRow; corrected to match header
    */
    public function Render_TableRow() {
	$arStat = $this->AvailStatus();
	$strCls = $arStat['cls'];

	$out = '<tr class='.$strCls.'><!-- ID='.$this->ID.' -->';
	$out .= '<td>&emsp;'.$this->Value('ItOpt_Descr').'</td>';
	$out .= '<td>'.$arStat['html'].'</td>';
	$out .= '<td align=right><i>'.DataCurr($this->Value('PriceList')).'</i></td>';
	$out .= '<td align=right>'.DataCurr($this->Value('PriceSell')).'</td>';
	$out .= '<td>'.'<input size=3 name="qty-'.$this->Value('CatNum').'"></td>';
	$out .= '</tr>';
	return $out;
    }
    /*----
      ACTION: Returns an array with human-friendly text about the item's availability status
      RETURNS:
	array['html']: status text, in HTML format
	array['cls']: class to use for displaying item row in a table
      USED BY: Render_TableRow()
      NOTE: This probably does not actually need to be a separate method; I thought I could reuse it to generate
	status for titles, but that doesn't make sense. Maybe it will be easier to adapt, though, as a separate method.
      HISTORY:
	2010-11-16 Modified truth table for in-print status so that if isInPrint=FALSE, then status always shows
	  "out of print" even if isCurrent=FALSE. What happens when a supplier has been discontinued? Maybe we need to
	  check that separately. Wait for an example to come up, for easier debugging.
	2011-01-24 Corrected to use cat_items fields
    */
    private function AvailStatus() {
//echo 'SQL=['.$this->sqlMake.']';
//echo '<pre>'.print_r($this->Row,TRUE).'</pre>';
      $qtyInStock = $this->Value('QtyIn_Stk');
	if ($qtyInStock) {
	    $strCls = 'inStock';
	    $strStk = $qtyInStock.' in stock';
	} else {
	    $strCls = 'noStock';
	    $strStk = 'none in stock';
	}
	$isInPrint = $this->Value('isInPrint');
	if ($isInPrint) {
	    if ($this->Value('isCurrent')) {
		    if ($qtyInStock) {
			$txt = $strStk.'; more available';
		    } else {
			$txt = '<a title="explanation..." href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'">available, not in stock</a>';
		    }
	    } else {
		if ($qtyInStock) {
		    $txt = $strStk.'; in-print status uncertain';
		} else {
		    $txt = $strStk.'; availability uncertain';
		}
	    }
	} else {
	    if (is_null($isInPrint)) {
		$txt = '<b>'.$strStk.'</b> - <i>possibly out of print</i>';
	    } else {
		$txt = '<b>'.$strStk.'</b> - <i>out of print!</i>';
	    }
	}
	$arOut['html'] = $txt;
	$arOut['cls'] = $strCls;
	return $arOut;
    }

    // DEPRECATED - use TitleObj()
    public function Title() {
	return $this->TitleObj();
    }
    public function TitleObj() {
	$doLoad = TRUE;
	if (is_object($this->objTitle)) {
	    if ($this->objTitle->ID == $this->ID_Title) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objTitle = $this->objDB->Titles()->GetItem($this->ID_Title);
	}
	return $this->objTitle;
    }
  public function Supplier() {
      return $this->TitleObj()->Supplier();
  }
  public function ItTyp() {
      $doLoad = TRUE;
      if (is_object($this->objItTyp)) {
	  if ($this->objItTyp->ID == $this->ID_ItTyp) {
	      $doLoad = FALSE;
	  }
      }
      if ($doLoad) {
	  $this->objItTyp = $this->objDB->ItTyps()->GetItem($this->ID_ItTyp);
      }
      return $this->objItTyp;
  }
  public function ItOpt() {
    $doLoad = TRUE;
    if (is_object($this->objItOpt)) {
      if ($this->objItOpt->ID == $this->ID_ItOpt) {
        $doLoad = FALSE;
      }
    }
    if ($doLoad) {
      $this->objItOpt = $this->objDB->ItOpts()->GetItem($this->ID_ItOpt);
    }
    return $this->objItOpt;
  }
    // DEPRECATED - use ShipCostObj()
    public function ShCost() {
	return $this->ShipCostObj();
    }
    /*----
      HISTORY:
	2010-10-19 created from contents of ShCost()
    */
    public function ShipCostObj() {
	$doLoad = FALSE;
	if (empty($this->objShCost)) {
	    $doLoad = TRUE;
	} elseif ($this->objShCost->ID != $this->ID_ShipCost) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->objShCost = $this->objDB->ShipCosts()->GetItem($this->ID_ShipCost);
	}
	return $this->objShCost;
    }
    /*----
      RETURNS: The item's per-item shipping price for the given shipping zone
      FUTURE: Rename to ShPerItm_forZone()
    */
    public function ShipPriceItem($iZone) {
	global $listItmFactors;

	$fltZoneFactor = $listItmFactors[$iZone];
	$objSh = $this->ShipCostObj();
	return $objSh->PerItem * $fltZoneFactor;
    }
    /*----
      RETURNS: The item's per-package shipping price for the given shipping zone
      FUTURE: Rename to ShPerPkg_forZone()
    */
    public function ShipPricePkg($iZone) {
	global $listPkgFactors;

	$fltZoneFactor = $listPkgFactors[$iZone];
	return $this->ShipCostObj()->PerPkg * $fltZoneFactor;
    }
    /*----
      RETURNS: The item's per-item shipping price, with no zone calculations
      FUTURE: need to handle shipping zone more gracefully and rigorously
	This function is currently only used in the admin area, so does not need
	to be infallible.
    */
    public function ShPerItm() {
	return $this->ShipCostObj()->Value('PerItem');
    }
    /*----
      RETURNS: The item's per-package shipping price, with no zone calculations
      FUTURE: need to handle shipping zone more gracefully and rigorously
	This function is currently only used in the admin area, so does not need
	to be infallible.
    */
    public function ShPerPkg() {
	return $this->ShipCostObj()->Value('PerPkg');
    }
}
/*====
  PURPOSE: clsItems with additional catalog information
*/
class clsItems_info_Cat extends clsItems {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryCat_Items');
	  //$this->ClassSng('clsItem_info_Cat');
    }
}
/* -------------------- *\
    ITEM TYPE classes
\* -------------------- */
class clsItTyps extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_ittyps');
	  $this->KeyName('ID');
	  $this->ClassSng('clsItTyp');
    }
    // BOILERPLATE - cache
    protected $objCache;
    protected function Cache() {
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
/*
    public function GetData_Cached($iWhere=NULL,$iClass=NULL,$iSort=NULL) {
	return $this->Cache()->GetData($iWhere,$iClass,$iSort);
    }
*/
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
      HISTORY:
	2010-11-21 Adapted from clsFolders.
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$arRows = $this->Cache()->GetData_array('isType',NULL,'Sort, NameSng');
	$out = $this->DropDown_for_array($arRows,$strName,$iDefault,$iChoose);
	return $out;
    }
    /*----
      ACTION: same as clsItTyp::DropDown_for_rows, but takes an array
      HISTORY:
	2011-02-11 wrote
    */
    public function DropDown_for_array(array $iRows,$iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->Name();
	}
	return DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
/*====
  CLASS: Item Type (singular)
*/
class clsItTyp extends clsDataSet_bare {
    /*----
      HISTORY:
	2011-02-02 removed the IsNew() check because sometimes we want to use this
	  on data which has not been associated with an ID
    */
    public function Name($iCount=NULL) {
	if (is_null($iCount)) {
	    if (isset($this->Row['cntInPrint'])) {
		$iCount = $this->Row['cntInPrint'];
	    } else {
		$iCount = 1;	// default: use singular
	    }
	}
	$strSng = NzArray($this->Row,'NameSng');
	if ($iCount == 1) {
	    $out = $strSng;
	} else {
	    $out = NzArray($this->Row,'NamePlr',$strSng);
	}
	return $out;
    }
    /*----
      ACTION: Shows a drop-down selection box contining the rows in the current dataset
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
    */
    public function DropDown_for_rows($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	if ($this->HasRows()) {
	    $out = '<select name="'.$strName.'">';
	    if (!is_null($iChoose)) {
		$out .= '<option>'.$iChoose.'</option>';
	    }
	    while ($this->NextRow()) {
		$id = $this->Row['ID'];
		if ($id == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= '<option'.$htSelect.' value="'.$id.'">'.$this->Name().'</option>';
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No item types found.';
	}
	return $out;
    }
}
/* -------------------- *\
    ITEM OPTION classes
\* -------------------- */
class clsItOpts extends clsVbzTable {
  public function __construct($iDB) {
    parent::__construct($iDB);
      $this->Name('cat_ioptns');
      $this->KeyName('ID');
      $this->ClassSng('clsItOpt');
  }
    // ==BOILERPLATE - cache
    protected $objCache;
    protected function Cache() {
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
    // ==/BOILERPLATE
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
	* Actually, this should be a boilerplate function with a helper class. The only change from clsItTyps
	  is the GetData filter and sorting.
      HISTORY:
	2010-11-21 Adapted from clsItTyps
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$arRows = $this->Cache()->GetData_array(NULL,NULL,'Sort');
	$out = $this->DropDown_for_array($arRows,$strName,$iDefault,$iChoose);
	return $out;
    }
    /*----
      ACTION: same as clsItTyp::DropDown_for_rows, but takes an array
      HISTORY:
	2011-02-11 wrote
    */
    public function DropDown_for_array(array $iRows,$iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->ChoiceLine();
	}
	return DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
class clsItOpt extends clsDataSet {
    /*----
      RETURNS: Approximately as much description as will fit nicely into a choice line for a drop-down or selection box
    */
    public function ChoiceLine() {
	return $this->Value('CatKey');
    }
    /*----
      RETURNS: A longer description for when horizontal space is not tight
    */
    public function DescrFull() {
	return $this->Value('CatKey').' - '.$this->Value('Descr');
    }
}
/* -------------------- *\
    SHIP COST classes
\* -------------------- */
class clsShipCosts extends clsVbzTable {
  public function __construct($iDB) {
    parent::__construct($iDB);
      $this->Name('cat_ship_cost');
      $this->KeyName('ID');
      $this->ClassSng('clsShipCost');
  }
    // ==BOILERPLATE - cache
    protected $objCache;
    protected function Cache() {
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
    // ==/BOILERPLATE
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
	* Actually, this should be a boilerplate function with a helper class. The only change from clsItTyps
	  is the GetData filter and sorting.
      HISTORY:
	2010-11-21 Adapted from clsItTyps
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$arRows = $this->Cache()->GetData_array(NULL,NULL,'Sort');
	$out = $this->DropDown_for_array($arRows,$strName,$iDefault,$iChoose);
	return $out;
    }
    /*----
      ACTION: same as clsItTyp::DropDown_for_rows, but takes an array
      HISTORY:
	2011-02-11 wrote
    */
    public function DropDown_for_array(array $iRows,$iName=NULL,$iDefault=NULL,$iChoose=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;
	$objRow = $this->SpawnItem();
	foreach($iRows as $key => $row) {
	    $objRow->Values($row);
	    $arList[$key] = $objRow->ChoiceLine();
	}
	return DropDown_arr($strName,$arList,$iDefault,$iChoose);
    }
}
class clsShipCost extends clsDataSet {
    /*----
      RETURNS: Approximately as much description as will fit nicely into a choice line for a drop-down or selection box
    */
    public function ChoiceLine() {
	return $this->Value('Descr');
    }
}
/* -------------------- *\
    STOCK ITEM classes
\* -------------------- */
/*====
  CLASS PAIR: Items_Stock
  PURPOSE: items with stock information (from a query or cache)
    Similar to clsItem, but with different fields
  HISTORY:
    2011-01-24 disabled -- use cat_items (has all necessary fields cached)
*/
/*
class clsItems_Stock extends clsItems {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryCat_Items_Stock');
	  //$this->Name('_title_ittyps'); // WRONG! This GROUPS the data, no Items.
	  $this->KeyName('ID');
	  $this->ClassSng('clsItem_Stock');
    }
    public function Render_TableHdr() {
	return '<tr>'
	  .'<th align=left>Option</th>'
	  .'<th>Status</th>'
	  .'<th align=right class=title-price>Price</th>'
	  .'<th align=center class=orderQty>Order<br>Qty.</th>'
	  .'<th><i>list<br>price</th>'
	  .'</tr>';
    }
}
*/
//class clsItem_Stock extends clsItem {
    /*-----
      ASSUMES:
	  This item is ForSale, so isForSale = true and (qtyForSale>0 || isInPrint) = true
	  This item's data was generated by clsItems_Stock
      TO DO: create a separate clsItem_Stock class and move this method there.
      TO DO: this method isn't named or structured canonically; consider refactoring. Whatever that means.
      HISTORY:
	  2011-01-24 moved from clsItem to clsItem_Stock
    */
/*
    public function Render_TableRow() {
	$arStat = $this->AvailStatus();
	$strCls = $arStat['cls'];

	$out = '<tr class='.$strCls.'><!-- ID='.$this->ID.' -->';
	$out .= '<td>&emsp;'.$this->ItOpt_Descr.'</td>';
	$out .= '<td>'.$arStat['html'].'</td>';
	$out .= '<td>'.DataCurr($this->PriceSell).'</td>';
	$out .= '<td>'.'<input size=3 name="qty-'.$this->CatNum.'"></td>';
	if ($this->PriceList) {
	    $out .= '<td><i>'.DataCurr($this->PriceList).'</i></td>';
	}
	$out .= '</tr>';
	return $out;
    }
*/
    /*----
      ACTION: Returns an array with human-friendly text about the item's availability status
      RETURNS:
	array['html']: status text, in HTML format
	array['cls']: class to use for displaying item row in a table
      USED BY: Print_TableRow()
      NOTE: This probably does not actually need to be a separate method; I thought I could reuse it to generate
	status for titles, but that doesn't make sense. Maybe it will be easier to adapt, though, as a separate method.
      HISTORY:
	2010-11-16 Modified truth table for in-print status so that if isInPrint=FALSE, then status always shows
	  "out of print" even if isCurrent=FALSE. What happens when a supplier has been discontinued? Maybe we need to
	  check that separately. Wait for an example to come up, for easier debugging.
	2011-01-24 Adapted from clsItem (was it being used there, for real?) to clsItem_Stock
    */
/*
    private function AvailStatus() {
//echo 'SQL=['.$this->sqlMake.']';
//echo '<pre>'.print_r($this->Row,TRUE).'</pre>';
      $qtyInStock = $this->Value('qtyForSale');
	if ($qtyInStock) {
	    $strCls = 'inStock';
	    $strStk = $qtyInStock.' in stock';
	} else {
	    $strCls = 'noStock';
	    $strStk = 'none in stock';
	}
	if ($this->Value('isInPrint')) {
	    if ($this->Value('isCurrent')) {
		    if ($qtyInStock) {
			$txt = $strStk.'; more available';
		    } else {
			$txt = '<a title="explanation..." href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'">available, not in stock</a>';
		    }
	    } else {
		if ($qtyInStock) {
		    $txt = $strStk.'; in-print status uncertain';
		} else {
		    $txt = $strStk.'; availability uncertain';
		}
	    }
	} else {
	    $txt = '<b>'.$strStk.'</b> - <i>out of print!</i>';
	}
	$arOut['html'] = $txt;
	$arOut['cls'] = $strCls;
	return $arOut;
    }
}
*/

/* -------------------- *\
    IMAGE classes
\* -------------------- */
class clsVbzFolders extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_folders');
	  $this->KeyName('ID');
	  $this->ClassSng('clsVbzFolder');
    }
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
    */
    public function DropDown($iName,$iDefault=NULL) {
	$dsRows = $this->GetData('Descr IS NOT NULL');
	return $dsRows->DropDown_for_rows($iName,$iDefault);
    }
    /*----
      PURPOSE: Finds the folder record which matches as much of the given URL as possible
      RETURNS: object for that folder, or NULL if no match found
      ASSUMES: folder list is not empty
      TO DO:
	does not yet handle adding new folders
	does not recursively check subfolders for improved match
      HISTORY:
	2011-01-30 created -- subfolders not implemented yet because no data to test with
    */
    public function FindBest($iURL) {
	if (strlen($iURL) > 0) {
	    $slURL = strlen($iURL);
	    $rs = $this->GetData('ID_Parent IS NULL');	// start with root folders
	    $arrBest = NULL;
	    $slBest = 0;
	    while ($rs->NextRow()) {
		$fp = $rs->Value('PathPart');
		$pos = strpos($iURL,$fp);	// does the folder appear in the URL?
		if ($pos === 0) {
		    $slFldr = strlen($fp);
		    if ($slFldr > $slBest) {
			$arrBest = $rs->Values();
			$slBest = $slFldr;
		    }
		}
	    }
	    if (is_array($arrBest)) {
		$rsFldr = $this->SpawnItem();
		$rsFldr->Values($arrBest);
		return $rsFldr;
	    }
	}
	return NULL;
    }
}
class clsVbzFolder extends clsDataSet {
    public function Spec() {
	$out = '';
	if (!is_null($this->ID_Parent)) {
	    $out = $this->ParentObj()->Spec();
	}
	$out .= $this->PathPart;
	return $out;
    }
    protected function ParentObj() {
	return $this->Table->GetItem($this->ID_Parent);
    }
    /*----
      ACTION: Shows a drop-down selection box contining the rows in the current dataset
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
    */
    public function DropDown_for_rows($iName,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    while ($this->NextRow()) {
		if ($this->ID == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= '<option'.$htSelect.' value="'.$this->ID.'">'.$this->Spec().'</option>';
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No shipments matching filter';
	}
	return $out;
    }
    /*----
      RETURNS: The rest of the URL after this folder's PathPart is removed from the beginning
      USED BY: bulk image entry admin routine
    */
    public function Remainder($iSpec) {
	$fsFldr = $this->Value('PathPart');
	$slFldr = strlen($fsFldr);
	$fsRest = substr($iSpec,$slFldr);
	return $fsRest;
    }
}
class clsImages extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_images');
	  $this->KeyName('ID');
	  $this->ClassSng('clsImage');
    }
    public function Update(array $iSet,$iWhere) {
	$iSet['WhenEdited'] = 'NOW()';
	parent::Update($iSet,$iWhere);
	$this->Touch(__METHOD__.' WHERE='.$iWhere);
    }
    public function Insert(array $iData) {
	$iData['WhenAdded'] = 'NOW()';
	parent::Insert($iData);
	$this->Touch(__METHOD__);
    }
    public function Thumbnails($iTitle,array $iarAttr=NULL) {
	$sqlFilt = '(ID_Title='.$iTitle.') AND (Ab_Size="th") AND isActive';
	$objTbl = $this->objDB->Images();
	$objRows = $objTbl->GetData($sqlFilt,NULL,'AttrSort');
	return $objRows->Images_HTML($iarAttr);
    }
}
class clsImage extends clsVbzRecs {
// object cache
    protected $objTitle;

    /*----
      HISTORY:
	2010-11-16 Modified to use new cat_folders data via ID_Folder
    */
    public function WebSpec() {
	//return KWP_IMG_MERCH.$this->Spec;
	return $this->FolderPath().$this->Spec;
    }
    /*----
      HISTORY:
	2010-11-16 Created
    */
    public function FolderObj() {
	return $this->objDB->Folders()->GetItem($this->ID_Folder);
    }
    /*----
      HISTORY:
	2010-11-16 Created
    */
    public function FolderPath() {
	return $this->FolderObj()->Spec();
    }
    /*-----
      ACTION: Generate the HTML code to display all images in the current dataset
    */
    public function Images_HTML(array $iarAttr=NULL) {
	if ($this->HasRows()) {
	    $out = '';
	    while ($this->NextRow()) {
		$out .= $this->Image_HTML($iarAttr);
	    }
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*-----
      ACTION: Generate the HTML code to display an image for the current row
    */
    public function Image_HTML(array $iarAttr=NULL) {
	$htDispl = $this->AttrDispl;
	if (!empty($htDispl)) {
	    nzApp($iarAttr['title'],' - '.$htDispl);
	}
	$iarAttr['src'] = $this->WebSpec();
	$htAttr = ArrayToAttrs($iarAttr);
	return '<img'.$htAttr.'>';
    }
    /*-----
      ACTION: Get the image with the same title and attribute but with the given size
    */
    public function ImgForSize($iSize) {
	if ($this->AttrFldr) {
	    $sqlAttr = '="'.$this->AttrFldr.'"';
	} else {
	    $sqlAttr = ' IS NULL';
	}
	$sqlFilt = '(ID_Title='.$this->ID_Title.') AND (AttrFldr'.$sqlAttr.') AND (Ab_Size="'.$iSize.'")';
	$objImgOut = $this->objDB->Images()->GetData($sqlFilt);
	return $objImgOut;
    }
    public function Title() {
      if (!is_object($this->objTitle)) {
	  $this->objTitle = $this->objDB->Titles()->GetItem($this->ID_Title);
      }
      return $this->objTitle;
    }
  public function ListImages_sameAttr() {
    $sqlFilt = 'isActive AND (ID_Title='.$this->ID_Title.')';
    if ($this->AttrFldr) {
      $sqlFilt .= ' AND (AttrFldr="'.$this->AttrFldr.'")';
    }
    $objImgOut = $this->objDB->Images()->GetData($sqlFilt);

    return $objImgOut;
  }
    public function ListImages_sameSize() {
	$sqlFilt = 'isActive AND (ID_Title='.$this->ID_Title.') AND (Ab_Size="'.$this->Ab_Size.'")';
//echo 'SQL: '.$sqlFilt;
	$objImgOut = $this->objDB->Images()->GetData($sqlFilt);
	return $objImgOut;
    }
    public function Href($iAbs=false) {
	$strFldrRel = $this->AttrFldr;
	if ($strFldrRel) {
	    $strFldrRel .= '-';
	}
	$strFldrRel .= $this->Ab_Size;

	if ($iAbs) {
	    $strFldr = $this->Title()->URL().'/'.$strFldrRel;
	} else {
	    $strFldr = $strFldrRel;
	}
	return '<a href="'.$strFldr.'/">';
    }
}
