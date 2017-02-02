<?php
/*
  PURPOSE: classes for handling business logic of line items in received restocks
  HISTORY:
    2014-03-09 split off from received.php
*/
class vctlRstkRcdLines extends vcBasicTable {

    // CEMENT
    protected function TableName() {
	return 'rstk_rcd_line';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrlRstkRcdLine';
    }
}
class vcrlRstkRcdLine extends vcBasicRecordset {

    // ++ FIELD VALUES ++ //

    /*----
      USED BY: stock movement functions
      PUBLIC for stock movement fx
      WRITABLE so stock movement fx know where to redirect back to
    */
    public function ParentID($id=NULL) {
	return $this->Value('ID_Parent',$id);
    }
    /*----
      USED BY: ItemRecord() and stock movement functions
      PUBLIC for stock movement fx
    */
    public function ItemID() {
	return $this->Value('ID_Item');
    }
    // USED BY: admin rows display
    protected function InvoiceLineNumber() {
	return $this->Value('InvcLineNo');
    }
    // USED BY: admin rows display
    protected function QtyReceived() {
	return $this->Value('QtyRecd');
    }
    // USED BY: admin rows display
    protected function QtyFiled() {
	return $this->Value('QtyFiled');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD ACTIONS ++ //
    
    /*----
      ACTION: updates fields:
	CostActTot <- [QtyRecd | InvcQtySent] x CostInvPer
	CostActBal <- CostActTot + [previous CostActBal]
      HISTORY:
	2016-01-18 written without consulting code in VBA version
      NOTE: This is a preliminary reconstruction of what the logic was.
	It may be revised if further experience indicates this would make sense.
	Tentatively, the point was to double-check the invoice's math.
    */
    protected function Update_LineCosts(&$arState) {
	$qRecdBest = is_null($this->QtyReceived())
	  ? $this->Value('InvcQtySent')	// use invoice qty if no manual count available
	  : $this->QtyReceived()
	  ;
	$dlrLineCost = $qRecdBest * $this->Value('CostInvPer');
	
	$arUpd = NULL;
	$sTotChgs = clsArray::Nz($arState,'s.chgs.tot');
	$sBalChgs = clsArray::Nz($arState,'s.chgs.bal');
	
	if (!clsMoney::Same($dlrLineCost,$this->Value('CostActTot'))) {
	    $arUpd['CostActTot'] = $dlrLineCost;

	    if (!is_null($sTotChgs)) {
		$sTotChgs .= ', ';
	    }
	    $sTotChgs .= $this->Value('CostActTot').'&rarr;'.$dlrLineCost;
	}
	
	$dlrBal = clsArray::Nz($arState,'line.bal');
	$dlrBal += $dlrLineCost;
	$arState['line.bal'] = $dlrBal;
	if (!clsMoney::Same($dlrBal,$this->Value('CostActBal'))) {
	    $arUpd['CostActBal'] = $dlrBal;

	    if (!is_null($sBalChgs)) {
		$sBalChgs .= ', ';
	    }
	    $sBalChgs .= $this->Value('CostActBal').'&rarr;'.$dlrBal;
	}
	
	if (!is_null($arUpd)) {
	    $this->Update($arUpd);
	}
	
	$arState['s.chgs.tot'] = $sTotChgs;
	$arState['s.chgs.bal'] = $sBalChgs;
    }
    
    // -- FIELD ACTIONS -- //
    // ++ CLASS NAMES ++ //
    
    protected function ItemsClass() {
	return 'clsItems';
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      HISTORY:
	2010-11-28 Created for viewing data
    */
    public function ItemObj() {
	throw new exception('Call ItemRecord() instead of ItemObj().');
	$idItem = $this->Row['ID_Item'];
	$objItem = $this->objDB->Items()->GetItem($idItem);
	return $objItem;
    }
    protected function ItemRecord() {
	$idItem = $this->ItemID();
	$rc = $this->ItemTable($idItem);
	return $rc;
    }
    
    // -- RECORDS -- //
    // ++ ACTIONS ++ //
    
    /*----
      INPUT:
	iBin: ID of destination bin
	iQty: quantity to move
      HOW: lets VbzAdminStkItems do all the work
      HISTORY:
	2010-12-01 Created for RstkRcd's items-admin form
    */
    public function Move_toBin($idBin,$iQty) {
	if (empty($iQty)) {
	    throw new exception('VbzCart Error: Trying to move zero quantity');
	}
	return $this->StockLineTable()->Add_fromRestock($idBin,$iQty,$this);
    }
    /*----
      ACTION: files the given quantity by:
	* incrementing QtyFiled
	* upping QtyRecd if QtyFiled is greater (later: make user confirm this?)
      DOES NOT: create an event -- doesn't really have enough information for this to be useful.
      HISTORY:
	2010-12-02 Created for Move_toBin() -> VbzAdminStkItems::Add_fromRestock()
      USAGE: for larger processes to use -- does not log event or make sure quantities end up anywhere
    */
    public function DoFile_Qty($iQty) {
	$qtyRecd = $this->Value('QtyRecd');
	$qtyFiled = $this->Value('QtyFiled');

  	$qtyFiledNow = $qtyFiled + $iQty;
	$arChg = array(
	  'QtyFiled'	=> $qtyFiledNow
	  );

	if ($qtyFiledNow > $qtyRecd) {
	    $qtyRecdNow = $qtyFiledNow;
	    $arChg['QtyRecd'] = $qtyRecdNow;
	}

	return $this->Update($arChg);
    }
    
    // -- ACTIONS -- //
}
