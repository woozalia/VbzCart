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

define('KS_FORM_INTYPE_EXISTING','old');
define('KS_FORM_INTYPE_NEWENTRY','new');

/*%%%%
  RULES: call FieldRows(cart ID) to get a dataset for the given cart
    Use the dataset's access methods to look up row data.
*/
class clsCartVars extends clsTable_indexed {

    //protected $objCust, $objShip, $objPay;
    // object cache
    private $idCart;	// ID of cart currently loaded
    private $rsFlds;	// recordset of cart fields

    // ++ SETUP ++ //

    public function __construct($iDB) {
	$objIdx = new clsIndexer_Table_multi_key($this);
	$objIdx->KeyNames(array('ID_Cart','ID_Type'));

	$this->arChg = NULL;
	$this->idCart = NULL;
	$this->rsFlds = NULL;

	parent::__construct($iDB);
	  $this->Name('shop_cart_data');
	  $this->ClassSng('clsCartVar');
	  $this->Indexer($objIdx);
    }

    // -- SETUP -- //
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
	    $sql = 'SELECT ID_Type, Val FROM '.$this->NameSQL().' WHERE ID_Cart='.$idCart;
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
      HISTORY:
	2014-11-10 This will need fixing, because ShipObj() has been renamed or something.
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
}
class clsCartVar extends clsRecs_indexed {
    private $arInput;	// user data retrieved from form
    private $arValues;	// latest data values
    private $arChg;	// list of fields changed from what is in db
    private $idCart;	// ID of cart for current recordset

    // ++ SETUP ++ //

    protected function InitVars() {
	$this->arValues = NULL;
	$this->arChg = NULL;
	$this->idCart = NULL;
    }

    // -- SETUP -- //
    // ++ STATIC ++ //

