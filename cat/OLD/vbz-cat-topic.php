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
class clsTopics extends vcVbzTable_shop {
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
    // ++ OPTIONS ++ //

    public function doBranch($iOn=NULL) {
	if (!is_null($iOn)) {
	    $this->doBranch = $iOn;
	}
	return $this->doBranch;
    }

    // -- OPTIONS -- //
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

    // -- SEARCHING -- //

}
class clsTopic extends vcVbzRecs_shop {
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
    // ++ OPTIONS ++ //
    
    public function doBranch($iOn=NULL) {
	return $this->Table()->doBranch($iOn);
    }
    
    // -- OPTIONS -- //
    // ++ STATUS ACCESS ++ //

    public function HasParent() {
	return !is_null($this->ParentID());
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

    public function ParentID() {
	return $this->Value('ID_Parent');
    }
    public function NameString() {
	return $this->Value('Name');
    }
    /*----
      RETURNS: Full name for this topic. If NameFull is not set, defaults to Name.
    */
    public function NameFull() {
	$sFull = $this->ValueNz('NameFull','');	// sometimes '' gets saved as '' instead of NULL
	if ($sFull == '') {
	    return $this->NameString();
	} else {
	    return $sFull;
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
    // ++ TABLES ++ //

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

    // -- TABLES -- //
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
    // ++ RECORDS ++ //

    /*----
      RETURNS: recordset of topics at the same level as this one, properly sorted.
    */
    protected function SeriesRecords() {
	$rs = $this->Table->GetData($this->SQL_Filt_Series(),NULL,'Sort, NameTree, Name, NameFull');
	return $rs;
    }
    /*----
      RETURNS: recordset of topics underneath as this one, properly sorted:
    */
    protected function KidsRecords() {
	$sql = 'ID_Parent='.$this->KeyValue();
	$rs = $this->Table()->GetData($sql,NULL,'Sort, NameTree');
	return $rs;
    }
    private $rcParent;
    public function ParentRecord() {
	$doGet = TRUE;
	if (!empty($this->rcParent)) {
	    $idParent = $this->ParentID();
	    if ($this->rcParent->KeyValue() == $idParent) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->rcParent = $this->Table()->GetItem($this->ParentID());
	}
	return $this->rcParent;
    }
    /*----
      RETURNS: dataset of Titles for this Topic
      DEPRECATED - use TitleRecords_forRow()
    */
    public function Titles() {
	$sqlFilt = 'ID_Topic='.$this->KeyValue();
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
      RETURNS: recordset of Titles, with availability information, for the current Topic row
    */
    protected function TitleRecords_withAvail_forRow() {
	$rs = $this->XTitleTable()->TitleRecords_withAvail_forID($this->KeyValue());
	if (is_null($rs)) {
	    throw new exception('Unable to generate recordset. SQL:<pre>'.$rs->sqlMake.'</pre>');
	}
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
    public function SubtopicRecords() {
	$sqlFilt = 'ID_Parent='.$this->KeyValue();
	$rs = $this->Table()->GetRecords($sqlFilt,'Sort,ID');
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

    // -- RECORDS -- //
    // ++ ARRAYS ++ //

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
    
    // -- ARRAYS -- //
    // ++ ACTIONS ++ //

    public function Tree_AddTwig(clsTreeNode $iTwig,$iText) {
	$id = $this->Value('ID');
	$objSub = $iTwig->Add($id,$iText,$this->ShopURL());
	return $objSub;
    }
    public function AddTitle($idTitle) {
	$this->XTitleTable()->SetPair($idTitle,$this->KeyValue(),TRUE); 
    }

    // -- ACTIONS -- //
    // ++ DISUSED ++ //

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

/* 2014-08-18 This is no longer used. I can't even find where it used to be called from.
    public function DoPiece_Thumbs_Avail() {
	$this->DoFigure_Titles();
	$arInfo = $this->arTitleInfo;
	return $arInfo['img.act'];
    }
    */

    // -- DISUSED -- //

}
