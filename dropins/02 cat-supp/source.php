<?php
/*
  FILE: dropins/cat-supp/source.php -- catalog sources for VbzCart Supplier Catalog dropin
  HISTORY:
    2010-10-15 Extracted catalog maintenance classes from SpecialVbzAdmin.php
    2017-
*/

// PURPOSE: common class for table and query
abstract class vctaSCSources_base extends vcAdminTable {
//   use ftLinkableTable;
    use ftExecutableTwig;	// dispatch events
   
   // ++ SETUP ++ //
   
    // CEMENT
    protected function TableName() {
	return 'ctg_sources';
    }
    // CEMENT
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_SOURCE;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_SOURCE;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sTitle = 'SCats';
	$htTitle = 'Supplier Catalogs';
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ ADMIN UI ++ //
    
    abstract protected function AdminPage();
}

class vctaSCSources extends vctaSCSources_base {

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
/*    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    } */

    // -- DROP-IN API -- //
    // ++ TABLES ++ //
    
    protected function WithSuppliersQuery() {
	return $this->GetConnection()->MakeTableWrapper(KS_QUERY_CLASS_SUPPCAT_SOURCES_WITH_SUPPLIERS);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    /*----
      NOTE: We could also filter for DateActive, but so far that has never been needed as a way to indicate that a catalog is not yet available. If it's in hand, then it's either valid or expired.
      HISTORY:
	2017-06-20 Added filtering for DateExpires
    */
    public function ActiveRecords() {
	return $this->SelectRecords('(ID_Supercede IS NULL) AND (DateExpires > NOW())');
    }
    
    // -- RECORDS -- //
    // ++ ADMIN UI PIECES ++ //

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
    
    // -- ADMIN UI PIECES -- //
    // ++ ADMIN UI PAGES ++ //
    
    protected function AdminPage() {
	return $this->WithSuppliersQuery()->AdminPage();
    }

    // -- ADMIN UI PAGES -- //

}
class vcraSCSource extends vcAdminRecordset implements fiEventAware {
    use ftLinkableRecord;
    use ftLoggableRecord;
    use ftExecutableTwig;	// dispatch events

    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name() {
	$sName = $this->SelfLink($this->NameString());
	$sBase = $this->IsCloseOut()?("-($sName)"):$sName;
	$out = $this->IsActive()?$sBase:("<s>$sBase</s>");
	return $out;
    }

    // -- TRAIT HELPERS -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	if ($this->IsNew()) {
	    $htTitle = 'New Supplier Catalog';
	    $sTitle = 'new SC';
	} else {
	    $sAbbr = $this->Abbreviation();
	    $id = $this->GetKeyValue();
	    $htTitle = "Supplier Catalog $id: '$sAbbr'";
	    $sTitle = "SC $id: ".$sAbbr;
	}
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ INTERNAL VALUES ++ //

    private $bDoEdit;
    protected function SetDoEdit($bOn) {
	$this->bDoEdit = $bOn;
    }
    protected function GetDoEdit() {
	return $this->bDoEdit;
    }
    private $bDoEnter;
    protected function SetDoEnter($bOn) {
	$this->bDoEnter = $bOn;
    }
    protected function GetDoEnter() {
	return $this->bDoEnter;
    }
    
    // -- INTERNAL VALUES -- //
    // ++ FIELD VALUES ++ //

