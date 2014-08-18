<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Topics
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class clsAdminTopics extends clsTopics {

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminTopic');
	  $this->ActionKey('topic');
    }
    protected function AdminRedirect() {
	global $vgPage;
	global $wgOut;

	$ar = array('page'=>$this->ActionKey());
	$url = $vgPage->SelfURL($ar,TRUE);
	$wgOut->redirect($url);
    }
    public function RootNode() {
	$objRoot = $this->SpawnItem();
	$objRoot->ID = NULL;
	return $objRoot;
    }
    /*----
      PURPOSE: renders tree with colors and links suitable for admin usage
      LATER: The parent method should be generalized for color and link-function
    */
/*
    public function RenderTree($iTwig=NULL) {
	if (is_null($iTwig)) {
	    $objRoot = $this->RootNode();
	} else {
	    $objRoot = $this->GetItem($iTwig);
	}
	$out = $objRoot->DrawTree();
	return $out;
    }
*/
    public function ListTitles_unassigned() {
	global $vgPage;

	$sql = 'SELECT tc.*, t.Name'
	  .' FROM (_titles AS tc LEFT JOIN cat_title_x_topic AS tt ON tc.ID=tt.ID_Title)'
	  .' LEFT JOIN cat_titles AS t ON tc.ID=t.ID'
	  .' WHERE (tt.ID_Title IS NULL) AND (tc.cntForSale > 0)';
	$rs = $this->Engine()->DataSet($sql,$this->Engine()->Titles()->ClassSng());
	if ($rs->HasRows()) {
	    $vgPage->UseHTML();
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
	$objRows = $this->Engine()->DataSet($sql,$this->ClassSng());
	return $objRows;
    }
    public function AdminPage() {
	global $wgOut;
	global $vgPage,$vgOut;

	$out = "\n".'<table align=right style="border: solid black 1px;"><tr><td><h3>Unassigned Titles</h3>';
	$out .= $this->ListTitles_unassigned();
	$out .= "\n</td></tr></table>";

	$arLink = array('rebuild'=>TRUE);
	//$vgPage->ArgsToKeep(array('page'));
	$htLink = $vgPage->SelfURL($arLink,FALSE);

	$doRebuild = $vgPage->Arg('rebuild');

	if ($doRebuild) {
	    // apparently there's no way to display a message without preventing the redirect
	    $this->RenderTree(TRUE);
	    $this->AdminRedirect();
	} else {
	    $out .= "\n".'[<a href="'.$htLink.'">rebuild tree</a>]';

	    $this->TreeCtrl()->FileForCSS('dtree.css');
	    $out .= $this->RenderTree(FALSE);
	}
	$wgOut->AddHTML($out); $out = '';
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
class clsAdminTopic extends clsTopic {
    /*----
      HISTORY:
	2010-12-05 boilerplate event logging added
    */
    //----
    // BOILERPLATE: event logging
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
    // BOILERPLATE: admin HTML
    public function AdminURL() {
	return clsAdminData_helper::_AdminURL($this);
    }
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	$strPopup = $this->AdminLink_default_popup($iPopup);
	return clsAdminData_helper::_AdminLink($this,$iText,$strPopup,$iarArgs);
    }
    // END BOILERPLATES
    //----
    //
    // Boilerplate auxiliary functions
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
    // END boilerplate auxiliary
    //----
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
    public function Tree_AddTwig(clsTreeNode $iTwig,$iText) {
	$id = $this->Value('ID');
//	$objSub = $iTwig->Add($id,$iText,$this->ShopURL());
	$objSub = $iTwig->Add($id,$iText,$this->AdminURL());
	return $objSub;
    }
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');
	$doRmvTitles = $wgRequest->GetBool('btnRmvTitles');

	$vgPage->UseHTML();

	$strTitle = 'Topic: '.$this->Value('Name');

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();

	// save edits before re-displaying data

	// edits to this record
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	// topic deletion
	if ($doRmvTitles) {
	    // TO BE WRITTEN
	}

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ctParent	= $objForm->Render('ID_Parent');
	    $ctName	= $objForm->Render('Name');
	    $ctNameTree	= $objForm->Render('NameTree');
	    $ctNameFull	= $objForm->Render('NameFull');
	    $ctNameMeta	= $objForm->Render('NameMeta');
	    $ctUsage	= $objForm->Render('Usage');
	    $ctSort	= $objForm->Render('Sort');
	    $ctVariants	= $objForm->Render('Variants');
	    $ctMispeled	= $objForm->Render('Mispeled');
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
	$wgOut->AddHTML($out);
	$wgOut->AddHTML("<h3>Subtopics</h3>");
	$wgOut->AddHTML($this->SubtopicListing());
	$wgOut->AddHTML("<h3>Titles</h3>");
	$wgOut->AddHTML($this->TitleListing());
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-01-26 adapted from VbzAdminTitle
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());
	    //$objCtrls = $objForm;

	    $objForm->AddField(new clsFieldNum('ID_Parent'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('NameTree'),	new clsCtrlHTML(array('size'=>15)));
	    $objForm->AddField(new clsField('NameFull'),	new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('NameMeta'),	new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Usage'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Sort'),		new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsField('Variants'),	new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsField('Mispeled'),	new clsCtrlHTML(array('size'=>40)));

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
    }
    protected function SubtopicListing() {
	global $wgRequest;

	$htStatus = NULL;

	// set up the entry widget
	$ctrlList = new clsWidget_ShortList();
	$me = $this;
	$arOpts = array(
	  'name'	=> 'topic',
	  'btnChk_Text'	=> 'Enter subtopics:',
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
	global $wgRequest;
	global $vgPage;

	// check for any form actions
	$doChk = $wgRequest->GetBool('btnChkTitles');
	$doAdd = $wgRequest->GetBool('btnAddTitles');
	$doRmv = $wgRequest->GetBool('btnRmvTitles');

	$htStatus = NULL;

	// pass-along vars for anon functions
	$tblTitles = $this->Engine()->Titles();	// for anon function to use
	$tblTitleTopics = $this->Engine()->TitleTopics();
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
	if ($wgRequest->GetBool('btnRmvTitles')) {
	    $arRmv = $wgRequest->GetArray('rmvTitle');
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
		$this->Engine()->TitleTopics()->Delete($sqlFilt);
		$txtRmv .= ' '.$id;
	    }
	    $arEv = array(
	      'descrfin'	=> 'Titles removed:'.$txtRmv
	      );
	    $this->FinishEvent($arEv);
	    $htStatus .= 'Titles removed:'.$txtRmv;
	}

	$out = "\n<form name=\"add-titles\" method=post>";

	$rs = $this->Titles();
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
		$rsDept = $rs->DeptObj();
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
    /*----
      HISTORY:
	2010-10-11 Created -- then discovered that it probably doesn't need
	  to be a separate function... but I think it makes things clearer.
    */
    public function Twigs() {
	if (is_null($this->ID)) {
	    $sql = 'ID_Parent IS NULL';
	} else {
	    $sql = 'ID_Parent='.$this->ID;
	}
	$objRows = $this->Table->GetData($sql,NULL,'Sort,NameTree,Name');
	return $objRows;
    }
    public function DrawTree($iLevel=0,$iRootName="Topics") {
	global $vgPage;

	$out = '';
	$intLevel = $iLevel + 1;
	$strIndent = str_repeat('*',$intLevel);

	$objRows = $this->Twigs();
	if ($objRows->HasRows()) {
	    if (empty($iLevel)) {
		$strName = $this->Name;
		if (empty($strName)) {
		    $strName = $iRootName;
		}
		$out .= "\n{{#tree:id=root|root='''$strName'''|";
		$vgPage->UseWiki();
	    }
	    while ($objRows->NextRow()) {
		$strNameTree = $objRows->NameTree;
		$strTwig = ifEmpty($strNameTree,$objRows->Name);
		$out .= "\n$strIndent".$objRows->AdminLink($strTwig);
		$out .= $objRows->DrawTree($intLevel);
	    }
	    if ($iLevel == 0) {
		$out .= "\n}}";
		$vgPage->UseWiki();
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
}
