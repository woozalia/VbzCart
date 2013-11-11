<?php
/*
  PURPOSE: handles shop_cart_data table
  HISTORY:
    2012-04-17 extracting from shop.php
    2012-05-14 moved KSI_ constants here from admin.cart.php
      ...and KSF_ constants from cart OLD
    2013-09-13 extracted constants to cart-const.php
*/

require_once('vbz-const-cart.php');
require_once('config-admin.php');

class clsCartVars extends clsTable_indexed {

    protected $idCart;	// ID of cart currently loaded
    protected $arData;
    protected $arChg;	// list of fields changed from what is in db
    protected $arInput;	// user data retrieved from form
    protected $objCust, $objShip, $objPay;

    protected static $arFormIdxs = array(
      KI_SHIP_ZONE	=> KSF_SHIP_ZONE,

      KSI_SHIP_IS_CARD	=> KSF_SHIP_IS_CARD,
      KI_RECIP_IS_BUYER	=> KSF_RECIP_IS_BUYER,
      KI_RECIP_MESSAGE	=> KSF_SHIP_MESSAGE,
      KI_RECIP_NAME	=> KSF_RECIP_NAME,
      KI_RECIP_STREET	=> KSF_RECIP_STREET,
      KI_RECIP_CITY	=> KSF_RECIP_CITY,
      KI_RECIP_STATE	=> KSF_RECIP_STATE,
      KI_RECIP_ZIP	=> KSF_RECIP_ZIP,
      KI_RECIP_COUNTRY	=> KSF_RECIP_COUNTRY,
      KI_RECIP_EMAIL	=> KSF_RECIP_EMAIL,
      KI_RECIP_PHONE	=> KSF_RECIP_PHONE,

      // -- payment
      KI_PAY_CARD_NUM		=> KSF_PAY_CARD_NUM,
      KI_PAY_CARD_EXP		=> KSF_PAY_CARD_EXP,
      KI_PAY_CARD_NAME		=> KSF_PAY_CARD_NAME,
      KI_PAY_CARD_STREET	=> KSF_PAY_CARD_STREET,
      KI_PAY_CARD_CITY		=> KSF_PAY_CARD_CITY,
      KI_PAY_CARD_STATE		=> KSF_PAY_CARD_STATE,
      KI_PAY_CARD_ZIP		=> KSF_PAY_CARD_ZIP,
      KI_PAY_CARD_COUNTRY	=> KSF_PAY_CARD_COUNTRY,
      KI_PAY_CHECK_NUM		=> KSF_PAY_CHECK_NUM,
      KI_BUYER_EMAIL		=> KSF_BUYER_EMAIL,
      KI_BUYER_PHONE		=> KSF_BUYER_PHONE,
      );

