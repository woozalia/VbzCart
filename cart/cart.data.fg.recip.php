<?php
/*
  PURPOSE: form classes for cart.xdata, just to keep things more manageable
    I may end up merging them back together later.
  HISTORY:
    2016-03-08 started
    2016-03-10 At this point, I'm not sure if this is going to be used, though bits of the code in it
      will probably be needed.
    2016-03-13 Gutted; moving some classes here (bits of code from here are probably included).
    2016-06-16 Renamed from cart.xdata.forms.php to cart.data.fg.recip.php
*/


/*%%%%
  PURPOSE: Handles Recipient subforms:
    * intype (choice of existing or entered address)
    * if existing: choice of address
    * if entered: name/address
    * instructions
  FUTURE: At some point, we might want to allow recipients to have their own email addresses and phone numbers, though we don't
    currently support that. When that happens, change this so it extends vcCartData_Contact instead of vcCartData_NameAddress.
*/
class vcCartData_Recip extends vcCartData_NameAddress {

    const KS_INTYPE_EXISTING = 'old';
    const KS_INTYPE_NEWENTRY = 'new';

    // ++ TRAIT REQUIREMENTS ++ //
    
    protected function FieldName_Prefix() {
	return _KSF_CART_PFX_RECIP;
    }
    protected function FieldName_forShipIntype() {
	return 'intype-ship';
    }
    protected function PrecedingLinesForTemplate() {
	$htName = $this->FieldName_forContactName();
	$out = "\n  <tr><td align=right>Name:</td><td>[[$htName]]</td></tr>";
	return $out;
    }
    protected function FollowingLinesForTemplate() {
	$htMsg = $this->FieldName_forDestinationMessage();
	/* 2016-08-01 rearranging things
	// Main label on left, additional explanation below
	$out = "\n<tr><td align=right>Optional<br>delivery<br>instructions:</td><td>[[$htMsg]]</td></tr>"
	  ."\n<tr><td colspan=2 align=center>(Any delivery instructions will be included on the shipping label or written on the package.)</td></tr>"
	  ;
	*/
	// Main label and additional explanation above; everything centered
	$out = "\n<tr><td align=center colspan=2>"
	  .'Optional delivery instructions (will be shown on the package):'
	  ."\n<br>[[$htMsg]]"
	  .'</td></tr>'
	  ;
	return $out;
    }
    
    // -- TRAIT REQUIREMENTS -- //
    // ++ TRAIT OVERRIDES ++ //
    
    protected function InvokeNameAddressFields() {
	parent::InvokeNameAddressFields();
	$this->IntypeField();	// also invoke intype field
    }

    // -- TRAIT OVERRIDES -- //
    // ++ FIELD NAMES ++ //
    
    protected function FieldName_forDestinationMessage() {
	return 'dest-msg';
    }
    protected function FieldName_forShipChoice() {
	return KSF_CART_RECIP_CONT_CHOICE;
    }
    
    // -- FIELD NAMES -- //
    // ++ FIELD VALUES ++ //

