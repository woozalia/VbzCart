<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Topics
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2014-03-28 adapting from MW for standalone CMS
    2016-02-07 descending VCTA_Topics (now vctAdminTopics) from clsTopics_StoreUI (now vctShopTopics)
      instead of clsTopics (now vctTopics)
      This is needed because Shop class knows about the treeview control.
*/
class vctAdminTopics extends vctShopTopics implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return KS_CLASS_CATALOG_TOPIC;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CATALOG_TOPIC;
    }
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    } */

    // -- EVENTS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }
    protected function TitleClass() {
	return KS_CLASS_CATALOG_TITLE;
    }
    protected function TitlesInfoClass() {
	return 'vcqtaTitlesInfo';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function TitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TitlesClass(),$id);
    }
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper($this->TitlesInfoClass());
    }
    protected function TopicInfoTable() {
	return $this->GetConnection()->MakeTableWrapper('vcqtTopicsInfo');
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function RootNode() {
	$rcRoot = $this->SpawnItem();
	$rcRoot->ClearKeyValue();
	return $rcRoot;
    }
    // used by form display
    public function GetData_forDropDown() {
	$sqlThis = $this->TableName_Cooked();
	$sql = <<<__END__
SELECT t1.*, t2.NameTree AS NameParent
  FROM $sqlThis AS t1
  LEFT JOIN $sqlThis AS t2
  ON t1.ID_Parent=t2.ID
  ORDER BY t1.Sort, t1.Name
__END__;
	return $this->FetchRecords($sql);
    }
    public function Data_forTitle($iTitle) {
	$sql = 'SELECT bt.* FROM cat_topic AS bt LEFT JOIN cat_title_x_topic AS bx ON bx.ID_Topic=bt.ID WHERE ID_Title='.$iTitle;
	$objRows = $this->DataSet($sql);
	return $objRows;
    }

    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    public function ListTitles_unassigned() {

	//$tbl = $this->TopicInfoTable();
	//$rs = $tbl->TitleRecords_active_noTopic();
	$tbl = $this->TitleInfoQuery();
	$rs = $tbl->SelectRecords_active_noTopic();
	if ($rs->HasRows()) {
	    $out = "<ul class=content>\n";
	    while ($rs->NextRow()) {
		$out .= $rs->RenderRow_forNoTopicList();
	    }
	    $out .= "</ul>\n";
	} else {
	    $out = 'All active titles have topics.';
	}

	return $out;
    }
    public function AdminPage() {
	$oApp = fcApp::Me();
	$oPathIn = $oApp->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	// check for things that need to be done
	$sDo = $oPathIn->GetString('do');
	switch($sDo) {
	  case 'rebuild':	// rebuild the tree
	    $this->RenderTree(TRUE);
	    $this->SelfRedirect();
	    break;
	  case 'add':		// add a new topic
	    // TODO : write this
	    break;
	}
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	
	$oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('view','View'));
	  $oGrp->SetChoice($ol = new fcHeaderChoice('flat','view topics as a flat list'));
	    $doViewFlat = $ol->GetIsSelected();
	  $oGrp->SetChoice($ol = new fcHeaderChoice('tree','view topics as a tree'));	
	    $doViewTree = $ol->GetIsSelected();
	  $oGrp->SetChoice($ol = new fcHeaderChoice('notopx','view active titles not assigned to any topics'));	
	    $doViewNotopx = $ol->GetIsSelected();
	$oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('do','Do'));
	  //$oGrp->SetChoice($ol = new fcHeaderChoice('rebuild','rebuild the topic tree'));	// 2018-02-09 obsolete, I think?
	  $oGrp->SetChoice($ol = new fcHeaderChoice('new','add a new topic'));	
	    $doNew = $ol->GetIsSelected();

	/* 2018-02-09 old menu API
	$arMenu = array(
	  new clsAction_section('View'),
	  new clsActionLink_option(
	    array(),
	    'flat',	// link key (value)
	    'view',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'view topics as a flat list'	// pop-up description
	    ),
	  new clsActionLink_option(
	    array(),
	    'tree',	// link key (value)
	    'view',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'view the topic tree'	// pop-up description
	    ),
	  new clsActionLink_option(
	    array(),
	    'notopx',	// link key (value)
	    'view',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'view active titles not assigned to any topics'	// pop-up description
	    ),
	  new clsAction_section('Do'),
	  new clsActionLink_option(
	    array(),
	    'rebuild',	// link key (value)
	    'do',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'rebuild the topic tree'	// pop-up description
	    ),
	  new clsActionLink_option(
	    array(),
	    'new',	// link key (value)
	    'id',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'add a new topic'	// pop-up description
	    ),
	    
	  );
	$oPage->PageHeaderWidgets($arMenu);
	*/
	  
	$out = NULL;
	
	if ($doViewTree) {
	    //$this->TreeCtrl()->FileForCSS('dtree.css');
	    
	    
	    /* 2018-02-14 How about just calling BuiltTree() directly?
	    // copied from shop/topic-ui:BuildTree()
	    $oTree = $this->TreeCtrl();
	    $oRoot = $oTree->RootNode();
	    $oFakeRoot = $oRoot->Add(0,'Topics');
	    $ar = $this->LoadTitleStats();	// 2018-02-09 works in shop/topic-ui, so must be in a different class
	    $this->AddLayer($arLayer,$oFakeRoot,0,$ar);	// build the node tree
	    $htTree .= $oRoot->RenderTree();
	    */
	    
	    $htTree = $this->BuildTree();
	    
	    $out .=
	      "\n<div class='auxiliary-info'>"
	      .(new fcSectionHeader('Search'))->Render()
	      .$this->HandleSearchForm()
	      ."\n</div>"
	      ."\n<table><tr><td class=content>"
	      .(new fcSectionHeader('Topic Tree'))->Render()
	      .$htTree
	      ."\n</td></tr></table>"
	      ;
	}
	if ($doViewFlat) {
	    $oHdr = new fcSectionHeader('Search');
	    $rs = $this->SelectRecords();
	    $out .=
	      "\n<div class='auxiliary-info'>"
	      .$oHdr->Render()
	      .$this->HandleSearchForm()
	      ."\n</div>"
	      .$rs->AdminRows()
	      ;
	}
	if ($doViewNotopx) {
	    $oHdr = new fcSectionHeader('Unassigned Titles');
	    $out .=
	      "\n"
	      //.'<div class="auxiliary-info"><h3>Unassigned Titles</h3>'
	      .$oHdr->Render()
	      .$this->ListTitles_unassigned()
	      //."\n</div>"
	      ;
	}
	
	// TODO: implement "new"

	return $out;
    }
    // ACTION: Displays search form and (if requested) does search and displays results
    protected function HandleSearchForm() {
	$oApp = fcApp::Me();
	$oPathIn = $oApp->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$sFind = $oFormIn->GetString('txtFind');
	$htFind = htmlspecialchars($sFind);
	$out = 
	  "\n<form method=post>"
	  ."\nSearch for: <input name=txtFind value='$htFind'><input type=submit name=btnFind value='Search...'>"
	  ."\n</form>"
	  ;
	if ($oFormIn->GetBool('btnFind')) {
	    $rs = $this->Search_forText($sFind);
	    if ($rs->HasRows()) {
		$out .= $rs->AdminRows();
	    } else {
		$out .= "No matches for &ldquo;$htFind&rdquo;.";
	    }
	}
	return $out;
    }
    /*----
      RETURNS: options array for entering topics
      USAGE:
	1. fill in fHandleData_Change_Start, fHandleData_Change_Finish, fHandleData_Change_Item
	2. Then, where $arOpts is the array returned:
	  $ctrlList = new clsWidget_ShortList();
	  $ctrlList->Options($arOpts);
	  $htOut = $ctrlList->HandleInput();
      HISTORY:
	2011-09-29 adapted from VbzAdminTitle::TopicListing()
    */
    public function TopicListing_base_array() {
	$tblTopics = $this;
	$tblTopics->doBranch(TRUE);
	$arOpts = array(
	  'name'	=> 'title',
	  'btnChk_Text'	=> 'Enter topics:',
	  'btnChg_Text' => '<= Add',
	  'txtCargo_sng' => 'topic ID',
	  'txtCargo_plr' => 'topic IDs',
	  'txtProd_sng' => 'topic',
	  'txtProd_plr' => 'topics',
	  'txtConf_list' => 'Adding',
	  'fHandleData_Check' => function($iVal) use ($tblTopics) {
	      $rc = $tblTopics->GetItem($iVal);	// for now, assume topic is ID
	      $id = (int)$rc->GetKeyValue();

	      $arOut['html'] = $rc->SelfLink_name();
	      $arOut['text'] = $id;
	      $arOut['val'] = $id;
	      $arOut['id'] = $id;
	      return $arOut;
	  },
	  );
	return $arOpts;
    }
}
class vcrAdminTopic extends vcrShopTopic implements fiLinkableRecord {
    use ftLinkableRecord;
    //use ftLoggableRecord;
    use ftShowableRecord;

    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name() {
	if ($this->IsNew()) {
	    return 'root';
	} else {
	    $txtShow = $this->NameString();
	    if ($this->doBranch()) {
		$txtPopup = $this->RenderBranch_text();
	    } else {
		$txtPopup = $this->NameFull();
	    }
	    return $this->SelfLink($txtShow,$txtPopup);
	}
    }
    protected function SelfLink_default_popup($sOverride) {
	if (is_null($sOverride)) {
	    $out = $this->NameString();
	} else {
	    $out = $sOverride;
	}
	return $out;
    }

    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminPage();
    }
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    public function ListItem_Text() {
	if ($this->HasParent()) {
	    $sParent = ' &larr; '.$this->ParentRecord()->NameFull();
	} else {
	    $sParent = NULL;
	}
	return $this->FldrName().' '.$this->NameTree_best().$sParent;
    }
    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    public function AdminRows_settings_columns() {
	return array(
	    'ID'	=> 'ID',
	    'ID_Parent'	=> 'Parent',
	    'Name'	=> 'Name',
	    'NameTree'	=> 'in Tree',
	    'NameFull'	=> '...Full'
	  );
    }
    protected function AdminField($sField) {
	switch($sField) {
	  case 'ID':
	    $val = $this->SelfLink();
	    break;
	  case 'ID_Parent':
	    if ($this->HasParent()) {
		$val = $this->ParentRecord()->SelfLink_name();
	    } else {
		$val = "<i>root</i>";
	    }
	    break;
	  default:
	    $val = $this->GetFieldValue($sField);
	}
	return "<td>$val</td>";
    }

    // -- CALLBACKS -- //
    // ++ FIELD CALCULATIONS ++ //
    
    // RETURNS: If NameTree() isn't defined, uses NameFull()
    protected function NameTree_best() {
	$sName = $this->NameTree();
	if (is_null($sName)) {
	    $sName = $this->Name();
	}
	return $sName;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }
    protected function XTopicsClass() {
	return KS_CLASS_CATALOG_TITLES_TOPICS;
    }
    protected function XTitlesClass() {
	return KS_CLASS_CATALOG_TITLES_TOPICS;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function XTopicTable() {
	return $this->Engine()->Make($this->XTopicsClass());
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      HISTORY:
	2010-10-11 Created -- then discovered that it probably doesn't need
	  to be a separate function... but I think it makes things clearer.
      TODO: Rename to TwigRecords()
    */
    public function Twigs() {
	$id = $this->GetKeyValue();
	if (is_null($id)) {
	    $sql = 'ID_Parent IS NULL';
	} else {
	    $sql = 'ID_Parent='.$id;
	}
	$rs = $this->Table()->GetData($sql,NULL,'Sort,NameTree,Name');
	return $rs;
    }
    /*----
      USED BY: form building
      NOTES:
	* In SQL, a NULL value neither equals nor does-not-equal anything else,
	  so if we want to include records with ID_Parent=NULL (i.e. root Topics),
	  we have to translate NULL to some regular value -- which is what IFNULL() does.
	* We can either sort by ID (which usually requires looking up the ID elsewhere,
	  but at least makes it impossible to misplace things) or by some combination of
	  Name and NameTree (which makes it easier to look things up locally if you have
	  some idea of what it's called).
	  
	  With anything other than ID, you have to watch out for the possibility of
	  some records depending on information that's not shown in the drop-down list
	  and hence is counterintuitive. This is especially true if, say, NameFull has been
	  set but NameTree has not.
	  
	  Either way, make sure that the list displays what you're searching by, using the
	  same logic. I went with IFNULL(NameTree,NameFull) (and updated ListItem_Text() to match)
	  so we can default to NameTree but fall back on NameFull where NameTree hasn't been entered.
	  
	  Also, watch out for fields that are "" rather than actual NULL. The current Ferreteria
	  code converts blanks to NULLs, but there could be bugs or there might have been other
	  entry methods.
    */
    protected function GetData_Parent_forDropDown() {
	$sqlTbl = $this->Table()->NameSQL();
	if ($this->IsNew()) {
	    // get all Topics -- any of them might be the parent for this new record
	    $rs = $this->Table()->SelectRecords(NULL,'ID');
	} else {
	    // get Topics which might be a parent to this one
	    $id = $this->GetKeyValue();
	    $sqlFilt = "(ID != $id) AND (IFNULL(ID_Parent,-1) != $id)";
	    //$sql = "SELECT ID, NameTree, Name, ID_Parent FROM $sqlTbl WHERE $sqlFilt ORDER BY IFNULL(NameTree,Name)";
	    $sql = "SELECT ID, NameTree, Name, ID_Parent FROM $sqlTbl WHERE $sqlFilt ORDER BY ID";
	    $rs = $this->Table()->DataSQL($sql);
	}
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    public function Tree_AddTwig(fcTreeNode $iTwig,$iText) {
	$id = $this->GetKeyValue();
	$objSub = $iTwig->Add($id,$iText,$this->SelfURL());
	return $objSub;
    }

    // -- ACTIONS -- //
    // ++ WEB UI COMPONENTS ++ //

    public function RenderBranch($iSep="&larr;") {
	$ftFullName = fcString::EncodeForHTML($this->Value('FullName'));
	$out = $this->AdminLink($this->Value('TreeName'),$ftFullName);
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentRecord()->RenderBranch($iSep);
	}
	return $out;
    }
    public function Tree_FormatTwigStats($iCntTitles) {
	$cntTitles = $iCntTitles;
	$txtNoun = ' title'.fcString::Pluralize($cntTitles).' available';	// for topic #'.$id;
	$out = ' [<b><span style="color: #00cc00;" title="'.$cntTitles.$txtNoun.'">'.$cntTitles.'</span></b>]';
//	$txt = $this->Value('CatNum').' '.$this->Value('Name');
	return $out;
    }

    // -- WEB UI COMPONENTS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$doRmvTitles = clsHTTP::Request()->GetBool('btnRmvTitles');

	$isNew = $this->IsNew();
	if ($isNew) {
	    $sTitle = "New Topic";
	    $doEdit = TRUE;	// always edit new record
	} else {
	    $sTitle = 'Topic #'.$this->GetKeyValue().': '.$this->Value('Name');
	}
	$oPage->TitleString($sTitle);

	$arActs = array(
	  new clsActionLink_option(
	    array(),
	    'edit',			// link key (value)
	    NULL,			// group key (name)
	    NULL,			// display when off
	    'cancel',			// display when on
	    'edit this topic'	// description (shows as hover-over text)
	    ),
	  );
	$oPage->PageHeaderWidgets($arActs);

	// save edits before re-displaying data

	// edits to this record
	$ftSaveStatus = NULL;
	$frm = $this->PageForm();
	if ($doSave) {
	    $idNew = $frm->Save();
	    $sMsg = $frm->MessagesString();
	    $this->SetKeyValue($idNew);	// so we redirect to the new record
	    $this->SelfRedirect(NULL,$sMsg);
	}
	$out = NULL;
	
	// topic deletion
	if ($doRmvTitles) {
	    // TO BE WRITTEN
	}

	// render form controls
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}

	$arCtrls = $frm->RenderControls($doEdit);
	
	if ($isNew) {
	    $arCtrls['!ID'] = '(new)';
	} else {
	    $arCtrls['!ID'] = $this->SelfLink().' ['.$this->ShopLink('shop').']';
	}
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}
	
	$oTplt = $this->PageTemplate();
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {
	    $out .= "\n<input type=submit name=btnSave value=Save>"
	      ."\n</form>"
	      ;
	}

	if (!$this->IsNew()) {
	    $out .= 
	      $oPage->SectionHeader('Subtopics',NULL,'section-header-sub')
	      .$this->SubtopicListing()
	      .$oPage->SectionHeader('Titles',NULL,'section-header-sub')
	      .$this->TitleListing()
	      ;
	}

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table><tr><td>
<ul>
  <li> <b>ID</b>: [[!ID]]
  <li> <b>Parent</b>: [[ID_Parent]]
  <li> <b>Name</b>: [[Name]]
  <ul>
    <li> <b>in tree</b>: [[NameTree]]
    <li> <b>complete</b>: [[NameFull]]
    <li> <b>meta</b>: [[NameMeta]]
  </ul>
  <li> <b>Usage</b>: [[Usage]]
  <li> <b>Searching</b>:
  <ul>
    <li> <b>variants</b>: [[Variants]]
    <li> <b>misspellings</b>: [[Mispeled]]
    <li> <b>sort by</b>: [[Sort]]
  </ul>