    public function __construct($iDB) {
	$objIdx = new clsIndexer_Table_multi_key($this);
	$objIdx->KeyNames(array('ID_Cart','Type'));

	$this->objCust = NULL;
	$this->objShip = NULL;
	$this->objPay = NULL;
	$this->arChg = NULL;
	$this->idCart = NULL;

	parent::__construct($iDB);
	  $this->Name('shop_cart_data');
//	  $this->KeyName('ID');
	  $this->ClassSng('clsCartVar');
//	  $this->ActionKey('cart');
	  $this->Indexer($objIdx);
    }
    public function IsLoaded() {
	return !is_null($this->arData);
    }
    /*----
      ACTION: Loads data for the given cart
    */
    public function LoadCart($iCart) {
	$sql = 'SELECT Type, Val FROM '.$this->Name().' WHERE ID_Cart='.$iCart;
	$rs = $this->DataSQL($sql);
	$this->arData = array();	// so we know it has been loaded, even if empty
	$this->idCart = $iCart;
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$row = $rs->Values();
		$idx = $row['Type'];
		$ar[$idx] = $row['Val'];
		unset($this->arChg[$idx]);
	    }
	    $this->arData = $ar;
	} else {
	    // This happens when we first enter the checkout; it's not an error condition.
//	    throw new exception('Cart ID=['.$iCart.'] has no data! SQL='.$sql);
	}
    }
    /*----
      ACTION: Save all modified data
      NOTE: The data is presumably already loaded, in order to compare new and old values
	in order to determine which ones need updating/adding (stored in $arChg),
	so we *also* need to update the loaded data as we are saving it. (2013-04-03)
	There is almost certainly a better way to do this, e.g. if arData[idx] is set,
	  then we should be able to assume the row exists, and not look it up.
	  This will need to be tested carefully, however. TODO
    */
    public function SaveCart() {
	if (is_array($this->arChg)) {
	    $idCart = $this->idCart;
	    if (is_null($idCart)) {
		throw new exception('Attempting to save cart without loading it.');
	    }
	    
	    foreach ($this->arChg as $idx => $on) {
		$sqlFilt = '(ID_Cart='.$idCart.') AND (Type='.$idx.')';

		// are we inserting, or updating?
		$rc = $this->GetData($sqlFilt.' LIMIT 1');
		$vIn = $this->arInput[$idx];	// new value
		$this->arData[$idx] = $vIn;	// save new value locally
		$sqlVal = SQLValue($vIn);	// new value in SQL-safe format
		if ($rc->HasRows()) {
		    $sql = 'UPDATE '.$this->Name().' SET Val='.$sqlVal.' WHERE '.$sqlFilt;
		    $this->Engine()->Exec($sql);
		} else {
		    $arIns = array(
		      'ID_Cart'	=> $idCart,
		      'Type'	=> $idx,
		      'Val'	=> $sqlVal,
		      );
		   $this->Insert($arIns);
		}
	    }
	}
    }
    /*----
      ACTION: Dump all loaded cart values in HTML
      RETURNS: text of dump, ready to display
    */
    public function DumpData() {
	$out = '<table>';
	foreach ($this->arData as $idx => $val) {
	    $sName = self::$arFormIdxs[$idx];
	    $out .= "\n<tr><td>$sName</td><td>$val</td></tr>";
	}
	$out .= "\n</table>";
	return $out;
    }
    /*----
      ACTION: capture non-item form data received
	Technically this is cart data, because it goes in shop_cart_data,
	but it is really more to do with the order... except "order"
	specifically refers to the normalized order data. Need to
	straighten out the terminology here.
      TODO: This partially duplicates the information used to
	set up the contact objects, and there should be some
	way to avoid that -- LATER.
    */
    public function CaptureData($iPage) {
	switch($iPage) {
	  case KSQ_PAGE_SHIP:	// shipping / payment-type page
	    $arInUse = array(
	      KI_RECIP_IS_BUYER,
	      KSI_SHIP_IS_CARD,		// is this redundant?
	      KI_RECIP_NAME,
	      KI_RECIP_STREET,
	      KI_RECIP_CITY,
	      KI_RECIP_STATE,
	      KI_RECIP_ZIP,
	      KI_RECIP_COUNTRY,
	      KI_SHIP_ZONE,
	      KI_RECIP_EMAIL,
	      KI_RECIP_PHONE,
	      KI_RECIP_MESSAGE,
	      );
	    break;
	  case KSQ_PAGE_PAY:	// payment page
	    $arInUse = array(
	      KI_PAY_CARD_NUM,
	      KI_PAY_CARD_EXP,
	      KI_PAY_CARD_NAME,
	      KI_PAY_CARD_STREET,
	      KI_PAY_CARD_CITY,
	      KI_PAY_CARD_STATE,
	      KI_PAY_CARD_ZIP,
	      KI_PAY_CARD_COUNTRY,
	      KI_PAY_CHECK_NUM,
	      KI_BUYER_EMAIL,
	      KI_BUYER_PHONE,
	      KSI_SHIP_IS_CARD,	// editing the card's address can override this
	      );
	    break;
	  case KSQ_PAGE_CART:
	  default:	// cart may not pass a page name (not sure why not)
	    $arInUse = array(
	      KI_SHIP_ZONE,
	      );
	}
	foreach ($arInUse as $index) {
	    if (!array_key_exists($index,self::$arFormIdxs)) {
		// fields required for each page are defined above
		echo 'Unknown index <b>'.$index.'</b> requested. Indexes available in form:<pre>'.print_r(self::$arFormIdxs,TRUE).'</pre>';
		throw new exception('Requested index '.$index.' not found in form data.');
	    }
	    $sName = self::$arFormIdxs[$index];		// get name as submitted by form
	    $valNew = NzArray($_POST,$sName);	// get value submitted by form
	    $valOld = NzArray($this->arData,$index);
	    $this->arInput[$index] = $valNew;
	    if ($valNew != $valOld) {
		$this->arChg[$index] = TRUE;
	    }
	}
    }
    /*----
      NOTE: for parallellism, this should be called FormVal (or FieldVal should be called FieldValue).
	On the other hand, there's probably some benefit in having the names at different lengths
	  so they're more difficult to confuse with each other.
    */
    public function FormValue($iIndex) {
	if (!array_key_exists($iIndex,$this->arInput)) {
	    echo 'Form data does not include the key "<b>'.$iIndex.'</b>".<br>';
	    echo 'Form data:<pre>'.print_r($this->arInput,TRUE).'</pre>';
	    throw new exception("Key $iIndex expected, but not found in data.");
	}
	return $this->arInput[$iIndex];
    }
    /*----
      ACTION: Same as FormValue(), but does not throw an exception if the value is not found.
	Uses the default value instead, when this happens.
	This is useful for checkboxes, which are simply not listed when not checked.
	Perhaps checkboxes should all be named the same and given different values, but that
	  complicates things -- so, LATER.
    */
    public function FormValueNz($iIndex,$iDefault=FALSE) {
	if (!array_key_exists($iIndex,$this->arInput)) {
	    $val = $iDefault;
	} else {
	    $val = $this->arInput[$iIndex];
	}
	return $val;
    }
    /*----
      NOTE: Needs to be public so that forms can be processed more automatically
      ACTION: Returns and maybe also sets the value of a given field.
	If input value is not NULL, sets the value.
	Only the value in memory is affected; nothing is written to the database.
    */
    public function FieldVal($iIndex,$iValue=NULL) {
	if (!is_null($iValue)) {
	    $this->arData[$iIndex] = $iValue;
	    $this->arChg[$iIndex] = TRUE;
	}
	return (NzArray($this->arData,$iIndex));
    }
    /*----
      HISTORY:
	2013-02-25 removing $iAsPayee and code which depends on it -- not adequately documented,
	  and conflicts with what I'm trying to do at the moment.
	  It only seems to be used by BillObj(); does anything still call that?
    */
