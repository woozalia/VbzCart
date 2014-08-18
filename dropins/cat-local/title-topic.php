<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling title-topic assignments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class VCTA_TitleTopic_Titles extends clsTable_indexed {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
 	  $this->ClassSng(KS_CLASS_CATALOG_TITLE);
	  $this->ActionKey($this->Engine()->Titles()->ActionKey());
    }
}
class VCTA_TitleTopic_Topics extends clsTable_indexed {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->ClassSng(KS_CLASS_CATALOG_TOPIC);
	  $this->ActionKey($this->Engine()->Topics()->ActionKey());
    }
}
