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

/*%%%%
  RULES: call FieldRows(cart ID) to get a dataset for the given cart
    Use the dataset's access methods to look up row data.
*/
class clsCartVars extends clsTable_indexed {

    //protected $arData;
    protected $arChg;	// list of fields changed from what is in db
    protected $arInput;	// user data retrieved from form
    //protected $objCust, $objShip, $objPay;
    // object cache
    private $idCart;	// ID of cart currently loaded
    private $rsFlds;	// recordset of cart fields

    // ++ SETUP ++ //

    public function __construct($iDB) {
	$objIdx = new clsIndexer_Table_multi_key($this);
	$objIdx->KeyNames(array('ID_Cart','Type'));

	$this->arChg = NULL;
	$this->idCart = NULL;
	$this->rsFlds = NULL;

	parent::__construct($iDB);
	  $this->Name('shop_cart_data');
	  $this->ClassSng('clsCartVar');
	  $this->Indexer($objIdx);
    }

    // -- SETUP -- //
    // ++ STATIC ++ //

    /*----
      WHAT: This seems to be a list of mappings from input form field names
	to cart data index numbers.
    */
    protected static $arFormIdxs = array(
      KI_CART_SHIP_ZONE		=> KSF_CART_RECIP_SHIP_ZONE,

      KI_CART_SHIP_IS_CARD	=> KSF_SHIP_IS_CARD,
      KI_CART_RECIP_INTYPE	=> KSF_CART_RECIP_CONT_INTYPE,
      KI_CART_RECIP_CHOICE	=> KSF_CART_RECIP_CONT_CHOICE,
      KI_CART_RECIP_IS_BUYER	=> KSF_CART_RECIP_IS_BUYER,
      KI_CART_RECIP_MESSAGE	=> KSF_SHIP_MESSAGE,
      KI_CART_RECIP_NAME	=> KSF_CART_RECIP_NAME,
      KI_CART_RECIP_STREET	=> KSF_CART_RECIP_STREET,
      KI_CART_RECIP_CITY	=> KSF_CART_RECIP_CITY,
      KI_CART_RECIP_STATE	=> KSF_CART_RECIP_STATE,
      KI_CART_RECIP_ZIP		=> KSF_CART_RECIP_ZIP,
      KI_CART_RECIP_COUNTRY	=> KSF_CART_RECIP_COUNTRY,
      KI_CART_RECIP_EMAIL	=> KSF_CART_RECIP_EMAIL,
      KI_CART_RECIP_PHONE	=> KSF_CART_RECIP_PHONE,

      // -- payment
      KI_CART_BUYER_INTYPE	=> KSF_CART_PAY_CARD_INTYPE,
      KI_CART_BUYER_CHOICE	=> KSF_CART_PAY_CARD_CHOICE,
      KI_CART_PAY_CARD_NUM	=> KSF_CART_PAY_CARD_NUM,
      KI_CART_PAY_CARD_EXP	=> KSF_CART_PAY_CARD_EXP,
      KI_CART_PAY_CARD_ENCR	=> KSF_CART_PAY_CARD_ENCR,
      KI_CART_PAY_CARD_NAME	=> KSF_CART_PAY_CARD_NAME,
      KI_CART_PAY_CARD_STREET	=> KSF_CART_PAY_CARD_STREET,
      KI_CART_PAY_CARD_CITY	=> KSF_CART_PAY_CARD_CITY,
      KI_CART_PAY_CARD_STATE	=> KSF_CART_PAY_CARD_STATE,
      KI_CART_PAY_CARD_ZIP	=> KSF_CART_PAY_CARD_ZIP,
      KI_CART_PAY_CARD_COUNTRY	=> KSF_CART_PAY_CARD_COUNTRY,
      KI_CART_PAY_CHECK_NUM	=> KSF_CART_PAY_CHECK_NUM,
      KI_CART_BUYER_EMAIL	=> KSF_CART_BUYER_EMAIL,
      KI_CART_BUYER_PHONE	=> KSF_CART_BUYER_PHONE,
      );

    static public function NameFromIndex($nIdx) {
	return self::$arFormIdxs[$nIdx];
    }
    static public function IndexFromName($sName) {
	return array_search($sName,self::$arFormIdxs);
    }

