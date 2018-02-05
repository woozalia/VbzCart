<?php
/*
  PURPOSE: classes for handling administration of line items in received restocks
  HISTORY:
    2016-01-06 split off from received-line.logic.php (formerly received-line.php)
*/
class vctaRstkRcdLines extends vctlRstkRcdLines implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;
    use ftLoggableTable;
    
    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return 'vcraRstkRcdLine';
    }
    // CEMENT (I think)
    public function GetActionKey() {
	return KS_ACTION_RESTOCK_RECEIVED_LINE;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return 'Nothing written yet; maybe searches by item or date-range would be good.';
    }

    // -- EVENTS -- //
    // ++ TABLES ++ //
    
    protected function LineTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED,$id);
    }
    
    // -- TABLES -- //
    // ++ FORM HANDLING ++ //
    
    /*----
      ACTION: handles input from the user on the AdminItems() form
	* update received quantities
	* move items into selected stock bin
      HISTORY:
	2010-12-01 Created
    */
    public function AdminItems_handle_input() {
	//$oPage = $this->PageObject();
    
	$out = NULL;

	$arRecd = clsHTTP::Request()->GetArray('QtyRecd');
	$arMove = clsHTTP::Request()->GetArray('QtyToMove');
	$idBin = clsHTTP::Request()->GetInt('bin');

	$cntRecd = count($arRecd);
	$cntMove = count($arMove);
	$txtEvent = 'Processing '
	  .$cntRecd.' item'.Pluralize($cntRecd).' to receive and '
	  .$cntMove.' item'.Pluralize($cntMove).' to move...';

	if ($cntRecd + $cntMove > 0) {
	    $arEv = array(
	      'descr'	=> $txtEvent,
	      'code'	=> 'ITM',
	      'where'	=> __METHOD__
	      );
	    $rcEv = $this->CreateEvent($arEv);

	    // update received quantities
	    $cntRecd = 0;
	    foreach ($arRecd as $idLine => $qty) {
		$rcLine = $this->LineTable($idLine);
		if ($rcLine->HasRows()) {
		    if ($qty != $rcLine->Value('QtyRecd')) {
			$arChg = array(
			  'QtyRecd'	=> $qty
			  );
			$rcLine->Update($arChg);
			$cntRecd++;
		    }
		} else {
		    $nLine = $rcLine->InvoiceLineNumber();
		    $out = 'Data Error: no rows found for line #'.$nLine.'.<br> - SQL: '.$rcLine->sqlFilt;
		}
	    }

	    // move items into stock
	    $cntMove = 0;
	    foreach ($arMove as $idLine => $qty) {
		$rcLine = $this->LineTable($idLine);
		if ($qty > 0) {
		    $out .= $rcLine->Move_toBin($idBin,$qty)
		      .'<br>'
		      ;
		    $cntMove++;
		}
	    }

	    $txtEv = '<br> - Done: '
	      .$cntRecd.' item'.Pluralize($cntRecd).' received and '
	      .$cntMove.' item'.Pluralize($cntMove).' moved.';
	    $arEv = array(
	      'descrfin'	=> $txtEv
	      );
	    $rcEv->Finish($arEv);
	    $out .= $txtEv;
	} else {
	    $out = 'No input to process.';
	}
	return $out;
    }
    
    // -- FORM HANDLING -- //

}
class vcraRstkRcdLine extends vcrlRstkRcdLine implements fiLinkableRecord {
    use ftLinkableRecord;
    use ftShowableRecord { AdminRows_head as AdminRows_head_base; }
    use ftFrameworkAccess;
    use vtRestockLines;
    
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	//$this->arArgs = $arArgs;	// not currently used
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //
    
