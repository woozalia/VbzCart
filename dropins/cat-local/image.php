<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Images
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class VCTA_Images extends clsImages_StoreUI {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_images');
	  $this->KeyName('ID');
	  $this->ClassSng(KS_CLASS_CATALOG_IMAGE);
	  $this->ActionKey(KS_ACTION_CATALOG_IMAGE);
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$arOpts = array(
	  'new'	=> TRUE
	  );
	$out = $this->AdminPage($arOpts);
	return $out;
    }

    // -- DROP-IN API -- //
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
    */
    public function AdminPage(array $iarArgs) {
	$oPage = $this->Engine()->App()->Page();
	$out = '';

	// get URL input
	$doEdit = ($oPage->PathArg('edit.img'));
	$doAdd = ($oPage->PathArg('add.img'));
	$arArgs = $iarArgs;
	$arArgs['edit'] = $doEdit;
	$sqlFilt = nzArray($arArgs,'filt');
	$sqlSort = nzArray($arArgs,'sort');

	// display section header

	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit.img',NULL,'edit',NULL,'edit image records'),
	  new clsActionLink_option(array(),'add.img',NULL,'add',NULL,'add one or more image records')
	  );
	$oPage->PageHeaderWidgets($arActs);

// handle possible form requests

	// -- bulk-entry form stage 1: check input
	if ($oPage->ReqArgBool('btnCheckImgs')) {
	    $doCheck = TRUE;
	    $xts = new xtString($oPage->ReqArgText('txtImgs'));
	    $arLines = $xts->ParseTextLines(array('line'=>'arr'));
	    $htForm = "\nImages submitted:\n<ul>";
	    foreach ($arLines as $idx => $arLine) {
		$txtURL = $arLine[0];
		$txtSize = isset($arLine[1])?$arLine[1]:NULL;

		$rsFldr = $this->Engine()->Folders()->FindBest($txtURL);
		if (is_null($rsFldr)) {
		    $htForm .= "\n<li>No folder found for <b>$txtUrl</b></li>";
		} else {
		    $fsFldr = $rsFldr->Value('PathPart');
		    $fsImg = $rsFldr->Remainder($txtURL);
		    $idFldr = $rsFldr->KeyValue();

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
	if ($oPage->ReqArgBool('btnAddImgs')) {
	    $arFldrs = $wgRequest->GetArray('img-fldr');
	    $arSpecs = $wgRequest->GetArray('img-spec');
	    $arSizes = $wgRequest->GetArray('img-size');
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
	if ($oPage->ReqArgBool('btnSaveImgs')) {

	    $arUpdate = $oPage->ReqArgArray('update');
	    $arDelete = $oPage->ReqArgArray('del');
	    $arActive = $oPage->ReqArgArray('isActive');
	    $arFolder = $oPage->ReqArgArray('ID_Folder');
	    $arFileSpec = $oPage->ReqArgArray('txtFileSpec');
	    $arAttrSize = $oPage->ReqArgArray('txtAttrSize');
	    $arAttrFldr = $oPage->ReqArgArray('txtAttrFldr');
	    $arAttrDispl = $oPage->ReqArgArray('txtAttrDispl');
	    $arAttrSort = $oPage->ReqArgArray('txtAttrSort');

	    if (count($arActive > 0)) {
		// add any reactivated rows to the update list
		foreach ($arActive as $id => $null) {
		    $arUpdate[$id] = TRUE;
		}
	    }

	    $cntRows = count($arUpdate);
	    if ($cntRows > 0) {
		$out .= '<b>Updating</b>: ';
		$txtEvDescr = 'Checking '.$cntRows.' record'.Pluralize($cntRows);
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
		    $isNew = ($id == 'new');
		    if (empty($arFileSpec[$id])) {
			$isNew = FALSE;	// nothing to save
		    }
		    $objImg = $this->GetItem($id);

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
			  'AttrFldr'	=> nz($arAttrFldr[$id]),
			  'AttrDispl'	=> nz($arAttrDispl[$id]),
			  'AttrSort'	=> nz($arAttrSort[$id])
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
			    $txtRowFlips .= ' #'.$id.'('.NoYes($isActive,'off','ON').')';
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
//global $sql;
//$out .= '<br>SQL: '.$objImg->sqlExec;
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
	    $sqlFilt = clsHTTP::Request()->GetText('sqlFilt');
	    $htFilt = htmlspecialchars($sqlFilt);
	    $out .= <<<__END__
	    <form method=get>
Search filter:<input name=sqlFilt width=30 value="$htFilt">
<input type=submit value="Search">
</form>
__END__;
	}
	if (!is_null($sqlFilt)) {
	    // render edit form outer shell:
	    if ($doEdit || $doAdd || $doCheck) {
		$arLink = $oPage->PathArgs(array('page','id'));

		// this can/should probably just be replaced by removing "action=" and redirecting after a form-save
		//$urlSelf = $this->SelfURL($arLink,TRUE);
		//$arArgs['pfx'] = '<form method=post action="'.$urlSelf.'">';
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
	    $objRows = $this->GetData($sqlFilt,NULL,$sqlSort);
	    $out .= $objRows->AdminList($arArgs);
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-10-19 Adapted from AdminPage()
	2010-11-16 Commented out editing functions, per earlier thought, after partly updating them.
	  Note: they were written for AdminPage().
    */
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
    }
}
class VCRA_Image extends clsImage_StoreUI {
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }

    // -- CLASS NAMES -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ DATA FIELD ACCESS ++ //

    protected function TitleLink($sText=NULL) {
	$rc = $this->TitleRecord();
	if (is_null($rc)) {
	    return $sText;
	} else {
	    return $rc->AdminLink($sText);
	}
    }

    // -- DATA FIELD ACCESS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      ACTION: Renders the current record as an editable page
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	$doSave = $oPage->ReqArgBool('btnSave');

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    if ($doSave) {
		$sNotes = clsHTTP::Request()->GetText('notes');
		$ftSaveStatus = $this->AdminSave($sNotes);
	    }
	}

	// page title string
	$sTitle = 'Image #'.$this->KeyValue().': '.$this->Value('Spec');
	$oPage->TitleString($sTitle);

	// page title bar and action links
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	$out = NULL;

	$ftID = $this->AdminLink();
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $frmPage = $this->PageForm();

	    $ctIsActv	= $frmPage->RenderControl('isActive');
	    $ctFolder	= $frmPage->RenderControl('ID_Folder');
	    $ctSpec	= $frmPage->RenderControl('Spec');
	    $ctTitle	= $frmPage->RenderControl('ID_Title');
	    $ctAbSize	= $frmPage->RenderControl('Ab_Size');
	    $ctAtFldr	= $frmPage->RenderControl('AttrFldr');
	    $ctAtDispl	= $frmPage->RenderControl('AttrDispl');
	    $ctAtSort	= $frmPage->RenderControl('AttrSort');
	    $ctWhenAdd	= $frmPage->RenderControl('WhenAdded');
	    $ctWhenEd	= $frmPage->RenderControl('WhenEdited');
	    $htShut = <<<__END__
<input type=submit name=btnSave value="Save">
edit notes: <input name=notes width=40>
</form>
__END__;
	} else {
	    $ctIsActv	= clsHTML::fromBool($this->Value('isActive'),'active','inactive');
	    $ctFolder	= $this->FolderPath();
	    $ctSpec	= $this->ShopLink($this->Value('Spec'));
	    $ctTitle	= $this->TitleLink();
	    $ctAbSize	= $this->Value('Ab_Size');
	    $ctAtFldr	= $this->Value('AttrFldr');
	    $ctAtDispl	= $this->Value('AttrDispl');
	    $ctAtSort	= $this->Value('AttrSort');
	    $ctWhenAdd	= $this->Value('WhenAdded');
	    $ctWhenEd	= $this->Value('WhenEdited');
	    $htShut = NULL;
	}

	$out .= <<<__END__
  <table>
    <tr><td align=right>ID:</td><td>$ftID $ctIsActv</td></tr>
    <tr><td align=right>Folder:</td><td>$ctFolder</td></tr>
    <tr><td align=right>Spec:</td><td>$ctSpec</td></tr>
    <tr><td align=right>Title:</td><td>$ctTitle</td></tr>
    <tr><td align=right>Size:</td><td>$ctAbSize</td></tr>
    <tr><td align=right title="folder attribute">F.Attr:</td><td>$ctAtFldr</td></tr>
    <tr><td align=right>Sort:</td><td>$ctAtSort</td></tr>
    <tr><td align=right>When Added:</td><td>$ctWhenAdd</td></tr>
    <tr><td align=right>When Edited:</td><td>$ctWhenEd</td></tr>
  </table>
$htShut
__END__;
	$out .= "\n<br clear=both>".$this->RenderInline()
	  .$oPage->SectionHeader('Event Log',NULL,'section-header-sub')
	  .$this->EventListing();

	return $out;
    }
    public function AdminSave($sNotes) {
	$out = $this->PageForm()->Save($sNotes,array('edit'=>FALSE));
	return $out;
    }
    private function PageForm() {
	if (is_null($this->frmPage)) {
	    $frmPage = new clsForm_recs($this);

	    $frmPage->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML_CheckBox());
	    $frmPage->AddField(new clsFieldNum('ID_Folder'),	new clsCtrlHTML(array('size'=>2)));
	    $frmPage->AddField(new clsField('Spec'),		new clsCtrlHTML(array('size'=>30)));
	    $frmPage->AddField(new clsFieldNum('ID_Title'),	new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('Ab_Size'),	new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsField('AttrFldr'),	new clsCtrlHTML(array('size'=>15)));
	    $frmPage->AddField(new clsField('AttrDispl'),	new clsCtrlHTML(array('size'=>30)));
	    $frmPage->AddField(new clsField('AttrSort'),	new clsCtrlHTML(array('size'=>15)));
	    $frmPage->AddField(new clsFieldTime('WhenAdded'),	new clsCtrlHTML(array('size'=>20)));
	    $frmPage->AddField(new clsFieldTime('WhenEdited'),	new clsCtrlHTML(array('size'=>20)));

	    $this->frmPage = $frmPage;
	}
	return $this->frmPage;
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
    public function AdminList(array $iarArgs) {
	$out = '';
	$doNew = nz($iarArgs['new']);
	if ($doNew || $this->HasRows()) {
	    $doEdit = nz($iarArgs['edit']);
	    $out .= nz($iarArgs['pfx']);
	    $out .= "\n<table>";
	    $out .= '<tr><td colspan=4></td><th colspan=4 bgcolor="#cceeff">attributes</th></tr>';
	    $out .= '<tr>'
	      .'<th>ID</th>'
	      .'<th>A?</th>'
	      .'<th>folder</th>'
	      .'<th>filename</th>'
	      .'<th>size</th>'
	      .'<th bgcolor="#cceeff">folder</th>'
	      .'<th bgcolor="#cceeff">description</th>'
	      .'<th bgcolor="#cceeff">sorting</th>'
	      .'</tr>';
	    $isOdd = FALSE;
	    while ($this->NextRow()) {
		$out .= $this->AdminListRow($doEdit,$isOdd);
		$isOdd = !$isOdd;
	    }
	    if ($doEdit) {
		// when editing, we also allow for adding a new record
		$out .= $this->AdminListRow($doEdit,$isOdd,TRUE);
	    }
	    $out .= "\n</table>";
	    $out .= nz($iarArgs['sfx']);
	} else {
	    if (isset($iarArgs['none'])) {
		$out .= $iarArgs['none'];
	    } else {
		$out .= 'No images found.';
	    }
	}
	return $out;
    }
    protected function AdminListRow($iEdit,$iOdd,$iNew=FALSE) {
	$isOdd = $iOdd;
	$doEdit = $iEdit;

	if ($iNew) {
	    $doEdit = TRUE;
	    $isActive = TRUE;
	    $id = 'new';
	    $txtFileSpec = '';
	    $txtFolder = '';
	    $txtAttrSize = '';
	    $txtAttrFldr = '';
	    $txtAttrDispl = '';
	    $txtAttrSort = '';
	} else {
	    //$id = $this->KeyValue();
	    $wtID = $this->AdminLink();
	    $isActive = $this->isActive;
	    $txtFileSpec = $this->Spec;
	    $txtFolder = $this->FolderPath();
	    $txtAttrSize = $this->Ab_Size;
	    $txtAttrFldr = $this->AttrFldr;
	    $txtAttrDispl = $this->AttrDispl;
	    $txtAttrSort = $this->AttrSort;
	}

	$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
	if ($isActive) {
	    $wtStyleCell = '';
	    $ftActive = '&radic;';
	} else {
	    $wtStyle .= ' color: #888888;';
	    $wtStyleCell = ' style="text-decoration: line-through;"';
	    $ftActive = '';
	}
	if ($doEdit) {
	    $id = $this->KeyValue();
	    // replace field values with editable versions
	    if ($isActive) {
		$wtID = '<input type=hidden name=update['.$id.'] value=1>'.$id;
	    } else {
		$wtID = '<input type=checkbox name=del['.$id.'] title="check rows to delete">'.$id;
	    }
	    $htEnabled = $isActive?'':' disabled';
	    $ftActive = '<input type=checkbox name=isActive['.$id.'] title="check if this image should be used"'.($isActive?' checked':'').'>';
	    $htFolder = $this->objDB->Folders()->DropDown('ID_Folder['.$id.']',$this->ID_Folder);
	    $htFileSpec = '<input'.$htEnabled.' size=20 name=txtFileSpec['.$id.'] value="'.htmlspecialchars($txtFileSpec).'">';
	    $htAttrSize = '<input'.$htEnabled.' size=4 name=txtAttrSize['.$id.'] value="'.htmlspecialchars($txtAttrSize).'">';
	    $htAttrFldr = '<input'.$htEnabled.' size=4 name=txtAttrFldr['.$id.'] value="'.htmlspecialchars($txtAttrFldr).'">';
	    $htAttrDispl = '<input'.$htEnabled.' size=10 name=txtAttrDispl['.$id.'] value="'.htmlspecialchars($txtAttrDispl).'">';
	    $htAttrSort = '<input'.$htEnabled.' size=2 name=txtAttrSort['.$id.'] value="'.htmlspecialchars($txtAttrSort).'">';
	} else {
	    $htFolder = $txtFolder;
	    $htFileSpec = $txtFileSpec = '<a href="'.$this->WebSpec().'">'.$txtFileSpec.'</a>';

	    $htAttrSize = $txtAttrSize;
	    $htAttrFldr = $txtAttrFldr;
	    $htAttrDispl = $txtAttrDispl;
	    $htAttrSort = $txtAttrSort;
	}

	$out = "\n<tr style=\"$wtStyle\">".
	    "\n<td align=right$wtStyleCell>$wtID</td>".
	    "\n<td$wtStyleCell>$ftActive</td>".
	    "\n<td$wtStyleCell>$htFolder</td>".
	    "\n<td$wtStyleCell>$htFileSpec</td>".
	    "\n<td$wtStyleCell>$htAttrSize</td>".
	    "\n<td$wtStyleCell>$htAttrFldr</td>".
	    "\n<td$wtStyleCell>$htAttrDispl</td>".
	    "\n<td$wtStyleCell>$htAttrSort</td>".
	    "\n</tr>";

	return $out;
    }
}
