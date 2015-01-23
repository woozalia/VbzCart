<?php
/*
  FILE: admin.rstk.php -- restock functionality for VbzCart
  HISTORY:
    2010-10-17 Extracted restock classes from SpecialVbzAdmin.php
    2013-12-15 Adapting for dropin-module system.
  CLASSES:
    * restock requests:
      clsRstkReq(s) - business logic functions, UI-agnostic
      VCM_RstkReqs - administration interface
      VCM_RstksNeeded - administration interface for requests *needed*
      clsAdminRstkReq(s) - not sure what uses this anymore
      * restock request line-items:
	clsRstkReqItem(s)
    * restocks received:
      clsRstkRcds
	  clsRstkRcdLines
*/

// %%%% BUSINESS LOGIC CLASSES %%%% //

class cRstkReqs extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('cRstkReq');
	  $this->Name(KS_TABLE_RESTOCK_REQUEST);
	  $this->KeyName('ID');
	  $this->ActionKey('rstk-req');
    }

    // ++ DATA RECORD ACCESS ++ //

    public function RowsActive($idSupp=NULL) {
	$this->Name('qryRstks_active');
	if (is_null($idSupp)) {
	    $sqlFilt = NULL;
	} else {
	    $sqlFilt = 'ID_Supplier='.$idSupp;
	}
	$rsRows = $this->GetData($sqlFilt,NULL,'WhenCreated DESC,WhenOrdered DESC');
	return $rsRows;
    }
    public function RowsInactive() {
	$this->Name('qryRstks_inactive');
	$rsRows = $this->GetData(NULL,NULL,'WhenCreated DESC,WhenOrdered DESC');
	return $rsRows;
    }

    // -- DATA OBJECT ACCESS -- //
    // ++ ACTIONS ++ //

    public function Create($idSupp,$sPONum,array $arItems) {
	$arNew = array(
	  'ID_Supplier'	=> $idSupp,
	  'PurchOrdNum'	=> SQLValue($sPONum),
	  'WhenCreated'	=> 'NOW()'
	  );
	$this->Insert($arNew);		// new restock request
	$id = $this->Engine()->NewID();
	$rcNew = $this->GetItem($id);	// load the request record

	// got the ID for the master record; now add the item records:
	foreach ($arItems as $id => $qty) {
	    $rcNew->AddItem($id,$qty);
	}
	return $rcNew;
    }

    // -- ACTIONS -- //
}
class cRstkReq extends clsDataSet {
    // ++ FIELD ACCESS ++ //

    /*----
      RETURNS: String identifying the request in a user-friendly way
      NOTE: This can be enhanced by borrowing from Access code, which added some more info.
    */
    public function Name() {
	return $this->PurchaseOrderNumber();
    }
    public function Descr() {
	return $this->PurchaseOrderNumber().' created '.$this->WhenCreated();
	// we can add more info later if needed
    }
    public function PurchaseOrderNumber() {
	return $this->Value('PurchOrdNum');
    }
    public function WhenCreated() {
	return $this->Value('WhenCreated');
    }
    public function IsActive() {
	return
	  (is_null($this->WhenKilled)) &&
	  (is_null($this->WhenClosed)) &&
	  (is_null($this->WhenOrphaned));
    }

    // -- FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function SupplierTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_SUPPLIERS,$id);
    }
    protected function CatalogItemTable() {
	return $this->Engine()->Make(KS_CLASS_CATALOG_ITEMS);
    }
    protected function ReceivedTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_RESTOCKS_RECEIVED,$id);
    }
    protected function RequestItemTable() {
	return $this->Engine()->Make(KS_CLASS_RESTOCK_REQ_ITEMS);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function SupplierRecord() {
	$id = $this->Value('ID_Supplier');
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->SupplierTable($id);
	}
    }
    /*----
      RETURNS: Received record (if any) corresponding to this Request
    */
    public function ReceivedRecord() {
	$id = $this->KeyValue();
	return $this->ReceivedTable($id);
    }
    protected function WarehouseRecord() {
	$idWhse = $this->Value('ID_Warehouse');
	if (is_null($idWhse)) {
	    return NULL;
	} else {
	    return $this->SupplierTable($idWhse);
	}
    }
    protected function WarehouseLink() {
	$rcWhse = $this->WarehouseRecord();
	if (is_null($rcWhse)) {
	    return '<i>none</i>';
	} else {
	    return $rcWhse->AdminLink_name();
	}
    }
    protected function WarehouseDropDown() {
	$rcWhse = $this->WarehouseRecord();
	if (is_null($rcWhse)) {
	    return '<i>N/A</i>';	// TODO: write a drop-down for the table class
	} else {
	    return $rcWhse->DropDown();
	}
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: adds a line item to the request
      METHOD: defers to item table class
    */
    public function AddItem(clsItem $rcItem,$qtyNeed,$qtySold) {
	return $this->RequestItemTable()->AddItem($this->KeyValue(),$rcItem,$qtyNeed,$qtySold);
    }

    // -- ACTIONS -- //
}

