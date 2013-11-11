<?php
/*
  FILE: base.cust.php -- VbzCart customer classes
    These are neither used in the customer-facing store nor dependent on the admin environment.
    Maybe in the future they will be used for customer order management.
  TODO:
    Possibly there needs to be a new scripting element that can extract the ID from a newly-created
      record then use that as part of an SQL filter -- so that different customers can use
      the same email/phone/ccard without having a given record "stolen" from customer A
      (leaving them possibly without contact info, or only old records) when customer B
      uses the same info.
    Or maybe I just solved that problem... during the import process, the admin specifies in advance
      whether we're using an existing contact or creating a new one.
  HISTORY:
    2011-02-22 extracted from shop.php
*/
/*
class clsCustData {
    public function Custs() {
	return $this->Make('clsCusts');
    }
    public function Names() {
	return $this->Make('clsCustNames');
    }
    public function Addrs() {
	return $this->Make('clsCustAddrs');
    }
    public function Emails() {
	return $this->Make('clsCustEmails');
    }
    public function Phones() {
	return $this->Make('clsCustPhones');
    }
    public function CCards() {
	return $this->Make('clsCustCards_dyn');
    }
}
*/
/* ===================
 CUSTOMER INFO CLASSES
*/
class clsCusts extends clsTable_key_single {
    const TableName='core_custs';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsCust');
    }
    public function Recs_forUser($idUser) {
	$rs = $this->GetData('(ID_Repl IS NULL) AND (ID_User='.$idUser.')');
	return $rs;
    }
    /*----
      ACTION: Ensures that the given customer data is recorded.
	The following tables may be affected:
	  customers
	  customer names
	  customer addresses
      RETURNS: SQL script for Make_fromCartAddr()
      HISTORY:
	2011-10-08 finally figured out that I was INSERTing the customer record twice
	2011-11-18 fixed more bugs (did it work last time? cuz I don't see how it could have)
    */
/*
    public function Make_fromCartAddr_SQL(clsCartAddr $iAddrObj) {
	$arIns = array('WhenCreated' => 'NOW()');

	$actIns_Cust = new Script_Tbl_Insert($arIns,$this);
	$actIns_Cust->Name('cust.ins');	// name it so it can be retrieved
	// do this if that works:

	$objNames = $this->objDB->CustNames();
	$objAddrs = $this->objDB->CustAddrs();

	// get base arrays for creating name and address records
	$arNameCreate = $objNames->Create_SQL_init($iAddrObj->Name()->Value());
	$arAddrCreate = $objAddrs->Create_SQL_init($iAddrObj);

	// set up the sequence to create name and address & then update the customer
	$actCre_Name = new Script_Tbl_Insert($arNameCreate,$objNames);
	$actCre_Addr = new Script_Tbl_Insert($arAddrCreate,$objAddrs);

	global $sql;
	if (!is_object($actCre_Name)) {
	    echo '<b>INTERNAL ERROR</b>: script not created for customer name.';
	    echo '<br><b>SQL</b>: '.$sql;
	    throw new exception('Script not created.');
	}
	if (!is_object($actCre_Addr)) {
	    echo '<b>INTERNAL ERROR</b>: script not created for customer name.';
	    echo '<br><b>SQL</b>: '.$sql;
	    throw new exception('Script not created.');
	}

	// update newly-created Name and Addr records with ID of newly-created Customer record
	$actFill_ID_toName = new Script_SQL_Use_ID($actIns_Cust,$actCre_Name,'ID_Cust');	// source, destination, field name
	$actFill_ID_toAddr = new Script_SQL_Use_ID($actIns_Cust,$actCre_Addr,'ID_Cust');

	// update new Customer record with IDs from newly-created Name and Addr records
	$actUpd_Cust = new Script_Row_Update_fromInsert(array(),$actIns_Cust);
	$actFill_ID_frName = new Script_SQL_Use_ID($actCre_Name,$actUpd_Cust,'ID_Name');
	$actFill_ID_frAddr = new Script_SQL_Use_ID($actCre_Addr,$actUpd_Cust,'ID_Addr');

	// create script for conditional execution:
	$actMain = new Script_Script();
	  // placeholder for ID-copying actions - needed by caller:
	  //$actMain->Add(new Script_RowObj_scratch('cust.id.xfer'));	// or maybe not needed anymore?
	  // add cust ID to new Name data:
	  $actMain->Add($actFill_ID_toName,'init.cust.name');
	  // add cust ID to new Addr data:
	  $actMain->Add($actFill_ID_toAddr,'init.cust.addr');
	  // create name
	  $actMain->Add($actCre_Name,'cust.name');	// this name is significant
	  // create address
	  $actMain->Add($actCre_Addr,'cust.addr');	// this name is significant
	  // copy ID of new name back to customer update
	  $actMain->Add($actFill_ID_frName);
	  // copy ID of new addr back to customer update
	  $actMain->Add($actFill_ID_frAddr);
	  // do the customer update
	  $actMain->Add($actUpd_Cust);
	  $actMain->Add(new Script_Status('2011-10-08 This ^ was not updating ID_Name and ID_Addr.'));

	// try to create cust record ($actIns_Cust); if ok, then do everything else ($actMain)
	$actIf_ok = new Script_IF_Ok($actIns_Cust,$actMain);
	return $actIf_ok;	// and that's the script
    }
*/
    /*----
      ACTION: Creates a script object for adding or updating a customer record
      RETURNS: the script
      INPUT:
	$iID: ID of existing record to update, or NULL to create a new one
      HISTORY:
	2011-12-17 started
	2012-04-26 iID can be NULL (code already supports this)
    */
/* 2013-10-21 DEPRECATED -- this whole area needs rewriting
    public function Make_Script($iID=NULL) {
	if (is_null($iID)) {
	    $ar = array(
	      'WhenCreated'	=> 'NOW()',
	      );
	      $act = new Script_Tbl_Insert($ar,$this);
	} else {
	    $ar = array(
	      'WhenUpdated'	=> 'NOW()',
	      );
	    $rc = $this->GetItem($iID);
	    $act = new Script_Row_Update($ar,$rc);
	}
	return $act;
    }
*/
    /*-----
      RETURNS: object of class-singular type
      STATUS: DEPRECATED - use scripting instead
      PURPOSE: Create a new customer record, and the name record and address record associated with it
	The minimum number of updates seems to be:
	  create customer, create name, create addr, update customer (with name and addr) - 4 operations
	The alternative is:
	  create name, create addr, create customer, update name (with customer), update addr (with customer) - 5 operations
      HISTORY:
	2011-03-23 renamed - Make_fromObj() -> Make_fromCartAddr()
    */