    /*----
      NOTE: See notes for vcCartData_Buyer::Value_forBillInType()
      PUBLIC so Cart object can access it during conversion to Order
    */
    public function Value_forShipInType($s=NULL) {
	$sfName = $this->FieldName_forShipIntype();
	if (!is_null($s)) {
	    $this->SetValue($sfName,$s);
	}
	return $this->GetValue($sfName);
    }
    public function Value_forShipInType_isNew() {
	$sIntype = $this->Value_forShipInType();
	return is_null($sIntype) || ($sIntype == KS_FORM_INTYPE_NEWENTRY);
    }
    public function GetValue_forDestinationMessage() {
    	$sfName = $this->FieldName_forDestinationMessage();
	return $this->GetValue($sfName);
    }
    public function GetValue_forShipChoice() {
	$sfName = $this->FieldName_forShipChoice();
	return $this->GetValue($sfName);
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD OBJECTS ++ //

    // NOTE: It's very important that this always return the *same* object. Hence the caching.
    private $oField_Intype;
    protected function IntypeField() {
	if (empty($this->oField_Intype)) {
	    $sName = $this->FieldName_forShipIntype();
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $sName
	      );
	    //$oField->SetValue(self::KS_INTYPE_EXISTING);
	    
	    $arBtns = array(
		self::KS_INTYPE_EXISTING => new fcInstaModeButton($sName,'Select a Destination'),
		self::KS_INTYPE_NEWENTRY => new fcInstaModeButton($sName,'Enter a New Destination'),
	      );
	    $oCtrl = new fcFormControl_HTML_InstaMode($oField,$arBtns);
	    
	    $this->oField_Intype = $oField;
	}
	
	return $this->oField_Intype;
    }
    private $oField_DestInstrux;
    protected function DestinationMessageField() {
	if (empty($this->oField_DestInstrux)) {
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $this->FieldName_forDestinationMessage()
	      );

	    $oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>2,'cols'=>60));
	      $oCtrl->Required(FALSE);
	      
	    $this->oField_DestInstrux = $oField;
	}

	return $this->oField_DestInstrux;
    }
    /*----
      NOTE: The control for this field is rendered separately, not through the Field object's Control object.
	Maybe that should be changed later; it's probably not that difficult.
    */
    private $oField_ShipChoice;
    protected function ShipChoiceField() {
	if (empty($this->oField_ShipChoice)) {
	    $oField = new fcFormField_Num(
	      $this->FormObject(),
	      $this->FieldName_forShipChoice()
	      );
	    $this->oField_ShipChoice = $oField;
	}

	return $this->oField_ShipChoice;
    }
    protected function LoadShippingFields() {
	$this->InvokeNameAddressFields();
	$this->IntypeField();
	$this->ShipChoiceField();
	$this->DestinationMessageField();

	// copy blob data to field objects
	$this->FormObject()->Load();
    }

    // -- FIELD OBJECTS -- //
    // ++ FORM I/O ++ //

    /*----
      ACTION:
	* Receive form data into form objects.
	* Note anything missing.
	* Store the received data in the local array.
	(The caller will need to retrieve the blob and save the Cart record.)
    */
    public function CaptureShipping() {
	// invoke controls to add them to the form
	//$this->InvokeNameAddressFields();
	$this->LoadShippingFields();
	$this->ReceiveForm();
    }
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
	$this->LoadShippingFields();
	if ($this->AppObject()->UserIsLoggedIn()) {
	    $sIntype = $this->Value_forShipInType();
	    $doOld = ($sIntype == self::KS_INTYPE_EXISTING);
	    // sometimes this doesn't get set automatically (BUG - TODO) ... 2016-06-19 might be fixed now; TODO: test
	    $this->IntypeField()->SetValue($sIntype);
	} else {
	    $doOld = FALSE;
	}
	$out = NULL;
	if ($doOld) {
	    $out .= $this->RenderShipping_OldMode($doEdit);
	} else {
	    $out .= $this->RenderShipping_NewMode($doEdit);
	}
	$out .= $this->RenderShipping_OrderInstructions($doEdit);	// special instructions for this order only
	return $out;
    }
    /*----
      ACTION: Old Mode output depends on whether we're editing:
	* if editing: show the drop-down and [Enter New Destination] button
	* if read-only: show the selected name/address
    */
    protected function RenderShipping_OldMode($doEdit) {
	if ($doEdit) {
	    $oUser = $this->UserRecord();
	    // allow user to select from existing recipient profiles
	    $rsCusts = $oUser->ContactRecords();
	    $htBtn = $this->IntypeField()->ControlObject()->Render(TRUE);
	    if ($rsCusts->RowCount() > 0) {
		$out = 
		  'Choose a destination: '
		  .$rsCusts->Render_DropDown_Addrs(KSF_CART_RECIP_CONT_CHOICE)
		  .' or '
//		  .'<input type=submit name=btnEnterRecip value="Enter New Destination">'
		  .$htBtn
		  ;
	    } else {
		$htCusts = 'You currently have no shipping destinations saved in your profile. '
		  .$htBtn
		  ;
	    }
	} else {
	    // the pre-existing record values should have been copied to local data when the form was saved
	    $out = $this->RenderNameAddress(FALSE);
	}
	return $out;
    }
    protected function RenderShipping_NewMode($doEdit) {
	$out = "\n<div align=center>";
	if ($this->AppObject()->UserIsLoggedIn()) {
//	$test = $this->IntypeField()->ControlObject();
	    $htBtn = $this->IntypeField()->ControlObject()->Render(TRUE);
	    $out .= "Enter new shipping destination below or $htBtn from your profile.";
	} else {
	    // Without the complication of any other options, no real explanation is needed here.
	}
	$out .=
	  '</div>'
	  .$this->RenderNameAddress($doEdit)
	  ;

	return $out;
    }
    protected function RenderShipping_OrderInstructions($doEdit) {
	$out = "\n<table><tr><td align=right>"
	  ;	// TODO: writing in progress 2016-08-01
    }
    
    // -- FORM I/O -- //
}