//    public function ShipObj($iAsPayee) {
    public function ShipObj() {
	if (is_null($this->arData)) {
	    throw new exception('Internal error: cart data not loaded.');
	}

/*
	// if using shipping address for payee, we still want to record cardholder name separately
	$useCardName = $iAsPayee && $this->ShipToCard();
	if ($useCardName) {
	    $objFld = new clsFormIndex(KSF_PAY_CARD_NAME,	KI_PAY_CARD_NAME);
	} else {
	    $objFld = new clsFormIndex(KSF_RECIP_NAME,	KI_RECIP_NAME);
	}
*/
	$objFld = new clsFormIndex(KSF_RECIP_NAME,	KI_RECIP_NAME);
	if (is_null($this->objShip)) {
	    $arFields = array(
	      $objFld,
	      new clsFormIndex(KSF_RECIP_STREET,	KI_RECIP_STREET),
	      new clsFormIndex(KSF_RECIP_CITY,	KI_RECIP_CITY),
	      new clsFormIndex(KSF_RECIP_STATE,	KI_RECIP_STATE),
	      new clsFormIndex(KSF_RECIP_ZIP,	KI_RECIP_ZIP),
	      new clsFormIndex(KSF_RECIP_COUNTRY,	KI_RECIP_COUNTRY),
	      new clsFormIndex(KSF_RECIP_EMAIL,	KI_RECIP_EMAIL),
	      new clsFormIndex(KSF_RECIP_PHONE,	KI_RECIP_PHONE),
	      // additional stuff to store
	      new clsFormIndex(KSF_SHIP_IS_CARD,	KSI_SHIP_IS_CARD),
	      new clsFormIndex(KSF_SHIP_MESSAGE,	KI_RECIP_MESSAGE)
	      );
	    $this->objShip = new clsPerson($this->Engine(),$this->arData,$arFields);
	}
	return $this->objShip;
    }
    public function CustObj() {
	if (!$this->IsLoaded()) {
	    throw new exception('Internal error: cart data not loaded.');
	}

	if (is_null($this->objCust)) {
	    $arFields = array(
	      new clsFormIndex(KSF_PAY_CARD_NAME,	KI_PAY_CARD_NAME),
	      new clsFormIndex(KSF_PAY_CARD_STREET,	KI_PAY_CARD_STREET),
	      new clsFormIndex(KSF_PAY_CARD_CITY,	KI_PAY_CARD_CITY),
	      new clsFormIndex(KSF_PAY_CARD_STATE,	KI_PAY_CARD_STATE),
	      new clsFormIndex(KSF_PAY_CARD_ZIP,	KI_PAY_CARD_ZIP),
	      new clsFormIndex(KSF_PAY_CARD_COUNTRY,	KI_PAY_CARD_COUNTRY),
	      NULL,NULL,	// no email or phone for customer (yet); they go with the shipping info
	      );
	    $this->objCust = new clsPerson($this->Engine(),$this->arData,$arFields);
	}
	return $this->objCust;
    }
    /*----
      RETURNS: if shipping to self, returns CustObj; otherwise returns ShipObj.
    */
/*
    public function DestObj() {
	if ($this->ShipToSelf()) {
	    return $this->CustObj();
	} else {
	    return $this->ShipObj();
	}
    }
*/
    /*----
      RETURNS: address data to use for billing.
      RULE: if shipping to self, billing object is shipping object (ShipObj)
	otherwise it's CustObj.
    */
    public function BillObj() {
	if ($this->ShipToSelf()) {
	    return $this->ShipObj(TRUE);
	} else {
	    return $this->CustObj();
	}
    }
    /*----
      ACTION: Returns an object to handle payment data.
    */
    public function PayObj() {
	if (is_null($this->arData)) {
	    throw new exception('Internal error: cart data not loaded.');
	}
	if (is_null($this->objPay)) {
	    $arFields = array(
	      new clsFormIndex(KSF_PAY_CARD_NUM,	KI_PAY_CARD_NUM),
	      new clsFormIndex(KSF_PAY_CARD_EXP,	KI_PAY_CARD_EXP),
	      new clsFormIndex(KSF_PAY_CHECK_NUM,	KI_PAY_CHECK_NUM),
	      );
	    $this->objPay = new clsPayment($this->Engine(),$this->arData,$arFields,$this->BillObj());
	}
	return $this->objPay;
    }
    public function CopyShipToCust() {
	$sShip = $this->ShipObj()->AsString();
	$this->CustObj()->AsString($sShip);
    }
    /*----
      RETURNS: Complete address as single string, in multiple lines
      HISTORY:
	2010-09-13 Added a line for Instruc()
	2012-05-27 Moved to clsPerson and then to clsCartVars
	2013-02-24 commenting this out, because it doesn't match the description -- is anyone useing it?
    */
/*
    public function ShipAddr_AsText(clsPerson $iPerson, $iLineSep="\n") {
	if ($this->HasShipMsg()) {
	    $xts = new xtString($this->ShipMessage(),TRUE);
	    $xts-> ReplaceSequence(chr(8).' ',' ',0);	// replace any blank sequences with single space
	    $xts->ReplaceSequence(chr(10).chr(13),$iLineSep,0);	// replace any sequences of newlines with line sep string
	    $out .= $iLineSep.$xts->Value;
	}
    }
*/
    /*----
      ACTION: check existing customer contact records for matches with the current data
      INPUT: current free-form cart data
      OUTPUT: returns array
	array[ID] = text describing what that ID matches
    */
    public function FindMatches() {
	$obj = $this->ShipObj(FALSE);
	$arShip['addr'] = $obj->FindMatches($this->Engine());
	if (!$this->ShipToSelf()) {
	    $obj = $this->CustObj();
	    $arCust['addr'] = $obj->FindMatches($this->Engine());
	}

	$strVal = $this->CustEmail();
	if (!empty($strVal)) {
	    $tbl = $this->Engine()->CustEmails();
	    $rs = $tbl->Find($strVal);
	    $arCust['email'] = AddMatches($rs);
	}

	$strVal = $this->CustPhone();
	if (!empty($strVal)) {
	    $tbl = $this->Engine()->CustPhones();
	    $rs = $tbl->Find($strVal);
	    $arCust['phone'] = AddMatches($rs);
	}

	$ar['cust'] = $arCust;
	$ar['ship'] = $arShip;
	return $ar;
    }
    /*----
      ACTION: generate datascript to import the free-form cart data into normalized order/contact data
      INPUT:
	$idCust,$idShip: contact IDs to use for buyer and ship-to, respectively; 'new' = create new record
      HISTORY:
	2013-11-06 commenting this out because data-scripting is being removed
    */
