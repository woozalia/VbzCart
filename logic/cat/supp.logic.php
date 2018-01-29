<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Suppliers
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-supp.php from base.cat.php
    2016-12-03 trait for easier Table access in other classes
*/
trait vtTableAccess_Supplier {
    protected function SuppliersClass() {
	return KS_LOGIC_CLASS_LC_SUPPLIERS;
    }
    protected function SupplierTable($id=NULL) {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper($this->SuppliersClass(),$id);
    }
}

class vctSuppliers extends vcShopTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cat_supp';
    }
    protected function SingularName() {
	return 'vcrSupplier';
    }
    
    // -- CEMENTING -- //
    // ++ QUERIES ++ //
    
    protected function StockInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtStockLinesInfo');
    }
    protected function SupplierItemTypeQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtSuppliertItemTypes');
    }
    
    // -- QUERIES -- //
    // ++ RECORDS ++ //

    public function GetRecord_byCatKey($sKey) {
	$sqlCatKey = $this->GetConnection()->Sanitize_andQuote(strtoupper($sKey));
	$rc = $this->SelectRecords("CatKey=$sqlCatKey");
	$nRows = $rc->RowCount();
	if ($nRows == 1) {
	    $rc->NextRow();
	} elseif ($nRows > 1) {
	    throw new exception("VbzCart data error: $nRows suppliers found for code '$sKey'.");
	}
	return $rc;
    }
    public function GetItem_byKey($iKey) {
	throw new exception('Call GetRecord_byCatKey() instead.');
	$sqlCatKey = $this->Engine()->SafeParam($iKey);
	$rcItem = $this->GetData('CatKey="'.$sqlCatKey.'"');
	return $rcItem;
    }
    /*----
      HISTORY
	2010-11-12 disabled automatic cache update
      USED BY catalog home page (DoHomePage()) (displays all active Suppliers)
    */
    protected function DataSet_forStore($iClass=NULL) {
	/* 2017-05-06 This calls deprecated code and the result is never used anyway.
	$qt = $this->StockInfoQuery();
	$qt->ItemStatusRecords_active();
	*/
    
	$qt = $this->SupplierItemTypeQuery();
	$rs = $qt->QueryRecords_forSellableItems();
	
	/* 2016-12-01 old code
	$sql = 'SELECT * FROM _supplier_ittyps ORDER BY Name, ItemCount DESC';
	$rs = $this->GetConnection()->FetchRecords($sql,$iClass);
	*/

	return $rs;
    }
    
    // -- RECORDS -- //

}

class vcrSupplier extends vcShopRecordset {
    //use ftLoggableRecord;	// 2017-01-15 I think this belongs in the admin class.
    use vtTableAccess_Department;
    use vtTableAccess_Title;
    use vtTableAccess_Item;
    use vtTableAccess_ItemType;

    // ++ FIELD VALUES ++ //

    /*----
      USAGE: Used by other classes for building titles
    */
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    /*----
      USAGE: Used by page display object to determine what title to show
    */
    public function TitleStr() {
	return $this->Value('Name');
    }
    public function CatKey() {
	return $this->GetFieldValue('CatKey');
    }
    public function CatNum() {
	return $this->Value('CatNum');
    }
    
    // -- FIELD VALUES -- //
    // ++ QUERIES ++ //
    
