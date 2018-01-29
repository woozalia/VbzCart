<?php
/*
  HISTORY:
    2015-09-03 Split off Pull Type classes from orders/pull.php
    2017-01-06 partially updated
*/

/*::::
  NOTES:
    2016-12-02 This class will need rewriting.
    2017-01-06 use ftCacheableTable
*/
class vctOrderPullTypes extends vcAdminTable {
    use ftExecutableTwig;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'ord_pull_type';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrOrderPullType';
    }
    // CEMENT
    public function GetActionKey() {
	return 'ord-pull-type';
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Pull Types');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();	// TODO: to be written
    }

    // -- EVENTS -- //
    // ++ ADMIN UI ++ //
    
    public function ComboBox($sName,$idWhich=NULL) {
	$rs = $this->SelectRecords(NULL,'OptionGroup,Name');
	return $rs->RenderDropDown($sName,$idWhich);
    }

    // -- ADMIN UI -- //
}
class vcrOrderPullType extends vcAdminRecordset {

    // ++ FIELD VALUES ++ //

    // PUBLIC so Order records can use it
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    protected function ReasonString() {
	return $this->GetFieldValue('Reason');
    }
    protected function GroupString() {
	return $this->GetFieldValue('OptionGroup');
    }

    // -- FIELD VALUES -- //
    // ++ UI ELEMENTS ++ //
    
    public function RenderDropDown($sName,$idWhich=NULL) {
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

