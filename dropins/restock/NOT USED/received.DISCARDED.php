<?php

// 2016-01-04 Bits of code discarded from received.logic.php

class clsRstkRcds_NOT_USED extends clsTable {
}
class vcrRstkRcvd_NOT_USED extends clsDataSet {
    /*----
      HISTORY:
	2010-11-26 boilerplate event logging
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->Engine()->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->LogObj()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function ReqObj() {
	throw new exception('Rewrite this if needed.');
	static $objReq;

	$doGet = TRUE;
	$idReq = $this->Row['ID_Request'];
	if (is_object($objReq)) {
	    if ($objReq->ID = $idReq) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $tblReqs = $this->objDB->RstkReqs();
	    $objReq = $tblReqs->GetItem($idReq);
	}
	return $objReq;
    }
    /*----
      HISTORY:
	2010-11-24 created
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$isNew = $this->IsNew();
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');
	$idReq = $vgPage->Arg('req');
	$doParse = $vgPage->Arg('parse');
	$doParseInvc = ($doParse == 'invoice');

	$vgPage->UseHTML();

	if ($isNew) {
	    $strTitle = 'New Shipment Received';
	    if ($idReq != 0) {
		$this->Value('ID_Request',$idReq);
		$objReq = $this->objDB->RstkReqs()->GetItem($idReq);
		$strTitle .= ' from PO# '.$objReq->PurchOrdNum;
	    }
	} else {
	    $strTitle = 'Restock Received inv# '.$this->SuppInvcNum.' (ID '.$this->ID.')';
	}

	// save edits before showing data
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	$vgOut->AddText($out); $out = '';

	$objReq = $this->ReqObj();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    // read-only fields
	    $ctReq = $objReq->AdminLink_friendly().$frm->Render('ID_Request');
	    // editable fields
	    $ctSuppInvc = $frm->Render('SuppInvcNum');
	    $ctCarrier = $frm->Render('CarrierDescr');
	    $ctInvcText = $frm->Render('InvcEntry');
	    $ctTracking = $frm->Render('TrackingCode');
	    $ctWhenShip = $frm->Render('WhenShipped');
	    $ctWhenRecd = $frm->Render('WhenReceived');
	    $ctWhenDebt = $frm->Render('WhenDebited');
	    $ctCalcMerch = $frm->Render('TotalCalcMerch');
	    $ctEstFinal = $frm->Render('TotalEstFinal');
	    $ctInvMerch = $frm->Render('TotalInvMerch');
	    $ctInvShip = $frm->Render('TotalInvShip');
	    $ctInvAdj = $frm->Render('TotalInvAdj');
	    $ctInvFinal = $frm->Render('TotalInvFinal');
	    $ctInvCond = $frm->Render('InvcCondition');
	    $ctPayMeth = $frm->Render('PayMethod');
	    $ctNotes = $frm->Render('Notes');
	} else {
	    $ctReq = $objReq->AdminLink_friendly();
	    $ctSuppInvc = fcString::EncodeForHTML($this->Row['SuppInvcNum']);
	    $ctCarrier = fcString::EncodeForHTML($this->Row['CarrierDescr']);

	    // invoice text block
	    $ctInvcText = '<pre>'.fcString::EncodeForHTML(trim($this->Row['InvcEntry'])).'</pre>';
	    if (!empty($this->Row['InvcEntry'])) {	// this will probably have to count rows -- must be >1
		if ($doParseInvc) {
		    $arStat = $this->ParseInvoice();
		    $ctInvcText .= $arStat['show'];
		    $cntNoFnd = $arStat['cnt.nofnd'];
		    if ($cntNoFnd > 0) {
			$strParse = 'Try again to parse items from text';
		    } else {
			$strParse = NULL;
		    }
		} else {
		    $strParse = 'parse items from this text';
		}
		if (is_null($strParse)) {
		    $ctInvcText .= '<hr>';
		} else {
		    $arLink = $vgPage->Args(array('page','id'));
		    $arLink['parse'] = 'invoice';
		    $urlLink = $vgPage->SelfURL($arLink,TRUE);
		    $ctInvcText .= ' [<a href="'.$urlLink.'">'.$strParse.'</a>]<hr>';
		}
	    }
	    // To avoid confusion, we only allow parsing of the saved text.
	    // Otherwise it would have to be a button = more programming.

	    $ctTracking = fcString::EncodeForHTML($this->Row['TrackingCode']);
	    $ctWhenShip = $this->Row['WhenShipped'];
	    $ctWhenRecd = $this->Row['WhenReceived'];
	    $ctWhenDebt = $this->Row['WhenDebited'];
	    $ctCalcMerch = $this->Row['TotalCalcMerch'];
	    $ctEstFinal = $this->Row['TotalEstFinal'];
	    $ctInvMerch = $this->Row['TotalInvMerch'];
	    $ctInvShip = $this->Row['TotalInvShip'];
	    $ctInvAdj = $this->Row['TotalInvAdj'];
	    $ctInvFinal = $this->Row['TotalInvFinal'];
	    $ctInvCond = $this->Row['InvcCondition'];
	    $ctPayMeth = fcString::EncodeForHTML($this->Row['PayMethod']);
	    $ctNotes = fcString::EncodeForHTML($this->Row['Notes']);
	}
	// non-editable fields:
	$ctID = $this->AdminLink();

	if ($this->IsNew()) {
	    $ftWikiInvcNum = '';
	} else {
//	    $txtInvcNum = $this->Row['SuppInvcNum'];
	    //$objReq = $this->ReqObj();
//	    $txtWikiInvcNum = $this->SupplierRecoprd()->CatKey.'-'.$txtInvcNum;
	    $txtWikiInvcNum = $this->Name();
	    $mwoCatg = Category::newFromName('invoices/'.$txtWikiInvcNum);
	    $mwoTitle = $mwoCatg->getTitle();
	    $ftWikiInvcNum = ' - ';
	    if ($mwoTitle === FALSE) {
		$ftWikiInvcNum .= $txtWikiInvcNum.': no wiki pages';
	    } else {
		$cntFiles = $mwoCatg->getFileCount();
		$txtFiles = $cntFiles.' file'.Pluralize($cntFiles);
		$wtCatg = '[[:'.$mwoTitle->getPrefixedText().'|'.$txtFiles.']]';
		$ftWikiInvcNum .= $wgOut->parseInLine($wtCatg);
	    }
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ctID</td></tr>";
	$out .= "\n<tr><td align=right><b>Request</b></td><td>$ctReq</td></tr>";
	$out .= "\n<tr><td align=right><b>Supplier invc #</b>:</td><td>$ctSuppInvc$ftWikiInvcNum</td></tr>";
	$out .= "\n<tr><td align=right><b>carrier</b>:</td><td>$ctCarrier</td></tr>";
	$out .= "\n<tr><td align=right><b>tracking code</b>:</td><td>$ctTracking</td></tr>";
	$out .= "\n<tr><td colspan=3><hr><b><big>timestamps</big></b></td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>shipped</b>:</td><td>$ctWhenShip</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>received</b>:</td><td>$ctWhenRecd</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>debited</b>:</td><td>$ctWhenDebt</td></tr>";
	$out .= "\n<tr><td colspan=3><hr><b><big>$ totals</big></b></td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>calculated merch</b>: $</td><td>$ctCalcMerch</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>estimated final</b>: $</td><td>$ctEstFinal</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice merch</b>: $</td><td>$ctInvMerch</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice shipping</b>: $</td><td>$ctInvShip</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice adjust</b>: $</td><td>$ctInvAdj</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice final</b>: $</td><td>$ctInvFinal</td></tr>";
	$out .= "\n<tr><td align=right><b>invoice condition</b>:</td><td>$ctInvCond</td></tr>";
	$out .= "\n</table>";
	if ($doParseInvc) {
	    $out .= '<hr>';	// bracket the invoice entry area to show that something's happening there
	}
	$out .= "\n<b>invoice entry</b> (first line is format): $ctInvcText";
	$out .= "\n<b>other notes</b>: $ctNotes";

	if ($doEdit) {
	    $out .= '<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out); $out = '';

	if (!$isNew) {
	    $objSection = new clsWikiSection($objPage,'Items Received',NULL,3);
	    //$objSection->ToggleAdd('stock','move received items into stock','move');
	    $out = $objSection->Generate();
	    $out .= $this->AdminItems();
	    $wgOut->AddHTML($out); $out = '';

	    $out = $vgOut->Header('Event Log',3);
	    $out .= $this->EventListing();
	    $vgOut->AddText($out);
	}
	return NULL;
    }
    /*----
      HISTORY:
	2010-11-25 adapted from VbzAdminTitle
    */
    protected function BuildEditForm() {
	global $vgPage,$vgOut;

	if (is_null($this->objForm)) {
	    // must be set before using $vgOut
	    $vgPage->UseHTML();
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    // We will eventually have this saved with the supplier data.
	    // For now, using the LB format to start with.
	    $txtInvcFmt = '\InvcQtyOrd\InvcQtySent\InvcCatNo\InvcDescr\CostInvPer\CostInvTot';

	    $objForm->AddField(new clsField('ID_Request'),$objCtrl = 	new clsCtrlHTML_Fixed());
	      if (isset($this->Row['ID_Request'])) {
		  $objReq = $this->ReqObj();
		  $objCtrl->Field()->Value($objReq->ID);
		  $objCtrl->Text_Show($objReq->AdminLink_friendly());
	      }
	    $objForm->AddField(new clsField('SuppInvcNum'),		new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsField('CarrierDescr'),		new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsField('InvcEntry',$txtInvcFmt),	new clsCtrlHTML_TextArea(array('rows'=>20)));
	    $objForm->AddField(new clsField('InvcTextFmt'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('TrackingCode'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsFieldTime('WhenShipped'),		new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsFieldTime('WhenReceived'),	new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsFieldTime('WhenDebited'),		new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsFieldNum('TotalCalcMerch'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalEstFinal'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvMerch'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvShip'),		new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvAdj'),		new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvFinal'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('InvcCondition'),$objCtrl = new clsCtrlHTML_DropDown());
	      $arDrop = array(
		0 => 'absent (no paperwork found)',
		1 => 'partial (one or more pages missing/illegible)',
		2 => 'complete (all pages located and legible)');
	      $objCtrl->Data_Rows($arDrop);
	      $objCtrl->Text_Choose('unknown / not yet entered');
	    $objForm->AddField(new clsField('PayMethod'),		new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsField('Notes'),			new clsCtrlHTML_TextArea(array('height'=>3)));

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the package
    */
    public function AdminSave() {
	global $vgOut,$wgRequest;

	$out = $this->objForm->Save($wgRequest->GetText('EvNotes'));
	$vgOut->AddText($out);
    }
    /*----
      ACTION: Renders table of items in restock request, with administrative controls
    */
    protected function AdminItems() {
	global $wgRequest;
	global $vgPage,$vgArg;

	$out = '';

	if ($wgRequest->GetBool('btnMove')) {
	    $out .= $this->AdminItems_handle_input();
	}

	$objRows = $this->Table->LinesTbl()->GetData('ID_Parent='.$this->GetKeyValue());
	$arLink = $vgPage->Args(array('page','id'));
	$urlForm = $vgPage->SelfURL($arLink,TRUE);
	$out .= '<form action="'.$urlForm.'" method=POST>';
	if ($objRows->hasRows()) {
	    $out .= '<table>';
	    $out .= '<tr>'
	      .'<td colspan=3></td>'	// ID, line, item
	      .'<td colspan=6 align=center bgcolor=#eeeeee>Invoice</td>'	// scat#, qty ord, qty sent, descr, per, tot
	      .'<td colspan=5 align=center bgcolor=#eeeeee>Actual</td>'		// cost act tot, cost act bal, act qty recd, qty filed
	      .'</tr>';
	    $out .= '<tr>'
	      .'<td colspan=4></td>'	// ID, line, item, scat#
	      .'<td colspan=2 align=center bgcolor=#eeeeee>Qtys</td>'	// inv qty ord, inv qty sent
	      .'<td colspan=1></td>'	// descr
	      .'<td colspan=2 align=center bgcolor=#eeeeee>Cost</td>'	// per, tot
	      .'<td colspan=3 align=center bgcolor=#eeeeee>Qtys</td>'	// act qty recd, qty filed
	      .'<td colspan=2 align=center bgcolor=#eeeeee>Cost</td>'	// act tot, act bal
	      .'</tr>';
	    $out .= '<tr>'
	      .'<th>Line</th>'
	      .'<th>ID</th>'
	      .'<th>Item</th>'
	      .'<th>SCat#</th>'
	      .'<th>ord</th>'
	      .'<th>sent</th>'
	      .'<th>Description</th>'
	      .'<th>$ ea</th>'
	      .'<th>$ line</th>'
	      .'<th>recd</th>'
	      .'<th>to move</th>'
	      .'<th>moved</th>'
	      .'<th>$ line</th>'
	      .'<th>$ bal</th>'
	      .'<th>Notes</th>'
	      .'</tr>';

	    while ($objRows->NextRow()) {
		$key = $objRows->Row['InvcLineNo'];
		$arSort[$key] = $objRows->RowCopy();
	    }
	    ksort($arSort);

	    $isOdd = FALSE;
	    $cntInputs = 0;
	    foreach ($arSort as $key => $obj) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$ftID = $obj->AdminLink();
		$objItem = $obj->ItemObj();
		$ftItem = $objItem->AdminLink($objItem->CatNum);
		$idxLine = $obj->Row['InvcLineNo'];
		$txtSCat = $obj->Row['InvcCatNo'];
		$qtyInvOrd = $obj->Row['InvcQtyOrd'];
		$qtyInvSent = $obj->Row['InvcQtySent'];
		$txtDescr = $obj->Row['InvcDescr'];
		$costInvPer = $obj->Row['CostInvPer'];
		$costInvTot = $obj->Row['CostInvTot'];
		$costActTot = $obj->Row['CostActTot'];
		$costActBal = $obj->Row['CostActBal'];

		$qtyRecd = $obj->Row['QtyRecd'];
		$qtyFiled = $obj->Row['QtyFiled'];
		$qtyLeft = $qtyRecd - $qtyFiled;

		$htQtyFiled = $qtyFiled;

		if (is_null($qtyRecd)) {
		    $htQtyRecd = '<input name="QtyRecd['.$idxLine.']" size=1 value="'.$qtyRecd.'">';
		    $cntInputs++;
		} else {
		    $htQtyRecd = $qtyRecd;
		}

		if (($qtyLeft > 0) || (is_null($qtyRecd))) {
		    $htQtyToMove = '<input name="QtyToMove['.$idxLine.']" size=1>';
		    $cntInputs++;
		} else {
		    $htQtyToMove = 'n/a';
		}

		$strNotes = $obj->Row['Notes'];

		$out .= '<tr style="'.$ftStyle.'">'
		  ."<td>$idxLine.</td>"
		  ."<td>$ftID</td>"
		  ."<td>$ftItem</td>"
		  ."<td>$txtSCat</td>"
		  ."<td align=center>$qtyInvOrd</td>"
		  ."<td align=center>$qtyInvSent</td>"
		  ."<td>$txtDescr</td>"
		  ."<td align=right>$costInvPer</td>"
		  ."<td align=right>$costInvTot</td>"
		  ."<td align=center>$htQtyRecd</td>"
		  ."<td align=center>$htQtyToMove</td>"
		  ."<td align=center>$htQtyFiled</td>"
		  ."<td align=right>$costActTot</td>"
		  ."<td align=right>$costActBal</td>"
		  ."<td>$strNotes</td>"
		  .'</tr>';
	    }
	    $out .= '</table>';
	    if ($cntInputs > 0) {
		$out .= '<input type=submit name=btnMove value="Update and/or Move"> Destination for move: ';
		$out .= $this->objDB->Bins()->DropDown_active();
	    }
	    $out .= '</form>';
	} else {
	    $out .= 'No items entered for this restock shipment.';
	}
/*
2010-11-27 We're probably not going to use this. The parsable invoice text lets us add new items as needed,
  plus allows for documentation (in comments) of what happened.
  	$arLink = $vgPage->Args(array('page','id'));
	$arLink['do'] = 'enter';
	$arLink['type'] = 'items';
	$out .= ' [<a href="'.$vgPage->SelfURL($arLink,TRUE).'">enter items</a>]';
*/
	return $out;
    }
    /*----
      ACTION: handles input from the user on the AdminItems() form
	* update received quantities
	* move items into selected stock bin
      HISTORY:
	2010-12-01 Created
    */
    protected function AdminItems_handle_input() {
	global $wgRequest;

	$out = NULL;

	$arRecd = $wgRequest->GetArray('QtyRecd');
	$arMove = $wgRequest->GetArray('QtyToMove');
	$idBin = $wgRequest->GetInt('bin');

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
	    $this->StartEvent($arEv);

	    $tblLines = $this->Table->LinesTbl();

	    // update received quantities
	    $cntRecd = 0;
	    foreach ($arRecd as $num => $qty) {
		$objLine = $this->LineObj($num);
		if ($objLine->HasRows()) {
		    if ($qty != $objLine->Value('QtyRecd')) {
			$arChg = array(
			  'QtyRecd'	=> $qty
			  );
			$objLine->Update($arChg);
			$cntRecd++;
		    }
		} else {
		    $out = 'Data Error: no rows found for line #'.$num.'.<br> - SQL: '.$objLine->sqlFilt;
		}
	    }

	    // move items into stock
	    $cntMove = 0;
	    foreach ($arMove as $num => $qty) {
		$objLine = $this->LineObj($num);
		if ($qty > 0) {
		    $out .= $objLine->Move_toBin($idBin,$qty);
		    $cntMove++;
		}
	    }

	    $txtEv = '<br> - Done: '
	      .$cntRecd.' item'.Pluralize($cntRecd).' received and '
	      .$cntMove.' item'.Pluralize($cntMove).' moved.';
	    $arEv = array(
	      'descrfin'	=> $txtEv
	      );
	    $this->FinishEvent($arEv);
	    $out .= $txtEv;
	} else {
	    $out = 'No input to process.';
	}
	return $out;
    }
    /*----
      NOTES:
	Ideally there should be no need to handle lost items gracefully, because any items on the invoice
	  should already be in the catalog because they were in the request... but in the real world,
	  (a) sometimes items ship that were not requested, (b) typos happen, (c) stuff gets requested without going
	  through VbzCart first.
      RETURNS: array
	array[show] = results to display
	array[cnt.nofnd] = number of items not found in the catalog data
    */
    protected function ParseInvoice() {
	$out = '';
	$txtItems = $this->Row['InvcEntry'];
	$xts = new xtString($txtItems);
	$arInLines = $xts->ParseTextLines(array('line'=>'arrx','blanks'=>"\t\n"));
	if (is_array($arInLines)) {
	    $cntLine = 0;
	    $cntFound = 0;	// count of items not found
	    $cntLost = 0;	// count of items not found
	    $txtFound = '';	// list of items found, plaintext format
	    $htFound = '';	// list of items found, HTML format
	    $txtLost = '';	// list of items not found
	    foreach ($arInLines as $idx => $arInLine) {
		if ($cntLine == 0) {
		    $arFormat = $arInLine;
		} else {
		    $arChg = NULL;
		    $strSCat = NULL;
		    foreach ($arInLine as $col => $val) {
			if (isset($arFormat[$col])) {
			    $strCol = $arFormat[$col];
			    //$out .= '[COL='.$col.' VAL='.trim($val).' =>'.$strCol.']';
			    if (isset($arOut[$strCol])) {
				$val = $arOut[$strCol].' '.$val;
			    }
			    $arChg[$strCol] = SQLValue($val);
			    if ($strCol == 'InvcCatNo') {
				$strSCat = $val;
			    }
			}
		    }

		    // look up item record from scat#
		    if (is_null($strSCat)) {
			$out .= '<br>Error: No supplier catalog # given in line '.$idx;
			$cntLost++;
		    } else {
			$objSupp = $this->SupplierRecord();
			$objItem = $objSupp->GetItem_bySCatNum($strSCat);
			if (is_null($objItem)) {
			    $cntLost++;
			    $txtLost .= ' '.$strSCat;
			} else {
			    $arChg['ID_Item'] = $objItem->ID;
			    $arChgs[$cntLine] = $arChg;
			    $cntFound++;
			    $htFound .= ' '.$objItem->AdminLink_CatNum();
			    $txtFound .= ' '.$objItem->CatNum.'(ID='.$objItem->ID.')';
			}
		    }
		}
		$cntLine++;
	    }
	    if ($cntFound > 0) {
		$txtDescr = 'Processing '.$cntFound.' item'.Pluralize($cntFound).':';
		$out .= $txtDescr.$htFound;
		$txtFound = $txtDescr.$txtFound;
	    }
	    if ($cntLost > 0) {
		$out .= '<br>Could not find '.$cntLost.' item'.Pluralize($cntLost).':'.$txtLost;
	    }
	    $out .= '<br>';
	    if (($cntLost == 0) && ($cntFound > 0)) {
		// log the update and do it
		$arEv = array(
		  'descr'	=> SQLValue($txtFound),
		  'code'	=> '"ITB"',	// item bulk entry
		  'where'	=> __METHOD__
		  );
		$this->StartEvent($arEv);
		$tblLines = $this->Table->LinesTbl();
		$idRcd = $this->Row['ID'];
		// disable all existing lines first, to avoid leftovers
		$this->ClearLines();
		$txtErr = $this->objDB->getError();
		if (!empty($txtErr)) {
		    $out .= '<br>Error deactivating lines: '.$txtErr;
		    $this->objDB->ClearError();
		}
		$cntNew = $cntOld = $cntErr = 0;	// reset counters
		foreach ($arChgs as $idx => $arChg) {
		    $dsLine = $tblLines->GetData('(ID_Parent='.$idRcd.') AND (InvcLineNo='.$idx.')');
		    $arChg['isActive'] = 'TRUE';
		    if ($dsLine->HasRows()) {
			$dsLine->NextRow();
			$dsLine->Update($arChg);
			$cntOld++;
		    } else {
			$arChg['ID_Parent'] = $idRcd;
			$arChg['InvcLineNo'] = $idx;
			$tblLines->Insert($arChg);
			$cntNew++;
		    }
		    $txtErr = $this->objDB->getError();
		    if (!empty($txtErr)) {
			$out .= '<br>Error in line '.$idx.': '.$txtErr;
			$out .= '<br> - SQL: '.$tblLines->sql;
			$this->objDB->ClearError();
			$cntErr++;
		    }
		}

		$txtEvent =
		  $cntNew.' line'.Pluralize($cntNew).' added, '
		  .$cntOld.' line'.Pluralize($cntOld).' updated.';
		if ($cntErr > 0) {
		    $txtErrEv = $cntErr.' error'.Pluralize($cntErr).' - last: '.$txtErr;
		    $txtEvent = $txtErrEv.' '.$txtEvent;
		    $isErr = TRUE;
		} else {
		    $isErr = FALSE;
		}
		$arEv = array(
		  'error'	=> $isErr,
		  'descrfin'	=> $txtEvent
		  );
		$this->FinishEvent($arEv);
		$out .= '<br>'.$txtEvent;
	    }
	} else {
	    $out = 'No data lines found.';
	}
	$arOut['show'] = $out;
	$arOut['cnt.nofnd'] = $cntLost;
	return $arOut;
    }
}