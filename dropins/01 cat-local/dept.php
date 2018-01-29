<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Departments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2014-03-25 I was trying to make Departments obsolete, but I'm not sure this is doable. Needs more research.
*/

define('KS_EVENT_VBZCART_ADD_TITLES','vc.add.titles');

class vctAdminDepts extends vctDepts implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;
    use vtTableAccess_Supplier;
    use ftExecutableTwig;
    
    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcrAdminDept';
    }
    public function GetActionKey() {
	return KS_ACTION_CATALOG_DEPARTMENT;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Departments');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return '<div class=content>There is no admin function for all Departments yet.</div>';
    }

    // -- EVENTS -- //
    // ++ RECORDS ++ //
    
    public function Data_forSupp($idSupp,$iFilt=NULL) {
	$sqlFilt = "ID_Supplier=$idSupp";
	if (!is_null($iFilt)) {
	    $sqlFilt = "($sqlFilt) AND ($iFilt)";
	}
	return $this->GetData($sqlFilt,NULL,'isActive, Sort, CatKey, PageKey');
    }
    // CALLBACK for form display
    public function GetData_forDropDown($sqlFiltX=NULL) {
	$sqlDept = $this->TableName_Cooked();
	$sqlSupp = $this->SupplierTable()->TableName_Cooked();
	$sqlFilt = is_null($sqlFiltX)?NULL:" AND ($sqlFiltX)";
	$sql =
	  "SELECT"
	    ." d.ID"
	    .", CONCAT_WS(' ',CONCAT_WS('-',s.CatKey,d.CatKey),d.Name) AS Text"
	    .", d.Name"
	    .", PageKey"
	  ." FROM $sqlDept AS d"
	    ." LEFT JOIN $sqlSupp AS s"
	    ." ON d.ID_Supplier=s.ID"
	  ." WHERE (d.isActive AND s.isActive)"
	  .$sqlFilt
	  ." ORDER BY s.CatKey, Sort, Name";
	$rs = $this->FetchRecords($sql);
	return $rs;
    }  
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    /*----
      HISTORY:
	2015-11-08 Removed $idSupp parameter; this is currently only called
	  by Supplier objects, which do have the Supplier object handy, so
	  no need to offer a lookup.
    */
    public function Listing_forSupp(vcrSupplier $rcSupp) {
	$sSuppKey = strtolower($rcSupp->CatKey());
	$idSupp = $rcSupp->GetKeyValue();

	$rs = $this->SelectRecords('ID_Supplier='.$idSupp,'isActive, Sort, CatKey, PageKey');
	if ($rs->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>Cat</th>
    <th>Page</th>
    <th>Sort</th>
    <th>Name</th>
    <th>Description</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$out .= $rs->AdminRow_forSupplier($isOdd);
		$isOdd = !$isOdd;
		
		/*
		$htPageKey = $rs->PageKey_admin();
		$htCatKey = $rs->CatKey_admin();
		
//		if (is_null($sPageCode)) {
//		    $ftPageCode = "($sPageCode)";
//		} else {
//		    $sPagePath = $sSuppKey.'/'.strtolower($sPageCode);
//		    $ftPageCode = '!<a href="'.KWP_CAT.$sPagePath.'">'.$sPageCode.'</a>!';
//		}

		//$id = $rs->GetKeyValue();
		$ftID = $rs->SelfLink();	// is this what the previous line intends?
		$isActive = $rs->IsActive();
		if ($isActive) {
		    $cssClass = $isOdd?'odd':'even';
		} else {
		    $cssClass = 'state-inactive';
		}
		$ftActive = fcHTML::fromBool($isActive);
		$sCatKey = $rs->CatKey();
		$sSort = $rs->SortKey();
		$sName = $rs->NameString();
		$sDescr = $rs->Description();
		$out .= <<<__END__
  <tr class="$cssClass">
    <td>$ftID</td>
    <td>$ftActive</td>
    <td>$htCatKey</td>
    <td>$htPageKey</td>
    <td>$sSort</td>
    <td>$sName</td>
    <td>$sDescr</td>
  </tr>
__END__;
//*/
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'This supplier has no departments.';
	}
	return $out;
    }
    
    // -- WEB UI -- //
    
}
class vcrAdminDept extends vcrDept_shop implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftExecutableTwig;
    use ftLoggableRecord;
    use ftSaveableRecord;	// implements ChangeFieldValues()
    //use vtLoggableAdminObject;
    
    // ++ TRAIT HELPERS ++ //
    
    public function SelfLink_name() {
	$sKey = strtoupper($this->PageKey_asSet());
	$sName = $this->NameString();
	$sShow = fcString::Concat(' ',$sKey,$sName);
	return $this->SelfLink($sShow);
    }
    
    // -- TRAIT HELPERS -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sTitle = 'Department: '.$this->NameString();

	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle($sTitle);
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    } */

    // -- EVENTS -- //
    // ++ CLASSES ++ //
    
    protected function SuppliersClass() {
	return KS_CLASS_CATALOG_SUPPLIERS;
    }
    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }
    
    // -- CLASSES -- //
    // ++ TABLES ++ //

      // TitleTable() implemented in base class
    
    // -- TABLES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    // CALLBACK for dropdown Control
    public function ListItem_Text() {
	if ($this->FieldIsNonBlank('Text')) {
	    return $this->GetFieldValue('Text');
	} else {
	    return $this->NameString();
	}
    }
    // CALLBACK for dropdown Control
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    protected function AddTitle($sCatKey,$sName,$sNotes) {
      // log start of event
	$idDept = $this->GetKeyValue();
	$sDesc = "new title in dept. #$idDept: $sCatKey: $sName";
	$arEv = array(
	  // use the class constant for the first line so the class will load
	  fcrEvent::KF_DESCR_START => $sDesc,
	  fcrEvent::KF_NOTES => $sNotes,
	  fcrEvent::KF_WHERE => __METHOD__,
	  fcrEvent::KF_CODE => '+TITLE',
	  fcrEvent::KF_PARAMS => NULL,
	  fcrEvent::KF_IS_ERROR => FALSE,
	  fcrEvent::KF_IS_SEVERE => FALSE,
	  );
	$rcEv = $this->CreateEvent($arEv);
	
	$arAdd = array(
	  'ID_Dept'	=> $this->GetKeyValue(),
	  'ID_Supp'	=> $this->SupplierID(),
	  );
	$idTitle = $this->TitleTable()->Add($sCatKey,$sName,$sNotes,$arAdd);
	$arEv = array(fcrEvent::KF_DESCR_FINISH => "Title #$idTitle created.");
	$rcEv->Finish($arEv);
	
	return $idTitle;
    }
    /*----
      ACTION: Add a list of titles to this department
      INPUT:
	iTitles: array
	  iTitles[catkey] = name
	iEvent: array to be passed to event log
    */
    public function AddTitles(array $iTitles,array $iEvent=NULL) {
	throw new exception('Does anyone actually call this?');
	// 2016-01-18 This is probably called after checking a bunch of manually-entered titles -- TBD
    
	$cntTitles = count($iTitles);
	if ($cntTitles > 0) {
	    $strDescr = 'adding '.$cntTitles.' title'.Pluralize($cntTitles);
	    $iEvent['descr'] = StrCat($iEvent['descr'],$strDescr,' ');
	    $iEvent['where'] = nz($iEvent['where'],__METHOD__);
	    $iEvent['code'] = 'ADM';	// add multiple
	    $this->StartEvent($iEvent);
	    $cntAdded = 0;
	    $cntError = 0;
	    $txtAdded = '';
	    $txtError = '';
	    $tblTitles = $this->Engine()->Titles();
	    foreach ($iTitles as $catnum => $name) {
		$arIns = array(
		  'Name'	=> SQLValue($name),
		  'CatKey'	=> SQLValue($catnum),
		  'ID_Dept'	=> $this->ID,
		  'DateAdded'	=> 'NOW()'
		  );
		$ok = $tblTitles->Insert($arIns);
		if ($ok) {
		    $idNew = $tblTitles->LastID();
		    $cntAdded++;
		    $txtAdded .= '['.$catnum.' ID='.$idNew.']';
		} else {
		    $cntError++;
		    $txtError .= '['.$catnum.' Error: '.$this->objDB->getError().']';
		}
	    }
	    if ($cntError > 0) {
		$txtDescr = $cntError.' error'.Pluralize($cntError).': '.$txtError;
		$txtDescr .= ' and ';
	    } else {
		$txtDescr = 'Success:';
	    }
	    $txtDescr .= $cntAdded.' title'.Pluralize($cntAdded).' added '.$txtAdded;
	    $arEv = array(
	      'descrfin' => SQLValue($txtDescr),
	      'error' => SQLValue($cntError > 0)
	      );
	    $this->FinishEvent($arEv);
	}
    }
    
    // -- ACTIONS -- //
    // ++ WEB UI ++ //
    
    //++pieces++//

    public function DropDown($iName=NULL,$idDefault=NULL,$sNone=NULL) {
	$sCtrlName = is_null($iName)?($this->Table()->ActionKey()):$iName;	// control name defaults to action key

	if ($this->HasRows()) {
	    while ($this->NextRow()) {
		$id = $this->GetKeyValue();
		$htAbbr = $this->PageKey_isSet()?'':($this->PageKey_asSet().' ');
		$htShow = $htAbbr.$this->NameString();
		$ar[$id] = $htShow;
	    }
	    
	    $out = fcHTML::DropDown_arr($sCtrlName,$ar,$idDefault,$sNone) 	    ;
	    
	    return $out;
	} else {
	    return NULL;
	}
    }
    
    //--pieces--//
    //++single++//
    
    /*----
      HISTORY:
	2011-09-25 renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	/*
	$sAction = $oPage->PathArg('do');
	$doEdit = ($sAction == 'edit');
	$doEnter = ($sAction == 'enter');
	*/
	$doSave = $oFormIn->GetBool('btnSave');

	$frm = $this->RecordForm();
	
	// save edits before showing events
	if ($doSave) {
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	}
	
	// header/menu
	$sTitle = '&ldquo;'.$this->NameString().'&rdquo; Department';
	fcApp::Me()->GetPageObject()->SetPageTitle($sTitle);
	$oMenu = fcApp::Me()->GetHeaderMenu();
	
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit department record'));
	    $doEdit = $ol->GetIsSelected();
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','enter',NULL,NULL,'enter... something (new titles?)'));
	    $doEnter = $ol->GetIsSelected();

	/* 2017-03-18 old
	$arActs = array(
	  new clsActionLink_option(array(),    // an "edit" link
	    'edit',			// $iLinkKey
	    'do',			// $iGroupKey
	    NULL,			// $iDispOff
	    NULL,			// $iDispOn
	    'edit this department'	// $iDescr - shows as hover-over text
	    ),
	  );
	$oPage->PageHeaderWidgets($arActs);
	*/
	$frm->LoadRecord();
	
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	
	// some more output calculations
	$url = $this->ShopURL();
	$htShopLink = " [<a href='$url' title='shopping page'>shop</a>]";

	$arCtrls['!ID'] = $this->SelfLink().$htShopLink;
	
	$out = NULL;
	
	/*
	// Title display/editing stuff
	$sListing = $this->TitleListing($doEnter);
	if ($doEnter) {
	    // if we're entering stuff, change the layout a bit:
	    $sEntry = $this->DoTitleEntryForm($doEnter);
	    $sTitleSections = <<<__END__

<table>
  <tr>
    <td valign=top>$sListing</td>
    <td valign=top>$sEntry</td>
  </tr>
</table>
__END__;
	} else {
	    $sTitleSections = $sListing;
	} */

	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	    if (!$this->PageKey_isSet()) {
		$sCatKey = $this->CatKey();
		$arCtrls['PageKey'] = "(blank; using <b>$sCatKey</b>)";
	    }
	}

	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '</form>';
	}
	if (!$this->IsNew()) {
	    $out .= 
	      $this->AdminTitles($doEnter)
	      .$this->EventListing()
	      ;
	}
	
	return $out;
    }
    private $oForm;
    private function RecordForm() {
	if (empty($this->oForm)) {
	
	    $oForm = new fcForm_DB($this);

	      $oField = new fcFormField_Num($oForm,'isActive');	// currently stored as BOOL (INT)
		$oField->ControlObject(new fcFormControl_HTML_CheckBox($oField));

	      $oField = new fcFormField_Text($oForm,'Name');
	      $oField = new fcFormField_Text($oForm,'Descr');
	      
	      $oField = new fcFormField_Num($oForm,'ID_Supplier');
		$oField->StorageObject()->Writable(FALSE);	// we never move departments between suppliers
		
	      $oField = new fcFormField_Text($oForm,'CatKey');
	      $oField = new fcFormField_Text($oForm,'PageKey');
	      $oField = new fcFormField_Text($oForm,'Sort');
	      
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=content>
  <tr>	<td align=right><b>ID</b>:</td>		<td>[[!ID]] - Active: [[isActive]]</td>		</tr>
  <tr>	<td align=right><b>Name</b>:</td>	<td>[[Name]]</td>		</tr>
  <tr>	<td align=right><b>Description</b>:</td><td>[[Descr]]</td>		</tr>
  <tr>	<td align=right><b>Supplier</b>:</td>	<td>[[ID_Supplier]]</td>	</tr>
  <tr>	<td align=right><b>CatKey</b>:</td>	<td>[[CatKey]]</td>		</tr>
  <tr>	<td align=right><b>PageKey</b>:</td>	<td>[[PageKey]]</td>		</tr>
  <tr>	<td align=right><b>SortKey</b>:</td>	<td>[[Sort]]</td>		</tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    
    //--single--//
    //++multiple++//
    
    public function AdminRow_forSupplier($isOdd) {
	$htPageKey = $this->PageKey_asSet();
	$htCatKey = $this->CatKey();
	
	$ftID = $this->SelfLink();	// is this what the previous line intends?
	$isActive = $this->IsActive();
	if ($isActive) {
	    $cssClass = $isOdd?'odd':'even';
	} else {
	    $cssClass = 'state-inactive';
	}
	$ftActive = fcHTML::fromBool($isActive);
	$sCatKey = $this->CatKey();
	$sSort = $this->SortKey();
	//$sName = $this->NameString();
	$htName = $this->ShopLink();
	$sDescr = $this->Description();
	$out = <<<__END__
  <tr class="$cssClass">
    <td>$ftID</td>
    <td>$ftActive</td>
    <td>$htCatKey</td>
    <td>$htPageKey</td>
    <td>$sSort</td>
    <td>$htName</td>
    <td>$sDescr</td>
  </tr>
__END__;

	return $out;
    }
    
    //--multiple--//
    //++dependent++//
    
    // PURPOSE: Handles the Titles section of the page, both listing and entering
    protected function AdminTitles($doEnter) {
	$sListing = $this->TitleListing($doEnter);
	if ($doEnter) {
	    // if we're entering stuff, change the layout a bit:
	    $sEntry = $this->DoTitleEntryForm($doEnter);
	    $out = <<<__END__

<table>
  <tr>
    <td valign=top>$sListing</td>
    <td valign=top>$sEntry</td>
  </tr>
</table>
__END__;
	} else {
	    $out = $sListing;
	}
	return $out;
    }
    
    //--dependent--//
    //++forms++//
    
    /*----
      LAYOUT: We want this to appear side-by-side with the title listing. They're both tall
	and narrow, and it might be handy to have the title listing visible when we're entering
	stuff. The output from this routine will be stuffed into the right-hand cell of a table.
    */
    protected function DoTitleEntryForm($doEnter) {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$doCheck = FALSE;
	if ($oFormIn->GetBool('btnCheck')) {
	    $doCheck = TRUE;
	    $nStage = 2;
	} elseif ($oFormIn->GetBool('btnAdd')) {
	    $nStage = 3;
	} else {
	    $nStage = 1;
	}

	/* 2017-03-18 actually, is this necessary?
	$oMenu = new fcHeaderMenu();
	$oHdr = new fcSectionHeader('Enter Titles',$oMenu);	

	$out = NULL;
	
	// build section header
	$arActs = array(
	  new clsActionLink_option(array(),    // "enter titles" link
	    'enter',				// $iLinkKey
	    'do',				// $iGroupKey
	    NULL,				// $iDispOff
	    'cancel',				// $iDispOn
	    'enter titles for this department'	// $iDescr - shows as hover-over text
	    ),
	  );
	$out .= 
	  //'<table align=right><tr><td>'
	  $oPage->ActionHeader('Enter Titles',$arActs,'section-header-sub')
	  ;
*/    
	$out = NULL;

	$doShowForm = $doEnter || $doCheck;
	if ($doShowForm) {
	    $out .= "\n<form method=post>";
	}
	$txtNotes = $oFormIn->GetString('notes');
	$htNotes = 'Notes: <input type=text name=notes size=25 value="'.fcString::EncodeForHTML($txtNotes).'">';
	
	switch ($nStage) {
	  case 1:	// STAGE 1: display form for entering titles
	    $out .= 'Enter titles to check ("catkey title"):<br>'
	      .'<textarea name=titles cols=30 rows=30></textarea>'
	      .'<br>'.$htNotes
	      .'<br><input type=submit name="btnCheck" value="Check">'
	      ;
	    break;
	  case 2:	// STAGE 2: check entered titles, allow user to fix problems & confirm
	    $sTitles = $oPathIn->GetString('titles');
	    $arTitles = fcsStringBlock::ParseTextLines(
	      $sTitles,
	      array(
		'blanks'	=> " \t",
		'sep'		=> ' ',
		)
	      );
	    if (is_array($arTitles)) {
		$doDeptOnly = $this->AffectsCatNum();
		$out .= '<table class=content>';
		$db = $this->GetConnection();
		foreach ($arTitles as $sCatKey => $sName) {
		    $sqlFilt = '';
		    if ($doDeptOnly) {
			$sqlFilt = '(ID_Dept='.$this->GetKeyValue().') AND ';
		    }
		    $sqlCatKey = $db->Sanitize_andQuote(strtoupper($sCatKey));
		    $idSupp = $this->SupplierID();
		    $sqlFilt .= "(UPPER(CatKey)=$sqlCatKey) AND (ID_Supp=$idSupp)";
		    $rsTitles = $this->TitleTable()->SelectRecords($sqlFilt);
		    if ($rsTitles->HasRows()) {
			$htStatus = '';
			$htMatches = '';
			while ($rsTitles->NextRow()) {
			    $htTitle = $rsTitles->SelfLink($rsTitles->CatNum()).': '.$rsTitles->NameString();
			    $htMatches .= '<tr><td></td><td><small>'.$htTitle.'</td></tr>';
			}
		    } else {
			$htStatus = '<td><font size=-2 color=green>new</font></td>';
			$htMatches = '';
		    }
		    $out .= '<tr><td>'.$sCatKey.'</td><td>'.$sName.'</td>'.$htStatus.'</tr>';
		    $out .= $htMatches;
		}
		$out .= '</table>';
	    }
	    $out .= $htNotes.'<br>';
	    $out .= '<input type=hidden name="titles" value="'.fcString::EncodeForHTML($sTitles).'">';
	    $out .= '<input type=submit name="btnAdd" value="Add Titles">';
	    break;
	  case 3:	// STAGE 3; process entered titles -- add them to the data:
	    $sTitles = $oPathIn->GetString('titles');
	    //$arTitles = $this->ParseSubmittedTitles($sTitles);
	    $arTitles = fcStringBlock_static::ParseTextLines($sTitles);
	    $cntTitles = count($arTitles);
	    $sAddText = 'Add '.$cntTitles.' title'.fcString::Pluralize($cntTitles);

	    // start event
	    
	    $oApp = fcApp::Me();
	    $sDescr = "adding $cntTitles title".fcString::Pluralize($cntTitles)." to '".$this->NameString().' department.';
	    $arData = array(
	      'text'	=> $sTitles
	      );
	    $idEvent = $oApp->EventPlexTable()->CreateEvent(KS_EVENT_VBZCART_ADD_TITLES,$sDescr,$arData);
	    $oApp->EventPlexTable_inTable()->CreateEvent($idEvent,$this->GetTableWrapper()->GetActionKey(),$this->GetKeyValue());
	    
	    /*
	    // log the event start:
	    // fcrEvent::TYPE_DESCR_START
	    // fcrEvent::TYPE_DESCR_FINISH
	    // fcrEvent::TYPE_NOTES
	    // fcrEvent::TYPE_MOD_TYPE	- handled automatically
	    // fcrEvent::TYPE_MOD_INDEX	- handled automatically
	    // fcrEvent::TYPE_WHERE
	    // fcrEvent::TYPE_CODE
	    // fcrEvent::TYPE_PARAMS
	    // fcrEvent::TYPE_IS_ERROR
	    // fcrEvent::TYPE_IS_SEVERE
	    //$this->StartEvent(__METHOD__,'ADD',$sAddText,$txtNotes);
	    $arEv = array(
	      // use the class constant for the first line so the class will load
	      fcrEvent::TYPE_DESCR_START => $sAddText,
	      fcrEvent::TYPE_NOTES => $txtNotes,
	      fcrEvent::TYPE_WHERE => __METHOD__,
	      fcrEvent::TYPE_CODE => 'ADD-BULK',
	      fcrEvent::TYPE_PARAMS => NULL,
	      fcrEvent::TYPE_IS_ERROR => FALSE,
	      fcrEvent::TYPE_IS_SEVERE => FALSE,
	      );
	    $rcEv = $this->CreateEvent($arEv);
	    */
	    
	    $out .= '<table class=content>';
	    $tTitles = $this->TitleTable();
	    foreach ($arTitles as $sCatKey=>$sName) {
		$idTitle = $this->AddTitle($sCatKey,$sName,$txtNotes);
		$rcTitle = $tTitles->GetRecord_forKey($idTitle);
		$out .= '<tr><td>'
		  .$rcTitle->SelfLink($rcTitle->CatNum())
		  .'</td><td>'
		  .$rcTitle->NameString()
		  .'</td></tr>'
		  ;
	    }
	    $out .= '</table>';

	    // TODO: finish event
	    $oApp->EventPlexTable_Finish()->CreateEvent($idEvent);
	    
	    // log completion of event:
	    //$this->FinishEvent();
	    $arEv = array(	// LATER: log any success or error codes
	      );
	    $rcEv->Finish($arEv);
	    $this->SelfRedirect(NULL,$out);	// format of $out will probably need refinement
	  
	}
	if ($doShowForm) {
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	//$out .= '</td></tr></table>';
	return $out;
    }
    /*----
      NOTE: $doEnter is just so we can suppress the "enter" menu entry if
	we're already doing it. There ought to be a better way to signal this...
      HISTORY:
	2017-03-18 Revised for current Ferreteria API, but didn't try to untangle weirdness.
    */
    protected function TitleListing($doEnter) {

	$oMenu = new fcHeaderMenu();
	$oHdr = new fcSectionHeader('Titles',$oMenu);
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','enter',NULL,'cancel','enter titles for this department'));
          
	$htHdr = $oHdr->Render();
    
    /* 2017-03-18 old
	// build section header
	$arActs = array();
	if (!$doEnter) {
	    $arActs[] = new clsActionLink_option(array(),    // "enter titles" link
	      'enter',			// $iLinkKey
	      'do',			// $iGroupKey
	      NULL,			// $iDispOff
	      'cancel',			// $iDispOn
	      'enter titles for this department'	// $iDescr - shows as hover-over text
	      );
	}
*/
	$out = $this->TitleRecords()->AdminRows(
	    array('disp.hdr' => $htHdr)
	  );
	return $out;
    }
    
    //--forms--//
    
    // -- WEB UI -- //
}