/*
    public function Make_fromCartAddr(clsCartAddr $iAddrObj) {
	$strName = $iAddrObj->Name()->Value();

	$arIns = array('WhenCreated' => 'NOW()');
	$ok = $this->Insert($arIns);
// TO DO: log severe error if $ok is FALSE
	if ($ok) {
	    $idCust = $this->objDB->NewID();

	    $objNames = $this->objDB->CustNames();
	    $idName = $objNames->Create($idCust,$strName);
	    $objAddrs = $this->objDB->CustAddrs();
	    $idAddr = $objAddrs->Create($idCust,$iAddrObj);
	    
	    $arUpd = array(
	      'ID_Name'	=> $idName,
	      'ID_Addr'	=> $idAddr
	      );
	    $this->Update($arUpd,'ID='.$idCust);
	    return $this->GetItem($idCust);
	} else {
	    return NULL;
	}
    }
*/
    /*----
      ACTION:
	Create or find a customer address record from the given address object.
	Create or find a customer name record from the given address object.
	Create or find a customer record from the other records found or created.
      NOTES: This whole process desperately needs to be redesigned.
      HISTORY:
	2011-03-23 This was an incomplete attempt to rewrite the function from scratch
	  when I couldn't find the code after a function rename. It might actually work
	  better, if finished, but it's too much work for the time available now.
    */
