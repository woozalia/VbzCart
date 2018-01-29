<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling image folders
  HISTORY:
    2016-02-03 started
*/

class vctaFolders extends vctFolders  /* implements fiEventAware, fiLinkableTable */ {
    //use ftLinkableTable;
    
    // ++ SETUP ++ //
    
    protected function SingularName() {
	return 'vcraFolder';
    }
    public function GetActionKey() {
	return KS_ACTION_FOLDER;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->AdminPage();
    }
    /*
    public function MenuExec() {
	return $this->AdminPage();
    }*/

    // -- CALLBACKS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$rs = $this->SelectRecords();
	return $rs->AdminRows();
    }
    
    // -- WEB UI -- //
}
class vcraFolder extends vcrFolder implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftShowableRecord;
    use ftExecutableTwig;
    use ftSaveableRecord;	// implements fiEditableRecord

    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sTitle = 'Folder #'.$this->GetKeyValue();
	
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle($sTitle);
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*
    public function MenuExec() {
	return $this->AdminPage();
    } */
    
    // -- EVENTS -- //
    // ++ FIELD VALUES ++ //
    
    protected function SpecPart() {
	return $this->GetFieldValue('PathPart');
    }
    protected function Description() {
	return $this->GetFieldValue('Descr');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    // CALLBACK
    public function ListItem_Text() {
	return $this->SpecPart().' - '.$this->Description();
    }
    // CALLBACK
    public function ListItem_Link() {
	return $this->SelfLink($this->ListItem_Text());
    }

    // -- FIELD CALCULATIONS -- //
    // ++ WEB UI ++ //

      // ++ lines ++ //
    
    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    public function AdminRows_settings_columns() {
	return array(
	    '!ID'	=> 'ID',
	    //'ID_Parent'	=> 'Parent',
	    'PathPart'	=> 'Path',
	    'Descr'	=> 'Description'
	  );
    }
    protected function AdminField($sField) {
	if ($sField == '!ID') {
	    $val = $this->SelfLink();
	} else {
	    $val = $this->GetFieldValue($sField);
	}
	return "<td>$val</td>";
    }
    
      // -- lines -- //
      // ++ record ++ //

    protected function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	//$doEdit = $oPage->URL_RequestObject()->GetBool('edit');
	$doSave = $oFormIn->GetBool('btnSave');

	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	}
	// page title bar and menu

	$oMenu = fcApp::Me()->GetHeaderMenu();
	
		  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit this folder record'));
	    $doEdit = $ol->GetIsSelected();
	
	/*
	// -- title string
	$sTitle = 'Folder #'.$this->GetKeyValue();
	$oPage->TitleString($sTitle);
	// -- action links
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);
	*/

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
	
	$oTplt->SetVariableValues($arCtrls);
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
  <table class=form-block>
    <tr><td class=form-block>ID</td><td>: [[!ID]]</td></tr>
    <tr><td class=form-block>Relative Path</td><td>: [[PathPart]]</td></tr>
    <tr><td class=form-block>Description</td><td>: [[Descr]]</td></tr>
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
	    /* 2017-07-23 field removed
	      $oField = new fcFormField_Num($oForm,'ID_Parent');
		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
		$oCtrl->SetRecords($this->GetTableWrapper()->SelectRecords());
		$oCtrl->AddChoice(NULL,'-- none --');
	      */
	      $oField = new fcFormField_Text($oForm,'PathPart');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));
		
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }

       // -- record -- //
   
    // -- WEB UI -- //
}