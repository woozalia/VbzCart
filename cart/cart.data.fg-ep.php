<?php
/*
  PURPOSE: Email-Phone form group for Cart Data
  HISTORY:
    2016-06-16 split off from cart.xdata.forms.php
*/
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

    private $oField_email;
    protected function EmailField() {
	if (empty($this->oField_email)) {
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $this->FieldName_forContactEmail()
	      );
	      
	    $oField->SetDefault($this->DefaultEmail());

	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>30));
	      $oCtrl->DisplayAlias('email address');

	      $this->oField_email = $oField;
	}

	return $this->oField_email;
    }
    private $oField_phone;
    protected function PhoneField() {
	if (empty($this->oField_phone)) {
	    $oField = new fcFormField_Text(
	      $this->FormObject(),
	      $this->FieldName_forContactPhone()
	      );

	    $oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	      $oCtrl->DisplayAlias('phone number');
	      $oCtrl->Required(FALSE);

	    $this->oField_phone = $oField;
	}
	return $this->oField_phone;
    }
    protected function LoadContactFields() {
	// invoke controls to add them to the form
	$this->EmailField();
	$this->PhoneField();
	// copy blob data to field objects
	$this->FormObject()->LoadFields_fromBlob();
    }

    // -- FIELD OBJECTS -- //
    // ++ FORM: FIELD VALUES ++ //
    
    public function GetEmailFieldValue() {
	$sfName = $this->FieldName_forContactEmail();
	return $this->GetValue($sfName);
    }
    public function GetPhoneFieldValue() {
	$sfName = $this->FieldName_forContactPhone();
	return $this->GetValue($sfName);
    }

    // -- FORM: FIELD VALUES -- //
    // ++ FORM I/O : CAPTURE ++ //

    public function CaptureContact() {
	$this->LoadContactFields();
	
	$arStat = $this->FormObject()->Receive($_POST);
	$this->AddMissing($arStat['blank']);
    }
    
    // -- FORM I/O : CAPTURE -- //
    // ++ FORM I/O : RENDER ++ //
    
    /*----
      ACTION: Renders email/phone controls
      ASSUMES: They have already been invoked (added to the form)
    */
    public function RenderContact($doEdit) {
	$this->LoadContactFields();
	
	$oTplt = $this->ContactTemplate($doEdit);
	$arCtrls = $this->FormObject()->RenderControls($doEdit);
	$oTplt->SetVariableValues($arCtrls);
	return $oTplt->RenderRecursive();
    }
    protected function RenderUserControls() {
    
	/* 2018-02-27 Don't the login controls respond appropriately to logged-in state now?
	if ($this->AppObject()->UserIsLoggedIn()) {
	    // render logout stuff
	    $out = $this->PageObject()->RenderLogout_Controls();
	} else {
	    // render login stuff
	    $out = $this->PageObject()->RenderLogin_Controls();
	} */
	// 2018-02-27 Trying this:
	$out = $this->PageObject()->RenderLogin_Controls();
	return $out;
    }
    
    // -- FORM I/O : RENDER -- //
    // ++ FORM TEMPLATE ++ //
    
    protected function ContactTemplate($doEdit) {
	$htEmail = $this->FieldName_forContactEmail();
	$htPhone = $this->FieldName_forContactPhone();
	$htPhoneSfx = $doEdit?' (optional)':'';	// only show "optional" when editing
	$htUser = $this->RenderUserControls();

	$htPrecede = $this->ContactTemplate_PrecedingLines();
	$htFollow = $this->ContactTemplate_FollowingLines();

	$sTplt = <<<__END__
<table class="form-block" id="contact">
$htPrecede
<tr><td colspan=2 align=center><hr>$htUser<hr></td></tr>
<tr><td align=right valign=middle>Email:</td><td>[[$htEmail]]</td></tr>
<tr><td align=right valign=middle>Phone:</td><td>[[$htPhone]]$htPhoneSfx</td></tr>
$htFollow
</table>
__END__;
	return new fcTemplate_array('[[',']]',$sTplt);
    }
    // PURPOSE: for user-classes to override if they want to add additional lines before the main block
    protected function ContactTemplate_PrecedingLines() {
	return NULL;
    }
    // PURPOSE: for user-classes to override if they want to add additional lines after the main block
    protected function ContactTemplate_FollowingLines() {
	return "GOT TO HERE";
	return NULL;
    }
    
    // -- FORM TEMPLATE -- //

}
