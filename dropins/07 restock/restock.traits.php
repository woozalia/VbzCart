<?php
/*
  PURPOSE: stuff that both Restock Requests and Received Restocks have in common --
    possibly including other stuff that should eventually be common to admin classes.
  HISTORY:
    2016-01-12 created for filter menus
*/

trait vtRestockTable_logic {
    // not sure how to organize functions like this
    // PUBLIC so records object can use it
    public function SupplierTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_SUPPLIERS,$id);
    }
    public function WarehouseTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->WarehousesClass(),$id);
    }
}

trait vtRestockTable_admin {

    // ++ CLASS NAMES ++ //

    // TODO: graceful failure if Stock dropin is unavailable
    protected function WarehousesClass() {
	return KS_ADMIN_CLASS_STOCK_WAREHOUSES;
    }

    // -- CLASS NAMES -- //
    // ++ APP FRAMEWORK ++ //

    // This one function should probably be part of a ftAppFramework trait... later.
    protected function PageObject() {
	return $this->Engine()->App()->Page();
    }
    
    // -- APP FRAMEWORK -- //
    // ++ ADMIN UI ++ //
    
    /*----
      PURPOSE: Sets up the common environment for displaying a set of restock records (requests or received),
	including the recordset (optionally filtered), then calls the recordset to display itself.
    */
    protected function AdminRows() {
    
	$sqlSort = $this->SQLstr_Sorter_Date();
	
	// get everything-recordset for filter menu:
	$rs = $this->AdminRecords(NULL,"$sqlSort DESC");
	// get stats for filter menu
	$arStat = $rs->Array_forFilterMenu();
	
	// check input
	$idSupp = $this->PageObject()->PathArg('supp');
	$idYear = $this->PageObject()->PathArg('year');
	
	// build SQL filter from input
	$oFilt = new fcSQLt_Filt('AND');
	
	if (!is_null($idSupp)) {
	    $oFilt->AddCond('ID_Supplier='.$idSupp);
	}
	if (!is_null($idYear)) {
	    $sqlYear = 'YEAR('.$this->SQLstr_Sorter_Date().')';
	    $sqlYearCond = ($idYear == 'none')?($sqlYear.' IS NULL'):($sqlYear.'='.$idYear);
	    $oFilt->AddCond($sqlYearCond);
	}
	$sqlFilt = $oFilt->Render();
	
	// get the recordset to view
	$rs = $this->AdminRecords($sqlFilt,$sqlSort.' DESC');
	// render filter menu
	$out = $this->AdminRows_FilterMenus($arStat,$idSupp,$idYear);
	// render recordset report
	$out .= $rs->AdminRows();
	// show SQL used
	$sqlMake = $rs->sqlMake;
	$out .= "<span class=line-stats><b>SQL</b>: $sqlMake</span><br>";
	return $out;
    }
    // ACTION: Iterate once through the entire (unfiltered) recordset to build the filter menus
    protected function AdminRows_FilterMenus(array $arData, $idSuppCurr, $idYearCurr) {
	$arSupp = $arData['supp'];
	$arYear = $arData['date'];
	
	$out =
	  $this->SupplierTable()->RenderLineMenu($arSupp,$this->LinkBuilder(),$idSuppCurr)
	  .'<br>'.$this->RenderYearMenu($arYear,$idYearCurr);
	return $out;
    }
    protected function RenderYearMenu(array $arYears,$idYearCurr) {
	$out = NULL;
	foreach ($arYears as $idYear => $qty) {
	    if (empty($idYear)) {
		$sYear = 'none';
		$sPopup = 'show only undated records';
	    } else {
		$sYear = $idYear;
		$sPopup = 'show only '.$sYear;
	    }
	    $htLink = $this->SelfLink($sYear,$sPopup,array('year'=>$sYear));
	    $htCtrl = "[$htLink:$qty]";
	    $out .= ' '.fcHTML::FlagToFormat($htCtrl,($idYearCurr == $sYear));
	}
	return $out;
    }
    
    // -- ADMIN UI -- //

}

trait vtRestockRecords_admin {
    use vtTableAccess_Supplier_admin;

    // ++ FIELD VALUES ++ //
    
    // some of these need to be copied from the restock request to a new received restock
    
    // PUBLIC so received restock can use it
    // WRITABLE so received restock can set it as a default
    public function SupplierID($id=NULL) {
	return $this->GetFieldValue('ID_Supplier',$id);
    }
    // PUBLIC so received restock can use it
    // WRITABLE so received restock can set it as a default
    public function WarehouseID($id=NULL) {
	return $this->GetFieldValue('ID_Warehouse',$id);
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD LOOKUP ++ //
    
    protected function HasSupplier() {
	if (is_null($this->SupplierID())) {
	    return FALSE;
	} else {
	    return $this->SupplierRecord()->HasRows();
	}
    }
    protected function SupplierCatKey() {
	if ($this->HasSupplier()) {
	    return $this->SupplierRecord()->CatKey();
	} else {
	    return 'n/a';
	}
    }
    protected function HasWarehouse() {
	if (is_null($this->WarehouseID())) {
	    return FALSE;
	} else {
	    return $this->WarehouseRecord()->HasRows();
	}
    }
    protected function WarehouseName() {
	if ($this->HasWarehouse()) {
	    return $this->WarehouseRecord()->NameString();
	} else {
	    return 'n/a';
	}
    }
    
    // -- FIELD LOOKUP -- //
    // ++ TABLES ++ //
    
    /* 2017-01-18 replaced by trait
    protected function SupplierTable($id=NULL) {
	return $this->GetTableWrapper()->SupplierTable($id);
    } */
    protected function WarehouseTable($id=NULL) {
	return $this->GetTableWrapper()->WarehouseTable($id);
    }
    
    // -- TABLES -- //
    // ++ RECORDSETS ++ //

    /*----
      NOTE: Although ID_Supplier is a NOT NULL field, when it is set to zero it is somehow
	being retrieved as NULL -- so we need to allow for that possibility even though we
	shouldn't have to.
    */
    protected function SupplierRecord() {
	$id = $this->SupplierID();
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->SupplierTable($id);
	}
    }
    // USED BY nothing currently, but provided for clarity
    protected function SupplierRecords_active() {
	return $this->SupplierTable()->ActiveRecords();
    }
    // USED BY page edit form
    protected function SupplierRecords_all() {
	return $this->SupplierTable()->SelectRecords();
    }
    
    protected function WarehouseRecord() {
	$id = $this->GetFieldValue('ID_Warehouse');
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->WarehouseTable($id);
	}
    }
    // USED BY nothing currently, but provided for clarity
    protected function WarehouseRecords_active() {
	return $this->WarehouseTable()->ActiveRecords();
    }
    // USED BY page edit form
    protected function WarehouseRecords_all() {
	return $this->WarehouseTable()->SelectRecords();
    }
    
    // -- RECORDSETS -- //

}
