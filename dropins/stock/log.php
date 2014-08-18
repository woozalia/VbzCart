<?php
/*
  FILE: stock-log.php - stock movement logging
  HISTORY:
    2010-11-03 extracted classes from SpecialVbzAdmin.php:
      VbzStockPlace(s), VbzStockBin(s), VbzStockBinLog, VbzStockBinEvent, VbzAdminStkItem(s), clsStkLog, clsStockEvent
    2013-12-14 extracted from stock.php: clsStkLog, clsStockEvent, VbzStockBinLog, VbzStockBinEvent
  CONCEPTS:
    This deals primarily with two places where stock can be:
      STOCK refers to the actual stock tables.
      OTHER refers to other possible places where stock may be moving to or from:
	* a package
	* a restock
	* stock
*/

define('KS_TBL_STOCK_ITEM_HIST','stk_history');
define('KS_TBL_STOCK_BIN_HIST','stk_bin_history');

// STOCK ITEM HISTORY //

class clsStkLog extends clsTable {
    const chTypeBin = 'L';
    const chTypePkg = 'P';
    const chTypeRstk = 'R';
    const chTypeMult = 'M';

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(KS_TBL_STOCK_ITEM_HIST);
	  $this->KeyName('ID');
	  $this->ClassSng('clsStockEvent');
    }

    // -- SETUP -- //
    // ++ DATA TABLE ACCESS ++ //

    /*----
      PUBLIC so recordset can call it
    */
    public function StockLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINES,$id);
    }
    /*----
      PUBLIC so recordset can call it
    */
    public function StockBinTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_BINS,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Create an object for a new record, but don't write to database yet.
	This is necessary so we can leave some NOT NULL fields blank initially.
    */
    protected function BlankEvent($idBin,$chOtherType) {
	$rcNew = $this->SpawnItem();
	$rcNew->OtherTypeCH($chOtherType);
	$rcNew->StockBinID($idBin);

	return $rcNew;
    }
    /*----
      ACTION: Create an event record for *removing* an item from stock.
	We will know the stock location (bin), line item, and destination type,
	but not necessarily the destination (it might need to be created).
    */
    public function CreateRemoval(
      $nQty,
      $idBin,
      $chOtherType
      ) {
	$rcEvent = $this->BlankEvent($idBin,$chOtherType);
	$rcEvent->QtyAdded(-$nQty);
	return $rcEvent;
    }
    /*----
      ACTION: Create an event record for *addiing* an item to stock.
	We will know the stock location (bin), destination type,
	and destination location, but not necessarily the stock line.
    */
    public function CreateAddition(
      $nQty,
      $idBin,
      $chOtherType
      ) {
	$rcEvent = $this->BlankEvent($idBin,$chOtherType);
	$rcEvent->QtyAdded($nQty);
	return $rcEvent;
    }
    // 2014-05-26 This might not ever get used. Review later.
    // 2014-08-08 Used by StockLine.MoveToBin()
    public function StartEvent(
      $nQty,
      $idBin,
      $chOtherType,
      $idOtherCont,
      $idStkLine=NULL,
      $idOtherLine=NULL,
      $sDescr=NULL,
      $idEvent=NULL
      ) {
//	$this->OtherTypeCH($chOtherType);
//	$this->OtherContID($idOtherCont);
//	$idsOthCont = $this->OtherContIDS();
	if (is_null($idStkLine)) {
	    $idItem = NULL;
	    $qtyBef = NULL;
	} else {
	    $rcStkLine = $this->StockLineTable($idStkLine);
	    $idItem = $rcStkLine->ItemID();
	    $qtyBef = $rcStkLine->Qty();
	}
	$rcBin = $this->StockBinTable($idBin);
	$qtyInBin = $rcBin->Qty_forItem($idItem);
	$sUser = $this->Engine()->App()->User()->UserName();
	$sAddr = $_SERVER['REMOTE_ADDR'];

	$arData = array(
	  'ID_Event'	=> SQLValue($idEvent),
	  'ID_StkBin'	=> $idBin,
	  'ID_StkLine'	=> SQLValue($idStkLine),
	  'CH_OthType'	=> SQLValue($chOtherType),
	  'ID_OthCont'	=> $idOtherCont,
	  'ID_OthLine'	=> SQLValue($idOtherLine),
	  'IDS_OthCont'	=> 'NULL',
	  'ID_Item'	=> SQLValue($idItem),
	  'QtyAdded'	=> $nQty,
	  'QtyBefore'	=> SQLValue($qtyBef),	// might be NULL
	  'QtyBinBefore'=> $qtyInBin,
	  'WhenStarted'	=> 'NOW()',
	  'What'	=> SQLValue($sDescr),
	  'WhoAdmin'	=> SQLValue($sUser),
	  'WhoNetwork'	=> SQLValue($sAddr)
	  );
	$idEvent = $this->Insert($arData);
	if ($idEvent === FALSE) {
	    echo '<b>SQL</b>='.$this->sqlExec.'<br>';
	    throw new exception('Could not retrieve ID of new event.');
	}
	$rcEvent = $this->GetItem($idEvent);
	return $rcEvent;
    }
    public function LogEvent_Start($iStkLine,$iBin,$iOtherType,$iOtherCont,$iOtherLine,$iDescr=NULL,$iQty,$iEvent=NULL) {
	throw new exception('LogEvent_Start() is deprecated; use StartEvent().');

	$idsOthCont = $iOtherType.'.'.$iOtherCont;
	$objStkLine = $this->StockLineTable($iStkLine);
	$idItem = $objStkLine->Value('ID_Item');
	assert('!empty($idItem); /* iStkLine='.$iStkLine.' */');
	$objBin = $this->StockBinTable($iBin);
	$qtyInBin = $objBin->Qty_ofItem($idItem);

	$arData = array(
	  'ID_Event'	=> SQLValue($iEvent),
	  'ID_StkBin'	=> $iBin,
	  'ID_StkLine'	=> $iStkLine,
	  'CH_OthType'	=> SQLValue($iOtherType),
	  'ID_OthCont'	=> $iOtherCont,
	  'ID_OthLine'	=> $iOtherLine,
	  'IDS_OthCont'	=> SQLValue($idsOthCont),
	  'ID_Item'	=> $idItem,
	  'QtyAdded'	=> $iQty,
	  'QtyBefore'	=> SQLValue($objStkLine->Value('Qty')),	// might be NULL
	  'QtyBinBefore'=> $qtyInBin,
	  'WhenStarted'	=> 'NOW()',
	  'What'	=> SQLValue($iDescr),
	  'WhoAdmin'	=> SQLValue($this->Engine()->App()->User()->UserName()),
	  'WhoNetwork'	=> SQLValue($_SERVER['REMOTE_ADDR'])
	  );
	$this->Insert($arData);
	$idEvent = $this->Engine()->NewID();
	assert('!empty($idEvent);');
	return $idEvent;
    }
    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //

    public function Listing_forItem($idItem) {
	$rs = $this->GetData('ID_Item='.$idItem,NULL,'ID DESC');
	return $rs->AdminRows(array(
	  'ID'		=> 'ID',
	  'ID_Event'	=> 'SysEv',
	  'ID_StkBin'	=> 'Bin',
	  'ID_StkLine'	=> 'Line',
	  'CH_OthType'	=> 'oType',
	  'ID_OthCont'	=> 'oCont',
	  'ID_OthLine'	=> 'oLine',
	  'IDS_OthCont'	=> 'Other',
	  //'ID_Item'	=> 'Item',
	  'QtyBefore'	=> 'qLineBef',
	  'QtyBinBefore'=> 'qBinBef',
	  'QtyAfter'	=> 'qLineAft',
	  'QtyBinAfter'	=> 'qBinAft',
	  'WhenStarted'	=> 'start',
	  'WhenFinished'=> 'finish',
	  'What'	=> 'description',
	  ));
    }
    public function Listing_forStockLine($idLine) {
	$rs = $this->GetData('ID_StkLine='.$idLine,NULL,'ID DESC');
	return $rs->AdminRows(array(
	  'ID'		=> 'ID',
	  'ID_Event'	=> 'SysEv',
	  'ID_Item'	=> 'Item',
	  'ID_StkBin'	=> 'Bin',
	  'CH_OthType'	=> 'oType',
	  'ID_OthCont'	=> 'oCont',
	  'ID_OthLine'	=> 'oLine',
	  'IDS_OthCont'	=> 'Other',
	  'QtyBefore'	=> 'qLineBef',
	  'QtyBinBefore'=> 'qBinBef',
	  'QtyAfter'	=> 'qLineAft',
	  'QtyBinAfter'	=> 'qBinAft',
	  'WhenStarted'	=> 'start',
	  'WhenFinished'=> 'finish',
	  'What'	=> 'description',
	  ));
    }
    protected function AdminRows(array $arFields) {
	throw new exception('Who calls this? Use new functionality.');
	return "\n<table class=listing>".$this->AdminRows($arFields)."\n</table>";
    }

    // -- ADMIN WEB UI -- //
    // ++ DEPRECATED ++ //

    public function LogEvent_Finish_DEPRECATED($iEvent,array $iData=NULL) {
	$objEvent = $this->GetItem($iEvent);
	$idStkLine = $objEvent->Value('ID_StkLine');
	$objStkLine = $this->SItemTable($idStkLine);
	$idItem = $objStkLine->Value('ID_Item');
	assert('!empty($idItem); /* ID_StkLine='.$idStkLine.' */');
	$idBin = $objStkLine->Value('ID_Bin');
	$objBin = $this->BinTable($idBin);
	$qtyInBin = $objBin->Qty_ofItem($idItem);

	$arAdd = array(
	  'QtyAfter'		=> $objStkLine->Value('Qty'),
	  'QtyBinAfter'		=> $qtyInBin,
	  'WhenFinished'	=> 'NOW()'
	  );
	if (is_array($iData)) {
	    $arData = array_merge($iData,$arAdd);
	} else {
	    $arData = $arAdd;
	}
	$this->Update($arData,'ID='.$iEvent);
    }
}
class clsStockEvent extends clsDataRecord_Menu {

