<?php
/*
  PURPOSE: extends title-topic classes to invoke shopping-UI-aware classes
  HISTORY:
    2014-08-14 created
*/

class vctTitlesTopics_shop extends vctTitlesTopics {
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    protected function TopicsClass() {
	return 'vctShopTopics';
    }

    // -- CLASS NAMES -- //
}
