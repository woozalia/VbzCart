<?php
/*
  FILE: admin.sess.php -- shopping session administration for VbzCart
  HISTORY:
    2010-10-17 created
*/
class VbzAdminSessions extends clsShopSessions {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminSession');
	  $this->ActionKey('sess');
    }
    public function ActionKey($iName=NULL) {
	if (!is_null($iName)) {
	    $this->ActionKey = $iName;
	}
	return $this->ActionKey;
    }
}
class VbzAdminSession extends clsShopSession {
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
}