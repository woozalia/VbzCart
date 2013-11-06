<?php
/*
FILE: topic.php
HISTORY:
  2010-10-13 created for clsPageTopic
  2011-01-18 moved clsTopic(s) here from store.php
  2011-01-25 split off page-topic.php (clsPageTopic only) from topic.php (clsTopic(s))
    to resolve dependency-order conflicts
  2011-11-06 If there's a reason clsTopics needs to set ActionKey, it should be documented. Commented out for now.
*/
class clsTopics extends clsVbzTable {
    protected $ctrlTree;
    protected $doBranch;

    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_topic');
	  $this->KeyName('ID');
	  $this->ClassSng('clsTopic');
//	  $this->ActionKey('cat.topic');
	  $this->doBranch(FALSE);
    }
    public function DoIndex() {
	CallEnter($this,__LINE__,'clsTopics.DoIndex()');
	$objSection = new clsPageOutput();

	$objTopics = $this->GetData('ID_Parent IS NULL',NULL,'Sort,Name,NameTree');
	$isFirst = TRUE;
	while ($objTopics->NextRow()) {
	    if ($isFirst) {
		$isFirst = FALSE;
		$objSection->SectionHdr('Root Topics');
	    } else {
		$objSection->AddText($objTopics->Name.'<br>');
	    }
	}

	CallExit('clsTopic.DoIndex()');
	return $objSection->out;
    }
    /*----
      USED BY: both store UI and admin UI
    */
    public function RenderTree($iRebuild) {
	$objCache = new clsCacheFile_vbz();
	$fnBase = $this->ClassSng().'.tree';
	if ($iRebuild || !$objCache->Exists($fnBase)) {
	    $out = $this->BuildTree();		// build the topic tree display
	    $objCache->Write($fnBase,$out);	// save it to the cache
	} else {
	    $out = $objCache->Read($fnBase);	// rad the topic tree display from the cache
	}
	return $out;
    }
    /*----
      ACTION: loads title-per-topic statistics into memory
      PURPOSE: for speeding up indications of where actual stuff-for-sale may be found within topic listings
    */
    public function LoadTitleStats() {
	$rs = $this->Engine()->DataSet('SELECT * FROM qryTitleTopic_Title_avail');
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$id = $rs->Value('ID_Topic');
		$ar[$id] = $rs->Value('cntForSale');
	    }
	} else {
	    $ar = NULL;
	}
	return $ar;
    }
    protected function UpdateTitleStats() {
	$ar = $this->LoadTitleStats();
	foreach ($ar as $id => $cnt) {
	}
    }
    public function IndexLink($iShow) {
	return '<a href="'.KWP_TOPICS_REL.'" title="topics: master index" class="dark-bg">'.$iShow.'</a>';
    }
    public function TreeCtrl() {
	if (is_null($this->ctrlTree)) {
	    $this->ctrlTree = new clsDTreeAPI(KWP_TOOLS_DTREE);
	    $this->ctrlTree->FileForCSS('dtree-dark.css');	// use CSS for dark background
	}
	return $this->ctrlTree;
    }
    public function RenderPageHdr() {
	$out = $this->TreeCtrl()->RenderPageHdr();
	return $out;
    }
    protected function BuildTree() {
	// update title stats for each topic

	// build reference array for tree structure
	$objRows = $this->GetData();
	while ($objRows->NextRow()) {
	    $id = $objRows->ID;
	    $idParent = $objRows->ID_Parent;
	    if (empty($idParent)) {
		//$idParent = -1;	// parent is fake root node
		$idParent = 0;	// parent is fake root node
	    }
	    //$objCopy = $objRows->RowCopy();
	    //$arLayer[$idParent][$id] = $objCopy;
	    $arLayer[$idParent][$id] = $objRows->Values();
	}

	$objTree = $this->TreeCtrl();
	$objRoot = $objTree->RootNode();
	//$objRoot->OneRoot(TRUE);
	$objFakeRoot = $objRoot->Add(0,'Topics');
	//$objRoot->TextShow('Topics');
	//$objRoot->Add(-1,'Topics');
	//$this->AddLayer($arLayer,$objRoot,-1);	// build the node tree
	$ar = $this->LoadTitleStats();
	$this->AddLayer($arLayer,$objFakeRoot,0,$ar);	// build the node tree
	$out = $objTree->RenderPageHdr();	// this belongs in a different place eventually
	$out .= $objRoot->RenderTree();

	return $out;
    }
    /*----
      ACTION: Recursively adds an array of twigs to the given node
      INPUT:
	iLayers[ID] = array of IDs of child objects
	iLayers[ID parent][ID child] = object's Values()
    */
    public function AddLayer(array $iLayers, clsTreeNode $iTwig, $iID, array $iTitleStats) {
//	if (isset($iLayers[$iID])) {
	if (array_key_exists($iID,$iLayers)) {
	    $obj = $this->SpawnItem();
	    $arLayer = $iLayers[$iID];
	    foreach ($arLayer as $id => $row) {
		$obj->Values($row);
		$strShow = $obj->NameTree();
		if (array_key_exists($id,$iTitleStats)) {
		    $cntTitles = $iTitleStats[$id];
		    $strShow .= $obj->Tree_RenderTwig($cntTitles);
/*
		    $txtNoun = ' title'.Pluralize($cntTitles).' available for topic #'.$id;
		    $strShow .= ' [<b><span style="color: #00cc00;" title="'.$cntTitles.$txtNoun.'">'.$cntTitles.'</span></b>]';
*/
		}
		//$objSub = $iTwig->Add($id,$strShow,$obj->ShopURL());
		$objSub = $obj->Tree_AddTwig($iTwig,$strShow);
		$this->AddLayer($iLayers,$objSub,$id,$iTitleStats);
	    }
	}
    }
    // options
    public function doBranch($iOn=NULL) {
	if (!is_null($iOn)) {
	    $this->doBranch = $iOn;
	}
	return $this->doBranch;
    }
}
class clsTopic extends clsDataSet {
    protected $objParent;
    protected $didTitles,$hasTitles;

