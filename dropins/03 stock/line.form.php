<?php
/*
  PURPOSE: Form to handle logging of changes to Stock Lines
  HISTORY:
    2016-02-24 created
*/

class fcForm_StockLine extends fcForm_DB {
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
    }
}