</ul>
</td></tr></table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }    
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-01-26 adapted from VbzAdminTitle
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_Parent');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->GetData_Parent_forDropDown());
		$oCtrl->AddChoice(NULL,'none (root)');
		
	      $oField = new fcFormField_Text($oForm,'Name');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

	      $oField = new fcFormField_Text($oForm,'NameTree');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>15));

	      $oField = new fcFormField_Text($oForm,'NameFull');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));

	      $oField = new fcFormField_Text($oForm,'NameMeta');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));

	      $oField = new fcFormField_Text($oForm,'Usage');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));

	      $oField = new fcFormField_Text($oForm,'Sort');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));

	      $oField = new fcFormField_Text($oForm,'Variants');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	      $oField = new fcFormField_Text($oForm,'Mispeled');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    protected function SubtopicListing() {
	$htStatus = NULL;

	// set up the entry widget
	$ctrlList = new clsWidget_ShortList();
	$me = $this;
	$arOpts = array(
	  'name'	=> 'topic',
	  'btnChk_Text'	=> 'Add subtopics:',
	  'btnChg_Text' => '<= Create',
	  'txtCargo_sng' => 'topic name',
	  'txtCargo_plr' => 'topic names',
	  'txtProd_sng' => 'subtopic',
	  'txtProd_plr' => 'subtopics',
	  'fHandleData_Check' => function($sVal) {
	      $arOut['html'] = $sVal;
	      $arOut['text'] = $sVal;
	      $arOut['val'] = $sVal;
	      return $arOut;
	  },
	  'fHandleData_Change_Start' => function($iText) use ($me) {
	      $arEv = array(
		'descr'	=> 'Adding '.$iText,
		'code'	=> 'sub++',
		'where'	=> __METHOD__
		);
	      $me->CreateEvent($arEv);
	  },
	  'fHandleData_Change_Finish' => function($iText) use ($me) {
	      $arEv = array(
		'descrfin'	=> $iText
		);
	      $me->GetEvent()->Finish($arEv);
	      $me->SelfRedirect(NULL,$iText);
	  },
	  'fHandleData_Change_Item' => function($iVal) use ($me) {
	      $txt = $iVal;
	      $db = $me->Engine();
	      $arIns = array(
		'ID_Parent'	=> $db->SanitizeAndQuote($me->GetKeyValue()),
		'Name'		=> $db->SanitizeAndQuote($txt)
		);
	      $id = $me->Table()->Insert($arIns);
	      return $id;
	  },
	  );
	$ctrlList->Options($arOpts);
	$htStatus = $ctrlList->HandleInput();

	$out = "\n<form method=post>";


	$rs = $this->SubtopicRecords();
	if ($rs->HasRows()) {
	    $out .= "\n<table class=listing>";
	    $out .= "\n<tr><th>ID</th><th>Name</th><th>in tree</th><th>full</th><th>sort</th><th>variants</th><th>mis-spellings</th></tr>";
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$isOdd = !$isOdd;
		//$ftStyleShade = $isOdd?'ffffff':'#cccccc';
		//$ftStyle = ' style="background: '.$ftStyleShade.';"';
		$cssClass = $isOdd?'odd':'even';

		$id = $rs->KeyString();
		$ftID = $rs->SelfLink();
		$ftName = $rs->Value('Name');
		$ftNameTree = $rs->Value('NameTree');
		$ftNameFull = $rs->Value('NameFull');
		$ftSort = $rs->Value('Sort');
		$ftVari = $rs->Value('Variants');
		$ftMisp = $rs->Value('Mispeled');

		$out .= "\n<tr class=$cssClass>"
		  ."<td>$ftID</td>"
		  ."<td>$ftName</td>"
		  ."<td>$ftNameTree</td>"
		  ."<td>$ftNameFull</td>"
		  ."<td>$ftSort</td>"
		  ."<td>$ftVari</td>"
		  ."<td>$ftMisp</td>"
		  ."</tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out .= '<i>None found.</i>';
	}
	$out .= '<br>'.$htStatus;
	$out .= $ctrlList->RenderForm_Entry();
	$out .= '</form>';
	return $out;
    }
    protected function TitleListing() {
	// check for any form actions
	$doChk = clsHTTP::Request()->GetBool('btnChkTitles');
	$doAdd = clsHTTP::Request()->GetBool('btnAddTitles');
	$doRmv = clsHTTP::Request()->GetBool('btnRmvTitles');

	$htStatus = NULL;

	// pass-along vars for anon functions
	$tblTitles = $this->TitleTable();	// for anon function to use
	$tblTitleTopics = $this->XTopicTable();
	$me = $this;

	$ctrlList = new clsWidget_ShortList();
	$arOpts = array(
	  'name'	=> 'title',
	  'btnChk_Text'	=> 'Enter titles:',
	  'btnChg_Text' => '<= Add',
	  'txtCargo_sng' => 'title ID',
	  'txtCargo_plr' => 'title IDs',
	  'txtProd_sng' => 'title',
	  'txtProd_plr' => 'titles',
	  'fHandleData_Check' => function($iVal) use ($tblTitles) {
	      $rc = $tblTitles->GetItem($iVal);	// for now, assume title is ID

	      $arOut['html'] = $rc->SelfLink($rc->CatNum(),$rc->NameString());
	      //$arOut['text'] = $rc->GetKeyValue().'('.$rc->CatNum().')';
	      $arOut['text'] = $rc->GetKeyValue();
	      return $arOut;
	  },
	  'fHandleData_Change_Start' => function($iText) use ($me) {
	      $arEv = array(
		'descr'	=> 'Adding '.$iText,
		'code'	=> 'sub++',
		'where'	=> __METHOD__
		);
	      $me->CreateEvent($arEv);
	  },
	  'fHandleData_Change_Finish' => function($iText) use ($me) {
	      $arEv = array(
		'descrfin'	=> $iText
		);
	      $me->GetEvent()->Finish($arEv);
	      $me->SelfRedirect(NULL,$iText);
	  },
	  'fHandleData_Change_Item' => function($iVal) use ($me,$tblTitleTopics) {
	      $txt = $iVal;
	      $db = $me->Engine();
	      $arIns = array(
		'ID_Title'	=> $db->Sanitize_andQuote($txt),
		'ID_Topic'	=> $db->Sanitize_andQuote($me->GetKeyValue())
		);
	      //$this->Engine()->TitleTopics()->Insert($arIns);
	      $sql = $tblTitleTopics->Insert($arIns);
	      $out = $me->Engine()->Sanitize_andQuote($txt);
	      return $out;
	  },
	  );
	$ctrlList->Options($arOpts);
	$htStatus = $ctrlList->HandleInput();
	if (clsHTTP::Request()->GetBool('btnRmvTitles')) {
	    $arRmv = clsHTTP::Request()->GetArray('rmvTitle');
	    $cntRmv = count($arRmv);
	    $arEv = array(
	      'descr'	=> 'Removing '.$cntRmv.' title'.Pluralize($cntRmv).' from this topic',
	      'code'	=> 'TTL--',
	      'where'	=> __METHOD__
	      );
	    $rcEv = $this->CreateEvent($arEv);
	    $txtRmv = '';
	    foreach ($arRmv as $id => $on) {
		$sqlFilt = '(ID_Topic='.$this->ID.') AND (ID_Title='.$id.')';
		$tblTitleTopics->Delete($sqlFilt);
		$txtRmv .= ' '.$id;
	    }
	    $arEv = array(
	      'descrfin'	=> 'Titles removed:'.$txtRmv
	      );
	    $rc->Finish($arEv);
	    $htStatus .= 'Titles removed:'.$txtRmv;
	}

	$out = "\n<form name=\"add-titles\" method=post>";

	$rs = $this->TitleRecords_forRow();
	if ($rs->HasRows()) {
	    $cnt = $rs->RowCount();
	    $out .= $cnt.' title'.fcString::Pluralize($cnt).' are assigned to this topic.';
	    $out .= "\n<table class=listing>";
	    $out .= "\n<tr><th>ID</th><th>Cat #</th><th>Name</th><th>Dept</th><th>When Added</th><th>When Unavail</th></tr>";
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$isOdd = !$isOdd;
		//$ftStyleShade = $isOdd?'ffffff':'#cccccc';
		//$ftStyle = ' style="background: '.$ftStyleShade.';"';
		$cssClass = $isOdd?'odd':'even';

		$id = $rs->KeyString();
		$ftID = $rs->SelfLink();
		$ftCatNum = $rs->CatNum();
		$ftName = $rs->Value('Name');
		$rsDept = $rs->DepartmentRecord();
		if (is_null($rsDept)) {
		    $ftDept = '<span title="no department has been assigned">n/a!</a>';
		} else {
		    $ftDept = $rsDept->SelfLink_name();
		}
		$ftAdded = $rs->Value('DateAdded');
		$ftUnavail = $rs->Value('DateUnavail');

		$out .= <<<__END__
  <tr class=$cssClass>
    <td><input type=checkbox name="rmvTitle[$id]">$ftID</td>
    <td>$ftCatNum</td>
    <td>$ftName</td>
    <td>$ftDept</td>
    <td>$ftAdded</td>
    <td>$ftUnavail</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	    $out .= '<input type=submit name="btnRmvTitles" value="Remove Checked">';
	} else {
	    $out .= '<i>None found.</i>';
	}
	$out .= '<br>'.$htStatus;
	$out .= $ctrlList->RenderForm_Entry();
/*
	$out .= '<input type=submit name="btnChkTitles" value="Add These:">';
	$out .= '<input size=40 name=txtNewTitles> (IDs separated by spaces)';
*/
	$out .= '</form>';
	return $out;
    }
    /*----
      What uses this?

    */
    public function AdminList() {
	throw new exception('Who calls this?');

	$objRecs = $this;

	if ($objRecs->HasRows()) {
	    $out = "{| class=sortable\n|-\n! ID || Name || Abbr || When Avail || Superceded || Status";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$objTopic = $this->objDB->Topics()->GetItem($this->ID_Topic);
		$id = $objTopic->ID;
		$ftID = $id;
		$out .= "\n|- style=\"$wtStyle\"".
		    "\n| ".$ftID.
		    ' || '.$objRecs->Name.
		    ' || '.$objRecs->Abbr.
		    ' || '.$strDate.
		    ' || '.$strSuper.
		    ' || '.$strStatus;
		$isOdd = !$isOdd;
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'No topics found.';
	}
	return $out;
    }

    // -- ADMIN WEB UI -- //

}