/*
    public function Script_forImport($idCust,$idShip) {
	$acts = new Script_Script();		// create root script

	$obj = $this->ShipObj(FALSE);
	$act = $obj->Script_forImport($idShip);
	$acts->Add($act,'person.ship');
	if (!$this->ShipToSelf()) {
	    $obj = $this->CustObj();
	    $act = $obj->Script_forImport($idCust);
	    $acts->Add($act,'person.cust');
	}

	return $acts;
    }
*/
  /*####
    SECTION: data-item specific access methods
    SUBSECTION: READ/WRITE methods
  */

    // SHIP TO info

    public function ShipZone($iZone=NULL) {
	return $this->FieldVal(KI_SHIP_ZONE,$iZone);
    }
    public function ShipToSelf($iFlag=NULL) {
	if (is_null($iFlag)) {
	    $strFlag = NULL;
	} else {
	    $strFlag = $iFlag?'on':'off';
	}
	$val = $this->FieldVal(KI_RECIP_IS_BUYER,$strFlag);
	return ($val == 'on');
    }
    public function ShipToCard($iFlag=NULL) {
	if (is_null($iFlag)) {
	    $strFlag = NULL;
	} else {
	    $strFlag = $iFlag?'1':'0';
	}
	$val = $this->FieldVal(KSI_SHIP_IS_CARD,$strFlag);
	return (!empty($val));
    }
    public function RecipName($iText=NULL) {
	return $this->FieldVal(KI_RECIP_NAME,$iText);
    }
    public function ShipAddrStreet($iText=NULL) {
	return $this->FieldVal(KI_RECIP_STREET,$iText);
    }
    public function ShipAddrTown($iText=NULL) {
	return $this->FieldVal(KI_RECIP_CITY,$iText);
    }
    public function ShipAddrState($iText=NULL) {
	return $this->FieldVal(KI_RECIP_STATE,$iText);
    }
    public function ShipAddrZip($iText=NULL) {
	return $this->FieldVal(KI_RECIP_ZIP,$iText);
    }
    public function ShipAddrCountry($iText=NULL) {
	return $this->FieldVal(KI_RECIP_COUNTRY,$iText);
    }
    public function ShipEmail($iText=NULL) {
	return $this->FieldVal(KI_RECIP_EMAIL,$iText);
    }
    public function ShipPhone($iText=NULL) {
	return $this->FieldVal(KI_RECIP_PHONE,$iText);
    }
    public function ShipMsg($iText=NULL) {
	return $this->FieldVal(KI_RECIP_MESSAGE,$iText);
    }

    // BUYER info

    public function CardNum($iText=NULL) {
	return $this->FieldVal(KI_PAY_CARD_NUM,$iText);
    }
    public function CardExp($iText=NULL) {
	return $this->FieldVal(KI_PAY_CARD_EXP,$iText);
    }
    public function CheckNum($iText=NULL) {
	return $this->FieldVal(KI_PAY_CHECK_NUM,$iText);
    }
    public function BuyerID() {
	return $this->FieldVal(KI_BUYER_ID,NULL);
    }
    /*----
      LATER: Cardholder name (PayCardName()) should be separate from buyer's name.
    */
    public function BuyerName() {
	return $this->PayCardName();
    }
    public function PayCardName() {
	return $this->FieldVal(KI_PAY_CARD_NAME,NULL);
    }
    /*----
      TODO: This should be KI_BUYER_EMAIL OSLT, but right now we don't record
	separate emails/phonesfor sender/recipient
    */
    public function CustEmail() {
	return $this->FieldVal(KI_RECIP_EMAIL);
    }
    /*----
      TODO: This should be KI_BUYER_PHONE OSLT, but right now we don't record
	separate emails/phonesfor sender/recipient
    */
  /*####
    SUBSECTION: READ-ONLY methods
  */
    public function CardAddrBlank() {
	return $this->BillObj()->AddrIsBlank();
    }
    public function CardMatchesShip() {
	$sShipAddr = $this->ShipObj()->Addr_forSearch_stripped(TRUE);
	$sPayAddr = $this->BillObj()->Addr_forSearch_stripped(TRUE);
	return ($sShipAddr == $sPayAddr);
    }
    public function Has_ShipMsg() {
	return !is_null($this->Get_ShipMessage());
    }
    // these could be converted to read/write if needed
    public function CustPhone() {
	return $this->FieldVal(KI_RECIP_PHONE);
    }
    public function CostTotalSale() {
	return $this->FieldVal(KI_CALC_SALE_TOTAL);
    }
    public function CostTotalPerItem() {
	return $this->FieldVal(KI_CALC_PER_ITEM_TOTAL);
    }
    public function CostTotalPerPkg() {	// "Total" is something of a misnomer here...
	return $this->FieldVal(KI_CALC_PER_PKG_TOTAL);
    }
}
class clsCartVar extends clsRecs_indexed {
}

// this might later be adapted to use forms.php, but LATER
class clsFormIndex {
    private $strName;	// name to use in form
    private $strIndex;	// index for db retrieval

