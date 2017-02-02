<?php
/*
  PURPOSE: admin interface for managing Warehouses
  HISTORY:
    2016-01-09 started
*/

class vctaWarehouses extends vctlWarehouses {

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcraWarehouse';
    }
    
    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ RECORDS ++ //
    
    public function ActiveRecords() {
	return $this->SelectRecords('isActive');	// get all active records
    }
    
    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$oSkin = $oPage->Skin();

	$out = $oPage->SectionHeader('Suppliers');

	$rs = $this->GetData();		// get all records, active or not
	$out = $rs->AdminRows($this->AdminFields());
	return $out;
    }
    protected function AdminFields() {
	return array(
	  'ID'		=> 'ID',
	  'Name'	=> 'Name',
	  'isActive'	=> 'A?',
	  'Notes'	=> 'Notes'
	  );
    }
    
    // -- ADMIN WEB UI -- //

}

class vcraWarehouse extends vcrlWarehouse {

    // ++ CALLBACK ++ //

    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- CALLBACK -- //
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

    protected function AdminRows_start(array $arOptions=NULL) {
	return "\n<table class=listing>";
    }
    protected function AdminField($sField,array $arOptions=NULL) {
	if ($sField == 'ID') {
	    $val = $this->SelfLink();
	} else {
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    
    // -- ADMIN UI: ROWS -- //
    // ++ ADMIN UI: RECORD ++ //
    
    protected function AdminPage() {
    	$oPage = $this->Engine()->App()->Page();

	$doSave = clsHTTP::Request()->GetBool('btnSave');
    	$doEdit = $oPage->PathArg('edit');

	// save edits before showing events
	if ($doSave) {
	    $ftSaveMsg = $this->PageForm()->Save();
	    $this->SelfRedirect($ftSaveMsg);
	}
	
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
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'<input type=submit name=btnCancel value="Cancel">'
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