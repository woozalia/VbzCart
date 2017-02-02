<?php
/*
  PURPOSE: form classes for cart.xdata, just to keep things more manageable
    I may end up merging them back together later.
  HISTORY:
    2016-03-08 started
    2016-03-10 At this point, I'm not sure if this is going to be used, though bits of the code in it
      will probably be needed.
    2016-03-13 Gutted; moving some classes here (bits of code from here are probably included).
*/

/*%%%%
  PURPOSE: encapsulates a name and address, either for ship-to or pay-card
  REQUIRES:
    protected function ControlName_Prefix();
    protected function NameValue();
    protected function StreetValue();
    protected function TownValue();
    protected function StateValue();
    protected function ZipcodeValue();
    protected function CountryValue();
*/
trait vtCartData_NameAddress {

    // ++ FORM: CONTROL NAMES ++ //

    protected function FieldName_forContactName() {
	return $this->FieldName_Prefix().'addr-name';
    }
    protected function FieldName_forContactStreet() {
	return $this->FieldName_Prefix().'addr-street';
    }
    protected function FieldName_forContactCity() {
	return $this->FieldName_Prefix().'addr-city';
    }
    protected function FieldName_forContactState() {
	return $this->FieldName_Prefix().'addr-state';
    }
    protected function FieldName_forContactZipcode() {
	return $this->FieldName_Prefix().'addr-zip';
    }
    protected function FieldName_forContactCountry() {
	return $this->FieldName_Prefix().'addr-country';
    }
    
    // -- FORM: CONTROL NAMES -- //
    // ++ FIELD OBJECTS ++ //
   
    protected function NameField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactName()
	  );
	  
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>50));
	  $oCtrl->DisplayAlias('ship-to name');
	
	return $oField;
    }
    protected function StreetField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactStreet()
	  );

	$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>50));

	return $oField;
    }
    protected function TownField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactCity()
	  );
	  
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	
	return $oField;
    }
    protected function StateField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactState()
	  );
	  
	$lenState = $this->ShipZone()->StateLength();
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>$lenState));
	
	return $oField;
    }
    protected function ZipcodeField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactZipcode()
	  );

	$oCtrl = new fcFormControl_HTML($oField,array('size'=>11));
	
	return $oField;
    }
    protected function CountryField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactCountry()
	  );

	$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

	return $oField;
    }
    
    // ++ TEMPLATES ++ //
    
    protected function NameAddressTemplate() {
	$oZone = $this->ShipZone();
	
	$htStreet = $this->FieldName_forContactStreet();
	$htCity = $this->FieldName_forContactCity();
	$htState = $this->FieldName_forContactState();
	$htZip = $this->FieldName_forContactZipcode();
	
	$htAftState	= is_null($oZone->HasState())?'(if needed)':NULL;
	$sZipLbl	= $oZone->PostalCodeLabel();
	$sStateLbl	= $oZone->StateLabel();
	
	if ($oZone->IsDomestic()) {
	    $htCountryLine = NULL;
	} else {
	    $htCtry = $this->FieldName_forContactCountry();
	    $htCountryLine = "<tr><td align=right valign=middle>Country:</td><td>[[$htCtry]]</td></tr>";
	}

	$sTplt = <<<__END__
<table class="form-block" id="name-address">
    <tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td><td>[[$htStreet]]</td></tr>
    <tr><td align=right valign=middle>City:</td><td>[[$htCity]]</td></tr>
    <tr><td align=right valign=middle>$sStateLbl:</td><td>[[$htState]]$htAftState</td></tr>
    <tr><td align=right valign=middle>$sZipLbl:</td><td>[[$htZip]]</td></tr>
    $htCountryLine
</table>
__END__;
	return new fcTemplate_array('[[',']]',$sTplt);
    }
    
    // -- TEMPLATES -- //
    // ++ FORM I/O ++ //
    
    protected function InvokeNameAddressFields() {
	// invoke controls to add them to the form
	$this->NameField();
	$this->StreetField();
	$this->TownField();
	$this->StateField();
	$this->ZipcodeField();
	$this->CountryField();
    }
    protected function RenderNameAddress($doEdit) {
	$this->InvokeNameAddressFields();
	$oTplt = $this->NameAddressTemplate();
	$arCtrls = $this->FormObject()->RenderControls($doEdit);
	$oTplt->VariableValues($arCtrls);
	return $oTplt->RenderRecursive();
    }
    public function CaptureShipping() {
	// invoke controls to add them to the form
	$this->InvokeNameAddressFields();
	$arStat = $this->FormObject()->Receive($_POST);
	$arMissed = $arStat['absent'];
	$this->AddMissing($arMissed);
    }
    
    // -- FORM I/O -- //

}

