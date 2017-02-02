<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Titles
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2013-12-15 Renamed from vbz-mw-title.php to title.php for drop-in system.
*/
class VCTA_Titles extends vctShopTitles {
    use ftLinkableTable;
    use ftLoggableTable;

    // ++ SETUP ++ //
/*
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng(KS_CLASS_CATALOG_TITLE);
	  $this->ActionKey(KS_ACTION_CATALOG_TITLE);
    } */

    // ++ CEMENT ++ //
    
    protected function GetActionKey() {
	return KS_ACTION_CATALOG_TITLE;
    }
    
    // -- SETUP -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminPage();
    }

    // -- CALLBACKS -- //
    // ++ ACTION ++ //

    /*----
      EVENT: does NOT log (caller should do it)
    */
    public function Add($sCatKey,$sName,$sNotes,array $arAdd) {
      // add the title record
	$db = $this->Engine();
	$arIns = array(
	  'CatKey'	=> $db->SanitizeAndQuote(strtoupper($sCatKey)),
	  'Name'	=> $db->SanitizeAndQuote($sName),
	  'DateAdded'	=> 'NOW()'
	  );
	$arIns = clsArray::Merge($arIns,$arAdd);
	$this->Insert($arIns);
	$idTitle = $this->Engine()->NewID();

	return $idTitle;
    }

    // -- ACTION -- //
    // ++ QUERIES ++ //
    
    protected function MeInfoQuery() {
	return $this->Engine()->Make('vcqtaTitlesInfo');
    }
    protected function ImageInfoQuery() {
	return $this->Engine()->Make('vcqtImagesInfo');
    }

    // -- QUERIES -- //
    // ++ RECORDS ++ //

    /*----
      HISTORY:
	2010-11-15 Changed to use qryTitles_Item_info instead of qryCat_Titles_Item_stats
    *//* 2016-03-05 This is almost certainly obsolete, replaced by Records_Active_noImage().
    public function Data_Imageless() {
	//$sql = 'SELECT t.ID_Title AS ID, t.* FROM `qryCat_Titles_Item_stats` AS t LEFT JOIN `cat_images` AS i ON t.ID_Title=i.ID_Title'
	$sql = 'SELECT * FROM qryTitles_Imageless';
	$this->ClassSng('VbzAdminTitle_info_Item');
	$objRows = $this->DataSQL($sql);
	return $objRows;
    }//*/
    /*----
      TODO:
	* should probably include the Supplier CatKey as well, via JOIN
	* This is being displayed with full catalog #s -- so there is probably
	  a duplicate lookup going on somewhere. That should be eliminated.
    */
    public function GetData_forDropDown($sqlFilt=NULL) {
	$sqlTbl = $this->NameSQL();
	$sqlFC = is_null($sqlFilt)?NULL:" WHERE $sqlFilt";
	$sql = "SELECT t.ID, CONCAT_WS(' ',t.CatKey,t.Name) AS Text"
	  .", t.Name"
	  .", t.CatKey"
	  .", ID_Supp"
	  .", ID_Dept"
	  ." FROM $sqlTbl AS t JOIN cat_depts AS d ON t.ID_Dept=d.ID"
	  .$sqlFC
	  .' ORDER BY d.CatKey,t.CatKey'
	  ;
	$rs = $this->DataSQL($sql);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    protected function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$sPfx = $this->ActionKey();
	$sSearchName = $sPfx.'-needle';
	$sFilterName = $sPfx.'-filter';
	$sFind = $oPage->ReqArgText($sSearchName);
	$sFilt = $oPage->ReqArgText($sFilterName);
	$doSearch = (!empty($sFind));
	$doFilter = (!empty($sFilt));
	$htFind = '"'.fcString::EncodeForHTML($sFind).'"';
	$htFilt = '"'.fcString::EncodeForHTML($sFilt).'"';
	
	$arMenu = array(
	  new clsAction_section('Show'),
	  new clsActionLink_option(
	    array(),
	    'no-image',	// link key (value)
	    'show',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'show active titles that have no images'	// pop-up description
	    )
	  );
	$oPage->PageHeaderWidgets($arMenu);
	
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
	    $sql = $rs->sqlMake;
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      ."<span class=line-stats><b>SQL</b>: $sql</span><br>"
	      .$rs->AdminRows();
	}

	if ($doFilter) {
	    $sqlSort = NULL; // implement later
	    $rs = $this->GetData($sFilt,NULL,$sqlSort);
	    $out .= $oPage->SectionHeader('Filter Results',NULL,'section-header-sub')
	      .$rs->AdminRows();
	}
	$sShow =$oPage->PathArg('show');
	if ($sShow =='no-image') {
	    $out .= $this->MeInfoQuery()->RenderRows_Active_noImage();
	}

	return $out;
    }

    // -- ADMIN WEB UI -- //

}
class VCRA_Title extends vcrShopTitle {
    use ftLoggableRecord;
    use ftLinkableRecord;

