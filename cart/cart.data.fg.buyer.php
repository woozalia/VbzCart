<?php
/*
  PURPOSE: "Buyer" form group for Cart Data
  HISTORY:
    2016-06-16 split off from cart.xdata.forms.php
*/

/*::::
  PURPOSE: general contact class that has everything (name, mail address, email, phone).
    This is currently only used for Buyers, but might later be extended to Recipients
*/
class vcCartData_Contact extends vcCartData_NameAddress {
    use vtCartData_EmailPhone;
}
/*%%%%
  PURPOSE: Handles Buyer subforms:
    * email/phone
    * payment (name/address/details)
*/
class vcCartData_Buyer extends vcCartData_Contact {

    const KS_CARDADDRTYPE_EXISTING = 'old';
    const KS_CARDADDRTYPE_NEWENTRY = 'new';
    
    // ++ TRAIT REQUIREMENTS ++ //

    protected function PrecedingLinesForTemplate() {
	$htName = $this->FieldName_forContactName();
	$out = "\n  <tr><td align=right>Name on Card:</td><td>[[$htName]]</td></tr>";
	return $out;
    }
    protected function FollowingLinesForTemplate() {
	return NULL;
    }
    protected function FieldName_Prefix() {
	return 'buyer-';
    }
    protected function DefaultEmail() {
	$oApp = $this->AppObject();
	if ($oApp->UserIsLoggedIn()) {
	    return $oApp->GetUserRecord()->EmailAddress();
	} else {
	    return NULL;
	}
    }
    
    // -- TRAIT REQUIREMENTS -- //
    // ++ FIELD VALUES ++ //
    
