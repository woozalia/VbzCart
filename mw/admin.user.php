<?php
/*
  FILE: admin.user.php
  PURPOSE: user administrative classes for gneneric HTML (not MW-specific)
*/
class clsVbzUserRecs_admin extends clsVbzUserTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsVbzUserRec_admin');
    }

// TO REWRITE
    public function UserObj(clsVbzUser $oUser=NULL) {
	if (!is_null($oUser)) {
	    // we are SETTING the user
	    $doChg = FALSE;
	    $rcUserNew = $oUser->RecObj();
	    if (is_null($this->objUser)) {
		$doChg = TRUE;
	    } else {
		$rcUserOld = $this->objUser->RecObj();
		if ($rcUserNew->KeyValue() != $rcUserOld->KeyValue()) {
		    $doChg = TRUE;
		}
	    }
	    if ($doChg) {
		$this->objUser = $oUser;
		$this->SetUser($rcUserNew->KeyValue());
	    }
	} else {
	    // we are trying to RETRIEVE the user
	    if (empty($this->objUser)) {
		$tUsers = $this->Engine()->Users();
		if ($this->HasUser()) {
		    $this->objUser = $tUsers->GetItem($this->Value('ID_User'));
		} else {
		    $this->objUser = NULL;
		}
	    }
	}
	return $this->objUser;
    }

}
class clsVbzUserRec_admin extends clsVbzUserRec {
}