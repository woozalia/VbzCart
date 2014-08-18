<?php
/*
  FILE: vbz-data.php
  PURPOSE: vbz-specific data classes - generic
  HISTORY:
    2011-01-25 split off from store.php in an attempt to resolve dependency conflicts
    2013-11-15 moved clsVbzData from store.php to here (vbz-data.php)
    2014-04-04 moved clsCacheFile_vbz here from store.php (not sure if this is *exactly* the place for it...(
*/

class clsCacheFile_vbz extends clsCacheFile {
    public function __construct() {
	parent::__construct(KFP_CACHE);
    }
}

class clsVbzData extends clsDatabase_UserAuth {

    // ++ STATIC ++ //

    /*
      STATUS: DEPRECATED
	Where possible, convert table fieldnames to be compatible with WhoString2_wt
    */
    static public function WhoString_OLD1($iRow) {
	$htUnknown = '<span style="color: #888888;">?</span>';

	if (isset($iRow['SysUser'])) {
	    $strSysUser	= $iRow['SysUser'];
	    $hasSysUser 	= TRUE;
	} else {
	    $strSysUser	= NULL;
	    $hasSysUser	= FALSE;
	}
	$strMachine	= $iRow['Machine'];
	$strVbzUser	= $iRow['VbzUser'];

	$htSysUser	= is_null($strSysUser)?$htUnknown:$strSysUser;
	$htMachine	= is_null($strMachine)?$htUnknown:$strMachine;
	$htVbzUser	= is_null($strVbzUser)?$htUnknown:$strVbzUser;

	$htWho = $htVbzUser;
	if ($hasSysUser) {
	    $htWho .= '/'.$htSysUser;
	}
	$htWho .= '@'.$htMachine;

	return $htWho;
    }

    // -- STATIC -- //
    // ++ SETUP ++ //

    public function __construct($iSpec) {
	global $vgoDB;

	parent::__construct($iSpec);
	$this->Open();
	$vgoDB = $this;

//	clsLibMgr::AddClass('clsSuppliers_StoreUI','vbz.cat');
    }

    // -- SETUP -- //
    // ++ METHOD OVERRIDES ++ //

    /*----
      PURPOSE: This implements any special values of $id.
	'new' = create a blank object that can be saved as a new record
      NOTE: This should go with the general app framework; it is not VBZ-specific.
    */
    public function Make($sClass,$id=NULL) {
	if ($id == 'new') {
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
    }

    // -- METHOD OVERRIDES -- //
    // ++ CACHE MANAGEMENT ++ //

    protected function CacheMgr_empty() {
	return new clsCacheMgr($this);
    }
    public function CacheMgr() {
	if (empty($this->objCacheMgr)) {
	    $objCache = $this->CacheMgr_empty();
	    $objCache->SetTables('cache_tables','cache_queries','cache_flow','cache_log');
	    $this->objCacheMgr = $objCache;
	}
	return $this->objCacheMgr;
    }

    // -- CACHE MANAGEMENT -- //
    // ++ DATA TABLE ACCESS ++ //

    // these are DEPRECATED

    public function Pages($id=NULL) {
	return $this->Make('clsCatPages',$id);
    }
    public function Suppliers($id=NULL) {
	return $this->Make('clsSuppliers_StoreUI',$id);
    }
    public function Depts($id=NULL) {
	return $this->Make('clsDepts',$id);
    }
    public function Titles($id=NULL) {
	return $this->Make('clsTitles_StoreUI',$id);
    }
    public function Items($id=NULL) {
	return $this->Make('clsItems',$id);
    }
    public function Items_Stock($id=NULL) {
	return $this->Make('clsItems_Stock',$id);
    }
    public function Items_Cat($id=NULL) {
	return $this->Make('clsItems_info_Cat',$id);
    }
    public function ItTyps($id=NULL) {
	return $this->Make('clsItTyps',$id);
    }
    public function ItOpts($id=NULL) {
	return $this->Make('clsItOpts',$id);
    }
    public function ShipCosts($id=NULL) {
	return $this->Make('clsShipCosts',$id);
    }
    public function Folders($id=NULL) {
	return $this->Make('clsVbzFolders',$id);
    }
    public function Images($id=NULL) {
	return $this->Make('clsImages_StoreUI',$id);
    }
    public function StkItems($id=NULL) {
	return $this->Make('clsStkItems',$id);
    }
    public function Topics($iID=NULL) {
	return $this->Make('clsTopics_StoreUI',$iID);
    }
    public function TitlesTopics() {
	return $this->Make('clsTitlesTopics');
    }
/*
    public function TitleTopic_Titles() {
	return $this->Make('clsTitleTopic_Titles');
    }
    public function TitleTopic_Topics() {
	return $this->Make('clsTitleTopic_Topics');
    }
*/
    public function VarsGlobal($id=NULL) {
	return $this->Make('clsGlobalVars',$id);
    }
    public function Syslog($id=NULL) {
	return $this->Make('clsSysEvents',$id);
    }
    // needed for both shopping and admin
    public function CustEmails() {
	return $this->Make('clsAdminCustEmails');
    }

    // -- DATA TABLE ACCESS -- //

    // SPECIALIZED STUFF
    public function LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere) {
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
    public function CryptObj() {
	if (!isset($this->objCrypt)) {
//	    $this->objCrypt = new Cipher($this->strCryptKey);
	    $this->objCrypt = new vbzCipher();
	    $objVars = $this->VarsGlobal();
/* 2013-09-09 no longer used
	    if ($objVars->Exists('crypt_seed')) {
		$strSeed = $objVars->Val('crypt_seed');
		$this->objCrypt->Seed($strSeed);
	    } else {
		$strSeed = $this->objCrypt->MakeSeed();
		$objVars->Val('crypt_seed',$strSeed);
	    }
*/
	    $intOrdLast = $objVars->Val('ord_seq_prev');
	}
	return $this->objCrypt;
    }
}

class clsVbzTable extends clsDataTable_Menu {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->KeyName('ID');	// default key field; can be overridden
    }

    // -- SETUP -- //
    // ++ ACTIONS ++ //

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
	$objCMgr = $this->Engine()->CacheMgr();
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

    // -- ACTIONS -- //
}
class clsVbzRecs extends clsDataRecord_Menu {

    // ++ SETUP ++ //

    protected function InitVars() {
	$this->Value_IdentityKeys(array('page','id'));
    }

    // -- SETUP -- //

}
