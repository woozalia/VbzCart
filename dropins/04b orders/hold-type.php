<?php
/*
  HISTORY:
    2015-09-03 Split off Pull Type classes from orders/pull.php
    2017-01-06 partially updated
    2017-06-04 updating for Order Holds
*/

/*::::
  NOTES:
    2016-12-02 This class will need rewriting.
    2017-01-06 use ftCacheableTable
*/
class vctOrderHoldTypes extends vcAdminTable {
    use ftExecutableTwig;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'ord_hold_type';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrOrderHoldType';
    }
    // CEMENT
    public function GetActionKey() {
	return 'ord-hold-type';
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Hold Types for Orders');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();	// TODO: to be written
    }

    // -- EVENTS -- //
    // ++ ADMIN UI ++ //
    
    public function ComboBox($sName,$idWhich=NULL) {
	throw new exception('2017-06-04 Does anyone actually call this still?');
	$rs = $this->SelectRecords(NULL,'OptionGroup,Name');
	return $rs->RenderDropDown($sName,$idWhich);
    }

    // -- ADMIN UI -- //
}
class vcrOrderHoldType extends vcAdminRecordset {

    // ++ FIELD VALUES ++ //

    /*----
      NOTE: replaces NameString() from Pull Types
      PUBLIC so Order records can use it
    */
    public function DisplayString() {
	return $this->GetFieldValue('Display');
    }
    /*----
      NOTE: replaces ReasonString() from PullTypes
    */
    protected function AboutString() {
	return $this->GetFieldValue('About');
    }
    protected function GroupString() {
	return $this->GetFieldValue('OptionGroup');
    }

    // -- FIELD VALUES -- //
    // ++ UI ELEMENTS ++ //
    
    public function RenderDropDown($sName,$idWhich=NULL) {
	throw new exception('2017-06-04 Is anyone still calling this?');
	$sGrpLast = NULL;
	$out = "\n".'<select name="'.$sName.'">';
	while ($this->NextRow()) {
	    $id = $this->GetKeyValue();
	    $sGrpThis = $this->GroupString();
	    if ($sGrpThis != $sGrpLast) {
		if (!is_null($sGrpLast)) {
		    $out .= "\n</optgroup>";
		}
		$out .= "\n<optgroup label='$sGrpThis'>";
		$sGrpLast = $sGrpThis;
	    }
	    if ($id == $idWhich) {
		$htSelect = " selected";
	    } else {
		$htSelect = '';
	    }
	    $htName =
	      $this->NameString()
	      .' - '
	      .$this->ReasonString()
	      ;
	    $out .= "\n	<option$htSelect value='$id'>$htName</option>";
	}
	if (!is_null($sGrpLast)) {
	    $out .= "\n</optgroup>";
	}
	$out .= "\n</select>";
	return $out;
    }

}

