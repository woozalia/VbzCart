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

    // ++ SETUP ++ //

    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_topic');
	  $this->KeyName('ID');
	  $this->ClassSng('clsTopic');
//	  $this->ActionKey('cat.topic');
	  $this->doBranch(FALSE);
    }

    // -- SETUP -- //
    // ++ SHOPPING WEB UI ++ //
    // This should probably be in vbz-cat-topic-ui

    public function DoIndex() {
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

	return $objSection->out;
    }

    // -- SHOPPING WEB UI -- //
    // ++ GENERAL WEB UI ++ //

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

    // -- GENERAL WEB UI -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: loads title-per-topic statistics into memory
      PURPOSE: for speeding up indications of where actual stuff-for-sale may be found within topic listings
      USED BY: ?
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
    /* 2014-03-17 this was apparently never finished
    protected function UpdateTitleStats() {
	$ar = $this->LoadTitleStats();
	foreach ($ar as $id => $cnt) {
	}
    }
    */
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

    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

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

    // -- ACTIONS -- //
    // ++ SEARCHING ++ //

    public function SearchRecords_forText($sSearch) {	// alias, for now
	return $this->Search_forText($sSearch);
    }
    public function Search_forText($sSearch) {
	$sqlFilt = <<<__END__
(Name LIKE "%$sSearch%") OR
(Variants LIKE "%$sSearch%") OR
(Mispeled LIKE "%$sSearch%")
__END__;
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }
    // options
    public function doBranch($iOn=NULL) {
	if (!is_null($iOn)) {
	    $this->doBranch = $iOn;
	}
	return $this->doBranch;
    }

    // -- SEARCHING -- //

}
class clsTopic extends clsVbzRecs {
    protected $objParent;
    protected $didTitles,$hasTitles;

    // ++ STATIC ++ //

    static private $oStat = NULL;
    static protected function Stats() {
	if (is_null(self::$oStat)) {
	    self::$oStat = new clsStatsMgr('clsItemsStat');
	}
	return self::$oStat;
    }

    // -- STATIC -- //
    // ++ STATUS ACCESS ++ //

    public function HasParent() {
	return !is_null($this->Value('ID_Parent'));
    }
    /*----
      RETURNS: Folder name to use for this topic
    */
    public function FldrName() {
	return sprintf(KS_FMT_TOPICID,$this->KeyValue());
    }
    public function StatThis() {
	$id = $this->KeyValue();
	if (!self::Stats()->IndexExists($id)) {
	    $rs = $this->ItemRecords();	// item records for this title
	    self::Stats()->StatFor($id)->SumItems($rs);	// calculate stats
	}
	return self::Stats()->StatFor($id);
    }
    public function ItemsForSale() {
	return $this->StatThis()->ItemsForSale();
    }

    // -- STATUS ACCESS -- //
    // ++ FIELD ACCESS ++ //

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
    public function ShopURL() {
	return KWP_TOPICS_REL.$this->FldrName();
    }

