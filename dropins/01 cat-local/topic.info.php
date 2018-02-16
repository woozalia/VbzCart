<?php
/*
  PURPOSE: Handles queries that combine Topics and availability info
  HISTORY:
    2016-02-18 started - so we can return Topic lists separated by whether they have any available Titles
      This file was in vbzcart/cat at that time, and the classes had no actual code in them.
    2016-03-23 moved to dropins/cat-local and added methods to get titles-with-no-topics
    2018-02-14 moved methods for titles-without-topics to title.info; is anything still using this now?
*/

class vcqtTopicsInfo extends vctShopTopics {

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcqrTopicInfo';
    }

    // -- SETUP -- //
    // ++ TABLES ++ //
    
/*    protected function TitleTable() {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES);
    }//*/
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_JOIN_TITLES);
    }
    
    // -- TABLES -- //
    
}
class vcqrTopicInfo extends vcrShopTopic {
}