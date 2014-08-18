<?php
/*
  LIBRARY: place.php - classes for managing stock places
  HISTORY:
  HISTORY:
    2014-03-22 extracted Item classes from place.php
      Made VCT_StkLines descend from clsVbzTable rather than clsStkItems
    2014-06-02 Changing terminology from "stock items" to "stock lines".
*/

class VCT_StkLines extends clsVbzTable {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(KS_TBL_STOCK_LINES);
	  $this->ClassSng('VCR_StkLine');
	  $this->ActionKey(KS_ACTION_STOCK_LINE);
    }

    // -- SETUP -- //
    // ++ DATA TABLE ACCESS ++ //

    public function StockLog() {
	throw new exception('StockLog() is deprecated - call StockLineLog().');
	return $this->Engine()->StkLog();
    }
    protected function StockLineLog($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*-----
      RETURNS: Recordset containing list of stock for the given item (qtys, bin, bin name, place, place name, notes)
      TODO: Rename as Records_forItem_info()
    */
    public function List_forItem($iItemID) {
	$sql = 'SELECT '
	  .'ID, QtyForSale, QtyForShip, QtyExisting, ID_Bin, ID_Place, BinCode, WhName, Notes '
	  .'FROM qryStk_lines_remaining WHERE (ID_Item='.$iItemID.');';
	$rsStock = $this->DataSQL($sql,KS_CLASS_STOCK_LINE_INFO);
	return $rsStock;
    }
    /*----
      RETURNS: recordset of stock items for the given Bin
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function Data_forBin($iBin) {
	$rs = $this->GetData('ID_Bin='.$iBin);
	return $rs;
    }
    /*----
      PURPOSE: retrieve a dataset of stock for the given title
      HISTORY:
	2012-02-03 created
    */
    public function Records_forTitle($iTitle) {
	$sql = 'SELECT * FROM qryStk_lines_Title_info WHERE ID_Title='.$iTitle;
	return $this->DataSQL($sql);
    }
    /*----
      HISTORY:
	2012-03-11 created for title stock summary
    */
    public function Data_forItem($iID,$iSort=NULL) {
	$rs = $this->GetData('ID_Item='.$iID,NULL,$iSort);
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: number of items for sale in stock - defaults to entire stock,
	but can be filtered.]
      USED BY: eventually will be used by sidebar indicia, but that code is
	currently commented out
    */
    public function Count_inStock($sqlFilt=NULL) {
	$sql = 'SELECT SUM(QtyForSale) AS Qty FROM qryStkItms_for_sale';
	if (!is_null($sqlFilt)) {
	    $sql .= ' WHERE '.$sqlFilt;
	}
	$rc = $this->Engine()->Make('clsRecs_generic');
	$rc->Query($sql);
	$rc->NextRow();
	return $rc->Value('Qty');
    }
    /*----
      NOTE: Currently not being used because there are no
	non-admin-level Stock classes. When that is fixed,
	then we'll want to use this for showing stock counts
	for catalog items.
    */
    public function Count_inStock_forItems($sqlIDs) {
	return $this->Count_inStock("ID_Item IN ($sqlIDs)");
    }
    /*----
      PUBLIC so Bin can call it
    */
    public function ItemQty_inBin($idBin,$idItem) {
	$sqlTbl = $this->NameSQL();
	$sql = <<<__END__
SELECT SUM(Qty) AS QtyTotal FROM $sqlTbl
WHERE (ID_Bin=$idBin)
AND (ID_Item=$idItem)
AND (WhenRemoved IS NULL)
GROUP BY ID_Bin;
__END__;
	$rc = $this->Engine()->DataSet($sql);
	$qRows = $rc->RowCount();
	switch ($qRows) {
	  case 0:
	    $rtn = NULL;
	    break;
	  case 1:
	    $rc->NextRow();	// load first/only row
	    $rtn = $rc->Value('QtyTotal');
	    break;
	  default:
	    throw new exception("This just shouldn't happen.");
	}
	return $rtn;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CALCULATED ARRAYS ++ //

    /*-----
      RETURNS: List of items needed to fill stock minimums
    */
    public function Needed() {
	$sql = 'SELECT * FROM qryItems_needed_forStock';
	$objRecs = $this->objDB->DataSet($sql);
	if ($objRecs->HasRows()) {
	    while ($objRecs->NextRow()) {
		$id = $objRecs->ID;
		$arOut[$id]['min'] = $objRecs->QtyMin_Stk;
		$arOut[$id]['got'] = $objRecs->QtyForSale;
		$arOut[$id]['need'] = $arOut[$id]['min'] - $arOut[$id]['got'];
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }

    // -- CALCULATED ARRAYS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Add the given quantity of the given Catalog Item to the given Bin,
	and log the event if a suitably-provisioned $oEvent is passed.
      USAGE: It is the caller's responsibility to ensure that the quantity added has been removed from somewhere else.
      PUBLIC so that Bin can use it to add items during the package return process.
      METHODOLOGY:
	Look for lines which already have some of the given item.
	Add this quantity to the line with the lowest ID, to minimize fragmentation.
	  (This negates the need to account for the deprecated WhenRemoved field being set.)
    */
    public function Add($qAdd,$idItem,$idBin,clsStockEvent $oEvent=NULL) {
	$doReally = !is_null($oEvent);
	$sqlFilt = "(ID_Bin=$idBin) AND (ID_Item=$idItem)";
	$sqlSort = 'ID';
	$sqlTbl = $this->NameSQL();
	$sql = "SELECT ID, Qty FROM $sqlTbl WHERE $sqlFilt ORDER BY $sqlSort LIMIT 1";
	$rc = $this->DataSQL($sql);

	if ($rc->HasRows()) {
	    // add quantity to this line
	    $rc->NextRow();			// load the record
	    $idLine = $rc->KeyValue();
	    $qBefore = $rc->QtyEffective();	// get qty already in line
	    $qAfter = $qBefore + $qAdd;
	    $sEvent = "+item <b>$idItem</b> -> sline <b>$idLine</b>: qty <b>$qBefore</b> before, adding <b>$qAdd</b>";

	    if ($doReally) {
		$oEvent->SetQtyLineBefore($qBefore);
		//$oEvent->SetQtyLineAfter($qAfter);
		$oEvent->QtyAdded($qAdd);
		$oEvent->StockLineID($idLine);
		$oEvent->ItemID($idItem);
		$oEvent->Write($sEvent);
	    }

	    $arUpd = array(
	      'Qty'		=> $qAfter,
	      'WhenChanged'	=> 'NOW()',
	      'WhenRemoved'	=> 'NULL'	// make sure this is not set
	      );
	    $rc->Update($arUpd);
	} else {
	    // create a new line
	    /*
	    $arIns = array(
	      'ID_Bin'		=> $idBin,
	      'ID_Item'		=> $idItem,
	      'Qty'		=> $qAdd,
	      'WhenAdded'	=> 'NOW()',
	      );
	    $idLine = $rc->Table()->Insert($arIns);
	    */
	    $idLine = $this->AddLine($idBin,$idItem,$qAdd);
	    $sEvent = "created stock line ID=$idLine in bin ID=$idBin with qty $qAdd of item ID=$idItem";

	    if ($doReally) {
		$oEvent->SetQtyLineBefore(0);
		//$oEvent->SetQtyLineAfter($qAdd);
		$oEvent->QtyAdded($qAdd);
		$oEvent->StockLineID($idLine);
		$oEvent->ItemID($idItem);
		$oEvent->Write($sEvent);
	    }
	}
	return $sEvent;
    }
    /*----
      USAGE: Internal; does not create an event.
    */
    public function AddLine($idBin,$idItem,$qty) {
	$arIns = array(
	  'ID_Bin'	=> $idBin,
	  'ID_Item'	=> $idItem,
	  'Qty'		=> $qty,
	  'WhenAdded'	=> 'NOW()',
	  );
	$idLine = $this->Insert($arIns);
	return $idLine;
    }
    /*----
      ACTION: Remove the given quantity of the given Catalog Item from the given Bin,
	and log the event if a suitably-provisioned $oEvent is passed.
      USAGE: It is the caller's responsibility to ensure that the quantity removed is added somewhere else.
      PUBLIC so that Bin can use it to remove items during the packaging process.
      METHODOLOGY: start with lines containing the smallest quantities, to minimize fragmentation.
      INPUT:
	$nQty = quantity to remove from stock
	$idItem = ID of catalog item being moved
	$idBin = bin from which quantity is being removed
	$oEvent (optional) = provisioned event object to use for logging
	  If NULL, then the transfer is only simulated.
    */
    public function Remove($nQty,$idItem,$idBin,clsStockEvent $oEvent=NULL) {
	$doReally = !is_null($oEvent);
	$sqlFilt = "(ID_Bin=$idBin) AND (ID_Item=$idItem)";
	$sqlSort = 'Qty';
	$sqlTbl = $this->NameSQL();
	$sql = "SELECT ID, Qty FROM $sqlTbl WHERE $sqlFilt ORDER BY $sqlSort";
	$rs = $this->DataSQL($sql);

	$arOut = NULL;
	$qNeed = $nQty;	// quantity still needed
	while ($rs->NextRow() && ($qNeed > 0)) {
	    if ($rs->Qty() > $qNeed) {
		// remove $qNeed; done
		$qTake = $qNeed;
	    } else {
		// remove $Qty() and continue
		$qTake = $rs->Qty();
	    }

	    $rs->Remove($qTake,$oEvent);

	    $idLine = $rs->KeyValue();
	    $sCond = $doReally?'':' would be';
	    $htDescr = "<b>$qTake</b>$sCond removed from line ID=$idLine; $qLeft remaining.";
	    $arOut[] = $htDescr;
	    $qNeed -= $qTake;
	}
	return $arOut;
    }
    /*----
      ACTION: Add a line item from an inventory count
	We don't know where it came from; probably the result of a bug or miscount somewhere along the line.
      USAGE: ONLY for adding an item discovered during an inventory count. Don't use for creating records for any other purpose.
      NOTE: Does not create event record; caller should do that. This is mainly for logistical reasons -- there may be multiple
	lines for a given item, and I haven't bothered to try matching them up; the algorithm just finds one of them, updates its
	quantity, and zeroes the rest. Ideally, it would distribute quantities found to match existing quantities to the
	greatest possible extent, and create a new record for any overflow. This would make it easier to track every time a given
	item was counted, It might be more sensible to just look at when the item's current box was last inventoried, because
	presumably the item is definitely there when it is put into the box -- but we don't know if other quantities of the
	same Item are correct. In the long term, we probably either need to track individual pieces, or else prohibit separate
	line numbers for multiple pieces of the same Item.
      FUTURE: Rename from InventoryAdd to Add_fromInventory
    */
    public function InventoryAdd($iBin,$iItem,$iQty) {
	$ar = $this->Datarray_Add_base($iBin,$iItem,$iQty,'NOW()');
	return $this->Insert($ar);
    }
    protected function Datarray_Add_base($iBin,$iItem,$iQty,$iWhenCounted=NULL) {
	$arIns = array(
	  'ID_Bin'	=> $iBin,
	  'ID_Item'	=> $iItem,
	  'Qty'		=> $iQty,
	  'WhenAdded'	=> 'NOW()',
	  'WhenCounted'	=> SQLValue($iWhenCounted),
	  );
	return $arIns;
    }
    /*----
      ACTION: Add a line from a package back into stock
	* removes the quantity from the package
	* creates a new stock line for that quantity
      ASSUMES: we want to add ALL items from the row.
      HISTORY:
	2011-10-08 written
    */
    public function Add_fromPkgLine(clsPkgLine $iSrce,$iBin) {
	$idItem = $iSrce->Value('ID_Item');
	$objItem = $iSrce->ItemObj();
	$objPkg = $iSrce->PkgObj();
	$qty = $iSrce->ItemQty();

	// - log start of event
	$objBin = $this->objDB->Bins($iBin);
	$txtEv = 'Moving qty '.$qty
	  .' of item '.$objItem->CatNum()
	  .' from package '.$objPkg->AdminLink_name()
	  .' to bin '.$objBin->AdminLink_name();
	$out = $txtEv;
	$arEv = array(
	  'descr'	=> $txtEv,
	  'code'	=> 'MV-'.clsStkLog::chTypeRstk,
	  'where'	=> __METHOD__
	  );
	$objBin->StartEvent($arEv);

	$iSrce->DoVoid('Moving to bin ID='.$iBin.' ('.$objBin->Name().')');
	$ar = $this->Datarray_Add_base($iBin,$idItem,$qty);

	$this->Insert($ar);

	$objBin->FinishEvent();
	return $out;
    }
    /*----
      ACTION: Add a line from a received restock
      NOTE: Does create event record
      INPUT:
	iBin: ID of destination bin
	iItem: ID of item
	iQty: quantity being moved
	iLine: ID of received restock line
    */
    public function Add_fromRestock($idBin,$nQty,clsRstkRcdLine $rcLine) {
	$idItem = $rcLine->ItemID();
	$idRstk = $rcLine->RstkRcdID();

	if (empty($nQty)) {
	    throw new exception("Internal error: adding quantity '$nQty' to stock.");
	}
	if (is_null($idItem)) {
	    throw new exception("Could not retrieve item ID from restock line.");
	}

	// LOG event start

	$rcRstk = $this->Engine()->RstkRcds($idRstk);
	$rcBin = $this->Engine()->Bins($idBin);
	$txtEv = 'Moving qty '.$nQty.' from restock '.$rcRstk->AdminLink_name().' to bin '.$rcBin->AdminLink_name();

	$out = $txtEv;

	// - create system log event:
	$arEv = array(
	  'descr'	=> $txtEv,
	  'code'	=> 'MV-'.clsStkLog::chTypeRstk,
	  'where'	=> __METHOD__
	  );
	$idEvSys = $rcBin->StartEvent($arEv);

	// - create stock log event
	$tStkLog = $this->StockLog();
	$rcStkEv = $tStkLog->StartEvent(
	  $nQty,
	  $idBin,
	  clsStkLog::chTypeRstk,
	  $idRstk,
	  NULL,		// stock line not yet created
	  $rcLine->KeyValue(),
	  $txtEv,
	  $idEvSys
	  );

	// CHANGE the data

	// - remove quantity from restock:
	$ok = $rcLine->DoFile_Qty($nQty);

	if ($ok) {
	    // - insert row in stock:
	    $arIns = array(
	      'ID_Bin'		=> $idBin,
	      'ID_Item'		=> $idItem,
	      'Qty'		=> $nQty,
	      'WhenAdded'	=> 'NOW()',
	      'WhenCounted'	=> NULL,
	      );
	    $this->Insert($arIns);
	    $idNew = $this->Engine()->NewID();
	}

	if ($ok) {
	    // update stock event log:
	    $rcStkEv->StockLineID($idNew);	// save the stock line ID
	    $rcStkEv->Finish();
	    // update system log event:
	    $arEv = array(
	      'descrfin'	=> 'OK'
	      );
	    $out .= ' - OK';
	} else {
	    // update system log event:
	    $txtErr = $this->Engine()->getError();
	    $this->Engine()->ClearError();
	    $arEv = array(
	      'descrfin'	=> 'Error: '.$txtErr,
	      'error'		=> TRUE
	      );
	    $out .= '<br> - Error: '.$txtErr;
	}
	$rcBin->FinishEvent($arEv);

	return $out;
    }

    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      HISTORY:
	2011-01-30 Replaced references to "Descr" field with "Notes" -- I can only think that this
	  is what was originally intended.
    */
    public function Listing_forItem($iObj) {
	$obj = $iObj;
	$id = $obj->KeyValue();

	$objRecs = $this->Data_forItem($id,'WhenRemoved,WhenAdded,WhenChanged');
	if ($objRecs->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Bin</th>
    <th>Qty</th>
    <th>Added</th>
    <th>Changed</th>
    <th>Counted</th>
    <th>Removed</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$id = $objRecs->KeyValue();

		$objBin = $objRecs->BinRecord();
		$htBin = $objBin->AdminLink_name();

		$ftID = $objRecs->AdminLink();
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$intQty = $objRecs->Value('Qty');

		$txtNotes = $objRecs->Value('Notes');
		$isActive = is_null($objRecs->Value('WhenRemoved')) && ($intQty > 0) && $objBin->IsActive();
		$isValid = $objBin->PlaceRecord()->IsActive();
		if (!$isActive) {
		    $wtStyle .= ' color: #888888;';
		}
		if ($isValid) {
		    $wtStyleCell = '';
		} else {
		    $wtStyleCell = 'style="text-decoration: line-through;"';
		}
		$ftWhenAdd = DataDate($objRecs->Value('WhenAdded'));
		$ftWhenChg = DataDate($objRecs->Value('WhenChanged'));
		$ftWhenCnt = DataDate($objRecs->Value('WhenCounted'));
		$ftWhenRmv = DataDate($objRecs->Value('WhenRemoved'));
		$out .= <<<__END__
  <tr style="$wtStyle">
    <td $wtStyleCell>$ftID</td>
    <td $wtStyleCell>$htBin</td>
    <td>$intQty</td>
    <td>$ftWhenAdd</td>
    <td>$ftWhenChg</td>
    <td>$ftWhenCnt</td>
    <td>$ftWhenRmv</td>
  </tr>
__END__;
		if (!is_null($txtNotes)) {
		    $out .= "<tr style=\"$wtStyle\"\n><td colspan=4>$txtNotes</td></tr>";
		}

		$isOdd = !$isOdd;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'There has never been any stock for this item.<br><small><b>SQL</b>: '.$sql.'</small>';
	}
	return $out;
    }
}
/*####
  HISTORY:
    2011-03-29 changed parent from clsAdminData to clsDataSet
      clsAdminData apparently does not work with form-building routines,
	and I don't *think* it's needed anymore.
*/
class VCR_StkLine extends clsVbzRecs {

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //

    protected function CItemsClass() {
	if (clsDropInManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItems';
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA FIELDS ACCESS ++ //

    /*----
      PUBLIC because Package objects need to access it
    */
    public function BinID() {
	return $this->Value('ID_Bin');
    }
    /*----
      PUBLIC because stock-event log needs to access it
    */
    public function ItemID() {
	return $this->Value('ID_Item');
    }
    public function Qty($qty=NULL) {
	return $this->Value('Qty',$qty);
    }
    /*----
      RETURNS: The *effective* quantity, taking into account any flags which might affect things.
	This used to be so that we could account for WhenRemoved (if set, quantity is effectively
	zero), but we're phasing that out. Since no new activities will be setting that field,
	we're just going to ignore it for now and just return Qty().
    */
    public function QtyEffective() {
	return $this->Qty();
    }
    /*----
      RETURNS: quantity actually in stock
	If WhenRemoved is not NULL, then qty is zero.
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function Qty_inStock() {
	if (!is_null($this->Value('WhenRemoved'))) {
	    return 0;
	} else {
	    return $this->Value('Qty');
	}
    }

    // -- DATA FIELDS ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function BinTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_BINS,$id);
    }
    protected function CItemTable($id=NULL) {
	return $this->Engine()->Make($this->CItemsClass(),$id);
    }
    protected function StockLogTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    public function Bin() {
	throw new exception('Bin() is deprecated - use BinRecord().');
	return $this->BinObj();
    }
    public function BinObj() {
	throw new exception('BinObj() is deprecated - use BinRecord().');
    }
    public function BinRecord() {
	$id = $this->BinID();
	$rc = $this->BinTable($id);
	return $rc;
    }
    public function Item() {
	throw new exception('Item() is deprecated - use LCItemRecord().');
	return $this->ItemObj();
    }
    public function ItemObj() {
	throw new exception('ItemObj() is deprecated - use LCItemRecord().');
    }
    public function LCItemRecord() {
	$id = $this->ItemID();
	if (is_null($id)) {
	    $rc = NULL;
	} else {
	    $rc = $this->CItemTable($id);
	}
	return $rc;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ ACTIONS ++ //

    public function Remove($qTake,clsStockEvent $oEvent=NULL) {
	$doReally = is_object($oEvent);
	$qFound = $this->Qty();
	$qLeft = $qFound - $qTake;
	$idStockLine = $this->KeyValue();
	if ($doReally) {
	    $oEvent->SetQtyLineBefore($qFound);
	    $oEvent->QtyAdded(-$qTake);
	    $oEvent->StockLineID($idStockLine);
	    //$oEvent->ItemID($idItem);
	    $oEvent->Write("removing <b>$qTake</b> from stock line ID $idStockLine: <b>$qFound</b> found, <b>$qLeft</b> remain");
	    $arUpd = array(
	      'Qty'=>$qLeft,
	      'WhenChanged'=>'NOW()'
	      );
	    $this->Update($arUpd);

	    $oEvent->Finish();
	}
    }
    /*----
      ACTION: count all (active, valid) stock in the current recordset
      INPUT: recordset
      RETURNS: array of Item quantities
	array[item id] = quantity in stock
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function CountStock() {
	$arOut = NULL;
	while ($this->NextRow()) {
	    $qty = $this->Qty_inStock();
	    if ($qty > 0) {
		$idItem = $this->Value('ID_Item');
		$qtySum = nzArray($arOut,$idItem);
		$arOut[$idItem] = $qtySum + $qty;
	    }
	}
	return $arOut;
    }
    /*-----
      ACTION: Move this stock item line to the given bin and log the change
    */
    public function MoveToBin($iBin,$iDescr=NULL) {
	$objLog = $this->StockLogTable();
	$id = $this->Value('ID');
	$rcEv = $objLog->StartEvent(
	  $id,		// stock line
	  $this->Value('ID_Bin'),	// stock bin
	  clsStkLog::chTypeBin,	// other type
	  $iBin,		// other container
	  $id,		// other line -- reusing same line, just changing bins
	  $iDescr,$this->Value('Qty'));
	$arUpd = array(
	  'ID_Bin'	=> $iBin,
	  'WhenChanged'	=> 'NOW()');
	$this->Update($arUpd);
	$rcEv->Finish();
    }
    /*----
      USED BY: inventory counting process
      HISTORY:
	2011-03-03
	  Changed overloaded self-references to Value()
	  Added iDescr and iQty to arg list -- caller will probably need modifying next
	2011-03-19 Was duplicate of $iQty ($iQty,$iDescr,$iQty) - not sure what happened. Removed 2nd one.
    */
    public function UpdateQty($iQty,$iDescr) {
	$objLog = $this->objDB->StkLog();
	// log the attempt
	$idThis = $this->Value('ID');
	$idBin = $this->Value('ID_Bin');
	$idEvent = $objLog->LogEvent_Start(
	  $idThis,	// stock line
	  $idBin,	// stock bin
	  clsStkLog::chTypeBin,	// other type
	  $idBin,	// other container
	  $idThis,	// other line
	  $iDescr,$iQty);

	// make the change
	$arUpd = array(
	  'Qty'	=> $iQty,
	  'WhenCounted' => 'NOW()');
	if ($iQty != $this->Value('Qty')) {
	    $arUpd['WhenChanged'] = 'NOW()';
	}
	if ($iQty == 0) {
	    $arUpd['WhenRemoved'] = 'NOW()';
	}
	$this->Update($arUpd);

	// log the completion
	$objLog->LogEvent_Finish($idEvent);
    }
    /*-----
      ACTION: Move this stock item line to the given package line and log the change
      INPUT:
	iPkg: package into which this stock line is being moved
	iLine: package line into which stock line is being moved
	iDescr: reason for move (description of activity)
	iQty: requested quantity
      RETURNS: quantity actually moved
      RULES:
	Caller handles creation of package line and putting quantity in it.
	This function handles stock record adjustment:
	  iQty =< $this->Qty: $this->Qty -= iQty, return iQty
	  iQty > $this->Qty: $this->Qty = 0, return old $this->Qty
    */
    public function MoveToPkg($iPkg,$iPLine,$iDescr=NULL,$iQty) {
	$tSLog = $this->StockLogTable();
	$idEvent = $tSLog->LogEvent_Start(
	  $this->KeyValue(),		// stock line
	  $this->Value('ID_Bin'),	// bin
	  clsStkLog::chTypePkg,	// other type
	  $iPkg,		// other container
	  $iPLine,		// other line
	  $iDescr,$this->Value('Qty'));

	// do quantity calculations
	$qtyOld = $this->Value('Qty');
	if ($iQty >= $qtyOld) {
	    $qtyNew = 0;
	    $qtyRtn = $qtyOld;
	    $sqlWhenRemoved = 'NOW()';	// the last units have been removed
	} else {
	    $qtyNew = $qtyOld - $iQty;
	    $qtyRtn = $iQty;
	    $sqlWhenRemoved = 'NULL';	// still some quantity left
	}
	$arUpd = array(
	  'Qty'		=> $qtyNew,
	  'WhenChanged'	=> 'NOW()',
	  'WhenRemoved'	=> $sqlWhenRemoved
	  );
	$this->Update($arUpd);
	$tSLog->LogEvent_Finish($idEvent);
	return $qtyRtn;
    }
    /*----
      ACTION: Move the given quantity into a new stock line
      HISTORY:
	2014-08-05 written
    */
    private function AdminSplit($qMove) {
	$qOld = $this->Qty_inStock();
	$qNew = $qOld - $qMove;
	$arEv = array(
	  clsSysEvents::ARG_DESCR_START	=> "splitting off $qMove: had $qOld, ending with $qNew",
	  clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	  clsSysEvents::ARG_CODE		=> 'SPL',
	  clsSysEvents::ARG_PARAMS		=> "qty=$qMove/before=$qOld/after=$qNew",
	  );
	$rcEv = $this->CreateEvent($arEv);
	// remove $qMove from current line
	$arUpd = array(
	  'Qty'		=> $qNew,
	  'WhenChanged'	=> 'NOW()',
	  );
	$this->Update($arUpd);
	// create a new line with $qMove in it
        $this->Table()->AddLine($this->BinID(),$this->ItemID(),$qMove);
	$rcEv->Finish();
	$this->Qty_inStock($qNew);
    }

    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      HISTORY:
	2011-03-29 adapted from clsPackage to VbzAdminStkItem
    */
    private function PageForm() {
	// create fields & controls

	if (empty($this->frmPage)) {
	    $frmPage = new clsForm_recs($this);

	    $frmPage->AddField(new clsFieldNum('Qty'),		new clsCtrlHTML(array('size'=>2)));
	    $frmPage->AddField(new clsFieldTime('WhenAdded'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('WhenChanged'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('WhenCounted'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('WhenRemoved'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldNum('Cost'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsField('CatNum'),		new clsCtrlHTML(array('size'=>20)));
	    $frmPage->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>40)));

	    $this->frmPage = $frmPage;
	}
	return $this->frmPage;
    }
    /*-----
      ACTION: Save the user's edits to the package
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
	2011-03-29 copied from clsPackage to VbzAdminStkItem
    */
    private function AdminSave($iNotes) {
	$out = $this->PageForm()->Save($iNotes);
	$this->AdminRedirect(array('edit'=>FALSE),$out);
	return $out;
    }
    /*----
      HISTORY:
	2011-03-29
	  fixing bugs due to class API change
	  adding edit capability
	  removed check for exactly one record; seems to never be a problem
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	if (clsHTTP::Request()->getBool('btnSave')) {
	    $this->AdminSave(clsHTTP::Request()->GetText('EvNotes'));		// save edit to existing package
	}
	if (clsHTTP::Request()->getBool('btnSplit')) {
	    $this->AdminSplit(clsHTTP::Request()->GetInt('qtyMove'));
	}
	$sDo = $oPage->PathArg('do');
	$doEdit = $doSplit = FALSE;
	switch($sDo) {
	  case 'edit':
	    $doEdit = TRUE;
	    break;
	  case 'split':
	    $doSplit = TRUE;
	    break;
	}

	$id = $this->KeyValue();
	$strName = "Stock Item ID #$id";
/*
	$objSection = new clsWikiSection($objPage,$strName);
	$objSection->ToggleAdd('edit','edit the stock item record');
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';
*/
	$oPage->TitleString($strName);
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this line-item'),
	  );
	if ($this->Qty_inStock() > 1) {
	    $arActs[] = new clsActionLink_option(array(),'split','do',NULL,NULL,'split some of this into another line-item');
	}
	$oPage->PageHeaderWidgets($arActs);

	$objItem = $this->LCItemRecord();
	$wtItem = $objItem->FullDescr();

	$objBin = $this->BinRecord();
	//$wtBin = $objBin->AdminLink($objBin->NameLong());
	$wtBin = $objBin->AdminLink_name();

	$intQty		= $this->Value('Qty');
	$dtWhenAdded	= $this->Value('WhenAdded');
	$dtWhenChged	= $this->Value('WhenChanged');
	$dtWhenCnted	= $this->Value('WhenCounted');
	$dtWhenRmved	= $this->Value('WhenRemoved');
	$prcCost	= $this->Value('Cost');
	$txtCatNum	= $this->Value('CatNum');
	$txtNotes	= $this->Value('Notes');

	$out = NULL;

	$sMsgs = clsHTTP::DisplayOnReturn();
	if (!is_null($sMsgs)) {
	    $out .= $sMsgs.'<hr>';
	}

	$htActForm = NULL;	// form for other stuff besides editing
	if ($doEdit) {
	    $objForm = $this->PageForm();

	    $out .= "\n<form method=post>";

	    $ctQty	=  $objForm->RenderControl('Qty');
	    $ctWhenAdded = $objForm->RenderControl('WhenAdded');
	    $ctWhenChged = $objForm->RenderControl('WhenChanged');
	    $ctWhenCnted = $objForm->RenderControl('WhenCounted');
	    $ctWhenRmved = $objForm->RenderControl('WhenRemoved');
	    $ctCost	= $objForm->RenderControl('Cost');
	    $ctCatNum	= $objForm->RenderControl('CatNum');
	    $ctNotes	= $objForm->RenderControl('Notes');
	} else {
	    $ctQty = $intQty;
	    $ctWhenAdded = $dtWhenAdded;
	    $ctWhenChged = $dtWhenChged;
	    $ctWhenCnted = $dtWhenCnted;
	    $ctWhenRmved = $dtWhenRmved;
	    $ctCost = $prcCost;
	    $ctCatNum = $txtCatNum;
	    $ctNotes = htmlspecialchars($txtNotes);

	    $ctSplitMax = $intQty-1;
	    $ctSplitDef = round($intQty/2);
	}

	$out .= <<<__END__
<table>
  <tr><td align=right><b>Item</b>:</td><td>$wtItem</td></tr>
  <tr><td align=right><b>Bin</b>:</td><td>$wtBin</td></tr>
  <tr><td align=right><b>Qty</b>:</td><td>$ctQty</td></tr>
  <tr><td align=right><b>Time stamps</b>:</td></tr>
  <tr><td align=center colspan=2>
    <table>
      <tr><td align=right><b>Added</b>:</td><td>$ctWhenAdded</td></tr>
      <tr><td align=right><b>Changed</b>:</td><td>$ctWhenChged</td></tr>
      <tr><td align=right><b>Counted</b>:</td><td>$ctWhenCnted</td></tr>
      <tr><td align=right><b>Removed</b>:</td><td>$ctWhenRmved</td></tr>
    </table>
  </td></tr>
  <tr><td align=right><b>Cost</b>:</td><td>$ctCost</td></tr>
  <tr><td align=right><b>Cat #</b>:</td><td>$ctCatNum</td></tr>
  <tr><td align=right><b>Notes</b>:</td><td>$ctNotes</td></tr>
</table>
__END__;

	if ($doEdit) {
	    // This does not appear to be saving in the event log. It may be that forms
	    // are not case-sensitive, in which case the record's notes may be overwriting it or something.
	    $out .= <<<__END__
Notes for event log:<textarea name=EvNotes rows=3></textarea>
<input type=submit name="btnSave" value="Save">
</form>
__END__;
	}
	if ($doSplit) {
	    $out .= <<<__END__
<form method=post>
  Quantity to move ($ctSplitMax maximum):
  <input name=qtyMove value="$ctSplitDef">
  <input name="btnSplit" value="Split" type=submit>
</form>
__END__;
	}

	$oSkin = $oPage->Skin();
	$out .=
	  $oPage->SectionHeader('Stock History',NULL,'section-header-sub')
	  .$this->AdminStockHistory()
	  .$oPage->SectionHeader('System Log',NULL,'section-header-sub')
	  .$this->EventListing();

	return $out;
    }
    /*----
      PURPOSE: display an admin listing of stock items in the current recordset
      HISTORY:
	2012-02-03 created for Title's display of stock
    */
    public function AdminList(array $iOpts) {
	$strNone = NzArray($iOpts,'none','No stock found.');

	$out = NULL;
	if ($this->HasRows()) {
	    $out .= <<<__END__
<table>
  <tr>
    <th>Stk ID</th>
    <th>Item</th>
    <th>Qty</th>
    <th>Bin</th>
    <th>Added</th>
    <th>Changed</th>
    <th>Counted</th>
    <th>Removed</th>
    <th>Cost</th>
  </tr>
__END__;
	    while ($this->NextRow()) {
		$htID = $this->AdminLink();
		$htItem = $this->LCItemRecord()->AdminLink_friendly();
		$htQty = $this->Value('Qty');
		$htBin = $this->BinRecord()->AdminLink_name();
		$htWhenAdd = $this->Value('WhenAdded');
		$htWhenChg = $this->Value('WhenChanged');
		$htWhenCnt = $this->Value('WhenCounted');
		$htWhenRmv = $this->Value('WhenRemoved');
		$htCost = $this->Value('Cost');

		$out .= "\n<tr>"
		  ."\n<td>$htID</td>"
		  ."\n<td>$htItem</td>"
		  ."\n<td>$htQty</td>"
		  ."\n<td>$htBin</td>"
		  ."\n<td>$htWhenAdd</td>"
		  ."\n<td>$htWhenChg</td>"
		  ."\n<td>$htWhenCnt</td>"
		  ."\n<td>$htWhenRmv</td>"
		  ."\n<td>$htCost</td>"
		  ."\n</tr>"
;
		if ($this->HasValue('Notes')) {
		    $htNotes = $this->Value('Notes');
		    $out .= '<tr><td></td><td colspan=8>'.$htNotes.'</td></tr>';
		}
	    }
	    $out .= '</table>';
	} else {
	    $out = $strNone;
	}
	return $out;
    }
    public function AdminStockHistory() {
	$tbl = $this->StockLogTable();
	//$rs = $tbl->GetData('ID_StkLine='.$this->KeyValue(),NULL,'WhenStarted DESC, WhenFinished DESC');
	//return $rs->AdminList();
	$out = $tbl->Listing_forStockLine($this->KeyValue());
	return $out;
    }
}
