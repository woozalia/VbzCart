<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling title-topic assignments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class vctTitlesTopics_admin extends vctTitlesTopics {
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }
    protected function TopicsClass() {
	return KS_CLASS_CATALOG_TOPICS;
    }

    // -- CLASS NAMES -- //
}
/* 2017-01-05 These do not seem to be used.
class VCTA_TitleTopic_Titles extends fcTable_keyed {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
 	  $this->ClassSng(KS_CLASS_CATALOG_TITLE);
	  $this->ActionKey(KS_ACTION_CATALOG_TITLE);
    }
    protected function SingularName() {
	return KS_CLASS_CATALOG_TITLE;
    }
}
class VCTA_TitleTopic_Topics extends fcTable_keyed {
    public function __construct(clsDatabase $iDB) {
	parent::__construct($iDB);
	  $this->ClassSng();
	  $this->ActionKey(KS_ACTION_CATALOG_TOPIC);
    }
    protected function SingularName() {
	return KS_CLASS_CATALOG_TOPIC;
    }
}
*/