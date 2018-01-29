<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling Item Types
  HISTORY:
    2016-01-19 started
    2017-05-05 finally writing admin fx()
    2017-05-20 admin trait
*/
trait vtAdminTableAccess_ItemType {
    use vtTableAccess_ItemType;
    
    protected function ItemTypesClass() {
	return 'vctaItemTypes';
    }
    
}
class vctaItemTypes extends vctItTyps implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;
    use ftExecutableTwig;

    // ++ SETUP ++ //
    
    protected function SingularName() {
	return 'vcraItemType';
    }
    public function GetActionKey() {
	return KS_ACTION_CATALOG_ITEM_TYPE;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Item Types');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ RECORDS ++ //
    
    public function DropDown_Records() {
	return $this->SelectRecords(NULL,'IFNULL(Sort,NameSng)');	// sort by Name
    }
    public function ActiveRecords() {
	$sqlFilt = 'isType';
	$sqlSort = 'Sort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$oMenu = fcApp::Me()->GetHeaderMenu();
	
			      // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('show','all','show all',NULL,'show inactive as well as active'));
	    $doAll = $ol->GetIsSelected();
    
	if ($doAll) {
	    $rs = $this->SelectRecords(NULL,'Sort');
	} else {
	    $rs = $this->ActiveRecords();
	}
	return $rs->AdminRows();
    }

    // -- WEB UI -- //
}

class vcraItemType extends vcrItTyp implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftShowableRecord;
    use ftSaveableRecord;
    use ftExecutableTwig;
    use ftLoggedRecord;		// automatically log edits

    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	if ($this->IsNew()) {
	    $sTitle = '+IT';
	    $htTitle = 'new Item Type';
	} else {
	    $id = $this->GetKeyValue();
	    $sName = $this->NameSingular();
	    $sTitle = "IT$id: $sName";
	    $htTitle = "Item Type #$id: $sName";
	}
    
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle('Item Types');
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }
    
    // -- EVENTS -- //
    // ++ FIELD VALUES ++ //
    
    protected function ParentID() {
	return $this->GetFieldValue('ID_Parent');
    }
    protected function IsType() {
	return $this->GetFieldValue('isType');
    }
    protected function AboutString() {
	return $this->GetFieldValue('Descr');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    // TRAIT HELPER
    protected function SelfLink_name() {
	return $this->SelfLink($this->NameSingular());
    }
    // CALLBACK
    public function ListItem_Text() {
	$out = $this->NameSingular();
	if (!$this->IsType()) {
	    $out = "[$out]";	// use brackets to indicate folder-types
	}
	$sAbout = $this->AboutString();
	if (!is_null($sAbout)) {
	    $out .= ' - '.$sAbout;
	}
	return $out;
    }
    // CALLBACK
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    
    // OVERRIDE
    public function Description_forItem() {
	return $this->SelfLink(parent::Description_forItem());
    }
    protected function HasParent() {
	return (!is_null($this->ParentID()));
    }
    protected function ParentLink() {
	if ($this->HasParent()) {
	    $rc = $this->GetTableWrapper()->GetRecord_forKey($this->ParentID());
	    return $rc->SelfLink_name();
	} else {
	    return '(root)';
	}
	
    }

    // -- FIELD CALCULATIONS -- //
    // ++ RECORDS ++ //

    // RETURNS: recordset of Item Types which could potentially be parents of the current record
    protected function ParentRecords() {
	$sqlFilt = 'NOT isType';
	if (!$this->IsNew()) {
	    $id = $this->GetKeyValue();
	    $sqlFilt = "($sqlFilt) AND (ID != $id)";
	}
	return $this->GetTableWrapper()->SelectRecords($sqlFilt,'IFNULL(Sort,NameSng)');
    }
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
      // ++ rows ++ //
    
    protected function AdminRows_settings_columns() {
	return array(
	  'ID' => 'ID',
	  'ID_Parent' 	=> 'Parent',
	  'NameSng'	=> 'Singular',
	  'NamePlr'	=> 'Plural',
	  'Descr'	=> 'Description',
	  'Sort'	=> 'Sort',
	  'isType'	=> 'Type?'
	  );
    }
    // OVERRIDE
    protected function AdminField($sField) {
	switch ($sField) {
	  case 'ID':
	    $val = $this->SelfLink();
	    break;
	  case 'ID_Parent':
	    $val = $this->ParentLink();
	    break;
	  default:
	    $val = $this->GetFieldValue($sField);
	}
	return "<td>$val</td>";
    }

      // -- rows -- //
      // ++ record ++ //
      
    protected function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$doSave = $oFormIn->GetBool('btnSave');

	// save edits before showing events
	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $this->SelfRedirect();
	}

	$oMenu = fcApp::Me()->GetHeaderMenu();
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit this record'));

	    $doEdit = $ol->GetIsSelected();

	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	
	$out = NULL;
	$arCtrls['!ID'] = $this->SelfLink();
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $arCtrls['!extra'] = '<tr>	<td colspan=2><b>Edit notes</b>: <input type=text name="'
	      .KS_FERRETERIA_FIELD_EDIT_NOTES
	      .'" size=60></td></tr>'
	      ;
	    $arCtrls['isType'] .= ' is a type';
	} else {
	    $arCtrls['!extra'] = NULL;
	    $arCtrls['isType'] = $this->IsType()?'type':'folder';
	}

	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {	    
	    $out .= <<<__END__
<input type=submit name="btnSave" value="Save">
<input type=reset value="Reset">
</form>
__END__;
	}
	return $out;
    }
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);

	      $oField = new fcFormField_Num($oForm,'isType');	// currently stored as BOOL (INT)
		$oField->ControlObject(new fcFormControl_HTML_CheckBox($oField));
	      $oField = new fcFormField_Num($oForm,'ID_Parent');
		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
		$oCtrl->SetRecords($this->ParentRecords());
		$oCtrl->AddChoice(NULL,'none (root)');
	      $oField = new fcFormField_Text($oForm,'NameSng');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));
	      $oField = new fcFormField_Text($oForm,'NamePlr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));
	      $oField = new fcFormField_Text($oForm,'Sort');
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
  <tr>	<td align=right><b>ID</b>:</td>		<td>[[!ID]] [[isType]]</td>	</tr>
  <tr>	<td align=right><b>Parent</b>:</td>	<td>[[ID_Parent]]</td>	</tr>
  <tr>	<td align=right><b>Sort Index</b>:</td>	<td>[[Sort]]</td>	</tr>
  <tr>	<td align=right><b>Singular</b>:</td>	<td>[[NameSng]]</td></tr>
  <tr>	<td align=right><b>Plural</b>:</td>	<td>[[NamePlr]]</td>	</tr>
  <tr>	<td align=right><b>About</b>:</td>	<td>[[Descr]]</td>	</tr>
  [[!extra]]
</table>
__END__;

	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }

      // -- record -- //
    
    // -- WEB UI -- //
}