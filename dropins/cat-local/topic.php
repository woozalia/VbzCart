<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Topics
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2014-03-28 adapting from MW for standalone CMS
*/
class VCTA_Topics extends clsTopics {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCRA_Topic');
	  $this->ActionKey(KS_ACTION_CATALOG_TOPIC);
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //

    protected function TitleClass() {
	return KS_CLASS_CATALOG_TITLE;
    }

    // -- CLASS NAMES -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function RootNode() {
	$objRoot = $this->SpawnItem();
	$objRoot->ID = NULL;
	return $objRoot;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      PURPOSE: renders tree with colors and links suitable for admin usage
      LATER: The parent method should be generalized for color and link-function
      USED BY: topic-tree-rebuilding process
    */
    public function RenderTree($iTwig=NULL) {
	if (is_null($iTwig)) {
	    $objRoot = $this->RootNode();
	} else {
	    $objRoot = $this->GetItem($iTwig);
	}
	$out = $objRoot->DrawTree();
	return $out;
    }
    public function ListTitles_unassigned() {
	$sql = 'SELECT tc.*, t.Name'
	  .' FROM (_titles AS tc LEFT JOIN cat_title_x_topic AS tt ON tc.ID=tt.ID_Title)'
	  .' LEFT JOIN cat_titles AS t ON tc.ID=t.ID'
	  .' WHERE (tt.ID_Title IS NULL) AND (tc.cntForSale > 0)';
	$rs = $this->Engine()->DataSet($sql,$this->TitleClass());
	if ($rs->HasRows()) {
	    $rs->Table = $this->Engine()->Titles();
	    $out = "\n<ul>";
	    while ($rs->NextRow()) {
		$out .= "\n<li>".$rs->AdminLink($rs->Value('CatNum')).' '.$rs->Value('Name').'</li>';
	    }
	    $out .= "\n</ul>";
	} else {
	    $out = 'All active titles have topics.';
	}
	return $out;
    }
    public function Data_forTitle($iTitle) {
	$sql = 'SELECT bt.* FROM cat_topic AS bt LEFT JOIN cat_title_x_topic AS bx ON bx.ID_Topic=bt.ID WHERE ID_Title='.$iTitle;
	$objRows = $this->DataSet($sql);
	return $objRows;
    }
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$doRebuild = $oPage->PathArg('rebuild');

	$out = NULL;

	$out .= "\n".'<div class="auxiliary-info"><h3>Unassigned Titles</h3>';
	$out .= $this->ListTitles_unassigned();
	$out .= "\n</div>";

	$arLink = array('rebuild'=>TRUE);
	$url = clsURL::FromArray($arLink);
	$htLink = '<a href="'.$url.'" title="rebuild the topic tree">rebuild tree</a>';

	//$htLink = $vgPage->SelfURL($arLink,FALSE);


	if ($doRebuild) {
	    // apparently there's no way to display a message without preventing the redirect
	    $this->RenderTree(TRUE);
	    $this->AdminRedirect();
	} else {
//	    $out .= "\n".'[<a href="'.$htLink.'">rebuild tree</a>]';
	    $out .= "\n[$htLink]";

	    $this->TreeCtrl()->FileForCSS('dtree.css');
	    $out .= $this->RenderTree();
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
	      $obj = $tblTopics->GetItem($iVal);	// for now, assume topic is ID

	      $arOut['html'] = $obj->AdminLink_name();
	      $arOut['text'] = $obj->KeyValue();
	      return $arOut;
	  },
	  );
	return $arOpts;
    }
}
class VCRA_Topic extends clsTopic_StoreUI {
    private $frmPage;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->frmPage = NULL;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE HELPERS ++ //

    public function AdminLink_name() {
	if ($this->IsNew()) {
	    return 'root';
	} else {
	    $txtShow = $this->Value('Name');
	    if ($this->doBranch()) {
		$txtPopup = $this->RenderBranch_text('<');
	    } else {
		$txtPopup = $this->NameFull();
	    }
	    return $this->AdminLink($txtShow,$txtPopup);
	}
    }
    protected function AdminLink_default_popup($iOverrideText) {
	if (is_null($iOverrideText)) {
	    $out = $this->Value('Name');
	} else {
	    $out = $iOverrideText;
	}
	return $out;
    }

