<?php
/*
  PURPOSE: miscellaneous bits and pieces for Bin admin classes
  HISTORY:
    2016-02-23 started
    2017-03-24 adding vtAdminStockBin; renamed fcForm_Bin to vcFormBin because why was it named fc*?
*/

/*::::
  TODO: change hard-coded HTML styles to CSS classes
*/
trait vtAdminStockBin {
    public function SelfLink_name() {
	$ftLink = $this->SelfLink($this->LabelString());

	$htStyleActv = NULL;
	$sDescr = NULL;
	$isActive = $this->SelfIsActive();
	$isUsable = $this->HasActivePlace();
	if (!$isActive) {	// voided
	    $sDescr .= 'voided ';
	    $htStyleActv = 'text-decoration: line-through;';
	}
	if (!$isUsable) {	// Place is invalid
	    $sDescr .= 'place-invalid';
	    $htStyleActv .= ' background-color: #aaaaaa;';
	}

	$ftSfx = NULL;
	/* 2017-03-24 isValid field is now deprecated
	$isValid = $this->IsValid();
	if ($isValid != $isUsable) {
	    $ftStat = $isValid?'enabled':'disabled';
	    $ftSfx .= ' <span title="update needed - should be '.$ftStat.'" style="color: red; font-weight: bold;">!!</span>';
	} */

	if (is_null($htStyleActv)) {
	    return $ftLink.$ftSfx;
	} else {
	    return '<span style="'.$htStyleActv.'" title="'.$sDescr.'">'.$ftLink.'</span>'.$ftSfx;
	}
    }
}

/* ::::
  PURPOSE: fcForm_DB descendant class to calculate values when saving Bin forms
  TODO: Needs better documentation, and possibly remediation.
  HISTORY:
    2016-02-23 started
    2017-03-24 renamed fcForm_Bin to vcFormBin because why was it named fc*?
    2017-05-26 replaced ProcessIncomingRecord() with GetInsertNativeValues() + GetUpdateNativeValues() in Recordset class
      ...which means this is now completely redundant. Retiring.
*/
class vcForm_Bin extends fcForm_DB {
/* 2017-05-26 no longer needed
    protected function ProcessIncomingRecord(array $ar) {
	if ($this->RecordsObject()->IsNew()) {
	    $ar['WhenCreated'] = time();
	} else {
	    $ar['WhenEdited'] = time();
	}
	return $ar;
    } */
}