    /*----
      2016-05-17 It's possible this hasn't been used yet. Maybe it should have "Intype" in there somewhere,
	to make its role more obvious. TODO: check to see if this is used.
    */
    protected function Value_forCardAddrType($s=NULL) {
	$sfName = $this->FieldName_forCardAddrType();
	if (!is_null($s)) {
	    $this->SetValue($sfName,$s);
	}
	return $this->GetValue($sfName);
    }
    /*----
      NOTE: This is basically the same as Value_forShipInType in the Recip class, but that may be deceptive.
	Right *now* there's only one intype field in Recip and Buyer, but that could change later. So even though
	they both use the same base naming function (FieldName_forIntype()), I'm giving the value functions
	different names (and adding a suffix to the field name) so there won't be any ambiguity later
	if we add more intype choices.
      PUBLIC so Cart object can access it during conversion to Order
    */
    public function Value_forBillInType($s=NULL) {
	$sfName = $this->FieldName_forBillIntype();
	if (!is_null($s)) {
	    $this->SetValue($sfName,$s);
	}
	return $this->GetValue($sfName);
    }
    public function Value_forBillInType_isNew() {
	$sIntype = $this->Value_forBillInType();
	return is_null($sIntype) || ($sIntype == KS_FORM_INTYPE_NEWENTRY);
    }
    public function GetValue_forCardNumber() {
	$sfName = $this->FieldName_forCardNumber();
	return $this->GetValue($sfName);
    }
    public function GetValue_forCardExpiry() {
	$sfName = $this->FieldName_forCardExpiry();
	return $this->GetValue($sfName);
    }
    public function GetValue_forOrderMessage() {
	$sfName = $this->FieldName_forOrderMessage();
	return $this->GetValue($sfName);
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD NAMES ++ //
    
    protected function FieldName_forBillIntype() {
	return 'intype-bill';
    }
    protected function FieldName_forCardAddrType() {
	return 'card-addr-type';
    }
    protected function FieldName_forCardNumber() {
	return 'card-num';
    }
    protected function FieldName_forCardExpiry() {
	return 'card-exp';
    }
    protected function FieldName_forOrderMessage() {
	return 'order-msg';
    }
    
    // -- FIELD NAMES -- //
    // ++ FIELD OBJECTS ++ //
    
    private $oField_CardAddrType;
    protected function CardAddrTypeField() {
	if (empty($this->oField_CardAddrType)) {
	    $sName = $this->FieldName_forCardAddrType();
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $sName
	      );
	    //$oField->SetValue(self::KS_INTYPE_EXISTING);
	    
	    $arBtns = array(
		self::KS_CARDADDRTYPE_EXISTING => new fcInstaModeButton($sName,"Ship to Card's Billing Address"),
		self::KS_CARDADDRTYPE_NEWENTRY => new fcInstaModeButton($sName,"Enter Card's Billing Address"),
	      );
	    $oCtrl = new fcFormControl_HTML_InstaMode($oField,$arBtns);
	    
	    $this->oField_Intype = $oField;
	}
	
	return $this->oField_Intype;
    }
    private $oField_CardNum;
    protected function CardNumberField() {
	if (empty($this->oField_CardNum)) {
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $this->FieldName_forCardNumber()
	      );

	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	      $oCtrl->DisplayAlias('card number');
	      $oCtrl->Required(TRUE);

	    $this->oField_CardNum = $oField;
	}
	return $this->oField_CardNum;
    }
    private $oField_CardExp;
    protected function CardExpiryField() {
	if (empty($this->oField_CardExp)) {
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $this->FieldName_forCardExpiry()
	      );

	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oCtrl->DisplayAlias('card expiration');
	      $oCtrl->Required(TRUE);

	    $this->oField_CardExp = $oField;
	}
	return $this->oField_CardExp;
    }
    private $oField_OrderInstrux;
    protected function OrderMessageField() {
	if (empty($this->oField_OrderInstrux)) {
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $this->FieldName_forOrderMessage()
	      );

	    $oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>50));
	      $oCtrl->Required(FALSE);
	      
	    $this->oField_OrderInstrux = $oField;
	}

	return $this->oField_OrderInstrux;
    }
     protected function InvokeCardNumberFields() {
	$this->CardNumberField();
	$this->CardExpiryField();
    }
    /*----
      TODO: When other payment types are available, this will have to invoke different sets of fields
	depending on which type is selected... or maybe we'll just invoke all the fields all the time,
	to simplify troubleshooting.
    */
    protected function LoadPaymentFields() {
	$this->InvokeCardNumberFields();
	// (if entering new card)
	$this->InvokeNameAddressFields();

	// copy blob data to field objects
	$this->FormObject()->LoadFields_fromBlob();
    }
    protected function LoadContactFields() {
      	$this->OrderMessageField();	// add this field to the standard ones
	parent::LoadContactFields();	// load standard ones
    }

    // -- FIELD OBJECTS -- //
    // ++ FORM I/O: CAPTURE ++ //
    
    public function CapturePayment() {
	$this->LoadPaymentFields();
	$this->ReceiveForm();
    }

    // -- FORM I/O: CAPTURE -- //
    // ++ FORM I/O: RENDER ++ //

    // OVERRIDE of trait method
    protected function ContactTemplate_FollowingLines() {
	$htMsg = $this->FieldName_forOrderMessage();
	// Main label and additional explanation above; everything centered
	$out = "\n<tr><td align=center colspan=2>"
	  .'Special instructions for this order only:'
	  ."\n<br>[[$htMsg]]"
	  .'</td></tr>'
	  ;
	return $out;
    }
    
    public function RenderPayment($doEdit) {
	$this->LoadPaymentFields();
	$out =
	  $this->RenderPayTypeSection($doEdit)
	  ;
	return $out;
    }
    /*----
      FUTURE: This will display instamode buttons to allow switching payment types.
	For now, we're only supporting credit card, so this just dispatches to
	the credit card form.
    */
    protected function RenderPayTypeSection($doEdit) {
	$sWhere = __METHOD__."() - ".__FILE__." LINE ".__LINE__;
	$out = $this->RenderPayCardSection($doEdit);
	/*
	$out =
	  "\n<!-- vv $sWhere vv -->"
	  //.$this->SectionHeader('Payment type:')
	  ."\n<table class=\"form-block\" id=\"pay-type\">"
	  ;

	$isShipCardSame = $this->CartFields()->IsShipToCard();
	$htChecked = $isShipCardSame?' checked':'';

	$out .= "\n<tr><td align=center>\n"
	  .$this->Skin()->RenderPaymentIcons()
	  ."<table><tr><td>"
	  .'<input name=payType value="'.KSF_CART_PTYP_CARD_HERE.'" type=radio checked disabled> Visa / MasterCard / Discover / American Express - pay here'
	  .'<br>&emsp;<input name="'.KSF_SHIP_IS_CARD.'" type=checkbox value=1'.$htChecked.'>billing address is same as shipping address above'
	  ."</td></tr></table>\n"
	  ."<hr>More payment options will be available soon.\n"
	  ."</td></tr>";

	$out .=
	//  "\n</table>"
	//  .$this->Skin()->SectionFooter()
	  "\n<!-- ^^ $sWhere ^^ -->"
	  ;
	*/
	return $out;
    }
    /*----
      RENDERS: Form controls for credit card name, number, expiration;
	dispatches to RenderPayCardAddr_* depending on whether address is
	same as shipping or not.

	Name defaults to recipient name, but is editable.
    */
    protected function RenderPayCardSection($doEdit) {
	$out =
	  $this->RenderPayCardNumberSection($doEdit)
	  .$this->RenderPayCardAddrSection($doEdit)
	  ;
	  
	return $out;
    }
    protected function RenderPayCardNumberSection($doEdit) {
	$oForm = $this->FormObject();
	$oTplt = $this->PayCardNumberTemplate($doEdit);
	$arCtrls = $oForm->RenderControls($doEdit);
	$oTplt->VariableValues($arCtrls);
	return $oTplt->RenderRecursive();
    }
    protected function RenderPayCardAddrSection($doEdit) {
	$sCAType = $this->Value_forCardAddrType();
	$doOld = ($sCAType == self::KS_CARDADDRTYPE_EXISTING);
	if ($doOld) {
	    $out = $this->RenderPayCardAddr_Old($doEdit);
	} else {
	    $out = $this->RenderPayCardAddr_New($doEdit);
	}
	return $out;
    }
    protected function RenderPayCardAddr_New($doEdit) {
	$oTplt = $this->NameAddressTemplate();
	$arCtrls = $this->FormObject()->RenderControls($doEdit);
	$oTplt->VariableValues($arCtrls);
	return $oTplt->RenderRecursive();
    }
    protected function RenderPayCardAddr_Old($doEdit) {
    }

    // -- FORM I/O: RENDER -- //
    // ++ TEMPLATES ++ //
    
    protected function PayCardNumberTemplate($doEdit) {
	$sMsg = $doEdit
	  ?"<tr><td colspan=2><span class=note><b>Tip</b>: It's okay to use dashes or spaces in the card number - reduces typing errors!</span></td></tr>"
	  :''
	  ;

	$og = vcGlobalsApp::Me();
	$wsVisa = $og->GetWebSpec_forVisaCard();
	$wsMCard = $og->GetWebSpec_forMasterCard();
	$wsAmex = $og->GetWebSpec_forAmexCard();
	$wsDisc = $og->GetWebSpec_forDiscoverCard();

	$sTplt = <<<__END__
<table class="form-block" id="card-address">
  <tr><td align=right valign=middle>We accept:</td>
	  <td>
	  <img align=absmiddle src="$wsVisa" title="Visa">
	  <img align=absmiddle src="$wsMCard" title="MasterCard">
	  <img align=absmiddle src="$wsAmex" title="American Express">
	  <img align=absmiddle src="$wsDisc" title="Discover / Novus">
	  </td></tr>
  <tr><td align=right valign=middle>Card Number:</td>
	  <td>[[card-num]] Expires: [[card-exp]] (mm/yy)
	  </td></tr>
	  $sMsg
</table>
__END__;
	return new fcTemplate_array('[[',']]',$sTplt);
    }
    
    // -- TEMPLATES -- //

}
