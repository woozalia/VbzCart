<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Titles
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2013-12-15 Renamed from vbz-mw-title.php to title.php for drop-in system.
    2017-03-16 updating for compatibility with Ferreteria revisions
*/
class vctAdminTitles extends vctShopTitles implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;
    use ftLoggableTable;
    use ftExecutableTwig;

    // ++ SETUP ++ //

    protected function SingularName() {
	return KS_CLASS_CATALOG_TITLE;
    }
    public function GetActionKey() {
	return KS_ACTION_CATALOG_TITLE;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Titles');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec() {
	return $this->AdminPage();
    } */

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
	return $this->GetConnection()->MakeTableWrapper('vcqtaTitlesInfo');
    }
    /*
    protected function ImageInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtImagesInfo');
    }*/

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
	$sqlTbl = $this->TableName_cooked();
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
	$rs = $this->FetchRecords($sql);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    protected function AdminPage() {
	$oApp = fcApp::Me();
	//$oPage = $oApp->GetPageObject();
	$oPathIn = $oApp->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$sPfx = $this->GetActionKey();
	$sSearchName = $sPfx.'-needle';
	$sFilterName = $sPfx.'-filter';
	$sFind = $oFormIn->GetString($sSearchName);
	$sFilt = $oFormIn->GetString($sFilterName);
	$doSearch = (!empty($sFind));
	$doFilter = (!empty($sFilt));
	$htFind = '"'.fcString::EncodeForHTML($sFind).'"';
	$htFilt = '"'.fcString::EncodeForHTML($sFilt).'"';

	$oMenu = fcApp::Me()->GetHeaderMenu();

	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Show'));
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('no-image','show active titles that have no images'));

	    /*
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
	*/
	
	// build forms

	//$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$oHdr = new fcSectionHeader('Search');
	$htSearchHdr = $oHdr->Render();
	$htSearchForm = <<<__END__
<form method=post>
  Search for:
  <input name="$sSearchName" size=40 value=$htFind>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	//$htFilterHdr = $oPage->SectionHeader('Filter',NULL,'section-header-sub');
	$oHdr = new fcSectionHeader('Filter');
	$htFilterHdr = $oHdr->Render();
	$htFilterForm = <<<__END__
<form method=get>
  Search filter (SQL):<input name="$sFilterName" width=40 value=$htFilt>
  <input type=submit name=btnFilt value="Apply">