    protected function SupplierItemTypeQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtSuppliertItemTypes');
    }
    
    // -- QUERIES -- //
    // ++ RECORDS ++ //

    protected function GetDepartmentRecord_byCatKey($sKey) {
	return $this->DepartmentTable()->GetRecord_bySupplier_andCatKey($this->GetKeyValue(),$sKey);
    }
    protected function GetTitleRecord_byCatKey($sKey) {
	return $this->TitleTable()->GetRecord_bySupplier_andCatKey($this->GetKeyValue(),$sKey);
    }
    /*----
      ACTION: Checks the given catalog number to see if it corresponds to a given item for the current supplier
      INPUT: supplier catalog number
      OUTPUT: item object (if found) or NULL (if not found)
      HISTORY:
	2011-01-09 Moved here from VbzAdminSupplier; replaces GetItem_bySuppCatNum()
	2016-01-23 TODO: Rename to GetItemRecord_bySupplierCatNum()
    */
    public function GetItem_bySCatNum($iSCat) {
	$sqlFind = '(ID_Supp='.$this->GetKeyValue().') AND (Supp_CatNum="'.$iSCat.'")';
	$rcItem = $this->ItemTable()->GetData($sqlFind);
	if ($rcItem->HasRows()) {
	    $rcItem->NextRow();
	    return $rcItem;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Finds the Title for this Supplier with the given CatKey
      RETURNS: object of type requested by user; defaults to vcrTitle. NULL if not found.
      HISTORY:
	2010-11-07 Created for Title editing page -- need to check for duplicate CatKey before saving.
    */
    public function GetTitle_byCatKey($iCatKey,$iClass='vcrTitle') {
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
    public function GetTitles_byCatKey($iCatKey,$iClass='vcrTitle') {
	$sqlCatKey = $this->Engine()->SafeParam($iCatKey);
	$sqlFilt = '(ID_Supplier='.$this->ID.') AND (CatKey LIKE "%'.$sqlCatKey.'%")';
	$objTitle = $this->Engine()->Titles_Cat()->GetData($sqlFilt,$iClass);
	if ($objTitle->HasRows()) {
	    $objTitle->NextRow();
	    return $objTitle;
	} else {
	    return NULL;
	}
    }
    
    // -- RECORDS -- //
    // ++ ARRAYS ++ //
    
    /*----
      ACTION: builds an array of item type data for the supplier, broken down by department.
	Caches the results in memory.
      TODO: 2016-11-07 This should probably be split off into a Supplier query class
      USED BY: Supplier page display
      RETURNS: array of data for the current supplier
	array['it'][ID_ItTyp] = basic Item Type information
	  ['NameSng']
	  ['NamePlr']
	array['d-it'][ID_Dept][ID_ItTyp] = count of items for sale by department and item type
	array['d'][ID_Dept] = some basic Department info for display
	  ['Name'] = name of Department
	  ['PageKey'] = page key to use for link
	array['s-it'][ID_ItTyp] = count of items for sale by item type, for all of supplier
      HISTORY:
	2011-02-02 switched data source from qryItTypsDepts_ItTyps to _dept_ittyps
	  Page was not displaying at all. Some additional changes were necessary.
	2013-11-18 rewriting to not use cached table(s)
	2015-10-08 added CatKey field to Department results
	2016-02-10 rewrote SQL
    */
    protected function PageData_forStore() {
	$idSupp = $this->GetKeyValue();
	
	// 2016-11-07 This replaces the stuff below:
	$rs = $this->SupplierItemTypeQuery()->FetchData_forExhibit_single($idSupp);
	return $rs->CompileExhibitData_array();
	
/* 2016-11-07 old version
	//$db = $this->Engine();
	$sqlDpt = $this->DepartmentTable()->TableName_Cooked();
	$sqlTtl = $this->TitleTable()->TableName_Cooked();
	$sqlItm = $this->ItemTable()->TableName_Cooked();
	$sqlItt = $this->ItemTypeTable()->TableName_Cooked();
	$sqlStock = vcqtStockLinesInfo::SQL_forItemStatus();

	$sql = <<<__END__
SELECT
  i.*,
  t.ID_Dept,
  it.NameSng,
  it.NamePlr,
  d.Name AS DeptName,
  d.PageKey AS DeptPageKey,
  d.CatKey AS DeptCatKey
FROM (($sqlItm AS i
JOIN $sqlTtl AS t ON i.ID_Title=t.ID)
JOIN $sqlItt AS it ON i.ID_ItTyp=it.ID)
JOIN $sqlDpt AS d ON t.ID_Dept=d.ID
JOIN ($sqlStock) AS s ON s.ID_Item=i.ID
WHERE (i.ID_Supp=$idSupp) AND (i.isAvail OR (s.QtyForSale>0))
__END__;
*/
/* 2016-02-10 older version
	$sql = <<<__END__
SELECT
  i.*,
  t.ID_Dept,
  it.NameSng,
  it.NamePlr,
  d.Name AS DeptName,
  d.PageKey AS DeptPageKey,
  d.CatKey AS DeptCatKey
FROM (($sqlItm AS i
LEFT JOIN $sqlTtl AS t ON i.ID_Title=t.ID)
LEFT JOIN $sqlItt AS it ON i.ID_ItTyp=it.ID)
LEFT JOIN $sqlDpt AS d ON t.ID_Dept=d.ID
WHERE (i.ID_Supp=$idSupp) AND i.isForSale
__END__;
//*/
//	$rs = $this->GetConnection()->FetchRecords($sql);
	// recordset of all Items for this Supplier

	/* 2016-11-07 moved to separate class
	$arSIT = NULL;
	$arDIT = NULL;
	$arIT = array();
	$arD = array();
	while ($rs->NextRow()) {
	    $idDept = $rs->Value('ID_Dept');
	    $idItTyp = $rs->Value('ID_ItTyp');
	    // these would normally generate an error for the first usage of each index
	    @$arDIT[$idDept][$idItTyp]++;
	    @$arSIT[$idItTyp]++;
	    if (!array_key_exists($idItTyp,$arIT)) {
		$arIT[$idItTyp] = array(
		  'NameSng'	=> $rs->Value('NameSng'),
		  'NamePlr'	=> $rs->Value('NamePlr')
		  );
	    }
	    if (!array_key_exists($idDept,$arD)) {
		$arD[$idDept] = array(
		  'Name'	=> $rs->Value('DeptName'),
		  'PageKey'	=> $rs->Value('DeptPageKey'),
		  'CatKey'	=> $rs->Value('DeptCatKey')
		  );
	    }
	}
	$ar = array(
	  's-it'=> $arSIT,
	  'd-it'=> $arDIT,
	  'it'	=> $arIT,
	  'd'	=> $arD
	  );
	return $ar; */
    }

    // -- ARRAYS -- //
}