/*%%%%
  REQUIRES: PhoneNumber(), EmailAddress(), FieldName_Prefix()
*/
trait vtCartData_EmailPhone {

    // ++ FIELD NAMES ++ //

    protected function FieldName_forContactPhone() {
	return $this->FieldName_Prefix().'addr-phone';
    }
    protected function FieldName_forContactEmail() {
	return $this->FieldName_Prefix().'addr-email';
    }
    
    // -- FIELD NAMES -- //
    // ++ FIELD OBJECTS ++ //

    protected function EmailField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactEmail()
	  );

	$oCtrl = new fcFormControl_HTML($oField,array('size'=>30));
	  $oCtrl->DisplayAlias('email address');

	return $oField;
    }
    protected function PhoneField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactPhone()
	  );

	$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	  $oCtrl->DisplayAlias('phone number');

	return $oField;
    }
    
    // -- FIELD OBJECTS -- //
    // ++ TEMPLATES ++ //
    
    protected function ContactTemplate() {
	$htEmail = $this->FieldName_forContactEmail();
	$htPhone = $this->FieldName_forContactPhone();
	$sTplt = <<<__END__
<table class="form-block" id="contact">
<tr><td align=right valign=middle>Email:</td><td>[[$htEmail]]</td></tr>
<tr><td align=right valign=middle>Phone:</td><td>[[$htPhone]]</td></tr>
</table>
__END__;
	return new fcTemplate_array('[[',']]',$sTplt);
    }
    
    // ++ FORM I/O ++ //
    
    /*----
      ACTION: Renders email/phone controls
    */
    public function RenderContact($doEdit) {
	// invoke controls to add them to the form
	$this->EmailField();
	$this->PhoneField();
	$oTplt = $this->ContactTemplate();
	$arCtrls = $this->FormObject()->RenderControls($doEdit);
	$oTplt->VariableValues($arCtrls);
	return $oTplt->RenderRecursive();
    }
    public function CaptureContact() {
	// invoke controls to add them to the form
	$ofEmail = $this->EmailField();
	$ofPhone = $this->PhoneField();
	$arStat = $this->FormObject()->Receive($_POST);
	/*
	$sEmail = $ofEmail->GetValue();
	if (is_null($sEmail)) {
	    $this->AddMissing('email');
	}*/
	$this->AddMissing($arStat['blank']);
    }
    
    // -- FORM I/O -- //

}
/*%%%%
  PURPOSE: Handles Buyer subforms:
    * email/phone
    * payment (name/address/details)
*/
class vcCartData_Buyer extends vcCartDataInstance {
    use vtCartData_NameAddress;
    use vtCartData_EmailPhone;
    
    // ++ TRAIT REQUIREMENTS ++ //
    
    protected function FieldName_Prefix() {
	return 'buyer-';
    }
    
    // -- TRAIT REQUIREMENTS -- //
}
/*%%%%
  PURPOSE: Handles Recipient subforms:
    * intype (choice of existing or entered address)
    * if existing: choice of address
    * if entered: name/address
    * instructions
*/
class vcCartData_Recip extends vcCartDataInstance {
    use vtCartData_NameAddress;

    const KS_INTYPE_EXISTING = 'old';
    const KS_INTYPE_NEWENTRY = 'new';

    private $oZone;
  
    public function __construct($sBlob=NULL, vcShipCountry $oZone) {
	parent::__construct($sBlob);
	$this->oZone = $oZone;
    }
    protected function ShipZone() {
	return $this->oZone;
    }

    // ++ TRAIT REQUIREMENTS ++ //
    
    protected function FieldName_Prefix() {
	return 'recip-';
    }
    protected function FieldName_forIntype() {
	return 'intype';
    }
    
    // -- TRAIT REQUIREMENTS -- //
    // ++ FIELD NAMES ++ //
    
    protected function FieldName_forInstructions() {
	return 'order-instrux';
    }
    
