<?php
/*
  FILE: admin.sess.php -- shopping session administration for VbzCart
  HISTORY:
    2010-10-17 created
*/
class VbzAdminSessions extends clsUserSessions {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminSession');
	  $this->ActionKey('sess');
    }
}
class VbzAdminSession extends clsUserSession {
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
}