    // ++ SETUP ++ //

    private $idItem;
    private $qtyBinBefore;
    private $qtyLineBefore;
    private $sDescrBase;
    protected function InitVars() {
	$this->idItem = NULL;
	$this->qtyBinBefore = NULL;
	$this->qtyLineBefore = NULL;
	$this->sDescrBase = NULL;
    }

    // -- SETUP -- //
    // ++ DATA FIELD ACCESS ++ //

    // Many of these need to be PUBLIC so they can be Merge with the AdminRows() functionalityset from outside.

    protected function HasStockLine() {
	return !is_null($this->StockLineID());
    }
    protected function HasStockBin() {
	return !is_null($this->StockBinID());
    }
    protected function HasItem() {
	return !is_null($this->ItemID());
    }
    public function SetQtyLineBefore($nQty) {
	$this->qtyLineBefore = $nQty;
    }
    protected function GetQtyLineBefore($isBefore) {
	if (is_null($this->qtyLineBefore) && $isBefore) {
	    $this->qtyLineBefore = $this->QtyLineNow();
	}
	return $this->qtyLineBefore;
    }
    public function SetQtyBinBefore($nQty) {
	$this->qtyBinBefore = $nQty;
    }
    protected function GetQtyBinBefore($isBefore) {
	if (is_null($this->qtyBinBefore) && $isBefore) {
	    $this->qtyBinBefore = $this->QtyBinNow();
	}
	return $this->qtyBinBefore;
    }
    public function QtyAdded($nQty=NULL) {
	if (!is_null($nQty)) {
	    $this->Value('QtyAdded',$nQty);
	}
	return $this->Value('QtyAdded');
    }
    public function StockBinID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_Bin',$id);
	}
	return $this->Value('ID_Bin');
    }
    public function StockLineID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_StkLine',$id);
	}
	return $this->ValueNz('ID_StkLine');
    }
    public function ItemID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_Item',$id);
	}
	return $this->ValueNz('ID_Item');
    }
    public function OtherTypeCH($ch=NULL) {
	if (!is_null($ch)) {
	    $this->Value('CH_OthType',$ch);
	}
	return $this->Value('CH_OthType');
    }
    public function OtherContID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_OthCont',$id);
	}
	return $this->ValueNz('ID_OthCont');
    }
    public function OtherLineID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_OthLine',$id);
	}
	return $this->ValueNz('ID_OthLine');
    }
    public function EventID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_Event',$id);
	}
	return $this->Value('ID_Event');
    }
    public function DescrBase($sDescr=NULL) {
	if (!is_null($sDescr)) {
	    $this->sDescrBase = $sDescr;
	}
	return $this->sDescrBase;
    }
    protected function OtherContIDS() {
	return $this->OtherTypeCH().'.'.$this->OtherContID();
    }
    protected function OtherShort() {
	return $this->Value('CH_OthType').'.'
	  .$this->Value('ID_OthCont').'.'
	  .$this->Value('ID_OthLine');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function StockBinTable($id=NULL) {
	return $this->Table()->StockBinTable($id);
    }
    protected function StockLineTable($id=NULL) {
	return $this->Table()->StockLineTable($id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    protected function StockBinRecord() {
	return $this->StockBinTable($this->StockBinID());
    }
    protected function StockLineRecord() {
	return $this->StockLineTable($this->StockLineID());
    }

    // -- DATA RECORD ACCESS -- //
    // ++ CALCULATIONS ++ //

    protected function QtyLineNow() {
	// if we know the stock line...
	if ($this->HasStockLine()) {
	    // ...then we can calculate this
	    $rcStkLine = $this->StockLineRecord();
	    // might as well make sure we have the Item ID
	    $this->ItemID($rcStkLine->ItemID());
	    return $rcStkLine->Qty();
	} else {
	    // otherwise, no idea
	    return NULL;
	}
    }
    protected function QtyBinNow() {
	// if we know the Stock Bin and Item ID...
	if ($this->HasStockBin() && $this->HasItem()) {
	    // ...then we can calculate this
	    $rcStkBin = $this->StockBinRecord();
	    $idItem = $this->ItemID();
	    return $rcStkBin->Qty_forItem($idItem);
	} else {
	    // otherwise, no idea
	    return NULL;
	}
    }
    /*----
      ACTION: Retrieve any of the following information that is available, and store locally:
	* quantity of the given item in the current stock bin (need: bin ID, item ID)
	* quantity in the current stock line (need: line ID)
    */
/*
    protected function FetchTotals() {
	if (is_null($this->ItemID()) || is_null($this->QtyLineBefore())) {
	    if (!is_null($this->StockLineID())) {
		$rc = $this->StockLineRecord();
		$idItem = $rc->ItemID();
		$this->ItemID($idItem);
		$this->QtyLineBefore($rc->Qty());
	    }
	}
	if (is_null($this->QtyBinBefore())) {
	    if (!is_null($this->StockBinID()) && !is_null($this->StockLineID())) {
		$rc = $this->StockBinRecord();
		$this->QtyBinBefore($rc->Qty_forItem($this->ItemID()));
	    }
	}
    }
*/
    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      PUBLIC so that table class can call it
      USAGE: Call after creating blank event but before stock is actually moved.
	The invoked object can be thought of as an event template, because multiple sub-events
	  can be created from it with minimal modification.
	The ID returned by this function is for the created event record.
    */
    public function Write($sDescr) {
	//$this->FetchTotals();	// look up all available data & do any doable calculations

	$sUser = $this->Engine()->App()->User()->UserName();
	$sAddr = $_SERVER['REMOTE_ADDR'];
	$sDescrFull = $this->DescrBase().$sDescr;
	$arData = array(
	  'ID_Event'	=> SQLValue((int)$this->EventID()),
	  'ID_StkBin'	=> $this->StockBinID(),
	  'ID_StkLine'	=> SQLValue($this->StockLineID()),	// we might not know which line yet
	  'CH_OthType'	=> SQLValue($this->OtherTypeCH()),	// will be a string
	  'ID_OthCont'	=> SQLValue($this->OtherContID()),	// we might not know this yet
	  'ID_OthLine'	=> SQLValue($this->OtherLineID()),	// we might not know this yet
	  'IDS_OthCont'	=> SQLValue($this->OtherContIDS()),
	  'ID_Item'	=> $this->ItemID(),
	  'QtyBefore'	=> SQLValue($this->GetQtyLineBefore(TRUE)),
	  'QtyBinBefore'=> SQLValue($this->GetQtyBinBefore(TRUE)),
	  'QtyAdded'	=> SQLValue($this->QtyAdded()),	// might not be known yet
	  'WhenStarted'	=> 'NOW()',
	  'What'	=> SQLValue($sDescrFull),
	  'WhoAdmin'	=> SQLValue($sUser),
	  'WhoNetwork'	=> SQLValue($sAddr)
	  );
	$idEvent = $this->Table()->Insert($arData);
	if (is_null($idEvent) || ($idEvent == FALSE)) {
	    echo '<b>INSERT</b> SQL: '.$this->Table()->sqlExec.'<br>';
	    $sError = $this->Engine()->GetError();
	    if (is_null($sError)) {
		echo 'No error message.<br>';
	    } else {
		echo "<b>ERROR message</b>: $sError<br>";
	    }
	    throw new exception('Could not retrieve ID of new stock event. Event may not have been created.');
	}
	return $idEvent;
    }
    public function Finish(array $arData=NULL) {
	$idStkLine = $this->Value('ID_StkLine');
	$rcStkLine = $this->StockLineTable($idStkLine);
	$idItem = $rcStkLine->ItemID();
	$idBin = $rcStkLine->BinID();
	$rcBin = $this->StockBinTable($idBin);
	$qtyInBin = $rcBin->Qty_forItem($idItem);

	$arAdd = array(
	  'QtyAfter'		=> $rcStkLine->Qty(),
	  'QtyBinAfter'		=> $qtyInBin,
	  'WhenFinished'	=> 'NOW()',
	  'ID_StkLine'		=> $this->StockLineID(),	// might not have been known earlier
	  'CH_OthType'		=> SQLValue($this->OtherTypeCH()),	// will be a string
	  'ID_OthCont'		=> $this->OtherContID(),	// might not have been known earlier
	  'ID_OthLine'		=> $this->OtherLineID(),	// might not have been known earlier
	  'IDS_OthCont'		=> SQLValue($this->OtherContIDS())	// will be a string
	  );
	if (is_array($arData)) {
	    $arData = array_merge($arData,$arAdd);
	} else {
	    $arData = $arAdd;
	}
	if (!is_null($this->ValueNz('ID_StkLine'))) {
	    $arAdd['ID_StkLine'] = $this->StockLineID();
	}
	if (!is_null($this->ValueNz('ID_OthLine'))) {
	    $arAdd['ID_OthLine'] = $this->OtherLineID();
	}
	$this->Update($arData);
    }

    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //

    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    public function AdminList() {
	throw new exception('Who calls this? Merge with the AdminRows() functionality.');
	if ($this->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Stk ID</th>
    <th>Item</th>
    <th>Home Bin</th>
    <th>Other</th>
    <th>pre-qty</th>
    <th>delta</th>
    <th>post-qty</th>
    <th>started</th>
    <th>finished</th>
    <th>description</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		$idStk = $this->Value('ID_StkLine');
		$objStk = $this->StockLineTable($idStk);
		$objBin = $objStk->BinRecord();
		$objItem = $objStk->LCItemRecord();

		//$wtBin = $objBin->SelfLink($objBin->Value('Code'));
		$wtBin = $objBin->AdminLink_name();
		$wtItem = $objItem->Value('CatNum');

		$wtQtyPre = $this->Value('QtyBefore');
		if ($this->HasValue('QtyBinBefore')) {
		    $wtQtyPre .= '(b:'.$this->Value('QtyBinBefore').')';
		}
		$wtQtyPost = $this->Value('QtyAfter');
		if ($this->HasValue('QtyBinAfter')) {
		    $wtQtyPost .= '(b:'.$this->Value('QtyBinAfter').')';
		}
		$qtyAdd = $this->Value('QtyAdded');
		if ($qtyAdd > 0) {
		    $wtQtyAdded = '+'.$qtyAdd;
		} elseif ($qtyAdd < 0) {
		    $wtQtyAdded = $qtyAdd;
		} else {
		    $wtQtyAdded = '-';
		}

		$ftWhenStart = $this->Value('WhenStarted');
		$ftWhenFinish = $this->Value('WhenFinished');
		$ftWhat = $this->Value('What');
		$ftOther = $this->OtherShort();
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$id</td>
    <td>$idStk</td>
    <td>$wtItem</td>
    <td>$wtBin</td>
    <td>$ftOther</td>
    <td>$wtQtyPre</td>
    <td>$wtQtyAdded</td>
    <td>$wtQtyPost</td>
    <td>$ftWhenStart</td>
    <td>$ftWhenFinish</td>
    <td>$ftWhat</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'none';
	}
	return $out;
    }

    // -- ADMIN WEB UI -- //
}

// STOCK BIN HISTORY //

class VbzStockBinLog extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(KS_TBL_STOCK_BIN_HIST);
	  $this->KeyName('ID');
	  $this->ClassSng('VbzStockBinEvent');
    }
    public function LogEvent($iBin,$iSrce,$iDest,$iDescr=NULL) {
	$arData = array(
	    'ID_Bin'	=> $iBin,
	    'WhenDone'	=> 'NOW()',
	    'WhoAdmin'	=> SQLValue($this->Engine()->App()->User()->UserName()),
	    'WhoNetwork'=> SQLValue($_SERVER['REMOTE_ADDR']),
	    'ID_Srce'	=> $iSrce,
	    'ID_Dest'	=> $iDest,
	    'Descr'	=> SQLValue($iDescr)
	    );
	return $this->Insert($arData);
    }
}

