<?php
/*
  PURPOSE: handles extended cart data stored in the cart's FieldData BLOB field.
  HISTORY:
    2016-03-08 started
    2016-06-16 renamed from cart.xdata.php to cart.data.fg.php
*/

class vcCartDataFieldGroup {
    use ftFrameworkAccess;

    public function __construct(fcBlobField $oBlob) {
	$this->SetDataBlob($oBlob);
    }
    
    // ++ I/O ++ //
    //++internal data++//

    private $oBlob;
    protected function SetDataBlob(fcBlobField $oBlob) {
	$this->oBlob = $oBlob;
    }
    protected function GetDataBlob() {
	return $this->oBlob;
    }
    /*
    private $arData;
    private function SetArray(array $ar=NULL) {
	$this->arData = $ar;
    }
    // PUBLIC because it turns out that this is the main output for this type of class
    public function GetArray() {
	return $this->arData;
    }//*/
    // PURPOSE: debugging
    public function DumpArray() {
	return fcArray::Render($this->GetDataBlob()->GetArray());
    }
    protected function GetValue($id) {
	return $this->GetDataBlob()->GetValue($id);
    }
    protected function SetValue($id,$sValue) {
	$this->GetDataBlob()->SetValue($id,$sValue);
    }
    
    //--internal data--//
    // -- I/O -- //
    // ++ FORMS ++ //

    private $oForm;
    protected function FormObject() {
	if (empty($this->oForm)) {
	    // create fields & controls
 	    $this->oForm = new fcForm_blob($this,$this->GetDataBlob());
	}
	return $this->oForm;
    }
    public function GetFormArray() {
	return $this->FormObject()->RecordValues_asNative_get();
    }
    private $arMissed;
    protected function AddMissing(array $ar=NULL) {
	if (!is_null($ar)) {
	    $this->arMissed = array_merge($this->GetMissingArray(),$ar);
	}
    }
    /*----
      NOTE: These two methods ^^ vv are essentially duplicates of methods in vcrCart_ShopUI.
	I *think* the way it works is that the Cart object is collating missing-lists from
	vcCartDataFieldGroup forms. Maybe missing-lists should be their own class?
    */
    public function GetMissingArray() {
	if (empty($this->arMissed)) {
	    $this->arMissed = array();
	}
	return $this->arMissed;
    }
    /*
      NOTE: These methods are kind of a kluge necessitated by limitations in Ferreteria Forms v2.
    */
    
    protected function CopyFieldToArray(fcFormField $oField) {
	$id = $oField->NameString();
	$val = $oField->GetValue();
	$this->SetValue($id,$val);
    }
    protected function CopyArrayToField(fcFormField $oField) {
	$id = $oField->NameString();
	$oField->SetValue($this->GetValue($id));
    }
    /*----
      NOTES:
	I originally thought we'd only want to return values we actually received, otherwise
	stored values kept in one form-instance might overwrite values actually received by
	another form-instance (though I'm not sure what actual usage this might reflect).
	
	I also wrote --
	
	However, if we do that here, then we can't set anything beforehand; it gets wiped out.
	Canonically, the way around this would just be to have a form field for any additional
	data -- but in the case of the input-mode thingy (new vs. old), the value is set by
	pressing a button, which doesn't really conform to any pre-existing kind of form control.
	So I'd have to write one just for this.
	
	-- but I'm kind of vague on what I thought I meant.
	
	It now (2016-06-19) turns out that we *need* to keep the existing data because otherwise
	a form which is being re-displayed after having been saved earlier won't show the saved
	values, which is definitely problematic.
      HISTORY:
	2016-06-19 Commenting out the $this->GetDataBlob()->ClearArray() line (see NOTES).
    */
    protected function CopyFieldsToArray() {
	$oForm = $this->FormObject();
	// see NOTE - clear the output array before copying received data:
	//$this->GetDataBlob()->ClearArray();
	foreach ($oForm->FieldArray() as $key => $oField) {
	    $this->CopyFieldToArray($oField);
	}
    }
    // NOTE: Copies only the fields for which there are objects defined
    // TODO: Is this the same functionality as $this->FormObject()->Load()?
    protected function CopyArrayToFields() {
	$oForm = $this->FormObject();
	foreach ($oForm->FieldArray() as $key => $oField) {
	    $this->CopyArrayToField($oField);
	}
    }
    
    // -- FORMS -- //
}



