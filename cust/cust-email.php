<?php

/*
  HISTORY:
    2014-09-23 Split off from base.cust.php (to be renamed cust.php) -> cust-email.php
*/

// == CUSTOMER EMAIL ADDRESS
class clsCustEmails extends vcBasicTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cust_emails';
    }
    protected function SingularName() {
	return 'clsCustEmail';
    }

    // -- CEMENTING -- //
    // ++ STATIC ++ //

    protected static function AuthURL($idEmail,$sToken) {
	throw new fcDebugException('Who is still calling this?');
	$sTokenHex = bin2hex($sToken);
	//$url = 'https://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'?auth='.$sTokenHex.'&e='.$idEmail;	// old format
	$url = 'https://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]."?auth=$idEmail:$sTokenHex";
	return $url;
    }
    /*----
      NOTE: using $_REQUEST instead of $_GET because the auth token is initially passed by GET
	(when it is in the emailed URL) but after that it is sent by POST (hidden element in
	forms). I can't see any security reason not to accept it as a cookie as well, although
	for now it's probably more secure not to *pass* it that way because of public computers.
	Restricting it to GET and POST means that when the user closes the window/tab, any
	incomplete login process is discontinued (unless someone thinks to reopen the tab).
    */
    protected static function ParseAuth() {
	$sAuth = $_REQUEST['auth'];

	$idEmail = strtok($sAuth, ':');	// string before ':' is email ID
	$sTokHex = strtok(':');			// string after ':' is token (in hexadecimal)
	//$sToken = hex2bin($sTokHex);		// requires PHP 5.4
	$sToken = pack("H*",$sTokHex);		// equivalent to hex2bin()
	$arOut = array(
	  'auth'	=> $sAuth,	// unparsed -- for forms
	  'email'	=> $idEmail,
	  'token'	=> $sToken
	  );
	return $arOut;
    }

    // -- STATIC -- //
    // ++ BOILERPLATE ++ //
/* 2016-10-31 this should be replaced by the app framework trait
    protected function App() {
	return $this->Engine()->App();
    }
*/
    // -- BOILERPLATE -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      USAGE needs to be documented
      HISTORY:
	2011-09-23 revised to use revised MakeFilt()
	2012-04-19 iCustID was not being set anywhere; this must have been unused
    */
    public function Find($iValue,$iCustID=NULL) {
	//$strSrch = strtolower($iName);
	//$objRows = $this->GetData('Email="'.$strSrch.'"');
	$sqlFilt = $this->MakeFilt_Cust($iValue,$iCustID);
	$objRows = $this->GetData($sqlFilt);
	return $objRows;
    }
    public function Find_forCust($iCustID) {
	$sql = 'ID_Cust='.$iCustID;
	$rs = $this->GetData($sql);
	return $rs;
    }
    /*----
      2012-04-19 reversed order of parameters to match Find()
	Allow idCust to be NULL.
    */
    protected function MakeFilt_Cust($iValue,$idCust=NULL) {
	$sqlEmail = SQLValue(strtolower($iValue));
	$sql = 'LOWER(Email)='.$sqlEmail;
	if (is_null($idCust)) {
	    return $sql;
	} else {
	    return "(ID_Cust=$idCust) AND ($sql)";
	}
    }
    /*----
      RETURNS: list of Customer IDs having the given email address
	NULL if none found
    */
    public function FindCusts_forAddr($iAddr) {
	$sql = 'SELECT ID_Cust FROM '.$this->NameSQL().' WHERE Email='.SQLValue($iAddr).' GROUP BY ID_Cust;';
	$rs = $this->Engine()->DataSet($sql);
	$arOut = NULL;
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$arOut[] = $rs->Value('ID_Cust');
	    }
	}
	return $arOut;
    }
    /*----
      ACTION: Generates base array for adding email address
	Customer ID is not yet known
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
    */ /*
    public function Add_SQL_base($iValue) {
	$ar = array(
	  'Email'	=> SQLValue($iValue),
	  'isActive'	=> 'TRUE'
	  );
	return $ar;
    } */

    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Make a new Email record from a user ID, contact ID, and submitted contact information
    */
    public function MakeUniqueRecord_fromContact($idUser,$idContact,vcCartData_NameAddress $oContact) {
	$rc = $this->SpawnRecordset();
	$rc->Load_fromContactObject($oContact);
	$rc->SetCustID($idContact);
	$rc->SaveUnique();
	return $rc;
    }
    /*----
      ACTIONS: assigns a user ID to all customer records having the given email address
      USAGE: called after a user authorizes a given email address
      NOTE: This could probably have been done with a compound statement --
	  UPDATE ID_User in (customers) WHERE ID IN (SELECT ID_Cust FROM (emails) WHERE Email=(iAddr))
	but tests indicated that this is very slow -- just a *select* statement takes several seconds to run.
	Maybe this indicates that something needs optimization... but doing it step-by-step does not seem
	particularly slow.
    */
    public function AssignUser_toAddr($iUser,$iAddr) {
	$arC = $this->FindCusts_forAddr($iAddr);
	if (is_null($arC)) {
	    // this shouldn't happen, because we only get here if ther was a match
	    throw new exception('Unexpected failure to find email address "'.$iAddr.'".');
	}

	// build filter for update
	$sqlIn = NULL;
	foreach ($arC as $id) {
	    if (!is_null($sqlIn)) {
		$sqlIn .= ',';
	    }
	    $sqlIn .= $id;
	}
	$sqlFilt = 'ID IN ('.$sqlIn.')';
	$arUpd = array('ID_User'=>$iUser);

	$tCusts = $this->Engine()->Custs();
	$tCusts->Update($arUpd,$sqlFilt);
	return $this->Engine()->RowsAffected();
    }
    /*----
      RETURNS: Script object for changes to make
      INPUT:
	$iCustID: ID of customer record being handled;
	  NULL = no existing customer, so always add
	$iValue: email address which needs to be associated with that customer
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
	2014-10-12 no longer using SQL scripting
    */ /*
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
	    $ar = $this->Add_SQL_base($iValue);
	    $ar['ID_Cust'] = SQLValue($iCustID);	// might be NULL
//	    $act = new Script_Status('DIFF: no match found for "'.$iValue.'"');
	    $act = new Script_Tbl_Insert($ar,$this);
	}
	$act->Name('email.data');
	return $act;
    } */
    /*----
      ACTION:
	* If the given customer ID already has an email matching the address in Value,
	  runs an Update using iArData.
	* If there is no match, runs an Insert using iArData.
      RETURNS: ID of email record found or created.
      USAGE needs to be explained; throwing an exception for now to see who is using it.
      HISTORY:
	2011-03-31 Was commented out; reinstated and renamed from Make() to Make_fromData()
    */
