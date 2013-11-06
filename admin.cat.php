<?php
/*
  FILE: admin.cat.php -- catalog administration for VbzCart
  HISTORY:
    2010-10-15 Extracted catalog maintenance classes from SpecialVbzAdmin.php
  FUTURE: This should be renamed admin.ctg.php for consistency
*/

class VbzAdminCatalogs extends clsTable {
   //const TableName='ctg_sources';

   public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCatalog');
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
class VbzAdminCatalog extends clsDataSet {
    protected $objForm, $objCtrls, $objCtrlTopics;
    private $objSupp;

    /*====
      BOILERPLATE: event logging
      HISTORY:
	2010-10-30 was using old boilerplate event-handling methods; now using helper class boilerplate
	  Event methods removed from plural class; helper-class methods added to singular class
    */
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
    // --/BOILERPLATE--

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
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name() {
	$out = $this->AdminLink($this->Name);
	if (!$this->IsActive()) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    public function IsActive() {
	return is_null($this->ID_Supercede);
    }
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
	    $out = 'New catalog for '.$objSupp->Name;
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
/*====
  CLASS: catalog title groups
*/
class clsCtgGroups extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ctg_groups');
	  $this->KeyName('ID');
	  $this->ClassSng('clsCtgGroup');
	  $this->ActionKey('ctg.grp');
    }
    public function Active_forSupplier($iSupp,$iSort=NULL) {
	  $objRows = $this->GetData('isActive AND (ID_Supplier='.$iSupp.')',NULL,$iSort);
	  return $objRows;
    }
}
class clsCtgGroup extends clsDataSet {
    /*%%%%
      SECTION: boilerplate - admin
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-09-24 copied from VbzAdminOrderTrxact to clsShipment
	  ...and then to clsCMItem
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }

    /*%%%%
      SECTION: extensions to boilerplate
    */

    public function AdminLink_friendly(array $iarArgs=NULL) {
	$out = $this->AdminLink($this->Name,NULL,$iarArgs);
	if (!$this->isActive) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }
    public function AdminLink_name() {
	return $this->AdminLink_friendly();
    }
    /*====
      SECTION: event logging
      HISTORY:
	2011-02-09 adding boilerplate event logging using helper class
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    //=====
    public function SuppObj() {
	return $this->objDB->Suppliers()->GetItem($this->ID_Supplier);
    }
    /*----
      RETURNS: DataSet of CM Items for this CM Group
    */
    protected function DataItems() {
	//return $this->objDB->CMItems()->GetData('ID_Group='.$this->ID);
	return $this->objDB->CMItems()->Data_forGroup($this->ID);
    }
    /*----
      ACTION: Adds a record (title+source) using the given data.
    */
    public function Add($iTitle,$iSource) {
	$tblCT = $this->objDB->CtgTitles();
	return $tblCT->Add($iTitle,$this->ID,$iSource);
    }
    public function AdminPage() {
	global $wgRequest;
	global $vgPage,$vgOut;

	$isNew = is_null($this->ID);
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');
	$idSupp = $vgPage->Arg('supp');
	if (!empty($idSupp)) {
	    $this->ID_Supplier = $idSupp;
	}

	if ($isNew) {
	    $strName = 'New Group';
	} else {
	    $strName = 'Group: '.$this->Name;
	}

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,$strName);
	//$out = $objSection->HeaderHtml_Edit();
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();

//	$this->HandleEntry();

