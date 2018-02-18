<?php
/*
FILE: topic.php
HISTORY:
  2010-10-13 created for vcPageTopic (was: clsPageTopic)
  2011-01-18 moved clsTopic(s) (later renamed vc*Topic(s)) here from store.php
  2011-01-25 split off page-topic.php (vcPageTopic only) from topic.php (clsTopic(s))
    to resolve dependency-order conflicts
  2011-11-06 Tentatively removed ActionKey from here; later determined that it doesn't belong.
*/
class vctTopics extends vcBasicTable {

    protected $doBranch;

    // ++ SETUP ++ //

    // UNSTUB
    protected function InitVars() {
	$this->doBranch(FALSE);
    }
    // CEMENT
    protected function TableName() {
	return 'cat_topic';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrTopic';
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
    // ++ TABLES ++ //
    
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtTitlesInfo_forTopics');
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    /*----
      PURPOSE: Makes sure the records are sorted properly so that branches don't have
      subtopics listed in silly/arbitrary order
    */
    protected function GetRecords_forTree() {
	return $this->SelectRecords(NULL,'Sort,Name');
    }
    
    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: loads title-per-topic statistics into memory
      PURPOSE: for speeding up indications of where actual stuff-for-sale may be found within topic listings
      OUTPUT: array[ID_Topic] -> # of Titles for sale
      USED BY: Topic tree builder
    */
    public function LoadTitleStats() {
	$tqTitle = $this->TitleInfoQuery();	// CLASS: vcqtTitlesInfo 
	$oq = $tqTitle->SQO_active_byTopic();	// CLASS: fcSQL_Query
	$sql = $oq->Render();
	$rs = $tqTitle->FetchRecords($sql);
	
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$id = $rs->GetFieldValue('ID_Topic');
		$ar[$id] = $rs->GetFieldValue('QtyForSale');
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
	arLayers = topic record values, in a tree:
	  arLayers[ID] = array of IDs of child objects
	  arLayers[ID parent][ID child] = object's Values()
	onTwig = current twig in the tree used for rendering
	arStats = title count by topic
	  arStats[Topic ID] = number of titles in this immediate topic
    */
    public function AddLayer(array $arLayers, fcTreeNode $onTwig, $iID, array $arStats) {
	if (array_key_exists($iID,$arLayers)) {		// if this topic has subtopics...
	    $rc = $this->SpawnRecordset();			// create empty object to handle subtopic data
	    $arLayer = $arLayers[$iID];				// get the list of subtopics for it
	    foreach ($arLayer as $id => $row) {			// for each subtopic...
		$rc->SetFieldValues($row);				// stuff subtopic data into object
		$sSub = $rc->NameTree();				// get subtopic text for display
		if (array_key_exists($id,$arStats)) {			// if there are stats for subtopic...
		    $qTitles = $arStats[$id];					// retrieve them
		    $sSub .= $rc->Tree_FormatTwigStats($qTitles);		// format them & add to display
		}
		$onSub = $rc->Tree_AddTwig($onTwig,$sSub);		// add display string to display tree
		$this->AddLayer($arLayers,$onSub,$id,$arStats);		// check subtopic for subsubtopics
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
	$rs = $this->SelectRecords($sqlFilt);
	return $rs;
    }

    // -- SEARCHING -- //

}
class vcrTopic extends vcShopRecordset {
    protected $didTitles,$hasTitles;

    // ++ SETTINGS ++ //
    
    public function doBranch($iOn=NULL) {
	return $this->GetTableWrapper()->doBranch($iOn);
    }
    
    // -- SETTINGS -- //
    // ++ FIELD VALUES ++ //

    public function ParentID() {
	return $this->GetFieldValue('ID_Parent');
    }
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    public function VariantsText() {
	return $this->GetFieldValue('Variants');
    }
    // PUBLIC so search Page can access it
    public function WrongText() {
	return $this->GetFieldValue('Mispeled');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function HasParent() {
	return !is_null($this->ParentID());
    }
    /*----
      RETURNS: Folder name to use for this topic
    */
    public function FldrName() {
	return sprintf(KS_FMT_TOPICID,$this->GetKeyValue());
    }
    /*----
      RETURNS: Full name for this topic. If NameFull is not set, defaults to Name.
      NOTE: 2016-11-06 Earlier note on 'NameFull' says "sometimes '' gets saved as '' instead of NULL".
	      Not sure what this means.
    */
    public function NameFull() {
	$sFull = $this->GetFieldValue('NameFull');
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
	$strTree = $this->GetFieldValue('NameTree');
	if (empty($strTree)) {
	    $strTree = $this->NameString();
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
    
    // -- FIELD CALCULATIONS -- //
    // ++ STATS ++ //
    
    static private $oStat = NULL;
    static protected function GetStatsManager() {
	if (is_null(self::$oStat)) {
	    self::$oStat = new fcTreeStatsMgr('vctItemsStat');
	}
	return self::$oStat;
    }
    /* 2018-02-08 This appears to be no longer in use.
    public function StatThis() {
	throw new exception('2018-02-08 Does anything still use this?');
	$id = $this->GetKeyValue();
	if (!self::Stats()->IndexExists($id)) {
	    $rs = $this->ItemRecords();	// item records for this title
	    self::Stats()->StatFor($id)->SumItems($rs);	// calculate stats
	}
	return self::Stats()->StatFor($id);
    } */
    /* 2018-02-08 This may be no longer in use.
    public function ItemsForSale() {
	return $this->StatThis()->ItemsForSale();
    } */

    // -- STATUS ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'vctTitles';
    }
    protected function XTitlesClass() {
	return 'vctTitlesTopics';
    }
    protected function ItemsClass() {
	return 'vctItems';
    }
    protected function ImagesClass() {
	return 'vctImages';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make($this->TitlesClass(),$id);
    }
    protected function XTitleTable() {
	return $this->GetConnection()->MakeTableWrapper($this->XTitlesClass());
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
	$rs = $this->XTitleTable()->TitleRecords($this->GetKeyValue());
	return $rs->SQLTitles();
    }
    /*----
      RETURNS: SQL filter for retrieving list of topics at same level
    */
    public function SQL_Filt_Series() {
	if ($this->HasParent()) {
	    $sql = 'ID_Parent='.$this->ParentID();
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
	return $this->GetTableWrapper()->SelectRecords($this->SQL_Filt_Series(),'Sort, NameTree, Name, NameFull');
    }
    /*----
      RETURNS: recordset of topics underneath as this one, properly sorted:
    */
    protected function KidsRecords() {
	$sql = 'ID_Parent='.$this->GetKeyValue();
	$rs = $this->GetTableWrapper()->SelectRecords($sql,'Sort, NameTree');
	return $rs;
    }
    private $rcParent;
    public function ParentRecord() {
	$doGet = TRUE;
	if (!empty($this->rcParent)) {
	    $idParent = $this->ParentID();
	    if ($this->rcParent->GetKeyValue() == $idParent) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->rcParent = $this->GetTableWrapper()->GetRecord_forKey($this->ParentID());
	}
	return $this->rcParent;
    }
    /*----
      RETURNS: dataset of Titles for this Topic
      DEPRECATED - use TitleRecords_forRow()
    */
    public function Titles() {
	$sqlFilt = 'ID_Topic='.$this->GetKeyValue();
	$rsRows = $this->XTitleTable()->TopicRecords($this->GetKeyValue());
	return $rsRows;
    }
    /*----
      RETURNS: dataset of Titles for the current Topic row
    */
    protected function TitleRecords_forRow() {
	$sqlFilt = 'ID_Topic='.$this->GetKeyValue();
	$rs = $this->XTitleTable()->TitleRecords_forID($this->GetKeyValue());
	return $rs;
    }
    /*----
      RETURNS: recordset of Titles, with availability information, for the current Topic row
    */
    protected function TitleRecords_withAvail_forRow() {
	$rs = $this->XTitleTable()->TitleRecords_withAvail_forID($this->GetKeyValue());
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
	$sqlTopicIDs = $this->FetchKeyValues_asSQL();					// SQL list of Topics
	$rs = $this->XTitleTable()->Titles_forIDs_Records($sqlTopicIDs);	// recordset of Titles
	return $rs;
    }
    public function TitleIDs_forRows_Array() {
	$sqlTopicIDs = $this->FetchKeyValues_asSQL();					// SQL list of Topic IDs
	$arTitleIDs = $this->XTitleTable()->Titles_forIDs_Array($sqlTopicIDs);	// SQL list of Title IDs
	return $arTitleIDs;
    }
    protected function ImageRecords($sSize=vctImages::SIZE_THUMB) {
	$rsTi = $this->TitleRecords_forRows();		// recordset of Titles for Topics recordset

	$tIm = $this->ImageTable();
	$rsIm = NULL;
	if (!is_null($rsTi)) {
	    if ($rsTi->RowCount() > 0) {
		$sqlTi = $rsTi->FetchKeyValues_asSQL();			// SQL list of Titles
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
	$sqlFilt = 'ID_Parent='.$this->GetKeyValue();
	$rs = $this->Table()->SelectRecords($sqlFilt,'Sort,ID');
	return $rs;
    }
    protected function ItemRecords() {
	$sqlTtl = $this->TitleTable()->NameSQL();
	$sqlItm = $this->ItemTable()->NameSQL();
	$sqlTxT = $this->XTitleTable()->NameSQL();
	$id = $this->GetKeyValue();
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
	$id = $this->GetKeyValue();
	$ar[$id] = $this->GetFieldValues();
	if ($this->HasParent()) {
	    $this->ParentRecord()->BranchArray($ar);
	}
	return $ar;
    }
    
    // -- ARRAYS -- //
    // ++ ACTIONS ++ //

    public function Tree_AddTwig(fcTreeNode $iTwig,$iText) {
	$id = $this->GetFieldValue('ID');
	$objSub = $iTwig->Add($id,$iText,$this->ShopURL());
	return $objSub;
    }
    public function AddTitle($idTitle) {
	$this->XTitleTable()->SetPair($idTitle,$this->GetKeyValue(),TRUE); 
    }

    // -- ACTIONS -- //

}
