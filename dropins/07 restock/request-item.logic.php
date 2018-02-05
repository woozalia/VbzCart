<?php
/*
  PURPOSE: business logic classes for managing line-items in restock requests
  HISTORY:
    2016-01-07 split off from request-item.php
*/
class vctlRstkReqItems extends fcTable_keyed_multi {

    // ++ SETUP ++ //

    /*
   public function __construct($iDB) {
	$objIdxr = new clsIndexer_Table_multi_key($this);
	parent::__construct($iDB,$objIdxr);
	  $this->ClassSng();
	  $this->Name();
	  $objIdxr->KeyNames(array('ID_Parent','ID_Item'));
    }*/
    // CEMENT
    protected function TableName() {
	return 'rstk_req_item';
    }
    // CEMENT
    protected function SingularName() {
	return 'clsRstkReqItem';
    }
    // CEMENT
    public function GetKeyNames() {
	return array('ID_Parent','ID_Item');
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function CatItemsClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return KS_LOGIC_CLASS_LC_ITEMS;
	}
    }

    // -- CLASS NAMES -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Add the given item to the restock request, with the given parameters
      OUTPUT FIELDS:
	QtyNeed: this is calculated by the called'
	QtyCust: quantity needed to fill customer orders
	  This should already have been adjusted for quantity in stock.
	QtyOrd: quantity we'll actually order -- for now, assume same as QtyNeed
      NOTE: Not sure if these take supplier restock minimum into account
    */
    public function AddItem($idRequest,vcrItem $rcItem,$qtyNeed,$qtySold) {
	$idItem = $rcItem->GetKeyValue();
	$sDesc = $rcItem->Description_forRestock();
	$prcCost = $rcItem->PriceBuy();	// cost to us
	$ar = array(
	  'ID_Parent'	=> $idRequest,
	  'ID_Item'	=> $idItem,
	  'Descr'     	=> SQLValue($sDesc),  // Item description as given at time of shopping
	  'WhenCreated'	=> 'NOW()',// when this line was added
	  'WhenVoided'	=> 'NULL',	// when this line was voided; not NULL = ignore this line",
	// quantities - data from creation of restock
	  'QtyNeed'	=> $qtyNeed,	// quantity needed, either for an order or to keep stock at desired level
	  'QtyCust'	=> SQLValue($qtySold),	// quantity needed just to fill customer orders
	  'QtyOrd'	=> $qtyNeed,	// quantity actually ordered from supplier
	  'QtyExp'	=> 'NULL',	// quantity actually expected, if supplier doesn't have enough available to fill the order
	  'isGone'	=> 'NULL',	// YES = item discontinued, no more available (if info from source other than invoice)
	// cost information
	  'CostExpPer'	=> $prcCost,	// expected per-item cost
 	  );
 	$ok = $this->Insert($ar);
 	if (!$ok) {
	    echo 'SQL FAILED: '.$this->sqlExec.'<br>';
 	}
 	return $ok;
    }
    /*----
      ACTION: Adds a line-item to the current restock request.
	In order to avoid event proliferation, this routine does NOT log events.
	Instead, caller should log an event for each batch of lines added.
      CALLED BY: clsAdminRstkReqs::AdminItemsSave()
      HISTORY:
	2010-12-12 Uncommented and corrected to add items to rstk_req_item instead of rstk_req
	2016-02-?? Re-commented out because it will need updating.
    */
    /* to be adapted
    public function AddLine(vcrItem $iItem,$iQtyNeed,$iQtyCust,$iQtyOrd) {
	assert('!$iItem->IsNew();');
	$arIns = array(
	  'ID_Request'	=> $this->ID,
	  'ID_Item'	=> $iItem->ID,
	  'Descr'	=> SQLValue($iItem->Description_forRestock()),
	  'WhenCreated'	=> 'NOW()',
	  'QtyNeed'	=> SQLValue($iQtyNeed),	// can be NULL
	  'QtyCust'	=> SQLValue($iQtyCust),	// can be NULL
	  'QtyOrd'	=> SQLValue($iQtyOrd),	// can be NULL
	  'CostExpPer'	=> SQLValue($iItem->PriceBuy)
	  );
	$this->LinesTbl()->Insert($arIns);
    }
    */
    
    // -- ACTIONS -- //

}
class clsRstkReqItem extends fcRecord_keyed_multi {

    // ++ FIELD KEYS ++ //

    protected function KeyNeedsQuote($sKey) {
	return FALSE;	// both keys are integer
    }
    
    // -- FIELD KEYS -- //
    // ++ FIELD VALUES ++ //

    protected function GetParentID() {
	return $this->GetFieldValue('ID_Parent');
    }
    protected function ItemID() {
	return $this->GetFieldValue('ID_Item');
    }

    // -- FIELD VALUES -- //
    // ++ CLASS NAMES ++ //

    protected function RequestsClass() {
	return KS_LOGIC_CLASS_RESTOCK_REQUESTS;
    }
    protected function LCItemsClass() {
	if (fcApp::Me()->GetDropinManager()->HasModule('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return KS_LOGIC_CLASS_LC_ITEMS;
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function ParentTable($id=NULL) {
	return $this->RestockRequestTable($id);
    }
    protected function RestockRequestTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->RequestsClass(),$id);
    }
    protected function LCItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->LCItemsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    protected function ParentRecord() {
	$id = $this->GetParentID();
	$rc = $this->ParentTable($id);
	//$rcItem = $tReqs->GetItem('ID='.$this->ItemID());	// ALERT: shouldn't this also filter for request ID?
	return $rc;
    }
    protected function LCItemRecord() {
	$id = $this->ItemID();
	$rc = $this->LCItemTable($id);
	return $rc;
    }

    // -- DATA RECORDS ACCESS -- //
}