/*
    public function Make_fromData($iCustID,$iValue,array $iArData=NULL) {
	throw new exception('Need to document why this method is called.');

//	$sqlFind = SQLValue(strtolower($iValue));
//	$objRows = $this->GetData("(ID_Cust=$iCustID) AND (LOWER(Email)=$sqlFind)");

	if ($objRows->HasRows()) {
	    $objRows->NextRow();
	    $id = $objRows->ID;
	    if (is_array($iArData)) {
		$objRows->Update($iArData);
	    }
	} else {
	    $id = $this->Insert_fromData($iCustID,$iValue,$iArData);
	}
	return $id;
    }
*/
    /*----
      HISTORY:
	2011-03-31 Was commented out; reinstated and renamed from Create() to Insert_fromData()
	  Also marked as private instead of public.
	2016-07-24 Does not seem to be in use after rewrite. Commenting out again.
    */ /*
    private function Insert_fromData($idCustID,$iValue,array $arData=NULL) {
	$arIns = array(
	  'ID_Cust'	=> $idCustID,
	  'Email'	=> SQLValue($iValue),
	  'isActive'	=> 'TRUE'
	  );
	if (is_array($arData)) {
	    $arIns = array_merge($arIns,$arData);
	}

	$ok = $this->Insert($arIns);
	if ($ok) {
	    $id = $this->Engine()->NewID();
	} else {
	    $id = NULL;
	}
	return $id;
    } */

    // -- ACTIONS -- //

}
class vcrContactEmail extends vcBasicRecordset {
    use ftSaveableRecord;
}
class clsCustEmail extends vcrContactEmail {
    use ftUniqueRecords;

    // ++ CEMENTING ++ //

    protected function FingerprintFilter() {
	return 'Email='.$this->AddressSQL().' AND ID_Cust='.$this->GetCustID();
    }

    // -- CEMENTING -- //
    // ++ OVERRIDES ++ //

    protected function UpdateArray($ar=NULL) {
	$arUpd = parent::UpdateArray($ar);
	$arUpd['WhenUpd'] = 'NOW()';
	return $arUpd;
    }
    protected function InsertArray($ar=NULL) {
	$arIns = parent::InsertArray($ar);
	$arIns['WhenEnt'] = 'NOW()';
	return $arIns;
    }

    // -- OVERRIDES -- //
    // ++ FIELD VALUES ++ //

    public function SetCustID($id) {
	return $this->SetFieldValue('ID_Cust',$id);
    }
    public function GetCustID() {
	return $this->GetFieldValue('ID_Cust');
    }
    public function ShortDescr() {	// 2014-10-12 does anyone actually call this? Why?
	return $this->Value('Email');
    }
    // TODO: rename this pair ?etAddressString()
    public function SetAddress($s) {
	return $this->SetFieldValue('Email',$s);
    }
    public function GetAddress() {
	return $this->GetFieldValue('Email');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function AddressSQL() {
	return $this->GetConnection()->Sanitize_andQuote($this->GetAddress());
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    public function Load_fromContactObject(vcCartData_Contact $oContact) {
	$this->SetAddress($oContact->GetEmailFieldValue());
	$this->SetFieldValue('isActive',TRUE);
    }

    // -- ACTIONS -- //

}