    // -- BOILERPLATE HELPERS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
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
    // ++ DATA TABLES ACCESS ++ //

    protected function XTopicTable() {
	return $this->Engine()->Make($this->XTopicsClass());
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      HISTORY:
	2010-10-11 Created -- then discovered that it probably doesn't need
	  to be a separate function... but I think it makes things clearer.
    */
    public function Twigs() {
	$id = $this->KeyValue();
	if (is_null($id)) {
	    $sql = 'ID_Parent IS NULL';
	} else {
	    $sql = 'ID_Parent='.$id;
	}
	$rs = $this->Table()->GetData($sql,NULL,'Sort,NameTree,Name');
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    public function Tree_AddTwig(clsTreeNode $iTwig,$iText) {
	$id = $this->Value('ID');
//	$objSub = $iTwig->Add($id,$iText,$this->ShopURL());
	$objSub = $iTwig->Add($id,$iText,$this->AdminURL());
	return $objSub;
    }

    // -- ACTIONS -- //
    // ++ WEB UI COMPONENTS ++ //

    public function RenderBranch($iSep="&larr;") {
	$ftFullName = htmlspecialchars($this->Value('FullName'));
	$out = $this->AdminLink($this->Value('TreeName'),$ftFullName);
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentRecord()->RenderBranch($iSep);
	}
	return $out;
    }
    public function Tree_RenderTwig($iCntTitles) {
	$cntTitles = $iCntTitles;
	$txtNoun = ' title'.Pluralize($cntTitles).' available';	// for topic #'.$id;
	$out = ' [<b><span style="color: #00cc00;" title="'.$cntTitles.$txtNoun.'">'.$cntTitles.'</span></b>]';
//	$txt = $this->Value('CatNum').' '.$this->Value('Name');
	return $out;
    }
    /*----
      ACTION: Render the topic tree
    */
    public function DrawTree($iLevel=0,$iRootName="Topics") {
	$out = '';
	$intLevel = $iLevel + 1;
	$strIndent = str_repeat('*',$intLevel);

	$rsRows = $this->Twigs();
	if ($rsRows->HasRows()) {
	    if (empty($iLevel)) {
		$sTwig = $this->Name;
		if (empty($sTwig)) {
		    $sTwig = $iRootName;
		}
		$out .= "\n{{#tree:id=root|root='''$sTwig'''|";
	    }
	    while ($rsRows->NextRow()) {
		$strNameTree = $rsRows->NameTree;
		$strTwig = ifEmpty($strNameTree,$rsRows->Name);
		$out .= "\n<br>$strIndent".$rsRows->AdminLink($strTwig);
		$out .= $rsRows->DrawTree($intLevel);
	    }
	    if ($iLevel == 0) {
		$out .= "\n}}";
	    }
	    return $out;
	} else {
	    if (empty($iLevel)) {
		return 'NO TOPICS FOUND';
	    } else {
		return NULL;
	    }
	}
    }

    // -- WEB UI COMPONENTS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$doRmvTitles = clsHTTP::Request()->GetBool('btnRmvTitles');


	$sTitle = 'Topic #'.$this->KeyValue().': '.$this->Value('Name');
	$oPage->TitleString($sTitle);
/*
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();
*/
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	// save edits before re-displaying data

	// edits to this record
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}
	$out = NULL;

	// topic deletion
	if ($doRmvTitles) {
	    // TO BE WRITTEN
	}

	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $oForm = $this->PageForm();

