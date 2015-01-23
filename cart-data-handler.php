<?
/*
  HISTORY:
    2014-09-18 Split off from cart-data.php
*/

/*%%%%
  PURPOSE: This class is for handling the mapping of a set of cart-var fields (rows) onto a set of
    common names, so that we can use the same code to handle (for example) customer billing addresses
    and recipient shipping addresses.
*/
abstract class clsCartDataGrp {
    private $arMap;
    private $rsVals;
    private $arVals;

    // ++ SETUP ++ //

    /*----
      INPUT:
	$arMap[suffix name] = cart var index to use
	$rsVals = recordset of the data rows to be loaded
    */
    public function __construct(clsCartVar $rsVals,array $arMap) {
	$this->MapArray($arMap);
	$this->ValueRecords($rsVals);
	$this->LoadCartData();
    }

    // -- SETUP -- //
    // ++ APP FRAMEWORK ACCESS ++ //

    protected function Engine() {
	return $this->ValueRecords()->Table()->Engine();
    }

    // -- APP FRAMEWORK ACCESS -- //
    // ++ INTERNAL FIELD ACCESS ++ //

    /*----
      INPUT:
	$arMap[suffix name] = cart data field name
    */
    protected function MapArray(array $arMap=NULL) {
	if (!is_null($arMap)) {
	    $this->arMap = $arMap;
	}
	return $this->arMap;
    }
    /* 2014-09-19 This should be redundant.
    protected function MapArray_add(array $arMap) {
	return
	$this->arMap = array_merge($this->arMap,$arMap);
    }
    */
    /*----
      ACTION: Load data from cart, mapping to internal fields
	based on MapArray().
      HISTORY:
	2014-11-13 This was loading values into $arData[$sSfx], but actually
	  we need them loaded into $arData[$sFld] when the data is from a new
	  entry. If there are other contexts where we need $sSfx, then something
	  needs rethinking. Changed $sSfx to $sFld.
    */
    protected function LoadCartData() {
	$arData = NULL;
	$arMap = $this->MapArray();
	$rsData = $this->ValueRecords();
	foreach ($arMap as $sSfx => $sFld) {
	    $sVal = $rsData->FieldValue_forName($sFld);
	    $arData[$sFld] = $sVal;
	    //echo "LOADING FLD=[$sFld] SFX=[$sSfx]<br>";
	}
	$this->arVals = $arData;

    }
    /*----
      ACTION: Save all memory values back to cart data records.
      USAGE: This seems to ONLY be needed after copying Recip data to Buyer.
      USED BY: CartDataRecord::CopyShipToCust()
      HISTORY:
	2014-11-10 Created because Buyer data needed saving after being cloned from Recip.
    */
    protected function SaveCartData() {
	$arMap = $this->MapArray();
	$rsData = $this->ValueRecords();

	foreach ($arMap as $sSfx => $sFld) {
	    $nIdx = $rsData->IndexFromName($sFld);	// get the index for this field
	    $sVal = $this->Value_forName($sFld);	// get the memory value for it
	    $rsData->FieldValue_forIndex($nIdx,$sVal);	// save it to the cart (method writes to disk)
	}
    }

    // -- INTERNAL FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function AddressTable($id=NULL) {
	return $this->ValueRecords()->AddrTable($id);
    }
    protected function CustCardTable($id=NULL) {
	return $this->ValueRecords()->CardTable($id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    protected function ValueRecords(clsCartVar $rsVals=NULL) {
	if (!is_null($rsVals)) {
	    $this->rsVals = $rsVals;
	}
	return $this->rsVals;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ FIELD VALUE ACCESS ++ //

    protected function Value_forName($sName,$sVal=NULL) {
	if (!is_null($sVal)) {
	    $this->arVals[$sName] = $sVal;
	}
	return clsArray::Nz($this->arVals,$sName);
    }
    /*----
      PUBLIC so checkout Page can retrieve names to use for form fields
    */
    public function Name_forSuffix($sSfx) {
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
    protected function Value_forSuffix($sSfx) {
	$sFld = $this->Name_forSuffix($sSfx);
	$sVal = $this->Value_forName($sFld);
	return $sVal;
    }

    // -- FIELD VALUE ACCESS -- //
    // ++ DEBUGGING ++ //

    public function DumpRecordValues() {
	return $this->ValueRecords()->DumpVals();
    }
    public function DumpLoadedValues() {
	$out = "<ul>";
	if (is_array($this->arVals)) {
	    foreach($this->arVals as $key => $val) {
		$out .= "\n<li>[$key] => ";
		if (is_object($val)) {
		    $out .= '('.get_class($val).')';
		} else {
		    $out .= "[$val]";
		}
		$out .= '</li>';
	    }
	} else {
	    $out .= '<i>no values loaded</i>';
	}
	return $out.'</ul>';
    }
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
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_EMAIL));
    }
    /*----
      PUBLIC so checkout page can discover whether a phone number is available or not
    */
    public function DoPhone() {
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_PHONE));
    }
