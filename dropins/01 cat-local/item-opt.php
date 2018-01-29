<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling Item Options
  HISTORY:
    2015-11-17 started
*/
trait vtAdminTableAccess_ItemOption {
    use vtTableAccess_ItemOption;
    
    protected function ItemOptionsClass() {
	return KS_ADMIN_CLASS_LC_ITEM_OPTIONS;
    }
}
class vtItemOpts_admin extends vctItemOptions implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcrAdminItemOption';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CATALOG_ITEM_OPTION;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {}	// nothing yet (2017-03-27)
    /*----
      PURPOSE: execution method called by dropin menu
    */
    /*
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }*/

    // -- EVENTS -- //
    // ++ RECORDS ++ //
    
    public function GetData_forDropDown() {
//	$sqlTbl = $this->TableName_cooked();
//	$rs = $this->DataSQL("SELECT * FROM $sqlTbl ORDER BY Sort");
	$rs = $this->SelectRecords(NULL,'Sort');
	return $rs;
    }
    public function ActiveRecords() {
	$sqlFilt = NULL;
	$sqlSort = 'Sort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- RECORDS -- //

}
class vcrAdminItemOption extends vcrItemOption implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftExecutableTwig;	// dispatch events
    use ftSaveableRecord;	// implements fiEditableRecord
    use ftLoggedRecord;
    use ftLoggableRecord;
    
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	if ($this->IsNew()) {
	    $sTitle = '+IO';
	    $htTitle = 'new Item Option';
	} else {
	    $id = $this->GetKeyValue();
	    $sName = $this->Description_forList();
	    $sTitle = "IO $id: $sName";
	    $htTitle = "Item Option #$id: $sName";
	}
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }
    
    // -- EVENTS -- //
    // ++ FIELD CALCULATIONS ++ //

    // CALLBACK
    public function ListItem_Text() {
	return $this->Description_forList();
    }
    // CALLBACK
    public function ListItem_Link() {
	return $this->SelfLink($this->ListItem_Text());
    }
    // OVERRIDE
    public function Description_forItem() {
	return $this->SelfLink($this->GetFieldValue('Descr'));
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$doSave = $oFormIn->GetBool('btnSave');
	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $this->SelfRedirect();
	}
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit current record'));

	    $doEdit = $ol->GetIsSelected();

	$frm = $this->PageForm();
	if ($this->IsNew()) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['!ID'] = $this->SelfLink();
	
	$out = NULL;
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $arCtrls['!extra'] = '<tr>	<td colspan=2><b>Edit notes</b>: <input type=text name="'
	      .KS_FERRETERIA_FIELD_EDIT_NOTES
	      .'" size=60></td></tr>'
	      ;
	} else {
	    $arCtrls['!extra'] = NULL;
	}

	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {	    
	    $out .= <<<__END__
	    
<input type=submit name="btnSave" value="Save">
</form>
__END__;
	}
	$out .= $this->EventListing();
	
	return $out;
    }
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);

	      $oField = new fcFormField_Text($oForm,'CatKey');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Text($oForm,'Sort');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));
	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=content>
  <tr>	<td align=right><b>ID</b>:</td>		<td>[[!ID]]</td>	</tr>
  <tr>	<td align=right><b>CatKey</b>:</td>	<td>[[CatKey]]</td>	</tr>
  <tr>	<td align=right><b>Sort</b>:</td>	<td>[[Sort]]</td></tr>
  <tr>	<td align=right><b>About</b>:</td>	<td>[[Descr]]</td>	</tr>
  [[!extra]]
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    
    // -- WEB UI -- //
}