    protected function ParentClass() {
	return KS_ADMIN_CLASS_RESTOCKS_RECEIVED;
    }
    protected function ItemsClass() {
	// TODO: display informative error message if cat-local dropin is not available
	return KS_ADMIN_CLASS_LC_ITEMS;
    }
    protected function BinsClass() {
	return KS_CLASS_STOCK_BINS;
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    protected function ParentTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ParentClass(),$id);
    }
    protected function BinTable($id=NULL) {
	return $this->Engine()->Make($this->BinsClass(),$id);
    }
    protected function StockLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINES,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function ParentRecord() {
	return $this->ParentTable($this->GetParentID());
    }
    
    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //
    
    //++multi++//

    /*----
      ACTION: updates calculated fields for each row, and returns totals
      RETURNS: array of calculations
    */
    public function FigureTotals() {
	$arState = NULL;
	while ($this->NextRow()) {
	    $this->Update_LineCosts($arState);
	}
	
	$sTotChgs = clsArray::Nz($arState,'s.chgs.tot');
	$sBalChgs = clsArray::Nz($arState,'s.chgs.bal');
	
	$htMsgs = NULL;
	
	if (!is_null($sTotChgs)) {
	    $htMsgs .= 'Line total updates: '.$sTotChgs.'<br>';
	}
	if (!is_null($sBalChgs)) {
	    $htMsgs .= 'Line balance updates: '.$sBalChgs.'<br>';
	}
	  
	$arState['html'] = $htMsgs;
	return $arState;
    }
    
    //--multi--//
    
    // -- CALCULATIONS -- //
    // ++ ADMIN UI ++ //

    //++single++//
        
    /*----
      HISTORY:
	2016-03-03 There was a mandatory $idParent parameter, which had this note attached:
	  "$idParent is necessary because sometimes we need to refer back to the Parent object
	  without having a specific Line record to get it from." However, this isn't needed
	  for normal editing, and can be passed (where needed) via $this->ParentID() -- so
	  I'm removing it.
    */
    private function AdminPage() {
	$oFormIn = fcHTTP::Request();

	$doSave = $oFormIn->GetBool('btnSave');
	if ($doSave) {
	    $this->PageForm()->Save();
	    $this->SelfRedirect();
	}

	// page title
	
	$sTitle = 'Received Restock Line ID #'.$this->GetKeyValue();
	fcApp::Me()->GetPageObject()->SetPageTitle($sTitle);

	// header menu
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit this record'));
 
	    $doEdit = $ol->GetIsSelected();
	
//	$doEdit = ($oPage->PathArg('edit'));
	$isNew = $this->IsNew();
	
	/* 2017-03-24 old
	$arActs = array(
	    // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),'edit'),
	  ); */

	//$oPage->PageHeaderWidgets($arActs);
	
	$frm = $this->PageForm();
	
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	
	$out = NULL;
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	    //$this->ParentID($idParent);
	    $arCtrls['ID_Parent'] = $this->ParentRecord()->SelfLink_name();
	    $arCtrls['ID_Item'] = $this->ItemRecord()->SelfLink_name();
	}
	// render the template
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '</form>';
	}
	
	return $out;
    }
    
    private $oForm;
    private function PageForm() {
	// create fields & controls
	if (is_null($this->oForm)) {
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_Parent');
	      $oField = new fcFormField_Num($oForm,'ID_Item');
	      $oField = new fcFormField_BoolInt($oForm,'isActive');
	      $oField = new fcFormField_Text($oForm,'InvcLineNo');
	      $oField = new fcFormField_Text($oForm,'InvcCatNo');
	      $oField = new fcFormField_Num($oForm,'InvcQtyOrd');
	      $oField = new fcFormField_Num($oForm,'InvcQtySent');
	      $oField = new fcFormField_Text($oForm,'InvcDescr');
	      $oField = new fcFormField_Num($oForm,'QtyRecd');
	      $oField = new fcFormField_Num($oForm,'QtyFiled');
	      $oField = new fcFormField_Num($oForm,'CostInvPer');
	      $oField = new fcFormField_Num($oForm,'CostInvTot');
	      $oField = new fcFormField_Num($oForm,'CostActTot');
	      $oField = new fcFormField_Num($oForm,'CostActBal');
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
<table class=listing>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr class=odd><td align=right><b>Parent</b>:</td><td>[[ID_Parent]]</td></tr>
  <tr class=even><td align=right><b>Item</b>:</td><td>[[ID_Item]]</td></tr>
  <tr class=odd><td align=right><b>Active?</b>:</td><td>[[isActive]]</td>
    <td>NO = data in this line is obsolete, so ignore it</td>
    </tr>
  <tr><td colspan=2>invoice fields - data verbatim from the invoice:</td></tr>
  <tr class=odd><td align=right><b>Line #</b>:</td><td>[[InvcLineNo]]</td>
    <td>line number on invoice</td>
    </tr>
  <tr class=even><td align=right><b>Catalog #</b>:</td><td>[[InvcCatNo]]</td>
    <td>catalog number shown on invoice</td>
    </tr>
  <tr class=odd><td align=right><b>Qty Ordered</b>:</td><td>[[InvcQtyOrd]]</td>
    <td>quantity ordered, from invoice</td>
    </tr>
  <tr class=even><td align=right><b>Qty Shipped</b>:</td><td>[[InvcQtySent]]</td>
    <td>quantity shipped to us, according to invoice</td>
    </tr>
  <tr class=odd><td align=right><b>Description</b>:</td><td>[[InvcDescr]]</td>
    <td>description as shown on invoice</td>
    </tr>

  <tr><td colspan=2>our quantities - what we found and did:</td></tr>
  <tr class=odd><td align=right><b>Qty Received</b>:</td><td>[[QtyRecd]]</td>
    <td>quantity actually received</td>
    </tr>
  <tr class=even><td align=right><b>Qty to Stock</b>:</td><td>[[QtyFiled]]</td>
    <td>quantity moved into stock</td>
    </tr>

  <tr><td colspan=2>cost information:</td></tr>
  <tr class=odd><td align=right><b>Invoiced per-item cost</b>:</td><td>$[[CostInvPer]]</td>
    <td>invoiced per-item cost</td>
    </tr>
  <tr class=even><td align=right><b>Invoiced line total cost</b>:</td><td>$[[CostInvTot]]</td>
    <td>invoice line total (CostExpPer x InvcQtySent) for this item</td>
    </tr>
  <tr class=odd><td align=right><b>Total cost actually used</b>:</td><td>$[[CostActTot]]</td>
    <td>actual (best) line total as used for reconciling</td>
    </tr>
  <tr class=even><td align=right><b>Running balance</b>:</td><td>$[[CostActBal]]</td>
    <td>running total, calculated from CostActTot</td>
    </tr>
  <tr class=odd><td colspan=3><b>Notes</b>:<br>[[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    
    //--single--//
    //++multiple++//
    
    // PUBLIC so it can be called by parent object
    public function AdminRows_forParent($idParent) {
	$out = NULL;
	
	$oPage = $this->Engine()->App()->Page();
	$doMove = $oPage->ReqArgBool('btnMove');
	if ($doMove) {
	    $out .= $this->Table()->AdminItems_handle_input();
	    $this->ParentID($idParent);	// set parent ID
	    $rcParent = $this->ParentRecord();
	    $rcParent->SelfRedirect(NULL,$out);
	}
	$out .= "\n<center>\n"
	  .$this->AdminRows($this->AdminFields())
	  ."\n</center>";
	return $out;
    }
    protected function AdminFields() {
	return array(
	    'InvcLineNo'=> '#',
	    'ID'	=> NULL,
	    'ID_Item'	=> 'Item',
	    'InvcCatNo'	=> 'SCat#',
	    'InvcQtyOrd'=> 'ord',
	    'InvcQtySent'=> 'sent',
	    'InvcDescr'	=> 'Description',
	    'CostInvPer'=> '$ ea',
	    'CostInvTot'=> '$ line',
	    'QtyRecd'	=> 'recd',
	    '!QtyToMove'=> 'to move',	// calculated
	    'QtyFiled'	=> 'moved',
	    'CostActTot'=> '$ line',
	    'CostActBal'=> '$ bal',
	    'Notes'	=> 'Notes',
	  );
    }
    
    // CEMENT
    protected function AdminRows_settings_columns() {
	throw new exception('2017-04-16 Is this actually being called?');
    }
    
    // OVERRIDES trait
    protected function AdminRows_start() {
	return "\n<form method=post>"
	  ."\n<table class=listing>"
	  ;
    }
    // OVERRIDES trait
    protected function AdminRows_head() {
	$out = '<tr>'
	  .'<td colspan=3></td>'	// ID, line, item
	  .'<td colspan=6 align=center bgcolor=#eeeeee>Invoice</td>'	// scat#, qty ord, qty sent, descr, per, tot
	  .'<td colspan=5 align=center bgcolor=#eeeeee>Actual</td>'	// cost act tot, cost act bal, act qty recd, qty filed
	  .'</tr>'
	  .'<tr>'
	  .'<td colspan=4></td>'	// ID, line, item, scat#
	  .'<td colspan=2 align=center bgcolor=#eeeeee>Qtys</td>'	// inv qty ord, inv qty sent
	  .'<td colspan=1></td>'	// descr
	  .'<td colspan=2 align=center bgcolor=#eeeeee>Cost</td>'	// per, tot
	  .'<td colspan=3 align=center bgcolor=#eeeeee>Qtys</td>'	// act qty recd, qty filed
	  .'<td colspan=2 align=center bgcolor=#eeeeee>Cost</td>'	// act tot, act bal
	  .'</tr>'
	  .'<tr>'
	  ;
	return $out.$this->AdminRows_head_base();
    }
    // OVERRIDES trait
    protected function AdminRows_finish() {
	$out = "\n</table>";
	if ($this->AdminOption_get_hasQtys()) {
	    $out .= 'Destination for move: '
	      .$this->BinTable()->DropDown_active()
	      .'<input type=submit name=btnMove value="Update/Move">'
	      ."\n</form>"
	      ;
	}
	return $out;
    }
    // PURPOSE: this is called if there are any quantities to be entered
    private $hasQtys;
    protected function AdminOption_set_hasQtys() {
	$this->hasQtys = TRUE;
    }
    protected function AdminOption_get_hasQtys() {
	return $this->hasQtys;
    }
    protected function AdminField($sField) {
	$sAlign = NULL;
	if (strpos($sField,'!') === 0) {
	    switch ($sField) {
	      // pseudo-fields with no corresponding table field
	      case '!QtyToMove':
		$qtyFiled = $this->QtyFiled();
		$qtyRecd = $this->QtyReceived();
		$qtyLeft = $qtyRecd - $qtyFiled;
		if (($qtyLeft > 0) || (is_null($qtyRecd))) {
		    // if there are items to move into stock, allow user to enter how many to move
		    //$idxLine = $this->InvoiceLineNumber();
		    $idLine = $this->GetKeyValue();
		    $htVal = "<input name='QtyToMove[$idLine]' size=1>";
		    $this->AdminOption_set_hasQtys();
		} else {
		    $htVal = '--';
		}
		break;
	    }
	} else {
	    // actual data-fields that just need a little tweaking:
	    $val = $this->Value($sField);
	    switch ($sField) {
	      case 'ID':
		$htVal = $this->SelfLink();
		break;
	      case 'ID_Item':
		$rcItem = $this->ItemRecord();
		$htVal = $rcItem->SelfLink_name();
		break;
	      case 'QtyRecd':
		$qtyRecd = $val;
		//$idxLine = $this->InvoiceLineNumber();
		$idLine = $this->GetKeyValue();
		if (is_null($qtyRecd)) {
		    // if received qty not yet entered, allow user to enter it
		    $htVal = "<input name='QtyRecd[$idLine]' size=1>";
		    $this->AdminOption_set_hasQtys();
		} else {
		    $htVal = $qtyRecd;
		}
		break;
	      case 'CostInvPer':
	      case 'CostInvTot':
	      case 'CostActTot':
	      case 'CostActBal':
		$sAlign = 'right';
	      default:
		$htVal = $val;
	    }
	}
	$htAlign = is_null($sAlign)?NULL:" align=$sAlign";
	return "<td$htAlign>$htVal</td>";
    }
    
    //--multiple--//
    //++multiple-for-item++//
    
    protected function AdminRows_forItem_NoDataText() {
	return '<div class=content>No received restocks found for this item.</div>';
    }
    protected function AdminRows_forItem_Header() {
	return <<<__END__
<table class=listing>
  <tr>
    <th colspan=6>Received</th>
    <th colspan=8>Line Item</th>
  </tr>
  <tr>
    <th colspan=3></th>
    <th colspan=3>-- When --</th>
    <th colspan=1></th>
    <th colspan=4>from Invoice</th>
  </tr>
  <tr>
    <th title="ShipIn (received restock) ID">ID</th>
    <th title="Request ID">Req ID</th>
    <th title="Invoice #">Invc #</th>
    <th>Shipped</th>
    <th>Received</th>
    <th>Debited</th>
  
    <th title="Line Item ID">ID</th>
    <th title="catalog number on invoice">Cat #</th>
    <th title="description on invoice">Description</th>
    <th title="from invoice: quantity ordered">Ord</th>
    <th title="from invoice: quantity sent"">Sent</th>
    <th title="quantity actually received">Rec'd</th>
    <th title="quantity moved into stock">Filed</th>
    <th>Notes</th>
  </tr>
__END__;
    }
    protected function AdminRow_forItem(array $arRow) {
	$rcMain = $this->ParentTable()->SpawnRecordset();
	
	$arLine = $arRow['line'];
	$arMain = $arRow['main'];
	$this->SetFieldValues($arLine);
	$rcMain->SetFieldValues($arMain);

	// Request fields

	$ftMainID = $rcMain->SelfLink();
	$ftReqID = $rcMain->RequestLink_name();
	$sInvcNum = $rcMain->InvoiceNumber();
	
	$sWhenShp = $rcMain->WhenShipped();
	$dt = strtotime($sWhenShp);
	$ftWhenShp = is_null($sWhenShp)?NULL:date('Y-m-d',$dt);
	$yr = date('Y',$dt);

	$sWhenRcd = $rcMain->WhenReceived();
	$ftWhenRcd = fcDate::DefaultYear($sWhenRcd,$yr);

	$sWhenDeb = $rcMain->WhenDebited();
	$ftWhenDeb = fcDate::DefaultYear($sWhenDeb,$yr);
	
	// Line-item fields
	
	$ftLineID = $this->SelfLink();
	$sCatNum = $this->GetFieldValue('InvcCatNo');
	$sDescr = $this->GetFieldValue('InvcDescr');
	$qtyOrd = $this->GetFieldValue('InvcQtyOrd');
	$qtySent = $this->GetFieldValue('InvcQtySent');
	$qtyRecd = $this->QtyReceived();
	$qtyFiled = $this->QtyFiled();

	$sNotes = $this->GetFieldValue('Notes');

	return <<<__END__
    <td>$ftMainID</td>
    <td>$ftReqID</td>
    <td>$sInvcNum</td>
    <td>$ftWhenShp</td>
    <td>$ftWhenRcd</td>
    <td>$ftWhenDeb</td>
    
    <td>$ftLineID</td>
    <td>$sCatNum</td>
    <td>$sDescr</td>
    <td align=center>$qtyOrd</td>
    <td align=center>$qtySent</td>
    <td align=center>$qtyRecd</td>
    <td align=center>$qtyFiled</td>
    <td>$sNotes</td>
__END__;
    }
    
    //--multiple-for-item--//
    
    // -- ADMIN UI -- //
}