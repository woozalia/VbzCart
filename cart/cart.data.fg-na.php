<?php
/*
  PURPOSE: Name-Address form groups for Cart Data
  HISTORY:
    2016-06-16 split off from cart.xdata.forms.php
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
    protected function FieldName_forContactTown() {
	return $this->FieldName_Prefix().'addr-town';
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
    // ++ FORM: FIELD OBJECTS ++ //
   
    protected function NameField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactName()
	  );
	  
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>50));
	  $oCtrl->DisplayAlias('name');
	
	return $oField;
    }
    protected function StreetField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactStreet()
	  );

	$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>50));
	  $oCtrl->DisplayAlias('street');

	return $oField;
    }
    protected function TownField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactTown()
	  );
	  
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	  $oCtrl->DisplayAlias('town');
	
	return $oField;
    }
    protected function StateField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactState()
	  );
	  
	$lenState = $this->ShipZone()->StateLength();
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>$lenState));
	  $oCtrl->DisplayAlias(strtolower($this->ShipZone()->StateLabel()));
	
	return $oField;
    }
    protected function ZipcodeField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactZipcode()
	  );

	$oCtrl = new fcFormControl_HTML($oField,array('size'=>11));
	  $oCtrl->DisplayAlias(strtolower($this->ShipZone()->PostalCodeLabel()));
	
	return $oField;
    }
    protected function CountryField() {
	$oField = new fcFormField_Text(
	  $this->FormObject(),
	  $this->FieldName_forContactCountry()
	  );
	
	$oField->SetValue($this->ShipZone()->CountryName());
	  
	$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	  $oCtrl->DisplayAlias('country');

	return $oField;
    }
    
    // -- FORM: FIELD OBJECTS -- //
    // ++ FORM: FIELD VALUES ++ //
    
    public function GetNameFieldValue() {
	$sfName = $this->FieldName_forContactName();
	return $this->GetValue($sfName);
    }
    public function GetStreetFieldValue() {
	$sfName = $this->FieldName_forContactStreet();
	return $this->GetValue($sfName);
    }
    public function GetTownFieldValue() {
	$sfName = $this->FieldName_forContactTown();
	return $this->GetValue($sfName);
    }
    public function GetStateFieldValue() {
	$sfName = $this->FieldName_forContactState();
	return $this->GetValue($sfName);
    }
    public function GetZipCodeFieldValue() {
	$sfName = $this->FieldName_forContactZipCode();
	return $this->GetValue($sfName);
    }
    public function GetCountryFieldValue() {
	$sfName = $this->FieldName_forContactCountry();
	return $this->GetValue($sfName);
    }
    
    // -- FORM: FIELD VALUES -- //
    // ++ TEMPLATES ++ //
    
    protected function NameAddressTemplate() {
	$oZone = $this->ShipZone();

//	$htName = $this->FieldName_forContactName();
	$htStreet = $this->FieldName_forContactStreet();
	$htCity = $this->FieldName_forContactTown();
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
	$htPrecede = $this->PrecedingLinesForTemplate();
	$htFollow = $this->FollowingLinesForTemplate();

	$sTplt = <<<__END__
<table class="form-block" id="name-address">
    $htPrecede
    <tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td><td>[[$htStreet]]</td></tr>
    <tr><td align=right valign=middle>City:</td><td>[[$htCity]]</td></tr>
    <tr><td align=right valign=middle>$sStateLbl:</td><td>[[$htState]]$htAftState</td></tr>
    <tr><td align=right valign=middle>$sZipLbl:</td><td>[[$htZip]]</td></tr>
    $htCountryLine
    $htFollow
</table>
__END__;

//    <tr><td align=right valign=middle>Name on Card:</td><td>[[$htName]]</td></tr>
	return new fcTemplate_array('[[',']]',$sTplt);
    }
    
    // -- TEMPLATES -- //
    // ++ FORM I/O ++ //
    
    protected function InvokeNameAddressFields() {
	$this->didNameAddress = TRUE;
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
    protected function ReceiveForm() {
	$arStat = $this->FormObject()->Receive($_POST);
	
	$arMissed = $arStat['blank'];

	$this->AddMissing($arMissed);
	$this->CopyFieldsToArray();
    }
    
    // -- FORM I/O -- //

}
/*%%%%
  PURPOSE: Base class so overridden trait function can be called as parent::f()
*/
class vcCartData_NameAddress extends vcCartDataFieldGroup {
    use vtCartData_NameAddress;

    private $oZone;
  
    public function __construct(array $arBlob, vcShipCountry $oZone) {
	$oBlob = new fcBlobField(); 
	$oBlob->SetArray($arBlob);

	parent::__construct($oBlob);
	$this->oZone = $oZone;
    }
    protected function ShipZone() {
	return $this->oZone;
    }
}
