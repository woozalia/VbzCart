<?php
/*
  PURPOSE: classes for displaying Order data for a LCItem
  HISTORY:
    2016-01-09 started - existing code gives some incorrect numbers, and seems unnecessarily complicated
    2017-01-05 I'm now trying to do a prelim reconstruction og how this was actually supposed to work.
      Evidence would indicate some kind of JOIN query, but what happened to the SQL? Maybe that needs
      to be written. Or maybe that's not the way to do it. For now, leaving TableName() blank. Hopefully
      clues will emerge. (Also, what happened to the original code? This file doesn't currently exist in GitHub.)
*/

class vctaLCItemOrders extends vcAdminTable {
    use ftLinkableTable;
    
    // ++ SETUP ++ //
    
    protected function TableName() {
	throw new exception('2017-01-05 Is this being called?');
    }
    protected function SingularName() {
	return 'vcraLCItemOrder';
    }
    public function GetActionKey() {
	return KS_ACTION_PKG_LINE;
    }

    // -- SETUP -- //
    // ++ TABLES ++ //
    
    protected function OrderLineTable() {
	return $this->Engine()->Make(KS_CLASS_ORDER_LINES);
    }
    protected function PackageLineTable() {
	return $this->Engine()->Make(KS_CLASS_PACKAGE_LINES);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function OrderLineRecords($sqlFilt) {
	return $this->OrderLineTable()->SelectRecords($sqlFilt);
    }
    protected function PackageLineRecords($sqlFilt) {
	return $this->PackageLineTable()->SelectRecords($sqlFilt);
    }

    // -- RECORDS -- //
    // ++ ARRAYS ++ //
    
    /*----
      RETURNS: array of all instances of a given LCItem in Order Lines and Package Lines
      PUBLIC so LCItem objects can use it
      NOTES:
	There doesn't seem to be any reasonable way to do this in pure SQL.
      RULES:
	(OL = Order Line, PL = Package Line)
	In clean data, every PL has an OL, but any given OL might not have a PL.
	In actual (legacy) data, some PLs don't have OLs, or point to
	  OLs that don't exist.
	So we first each OL to an array entry. Then we look at PLs and try to
	  connect each one with an OL entry. Each orphaned (left-over) PL then
	  gets its own entry in the array.
    */
    protected function Array_forLCItem($idItem) {
	$ar = NULL;

	// get matching Order Items
	$rs = $this->OrderLineRecords('ID_Item='.$idItem);
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$id = $rs->GetKeyValue();
		$sKey = 'o'.$id;
		$ar[$sKey]['ol'] = $rs->Values();
	    }
	}

	// get matching Package Lines
	$rs = $this->PackageLineRecords('ID_Item='.$idItem);
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$id = $rs->GetKeyValue();
		if ($rs->HasOrderLine()) {
		    $sKey = 'o'.$rs->OrderLineID();
		} else {
		    $sKey = 'p'.$id;
		}
		$ar[$sKey]['pl'] = $rs->Values();
	    }
	}
	
	return $ar;
    }
    
    // -- ARRAYS -- //
    // ++ WEB UI ++ //
    
    public function AdminRows_forLCItem($idItem) {
	$ar = $this->Array_forLCItem($idItem);
	if (is_null($ar)) {
	    $out = '<i>no orders for this item</i>';
	} else {
	    $rs = $this->SpawnItem();
	    $out = $rs->AdminArray_forLCItem($ar);
	}
	return $out;
    }
    
    // -- WEB UI -- //

}
class vcraLCItemOrder extends vcAdminRecordset {

    // ++ FIELD VALUES ++ //
    
