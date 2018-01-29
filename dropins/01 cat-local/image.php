<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Images
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class vctAdminImages extends vctImages_StoreUI implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    protected function SingularName() {
	return KS_CLASS_CATALOG_IMAGE;
    }
    public function GetActionKey() {
	return KS_ACTION_CATALOG_IMAGE;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ ADMIN WEB UI ++ //

    /*-----
      ACTION: Processes form data, shows section header, loads image dataset, calls clsAdminImage::AdminList() to render
      INPUT:
	iarArgs: array of options
	  ['filt'] = SQL filter for dataset to show ("WHERE" clause)
	  ['sort'] = sorting order for dataset to show ("ORDER BY" clause)
	  ['event.obj'] = object used for event logging
	  ['title.id'] = ID_Title to use for new records
	  ['new']: if TRUE, allow user to create new records when editing
      HISTORY:
	2016-01-17 updated nzArray() calls to clsarray::Nz(), and noticed that some functions are still hooked to MediaWiki objects
	2016-03-29 Updated clsArray:: to fcArray::
	2017-05-21 Making $arArgs optional; it may even be unnecessary. Nobody seems to use it.
    */
    public function AdminPage(array $arArgs=array()) {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$oMenu = new fcHeaderMenu();
	$oHdr = new fcSectionHeader('Images',$oMenu);
	$sDoKey = 'do.'.$this->GetActionKey();
		      // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($ol = new fcMenuOptionLink($sDoKey,'edit',NULL,'cancel','edit image records'));
	    $doEdit = $ol->GetIsSelected();  
	    
	  $oMenu->SetNode($ol = new fcMenuOptionLink($sDoKey,'add',NULL,'cancel','add image records'));
	    $doAdd = $ol->GetIsSelected();
	    
	  // "show" option:
	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Display'));
						  // ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('un','show unassigned images','unused'));
	    $doUnused = $ol->GetIsSelected();

	$out = $oHdr->Render();
	
	// 2017-08-01 written but not really tested (need to confirm that SQL is correct)
	if ($doUnused) {
	    $rs = $this->SelectRecords_Unused();
	    $oHdr = new fcSectionHeader('Unassigned');
	    $out .=
	      "\n<table align=right><tr><td>"	// yeah, this is bad form, but I can remember how to do it.
	      .$oHdr->Render()
	      .$rs->AdminRows()
	      ."\n</td></tr></table>"
	      ;
	}
	
	$arArgs['edit'] = $doEdit;
	$sqlFilt = fcArray::Nz($arArgs,'filt');
	$sqlSort = fcArray::Nz($arArgs,'sort');

	/* 2017-03-17 old
	// get URL input
	$doEdit = ($oPage->PathArg('edit.img'));
	$doAdd = ($oPage->PathArg('add.img'));
	$arArgs = $iarArgs;
	$arArgs['edit'] = $doEdit;
	$sqlFilt = fcArray::Nz($arArgs,'filt');
	$sqlSort = fcArray::Nz($arArgs,'sort');

	// display section header

	//clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit.img',NULL,'edit',NULL,'edit image records'),
	  new clsActionLink_option(array(),'add.img',NULL,'add',NULL,'add one or more image records')
	  );
	$oPage->PageHeaderWidgets($arActs);
*/
// handle possible form requests

	// -- bulk-entry form stage 1: check input
	if ($oFormIn->GetBool('btnCheckImgs')) {
	    $doCheck = TRUE;
	    $xts = new xtString($oFormIn->GetString('txtImgs'));
	    $arLines = $xts->ParseTextLines(array('line'=>'arr'));
	    $htForm = "\nImages submitted:\n<ul>";
	    foreach ($arLines as $idx => $arLine) {
		$txtURL = $arLine[0];
		$txtSize = fcArray::Nz($arLine,1);

		$rsFldr = $this->FolderTable()->FindBest($txtURL);
		if (is_null($rsFldr)) {
		    $htForm .= "\n<li>No folder found for <b>$txtUrl</b></li>";
		} else {
		    $fsFldr = $rsFldr->GetFieldValue('PathPart');
		    $fsImg = $rsFldr->Remainder($txtURL);
		    $idFldr = $rsFldr->GetKeyValue();

		    $ftSize = empty($txtSize)?'':'<b>'.$txtSize.'</b> ';
		    $htForm .= "\n<li>$ftSize$fsFldr<a href=\"$fsFldr$fsImg\">$fsImg</a></li>";
		    $htForm .= '<input type=hidden name="img-fldr['.$idx.']" value='.$idFldr.'>';
		    $htForm .= '<input type=hidden name="img-spec['.$idx.']" value="'.$fsImg.'">';
		    if (!is_null($txtSize)) {
			$htForm .= '<input type=hidden name="img-size['.$idx.']" value="'.$txtSize.'">';
		    }
		}
	    }
	    $htForm .= '</ul>';
	} else { $doCheck = FALSE; }
	// -- bulk-entry form stage 2: create records
	if ($oFormIn->GetBool('btnAddImgs')) {
	    $arFldrs = $oFormIn->GetArray('img-fldr');
	    $arSpecs = $oFormIn->GetArray('img-spec');
	    $arSizes = $oFormIn->GetArray('img-size');
	    throw new exception('2017-03-17 At the moment, I am not sure how this is supposed to work. Will need fixing, tho.');
	    $idTitle = $vgPage->Arg('id');
	    assert('!empty($idTitle);');

	    // log event to the title
	    $objTitle = $this->Engine()->Titles($idTitle);
	    $cntImgs = count($arFldrs);
	    $arEv = array(
	      'descr'	=> 'Adding '.$cntImgs.' image'.Pluralize($cntImgs),
	      'where'	=> __METHOD__,
	      'code'	=> 'IMG++'
	      );
	    $objTitle->StartEvent($arEv);

	    foreach ($arFldrs as $idx => $idFldr) {
		$fs = $arSpecs[$idx];
		$sz = $arSizes[$idx];
		$arIns = array(
		  'ID_Folder'	=> $idFldr,
		  'isActive'	=> 'TRUE',
		  'Spec'	=> SQLValue($fs),
		  'ID_Title'	=> $idTitle,
		  'WhenAdded'	=> 'NOW()'
		  );
		if (!empty($sz)) {
		  $arIns['Ab_Size'] = SQLValue($sz);
		}
		$this->Insert($arIns);
		$strNew = ' '.$this->LastID();
	    }
	    $arEv = array(
	      'descrfin'	=> 'New ID'.Pluralize($cntImgs).':'.$strNew
	      );
	    $objTitle->FinishEvent($arEv);
	}
	// -- existing item edit form
	if ($oFormIn->GetBool('btnSaveImgs')) {

	    $arUpdate = $oFormIn->GetArray('update');
	    $arDelete = $oFormIn->GetArray('del');
	    $arActive = $oFormIn->GetArray('isActive');
	    $arFolder = $oFormIn->GetArray('ID_Folder');
	    $arFileSpec = $oFormIn->GetArray('txtFileSpec');
	    $arAttrSize = $oFormIn->GetArray('txtAttrSize');
	    $arAttrFldr = $oFormIn->GetArray('txtAttrFldr');
	    $arAttrDispl = $oFormIn->GetArray('txtAttrDispl');
	    $arAttrSort = $oFormIn->GetArray('txtAttrSort');

	    if (count($arActive > 0)) {
		// add any reactivated rows to the update list
		foreach ($arActive as $id => $null) {
		    $arUpdate[$id] = TRUE;
		}
	    }

	    $cntRows = count($arUpdate);
	    if ($cntRows > 0) {
		$out .= '<b>Updating</b>: ';
		$txtEvDescr = 'Checking '.$cntRows.' record'.fcString::Pluralize($cntRows);
		$txtRowEdits = '';
		$txtRowFlips = '';

		$doLog = isset($arArgs['event.obj']);
		if ($doLog) {
		    $objLog = $arArgs['event.obj'];
		    $arEv = array(
		      'descr'	=> $txtEvDescr,
		      'where'	=> __METHOD__,
		      'code'	=> 'IMG SVM'	// image save multiple
		      );
		    $objLog->StartEvent($arEv);
		}

		$cntUpd = 0;
		foreach ($arUpdate as $id => $null) {
		    $isActive = isset($arActive[$id]);
		    $isNew = ($id == KS_NEW_REC);
		    if (empty($arFileSpec[$id])) {
			$isNew = FALSE;	// nothing to save
		    }
		    $objImg = $this->GetRecord_forKey($id);

		    if ($isNew) {
			$isDiff = TRUE;
			$doSaveActive = TRUE;
			$doSaveValues = TRUE;
		    } else {
			$isDiff = ((int)$isActive != (int)$objImg->isActive);
			$isStateChg = $isDiff;
			$doSaveActive = $isDiff;
			$doSaveValues = !$isDiff;
		    }
		    if (!$isDiff) {
			$arUpd = array(
			  'ID_Folder'	=> $arFolder[$id],
			  'Spec'	=> $arFileSpec[$id],
			  'Ab_Size'	=> $arAttrSize[$id],
			  'AttrFldr'	=> fcArray::Nz($arAttrFldr,$id),
			  'AttrDispl'	=> fcArray::Nz($arAttrDispl,$id),
			  'AttrSort'	=> fcArray::Nz($arAttrSort,$id)
			  );
			$isDiff = !$objImg->SameAs($arUpd);
		    }
		    $arUpd = NULL;
		    if ($isDiff) {
			if ($doSaveValues) {
			    $arUpd = array(
			      'ID_Folder'	=> SQLValue($arFolder[$id]),
			      'Spec'		=> SQLValue($arFileSpec[$id]),
			      'Ab_Size'		=> SQLValue($arAttrSize[$id]),
			      'AttrFldr'	=> SQLValue(nz($arAttrFldr[$id])),
			      'AttrDispl'	=> SQLValue(nz($arAttrDispl[$id])),
			      'AttrSort'	=> SQLValue(nz($arAttrSort[$id]))
			      );
			    $txtRowEdits .= ' #'.$id;
			}
			if ($doSaveActive) {
			    $arUpd['isActive'] = SQLValue($isActive);
			    $txtRowFlips .= ' #'.$id.'('.fcString::NoYes($isActive,'off','ON').')';
			}
			if ($isNew) {
			    // create new record
			    $idTitle = (int)$arArgs['title.id'];
			    assert('is_int($idTitle)');
			    $arUpd['ID_Title'] = $idTitle;
			    $this->Insert($arUpd);
			} else {
			    // not new: just update
			    $objImg->Update($arUpd);
			}
			$cntUpd++;
		    }
		}
		if ($doLog) {
		    $txtStat = $cntUpd.' image'.Pluralize($cntUpd).' updated -';
		    if (!empty($txtRowFlips)) {
			$txtStat .= ' toggled:'.$txtRowEdits;
		    }
		    if (!empty($txtRowEdits)) {
			$txtStat .= ' edits:'.$txtRowEdits;
		    }
		    $arEv = array(
		      'descrfin' => SQLValue($txtStat)
		      );
		    $objLog->FinishEvent($arEv);
		}
		$out .= $txtStat;
	    } else {
		$out .= 'No image records selected for update.';
	    }
	}

	// if no external filter, display search form
	if (is_null($sqlFilt)) {
	    // we'll trust admins, for now, not to try to hack the database:
	    $sqlFilt = $oFormIn->GetString('sqlFilt');
	    $htFilt = fcString::EncodeForHTML($sqlFilt);
	    $out .= <<<__END__
	    <form method=get>
Search filter (SQL WHERE clause):<input name=sqlFilt width=30 value="$htFilt">
<input type=submit value="Search">
</form>
__END__;
	}
	if (!is_null($sqlFilt)) {
	    // render edit form outer shell:
	    if ($doEdit || $doAdd || $doCheck) {

		$arArgs['pfx'] = "\n<form method=post>";

		if ($doEdit) {
		    $sfx = '<input type=submit name=btnSaveImgs value="Save">';
		    $sfx .= '<input type=reset value="Revert">';
		}
		if ($doAdd) {
		    $sfx = 'Enter images, one complete URL per line:';
		    $sfx .= '<textarea name=txtImgs rows=6></textarea>';
		    $sfx .= '<input type=submit name=btnCheckImgs value="Check">';
		}
		if ($doCheck) {
		    $sfx = $htForm;	// calculated earlier
		    $sfx .= '<input type=submit name=btnAddImgs value="Add These">';
		}
		$sfx .= '</form>';
		$arArgs['sfx'] = $sfx;
	    }

	// load the latest data and show it in a table:
	    $arArgs['none'] = 'No images found.';
	    $rs = $this->SelectRecords($sqlFilt,$sqlSort);
	    $out .= $rs->AdminList($arArgs);
	}
	return "<div class=content>$out</div>";
    }
    /*----
      HISTORY:
	2010-10-19 Adapted from AdminPage()
	2010-11-16 Commented out editing functions, per earlier thought, after partly updating them.
	  Note: they were written for AdminPage().
    */
    /* 2016-03-05 This is almost certainly obsolete. ...(2017-07-30) except we don't seem to have a menu item that does this.
    public function AdminPage_Unassigned() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	$out = '';

	// get URL input
	$doEdit = ($vgPage->Arg('edit.img'));
	$arArgs['edit'] = $doEdit;

	// display section header
//	$strName = 'Images';

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Unassigned Images',NULL,3);
	$objSection->ToggleAdd('edit','edit image records','edit.img');
	$out .= $objSection->Generate();

	// load the latest data and show it in a table:
	$arArgs['none'] = 'No unassigned images found.';
	$sqlFilt = 'ID_Title IS NULL';
	$sqlSort = NULL;	// maybe this could be useful later
	$objRows = $this->GetData($sqlFilt,NULL,$sqlSort);
	$out .= $objRows->AdminList($arArgs);

	$objSection = new clsWikiSection($objPage,'Imageless Active Titles',NULL,3);
	$out .= $objSection->Generate();
	$objRows = $this->objDB->Titles()->Data_Imageless();

	$out .= $objRows->AdminSummary();

	// show the complete list
	$out .= $objRows->AdminList($arArgs);

	$wgOut->AddHTML($out); $out = NULL;
	return $out;
    }//*/
    
    // ++ READ DATA ++ //
    
    protected function SelectRecords_Unused() {
	$sqlFilt = 'isActive AND (ID_Title IS NULL)';
	$sqlSort = NULL;	// maybe this could be useful later (plenty of useful options...)
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- READ DATA -- //
}
class vcrAdminImage extends vcrImage_StoreUI implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftShowableRecord;
    use ftLoggableRecord;
    use ftExecutableTwig;
    use ftSaveableRecord;

    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }
    protected function FoldersClass() {
	return KS_CLASS_FOLDERS;
    }

    // -- CLASS NAMES -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sTitle = 'Image #'.$this->GetKeyValue().': '.$this->SpecPart();
	
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle($sTitle);
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ FIELD CALCS ++ //

    protected function TitleLink($sText=NULL) {
	$rc = $this->TitleRecord();
	if (is_null($rc)) {
	    return $sText;
	} else {
	    return $rc->SelfLink($sText);
	}
    }
    /*----
      ACTION: Render $htContent as a link directly to the image file
    */
    public function RenderImageLink($htContent) {
	$fsImg = $this->WebSpec();
	$out = "<a href='$fsImg'>$htContent</a>";
	return $out;
    }

    // -- FIELD CALCS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      ACTION: Renders the current record as an editable page
    */
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	//$doEdit = $oPage->PathArg('edit');
	$doSave = $oFormIn->GetBool('btnSave');

	// save edits before showing events
	if ($doSave) {
	    $sNotes = $oFormIn->GetText('EvNotes');
	    $frm = $this->PageForm();
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $rcEv->Finish();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	}
	
	/*
	if ($doSave) {
	    $sNotes = clsHTTP::Request()->GetText('EvNotes');
	    $arEv = array(
	      clsSysEvents::ARG_DESCR_START	=> 'update img record',
	      clsSysEvents::ARG_NOTES		=> $sNotes,
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      clsSysEvents::ARG_CODE		=> 'UPD',
	      );
	    $rcEv = $this->CreateEvent($arEv);
	    $frm = $this->PageForm();
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $rcEv->Finish();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	} */

	// page title bar and menu

	$oMenu = fcApp::Me()->GetHeaderMenu();

	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit the image record'));
	    $doEdit = $ol->GetIsSelected();

	/*
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
	    $htShop = $this->RenderPageLink('exhibit',TRUE);
	    $fsFull = $this->WebSpec();
	    $fsPart = $this->Spec();
	    // 2016-02-12 ShopLink() not tested; ok to remove it if it is broken.
	    $arCtrls['Spec'] = "<a href='$fsFull'>$fsPart</a> [$htShop]";
	    $arCtrls['ID_Title'] = $this->TitleLink();
	}
	
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .= <<<__END__
<input type=submit name=btnSave value="Save">
edit notes: <input name=EvNotes width=40>
</form>
__END__;
	}

	if (!$this->IsNew()) {
	    $out .= $this->EventListing();
	}

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
  <table class=form-record>
    <tr><td align=right>ID:</td><td>[[!ID]] [[isActive]]</td></tr>
    <tr><td align=right>Folder:</td><td>[[ID_Folder]]</td></tr>
    <tr><td align=right>Spec:</td><td>[[Spec]]</td></tr>
    <tr><td align=right>Title:</td><td>[[ID_Title]]</td></tr>
    <tr><td align=right>Size:</td><td>[[Ab_Size]]</td></tr>
    <tr><td colspan=2>Folder details:</td></tr>
    <tr><td align=right>Attribute:</td><td>[[AttrFldr]]</td></tr>
    <tr><td align=right>Display:</td><td>[[AttrDispl]]</td></tr>
    <tr><td align=right>Sort:</td><td>[[AttrSort]]</td></tr>
    <tr><td align=right>When Added:</td><td>[[WhenAdded]]</td></tr>
    <tr><td align=right>When Edited:</td><td>[[WhenEdited]]</td></tr>
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

	      $oField = new fcFormField_BoolInt($oForm,'isActive');
	    
	      $oField = new fcFormField_Num($oForm,'ID_Folder');
		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
		$oCtrl->SetRecords($this->FolderTable()->SelectRecords());
	      
	      $oField = new fcFormField_Text($oForm,'Spec');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));
		
	      $oField = new fcFormField_Num($oForm,'ID_Title');

	      $oField = new fcFormField_Text($oForm,'Ab_Size');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));

	      $oField = new fcFormField_Text($oForm,'AttrFldr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>15));
	      
	      $oField = new fcFormField_Text($oForm,'AttrDispl');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>30));
		
	      $oField = new fcFormField_Text($oForm,'AttrSort');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>15));
		
	      $oField = new fcFormField_Time($oForm,'WhenAdded');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>15));
		$oCtrl->Editable(FALSE);
		
	      $oField = new fcFormField_Time($oForm,'WhenEdited');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>15));
		$oCtrl->Editable(FALSE);

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    /*----
      CALLBACK for AdminRows()
      TODO: this will need updating if we ever have different columns
    */
    protected function AdminRows_start(array $arOptions=NULL) {
	return "\n<table class=listing>"
	  ."\n  <tr>"
	  ."\n    <th colspan=4></th>"
	  ."\n    <th colspan=4>Attributes</th>"
	  ."\n    <th colspan=2>Timestamps</th>"
	  ."\n  </tr>"
	  ;
    }
    protected function AdminRows_settings_columns() {
	$arFlds = array('ID'	=> 'ID',
	  'isActive'	=> 'A?',
	  'ID_Folder'	=> 'Folder',
	  'Spec'	=> 'File',
	  'Ab_Size'	=> 'Size',
	  'AttrFldr'	=> 'Folder',
	  'AttrDispl'	=> 'Display',
	  'AttrSort'	=> 'Sort',
	  'WhenAdded'	=> 'Added',
	  'WhenEdited'	=> 'Edited',
	  );
	return $arFlds;
    }
    
    // CALLBACK for AdminRows()
    protected function AdminField($sField,array $arOptions=NULL) {
	switch ($sField) {
	  case 'ID':
	    $val = $this->SelfLink();
	    break;
	  case 'isActive':
	    $val = fcHTML::fromBool($this->IsActive());
	    break;
	  case 'Spec':
	    $val = $this->RenderImageLink($this->Spec(),TRUE);
	    break;
	  default:
	    $val = $this->GetFieldValue($sField);
	}
	return "<td>$val</td>";
    }

    /*-----
      ACTION: Renders dataset as a table
      INPUT: iarArgs
	none: text to insert as description if no records
	pfx: html to include before table rows, if there is data
	sfx: html to include after table rows, if there is data
	edit: render editable fields
	new: allow entering new records -- show edit even if no data
    */
    public function AdminList() {
	$out = $this->AdminRows();
    
	return $out;
    }
}
