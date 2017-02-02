<?php
/*
  FILE: pages.php
  PURPOSE: VbzCart application framework classes
  HISTORY:
    2013-11-13 Extracted clsVbzApp (now vcApp) from vbz-page.php
    2016-10-01 Revising to use db.v2
*/

/*::::
  CLASS: vcApp
  IMPLEMENTATION: uses VBZ database object, but lets caller choose what type of Page to display
  ABSTRACT: n/i - GetPageClass(), GetKioskClass()
*/
abstract class vcApp extends fcAppStandard {
    private $oPage;
    private $oData;

    // ++ SETUP ++ //

    public function Go() {
	$this->SetStartTime();	// get the starting time, for benchmarking
	parent::Go();
    }

    // -- SETUP -- //
    // ++ CEMENT ++ //
    
    private $db;
    public function GetDatabase() {
	if (empty($this->db)) {
	    $dbf = new vcDBOFactory(KS_DB_VBZCART);
	    $db = $dbf->GetMainDB();
	    $this->db = $db;
	}
	return $this->db;
    }
    
    // -- CEMENT -- //
    // ++ CLASS NAMES ++ //
    
    protected function SettingsClass() {
	return 'vctSettings';
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    // PUBLIC so Page objects can use it
    public function CartTable() {
	return $this->GetDatabase()->MakeTableWrapper($this->CartsClass());
    }
    public function SettingsTable() {
	return $this->GetDatabase()->MakeTableWrapper($this->SettingsClass());
    }

    // -- TABLES -- //

}
// NOTE: This originally added EventTable(), but I moved that back into ftFrameworkAccess_standard.
trait vtFrameworkAccess {
    use ftFrameworkAccess_standard;
}