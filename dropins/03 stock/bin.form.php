<?php
/*
  PURPOSE: fcForm_DB descendant class to calculate values when saving Bin forms
  HISTORY:
    2016-02-23 started
*/

class fcForm_Bin extends fcForm_DB {
    protected function ProcessIncomingRecord(array $ar) {
	if ($this->RecordsObject()->IsNew()) {
	    $ar['WhenCreated'] = time();
	} else {
	    $ar['WhenEdited'] = time();
	}
	return $ar;
    }
}