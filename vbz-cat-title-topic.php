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
class clsTitlesTopics extends clsTable_abstract {

    // ++ SETUP ++ //

    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_title_x_topic');
	  $this->ClassSng('clsTitleTopic');
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'clsVbzTitles';
    }
    protected function TopicsClass() {
	return 'clsTopics';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make($this->TitlesClass(),$id);
    }
    protected function TopicTable($id=NULL) {
	return $this->Engine()->Make($this->TopicsClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ DATA RECORDS ++ //

    protected function TitleRecords_forFilt($sqlFilt) {
	$tblDest = $this->TitleTable();
	$sqlTNThis = $this->NameSQL();
	$sqlTNDest = $tblDest->NameSQL();
	$sql = "SELECT t.* FROM $sqlTNThis AS x LEFT JOIN $sqlTNDest AS t ON t.ID=x.ID_Title WHERE $sqlFilt";
	$rs = $tblDest->DataSQL($sql);
	return $rs;
    }
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
	return $this->TitleRecords_forFilt($sqlFilt);
    }
    public function TitleRecords_forIDs($sqlTopicIDs) {
	if (is_null($sqlTopicIDs)) {
	    return NULL;
	} else {
	    $sqlFilt = "x.ID_Topic IN ($sqlTopicIDs)";
	    return $this->TitleRecords_forFilt($sqlFilt);
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
	$sqlTNThis = $this->NameSQL();
	$sqlTNDest = $tblDest->NameSQL();
	$sql = "SELECT t.* FROM $sqlTNThis AS x LEFT JOIN $sqlTNDest AS t ON t.ID=x.ID_Topic WHERE $sqlFilt";
	$rs = $tblDest->DataSQL($sql);
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
	throw new exception('GetTopics() is deprecated; use TopicRecords().');
    }
    public function GetTitles($iID) {
	throw new exception('GetTitles() is deprecated; use TitleRecords().');
    }

    // -- DATA RECORDS -- //
    // ++ ACTIONS ++ //

    /*----
      ASSUMES: state is changing; does not bother to check for redundant action.
      LATER: add an option for this, but only if it would actually be useful.
      HISTORY:
	2011-03-03 written for topic-deletion portion of title admin page
	2013-11-18 copied from clsTitleTopics_base to clsTitlesTopics
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
class clsTitleTopic extends clsRecs_generic {

    // ++ DATA FIELD ACCESS ++ //

    public function TitleID() {
	return $this->Value('ID_Title');
    }

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
}
/*%%%%
  PURPOSE: base class for handling title-topics
    Can also be used for writing to the table
    Use descendant classes for reading
  NOTE: why is this descended from clsTopics?
  DEPRECATED - use clsTitlesTopics
*/
/*
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
/*
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

  DEPRECATED - use clsTitlesTopics
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
/*
    public function GetTopic($iID) {
	$sql = 'ID_Topic='.$iID;
	$rs = $this->GetData($sql);
	return $rs;
    }
}

  DEPRECATED - use clsTitlesTopics
class clsTitleTopic_Topics extends clsTitleTopics_base {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->Name('qryTitleTopic_Topics');
	  //$this->ClassSng('clsTopic');
	  $this->doBranch(TRUE);
    }
    /*----
      RETURNS: recordset of rows which have this Title,
	essentially amounting to a list of Topics for this Title.
    */
/*
    public function GetTitle($iID) {
	$sql = 'ID_Title='.$iID;
	$rs = $this->GetData($sql);
	return $rs;
    }
}
*/