    public function __construct($iName,$iIndex) {
	if (!is_integer($iIndex)) {
	    throw new exception('clsFormInded initialized improperly -- iIndex is ['.$iIndex.'], should be an integer.');
	}
	$this->strName = $iName;
	$this->strIndex = $iIndex;
    }
    public function Name() {
	return $this->strName;
    }
    public function Index() {
	return $this->strIndex;
    }
}

class clsCartDataGrp {
    protected $objDB;
    protected $arNames;
    protected $arVals;
    protected $arIdxs;

    public function __construct(clsDatabase $iDB, array $arData, array $arIdxs) {
	$this->objDB = $iDB;
	$this->Init($arData,$arIdxs);
    }

    public function Init(array $arData, array $arIdxs) {
	$this->arIdxs = $arIdxs;

	foreach ($arIdxs as $obj) {
	    if (is_object($obj)) {
		$idx = $obj->Index();
		$this->arNames[] = $obj->Name();
		$this->arVals[] = NzArray($arData,$idx);
	    } else {
		$this->arVals[] = NULL;
		$this->arNames[] = NULL;
	    }
	}
    }
}

class clsPerson extends clsCartDataGrp {
    /*----
      ASSUMES: $arIdxs is in a particular order
	0: Name
	1: Street
	2: Town
	3: State
	4: Zip
	5: Ctry
	6: Email
	7: Phone
    */
    public function DoEmail() {
	return !is_null($this->arNames[6]);
    }
    public function DoPhone() {
	return !is_null($this->arNames[7]);
    }
    /*----
      USED BY: order confirmation
    */
    public function NameVal($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->arVals[0] = $iVal;
	}
	return $this->arVals[0];
    }
    protected function StreetVal($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->arVals[1] = $iVal;
	}
	return $this->arVals[1];
    }
    protected function DoStreet() {
	return !is_null($this->arNames[1]);
    }
    protected function TownVal($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->arVals[2] = $iVal;
	}
	return $this->arVals[2];
    }
    protected function StateVal($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->arVals[3] = $iVal;
	}
	return $this->arVals[3];
    }
    protected function DoState() {
	return !is_null($this->arNames[3]);
    }
    protected function ZipVal($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->arVals[4] = $iVal;
	}
	return $this->arVals[4];
    }
    protected function DoZip() {
	return !is_null($this->arNames[4]);
    }
    protected function CountryVal($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->arVals[5] = $iVal;
	}
	return $this->arVals[5];
    }
/*
    protected function CountryVal_effective() {
	$sCtry = $this->CountryVal();
	if (empty($sCtry)) {
	    $sCtry = KS_SHIP_ZONE_DEFAULT;
	}
	return $sCtry;
    }
*/
    protected function DoCountry() {
	return !is_null($this->arNames[5]);
    }
    /*----
      USED BY: order confirmation
    */
    public function EmailVal() {
	return $this->arVals[6];
    }
    protected function PhoneVal() {
	return $this->arVals[7];
    }
    protected function ZoneObj() {
	$objZone = new clsShipZone();
	$objZone->Set_fromName($this->CountryVal());
	return $objZone;
    }
    /*----
      RETURNS: address (not including name) basically unformatted, in a single line
	Ready to be stripped down for search, but not actually stripped down yet.
    */
    public function Addr_forSearch($iUseName) {
	$objZone = $this->ZoneObj();

	$strAddr = NULL;
	if ($iUseName) {
	    $strAddr = $this->NameVal();
	}
	$strAddr .= $this->StreetVal().' '.$this->TownVal().' '.$this->StateVal().' '.$this->ZipVal();
	if (!$objZone->isDomestic()) {
	    $strAddr .= ' '.$this->CountryVal();
	}

	return $strAddr;
    }
    /*----
      RETURNS: Same as Addr_forSearch(), but massaged for searching:
	* all chars lowercase
	* all blank and CRLF sequences condensed to a single space
    */
    public function Addr_forSearch_stripped($iUseName) {
	$s = $this->Addr_forSearch($iUseName);
	return strtolower(xtString::_ReplaceSequence($s, " \t\n\r", ' '));
    }

    /*----
      RETURNS: Main address as single string, in multiple lines
      HISTORY:
	2012-01-11 extracted from AsText() so instructions can be left out of search
	2012-05-27 moved to clsPerson
    */
    public function Addr_AsText($iLineSep="\n") {
	$xts = new xtString($this->StreetVal(),TRUE);
	$xts-> ReplaceSequence(chr(8).' ',' ',0);	// replace any blank sequences with single space
	$xts->ReplaceSequence(chr(10).chr(13),$iLineSep,0);	// replace any sequences of newlines with line sep string

	$xts->Value .= $iLineSep.$this->TownVal();
	if ($this->DoState()) {
	    $xts->Value .= ', '.$this->StateVal();
	}
	if ($this->DoZip()) {
	    $xts->Value .= ' '.$this->ZipVal();
	}
	if ($this->DoCountry()) {
	    $xts->Value .= ' '.$this->CountryVal();
	}
	return $xts->Value;
    }
    public function AddrIsBlank() {
	if ($this->NameVal() == '') {
	    if ($this->StreetVal() == '') {
		if ($this->TownVal() == '') {
		    if ($this->StateVal() == '') {
			if ($this->ZipVal() == '') {
			    if ($this->ZoneObj()->isDomestic()) {
				return TRUE;
			    } else {
				if ($this->CountryVal() == '') {
				    return TRUE;
				}
			    }
			}
		    }
		}
	    }
	}
	return FALSE;
    }

    public function AsString($iString=NULL) {
	if (!is_null($iString)) {
	    clsModule::LoadFunc('Xplode');
	    $arStr = Xplode($iString);
	    $this->NameVal($arStr[0]);
	    $this->StreetVal($arStr[1]);
	    $this->TownVal($arStr[2]);
	    $this->StateVal($arStr[3]);
	    $this->ZipVal($arStr[4]);
	    if (count($arStr) > 5) {
		$this->CountryVal($arStr[5]);
	    }
	}
	$out = "\t".$this->NameVal()
	  ."\t".$this->StreetVal()
	  ."\t".$this->TownVal()
	  ."\t".$this->StateVal()
	  ."\t".$this->ZipVal();
	if (!$this->ZoneObj()->isDomestic()) {
	    $out .= "\t".$this->CountryVal();
	}
	return $out;
    }

    public function FindMatches($idCont=NULL) {
	// search for address matches
	$strAddr = $this->Addr_forSearch();
	$tbl = $this->objDB->CustAddrs();
	$rs = $tbl->Find($strAddr);
	$ar = AddMatches($rs);
	return $ar;
    }

    /*----
      INPUT:
	idCont - ID of customer record to use
	  NULL means a new one is being created.
      NOTES:
	A logistical issue to keep in mind: this creates the customer record if it doesn't exist. Email and Phone objects will
	  not be able to do this. Maybe we need to handle them in here too?
      HISTORY:
	2013-11-06 commenting this out because data-scripting is being removed
    */
