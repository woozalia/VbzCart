<?php
/*
  PURPOSE: Form to handle logging of changes to Stock Lines
  HISTORY:
    2016-02-24 created
    2017-05-26 replaced ProcessIncomingRecord() with GetInsertNativeValues() + GetUpdateNativeValues() in Recordset class
      ...which means this is now completely redundant. Retiring.
*/

class fcForm_StockLine extends fcForm_DB {
    /* 2017-05-26 no longer needed
    protected function ProcessIncomingRecord(array $ar) {
	$rc = $this->RecordsObject();
    
	// TIMESTAMPS
    
	if ($rc->IsNew()) {
	    $ar['WhenAdded'] = time();
	} else {
	    $ar['WhenChanged'] = time();
	}
	
	// STOCK MOVEMENT
	
	$idBinOld = $rc->BinID();
	$idBinNew = $ar['ID_Bin'];
	if ($idBinNew != $idBinOld) {
	    //$rc->Log_MoveToBin($idBinNew,'moved manually');
	    $rc->MoveToBin($idBinNew,'moved manually');
	}
	
	return $ar;
    } */
}