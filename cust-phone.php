<?php

/*
  HISTORY:
    2014-09-23 Split off from base.cust.php (to be renamed cust.php)
*/
// == CUSTOMER PHONE NUMBER
class clsCustPhones extends clsTable {
    // STATIC
    const TableName='cust_phones';

    public static function Searchable($iRaw) {
	$xts = new xtString(strtolower($iRaw),TRUE);
	/*
	$xts->DelAscRange(0,47);	// up to 0
	$xts->DelAscRange(58,255);	// after 9
	*/
	$xts->KeepOnly('0-9');		// keep only numerics (TO DO: translate letters to their phone-dial digits)
	$xts->DelLead('01');		// remove any initial phone # escape codes
	return $xts->Value;
    }

    // DYNAMIC
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsCustPhone');
    }
    /*----
      HISTORY:
	2012-04-20 created for 2nd rewrite of import routine
    */
    public function Find($iVal) {
	$sql = $this->MakeFilt_Cust($iVal);
	$rs = $this->GetData($sql);
	return $rs;
    }
    /*----
      HISTORY:
	2011-02-22 created
      IMPLEMENTATION:
	Assumes both ID_Cust and PhoneSrch are set.
	Both must match.
    */
/* 2011-11-21 this version deprecated until we figure out who is using it
    protected function MakeFilt(array $iData) {
	$sqlCust = $iData['ID_Cust'];
	$sqlSrch = SQLValue($iData['PhoneSrch']);
	return "(ID_Cust=$sqlCust) AND (PhoneSrch=$sqlSrch)";
    }
*/
    /*----
      HISTORY:
	2011-11-21 adapted from clsCustEmails to clsCustPhones
	2012-04-20 reversed args so idCust can be optional; added code to handle this
    */
    protected function MakeFilt_Cust($iValue,$idCust=NULL) {
	$sqlSrch = SQLValue(self::Searchable($iValue));
	$sql = "PhoneSrch=$sqlSrch";
	if (is_null($idCust)) {
	    return $sql;
	} else {
	    return "(ID_Cust=$idCust) AND ($sql)";
	}
    }
    /*----
      HISTORY:
	2011-03-31 Was commented out; reinstated and renamed from Make() to Make_fromData()
	2011-11-21 Modified to use new MakeFilt_Cust() method
	2012-04-20 This can't be working -- nothing sets $objRows; commenting out
    */
/*
    public function Make_fromData($iCustID,$iValue,array $iArData=NULL) {
//	$sqlFind = SQLValue(self::Searchable($iValue));

//	$objRows = $this->GetData("(ID_Cust=$iCustID) AND (PhoneSrch=$sqlFind)");
	$sqlSrch = $this->MakeFilt_Cust($iValue,$iCustID);
	if ($objRows->HasRows()) {
	    $objRows->NextRow();
	    $id = $objRows->ID;
	    if (is_array($iArData)) {
		$objRows->Update($iArData);
	    }
	} else {
	    $id = $this->Insert_fromData($iCustID,$iValue);
	}
	return $id;
    }
*/
    /*----
      RETURNS: Script object for changes to make
      INPUT:
	$iCustID: ID of customer record being handled
	$iValue: phone number which needs to be associated with that customer
      HISTORY:
	2011-09-23 Created for clsCustEmails so we can inspect SQL before executing
	2011-11-21 adapting from clsCustEmails to clsCustPhones
	2011-12-15 handles empty $iCustID gracefully now
    */
    public function Make_Script($iCustID,$iValue) {
	$isFound = TRUE;
	if (empty($iCustID)) {
	    $isFound = FALSE;
	} else {
	    $sqlFilt = $this->MakeFilt_Cust($iValue,$iCustID);
	    $objRows = $this->GetData($sqlFilt);
	    $isFound = $objRows->HasRows();
	}
	if ($isFound) {
/*
	    $cnt = $objRows->RowCount();
	    $objRows->FirstRow();
	    $id = $objRows->KeyValue();
	    $act = new Script_Status('SAME as '.$cnt.' existing phone'.Pluralize($cnt).' - first: #'.$id.' '.$objRows->Value('Phone'));
*/
	    $act = new Script_Row_Data();
	    $act->LetRecord($objRows);
	} else {
	    $ar = $this->Add_SQL_base($iValue);
	    $ar['ID_Cust'] = $iCustID;
	    $ar['WhenEnt'] = 'NOW()';
	    $act = new Script_Tbl_Insert($ar,$this);
	}
	$act->Name('phone.data');
	return $act;
    }
    /*----
      RETURNS: Script to add a new email address to a customer that hasn't been created yet
      HISTORY:
	2011-10-05 adapted from clsCustEmails
	2013-11-06 commenting this out because data-scripting is being removed
    */
/*
    public function Script_forAdd($iValue, Script_Tbl_Insert $iCustInsert_Script) {
	$acts = new Script_Script();

	$ar = $this->Add_SQL_base($iValue);
	$actIns = new Script_Tbl_Insert($ar,$this);

	$acts->Add(new Script_SQL_Use_ID($iCustInsert_Script,$actIns,'ID_Cust'));
	$acts->Add($actIns,'cust.phone.add');
	return $acts;
    }
*/
    /*----
      PURPOSE: fills in the INSERT array with everything we know by default
      HISTORY:
	2011-10-05 created for Make_Add_Script() - adapted from Insert_fromData()
    */
    protected function Add_SQL_base($iValue) {
	$sqlSearch = SQLValue(self::Searchable($iValue));
	$arIns = array(
	  //'ID_Cust'	=> $iCustID,	// we don't know this yet
	  'Phone'	=> SQLValue($iValue),
	  'PhoneSrch'	=> $sqlSearch,
	  'isActive'	=> 'TRUE'
	  );
	return $arIns;
    }
    /*----
      HISTORY:
	2011-03-31 Was commented out; reinstated and renamed from Create() to Insert_fromData()
	  Also marked as private instead of public.
    */
    private function Insert_fromData($iCustID,$iValue,array $iArData=NULL) {
	$arIns = $this->Add_SQL_base($iCustID,$iValue,$iArData);
	$arIns['ID_Cust'] = $iCustID;
	if (is_array($iArData)) {
	    $arIns = array_merge($arIns,$iArData);
	}

	$ok = $this->Insert($arIns);
	if ($ok) {
	    $id = $this->objDB->NewID();
	} else {
	    $id = NULL;
	}
	return $id;
    }
}
class clsCustPhone extends clsVbzRecs {
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function Number() {
	return $this->Value('Phone');
    }
    public function ShortDescr() {
	return $this->Value('Phone');
    }
}