	if (!$isNew) {
	    $objSection = new clsWikiSection($objPage,'Current Record',NULL,3);
	    $objSection->ToggleAdd('edit');
	    $out .= $objSection->Generate();
	}
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$this->AdminSave();
		$this->AdminRedirect();
	    }
	}
	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id','supp'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $objForm = $this->objForm;

	    $htActv = $this->objForm;
	    $htName = $objForm->Render('Name');
	    $htDescr = $objForm->Render('Descr');
	    $htActv = $objForm->Render('isActive');
	    $htCode = $objForm->Render('Code');
	    $htSort = $objForm->Render('Sort');
	} else {
	    $htActv = NoYes($this->Value('isActive'));
	    $htName = htmlspecialchars($this->Value('Name'));
	    $htDescr = htmlspecialchars($this->Descr);
	    $htCode = htmlspecialchars($this->Code);
	    $htSort = htmlspecialchars($this->Sort);
	}
	// non-editable fields:
	$htID = $this->ID;
	$htSupp = $this->SuppObj()->AdminLink_name();

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>";
	$out .= "\n<tr><td align=right><b>Active</b>:</td><td>$htActv</td></tr>";
	$out .= "\n<tr><td align=right><b>Name</b>:</td><td>$htName</td></tr>";
	$out .= "\n<tr><td align=right><b>Supplier</b>:</td><td>$htSupp</td></tr>";
	$out .= "\n<tr><td align=right><b>Code</b>:</td><td>$htCode</td></tr>";
	$out .= "\n<tr><td align=right><b>Sort</b>:</td><td>$htSort</td></tr>";
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
	if (!$isNew) {
	    // group item listing
	    $objSection = new clsWikiSection($objPage,'Items',NULL,3);
	    $objSection->ToggleAdd('edit','edit items','edit.items');
	    $out .= $objSection->Generate();
	    $out .= $this->AdminItems();
	}

	$vgOut->AddText($out);
    }
    protected function AdminSave() {
	$out = $this->objForm->Save();
	return $out;
    }
    /*----
      HISTORY:
	2010-11-17 adapted from VbzAdminCatalog
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
//	    $objCtrls = new clsCtrls($objForm->Fields());

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsField('Code'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('Sort'),		new clsCtrlHTML());

	    $this->objForm = $objForm;
//	    $this->objCtrls = $objCtrls;
	}
    }
    /*----
      INPUT:
	iContext = array of fields (array[name]=val) that are always fixed (can be empty)
	  These are set when inserting new records.
      HISTORY:
	2011-02-06 added editing ability
    */
    public function AdminList($iEdit,array $iContext) {
	global $wgRequest;
	global $wgOut;

	$out = NULL;
	$txtEdit = NULL;
	if ($iEdit) {
	    $out .= '<form method=post>';
	}

	if ($wgRequest->GetBool('btnCtgChange')) {
	    $arUpd = SQLValue($wgRequest->GetArray('ctg-upd'));
	    $arIns = SQLValue($wgRequest->GetArray('ctg-ins'));
	    if (is_array($arUpd)) {
		foreach ($arUpd as $id => $row) {
		    $this->Table->Update($row,'ID='.$id);
		    $out .= '<br>'.$this->Table->sqlExec;
		}
	    }
	    if (is_array($arIns)) {
		foreach ($arIns as $idx => $row) {
		    $row = array_merge($row,$iContext);
		    $this->Table->Insert($row);
		    $out .= '<br>'.$this->Table->sqlExec;
		}
	    }
	    $iEdit = FALSE;	// saving changes -- turn off edit controls
	    // this has not yet been tested
	}
	if ($wgRequest->GetBool('btnCtgCheck')) {
	    $txtEdit = $wgRequest->GetText('txtCtgGrps');
	    $arLines = preg_split("/\n/",$txtEdit);

	    $xts = new xtString();
	    $htTbl = '<table border=1><tr><th>ID</th><th>A?</th><th>Code</th><th>Sort</th><th>Name</th><th>Description</th></tr>';
	    $arFlds = array('isActive','Code','Sort','Name','Descr');
	    $arDiff = NULL;
	    $cntRowChg = 0;
	    $idxIns = 0;
	    foreach ($arLines as $line) {
		$xts->Value = $line;
		$arRow = $xts->Xplode();

		list($id,$isActive,$code,$sort,$name,$descr) = $arRow;
		if (empty($id)) {
		    // new row - add to list of changes
		    $cntRowChg++;
		    //$htRow .= '<tr><td><b>NEW</b></td>';
		    $htRow = '<tr><td>NEW</td>';
		    $idxFld = 0;
		    $idxIns++;
		    foreach ($arFlds as $key) {
			$idxFld++;
			$vNew = $arRow[$idxFld];
			$htVal = htmlspecialchars($vNew);
			$ctVal = $htVal.'<input type=hidden name="ctg-ins['.$idxIns.']['.$key.']" value="'.$htVal.'">';
			$htRow .= '<td><b>'.$ctVal.'</b></td>';
		    }
		    //$sql = $this->Table->SQL_forInsert($arIns);
		    $htRow .= '</tr>';
		    //$htRow .= '<tr><td colspan=6>'.$sql.'</td></tr>';
		    $htTbl .= $htRow;
		} else {
		    $obj = $this->Table->GetItem($id);

		    $chg = NULL;
		    $htRow = '<tr><td>'.$obj->AdminLink().'</td>';
		    $idxFld = 0;
		    foreach ($arFlds as $key) {
			$htRow .= '<td>';
			$idxFld++;
			$vNew = $arRow[$idxFld];
			if ($obj->Value($key) != $vNew) {
			    $chg .= ' '.$key;
			    $htVal = htmlspecialchars($vNew);
			    $ctVal = $htVal.'<input type=hidden name="ctg-upd['.$id.']['.$key.']" value="'.$htVal.'">';
			    $htRow .= '<s>'.htmlspecialchars($obj->Value($key)).'</s><b>'.$ctVal.'</b>';
			} else {
			    $htRow .= $vNew;
			}
			$htRow .= '</td>';
		    }
		    $htRow .= '<td>diff:'.$chg.'</td></tr>';
		    if (!is_null($chg)) {
			$htTbl .= $htRow;
			$cntRowChg++;
		    }
		}
	    }
	    $htTbl .= '</table>';
	    if ($cntRowChg) {
		$out .= $htTbl;
		$out .= '<input type=submit name="btnCtgChange" value="Save Changes">';
	    } else {
		$out .= 'No changes were detected.<br>';
	    }
	}

	if ($this->HasRows()) {
	    if ($iEdit) {
		$cntRows = $this->RowCount();
		if ($cntRows < 10) {
		    $cntRows = 10;
		}
		$out .= 'each line = [ID - Active? - Code - Sort - Name - Description] as a <a href="http://htyp.org/prefix-separated_list">prefix-separated list</a>';
		$out .= '<textarea name=txtCtgGrps rows='.$cntRows.'>';
		if (is_null($txtEdit)) {
		    while ($this->NextRow()) {
			$txt = "\n\t".$this->KeyValue()
			  ."\t".$this->Value('isActive')
			  ."\t".$this->Value('Code')
			  ."\t".$this->Value('Sort')
			  ."\t".$this->Value('Name')
			  ."\t".$this->Value('Descr');
			$out .= htmlspecialchars($txt);
		    }
		} else {
		    $out .= htmlspecialchars($txtEdit);
		}
		$out .= '</textarea>';
		$out .= '<input type=submit name=btnCtgCheck value="Review Changes">';
		$out .= '</form>';
	    } else {
		$out .= "<table class=sortable><tr><th>ID</th><th>A?</th><th>#it</th><th>Code</th><th>Sort</th><th>Name</th><th>Description</th>";
		$isOdd = TRUE;
		while ($this->NextRow()) {
		    $isOdd = !$isOdd;
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isActive = $this->isActive;
		    if (!$isActive) {
			$wtStyle .= ' color: #888888;';
		    }

		    $rsItems = $this->DataItems();
		    $rcItems = $rsItems->RowCount();
		    $htItems = ($rcItems==0)?'<span style="color: red; font-weight: bold;">0</span>':$rcItems;
		    $wtID = $this->AdminLink();
		    $out .= "\n<tr style=\"$wtStyle\">".
			"\n<td>$wtID</td>".
			'<td>'.($isActive?'&radic;':'').'</td>'.
			'<td>'.$htItems.'</td>'.
			'<td>'.$this->Code.'</td>'.
			'<td>'.$this->Sort.'</td>'.
			'<td>'.$this->Name.'</td>'.
			'<td>'.$this->Descr.'</td>'.
			'</tr>';
		}
		$out .= '</table>';
	    }
	} else {
	    $out = 'No groups found.';
	}
	$wgOut->AddHTML($out);
	return NULL;
    }
    /*----
      ACTION: Render table of CM Items for this CM Group, with admin controls
      HISTORY:
	2012-03-06 removing "ID_Supplier" - CM Items don't have a supplier field.
	  Including this causes new records not to be saved.
	2012-03-08 we DO need ID_Supplier so AdminRows() can show the drop-down list
	  of possible groups to copy from. AdminRows() is responsible for stripping it
	  out when passing it on to the row-editor form.
    */
    protected function AdminItems() {
	$rsRows = $this->DataItems();
	return $rsRows->AdminRows(array(
	  'ID_Group'	=> $this->KeyValue(),
	  'ID_Supplier'	=> $this->Value('ID_Supplier')
	  ),$this->Log());
    }
    /*-----
      ACTION: Display dataset in a format which allows multiple independent selections
      INPUT:
	iName = name for the control. If using checkboxes, each checkbox is named iName[ID].
	iAsBox:
	  FALSE: show one checkbox for each record.
	  TRUE: show all in a single multi-select option box
      RETURNS: HTML (NULL if no rows to display)
    */
    public function MultiSelect($iName,$iAsBox,$iAttr=NULL) {
	if ($this->HasRows()) {
	    $out = '';

	    $doBox = $iAsBox;
	    $htAttr = is_null($iAttr)?NULL:(' '.$iAttr);

	    if ($doBox) {
		$out .= "\n<SELECT NAME=\"$iName\"$htAttr MULTIPLE>";
		while ($this->NextRow()) {
		    $id = $this->ID;
		    $htShow = htmlspecialchars($this->Name);
		    $out .= "\n<OPTION value=$id>$htShow</option>";
		}
		$out .= "\n</SELECT>";
	    } else {
		while ($this->NextRow()) {
		    $out .= '<br><input type=checkbox name="'.$iName.'['.$this->ID.']">'.$this->Name;
		}
	    }
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*----
      HISTORY:
	2011-02-08 adapted from VbzAdminDept::DropDown()
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iNone=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key

	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		$htShow = $this->Value('Name');
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
     }
}
/*====
  CLASS: catalog titles
*/
class clsCtgTitles extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ctg_titles');
	  $this->KeyName('ID');
	  $this->ClassSng('clsCtgTitle');
    }
    public function List_forSource($iSrc) {
	$objRows = $this->GetData('ID_Source='.$iSrc);
	return $objRows;
    }
    public function List_forGroup($iGrp) {
	$objRows = $this->GetData('ID_Group='.$iGrp.')');
	return $objRows;
    }
    public function Add($iTitle,$iGroup,$iSource) {
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
}
class clsCtgTitle extends clsDataSet {
    /*----
      HISTORY:
	2010-11-06 Added boilerplate function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminList() {
	global $vgOut;

	if ($this->HasRows()) {
	    $out = $vgOut->TableOpen();
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('ID');
	      $out .= $vgOut->TblCell('A?');
	      $out .= $vgOut->TblCell('Title');
	      $out .= $vgOut->TblCell('Source');
	      $out .= $vgOut->TblCell('Group');
	      $out .= $vgOut->TblCell('Gone');
	      $out .= $vgOut->TblCell('SC#');
	      $out .= $vgOut->TblCell('Notes');
	    $out .= $vgOut->TblRowShut();

	    $isOdd = TRUE;

	    while ($this->NextRow()) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$htAttr = 'style="'.$ftStyle.'"';
		$isOdd = !$isOdd;

		$objTitle = $this->TitleObj();
		$objSource = $this->SourceObj();
		$objGroup = $this->GroupObj();
		$txtActive = $this->isActive?'&radic;':'-';
		$txtTitle = $objTitle->CatNum().' '.$objTitle->Name;
		$txtSource = $objSource->Abbr;
		$txtGroup = $objGroup->Name;
		$dtWhenDisc = $this->WhenDiscont;
		if (is_null($dtWhenDisc)) {
		    $txtWhenDisc = '-';
		} else {
		    $xtd = new xtTime($dtWhenDisc);
		    $txtWhenDisc = $xtd->FormatSortable();
		}
//		$txtWhenDisc = empty($dtWhenDisc)?'-':('['.$dtWhenDisc.']');

		$out .= $vgOut->TblRowOpen($htAttr);
		$out .= $vgOut->TblCell($this->AdminLink());
		$out .= $vgOut->TblCell($txtActive);
		$out .= $vgOut->TblCell($objTitle->AdminLink($txtTitle));
		$out .= $vgOut->TblCell($objSource->AdminLink($txtSource));
		$out .= $vgOut->TblCell($objGroup->AdminLink($txtGroup));
		$out .= $vgOut->TblCell($txtWhenDisc);
		$out .= $vgOut->TblCell($this->Supp_CatNum);
		$out .= $vgOut->TblCell($this->Notes);
		$out .= $vgOut->TblRowShut();
	    }
	    $out .= $vgOut->TableShut();
	} else {
	    $out = 'No titles found.';
	}
	return $out;
    }
    public function TitleObj() {
	$id = $this->ID_Title;
	$obj = $this->objDB->Titles()->GetItem($id);
	return $obj;
    }
    public function SourceObj() {
	$id = $this->ID_Source;
	$obj = $this->objDB->CtgSrcs()->GetItem($id);
	return $obj;
    }
    public function GroupObj() {
	$id = $this->ID_Group;
	$obj = $this->objDB->CtgGrps()->GetItem($id);
	return $obj;
    }
}
/*====
  CLASS: Catalog Management Items
*/
class clsCMItems extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ctg_items');
	  $this->KeyName('ID');
	  $this->ActionKey('cmi');
	  $this->ClassSng('clsCMItem');
    }
    public function Data_forGroup($idGroup) {
	return $this->GetData('ID_Group='.$idGroup,NULL,'Sort');
    }
}
class clsCMItem extends clsDataSet {
    protected $objForm;

