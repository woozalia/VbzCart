<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Page lookup
  HISTORY:
    2012-05-13 moved clsCatPages from pages.php to base.cat.php
    2013-11-10 split off vbz-cat-page.php from base.cat.php
    2017-01-08 I *think* these classes are no longer used.
*/
class clsCatPages extends vcShopTable {

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_pages');	// cache
	  //$this->Name('qryCat_pages');	// live data
	  $this->KeyName('AB');
	  $this->ClassSng('clsCatPage');
    }
    /*----
      RETURNS: Catalog Page record, if found
	Recordset will be empty if not found.
      HISTORY:
	2015-10-12 Removed "-" -> "/" conversion because it causes problems
	  where catkeys have "-" in them, e.g. PrimalWear. If there's a need
	  for it, document it so we can solve the problem better.
    */
    public function GetItem_byKey($iKey) {
	$strKey = trim($iKey,'/');
//	$strKey = str_replace('-','/',$strKey);
	$sqlCatKey = $this->Engine()->SafeParam($strKey);
	$rcCPage = $this->GetData('Path="'.$sqlCatKey.'"');
	$rcCPage->NextRow();	// load first/only row
	return $rcCPage;
    }
}
// just for paralellism, at this point
class clsCatPage extends vcShopRecordset {
    // object cache
    private $oItem;

    protected function InitVars() {
	$this->oItem = NULL;
	parent::InitVars();
    }
    
    // ++ DATA RECORD ACCESS ++ //
    
    /*----
      RETURNS: an object of the appropriate type, as determined by what the current page information record indicates
      ASSUMES: if there is an object, it's the correct one
    */
    public function ItemObj() {
	if (is_null($this->oItem)) {
	    $id = $this->Row['ID_Row'];
	    $objData = $this->Engine();
	    switch ($this->TypeKey()) {
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
	    $this->oItem = $rc;
	}
	return $this->oItem;
    }
    
    // -- DATA RECORD ACCESS -- //
    // ++ DATA FIELD ACCESS ++ //
    
    public function TypeKey() {
	return $this->Value('Type');
    }
    public function RowID() {
	return $this->Value('ID_Row');
    }
    public function TitleStr() {
	$oItem = $this->ItemObj();
	if (is_null($oItem)) {
	    return NULL;
	} else {
	    return $oItem->TitleStr();
	}
    }
    
    // -- DATA FIELD ACCESS -- //
}