	    $ctParent	= $oForm->RenderControl('ID_Parent');
	    $ctName	= $oForm->RenderControl('Name');
	    $ctNameTree	= $oForm->RenderControl('NameTree');
	    $ctNameFull	= $oForm->RenderControl('NameFull');
	    $ctNameMeta	= $oForm->RenderControl('NameMeta');
	    $ctUsage	= $oForm->RenderControl('Usage');
	    $ctSort	= $oForm->RenderControl('Sort');
	    $ctVariants	= $oForm->RenderControl('Variants');
	    $ctMispeled	= $oForm->RenderControl('Mispeled');
	} else {
	    $ctParent	= $this->ParentRecord()->AdminLink_name();
	    $ctName	= $this->Value('Name');
	    $ctNameTree	= $this->Value('NameTree');
	    $ctNameFull	= $this->Value('NameFull');
	    $ctNameMeta	= $this->Value('NameMeta');
	    $ctUsage	= $this->Value('Usage');
	    $ctSort	= $this->Value('Sort');
	    $ctVariants	= $this->Value('Variants');
	    $ctMispeled	= $this->Value('Mispeled');
	}

	$out .= '<ul>';
	$out .= '<li> <b>ID</b>: '.$this->KeyValue().' ['.$this->ShopLink('shop').']';
	$out .= '<li> <b>Parent</b>: '.$ctParent;
	$out .= '<li> <b>Name</b>: '.$ctName;
	  $out .= '<ul>';
	  $out .= '<li> <b>in tree</b>: '.$ctNameTree;
	  $out .= '<li> <b>complete</b>: '.$ctNameFull;
	  $out .= '<li> <b>meta</b>: '.$ctNameMeta;
	  $out .= '</ul>';
	$out .= '<li> <b>Usage</b>: '.$ctUsage;
	$out .= '<li> <b>Searching</b>: ';
	  $out .= '<ul>';
	  $out .= '<li> <b>variants</b>: '.$ctVariants;
	  $out .= '<li> <b>misspellings</b>: '.$ctMispeled;
	  $out .= '<li> <b>sort by</b>: '.$ctSort;
	  $out .= '</ul>';
	$out .= '</ul>';

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$out .= $oPage->SectionHeader('Subtopics',NULL,'section-header-sub');
	$out .= $this->SubtopicListing();
	$out .= $oPage->SectionHeader('Titles',NULL,'section-header-sub');
	$out .= $this->TitleListing();

	return $out;
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
    */
    public function AdminSave() {
	$out = $this->PageForm()->Save();
	return $out;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-01-26 adapted from VbzAdminTitle
    */
    private function PageForm() {
	if (is_null($this->frmPage)) {
	    $frmPage = new clsForm_recs($this);

	    $frmPage->AddField(new clsFieldNum('ID_Parent'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>20)));
	    $frmPage->AddField(new clsField('NameTree'),	new clsCtrlHTML(array('size'=>15)));
	    $frmPage->AddField(new clsField('NameFull'),	new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('NameMeta'),	new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('Usage'),		new clsCtrlHTML(array('size'=>25)));
	    $frmPage->AddField(new clsField('Sort'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsField('Variants'),	new clsCtrlHTML(array('size'=>40)));
	    $frmPage->AddField(new clsField('Mispeled'),	new clsCtrlHTML(array('size'=>40)));

	    $this->frmPage = $frmPage;
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
	  'fHandleData_Check' => function($iVal) {
	      $arOut['html'] = $iVal;
	      $arOut['text'] = $iVal;
	      return $arOut;
	  },
	  'fHandleData_Change_Start' => function($iText) use ($me) {
	      $arEv = array(
		'descr'	=> 'Adding '.$iText,
		'code'	=> 'sub++',
		'where'	=> __METHOD__
		);
	      $me->StartEvent($arEv);
	  },
	  'fHandleData_Change_Finish' => function($iText) use ($me) {
	      $arEv = array(
		'descrfin'	=> $iText
		);
	      $me->FinishEvent($arEv);
	  },
	  'fHandleData_Change_Item' => function($iVal) use ($me) {
	      $txt = $iVal;
	      $arIns = array(
		'ID_Parent'	=> SQLValue($me->KeyValue()),
		'Name'	=> SQLValue($txt)
		);
	      $me->Table->Insert($arIns);
	      $out = $me->Table->LastID();
	      return $out;
	  },
	  );
	$ctrlList->Options($arOpts);
	$htStatus = $ctrlList->HandleInput();

	$out = "\n<form method=post>";


	$rs = $this->Subtopics();
	if ($rs->HasRows()) {
	    $out .= "\n<table class=sortable>";
	    $out .= "\n<tr><th>ID</th><th>Name</th><th>in tree</th><th>full</th><th>sort</th><th>variants</th><th>mis-spellings</th></tr>";
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$isOdd = !$isOdd;
		$ftStyleShade = $isOdd?'ffffff':'#cccccc';
		$ftStyle = ' style="background: '.$ftStyleShade.';"';

		$id = $rs->KeyString();
		$ftID = $rs->AdminLink();
		$ftName = $rs->Value('Name');
		$ftNameTree = $rs->Value('NameTree');
		$ftNameFull = $rs->Value('NameFull');
		$ftSort = $rs->Value('Sort');
		$ftVari = $rs->Value('Variants');
		$ftMisp = $rs->Value('Mispeled');

		$out .= "\n<tr$ftStyle>"
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
	      $obj = $tblTitles->GetItem($iVal);	// for now, assume title is ID

	      $arOut['html'] = $obj->AdminLink($obj->CatNum(),$obj->Value('Name'));
	      //$arOut['text'] = $obj->KeyValue().'('.$obj->CatNum().')';
	      $arOut['text'] = $obj->KeyValue();
	      return $arOut;
	  },
	  'fHandleData_Change_Start' => function($iText) use ($me) {
	      $arEv = array(
		'descr'	=> 'Adding '.$iText,
		'code'	=> 'sub++',
		'where'	=> __METHOD__
		);
	      $me->StartEvent($arEv);
	  },
	  'fHandleData_Change_Finish' => function($iText) use ($me) {
	      $arEv = array(
		'descrfin'	=> $iText
		);
	      $me->FinishEvent($arEv);
	  },
	  'fHandleData_Change_Item' => function($iVal) use ($me,$tblTitleTopics) {
	      $txt = $iVal;
	      $arIns = array(
		'ID_Title'	=> SQLValue($txt),
		'ID_Topic'	=> SQLValue($me->KeyValue())
		);
	      //$this->Engine()->TitleTopics()->Insert($arIns);
	      $sql = $tblTitleTopics->Insert($arIns);
	      $out = SQLValue($txt);
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
	    $this->StartEvent($arEv);
	    $txtRmv = '';
	    foreach ($arRmv as $id => $on) {
		$sqlFilt = '(ID_Topic='.$this->ID.') AND (ID_Title='.$id.')';
		$tblTitleTopics->Delete($sqlFilt);
		$txtRmv .= ' '.$id;
	    }
	    $arEv = array(
	      'descrfin'	=> 'Titles removed:'.$txtRmv
	      );
	    $this->FinishEvent($arEv);
	    $htStatus .= 'Titles removed:'.$txtRmv;
	}

	$out = "\n<form name=\"add-titles\" method=post>";

	$rs = $this->TitleRecords_forRow();
	if ($rs->HasRows()) {
	    $out .= "\n<table class=sortable>";
	    $out .= "\n<tr><th>ID</th><th>Cat #</th><th>Name</th><th>Dept</th><th>When Added</th><th>When Unavail</th></tr>";
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$isOdd = !$isOdd;
		$ftStyleShade = $isOdd?'ffffff':'#cccccc';
		$ftStyle = ' style="background: '.$ftStyleShade.';"';

		$id = $rs->KeyString();
		$ftID = $rs->AdminLink();
		$ftCatNum = $rs->CatNum();
		$ftName = $rs->Value('Name');
		$rsDept = $rs->DepartmentRecord();
		$ftDept = $rsDept->AdminLink_name();
		$ftAdded = $rs->Value('DateAdded');
		$ftUnavail = $rs->Value('DateUnavail');

		$out .= "\n<tr$ftStyle>"
		  ."<td><input type=checkbox name=\"rmvTitle[$id]\">$ftID</td>"
		  ."<td>$ftCatNum</td>"
		  ."<td>$ftName</td>"
		  ."<td>$ftDept</td>"
		  ."<td>$ftAdded</td>"
		  ."<td>$ftUnavail</td>"
		  ."</tr>";
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
