<?php
/*
  PURPOSE: extends title-topic classes to invoke shopping-UI-aware classes
  HISTORY:
    2014-08-14 created
*/

class clsTitlesTopics_shopUI extends clsTitlesTopics {
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'clsTitles_StoreUI';
    }
    protected function TopicsClass() {
	return 'clsTopics_StoreUI';
    }

    // -- CLASS NAMES -- //
}