    protected function NameString() {
	return $this->GetFieldValue('Name');
    }
    // PUBLIC so SCTitle listing can use it
    public function Abbreviation() {
	return $this->GetFieldValue('Abbr');
    }
    protected function DateAvailable() {
	return $this->GetFieldValue('DateActive');
    }
    /*----
      NOTE: SupplierID() was "PUBLIC so Title Entry form can use it", but not sure whether that's read or write
    */
    protected function SetSupplierID($id) {
	return $this->SetFieldValue('ID_Supplier',$id);
    }
    /*----
      NOTE: SupplierID() was "PUBLIC so Title Entry form can use it", but not sure whether that's read or write
    */
    protected function GetSupplierID() {
	return $this->GetFieldValue('ID_Supplier');
    }
    protected function SupercedeID() {
	return $this->GetFieldValue('ID_Supercede');
    }
    protected function IsCloseout() {
	return $this->GetFieldValue('isCloseOut');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    // CALLBACK for dropdowns
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    // CALLBACK for dropdowns
    public function ListItem_Text() {
	$sSummary = $this->Abbreviation().' - '.$this->NameString();
	if ($this->IsActive()) {
	    return $sSummary;
	} else {
	    return "($sSummary)";
	}
    }
    protected function HasSupplier() {
	if ($this->IsNew()) {
	    return FALSE;
	} else {
	    return !is_null($this->GetSupplierID());
	}
    }
    protected function IsSuperceded() {
	return !is_null($this->SupercedeID());
    }
    protected function IsActive() {
	return is_null($this->SupercedeID());
    }

    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //
    
    protected function SupplierTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_CATALOG_SUPPLIERS,$id);
    }
    protected function SCTitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_TITLES,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      USED BY: Title Entry form, and some internal stuff
      PUBLIC so TEf can use it
    */
    private $rcSupp;
    public function SupplierRecord() {
	$idSupp = $this->GetSupplierID();
	$doGet = TRUE;
	if (!empty($this->rcSupp)) {
	    if ($this->rcSupp->GetKeyValue() == $idSupp) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->rcSupp = $this->SupplierTable($idSupp);
	}
	return $this->rcSupp;
    }
    protected function SupplierLink() {
	if ($this->HasSupplier()) {
	    return $this->SupplierRecord()->SelfLink_name();
	} else {
	    return '<b>SUPPLIER NOT SET</b>';
	}
    }
    public function SupercedeRecord() {
	if ($this->IsSuperceded()) {
	    $rc = $this->GetTableWrapper()->GetRecord_forKey($this->SupercedeID());
	} else {
	    $rc = NULL;
	}
	return $rc;
    }
    protected function GetRecords_forSupplier() {
	$sqlFilt = 'ID_Supplier='.$this->GetSupplierID();
	$sqlSort = '(ID_Supercede IS NOT NULL), Abbr DESC, Name DESC';
	$rs = $this->GetTableWrapper()->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ DB WRITE ++ //
    
    public function MakeTitle($idLCTitle,$idGroup) {
	$t = $this->SCTitleTable();
	return $t->Add($idLCTitle,$idGroup,$this->GetKeyValue());
    }
    
    // -- DB WRITE -- //
    // ++ ADMIN UI PIECES ++ //

    /* 2017-06-20 I don't think this code is needed anymore.
    public function DropDown($iName,$iDefault=NULL) {
	throw new exception('Does anything still use this? It won\'t work as written.');
	$objRows = $this->Table->GetData('ID_Supplier='.$this->ID_Supplier,NULL,'DateActive DESC');
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
    } */
    /* 2017-06-18 It seems very unlikely that this is needed anymore.
    public function PageTitle() {
	throw new exception(__METHOD__.' needs a little updating.');
	$isNew = is_null($this->ID);
	if ($isNew) {
	    $idSupp = $vgPage->Arg('supp');
	    $this->ID_Supplier = $idSupp;
	    $objSupp = $this->SupplierRecord();
	    $out = 'New catalog for '.$objSupp->NameStr();
	} else {
	    $out = $this->SupplierRecord()->Value('CatKey') . ': ' . $this->Value('Abbr') . ' catalog';
	}
	return $out;
    } */
    
    // -- ADMIN UI PIECES -- //
    // ++ ADMIN UI PAGES ++ //
    
    //++multi++//

    // CALLED BY SCM Supplier objects
    public function AdminRows() {
	$out = '';
	if ($this->HasRows()) {
	    $out .= <<<__END__

<table class=listing>
  <tr>
    <th>ID</th>
    <th>Supplier</th>
    <th>Name</th>
    <th>Abbr</th>
    <th>When Avail</th>
    <th>Superceded</th>
    <th>Status</th>
  </tr>
__END__;
	    while ($this->NextRow()) {
		$out .= $this->AdminRow();
	    }
	    $out .= "\n</table>";
	} else {
	    $out = '<div class=content>No catalogs found.</div>';
	}
	return $out;
    }
    // CALLED BY $this->AdminRows()
    protected function AdminRow() {
	static $isOdd=FALSE;
	
	$id = $this->GetKeyValue();
	$ftID = $this->SelfLink();
	$ftSupp = $this->SupplierLink();

	$isOdd = !$isOdd;
	$cssClass = $isOdd?'odd':'even';
	$isActive = is_null($this->SupercedeID());
	if (!$isActive) {
	    $cssClass .= 'inact';
	}

	$strDate = fcDate::NzDate($this->DateAvailable());
	if ($this->IsSuperceded()) {
	    if ($this->SupercedeID() == $this->GetKeyValue()) {
		$htSuper = '(self)';
	    } else {
		$rcSuper = $this->SupercedeRecord();
//		$sSuper = $rcSuper->Abbreviation();
		$htSuper = $rcSuper->SelfLink_name();
	    }
	} else {
	    $htSuper = '(current)';
	}
	$strStatus = $this->isCloseOut()?'closeout':'';
	
	$sName = $this->NameString();
	$sAbbr = $this->Abbreviation();
	
	$out = <<<__END__

  <tr class=$cssClass>
    <td>$ftID</td>
    <td>$ftSupp</td>
    <td>$sName</td>
    <td>$sAbbr</td>
    <td>$strDate</td>
    <td>$htSuper</td>
    <td>$strStatus</td>
  </tr>
__END__;

	return $out;
    }
    
    //--multi--//
    //++single++//
    
    public function AdminPage() {
	$oFormIn = fcHTTP::Request();
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$isNew = $this->IsNew();
	$frm = $this->PageForm();
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}

	//$doEdit = $oPage->PathArg('edit') || $isNew;
	$doSave = $oFormIn->GetBool('btnSave');
	$idSupp = $oPathIn->GetInt('supp');

	if (!is_null($idSupp) && !$this->HasSupplier()) {
	    $this->SetSupplierID($idSupp);
//	    if (!$isNew) {
//		$frm->LoadRecord();	// copy to Field object
//	    }
	    $frm->FieldObject('ID_Supplier')->SetDefault($idSupp);
	    $frm->FieldObject('ID_Supplier')->SetValue($idSupp);
	}
	
	if ($doSave) {
	    $id = $frm->Save();
	    $this->SetKeyValue($id);	// store new ID so we can redirect to it
	    $this->SelfRedirect(array(),$frm->MessagesString());
	}
	
	// load the Values() into the Field controls
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit this source'));
	    $this->SetDoEdit($ol->GetIsSelected());

	$doEdit = $this->GetDoEdit();
	
	// Set up rendering objects
	
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	
	$out = NULL;
		
	if ($doEdit) {
	    $out .= "\n<form id=AdminPage method=post>";
	} else {
	    $idSuper = $this->SupercedeID();
	    $id = $this->GetKeyValue();
	    if ($idSuper == $id) {
		$arCtrls['ID_Supercede'] = '<i>catalog inactive</i>';
	 //   } else {
	//	$rcSuper = $this->SupercedeRecord();
	//	$htReplaced = $rcSuper->SelfLink_name();
	    }
	    $arCtrls['isCloseOut'] = $this->isCloseOut()?'YES':'no';
	    //$arCtrls['ID_Supplier'] = $this->GetSupplierID();	// KLUGE
	}
	// non-editable
	//$rcSupp = $this->SupplierRecord();
	//$arCtrls['!Supplier'] = $rcSupp->SelfLink_name();

	// render the template
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $sLabel = $isNew?'Create':'Save';
	    $out .= "\n<input type=submit name='btnSave' value='$sLabel'>"
	      .'</form>'
	      ;
	}

	if (!$isNew) {
	    $out .= $this->DoTitlesHeader();
	
	    // optional Title-entry form:
	    $out .= $this->HandleEntry();
	    
	    // title listing
	    $out .= $this->AdminTitles();

	    // event listing
//	    $objSection = new clsWikiSection($objPage,'Events',NULL,3);
//	    $out .= $objSection->Generate();
	    $out .= $this->EventListing();

	}
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=content>
  <tr><td align=right><b>Name</b>:</td><td>[#Name#]</td></tr>
  <tr><td align=right><b>Code</b>:</td><td>[#Abbr#]</td></tr>
  <tr><td align=right><b>Supplier</b>:</td><td>[#ID_Supplier#]</td></tr>
  <tr><td align=right><b>Available</b>:</td><td>[#DateActive#]</td></tr>
  <tr><td align=right><b>Updated</b>:</td><td>[#LastUpdate#]</td></tr>
  <tr><td align=right><b>Replaced by</b>:</td><td>[#ID_Supercede#]</td></tr>
  <tr><td align=right><b>Closeout</b>:</td><td>[#isCloseOut#]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      HISTORY:
	2010-11-07 adapted from VbzAdminTitle
	2016-01-27 rewritten to use ferreteria forms
    */
    private $oForm;
    protected function PageForm() {
	if (is_null($this->oForm)) {
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Text($oForm,'Name');
	      
	      $oField = new fcFormField_Text($oForm,'Abbr');
	      
	      $oField = new fcFormField_Num($oForm,'ID_Supplier');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		//$oCtrl->SetRecords($this->SupplierTable()->ActiveRecords());
		$oCtrl->SetRecords($this->SupplierTable()->SelectRecords());
		/* 2017-06-18 might reinstate this later...
		if ($this->HasSupplier()) {
		    $oCtrl->AddChoice($this->GetSupplierID(),$this->SupplierRecord()->ListItem_Text());
		    $oField->ControlObject()->Editable(FALSE);
		} */
		
	      $oField = new fcFormField_Time($oForm,'DateActive');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
		$oCtrl->Format('Y-m-d');

	      $oField = new fcFormField_Time($oForm,'LastUpdate');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
		//$oCtrl->Format('Y-m-d h:n:s');	// 2017-06-18 not sure of time fmt, but maybe this is default?

	      $oField = new fcFormField_Num($oForm,'ID_Supercede');
		if ($this->HasSupplier()) {
		    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		    $oCtrl->SetRecords($this->GetRecords_forSupplier());
		    $oCtrl->AddChoice(NULL,'(current)');
		}
		
	      $oField = new fcFormField_Num($oForm,'isCloseOut');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());

	    $this->oForm = $oForm;
	}
	return $this->oForm;
		
    }
    
    //--single--//
    
    // -- ADMIN UI PAGES -- //
    // ++ ADMIN UI FORMS ++ //
    
    /*----
      HISTORY:
	2016-02-06 Rewriting from scratch to make a bit more sense...
	2017-05-29 Pulling the control points back into this object so we don't have to make them public.
    */
    protected function HandleEntry() {
	$oFormIn = fcHTTP::Request();

	$sClass = KS_CLASS_TITLE_ENTRY_MANAGER;
	$oMgr = new $sClass($this);
	
	$isReqEnter = $this->GetDoEnter();
	$isReqDone = $oFormIn->GetBool('btnDone');
	
	$out = NULL;
	if ($isReqEnter && !$isReqDone) {
	    $out .= $oMgr->DoEnter();
	}
	
	return $out;
    }
    /*
    protected function HandleEntry_old() {
	$oFormIn = fcHTTP::Request();
    
	$doEnter = $this->GetDoEnter();
;	// activates the first form
	$doParse = $oFormIn->GetBool('btnParse')	// first button - run STAGE 2
	  || $oFormIn->GetBool('title-btnChk')		// ...or if the Use Topics button was pressed
	  ;
	$doChange = $oFormIn->GetBool('btnChange');	// second button - run STAGE 3

	$doSomething = $doEnter || $doParse || $doChange;
	$out = NULL;

	if ($doSomething) {
	    $doShowForm = $doEnter || $doTitleLoad;
	    //$doBox = $doEnter && !$doTitleLoad;
	    $doBox = FALSE;	// is the box actually helpful?

// WAS commented out with NOTE: 2011-09-30 departments are going away
	    $idDept = $wgRequest->GetIntOrNull('dept');	// get chosen department

	    if ($doChange) {
		$out .= $this->HandleEntry_stage_3();	// process data before displaying status (so status shows the changes)
	    }

	    if ($doShowForm) {
		$out .= "\n<form id=HandleEntry method=POST>";
	    }
	    if ($doBox) {
		$out .= "\n<table align=right><tr><td>";
	    }

	    if ($doParse) {
		$out .= $this->HandleEntry_stage_2();	// allow user to select titles and groups to be added to them

	    }	// IF we're parsing the titles from the entry box

	    if ($doEnter) {
	    // STAGE 1: display controls for entering titles

		$txtTitles = $oPage->ReqArgText('titles');
		$htTitles = fcString::EncodeForHTML($txtTitles);

		// 2016-02-04 Yes, this does belong here.
		$out .= $this->TopicEditor();
		$out .= $oPage->ActionHeader('Enter Titles');
		$out .= <<<__END__
		
<span title="supplier title cat#, then space, then title's name">List titles to be entered:</span><br>
<textarea name=titles cols=40 rows=30>$htTitles</textarea>
<br><input type=submit name="btnParse" value="Check Entered Titles...">
__END__;
		//$out .= $this->SupplierRecord()->Depts_DropDown(NULL,$idDept,'-- choose a department--');
	    }
	    if ($doShowForm) {
		//$out .= '<input type=submit name="btnCancel" value="Cancel">';
		$out .= "\n</form>";
	    }
	    if ($doBox) {
		$out .= "\n</td></tr></table>";
	    }
	}
	return $out;
    } */
    /*----
      PURPOSE: HandleEntry() STAGE 2 -- allow user to select titles and groups to be added to them
      LATER: we will need to modify this to deal with suppliers where the topic (formerly department)
	affects the catalog #.
      HISTORY:
	2012-03-05 switched title query to use cat_titles directly
    */
    protected function HandleEntry_stage_2() {

	$idSupp = $this->Value('ID_Supplier');
	$out = NULL;

      // GRAB USER INPUT
	// get and parse the user-entered list of titles
	$strTitles = clsHTTP::Request()->getText('titles');
	$arOpts = array(
	  'def val'	=> '<font color=red>please enter a descriptive title</font>'
	  );
	$arTitles = fcsStringBlock::ParseTextLines($strTitles,$arOpts);
	/*
	$xts = new xtString($strTitles);
	$arTitles = $xts->ParseTextLines(array('def val'=>'<font color=red>please enter a descriptive title</font>'));
	*/
	$arGroups = clsHTTP::Request()->GetArray('group');	// get any selected groups

	$txtNotes = clsHTTP::Request()->GetText('notes');
	$htNotes = fcString::EncodeForHTML($txtNotes);
      // /USER INPUT

	// show action options
	$out .= '<table align=right><tr><td>';
	$out .= '<h3>Action Options</h3>';

/* 2016-02-05 Although we are still using Departments, I don't see the purpose of having this here.
	// -- department chooser
	$idDept = NULL;	// 2016-02-04 not sure how this is supposed to be set
	$out .= '<h4>Department</h4>';
	$out .= $this->SupplierRecord()->Depts_DropDown(NULL,$idDept,'-- choose a department--');
*/
	// -- list of CT groups
	$out .= '<h4>Groups</h4>';
	$rs = $this->SCGroupTable()->Active_forSupplier($idSupp,'Sort');
	$out .= $rs->MultiSelect('group',TRUE,'size=20');

	$out .= '</td></tr></table>';

	$out .= '<h3>Title Actions</h3>';

	$tblTitles = $this->LCTitleTable();	// local catalog titles table
	$tblCTitles = $this->SCTitleTable();	// supplier catalog titles table
	if (is_array($arTitles)) {
	
	      // 2011-02-06 allow restriction to selected dept
	      if (empty($idDept)) {

		  $sqlBase = '(ID_Supp='.$idSupp.')';
		  $out .= 'Searching supplier for matches...';
	      } else {
		  $sqlBase = '(ID_Dept='.$idDept.')';
		  $rcDept = $this->DepartmentTable($idDept);
		  $out .= 'Searching '.$rcDept->SelfLink_name().' department for matches...';
	      }

	    $isOdd = TRUE;
	    $db = $this->Engine();
	    $out .= <<<__END__

<table class=listing>
  <tr>
    <th>ID</th>
    <th>Cat #</th>
    <th>Name</th>
    <th colspan=2>Topics</th>
    <th colspan=2>Groups</th>
  </tr>
__END__;
	    foreach ($arTitles as $strCatKey=>$strNameEnt) {
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		// is this line already entered as a title record?
		$sqlFilt = $sqlBase.' AND (CatKey='.$db->SanitizeAndQuote($strCatKey).')';
		$rcTitle = $tblTitles->SelectRecords($sqlFilt);
		$isInCat = $rcTitle->HasRows();
		// if it's a title record...
		if ($isInCat) {
		    $rcTitle->NextRow();	// load the record found
		    $idTitle = $rcTitle->GetKeyValue();
		    $strName = $rcTitle->NameString();
		    $htName = fcString::EncodeForHTML($strName);

		    // ...does it have any catalog management entries yet (for this source)?
		    $idSource = $this->GetKeyValue();
		    $sqlFilt = "isActive AND (ID_Title=$idTitle) AND (ID_Source=$idSource)";
		    $rsCTitle = $tblCTitles->SelectRecords($sqlFilt);
		    $isInCtg = $rsCTitle->HasRows();

		    // show checkbox to add this title to the selected groups
		    $htPopup = "add &ldquo;$htName&rdquo; to selected groups";
		    $htAdmin = "<input type=checkbox title='$htPopup' name=addToCtg[$idTitle]>";
		    if ($strNameEnt != $strName) {
			$htAdmin .= "<br><input type=checkbox name=updTitle[$idTitle]>update name";
		    }
		    if ($isInCtg) {
			$htGroups = '';
			$cntGrps = 0;
			while ($rsCTitle->NextRow()) {
			    $rcCGroup = $rsCTitle->GroupObj();
			    if ($cntGrps) {
				$htGroups .= '<br>';
			    }
			    $cntGrps++;
			    $htGroups .= $rcCGroup->SelfLink_friendly();
			}
			$htGroups .= '<br>';
		    } else {
			$htGroups = '';
		    }

		    $strCatKey = $rcTitle->CatKey();
		    $htTopics = $rcTitle->TopicList_ft();
		    $idTitle = $rcTitle->GetKeyValue();
		    $htTitle = $rcTitle->SelfLink();
		    $htPopup = "add &ldquo;$htName&rdquo; to entered topics";
		    $htTTChk = "<input title='$htPopup' type=checkbox name=addTopic[$idTitle]>";
		      /* $out: $htName is just filler here, but I wanted the array to be key-val
			in case we have a need for the value later on. */
		    $out .= <<<__END__
  <tr class=$cssClass>
    <td>$htTitle
      <input type=hidden name=lstTitles[$idTitle] value='$htName' />
      </td>
    <td>$strCatKey</td>
    <td>$htName</td>
    <td rowspan=2>$htTopics</td>
    <td rowspan=2>$htTTChk</td>
    <td rowspan=2>$htGroups</td>
    <td rowspan=2>$htAdmin</td>
  </tr>
__END__;
		} // if ($isInCat)
		else {
		    $strName = $strNameEnt;
		    $htName = fcString::EncodeForHTML($strName);
		}

		// show the entered information:
		$out .= <<<__END__
  <tr class=$cssClass>
    <td><input type=hidden name=lstEntry[$strCatKey] value="$htName" /><span title="what you typed">(sic)</span></td>
    <td><i>$strCatKey</i></td>
    <td><i>$strNameEnt</i></td>
__END__;
		if (!$isInCat) {
		    $out .= '<td><input type=checkbox name=addToCat['.$strCatKey.'] checked>add to catalog</td>';
		}
		$out .= '</tr>';
		// if available, show the information found in the db:
	    }
	    // for visual clarity, put action button in same column as checkboxes
	    $out .= <<<__END__

  <tr>
    <td colspan=7 style="border-top: 1px solid black;"></td>
  </tr>
  <tr>
    <td colspan=3>Notes: <input type=text name=notes size=25 value="$htNotes"></td>
    <td colspan=4 align=center>
      <input type=submit name=btnChange value="Make Changes">
      <input type=submit name=btnDone value="Done">
    </td>
  </tr>
</table>
__END__;
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

	// <user input>
	
	$rq = clsHTTP::Request();

	$doTopics = $rq->GetBool('chkAddTopics');	// add the entered topics to all titles
	$arTitles = $rq->GetArray('lstTitles');		// all matching titles found in catalog
	$arEntry = $rq->GetArray('lstEntry');	// all titles parsed from user text ([catkey] = title)
	$arForCat = $rq->GetArray('addToCat');	// list of titles to add to Supplier ("add to catalog")
	$arToRen = $rq->GetArray('updTitle');	// list of titles to rename
	$arForCtg = $rq->GetArray('addToCtg');	// list of titles to add to groups
	$arGroups = $rq->GetArray('group');

	$txtNotes = $rq->GetText('notes');

	// </user input>

	$idSupp = $this->Value('ID_Supplier');
	//$rcSupp = $this->SupplierRecord();

	$out = NULL;
	$out .= '<h3>Processing Changes</h3>';
	$arEv = array(
	  'where'	=> __METHOD__,
	  'descr'	=> 'processing catalog input',
	  'params'	=> '.ID_Source='.$this->GetKeyValue(),
	  'notes'	=> $txtNotes
	  );

	// CHANGE PART 1: see if we're adding topics

	if ($doTopics) {
	    $ctrlTopics = $this->TopicControl();
	    $arTopics = $ctrlTopics->Data_toChange();
	    if (count($arTopics)) {
	    die('ARFORCAT:'.clsArray::Render($arForCat));
		$out .= 'Adding topics to titles --<ul>';
		$out .= '<li><b>topics</b>:';
		foreach ($arTopics as $idTopic) {
		    $rc = $this->TopicTable($idTopic);
		    $arObjTopics[$idTopic] = $rc;
		    $out .= ' '.$rc->SelfLink();
		}
		$out .= '</li>';

		$out .= '<li><b>titles</b>:';
		$arEv = array(
		  'descr'	=> 'assigning topics to titles',
		  'where'	=> __METHOD__,
		  'code'	=> '+TT',
		  );
		$rcEv = $this->CreateEvent($arEv);
		if (is_null($rcEv)) {
		    throw new exception('Could not create event; SQL='.$this->Engine()->sql);
		}
		if (!$rcEv->HasRows()) {
		    throw new exception('Could not create event; SQL='.$rcEv->sqlMake);
		}
		foreach ($arForCat as $idTitle => $txt) {
		    $out .= ' '.$idTitle;
		    foreach ($arObjTopics as $idTopic => $rc) {
			$rc->AddTitle($idTitle);
		    }
		}
		$arEv = array(
		  'descrfin'	=> '',
		  );
		$rcEv->Finish($arEv);
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
	$hasSTitles = is_array($arForCat) && (count($arForCat)>0);	// has titles to add to Supplier
	$hasRenames = is_array($arToRen) && (count($arToRen)>0);

/* 2011-09-30 departments are going away
	$doEvent = ($hasGroups && $hasGTitles) || ($hasDept && $hasDTitles) || $hasRenames;
*/
	$doEvent = ($hasGroups && $hasGTitles) || $hasSTitles || $hasRenames;
	if ($doEvent) {
	    $rcEv = $this->CreateEvent($arEv);
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
		    $rcTitle = $this->LCTitleTable()->GetItem($idTitle);
		    $out .= 'Adding title '.$rcTitle->SelfLink($rcTitle->CatNum()).' to:';
		    $out .= '<ul>';
		    foreach ($arGroups as $idGroup) {
			$rcGroup = $this->SCGroupTable($idGroup);
			$out .= '<li> (ID '.$idGroup.') '.$rcGroup->SelfLink($rcGroup->NameString());
			$arRes = $rcGroup->Add($idTitle,$this->GetKeyValue());
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
	    $rcSupp = $this->SupplierRecord();
	    $sSupp = $rcSupp->NameString();
	    $strStat .= " Titles added to Supplier $idSupp ($sSupp):";
	    $out .= "Adding titles to &ldquo;$sSupp&rdquo;.";
	    if (count($arForCat) > 0) {
		foreach ($arForCat as $catnum => $on ) {
		    $arAdd[$catnum] = $arEntry[$catnum];
		    $strStat .= ' '.$catnum;
		}
		$rcSupp->AddTitles($arAdd,$arEv);
	    } else {
		throw new exception('Should this ever happen?');
	    }
	    $strStat .= '.';
	} else {
	    $out .= 'No titles added.';
	}

	// updating title names
	if ($hasRenames) {
	    $strStat .= ' Renaming Titles:';
	    foreach ($arToRen as $idTitle => $on ) {
		$strStat .= ' '.$idTitle;
		$objTitle = $this->Engine()->Titles()->GetItem($idTitle);
		$strCatKey = $objTitle->CatKey;
		$strNameEnt = $arTitles[$strCatKey];
		$strNameOld = $objTitle->Name;
		$out .= "<br>Renaming ID $idTitle ($strCatKey): &ldquo;$strNameOld&rdquo; &rarr; &ldquo;$strNameEnt&rdquo;";
		$objTitle->Name($strNameEnt,$arEv);	// rename the title
	    }
	    $strStat .= '.';
	}

	if (!is_null($rcEv)) {
	    $arEv = array(
	      'descrfin' => $strStat
	      );
	    $rcEv->Finish($arEv);
	}
	return $out;
    }
    
    //--sources--///
    //++topics++//
    
    /*----
      RETURNS: Edit controls to specify a list of topics
    */
    protected function TopicEditor() {
	$ctrlList = $this->TopicControl();

	$out = $ctrlList->HandleInput(FALSE);	// FALSE: only expect input if btnCheck is pressed
	$out .= "\n<br>";
	$out .= $ctrlList->RenderForm_Entry(TRUE);

	return $out;
    }
    /*----
      HISTORY:
	2016-02-05 Uncommenting the function-variable contents and trying to figure out
	  how to make this integrate properly with the larger Title entry form.
    */
    private $oTopicCtrl;    
    protected function TopicControl() {

	if (!isset($this->oTopicCtrl)) {

	    $tblTitleTopics = $this->TitleTopicTable();
	    $me = $this;
	    $arOpts = $this->TopicTable()->TopicListing_base_array();
	    $arOpts['btnChk_Text']	= 'Use topics:';
	    $arOpts['txtConf_list']	= 'using';
	    $arOpts['btnChg_Text']	= NULL;

	    $arOpts['fHandleData_Change_Start'] = function($iText) use ($me) {
		  $arEv = array(
		    'descr'	=> 'Adding '.$iText,
		    'code'	=> 'topic++',
		    'where'	=> __METHOD__
		    );
		  $me->CreateEvent($arEv);
	      };
	    $arOpts['fHandleData_Change_Finish'] = function($iText) use ($me) {
		  $arEv = array(
		    'descrfin'	=> $iText
		    );
		  $me->GetEvent()->Finish($arEv);
		  // normally, we'd do this -- but this is part of a larger form...
		  //$me->SelfRedirect(NULL,$iText);
	      };
	    $arOpts['fHandleData_Change_Item'] = function($iVal) use ($me,$tblTitleTopics) {
		  $sqlTopic = $iVal;
		  $arIns = array(
		    'ID_Title'	=> SQLValue($me->GetKeyValue()),
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
	      };

	    $ctrlList = new clsWidget_ShortList();
	    $ctrlList->Options($arOpts);

	    $this->oTopicCtrl = $ctrlList;
	}

	return $this->oTopicCtrl;
    }
    
    //--topics--//
    //++titles++//
    
    protected function DoTitlesHeader() {
	$oMenu = new fcHeaderMenu();
	  $oMenu->SetNode($ol = new fcMenuOptionLink('titles.do','enter',NULL,NULL));
	    $this->SetDoEnter($ol->GetIsSelected());
	  
	$oHdr = new fcSectionHeader('Titles',$oMenu);
	return $oHdr->Render();
    }
    /*----
      ACTION: Displays the list of titles for this catalog, along with any
	appropriate administrative controls.
      HISTORY:
	2010-11-06 started writing
	2016-01-27 marked as needing update
	2017-05-29 fixing menus
    */
    protected function AdminTitles() {
/*
	$arMenu = array(
	    // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),
	    'enter',	// link key
	    NULL,	// group key
	    NULL,	// OFF display
	    NULL,	// ON display,
	    'edit this source'	// popup description
	    ),
	  );
	$out = $oPage->ActionHeader('Titles',$arMenu);
	*/
	$rs = $this->SCTitleTable()->List_forSource($this->GetKeyValue());

	return $rs->AdminList();
    }
    
    //--titles--//
    
    // -- ADMIN UI FORMS -- //
}
