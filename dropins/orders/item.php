<?php
/*
  FILE: dropins/orders/order.php -- customer order administration dropin for VbzCart
  HISTORY:
    2014-02-22 split off OrderItem classes from order.php
*/
class VCA_OrderItems extends clsOrderLines {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCA_OrderItem');	// override parent
	  $this->ActionKey('order-item');
    }

    // -- SETUP -- //
    // ++ ADMIN WEB UI ++ //

    /*-----
      ARGS:
	$iFull: show all editable fields? (FALSE = only show fields which can't be looked up)
      TO DO: In case this is ever used more generally, probably *all* fields
	should be protected by htmlspecialchars()
      HISTORY:
	2011-03-23 fixed the order of the last 4 fields -- qty goes *after* prices
    */
    static public function RenderEdit_inline($iFull,array $iarArgs=NULL) {
	if ($iFull) {
	    // show the specs for final approval
	    $ftPart1 =
		'<td><input name="Descr"	size=30	value="'.htmlspecialchars($iarArgs['Descr']).'"></td>';
	    $ftPart2 = '<input type=hidden name="ID_Item" value="'.$iarArgs['ID_Item'].'">'
	      .'<td><input name="Price"		size=5	value="'.$iarArgs['Price'].'"></td>'
	      .'<td><input name="PerItem"	size=5	value="'.$iarArgs['PerItem'].'"></td>'
	      .'<td><input name="PerPkg"	size=5	value="'.$iarArgs['PerPkg'].'"></td>';
	    $btnSave = '<td><input type=submit name="btnSaveItem" value="Save"></td>';
	} else {
	    $ftPart1 = '<td><input type=submit name="btnLookup" value="Check..."></td>';
	    $ftPart2 = '<td colspan=3 align=center><i>to be looked up</i></td>';
	    $btnSave = '';
	}
	$out = "\n<tr>"
	  .'<td align=center>new</td>'
	  .'<td><input name="CatNum"	size=10 value="'.htmlspecialchars($iarArgs['CatNum']).'"></td>'
	  .$ftPart1
	  .$ftPart2
	  .'<td><input name="Qty"	size=2 value="'.htmlspecialchars($iarArgs['Qty']).'"></td>'
	  .$btnSave
	  .'</tr>';
	return $out;
    }
    static public function CaptureEdit() {
	global $wgRequest;
	// always capture these fields
	$arOut['ID_Item'] = $wgRequest->GetIntOrNull('ID_Item');
	$arOut['CatNum'] = $wgRequest->GetText('CatNum');
	$arOut['Qty'] = $wgRequest->GetIntOrNull('Qty');
	$arOut['Descr'] = $wgRequest->GetText('Descr');
	$arOut['Price'] = $wgRequest->GetText('Price');
	$arOut['PerItem'] = $wgRequest->GetText('PerItem');
	$arOut['PerPkg'] = $wgRequest->GetText('PerPkg');
	return $arOut;
    }
    static public function SaveEdit(clsOrder $iOrder, array $iarFields, clsOrderLine $iLine=NULL) {
	$isOld = isset($iarFields['ID']);
	if ($isOld) {
	    $intSeq = (int)$iarFields['Seq'];
	} else {
	    $intSeq = $iOrder->NextSeq();
	}
	$strCatNum = $iarFields['CatNum'];
	$intQty = (int)$iarFields['Qty'];
	$arUpd = array(
	  'ID_Order'	=> $iOrder->ID,
	  'Seq'		=> $intSeq,
	  'ID_Item'	=> (int)$iarFields['ID_Item'],
	  'CatNum'	=> SQLValue($iarFields['CatNum']),
	  'Descr'	=> SQLValue($iarFields['Descr']),
	  'QtyOrd'	=> $intQty,
	  'Price'	=> SQLValue($iarFields['Price']),
	  'ShipPkg'	=> SQLValue($iarFields['PerPkg']),
	  'ShipItm'	=> SQLValue($iarFields['PerItem']),
	  'WhenAdded'	=> 'NOW()',
	  );
	if ($isOld) {
	    // LOG THE ATTEMPT
	    $arEv = array(
	      'code'	=> 'UDI',
	      'descr'	=> 'updating cat #'.$strCatNum.' qty '.$intQty,
	      'where'	=> __METHOD__
	      );
	    $iOrder->StartEvent($arEv);
	    // UPDATE THE RECORD
	    if (is_object($iLine)) {
		$objLine = $iLine;
	    } else {
		$idLine = $iarFields['ID'];
		$objLine = $this->GetItem($idLine);
	    }
	    $objLine->Update($arUpd);
	} else {
	    // LOG THE ATTEMPT
	    $arEv = array(
	      'code'	=> 'ADI',
	      'descr'	=> 'adding cat #'.$strCatNum.' qty '.$intQty,
	      'where'	=> __METHOD__
	      );
	    $iOrder->StartEvent($arEv);
	    // CREATE THE RECORD
	    $iOrder->LineTable()->Insert($arUpd);
	}
	// LOG COMPLETION
	$iOrder->FinishEvent();
    }

    // -- ADMIN WEB UI -- //

}
class VCA_OrderItem extends clsOrderLine {
    private $doNewEntry;

