<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling image folders
  HISTORY:
    2016-02-03 started
*/

class vctaFolders extends clsVbzFolders {
    use ftLinkableTable;
    
    // ++ SETUP ++ //
    
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcraFolder');
	  $this->ActionKey(KS_ACTION_FOLDER);
    }
    
    // -- SETUP -- //
    // ++ CALLBACKS ++ //
    
    public function MenuExec() {
	return $this->AdminPage();
    }

    // -- CALLBACKS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$rs = $this->SelectRecords();
	return $rs->AdminRows();
    }
    
    // -- WEB UI -- //
}

class vcraFolder extends clsVbzFolder {
    use ftLinkableRecord;
    use ftShowableRecord;

    // ++ CALLBACKS ++ //
    
    public function MenuExec() {
	return $this->AdminPage();
    }
    public function ListItem_Text() {
	return $this->SpecPart().' - '.$this->Description();
    }
    public function ListItem_Link() {
	return $this->SelfLink($this->ListItem_Text());
    }
    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    public function AdminRows_settings_columns_default() {
	return array(
	    '!ID'	=> 'ID',
	    'ID_Parent'	=> 'Parent',
	    'PathPart'	=> 'Path',
	    'Descr'	=> 'Description'
	  );
    }
    protected function AdminField($sField) {
	if ($sField == '!ID') {
	    $val = $this->SelfLink();
	} else {
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    
    // -- CALLBACKS -- //
    // ++ FRAMEWORK ++ //
    
    protected function PageObject() {
	return $this->Engine()->App()->Page();
    }

    // -- FRAMEWORK -- //
    // ++ FIELD VALUES ++ //
    
    protected function SpecPart() {
	return $this->Value('PathPart');
    }
    protected function Description() {
	return $this->Value('Descr');
    }

    // -- FIELD VALUES -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$oPage = $this->PageObject();
	
	$doEdit = $oPage->URL_RequestObject()->GetBool('edit');
	$doSave = $oPage->HTTP_RequestObject()->GetBool('btnSave');

	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	}
	// page title bar and action links
	
	// -- title string
	$sTitle = 'Folder #'.$this->GetKeyValue();
	$oPage->TitleString($sTitle);
	// -- action links
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	// generate the record display
	
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['!ID'] = $this->SelfLink();
	
	$out = NULL;

	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	}
	
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .= <<<__END__
<input type=submit name=btnSave value="Save">
</form>
__END__;
	}
	
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
  <table>
    <tr><td align=right><b>ID</b>:</td><td>[[!ID]]</td></tr>
    <tr><td align=right><b>Parent</b>:</td><td>[[ID_Parent]]</td></tr>
    <tr><td align=right><b>Relative Path</b>:</td><td>[[PathPart]]</td></tr>
    <tr><td align=right><b>Description</b>:</td><td>[[Descr]]</td></tr>
  </table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    private $oForm;
    private function PageForm() {
	if (empty($this->oForm)) {
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_Parent');
		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
		$oCtrl->Records($this->Table()->SelectRecords());
		$oCtrl->AddChoice(NULL,'-- none --');
	      
	      $oField = new fcFormField_Text($oForm,'PathPart');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));
		
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    
    // -- WEB UI -- //
}