    /*%%%%
      SECTION: BOILERPLATE admin HTML
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-09-24 copied from VbzAdminOrderTrxact to clsShipment
	  ...and then to clsCMItem
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    // /boilerplate

    /*%%%%
      SECTION: EXTENSIONS to boilerplate admin
    */
    public function AdminLink_descr() {
	$out = $this->AdminLink($this->Descr);
	if (!$this->IsActive()) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }

    /*%%%%
      SECTION: data access
    */

    public function GroupObj() {
	$id = $this->Value('ID_Group');
	$rc = $this->Engine()->CtgGrps($id);
	return $rc;
    }
    public function ItTypObj() {
	$id = $this->Value('ID_ItTyp');
	$rc = $this->Engine()->ItTyps($id);
	return $rc;
    }
    public function ItOptObj() {
	$id = $this->Value('ID_ItOpt');
	$rc = $this->Engine()->ItOpts($id);
	return $rc;
    }
    public function ShCostObj() {
	$id = $this->Value('ID_ShipCost');
	$rc = $this->Engine()->ShipCosts($id);
	return $rc;
    }

    /*%%%%
      SECTION: ADMIN code
    */
/* *sigh* this actually isn't needed... the Group page lets you edit by row.
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	//$strAction = $vgPage->Arg('do');
	//$doAdd = ($strAction == 'add');
	$isNew = is_null($this->ID);
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');

	if ($isNew) {
	    $strName = 'New Group Item';
	} else {
	    $strName = 'Group Item: '.$this->Value('Descr');
	}

	$objPage = new clsWikiFormatter($vgPage);
	$vgPage->UseHTML();
	$out = NULL;

	$objSection = new clsWikiSection_std_page($objPage,'Group Item record');
	$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));
	$out .= $objSection->Render();

	if ($doEdit || $doSave) {
	    if ($isNew) {
		$idGrp = $vgPage->Arg('grp');
		$arNew = array('ID_Group' => $idGrp);
	    } else {
		$arNew = array();	// new record data not needed
	    }

	    $this->BuildEditForm($arNew);
	    if ($doSave) {
		$this->AdminSave();
		$this->AdminRedirect();
	    }
	}
	if ($doEdit) {
	    $out .= '<form>';
	    $objForm = $this->objForm;

	    $htAct = $objForm->Render('isActive');
	    $htDesc = $objForm->Render('Descr');
	    $htSort = $objForm->Render('Sort');
	    $htPrcBuy = $objForm->Render('PriceBuy');
	    $htPrcSell = $objForm->Render('PriceSell');
	    $htPrcList = $objForm->Render('PriceList');
	} else {
	    $htAct = NoYes($this->Value('isActive'));
	    $htDesc = htmlspecialchars($this->Value('Descr'));
	    $htSort = htmlspecialchars($this->Value('Sort'));
	    $htPrcBuy = DataCurr($this->Value('PriceBuy'),'');
	    $htPrcSell = DataCurr($this->Value('PriceSell'),'');
	    $htPrcList = DataCurr($this->Value('PriceList'),'');
	}
	// non-editable fields:
	$htID = $this->ID;
	$rcGrp = $this->GroupObj();
	$rcItt = $this->ItTypObj();
	$rcOpt = $this->ItOptObj();
	$rcShp = $this->ShCostObj();
	$htGrp = $rcGrp->AdminLink_name();
	//$htItt = $rcItt->AdminLink_name();
	$htItt = $rcItt->Name();
	//$htOpt = $rcOpt->AdminLink_name();
	$htOpt = $rcOpt->DescrFull();

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>";
	$out .= "\n<tr><td align=right><b>Active</b>:</td><td>$htAct</td></tr>";
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>$htDesc</td></tr>";
	$out .= "\n<tr><td align=right><b>Sorting</b>:</td><td>$htSort</td></tr>";
	$out .= "\n<tr><td align=right><b>Group</b>:</td><td>$htGrp</td></tr>";
	$out .= "\n<tr><td align=right><b>Item Type</b>:</td><td>$htItt</td></tr>";
	$out .= "\n<tr><td align=right><b>Item Option</b>:</td><td>$htOpt</td></tr>";
	$out .= "\n<tr><td align=right><b>Purchase Price</b>: $</td><td>$htPrcBuy</td></tr>";
	$out .= "\n<tr><td align=right><b>Selling Price</b>: $</td><td>$htPrcSell</td></tr>";
	$out .= "\n<tr><td align=right><b>List Price</b>:$</td><td>$htPrcList</td></tr>";
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
	$wgOut->AddHTML($out);
    }
*/
    /*----
      INPUT:
	iContext: array of values that apply to all rows (e.g. for creating new records)
	  iContext[key] = value
      FUTURE: This function should also display its own header, so we can have all the action-link naming in one place
      2012-03-08 we DO need ID_Supplier in the Context, in order to show the list of groups to copy from --
	but we have to strip it out before passing it on to the row editor
    */
    public function AdminRows(array $iContext, clsLogger_DataSet $iLogger=NULL) {
	global $wgRequest;
	global $vgPage,$vgOut;

	// get URL input
	$doEdit = ($vgPage->Arg('edit.items'));
	$doSave = $wgRequest->getBool('btnSaveItems');
	$doCopy = $wgRequest->getBool('btnCopyFrom');

	// handle edit form input:
	if ($doSave) {
	    $arUpdate = $wgRequest->getArray('update');
	    $arActive = $wgRequest->getArray('isActive');

	    if (count($arActive > 0)) {
		// add any reactivated rows to the update list
		foreach ($arActive as $id => $null) {
		    $arUpdate[$id] = TRUE;
		}
	    }
	}

	$out = NULL;
	$didEdit = FALSE;
	$sqlMake = $this->sqlMake;

	// handle copying request
	if ($doCopy) {
	    $idGrp = $wgRequest->GetIntOrNull('group_model');
	    $rsItems = $this->Table->Data_forGroup($idGrp);
	    if ($rsItems->HasRows()) {
		$objGrp = $this->Engine()->CtgGrps($idGrp);
		$out .= 'Copying from group ['.$objGrp->AdminLink_friendly().']: ';
		if (!is_null($iLogger)) {
		    $arEv = array(
		      'descr'	=> 'Copying rows from group ID='.$idGrp,
		      'code'	=> 'CPY',
		      'params'	=> '\group='.$idGrp,
		      'where'	=> __METHOD__
		      );
		    $iLogger->StartEvent($arEv);
		}
		$rc=0; $rtxt='';
		$strKeyName = $rsItems->Table->KeyName();
		while ($rsItems->NextRow()) {
		    $rc++; $rtxt.='['.$rsItems->KeyValue();
		    $arRow = $rsItems->Values();
		    // unset row key
		    unset($arRow[$strKeyName]);
		    // overwrite any default values from context
		    foreach ($iContext as $key => $val) {
			if (array_key_exists($key,$arRow)) {
			    $arRow[$key] = $val;
			}
		    }
		    // build insert array by iterating through row's fields
		    foreach ($arRow as $key => $val) {
			$arIns[$key] = SQLValue($val);
		    }
		    // do the update
		    $this->Table->Insert($arIns);
		    $out .= '<br><b>SQL</b>: '.$this->Table->sqlExec;
		    $idNew = $this->Table->LastID();
		    $rtxt .= '->'.$idNew.']';
		}
		$txtDescr = $rc.' item'.Pluralize($rc).' copied:'.$rtxt;
		$out .= $txtDescr;
		if (!is_null($iLogger)) {
		    $arEv = array(
		      'descrfin'	=> $txtDescr
		      );
		    $iLogger->FinishEvent($arEv);
		}
	    }
	    $didEdit = TRUE;
	}

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $oContext = $iContext;
	    unset($oContext['ID_Supplier']);
	    $this->BuildEditForm($iContext);
	    if ($doSave) {
		$sqlLoad = $this->sqlCreate;
		$ftSaveStatus = $this->AdminSave();
		//$this->Query($sqlLoad);
		$out .= $ftSaveStatus;
		$didEdit = TRUE;
	    }
	}
	if ($didEdit) {
	    $this->sqlMake = $sqlMake;
	    $this->Reload();
	    $this->StartRows();	// make sure no rows got skipped
	}

