<?php
/*
  LIBRARY: place.php - classes for managing stock places
  HISTORY:
  HISTORY:
    2014-03-22 extracted Item classes from place.php
      Made VCT_StkLines descend from clsVbzTable rather than clsStkItems
    2014-06-02 Changing terminology from "stock items" to "stock lines".
*/

class VCT_StkLines extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return KS_TBL_STOCK_LINES;
    }
    // CEMENT
    protected function SingularName() {
	return 'VCR_StkLine';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_STOCK_LINE;
    }
    
    // -- SETUP -- //
    // ++ CLASS NAMES ++ //
    
    /*----
      CLASS FOR: local catalog items
    */
    protected function LCItemsClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return KS_LOGIC_CLASS_LC_ITEMS;
	}
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function BinTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_BINS,$id);
    }
    protected function PlacesTable($id=NULL) {
    	return $this->Engine()->Make(KS_CLASS_STOCK_PLACES,$id);
    }
    public function StockLog() {
	throw new exception('StockLog() is deprecated - call StockLineLog().');
	return $this->Engine()->StkLog();
    }
    protected function StockInfoQuery() {
	return $this->Engine()->Make('vcqtStockLinesInfo');
    }
    protected function StockLineLog($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }
    // PUBLIC so Records can use it
    public function LCItemTable($id=NULL) {
    	return $this->Engine()->Make($this->LCItemsClass(),$id);
    }
    protected function ReceivedRestockTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_RESTOCKS_RECEIVED,$id);
    }
    protected function TitleInfoQuery() {
	return $this->Engine()->Make('vcqtTitlesInfo');
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*-----
      RETURNS: Recordset containing list of stock for the given item (qtys, bin, bin name, place, place name, notes)
      USED BY: Admin Package::AdminStock()
      TODO: Rename as Records_forItem_info()
    */
    public function List_forItem($iItemID) {
      throw new exception('Method deprecated; call Records_forItem_info() instead.');
    }
    public function Records_forItem_info($idItem) {
	$tbl = $this->StockInfoQuery();
	$rs = $tbl->ItemStatusRecords_wBin($idItem);
    /* 2016-08-13 old version
	$sql = 'SELECT '
	  .'ID, QtyForSale, QtyForShip, QtyExisting, ID_Bin, ID_Place, BinCode, WhName, Notes '
	  .'FROM qryStk_lines_remaining WHERE (ID_Item='.$idItem.');';
	$rsStock = $this->DataSQL($sql,KS_CLASS_STOCK_LINE_INFO);
	*/
	return $rs;
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
      USED FOR: stock listing in Title record display page
      HISTORY:
	2012-02-03 created
	2016-03-22 replaced stored query with SQO
    */
    public function Records_forTitle($idTitle) {
	$sqlFilt = "ID_Title=$idTitle";
	
	// This would be for if we wanted a stock summary grouped by Title
	//$sql = $this->TitleInfoQuery()->SQL_ExhibitInfo($sqlFilt);

	// This would be for if we wanted a stock summary grouped by Item
	//$sql = vcqtItemsInfo::SQL_forTitlePage($idTitle);

	// Get information for every Stock Line for this Title
	
	$sqo = vcqtStockLinesInfo::SQO_forItemStatus();
	$sqo->Select()->Source()->AddElement(
	  new fcSQL_JoinElement(
	    new fcSQL_TableSource('cat_items','i'),
	    'si.ID_Item=i.ID'
	    )
	  );
	$sqo->Select()->Fields()->SetFields(
	  array(
	    'si.WhenAdded',
	    'si.WhenChanged',
	    'si.WhenCounted',
	    'si.WhenRemoved',
	    'si.ID_Bin',
	    'si.Qty',
	    'si.Cost',
	    'si.Notes'
	    )
	  );
	$sqo->Terms()->Filters()->AddCond($sqlFilt);
	$sql = $sqo->Render();
	
	// old version - deprecated 2016-03-22
	//$sql = 'SELECT * FROM qryStk_lines_Title_info WHERE '.$sqlFilt;
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

    // -- RECORDS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: number of items for sale in stock - defaults to entire stock,
	but can be filtered.]
      USED BY: eventually will be used by sidebar indicia, but that code is
	currently commented out
    */
    public function Count_inStock($sqlFilt=NULL) {
      throw new exception('Who calls this?');
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
	$sql = $this->SQL_forItemQty_inBin($idBin,$idItem);
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
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      HISTORY:
	2016-08-28 Fixed -- was not returning anything.
    */
    protected function SQL_forItemQty_inBin($idBin,$idItem) {
	$sqlTbl = $this->NameSQL();
	$sql = <<<__END__
SELECT SUM(Qty) AS QtyTotal FROM $sqlTbl
WHERE (ID_Bin=$idBin)
AND (ID_Item=$idItem)
AND (WhenRemoved IS NULL)
GROUP BY ID_Bin;
__END__;
	return $sql;
    }
    protected function SQLobj_forRemaining_basic() {
	$osLines = new fcSQL_Table($this->NameSQL(),'sl');
	$osBins =  new fcSQL_Table($this->BinTable()->NameSQL(),'sb');
	$osJoin = new fcSQL_Join($osLines,$osBins,array('sb.ID'=>'sl.ID_Bin'));
	$arFields = array(
	    'sl.ID'			=> NULL,
	    'sl.ID_Bin'			=> NULL,
	    'sl.ID_Item'		=> NULL,
	    'IF(sb.isForSale,sl.Qty,0)'	=> 'QtyForSale',
	    'IF(sb.isForShip,sl.Qty,0)'	=> 'QtyForShip',
	    'sl.Qty'			=> 'QtyExisting',
	    'sl.CatNum'			=> NULL,
	    'sl.WhenAdded'		=> NULL,
	    'sl.WhenChanged'		=> NULL,
	    'sl.WhenCounted'		=> NULL,
	    'sl.Notes'			=> NULL,
	    'sb.Code AS BinCode'	=> NULL,
	    'sb.ID_Place'		=> NULL,
	    //'sp.Name'			=> 'WhName'
	    );
	$osJoin->FieldsArray($arFields);
	return $osJoin;
    }
    protected function SQLobj_forRemaining_withPlaces() {
	$osJoin = $this->SQLobj_forRemaining_basic();
	$osPlaces = new fcSQL_Table($this->PlacesTable()->NameSQL(),'sp');
	
	$osOut = new fcSQL_Join($osJoin,$osPlaces,array('sb.ID_Place'=>'sp.ID'));
	$arFields = array(
	    'sp.Name'			=> 'WhName'
	    );
	$osOut->FieldsArray($arFields);
	return $osOut;
    }
    /*----
      REPLACES: http://htyp.org/VbzCart/queries/qryStk_lines_remaining
    */
    protected function SQL_forRemaining() {
	$os = $this->SQLobj_forRemaining_withPlaces();

	$sql = $os->Render();
	return $sql;
    }
    /*----
      REPLACES: http://htyp.org/VbzCart/queries/qryStk_lines_remaining_forSale
    */
    protected function SQL_forRemaining_forSale() {
	$sqlJoin = $this->SQL_forRemaining();
	$sql = "SELECT * FROM ($sqlJoin) AS sr WHERE QtyForSale>0";
	
	return $sql;
    }
    /*----
      PUBLIC so LCItems table can access it when doing Stock Needed calculations
      REPLACES: http://htyp.org/VbzCart/queries/qryStkItms_for_sale
    */
    public function SQL_forItems_inStock_forSale() {
	$sqlJoin = $this->SQL_forRemaining_forSale();
	$sql = "\nSELECT"
	  ."\n  ID_Item,"
	  ."\n  SUM(rfs.QtyForSale) AS QtyForSale,"
	  ."\n  SUM(rfs.QtyForShip) AS QtyForShip,"
	  ."\n  SUM(rfs.QtyExisting) AS QtyExisting"
	  ."\n FROM ($sqlJoin) AS rfs"
	  ."\n GROUP BY ID_Item"
	  ;
	return $sql;
    }

    // -- SQL CALCULATIONS -- //
    // ++ CALCULATED ARRAYS ++ //

    /*-----
      RETURNS: List of items needed to fill stock minimums
    */
    public function Needed_array() {
	return $this->LCItemTable()->Needed_forStock_array();
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
    public function Add($qAdd,$idItem,$idBin,vcrStockEvent $oEvent=NULL) {
	$doReally = !is_null($oEvent);
	$sqlFilt = "(ID_Bin=$idBin) AND (ID_Item=$idItem)";
	$sqlSort = 'ID';
	$sqlTbl = $this->NameSQL();
	$sql = "SELECT ID, Qty, WhenRemoved FROM $sqlTbl WHERE $sqlFilt ORDER BY $sqlSort LIMIT 1";
	$rc = $this->DataSQL($sql);

	if ($rc->HasRows()) {
	    // add quantity to this line
	    $rc->NextRow();			// load the record
	    $idLine = $rc->GetKeyValue();
	    if ($rc->IsRemoved()) {
		$qBefore = 0;
	    } else {
		$qBefore = $rc->Qty();	// get qty already in line
	    }
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
    public function Remove($nQty,$idItem,$idBin,vcrStockEvent $oEvent=NULL) {
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
	    $qLeft = $rs->Qty();

	    $idLine = $rs->GetKeyValue();
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
	$ar = $this->Datarray_Add_base($iBin,$iItem,$iQty,array());
	$ar['WhenCounted'] = 'NOW()';
	return $this->Insert($ar);
    }
    protected function Datarray_Add_base($idBin,$idItem,$nQty) {
	$arIns = array(
	  'ID_Bin'	=> $idBin,
	  'ID_Item'	=> $idItem,
	  'Qty'		=> $nQty,
	  'WhenAdded'	=> 'NOW()'
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
	$objBin = $this->BinTable($iBin);
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
      TODO: rename to Add_fromReceived OSLT
      INPUT:
	iBin: ID of destination bin
	iItem: ID of item
	iQty: quantity being moved
	iLine: ID of received restock line
    */
    public function Add_fromRestock($idBin,$nQty,vcrlRstkRcdLine $rcLine) {
	$idItem = $rcLine->ItemID();
	$idRstk = $rcLine->ParentID();

	if (empty($nQty)) {
	    throw new exception("Internal error: adding quantity '$nQty' to stock.");
	}
	if (is_null($idItem)) {
	    throw new exception("Could not retrieve item ID from restock line.");
	}

	// LOG event start

	$rcRstk = $this->ReceivedRestockTable($idRstk);
	$rcBin = $this->BinTable($idBin);
	$sBin = $rcBin->Name();
	$txtEv = "Moving qty $nQty from restock $idRstk to bin $idBin ($sBin)";

	$out = $txtEv;

	// - create system log event:
	$arEv = array(
	  'descr'	=> $txtEv,
	  'code'	=> 'MV-'.clsStkLog::chTypeRstk,
	  'where'	=> __METHOD__
	  );
	$rcEvSys = $rcBin->CreateEvent($arEv);

	// - create stock log event (2016-02-24: uncommented, but not tested)
	$tStkLog = $this->StockLineLog();
	
	$rcStkEv = $tStkLog->StartEvent(
	  $nQty,
	  $idBin,
	  clsStkLog::chTypeRstk,
	  $idRstk,
	  NULL,		// stock line not yet created
	  $rcLine->GetKeyValue(),
	  $txtEv
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
	      'WhenCounted'	=> 'NULL',
	      );
	    $idNew = $this->Insert($arIns);
	    //$idNew = $this->Engine()->NewID();
	    
	    // log the change in the stock log
	    $idStkEv = $this->StockLineLog()->Log_MoveToBin(
	      $idItem,
	      $nQty,
	      $idBin,
	      clsStkLog::chTypeRstk,	// $chOtherType
	      $rcLine->ParentID(),	// $idOtherCont
	      $idNew,			// $idStkLine
	      $rcLine->GetKeyValue(),	// $idOtherLine,
	      $txtEv,			// $sDescr
	      $rcEvSys->GetKeyValue()	// $idEvent
	      );
	
	/*
	    // update stock event log:
	    $rcStkEv->StockLineID($idNew);	// save the stock line ID
	    $rcStkEv->Finish();
	*/
	    // update system log event:
	    $arEv = array(
	      'descrfin'	=> "OK (stock event ID $idStkEv)"
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
	//$rcBin->FinishEvent($arEv);
	$rcEvSys->Finish($arEv);	// finish the system event

	return $out;
    }

    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      HISTORY:
	2011-01-30 Replaced references to "Descr" field with "Notes" -- I can only think that this
	  is what was originally intended.
    */
    public function Listing_forItem($rcItem) {
	$id = $rcItem->GetKeyValue();

	$rs = $this->Data_forItem($id,'WhenRemoved,WhenAdded,WhenChanged');
	if ($rs->HasRows()) {
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
	    while ($rs->NextRow()) {
		$id = $rs->GetKeyValue();

		$rcBin = $rs->BinRecord();
		$htBin = $rcBin->SelfLink_name();

		$ftID = $rs->SelfLink();
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$intQty = $rs->Value('Qty');

		$txtNotes = $rs->Value('Notes');
		$isActive = is_null($rs->Value('WhenRemoved')) && ($intQty > 0) && $rcBin->IsActive();
		$isValid = $rcBin->PlaceRecord()->IsActive();
		if (!$isActive) {
		    $wtStyle .= ' color: #888888;';
		}
		if ($isValid) {
		    $wtStyleCell = '';
		} else {
		    $wtStyleCell = 'style="text-decoration: line-through;"';
		}
		$ftWhenAdd = fcDate::NzDate($rs->Value('WhenAdded'));
		$ftWhenChg = fcDate::NzDate($rs->Value('WhenChanged'));
		$ftWhenCnt = fcDate::NzDate($rs->Value('WhenCounted'));
		$ftWhenRmv = fcDate::NzDate($rs->Value('WhenRemoved'));
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
	    $sql = $rs->sqlMake;
	    $out = 'There has never been any stock for this item.'
	      ."\n<br><small><b>SQL</b>: $sql</small>";
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
class VCR_StkLine extends vcAdminRecordset {
    use ftFrameworkAccess;

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ FIELD VALUES ++ //

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
    protected function WhenRemoved() {
	return $this->Value('WhenRemoved');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    public function IsRemoved() {
	return !is_null($this->WhenRemoved());
    }
    /*----
      RETURNS: quantity actually in stock
	If WhenRemoved is not NULL, then qty is zero.
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function Qty_inStock() {
	if ($this->IsRemoved()) {
	    return 0;
	} else {
	    return $this->Value('Qty');
	}
    }

    // -- FIELD VALUES -- //
    // ++ TABLES ++ //
    
    protected function LCItemTable($id=NULL) {
	return $this->Table()->LCItemTable($id);
    }
    protected function BinTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_BINS,$id);
    }
    protected function StockLogTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function LCItemRecord() {
	return $this->LCItemTable($this->ItemID());
    }
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
    /* 2016-02-24 Duplicates ItemRecord(), now renamed LCItemRecord().
    public function LCItemRecord() {
	$id = $this->ItemID();
	if (is_null($id)) {
	    $rc = NULL;
	} else {
	    $rc = $this->LCItemTable($id);
	}
	return $rc;
    }//*/

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    public function Remove($qTake,vcrStockEvent $oEvent=NULL) {
	$doReally = is_object($oEvent);
	$qFound = $this->Qty();
	$qLeft = $qFound - $qTake;
	$idStockLine = $this->GetKeyValue();
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
		$qtySum = clsArray::Nz($arOut,$idItem);
		$arOut[$idItem] = $qtySum + $qty;
	    }
	}
	return $arOut;
    }
    /*-----
      ACTION: Move this stock item line to the given bin and log the change
    */
    public function MoveToBin($idBin,$sDescr=NULL) {
	$rcEv = $this->LogStart_MoveToBin($idBin,$sDescr);
	$arUpd = array(
	  'ID_Bin'	=> $idBin,
	  'WhenChanged'	=> 'NOW()');
	$this->Update($arUpd);
	$rcEv->Finish();
    }
    /*----
      ACTION: Logs movement of a single Stock Line directly to another Bin
      RETURNS: stock log event to be finished
      PROTECTED: Don't call this directly; call MoveToBin -- even if you're
	already updating the actual Bin data, because MoveToBin will make sure
	everything is logged properly.
    */
    protected function LogStart_MoveToBin($idBin,$sDescr=NULL) {
	$tLog = $this->StockLogTable();
	$id = $this->Value('ID');
	$rcEv = $tLog->StartEvent(
	  $this->Qty(),		// qty being moved
	  $this->BinID(),	// ID of local bin
	  clsStkLog::chTypeBin,	// type of other container (=bin)
	  $idBin,		// ID of other container (bin)
	  $id,			// stock line being moved
	  $id,			// ID of other line
	  $sDescr,		// description of what is happening,
	  NULL			// associated system event (not currently supported here)
	  );
	return $rcEv;
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
	$tLog = $this->StockLogTable();
	// log the attempt
	$idThis = $this->Value('ID');
	$idBin = $this->Value('ID_Bin');
	$rcEvent = $tLog->StartEvent(
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
	$rcEvent->Finish($arUpd);
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
	  $this->GetKeyValue(),		// stock line
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
    private $oForm;
    private function PageForm() {
	// create fields & controls

	if (empty($this->oForm)) {
	    $oForm = new fcForm_StockLine($this);

	      $oField = new fcFormField_Num($oForm,'ID_Item');

	      $oField = new fcFormField_Num($oForm,'ID_Bin');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->BinTable()->GetActive());
	    
	      $oField = new fcFormField_Num($oForm,'Qty');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>2));
	      
	      $oField = new fcFormField_Time($oForm,'WhenAdded');
	      
	      $oField = new fcFormField_Time($oForm,'WhenChanged');

	      $oField = new fcFormField_Time($oForm,'WhenCounted');
	      
	      $oField = new fcFormField_Time($oForm,'WhenRemoved');
	      
	      $oField = new fcFormField_Num($oForm,'Cost');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));

	      $oField = new fcFormField_Text($oForm,'CatNum');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
$sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Item</b>:</td><td>[[ID_Item]]</td></tr>
  <tr><td align=right><b>Bin</b>:</td><td>[[ID_Bin]]</td></tr>
  <tr><td align=right><b>Qty</b>:</td><td>[[Qty]]</td></tr>
  <tr><td align=right><b>Time stamps</b>:</td></tr>
  <tr><td align=center colspan=2>
    <table>
      <tr><td align=right><b>Added</b>:</td><td>[[WhenAdded]]</td></tr>
      <tr><td align=right><b>Changed</b>:</td><td>[[WhenChanged]]</td></tr>
      <tr><td align=right><b>Counted</b>:</td><td>[[WhenCounted]]</td></tr>
      <tr><td align=right><b>Removed</b>:</td><td>[[WhenRemoved]]</td></tr>
    </table>
  </td></tr>
  <tr><td align=right><b>Cost</b>:</td><td>[[Cost]]</td></tr>
  <tr><td align=right><b>Cat #</b>:</td><td>[[CatNum]]</td></tr>
  <tr><td colspan=2><b>Notes</b>:<br>[[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
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
	    //$sEvNotes = clsHTTP::Request()->GetText('EvNotes'); // TODO: implement this
	    $frm = $this->PageForm();
	    $frm->Save();		// save edit to existing package
	    $sMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsg);
	}
	if (clsHTTP::Request()->getBool('btnSplit')) {
	    $this->AdminSplit(clsHTTP::Request()->GetInt('qtyMove'));
	}
	$isNew = $this->IsNew();
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

	$id = $this->GetKeyValue();
	$strName = "Stock Line ID #$id";
	$oPage->TitleString($strName);
	
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this line-item'),
	  );
	if ($this->Qty_inStock() > 1) {
	    $arActs[] = new clsActionLink_option(array(),
	      'split',
	      'do',
	      NULL,
	      NULL,
	      'split some of this into another line-item'
	      );
	}
	$oPage->PageHeaderWidgets($arActs);
	
	$frm = $this->PageForm();
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	
	$out = NULL;
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	if (!$doEdit) {
	    $arCtrls['ID_Item'] = $this->LCItemRecord()->SelfLink_name();
	    $arCtrls['ID_Bin'] = $this->BinRecord()->SelfLink_name();
	}
	// render the form template
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {
	    $out .= <<<__END__
<input type=submit name="btnSave" value="Save">
</form>
__END__;
	}
	if ($doSplit) {
	    $nQty	= $this->Qty();
	    $nSplitMax = $nQty-1;
	    $nSplitDef = round($nQty/2);
	    $out .= <<<__END__
<form method=post>
  Quantity to move ($nSplitMax maximum):
  <input name=qtyMove value="$nSplitDef">
  <input name="btnSplit" value="Split" type=submit>
</form>
__END__;
	}

	$out .=
	  $this->AdminStockHistory()
	  .$this->EventListing();

	return $out;
    }
    /*----
      PURPOSE: display an admin listing of stock items in the current recordset
      HISTORY:
	2012-02-03 created for Title's display of stock
    */
    public function AdminList(array $iOpts) {
	$strNone = clsArray::Nz($iOpts,'none','No stock found.');

	$out = NULL;
	if ($this->HasRows()) {
	    $out .= <<<__END__
<table class=listing>
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
	    $isOdd = FALSE;
	    while ($this->NextRow()) {
		if ($this->IsRemoved()) {
		    $cssClass = 'void';
		} elseif (!$this->BinRecord()->IsEnabled()) {
		    $cssClass = 'inact';
		} else {
		    $isOdd = !$isOdd;
		    $cssClass = $isOdd?'odd':'even';
		}
		$htID = $this->SelfLink();
		$htItem = $this->LCItemRecord()->SelfLink_friendly();
		$htQty = $this->Value('Qty');
		$htBin = $this->BinRecord()->SelfLink_name();
		$htWhenAdd = $this->Value('WhenAdded');
		$htWhenChg = $this->Value('WhenChanged');
		$htWhenCnt = $this->Value('WhenCounted');
		$htWhenRmv = $this->Value('WhenRemoved');
		$htCost = $this->Value('Cost');

		$out .= <<<__END__

  <tr class=$cssClass>
    <td>$htID</td>
    <td>$htItem</td>
    <td align=right>$htQty</td>
    <td>$htBin</td>
    <td>$htWhenAdd</td>
    <td>$htWhenChg</td>
    <td>$htWhenCnt</td>
    <td>$htWhenRmv</td>
    <td>$htCost</td>
  </tr>
__END__;
		$sNotes = $this->Value('Notes');
		if (!is_null($sNotes)) {
		    $out .= "<tr class=$cssClass><td></td><td colspan=8>$sNotes</td></tr>";
		}
	    }
	    $out .= '</table>';
	} else {
	    $out = $strNone;
	}
	return $out;
    }
    public function AdminStockHistory() {
	$out = $this->PageObject()->SectionHeader('Stock History',NULL,'section-header-sub');
	$tbl = $this->StockLogTable();
	$out .= $tbl->Listing_forStockLine($this->GetKeyValue());
	return $out;
    }
}
