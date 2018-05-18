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
class vctCusts extends vcShopTable {

    // ++ CEMENTING ++ //

    protected function TableName() {
	return 'core_custs';
    }
    protected function SingularName() {
	return 'vcrCust';
    }
    
    // -- CEMENTING -- //
    // ++ CLASS NAMES ++ //

    protected function NamesClass() {
	return 'vctCustNames';
    }
    protected function CardsClass() {
	return 'vctCustCards_dyn';
    }
    protected function MailAddrsClass() {
	return 'vctMailAddrs';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function NameTable() {
	return $this->Engine()->Make($this->NamesClass());
    }
    protected function AddrTable() {
	return $this->Engine()->Make($this->MailAddrsClass());
    }
    protected function CardTable() {
	return $this->Engine()->Make($this->CardsClass());
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function Recs_forUser($idUser) {
	return $this->SelectRecords('(ID_Repl IS NULL) AND (ID_User='.$idUser.')');
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Given a name-address contact object, creates a new Cust record and any dependent records.
	This does all the bits that are the same for both Buyer and Recip.
      2016-06-05 This is probably OBSOLETE
    *//*
    protected function CreateRecords_fromContact($idUser,vcCartData_NameAddress $oContact) {
	$id = $this->CreateRecord($idUser);
	if ($id === FALSE) {
	    // TODO: more friendly error message? Or maybe this really just shouldn't happen.
	    throw new exception("VbzCart bug: Could not create blank contact record for user ID=$idUser.");
	} else {

	    // create Name record (2016-05-30 This table is beginning to seem unnecessary. Just save the name in the Address table.)
	    $sName = $oContact->GetNameFieldValue();
	    $tNames = $this->NameTable();
	    $idName = $tNames->CreateRecord($id,$sName);
	    
	}
	return $id;
    }//*/
    /*----
      ACTION: Given a Buyer object, create a new Cust record and any dependent records.
      CALLER must determine if a new Cust is actually needed.
      NOTE: This plus CreateRecord_fromRecip() replace CreateCustomer().
      2016-06-05 This is probably OBSOLETE
    *//*
    public function CreateRecords_fromBuyer($idUser, vcCartData_Buyer $oBuyer) {
	$id = $this->CreateRecords_fromContact($idUser,$oBuyer);

	// create BUYER Address record
	$tAddrs = $this->AddrTable();
	$rcAddr = $tAddrs->CreateRecord_fromBuyer($id,$oBuyer);

	// create Ccard record
	$tCard = $this->CardTable();
	$idBuyerCard	= $tCard->CreateRecord($id,$rcAddr,$oBuyer);
	if ($idBuyerCard === FALSE) {
	    throw new exception('Could not create Card record for buyer ID='.$id);
	}
	return $id;
    }//*/
    /*----
      ACTION: Given a Recip object, create a new Cust record and any dependent records.
      CALLER must determine if a new Cust is actually needed.
      NOTES:
	* This plus CreateRecord_fromBuyer() replace CreateCustomer().
	* (2016-05-29) We're not going to add shipping instructions to the message record.
	  They should be considered part of the address only. There should probably be a
	  separate field for entering order-specific instructions. (TODO)
      2016-06-05 This is probably OBSOLETE
    */ /*
    public function CreateRecords_fromRecip($idUser, vcCartData_Recip $oRecip) {
	$id = $this->CreateRecords_fromContact($idUser,$oRecip);

	// create RECIP Address record
	$tAddrs = $this->AddrTable();
	$idAddr = $tAddrs->CreateRecord_fromRecip($id,$oRecip);
	
	return $id;
    }//*/
    /*----
      ACTION: Creates records for customer, name, and address --
	everything needed to record a new customer.
      2016-05-22 Commenting this out because parent has a method by the same name. If descendant is needed, document why.
      2016-05-27 Okay, it's needed, but now I've forgotten why. >.<
    *//*
    public function CreateCustomer($idUser, vcCartData_NameAddress $oBuyer) {
    throw new exception('Who is calling this? Do they call once each for Buyer and Recip?');
	$id = $this->CreateRecord($idUser);

	// create Name record
	$sName = $oBuyer->GetNameFieldValue();
	$tNames = $this->NameTable();
	$idName = $tNames->CreateRecord($id,$sName);
	
	// create Address record
	$tAddrs = $this->AddrTable();
	$idAddr = $tAddrs->CreateRecord($id,$oBuyer);	// TODO: call function specific to buyer or recip
	
	if (!is_null($idName) && !is_null($idAddr)) {
	    $rcCust = $this->GetItem($id);
	    $rcCust->FinishRecord($idName,$idAddr);
	    return $id;
	} else {
	    return FALSE;
	}
    }//*/
    /* 2016-05-21 old version
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
    }*/
    public function CreateRecord($idUser) {
	$arUpd = array(
	  'ID_User'	=> $idUser,
	  'WhenCreated'	=> 'NOW()',
	  );
	$ok = $this->Insert($arUpd);
	if ($ok) {
	    return $this->GetConnection()->CreatedID();
	} else {
	    return NULL;
	}
    }

    // -- ACTIONS -- //

}
class vcrContact_trait extends vcShopRecordset {
    use ftSaveableRecord;
}
class vcrCust extends vcrContact_trait {

    // ++ OVERRIDES ++ //

    protected function UpdateArray($ar=NULL) {
	$ar = parent::UpdateArray($ar);
	$ar['WhenUpdated'] = 'NOW()';
	return $ar;
    }
    protected function InsertArray($ar=NULL) {
	$ar = parent::InsertArray($ar);
	$ar['WhenCreated'] = 'NOW()';
	return $ar;
    }

    // -- OVERRIDES -- //
    // ++ CLASSES ++ //

    protected function NamesClass() {
	return 'vctCustNames';
    }
    protected function MailAddrsClass() {
	return 'vctMailAddrs';
    }
    protected function EmailAddrsClass() {
	return 'vctCustEmails';
    }
    protected function PhonesClass() {
	return 'vctCustPhones';
    }
    protected function CardsClass() {
	return 'vctCustCards';
    }
    protected function CartsClass() {
	return 'vctShopCarts';
    }

    // -- CLASSES ++ //
    // ++ TABLES ++ //

    protected function NameTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->NamesClass(),$id);
    }
    protected function MailAddrTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->MailAddrsClass(),$id);
    }
    protected function EmailAddrTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->EmailAddrsClass(),$id);
    }
    protected function PhoneTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->PhonesClass(),$id);
    }
    protected function CardTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CardsClass(),$id);
    }
    protected function CartTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CartsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    protected function CartRecords() {
	$tCarts = $this->CartTable();
	$rs = $tCarts->GetData('ID_Cust='.$this->GetKeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of aliases for this customer ID
      HISTORY:
	2012-01-09 created for admin page
    */
    protected function AliasRecords() {
	$id = $this->GetKeyValue();
	$tbl = $this->GetTableWrapper();
	$rs = $tbl->SelectRecords('ID_Repl='.$id,'ID');
	return $rs;
    }

    /*----
      RETURNS: recordset of Names for this Customer
      HISTORY:
	2012-01-08 split off from AdminNames
	2016-06-11 renamed from Names() to NameRecords().
    */
    public function Names() {
	throw new exception('Names() is deprecated; call NameRecords().');
    }
    public function NameRecords() {
	$tbl = $this->NameTable();
	$rs = $tbl->GetData('ID_Cust='.$this->GetKeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of default name for this customer
      HISTORY:
	2013-11-09 Created for user-based checkout.
    */
    public function NameRecord() {
	$id = $this->GetNameID();
	if (empty($id)) {
	    $rc = NULL;
	} else {
	    $rc = $this->NameTable($id);
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
	$id = $this->GetKeyValue();
	$rc = $tbl->Recs_forCust($id,$iFilt);
	return $rc;
    }
    protected function AddrRecords($doVoided,$sqlFilt=NULL) {
	$tbl = $this->MailAddrTable();
	$id = $this->GetKeyValue();
	$oFilt = new fcSQLt_Filt('AND');
	if (!$doVoided) {
	    $oFilt->AddCond('WhenVoid IS NULL');
	}
	$oFilt->AddCond($sqlFilt);
	$sqlFilt = $oFilt->RenderValue();
	$rc = $tbl->Recs_forCust($id,$sqlFilt);
	return $rc;
    }
    /*----
      RETURNS: recordset of only active Addresses for this Customer
      HISTORY:
	2013-11-08 Created for user-based checkout.
    */
    public function AddrsActive() {
	return $this->AddrRecords('NOT (WhenExp < NOW())');
    }
    /*----
      RETURNS: recordset of default address for this customer
	If ID_Addr is not set, returns first active record found.
	If there are no active records, returns NULL.
      HISTORY:
	2013-11-09 Added check for ID_Addr = NULL.
    */
    public function AddrRecord() {
	$id = $this->GetFieldValue('ID_Addr');
	if (is_null($id)) {
	    $rc = $this->AddrsActive();
	    if ($rc->HasRows()) {
		$rc->NextRow();		// load the first record
	    } else {
		$rc = NULL;
	    }
	} else {
	    $rc = $this->MailAddrTable($id);
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
	$rs = $tbl->GetData('ID_Cust='.$this->GetKeyValue());
	return $rs;
    }
    /*----
      RETURNS: *active* email records for this customer
    */
    protected function EmailAddrRecords() {
	$tbl = $this->EmailAddrTable();
	$id = $this->GetKeyValue();
	$rs = $tbl->SelectRecords("isActive AND (ID_Cust=$id)");
	return $rs;
    }
    protected function PhoneNumberRecords() {
	$tbl = $this->PhoneTable();
	$rs = $tbl->SelectRecords('ID_Cust='.$this->GetKeyValue());
	return $rs;
    }
    public function CardRecords() {
	$tbl = $this->CardTable();
	$rs = $tbl->SelectRecords('ID_Cust='.$this->GetKeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of Names for all records in the current set
    */
    public function NameRecords_forRows() {
	$sqlIDs = $this->FetchKeyValues_asSQL();
	return $this->NameTable()->SelectRecords("isActive AND (ID_Cust IN ($sqlIDs))");
    }
    /*----
      RETURNS: recordset of mailing addresses for all records in the current set
    */
    public function AddrRecords_forRows() {
	$sqlIDs = $this->FetchKeyValues_asSQL();
	return $this->MailAddrTable()->SelectRecords("(WhenVoid IS NULL) AND (NOW() <= IFNULL(WhenExp,NOW())) AND (ID_Cust IN ($sqlIDs))");
    }
    /*----
      RETURNS: recordset of payment cards for all records in the current set
    */
    public function CardRecords_forRows() {
	$sqlIDs = $this->FetchKeyValues_asSQL();
	return $this->CardTable()->SelectRecords("isActive AND (NOW() <= IFNULL(WhenInvalid,NOW())) AND (ID_Cust IN ($sqlIDs))");
    }

    // -- RECORDS -- //
    // ++ RECORD ARRAYS ++ //

    public function AsArray($doNames,$doAddrs,$doCards) {
	$ar = NULL;
	while ($this->NextRow()) {
	    $idCust = $this->GetKeyValue();
	    $qRows = 0;

	    if ($doNames) {
		$rs = $this->NameRecords();	// get names for this customer
		while ($rs->NextRow()) {
		    $qRows++;
		    $idName = $rs->GetKeyValue();
		    $sName = $rs->ShortDescr();
		    $ar[$idCust]['names'][$idName] = $sName;
		}
	    }

	    if ($doAddrs) {
		$rs = $this->AddrRecords(FALSE);	// get addresses for this customer
		while ($rs->NextRow()) {
		    $qRows++;
		    $idAddr = $rs->GetKeyValue();
		    $ht = fcString::EncodeForHTML($rs->AsSingleLine());
		    $ar[$idCust]['addrs'][$idAddr] = $ht;
		}
	    }

	    if ($doCards) {
		$rs = $this->CardRecords(FALSE);	// get addresses for this customer
		while ($rs->NextRow()) {
		    $qRows++;
		    $idRow = $rs->GetKeyValue();
		    $ht = fcString::EncodeForHTML($rs->AsSingleLine());
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
	$idCust = $this->GetKeyValue();
	$arRow = NULL;
	$arOrd = NULL;

	// collect orders where customer is BUYER (B)
	$rs = $tOrd->GetData('ID_Buyer='.$idCust);
	while ($rs->NextRow()) {
	    $idOrd = $rs->GetKeyValue();
	    $arRow = $rs->Values();
	    fcArray::NzAppend($arRow,'roles','B');	// append "B" to the Roles list
	    $arOrd[$idOrd] = $arRow;
	}

	// collect orders where customer is RECIPIENT (R)
	$rs = $tOrd->GetData('ID_Recip='.$idCust);
	while ($rs->NextRow()) {
	    $idOrd = $rs->GetKeyValue();
	    if (fcArray::Exists($arOrd,$idOrd)) {
		$arRow = $arOrd[$idOrd];
	    } else {
		$arRow = $rs->Values();
	    }
	    fcArray::NzAppend($arRow,'roles','R');	// append "R" to the Roles list
	    $arOrd[$idOrd] = $arRow;
	}

	return $arOrd;

    }

    // -- RECORD ARRAYS -- //
    // ++ FIELD VALUES ++ //

    /*----
      PUBLIC so CustCarts can access it during cart-to-order conversion
    */
    public function SetNameID($id) {
	return $this->SetFieldValue('ID_Name',$id);
    }
    /*----
      PUBLIC so CustCart can access it during cart-to-order conversion
    */
    public function GetNameID() {
	return $this->GetFieldValue('ID_Name');
    }
    /*----
      PUBLIC so CustCards can access it during cart-to-order conversion
    */
    public function GetAddrID() {
	return $this->GetFieldValue('ID_Addr');
    }
    protected function HasAddr() {
	return (!is_null($this->GetAddrID()));
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

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
	    $txt .= ' (no address, cust ID='.$this->GetKeyValue().')';
	} else {
	    $txt .= ' - '.$rcAddr->ShortDescr();
	}
	$ht = fcString::EncodeForHTML($txt);
	return $ht;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ FIELD LOOKUP ++ //

    /*----
      ACTION: Gets the name from the default name record
    *//* 2016-06-26 old version
    public function NameString() {
	$rcName = $this->NameRecord();
	if (is_null($rcName)) {
	    return KS_DESCR_IS_NULL;
	} else {
	    return $rcName->NamePlain();
	}
    }*/
    public function NameString() {
	$rc = $this->NameRecord();
	if (is_object($rc)) {
	    $txt = $rc->NameString();
	    return empty($txt)?KS_DESCR_IS_BLANK:$txt;
	} else {
	    return KS_DESCR_IS_NULL;
	}
    }
    /*----
      RETURNS: Text list of active email addresses for this contact
    */
    public function EmailsText($sSep='; ') {
	$rs = $this->EmailAddrRecords();
	$out = NULL;
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		if (!is_null($out)) {
		    $out .= $sSep;
		}
		$out .= $rs->GetAddress();
	    }
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
	    $out .= $rs->GetDigitsString();
	}
	return $out;
    }

    // -- FIELD LOOKUP -- //
    // ++ DB WRITE ++ //

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

    // -- DB WRITE -- //
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
		  $id = $rs->GetKeyValue();
		  $sRow = $rs->AsString(' / ');
		  $ht = fcString::EncodeForHTML($sRow);
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
		  $id = $rs->GetKeyValue();
		  $sRow = $rs->SafeDescr_Long();
		  $ht = fcString::EncodeForHTML($sRow);
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
			$ht = fcString::EncodeForHTML($sAddr);
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
