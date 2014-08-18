<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Departments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2014-03-25 I was trying to make Departments obsolete, but I'm not sure this is doable. Needs more research.
*/
class VCTA_Depts extends clsDepts {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCRA_Dept');
    }
    public function Data_forSupp($iSupp,$iFilt=NULL) {
	$sqlFilt = "ID_Supplier=$iSupp";
	if (!is_null($iFilt)) {
	    $sqlFilt = "($sqlFilt) AND ($iFilt)";
	}
	$objRecs = $this->GetData($sqlFilt,NULL,'isActive, Sort, CatKey, PageKey');
	return $objRecs;
    }
    public function Listing_forSupp($iSuppID,clsSupplier $iSuppObj=NULL) {
	global $wgOut;

	if (is_null($iSuppObj)) {
	    $objSupp = $this->objDB->Suppliers()->GetItem($iSuppID);
	} else {
	    $objSupp = $iSuppObj;
	}
	$strSuppKey = strtolower($objSupp->CatKey);

	$objRecs = $this->GetData('ID_Supplier='.$iSuppID,'VbzAdminDept','isActive, Sort, CatKey, PageKey');
	if ($objRecs->HasRows()) {
	    $out = "{| class=sortable\n|-\n! ID || A? || Cat || Page || Sort || Name || Description";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$strPageCode = $objRecs->PageKey;
		if (is_null($strPageCode)) {
		    $wtPageCode = $strPageCode;
		} else {
		    $strPagePath = $strSuppKey.'/'.strtolower($strPageCode);
		    $wtPageCode = '['.KWP_CAT.$strPagePath.' '.$strPageCode.']';
		}
		$id = $objRecs->ID;
		$wtID = SelfLink_Page('dept','id',$id,$id);
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$isActive = $objRecs->isActive;
		if (!$isActive) {
		    $wtStyle .= ' color: #888888;';
		}
		$out .= "\n|- style=\"$wtStyle\"".
		    "\n| ".$wtID.
		    ' || '.($isActive?'&radic;':'').
		    ' || '.$objRecs->CatKey.
		    ' || '.$wtPageCode.
		    ' || '.$objRecs->Sort.
		    ' || '.$objRecs->Name.
		    ' || '.$objRecs->Descr;
		$isOdd = !$isOdd;
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'This supplier has no departments.';
	}
	$wgOut->addWikiText($out,TRUE);	$out = '';
    }
}
class VCRA_Dept extends clsDept {
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name() {
	$strPgKey = strtoupper($this->Value('PageKey'));
	$out = (is_null($strPgKey))?'':($strPgKey.' ');
	$out .= $this->Value('Name');
	return $this->AdminLink($out);
    }
    public function DropDown($iName=NULL,$iDefault=NULL,$iNone=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key

	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->ID;
		$htAbbr = (is_null($this->PageKey))?'':($this->PageKey.' ');
		$htShow = $htAbbr.$this->Name;
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
    }
/* 2010-11-06 commenting out old event handling
    public function StartEvent($iWhere,$iCode,$iDescr,$iNotes=NULL) {
	$arEvent = array(
	  'type'	=> clsEvents::kTypeDept,
	  'id'		=> $this->ID,
	  'where'	=> $iWhere,
	  'code'	=> $iCode,
	  'descr'	=> $iDescr
	  );
	if (!is_null($iNotes)) {
	    $arEvent['notes'] = $iNotes;
	}
	$this->idEvent = $this->objDB->Events()->StartEvent($arEvent);
    }
    public function FinishEvent() {
	$this->objDB->Events()->FinishEvent($this->idEvent);
    }
*/
    /*----
      HISTORY:
	2010-10-20 changing event logging to use helper class
	2010-11-07 added StartEvent(), FinishEvent()
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
/*
	$objTbl = $this->objDB->Events();
	$objRows = $objTbl->GetData('(ModType="'.clsEvents::kTypeDept.'") AND (ModIndex='.$this->ID.')');
	if ($objRows->HasRows()) {
	    $out = $objRows->AdminRows();
	} else {
	    $out = 'No events found for this department.';
	}
	return $out;
*/
    }
    /*----
      HISTORY:
	2011-09-25 renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strCatKey = $this->CatKey;
	$strPageKey = $this->PageKey;
	if (empty($strPageKey)) {
	    $strShopKey = strtolower($strCatKey);
	} else {
	    $strShopKey = strtolower($strPageKey);
	}
	$strAction = $vgPage->Arg('do');
	$doEdit = ($strAction == 'edit');
	$doEnter = ($strAction == 'enter');

	$doTitleCheck = $wgRequest->GetBool('btnCheck');
	$doTitleAdd = $wgRequest->GetBool('btnAdd');
	$doEnterBox = $doEnter || $doTitleCheck || $doTitleAdd;

	$strTitle = '&ldquo;'.$this->Name.'&rdquo; Department';

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,$strTitle);
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ActionAdd('edit');
	$objSection->ActionAdd('enter','enter titles for this department');
	$out = $objSection->Generate();

	$wgOut->AddHTML($out); $out = '';

	if ($doEnterBox) {
	    $out = '<table align=right><tr><td><h3>Enter Titles</h3>';
	    $doShowForm = $doEnter || $doTitleCheck;
	    if ($doShowForm) {
		$out .= $objSection->FormOpen();
	    }
	    $txtNotes = $wgRequest->GetText('notes');
	    $htNotes = 'Notes: <input type=text name=notes size=25 value="'.htmlspecialchars($txtNotes).'">';
	    if ($doEnter) {
	    // STAGE 1: display form for entering titles
		$out .= 'Enter titles to check:<br>';
		$out .= '<textarea name=titles cols=5 rows=30></textarea>';
		$out .= '<br>'.$htNotes;
		$out .= '<br><input type=submit name="btnCheck" value="Check">';
	    } elseif ($doTitleCheck) {
	    // STAGE 2: check entered titles, allow user to fix problems & confirm
		$strTitles = $wgRequest->getText('titles');
		//$arTitles = $this->ParseSubmittedTitles($strTitles);
		$arTitles = ParseTextLines($strTitles);
		if (is_array($arTitles)) {
		    $doDeptOnly = $this->AffectsCatNum();
		    $out .= '<table>';
		    foreach ($arTitles as $strCatKey=>$strName) {
			$sqlFilt = '';
			if ($doDeptOnly) {
			    $sqlFilt = '(ID_Dept='.$this->ID.') AND ';
			}
			$sqlFilt .= 'CatKey='.SQLValue($strCatKey);
			$objTitles = $this->objDB->Titles()->GetData($sqlFilt);
			if ($objTitles->HasRows()) {
			    $htStatus = '';
			    $htMatches = '';
			    while ($objTitles->NextRow()) {
				$htTitle = $objTitles->AdminLink($objTitles->CatNum()).': '.$objTitles->Name;
				$htMatches .= '<tr><td></td><td><small>'.$htTitle.'</td></tr>';
			    }
			} else {
			    $htStatus = '<td><font size=-2 color=green>new</font></td>';
			    $htMatches = '';
			}
			$out .= '<tr><td>'.$strCatKey.'</td><td>'.$strName.'</td>'.$htStatus.'</tr>';
			$out .= $htMatches;
		    }
		    $out .= '</table>';
		}
		$out .= $htNotes.'<br>';
		$out .= '<input type=hidden name="titles" value="'.htmlspecialchars($strTitles).'">';
		$out .= '<input type=submit name="btnAdd" value="Add Titles">';
	    } else {
	    // STAGE 3; process entered titles -- add them to the data:
		$strTitles = $wgRequest->getText('titles');
		$arTitles = $this->ParseSubmittedTitles($strTitles);
		$cntTitles = count($arTitles);
		$strAddText = 'Add '.$cntTitles.' title'.Pluralize($cntTitles);
		$this->StartEvent(__METHOD__,'ADD',$strAddText,$txtNotes);
		$out .= '<table>';
		$objTitles = $this->objDB->Titles();
		foreach ($arTitles as $strCatKey=>$strName) {
		    $idTitle = $objTitles->Add($strCatKey,$strName,$this->ID,$txtNotes);
		    $objTitle = $this->objDB->Titles()->GetItem($idTitle);
		    $out .= '<tr><td>'.$objTitle->AdminLink($objTitle->CatNum()).'</td><td>'.$objTitle->Name.'</td></tr>';
		}
		$out .= '</table>';
		$this->FinishEvent();
	    }
	    if ($doShowForm) {
		$out .= '<input type=submit name="btnCancel" value="Cancel">';
		$out .= '<input type=reset value="Reset">';
		$out .= '</form>';
	    }
	    $out .= '</td></tr></table>';
	    $wgOut->AddHTML($out); $out = '';
	}

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	} else {
	}

	$vgPage->UseWiki();
	$out .= "\n* '''ID''': ".$this->ID;
	$out .= "\n* '''Supplier''': ".$this->SuppObj()->AdminLink_name();
	$out .= "\n* '''CatKey''': $strCatKey";
	$out .= "\n* '''PageKey''': $strPageKey";
	$out .= "\n* '''Shop''': [".$this->URL_Abs().' '.$this->URL_Rel().']';
	$out .= "\n===Titles===";
	$wgOut->addWikiText($out,TRUE);	$out = '';
	$out = $this->TitleListing();
	$wgOut->addWikiText($out,TRUE);	$out = '';

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out); $out = '';
	$wgOut->addWikiText('===Events===',TRUE);
	$out = $this->EventListing();
	$wgOut->addWikiText($out,TRUE);
    }
    public function TitleListing() {
	$out = $this->objDB->Titles_Item()->Listing_forDept($this);
	return $out;
    }
    /*----
      ACTION: Add a list of titles to this department
      INPUT:
	iTitles: array
	  iTitles[catkey] = name
	iEvent: array to be passed to event log
    */
    public function AddTitles(array $iTitles,array $iEvent=NULL) {
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
	    $tblTitles = $this->objDB->Titles();
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
}