	$isEdit = FALSE;	// set if there is anything to save or revert

	$arLink = $vgPage->Args(array('page','id'));
	$urlForm = $vgPage->SelfURL($arLink,TRUE);
	$out .= '<form method=POST action="'.$urlForm.'">';

	// display rows
	$isOdd = TRUE;
	$out .= $vgOut->TableOpen();
	$htAfter = NULL;
	if ($this->HasRows()) {
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('ID');
	      $out .= $vgOut->TblCell('<span title="active?">A?</a>');
	      $out .= $vgOut->TblCell('<span title="sorting order">S</span>');
	      $out .= $vgOut->TblCell('Item Type');
	      $out .= $vgOut->TblCell('Item Option');
	      $out .= $vgOut->TblCell('Description');
	      $out .= $vgOut->TblCell('$ Buy');
	      $out .= $vgOut->TblCell('$ Sell');
	      $out .= $vgOut->TblCell('$ List');
	      $out .= $vgOut->TblCell('S/H charges');
	    $out .= $vgOut->TblRowShut();


	    while ($this->NextRow()) {
		$isOdd = !$isOdd;
		$out .= $this->AdminRow($isOdd,$doEdit);

	    }

	    if ($doEdit) {
	    // form buttons
		$isEdit = TRUE;
	    }
	    
	} else {
	    $out .= '<tr><td colspan=10>No items found.</td></tr>';
	    if ($doEdit && array_key_exists('ID_Supplier',$iContext)) {
		$htAfter .= '<input type=submit name="btnCopyFrom" value="Copy items from:">';
		$idSupp = $iContext['ID_Supplier'];

		$objRows = $this->objDB->CtgGrps()->Active_forSupplier($idSupp,'Sort');
		$htAfter .= $objRows->DropDown('group_model');
	    }
	}
	$objNew = $this->Table->SpawnItem();
	$objNew->ID_Group = $this->ID_Group;
	$objNew->objForm = $this->objForm;
	$out .= $objNew->AdminRow($isOdd,$doEdit);

