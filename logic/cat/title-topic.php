<?php
/*
  PURPOSE: title-topic assignment classes for VbzCart
  HISTORY:
    2014-03-17 split off from vbz-cat-topic.php
*/
/*%%%%
  PURPOSE: base class for handling title-topic assignments
  HISTORY:
    2013-11-18 started as rewrite of three classes
      clsTitleTopics_base
      clsTitleTopic_Titles
      clsTitleTopic_Topics
*/
class vctTitlesTopics extends fcTable_wName_wSource_wRecords {

    // ++ CEMENT ++ //
    
    protected function TableName() {
	return 'cat_title_x_topic';
    }
    protected function SingularName() {
	return 'clsTitleTopic';
    }

    // -- CEMENT -- //
    // ++ CLASSES ++ //

    protected function TitlesClass() {
	return 'vctTitles';
    }
    protected function TopicsClass() {
	return 'vctTopics';
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make($this->TitlesClass(),$id);
    }
    protected function TopicTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TopicsClass(),$id);
    }
    protected function TitleInfoQuery($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper('vcqtTitlesInfo',$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    //*
    protected function Titles_forFilt_Records($sqlFilt) {
	$tblDest = $this->TitleTable();
	$sqlTNThis = $this->TableName_Cooked();
	$sqlTNDest = $tblDest->TableName_Cooked();
	$sql = "SELECT t.* FROM $sqlTNThis AS x LEFT JOIN $sqlTNDest AS t ON t.ID=x.ID_Title WHERE $sqlFilt";
	$rs = $tblDest->DataSQL($sql);
	return $rs;
    } //*/
    protected function Titles_forFilt_Array($sqlFilt) {
	throw new exception('This has been renamed TitleIDs_forFilt_Array().');
    }
    protected function TitleIDs_forFilt_Array($sqlFilt) {
    /*
	$sqlTNThis = $this->TableName_Cooked();
	$sql = "SELECT ID_Title FROM $sqlTNThis WHERE $sqlFilt";
	$rs = $this->FetchRecords($sql);
    */ // This should be equivalent to the above:
	$rs = $this->SelectRecords($sqlFilt);
	return $rs->FetchColumnValues_asArray('ID_Title');
    } /*
    protected function TitleIDs_forFilt_SQL($sqlFilt) {
	throw new exception('Is anything calling this?');
	$sqlTNThis = $this->TableName_Cooked();
	$sql = "SELECT ID_Title FROM $sqlTNThis WHERE $sqlFilt";
	$rs = $this->GetTableWrapper()->FetchRecords($sql);
	return $rs->ColumnValues_SQL('ID_Title');
    } */
    /*----
      RETURNS: recordset of rows which have the given Topic,
	essentially amounting to a list of Titles for that Topic.
	Recordset is generic, and contains no additional Title or Topic information.
      HISTORY:
	2014-08-14
	  Renamed from TitleRecords() to TitleRecords_forID().
	  Now returns Title recordset instead of TopicXTitle recordset.
    */
    public function TitleRecords_forID($idTopic) {
	if (is_null($idTopic)) {
	    throw new exception('Trying to get titles for NULL topic.');
	}

	$sqlFilt = 'x.ID_Topic='.$idTopic;
	return $this->Titles_forFilt_Records($sqlFilt);
    }
    /*----
      RETURNS: recordset of Titles, with availability information, for the given Topic ID
      HISTORY:
	2016-02-11 written
    */
    public function TitleRecords_withAvail_forID($idTopic) {
	$rs = $this->TitleInfoQuery()->GetRecords_withCatNum_forTopic($idTopic);
	return $rs;
    }
    public function Titles_forIDs_Records($sqlTopicIDs) {
	if (is_null($sqlTopicIDs)) {
	    return NULL;
	} else {
	    $sqlFilt = "ID_Topic IN ($sqlTopicIDs)";
	    return $this->Titles_forFilt_Records($sqlFilt);
	}
    }
    /* 2016-02-21 ended up not using
    public function TitleIDs_forIDs($sqlTopicIDs) {
	if (is_null($sqlTopicIDs)) {
	    return NULL;
	} else {
	    $sqlFilt = "x.ID_Topic IN ($sqlTopicIDs)";
	    return $this->TitleIDs_forFilt($sqlFilt);
	}
    } //*/
    public function Titles_forIDs_Array($sqlTopicIDs) {
	if (is_null($sqlTopicIDs)) {
	    return NULL;
	} else {
	    $sqlFilt = "ID_Topic IN ($sqlTopicIDs)";
	    return $this->TitleIDs_forFilt_Array($sqlFilt);
	}
    }
    /*----
      RETURNS: recordset of rows which have the given Title,
	essentially amounting to a list of Topics for that Title.
	Recordset is generic, and contains no additional Title or Topic information.
      HISTORY:
	2014-08-14
	  Renamed from TopicRecords() to TopicRecords_forID().
	  Now returns Topic recordset instead of TopicXTitle recordset.
    */
    public function TopicRecords_forID($idTitle) {
	if (is_null($idTitle)) {
	    throw new exception('Trying to get topics for NULL title.');
	}
	$tblDest = $this->TopicTable();
	$sqlFilt = 'x.ID_Title='.$idTitle;
	$sqlTNThis = $this->TableName_Cooked();
	$sqlTNDest = $tblDest->TableName_Cooked();
	$sql = "SELECT t.* FROM $sqlTNThis AS x LEFT JOIN $sqlTNDest AS t ON t.ID=x.ID_Topic WHERE $sqlFilt";
	$rs = $tblDest->FetchRecords($sql);
	return $rs;
    }
    public function TopicRecords_forIDs($sqlTopicIDs) {
	if (is_null($sqlTopicIDs)) {
	    return NULL;
	} else {
	    $sqlFilt = "ID IN ($sqlTopicIDs)";
	    $rs = $this->TopicTable()->GetData($sqlFilt);
	    return $rs;
	}
    }
    public function GetTopics($iID) {
	throw new exception('GetTopics() is deprecated; use TopicRecords_forID[s]().');
    }
    public function GetTitles($iID) {
	throw new exception('GetTitles() is deprecated; use TitleRecords_forID[s]().');
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    public function SetPair($idTitle,$idTopic,$doPair) {
	$sqlFilt = "(ID_Title=$idTitle) AND (ID_Topic=$idTopic)";
	$rs = $this->SelectRecords($sqlFilt);
	if ($rs->HasRows() != $doPair) {
	    // need to change state
	    if ($doPair) {
		$ar = array(
		  'ID_Title'	=> $idTitle,
		  'ID_Topic'	=> $idTopic
		  );
		$this->Insert($ar);
	    } else {
		$this->Delete($sqlFilt);
	    }
	}
    }
    /*----
      ASSUMES: state is changing; does not bother to check for redundant action.
      LATER: add an option for this, but only if it would actually be useful.
      HISTORY:
	2011-03-03 written for topic-deletion portion of title admin page
	2013-11-18 copied from clsTitleTopics_base to vctTitlesTopics (was clsTitlesTopics)
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

    // ++ ACTIONS ++ //

}
class clsTitleTopic extends fcDataRecord {

    // ++ FIELD VALUES ++ //

    public function TitleID() {
	return $this->Value('ID_Title');
    }

    // -- FIELD VALUES -- //
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      RETURNS: list of Titles from the current recordset
      FORMAT: comma-separated string suitable for use with SQL "IN" operator
      HISTORY:
	2013-11-18 created as part of general catalog rewrite
    */
    public function SQLTitles() {
	$sql = NULL;
	while ($this->NextRow()) {
	    $sql .= is_null($sql)?'':',';
	    $sql .= $this->Value('ID_Title');
	}
	return $sql;
    }
    /*----
      RETURNS: list of Topics from the current recordset
      FORMAT: comma-separated string suitable for use with SQL "IN" operator
      HISTORY:
	2013-11-18 created as part of general catalog rewrite
    */
    public function SQLTopics() {
	$sql = NULL;
	while ($this->NextRow()) {
	    $sql .= is_null($sql)?'':',';
	    $sql .= $this->Value('ID_Topic');
	}
	return $sql;
    }
    
    // -- SQL CALCULATIONS -- //

}