/*
    public function Make_fromCartAddr(clsCartAddr $iAddr) {
	// address
	$objAddr = $this->Engine()->Addrs()->Make_fromCartAddr();
	$idAddr = $objAddr->KeyValue();

	// name
	$objName = $this->Engine()->Names()->Make_fromData($iAddr->Name());
	$idName = $objName->KeyValue();

	// customer
	$arMake = array(
	  'Name'	=> $idName,
	  'ID_Addr'	=> $idAddr
	  );
	$objOut = $this->Make($arMake);
	$idCust = $objOut->KeyValue();

	// check for data problems
	$idCustPtr = $objAddr->Value('ID_Cust');
	if (!empty($idCustPtr)) && ($idCustPtr != $idCust)) {
	    throw new exception 'Existing address record ID '.$idAddr.' points back to customer ID '.$idCustPtr.' instead of ID '.$idCust;
	}
	$idCustPtr = $objName->Value('ID_Cust');
	if (!empty($idCustPtr)) && ($idCustPtr != $idCust)) {
	    throw new exception 'Existing name record ID '.$idAddr.' points back to customer ID '.$idCustPtr.' instead of ID '.$idCust;
	}

	// point things back at the customer record
	$arUpd = array(
	  'ID_Cust'	=> $idCust
	  );

	// -- address record:
	$objAddr->Update($arUpd);
	// -- name record:
	$objName->Update($arUpd);

	return $objOut;
    }
*/
}
class clsCust extends clsRecs_key_single {
    /*----
      RETURNS: HTML for a drop-down list of all the customers
	in the current recordset
    */
    public function Render_DropDown($iName) {
	$out = "\n<select name=\"$iName\">";
	while ($this->NextRow()) {
	    $htRow = NULL;

	    $sTag = $this->Value('Title');
	    if (!is_null($sTag)) {
		$htRow .= htmlspecialchars($sTag).': ';
	    }

	    $oName = $this->NameObj();
	    $oAddr = $this->AddrObj();

	    $htRow .= $oName->ShortDescr();
	    if (is_null($oAddr)) {
		$htRow .= ' (no address)';
	    } else {
		$htRow .= ' - '.$oAddr->ShortDescr();
	    }

	    $id = $this->KeyValue();
	    $out .= "\n<option value=$id>$htRow</option>";
	}
	$out .= "\n</select>";
    }
    /*----
      RETURNS: recordset of aliases for this customer ID
      HISTORY:
	2012-01-09 created for admin page
    */
    protected function Aliases_rs() {
	$id = $this->KeyValue();
	$tbl = $this->Table();
	$rs = $tbl->GetData('ID_Repl='.$id,NULL,'ID');
	return $rs;
    }
    /*----
      RETURNS: array of orders for this customer
      FUTURE: also check ID_Buyer and ID_Recip
      HISTORY:
	2012-01-08 split off from AdminOrders(), moved from admin.cust to base.cust
    */
    protected function Orders_array() {
	$objTbl = $this->objDB->Orders();

	// collect names for this customer
	$tbl = $this->objDB->CustNames();
	$objRows = $tbl->GetData('ID_Cust='.$this->KeyValue());
	$sqlList = '';
	if ($objRows->HasRows()) {
	    while ($objRows->NextRow()) {
		if ($sqlList != '') {
		    $sqlList .= ',';
		}
		$sqlList .= $objRows->ID;
	    }

	    $arOrd = array();

	    $tbl = $this->Engine()->Orders();

	    // collect orders where customer is buyer
	    $objRows = $tbl->GetData('ID_NameBuyer IN ('.$sqlList.')');
	    while ($objRows->NextRow()) {
		$idOrd = $objRows->ID;
		$arRow = $objRows->Values();
		$arRow['roles'] = nz($arRow['roles']).'B';
		$arOrd[$idOrd] = $arRow;
	    }
	    
	    // collect orders where customer is recipient
	    $objRows = $tbl->GetData('ID_NameRecip IN ('.$sqlList.')');
	    while ($objRows->NextRow()) {
		$idOrd = $objRows->ID;
		if (array_key_exists($idOrd,$arOrd)) {
		    $arRow = $arOrd[$idOrd];
		} else {
		    $arRow = $objRows->Values();
		}
		$arRow['roles'] = nz($arRow['roles']).'R';
		$arOrd[$idOrd] = $arRow;
	    }

	    return $arOrd;
	} else {
	    return NULL;
	}
    }
    /*----
      RETURNS: recordset of Names for this Customer
      HISTORY:
	2012-01-08 split off from AdminNames
    */
    public function Names() {
	$tbl = $this->objDB->CustNames();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of only active Names for this Customer
      HISTORY:
	2013-11-09 Created for user-based checkout, but then not used.
    */
/*
    public function NamesActive() {
	return $this->Names('isActive');
    }
*/
    /*----
      RETURNS: recordset of default name for this customer
      HISTORY:
	2013-11-09 Created for user-based checkout.
    */
    public function NameObj() {
	$id = $this->Value('ID_Name');
	if (is_null($id)) {
	    $rc = NULL;
	} else {
	    $rc = $this->Engine()->CustNames($id);
	}
	return $rc;
    }
    /*----
      RETURNS: recordset of all Addresses for this Customer, optionally filtered
      HISTORY:
	2012-01-08 split off from AdminAddrs
	2013-11-08 Added optional $iFilt parameter.
	2013-11-09 Moved most of the code to clsCusts::Recs_forCust()
    */
    public function Addrs($iFilt=NULL) {
	$tbl = $this->objDB->CustAddrs();
	$id = $this->KeyValue();
	$rc = $tbl->Recs_forCust($id,$iFilt);
	return $rc;
    }
    /*----
      RETURNS: recordset of only active Addresses for this Customer
      HISTORY:
	2013-11-08 Created for user-based checkout.
    */
    public function AddrsActive() {
	return $this->Addrs('(WhenVoid IS NULL) AND NOT (WhenExp < NOW())');
    }
    /*----
      RETURNS: recordset of default address for this customer
	If ID_Addr is not set, returns first active record found.
	If there are no active records, returns NULL.
      HISTORY:
	2013-11-09 Added check for ID_Addr = NULL.
    */
    public function AddrObj() {
	$id = $this->Value('ID_Addr');
	if (is_null($id)) {
	    $rc = $this->AddrsActive();
	    if ($rc->RowCount == 0) {
		$rc = NULL;
	    } else {
		$rc->NextRow();		// load the first record
	    }
	} else {
	    $rc = $this->Engine()->CustAddrs($id);
	}
	return $rc;
    }
    /*----
      RETURNS: recordset of Emails for this Customer
      HISTORY:
	2012-01-08 split off from AdminEmails
    */
    public function Emails() {
	$tbl = $this->objDB->CustEmails();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of Phones for this Customer
      HISTORY:
	2012-01-08 split off from AdminPhones
    */
    public function Phones() {
	$tbl = $this->objDB->CustPhones();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of Cards for this Customer
      HISTORY:
	2012-01-08 split off from AdminCards
    */
    public function Cards() {
	$tbl = $this->objDB->CustCards();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }
}
// CUSTOMER FIELD TYPE (abstract)
class clsCustField extends clsDataSet {
}
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
    public function Create($iCustID, $iNameStr) {
/*	$strName = $iNameStr;
	$arIns = array(
	  'ID_Cust'	=> $iCustID,
	  'Name'	=> SQLValue($strName),
	  'NameSrch'	=> SQLValue(self::Searchable($strName)),
	  'isActive'	=> 'TRUE'
	  );
*/
	$arIns = $this-> Create_SQL($iCustID,$iNameStr);
	$ok = $this->Insert($arIns);
	return $this->objDB->NewID();
    }
    /*----
      ACTION: Generates the initial SQL to create the record just from the name string
	ID_Cust needs to be filled in before the SQL is executed.
    */
    public function Create_SQL_init($iNameStr) {
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
    public function Create_SQL($iCustID, $iNameStr) {
	$arIns = $this-> Create_SQL_init($iNameStr);
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
	$tbl = $this->objDB->CustAddrs();
	$sqlFilt = 'ID_Cust='.$idCust;
	if (!is_null($iFilt)) {
	    $sqlFilt = '('.$sqlFilt.') AND ('.$iFilt.')';
	}
	$rc = $tbl->GetData($sqlFilt);
	return $rc;
    }

}
class clsCustName extends clsDataSet {
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function ShortDescr() {
	return $this->Value('Name');
    }
}
// == CUSTOMER MAILING ADDRESS
class clsCustAddrs extends clsTable {
    // STATIC
    const TableName='cust_addrs';

    /*----
      RETURNS: full address in searchable form (variants reduced to abbreviation)
      INPUT: street address, city [, state] [, country] [, zipcode]
    */
    public static function Searchable($iRaw) {
	$xts = new xtString(strtolower($iRaw),TRUE);
	$arRep = array(
	  "street"	=> "st",
	  "road"	=> "rd",
	  "place"	=> "pl",
	  "drive"	=> "dr",
	  "avenue"	=> "ave",
	  "court"	=> "ct",
	  "boulevard"	=> "blvd",
	  "terrace"	=> "ter",
	  "lane"	=> "ln",
	  "circle"	=> "cir",
	  "north"	=> "n",
	  "south"	=> "s",
	  "east"	=> "e",
	  "west"	=> "w",
	  "apartment"	=> "apt",
	  "route"	=> "rt",
	  "usa"		=> "us",

// US states -- this should eventually be a separate array
// 	some of these should only be checked *before* stripping punctuation;
//	commenting those out for now
	  'alabama'	=> 'AL',
//	  'ala.'	=> 'AL',
	  'alaska'	=> 'AK',
//	  'alas.'	=> 'AK',
	  'arizona'	=> 'AZ',
//	  'ariz.'	=> 'AZ',
	  'arkansas'	=> 'AR',
//	  'ark.'	=> 'AR',
	  'california'	=> 'CA',
//	  'calif.'	=> 'CA',
	  'colorado'	=> 'CO',
//	  'colo.'	=> 'CO',
	  'connecticut'	=> 'CT',
//	  'conn.'	=> 'CT',
	  'delaware'	=> 'DE',
//	  'del.'	=> 'DE',
	  'district of columbia'	=> 'DC',
	  'florida'	=> 'FL',
//	  'fla.'	=> 'FL',
//	  'flor.'	=> 'FL',
	  'georgia'	=> 'GA',
	  'hawaii'	=> 'HI',
	  'idaho'	=> 'ID',
//	  'ida.'	=> 'ID',
	  'illinois'	=> 'IL',
//	  'ill.'	=> 'IL',
	  'indiana'	=> 'IN',
	  'iowa'	=> 'IA',
	  'kansas'	=> 'KS',
	  'kans.'	=> 'KS',
	  'kan.'	=> 'KS',
//	  'ka.'		=> 'KS',
	  'kentucky'	=> 'KY',
//	  'ken.'	=> 'KY',
//	  'kent.'	=> 'KY',
	  'louisiana'	=> 'LA',
	  'maine'	=> 'ME',
	  'maryland'	=> 'MD',
	  'massachusetts'	=> 'MA',
//	  'mass.'	=> 'MA',
	  'michigan'	=> 'MI',
//	  'mich.'	=> 'MI',
	  'minnesota'	=> 'MN',
//	  'minn.'	=> 'MN',
	  'mississippi'	=> 'MS',
//	  'miss.'	=> 'MS',
	  'missouri'	=> 'MO',
	  'montana'	=> 'MT',
//	  'mont.'	=> 'MT',
	  'nebraska'	=> 'NE',
//	  'nebr.'	=> 'NE',
//	  'neb.'	=> 'NE',
	  'nevada'	=> 'NV',
//	  'nev.'	=> 'NV',
	  'new hampshire'	=> 'NH',
	  'new jersey'	=> 'NJ',
	  'new mexico'	=> 'NM',
//	  'n. mex.'	=> 'NM',
	  'new york'	=> 'NY',
//	  'n. york'	=> 'NY',
	  'north carolina'	=> 'NC',
	  'north dakota'	=> 'ND',
	  'ohio'	=> 'OH',
	  'oklahoma'	=> 'OK',
//	  'okla.'	=> 'OK',
	  'oregon'	=> 'OR',
//	  'oreg.'	=> 'OR',
//	  'ore.'	=> 'OR',
	  'pennsylvania'	=> 'PA',
//	  'penn.'	=> 'PA',
//	  'penna.'	=> 'PA',
	  'rhode island'	=> 'RI',
	  'south carolina'	=> 'SC',
	  'south dakota'	=> 'SD',
	  'tennessee'	=> 'TN',
//	  'tenn.'	=> 'TN',
	  'texas'	=> 'TX',
//	  'tex. 	=> 'TX',
	  'utah'	=> 'UT',
	  'vermont'	=> 'VT',
	  'virginia'	=> 'VA',
//	  'virg.'	=> 'VA',
	  'washington'	=> 'WA',
	  'wash.'	=> 'WA',
	  'west virginia'	=> 'WV',
	  'wisconsin'	=> 'WI',
// 	  'wis.'	=> 'WI',
//	  'wisc.'	=> 'WI',
	  'wyoming'	=> 'WY',
//	  'wyo.'	=> 'WY',
	  'american samoa'	=> 'AS',
	  'guam'	=> 'GU',
	  'northern mariana islands'	=> 'MP',
	  'puerto Rico'	=> 'PR',
	  'virgin islands'	=> 'VI',
	  'marshall islands'	=> 'MH',
	  'palau'	=> 'PW'
	  );
	$xts->ReplaceList($arRep);
	$xts->DelAscRange(0,47);	// up to 0
	$xts->DelAscRange(58,96);	// after 9, to a
	$xts->DelAscRange(123,255);	// after z
	return $xts->Value;
    }
    public static function SearchableSQL($iRaw) {
	return SQLValue(self::Searchable($iRaw));
    }

    // DYNAMIC
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsCustAddr');
    }
    /*----
      ACTION: Generates the initial change array to create the record,
	but only fills in fields that can be determined just from the address object
	ID_Cust needs to be filled in before the SQL is executed.
      TODO: decouple this from clsCartAddr
    */
    public function Create_SQL_init(clsCartAddr $iAddrObj) {
	$arIns = array(
	  'Street'	=> SQLValue($iAddrObj->StreetStr()),
	  'Town'	=> SQLValue($iAddrObj->CityStr()),
	  'State'	=> SQLValue($iAddrObj->StateStr()),
	  'Zip'		=> SQLValue($iAddrObj->ZipStr()),
	  'Country'	=> SQLValue($iAddrObj->CountryStr()),
	  'Extra'	=> SQLValue($iAddrObj->InstrucStr()),
	  // calculated fields
	  'Full'	=> SQLValue($iAddrObj->AsText()),
	  'Search'	=> SQLValue($iAddrObj->AsSearchable())
	  );
	return $arIns;
    }
    /*----
      ACTION: Generates the complete change array to create the record
      USED BY: this->Make_Script(), this->Create()
      TODO: decouple this from clsCartAddr
    */
    public function Create_SQL($iCustID,clsCartAddr $iAddrObj) {
	$ar = $this->Create_SQL_init($iAddrObj);
	$ar['ID_Cust'] = $iCustID;
	$ar['WhenEnt'] = 'NOW()';
	return $ar;
    }
    /*----
      ACTION: Generates the complete change array to update the record
	This is sort of a formality, because it's the same as Create_SQL_init --
	i.e. everything but the customer ID... but this conceivably
	might change later, so better to encapsulate now.
      TODO: decouple this from clsCartAddr
    */
    public function Update_SQL(clsCartAddr $iAddrObj) {
	$ar = $this->Create_SQL_init($iAddrObj);
	$ar['WhenUpd'] = 'NOW()';
	return $ar;
    }
    /*----
      ACTION: creates a record with previously-initialized data
    */
    public function Create($iCustID,clsCartAddr $iAddrObj) {
/*	$arIns = array(
	  'ID_Cust'	=> $iCustID,
	  'Street'	=> SQLValue($iAddrObj->Street()->Value()),
	  'Town'	=> SQLValue($iAddrObj->City()->Value()),
	  'State'	=> SQLValue($iAddrObj->State()->Value()),
	  'Zip'		=> SQLValue($iAddrObj->Zip()->Value()),
	  'Country'	=> SQLValue($iAddrObj->Country()->Value()),
	  'Extra'	=> SQLValue($iAddrObj->Instruc()->Value())
	  );
*/
	$arIns = $this->Create_SQL($iCustID,$iAddrObj);

	$ok = $this->Insert($arIns);
	if ($ok) {
	    $id = $this->objDB->NewID();
	} else {
	    $id = NULL;
	}
	return $id;
    }
/*
    public function Make_fromCartAddr($iCustID,clsCartAddr $iAddr) {
	$sqlAddr = SQLValue($iAddr->AsSearchable());
	$objRows = $this->GetData("(ID_Cust=$iCustID) AND (Search=$sqlAddr)");
	if ($objRows->HasRows()) {
	    $objRows->NextRow();
	    $id = $objRows->ID;
	} else {
	    $id = $this->Create($iCustID,$iAddr);
	}
	return $id;
    }
*/
    /*----
      HISTORY:
	2012-04-24 This couldn't have been working -- iData index was wrong. Fixed.
	  Adding option to filter without ID_Cust, and another option to provide
	    data in Raw (non-stripped) form.
	  Making function public, to match declaration in abstract class.
    */
    public function MakeFilt(array $iData) {
	if (array_key_exists('Search',$iData)) {
	    $sqlAddr = SQLValue($iData['Search']);
	} else {
	    $sqlAddr = self::SearchableSQL($iData['SearchRaw']);
	}
	$sqlFilt = "Search=$sqlAddr";
	if (array_key_exists('ID_Cust',$iData)) {
	    $idCust = $iData['ID_Cust'];
	    return "(ID_Cust=$idCust) AND ($sqlFilt)";
	} else {
	    return $sqlFilt;
	}
    }
    /*-----
      INPUT: iAddr = address to look for
    */
    public function Find($iAddr,$iCust=NULL) {
	$strSrch = self::SearchableSQL($iAddr);	// replaces aliases and adds quotes
	$sql = 'Search='.$strSrch;
	if (!is_null($iCust)) {
	    $sql = '('.$sql.') AND (ID_Cust='.$iCust.')';
	}
//echo 'SQL=['.$sql.']';
	$objRows = $this->GetData($sql);
//echo ' ROWS=['.$objRows->RowCount().']';
	$objRows->NextRow();	// load the first row, which should be the only one
	return $objRows;
    }
    /*----
      LATER: if idCust is not known, can skip the searching
      NOTE: This is complicated because it also needs to update fields in the
	order record, but they may be different fields depending on which address
	this is (shipping or payment). What I ended up doing is adding the ability
	for the update/create script to be retrieved from outside (under the name
	"cust.addr.do"), so the applicable ID can be used as appropriate.
      INPUT:
	$iAddr: TreeNode-descended address object to import (from cart data)
	$idCust: ID of customer record to which this address belongs
	$actOrder: the script which is being used to update the order record
	  POSSIBLY THIS IS OBSOLETE? Previous description, from when this was an array:
	  We may need to update fields in the order record, but we don't know
	    which ones -- because that depends on the type of contact information
	    being imported. So we stash the result in a generic array which the
	    caller will plug into the appropriate fields.
      HISTORY:
	2011-09-23 written for script-based import process
    */
/* 2011-12-18 does anything call this?
    public function Make_Script(clsCartAddr $iAddr,$idCust,Script_RowObj $actOrder) {
	$acts = new Script_Script();
	
	$strKey = $iAddr->AsSearchable();
	$objAddr = $this->Find($strKey,$idCust);
	if ($objAddr->HasRows()) {
	    $objAddr->FirstRow();
	    $id = $objAddr->KeyValue();
	    $acts->Add(new Script_Status('SAME as existing address ID='.$id));
	    $arAct = $this->Update_SQL($iAddr);
	    $act = new Script_Row_Update($arAct,$objAddr);
	    $acts->Add($act,'cust.addr.do');	// name it so caller can find it
	    $actOrder->Name('addr',$objAddr->ID);	// using existing record
	} else {
	    $arAct = $this->Create_SQL($idCust,$iAddr);
	    $act = new Script_Tbl_Insert($arAct,$this);
	    $acts->Add($act,'cust.addr.do');	// name it so caller can find it
	    $acts->Add(new Script_SQL_Use_ID($act,$actOrder,'addr'));
	}
throw new exception('Path to here is...?');
	return $acts;
    }
*/
    public function Recs_forCust($idCust,$iFilt=NULL) {
	$tbl = $this->objDB->CustAddrs();
	$sqlFilt = 'ID_Cust='.$idCust;
	if (!is_null($iFilt)) {
	    $sqlFilt = '('.$sqlFilt.') AND ('.$iFilt.')';
	}
	$rc = $tbl->GetData($sqlFilt);
	return $rc;
    }
}
class clsCustAddr extends clsDataSet {
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function CustObj() {
	$idCust = $this->CustID();
	$rcCust = $this->Engine()->Custs($idCust);
	return $rcCust;
    }
    public function ShortDescr() {
	return $this->AsSingleLine();
    }
    public function AsString($iLineSep="\n") {
	$xts = new xtString($this->Street,TRUE);
	$xts->DelLead();	// delete any leading whitespace
	$xts->DelTail();	// delete any trailing whitespace
	if ($iLineSep != "\n") {
	    $xts->ReplaceSequence("\n",$iLineSep);	// replace any CR/LF sequences with ' / '
	}
	$xts->ReplaceSequence(chr(9).' ',' ');		// condense blank sequences into single blank

	$strStreet = $xts->Value;
	$strTown = $this->Town;
	$strState = $this->State;
	$strZip = $this->Zip;
	$strCountry = $this->Country;

	$out = $strStreet;
	if (!empty($out) && !empty($strTown)) {
	    $out .= $iLineSep.$strTown;
	}
	if (!empty($strState)) {
	    $out .= ', '.$strState;
	}
	if (!empty($strZip)) {
	    $out .= ' '.$strZip;
	}
	if (!empty($strCountry) && !empty($out)) {
	    $out .= $iLineSep.$strCountry;
	}
	return $out;
    }
    public function SearchString() {
	$xts = new xtString($this->AsString(''),TRUE);
	$xts->ReplaceSequence("\n ",'');	// remove all newlines and spaces
	$strSearch = strtolower($xts->Value);
	return $strSearch;
    }
    /*
      RETURNS: array of calculated values to update
      HISTORY:
	2013-11-07 $strSearch was being set as $strSeach; fixed.
    */
    protected function CalcUpdateArray() {
	$strSearch = $this->SearchString();
	$strFull = $this->AsString();
	$arUpd = array(
	  'Full'	=> $strFull,
	  'Search_raw'	=> $strFull,
	  'Search'	=> $strSearch
	  );
	return $arUpd;
    }
    /*-----
      ACTION: Update calculated fields
    */
    public function UpdateCalc() {
/*
	$xts = new xtString($this->AsString(''),TRUE);
	$xts->ReplaceSequence("\n ",'');	// remove all newlines and spaces
	$strSearch = strtolower($xts->Value);
	$strFull = $this->AsString();
	$sqlFull = SQLValue($strFull);
*/
	$arUpdRaw = $this->CalcUpdateArray();
	$sqlFull = SQLValue($arUpdRaw['Full']);
	$arUpd = array(
	  'Full'	=> $sqlFull,
	  'Search_raw'	=> $sqlFull,
	  'Search'	=> SQLValue($arUpdRaw['Search'])
	  );
	return $this->Update($arUpd);
    }
    /*-----
      RETURNS: Address formatted as single line
    */
    public function AsSingleLine() {
	return $this->AsString(' / ');
    }
}
// == CUSTOMER EMAIL ADDRESS
class clsCustEmails extends clsTable {

    // +STATIC

    protected static function AuthURL($idEmail,$sToken) {
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

    // -STATIC
    // +BOILERPLATE

    protected function App() {
	return $this->Engine()->App();
    }

    // -BOILERPLATE
    // +DYNAMIC

    const TableName='cust_emails';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsCustEmail');
    }
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
/* 2011-09-23 this version deprecated until we discover who is using it
    protected function MakeFilt(array $iData) {
	$idCust = $iData['ID_Cust'];
	$sqlEmail = SQLValue($iData['Email']);
	return "(ID_Cust=$idCust) AND (LOWER(Email)=$sqlEmail)";
    }
*/
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
//echo 'SQL: '.$tCusts->sqlExec;
	return $this->Engine()->RowsAffected();
    }
    /*----
      RETURNS: Script to add a new email address to a customer that hasn't been created yet
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
	2013-11-06 commenting this out because data-scripting is being removed
    */
/*
    public function Script_forAdd($iValue, Script_Tbl_Insert $iCustInsert_Script) {
	//$acts = new Script_Script();

	$ar = $this->Add_SQL_base($iValue);
	$ar['WhenEnt'] = 'NOW()';
	$actIns = new Script_Tbl_Insert($ar,$this);
	$acts->Add(new Script_SQL_Use_ID($iCustInsert_Script,$actIns,'ID_Cust'));
	$acts->Add($actIns,'cust.email.add');
	return $acts;
    }
*/
    /*----
      ACTION: Generates base array for adding email address
	Customer ID is not yet known
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
    */
    public function Add_SQL_base($iValue) {
	$ar = array(
	  'Email'	=> SQLValue($iValue),
	  'isActive'	=> 'TRUE'
	  );
	return $ar;
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
    public function Make_Script($iCustID,$iValue) {
	if (is_null($iCustID)) {
	    $isFnd = FALSE;
	} else {
	    $sqlFilt = $this->MakeFilt_Cust($iCustID,$iValue);
	    $objRows = $this->GetData($sqlFilt);
	    $isFnd = $objRows->HasRows();
	}
	if ($isFnd) {
/*
	    $cnt = $objRows->RowCount();
	    $objRows->FirstRow();
	    $id = $objRows->KeyValue();
	    $act = new Script_Status('SAME as '.$cnt.' existing email'.Pluralize($cnt).' - first: #'.$id.' '.$objRows->Value('Email'));
*/
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
    }
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
    */
    private function Insert_fromData($iCustID,$iValue,array $iArData=NULL) {
	$arIns = array(
	  'ID_Cust'	=> $iCustID,
	  'Email'	=> SQLValue($iValue),
	  'isActive'	=> 'TRUE'
	  );
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
class clsCustEmail extends clsDataSet {
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function ShortDescr() {
	return $this->Value('Email');
    }
}
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
class clsCustPhone extends clsDataSet {
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
/* =======
 CREDIT CARD UTILITY CLASS
*/
class clsCustCards extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cust_cards');
	  $this->KeyName('ID');
    }

    // STATIC section //

   public static function CardTypeChar($iNum) {
	$chDigit = substr($iNum,0,1);
	$arDigits = array(
	  '3' => 'A',
	  '4' => 'V',
	  '5' => 'M',
	  '6' => 'D'
	  );
	if (isset($arDigits[$chDigit])) {
	    $chOut = $arDigits[$chDigit];
	} else {
	    $chOut = '?'.$chDigit;
	}
	return $chOut;
    }
    public static function CardTypeName($iNum) {
	$chDigit = substr($iNum,0,1);
	$arDigits = array(
	  '3' => 'Amex',
	  '4' => 'Visa',
	  '5' => 'MasterCard',
	  '6' => 'Discover'
	  );
	if (isset($arDigits[$chDigit])) {
	    $out = $arDigits[$chDigit];
	} else {
	    $out = '?'.$chDigit;
	}
	return $out;
    }
    public static function SafeDescr_Short($iNum,$iExp) {
	//$dtExp = strtotime($this->CardExp);
	$dtExp = self::ExpDate($iExp);
	if (is_null($dtExp)) {
	    $strDate = '?/?';
	} else {
	    $strDate = $dtExp->format('n/y');
	}
	$out = self::CardTypeChar($iNum).'-'.substr($iNum,-4).'x'.$strDate;
	return $out;
    }
    public static function SafeDescr_Long($iNum,$iExp) {
	$dtExp = self::ExpDate($iExp);
	if (is_null($dtExp)) {
	    $strDate = '?/?';
	} else {
	    $strDate = $dtExp->format('F').' of '.$dtExp->format('Y').' ('.$dtExp->format('n/y').')';
	}
	$out = self::CardTypeName($iNum).' -'.substr($iNum,-4).' expires '.$strDate;
	return $out;
    }
    public static function Searchable($iRaw) {
	$xts = new xtString(strtolower($iRaw),TRUE);
	$xts->KeepOnly('0-9');	// keep only numerics
	return $xts->Value;
    }
    /*-----
      INPUT:
	iMaxFuture: if year is given as 2 digits, then this is the furthest in the future the year
	  is allowed to be (# of years from now). NOTE: Should be tested with current dates after 2050
	  (or between 1950 and 1999) to make sure it doesn't allow a year too far in the past.
      OUTPUT: EXP as a DateTime object
    */
    public static function ExpDate($iRaw,$iMaxFuture=50) {
	$strExp = $iRaw;
	// -- split into month/year or month/day/year
	$arExp = preg_split('/[\/.\- ]/',$strExp);
	$intParts = count($arExp);
	switch ($intParts) {
	  case 1:	// for now, we're going to assume MMYY[YY]
	    // TO DO: if people start typing in M with no leading zero, will have to check for even/odd # of chars
	    $intMo = substr($strExp,0,2);
	    $intYr = substr($strExp,2);
	    break;
	  case 2:	// month/year
	    $intMo = $arExp[0];
	    $intYr = $arExp[1];
	    break;
	  case 3:	// month/day/year or year/month/day
	    if (strlen($arExp[0]) > 3) {
	      $intYr = $arExp[0];
	      $intMo = $arExp[1];
	      $intDy = $arExp[2];
	    } else {
	      $intMo = $arExp[0];
	      $intDy = $arExp[1];
	      $intYr = $arExp[2];
	    }
	    break;
	  default:
	    // unknown format, can't do anything
	}
	// check for validity:
	$ok = FALSE;
	if (isset($intYr)) {
	    if ($intYr > 0) {
		if (($intMo > 0) && ($intMo < 13)) {
		    $ok = TRUE;
		}
	    }
	}
	if ($ok) {
	    if ($intYr < 100) {	// if year has no century, give it one
		$intYrNowPart = strftime('%y');
		$intCent = (int)substr(strftime('%Y'),0,2);
		if ($intYr < $intYrNowPart) {
		    $intCent++;
		}
		$intYr += ($intCent*100);
		$intYrNowFull = (int)strftime('%Y');
		if ($intYr - $intYrNowFull > $iMaxFuture) {
		    $intYr -= 100;
		}
	    }
	    if (!isset($intDy)) {
		$intDy = cal_days_in_month(CAL_GREGORIAN, $intMo, $intYr);	// set to last day of month
	    }
	    $dtOut = $datetime = new DateTime();
	    $dtOut->setDate($intYr, $intMo, $intDy);
	    return $dtOut;
	} else {
	    return NULL;	// if no year, then could not parse format
	}
    }
    public static function ExpDateSQL($iRaw) {
	$dt = self::ExpDate($iRaw);
	if (is_object($dt)) {
	    return '"'.$dt->format('Y-m-d').'"';
	} else {
	    return 'NULL';
	}
    }
}
// CUSTOMER CREDIT CARD
class clsCustCards_dyn extends clsCustCards {

    private $objCrypt;
    private $strCryptKey;

    public function __construct($iDB) {
	parent::__construct($iDB);
//	  $this->Name('cust_cards');
//	  $this->KeyName('ID');
	  $this->ClassSng('clsCustCard');
    }
    /*----
      ACTION: Looks for a matching card
	Filters by customer ID if it is given, otherwise checks all records.
      HISTORY:
	2011-12-18
	  removed unnecessary strtolower()
	  added optional $idCust parameter
	  calls MakeFilt() to generate the SQL filter
    */
    public function Find($iNum,$idCust=NULL) {
	$sqlFilt = $this->MakeFilt_val_strip($iNum,$idCust);
	$rc = $this->GetData($sqlFilt);
	return $rc;
    }
/* 2011-11-29 old object-specific version
    protected function MakeFilt_Cust($idCust,clsPayment $iData) {
	$sqlValue = SQLValue((string)self::Searchable($iData->Num()->Value()));	// strip out extraneous characters
	$sqlFilt = "(ID_Cust=$idCust) AND (CardNum=$sqlValue)";
	return $sqlFilt;
    }
*/
    /*----
      PURPOSE: Make the SQL filter to find matching records for a given customer and card number
      HISTORY:
	2011-11-29 de-coupled this from pre-processed cart data classes (clsPayment)
	2011-12-18
	  reversed order of params so idCust can be optional
	  renamed from MakeFilt_Cust() to MakeFilt_val_strip()
    */
    public function MakeFilt_val_strip($iNum,$idCust=NULL) {
	$strVal = (string)self::Searchable($iNum);	// strip out extraneous characters
	$sqlFilt = $this->MakeFilt_val($strVal,$idCust);
	return $sqlFilt;
    }
    /*----
      PURPOSE: Same as MakeFilt_val_strip, but assumes $iNum is already stripped of punctuation
    */
    protected function MakeFilt_val($iNum,$idCust=NULL) {
	$sqlFilt = '(CardNum='.SQLValue((string)$iNum).')';
	if (!is_null($idCust)) {
	    $sqlFilt .= " AND (ID_Cust=$idCust)";
	}
	return $sqlFilt;
    }
    /*----
      RETURNS: Script to add a new credit card to a customer that may or may not have been created
      ASSUMES:
	The detail scripts to make the customer, name, and address have already been created
      FUTURE: There needs to be some way to extract the ID from a newly-created customer record
	and use it as part of the filter.
      INPUT:
	$iData - credit card data array
	  'cont' (optional)	: contact ID
	  'num'			: credit card number, possibly with punctuation
	  'srch' (optional)	: credit card number distilled to searchable form (numbers only)
	  'exp' 		: expiration date as entered by user (mm/yy or mm/yyyy)
	  'addr' 		: billing address
	  'name' 		: cardholder's name as given on card
	$iCustInsert_Script - script that creates/updates the customer record
	$iAddrInsert_Script - script that creates/updates the address record
      HISTORY:
	2011-10-05 adapted from clsCustEmails
	2011-11-21 commented out until we can confirm that this is being used somewhere
	2011-12-18 it wasn't being used, but now is needed as part of the tree-based rewrite
	2011-12-18 is this a duplicate of functionality in admin.cart.php clsPayment_admin::Make_Script?
	2013-11-06 commenting this out because data-scripting is being removed
    */
/*
    public function Script_Make(
      $iData,
      Script_RowObj $iCustInsert_Script,
      Script_RowObj $iNameInsert_Script,
      Script_RowObj $iAddrInsert_Script
      ) {
	$acts = new Script_Script();

	$ar = $this->MakeArray_base($iData);
	$strCard = $iData['num'];
	$idCont = NzArray($iData,'cont');
	$strSrch = NzArray($iData,'srch');
	if (empty($strSrch)) {
	    $strNum = $iData['num'];
	    $sqlFilt = $this->MakeFilt_val_strip($strNum,$idCont);
	} else {
	    $sqlFilt = $this->MakeFilt_val($strSrch,$idCont);
	}
	$rc = $this->GetData($sqlFilt);
	if ($rc->HasRows()) {
	    $ar['WhenUpd'] = 'NOW()';
	    // is this actually needed?
	    //$rc->NextRow();	// advance to first row
	    $actRec = new Script_Row_Update($ar,$rc);
	} else {
	    $ar['WhenEnt'] = 'NOW()';
	    $actRec = new Script_Tbl_Insert($ar,$this);
	}

	$acts->Add(new Script_SQL_Use_ID($iCustInsert_Script,$actRec,'ID_Cust'));
	$acts->Add(new Script_SQL_Use_ID($iNameInsert_Script,$actRec,'ID_Name'));
	$acts->Add(new Script_SQL_Use_ID($iAddrInsert_Script,$actRec,'ID_Addr'));
	$acts->Add($actRec,'ccard.make');
	return $acts;
    }
*/
    /*----
      ACTION: Generates base array for adding/updating credit card
	Customer ID is not yet known
	Adding a card means we also need to add the card's address
      OUTPUT:
	$iData will have 'srch' after this is called
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
	2011-11-29 de-coupled this from pre-processed cart data classes (clsPayment)
    */
    public function MakeArray_base(array &$iData) {
	// get all the basic values
/*
	$sqlNum = SQLValue((string)self::Searchable($iData->Num()->Value()));	// strip out extraneous characters
	$sqlExp = $iData->ExpDateSQL();
	$strAddr = $iData->Addr()->AsText();
	$sqlAddr = SQLValue($strAddr);
	$sqlName = SQLValue($iData->Addr()->Name()->Value());
*/
	$strNum = $iData['num'];
	if (array_key_exists('srch',$iData)) {
	    $strSrch = $iData['srch'];
	} else {
	    $strSrch = (string)self::Searchable($strNum);
	}
	$sqlNum = SQLValue($strSrch);	// strip out extraneous characters
	$sqlExp = $this->ExpDateSQL($iData['exp']);
	$sqlAddr = SQLValue($iData['addr']);
	$sqlName = SQLValue($iData['name']);

	// create the array, filling in the stuff we do know:
	$ar = array(
	  //'ID_Cust'	=> $iCustID,	// not known yet
	  //'ID_Addr'	=> $idAddr,	// not created yet
	  'CardNum'	=> $sqlNum,
	  'CardExp'	=> $sqlExp,
	  'OwnerName'	=> $sqlName,
	  'Address'	=> $sqlAddr,
	  'isActive'	=> 'TRUE'
	  );
	return $ar;
    }
/* 2011-09-23 this probably isn't needed anymore
    protected function MakeFilt(array $iData) {
	$idCust = $iData['ID_Cust'];
	$sqlNum = SQLValue($iData['CardNum'] = self::Searchable($iData['CardNum']));
	return "(ID_Cust=$idCust) AND (CardNum=$sqlNum)";
    }
*/
/*
    public function Make($iCustID,clsPayment $iData) {
	$strNum = self::Searchable($iData->Num()->Value());	// strip out extraneous characters
	$sqlFind = SQLValue($strNum);		// mark up for use in SQL statement
	
	$objRows = $this->GetData("(ID_Cust=$iCustID) AND (CardNum=$sqlFind)");
	if ($objRows->HasRows()) {
	    $objRows->NextRow();
	    $id = $objRows->ID;
	} else {
	    $id = $this->Create($iCustID,$iData);
	}
	return $id;
    }
*/
    /*----
      HISTORY:
	2011-03-23 This function was commented out for unknown reasons, but it is used as part of the ugly ugly import process.
	2011-09-23 commenting it out again since I am rewriting the import process to remove maybe one "ugly"
    */
/*
    public function Create($iCustID,clsPayment $iData) {
	$sqlNum = SQLValue((string)self::Searchable($iData->Num()->Value()));	// strip out extraneous characters
	$sqlExp = $iData->ExpDateSQL();
	$strAddr = $iData->Addr()->AsText();
	$idAddr = (int)$this->objDB->CustAddrs()->Create($iCustID,$iData->Addr());
	$sqlAddr = SQLValue($strAddr);
	$sqlName = SQLValue($iData->Addr()->Name()->Value());

	$arIns = array(
	  'ID_Cust'	=> $iCustID,
	  'ID_Addr'	=> $idAddr,
	  'CardNum'	=> $sqlNum,
	  'CardExp'	=> $sqlExp,
	  'OwnerName'	=> $sqlName,
	  'Address'	=> $sqlAddr,
	  'isActive'	=> 'TRUE'
	  );
	$ok = $this->Insert($arIns);
	if ($ok) {
	    $id = $this->objDB->NewID();
	} else {
	    $id = NULL;
	}
	return $id;
    }
*/
    // - encryption methods
/*
    public function CryptKey($iKey) {
	$this->strCryptKey = $iKey;
	$this->Engine()->strCryptKey = $iKey;	// kluge
    }
*/
    /*----
      2013-05-15 moved code to Engine (clsDatabase in store.php)
    */
    public function CryptObj() {
	if (!isset($this->objCrypt)) {
	    $this->objCrypt = $this->Engine()->CryptObj();
/*
	    $this->objCrypt = new Cipher($this->strCryptKey);
	    $objVars = $this->objDB->VarsGlobal();
	    if ($objVars->Exists('crypt_seed')) {
		$strSeed = $objVars->Val('crypt_seed');
		$this->objCrypt->Seed($strSeed);
	    } else {
		$strSeed = $this->objCrypt->MakeSeed();
		$objVars->Val('crypt_seed',$strSeed);
	    }
	    $intOrdLast = $objVars->Val('ord_seq_prev');
*/
	}
	return $this->objCrypt;
    }
    public function CryptReset() {
	unset($this->objCrypt);
    }
}
class clsCustCard extends clsDataSet {
    public $_strPlain;	// data {to encode}/{decoded}

    public function CustObj() {
	$idCust = $this->ID_Cust;
	return $this->objDB->Custs()->GetItem($idCust);
    }
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    public function AddrObj() {
	$idAddr = $this->ID_Addr;
	return $this->objDB->CustAddrs()->GetItem($idAddr);
    }
    public function NameObj() {
	$idName = $this->ID_Name;
	return $this->objDB->CustNames()->GetItem($idName);
    }
    public function Reset() {
    // PURPOSE: Force object to reload the crypt key
	$this->Table->CryptReset();
    }
    public function ShortDescr() {
	return $this->SafeString();
    }
    public function ShortExp() {
	return date('n/y',$this->CardExp);
    }
    public function CryptObj() {
	return $this->Table->CryptObj();
    }
    public function SingleString() {
    // ACTION: Return plain card data as a single parseable string
	return self::PackCardData($this->CardNum,$this->CardCVV,$this->CardExp);
	//return ':'.$this->CardNum.':'.$this->CardCVV.':'.$this->CardExp;
    }
    /*----
      RETURNS: card number in a friendly format
    */
    public function FriendlyNum() {
	$out = $this->CardNum;
	$arChunks = str_split($out, 4);	// split number into chunks of 4 chars
	$out = implode('-',$arChunks);
	return $out;
    }
    public function ShortNumExpName() {
	return $this->SafeString().' '.$this->OwnerName;
    }
    public function CardTypeChar() {
	return clsCustCards::CardTypeChar($this->CardNum);
    }
    public function CardTypeName() {
	return clsCustCards::CardTypeName($this->CardNum);
    }
    public function SafeString() {
	return clsCustCards::SafeDescr_Short($this->CardNum,$this->CardExp);
    }
    /*
    NOTE: whatever separator is used, make sure it doesn't have any special meaning to regex
      the separator is mainly for human readability; because of the binary salt, we're
      going to actually treat this as a series of fixed-width fields.
    */
    protected static function PackCardData($iNum,$iCVV,$iExp) {
	$sData =
	  ':'.sprintf('%16s',$iNum)
	  .':'.sprintf('%4s',$iCVV)
	  .':'.$iExp;
	return $sData;
    }
    public function Encrypt($iDoSave,$iDoWipe) {
	if (is_null($this->CardNum)) {
	    // do nothing (do we want to raise an error?)
	    // this might happen if card data isn't completely decrypted after a migration or backup
	} else {
/*
	    $strRawData =
	      ':'.sprintf('%16s',$this->CardNum)
	      .':'.sprintf('%4s',$this->CardCVV)
	      .':'.$this->CardExp;	// .':'. bin2hex($sSalt);
*/
	    $strRawData = self::PackCardData($this->CardNum,$this->CardCVV,$this->CardExp);

	    // generate salt of same length
	    $nDataLen = strlen($strRawData);
	    $sSalt = openssl_random_pseudo_bytes($nDataLen);

	    $this->_strPlain = $strRawData.':'.$sSalt;
	    $strEncrypted = $this->CryptObj()->encrypt($strRawData);
	    if (strlen($strEncrypted) > 256) {
		throw new exception('Encrypted data length ('.strlen($strEncrypted).') exceeds storage field length.');
	    }
	    $this->Encrypted = $strEncrypted;

	    if ($iDoWipe) {
		$this->CardNum = NULL;
		$this->CardCVV = NULL;
		$this->CardExp = NULL;
	    }
	    if ($iDoSave) {
		$arUpd['Encrypted'] = SQLValue($strEncrypted);
		$arUpd['CardSalt'] =  SQLValue($sSalt);
		if ($iDoWipe) {
		    $arUpd['CardNum'] = 'NULL';
		    $arUpd['CardCVV'] = 'NULL';
		    $arUpd['CardExp'] = 'NULL';
		}
		$this->Update($arUpd);
//echo '<br>SQL='.$this->sqlExec;
	    }
	}
    }
    public function Decrypt($iDoSave,$iPvtKey) {
	$strEncrypted = $this->Encrypted;

	$sMsg = NULL;
	while ($sErr = openssl_error_string()) {
	    $sMsg .= '<br>PRIOR ERROR: '.$sErr;
	}
	$strRawData = $this->CryptObj()->decrypt($strEncrypted,$iPvtKey);

	while ($sErr = openssl_error_string()) {
	    $sMsg .= '<br>CURRENT ERROR: '.$sErr;
	}
	if (!is_null($sMsg)) {
	    echo $sMsg;
	    throw new exception('One or more errors were encountered during decryption.');
	}

	$this->_strPlain = $strRawData;

/* doesn't work with salt
	$chSep = substr($strRawData,0,1);
	$strToSplit = substr($strRawData,1);
*/
	$sNum = trim(substr($strRawData,1,16));
	$sCVV = trim(substr($strRawData,18,4));
	$sExp = trim(substr($strRawData,23,10));

	if (empty($sNum)) {
		$arUpd = array(
		  'CardNum' => 'NULL',
		  'CardCVV' => 'NULL',
		  'CardExp' => 'NULL',
		  'CardSafe' => 'NULL'
		  );
	} else {
/*
	    $arData = preg_split('/'.$chSep.'/',$strToSplit);

	    $sNum = $arData[0];
	    $sCVV = $arData[1];
	    $sExp = $arData[2];
*/
//echo "Num=[$sNum] CVV=[$sCVV] Exp=[$sExp]"; die();
	    $this->CardNum = ($sNum == '')?NULL:$sNum;
	    $this->CardCVV = ($sCVV == '')?NULL:$sCVV;
	    $this->CardExp = ($sExp == '')?NULL:$sExp;

	    if ($iDoSave) {
		$arUpd = array(
		  'CardNum' => SQLValue($this->CardNum),
		  'CardCVV' => SQLValue($this->CardCVV),
		  'CardExp' => SQLValue($this->CardExp),
		  'CardSafe' => SQLValue($this->SafeString())
		  );
	    }
	}
	if ($iDoSave) {
	    $this->Update($arUpd);
	}
    }
}
/*%%%%
  PURPOSE: cipher class that works with vbz internals
*/
class vbzCipher extends Cipher_pubkey {
    public function encrypt($input) {
	global $vgoDB;

	if (!$this->PubKey_isSet()) {
	    $fn = $vgoDB->VarsGlobal()->Val('public_key.fspec');
	    $fs =  KFP_KEYS.'/'.$fn;
	    $sKey = file_get_contents($fs);
	    $this->PubKey($sKey);
	}
	return parent::encrypt($input);
    }
}