/*
    protected function DoStreet() {
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_STREET));
    }
*/
    protected function DoTown() {
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_CITY));
    }
    protected function DoState() {
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_STATE));
    }
    protected function DoZipcode() {
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_ZIP));
    }
    protected function DoCountry() {
	return !is_null($this->Value_forSuffix(_KSF_CART_SFX_CONT_COUNTRY));
    }

    // -- STATUS ACCESS -- //
    // ++ FIELD COLLECTIONS ++ //

    public function NameValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_NAME);
    }
    public function StreetValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_STREET);
    }
    public function TownValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_CITY);
    }
    public function StateValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_STATE);
    }
    public function ZipcodeValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_ZIP);
    }
    public function CountryValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_COUNTRY);
    }
    public function EmailValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_EMAIL);
    }
    public function PhoneValue() {
	return $this->Value_forSuffix(_KSF_CART_SFX_CONT_PHONE);
    }
    abstract public function DirectionsValue();

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      ACTION: Return a description of the payment in a safe format
	(incomplete credit card number)
      NOTE: The *calculated* safe display is only stored once the Order is created,
	though this could change.
    */
    public function SafeDisplay() {
	$out = clsCustCards::SafeDescr_Long($this->CardNumValue(),$this->CardExpValue());
	return $out;
    }
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
    /*----
      RETURNS: Contents as a segmented string
      NOTE: This should probably be renamed "Serialize" OSLT. Otherwise it sounds
	like it's meant to be human-readable.
    */
    public function AsString($iString=NULL) {
	if (!is_null($iString)) {
	    clsModule::LoadFunc('Xplode');
	    $arStr = Xplode($iString);
	    $this->NameValue($arStr[0]);
	    $this->StreetValue($arStr[1]);
	    $this->TownValue($arStr[2]);
	    $this->StateValue($arStr[3]);
	    $this->ZipcodeValue($arStr[4]);
	    if (count($arStr) > 5) {
		$this->CountryValue($arStr[5]);
	    }
	}
	$out = "\t".$this->NameValue()
	  ."\t".$this->StreetValue()
	  ."\t".$this->TownValue()
	  ."\t".$this->StateValue()
	  ."\t".$this->ZipcodeValue();
	if (!$this->ZoneObj()->isDomestic()) {
	    $out .= "\t".$this->CountryValue();
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

    /*----
      OVERRIDE: Check to see if data should be retrieved from Cart
	or from pre-existing customer records.
    */
    protected function LoadCartData() {
	if ($this->UseExisting()) {
	    $this->LoadExisting();
	} else {
	    parent::LoadCartData();
	}
    }
    abstract protected function UseExisting();	// use pre-existing record for data?
    abstract protected function LoadExisting();	// load the pre-existing record

    // -- ACTIONS -- //
    // ++ WEB CHECKOUT UI ++ //

    /*----
      TODO: This could probably use a more descriptive function name,
	once I understand better exactly what it's for.
      HISTORY:
	2014-10-07 Adapting back to clsPerson after (partially?) adapting it for Checkout Page class
    */
    public function RenderPerson(array $arOptions) {
//	if (count($arOptions) < 20) {
//	    echo 'OPTIONS RECEIVED:<pre>'.print_r($arOptions,TRUE).'</pre>';
//	    throw new exception('Some options were not defined.');
//	}

	$htBefAddr	= $arOptions['ht.before.addr'];
	$htAftAddr	= $arOptions['ht.after.addr'];
	$htAftState	= $arOptions['ht.after.state'];
	$htShipCombo	= $arOptions['ht.ship.combo'];
	$strZipLbl	= $arOptions['str.zip.label'];
	$strStateLbl	= $arOptions['str.state.label'];
	$lenStateInp	= $arOptions['len.state.inp'];	// "length" attribute to use for user input field for "state"
	$doFixedCtry 	= clsArray::Nz($arOptions,'do.fixed.ctry',TRUE);
	$doFixedName 	= clsArray::Nz($arOptions,'do.fixed.name',TRUE);
	$doFixedAll	= clsArray::Nz($arOptions,'do.fixed.all',TRUE);
	$doShipZone	= clsArray::Nz($arOptions,'do.ship.zone');

// copy calculated stuff over to variables to make it easier to insert in formatted output:
	$ksName		= $this->Name_forSuffix(_KSF_CART_SFX_CONT_NAME);
	$ksStreet	= $this->Name_forSuffix(_KSF_CART_SFX_CONT_STREET);
	$ksCity		= $this->Name_forSuffix(_KSF_CART_SFX_CONT_CITY);
	$ksState	= $this->Name_forSuffix(_KSF_CART_SFX_CONT_STATE);
	$ksZip		= $this->Name_forSuffix(_KSF_CART_SFX_CONT_ZIP);
	$ksCountry	= $this->Name_forSuffix(_KSF_CART_SFX_CONT_COUNTRY);
	$ksEmail	= $this->Name_forSuffix(_KSF_CART_SFX_CONT_EMAIL);
	$ksPhone	= $this->Name_forSuffix(_KSF_CART_SFX_CONT_PHONE);

	$strName	= $this->NameValue();
	$strStreet	= $this->StreetValue();
	$strCity	= $this->TownValue();
	$strState	= $this->StateValue();
	$strZip		= $this->ZipcodeValue();
	$strCountry	= $this->CountryValue();

	$doEmail	= $this->DoEmail();
	$doPhone	= $this->DoPhone();

	$strEmail = $strPhone = NULL;
	if ($doEmail) {
	    $strEmail	= $this->EmailValue();
	}
	if ($doPhone) {
	    $strPhone	= $this->PhoneValue();
	}

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
    public function RenderAddress(array $arOpts, clsShipZone $oZone) {
	if (!is_object($oZone)) {
	    throw new exception('Could not retrieve Shipping Zone object in RenderAddress().');
	}

	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";

	if (isset($this->strInsteadOfAddr)) {
	    $out .= '<tr><td colspan=2>'.$this->strInsteadOfAddr.'</td></tr>';
	} else {
	    $strStateLabel = $oZone->StateLabel();	// "province" etc.
	    if ($oZone->hasState()) {
		$htStateAfter = '';
		$lenStateInput = 3;
		// later, we'll also include how many chars the actual abbr is and use this to say "(use n-letter abbreviation)"
	    } else {
		$htStateAfter = ' (if needed)';
		$lenStateInput = 10;
	    }

	    $isCountryFixed = FALSE;
	    if (empty($strCountry)) {
		if (!$oZone->isDomestic()) {
		    // this code cannot possibly work; it will need rewriting
		    $idxCountry = $oAddr->CountryNode()->DataType();
		    $this->DataItem($idxCountry,$oZone->Country());
		}
		$isCountryFixed = !empty($strCountry);
	    }

	    $hrefForSpam = '<a href="'.KWP_WIKI_PUBLIC.'Anti-Spam_Policy">';
	    $arOpts = array_merge($arOpts,array(
	      'ht.before.addr'	=> NULL,	// for now - was not being used
	      'ht.after.addr'	=> nz($this->htmlAfterAddress),
	      'ht.after.email'	=> $hrefForSpam.'anti-spam policy</a>',
	      'ht.ship.combo'	=> $oZone->ComboBox(),
	      'ht.after.state'	=> $htStateAfter,
	      'str.zip.label'	=> $oZone->PostalCodeName(),	// US='Zip Code', otherwise="postal code"
	      'str.state.label'	=> $strStateLabel,
	      'len.state.inp'	=> $lenStateInput,
	      //'do.fixed.all'	=> $this->doFixedCard,	// TRUE = disable editing
	      //'do.fixed.name'	=> $this->doFixedName,
	      //'do.fixed.ctry'	=> $this->doFixedCountry,
	      ));

	    $out .= $this->RenderPerson($arOpts);
	}

	if (isset($this->msgAfterAddr)) {
	    $out .= '<td colspan=2>'.$this->msgAfterAddr.'</td>';
	}

	return $out;
    }

    // -- WEB CHECKOUT UI -- //
}

class clsPerson_Buyer extends clsPerson {

    // ++ SETUP ++ //

    /*----
      INPUT:
	$rsVals = recordset of the data rows to be loaded
    */
    public function __construct(clsCartVar $rsVals,array $arMap=NULL) {
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
	$arMapAdd = array(
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
	  $arMap = clsArray::Merge($arMapAdd,$arMap);
	  parent::__construct($rsVals,$arMap);
    }

    // -- SETUP -- //
    // ++ ACTIONS ++ //

    /*----
      RETURNS: TRUE iff the user has chosen to use a pre-existing record
    */
    protected function UseExisting() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_PAY_CARD_INTYPE) == KS_FORM_INTYPE_EXISTING;
    }
    /*----
      RETURNS: ID of existing pay-card record chosen by user
    */
    protected function ExistingChoiceID() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_PAY_CARD_CHOICE);
    }
    /*----
      ACTION: Loads data from chosen pre-existing record into local fields
      FIELDS:
	card number
	card expiry
	cardholder name
	billing street address
	billing city
	billing state
	billing zipcode
	billing country
    */
    protected function LoadExisting() {
	$idCard = $this->ExistingChoiceID();
	$rcCard = $this->CustCardTable($idCard);
	$rcAddr = $rcCard->AddressRecord();

	// get pre-existing data
	  // - card numbers etc.
	$sCardNum	= $rcCard->NumberRaw();
	$sCardExp	= $rcCard->ExpiryRaw();
	$sName		= $rcCard->OwnerName();
	  // - card billing address
	$sStreet	= $rcAddr->StreetString();
	$sTown		= $rcAddr->TownString();
	$sState		= $rcAddr->StateString();
	$sZCode		= $rcAddr->ZipCodeString();
	$sCtry		= $rcAddr->CountryString();
	//$sMsg = $rcAddr->MessageString();	// TODO: this doesn't exist yet

	// load pre-existing customer data into memory
	  // load card numbers etc.
	$this->Value_forName(KSF_CART_PAY_CARD_NUM,$sCardNum);
	$this->Value_forName(KSF_CART_PAY_CARD_EXP,$sCardExp);
	$this->Value_forName(KSF_CART_PAY_CARD_NAME,$sName);
	  // load card billing address
	$this->Value_forName(KSF_CART_PAY_CARD_STREET,$sStreet);
	$this->Value_forName(KSF_CART_PAY_CARD_CITY,$sTown);
	$this->Value_forName(KSF_CART_PAY_CARD_STATE,$sState);
	$this->Value_forName(KSF_CART_PAY_CARD_ZIP,$sZCode);
	$this->Value_forName(KSF_CART_PAY_CARD_COUNTRY,$sCtry);
/*
	// store relevant values in cart's memory, which is the data source for this object
	$rcCD = $this->ValueRecords();
	  // save card numbers etc.
	$rcCD->SetCartValue(KI_CART_PAY_CARD_NUM,$sCardNum);
	$rcCD->SetCartValue(KI_CART_PAY_CARD_EXP,$sCardExp);
	$rcCD->SetCartValue(KI_CART_PAY_CARD_NAME,$sName);
	  // save card billing address
	$rcCD->SetCartValue(KI_CART_PAY_CARD_STREET,$sStreet);
	$rcCD->SetCartValue(KI_CART_PAY_CARD_CITY,$sTown);
	$rcCD->SetCartValue(KI_CART_PAY_CARD_STATE,$sState);
	$rcCD->SetCartValue(KI_CART_PAY_CARD_ZIP,$sZCode);
	$rcCD->SetCartValue(KI_CART_PAY_CARD_COUNTRY,$sCtry);
	*/
    }

    // -- ACTIONS -- //
    // ++ UNKNOWN ++ //

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
	  parent::__construct($rsVals,$arMap);
    }

    // -- SETUP -- //
    // ++ DATA FIELD ACCESS ++ //

    public function DirectionsValue() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_RECIP_MESSAGE);
    }

    // -- DATA FIELD ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      RETURNS: TRUE iff the user has chosen to use a pre-existing record
    */
    public function UseExisting() {
 	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_RECIP_INTYPE) == KS_FORM_INTYPE_EXISTING;
    }
    /*----
      RETURNS: ID of existing address record chosen by user
    */
    protected function ExistingChoiceID() {
	return $this->ValueRecords()->FieldValue_forIndex(KI_CART_RECIP_CHOICE);
    }
    /*----
      ACTION: Loads data from chosen pre-existing record into local fields
	ship-to name
	ship-to street address
	ship-to city
	ship-to state
	ship-to zipcode
	ship-to country
    */
    protected function LoadExisting() {
	$idAddr = $this->ExistingChoiceID();
	$rcAddr = $this->AddressTable($idAddr);

	// get pre-existing data
	$sName = $rcAddr->NameString();
	$sStreet = $rcAddr->StreetString();
	$sTown = $rcAddr->TownString();
	$sState = $rcAddr->StateString();
	$sZCode = $rcAddr->ZipCodeString();
	$sCtry = $rcAddr->CountryString();
	//$sMsg = $rcAddr->MessageString();	// TODO: this doesn't exist yet

	// store pre-existing customer data in memory
	$this->Value_forName(KSF_CART_RECIP_NAME,$sName);
	$this->Value_forName(KSF_CART_RECIP_STREET,$sStreet);
	$this->Value_forName(KSF_CART_RECIP_CITY,$sTown);
	$this->Value_forName(KSF_CART_RECIP_STATE,$sState);
	$this->Value_forName(KSF_CART_RECIP_ZIP,$sZCode);
	$this->Value_forName(KSF_CART_RECIP_COUNTRY,$sCtry);
	/*
	// store relevant values in cart's memory, which is the data source for this object
	$rcCD = $this->ValueRecords();
	$rcCD->SetCartValue(KI_CART_RECIP_NAME,$sName);
	$rcCD->SetCartValue(KI_CART_RECIP_STREET,$sStreet);
	$rcCD->SetCartValue(KI_CART_RECIP_CITY,$sTown);
	$rcCD->SetCartValue(KI_CART_RECIP_STATE,$sState);
	$rcCD->SetCartValue(KI_CART_RECIP_ZIP,$sZCode);
	$rcCD->SetCartValue(KI_CART_RECIP_COUNTRY,$sCtry);
	//$rcCD->SetValue(KI_CART_RECIP_MESSAGE,$sMsg);
	*/
    }

    // -- ACTIONS -- //
}

class clsPayment extends clsPerson_Buyer {

    // ++ SETUP ++ //

    public function __construct(clsCartVar $rsVals) {
	// $arMap[suffix name] = cart var index to use
	$arMap = array(
	  KSF_CART_PAY_CARD_NAME		=> KSF_CART_PAY_CARD_NAME,
	  KSF_CART_PAY_CARD_ENCR		=> KSF_CART_PAY_CARD_ENCR,
	  KSF_CART_PAY_CARD_NUM			=> KSF_CART_PAY_CARD_NUM,
	  KSF_CART_PAY_CARD_EXP			=> KSF_CART_PAY_CARD_EXP,
	  );
	parent::__construct($rsVals,$arMap);
    }

    // -- SETUP -- //
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
	return $this->Value_forName(KSF_CART_PAY_CARD_NUM);
    }
    public function CardExpValue() {
	return $this->Value_forName(KSF_CART_PAY_CARD_EXP);
    }
    public function CardCVVValue() {
	return '';	// TODO: implement this
    }
    public function CardAddrValue() {
	return $this->Addr_AsText();
    }
    public function CardNameValue() {
	return $this->NameValue();
    }

    // -- FIELD ACCESS -- //

}