    // -- FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'clsVbzTitles';
    }
    protected function XTitlesClass() {
	return 'clsTitlesTopics';
    }
    protected function ItemsClass() {
	return 'clsItems';
    }
    protected function ImagesClass() {
	return 'clsImages';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make($this->TitlesClass(),$id);
    }
    protected function XTitleTable() {
	return $this->Engine()->Make($this->XTitlesClass());
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    protected function ImageTable($id=NULL) {
	return $this->Engine()->Make($this->ImagesClass(),$id);
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    public function ParentRecord() {
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
      DEPRECATED - use TitleRecords_forRow()
    */
    public function Titles() {
	$sqlFilt = 'ID_Topic='.$this->KeyValue();
//	$sql = 'SELECT ID_Title FROM `'.ksTbl_title_topics.'` WHERE '.$sqlFilt;
//	$rsRows = $this->objDB->DataSet($sql);
	//$rsRows = $this->XTitleTable()->GetData($sqlFilt);
	$rsRows = $this->XTitleTable()->TopicRecords($this->KeyValue());
	return $rsRows;
    }
    /*----
      RETURNS: dataset of Titles for the current Topic row
    */
    protected function TitleRecords_forRow() {
	$sqlFilt = 'ID_Topic='.$this->KeyValue();
	$rs = $this->XTitleTable()->TitleRecords_forID($this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: dataset of Titles for all topics in the current dataset
      PUBLIC so topic search can use it to display titles for found topics
    */
    public function TitleRecords_forRows() {
	$sqlTopicIDs = $this->KeyListSQL();					// SQL list of Topics
	$rs = $this->XTitleTable()->TitleRecords_forIDs($sqlTopicIDs);	// recordset of Titles
	return $rs;
    }
    protected function ImageRecords($sSize=clsImages::SIZE_THUMB) {
	$rsTi = $this->TitleRecords_forRows();		// recordset of Titles for Topics recordset

//	echo 'TOPIC ROW COUNT=['.$this->RowCount().']<br>';
//	$this->RewindRows();
//	while ($this->NextRow()) {
//	    echo 'TITLE ID: '.$this->KeyValue().' - '.$this->NameFull().'<br>';
//	}
//	echo 'TITLE ROW COUNT=['.$rsTi->RowCount().']<br>';
//	echo 'SQL='.$rsTi->sqlMake.'<br>';
//	while ($rsTi->NextRow()) {
//	    echo 'TITLE ID: '.$rsTi->KeyValue().' - '.$rsTi->TitleStr().'<br>';
//	}

	$tIm = $this->ImageTable();
	$rsIm = NULL;
	if (!is_null($rsTi)) {
	    if ($rsTi->RowCount() > 0) {
		$sqlTi = $rsTi->KeyListSQL();			// SQL list of Titles
		//$sqlFilt = "(Ab_Size='$sSize') AND (ID_Title IN ($sqlTi))";
		//$rsIm = $tIm->GetData($sqlFilt);
		$rsIm = $tIm->Records_forTitles_SQL($sqlTi,$sSize);
	    }
	}
	return $rsIm;
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
      USAGE: called by Topic rendering page
      RETURNS: array of information about titles for this topic
	For each title:
	    [row] = raw Title record
	    [stats] = count of active and retired (not for sale) items
	      [cnt.act] = count of active (for sale) items
	      [cnt.ret] = count of retired (no longer for sale) items
    */
    protected function TitleRecords() {
	throw new exception('TitleRecords() is deprecated; call TitleRecords_forRow() or  TitleRecords_forRow.() Consider also ImageRecords().');
//	$db = $this->Engine();
//	$rs = $db->TitlesTopics()->TitleRecords($this->KeyValue());
//	$ar = NULL;

	$sqlT = $this->TitleRecordsSQL();
	if (is_null($sqlT)) {
	    $rs = NULL;
	} else {
	    $rs = $this->TitleTable()->GetData("ID IN ($sqlT)");
	}
	return $rs;
/*
	$ar = NULL;
	if ($rs->HasRows()) {
	    $sql = $rs->SQLTitles();
	    $rs = $this->TitleTable()->GetData("ID IN ($sql)");

	    // save results into an array and object
	    while ($rs->NextRow()) {

		// BASIC RECORD for this title

		$id = $rs->KeyValue();
		$ar[$id]['row'] = $rs->Values();

		// ITEM COUNTS for this title

		$arStat = $rs->FigureCounts();
		$ar[$id]['stats'] = $arStat;
	    }
	}
	return $ar;
	*/
    }
    protected function ItemRecords() {
	$sqlTtl = $this->TitleTable()->NameSQL();
	$sqlItm = $this->ItemTable()->NameSQL();
	$sqlTxT = $this->XTitleTable()->NameSQL();
	$id = $this->KeyValue();
	$sql = <<<__END__
SELECT i.*
FROM ($sqlItm AS i
LEFT JOIN $sqlTtl AS t
ON i.ID_Title=t.ID)
LEFT JOIN $sqlTxT as tt
ON t.ID=tt.ID_Title
WHERE tt.ID_Topic=$id;
__END__;

	$rs = $this->Engine()->Query($sql,$this->ItemTable()->ClassSng());
	return $rs;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ SQL CALCULATIONS ++ //

    /*----
      RETURNS: Titles for this Topic
      FORMAT: string consisting of title IDs suitable for use
	in a SQL "IN ()" clause.
    */
    protected function TitleRecordsSQL() {
	$rs = $this->XTitleTable()->TitleRecords($this->KeyValue());
	return $rs->SQLTitles();
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

    // -- SQL CALCULATIONS -- //
    // ++ RECORDSETS AND RECORD ARRAYS ++ //

    /*----
      RETURNS: recordset of topics at the same level as this one, properly sorted.
    */
    protected function SeriesRecords() {
	$rs = $this->Table->GetData($this->SQL_Filt_Series(),NULL,'Sort, NameTree, Name, NameFull');
	return $rs;
    }
    /*----
      RETURNS: an array of topics from the current to the root
      HISTORY:
	2013-11-16 created so we can have less rendering code
	  inside the logic objects
	2014-03-22 renamed FigureBranch() to BranchArray() for consistency
    */
    public function BranchArray(array &$ar=NULL) {
	$id = $this->KeyValue();
	$ar[$id] = $this->Values();
	if ($this->HasParent()) {
	    $this->ParentRecord()->BranchArray($ar);
	}
	return $ar;
    }
    /*----
      RETURNS: recordset of topics underneath as this one, properly sorted:
    */
    protected function KidsRecords() {
	$sql = 'ID_Parent='.$this->KeyValue();
	$rs = $this->Table()->GetData($sql,NULL,'Sort, NameTree');
	return $rs;
    }
    /*----
      RETURNS: array of topics underneath as this one, properly sorted:
	ar[id] = HTML to display for that topic
      HISTORY:
	2014-03-22 replaced by KidsRecords()
    */
    /*
    protected function FigureKids() {
	$sql = 'ID_Parent='.$this->KeyValue();
	$rs = $this->Table->GetData($sql,NULL,'Sort, NameTree');
	$ar = NULL;
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$idRow = $rs->KeyValue();
		$ar[$idRow] = $rs->Values();
	    }
	}
	return $ar;
    }*/

    // -- RECORDSETS AND RECORD ARRAYS -- //
    // ++ ARRAY CALCULATIONS ++ //

    /*----
      PURPOSE: Does the heavy-lifting for rendering the page,
	but does not actually format it.
      ASSUMES: there is a recordset loaded
      RETURNS: multi-tiered array of data needed to render the page
	[ser] = recordset of topics at the same level
	[pth] = array of topics leading back to root
	[sub] = recordset of topics under this one
	[ttl] = recordset of titles for this topic
	[img] = recordset of title-images for this topic
      HISTORY:
	2013-11-16 started as a rewrite of DoPage()
	2014-03-22 most array elements are now returned as recordsets instead of arrays
	2014-08-14 Removed check for ID field -- caller should ensure this.
    */
    /*
    public function FigurePage() {
	$arPth = $this->BranchArray();		// path from this to root
	$rsSer = $this->SeriesRecords();	// topics at same level as this one
	$rsSub = $this->KidsRecords();		// topics under this one
	$rsTtl = $this->TitleRecords_forRow();	// titles for this topic (Title recordset)
	$rsImg = $rsTtl->ImageRecords_thumb();	// images for those titles (recordset) (defaults to thumbsize)

	$arOut = array(
	  'ser' => $rsSer,
	  'pth' => $arPth,
	  'sub' => $rsSub,
	  'ttl' => $rsTtl,
	  'img' => $rsImg
	  );
	return $arOut;
    }*/
    /*----
      ACTION: Processes data for all titles assigned to this topic, and generates various results from this data.
      HISTORY:
	2011-02-23 Split off from DoPage() for SpecialVbzCart
    */
    /* 2014-08-18 no longer used
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
		$tImgs = $this->Engine()->Images();

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

			$rsImgs = $tImgs->Records_forTitle($id,KS_IMG_SIZE_THUMB);
			$ftImgs = $htLink.$rsImgs->RenderInline_set(array('title'=>$txtTitle)).'</a>';
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
    */

    // -- ARRAY CALCULATIONS -- //
    // ++ ACTIONS ++ //

    public function Tree_AddTwig(clsTreeNode $iTwig,$iText) {
	$id = $this->Value('ID');
	$objSub = $iTwig->Add($id,$iText,$this->ShopURL());
	return $objSub;
    }

    // -- ACTIONS -- //

/* 2014-08-18 This is no longer used. I can't even find where it used to be called from.
    public function DoPiece_Thumbs_Avail() {
	$this->DoFigure_Titles();
	$arInfo = $this->arTitleInfo;
	return $arInfo['img.act'];
    }
    */
    public function doBranch($iOn=NULL) {
	return $this->Table->doBranch($iOn);
    }

}
