<?php
/*
  PURPOSE: classes for handling individual lines in received restocks
  HISTORY:
    2014-03-09 split off from received.php
*/
class clsRstkRcdLines extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsRstkRcdLine');
	  $this->Name('rstk_rcd_line');
	  $this->KeyName('ID');
	  $this->ActionKey('rstk-rcd-line');
    }
}
class clsRstkRcdLine extends clsDataSet {
    /*----
      HISTORY:
	2010-11-28 Created from boilerplate
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      HISTORY:
	2010-11-28 Created for viewing data
    */
    public function ItemObj() {
	$idItem = $this->Row['ID_Item'];
	$objItem = $this->objDB->Items()->GetItem($idItem);
	return $objItem;
    }
    /*----
      INPUT:
	iBin: ID of destination bin
	iQty: quantity to move
      HOW: lets VbzAdminStkItems do all the work
      HISTORY:
	2010-12-01 Created for RstkRcd's items-admin form
    */
    public function Move_toBin($iBin,$iQty) {
	assert('!empty($iQty);');
	return $this->objDB->StkItems()->Add_fromRestock($iBin,$iQty,$this);
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
}
