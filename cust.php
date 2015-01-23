<?php
/*
  FILE: cust.php -- VbzCart customer classes
    These are neither used in the customer-facing store nor dependent on the admin environment.
    Maybe in the future they will be used for customer order management.
  HISTORY:
    2011-02-22 extracted from shop.php
    2014-09-23 Extracted all sub-data classes to cust-*.php files; renamed from base.cust.php to cust.php.
*/
/* ===================
 CUSTOMER INFO CLASSES
*/
class clsCusts extends clsTable_key_single {
    const TableName='core_custs';

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsCust');
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function NamesClass() {
	return 'clsCustNames';
    }
    protected function AddrsClass() {
	return 'clsCustAddrs';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function NameTable() {
	return $this->Engine()->Make($this->NamesClass());
    }
    protected function AddrTable() {
	return $this->Engine()->Make($this->AddrsClass());
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function Recs_forUser($idUser) {
	$rs = $this->GetData('(ID_Repl IS NULL) AND (ID_User='.$idUser.')');
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Creates records for customer, name, and address --
	everything needed to record a new customer.
    */
    public function CreateCustomer($idUser,$sName,clsPerson $oPerson) {
	$id = $this->CreateRecord($idUser);

	// create Name record
	$tNames = $this->NameTable();
	$idName = $tNames->CreateRecord($id,$sName);

	// create Address record
	$tAddrs = $this->AddrTable();
	$idAddr = $tAddrs->CreateRecord($id,$oPerson);

	if (!is_null($idName) && !is_null($idAddr)) {
	    $rcCust = $this->GetItem($id);
	    $rcCust->FinishRecord($idName,$idAddr);
	    return $id;
	} else {
	    return FALSE;
	}
    }
    public function CreateRecord($idUser) {
	$arUpd = array(
	  'ID_User'	=> $idUser,
	  'WhenCreated'	=> 'NOW()',
	  );
	$ok = $this->Insert($arUpd);
	if ($ok) {
	    return $this->Engine()->NewID();
	} else {
	    return NULL;
	}
    }

    // -- ACTIONS -- //

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
class clsCust extends clsVbzRecs {

    // ++ DATA FIELD ACCESS ++ //

    /*----
      PUBLIC so CustCards can access it during cart-to-order conversion
    */
    public function NameID() {
	return $this->Value('ID_Name');
    }
    /*----
      PUBLIC so CustCards can access it during cart-to-order conversion
    */
    public function AddrID() {
	return $this->Value('ID_Addr');
    }
    protected function HasAddr() {
	return (!is_null($this->AddrID()));
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA FIELD CALCULATIONS ++ //

    public function Address_text($sSep="\n") {
	if ($this->HasAddr()) {
	    $rcAddr = $this->AddrRecord();
	    return $rcAddr->AsString($sSep);
	} else {
	    return NULL;
	}
    }
    protected function SingleLine() {
	$rcName = $this->NameRecord();
	$rcAddr = $this->AddrRecord();
	$txt = $rcName->ShortDescr();
	if (empty($rcAddr)) {
	    $txt .= ' (no address, cust ID='.$this->KeyValue().')';
	} else {
	    $txt .= ' - '.$rcAddr->ShortDescr();
	}
	$ht = htmlspecialchars($txt);
	return $ht;
    }

    // -- DATA FIELD CALCULATIONS -- //
    // ++ DATA FIELD LOOKUP ++ //

    /*----
      ACTION: Gets the name from the default name record
    */
    public function NameString() {
	$rcName = $this->NameRecord();
	return $rcName->NamePlain();
    }
    /*----
      RETURNS: Text list of active email addresses for this contact
    */
    public function EmailsText($sSep='; ') {
	$rs = $this->EmailAddrRecords();
	$out = NULL;
	while ($rs->NextRow()) {
	    if (!is_null($out)) {
		$out .= $sSep;
	    }
	    $out .= $rs->Text();
	}
	return $out;
    }
    /*----
      RETURNS: Text list of active phone numbers for this contact
    */
    public function PhonesText($sSep='; ') {
	$rs = $this->PhoneNumberRecords();
	$out = NULL;
	while ($rs->NextRow()) {
	    if (!is_null($out)) {
		$out .= $sSep;
	    }
	    $out .= $rs->Text();
	}
	return $out;
    }

    // -- DATA FIELD LOOKUP -- //
    // ++ CLASS NAMES ++ //

    protected function MailAddrsClass() {
	return 'clsCustAddrs';
    }
    protected function EmailAddrsClass() {
	return 'clsCustEmails';
    }
    protected function PhonesClass() {
	return 'clsCustPhones';
    }
    protected function CardsClass() {
	return 'clsCustCards';
    }
    protected function CartsClass() {
	return 'clsShopCarts';
    }

    // -- CLASS NAMES ++ //
    // ++ DATA TABLE ACCESS ++ //

    protected function MailAddrTable($id=NULL) {
	return $this->Engine()->Make($this->MailAddrsClass(),$id);
    }
    protected function EmailAddrTable($id=NULL) {
	return $this->Engine()->Make($this->EmailAddrsClass(),$id);
    }
    protected function PhoneTable($id=NULL) {
	return $this->Engine()->Make($this->PhonesClass(),$id);
    }
    protected function CardTable($id=NULL) {
	return $this->Engine()->Make($this->CardsClass(),$id);
    }
    protected function CartTable($id=NULL) {
	return $this->Engine()->Make($this->CartsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    protected function CartRecords() {
	$tCarts = $this->CartTable();
	$rs = $tCarts->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
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
      RETURNS: recordset of Names for this Customer
      HISTORY:
	2012-01-08 split off from AdminNames
    */
    public function Names() {
	$tbl = $this->Engine()->CustNames();
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
    public function NameRecord() {
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
	throw new exception('Addrs() is deprecated; call AddrRecords().');
	$tbl = $this->MailAddrTable();
	$id = $this->KeyValue();
	$rc = $tbl->Recs_forCust($id,$iFilt);
	return $rc;
    }
    protected function AddrRecords($doVoided) {
	$tbl = $this->MailAddrTable();
	$id = $this->KeyValue();
	$sqlFilt = NULL;
	if (!$doVoided) {
	    $sqlFilt = 'WhenVoid IS NULL';
	}
	$rc = $tbl->Recs_forCust($id,$sqlFilt);
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
    public function AddrRecord() {
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
	throw new exception('Emails() is deprecated; call EmailRecords().');
	$tbl = $this->objDB->CustEmails();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: *active* email records for this customer
    */
    protected function EmailAddrRecords() {
	$tbl = $this->EmailAddrTable();
	$id = $this->KeyValue();
	$rs = $tbl->GetData("isActive AND (ID_Cust=$id)");
	return $rs;
    }
    /*----
      RETURNS: recordset of Phones for this Customer
      HISTORY:
	2012-01-08 split off from AdminPhones
    */
    public function Phones() {
	throw new exception('Phones() is deprecated; call PhoneNumebrRecords().');
    }
    protected function PhoneNumberRecords() {
	$tbl = $this->PhoneTable();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of Cards for this Customer
      HISTORY:
	2012-01-08 split off from AdminCards
    */
    public function Cards() {
	throw new exception('Cards() is deprecated; call CardRecords().');
    }
    public function CardRecords() {
	$tbl = $this->CardTable();
	$rs = $tbl->GetData('ID_Cust='.$this->KeyValue());
	return $rs;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ DATA RECORD ARRAYS ++ //


    // TODO: Each of these table-types needs a GetData_active() method, and we should be using that instead.
    /*----
      RETURNS: recordset of Names for all records in the current set
    */
    public function NameRecords_forRows() {
	$sqlIDs = $this->KeyListSQL();
	return $this->NameTable()->GetData("isActive AND (ID_Cust IN ($sqlIDs))");
    }
    public function AddrRecords_forRows() {
	$sqlIDs = $this->KeyListSQL();
	return $this->MailAddrTable()->GetData("(WhenVoid IS NULL) AND (NOW() <= IFNULL(WhenExp,NOW())) AND (ID_Cust IN ($sqlIDs))");
    }
    public function CardRecords_forRows() {
	$sqlIDs = $this->KeyListSQL();
	return $this->CardTable()->GetData("isActive AND (NOW() <= IFNULL(WhenInvalid,NOW())) AND (ID_Cust IN ($sqlIDs))");
    }

    public function AsArray($doNames,$doAddrs,$doCards) {
	$ar = NULL;
	while ($this->NextRow()) {
	    $idCust = $this->KeyValue();
	    $qRows = 0;

	    if ($doNames) {
		$rs = $this->Names();	// get names for this customer
		while ($rs->NextRow()) {
		    $qRows++;
		    $idName = $rs->KeyValue();
		    $sName = $rs->ShortDescr();
		    $ar[$idCust]['names'][$idName] = $sName;
		}
	    }

	    if ($doAddrs) {
		$rs = $this->AddrRecords(FALSE);	// get addresses for this customer
		while ($rs->NextRow()) {
		    $qRows++;
		    $idAddr = $rs->KeyValue();
		    $ht = htmlspecialchars($rs->AsSingleLine());
		    $ar[$idCust]['addrs'][$idAddr] = $ht;
		}
	    }

	    if ($doCards) {
		$rs = $this->CardRecords(FALSE);	// get addresses for this customer
		while ($rs->NextRow()) {
		    $qRows++;
		    $idRow = $rs->KeyValue();
		    $ht = htmlspecialchars($rs->AsSingleLine());
		    $ar[$idCust]['cards'][$idRow] = $ht;
		}
	    }
	    if ($qRows > 0) {
		$sCust = $this->SingleLine();
		$ar[$idCust]['cust'] = $sCust;
	    }
	}
	return $ar;
    }
    /*----
      RETURNS: array of orders for this customer
      FUTURE: also check ID_Buyer and ID_Recip
      HISTORY:
	2012-01-08 split off from AdminOrders(), moved from admin.cust to base.cust
    */
    protected function Orders_array() {
	$tOrd = $this->Engine()->Orders();
	$idCust = $this->KeyValue();
	$arRow = NULL;
	$arOrd = NULL;

	// collect orders where customer is buyer
	$rs = $tOrd->GetData('ID_Buyer='.$idCust);
	while ($rs->NextRow()) {
	    $idOrd = $rs->KeyValue();
	    $arRow = $rs->Values();
	    $arRow['roles'] = nz($arRow['roles']).'B';
	    $arOrd[$idOrd] = $arRow;
	}

	// collect orders where customer is recipient
	$rs = $tOrd->GetData('ID_Recip='.$idCust);
	while ($rs->NextRow()) {
	    $idOrd = $rs->KeyValue();
	    if (array_key_exists($idOrd,$arOrd)) {
		$arRow = $arOrd[$idOrd];
	    } else {
		$arRow = $rs->Values();
	    }
	    $arRow['roles'] = nz($arRow['roles']).'R';
	    $arOrd[$idOrd] = $arRow;
	}

	return $arOrd;

/* 2013-11-23 this is obsolete
	$objTbl = $this->Engine()->Orders();

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
*/
    }

    // -- DATA RECORD ARRAYS -- //
    // ++ ACTIONS ++ //

    /*----
      USED BY: Customers table
    */
    public function FinishRecord($idName,$idAddr) {
	$arUpd = array(
	  'ID_Name'	=> $idName,
	  'ID_Addr'	=> $idAddr,
	  'WhenCreated'	=> 'NOW()',
	  );
	$ok = $this->Update($arUpd);
    }

    // -- ACTIONS -- //
    // ++ WEB UI ++ //

    /*----
      RETURNS: HTML for a drop-down list of all Addresses belonging to
	any of the Customer records in the current set
    */
    public function Render_DropDown_Addrs($sName) {
	$out = NULL;
	$rs = $this->AddrRecords_forRows();
	if (is_null($rs) || ($rs->RowCount() == 0)) {
	    $out .= 'No addresses found.';	// should this actually ever happen?
	} else {
	    $out .= "\n<select name='$sName'>";
	    while ($rs->NextRow()) {
		  $id = $rs->KeyValue();
		  $sRow = $rs->AsString(' / ');
		  $ht = htmlspecialchars($sRow);
		  $out .= "\n<option value=$id>$ht</option>";
	    }
	    $out .= "\n</select>";
	}
	return $out;
    }
    /*----
      RETURNS: HTML for a drop-down list of all Cards belonging to
	any of the Customer records in the current set
    */
    public function Render_DropDown_Cards($sName) {
	$out = NULL;
	$rs = $this->CardRecords_forRows();
	if (is_null($rs) || ($rs->RowCount() == 0)) {
	    $out .= 'No cards found.';	// should this actually ever happen?
	} else {
	    $out .= "\n<select name='$sName'>";
	    while ($rs->NextRow()) {
		  $id = $rs->KeyValue();
		  $sRow = $rs->SafeDescr_Long();
		  $ht = htmlspecialchars($sRow);
		  $out .= "\n<option value=$id>$ht</option>";
	    }
	    $out .= "\n</select>";
	}
	return $out;
    }
    /*----
      RETURNS: HTML for a drop-down list of all the customers
	in the current recordset
      NOTE: This is somewhat DEPRECATED
    */
    public function Render_DropDown($iName,$doNames,$doAddrs,$doCards) {
	$ar = $this->AsArray($doNames,$doAddrs,$doCards);

	// output the results

	$out = NULL;

	if (is_null($ar) || (count($ar) == 0)) {
	    $out .= 'No entries found.';	// should this actually ever happen?
	} else {
	    $out .= "\n<select name=\"$iName\">";

	    foreach ($ar as $idCust => $arCust) {

		//$ht = escapeshellarg($arCust['cust']);
		if (array_key_exists('addrs',$arCust)) {
		    $ht = escapeshellarg("customer ID #$idCust");
		    $out .= "\n<optgroup label=$ht>";

		    $arAddr = $arCust['addrs'];
		    foreach ($arAddr as $idAddr => $sAddr) {
			$ht = htmlspecialchars($sAddr);
			$out .= "\n<option value=$idAddr>$ht</option>";
		    }

		    $out .= "\n</optgroup>";
		}

		if (array_key_exists('cards',$arCust)) {
		    // TODO: finish
		}
	    }

	    $out .= "\n</select>";
	}

	return $out;
    }
}