    // -- STATIC -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      USED BY:
	* Cart object, for retrieving cart stats (totals) reported to customer
	* internally, for initializing data-group objects
      ASSUMES:
	* If caller passes NULL for $idCart, then data for the correct cart has already been loaded.
    */
    public function FieldRows($idCart=NULL) {
	if (!is_null($idCart) && ($this->idCart != $idCart)) {
	    // if cart fields not already loaded for the given cart, load them:
	    $sql = 'SELECT Type, Val FROM '.$this->NameSQL().' WHERE ID_Cart='.$idCart;
	    $this->rsFields = $this->DataSQL($sql);
	    $this->idCart = $idCart;		// we need to know which cart the recordset has, for caching
	    $this->rsFields->CartID($idCart);	// recordset needs to know it too (not sure why)
	}
	return $this->rsFields;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ SEARCHING ++ //

    /*----
      ACTION: check existing customer contact records for matches with the current data
      INPUT: current free-form cart data
      OUTPUT: returns array
	array[ID] = text describing what that ID matches
    */
    public function FindMatches() {
	$obj = $this->ShipObj(FALSE);
	$arShip['addr'] = $obj->FindMatches($this->Engine());
	if (!$this->IsShipToSelf()) {
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

    // -- SEARCHING -- //
    // ++ FORM PROCESSING ++ //

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
    public function CaptureData($sPage) {
	switch($sPage) {
	  case KSQ_PAGE_SHIP:	// shipping / payment-type page
	    $arInUse = array(
	      KI_CART_RECIP_IS_BUYER,
	      KI_CART_SHIP_IS_CARD,		// is this redundant?
	      KI_CART_RECIP_NAME,
	      KI_CART_RECIP_STREET,
	      KI_CART_RECIP_CITY,
	      KI_CART_RECIP_STATE,
	      KI_CART_RECIP_ZIP,
	      KI_CART_RECIP_COUNTRY,
	      KI_CART_SHIP_ZONE,
	      KI_CART_RECIP_EMAIL,
	      KI_CART_RECIP_PHONE,
	      KI_CART_RECIP_MESSAGE,
	      );
	    break;
	  case KSQ_PAGE_PAY:	// payment page
	    $arInUse = array(
	      KI_CART_PAY_CARD_NUM,
	      KI_CART_PAY_CARD_EXP,
	      KI_CART_PAY_CARD_NAME,
	      KI_CART_PAY_CARD_STREET,
	      KI_CART_PAY_CARD_CITY,
	      KI_CART_PAY_CARD_STATE,
	      KI_CART_PAY_CARD_ZIP,
	      KI_CART_PAY_CARD_COUNTRY,
	      KI_CART_PAY_CHECK_NUM,
	      KI_CART_BUYER_EMAIL,
	      KI_CART_BUYER_PHONE,
	      KI_CART_SHIP_IS_CARD,	// editing the card's address can override this
	      );
	    break;
	  case KSQ_PAGE_CART:
	  default:	// cart may not pass a page name (not sure why not)
	    $arInUse = array(
	      KI_CART_SHIP_ZONE,
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
	    //$valOld = NzArray($this->arData,$index);
	    $valOld = $this->FieldRows()->FieldValue_forIndex($index);
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

    // -- FORM PROCESSING -- //
    // ++ ACTIONS ++ //

    public function CopyShipToCust() {
	$sShip = $this->ShipObj()->AsString();
	$this->CustObj()->AsString($sShip);
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

    // -- ACTIONS -- //
    // ++ DEBUGGING ++ //

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

    // -- DEBUGGING -- //
}
class clsCartVar extends clsRecs_indexed {
    private $arVals;
    private $idCart;

    // ++ SETUP ++ //

    protected function InitVars() {
	$this->arVals = NULL;
	$this->idCart = NULL;
    }

    // -- SETUP -- //
    // ++ RECORD FIELD ACCESS ++ //

    public function TypeID() {
	return $this->Value('Type');
    }

    // -- RECORD FIELD ACCESS -- //
    // ++ FIELD CACHE ++ //

    protected function LoadValues() {
	$this->arVals = array();	// in case there's no data yet
	while ($this->NextRow()) {
	    $nType = $this->TypeID();
	    $this->arVals[$nType] = $this->Value('Val');
	}
    }
    public function FieldValue_forName($sFld) {
	$nIdx = clsCartVars::IndexFromName($sFld);
	if ($nIdx > 0) {
	    return $this->FieldValue_forIndex($nIdx);
	} else {
	    throw new exception("No index found for field name [$sFld].");
	}
    }
    /*----
      HISTORY:
	2014-03-02 making this writable
    */
    public function FieldValue_forIndex($idType,$val=NULL) {
	if (!is_numeric($idType)) {
	    throw new exception('$idIndex should be a numeric ID; type ['.gettype($idIndex).'] was received.');
	}
	if (is_null($val)) {
	    if (is_null($this->arVals)) {
		$this->LoadValues();
	    }
	    if (array_key_exists($idType,$this->arVals)) {
		return $this->arVals[$idType];
	    } else {
		return NULL;
	    }
	} else {
	    $arUpd = array(
	      'Type'	=> $idType,
	      'Val'	=> SQLValue($val)
	      );
	    $idCart = $this->CartID();
	    $sqlFilt = "(ID_Cart=$idCart) AND (Type=$idType)";
	    $this->Table()->Make($arUpd,$sqlFilt);
	    return $val;
	}
    }

    // -- FIELD CACHE -- //
    // ++ SPECIFIC FIELD VALUES ++ //


      // ++ cross-form values

    public function IsShipToCard($iFlag=NULL) {
	if (is_null($iFlag)) {
	    $strFlag = NULL;
	} else {
	    $strFlag = $iFlag?'1':'0';
	}
	$val = $this->FieldValue_forIndex(KI_CART_SHIP_IS_CARD,$strFlag);
	return (!empty($val));
    }
    public function IsShipToSelf() {
	throw new exception('IsShipToSelf() is deprecated until I can figure out why it is necessary.');
    }
    public function IsRecipNewEntry() {
	return $this->FieldValue_forIndex(KI_CART_RECIP_INTYPE);
    }
    public function IsBuyerNewEntry() {
	return $this->FieldValue_forIndex(KI_CART_BUYER_INTYPE);
    }

      // ++ form-specific values

    public function CardNumber() {
	return $this->FieldValue_forIndex(KI_CART_PAY_CARD_NUM);
    }
    public function CardExpiry() {
	return $this->FieldValue_forIndex(KI_CART_PAY_CARD_EXP);
    }
    public function CostTotalSale($nVal=NULL) {
	return $this->FieldValue_forIndex(KI_CART_CALC_SALE_TOTAL,$nVal);
    }
    public function CostTotalPerItem($nVal=NULL) {
	return $this->FieldValue_forIndex(KI_CART_CALC_PER_ITEM_TOTAL,$nVal);
    }
    public function CostTotalPerPkg($nVal=NULL) {
	throw new exception('CostTotalPerPkg() is deprecated; use CostMaxPerPkg().');
    }
    public function CostMaxPerPkg($nVal=NULL) {
	return $this->FieldValue_forIndex(KI_CART_CALC_PER_PKG_TOTAL,$nVal);
    }
    public function CostShipTotal($nVal=NULL) {
	return $this->FieldValue_forIndex(KI_CART_CALC_SHIP_TOTAL,$nVal);
    }
    public function CostTotalFinal($nVal=NULL) {
	return $this->FieldValue_forIndex(KI_CART_CALC_FINAL_TOTAL,$nVal);
    }
    public function BuyerName() {
	return $this->PayCardName();
    }
    public function PayCardName() {
	return $this->FieldValue_forIndex(KI_CART_PAY_CARD_NAME);
    }
    public function RecipName() {
	return $this->FieldValue_forIndex(KI_CART_RECIP_NAME);
    }
    public function RecipAddr_text() {
	$oRecip = $this->RecipFields();
	return $oRecip->Addr_AsText();
    }
    public function ShipMsg() {
	return $this->FieldValue_forIndex(KI_CART_RECIP_MESSAGE);
    }
    /*----
      USED BY: cart->CheckFormInput() - for calculating shipping totals
    */
    public function ShipZone_code() {
	return $this->FieldValue_forIndex(KI_CART_SHIP_ZONE);
    }

    // -- SPECIFIC FIELD VALUES -- //
    // ++ NON-DATA FIELD ACCESS ++ //

    public function CartID($id=NULL) {
	if (!is_null($id)) {
	    $this->idCart = $id;
	}
	return $this->idCart;
    }

    // ++ FIELD VALUE COLLECTIONS ++ //

    public function CardAddrBlank() {
	return $this->PayFields()->AddrIsBlank();
    }
      // ++ objects

    /*====
      USED BY: cart-to-order conversion in Cart class
      HISTORY:
	2013-02-25 removing $iAsPayee and code which depends on it -- not adequately documented,
	  and conflicts with what I'm trying to do at the moment.
	  It only seems to be used by BillObj(); does anything still call that?
	2014-02-02 made these protected, in order to see if anything else is actually using them.
	2014-02-09 complete rewrite of CustObj(), ShipObj(), PayObj() as BuyerObj(), RecipObj()
	  Moved from table class to records class
	2014-02-16 renamed *Obj() -> *Fields()
    */

    public function BuyerFields() {
	return new clsPerson_Buyer($this);
    }
    public function RecipFields() {
	return new clsPerson_Recip($this);
    }
    public function PayFields() {
	return new clsPayment($this);
    }

    // -- FIELD VALUE COLLECTIONS -- //
}

/*%%%%
  PURPOSE: This class is for handling the mapping of a set of cart-var fields (rows) onto a set of
    common names, so that we can use the same code to handle (for example) customer billing addresses
    and recipient shipping addresses.
*/
abstract class clsCartDataGrp {
    private $arMap;
    private $rsVals;
    //protected $arIdxs;

    // ++ SETUP ++ //

    /*----
      INPUT:
	$arMap[suffix name] = cart var index to use
	$rsVals = recordset of the data rows to be loaded
    */
    public function __construct(array $arMap, clsCartVar $rsVals) {
	$this->MapArray($arMap);
	$this->ValueRecords($rsVals);
    }

    // -- SETUP -- //
    // ++ OBJECT ACCESS ++ //

    protected function ValueRecords(clsCartVar $rsVals=NULL) {
	if (!is_null($rsVals)) {
	    $this->rsVals = $rsVals;
	}
	return $this->rsVals;
    }
    /*----
      INPUT:
	$arMap[suffix name] = clsFormIndex object
    */
    protected function MapArray(array $arMap=NULL) {
	if (!is_null($arMap)) {
	    $this->arMap = $arMap;
	}
	return $this->arMap;
    }
    protected function MapArray_add(array $arMap) {
	$this->arMap = array_merge($this->arMap,$arMap);
    }

    // -- OBJECT ACCESS -- //
    // ++ FIELD VALUE ACCESS ++ //

    /*----
      PUBLIC so checkout Page can retrieve names to use for form fields
    */
    public function NameForSuffix($sSfx) {
	$ar = $this->MapArray();	// get map array
	$sFld = $ar[$sSfx];		// get cart field name
	if (empty($sFld)) {
	    throw new exception('No field name found for suffix "'.$sSfx.'".');
	}
	return $sFld;
    }
    /*----
      RETURNS: The cart value for the given suffix
    */
    protected function ValueForSuffix($sSfx) {
	$sFld = $this->NameForSuffix($sSfx);
	return $this->ValueRecords()->FieldValue_forName($sFld);
    }

    // -- FIELD VALUE ACCESS -- //
}

abstract class clsPerson extends clsCartDataGrp {
    /*----
      FIELDS NEEDED:
	0: Name
	1: Street
	2: Town
	3: State
	4: Zip
	5: Ctry
	6: Email
	7: Phone
    */

    // ++ STATUS ACCESS ++ //

    /*----
      PUBLIC so checkout page can discover whether an email address is available or not
    */
    public function DoEmail() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_EMAIL));
    }
    /*----
      PUBLIC so checkout page can discover whether a phone number is available or not
    */
    public function DoPhone() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_PHONE));
    }
/*
    protected function DoStreet() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_STREET));
    }
*/
    protected function DoTown() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_CITY));
    }
    protected function DoState() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_STATE));
    }
    protected function DoZipcode() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_ZIP));
    }
    protected function DoCountry() {
	return !is_null($this->ValueForSuffix(_KSF_CART_SFX_CONT_COUNTRY));
    }

    // -- STATUS ACCESS -- //
    // ++ FIELD COLLECTIONS ++ //

    public function NameValue() {
	return $this->ValueForSuffix(_KSF_CART_SFX_CONT_NAME);
    }
    public function StreetValue() {
	return $this->ValueForSuffix(_KSF_CART_SFX_CONT_STREET);
    }
    public function TownValue() {
	return $this->ValueForSuffix(_KSF_CART_SFX_CONT_CITY);
    }
    public function StateValue() {
	return $this->ValueForSuffix(_KSF_CART_SFX_CONT_STATE);
    }
    public function ZipcodeValue() {
	return $this->ValueForSuffix(_KSF_CART_SFX_CONT_ZIP);
    }
    public function CountryValue() {
	return $this->ValueForSuffix(_KSF_CART_SFX_CONT_COUNTRY);
    }
    abstract public function DirectionsValue();

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: address (not including name) basically unformatted, in a single line
	Ready to be stripped down for search, but not actually stripped down yet.
    */
    public function Addr_forSearch($doUseName) {
	$objZone = $this->ZoneObj();

	$strAddr = NULL;
	if ($doUseName) {
	    $strAddr = $this->NameValue();
	}
	$strAddr .=
	  $this->StreetValue()
	  .' '.$this->TownValue()
	  .' '.$this->StateValue()
	  .' '.$this->ZipcodeValue();
	if (!$objZone->isDomestic()) {
	    $strAddr .= ' '.$this->CountryValue();
	}

	return $strAddr;
    }
    /*----
      RETURNS: Same as Addr_forSearch(), but massaged for searching:
	* all chars lowercase
	* all blank and CRLF sequences condensed to a single space
    */
    public function Addr_forSearch_stripped($doUseName) {
	$s = $this->Addr_forSearch($doUseName);
	return strtolower(xtString::_ReplaceSequence($s, " \t\n\r", ' '));
    }

    /*----
      RETURNS: Main address as single string, in multiple lines
      HISTORY:
	2012-01-11 extracted from AsText() so instructions can be left out of search
	2012-05-27 moved to clsPerson
    */
    public function Addr_AsText($iLineSep="\n") {
	$xts = new xtString($this->StreetValue(),TRUE);
	$xts->ReplaceSequence(chr(8).' ',' ',0);		// replace any blank sequences with single space
	$xts->ReplaceSequence(chr(10).chr(13),$iLineSep,0);	// replace any sequences of newlines with line sep string

	$xts->Value .= $iLineSep.$this->TownValue();
	if ($this->DoState()) {
	    $xts->Value .= ', '.$this->StateValue();
	}
	if ($this->DoZipcode()) {
	    $xts->Value .= ' '.$this->ZipcodeValue();
	}
	if ($this->DoCountry()) {
	    $xts->Value .= ' '.$this->CountryValue();
	}
	return $xts->Value;
    }
    public function AddrIsBlank() {
	if ($this->NameValue() == '') {
	    if ($this->StreetValue() == '') {
		if ($this->TownValue() == '') {
		    if ($this->StateValue() == '') {
			if ($this->ZipcodeValue() == '') {
			    if ($this->ZoneObj()->isDomestic()) {
				return TRUE;
			    } else {
				if ($this->CountryValue() == '') {
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

    // -- FIELD CALCULATIONS -- //
    // ++ DATA OBJECT ACCESS ++ //

    protected function ZoneObj() {
	$objZone = new clsShipZone();
	$objZone->Set_fromName($this->CountryValue());
	return $objZone;
    }

    // -- DATA OBJECT ACCESS -- //
    // ++ ACTIONS ++ //

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
	$shipZone	= $iCart->GetFormItem(KSF_CART_RECIP_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	$custShipToSelf	= $iCart->GetFormItem(KSF_SHIP_TO_SELF);
	$custShipIsCard	= $iCart->GetFormItem(KSF_SHIP_IS_CARD);
	$custName	= $iCart->GetFormItem(KSF_CART_RECIP_NAME);
	$custStreet	= $iCart->GetFormItem(KSF_CART_RECIP_STREET);
	$custCity	= $iCart->GetFormItem(KSF_CART_RECIP_CITY);
	$custState	= $iCart->GetFormItem(KSF_CART_RECIP_STATE);
	$custZip	= $iCart->GetFormItem(KSF_CART_RECIP_ZIP);
	$custCountry	= $iCart->GetFormItem(KSF_CART_RECIP_COUNTRY);
	$custEmail	= $iCart->GetFormItem(KSF_CART_RECIP_EMAIL);
	$custPhone	= $iCart->GetFormItem(KSF_CART_RECIP_PHONE);
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
	if (!$objVars->IsShipToCard()) {
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

class clsPerson_Buyer extends clsPerson {
    // ++ SETUP ++ //

    /*----
      INPUT:
	$rsVals = recordset of the data rows to be loaded
    */
    public function __construct(clsCartVar $rsVals) {
    /*----
      FIELDS NEEDED:
	0: Name
	1: Street
	2: Town
	3: State
	4: Zip
	5: Ctry
	6: Email
	7: Phone
	8: Entry Type
    */

	// this seems to be a list of all the fields needed for this group
	$arMap = array(
	  _KSF_CART_SFX_CONT_NAME	=> KSF_CART_PAY_CARD_NAME,
	  _KSF_CART_SFX_CONT_STREET	=> KSF_CART_PAY_CARD_STREET,
	  _KSF_CART_SFX_CONT_CITY	=> KSF_CART_PAY_CARD_CITY,
	  _KSF_CART_SFX_CONT_STATE	=> KSF_CART_PAY_CARD_STATE,
	  _KSF_CART_SFX_CONT_ZIP	=> KSF_CART_PAY_CARD_ZIP,
	  _KSF_CART_SFX_CONT_COUNTRY	=> KSF_CART_PAY_CARD_COUNTRY,
	  _KSF_CART_SFX_CONT_EMAIL	=> KSF_CART_BUYER_EMAIL,
	  _KSF_CART_SFX_CONT_PHONE	=> KSF_CART_BUYER_PHONE,
	  _KSF_CART_SFX_CONT_INTYPE	=> KSF_CART_PAY_CARD_INTYPE,
	  );
	  parent::__construct($arMap,$rsVals);
    }

    // -- SETUP -- //

    public function DirectionsValue() {
	return NULL;	// not currently supported for buyer
    }
}

class clsPerson_Recip extends clsPerson {
    // ++ SETUP ++ //

    /*----
      INPUT:
	$rsVals = recordset of the data rows to be loaded
    */
    public function __construct(clsCartVar $rsVals) {
	// $arMap[suffix name] = cart var index to use
	$arMap = array(
	  _KSF_CART_SFX_CONT_NAME	=> KSF_CART_RECIP_NAME,
	  _KSF_CART_SFX_CONT_STREET	=> KSF_CART_RECIP_STREET,
	  _KSF_CART_SFX_CONT_CITY	=> KSF_CART_RECIP_CITY,
	  _KSF_CART_SFX_CONT_STATE	=> KSF_CART_RECIP_STATE,
	  _KSF_CART_SFX_CONT_ZIP	=> KSF_CART_RECIP_ZIP,
	  _KSF_CART_SFX_CONT_COUNTRY	=> KSF_CART_RECIP_COUNTRY,
	  _KSF_CART_SFX_CONT_EMAIL	=> KSF_CART_RECIP_EMAIL,
	  _KSF_CART_SFX_CONT_PHONE	=> KSF_CART_RECIP_PHONE,
	  _KSF_CART_SFX_CONT_INTYPE	=> KSF_CART_RECIP_CONT_INTYPE,
	  );
	  parent::__construct($arMap,$rsVals);
    }

    // -- SETUP -- //

    public function DirectionsValue() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_RECIP_MESSAGE);
    }
}

class clsPayment extends clsPerson_Buyer {
    //private $objCust;

    public function __construct(clsCartVar $rsVals) {
	// $arMap[suffix name] = cart var index to use
	$arMap = array(
	  KSF_CART_PAY_CARD_NAME		=> KSF_CART_PAY_CARD_NAME,
	  KSF_CART_PAY_CARD_ENCR		=> KSF_CART_PAY_CARD_ENCR,
	  KSF_CART_PAY_CARD_NUM			=> KSF_CART_PAY_CARD_NUM,
	  KSF_CART_PAY_CARD_EXP			=> KSF_CART_PAY_CARD_EXP,
	  );
	parent::__construct($rsVals);
	$this->MapArray_add($arMap);
    }
    // ++ FIELD COLLECTIONS ++ //
/*
    protected function FieldsToReturn() {
	return array(
	  KSF_CART_PAY_CARD_NAME,
	  KSF_CART_PAY_CARD_ENCR,
	  KSF_CART_PAY_CARD_NUM	,
	  KSF_CART_PAY_CARD_EXP,
	  );
    }
*/
    // -- FIELD COLLECTIONS -- //
    // ++ FIELD ACCESS ++ //

    public function CardNumValue() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_PAY_CARD_NUM);
    }
    public function CardExpValue() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_PAY_CARD_EXP);
    }
    public function CardAddrValue() {
	return $this->Addr_AsText();
    }
    public function CardNameValue() {
	return $this->NameValue();
    }

    // -- FIELD ACCESS -- //

    /*----
      ASSUMES: $arIdxs is in a particular order
    */
/*
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
*/
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
