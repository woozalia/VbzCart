<?php
/*
  FILE: vbz-data.php
  PURPOSE: vbz-specific data classes - generic
  HISTORY:
    2011-01-25 split off from store.php in an attempt to resolve dependency conflicts
*/
class clsVbzTable extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
    }
    public function ActionKey($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->ActionKey = $iVal;
	}
	if (isset($this->ActionKey)) {
	    return $this->ActionKey;
	} else {
	    return $this->ClassSng();
	}
    }
    /*----
      ACTION: Updates the cache's timestamp for this source table,
	so that any dependent cache tables will be updated if needed.
      INPUT:
	iCaller: description of code event which caused the data update.
	  Used in event log.
      HISTORY:
	2010-11-16 $this->Name(), not $this->Name
    */
    protected function Touch($iCaller) {
	$objCMgr = $this->objDB->CacheMgr();
	$objCMgr->UpdateTime_byName($this->Name(),$iCaller);
    }
/* 2010-11-12 disable the freakin' cache for now
    protected function Touch($iCaller) {

This is named wrong anyway; "Touch" should be called when the data has changed,
but this is being called when a routine wants to make sure that it is up-to-date.
Making sure that it is up-to-date should probably be done only within administrative
functions anyway, rather than repeatedly in the store. The closest we would ever come
to automatic updates is if we automatically remove stuff from stock when people order it --
and then the update should happen (or be triggered, anyway) at ordering time,
not at catalog-viewing time.

	$objCache = $this->objDB->CacheMgr();
	$objCache->Update_byName($this->Name(),$iCaller);
    }
*/
}
class clsVbzRecs extends clsDataSet {
    /*----
      HISTORY:
	2010-10-25 changing event logging to use helper class
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    /*-----
      ACTION: Logs an event from specs in an array
	See clsEvents for details.
    */
    public function StartEvent(array $iarArgs) {
	$this->Log()->StartEvent($iarArgs);
    }
    public function FinishEvent(array $iarArgs=NULL) {
	$this->Log()->FinishEvent($iarArgs);
    }
    // CACHE MANAGEMENT
    /*----
      HISTORY:
	2010-11-18 Created because clsImage objects weren't being given a Mgr.
	  Possibly there is a better way of doing this which was implemented
	  for other classes and I just haven't found it yet.
    */
/*
    public function Mgr(clsCacheMgr $iMgr=NULL) {
	parent::Mgr($iMgr);
	if (is_null($this->objMgr)) {
echo 'GOT TO HERE';
	    // is this a kluge?
	    $this->objMgr = $this->objDB->CacheMgr();
	}
	return $this->objMgr;
    }
*/
}