class VbzStockBinEvent extends clsDataSet {

    // ++ DATA TABLE ACCESS ++ //

    protected function PlaceTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_PLACES,$id);
    }

    // -- DATA TABLE ACCESS -- //

    /*----
      TODO: Hard-coded HTML markup should be replaced by CSS classes or Skin calls
    */
    public function Listing() {
	throw new exception('Listing() is deprecated; call RenderRows() and output the returned results.');
    }
    public function RenderRows() {
	if ($this->hasRows()) {
	    $tPlaces = $this->PlaceTable();

	    $out = <<<__END__
<table>
  <tr>
    <th>ID</th><th>When</th><th>From</th><th>To</th><th>What</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Values();
		$id = $row['ID'];
		$ftWhen = $row['WhenDone'];
		$idSrce = $row['ID_Srce'];
		$idDest = $row['ID_Dest'];
		$rcSrce = $tPlaces->GetItem($idSrce);
		if ($rcSrce->RowCount() == 0) {	// happens if idSrce is 0
		    $strSrce = "<i>??</i>$idSrce";	// TODO: use CSS class
		} else {
		    $strSrce = $rcSrce->AdminLink_name();
		}
		if ($idSrce == $idDest) {
		    $ftToFrom = "<td colspan=2 align=center>$strSrce</td>";
		} else {
		    $objDest = $tPlaces->GetItem($idDest);
		    if (is_null($objDest)) {
			$strDest = "<i>??</i>$idDest";
		    } else {
			$strDest = $objDest->AdminLink_name();
		    }
		    $ftToFrom = "<td>$strSrce</td><td>$strDest</td>";
		}

		$ftWhat = $row['Descr'];
		if (isset($row['Notes'])) {
		    $ftWhat .= ' <i>'.$row['Notes'].'</i>';	// TODO: use CSS
		}

		$out .= <<<__END__
  <tr style="$ftStyle">
    <td>$id</td>
    <td>$ftWhen</td>
    <td>$ftToFrom</td>
    <td>$ftWhat</td>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    //$out = 'No events found';
	    $out = NULL;
	}
	return $out;
    }
}
