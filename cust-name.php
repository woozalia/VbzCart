<?php

/*
  HISTORY:
    2014-09-23 Split off from base.cust.php (to be renamed cust.php)
*/
// CUSTOMER NAME
class clsCustNames extends clsTable {
    // STATIC
    const TableName='cust_names';

    public static function Searchable($iRaw) {
	if (!is_string($iRaw)) {
	    throw new exception('received non-string as argument');
	}
	$xts = new xtString(strtolower($iRaw),TRUE);
	$xts->DelAscRange(0,96);
	$xts->DelAscRange(123,255);
	return $xts->Value;
    }
    public static function SearchableSQL($iRaw,$iPfx=NULL,$iSfx=NULL) {
	return SQLValue($iPfx.self::Searchable($iRaw).$iSfx);
    }

    // DYNAMIC
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsCustName');
    }
    public function Find($iName) {
	$sqlSrch = self::SearchableSQL($iName);
	$objRows = $this->GetData('NameSrch='.$sqlSrch);
	return $objRows;
    }
    public function Search($iName) {
	$sqlSrch = self::SearchableSQL($iName,'%','%');
	$objRows = $this->GetData('NameSrch LIKE '.$sqlSrch);
	return $objRows;
    }
    /*-----
      ACTION: Always creates a new record
    */
    public function CreateRecord($iCustID, $iNameStr) {
/*	$strName = $iNameStr;
	$arIns = array(
	  'ID_Cust'	=> $iCustID,
	  'Name'	=> SQLValue($strName),
	  'NameSrch'	=> SQLValue(self::Searchable($strName)),
	  'isActive'	=> 'TRUE'
	  );
*/
	$arIns = $this-> Create_array($iCustID,$iNameStr);
	$ok = $this->Insert($arIns);
	return $this->Engine()->NewID();
    }
    /*----
      ACTION: Generates the initial SQL to create the record just from the name string
	ID_Cust needs to be filled in before the SQL is executed.
    */
    public function Create_array_init($iNameStr) {
	$arIns = array(
	  'Name'	=> SQLValue($iNameStr),
	  'NameSrch'	=> SQLValue(self::Searchable($iNameStr)),
	  'isActive'	=> 'TRUE'
	  );
	return $arIns;
    }
    /*----
      ACTION: Generates the SQL to create the record
      INPUT:
	iCustID: customer ID for this name
	iNameStr: name to add
      RETURNS: array for Insert()
    */
    public function Create_array($iCustID, $iNameStr) {
	$arIns = $this-> Create_array_init($iNameStr);
	$arIns['ID_Cust'] = $iCustID;
	$arIns['WhenEnt'] = 'NOW()';
	return $arIns;
    }
    /*-----
      ACTION: Creates a new record if an exact match for the given customer name/ID is not found
    */
/*
    public function Make($iCustID, $iNameStr) {
	$sqlName = $this->objDB->SafeParam($iNameStr);
	$objRows = $this->GetData('(ID_Cust='.$iCustID.') AND (Name="'.$sqlName.'")');
	if ($objRows->HasRows() === FALSE) {
	    return $this->Create($iCustID,$iNameStr);
	} else {
	    $objRows->NextRow();
	    return $objRows->ID;
	}
    }
*/
    /*----
      HISTORY:
	2012-04-24 Making function public, to match declaration in abstract class
    */
    public function MakeFilt(array $iData) {
	$idCust = $iData['ID_Cust'];
	$sqlName = SQLValue($iData['Name']);
	return '(ID_Cust='.$idCust.') AND (Name='.$sqlName.')';
    }
    /*----
      PURPOSE: for compatibility with the boilerplate code in $this->Make_Script()
      HISTORY:
	2011-11-30 created
    */
    protected function MakeFilt_Cust($idCust,$iValue) {
	$ar = array(
	  'ID_Cust'	=> $idCust,
	  'Name'	=> $iValue,
	  );
	return $this->MakeFilt($ar);
    }
    /*----
      RETURNS: Script object for changes to make
      INPUT:
	$iCustID: ID of customer record being handled;
	  NULL = no existing customer, so always add
	$iValue: email address which needs to be associated with that customer
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
    */
/* 2013-11-09 removing all data-scripting
    public function Make_Script($iCustID,$iValue) {
	if (is_null($iCustID)) {
	    $isFnd = FALSE;
	} else {
	    $sqlFilt = $this->MakeFilt_Cust($iCustID,$iValue);
	    $objRows = $this->GetData($sqlFilt);
	    $isFnd = $objRows->HasRows();
	}
	if ($isFnd) {
	    $act = new Script_Row_Data();
	    $act->LetRecord($objRows);
	} else {
	    $ar = $this->Create_SQL_init($iValue);
	    $ar['ID_Cust'] = SQLValue($iCustID);	// might be NULL
//	    $act = new Script_Status('DIFF: no match found for "'.$iValue.'"');
	    $act = new Script_Tbl_Insert($ar,$this);
	}
//throw new exception('How do we get here?');
	$act->Name('name.data');
	return $act;
    }
*/
    public function Recs_forCust($idCust,$iFilt=NULL) {
	$tbl = $this->MailAddrTable();
	return $tbl->Recs_forCust($idCust,$iFilt);
/*
	$sqlFilt = 'ID_Cust='.$idCust;
	if (!is_null($iFilt)) {
	    $sqlFilt = '('.$sqlFilt.') AND ('.$iFilt.')';
	}
	$rc = $tbl->GetData($sqlFilt);
	return $rc;
	*/
    }

}
class clsCustName extends clsVbzRecs {
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function NamePlain() {
	return $this->Value('Name');
    }
    public function ShortDescr() {
	$sName = $this->NamePlain();
	if (empty($sName)) {
	    $out = "(blank name)";
	} else {
	    $out = $sName;
	}
	$id = $this->KeyValue();
	return $out." (n$id)";
    }
}
