<?php
/*
  PURPOSE: admin interface for managing Warehouses
  HISTORY:
    2016-01-09 started
*/

class vctaWarehouses extends vctlWarehouses implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;
    use ftExecutableTwig;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcraWarehouse';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_STOCK_WAREHOUSE;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Warehouses');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec() {
	return $this->AdminPage();
    } */

    // -- EVENTS -- //
    // ++ RECORDS ++ //
    
    public function ActiveRecords() {
	return $this->SelectRecords('isActive');	// get all active records
    }
    
    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$rs = $this->SelectRecords();		// get all records, active or not
	$out = $rs->AdminRows();
	return $out;
    }
    
    // -- ADMIN WEB UI -- //

}

class vcraWarehouse extends vcrlWarehouse implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftShowableRecord;
    use ftExecutableTwig;
    use ftSaveableRecord;

    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$id = $this->GetKeyValue();
	$sName = $this->NameString();
	$sTitle = "wh$id: $sName";
	$htTitle = "Warehouse #$id: $sName";
    
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle('Suppliers');
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ TRAIT HELPER ++ //
    
    public function SelfLink_name() {
	return $this->SelfLink($this->NameString());
    }
    
    // -- TRAIT HELPER -- //
    // ++ CALLBACKS ++ //
    
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    public function ListItem_Text() {
	return $this->NameString();
    }
    
    // -- CALLBACKS -- //
    // ++ ADMIN UI: ROWS ++ //

    protected function AdminRows_settings_columns() {
	return array(
	  'ID'		=> 'ID',
	  'Name'	=> 'Name',
	  'isActive'	=> 'A?',
	  'Notes'	=> 'Notes'
	  );
    }
    protected function AdminRows_start(array $arOptions=NULL) {
	return "\n<table class=listing>";
    }
    protected function AdminField($sField,array $arOptions=NULL) {
	if ($sField == 'ID') {
	    $val = $this->SelfLink();
	} else {
	    $val = $this->GetFieldValue($sField);
	}
	return "<td>$val</td>";
    }
    
    // -- ADMIN UI: ROWS -- //
    // ++ ADMIN UI: RECORD ++ //
    
    protected function AdminPage() {
	$oFormIn = fcHTTP::Request();
//	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();

	$doSave = $oFormIn->GetBool('btnSave');
//    	$doEdit = $oPathIn->GetBool('edit');

	// save edits before showing events
	if ($doSave) {
	    $ftSaveMsg = $this->PageForm()->Save();
	    $this->SelfRedirect($ftSaveMsg);
	}
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,'cancel','edit this warehouse'));
	    $doEdit = $ol->GetIsSelected();
 
	/*
	$arActs = array(
	  new clsActionLink_option(array(),
	    'edit',			// $iLinkKey
	    NULL,			// $iGroupKey
	    NULL,			// $iDispOff
	    'cancel',			// $iDispOn
	    'edit this supplier'	// $iDescr
	    ),
	  );
	$oPage->PageHeaderWidgets($arActs);
	*/
    
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	
	$out = NULL;
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'</form>'
	      ;
	}
	
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=listing>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Active?</b>:</td><td>[[isActive]]</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>[[Name]]</td></tr>
  <tr><td colspan=2><b>Notes</b>:<br>[[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    private $oPageForm;
    protected function PageForm() {
	if (empty($this->oPageForm)) {
	    // create fields & controls
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'isActive');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());
		
	      $oField = new fcFormField_Num($oForm,'Name');
	      
	      $oField = new fcFormField_Num($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));
		
	    $this->oPageForm = $oForm;
	}
	return $this->oPageForm;
    }

    // -- ADMIN UI: RECORD -- //
}