    // -- FIELD NAMES -- //
    // ++ FIELD OBJECTS ++ //

    protected function MessageField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forInstructions()
	  );

	$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>50));

	return $oField;
    }
    // ++ FORM I/O ++ //
    
    /*----
      ACTION: Shows multiple subforms:
	1. If editing and logged in: controls to select OLD or NEW name/address
	2. entered/chosen name/address:
	  if OLD:
	    If editing, shows drop-down for name/address choice
	    If not editing, shows read-only name/address form
	  if NEW: Shows name/address form (editable or not)
	3. Shows special instructions control
	
	...UNLESS user is not logged in, in which case we force NEW mode
	  but show log-in controls.
    */
    public function RenderShipping($doEdit) {
	$rcUser = $this->UserRecord();
	if ($rcUser->IsLoggedIn()) {
	    $sIntype = $this->GetValue($this->FieldName_forIntype());
	    $doOld = ($sIntype == self::KS_INTYPE_EXISTING);
	} else {
	    $doOld = FALSE;
	}
	$out = NULL;
	if ($doOld) {
	    $out .= $this->RenderShipping_OldMode($doEdit);
	} else {
	    $out .= $this->RenderShipping_NewMode($doEdit);
	}
	$out .= $this->MessageField()->ControlObject()->Render($doEdit);
	return $out;
    }
    /*----
      ACTION: Old Mode output depends on whether we're editing:
	* if editing: show the drop-down and [Enter New Address] button
	* if read-only: show the selected name/address
    */
    protected function RenderShipping_OldMode($doEdit) {
	$oUser = $this->UserRecord();
	if ($doEdit) {
		// allow user to select from existing recipient profiles
	    $rsCusts = $oUser->ContactRecords();
	    if ($rsCusts->RowCount() > 0) {
		$out = 
		  'Choose an address: '
		  .$rsCusts->Render_DropDown_Addrs(KSF_CART_RECIP_CONT_CHOICE)
		  .' or '
		  .'<input type=submit name=btnEnterAddress value="Enter New Address">'
		  ;
	    } else {
		$htCusts = 'You currently have no addresses saved in your profile.';
	    }
	} else {
	    // the pre-existing record values should have been copied to local data when the form was saved
	    $out = $this->RenderNameAddress(FALSE);
	}
	return $out;
    }
    protected function RenderShipping_NewMode($doEdit) {
	return
	  '<div align=center>Enter new address below or '
	  .'<input type=submit name=btnChooseAddress value="Select an Address"> from your profile'
	  .'</div>'
	  .$this->RenderNameAddress($doEdit);
    }
    
    // -- FORM I/O -- //
}

abstract class vcCartData_Contact_old extends vcCartDataInstance {

    // ++ FIELD VALUES ++ //
    
    abstract protected function PhoneNumber();
    abstract protected function EmailAddress();
    protected function HTML_AfterEmailAddress() {
	$href = '<a href="'.KWP_WIKI_PUBLIC.'Anti-Spam_Policy">';
	return $href.'anti-spam policy</a>';
    }
    
    // -- FIELD VALUES -- //
    protected function ControlName_forContactPhone() {
	return $this->ControlName_Prefix().'addr-phone';
    }
    protected function ControlName_forContactEmail() {
	return $this->ControlName_Prefix().'addr-email';
    }
    
    // -- FORM: CONTROL NAMES -- //
    // ++ FORM: RENDERING ++ //

    // these depend on what type of Contact is being rendered
    abstract protected function RenderOption_DoShipZone();
    abstract protected function RenderOption_DoEmail();
    abstract protected function RenderOption_DoPhone();

