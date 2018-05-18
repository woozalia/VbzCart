<?php
/*
  FILE: pages.php
  PURPOSE: VbzCart application framework classes
  HISTORY:
    2013-11-13 Extracted clsVbzApp (now vcApp) from vbz-page.php
    2016-10-01 Revising to use db.v2
    2018-04-27 changing class vcApp to a trait, vtApp, so we can layer it
      on top of a selection of base app classes (basic vs. admin, at this point)
      Removed private $oPage and $oData members (apparently unused).
*/

/*::::
  IMPLEMENTATION: uses VBZ database object, but lets caller choose what type of Page to display
  ABSTRACT: n/i - GetPageClass(), GetKioskClass()
*/
trait vtApp {

    // ++ SETUP ++ //

    public function Go() {
	$this->SetStartTime();	// get the starting time, for benchmarking
	parent::Go();
    }

    // -- SETUP -- //
    // ++ CLASSES ++ //
    
    protected function SettingsClass() {
	return 'vctSettings';
    }
    
    // -- CLASSES -- //
    // ++ FRAMEWORK ++ //
    
    private $db;
    // CEMENT
    public function GetDatabase() {
	if (empty($this->db)) {
	    $dbf = new vcDBOFactory(KS_DB_VBZCART);
	    $db = $dbf->GetMainDB();
	    $this->db = $db;
	}
	return $this->db;
    }
    public function SettingsTable() {
	return $this->GetDatabase()->MakeTableWrapper($this->SettingsClass());
    }
    
    // -- FRAMEWORK -- //

}
/*----
  NOTE: This originally added EventTable(), but I moved that back into ftFrameworkAccess_standard
    and then into ftFrameworkAccess and then removed it. For now, go to fcApp::Me()->EventTable().
*/
trait vtFrameworkAccess {
    use ftFrameworkAccess;
}