/*
    public function Script_forImport($idCont) {
	$strAddr = $this->Addr_forSearch();
	$tbl = $this->objDB->CustAddrs();
	$actRoot = new Script_Script();
	if (is_null($idCont)) {
	    $hasRows = FALSE;
	    $actRoot->Add(new Script_Status('No contact ID given'));
	} else {
	    $arFilt = array(
	      'ID_Cust'	=> $idCont,
	      'SearchRaw'	=> $strAddr,
	      );
	    $sqlFilt = $tbl->MakeFilt($arFilt);
	    $rs = $tbl->GetData($sqlFilt);
	    $hasRows = $rs->HasRows();
	    $actRoot->Add(new Script_Status('Using contact ID='.$idCont.' - addresses found: '.$hasRows));
	}
	if ($hasRows) {
	    $actRoot->Add(new Script_Status('Will update order from existing ID'));
	    // get ID so we can update order record
	    $arUpd = array('WhenUpdated' => 'NOW()');
	    $rs->FirstRow();
//	    $actCust = new Script_Row_Data($rs);	// load row data, including ID
	    // not sure if this does what we need
	    $actCust = new Script_Row_Update($arUpd,$rs);	// add to script: update existing row's timestamp
	      // or maybe we could just use Script_Row_Update?
	} else {
	    $actRoot->Add(new Script_Status('Creating new address record'));
	    // create new address record: start the update array
	    $arIns = array('WhenCreated' => 'NOW()');

	    if (is_null($idCont)) {
		// create new customer master record
		$tblMaster = $this->objDB->Custs();
		$actInsMaster = $tblMaster->Make_Script();
		$actCust->Add($actInsMaster,'cont.make');
		// create new address record: add new master record ID to the update array
		$actCust->Add(new Script_Row_Update_fromInsert($arIns, $actInsMaster));	 // add new ID to the insert
	    } else {
		// create new address record: add existing master record ID to the update array
		$arIns['ID_Cust'] = $idCont;
	    }

	    // create new address record - generate script step
	    $actCust = new Script_Tbl_Insert($arIns,$tblAddrs);
	}
	$actRoot->Add($actCust,'addr.make');
	return $actRoot;
    }
*/
    /*----
      HISTORY:
	2012-05-16 copied from old clsPerson, but will probably need significant rewriting
    */
    public function Render(clsPageCkout $iCart, array $arOptions) {
	$htBefAddr	= $arOptions['ht.before.addr'];
	$htAftAddr	= $arOptions['ht.after.addr'];
	$htAftState	= $arOptions['ht.after.state'];
	$htShipCombo	= $arOptions['ht.ship.combo'];
	$strZipLbl	= $arOptions['str.zip.label'];
	$strStateLbl	= $arOptions['str.state.label'];
	$lenStateInp	= $arOptions['len.state.inp'];	// "length" attribute to use for user input field for "state" 
	$doFixedCtry 	= $arOptions['do.fixed.ctry'];
	$doFixedName 	= $arOptions['do.fixed.name'];
	$doFixedAll	= $arOptions['do.fixed.all'];
	$doShipZone	= NzArray($arOptions,'do.ship.zone');

// copy calculated stuff over to variables to make it easier to insert in formatted output:
	$ksName		= $this->arNames[0];
	$ksStreet	= $this->arNames[1];
	$ksCity		= $this->arNames[2];
	$ksState	= $this->arNames[3];
	$ksZip		= $this->arNames[4];
	$ksCountry	= $this->arNames[5];
	$ksEmail	= $this->arNames[6];
	$ksPhone	= $this->arNames[7];

	$strName	= $this->NameVal();
	$strStreet	= $this->StreetVal();
	$strCity	= $this->TownVal();
	$strState	= $this->StateVal();
	$strZip		= $this->ZipVal();
	$strCountry	= $this->CountryVal();
	$strEmail	= $this->EmailVal();
	$strPhone	= $this->PhoneVal();

	if ($doFixedAll) {
	    $doFixedCtry = TRUE;
	}

	if ($doFixedCtry) {
	    $htCountry = '<b>'.$strCountry.'</b>';
	    $htZone = '';
	} else {
	    $htCountry = '<input name="'.$ksCountry.'" value="'.$strCountry.'" size=20>';
	    $htBtnRefresh = '<input type=submit name="update" value="Update Form">';
//	    $htZone = " shipping zone: $htShipCombo $htBtnRefresh";
	    // this no longer displays the total, so no need for an update button
	    $htZone = $doShipZone?(" shipping zone: $htShipCombo"):'';
	}

	if ($doFixedName) {
	    $out = <<<__END__
<tr><td align=right valign=middle>Name:</td>
	<td><b>$strName</b></td>
	</tr>
__END__;
	} else {
	    $out = <<<__END__
<tr><td align=right valign=middle>Name: </td>
	<td><input name="$ksName" value="$strName" size=50></td>
	</tr>
__END__;
	}
	$out .= $htBefAddr;

	$doEmail = $this->DoEmail();
	$doPhone = $this->DoPhone();

	if ($doFixedAll) {
	    $htStreet = "<b>$strStreet</b>";
	    $htCity = "<b>$strCity</b>";
	    $htState = "<b>$strState</b>";
	    $htZip = "<b>$strZip</b>";
	    $htCtry = "<b>$htCountry</b>$htZone";
	    $htEmail = "<b>$strEmail</b> (".$arOptions['ht.after.email'].')';
	    $htPhone = "<b>$strPhone</b> (optional)";
	} else {
	    $htStreet = '<textarea name="'.$ksStreet.'" cols=50 rows=3>'.$strStreet.'</textarea>';
	    $htCity = '<input name="'.$ksCity.'" value="'.$strCity.'" size=20>';
	    $htState = '<input name="'.$ksState.'" value="'.$strState.'" size='.$lenStateInp.'>'.$htAftState;
	    $htZip = '<input name="'.$ksZip.'" value="'.$strZip.'" size=11>';
//	    $htCtry = "$htCountry - change shipping zone: $htShipCombo $htBtnRefresh";
	    $htCtry = $htCountry.$htZone;

	    $htEmail = '<input name="'.$ksEmail.'" value="'.$strEmail.'" size=30> '.$arOptions['ht.after.email'];
	    $htPhone = '<input name="'.$ksPhone.'" value="'.$strPhone.'" size=20> (optional)';
	}

	$out .= <<<__END__
<tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td><td>$htStreet</td></tr>
<tr><td align=right valign=middle>City:</td><td>$htCity</td></tr>
<tr><td align=right valign=middle>$strStateLbl:</td><td>$htState</td></tr>
<tr><td align=right valign=middle>$strZipLbl:</td><td>$htZip</td></tr>
<tr><td align=right valign=middle>Country:</td><td>$htCtry</td></tr>
__END__;

      
	
/*
	if ($doFixedAll) {
	    $out .= <<<__END__
<tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td>
	<td><b>$strStreet</b></td>
	</tr>
<tr><td align=right valign=middle>City:</td><td><b>$strCity</b></td></tr>
<tr><td align=right valign=middle>$strStateLbl:</td><td><b>$strState</b></td></tr>
<tr><td align=right valign=middle>$strZipLbl:</td><td><b>$strZip</b></td></tr>
<tr><td align=right valign=middle>Country:</td><td><b>$htCountry<b>$htZone</td></tr>
__END__;
	} else {
	    $out .= <<<__END__
<tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td>
	<td><textarea name="$ksStreet" cols=50 rows=3>$strStreet</textarea></td>
	</tr>
<tr><td align=right valign=middle>City: </td><td><input name="$ksCity" value="$strCity" size=20></td></tr>
<tr><td align=right valign=middle>$strStateLbl: </td><td><input name="$ksState" value="$strState" size=$lenStateInp>$htAftState</td></tr>
<tr><td align=right valign=middle>$strZipLbl: </td><td><input name="$ksZip" value="$strZip" size=11></td></tr>
<tr><td align=right valign=middle>Country: </td><td>$htCountry - change shipping zone: $htShipCombo $htBtnRefresh</td></tr>
__END__;
	}
*/
	$out .= $htAftAddr;

// if this contact saves email and phone, then render those too:
	if ($doEmail) {
	    $out .= "<tr><td align=right valign=middle>Email:</td><td>$htEmail</td></tr>";
	}
	if ($doPhone) {
	    $out .= "<tr><td align=right valign=middle>Phone:</td><td>$htPhone</td></tr>";
	}

	return $out;
    }
    public function Capture(clsPageCkout $iPage) {

throw new exception('Who calls this? (2013-11-07)');

	$objCart = $iPage->CartObj();
	$objZone = $objCart->ShipZoneObj();
	$objVars = $iPage->CartData();

	// this is a bit of a kluge; there may be a better way.
	$objVars->LoadCart($objCart->KeyValue());	// get data already loaded

	foreach ($this->arIdxs as $idxArr => $obj) {
    
	    if (is_object($obj)) {
		$strName = $obj->Name();
		$idxForm = $obj->Index();
		$valForm = $iPage->GetFormItem($strName);	// get user input
echo '<br>INDEX='.$idxForm.' NAME=['.$strName.'] VAL=['.$valForm.']';
		$objVars->SaveField($idxForm,$valForm);		// save to database
		$this->arVals[$idxArr] = $valForm;		// save to object (to update display)

/*
Defined in $this->Init():

	  $this->strName,	0
	  $this->strStreet,	1
	  $this->strTown,	2
	  $this->strState,	3
	  $this->strZip,	4
	  $this->strCtry,	5
	  $this->strEmail,	6
	  $this->strPhone	7
*/
/*
		switch ($idxArr) {
		  case 0:
		    $custName = $valForm;
		    break;
		  case 1:
		    $custStreet = $valForm;
		    break;
		  case 2:
		    $custCity = $valForm;
		    break;
		  case 3:
		    $custState = $valForm;
		    break;
		  case 6:
		    $custEmail = $valForm;
		    break;
		}
*/
	    }
	}
	$objVars->SaveCart();
	$custName = $this->NameVal();
	$custStreet = $this->StreetVal();
	$custCity = $this->TownVal();
	$custState = $this->StateVal();
	$custEmail = $this->EmailVal();

/*
	$shipZone	= $iCart->GetFormItem(KSF_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	$custShipToSelf	= $iCart->GetFormItem(KSF_SHIP_TO_SELF);
	$custShipIsCard	= $iCart->GetFormItem(KSF_SHIP_IS_CARD);
	$custName	= $iCart->GetFormItem(KSF_RECIP_NAME);
	$custStreet	= $iCart->GetFormItem(KSF_RECIP_STREET);
	$custCity	= $iCart->GetFormItem(KSF_RECIP_CITY);
	$custState	= $iCart->GetFormItem(KSF_RECIP_STATE);
	$custZip	= $iCart->GetFormItem(KSF_RECIP_ZIP);
	$custCountry	= $iCart->GetFormItem(KSF_RECIP_COUNTRY);
	$custEmail	= $iCart->GetFormItem(KSF_RECIP_EMAIL);
	$custPhone	= $iCart->GetFormItem(KSF_RECIP_PHONE);
	$custMessage	= $iCart->GetFormItem(KSF_SHIP_MESSAGE);


	$objCD->ShipZone($shipZone);
	$objCD->RecipName($custName);
	$objCD->ShipAddrStreet($custStreet);
	$objCD->ShipAddrTown($custCity);
	$objCD->ShipAddrState($custState);
	$objCD->ShipAddrZip($custZip);
	$objCD->ShipAddrCountry($custCountry);

	$objCD->ShipToSelf($custShipToSelf);
	$objCD->ShipToCard($custShipIsCard);
	$objCD->ShipEmail($custEmail);
	$objCD->ShipPhone($custPhone);
	$objCD->ShipMessage($custMessage);
*/
	$iPage->CheckField('name',$custName);
	if (!$objVars->ShipToCard()) {
	    $iPage->CheckField('street address',$custStreet);
	    $iPage->CheckField('city',$custCity);
	    if (($custState == '') && ($objZone->hasState())) {
		    $iPage->AddMissing($objZone->StateLabel());
	    }
	    if (!$objZone->isDomestic()) {
		$iPage->CheckField('country',$custCountry);
	    }
	}
	if ($this->DoEmail()) {
	    $iPage->CheckField('email',$custEmail);
	}
    }
}