// %%%% USER (ADMIN) INTERFACE CLASSES %%%% //

class VCT_RstkReqs extends cRstkReqs {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_RstkReq');
    }

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;	// not currently used
	$rs = $this->RowsActive();
	$out = $rs->AdminList();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ ADMIN API ++ //
    // -- ADMIN API -- //
}

class VCR_RstkReq extends cRstkReq {

    // ++ BOILERPLATE ++ //

    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = $this->Logger();
	}
	return $this->logger;
    }
    public function Logger() {
	return new clsLogger_DataSet($this,$this->Engine()->App()->Events());
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name($arArgs=NULL) {
	$sName = $this->Name();
	$sDescr = $this->Descr();
	return $this->AdminLink($sName,$sDescr,$arArgs);
    }

    // -- BOILERPLATE -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ ADMIN API ++ //

    protected function AdminReceived() {
	$id = $this->KeyValue();
	$rc = $this->ReceivedRecord();
	$out = $rc->AdminList();

	if (is_null($out)) {
	    $out = "\nNo restock shipments received.";
	}

	// include action link for creating new received restock
	$rcNew = $rc->Table()->SpawnItem();
	$arArgs = array('req'=>$id);
	$out .= '[ '.$rcNew->AdminLink('add','add a received restock shipment',$arArgs).' ]';

	return $out;
    }
    public function AdminList() {
	clsModule::LoadFunc('Date_DefaultYear');

	if ($this->hasRows()) {
	    $out = <<<__END__
<table>
  <tr>
    <th>ID</th>
    <th>Crea.</th>
    <th>Ord.</th>
    <th>sent to</th>
    <th>via</th>
    <th>our PO#</th>
    <th>their PO#</th>
    <th>Supp. Ord#</th>
    <th>$ Est</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    $yrLast = 0;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;
		$id = $row['ID'];
		$ftID = $this->AdminLink();

		$strOver = is_null($row['WhenOrdered'])?$row['WhenCreated']:$row['WhenOrdered'];
		$dtOver = strtotime($strOver);
		$yrOver	= date('Y',$dtOver);
		if ($yrLast != $yrOver) {
		    $yrLast = $yrOver;
		    $out .= '<tr style="background: #444466; color: #ffffff;"><td colspan=5><b>'.$yrOver.'</b></td></tr>';
		}
		$ftWhenCre = Date_DefaultYear($row['WhenCreated'],$yrOver);
		$ftWhenOrd = Date_DefaultYear($row['WhenOrdered'],$yrOver);

		$idWH = $row['ID_Warehouse'];
		if (is_null($idWH)) {
		    $ftDest = '(none)';
		} else {
		    $rcDest = $this->SupplierTable($idWH);
		    $ftDest = $rcDest->AdminLink($rcDest->Value('CatKey'),'manage '.$rcDest->Value('Name'));
		}
		$ftShipVia = $row['CarrierDescr'];
		$ftOurPO = $row['PurchOrdNum'];
		$ftSuppPO = $row['SuppPONum'];
		$ftSuppOrd = $row['SuppOrdNum'];
		$ftCostEst = $row['TotalCalcMerch'];
		$ftNotes = $row['Notes'];

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$ftID</td>
    <td>$ftWhenCre</td>
    <td>$ftWhenOrd</td>
    <td>$ftDest</td>
    <td>$ftShipVia</td>
    <td>$ftOurPO</td>
    <td>$ftSuppPO</td>
    <td>$ftSuppOrd</td>
    <td>$ftCostEst</td>
    <td><small>$ftNotes</small></td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No requests found.';
	}
	return $out;
    }
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$arActs = array(
	  new clsActionLink_option(array(),'edit'),
	  //new clsActionLink_option(array(),'update'),
	  //new clsActionLink_option(array(),'inv',NULL,'inventory',NULL,'list all inventory of location '.$strName)
	  );
	$sTitle = 'Restock Request '.$this->Value('PurchOrdNum').' (ID '.$this->KeyValue().')';
	$out = $oPage->ActionHeader($sTitle,$arActs);

/*
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Restock Request '.$this->Value('PurchOrdNum').' (ID '.$this->KeyValue().')');
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();
*/
	$strAction = $oPage->PathArg('do');
	$doEdit = $oPage->PathArg('edit');
	$doSave = $oPage->ReqArgBool('btnSave');
	if ($doEdit || $doSave) {
	    $this->BuildEditForm(FALSE);
	    if ($doSave) {
		$this->AdminSave();
	    }
	}

	$doActBox = FALSE;
	$doAction = FALSE;
	$doStamp = FALSE;
	$doEnter = FALSE;
	$strType = $oPage->PathArg('type');
	switch ($strAction) {
	  case 'mark':
	    $doActBox = TRUE;
	    $doStamp = TRUE;	// show the controls for setting a stamp
	    break;
	  case 'enter':
	    $doActBox = TRUE;
	    $doEnter = TRUE;	// show the controls for entering stuff
	    break;
/*
	  case 'add':
	    $doEnter = TRUE;
	    break;
*/
	}
	if ($oPage->ReqArgBool('btnStamp')) {
	    $doActBox = TRUE;
	    $doAction = TRUE;
	    $doStamp = TRUE;	// receive timestamp data and use it
	}
	if ($doActBox) {
	    $htXtra = '';
	    if ($doStamp) {
		// we're doing a timestamp
		switch ($strType) {
		  case 'order':
		    $strDescr = 'Mark as Ordered';
		    $htXtra = '<br>Payment: <input type=text name=PayMeth size=20>';
		    $sqlField = 'WhenOrdered';
		    break;
		  case 'confirm':
		    $strDescr = 'Mark as Confirmed';
		    $htXtra = '<br>Supplier PO #: <input type=text name=SuppPONum size=20>';
		    $htXtra .= '<br>Supplier Ord #: <input type=text name=SuppOrdNum size=20>';
		    $sqlField = 'WhenConfirmed';
		    break;
		  case 'kill':
		    $strDescr = 'Mark as Cancelled';
		    $sqlField = 'WhenKilled';
		    break;
		  case 'close':
		    $strDescr = 'Close the Order';
		    $sqlField = 'WhenClosed';
		    break;
		  case 'orphan':
		    $strDescr = 'Mark as Orphaned';
		    $sqlField = 'WhenOrphaned';
		    break;
		}
	    }
	    if ($doEnter) {
		switch ($strType) {
		  case 'items':
		    $out .= $this->EnterItems();
		    break;
/*
		  case 'rcd':
		    $out .= $this->EnterRecd();
		    break;
*/
		}
	    }

	    if ($doStamp) {
		// we do stamps in a box
		$out .= '<table align=right width=30%><tr><td><h3>'.$strDescr.'</h3>';
	    }
	    if ($doAction) {
		if ($doStamp) {
		    $arUpd[$sqlField] = 'NOW()';
		    if ($oPage->ReqArgText('SuppPONum')) {
			$arUpd['SuppPONum'] = $oPage->ReqArgText('SuppPONum');
		    }
		    if ($oPage->ReqArgText('SuppOrdNum')) {
			$arUpd['SuppOrdNum'] = $oPage->ReqArgText('SuppOrdNum');
		    }
		    $strNotes = $oPage->ReqArgText('notes');
		    if (!empty($strNotes)) {
			$strDescr .= ': '.$strNotes;
		    }

		    $arEv = array(
		      clsSysEvents::ARG_WHERE	=> __METHOD__,
		      clsSysEvents::ARG_DESCR_START	=> $strDescr,
		      clsSysEvents::ARG_CODE		=> 'mark-'.$strType,
		      );
		    $this->StartEvent($arEv);
		    $this->Update($arUpd);
		    $this->Reload();
		    $this->FinishEvent();
		    $out .= 'Order marked';
		    if (!empty($strNotes)) {
			$out .= ', with notes: <b>'.$strNotes.'</b>';
		    }
		    $out .= ' (event ID #'.$this->idEvent.')';
		}
		if ($doEnter) {
		    $txtList = $oPage->ReqArgArray('items');
		    $xts = new xtString($txtList);
		    $xts->ReplaceSequence("\t ",' ');
		    $txtList = $xts->Value();
		    $arLines = explode("\n", $txtList);
		    $tblSupp = $this->SupplierObj();
		    foreach ($arLines as $line) {
			$strLine = rtrim($line,';#!');	// remove comments
			$strLine = trim($strLine);	// remove leading & trailing whitespace
			$arLine = explode(' ',$strLine);
			$strCat = $arLine[0];
			if (!empty($strCat)) {
			    $intQty = isset($arLine[1])?((int)$arLine[1]):1;
			    $objItem = $tblSupp->FindItems($arCatNums);

			    $out .= '<tr><td>';
			}
		    }
		}
	    } else {
		if ($doStamp) {
		    $arLink = $oPage->PathArgs(array('page','id'));
		    $txtNotes = $oPage->ReqArgText('notes');
		    $urlForm = $oPage->SelfURL($arLink,TRUE);
		    $out .= '<form method=post action="'.$urlForm.'">';
		    $out .= 'Log notes:<br>';
		    $out .= '<textarea rows=3 cols=30 name=notes>'.htmlspecialchars($txtNotes).'</textarea>';
		    $out .= $htXtra;
		    $out .= '<input type=hidden name=type value="'.$strType.'">';
		    $out .= '<br><input type=submit name=btnStamp value="Stamp It">';
		    $out .= '</form>';
		}
	    }
	    $out .= '</td></tr></table><hr>';
	}

	$htOurPO = htmlspecialchars($this->PurchOrdNum);
	$htSupPO = htmlspecialchars($this->SuppPONum);
	$htSupOr = htmlspecialchars($this->SuppOrdNum);
	$htCarrier = htmlspecialchars($this->CarrierDescr);
	$htCostMerch = cCartLine_form::FormatMoney($this->TotalCalcMerch);	// calculated cost of merchandise
	$htCostFinal = cCartLine_form::FormatMoney($this->TotalEstFinal);	// estimated final total (s/h usually uncertain)
	$htPayMethod = htmlspecialchars($this->PayMethod);
	if ($doEdit) {
	    //$arLink = $oPage->PathArgs(array('page','id'));
	    //$htPath = $oPage->SelfURL($arLink,TRUE);
	    //$out .= "\n<form method=post action=\"$htPath\">";
	    $out .= "\n<form method=post>";

	    $ctWhse = $this->WarehouseDropDown();

	    $frm = $this->objForm;

	    $ctrlOurPO		= $frm->Render('PurchOrdNum');
	    $ctrlSupPO		= $frm->Render('SuppPONum');
	    $ctrlSupOr		= $frm->Render('SuppOrdNum');
	    $ctrlCarrier	= $frm->Render('CarrierDescr');
	    $ctrlCostMerch	= $frm->Render('TotalCalcMerch');
	    $ctrlCostFinal	= $frm->Render('TotalEstFinal');
	    $ctrlPayMethod	= $frm->Render('PayMethod');

	    // timestamps
	    $ctrlWhenOrdered	= $frm->Render('WhenOrdered');
	    $ctrlWhenConfirmed	= $frm->Render('WhenConfirmed');
	    $ctrlWhenKilled	= $frm->Render('WhenKilled');
	    $ctrlWhenClosed	= $frm->Render('WhenClosed');
	    $ctrlWhenOrphaned	= $frm->Render('WhenOrphaned');

	    $ctrlNotes		= $frm->Render('Notes');
	} else {
	    $ctWhse = $this->WarehouseLink();

	    $ctrlOurPO = $htOurPO;
	    $ctrlSupPO = $htSupPO;
	    $ctrlSupOr = $htSupOr;
	    $ctrlCarrier = $htCarrier;
	    $ctrlCostMerch = $htCostMerch;
	    $ctrlCostFinal = $htCostFinal;
	    $ctrlPayMethod = $htPayMethod;

	    // TIMESTAMPS
	    $arLink = array(
	      'page'	=> 'rstk-req',
	      'id'		=> $this->ID,
	      'do'		=> 'mark');
	    $isActive = $this->IsActive();

	    $txtVal = $this->Value('WhenOrdered');
	    if ($isActive && is_null($txtVal)) {
		$arLink['type'] = 'order';
		$url = $oPage->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'stamp','mark as ordered');
		$ctrlWhenOrdered = ifEmpty($txtVal,'['.$htLink.']');
	    } else {
		$ctrlWhenOrdered = $txtVal;
	    }

	    $txtVal = $this->Value('WhenConfirmed');
	    if ($isActive && is_null($txtVal)) {
		$arLink['type'] = 'confirm';
		$url = $oPage->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'stamp','mark order as confirmed');
		$ctrlWhenConfirmed = ifEmpty($txtVal,'['.$htLink.']');
	    } else {
		$ctrlWhenConfirmed = $txtVal;
	    }

	    $txtVal = $this->Value('WhenKilled');
	    if ($isActive && is_null($txtVal)) {
		$arLink['type'] = 'kill';
		$url = $oPage->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'kill','kill the order');
		$ctrlWhenKilled = ifEmpty($txtVal,'['.$htLink.']');
	    } else {
		$ctrlWhenKilled = $txtVal;
	    }

	    $txtVal = $this->Value('WhenClosed');
	    if ($isActive && is_null($txtVal)) {
		$arLink['type'] = 'close';
		$url = $oPage->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'close','close the order');
		$ctrlWhenClosed = ifEmpty($txtVal,'['.$htLink.']');
	    } else {
		$ctrlWhenClosed = $txtVal;
	    }

	    $txtVal = $this->Value('WhenOrphaned');
	    if ($isActive && is_null($txtVal)) {
		$arLink['type'] = 'orphan';
		$url = $oPage->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'stamp','mark this order as orphaned');
		$ctrlWhenOrphaned = ifEmpty($txtVal,'['.$htLink.']');
	    } else {
		$ctrlWhenOrphaned = $txtVal;
	    }

	    $ctrlNotes = $this->Value('Notes');
	}
	$ftWhenCreated = $this->Value('WhenCreated');
	if (is_null($this->Row['ID_Supplier'])) {
	    throw new exception('Supplier is NULL, which should not be happening.');
	}
	$rcSupp = $this->SupplierRecord();
	$ctSupp = $rcSupp->AdminLink_name();
	$strSupp = $rcSupp->Value('Name');
	//$htSuppWiki = $vgOut->InternalLink($rcSupp,'wiki',$strSupp.' info on wiki, if any');
	// temporary kluge until wiki component is available:
	$wtSupp = str_replace(' ','_',$strSupp);
	$urlSuppWiki = KWP_WIKI_PRIVATE.$wtSupp;
	$htSuppWiki = '<a href="'.$urlSuppWiki.'" title="'.$strSupp.' info on wiki, if any">wiki</a>';

	$out .= <<<__END__