    protected function RenderContact() {
	$oZone = $this->ShipZone();
	$htAftState	= is_null($oZone->HasState())?'(if needed)':NULL;
	$htShipCombo	= $oZone->ComboBox();
	$strZipLbl	= $oZone->PostalCodeLabel();
	$strStateLbl	= $oZone->StateLabel();
	$lenStateInp	= $oZone->StateLength();	// "length" attribute to use for user input field for "state"
	// Not sure when these would need to be true; possibly on the Confirmation page.
	$doFixedCtry 	= FALSE;
	$doFixedName 	= FALSE;
	$doFixedAll	= FALSE;
	$doShipZone	= $this->RenderOption_DoShipZone();
	if ($doFixedAll) {
	    $doFixedCtry = TRUE;
	}

// copy calculated stuff over to variables to make it easier to insert in formatted output:
	$ksName		= $this->ControlName_forContactName();
	$ksStreet	= $this->ControlName_forContactStreet();
	$ksCity		= $this->ControlName_forContactCity();
	$ksState	= $this->ControlName_forContactState();
	$ksZip		= $this->ControlName_forContactZipcode();
	$ksCountry	= $this->ControlName_forContactCountry();
	$ksEmail	= $this->ControlName_forContactEmail();
	$ksPhone	= $this->ControlName_forContactPhone();

	$strName	= $this->NameValue();
	$strStreet	= $this->StreetValue();
	$strCity	= $this->TownValue();
	$strState	= $this->StateValue();
	$strZip		= $this->ZipcodeValue();
	$strCountry	= $this->CountryValue();

	$doEmail	= $this->RenderOption_DoEmail();
	$doPhone	= $this->RenderOption_DoPhone();

	$strEmail = $doEmail?$this->EmailAddress():NULL;
	$strPhone = $doPhone?$this->PhoneNumber():NULL;

	if ($doFixedCtry) {
	    $htCountry = '<b>'.$strCountry.'</b>';
	    $htZone = '';
	} else {
	    $htCountry = "<input name='$ksCountry' value='$strCountry' size=20>";
	    $htBtnRefresh = '<input type=submit name="update" value="Update Form">';
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
	if ($doFixedAll) {
	    $htStreet = "<b>$strStreet</b>";
	    $htCity = "<b>$strCity</b>";
	    $htState = "<b>$strState</b>";
	    $htZip = "<b>$strZip</b>";
	    $htCtry = "<b>$htCountry</b>$htZone";
	    $htEmail = "<b>$strEmail</b> (".$this->HTML_AfterEmailAddress().')';
	    $htPhone = "<b>$strPhone</b> (optional)";
	} else {
	    $htStreet = '<textarea name="'.$ksStreet.'" cols=50 rows=3>'.$strStreet.'</textarea>';
	    $htCity = '<input name="'.$ksCity.'" value="'.$strCity.'" size=20>';
	    $htState = '<input name="'.$ksState.'" value="'.$strState.'" size='.$lenStateInp.'>'.$htAftState;
	    $htZip = '<input name="'.$ksZip.'" value="'.$strZip.'" size=11>';
	    $htCtry = $htCountry.$htZone;

	    $htEmail = '<input name="'.$ksEmail.'" value="'.$strEmail.'" size=30> '.$this->HTML_AfterEmailAddress();
	    $htPhone = '<input name="'.$ksPhone.'" value="'.$strPhone.'" size=20> (optional)';
	}

	$out .= <<<__END__
<tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td><td>$htStreet</td></tr>
<tr><td align=right valign=middle>City:</td><td>$htCity</td></tr>
<tr><td align=right valign=middle>$strStateLbl:</td><td>$htState</td></tr>
<tr><td align=right valign=middle>$strZipLbl:</td><td>$htZip</td></tr>
<tr><td align=right valign=middle>Country:</td><td>$htCtry</td></tr>
__END__;

// if this contact saves email and phone, then render those too:
	if ($doEmail) {
	    $out .= "<tr><td align=right valign=middle>Email:</td><td>$htEmail</td></tr>";
	}
	if ($doPhone) {
	    $out .= "<tr><td align=right valign=middle>Phone:</td><td>$htPhone</td></tr>";
	}

	return $out;
    }
    
    // -- FORM: RENDERING -- //
    
}
/*
class vcCartData_Buyer_OLD extends vcCartData_Contact {

    // ++ CEMENTING ++ //
    
    protected function ControlName_Prefix() {
	return 'buyer-';
    }
    protected function RenderOption_DoShipZone() {
	return FALSE;
    }
    protected function RenderOption_DoEmail() {
	return TRUE;
    }
    protected function RenderOption_DoPhone() {
	return TRUE;
    }
    
    // -- CEMENTING -- //
    // ++ FIELD VALUES ++ //
    
    protected function NameValue() {
    	return $this->GetValue(KI_CART_PAY_CARD_NAME);
    }
    protected function StreetValue() {
    	return $this->GetValue(KI_CART_PAY_CARD_STREET);
    }
    protected function TownValue() {
    	return $this->GetValue(KI_CART_PAY_CARD_CITY);
    }
    protected function StateValue() {
    	return $this->GetValue(KI_CART_PAY_CARD_STATE);
    }
    protected function ZipcodeValue() {
    	return $this->GetValue(KI_CART_PAY_CARD_ZIP);
    }
    protected function CountryValue() {
    	return $this->GetValue(KI_CART_PAY_CARD_COUNTRY);
    }
    protected function PhoneNumber() {
    	return $this->BuyerPhoneNumber();
    }
    protected function EmailAddress() {
    	return $this->BuyerEmailAddress();
    }
    protected function BuyerPhoneNumber() {
	return $this->GetValue(KI_CART_BUYER_PHONE);
    }
    protected function BuyerEmailAddress() {
	return $this->GetValue(KI_CART_BUYER_EMAIL);
    }
    
    // -- FIELD VALUES -- //

    // maybe this should be renamed RenderBuyer() OSLT?
    public function Render() {
	$htFNEmail = KSF_CART_BUYER_EMAIL;	// field name for email address
	$htFNPhone = KSF_CART_BUYER_PHONE;	// field name for phone number

	$sValPhone = $this->BuyerPhoneNumber();
	$sValEmail = $this->BuyerEmailAddress();
	if (is_null($sValEmail)) {	// if this has not already been entered
	    $sValEmail = $this->SessionRecord()->UserEmailAddress();
	}
	$htValEmail = fcString::EncodeForHTML($sValEmail);
	$htValPhone = fcString::EncodeForHTML($sValPhone);
	
	$sWhere = __METHOD__.' in '.__FILE__;

	$out = <<<__END__
	
<!-- vv $sWhere vv -->

<table class="form-block" id="shipping">
  <tr>
    <td align=right><b>Email address</b>:</td>
    <td><input size=50 name="$htFNEmail" value="$htValEmail"></td>
  </tr>
  <tr>
    <td align=right><b>Phone number</b>:</td>
    <td><input size=30 name="$htFNPhone" value="$htValPhone"> (optional)</td>
  </tr>
</table>

<!-- ^^ $sWhere ^^ -->
__END__;

	return $out;
    }
}
class vcCartData_Recip_OLD extends vcCartData_Contact {

    // ++ SETUP ++ //

    const KS_INTYPE_EXISTING = 'old';
    const KS_INTYPE_NEWENTRY = 'new';
  
    private $oZone;
  
    public function __construct($sBlob=NULL, vcShipCountry $oZone) {
	parent::__construct($sBlob);
	$this->oZone = $oZone;
    }
    protected function ShipZone() {
	return $this->oZone;
    }

    // -- SETUP -- //
    // ++ CEMENTING ++ //
    
    protected function ControlName_Prefix() {
	return 'recip-';
    }
    protected function ControlName_InType() {
	return 'intype';
    }
    protected function ControlName_RecipChoice() {
	return $this->ControlName_Prefix().'-choice';
    }
    protected function RenderOption_DoShipZone() {
	return TRUE;
    }
    protected function RenderOption_DoEmail() {
	return TRUE;
    }
    protected function RenderOption_DoPhone() {
	return TRUE;
    }
    
    // -- CEMENTING -- //
    // ++ FIELD VALUES ++ //
    
    protected function NameValue() {
    	return $this->GetValue(KI_CART_RECIP_NAME);
    }
    protected function StreetValue() {
    	return $this->GetValue(KI_CART_RECIP_STREET);
    }
    protected function TownValue() {
    	return $this->GetValue(KI_CART_RECIP_CITY);
    }
    protected function StateValue() {
    	return $this->GetValue(KI_CART_RECIP_STATE);
    }
    protected function ZipcodeValue() {
    	return $this->GetValue(KI_CART_RECIP_ZIP);
    }
    protected function CountryValue() {
    	return $this->GetValue(KI_CART_RECIP_COUNTRY);
    }
    protected function PhoneNumber() {
    	return $this->GetValue(KI_CART_RECIP_PHONE);
    }
    protected function EmailAddress() {
    	return $this->GetValue(KI_CART_RECIP_EMAIL);
    }
    protected function IsRecipOldEntry() {
	$sInType = $this->GetValue(KI_CART_RECIP_INTYPE);
	return ($sInType == self::KS_INTYPE_EXISTING);
    }
    protected function ShipMessage() {
	return $this->GetValue(KI_CART_RECIP_MESSAGE);
    }

    // -- FIELD VALUES -- //
    // ++ FORM I/O ++ //
    
    public function Render() {
	$hrefForShipping = '<a href="'.KWP_WIKI_PUBLIC.'Shipping_Policies">';

// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;

	$sCustShipMsg = $this->ShipMessage(FALSE);

	$this->htmlAfterAddress = NULL;

	$this->doFixedCard = FALSE;
	$this->doFixedName = FALSE;

	$sWhere = __METHOD__.' in '.__FILE__;

	$out = <<<__END__
	
<!-- vv $sWhere vv -->

<table class="form-block" id="shipping">
__END__;

//	$oAddrShip = $rcCartData->RecipFields();

	$htEnter = NULL;
	if ($this->UserRecord()->IsLoggedIn()) {
	    // allow user to select from existing recipient profiles
	    $oUser = $this->UserRecord();
	    $rsCusts = $oUser->ContactRecords();
	    if ($rsCusts->RowCount() > 0) {
		$doUseOld = $this->IsRecipOldEntry();
		$htSelOld = $doUseOld?' checked':'';
		$htSelNew = $doUseOld?'':' checked';
		$htCusts = '<input type=radio name="'
		  .$this->ControlName_InType()
		  .'" value="'
		  .self::KS_INTYPE_EXISTING
		  .'"'
		  .$htSelOld
		  .'><b>ship to an existing address:</b>'
		  .$rsCusts->Render_DropDown_Addrs(KSF_CART_RECIP_CONT_CHOICE)
		  //.'SQL:'.$rsCusts->sqlMake
		  ;
		$htEnter = '<input type=radio name="'
		  .KSF_CART_RECIP_CONT_INTYPE
		  .'" value="'
		  .self::KS_INTYPE_NEWENTRY
		  .'"'
		  .$htSelNew
		  .'><b>enter new shipping information:</b>'
		  ;
	    } else {
		$htCusts = 'You currently have no addresses saved in your profile.';
	    }
	    $out .= '<tr><td colspan=2>'
	      .$htCusts
	      // TODO: shouldn't the *skin* be the one setting the CSS class?
	      .'<span class=logout-inline>'.$this->SkinObject()->RenderLogout().'</span>'
	      .'<hr></td></tr>';
	} else {
	    // make it easy for user to log in
	    $out .= '<tr><td colspan=2><b>Shopped here before?</b>'
	      .' '.$this->RenderLogin()
	      .' or <a href="'.KWP_LOGIN.'" title="login options: reset password, create account">more options</a>.'
	      .'<hr></td></tr>';
	}

	$out .= "<tr><td colspan=2>$htEnter</td></tr>";
	$out .= $this->RenderContact();
	$htFtr = $this->SkinObject()->SectionFooter();
	$out .= <<<__END__
<tr><td colspan=2 align=left>
	<font color=#880000>
	<b>Note:</b> If you need any part of your order by a particular date, <b>please tell us</b> in the space below.</font>
	See our {$hrefForShipping}shipping policies</a> for details.
	</td></tr>
<tr><td align=right valign=top>
	Special Instructions:<br>
	</td>
	<td><textarea name="$ksShipMsg" cols=50 rows=5>$sCustShipMsg</textarea></td>
	</tr>
</table>

<!-- vv $sWhere vv -->

__END__;
	return $out;
    }
    public function Capture() {
	$oForm = $this->FormObject();
	// invoke the field objects we'll need
	$this->NameField();
	$this->StreetField();
	$this->TownField();
	$this->StateField();
	$this->ZipcodeField();
	$this->CountryField();
	$this->EmailField();
	$this->PhoneField();
	$oForm->Receive($_POST);	// get values submitted by form
    
      
	$custIntype = fcArray::Nz($_POST,$this->ControlName_InType());
	if ($custIntype = self::KS_INTYPE_EXISTING) {
	    $custChoice = fcArray::Nz($_POST,$this->ControlName_RecipChoice());
	}
	
	// TODO: not finished
    }
    
    // -- FORM I/O -- //

}//*/