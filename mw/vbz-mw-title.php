<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Titles
    VbzAdminTitles
    VbzAdminTitle
    VbzAdminTitles_info_Cat
    VbzAdminTitles_info_Item
    VbzAdminTitle_info_Item
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class VbzAdminTitles extends clsVbzTitles {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminTitle');
	  $this->ActionKey('title');
    }
    public function Add($iCatKey,$iName,$iDept,$iNotes) {
      // log start of event
	$arEvent = array(
	  'type'	=> clsEvents::kTypeTitle,
	  'id'		=> NULL,
	  'where'	=> __METHOD__,
	  'code'	=> 'add',
	  'descr'	=> 'new title in dept. '.$iDept.': '.$iCatKey.': '.$iName
	  );
	if (!is_null($iNotes)) {
	    $arEvent['notes'] = $iNotes;
	}
	$idEvent = $this->objDB->Events()->StartEvent($arEvent);

      // add the title record
	$arIns = array(
	  'CatKey'	=> SQLValue($iCatKey),
	  'Name'	=> SQLValue($iName),
	  'ID_Dept'	=> $iDept,
	  'DateAdded'	=> 'NOW()'
	  );
	$this->Insert($arIns);
	$idTitle = $this->objDB->NewID(__METHOD__);

      // log the event's completion
	$arUpd = array('id' => $idTitle);
	$this->objDB->Events()->FinishEvent($idEvent,$arUpd);
	return $idTitle;
    }
    /*----
      HISTORY:
	2010-11-15 Changed to use qryTitles_Item_info instead of qryCat_Titles_Item_stats
    */
    public function Data_Imageless() {
	//$sql = 'SELECT t.ID_Title AS ID, t.* FROM `qryCat_Titles_Item_stats` AS t LEFT JOIN `cat_images` AS i ON t.ID_Title=i.ID_Title'
	$sql = 'SELECT * FROM qryTitles_Imageless';
	$this->ClassSng('VbzAdminTitle_info_Item');
	$objRows = $this->DataSQL($sql);
	return $objRows;
    }
}
class VbzAdminTitle extends clsVbzTitle {
    /*----
      HISTORY:
	2010-10-20 changing event logging to use helper class
	2010-11-08 conversion complete: added StartEvent() and FinishEvent(), deleted commented code
    */
    //----
    // BOILERPLATE: event logging
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
    // BOILERPLATE: admin HTML
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name() {
	return $this->AdminLink($this->Value('Name'));
    }
    public function AdminURL(array $iArgs=NULL) {
	return clsAdminData_helper::_AdminURL($this,$iArgs);
    }
    // END BOILERPLATE
    //----
    /*----
      ACTION:
	Return the current title name
	If a value is given, update it to the new value first (returns old value)
	If an event array is given, log the event
      HISTORY:
	2010-11-07 adapted from clsItem::SCatNum()
    */
    public function Name($iVal=NULL,array $iEvent=NULL) {
	$strOld = $this->Name;
	if (!is_null($iVal)) {
	    if (is_array($iEvent)) {
		$iEvent['descr'] = StrCat($iEvent['descr'],'renaming title',': ');
		$iEvent['params'] = nz($iEvent['params']).':old=['.$strOld.']';
		$iEvent['code'] = 'NN';	// new name
		$this->StartEvent($iEvent);
	    }
	    $arUpd = array('Name'=>SQLValue($iVal));
	    $this->Update($arUpd);
	    if (is_array($iEvent)) {
		$this->FinishEvent();
	    }
	}
	return $strOld;
    }
    /*----
      RETURNS: Text suitable for use as a title for this Title
	(Ok, can *you* think of a better method name?)
      HISTORY:
	2010-11-19 Created for AdminPage()
    */
    public function Title() {
	return $this->CatNum().' '.$this->Name;
    }
    public function DeptObj() {
	$objDept = $this->objDB->Depts()->GetItem($this->ID_Dept);
	return $objDept;
    }
    public function ShopLink($iText) {
	return $this->LinkAbs().$iText.'</a>';
    }
    public function PageTitle() {
	return $this->CatNum('-');
    }
    /*----
      RETURNS: List of titles as formatted text
      HISTORY:
	2011-10-01 created for revised catalog entry -- no departments anymore, need more topic info
    */
    public function TopicList_ft($iNone='-') {
	$rcs = $this->Topics();	// recordset of Topics for this Title
	if ($rcs->HasRows()) {
	    $out = '';
	    while ($rcs->NextRow()) {
		$out .= ' '.$rcs->AdminLink();
	    }
	} else {
	    $out = $iNone;
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-02-23 Finally renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

	$vgPage->UseHTML();

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$isMissing = is_null($this->ID);
	if ($isMissing) {
	    $strTitle = 'Missing Record';
	    $this->ID = $vgPage->Arg('ID');
	} else {
	    // items
	    $ftItems = $this->ItemListing();
	    $ftStock = $this->StockListing();
	    $htImages = $this->ImageListing();	// this may update the thumbnails, so do it before showing them
	    $htGroups = $this->CMGrpListing();
	    $wtEvents = $this->EventListing();

	    $objTbl = $this->objDB->Images();
	    $htThumbs = $objTbl->Thumbnails($this->ID);
	    if (!is_null($htThumbs)) {
		$wgOut->AddHTML('<table align=right><tr><td>'.$htThumbs.'</td></tr></table>');
	    }

	    //$strCatNum = $this->CatNum();
	    $strCatPage = $this->CatNum('/');

	    $strTitle = 'Title: '.$this->Title();
	}

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';
	$vgOut->AddText($ftSaveStatus);

	$objSupp = $this->SuppObj();
	assert(is_object($objSupp));

	if ($doEdit) {
	    $out .= $objSection->FormOpen();

	    $ftCatKey = $this->objForm->Ctrl('CatKey')->Render();
	    $ftSuppCN = $this->objForm->Ctrl('Supplier_CatNum')->Render();
	    $ftSupp = $objSupp->DropDown('ID_Supp');
	    $ftDept = $objSupp->Depts_DropDown('ID_Dept',$this->ID_Dept);
	    $ftName = $this->objForm->Ctrl('Name')->Render();
	    $ftSearch = $this->objForm->Ctrl('Search')->Render();
	    $ftDescr = $this->objForm->Ctrl('Desc')->Render();
	    $ftNotes = $this->objForm->Ctrl('Notes')->Render();
	    $ftWhAdded = $this->objForm->Ctrl('DateAdded')->Render();
	    $ftWhChckd = $this->objForm->Ctrl('DateChecked')->Render();
	    $ftWhUnavl = $this->objForm->Ctrl('DateUnavail')->Render();
	} else {
	    $ftCatKey = htmlspecialchars($this->CatKey);
	    $ftSuppCN = htmlspecialchars($this->Supplier_CatNum);
	    $ftSupp = $objSupp->AdminLink_name();
	    $objDept = $this->DeptObj();
	    if (is_object($objDept)) {
		$ftDept = $objDept->AdminLink($objDept->Name);
	    } else {
		$ftDept = 'not set';
	    }
	    $ftName = htmlspecialchars($this->Name);
	    $ftSearch = htmlspecialchars($this->Search);
	    $ftDescr = htmlspecialchars($this->Desc);
	    $ftNotes = htmlspecialchars($this->Notes);
	    $ftWhAdded = $this->DateAdded;
	    $ftWhChckd = $this->DateChecked;
	    $ftWhUnavl = $this->DateUnavail;
	}
	$out .= '<table>';
	$out .= '<tr><td align=right><b>ID</b>:</td><td>'.$this->AdminLink().' ['.$this->ShopLink('shop').']';
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>Cat Key</b>:</td><td>'.$ftCatKey;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right title="supplier catalog #"><b>SC#</b>:</td><td>'.$ftSuppCN;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>Supplier</b>:</td><td>'.$ftSupp;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>Dept</b>:</td><td>'.$ftDept;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>Name</b>:</td><td>'.$ftName;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>Search</b>:</td><td>'.$ftSearch;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>When Added</b>:</td><td>'.$ftWhAdded;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>When Checked</b>:</td><td>'.$ftWhChckd;
	  $out .= '</td></tr>';
	$out .= '<tr><td align=right><b>When Unavailable</b>:</td><td>'.$ftWhUnavl;
	  $out .= '</td></tr>';
	$out .= '</table>';
	if ($doEdit) {
	    $out .= '<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	$wgOut->addHTML($out);	$out = '';

	if (!$isMissing) {
	    $vgPage->UseHTML();

	    $wgOut->addHTML('<table width=100%><tr><td valign=top bgcolor=#eeeeff>');
	    $wgOut->addWikiText('===Items===',TRUE);
	    $vgOut->addText($ftItems);
	    $wgOut->addHTML('</td><td valign=top bgcolor=#eeffee>');
	    $wgOut->addWikiText('===Stock===',TRUE);
	    $wgOut->AddHTML($this->StockListing());
	    $wgOut->addHTML('</td></tr></table>');

	    $wgOut->addHTML($htImages);	// Images (includes header)
	    $wgOut->addWikiText('===Topics===',TRUE);
	    $wgOut->addHTML($this->TopicListing());
	    $wgOut->addHTML($htGroups);	// Catalog Groups
	    $wgOut->addWikiText('===Events===',TRUE);
	    $wgOut->addWikiText($wtEvents,TRUE);
	}
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());
	    //$objCtrls = $objForm;

	    $objForm->AddField(new clsField('CatKey'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsField('Supplier_CatNum'),	new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsFieldNum('ID_Supp'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Dept'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Search'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Desc'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('DateAdded'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('DateChecked'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('DateUnavail'),	new clsCtrlHTML());

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
    }
    public function AdminSave() {
	global $wgRequest;
	global $vgOut;

	// check input for problems
	$strCatKeyNew = $wgRequest->GetText('CatKey');
	$strCatKeyOld = $this->CatKey;
	$ok = TRUE;	// ok to save unless CatKey conflict
	$out = '';
	if ($strCatKeyNew != $strCatKeyOld) {
	    $ok = FALSE; // don't save unless CatKey passes tests
	    // if catkey is being changed, then check new number for duplicates
	    $objSupp = $this->SuppObj();
	    $objMatch = $objSupp->GetTitle_byCatKey($strCatKeyNew,'VbzAdminTitle');
	    if (is_null($objMatch)) {
		$ok = TRUE;
	    } else {
		/*
		  Requested catkey matches an existing title.
		  Look for other titles with the same base catkey (in same supplier),
		    on the theory that this will represent a list of previous renames
		    for this catkey.
		*/
		$objMatch->NextRow();
		$out = 'Your entered CatKey ['.$strCatKeyNew.'] has already been used for '.$objMatch->AdminLink($objMatch->Name);
		$objMatch = $objSupp->GetTitles_byCatKey($strCatKeyOld,'VbzAdminTitle');
		if (!is_null($objMatch)) {
		    // there are some similar entries -- show them:
		    $out .= ' Other similar CatKeys:';
		    while ($objMatch->NextRow()) {
			$out .= ' '.$objMatch->AdminLink($objMatch->CatKey);
		    }
		}
	    }
	}
	if ($ok) {
	    $out .= $this->objForm->Save($wgRequest->GetText('EvNotes'));
	}
	return $out;
    }
    public function ItemListing() {
	$out = $this->objDB->Items()->Listing_forTitle($this);
	return $out;
    }
    /*----
      PURPOSE: show all stock for the given title
      HISTORY:
	2012-02-03 created
    */
    public function StockListing() {
	$rs = $this->Engine()->StkItems()->Data_forTitle($this->KeyValue());
	$out = $rs->AdminList(array('none'=>'No stock for this title'));
	return $out;
    }
    public function ImageListing() {
	$objTbl = $this->objDB->Images();
	$arArgs = array(
	  'filt'	=> 'ID_Title='.$this->ID,
	  'sort'	=> 'AttrSort,ID',
	  'event.obj'	=> $this,
	  'title.id'	=> $this->ID,
	  'new'		=> TRUE
	  );
	$out = $objTbl->AdminPage($arArgs);

//	$objRows = $objTbl->GetData('ID_Title='.$this->ID,NULL,'AttrSort');
//	$out = $objRows->AdminList();
	return $out;
    }
    /*----
      RETURNS: Editable listing of topics for this Title
    */
    protected function TopicListing() {
	global $wgRequest;
	global $vgPage;

	$tblTitleTopics = $this->Engine()->TitleTopics();

	$me = $this;
	$arOpts = $this->Engine()->Topics()->TopicListing_base_array();
	$arOpts['fHandleData_Change_Start'] = function($iText) use ($me) {
	      $arEv = array(
		'descr'	=> 'Adding '.$iText,
		'code'	=> 'topic++',
		'where'	=> __METHOD__
		);
	      $me->StartEvent($arEv);
	  };

	$arOpts['fHandleData_Change_Finish'] = function($iText) use ($me) {
	      $arEv = array(
		'descrfin'	=> $iText
		);
	      $me->FinishEvent($arEv);
	  };
	$arOpts['fHandleData_Change_Item'] = function($iVal) use ($me,$tblTitleTopics) {
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
	  };

	$ctrlList = new clsWidget_ShortList();
	$ctrlList->Options($arOpts);
	$htStatus = $ctrlList->HandleInput();

	$doRmvTopics = $wgRequest->GetBool('btnRmvTopics');

	// begin output phase
	$out = '';

	if ($doRmvTopics) {
	    $arTopics = $wgRequest->GetArray('rmvTitle');
	    $cnt = $tblTitleTopics->DelTopics($this->Value('ID'),$arTopics);
	    $out .= 'Removed '.$cnt.' topic'.Pluralize($cnt).':';
	    foreach ($arTopics as $id => $on) {
		$objTopic = $tblTopics->GetItem($id);
		$out .= ' '.$objTopic->AdminLink();
	    }
	}
/*
	$htPath = $vgPage->SelfURL();
	$out = "\n<form method=post action=\"$htPath\">";
*/
	$out .= "\n<form method=post>";

	$rs = $this->Topics();
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {

		$id = $rs->KeyString();
		$ftName = $rs->AdminLink_name();

		$out .= "\n[<input type=checkbox name=\"rmvTitle[$id]\">$ftName ]";
	    }
	    $out .= '<br><input type=submit name="btnRmvTopics" value="Remove Checked">';
	} else {
	    $out .= '<i>None found.</i>';
	}
/*
	$out .= '<input type=submit name="btnAddTopics" value="Add These:">';
	$out .= '<input size=40 name=txtNewTitles> (IDs separated by spaces)';
*/
	$out .= '<br>'.$htStatus;
	$out .= $ctrlList->RenderForm_Entry();

	$out .= '</form>';
	return $out;
    }
    /*----
      RETURNS: Listing of CM (catalog management) groups for this title
      HISTORY:
	2011-02-06 added controls to allow deactivating/activating selected rows
    */
    protected function CMGrpListing() {
	global $wgRequest;
	global $vgOut;

	$out = $vgOut->Header('Catalog Groups',3);

	$tblCMT = $this->objDB->CtgTitles();	// catalog management titles
	$tblCMS = $this->objDB->CtgSrcs();	// catalog management sources
	$tblCMG = $this->objDB->CtgGrps();	// catalog management groups

	$doEnable = $wgRequest->GetBool('btnCtgEnable');
	$doDisable = $wgRequest->GetBool('btnCtgDisable');
	if ($doEnable || $doDisable) {
	    $arChg = $wgRequest->GetArray('ctg');
	    $out .= $doEnable?'Activating':'Deactivating';
	    foreach ($arChg as $id => $on) {
		$out .= ' '.$id;
		$arUpd = array(
		  'isActive'	=> SQLValue($doEnable)
		  );
		$tblCMT->Update($arUpd,'ID='.$id);
	    }
	}

	$rsRows = $tblCMT->GetData('ID_Title='.$this->ID);
	if ($rsRows->HasRows()) {
	    $out .= '<form method=post>';
	    $out .= $vgOut->TableOpen();
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	    $out .= $vgOut->TblCell('ID');
	    $out .= $vgOut->TblCell('A?');
	    $out .= $vgOut->TblCell('Catalog');
	    $out .= $vgOut->TblCell('Group');
	    $out .= $vgOut->TblCell('Discontinued');
	    $out .= $vgOut->TblCell('Grp Code');
	    $out .= $vgOut->TblCell('Grp Descr');
	    $out .= $vgOut->TblCell('Grp Sort');
	    $out .= $vgOut->TblCell('Supp Cat #');
	    $out .= $vgOut->TblCell('Notes');
	    $out .= $vgOut->TblRowShut();
	    while ($rsRows->NextRow()) {
		$isActive = $rsRows->isActive;
		$htActive = $isActive?'&radic;':'-';

		$objCMSrce = $tblCMS->GetItem($rsRows->ID_Source);
		$objCMGrp = $tblCMG->GetItem($rsRows->ID_Group);
		if ($objCMSrce->HasRows()) {
		    $htCMSrce = $objCMSrce->AdminLink_name();
		} else {
		    $htCMSrce = '?'.$rsRows->ID_Source;
		}
		if ($objCMGrp->HasRows()) {
		    $htCMGrp = $objCMGrp->AdminLink_name();
		} else {
		    $htCMGrp = '?'.$rsRows->ID_Group;
		}

		$out .= $vgOut->TblRowOpen();
		$htID = '<input type=checkbox name="ctg['.$rsRows->KeyValue().']">'.$rsRows->AdminLink();
		$out .= $vgOut->TblCell($htID);
		$out .= $vgOut->TblCell($htActive);
		$out .= $vgOut->TblCell($htCMSrce);
		$out .= $vgOut->TblCell($htCMGrp);
//		$out .= $vgOut->TblCell($rsRows->ID_Source);
//		$out .= $vgOut->TblCell($rsRows->ID_Group);

		$out .= $vgOut->TblCell($rsRows->WhenDiscont);
		$out .= $vgOut->TblCell($rsRows->GroupCode);
		$out .= $vgOut->TblCell($rsRows->GroupDescr);
		$out .= $vgOut->TblCell($rsRows->GroupSort);
		$out .= $vgOut->TblCell($rsRows->Supp_CatNum);
		$out .= $vgOut->TblCell($rsRows->Notes);
		$out .= $vgOut->TblRowShut();
	    }
	    $out .= $vgOut->TableShut();
	    $out .= '<input type=submit name=btnCtgDisable value="Deactivate Selected">';
	    $out .= '<input type=submit name=btnCtgEnable value="Activate Selected">';
	    $out .= '</form>';
	} else {
	    $out .= 'None found.';
	}
	return $out;
    }
}
/*====
  PURPOSE: VbzAdminTitles with additional catalog information
*/
class VbzAdminTitles_info_Cat extends VbzAdminTitles {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryCat_Titles');
    }
    public function Search_forText_SQL($iFind) {
	$sqlFind = '"%'.$iFind.'%"';
	return "(Name LIKE $sqlFind) OR (Descr LIKE $sqlFind) OR (Search LIKE $sqlFind) OR (CatNum LIKE $sqlFind)";
    }
    public function SearchPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strThis = 'SearchCatalog';

	$strForm = $wgRequest->GetText('form');
	$doForm = ($strForm == $strThis);

	$strField = 'txtSearch-'.$strThis;
	$strFind = $wgRequest->GetText($strField);
	$htFind = '"'.htmlspecialchars($strFind).'"';

	$vgPage->UseHTML();
	$out = "\n<h2>Catalog Search</h2>"
	  ."\n<form method=post>"
	  .'Search for:'
	  ."\n<input name=$strField size=40 value=$htFind>"
	  ."\n<input type=hidden name=form value=$strThis>"
	  ."\n<input type=submit name=btnSearch value=Go>"
	  ."\n</form>";
	$wgOut->AddHTML($out); $out = '';

	if ($doForm && !empty($strFind)) {
	    $tblTitles = $this;
	    $tblItems = $this->Engine()->Items();
	    $tblImgs = $this->Engine()->Images();

	    $arTitles = NULL;

	    $rs = $tblTitles->Search_forText($strFind);
	    if ($rs->HasRows()) {
		while ($rs->NextRow()) {
		    $id = $rs->ID;
		    $arTitles[$id] = $rs->Values();
		}
	    }

	    $out .= "<br><b>Searching catalog for</b> $htFind:<br>";
	    $wgOut->AddHTML($out); $out = '';

	    $rs = $tblItems->Search_byCatNum($strFind);
	    if (is_object($rs)) {
		while ($rs->NextRow()) {
		    $id = $rs->Value('ID_Title');
		    if (!isset($arTitles[$id])) {
			$obj = $tblTitles->GetItem($id);
			$arTitles[$id] = $obj->Values();
		    }
		}
	    }

	    if (!is_null($arTitles)) {
		if (empty($obj)) {
		    $obj = $tblTitles->SpawnItem();
		}
		$out .= '<table align=left style="border: solid black 1px;"><tr><td>';
		$ftImgs = '';
		$isFirst = TRUE;
		foreach ($arTitles as $id => $row) {
		    $obj->Values($row);
		    $txtCatNum = $obj->CatNum();
		    $txtName = $obj->Value('Name');
		    if ($isFirst) {
			$isFirst = FALSE;
		    } else {
			$out .= "\n<br>";
		    }
		    $htLink = $obj->AdminLink($txtCatNum);
		    $out .= $htLink.' '.$txtName;

		    $txtTitle = $txtCatNum.' &ldquo;'.htmlspecialchars($txtName).'&rdquo;';
		    $ftImg = $tblImgs->Thumbnails($id,array('title'=>$txtTitle));
		    $ftImgs .= '<a href="'.$obj->AdminURL().'">'.$ftImg.'</a>';
		}
		$out .= '</td></tr></table>'.$ftImgs;
	    }

	    $wgOut->AddHTML($out); $out = '';
	}

	return $out;
    }
}
/*====
  PURPOSE: VbzAdminTitles with additional item (and stock) information
*/
class VbzAdminTitles_info_Item extends VbzAdminTitles {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryTitles_Item_info');
	  $this->ClassSng('VbzAdminTitle_info_Item');
    }
    public function Listing_forDept($iDeptObj) {
	global $wgOut;
	global $vgPage;
	global $sql;

	$vgPage->UseHTML();

	$objDept = $iDeptObj;
	$idDept = $objDept->ID;
//	$strSuppKey = strtolower($objSupp->CatKey);
//	$objRecs = $this->GetData('ID_Dept='.$idDept,'VbzAdminTitle','CatKey');
	//$objRecs = $this->DataSQL('SELECT t.ID_Title AS ID, t.* FROM qryCat_Titles_Item_stats AS t WHERE t.ID_Dept='.$idDept);
	$objRecs = $this->GetData('ID_Dept='.$idDept,NULL,'CatKey');

	$out = $objRecs->AdminList();
	$wgOut->addHTML($out);
    }
}
class VbzAdminTitle_info_Item extends VbzAdminTitle {
    protected $arBins;
    /*----
      ACTION: Renders a summary of dataset by location
    */
    public function AdminSummary() {
	$out = NULL;
	$arBins = NULL;
	$arTitleBins = NULL;
	if ($this->HasRows()) {
	    while ($this->NextRow()) {	// titles
		$arTitle = NULL;
		$rsItems = $this->Items();
		while ($rsItems->NextRow()) {	// items
		    $rsStock = $rsItems->Data_forStkItems();
		    if ($rsStock->HasRows()) {
			while ($rsStock->NextRow()) {	// stock items
			    if ($rsStock->Qty_inStock() > 0) {
				$idBin = $rsStock->Value('ID_Bin');
				$arBins[$idBin] = NzArray($arBins,$idBin)+1;
				$arTitle[$idBin] = NzArray($arTitle,$idBin)+1;
			    }
			}	// /stock items
		    }
		}	// /items
		$idTitle = $this->KeyValue();
		$arThis = NzArray_debug($arTitleBins,$idTitle);
		if (is_array($arThis)) {
		    $arTitleBins[$idTitle] = array_merge($arTitle,$arThis);
		} else {
		    if (is_array($arTitle)) {
			$arTitleBins[$idTitle] = $arTitle;
		    }
		}
	    }	// /titles
	}
	$this->StartRows();	// rewind the main recordset so it can be used again

	$arPlaces = NULL;
	foreach ($arBins as $idBin => $cnt) {
	    $rcBin = $this->Engine()->Bins($idBin);
	    if ($rcBin->IsRelevant()) {
		$idPlace = $rcBin->PlaceID();
		$arPlaces[$idPlace][$idBin] = $arBins[$idBin];
	    }
	}

	$out .= "\n<table>";
	foreach ($arPlaces as $idPlace => $arBins) {
	    $rcPlace = $this->Engine()->Places($idPlace);
	    $out .= "\n<tr><td align=right>".$rcPlace->AdminLink_name().':</td><td>';
	    foreach ($arBins as $idBin => $cnt) {
		$rcBin = $this->Engine()->Bins($idBin);
		$out .= ' '.$rcBin->AdminLink_name().':'.$cnt;
	    }
	    $out .= "</td></tr>";
	}
	$out .= "\n</table>";
//$out .= 'ARBINS:<pre>'.print_r($arTitleBins,TRUE).'</pre>';
	$this->arBins = $arTitleBins;	// save bins-for-titles data
	return $out;
    }
    /*----
      RETURNS: listing of titles in the current dataset
      HISTORY:
	2010-11-16 "in print" column now showing cntInPrint instead of cntForSale
    */
    public function AdminList(array $iarArgs=NULL) {
	$objRecs = $this;

	if ($objRecs->HasRows()) {
	    if (empty($this->arBins)) {
		$htBinHdr = NULL;
		$doBins = FALSE;
	    } else {
		$htBinHdr = '<th>Bins</th>';
		$doBins = TRUE;
	    }
	    $out = "\n<table class=sortable>"
	      ."\n<tr>"
		.'<th>ID</th>'
		.'<th>Name</th>'
		.'<th>Cat #</th>'
		.'<th><small>CatKey</small></th>'
		.'<th>SCat#</th>'
		.'<th>When Added</th>'
		.'<th title="number of item records"><small>item<br>recs</small></th>'
		.'<th title="number of items in print"><small>items<br>in print</small></th>'
		.'<th>stk<br>qty</th>'
		.$htBinHdr
	      .'</tr>';
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$ftID = $objRecs->AdminLink();
		$ftName = $objRecs->Name;
		$ftCatNum = $objRecs->CatNum();
		$ftCatKey = $objRecs->Row['CatKey'];
		$ftSCatNum = $objRecs->Row['Supplier_CatNum'];
		$ftWhen = $objRecs->DateAdded;

		// FUTURE: If we're using this on a dataset which does not have these fields,
		//	test for them and then retrieve them the slow way if not found.
		$qtyStk = $objRecs->qtyForSale;
		//$cntAva = $objRecs->cntForSale;
		$cntPrn = $objRecs->cntInPrint;
		$cntItm = $objRecs->cntItems;

		$isActive = (($qtyStk > 0) || ($cntPrn > 0));
		$isPassive = (nz($cntItm) == 0);

		$isOdd = !$isOdd;
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';

		if ($isActive) {
		    $wtStyle .= ' font-weight: bold;';
		}
		if ($isPassive) {
		    $wtStyle .= ' color: #888888;';
		}

		$htBins = NULL;
		$htBinCell = NULL;
		if ($doBins) {
		    $id = $this->KeyValue();
		    if (array_key_exists($id,$this->arBins)) {
			$arBins = $this->arBins[$id];
			foreach ($arBins as $idBin => $cnt) {
			    $rcBin = $this->Engine()->Bins($idBin);
			    if ($rcBin->IsRelevant()) {
				$htBins .= ' '.$rcBin->AdminLink_name().'('.$cnt.')';
			    }
			}
			$htBinCell = "<td>$htBins</td>";
		    }
		}

		$out .= "\n<tr style=\"$wtStyle\">"
		  ."<td>$ftID</td>"
		  ."<td>$ftName</td>"
		  ."<td>$ftCatNum</td>"
		  ."<td>$ftCatKey</td>"
		  ."<td>$ftSCatNum</td>"
		  ."<td>$ftWhen</td>"
		  ."<td align=right>$cntItm</td>"
		  ."<td align=right>$cntPrn</td>"
		  ."<td align=right>$qtyStk</td>"
		  .$htBinCell
		  .'</tr>';
	    }
	    $out .= "\n</table>";
	} else {
	    if (isset($iarArgs['none'])) {
		$out .= $iarArgs['none'];
	    } else {
		$out .= 'No titles found.';
	    }
	}
	return $out;
    }
}