<ul>
<li> <b>Our PO#</b>: $ctrlOurPO	</li>
<li> <b>Supp PO#</b>: $ctrlSupPO	</li>
<li> <b>Supp Ord#</b>: $ctrlSupOr	</li>

<li> <b>from Supplier</b>: $ctSupp ($htSuppWiki)	</li>
<li> <b>to Warehouse</b>: $ctWhse	</li>
<li> <b>Carrier</b>: $ctrlCarrier	</li>
<li> <b>Total Calc Merch</b>: $ctrlCostMerch	</li>
<li> <b>Total Est Final</b>: $ctrlCostFinal	</li>
<li> <b>Paid with</b>: $ctrlPayMethod	</li>

<li> <b>Timestamps</b>:
<table>
  <tr><td align=right><b>Created</b>:</td><td>	$ftWhenCreated</td></tr>
  <tr><td align=right><b>Ordered</b>:</td><td>	$ctrlWhenOrdered</td></tr>
  <tr><td align=right><b>Confirmed</b>:</td><td>	$ctrlWhenConfirmed</td></tr>
  <tr><td align=right><b>Killed</b>:</td><td>	$ctrlWhenKilled</td></tr>
  <tr><td align=right><b>Closed</b>:</td><td>	$ctrlWhenClosed</td></tr>
  <tr><td align=right><b>Orphaned</b>:</td><td>	$ctrlWhenOrphaned</td></tr>