</form>
__END__;

	$out = "\n<table class=content><tr><td>\n"
	  .$htSearchHdr.$htSearchForm.$htFilterHdr.$htFilterForm
	  ."\n</td></tr></table>\n"
	  ;

	// do the request

	if ($doSearch) {
	    $rs = $this->Search_forText($sFind);
	    $sql = $rs->sql;
	    $oHdr = new fcSectionHeader('Search Results');
	    //$out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	    $out .= $oHdr->Render()
	      ."<span class=line-stats><b>SQL</b>: $sql</span><br>"
	      .$rs->AdminRows();
	}

	if ($doFilter) {
	    $sqlSort = NULL; // implement later
	    $rs = $this->GetData($sFilt,NULL,$sqlSort);
	    $oHdr = new fcSectionHeader('Filter Results');
	    //$out .= $oPage->SectionHeader('Filter Results',NULL,'section-header-sub')
	    $out .= $oHdr->Render()
	      .$rs->AdminRows();
	}
	$sShow =$oPathIn->GetString('show');
	if ($sShow =='no-image') {
	    $out .= $this->MeInfoQuery()->RenderRows_Active_noImage();
	}

	//$out .= "\n</td></tr></table>";
	
	return $out;
    }

    // -- ADMIN WEB UI -- //

}
class vcrAdminTitle extends vcrShopTitle implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLoggableRecord;
    use ftLinkableRecord;
    use ftSaveableRecord;
    use ftExecutableTwig;

    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name() {
	//return $this->SelfLink($this->Value('Name'));
	return $this->SelfLink($this->NameFull());
    }

    // -- TRAIT HELPERS -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sTitle = 'Title: '.$this->NameString();

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
    // ++ FIELD VALUES -- // -- apparently everything useful is defined in the parent class
    // ++ FIELD CALCULATIONS ++  //
    
    // CALLBACK for dropdown list in non-edit mode
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    // CALLBACK for dropdown list in non-edit mode
    public function ListItem_Text() {
	return $this->NameFull();
    }
    protected function HasSupplier() {
	return !is_null($this->SupplierID());
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
	if (fcDropInManager::Me()->HasModule('vbz.stock')) {
	    return KS_CLASS_STOCK_LINES;
	} else {
	    throw new exception('Cannot access stock functions: "vbz.stock" dropin not loaded.');
	}
    }
    protected function ImagesClass() {
	if (fcDropInManager::Me()->HasModule('vbz.lcat')) {
	    return KS_CLASS_CATALOG_IMAGES;
	} else {
	    return 'vctImages_StoreUI';
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
	return $this->GetConnection()->MakeTableWrapper($this->StockItemsClass());
    }
    protected function SCatSourceTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_SOURCES);
    }
    protected function SCatGroupTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_GROUPS);
    }
    protected function SCatTitleTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_TITLES);
    }
    protected function TopicTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TopicsClass(),$id);
    }
    protected function XTopicTable() {
	return $this->GetConnection()->MakeTableWrapper($this->XTopicsClass());
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    protected function TopicRecords() {
    	$tTxT = $this->XTopicTable();
	$sqlXName = $tTxT->TableName_Cooked();
	$sqlTName = $this->TopicTable()->TableName_Cooked();
	$idTitle = $this->GetKeyValue();

	$sql = "SELECT t.*"
	  ." FROM $sqlTName AS t"
	  ." LEFT JOIN $sqlXName AS tt"
	  ." ON tt.ID_Topic=t.ID"
	  ." WHERE tt.ID_Title=$idTitle"
	  ." ORDER BY t.Sort,t.Name;";
	$rs = $this->TopicTable()->FetchRecords($sql);
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
	2017-03-17 This should be for displaying a single title, so changing the RenderImages_forRows() call
	  to RenderImages_forRow(). (The distinction is important, because ...Rows() advances past the end
	  of the recordset, which causes problems for everything that tries to use the record after that.)
    */
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
			  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,'cancel','edit '.$this->NameString()));
	    $doEdit = $ol->GetIsSelected();

	//$doEdit = $oPathIn->GetBool('edit');
	$doSave = $oFormIn->GetBool('btnSave')
	  && ($oFormIn->GetString('@form-for') == 'title')
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
	    
	    $ftThumbs = $this->RenderImages_forRow($this->NameString());
	    if (!is_null($ftThumbs)) {
		$out .= ('<table align=right><tr><td>'.$ftThumbs.'</td></tr></table>');
	    }

	    //fcApp::Me()->GetPageObject()->SetPageTitle('Title: '.$this->NameString());
	}

	/* 2017-03-17 old
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);
	*/

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
	    
	$oTplt->SetVariableValues($arCtrls);
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
<table class=content>
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
		$oCtrl->SetRecords($this->SupplierTable()->SelectRecords());
		
	      $oField = new fcFormField_Num($oForm,'ID_Dept');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		if ($this->IsNew()) {
		    $sqlFilt = NULL;
		} else {
		    $sqlFilt = 'ID_Supplier='.$this->SupplierID();
		}
		$oCtrl->SetRecords($this->DepartmentTable()->GetData_forDropDown($sqlFilt));
		
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
	throw new exception('2017-04-29 What calls this, anyway?');
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
	    $this->PageForm()->Save();
	    $this->SelfRedirect();
	}
	return $out;
    }
    /*----
      ACTION: renders the current recordset for administration
    */
    public function AdminRows(array $arOpts=NULL) {
	if ($this->HasRows()) {
	    $sHdr = fcArray::Nz($arOpts,'disp.hdr');
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
	    $sNone = fcArray::Nz($arOpts,'disp.none','no titles found');
	    $out = "<div class=content>$sNone</div>";
	}
	return $out;
    }
    public function ItemListing() {
	$idTitle = $this->GetKeyValue();
	
	// set up the section header/menu
	
	$oMenu = new fcHeaderMenu();
	$oHdr = new fcSectionHeader('Items',$oMenu);
	
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($ol = new fcMenuOptionLink('add','item','add',NULL,'add an item'));
	    $doAdd = $ol->GetIsSelected();
	  $oMenu->SetNode($ol = new fcMenuOptionLink('upd','item','update',NULL,'update specs for selected items'));
	    $doUpd = $ol->GetIsSelected();
    
	// check for user input
	
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	//$sAdd = $oPath->GetString('add');
	//$doUpd = $oPath->GetString('item') == 'upd';
	$doForm = $doUpd;	// might be other reasons we'd need a form
	
	if ($oFormIn->GetBool('btnUpd')) {
	    $rs = $this->ItemTable()->Records_forTitle($idTitle);
	    // checkbox array name will be value of $arOpt['chkname']
	    $arChkd = $oFormIn->GetArray('item');
	    $out = $rs->UpdateCatSpecs($arChkd);
	    $this->SelfRedirect(NULL,$out);
	}
	
	if ($doAdd) {
	    $rcItem = $this->ItemTable()->SpawnRecordset();
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
    	
	/* 2017-03-16 old
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
	*/

	// render the section header and listing
	
	if ($doUpd) {
	  $arOpt = array(
	    'dochk'=>TRUE,
	    'chkname'=>'item'
	    );
	} else { $arOpt = NULL; }
	$out =
	  $htForm	// new item form, if any
	  .$oHdr->Render()
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
	$oHdr = new fcSectionHeader('Stock');
	$out =
	  //$this->Engine()->App()->Page()->ActionHeader('Stock')
	  $oHdr->Render()
	  .$rs->AdminList(array('none'=>'There is no stock for this title.'))
	  ;
	return $out;
    }
    public function ImageListing() {
	$tImg = $this->ImageTable();
	$id = $this->GetKeyValue();
	
	// section header/menu
/* 2017-03-18 Image table does its own header/menu
	$oMenu = new fcHeaderMenu();
	$oHdr = new fcSectionHeader('Images',$oMenu);
	
	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($ol = new fcMenuOptionLink('add',$tImg->GetActionKey(),'add','cancel','add an image'));

	    $doAdd = $ol->GetIsSelected();
*/
	$arArgs = array(
	  'filt'	=> 'ID_Title='.$id,
	  'sort'	=> 'AttrSort,ID',
	  'event.obj'	=> $this,
	  'title.id'	=> $id,
	  'new'		=> TRUE
	  );
	$out = $tImg->AdminPage($arArgs);

	/* 2017-03-17 old
	
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
	  
	*/
	return $out;
    }
    /*----
      RETURNS: Editable listing of topics for this Title
    */
    protected function TopicListing() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
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

	$doRmvTopics = $oFormIn->GetBool('btnRmvTopics');

	// section header
	
	$oHdr = new fcSectionHeader('Topics');

	// OUTPUT begins
	
	$out = $oHdr->Render();

	if ($doRmvTopics) {
	    $arTopics = $oFormIn->GetArray('rmvTitle');
	    $cnt = $tTxT->DelTopics($this->GetKeyValue(),$arTopics);
	    $out .= 'Removed '.$cnt.' topic'.fcString::Pluralize($cnt).':';
	    foreach ($arTopics as $id => $on) {
		$rcTopic = $tTopics->GetRecord_forKey($id);
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

		$id = $rsTopics->GetKeyValue();
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
	return "<div class=content>$out</div>";
    }
    /*----
      RETURNS: Listing of CM (catalog management) groups for this title
      HISTORY:
	2011-02-06 added controls to allow deactivating/activating selected rows
    */
    protected function CMGrpListing() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$oHdr = new fcSectionHeader('Catalog Groups');
	
	$out = $oHdr->Render();

	$tblCMT = $this->SCatTitleTable();	// catalog management titles
	$tblCMS = $this->SCatSourceTable();	// catalog management sources
	$tblCMG = $this->SCatGroupTable();	// catalog management groups

	$doEnable = $oFormIn->GetBool('btnCtgEnable');
	$doDisable = $oFormIn->GetBool('btnCtgDisable');
	if ($doEnable || $doDisable) {
	    $arChg = $oFormIn->GetArray('ctg');
	    $out .= $doEnable?'Activating':'Deactivating';
	    foreach ($arChg as $id => $on) {
		$out .= ' '.$id;
		$arUpd = array(
		  'isActive'	=> $doEnable?'TRUE':'FALSE'
		  );
		$tblCMT->Update($arUpd,'ID='.$id);
	    }
	}

	$rsRows = $tblCMT->SelectRecords('ID_Title='.$this->GetKeyValue());
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
		$htActive = fcHTML::fromBool($isActive);

		$objCMSrce = $tblCMS->GetRecord_forKey($rsRows->SourceID());
		$objCMGrp = $tblCMG->GetRecord_forKey($rsRows->GroupID());
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
