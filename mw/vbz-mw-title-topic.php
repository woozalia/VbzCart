<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling title-topic assignments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class clsAdminTitleTopic_Titles extends clsTitleTopic_Titles {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
 	  $this->ClassSng('VbzAdminTitle');
	  $this->ActionKey($this->Engine()->Titles()->ActionKey());
    }
}
class clsAdminTitleTopic_Topics extends clsTitleTopic_Topics {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminTopic');
	  $this->ActionKey($this->Engine()->Topics()->ActionKey());
    }
}
