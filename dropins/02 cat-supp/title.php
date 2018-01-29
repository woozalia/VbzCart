<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
/*====
  CLASS: catalog titles
*/
class vctaSCTitles extends vcAdminTable {
    use ftLinkableTable;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'ctg_titles';
    }
    // CEMENT
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_TITLE;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_TITLE;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	throw new exception('2017-04-10 This probably cannot be invoked...');
    }
    
    // -- EVENTS -- //
    // ++ RECORDS ++ //
    
    /*
    public function ActiveRecords() {
	return $this->SelectRecords('isActive');
    }*/
    public function List_forSource($idSrc) {
	return $this->SelectRecords('ID_Source='.$idSrc);
    }
    /*
    public function List_forGroup($idGrp) {
	$rs = $this->GetData('ID_Group='.$idGrp.')');
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ACTIONS ++ //
    
    public function Add($idLCTitle,$idGroup,$idSource) {
	$sqlFilt = "(ID_Title=$idLCTitle) AND (ID_Group=$idGroup) AND (ID_Source=$idSource)";
	$rs = $this->SelectRecords($sqlFilt);
	if ($rs->HasRows()) {
	    $rs->NextRow();
	    $txt = 'Updating SCTitle ID='.$rs->SelfLink();
	    if ($rs->IsActive()) {
		$txt .= ' - was active; no change';
	    } else {
		$txt .= ' - reactivating';
		$arUpd = array(
		  'isActive'	=> 'TRUE',
		  );
		$rs->Update($arUpd);
	    }
	    $arOut['obj'] = $rs;
	} else {
	    $txt = 'Adding';
	    $arIns = array(
	      'ID_Title'	=> $idLCTitle,
	      'ID_Group'	=> $idGroup,
	      'ID_Source'	=> $idSource,
	      'isActive'	=> 'TRUE',
	      );
	    $idNew = $this->Insert($arIns);
	    $arOut['id'] = $idNew;
	    $txt .= ' SCTitle ID='.$idNew;
	}
	$arOut['msg'] = $txt;
	return $arOut;
    }
    /* 2016-02-08 old version
    public function Add($iTitle,$iGroup,$iSource) {
	throw new exception('If anything is still calling this, it will need updating.');

	$sqlFilt = '(ID_Title='.$iTitle.') AND (ID_Group='.$iGroup.') AND (ID_Source='.$iSource.')';
	$rsFnd = $this->GetData($sqlFilt);
	$arChg = array(
	  'isActive'	=> TRUE
	  );
	if ($rsFnd->HasRows()) {
	    $txt = 'Updating ID='.$this->ID;
	    if ($rsFnd->isActive) {
		$txt .= ' - was active; no change';
	    } else {
		$txt .= ' - reactivating';
	    }
	    $this->Update($arChg);
	} else {
	    $txt = 'Adding';
	    $arChg['ID_Title'] = $iTitle;
	    $arChg['ID_Group'] = $iGroup;
	    $arChg['ID_Source'] = $iSource;
	    $this->Insert($arChg);

	    $rsFnd = NULL;
	}
	$arOut['obj'] = $rsFnd;
	$arOut['msg'] = $txt;
	return $arOut;
    }
    */
    
    // -- ACTIONS -- //

}
class vcraSCTitle extends vcAdminRecordset implements fiEventAware {
    use ftLinkableRecord;
    use ftExecutableTwig;	// dispatch events
    use ftLoggableRecord;	// logs changes, displays change log

    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$id = $this->GetKeyValue();
	$sTitle = 'sct '.$id;
	$htTitle = 'Supplier Catalog Title #'.$id;
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ FIELD VALUES ++ //

    public function IsActive() {
	return $this->GetFieldValue('isActive');
    }
    protected function LCTitleID() {
	return $this->GetFieldValue('ID_Title');
    }
    public function SourceID() {
	return $this->GetFieldValue('ID_Source');
    }
    public function GroupID() {
	return $this->GetFieldValue('ID_Group');
    }
    public function WhenDiscontinued() {
	return $this->GetFieldValue('WhenDiscont');
    }
    public function Code() {
	return $this->GetFieldValue('Code');
    }
    public function Descr() {
	return $this->GetFieldValue('Descr');
    }
    public function Supp_CatNum() {
	return $this->GetFieldValue('Supp_CatNum');
    }
    public function Notes() {
	return $this->GetFieldValue('Notes');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    // TRAIT HELPER
    public function SelfLink_name() {
	return $this->SelfLink($this->NameString());
    }
    // CALLBACK
    public function ListItem_Link() {
	return $this->SelfLink($this->NameString());
    }
    // CALLBACK
    public function ListItem_Text() {
	return $this->NameString();
    }
    protected function HasCode() {
	return !is_null($this->Code());
    }
    /*
      This table doesn't really have a "name" field, so we throw some other fields together
	to make a brief descriptor. Not sure how useful this is, but it does at least
	give us some idea of what each record is about.
    */
    protected function NameString() {
	$out = $this->GetKeyValue()
	  .' (s'
	  .$this->SourceID()
	  .' g'
	  .$this->GroupID()
	  .' t'
	  .$this->LCTitleID()
	  .')'
	  ;
	if ($this->HasCode()) {
	    $out .= ' '.$this->Code().' '.$this->Descr();
	}
	return $out;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //
    
    protected function LCTitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function SCSourceTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_SOURCES,$id);
    }
    protected function SCGroupTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_GROUPS,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    // 2015-09-09 These will probably need fixes. Rename them when they do.

