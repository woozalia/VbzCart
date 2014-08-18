<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Titles
    VbzAdminTitles
    VbzAdminTitle
    VCA_Titles_info_Cat - currently disabled (who uses it?)
    VbzAdminTitles_info_Item
    VbzAdminTitle_info_Item
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2013-12-15 Renamed from vbz-mw-title.php to title.php for drop-in system.
*/
class VCTA_Titles extends clsTitles_StoreUI {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng(KS_CLASS_CATALOG_TITLE);
	  $this->ActionKey('title');
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->RenderSearch();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ ACTION ++ //

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
//	$idEvent = $this->objDB->Events()->StartEvent($arEvent);
	$this->StartEvent($arEvent);

      // add the title record
	$arIns = array(
	  'CatKey'	=> SQLValue($iCatKey),
	  'Name'	=> SQLValue($iName),
	  'ID_Dept'	=> $iDept,
	  'DateAdded'	=> 'NOW()'
	  );
	$this->Insert($arIns);
	$idTitle = $this->Engine()->NewID();

      // log the event's completion
	$arUpd = array('id' => $idTitle);
	//$this->Engine()->Events()->FinishEvent($idEvent,$arUpd);
	$this->FinishEvent($arUpd);
	return $idTitle;
    }

    // -- ACTION -- //
    // ++ DATA RECORDS ++ //

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

    // -- DATA RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$sPfx = $this->ActionKey();
	$sSearchName = $sPfx.'-needle';
	$sFilterName = $sPfx.'-filter';
	$sFind = $oPage->ReqArgText($sSearchName);
	$sFilt = $oPage->ReqArgText($sFilterName);
	$doSearch = (!empty($sFind));
	$doFilter = (!empty($sFilt));
	$htFind = '"'.htmlspecialchars($sFind).'"';
	$htFilt = '"'.htmlspecialchars($sFilt).'"';

	// build forms

	$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$htSearchForm = <<<__END__
<form method=post>
  Search for:
  <input name="$sSearchName" size=40 value=$htFind>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	$htFilterHdr = $oPage->SectionHeader('Filter',NULL,'section-header-sub');
	$htFilterForm = <<<__END__
<form method=get>
  Search filter (SQL):<input name="$sFilterName" width=40 value=$htFilt>
  <input type=submit name=btnFilt value="Apply">
</form>
__END__;
/*
	$out = <<<__END__
<table width=100%>
  <tr><th>$htSearchHdr</th><th>$htFilterHdr</th></tr>
  <tr><td>$htSearchForm</td><td>$htFilterForm</td></tr>
</table>
__END__;
*/
	$out = $htSearchHdr.$htSearchForm.$htFilterHdr.$htFilterForm;

	// do the request

	if ($doSearch) {
	    $rs = $this->Search_forText($sFind);
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      .$rs->AdminRows();
	}

	if ($doFilter) {
	    $sqlSort = NULL; // implement later
	    $rs = $this->GetData($sFilt,NULL,$sqlSort);
	    $out .= $oPage->SectionHeader('Filter Results',NULL,'section-header-sub')
	      .$rs->AdminRows();
	}

	return $out;
    }

    // -- ADMIN WEB UI -- //

}
class VCRA_Title extends clsTitle_StoreUI {

    // ++ SETUP ++ //

    protected $frmPage;
    protected function InitVars() {
	parent::InitVars();
	$this->frmPage = NULL;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE: event logging ++ //

    public function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->Engine()->App()->Events());
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

    // ++ BOILERPLATE: admin HTML ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name() {
	return $this->AdminLink($this->Value('Name'));
    }
    public function AdminURL(array $iArgs=NULL) {
	return clsMenuData_helper::_AdminURL($this,$iArgs);
    }

    // -- BOILERPLATE -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ DATA FIELD ACCESS ++ //

    /*----
      RETURNS: Text suitable for use as a title for this Title
      TODO: Rename from Title() to NameFull() or TitleText()
      HISTORY:
	2010-11-19 Created for AdminPage()
    */
    public function Title() {
	return $this->CatNum().' '.$this->Name;
    }