    protected function GetOrderID() {
	return $this->Value('ID_Order');
    }
    protected function OrderLineID() {
	return $this->Value('ID_OrdLine');
    }
    protected function PackageLineID() {
	return $this->Value('ID_PkgLine');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function NotesText() {
	$out = NULL;
    
	$sPkgNotes = $this->Value('PkgNotes');
	if (!is_null($sPkgNotes)) {
	    $out .= '<b>Pkg</b>: '.$sPkgNotes;
	}
	
	$sOrdNotes = $this->Value('OrdNotes');
	if (!is_null($sOrdNotes)) {
	    $out .= '<b>Ord</b>: '.$sOrdNotes;
	}
	
	return $out;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //
    
    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDERS,$id);
    }
    protected function OrderLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDER_LINES,$id);
    }
    protected function PackageLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PACKAGE_LINES,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function OrderRecord() {
	return $this->OrderTable($this->GetOrderID());
    }
    protected function OrderLineRecord() {
	return $this->OrderLineTable($this->OrderLineID());
    }
    protected function PackageLineRecord() {
	return $this->PackageLineTable($this->PackageLineID());
    }
    
    // -- RECORDS -- //
    // ++ ADMIN UI ++ //

    public function AdminRows_forLCItem() {
	throw new exception('2016-03-06 Does anyone call this? Where does the data come from?');
	if ($this->HasRows()) {
	    $out = $this->AdminRows(
	    );
	} else {
	    $out = 'No order data found.';
	}
	return $out;
    }
    // INPUT: output from Table.Array_forLCItem()
    public function AdminArray_forLCItem(array $ar) {
	if (count($ar) > 0) {
	    $rcOrdLine = $this->OrderLineTable()->SpawnItem();
	    $rcPkgLine = $this->PackageLineTable()->SpawnItem();
	    $out = <<<__END__

<table class=listing>
  <tr>
    <th colspan=3 rowspan=2>-- Order --</th>
    <th colspan=6>-- Package --</th>
  </tr>
  <tr>
    <th colspan=2 align=right>Shipment &rarr;</th>
    <th colspan=4>Quantities</th>
  <tr>
    <th>Line</th>
    <th>When Placed</th>
    <th>Qty Ord</th>
    
    <th>Line</th>
    <th title="when package was created">When</th>
    <th><abbr title="quantity shipped">shp</abbr></th>
    <th><abbr title="quantity not available">n/a</abbr></th>
    <th><abbr title="quantity cancelled">kld</abbr></th>
    <th><abbr title="quantity returned">rtn</abbr></th>
    
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = FALSE;
	    foreach ($ar as $sKey => $arPair) {
		$isOdd = !$isOdd;
		$cssClass = $isOdd?'odd':'even';
		$out .= "\n  <tr class=$cssClass>";
		
		$hasOL = array_key_exists('ol',$arPair);
		$hasPL = array_key_exists('pl',$arPair);
		
		$sNotesOL = NULL;
		$sNotesPL = NULL;
		
		if ($hasOL) {
		    $rcOrdLine->Values($arPair['ol']);
		    $htIDOL = $rcOrdLine->SelfLink_name();
		    
		    $rcOrd = $rcOrdLine->OrderRecord();
		    $htWhenPlaced = $rcOrd->WhenStarted();
		    $qtyOrd = $rcOrdLine->QtyOrdered();
		    $sNotesOL = $rcOrdLine->NotesText();
		    if (!is_null($sNotesOL)) {
			$sNotesOL = '<b>ord</b>: '.$sNotesOL;
		    }
		    
		    $out .= <<<__END__
    <td>$htIDOL</td>
    <td>$htWhenPlaced</td>
    <td align=center>$qtyOrd</td>
__END__;
		} else {
		    // indicate status of PL's OL pointer
		    if ($hasPL) {
			$idOL = $arPair['pl']['ID_OrdLine'];
			if (is_null($idOL)) {
			    $sOL = "Order Line NULL";
			} else {
			    $sOL = "Order Line ID $idOL N/A";
			}
		    } else {
			$sOL = NULL;
		    }
		    $out .= "\n    <td colspan=3 class=inact>$sOL</td>";
		}
		    
		if ($hasPL) {
		    $rcPkgLine->Values($arPair['pl']);
		    $htIDPL = $rcPkgLine->SelfLink_name();
		
		    $rcPkg = $rcPkgLine->PackageRecord();
		    $htWhenPacked = $rcPkg->WhenStarted();
		    $qtyShp = $rcPkgLine->QtyShipped();
		    $qtyNA = $rcPkgLine->QtyNotAvail();
		    $qtyKld = $rcPkgLine->QtyKilled();
		    $qtyRtn = $rcPkgLine->QtyReturned();
		    $sNotesPL = $rcPkgLine->NotesText();
		    if (!is_null($sNotesPL)) {
			$sNotesPL = '<b>pkg</b>: '.$sNotesPL;
		    }
		
		    $out .= <<<__END__
    <td>$htIDPL</td>
    <td>$htWhenPacked</td>
    <td align=center>$qtyShp</td>
    <td align=center>$qtyNA</td>
    <td align=center>$qtyKld</td>
    <td align=center>$qtyRtn</td>
__END__;
		}
		$sNotes = fcString::ConcatArray(' / ',array($sNotesOL,$sNotesPL));
		$out .= 
		  "\n    <td>$sNotes</td>"
		  ."\n  </tr>"
		  ;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No order or package lines found.';
	}
	return $out;
    }
    // this might not actually be used
    protected function AdminColumns_forLCItem() {
	return array(
	  'ID_OrdLine'	=> '<abbr title="order line">OrL</abbr>',	// from OrderLine
	  'ID_PkgLine'	=> '<abbr title="package line">PkL</abbr>',	// from PackageLine
	  '!Number'	=> 'Order #',	// from Order
	  '!WhenPlaced'	=> 'Ordered',	// from Order
	  'WhenFinished'	=> 'Shipped',	// from Package
	  'QtyOrd'	=> 'ord',	// from OrderLine
	  'QtyShipped'	=> 'shp',	// from PackageLine
	  'QtyNotAvail'	=> 'n/a',	// from PackageLine
	  'QtyKilled'	=> '<abbr title="quantity killed">kld</abbr>',	// from PackageLine
	  'QtyReturned'	=> '<abbr title="quantity returned">rtn</abbr>',	// from PackageLine
	  '!Notes'	=> 'Notes'	// from OrderLine + PackageLine
	);
    }
    protected function AdminRows_start(array $arOptions=NULL) {
	return "\n<table class=listing>";
    }
    protected function AdminField($sField,array $arOptions=NULL) {
	switch ($sField) {
	  case '!Number':
	    $val = $this->OrderRecord()->SelfLink_name();
	    break;
	  case '!WhenPlaced':
	    $val = $this->OrderRecord()->WhenPlaced();
	    break;
	  case '!Notes':
	    $val = $this->NotesText();
	    break;
	  case 'ID_OrdLine':
	    $val = $this->OrderLineRecord()->SelfLink();
	    break;
	  case 'ID_PkgLine':
	    $val = $this->PackageLineRecord()->SelfLink();
	    break;
	  default:
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    
    // -- ADMIN UI -- //

}