<?php
/*
  PURPOSE: replaces the _supplier_ittyps calculated table
  HISTORY:
    2016-11-06 created
*/
class vcqtSuppliertItemTypes extends fcTable_wSource_wRecords {
    use vtTableAccess_Department;
    use vtTableAccess_Title;
    use vtTableAccess_Item;
    use vtTableAccess_ItemType;

    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'vcqrSupplierItemType';
    }

    // -- OVERRIDES -- //
    // ++ QUERIES ++ //

    protected function ItemInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtItemsInfo');
    }
    protected function StockInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtStockLinesInfo');
    }
    
    // -- QUERIES -- //
    // ++ RECORDS ++ //
    
    /*----
      ACTION: Fetch the recordset for the given Supplier's exhibit page
    */
    public function FetchData_forExhibit_single($idSupp) {
	$sqlDept = $this->DepartmentTable()->TableName_Cooked();
	$sqlTitle = $this->TitleTable()->TableName_Cooked();
	$sqlItem = $this->ItemTable()->TableName_Cooked();
	$sqlItTyp = $this->ItemTypeTable()->TableName_Cooked();
	//$sqlStock = vcqtStockLinesInfo::SQL_forItemStatus();
	$sqlStock = vcqtStockItemsInfo::SQL_forItemStatus('TRUE');	// "TRUE" here is a kluge to get the query working

	$sql = <<<__END__
SELECT
  i.*,
  t.ID_Dept,
  it.NameSng,
  it.NamePlr,
  d.Name AS DeptName,
  d.PageKey AS DeptPageKey,
  d.CatKey AS DeptCatKey
FROM (($sqlItem AS i
JOIN $sqlTitle AS t ON i.ID_Title=t.ID)
JOIN $sqlItTyp AS it ON i.ID_ItTyp=it.ID)
JOIN $sqlDept AS d ON t.ID_Dept=d.ID
JOIN ($sqlStock) AS s ON s.ID_Item=i.ID
WHERE (i.ID_Supp=$idSupp) AND (i.isAvail OR (s.QtyForSale>0))
__END__;

	$rs = $this->FetchRecords($sql);
	$this->GetConnection()->TestDriver($rs);	// debugging
	return $rs;
    
    }

    // -- RECORDS -- //
    // ++ ARRAYS ++ //

    /*----
      FIELDS NEEDED: s.ID, s.CatKey, s.Name, ItemType, ItemCount
	ItemCount = number of active Titles for this Item Type
	ItemType = string for type - it.NamePlr or it.NameSng depending on ItemCount
    */
    public function FigureArray_forActiveSuppliers_ItemTypeAvailability() {
	// get in-stock status by item
	$qt = $this->StockInfoQuery();
	$rs = $qt->ItemStatusRecords_active();
	
	$ar = array();
	while ($rs->NextRow()) {
	    $ar[$rs->ItemID()] = $rs->GetFieldValues();
	}
	
	// get more item information for available items
	$qt = $this->ItemInfoQuery();
	$rs = $qt->Records_byItemType_active();
	throw new exception('writing still in progress');
    }

    // -- ARRAYS -- //

    /*----
      RETURNS: Recordset of all active Supplier-ItemType combinations
	For now, this will mean "in stock". TODO: Later, there should be
	  a user option to include items known to be available from Suppliers.
      FIELDS NEEDED: s.ID, s.CatKey, s.Name, ItemType, ItemCount
	ItemCount = number of active Titles
	ItemType = it.NamePlr or it.NameSng depending on ItemCount
      HISTORY:
	2016-11-06 started, but looks like maybe not what we need; commented out
	2016-12-01 yes, we need it for catalog home page (Suppliers index)
    */
    public function QueryRecords_forSellableItems() {
	// TODO: use SQO to generate this
	$sqlItemStatus = vcqtStockItemsInfo::SQL_forItemStatus('QtyForSale > 0');
	$sql = <<<__END__
SELECT
    i.ID_Supp,
    i.ID_ItTyp,
    SUM(si.QtyForSale) AS QtyForSale
  FROM ($sqlItemStatus) AS si
    LEFT JOIN cat_items AS i
    ON si.ID_Item=i.ID
  GROUP BY i.ID_Supp,i.ID_ItTyp
	
__END__;

	/* 2016-12-02 old code
	$sqlTitles = <<<__END__
  SELECT
      ID_Title,
      SUM(IF(i.isForSale,1,0)) AS cntForSale,
      SUM(IF(i.isInPrint,1,0)) AS cntInPrint,
      SUM(i.qtyIn_Stk) AS qtyInStock,
      MIN(i.PriceSell) AS currMinSell,
      MAX(i.PriceSell) AS currMaxSell
    FROM cat_items AS i GROUP BY ID_Title
       
__END__;
	$sqlMain = <<<__END__
  SELECT
      s.ID,
      if(Count(ti.ID_Title)=1,it.NameSng,it.NamePlr) AS ItemType,
      Count(ti.ID_Title) AS ItemCount,
      s.Name, s.CatKey
    FROM (
      (cat_supp AS s LEFT JOIN $sqlTitles AS tc ON tc.ID_Supp=s.ID)
      LEFT JOIN _title_ittyps AS ti ON ti.ID_title=tc.ID)
      LEFT JOIN cat_ittyps AS it ON ti.ID_ItTyp=it.ID
    WHERE (tc.cntForSale>0) AND (it.ID IS NOT NULL)
    GROUP BY s.ID, s.Name, s.CatKey, it.NameSng, it.NamePlr, ID_Parent
    HAVING SUM(tc.cntForSale)
    ORDER BY s.Name, SUM(tc.cntForSale) DESC;
__END__;
die('SQL for Titles:<br>'.$sqlTitles);
//die('SQL:<br>'.$sqlMain); */
	return $this->FetchRecords($sql);
    }
}
class vcqrSupplierItemType extends vcBasicRecordset {

    /*----
      ACTION: reduce the recordset returned by FetchData_forExhibit_single() into an array ready for page-rendering
    */
    public function CompileExhibitData_array() {
	$arSIT = NULL;
	$arDIT = NULL;
	$arIT = array();
	$arD = array();
	while ($this->NextRow()) {
	    $idDept = $this->GetFieldValue('ID_Dept');
	    $idItTyp = $this->GetFieldValue('ID_ItTyp');
	    // these would normally generate an error for the first usage of each index
	    @$arDIT[$idDept][$idItTyp]++;
	    @$arSIT[$idItTyp]++;
	    if (!array_key_exists($idItTyp,$arIT)) {
		$arIT[$idItTyp] = array(
		  'NameSng'	=> $this->GetFieldValue('NameSng'),
		  'NamePlr'	=> $this->GetFieldValue('NamePlr')
		  );
	    }
	    if (!array_key_exists($idDept,$arD)) {
		$arD[$idDept] = array(
		  'Name'	=> $this->GetFieldValue('DeptName'),
		  'PageKey'	=> $this->GetFieldValue('DeptPageKey'),
		  'CatKey'	=> $this->GetFieldValue('DeptCatKey')
		  );
	    }
	}
	$ar = array(
	  's-it'=> $arSIT,
	  'd-it'=> $arDIT,
	  'it'	=> $arIT,
	  'd'	=> $arD
	  );
	return $ar;
    }
}