/* duplicates method in shopping UI class
    public function ShopLink($iText) {
	return $this->LinkAbs().$iText.'</a>';
    }
    */
    public function PageTitle() {
	return $this->CatNum('-');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function SuppliersClass() {
	return KS_CLASS_CATALOG_SUPPLIERS;
    }
    protected function DepartmentsClass() {
	return KS_CLASS_CATALOG_DEPARTMENTS;
    }
    protected function ItemsClass() {
	return KS_CLASS_CATALOG_ITEMS;
    }
    protected function StockItemsClass() {
	if (clsDropInManager::ModuleLoaded('vbz.stock')) {
	    return KS_CLASS_STOCK_LINES;
	} else {
	    throw new exception('Cannot access stock functions: "vbz.stock" dropin not loaded.');
	}
    }
    protected function ImagesClass() {
	if (clsDropInManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_IMAGES;
	} else {
	    return 'clsImages_StoreUI';
	}
    }
    protected function TopicsClass() {
	return KS_CLASS_CATALOG_TOPICS;
    }
    protected function XTopicsClass() {
	//return KS_CLASS_CATALOG_TITLE_TOPIC_TOPICS;
	return 'clsTitlesTopics';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function StockItemTable() {
	return $this->Engine()->Make($this->StockItemsClass());
    }
    protected function SCatSourceTable() {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_SOURCES);
    }
    protected function SCatGroupTable() {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_GROUPS);
    }
    protected function SCatTitleTable() {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_TITLES);
    }
    protected function TopicTable($id=NULL) {
	return $this->Engine()->Make($this->TopicsClass(),$id);
    }
    protected function XTopicTable() {
	return $this->Engine()->Make($this->XTopicsClass());
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    protected function TopicRecords() {
    	$tTxT = $this->XTopicTable();
	$sqlXName = $tTxT->NameSQL();
	$sqlTName = $this->TopicTable()->NameSQL();
	$idTitle = $this->KeyValue();

	$sql = "SELECT t.*"
	  ." FROM $sqlTName AS t"
	  ." LEFT JOIN $sqlXName AS tt"
	  ." ON tt.ID_Topic=t.ID"
	  ." WHERE tt.ID_Title=$idTitle"
	  ." ORDER BY t.Sort,t.Name;";
	$rs = $this->TopicTable()->DataSQL($sql);
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ DEPRECATED ++ //

    /*----
      ACTION:
	Return the current title name
	If a value is given, update it to the new value first (returns old value)
	If an event array is given, log the event
      HISTORY:
	2010-11-07 adapted from clsItem::SCatNum()
    */
    public function Name($iVal=NULL,array $iEvent=NULL) {
	throw new exception('Who actually uses this?');

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

    // -- DEPRECATED -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      RETURNS: List of topics as formatted text
      USED BY: catalog entry process (not yet ported)
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
	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	$doSave = $oPage->ReqArgBool('btnSave');

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$out = NULL;

	$isMissing = $this->IsNew();
	if ($isMissing) {
	    throw new exception('When is this actually used?');
	    $strTitle = 'Missing Record';
	    $this->Value('ID',$oPage->ReqArgInt('ID'));
	} else {
	    // items
	    $ftItems = $this->ItemListing();
	    $ftStock = $this->StockListing();
	    $htImages = $this->ImageListing();	// this may update the thumbnails, so do it before showing them
	    $htGroups = $this->CMGrpListing();
	    $wtEvents = $this->EventListing();

	    $objTbl = $this->ImageTable();
	    //$htThumbs = $objTbl->Thumbnails($this->ID);
	    $htThumbs = $this->RenderImages();
	    if (!is_null($htThumbs)) {
		$out .= ('<table align=right><tr><td>'.$htThumbs.'</td></tr></table>');
	    }

	    //$strCatNum = $this->CatNum();
	    //$strCatPage = $this->CatNum('/');

	    $strTitle = 'Title: '.$this->Title();
	}
/*
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';
	$vgOut->AddText($ftSaveStatus);
*/
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	$objSupp = $this->SupplierRecord();
	assert(is_object($objSupp));

	if ($doEdit) {
	    $out .= '<form method=post>';

	    $oForm = $this->PageForm();
	    $ftCatKey = $oForm->RenderControl('CatKey');
	    $ftSuppCN = $oForm->RenderControl('Supplier_CatNum')->Render();
	    $ftSupp = $objSupp->DropDown('ID_Supp');
	    $ftDept = $objSupp->Depts_DropDown('ID_Dept',$this->ID_Dept);
	    $ftName = $oForm->RenderControl('Name');
	    $ftSearch = $oForm->RenderControl('Search');
	    $ftDescr = $oForm->RenderControl('Desc');
	    $ftNotes = $oForm->RenderControl('Notes');
	    $ftWhAdded = $oForm->RenderControl('DateAdded');
	    $ftWhChckd = $oForm->RenderControl('DateChecked');
	    $ftWhUnavl = $oForm->RenderControl('DateUnavail');
	} else {
	    $ftCatKey = htmlspecialchars($this->CatKey);
	    $ftSuppCN = htmlspecialchars($this->Supplier_CatNum);
	    $ftSupp = $objSupp->AdminLink_name();
	    $objDept = $this->DepartmentRecord();
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
	$htID = $this->AdminLink().' ['.$this->ShopLink('shop').']';
	$out .= <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>
  <tr><td align=right><b>Cat Key</b>:</td><td>$ftCatKey</td></tr>
  <tr><td align=right title="supplier catalog #"><b>SC#</b>:</td><td>$ftSuppCN</td></tr>
  <tr><td align=right><b>Supplier</b>:</td><td>$ftSupp</td></tr>
  <tr><td align=right><b>Dept</b>:</td><td>$ftDept</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>
  <tr><td align=right><b>Search</b>:</td><td>$ftSearch</td></tr>
  <tr><td align=right><b>When Added</b>:</td><td>$ftWhAdded</td></tr>
  <tr><td align=right><b>When Checked</b>:</td><td>$ftWhChckd</td></tr>
  <tr><td align=right><b>When Unavailable</b>:</td><td>$ftWhUnavl</td></tr>
</table>
__END__;
	if ($doEdit) {
	    $out .= <<<__END__
<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>
<input type=submit name="btnSave" value="Save">
<input type=submit name="btnCancel" value="Cancel">
<input type=reset value="Reset">
</form>
__END__;
	}

	if (!$isMissing) {	// this might be unnecessary
	    //$oSkin = $oPage->Skin();
	    $htStockLst = $this->StockListing();
	    $htTopicLst = $this->TopicListing();
	    $htItemsHdr = $oPage->SectionHeader('Items',NULL,'section-header-sub');
	    $htImageHdr = $oPage->SectionHeader('Images',NULL,'section-header-sub');
	    $htStockHdr = $oPage->SectionHeader('Stock',NULL,'section-header-sub');
	    $htTopicHdr = $oPage->SectionHeader('Topic',NULL,'section-header-sub');
	    $htEventHdr = $oPage->SectionHeader('Event Log',NULL,'section-header-sub');
	    $out .= <<<__END__
$htItemsHdr
$ftItems
$htStockHdr
$htStockLst
$htImageHdr
$htImages
$htTopicHdr
$htTopicLst
$htGroups
$htEventHdr
$wtEvents
__END__;
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
    */
    protected function PageForm() {
	if (is_null($this->frmPage)) {
	    $frmPage = new clsForm_recs($this);

	    $frmPage->AddField(new clsField('CatKey'),			new clsCtrlHTML(array('size'=>8)));
	    $frmPage->AddField(new clsField('Supplier_CatNum'),	new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsFieldNum('ID_Supp'),		new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldNum('ID_Dept'),		new clsCtrlHTML());
	    $frmPage->AddField(new clsField('Name'),			new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('Search'),			new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('Desc'),			new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('Notes'),			new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('DateAdded'),		new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('DateChecked'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('DateUnavail'),	new clsCtrlHTML());

	    $this->$frmPage = $frmPage;
	}
	return $this->frmPage;
    }
    public function AdminSave() {
	$oPage = $this->Engine()->App()->Page();

	// check input for problems
	$strCatKeyNew = $oPage->ReqArgText('CatKey');
	$strCatKeyOld = $this->CatKey();
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
			$out .= ' '.$objMatch->AdminLink($objMatch->CatKey());
		    }
		}
	    }
	}
	if ($ok) {
	    $sNotes = $oPage->ReqArgText('EvNotes');
	    $out .= $this->PageForm()->Save($sNotes);
	}
	return $out;
    }
    /*----
      ACTION: renders the current recordset for administration
    */
    public function AdminRows($sNone='No titles found.') {
	if ($this->HasRows()) {
	    $out = "\n<table class=listing>";
	    while ($this->NextRow()) {
		$htID = $this->AdminLink();
		$sCatNum = $this->CatNum();
		$sName = $this->NameText();
		// add more info later
		$out .= "\n  <tr><td>$htID</td><td>$sCatNum</td><td>$sName</td></tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out = $sNone;
	}
	return $out;
    }
    public function ItemListing() {
	$out = $this->ItemTable()->Listing_forTitle($this);
	return $out;
    }
    /*----
      PURPOSE: show all stock for the given title
      HISTORY:
	2012-02-03 created
    */
    public function StockListing() {
	$rs = $this->StockItemTable()->Records_forTitle($this->KeyValue());
	$out = $rs->AdminList(array('none'=>'No stock for this title'));
	return $out;
    }
    public function ImageListing() {
	$tImg = $this->ImageTable();
	$id = $this->KeyValue();
	$arArgs = array(
	  'filt'	=> 'ID_Title='.$id,
	  'sort'	=> 'AttrSort,ID',
	  'event.obj'	=> $this,
	  'title.id'	=> $id,
	  'new'		=> TRUE
	  );
	$out = $tImg->AdminPage($arArgs);

//	$objRows = $objTbl->GetData('ID_Title='.$this->ID,NULL,'AttrSort');
//	$out = $objRows->AdminList();
	return $out;
    }
    /*----
      RETURNS: Editable listing of topics for this Title
    */
    protected function TopicListing() {
	$tTxT = $this->XTopicTable();
	$tTopics = $this->TopicTable();
	$rsTopics = $this->TopicRecords();

	$me = $this;
	$arOpts = $tTopics->TopicListing_base_array();
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
	$arOpts['fHandleData_Change_Item'] = function($iVal) use ($me,$tTxT) {
	      $sqlTopic = $iVal;
	      $arIns = array(
		'ID_Title'	=> SQLValue($me->KeyValue()),
		'ID_Topic'	=> $sqlTopic
		);
	      $db = $me->Engine();
	      $db->ClearError();
	      $ok = $tTxT->Insert($arIns);
	      if (!$ok) {
		  $strErr = $db->getError();
		  $out = $sqlTopic.': '.$strErr.' (SQL:'.$tTxT->sqlExec.')';
	      } else {
		  $out = SQLValue($sqlTopic);
	      }
	      return $out;
	  };

	$ctrlList = new clsWidget_ShortList();
	$ctrlList->Options($arOpts);
	$htStatus = $ctrlList->HandleInput();

	$doRmvTopics = clsHTTP::Request()->GetBool('btnRmvTopics');

	// begin output phase
	$out = '';

	if ($doRmvTopics) {
	    $oPage = $this->Engine()->App()->Page();
	    $arTopics = $oPage->ReqArgArray('rmvTitle');
	    $cnt = $tTxT->DelTopics($this->KeyValue(),$arTopics);
	    $out .= 'Removed '.$cnt.' topic'.Pluralize($cnt).':';
	    foreach ($arTopics as $id => $on) {
		$objTopic = $tTopics->GetItem($id);
		$out .= ' '.$objTopic->AdminLink();
	    }
	}
/*
	$htPath = $vgPage->SelfURL();
	$out = "\n<form method=post action=\"$htPath\">";
*/
	$out .= "\n<form method=post>";

	//$rs = $this->Topics();
	if ($rsTopics->HasRows()) {
	    while ($rsTopics->NextRow()) {

		$id = $rsTopics->KeyString();
		$ftName = $rsTopics->AdminLink_name();

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
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$out = $oPage->SectionHeader('Catalog Groups',NULL,'section-header-sub');

	$tblCMT = $this->SCatTitleTable();	// catalog management titles
	$tblCMS = $this->SCatSourceTable();	// catalog management sources
	$tblCMG = $this->SCatGroupTable();	// catalog management groups

	$doEnable = $oPage->ReqArgBool('btnCtgEnable');
	$doDisable = $oPage->ReqArgBool('btnCtgDisable');
	if ($doEnable || $doDisable) {
	    $arChg = $oPage->ReqArgArray('ctg');
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
	    $out .= <<<__END__
<form method=post>
  <table>
    <tr>
      <th>ID</th>
      <th>A?</th>
      <th>Catalog</th>
      <th>Group</th>
      <th>Discontinued</th>
      <th>Grp Code</th>
      <th>Grp Descr</th>
      <th>Grp Sort</th>
      <th>Supp Cat #</th>
      <th>Notes</th>
    </tr>
__END__;
	    while ($rsRows->NextRow()) {
		$isActive = $rsRows->isActive;
		$htActive = clsHTML::fromBool($isActive);

		$objCMSrce = $tblCMS->GetItem($rsRows->SourceID());
		$objCMGrp = $tblCMG->GetItem($rsRows->GroupID());
		if ($objCMSrce->HasRows()) {
		    $htCMSrce = $objCMSrce->AdminLink_name();
		} else {
		    $htCMSrce = '?'.$rsRows->SourceID();
		}
		if ($objCMGrp->HasRows()) {
		    $htCMGrp = $objCMGrp->AdminLink_name();
		} else {
		    $htCMGrp = '?'.$rsRows->GroupID();
		}

		$htID = '<input type=checkbox name="ctg['.$rsRows->KeyValue().']">'.$rsRows->AdminLink();
//		$htSource = $rsRows->SourceID();
//		$htGroup = $rsRows->GroupID();
		$htWhenDiscont = $rsRows->WhenDiscont;
		$htGroupCode = $rsRows->GroupCode;
		$htGroupDescr = $rsRows->GroupDescr;
		$htGroupSort = $rsRows->GroupSort;
		$htSuppCatNum = $rsRows->Supp_CatNum;
		$htNotes = $rsRows->Notes;
		$out .= <<<__END__
    <tr>
	<td>$htID</td>
	<td>$htActive</td>
	<td>$htCMSrce</td>
	<td>$htCMGrp</td>
	<td>$htWhenDiscont</td>
	<td>$htGroupCode</td>
	<td>$htGroupDescr</td>
	<td>$htGroupSort</td>
	<td>$htSuppCatNum</td>
	<td>$htNotes</td>
    </tr>
__END__;
	    }
	    $out .= <<<__END__
  </table>
  <input type=submit name=btnCtgDisable value="Deactivate Selected">
  <input type=submit name=btnCtgEnable value="Activate Selected">
</form>
__END__;
	} else {
	    $out .= 'None found.';
	}
	return $out;
    }

    // -- ADMIN WEB UI -- //
}