	$out .= $vgOut->TableShut();

	// close editing form
	$out .= '<input type=submit name="btnSaveItems" value="Save">';
	$out .= '<input type=reset value="Revert">';
	$out .= $htAfter;	// stuff to go after main form
	$out .= "\n</form>";
	return $out;
    }
    protected function AdminRow($iOdd,$iEdit) {
	global $vgOut;

	$doNew = $this->IsNew();
	$doEdit = $iEdit;
	$isOdd = $iOdd;

	if ($doNew && !$doEdit) { return; }

	$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
	$htAttr = 'style="'.$ftStyle.'"';

	$out = NULL;

	if ($doEdit) {
	    $objForm = $this->objForm;

	    $out .= $objForm->RowPrefix();

	    $ftActive = $objForm->Render('isActive');
	    $ftSort = $objForm->Render('Sort');

	    $keyForm = $doNew?'new':$this->KeyValue();

	    $htNameSfx = '['.$keyForm.']';
	    $idItTyp = $this->ValueNz('ID_ItTyp');
	    $idItOpt = $this->ValueNz('ID_ItOpt'); 
	    $idShip =  $this->ValueNz('ID_ShipCost');
	    $ftItTyp = $this->Engine()->ItTyps()->DropDown('ID_ItTyp'.$htNameSfx,$idItTyp,'--choose a type--');
	    $ftItOpt = $this->Engine()->ItOpts()->DropDown('ID_ItOpt'.$htNameSfx,$idItOpt,'--choose--');
	    $ftShip = $this->Engine()->ShipCosts()->DropDown('ID_ShipCost'.$htNameSfx,$idShip,'--choose--');

	    $ftDescr = $objForm->Render('Descr');
	    $ftPriceBuy = $objForm->Render('PriceBuy');
	    $ftPriceSell = $objForm->Render('PriceSell');
	    $ftPriceList = $objForm->Render('PriceList');
	} else {
	    $objItTyp = $this->objDB->ItTyps()->GetItem($this->ID_ItTyp);
	    $objItOpt = $this->objDB->ItOpts()->GetItem($this->ID_ItOpt);
	    $objShip = $this->objDB->ShipCosts()->GetItem($this->ID_ShipCost);
	    $ftActive = $this->Value('isActive')?'&radic;':'-';
	    $ftSort = $this->Value('Sort');

	    $ftItTyp = $objItTyp->Name();
	    $ftItOpt = $objItOpt->CatKey.' - '.$objItOpt->Descr;
	    $ftShip = '(i'.$objShip->PerItem.'+p'.$objShip->PerPkg.') '.$objShip->Descr;

	    $ftDescr = $this->Descr;
	    $ftPriceBuy = DataCurr($this->PriceBuy);
	    $ftPriceSell = DataCurr($this->PriceSell);
	    $ftPriceList = DataCurr($this->PriceList);
	}

	$out .= $vgOut->TblRowOpen($htAttr);
	$out .= $vgOut->TblCell($this->KeyValue());
	$out .= $vgOut->TblCell($ftActive);
	$out .= $vgOut->TblCell($ftSort);
	$out .= $vgOut->TblCell($ftItTyp);
	$out .= $vgOut->TblCell($ftItOpt);
	$out .= $vgOut->TblCell($ftDescr);
	$out .= $vgOut->TblCell($ftPriceBuy,'align=right');
	$out .= $vgOut->TblCell($ftPriceSell,'align=right');
	$out .= $vgOut->TblCell($ftPriceList,'align=right');
	$out .= $vgOut->TblCell($ftShip);

	return $out;
    }
    /*----
      HISTORY:
	2010-11-18 adapted from VbzAdminItem
    */
    private function BuildEditForm(array $iNewVals) {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet_indexed($this,$vgOut);
	    $arNewVals = $iNewVals;
	    $arNewVals['ID_ItTyp'] = NULL;	// required field for new records
	    $objForm->NewVals($arNewVals);

	    $objForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());
	    //$objForm->AddField(new clsFieldNum('ID_Group'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_ItTyp'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_ItOpt'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Sort'),		new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsFieldNum('PriceBuy'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('PriceSell'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('PriceList'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ID_ShipCost'),	new clsCtrlHTML());

	    $this->objForm = $objForm;
	}
    }
    protected function AdminSave() {
	$out = $this->objForm->Save();
	return $out;
    }

}