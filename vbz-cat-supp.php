<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Suppliers
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-supp.php from base.cat.php
*/
class clsSuppliers extends clsVbzTable {
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