    public function Tree_RenderTwig($iCntTitles) {
	$cntTitles = $iCntTitles;
	$txtNoun = ' title'.Pluralize($cntTitles).' available';	// for topic #'.$id;
	$out = ' [<b><span style="color: #00cc00;" title="'.$cntTitles.$txtNoun.'">'.$cntTitles.'</span></b>]';
	return $out;
    }
    public function Tree_AddTwig(clsTreeNode $iTwig,$iText) {
	$id = $this->Value('ID');
	$objSub = $iTwig->Add($id,$iText,$this->ShopURL());
	return $objSub;
    }
    public function HasParent() {
	return !is_null($this->Value('ID_Parent'));
    }
    public function ParentObj() {
	$doGet = TRUE;
	if (!empty($this->objParent)) {
	    $idParent = $this->Value('ID_Parent');
	    if ($this->objParent->KeyValue() == $idParent) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->objParent = $this->Table->GetItem($this->Value('ID_Parent'));
	}
	return $this->objParent;
    }
    /*----
      RETURNS: dataset of Titles for this Topic
    */
    public function Titles() {
	$sqlFilt = 'ID_Topic='.$this->ID;
//	$sql = 'SELECT ID_Title FROM `'.ksTbl_title_topics.'` WHERE '.$sqlFilt;
//	$objRows = $this->objDB->DataSet($sql);
	$objRows = $this->Engine()->TitleTopic_Titles()->GetData($sqlFilt);
	return $objRows;
    }
    /*----
      RETURNS: dataset of sub-Topics for this topic
    */
    public function Subtopics() {
	$sqlFilt = 'ID_Parent='.$this->KeyValue();
	$rs = $this->Table->GetData($sqlFilt,NULL,'Sort,Name');
	return $rs;
    }
    /*----
      RETURNS: SQL filter for retrieving list of topics at same level
    */
    public function SQL_Filt_Series() {
	if ($this->HasParent()) {
	    $sql = 'ID_Parent='.$this->Value('ID_Parent');
	} else {
	    $sql = 'ID_Parent IS NULL';
	}
	return $sql;
    }
    /*
      ACTION: Processes data for all titles assigned to this topic, and generates various results from this data.
      HISTORY:
	2011-02-23 Split off from DoPage() for SpecialVbzCart
    */
    protected function DoFigure_Titles() {
	if (empty($this->didTitles)) {
	    $obj = $this->Engine()->TitleTopic_Titles()->GetTopic($this->KeyValue());

	    $hasRows = $obj->HasRows();
	    if ($hasRows) {
		// save results into an array and object
		$arTitles = NULL;
		$objDoc = new clsRTDoc_HTML();
		while ($obj->NextRow()) {
		    $id = $obj->Value('ID');
		    $arTitles[$id] = $obj->Row;
		}
		
		$obj = $this->Engine()->Titles()->SpawnItem();
		$objImgs = $this->Engine()->Images();

		$ftTextRetired = '';
		$ftImgsAct = '';
		$ftImgsRet = '';
		if (is_null($arTitles)) {
		    $objTbl = NULL;
		} else {
		    $objTbl = new clsRTTable_HTML();
		    $objRow = $objTbl->NewHeader();
		      $objRow->NewCell('Description');
		      $objRow->NewCell('Available');
		      $objRow->NewCell('In Stock');
		    foreach ($arTitles as $id => $row) {
			$obj->Values($row);

			$arStats = $obj->Indicia(array('class'=>'thumb'));
			$intActive = $arStats['cnt.active'];
			$txtCatNum = $arStats['txt.cat.num'];
			$ftLine = $arStats['ht.cat.line'];
			$htLink = $arStats['ht.link.open'];
			$txtName = $obj->Value('Name');
			$txtTitle = $txtCatNum.' &ldquo;'.$txtName.'&rdquo;';

			$ftImgs = $htLink.$objImgs->Thumbnails($id,array('title'=>$txtTitle)).'</a>';
			if ($intActive) {
			    //$ftTextActive .= $ftLine.' - '.$intActive.' item'.Pluralize($intActive).'<br>';
			    $arTypes = $obj->Summary_ItTyps('<br>');
			    $objRow = $objTbl->NewRow();
			      $objRow->NewCell($ftLine);
			      $objRow->NewCell($arTypes['text.!num']);
			      $objRow->NewCell($arTypes['html.qty']);
			    $ftImgsAct .= $ftImgs;
			} else {
			    $ftTextRetired .= $ftLine.'<br>';
			    $ftImgsRet .= $ftImgs;
			}
		    }
		    if ($intActive == 0) {
			$objTbl = NULL;
		    }
		}

		$arInfo = array(
		  'img.act'	=> $ftImgsAct,
		  'img.ret'	=> $ftImgsRet,
		  'obj.act'	=> $objTbl,
		  'txt.ret'	=> $ftTextRetired
		  );
		$this->arTitleInfo = $arInfo;
	    } else {
		$this->arTitleInfo = array();
	    }
	    $this->didTitles = TRUE;
	    $this->hasTitles = $hasRows;
	}
    }
    public function DoPiece_Thumbs_Avail() {
	$this->DoFigure_Titles();
	$arInfo = $this->arTitleInfo;
	return $arInfo['img.act'];
    }
    /*=====
      RETURNS: Folder name to use for this topic
    */
    public function FldrName() {
	return sprintf(KS_FMT_TOPICID,$this->ID);
    }
    /*----
      RETURNS: Full name for this topic. If NameFull is not set, defaults to Name.
    */
    public function NameFull() {
	$strFull = $this->ValueNz('NameFull');
	if (is_null($strFull)) {
	    return $this->Value('Name');
	} else {
	    return $this->Value('NameFull');
	}
    }
    /*----
      RETURNS: Name to use in tree structure. If NameTree not set, defaults to Name.
    */
    public function NameTree() {
	$strTree = $this->Value('NameTree');
	if (empty($strTree)) {
	    $strTree = $this->Value('Name');
	}
	return $strTree;
    }
    public function NameMeta() {
	if (is_null($this->Value('NameMeta'))) {
	    if (is_null($this->Value('NameFull'))) {
		$out = $this->Value('Name');
	    } else {
		$out = $this->Value('NameFull');
	    }
	} else {
	    $out = $this->Value('NameMeta');
	}
	return $out;
    }
    /*****
      FUNCTIONS: Link generation
    */
    public function doBranch($iOn=NULL) {
	return $this->Table->doBranch($iOn);
    }
    public function LinkOpen() {
	if ($this->doBranch()) {
	    $txtNameFull = $this->RenderBranch_text();
	} else {
	    $txtNameFull = htmlspecialchars($this->NameFull());
	}
	return '<a class="dark-bg" href="'.$this->ShopURL().'" title="'.$txtNameFull.'">';
    }
    public function ShopURL() {
	return KWP_TOPICS_REL.$this->FldrName();
    }
    public function ShopLink($iShow=NULL) {
	$txtShow = is_null($iShow)?($this->Value('Name')):$iShow;

	$out = $this->LinkOpen().$txtShow.'</a>';
	return $out;
    }
    public function RenderBranch($iSep=" &larr; ") {
	$out = $this->ShopLink();
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentObj()->RenderBranch($iSep);
	}
	return $out;
    }
    public function RenderBranch_text($iSep=" &larr; ") {
	$out = $this->Value('Name');
	if ($this->HasParent()) {
	    $out .= $iSep.$this->ParentObj()->RenderBranch_text($iSep);
	}
	return $out;
    }
}
/*====
  PURPOSE: base class for handling title-topics
    Can also be used for writing to the table
    Use descendant classes for reading
  NOTE: why is this descended from clsTopics?
*/
class clsTitleTopics_base extends clsTopics {
    public function __construct(clsDatabase $iDB) {
	//$objIdx = new clsIndexer_Table_multi_key($this);
	//$objIdx->KeyNames(array('ID_Title','ID_Topic'));
	parent::__construct($iDB);
	  $this->Name('cat_title_x_topic');
	  //$this->ClassSng('clsCacheFlow');
	  $this->doBranch(FALSE);
    }
    // options
    public function doBranch($iOn=NULL) {
	if (!is_null($iOn)) {
	    $this->doBranch = $iOn;
	}
	return $this->doBranch;
    }
    public function GetItem($iID=NULL) {
	return $this->Engine()->Topics($iID);
    }
    /*----
      ASSUMES: state is changing; does not bother to check for redundant action.
      LATER: add an option for this, but only if it would actually be useful.
      HISTORY:
	2011-03-03 written for topic-deletion portion of title admin page
    */
    public function DelTopics($iTitle, array $arTopics) {
	$cnt = 0;
	foreach ($arTopics as $id => $on) {
	    $sql = 'DELETE FROM '.$this->Name().' WHERE ID_Title='.$iTitle.' AND ID_Topic='.$id;
	    $ok = $this->Engine()->Exec($sql);
	    if ($ok) { $cnt++; }
	}
	return $cnt;
    }
}
class clsTitleTopic_Titles extends clsTitleTopics_base {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->Name('qryTitleTopic_Titles');
	  $this->ClassSng('clsVbzTitle');
    }
    /*----
      RETURNS: recordset of rows which have this Topic,
	essentially amounting to a list of Titles for the Topic.
    */
    public function GetTopic($iID) {
	$sql = 'ID_Topic='.$iID;
	$rs = $this->GetData($sql);
	return $rs;
    }
}
class clsTitleTopic_Topics extends clsTitleTopics_base {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->Name('qryTitleTopic_Topics');
	  $this->ClassSng('clsTopic');
	  $this->doBranch(TRUE);
    }
    /*----
      RETURNS: recordset of rows which have this Title,
	essentially amounting to a list of Topics for this Title.
    */
    public function GetTitle($iID) {
	$sql = 'ID_Title='.$iID;
	$rs = $this->GetData($sql);
	return $rs;
    }
}