class clsPayment extends clsCartDataGrp {
    private $objCust;

    public function __construct(clsDatabase $iDB, array $arData, array $arIdxs, clsPerson $iCust) {
	parent::__construct($iDB, $arData, $arIdxs);
	$this->objCust = $iCust;
    }
    /*----
      ASSUMES: $arIdxs is in a particular order
    */
    public function Init(array $arData, array $arIdxs) {
	parent::Init($arData,$arIdxs);
	list(
	  $this->strCardNum,
	  $this->strCardExp,
	  $this->strCheckNum,
	  ) = $this->arVals;
	list(
	  $this->ftCardNum,
	  $this->ftCardExp,
	  $this->ftCheckNum,
	  ) = $this->arNames;
    }
    protected function CustObj() {
	return $this->objCust;
    }
    protected function CardNum() {
	return $this->arVals[0];
    }
    protected function CardExp() {
	return $this->arVals[1];
    }
    protected function CheckNum() {
	return $this->arVals[2];
    }
    public function Capture(clsPageCkout $iCart) {
	$objCart = $iCart->CartObj();
	$objZone = $objCart->ShipZoneObj();
	$objVars = $iCart->CartData();

	// this is a bit of a kluge; there may be a better way.
	$objVars->LoadCart($objCart->KeyValue());	// get data already loaded

	foreach ($this->arIdxs as $idxArr => $obj) {
    
	    if (is_object($obj)) {
		$strName = $obj->Name();
		$idxForm = $obj->Index();
		$valForm = $iCart->GetFormItem($strName);	// get user input
		$objVars->SaveField($idxForm,$valForm);		// save to database

		switch ($idxArr) {
		  case 0:
		    $cardNum = $valForm;
		    break;
		  case 1:
		    $cardExp = $valForm;
		    break;
		  case 2:
		    $checkNum = $valForm;
		    break;
		}
	    }
	}

	if (is_null($checkNum)) {
	    $iCart->CheckField('card number',$cardNum);
	    $iCart->CheckField('card expiration',$cardExp);
	}

	$objVars->SaveCart();

/*
	    # check for missing data
	    $this->CheckField("cardholder's name",$custCardName);
	    $this->CheckField("card's billing address",$custCardStreet);
	    $this->CheckField("card's billing address - city",$custCardCity);
*/
    }

    /*----
      ACTION: Return a description of the payment in a safe format
	(incomplete credit card number)
      TO DO: Allow for payment types other than credit card
    */
    public function SafeDisplay() {
	$out = clsCustCards::SafeDescr_Long($this->CardNum(),$this->CardExp());
	$out .= '<br>'.$this->CustObj()->Addr_AsText("\n<br>");
	return $out;
    }
}

function AddMatches(clsRecs_keyed_abstract $iRows) {
    $ar = NULL;
    if ($iRows->HasRows()) {
	$iRows->StartRows();
	while ($iRows->NextRow()) {
	    $id = $iRows->CustID();
	    $ar[] = $id;
	}
    }
    return $ar;
}
