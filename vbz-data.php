<?php
/*
  FILE: vbz-data.php
  PURPOSE: vbz-specific data classes - generic
  HISTORY:
    2011-01-25 split off from store.php in an attempt to resolve dependency conflicts
    2013-11-15 moved clsVbzData from store.php to here (vbz-data.php)
    2014-04-04 moved clsCacheFile_vbz here from store.php (not sure if this is *exactly* the place for it...(
    2015-09-06 splitting a couple of classes:
        clsVbzTable:
            vcVbzTable_base will define a couple of common methods
            vcVbzTable_shop won't have link-building stuff built in anymore
            vcAdminTable (was vcVbzTable_admin) will use traits to add admin functionality
        clsVbzRecs:
            vcVbzRecs_shop won't have link-building stuff built in anymore
            vcVbzRecs_admin will use traits to add admin functionality
                and will no longer descend from clsDataRecord_Menu
    2016-10-01 Changes to ferreteria:app-user.php (switching from db.v1 to db.v2) require changes here.
    2016-10-24 ...aaaand apparently the conversion was not complete; finishing that. More name and structure changes.
	vcVbzTable_base -> vcBasicTable
	vcVbzTable_shop -> vcShopTable
	vcVbzTable_admin -> vcAdminTable (2017-01-04)
*/

class vcCacheFile extends fcCacheFile {
    public function __construct() {
	parent::__construct(KFP_CACHE);
    }
}

class vcDBOFactory extends fcDBOFactory {

    // ++ SETUP ++ //

    public function __construct($sSpec) {
	$oConn = self::GetConn($sSpec,FALSE);
	$this->SetMainDB($oConn);
    }
    private $dbMain;
    protected function SetMainDB(fcDataConn_CliSrv $oConn) {
	$this->dbMain = $oConn;
    }
    public function GetMainDB() {
	return $this->dbMain;
    }

    // -- SETUP -- //
    // ++ METHOD OVERRIDES ++ //

    /*----
      PURPOSE: This implements any special values of $id.
	'new' = create a blank object that can be saved as a new record
      NOTE: This should go with the general app framework; it is not VBZ-specific.
    */ /* 2016-10-01 Removing this for now. If it turns out to be useful/necessary, port it back into Ferreteria.
    public function Make($sClass,$id=NULL) {
	if ($id == KS_NEW_REC) {
	    $tbl = parent::Make($sClass);	// get the table object
	    $rc = $tbl->SpawnItem();		// get a blank object
	    if (method_exists($rc,'InitFromInput')) {
		$rc->InitFromInput();		// initialize it from available inputs
	    } else {
		// debugging only; turn this off for production:
		//throw new exception('Object of class '.$sClass.' needs InitFromInput() method.');
	    }
	    return $rc;
	} else {
	    return parent::Make($sClass,$id);
	}
    } */

    // -- METHOD OVERRIDES -- //
    // ++ CACHE MANAGEMENT ++ //

    protected function CacheMgr_empty() {
	return new clsCacheMgr($this);
    }
    private $oCacheMgr;
    public function CacheMgr() {
	if (empty($this->oCacheMgr)) {
	    $oCM = $this->CacheMgr_empty();
	    $oCM->SetTables('cache_tables','cache_queries','cache_flow','cache_log');
	    $this->oCacheMgr = $oCM;
	}
	return $this->oCacheMgr;
    }

    // -- CACHE MANAGEMENT -- //
    // ++ GLOBAL SETTINGS ++ //

    private $fsPubKey;
    public function PublicKey_FileSpec() {
	if (empty($this->fsPubKey)) {
	    $fn = $this->VarsGlobal()->Val('public_key.fspec');
	    $fs =  KFP_KEYS.'/'.$fn;
	    $this->fsPubKey = $fs;
	}
	return $this->fsPubKey;
    }
    private $sPubKey;
    public function PublicKey_string() {
	if (empty($this->sPubKey)) {
	    $fs = $this->PublicKey_FileSpec();
	    $this->sPubKey = file_get_contents($fs);
	}
	return $this->sPubKey;
    }

    // -- GLOBAL SETTINGS -- //

    // SPECIALIZED STUFF
    public function LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere) {
	throw new exception('2016-10-31 Is anyone still calling this?');
	//return $this->Syslog()->LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere);
	$rcEv = $this->Syslog()->CreateEvent(
	  array(
	    'where'	=> __METHOD__,
	    'params'	=> $iParams,
	    'descr'	=> $iDescr,
	    'code'	=> $iCode,
	    'error'	=> $iIsError,
	    'severe'	=> $iIsSevere
	    )
	  );
	return $rcEv;
    }
}

/*----
  PURPOSE: standard table wrapper class for VbzCart -- adds a service useful for caching
  TODO: maybe that should be in a descendant
*/
abstract class vcBasicTable extends fcTable_keyed_single_standard {

    // ++ ACTIONS ++ //

    /*----
      ACTION: Updates the cache's timestamp for this source table,
	so that any dependent cache tables will be updated if needed.
      INPUT:
	iCaller: description of code event which caused the data update.
	  Used in event log.
      HISTORY:
	2010-11-16 $this->Name(), not $this->Name
      TODO: Rename this. "Touch" should be what you call when the data has changed,
but this is being called when a routine wants to make sure that it is up-to-date.
Making sure that it is up-to-date should probably be done only within administrative
functions anyway, rather than repeatedly in the store. The closest we would ever come
to automatic updates is if we automatically remove stuff from stock when people order it --
and then the update should happen (or be triggered, anyway) at ordering time,
not at catalog-viewing time.
    */
    protected function Touch($iCaller) {
	$objCMgr = $this->Engine()->CacheMgr();
	$objCMgr->UpdateTime_byName($this->Name(),$iCaller);
    }

    // -- ACTIONS -- //
}
// PURPOSE: standard recordset wrapper class for VbzCart; currently just an alias
abstract class vcBasicRecordset extends fcRecord_keyed_single_integer {
}

// SHOPPING data types

abstract class vcShopTable extends vcBasicTable {
// just an alias for now
}
class vcShopRecordset extends vcBasicRecordset {
// just an alias for now
}

// ADMIN data types

abstract class vcAdminTable extends vcBasicTable {
    use ftLinkableTable;
}
class vcAdminRecordset extends vcBasicRecordset {
    use ftLinkableRecord;
}


