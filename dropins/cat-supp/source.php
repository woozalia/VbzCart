<?php
/*
  FILE: admin.cat.php -- catalog administration for VbzCart
  HISTORY:
    2010-10-15 Extracted catalog maintenance classes from SpecialVbzAdmin.php
  FUTURE: This should be renamed admin.ctg.php for consistency
*/

class VCTA_SCSources extends clsTable {
   //const TableName='ctg_sources';

   public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng(KS_CLASS_SUPPCAT_SOURCE);
	  $this->Name('ctg_sources');
	  $this->KeyName('ID');
	  $this->ActionKey('ctg');
    }
    public function DropDown($iName,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    while ($this->NextRow()) {
		if ($this->ID == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= '<option'.$htSelect.' value="'.$this->ID.'">'.$this->Descr().'</option>';
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No shipments matching filter';
	}
	return $out;
    }
}
class VCRA_SCSource extends clsDataSet {
    protected $objForm, $objCtrls, $objCtrlTopics;
    private $objSupp;

    // ++ BOILERPLATE: event logging ++ //

    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }

    // ++ BOILERPLATE: self-linking ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminRedirect($this,$iarArgs);
    }

    // -- BOILERPLATE -- //
    // ++ BOILERPLATE AUXILIARY ++ //

    public function AdminLink_name() {
	$out = $this->AdminLink($this->Name);
	if (!$this->IsActive()) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }

    // -- BOILERPLATE AUXILIARY -- //
    // ++ DATA FIELD ACCESS ++ //

    public function IsActive() {
	return is_null($this->Value('ID_Supercede'));
    }

    // ++ DATA RECORD ACCESS ++ //

    public function SuppObj() {
	$idSupp = $this->Value('ID_Supplier');
	$doGet = TRUE;
	if (!empty($this->objSupp)) {
	    if ($this->objSupp->Value('ID') == $idSupp) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->objSupp = $this->Engine()->Suppliers()->GetItem($idSupp);
	}
	return $this->objSupp;
    }
    public function SupercedeObj() {
	$objSuper = $this->objDB->Catalogs()->GetItem($this->ID_Supercede);
	return $objSuper;
    }

    // -- DATA RECORD ACCESS -- //

    public function DropDown($iName,$iDefault=NULL) {
	$objRows = $this->Table->GetData('ID_Supplier='.$this->ID_Supplier,NULL,'DateAvail DESC');
	return $objRows->DropDown_this_data($iName,$iDefault,$this->ID);
    }
    public function DropDown_this_data($iName,$iDefault=NULL,$iCurrent=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    $out .= DropDown_row(NULL,'--NONE--',$iDefault=NULL,$iCurrent=NULL);
	    while ($this->NextRow()) {
		$id = $this->ID;
		if ($id == $iCurrent) {
		    $txt = '> Discontinue Supplier <';
		} else {
		    $txt = $this->Name;
		}
		$out .= DropDown_row($id,$txt,$iDefault=NULL,$iCurrent=NULL);
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No source catalogs found';
	}
	return $out;
    }
    public function AdminList() {
	global $vgOut;

	$objRecs = $this;

	$out = '';
	if ($objRecs->HasRows()) {
	    $out .= $vgOut->TableOpen('class=sortable');
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('ID');
	      $out .= $vgOut->TblCell('Name');
	      $out .= $vgOut->TblCell('Abbr');
	      $out .= $vgOut->TblCell('When Avail');
	      $out .= $vgOut->TblCell('Superceded');
	      $out .= $vgOut->TblCell('Status');
	    $out .= $vgOut->TblRowShut();
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$id = $objRecs->ID;
		//$wtID = SelfLink_WT(array('page'=>'catalog','id'=>$id),$id);
		$wtID = $objRecs->AdminLink();

		$isOdd = !$isOdd;
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$isActive = is_null($objRecs->ID_Supercede);
		if (!$isActive) {
		    $wtStyle .= ' color: #888888;';
		}
		$htStyle = 'style="'.$wtStyle.'"';

		$strDate = DataDate($objRecs->DateAvail);
		$strSuper = $this->SupercedeObj()->Abbr;
		$strStatus = $objRecs->isCloseOut?'closeout':'';

		$out .= $vgOut->TblRowOpen($htStyle);
		  $out .= $vgOut->TblCell($wtID);
		  $out .= $vgOut->TblCell($objRecs->Name);
		  $out .= $vgOut->TblCell($objRecs->Abbr);
		  $out .= $vgOut->TblCell($strDate);
		  $out .= $vgOut->TblCell($strSuper);
		  $out .= $vgOut->TblCell($strStatus);
		$out .= $vgOut->TblRowShut();
	    }
	    $out .= $vgOut->TableShut();
	} else {
	    $out = 'No catalogs found.';
	}
	return $out;
    }
    public function PageTitle() {
	global $wgRequest;
	global $vgPage;

	$isNew = is_null($this->ID);
	if ($isNew) {
	    $idSupp = $vgPage->Arg('supp');
	    $this->ID_Supplier = $idSupp;
	    $objSupp = $this->SuppObj();
	    $out = 'New catalog for '.$objSupp->NameStr();
	} else {
	    $out = $this->SuppObj()->Value('CatKey') . ': ' . $this->Value('Abbr') . ' catalog';
	}
	return $out;
    }
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	//$strAction = $vgPage->Arg('do');
	//$doAdd = ($strAction == 'add');
	$isNew = is_null($this->ID);
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');
	$idSupp = $vgPage->Arg('supp');
	if (!empty($idSupp)) {
	    $this->ID_Supplier = $idSupp;
	}

	if ($isNew) {
	    $strName = 'New Catalog';
	} else {
	    $strName = 'Catalog: '.$this->Abbr;
	}

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	//$objSection = new clsWikiSection($objPage,$strName);
	$objSection = new clsWikiSection_std_page($objPage,$strName);
	$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));

	$out = $objSection->Render();

	$wgOut->AddHTML($out); $out = '';

	$this->HandleEntry();

	if (!$isNew) {
	    //$objSection = new clsWikiSection($objPage,'Current Record',NULL,3);
	    //$objSection->ToggleAdd('edit');
	    $objSection = new clsWikiSection_std_page($objPage,'Current Record',3);
	    $objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));
	    $out = $objSection->Render();
	}
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		//$this->objForm->objFlds->AddDefault('ID_Supplier',$this->ID_Supplier);
		$this->objForm->NewVals(array('ID_Supplier'=>$this->ID_Supplier));
		$this->AdminSave();
	    }
	}

	if ($doEdit) {
	    assert('is_object($this->objForm)');

	    //$out .= $objSection->FormOpen();
	    $arLink = $vgPage->Args(array('page','id','supp'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';

	    $objForm = $this->objForm;
	    //$htName = $this->objCtrls->Ctrl('Name')->Render();
	    $htName = $objForm->Render('Name');
	    $htCode = $objForm->Render('Abbr');
	    $htDateAvail = $objForm->Render('DateAvail');
	    $htReplaced = $this->DropDown('ID_Supercede',$this->ID_Supercede);
	    $htStatus = $objForm->Render('isCloseOut').' closeout';
	} else {
	    $htName = $this->Name;
	    $htCode = $this->Abbr;
	    $htDateAvail = $this->DateAvail;
	    $objCtgRep = $this->SupercedeObj();
	    if ($objCtgRep->ID == $this->ID) {
		$htReplaced =  '<i>supplier not available</i>';
	    } else {
		$htReplaced = $objCtgRep->AdminLink($objCtgRep->Abbr);
	    }
	    $htStatus = $this->isCloseOut?'CLOSEOUT':'';
	}
	// non-editable
	$objSupp = $this->SuppObj();
	$htSupp = $objSupp->AdminLink($objSupp->Name);

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>Name</b>:</td><td>$htName</td></tr>";
	$out .= "\n<tr><td align=right><b>Code</b>:</td><td>$htCode</td></tr>";
	$out .= "\n<tr><td align=right><b>Supplier</b>:</td><td>$htSupp</td></tr>";
	$out .= "\n<tr><td align=right><b>Available</b>:</td><td>$htDateAvail</td></tr>";
	$out .= "\n<tr><td align=right><b>Replaced by</b>:</td><td>$htReplaced</td></tr>";
	$out .= "\n<tr><td align=right><b>Status</b>:</td><td>$htStatus</td></tr>";
	$out .= '</table>';

	if ($doEdit) {
	    if ($isNew) {
		$out .= '<input type=submit name="btnSave" value="Create">';
	    } else {
		$out .= '<input type=submit name="btnSave" value="Save">';
	    }
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out); $out = '';

	if (!$isNew) {
	    // title listing
	    $objSection = new clsWikiSection($objPage,'Titles',NULL,3);
	    $objSection->ToggleAdd('enter');
	    $out .= $objSection->Generate();
	    $out .= $this->AdminTitles();

	    // event listing
	    $objSection = new clsWikiSection($objPage,'Events',NULL,3);
	    $out .= $objSection->Generate();
	    $out .= $this->EventListing();

	    $vgOut->AddText($out);
	}
    }
    /*----
      HISTORY:
	2010-11-07 adapted from VbzAdminTitle
    */
    protected function BuildEditForm() {
	global $vgOut;
	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('Abbr'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsFieldNum('ID_Supplier'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('DateAvail'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Supercede'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldBool('isCloseOut'),	new clsCtrlHTML_CheckBox());

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
    }
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    protected function HandleEntry() {
	global $wgRequest;
	global $vgPage,$vgOut;

	$doEnter = $vgPage->Arg('enter');	// activates the first form
	$doParse = $wgRequest->GetBool('btnParse');	// first button - run STAGE 2
	$doChange = $wgRequest->GetBool('btnChange');	// second button - run STAGE 3

	$doSomething = $doEnter || $doParse || $doChange;

	if ($doSomething) {
	    $doShowForm = $doEnter || $doTitleLoad;
	    //$doBox = $doEnter && !$doTitleLoad;
	    $doBox = FALSE;	// is the box actually helpful?

/* 2011-09-30 departments are going away
	    $idDept = $wgRequest->GetIntOrNull('dept');	// get chosen department
*/
	    $out = NULL;

	    if ($doChange) {
		$out .= $this->HandleEntry_stage_3();	// process data before displaying status (so status shows the changes)
	    }

	    if ($doShowForm) {
		$arLink = $vgPage->Args(array('page','id','enter'));
		$urlForm = $vgPage->SelfURL($arLink,TRUE);
		$out .= '<form method=POST action="'.$urlForm.'">';
	    }
	    if ($doBox) {
		$out .= '<table align=right><tr><td>';
	    }

	    if ($doParse) {
		$out .= $this->HandleEntry_stage_2();	// allow user to select titles and groups to be added to them

	    }	// IF we're parsing the titles from the entry box

	    if ($doEnter) {
	    // STAGE 1: display controls for entering titles

		$txtTitles = $wgRequest->GetText('titles');
		$htTitles = htmlspecialchars($txtTitles);

		$out .= $this->TopicEditor();
		$out .= '<h3>Enter Titles</h3>';
		$out .= '<span title="supplier cat# only, no supplier code or option/size">List titles to be entered:</span>';
		$out .= '<textarea name=titles cols=3 rows=30>'.$htTitles.'</textarea>';
		$out .= '<br><input type=submit name="btnParse" value="Check Entered Titles...">';
		//$out .= $this->SuppObj()->Depts_DropDown(NULL,$idDept,'-- choose a department--');
	    }
	    if ($doShowForm) {
		//$out .= '<input type=submit name="btnCancel" value="Cancel">';
		$out .= '<input type=reset value="Reset">';
		$out .= '</form>';
	    }
	    if ($doBox) {
		$out .= '</td></tr></table>';
	    }
	    $vgOut->AddText($out); $out = '';
	}
    }
    /*----
      PURPOSE: HandleEntry() STAGE 2 -- allow user to select titles and groups to be added to them
      LATER: we will need to modify this to deal with suppliers where the topic (formerly department)
	affects the catalog #.
      HISTORY:
	2012-03-05 switched title query to use cat_titles directly
    */
    protected function HandleEntry_stage_2() {
	global $wgRequest;

	$idSupp = $this->Value('ID_Supplier');
	$out = NULL;

      // GRAB USER INPUT
	// get and parse the user-entered list of titles
	$strTitles = $wgRequest->getText('titles');
	$xts = new xtString($strTitles);
	$arTitles = $xts->ParseTextLines(array('def val'=>'<font color=red>please enter a descriptive title</font>'));

	$arGroups = $wgRequest->GetArray('group');	// get any selected groups

	$txtNotes = $wgRequest->GetText('notes');
	$htNotes = htmlspecialchars($txtNotes);
      // /USER INPUT

	// show action options
	$out .= '<table align=right><tr><td>';
	$out .= '<h3>Action Options</h3>';

/* 2011-09-30 departments are going away.
		// -- department chooser
		$out .= '<h4>Department</h4>';
		$out .= $this->SuppObj()->Depts_DropDown(NULL,$idDept,'-- choose a department--');
*/
	// -- list of CT groups
	$out .= '<h4>Groups</h4>';
	$objRows = $this->objDB->CtgGrps()->Active_forSupplier($idSupp,'Sort');
	$out .= $objRows->MultiSelect('group',TRUE,'size=20');

	$out .= '</td></tr></table>';

	$out .= '<h3>Title Actions</h3>';

	$tblTitles = $this->objDB->Titles();
	$tblCTitles = $this->objDB->CtgTitles();
	if (is_array($arTitles)) {
/* 2011-09-30 departments are going away
		    // 2011-02-06 allow restriction to selected dept
		    if (empty($idDept)) {
*/
	    $sqlBase = '(ID_Supp='.$idSupp.')';
	    $out .= 'Searching supplier for matches...';
/* 2011-09-30 departments are going away
		    } else {
			$sqlBase = '(ID_Dept='.$idDept.')';
			$objDept = $this->Engine()->Depts($idDept);
			$out .= 'Searching '.$objDept->AdminLink_name().' department for matches...';
		    }
*/

	    $isOdd = TRUE;
	    $out .= '<table><tr><th>ID</th><th>Cat #</th><th>Name</th><th>Topics</th><th>Groups</th></tr>';
	    foreach ($arTitles as $strCatKey=>$strNameEnt) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$htAttr = ' style="'.$ftStyle.'"';
		$isOdd = !$isOdd;

		// is this line already entered as a title record?
		$sqlFilt = $sqlBase.' AND (CatKey='.SQLValue($strCatKey).')';
		$objTitle = $tblTitles->GetData($sqlFilt);
		$isInCat = $objTitle->HasRows();
		// if it's a title record...
		if ($isInCat) {
		    $objTitle->NextRow();	// load the record found
		    $idTitle = $objTitle->ID;
		    $strName = $objTitle->Name;
		    $htName = htmlspecialchars($strName);

		    // ...does it have any catalog management entries yet (for this source)?
		    $sqlFilt = 'isActive AND (ID_Title='.$idTitle.') AND (ID_Source='.$this->ID.')';
		    $objCTitle = $tblCTitles->GetData($sqlFilt);
		    $isInCtg = $objCTitle->HasRows();

		    // show checkbox to add this title to the selected groups
		    $htPopup = 'add &ldquo;'.$htName.'&rdquo; to selected groups';
		    $htAdmin = '<input type=checkbox title="'.$htPopup.'" name=addToCtg['.$idTitle.']>add to selected';
		    if ($strNameEnt != $strName) {
			$htAdmin .= '<br><input type=checkbox name=updTitle['.$idTitle.']>update name';
		    }
		    if ($isInCtg) {
			$htGroups = '';
			$cntGrps = 0;
			while ($objCTitle->NextRow()) {
			    $objCGroup = $objCTitle->GroupObj();
			    if ($cntGrps) {
				$htGroups .= '<br>';
			    }
			    $cntGrps++;
			    $htGroups .= $objCGroup->AdminLink_friendly();
			}
			$htGroups .= '<br>';
		    } else {
			$htGroups = '';
		    }

		    $strCatKey = $objTitle->CatKey;
		    //$objDept = $objTitle->DeptObj();
		    //$htDept = $objDept->AdminLink($objDept->Name);
		    $htTopics = $objTitle->TopicList_ft();
		    $out .= "<tr$htAttr>"
		      .'<td>'.$objTitle->AdminLink().'</td>'
		      /* $htName is just filler here, but I wanted the array to be key-val
			in case we have a need for the value later on. */
		      .'<input type=hidden name=lstTitles['.$objTitle->ID.'] value="'.$htName.'" />'
		      .'<td>'.$strCatKey.'</td>'
		      .'<td>'.$htName.'</td>'
		      .'<td>'.$htTopics.'</td>'
		      .'<td rowspan=2>'.$htGroups.$htAdmin.'</td>'
		      .'</tr>';
		} // if ($isInCat)
		else {
		    $strName = $strNameEnt;
		    $htName = htmlspecialchars($strName);
		}

		// show the entered information:
		$out .= "<tr$htAttr>"
		  .'<td><input type=hidden name=lstEntry['.$strCatKey.'] value="'.$htName.'"></td>'
		  .'<td><i>'.$strCatKey.'</i></td>'
		  .'<td><i>'.$strNameEnt.'</i></td>';
		if (!$isInCat) {
		    $out .= '<td><input type=checkbox name=addToCat['.$strCatKey.'] checked>add to chosen</td>';
		}
		$out .= '</tr>';
		// if available, show the information found in the db:
	    }
	    // for visual clarity, put action button in same column as checkboxes
	    $out .= '<tr><td colspan=5 style="border-top: 1px solid black;"></td></tr>'
	      .'<tr>'
	      .'<td colspan=3>Notes: <input type=text name=notes size=25 value="'.$htNotes.'"></td>'
	      .'<td colspan=1 align=center title="add all titles to entered topics"><input type=checkbox checked name=chkAddTopics>add</input></td>'
	      .'<td colspan=1 align=center><input type=submit name=btnChange value="Make Changes"></td>'
	      .'</tr>';
	    $out .= '</table>';
	} // IF titles entered
	return $out;
    }
    /*----
      PURPOSE: STAGE 3 -- process data before displaying status (so status shows the changes)
      HISTORY:
	2012-03-05 entered title text was being discarded. I thought I had this working earlier,
	  but possibly the removal of Department-related code broke it.
    */
    protected function HandleEntry_stage_3() {
	global $wgRequest;

	// <user input>

	$doTopics = $wgRequest->GetBool('chkAddTopics');	// add the entered topics to all titles
	$arTitles = $wgRequest->GetArray('lstTitles');		// all matching titles found in catalog
	$arEntry = $wgRequest->GetArray('lstEntry');	// all titles parsed from user text ([catkey] = title)
	$arForCat = $wgRequest->GetArray('addToCat');	// list of titles to add to Supplier ("add to chosen")
	$arToRen = $wgRequest->GetArray('updTitle');	// list of titles to rename
	$arForCtg = $wgRequest->GetArray('addToCtg');	// list of titles to add to groups
	$arGroups = $wgRequest->GetArray('group');

	$txtNotes = $wgRequest->GetText('notes');

	// </user input>

	$idSupp = $this->Value('ID_Supplier');
	$objSupp = $this->SuppObj();

	$out = NULL;
	$out .= '<h3>Processing Changes</h3>';
	$arEv = array(
	  'where'	=> __METHOD__,
	  'descr'	=> 'processing catalog input',
	  'params'	=> '.ID_Source='.$this->ID,
	  'notes'	=> $txtNotes
	  );

	// CHANGE PART 1: see if we're adding topics

	if ($doTopics) {
	    $ctrlTopics = $this->TopicEditor_init();
	    $arTopics = $ctrlTopics->Data_toChange();
	    if (count($arTopics)) {
		$out .= 'Adding topics to titles --<ul>';
		$out .= '<li><b>topics</b>:';
		foreach ($arTopics as $idTopic) {
		    $obj = $this->Engine()->Topics($idTopic);
		    $arObjTopics[$idTopic] = $obj;
		    $out .= ' '.$obj->AdminLink();
		}
		$out .= '</li>';

		$out .= '<li><b>titles</b>:';
		$arEv = array(
		  'descr'	=> 'assigning topics to titles',
		  'where'	=> __METHOD__,
		  'code'	=> '+TT',
		  );
		$this->StartEvent($arEv);
		foreach ($arForCat as $idTitle => $txt) {
		    $out .= ' '.$idTitle;
		    foreach ($arObjTopics as $idTopic => $obj) {
			$obj->AddTitle($idTitle);
		    }
		}
		$arEv = array(
		  'descrfin'	=> '',
		  );
		$this->FinishEvent($arEv);
		$out .= '</li>';
		$out .= '</ul><br>';
	    }
	}

	// CHANGE PART 2: see if we're adding groups

	$hasGroups = is_array($arGroups);
	$hasGTitles = is_array($arForCtg);
/* 2011-09-30 departments are going away
	$hasDept = !is_null($idDept);
*/
	$hasSTitles = is_array($arForCat);	// has titles to add to Supplier
	$hasRenames = is_array($arToRen);

/* 2011-09-30 departments are going away
	$doEvent = ($hasGroups && $hasGTitles) || ($hasDept && $hasDTitles) || $hasRenames;
*/
	$doEvent = ($hasGroups && $hasGTitles) || $hasSTitles || $hasRenames;
	if ($doEvent) {
	    $this->StartEvent($arEv);
	}

	// adding titles to selected tgroups
	$strStat = '';
	if ($hasGroups) {
	    // ...if there are groups selected...

	    // build list for event description
	    $strStat .= 'CT Groups:';
	    foreach ($arGroups as $idGroup) {
		$strStat .= ' '.$idGroup;
	    }
	    $strStat .= '.';

	    if ($hasGTitles) {
		// ...if there are titles selected to be in those groups:
		$strStat .= ' Titles added to CT Group:';
		foreach ($arForCtg as $idTitle => $on) {
		    $strStat .= ' '.$idTitle;
		    $objTitle = $this->objDB->Titles()->GetItem($idTitle);
		    $out .= 'Adding title '.$objTitle->AdminLink($objTitle->CatNum()).' to:';
		    $out .= '<ul>';
		    foreach ($arGroups as $idGroup) {
			$objGroup = $this->objDB->CtgGrps()->GetItem($idGroup);
			$out .= '<li> (ID '.$idGroup.') '.$objGroup->AdminLink($objGroup->Name);
			$arRes = $objGroup->Add($idTitle,$this->ID);
		    }
		    $out .= '</ul>';
		}
		$strStat .= '.';
	    }
	}

/* 2011-09-30 departments are going away
	if ($hasDept) {
	    $objDept = $this->objDB->Depts()->GetItem($idDept);
	}
*/

	// adding titles to Supplier
	if ($hasSTitles) {
//		    if ($hasDept) {
		$strStat .= ' Titles added to Supplier'.$idSupp.' ('.$objSupp->Name.'):';
		$out .= 'Adding titles to &ldquo;'.$objSupp->Name.'&rdquo;.';
		foreach ($arForCat as $catnum => $on ) {
		    $arAdd[$catnum] = $arEntry[$catnum];
		    $strStat .= ' '.$catnum;
		}
		$objSupp->AddTitles($arAdd,$arEv);
/* 2011-09-30 departments are going away
	    } else {
		$out .= '<b>Check your input</b>: No department chosen for titles!';
	    }
*/
	    $strStat .= '.';
	} else {
/* 2011-09-30 departments are going away
	    if ($hasDept) {
*/
		$out .= 'No titles added.';
//		    }
	}

	// updating title names
	if ($hasRenames) {
	    $strStat .= ' Renaming Titles:';
	    foreach ($arToRen as $idTitle => $on ) {
		$strStat .= ' '.$idTitle;
		$objTitle = $this->objDB->Titles()->GetItem($idTitle);
		$strCatKey = $objTitle->CatKey;
		$strNameEnt = $arTitles[$strCatKey];
		$strNameOld = $objTitle->Name;
		$out .= "<br>Renaming ID $idTitle ($strCatKey): &ldquo;$strNameOld&rdquo; &rarr; &ldquo;$strNameEnt&rdquo;";
		$objTitle->Name($strNameEnt,$arEv);	// rename the title
	    }
	    $strStat .= '.';
	}

	if ($doEvent) {
	    $arEv = array(
	      'descrfin' => $strStat
	      );
	    $this->FinishEvent($arEv);
	}
	return $out;
    }
    /*----
      RETURNS: Edit controls to specify a list of topics
    */
    protected function TopicEditor() {
	$ctrlList = $this->TopicEditor_init();

	$out = $ctrlList->HandleInput(TRUE);
	$out .= "\n<br>";
	$out .= $ctrlList->RenderForm_Entry(TRUE);

	return $out;
    }
    protected function TopicEditor_init() {
	global $wgRequest;
	global $vgPage;

	if (!isset($this->objCtrlTopics)) {

	    $tblTitleTopics = $this->Engine()->TitleTopics();
	    $me = $this;
	    $arOpts = $this->Engine()->Topics()->TopicListing_base_array();
	    $arOpts['btnChk_Text']	= 'Use topics:';
	    $arOpts['txtConf_list'] = 'using';
	    $arOpts['btnChg_Text'] = NULL;

	    $arOpts['fHandleData_Change_Start'] = function($iText) use ($me) {
    /*
		  $arEv = array(
		    'descr'	=> 'Adding '.$iText,
		    'code'	=> 'topic++',
		    'where'	=> __METHOD__
		    );
		  $me->StartEvent($arEv);
    */
	      };
	    $arOpts['fHandleData_Change_Finish'] = function($iText) use ($me) {
    /*
		  $arEv = array(
		    'descrfin'	=> $iText
		    );
		  $me->FinishEvent($arEv);
    */
	      };
	    $arOpts['fHandleData_Change_Item'] = function($iVal) use ($me,$tblTitleTopics) {
    /*
		  $sqlTopic = $iVal;
		  $arIns = array(
		    'ID_Title'	=> SQLValue($me->KeyValue()),
		    'ID_Topic'	=> $sqlTopic
		    );
		  $db = $tblTitleTopics->Engine();
		  $db->ClearError();
		  $ok = $tblTitleTopics->Insert($arIns);
		  if (!$ok) {
		      $strErr = $db->getError();
		      $out = $sqlTopic.': '.$strErr.' (SQL:'.$tblTitleTopics->sqlExec.')';
		  } else {
		      $out = SQLValue($sqlTopic);
		  }
		  return $out;
    */
	      };

	    $ctrlList = new clsWidget_ShortList();
	    $ctrlList->Options($arOpts);

	    $this->objCtrlTopics = $ctrlList;
	} else {
	    $ctrlList = $this->objCtrlTopics;
	}

	return $ctrlList;
    }
    /*----
      ACTION: Displays the list of titles for this catalog, along with any
	appropriate administrative controls.
      HISTORY:
	2010-11-06 started writing
    */
    protected function AdminTitles() {
	$objRows = $this->objDB->CtgTitles()->List_forSource($this->ID);
	$out = $objRows->AdminList();
	return $out;
    }
}