</table>
<li> <b>Notes</b>: $ctrlNotes
</ul>
__END__;

	if ($doEdit) {
	    $out .= '<input type=submit value="Save" name=btnSave>';
	    $out .= '</form>';
	}

	$out .= '<h3>Shipments Received</h3>';
	$out .= $this->AdminReceived($this->KeyValue());

	$out .= '<h3>Items in Request</h3>';
	$out .= $this->AdminItems();

	$out .= '<h3>Event Log</h3>';
	$out .= $this->EventListing();

	return $out;
    }
    /*-----
      ACTION: Build the editing form
    */
    private function BuildEditForm($iNew) {
	// create fields & controls
	if (is_null($this->objFlds)) {
	    $objForm = new clsForm_recs($this);

	    $objForm->AddField(new clsField('PurchOrdNum'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('SuppOrdNum'),		new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsField('SuppPONum'),		new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsField('CarrierDescr'),		new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsFieldNum('TotalCalcMerch'),	new clsCtrlHTML(array('size'=>'6')));
	    $objForm->AddField(new clsFieldNum('TotalEstFinal'),	new clsCtrlHTML(array('size'=>'6')));
	    $objForm->AddField(new clsField('PayMethod'),		new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsFieldTime('WhenOrdered'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenConfirmed'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenKilled'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenClosed'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenOrphaned'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenExpectedOrig'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenExpectedFinal'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('isLocked'),		new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsField('Notes'),	new clsCtrlHTML_TextArea(array('height'=>3,'width'=>30)));

	    $this->objForm = $objForm;
	}
    }
    /*----
      ACTION: Renders table of items in restock request, with administrative controls
    */
    protected function AdminItems() {
	$out = '';
	$rsLine = $this->RequestItemTable()->GetData('ID_Restock='.$this->KeyValue());
	if ($rsLine->hasRows()) {
	    $out .= <<<__END__
<table>
  <tr><td colspan=2></td><td colspan=5 align=center bgcolor=#eeeeee>Quantities</td></tr>
  <tr>
    <th>Item</th>
    <th>Description</th>
    <th>need</th>
    <th>cust</th>
    <th>ord</th>
    <th>exp</th>
    <th>gone</th>
    <th>$ ea</th>
    <th>Notes</th>
  </tr>
__END__;
	    $tItems = $this->CatalogItemTable();
	    while ($rsLine->NextRow()) {
		$idItem = $rsLine->Value('ID_Item');
		$rcItem = $tItems->GetItem($idItem);
		$key = $rcItem->Value('CatNum');
		$arSort[$key]['item'] = $rcItem;
		$arSort[$key]['line'] = $rsLine->Values();
	    }
	    ksort($arSort);

	    $isOdd = FALSE;
	    $rcLine = $rsLine;	// need a line record-object
	    foreach ($arSort as $key => $data) {
		$rcItem = $data['item'];
		$rcLine->Values($data['line']);

		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$ftItem =
		  '['.$rcLine->AdminLink('edit').'] '
		  .$rcItem->AdminLink($rcItem->CatNum());
		$ftDescr = $rcLine->Value('Descr');
		$qtyNeed = $rcLine->Value('QtyNeed');
		$qtyCust = $rcLine->Value('QtyCust');
		$qtyOrd = $rcLine->Value('QtyOrd');
		$qtyExp = $rcLine->Value('QtyExp');
		$ftIsGone = clsHTML::fromBool($rcLine->Value('isGone'));
		$ftCostExp = cCartLine_form::FormatMoney($rcLine->Value('CostExpPer'));
		$strNotes = $rcLine->Value('Notes');

		$out .= <<<__END__
  <tr style="$ftStyle">
    <td>$ftItem</td>
    <td>$ftDescr</td>
    <td>$qtyNeed</td>
    <td>$qtyCust</td>
    <td>$qtyOrd</td>
    <td>$qtyExp</td>
    <td>$ftIsGone</td>
    <td align=right>$ftCostExp</td>
    <td>$strNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out .= 'No items known for this restock request.';
	}
	//$arLink = $oPage->PathArgs(array('page','id'));
	$arLink['do'] = 'enter';
	$arLink['type'] = 'items';
	$oPage = $this->Engine()->App()->Page();
	$out .= ' [<a href="'.$oPage->SelfURL($arLink,TRUE).'">enter items</a>]';
	return $out;
    }

    // -- ADMIN API -- //
}
