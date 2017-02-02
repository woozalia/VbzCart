<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Departments
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-dept.php from base.cat.php
    2016-11-06 removed comment about Departments being deprecated; other solution proved awkward/difficult to implement.
      Might still do it in the future, but it isn't something to be undertaken in a hurry.
    2016-12-03 trait for easier table access in other classes
*/
trait vtTableAccess_Department {
    protected function DepartmentsClass() {
	return 'clsDepts';
    }
    protected function DepartmentTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->DepartmentsClass(),$id);
    }
}

class clsDepts extends vcShopTable {

    // ++ CEMENTING ++ //

    protected function TableName() {
	return 'cat_depts';
    }
    protected function SingularName() {
	return 'clsDept';
    }

    // -- CEMENTING -- //
    // ++ RECORDS ++ //

    /*----
      NOTE: If PageKey is NULL, CatKey should be used instead
    */
    public function GetRecord_bySupplier_andCatKey($idSupp,$sKey) {
	$sqlCatKey = $this->GetConnection()->Sanitize_andQuote(strtoupper($sKey));
	$sqlFilt = "(ID_Supplier=$idSupp) AND (IFNULL(PageKey,CatKey)=$sqlCatKey)";
	$rc = $this->SelectRecords($sqlFilt);
	$nRows = $rc->RowCount();
	if ($nRows == 1) {
	    $rc->NextRow();
	} elseif ($nRows > 1) {
	    throw new exception("VbzCart data error: $nRows departments found for code '$sKey' in supplier ID $idSupp.");
	}
	return $rc;
    }
    
    // -- RECORDS -- //
}
class clsDept extends vcBasicRecordset {
    use vtTableAccess_Supplier, vtTableAccess_Title;
    
    // ++ RECORDS ++ //

    private $rcSupp;
    public function SupplierRecord() {
	if (!is_object($this->rcSupp)) {
	    $idSupp = $this->SupplierID();
	    if ($idSupp != 0) {
		$this->rcSupp = $this->SupplierTable($idSupp);
	    
	    } else {
		$this->rcSupp = NULL;
	    }
	}
	return $this->rcSupp;
    }
    /*----
      HISTORY:
	2015-11-09 created while updating Department admin methods
    */
    public function TitleRecords() {
	$id = $this->GetKeyValue();
	$sqlFilt = "ID_Dept=$id AND (DateUnavail IS NULL) OR (DateUnavail > NOW())";
	$rs = $this->TitleTable()->DataSet("WHERE $sqlFilt ORDER BY CatKey, Name");
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //

    public function SupplierID() {
	return $this->GetFieldValue('ID_Supplier');
    }
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    public function Description() {
	return $this->GetFieldValue('Descr');
    }
    public function IsActive() {
	return $this->GetFieldValue('isActive');
    }
    public function CatKey() {
	return $this->GetFieldValue('CatKey');
    }
    public function SortKey() {
	return $this->GetFieldValue('Sort');
    }
    protected function PageKey_asSet() {
	return $this->GetFieldValue('PageKey');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function PageKey_toUse() {
	if ($this->PageKey_isSet()) {
	    return $this->PageKey_asSet();
	} else {
	    return $this->CatKey();
	}
    }
    // RETURNS: TRUE = actual PageKey set; FALSE = using CatKey value for PageKey
    protected function PageKey_isSet() {
	return !is_null($this->PageKey_asSet());
    }
    /*-----
      RETURNS: The string which, when prepended to a Title's CatKey, would form the Title's catalog number
      HISTORY:
	2013-11-18 Added sSep parameter so we could generate URLs too
    */
    public function CatPfx($sSep='-') {
	$strFull = strtoupper($this->SupplierRecord()->CatKey());
	if ($this->AffectsCatNum()) {
	    $strFull .= $sSep.strtoupper($this->CatKey());
	}
	return $strFull.$sSep;
    }
    public function ShopURL() {
	$strURL = $this->SupplierRecord()->ShopURL();
	$strKey = $this->PageKey_toUse();
	if ($strKey) {
	    $strURL .= strtolower($strKey).'/';
	}
	return $strURL;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*-----
      RETURNS: TRUE if this department affects the catalog number (i.e. if CatKey is non-blank)
    */
    public function AffectsCatNum() {
	return ($this->CatKey() != '');
    }
    
}