    /*----
      WHAT: mapping from input form field names to cart data index numbers.
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
      KI_CART_PAY_CARD_INTYPE	=> KSF_CART_PAY_CARD_INTYPE,
      KI_CART_PAY_CARD_CHOICE	=> KSF_CART_PAY_CARD_CHOICE,
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
    // ++ FIELD CACHE/STATUS ++ //

    /*----
      ACTION: set stored value for the given index
      NOTE: Named SetCartValue() to distinguish CART values from RECORD values,
	e.g. as in standard recordset Value() and Values() methods.
    */
    protected function CartValues() {
	if (is_null($this->arValues)) {
	    $this->arValues = array();	// even if there's no data, we want to return an array
	    while ($this->NextRow()) {
		$nType = $this->TypeID();
		$this->SetCartValue($nType,$this->Value('Val'));
	    }
	}
	return $this->arValues;
    }
    /*----
      PURPOSE: This remains set even when no record is loaded.
    */
    protected function CartID_master() {
	return $this->idCart;
    }
    /*----
      RETURNS: Record object for the current Cart ID and given Type ID
    */
    protected function LoadType($idType) {
	$idCart = $this->CartID_master();
	$sqlFilt = "(ID_Cart=$idCart) AND (ID_Type=$idType)";
	$rc = $this->Table()->GetData($sqlFilt);
	$rc->NextRow();		// load the first/only row
	return $rc;
    }
    /*----
      PUBLIC so that clsPerson can call it to load existing data
    */
    public function SetCartValue($idx,$val) {
	$this->arValues[$idx] = $val;
    }
    /*----
      RETURNS: form input for the given index
    */
    protected function GetInput($idx) {
	return $this->arInput[$idx];
    }
    protected function SetInput($idx,$val) {
	$this->arInput[$idx] = $val;
    }
    protected function FlagChange($idx) {
	$this->arChg[$idx] = TRUE;
    }
    protected function ChangeList() {
	return $this->arChg;
    }
    protected function HasChanges() {
	return is_array($this->arChg);
    }
    public function FieldValue_forName($sFld) {
	$nIdx = self::IndexFromName($sFld);
	if ($nIdx > 0) {
	    return $this->FieldValue_forIndex($nIdx);
	} else {
	    if (is_object($sFld)) {
		throw new exception('Received object of type "'.get_class($sFld).'" for $sFld. Should be a string.');
	    } else {
		throw new exception("No index found for field name [$sFld].");
	    }
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
	$idCart = $this->CartID_master();
	if (is_null($idCart)) {
	    throw new exception('Internal error: trying to update cart data when cart ID is not set.');
	}
	$arVals = $this->CartValues();
	if (is_null($val)) {
	    if (array_key_exists($idType,$arVals)) {
		return $arVals[$idType];
	    } else {
		return NULL;
	    }
	} else {
	    $arChg = array(
	      'ID_Type'	=> $idType,
	      'Val'	=> SQLValue($val)
	      );

	    // check to see if assigned value represents a change from what is stored

	    if (array_key_exists($idType,$arVals)) {
		if ($arVals[$idType] != $val) {
		    $rcCart = $this->LoadType($idType);
		    $rcCart->Update($arChg);
		}
	    } else {
		$arChg['ID_Cart'] = $idCart;
		$this->Table()->Insert($arChg);
	    }
	    $this->arVals[$idType]=$val;		// update the loaded value
	    return $val;
	}
    }

    // -- FIELD CACHE/STATUS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Copy shipping address to buyer address
    */
    public function CopyShipToCust() {
	$sShip = $this->RecipFields()->AsString();
	$this->BuyerFields()->AsString($sShip);
	$this->BuyerFields->SaveCartData();	// write to database
    }
    /*----
      ACTION: Save all modified data
      NOTE: The data is presumably already loaded, in order to compare new and old values
	in order to determine which ones need updating/adding (stored in $arChg),
	so we *also* need to update the loaded data as we are saving it. (2013-04-03)
	There is almost certainly a better way to do this, e.g. if arValues[idx] is set,
	  then we should be able to assume the row exists, and not look it up.
	  This will need to be tested carefully, however. TODO
    */
    public function SaveCart() {
	if ($this->HasChanges()) {
	    $idCart = $this->idCart;
	    if (is_null($idCart)) {
		throw new exception('Attempting to save cart without loading it.');
	    }

	    // possibly this code, or part of it, should be a Table method
	    foreach ($this->ChangeList() as $idx => $on) {
		$sqlFilt = '(ID_Cart='.$idCart.') AND (ID_Type='.$idx.')';

		// are we inserting, or updating?
		$tbl = $this->Table();
		$rc = $tbl->GetData($sqlFilt.' LIMIT 1');
		$vIn = $this->GetInput($idx);		// new value from form
		$this->SetCartValue($idx,$vIn);	// save new value locally
		$sqlVal = SQLValue($vIn);		// new value in SQL-safe format
		if ($rc->HasRows()) {
		    $sql = 'UPDATE '.$tbl->Name().' SET Val='.$sqlVal.' WHERE '.$sqlFilt;
		    $this->Engine()->Exec($sql);
		} else {
		    $arIns = array(
		      'ID_Cart'	=> $idCart,
		      'ID_Type'	=> $idx,
		      'Val'	=> $sqlVal,
		      );
		   $tbl->Insert($arIns);
		}
	    }
	}
    }

    // -- ACTIONS -- //
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
	      KI_CART_RECIP_INTYPE,
	      KI_CART_RECIP_CHOICE,
	      KI_CART_RECIP_IS_BUYER,
	      KI_CART_SHIP_IS_CARD,		// is this redundant?
	      KI_CART_RECIP_NAME,
	      KI_CART_RECIP_STREET,
	      KI_CART_RECIP_CITY,
	      KI_CART_RECIP_STATE,
	      KI_CART_RECIP_ZIP,
	      KI_CART_RECIP_COUNTRY,
	      KI_CART_SHIP_ZONE,
	      KI_CART_RECIP_EMAIL,	// not actually used yet
	      KI_CART_RECIP_PHONE,	// not actually used yet
	      KI_CART_BUYER_EMAIL,
	      KI_CART_BUYER_PHONE,
	      KI_CART_RECIP_MESSAGE,
	      );
	    break;
	  case KSQ_PAGE_PAY:	// payment page
	    $arInUse = array(
	      KI_CART_PAY_CARD_INTYPE,
	      KI_CART_PAY_CARD_CHOICE,
	      KI_CART_PAY_CARD_NUM,
	      KI_CART_PAY_CARD_EXP,
	      KI_CART_PAY_CARD_NAME,
	      KI_CART_PAY_CARD_STREET,
	      KI_CART_PAY_CARD_CITY,
	      KI_CART_PAY_CARD_STATE,
	      KI_CART_PAY_CARD_ZIP,
	      KI_CART_PAY_CARD_COUNTRY,
	      KI_CART_PAY_CHECK_NUM,
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
	    $sName = self::$arFormIdxs[$index];	// get name as submitted by form
	    $valNew = NzArray($_POST,$sName);		// get value submitted by form
	    //$valOld = NzArray($this->arData,$index);
	    $valOld = $this->FieldValue_forIndex($index);
	    $this->SetInput($index,$valNew);
	    if ($valNew != $valOld) {
		$this->FlagChange($index);
		$this->SetCartValue($index,$valNew);
	    }
	}
    }

    // -- FORM PROCESSING -- //
    // ++ RECORD FIELD ACCESS ++ //

    public function TypeID() {
	return $this->Value('ID_Type');
    }

    // -- RECORD FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function CardsClass() {
	return 'clsCustCards_dyn';
    }
    protected function AddrsClass() {
	return 'clsCustAddrs';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    /*----
      PUBLIC so clsPerson can access it
    */
    public function CardTable($id=NULL) {
	return $this->Engine()->Make($this->CardsClass(),$id);
    }
    /*----
      PUBLIC so clsPerson can access it
    */
    public function AddrTable($id=NULL) {
	return $this->Engine()->Make($this->AddrsClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ CART DATA VALUES ++ //

      // ++ inter-group values

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
    public function IsRecipOldEntry() {
	$sInType = $this->FieldValue_forIndex(KI_CART_RECIP_INTYPE);
	return ($sInType == KS_FORM_INTYPE_EXISTING);
    }
    protected function RecipChoiceID() {
	return $this->FieldValue_forIndex(KI_CART_RECIP_CHOICE);
    }
    public function IsCardOldEntry() {
	$sInType = $this->FieldValue_forIndex(KI_CART_PAY_CARD_INTYPE);
	return ($sInType == KS_FORM_INTYPE_EXISTING);
    }
    protected function CardChoiceID() {
	return $this->FieldValue_forIndex(KI_CART_PAY_CARD_CHOICE);
    }
    public function BuyerEmailAddress_entered() {
	return $this->FieldValue_forIndex(KI_CART_BUYER_EMAIL);
    }
    public function BuyerPhoneNumber_entered() {
	return $this->FieldValue_forIndex(KI_CART_BUYER_PHONE);
    }

      // ++ group-specific values

    public function CardNumber() {
	if ($this->IsCardOldEntry()) {
	    return $this->CardRecord()->NumberRaw();
	} else {
	    return $this->FieldValue_forIndex(KI_CART_PAY_CARD_NUM);
	}
    }
    public function CardExpiry() {
	if ($this->IsCardOldEntry()) {
	    return $this->CardRecord()->ExpiryRaw();
	} else {
	    return $this->FieldValue_forIndex(KI_CART_PAY_CARD_EXP);
	}
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
	if ($this->IsCardOldEntry()) {
	    $sName = $this->CardRecord()->OwnerName();
	    return $sName;
	} else {
	    return $this->FieldValue_forIndex(KI_CART_PAY_CARD_NAME);
	}
    }
    public function RecipName() {
	if ($this->IsCardOldEntry()) {
	    $rcRecip = $this->RecipRecord();
	    if ($rcRecip->IsNew()) {
		throw new exception('No recipient record found; ID='.$this->RecipChoiceID());
	    } else {
		$sName = $rcRecip->NameString();
		if (is_null($sName)) {
		    throw new exception('Recipient record has no value for name. Record:<pre>'.print_r($rcRecip->Values,TRUE).'</pre>');
		}
		return $sName;
	    }
	} else {
	    return $this->FieldValue_forIndex(KI_CART_RECIP_NAME);
	}
    }
    public function BuyerAddr_text() {
	$oFlds = $this->BuyerFields();
	return $oFlds->Addr_AsText();
    }
    public function CardAddrBlank() {
	return $this->PayFields()->AddrIsBlank();
    }
    public function RecipAddr_text() {
	$oFlds = $this->RecipFields();
	return $oFlds->Addr_AsText();
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

    // -- CART DATA VALUES -- //
    // ++ NON-DATA FIELD ACCESS ++ //

    public function CartID($id=NULL) {
	if (!is_null($id)) {
	    $this->idCart = $id;
	}
	return $this->idCart;
    }

    // -- NON-DATA FIELD ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    protected function RecipRecord() {
	$idAddr = $this->RecipChoiceID();
	$rcAddr = $this->AddrTable($idAddr);
	return $rcAddr;
    }
    protected function CardRecord() {
	$idCard = $this->CardChoiceID();
	if (is_null($idCard)) {
	    throw new exception('Attempting to look up record for NULL card choice.');
	}
	$rcCard = $this->CardTable($idCard);
	return $rcCard;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ FIELD COLLECTIONS ++ //

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

    // -- FIELD COLLECTIONS -- //
    // ++ DEBUGGING ++ //

    public function DumpVals() {
	$out = NULL;
	$arVals = $this->CartValues();
	if (count($arVals) == 0) {
	    $out = 'NO CART DATA<br>';
	} else {
	    foreach($arVals as $key => $val) {
		$out .= "KEY=[$key] VAL=[$val]<br>";
	    }
	}
	return $out;
    }
    /*----
      ACTION: Dump all loaded cart values in HTML
      RETURNS: text of dump, ready to display
      TODO: This no longer works, but it might be cannibalized
	to improve DumpVals().
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
