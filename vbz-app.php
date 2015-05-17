<?php
/*
  FILE: pages.php
  PURPOSE: VbzCart application framework classes
  HISTORY:
    2013-11-13 Extracted clsVbzApp from vbz-page.php
*/
/*%%%%
  CLASS: clsApp
  PURPOSE: base class -- container for the application
*/
/*
abstract class clsApp {
    abstract public function Go();
    abstract public function Session();
    abstract public function Skin();
    abstract public function Page(clsPage $iObj=NULL);
    abstract public function Data(clsDatabase $iObj=NULL);
    abstract public function User();
}
*/

/*%%%%
  CLASS: clsVbzApp
  IMPLEMENTATION: uses VBZ database object, but lets caller choose what type of Page to display
*/
class clsVbzApp extends cAppStandard {
    private $oPage;
    private $oData;

    // ++ INITIALIZATION ++ //

    public function __construct() {
	parent::__construct();
	//$this->UserClass('clsVbzUserRec');	// override user class
	$oData = new clsVbzData_Shop(KS_DB_VBZCART);
	$oDoc = new clsRTDoc_HTML();
	$this->Data($oData);
//	$iPage->Doc($oDoc);
//	$iPage->App($this);
	$oData->App($this);
    }
    public function Go() {
	$this->Skin()->SetStartTime();	// get the starting time, for benchmarking
	parent::Go();
    }

    // -- INITIALIZATION -- //
    // ++ FRAMEWORK OBJECTS ++ //

    public function Data(clsDatabase $iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->oData = $iObj;
	}
	return $this->oData;
    }
    /*----
      NOTE: For now, we defer to the Page object to get a skin,
	because different page types use different skin classes.
	This is probably something that should be changed.
    */
    public function Skin() {
	if (is_null($this->Page())) {
	    echo '<pre>';
	    throw new exception('Trying to access Skin before Page has been constructed.');
	}
	return $this->Page()->Skin();
    }
    public function Users($id=NULL) {
	$o = $this->Data()->Make('clsUserAccts',$id);
	return $o;
    }

    // ++ FRAMEWORK OBJECTS ++ //
    // ++ FRAMEWORK CLASSES ++ //

    public function EventsClass() {
	if (clsDropInManager::IsReady('vbz.syslog')) {
	    return KS_CLASS_EVENT_LOG;
	} else {
	    return parent::EventsClass();
	}
    }
    protected function SessionsClass() {
	return 'cVbzSessions';
    }

    // -- FRAMEWORK CLASSES -- //
    // ++ CALLBACKS ++ //

    public function BaseURL() {
	return KWP_PAGE_BASE;
    }
}

