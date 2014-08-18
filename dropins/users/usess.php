<?php
/*
  FILE: admin.sess.php -- shopping session administration for VbzCart
  HISTORY:
    2010-10-17 created
*/
class VCT_AdminSessions extends cVbzSessions {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_AdminSession');
	  $this->ActionKey(KS_ACTION_USER_SESSION);
    }
}
class VCR_AdminSession extends cVbzSession {
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
}