    public function TitleObj() {
	throw new exception('Call LCTitleRecord() instead.');
    }
    protected function LCTitleRecord() {
	$id = $this->LCTitleID();
	$rc = $this->LCTitleTable($id);
	return $rc;
    }
    public function SourceObj() {
	throw new exception('Call SCSourceRecord() instead.');
    }
    protected function SCSourceRecord() {
	$id = $this->SourceID();
	$rc = $this->SCSourceTable($id);
	return $rc;
    }
    public function GroupObj() {
	throw new exception('Call SCGroupRecord() instead.');
    }
    // PUBLIC so SC Source Title entry object can use it
    public function SCGroupRecord() {
	$id = $this->GroupID();
	$rc = $this->SCGroupTable($id);
	return $rc;
    }

    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);

	      $oField = new fcFormField_Num($oForm,'isActive');	// currently stored as BOOL (INT)
		$oField->ControlObject(new fcFormControl_HTML_CheckBox($oField));

	      $oField = new fcFormField_Num($oForm,'ID_Title');
//		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
//		$oCtrl->SetRecords($this->TitleTable()->GetData_forDropDown());
//		$oCtrl->AddChoice(NULL,'none (root)');

	      $oField = new fcFormField_Num($oForm,'ID_Group');
	      $oField = new fcFormField_Num($oForm,'ID_Source');

	      $oField = new fcFormField_Time($oForm,'WhenDiscont');

	      $oField = new fcFormField_Text($oForm,'Code');
		$oField->ControlObject()->TagAttributes(array('size'=>8));

	      $oField = new fcFormField_Text($oForm,'Descr');
		$oField->ControlObject()->TagAttributes(array('size'=>40));
		
	      $oField = new fcFormField_Text($oForm,'Supp_CatNum');
		$oField->ControlObject()->TagAttributes(array('size'=>16));
	      
	      $oField = new fcFormField_Text($oForm,'Notes');
		$oField->ControlObject(new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60)));

	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=record-block>
  <tr>	<td align=right><b>ID</b>:</td>		<td>[[!ID]]</td>	</tr>
  <tr>	<td align=right><b>Active</b>:</td>	<td>[[isActive]]</td>	</tr>
  <tr>	<td align=right><b>Title</b>:</td>	<td>[[ID_Title]]</td>	</tr>
  <tr>	<td align=right><b>Group</b>:</td>	<td>[[ID_Group]]</td>	</tr>
  <tr>	<td align=right><b>Source</b>:</td>	<td>[[ID_Source]]</td></tr>
  <tr>	<td align=right><b>Discontinued</b>:</td>	<td>[[WhenDiscont]]</td>	</tr>
  <tr>	<td align=right><b>Supplier Catalog #</b>:</td>	<td>[[Supp_CatNum]]</td>	</tr>
  <tr>	<td colspan=2><b>Saved Notes</b>:<br>[[Notes]]</td>			</tr>
  [[!extra]]
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$doSave = $oFormIn->GetBool('btnSave');

	// save edits before showing events
	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	}
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	//$oMenu = new fcHeaderMenu();	// for putting menu in a section header
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit current record'));

	    $doEdit = $ol->GetIsSelected();

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
	    $arCtrls['!extra'] = '<tr>	<td colspan=2><b>Edit notes</b>: <input type=text name="'
	      .KS_FERRETERIA_FIELD_EDIT_NOTES
	      .'" size=60></td></tr>'
	      ;
	} else {
	    $arCtrls['ID_Title'] = $this->LCTitleRecord()->SelfLink_name();
	    $arCtrls['ID_Group'] = $this->SCGroupRecord()->SelfLink_name();
	    $arCtrls['ID_Source'] = $this->SCSourceRecord()->SelfLink_name();
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
    public function AdminList() {
	if ($this->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>Title</th>
    <th>Source</th>
    <th>Group</th>
    <th>Gone</th>
    <th>SC#</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;

	    while ($this->NextRow()) {
		$css = $isOdd?'odd':'even';
		$htAttr = 'class="'.$css.'"';
		$isOdd = !$isOdd;

		$rcTitle = $this->LCTitleRecord();
		$rcSource = $this->SCSourceRecord();
		$rcGroup = $this->SCGroupRecord();
		$txtActive = $this->isActive()?'&radic;':'-';
		$txtTitle = $rcTitle->CatNum().' '.$rcTitle->NameString();
		$txtSource = $rcSource->Abbreviation();
		$txtGroup = $rcGroup->NameString();
		$dtWhenDisc = $this->WhenDiscontinued();
		if (is_null($dtWhenDisc)) {
		    $txtWhenDisc = '-';
		} else {
		    $xtd = new xtTime($dtWhenDisc);
		    $txtWhenDisc = $xtd->FormatSortable();
		}

		$ftID = $this->SelfLink();
		$ftTitle = $rcTitle->SelfLink($txtTitle);
		$ftSource = $rcSource->SelfLink($txtSource);
		$ftGroup = $rcGroup->SelfLink($txtGroup);
		$sSuppCatNum = $this->GetFieldValue('Supp_CatNum');
		$sNotes = $this->GetFieldValue('Notes');
		$out .= <<<__END__
  <tr $htAttr>
    <td>$ftID</td>
    <td>$txtActive</td>
    <td>$ftTitle</td>
    <td>$ftSource</td>
    <td>$ftGroup</td>
    <td>$txtWhenDisc</td>
    <td>$sSuppCatNum</td>
    <td>$sNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n<table>";
	} else {
	    $out = '<div class=content>No titles found.</div>';
	}
	return $out;
    }

    // -- ADMIN WEB UI -- //
}
