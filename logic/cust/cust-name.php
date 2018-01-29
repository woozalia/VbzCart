<?php

/*
  HISTORY:
    2014-09-23 Split off from base.cust.php (to be renamed cust.php)
*/
// CUSTOMER NAME
class vctCustNames extends vcBasicTable {
    use ftUniqueRowsTable;

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cust_names';
    }
    protected function SingularName() {
	return 'vcrCustName';
    }

    // -- CEMENTING -- //
    // ++ STATIC ++ //

    public static function Searchable($sRaw) {
	if (!is_string($sRaw)) {
	    throw new exception('received non-string as argument');
	}
	$xts = new xtString(strtolower($sRaw),TRUE);
	$xts->DelAscRange(0,96);
	$xts->DelAscRange(123,255);
	return $xts->Value;
    }
    public function SearchableSQL($sRaw,$sPfx=NULL,$sSfx=NULL) {
	return $this->GetConnection()->Sanitize_andQuote($sPfx.self::Searchable($sRaw).$sSfx);
    }

    // -- STATIC -- //
    // ++ RECORDS ++ //
    
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

    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //
    
    /*----
      ACTION: Generates the SQL to create the record
      INPUT:
	idCust: customer ID for this name
	sName: name to add
      RETURNS: array for Insert()
    */
    public function Create_array($idCust, $sName) {
	$arIns = $this-> Create_array_init($sName);
	$arIns['ID_Cust'] = $idCust;
	$arIns['WhenEnt'] = 'NOW()';
	return $arIns;
    }
    /*----
      ACTION: Generates the initial SQL to create the record just from the name string
	ID_Cust needs to be filled in before the SQL is executed.
    */
    public function Create_array_init($sName) {
	$db = $this->Engine();
	$arIns = array(
	  'Name'	=> $db->SanitizeAndQuote($sName),
	  'NameSrch'	=> $db->SanitizeAndQuote(self::Searchable($sName)),
	  'isActive'	=> 'TRUE'
	  );
	return $arIns;
    }
    /*----
      HISTORY:
	2012-04-24 Making function public, to match declaration in abstract class
    */
    public function MakeFilt(array $ar) {
	$idCust = $ar['ID_Cust'];
	$sqlName = $this->Engine()->SanitizeAndQuote($ar['Name']);
	return "(ID_Cust=$idCust) AND (Name=$sqlName)";
    }
    /*----
      PURPOSE: for compatibility with the boilerplate code in $this->Make_Script()
      HISTORY:
	2011-11-30 created
	2016-07-08 may be redundant now; see Make_forCust()
    */
    protected function MakeFilt_Cust($idCust,$sValue) {
	$ar = array(
	  'ID_Cust'	=> $idCust,
	  'Name'	=> $sValue,
	  );
	return $this->MakeFilt($ar);
    }

    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*-----
      ACTION: Always creates a new record
    */
    public function CreateRecord($idCustID, $sName) {
	if (is_string($sName)) {
	    $arIns = $this->Create_array($idCustID,$sName);
	    $ok = $this->Insert($arIns);
	    $id = $this->Engine()->NewID();
	} else {
	    $id = NULL;
	}
	return $id;
    }
    /*----
      RETURNS: matching recordset
      ACTION: either creates a recordset matching $idCust and $sName, or finds an existing one that matches
    */
    public function MakeRecord_forCust($idCust,$sName) {
	$ar = array(
	  'ID_Cust'	=> $idCust,
	  'Name'	=> $sName,
	  );
	$rc = $this->MakeDistinct($ar);
	return $rc;
    }
    /*----
      ACTION: Either create a new record from $ar or find one that matches
      TODO: This could probably be merged with customer address AutoMake().
    */
    protected function MakeDistinct(array $ar) {
	$rc = $this->FetchRecord_toMatchValues($ar);
	if (is_null($rc)) {
	    $rc = $this->SpawnRecordset();
	    $rc->SetFieldValues($ar);
	    $rc->Save();
	} else {
	    $rc->NextRow();
	}
	return $rc;
    }
    public function Recs_forCust($idCust,$iFilt=NULL) {
	throw new exception('2016-11-04 This is misleadingly named. If anyone is still calling it, rename it.');
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
    
    // -- ACTIONS -- //
    /*-----
      ACTION: Creates a new record if an exact match for the given customer name/ID is not found
      HISTORY:
	2016-06-27 This was commented out, with no date or note, but I'm thinking now it makes sense
	  to restrict name records to only *unique* names. Think of the drop-down lists...
	  Also updated Ferreteria usage a bit.
      TODO: Eventually we will want to go through all the duplicate name records created in the interim,
	and reduce them.
    */
    /* 2016-07-03 Don't know who calls this, but it's generating an error because it doesn't match the parent.
    public function Make($idCust, $sName) {
    throw new exception('Who calls this? Needs to be renamed Make_withName() OSLT.');
	$sqlName = $this->Engine()->SanitizeAndQuote($sName);
	$rs = $this->SelectRecords("(ID_Cust=$idCust) AND (Name='$sqlName')");
	if ($rs->HasRows() === FALSE) {
	    return $this->Create($idCust,$sName);
	} else {
	    $rs->NextRow();
	    return $rs->GetKeyValue();
	}
    }*/

}
class vcrCustName_trait extends vcBasicRecordset {
    use ftSaveableRecord;
}
class vcrCustName extends vcrCustName_trait {

    // ++ OVERRIDES ++ //

    protected function UpdateArray($ar=NULL) {
	$ar = parent::UpdateArray($ar);
	//$ar = fcArray::Merge($ar,$this->ChangeArray());	2016-11-04 This should be redundant now.
	$ar['WhenUpd'] = 'NOW()';
	return $ar;
    }
    protected function InsertArray($ar=NULL) {
	$ar = parent::InsertArray($ar);
	//$ar = fcArray::Merge($ar,$this->ChangeArray());	2016-11-04 This should be redundant now.
	$ar['WhenEnt'] = 'NOW()';
	return $ar;
    }
    protected function ChangeArray($ar=NULL) {
	$ar = parent::ChangeArray($ar);
	$ar['NameSrch']	= $this->GetTableWrapper()->SearchableSQL($this->GetNamePlain());
	return $ar;
    }
    
    // -- OVERRIDES -- //
    // ++ FIELD VALUES ++ //

    public function CustID() {
	return $this->Value('ID_Cust');
    }
    protected function GetNamePlain() {
	return $this->GetFieldValue('Name');
    }
    public function NameSearchable() {
	return $this->Value('NameSrch');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function ShortDescr() {
	$sName = $this->GetNamePlain();
	if (empty($sName)) {
	    $out = "(blank name)";
	} else {
	    $out = $sName;
	}
	$id = $this->GetKeyValue();
	return $out." (n$id)";
    }

    // -- FIELD CALCULATIONS -- //
}