    // ++ SETUP ++ //

//    protected function InitVars() {
//	parent::InitVars();
//    }

    // -- SETUP -- //
    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name() {
	//return $this->SelfLink($this->Value('Name'));
	return $this->SelfLink($this->NameFull());
    }

    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    // CALLBACK for dropdown list in non-edit mode
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    // CALLBACK for dropdown list in non-edit mode
    public function ListItem_Text() {
	return $this->NameFull();
    }

    // -- CALLBACKS -- //
    // ++ FIELD VALUES ++ //
    
    // apparently everything useful is defined in the parent class
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++  //
    
    protected function HasSupplier() {
	return !is_null($this->SupplierID());
    }
    /*----
      RETURNS: Text suitable for use as a title for this Title
      TODO: DEPRECATE
      HISTORY:
	2010-11-19 Created for AdminPage()
    */
    public function Title() {
	return $this->NameFull();
    }
    public function NameFull() {
	return $this->CatNum().' '.$this->NameString();
    }
    public function PageTitle() {
	return $this->CatNum();
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function SuppliersClass() {
	return KS_CLASS_CATALOG_SUPPLIERS;
    }
    protected function DepartmentsClass() {
	return KS_CLASS_CATALOG_DEPARTMENTS;
    }
    protected function ItemsClass() {
	return KS_ADMIN_CLASS_LC_ITEMS;
    }
    protected function StockItemsClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.stock')) {
	    return KS_CLASS_STOCK_LINES;
	} else {
	    throw new exception('Cannot access stock functions: "vbz.stock" dropin not loaded.');
	}
    }
    protected function ImagesClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_IMAGES;
	} else {
	    return 'clsImages_StoreUI';
	}
    }
    protected function TopicsClass() {
	return KS_CLASS_CATALOG_TOPICS;
    }
    protected function XTopicsClass() {
	return KS_CLASS_CATALOG_TITLES_TOPICS;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

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

    // -- TABLES -- //
    // ++ RECORDS ++ //

    protected function TopicRecords() {
    	$tTxT = $this->XTopicTable();
	$sqlXName = $tTxT->NameSQL();
	$sqlTName = $this->TopicTable()->NameSQL();
	$idTitle = $this->GetKeyValue();

	$sql = "SELECT t.*"
	  ." FROM $sqlTName AS t"
	  ." LEFT JOIN $sqlXName AS tt"
	  ." ON tt.ID_Topic=t.ID"
	  ." WHERE tt.ID_Title=$idTitle"
	  ." ORDER BY t.Sort,t.Name;";
	$rs = $this->TopicTable()->DataSQL($sql);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      RETURNS: List of topics as formatted text
      USED BY: catalog entry process (not yet ported)
      HISTORY:
	2011-10-01 created for revised catalog entry -- no departments anymore, need more topic info
    */
    public function TopicList_ft($iNone='-') {
	$rs = $this->Topics();	// recordset of Topics for this Title
	if ($rs->HasRows()) {
	    $out = '';
	    while ($rs->NextRow()) {
		$out .= ' '.$rs->SelfLink();
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
	$doSave = $oPage->ReqArgBool('btnSave')
	  && ($oPage->ReqArgText('@form-for') == 'title')
	  ;

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $sMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsg);
	}
	
	$out = NULL;

	$isMissing = $this->IsNew();
	if ($isMissing) {
	    // defective data -- title record does not exist
	    $strTitle = 'Missing Record';
	    $this->Value('ID',$oPage->ReqArgInt('ID'));
	    $ftLists = NULL;
	} else {
	    // subsidiary listings
	    
	    $ftLists = 
	      $this->ItemListing()
	      .$this->StockListing()
	      .$this->ImageListing()	// this may update the thumbnails, so do it before showing them
	      .$this->CMGrpListing()
	      .$this->TopicListing()
	      .$this->EventListing()
	      ;
	    
	    $ftThumbs = $this->RenderImages_forRows($this->Title());
	    if (!is_null($ftThumbs)) {
		$out .= ('<table align=right><tr><td>'.$ftThumbs.'</td></tr></table>');
	    }

	    $oPage->Skin()->SetPageTitle('Title: '.$this->Title());
	}

	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	// build and render the form:
	
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$sID = $this->SelfLink();
	if ($this->IsNew()) {
	    $sID .= ' - no record!';
	} else {
	    $sID .= ' ['.$this->ShopLink('shop').']';
	}
	$arCtrls['!ID'] = $sID;

	if ($doEdit) {
	    $out .= "\n<form method=post>\n<input type=hidden name='@form-for' value='title'>";
	}
	    
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {
	    $out .= "\n<input type=submit name=btnSave value='Save'>\n</form>";
	}
	$out .= $ftLists;
	$out .= '<hr><span class=footer-stats>generated by '.__FILE__.' line '.__LINE__.'</span>';
	
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>[[!ID]]</td></tr>
  <tr><td align=right><b>Cat Key</b>:</td><td>[[CatKey]]</td></tr>
  <tr><td align=right title="supplier catalog #"><b>SC#</b>:</td><td>[[Supplier_CatNum]]</td></tr>
  <tr><td align=right><b>Supplier</b>:</td><td>[[ID_Supp]]</td></tr>
  <tr><td align=right><b>Dept</b>:</td><td>[[ID_Dept]]</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>[[Name]]</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>[[Desc]]</td></tr>
  <tr><td align=right><b>Search Text</b>:</td><td>[[Search]]</td></tr>
  <tr><td align=right><b>When Added</b>:</td><td>[[DateAdded]]</td></tr>
  <tr><td align=right><b>When Checked</b>:</td><td>[[DateChecked]]</td></tr>
  <tr><td align=right><b>When Unavailable</b>:</td><td>[[DateUnavail]]</td></tr>
  <tr><td colspan=2><b>Notes</b>: [[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
    */
    private $frmPage;
    protected function PageForm() {
	if (is_null($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Text($oForm,'CatKey');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>10));
		
	      $oField = new fcFormField_Text($oForm,'Supplier_CatNum');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>10));
		
	      $oField = new fcFormField_Num($oForm,'ID_Supp');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->SupplierTable()->AllRecords());
		
	      $oField = new fcFormField_Num($oForm,'ID_Dept');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		if ($this->IsNew()) {
		    $sqlFilt = NULL;
		} else {
		    $sqlFilt = 'ID_Supplier='.$this->SupplierID();
		}
		$oCtrl->Records($this->DepartmentTable()->GetData_forDropDown($sqlFilt));
		
	      $oField = new fcFormField_Text($oForm,'Name');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));
	      $oField = new fcFormField_Text($oForm,'Search');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>10));
	      $oField = new fcFormField_Text($oForm,'Desc');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>60));

	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));

	      $oField = new fcFormField_Time($oForm,'DateAdded');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>14));
	      $oField = new fcFormField_Time($oForm,'DateChecked');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>14));
	      $oField = new fcFormField_Time($oForm,'DateUnavail');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>14));

	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    public function AdminSave() {
	$oPage = $this->Engine()->App()->Page();

	// check input for problems
	$sCatKeyNew = $oPage->ReqArgText('CatKey');
	$sCatKeyOld = $this->CatKey();
	$ok = TRUE;	// ok to save unless CatKey conflict
	$out = '';
	if ($sCatKeyNew != $sCatKeyOld) {
	    $ok = FALSE; // don't save unless CatKey passes tests
	    // if catkey is being changed, then check new number for duplicates
	    $rcSupp = $this->SupplierRecord();
	    $rcMatch = $rcSupp->GetTitle_byCatKey($sCatKeyNew,'VbzAdminTitle');
	    if (is_null($rcMatch)) {
		$ok = TRUE;
	    } else {
		/*
		  Requested catkey matches an existing title.
		  Look for other titles with the same base catkey (in same supplier),
		    on the theory that this will represent a list of previous renames
		    for this catkey.
		*/
		$rcMatch->NextRow();
		$ftMatchName = $rcMatch->AdminLink($rcMatch->NameString());
		$out = "Your entered CatKey [$sCatKeyNew] has already been used for $ftMatchName";
		$rcMatch = $rcSupp->GetTitles_byCatKey($sCatKeyOld,'VbzAdminTitle');
		if (!is_null($rcMatch)) {
		    // there are some similar entries -- show them:
		    $out .= ' Other similar CatKeys:';
		    while ($rcMatch->NextRow()) {
			$out .= ' '.$rcMatch->AdminLink($rcMatch->CatKey());
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
    public function AdminRows(array $arOpts=NULL) {
	if ($this->HasRows()) {
	    $sHdr = clsArray::Nz($arOpts,'disp.hdr');
	    $htHdr = is_null($sHdr)?NULL:"<tr><td colspan=3>$sHdr</td></tr>";
	    $out = "\n<table class=listing>$htHdr";
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$htID = $this->SelfLink();
		$sCatNum = $this->CatNum();
		$sName = $this->NameText();
		$sCSS = $isOdd?'odd':'even';
		$isOdd = !$isOdd;
		// add more info later
		$out .= "\n  <tr class=$sCSS><td>$htID</td><td>$sCatNum</td><td>$sName</td></tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $sNone = clsArray::Nz($arOpts,'disp.none','no titles found');
	    $out = $sNone;
	}
	return $out;
    }
    public function ItemListing() {
	$idTitle = $this->GetKeyValue();
    
	// check for user input
	$oPage = $this->Engine()->App()->Page();
	$sAdd = $oPage->PathArg('add');
	$doUpd = $oPage->PathArg('item') == 'upd';
	$doForm = $doUpd;	// might be other reasons we'd need a form
	
	if (clsHTTP::Request()->GetBool('btnUpd')) {
	    $rs = $this->ItemTable()->Records_forTitle($idTitle);
	    // checkbox array name will be value of $arOpt['chkname']
	    $arChkd = clsHTTP::Request()->GetArray('item');
	    $out = $rs->UpdateCatSpecs($arChkd);
	    $this->SelfRedirect(NULL,$out);
	}
	
	if ($sAdd == 'item') {
	    $rcItem = $this->ItemTable()->SpawnItem();
	    $rcItem->TitleID($this->GetKeyValue());
	    $rcItem->SupplierID($this->SupplierID());
	    $htForm = $rcItem->AdminPage(
	      array(
		'url.return'	=> $this->SelfURL(),
		'id.title'	=> $idTitle
		)
	      );
	} else {
	    if ($doForm) {
		$htForm = "\n<form method=post>";
	    } else {
		$htForm = NULL;
	    }
	}
    
	// set up the section menu
	$arActs = array(
	  new clsActionLink_option(array(),
	    'item',		// link key
	    'add',		// group key
	    'add',		// display when off
	    'cancel',		// display when on
	    'add an item'	// description (shows as hover-over text)
	    ),
	  //new clsAction_section('update'),
	  new clsActionLink_option(array(),
	    'upd',		// link key
	    'item',		// group key
	    'update',		// display when off
	    NULL,		// display when on
	    'update specs for selected items'	// description (shows as hover-over text)
	    ),
	  );
	// render the section header and listing
	if ($doUpd) {
	  $arOpt = array(
	    'dochk'=>TRUE,
	    'chkname'=>'item'
	    );
	} else { $arOpt = NULL; }
	$out =
	  $htForm	// new item form, if any
	  .$oPage->ActionHeader('Items',$arActs)
	  .$this->ItemTable()->Listing_forTitle($idTitle,$arOpt)
	  .($doUpd?"\n<input type=submit name=btnUpd value='Update'>":NULL)
	  .($doForm?"\n</form>":NULL)
	  ;
	return $out;
    }
    /*----
      PURPOSE: show all stock for the given title
      HISTORY:
	2012-02-03 created
    */
    public function StockListing() {
	$rs = $this->StockItemTable()->Records_forTitle($this->GetKeyValue());
	$out =
	  $this->Engine()->App()->Page()->ActionHeader('Stock')
	  .$rs->AdminList(array('none'=>'There is no stock for this title.'));
	return $out;
    }
    public function ImageListing() {
	$tImg = $this->ImageTable();
	$id = $this->GetKeyValue();
	$arActs = array(
	  new clsActionLink_option(array(),
	    $tImg->ActionKey(),	// link key
	    'add',		// group key
	    'add',		// display when off
	    'cancel',		// display when on
	    'add an image'	// description (shows as hover-over text)
	    ),
	  );
	$arArgs = array(
	  'filt'	=> 'ID_Title='.$id,
	  'sort'	=> 'AttrSort,ID',
	  'event.obj'	=> $this,
	  'title.id'	=> $id,
	  'new'		=> TRUE
	  );
	$out = 
	  $this->Engine()->App()->Page()->ActionHeader('Images',$arActs)
	  .$tImg->AdminPage($arArgs);
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
	      //$me->FinishEvent($arEv);	// gives an error. TODO: fix event logging
	  };
	$arOpts['fHandleData_Change_Item'] = function($iVal) use ($me,$tTxT) {
	      $db = $me->Engine();

	      $sqlTopic = $iVal;
	      $arIns = array(
		'ID_Title'	=> $db->SanitizeAndQuote($me->GetKeyValue()),
		'ID_Topic'	=> $sqlTopic
		);
	      $db->ClearError();
	      $ok = $tTxT->Insert($arIns);
	      if (!$ok) {
		  $strErr = $db->getError();
		  $out = $sqlTopic.': '.$strErr.' (SQL:'.$tTxT->sqlExec.')';
	      } else {
		  $out = $db->SanitizeAndQuote($sqlTopic);
	      }
	      return $out;
	  };

	$ctrlList = new clsWidget_ShortList();
	$ctrlList->Options($arOpts);
	$htStatus = $ctrlList->HandleInput();

	$doRmvTopics = clsHTTP::Request()->GetBool('btnRmvTopics');

	// begin output phase
	$oPage = $this->Engine()->App()->Page();
	$out = $oPage->ActionHeader('Topics');

	if ($doRmvTopics) {
	    $arTopics = $oPage->ReqArgArray('rmvTitle');
	    $cnt = $tTxT->DelTopics($this->GetKeyValue(),$arTopics);
	    $out .= 'Removed '.$cnt.' topic'.fcString::Pluralize($cnt).':';
	    foreach ($arTopics as $id => $on) {
		$rcTopic = $tTopics->GetItem($id);
		$out .= ' '.$rcTopic->SelfLink();
	    }
	    $this->SelfRedirect(NULL,$out);
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
		$ftName = $rsTopics->SelfLink_name();

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

	$out = $this->Engine()->App()->Page()->ActionHeader('Catalog Groups');

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

	$rsRows = $tblCMT->GetData('ID_Title='.$this->GetKeyValue());
	if ($rsRows->HasRows()) {
	    $out .= <<<__END__
<form method=post>
  <table class=listing>
    <tr>
      <th>ID</th>
      <th>A?</th>
      <th>Catalog</th>
      <th>Group</th>
      <th>Discontinued</th>
      <th>Code</th>
      <th>Descr</th>
      <th>Supp Cat #</th>
      <th>Notes</th>
    </tr>
__END__;
	    $isOdd = FALSE;
	    while ($rsRows->NextRow()) {
		$isOdd = !$isOdd;
		$isActive = $rsRows->IsActive();
		$htActive = clsHTML::fromBool($isActive);

		$objCMSrce = $tblCMS->GetItem($rsRows->SourceID());
		$objCMGrp = $tblCMG->GetItem($rsRows->GroupID());
		if ($objCMSrce->HasRows()) {
		    $htCMSrce = $objCMSrce->SelfLink_name();
		} else {
		    $htCMSrce = '?'.$rsRows->SourceID();
		}
		if ($objCMGrp->HasRows()) {
		    $htCMGrp = $objCMGrp->SelfLink_name();
		} else {
		    $htCMGrp = '?'.$rsRows->GroupID();
		}

		$htID = '<input type=checkbox name="ctg['.$rsRows->GetKeyValue().']">'.$rsRows->SelfLink();
//		$htSource = $rsRows->SourceID();
//		$htGroup = $rsRows->GroupID();
		$htWhenDiscont = $rsRows->WhenDiscontinued();
		$htCode = $rsRows->Code();
		$htDescr = $rsRows->Descr();
		$htSuppCatNum = $rsRows->Supp_CatNum();
		$htNotes = $rsRows->Notes();
		$cssClass = $isOdd?'odd':'even';
		$out .= <<<__END__
    <tr class=$cssClass>
	<td>$htID</td>
	<td>$htActive</td>
	<td>$htCMSrce</td>
	<td>$htCMGrp</td>
	<td>$htWhenDiscont</td>
	<td>$htCode</td>
	<td>$htDescr</td>
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
