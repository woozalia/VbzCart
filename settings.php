<?php
/*
  PURPOSE: specialized settings class with named settings
  HISTORY:
    2016-11-03 started
*/

class vctSettings extends fcSettingsTable_standard {
    use vtFrameworkAccess;

    // -- EXPECTED FUNCTIONS -- //
    // ++ NAMED SETTINGS ++ //
    
    protected function SetLastOrderSequence($n) {
	return $this->SetValue('ord_seq_prev',$n);
    }
    /*----
      ACTION: Calculates the next order sequence to use, and saves it as used.
      PUBLIC so Orders table wrapper can use it
    */
    public function UseNextOrderSequence() {
	$nThis = $this->GetValue('ord_seq_prev');
	$nNext = $nThis+1;
	$this->SetLastOrderSequence($nNext);
	return $nNext;
    }
    public function GetPublicKeyFileSpec() {
	return $this->GetValue('public_key.fspec');
    }

    // ++ NAMED SETTINGS ++ //

}