    // ++ INITIALIZATION ++ //

    private $rcOrd;
    protected function InitVars() {
	$this->rcOrd = NULL;
	$this->doNewItem = FALSE;
	parent::InitVars();
    }

    // -- INITIALIZATION -- //
    // ++ BOILERPLATE ++ //
/*
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
*/
    // -- BOILERPLATE -- //
    // ++ OPTIONS ++ //

    public function Want_ShowNewEntry($bShow=NULL) {
	if (!is_null($bShow)) {
	    $this->doNewEntry = $bShow;
	}
	return $this->doNewEntry;
    }

    // -- OPTIONS -- //
    // ++ FIELD ACCESS ++ //


    protected function OrderID() {
	return $this->Value('ID_Order');
    }
    protected function ItemID() {
	return $this->Value('ID_Item');
    }
    /*----
      PUBLIC because Package needs it in order to look up item specs for orders being packaged
    */
    public function Price() {
	if ($this->IsNew()) {
	    throw new exception('Attempted to access field from nonexistent order item.');
	}
	return $this->Value('Price');
    }
    public function ShipPerItem() {
	return $this->Value('ShipItm');
    }
    public function ShipPerPackage() {
	return $this->Value('ShipPkg');
    }

    // -- FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function OrdersClass() {
	return KS_CLASS_ORDERS;
    }
    protected function ItemsClass() {
	if (clsDropInManager::IsReady('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItems';
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make($this->OrdersClass(),$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
    */
    public function OrderObj() {
	throw new exception('OrderObj() is deprecated; call OrderRecord().');
    }
    public function OrderRecord() {
	$doLoad = TRUE;
	$id = $this->OrderID();
	if (!is_null($this->rcOrd)) {
	    if ($this->rcOrd->KeyValue() == $id) {
		$doLoad = FALSE;
	    } else {
		throw new exception('This should never happen, should it?');
	    }
	}
	if ($doLoad) {
	    $this->rcOrd = $this->OrderTable($id);
	}
	return $this->rcOrd;
    }
    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
    */
    protected $objItem;
    public function ItemObj() {
	$doLoad = TRUE;
	$id = $this->Value('ID_Item');
	if (isset($this->objItem)) {
	    if ($this->objItem->KeyValue() == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objItem = $this->Engine()->Items($id);
	}
	return $this->objItem;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ ADMIN INTERFACE ++ //

    /*----
      HISTORY:
	2011-03-23 adapted from VbzAdminItem to VbzAdminOrderItem
    */
    private function EditForm() {
	if (is_null($this->frmEdit)) {
	    $frm = new clsForm_recs($this);

	    $frm->AddField(new clsField('Seq'),	new clsCtrlHTML(array('size'=>3)));
	    $frm->AddField(new clsField('ID_Item'),	new clsCtrlHTML(array('size'=>8)));
	    $frm->AddField(new clsField('CatNum'),	new clsCtrlHTML());
	    $frm->AddField(new clsFieldNum('Descr'),	new clsCtrlHTML(array('size'=>40)));
	    $frm->AddField(new clsField('QtyOrd'),	new clsCtrlHTML(array('size'=>3)));
	    $frm->AddField(new clsFieldNum('Price'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsFieldNum('ShipPkg'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsFieldNum('ShipItm'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsField('Notes'),	new clsCtrlHTML(array('size'=>40)));

	    $this->frmEdit = $frm;
	}
	return $this->frmEdit;
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
	2011-03-23 copied from clsAdminTopic to VbzAdminOrderItem
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    public function AdminPage() {
	$out = NULL;
	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	$doSave = $clsHTTP::Request()->GetBool('btnSave');

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objOrd = $this->OrderObj();

	$strTitle = 'Order '.$objOrd->AdminName().' item #'.$this->Value('Seq');

	$objPage = new clsWikiFormatter($vgPage);
/*
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();
*/
    	$objSection = new clsWikiSection_std_page($objPage,$strTitle,3);
	//$objSection->PageKeys(array('page','id'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'edit'));
	//  $objLink->Popup('receipt for order #'.$strNum);
	$out = $objSection->Render();

	$wgOut->AddHTML($out); $out = '';
	$vgOut->AddText($ftSaveStatus);

	$ftID = $this->AdminLink();
	$ftOrd = $objOrd->AdminLink_name();
	$ftWho = $this->Value('VbzUser');
	$ftWhere = $this->Value('Machine');
	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ftSeq	= $objForm->Render('Seq');
	    $ftItem	= $objForm->Render('ID_Item');
	    $ftCatNum	= $objForm->Render('CatNum');
	    $ftDescr	= $objForm->Render('Descr');
	    $ftQty	= $objForm->Render('QtyOrd');
	    $ftPrice	= $objForm->Render('Price');
	    $ftShPkg	= $objForm->Render('ShipPkg');
	    $ftShItm	= $objForm->Render('ShipItm');
	    $ftNotes	= $objForm->Render('Notes');
	} else {
	    $objItem = $this->ItemObj();

	    $ftSeq	= $this->Value('Seq');
	    $ftItem	= $objItem->AdminLink_friendly();
	    $ftCatNum	= $this->Value('CatNum');
	    $ftDescr	= $this->Value('Descr');
	    $ftQty	= $this->Value('QtyOrd');
	    $ftPrice	= $this->Value('Price');
	    $ftShPkg	= $this->Value('ShipPkg');
	    $ftShItm	= $this->Value('ShipItm');
	    $ftNotes	= $this->Value('Notes');
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>";
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>$ftOrd</td></tr>";
	$out .= "\n<tr><td align=right><b>Seq</b>:</td><td>$ftSeq</td></tr>";
	$out .= "\n<tr><td align=right><b>Item</b>:</td><td>$ftItem</td></tr>";
	$out .= "\n<tr><td align=right><b>Cat #</b>:</td><td>$ftCatNum</td></tr>";
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>$ftDescr</td></tr>";
	$out .= "\n<tr><td align=right><b>Qty ordered</b>:</td><td>$ftQty</td></tr>";
	$out .= "\n<tr><td align=right><b>Price</b>:</td><td>$ $ftPrice</td></tr>";
	$out .= "\n<tr><td align=right><b>s/h per pkg</b>:</td><td>$ $ftShPkg</td></tr>";
	$out .= "\n<tr><td align=right><b>s/h per item</b>:</td><td>$ $ftShItm</td></tr>";
	$out .= "\n<tr><td align=right><b>Notes</b>:</td><td>$ftNotes</td></tr>";
	$out .= "\n<tr><td align=right><b>Admin</b>:</td><td>$ftWho</td></tr>";
	$out .= "\n<tr><td align=right><b>Where</b>:</td><td>$ftWhere</td></tr>";
	$out .= "\n</table>";

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	// events
	$objSection = new clsWikiSection($objPage,'Events',NULL,3);
	$out .= $objSection->Generate();
	$out .= $this->EventListing();

	$wgOut->addHTML($out);	$out = '';
    }
    /*-----
      INPUT:
	$iArgs needs to be documented
    */
    public function AdminTable_forOrder() {
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

	$doNew = $this->Want_ShowNewEntry();	// displaying extra stuff for adding new line
	$nRows = $this->RowCount();
	$doRows = $nRows > 0;

	if ($doRows) {
	    $this->StartRows();		// rewind to before first row
	    // no, I don't know why it doesn't remember the last row loaded
	    $this->NextRow();		// load first row to get order ID
	    $arPkgSums = $this->OrderRecord()->PackageSums();
	    $hasPkgs = is_array($arPkgSums);
	} else {
	    $hasPkgs = FALSE;
	}

	$doLookup = $oPage->ReqArgBool('btnLookup');
	$doSave =  $oPage->ReqArgBool('btnSaveItem');
	$doCapture = $doLookup || $doSave;	// is there form data to capture?
	if ($doRows || $doNew) {
	    if ($hasPkgs) {
		$htQtyHdr = '<th colspan=6>Quantities</th>';
	    } else {
		$htQtyHdr = '<th>qty</th>';
	    }
	    $out = <<<__END__
<table class=listing>
  <tr><td colspan=6></td>$htQtyHdr</tr>
  <tr>
    <th>ID</th>
    <th>Cat #</th>
    <th>Description</th>
    <th>price</th>
    <th>per-item</th>
    <th>per-pkg</th>
    <th>ord</th>
__END__;
	    if ($hasPkgs) {
		$out .= <<<__END__
    <th><i>shp</i></th>
    <th><i>rtn</i></th>
    <th><i>kld</i></th>
    <th><i>n/a</i></th>
    <th>OPEN</th>
__END__;
	    }
	    $out .= "\n  </tr>";

	    if ($doCapture) {
		$arFields = $this->Table()->CaptureEdit();
	    } else {
		$arFields = NULL;
	    }
	    if ($doRows) {
		$rnItem = $this->ItemTable()->SpawnItem();
		$isOdd = TRUE;
		$this->StartRows();
		while ($this->NextRow()) {
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isOdd = !$isOdd;

		    $row = $this->Values();
		    $id = $row['ID'];
		    $idItem = $this->Value('ID_Item');
		    $ftID = $this->AdminLink();
		    $strCatNum = $row['CatNum'];
		    $rnItem->KeyValue($idItem);
		    $htCatNum = $rnItem->AdminLink($strCatNum);
		    $strDescr = $row['Descr'];
		    $strPrice = $row['Price'];
		    $strPerItem = $row['ShipPkg'];
		    $strPerPkg = $row['ShipItm'];
		    $intQtyOrd = (int)$row['QtyOrd'];
		    $strQtyOrd = $intQtyOrd;
		    // calculated fields
		    if ($hasPkgs) {
			$arSumItem = $this->OrderRecord()->ItemStats_update_line($arPkgSums[$idItem]);

			$intQtyShp = (int)$arSumItem['qty-shp-line'];
			$intQtyRtn = (int)$arSumItem['qty-rtn'];
			$intQtyKld = (int)$arSumItem['qty-kld'];
			$intQtyNA  = (int)$arSumItem['qty-na'];
			$intQtyOpen = $intQtyOrd - $intQtyShp - $intQtyKld - $intQtyNA;
			$strQtyOpen = ($intQtyOpen == 0)?'-':('<font color=red><b>'.$intQtyOpen.'</b></font>');
		    }

		    //$out .= "\n|- style=\"$wtStyle\"";
		    //$out .= "\n| $ftID || $htCatNum || $strDescr || $strQtyOrd || $strPrice || $strPerItem || $strPerPkg ";
		    $out .= <<<__END__
  <tr style="$wtStyle">
    <td>$ftID</td>
    <td>$htCatNum</td>
    <td>$strDescr</td>
    <td align=right>$strPrice</td>
    <td align=right>$strPerItem</td>
    <td align=right>$strPerPkg</td>
    <td align=center>$strQtyOrd</td>
__END__;
		    if ($hasPkgs) {
			$out .= <<<__END__
<td align=center>$intQtyShp</td>
<td align=center>$intQtyRtn</td>
<td align=center>$intQtyKld</td>
<td align=center>$intQtyNA</td>
<td align=center>$strQtyOpen</td>
__END__;
		    }
		    $out .= "\n  </tr>";
		}
	    }
	    if ($doNew) {
		// need to initialize these, one way or another:
		/* or do we?
		$intQty = nz($arFields['Qty']);
		$ftDescr = nz($arFields['Descr']);
		$ftPrice = nz($arFields['Price']);
		$ftPerItem = nz($arFields['PerItem']);
		$ftPerPkg = nz($arFields['PerPkg']);
		*/
		$out .= "\n<form method=post>";
		$doFull = $doLookup;
		if ($doLookup) {
		    // look up the item specs
		    $strCatNum = nz($arFields['CatNum']);
		    $objItems = $this->ItemTable()->GetData('CatNum="'.$strCatNum.'"');
		    $doFull = $objItems->HasRows();
		    if (!$doFull) {
			$out .= 'No match found for cat # '.$strCatNum;
		    }
		    $objItems->FirstRow();
		    $arFields['Descr'] = $objItems->DescLong();
		    $arFields['Price'] = FormatMoney($objItems->PriceSell);
		    $objShipCode = $objItems->ShCost();
		    $arFields['ID_Item'] = $objItems->ID;
		    $arFields['PerItem'] = FormatMoney($objShipCode->PerItem);
		    $arFields['PerPkg'] = FormatMoney($objShipCode->PerPkg);
		} else if ($doSave) {
		    // gloopwy
		}
		$out .= self::RenderEdit_inline($doFull,$arFields);
		$out .= "\n</form>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out .= "\nNo items found.";
	}
	return $out;
    }
    // TODO: update or remove
    public function AdminTable_forItem() {
	if ($this->hasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>When</th>
    <th>Order</th>
    <th>Status</th>
    <th>price</th>
    <th align=right>qtys:</th>
    <th>Ord</th>
    <th>Shp</th>
    <th>Ext</th>
    <th>Xed</th>
    <th>N/A</th>
    <th>Open!</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$idOrd = $this->OrderID();
		$objOrd = $this->OrderTable()->GetItem($idOrd);
		$key = $objOrd->Value('WhenStarted').$objOrd->Value('Number');
		$arOrd[$key]['line'] = $this->RowCopy();
		$arOrd[$key]['ord'] = $objOrd;
	    }
	    krsort($arOrd);
	    foreach ($arOrd as $key => $data) {
		$objLine = $data['line'];
		$objOrd = $data['ord'];
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		//$wtOrd = $vgPage->SelfLink_WT(array('page'=>'order','id'=>$idOrd),$objOrd->Number);
		$wtOrd = $objOrd->AdminLink($objOrd->Number);
		$objBuyer = $objOrd->BuyerObj();
		if (is_object($objBuyer)) {
		    $wtOrd .= ': '.$objBuyer->AdminLink($objBuyer->NameStr());
		} else {
		    $wtOrd .= ' <i>(no buyer!)</i>';
		}
		$wtStatus = $objOrd->PulledText();

		$arStats = $objOrd->PackageSums($objLine->ItemID());
		$qtyOrd = $objLine->Value('QtyOrd');
		$qtyShp = nzArray($arStats,'qty-shp');
		$qtyRtn = nzArray($arStats,'qty-rtn');
		$qtyKld = nzArray($arStats,'qty-kld');
		$qtyNA = nzArray($arStats,'qty-na');
		$qtyOpen = $qtyOrd - $qtyShp - $qtyKld - $qtyNA;
		$wtOpen = empty($qtyOpen)?'-':"<b>$qtyOpen</b>";
		$wtWhen = $objOrd->WhenStarted;

		$strPrice = $objLine->Price;

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$wtWhen</td>
    <td>$wtOrd</td>
    <td>$wtStatus</td>
    <td>$strPrice</td>
    <td></td>
    <td align=center>$qtyOrd</td>
    <td align=center>$qtyShp</td>
    <td align=center>$qtyRtn</td>
    <td align=center>$qtyKld</td>
    <td align=center>$qtyNA</td>
    <td align=center>$wtOpen</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = "\nNo orders found for this item";
	}
	return $out;
    }

    // -- ADMIN INTERFACE -- //
    // ++ BUSINESS LOGIC ++ //

    /*----
      RETURNS: array contining sum of quantities ordered for each item in the order
      HISTORY:
	2011-03-24 fixed bug where multiple rows of same item were not being handled properly
	  Quantities are now added instead of latest overwriting previous value.
    */
    public function QtyArray() {
	if ($this->hasRows()) {
	    $arOut = NULL;
	    while ($this->NextRow()) {
		$idItem = $this->ItemID();
		$arOut[$idItem] = $this->Value('QtyOrd') + nzArray($arOut,$idItem,0);
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }

    // -- BUSINESS LOGIC -- //
}
