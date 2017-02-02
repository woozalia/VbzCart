<?php
/*
  FILE: restock requests - admin interface
  HISTORY:
    2010-10-17 Extracted restock classes from SpecialVbzAdmin.php
    2013-12-15 Adapting for dropin-module system.
    2015-12-31 Split request.php into request.logic.php and request.admin.php
*/

class VCT_RstkReqs extends vctRstkReqs {
    use ftLinkableTable;
    use vtRestockTable_admin;

    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return 'VCR_RstkReq';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_RESTOCK_REQUEST;
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminRows();
    }

    // -- DROP-IN API -- //
    // ++ TRAIT HELPERS ++ //

    // RETURNS: recordset that includes all fields needed for admin functions
    protected function AdminRecords($sqlWhere,$sqlOrder) {
	if ($this->PageOption_ShowOnlyActive()) {
	    $rs = $this->RowsActive($sqlWhere,$sqlOrder);
	} else {
	    $sqlWhereClause = empty($sqlWhere)?NULL:" WHERE $sqlWhere";
	    $sqlOrderClause = empty($sqlOrder)?NULL:" ORDER BY $sqlOrder";
	    $rs = $this->DataSet($sqlWhereClause.$sqlOrderClause);
	}
	return $rs;
    }
    
    // -- TRAIT HELPERS -- //
    // ++ CLASS NAMES ++ //
    
    protected function ReceivedClass() {
	return KS_ADMIN_CLASS_RESTOCKS_RECEIVED;
    }
    
    // -- CLASS NAMES -- //
    // ++ RECORDS ++ //
    
    /*----
      PUBLIC so Received Restocks can use it to show available restocks
    */
    public function GetData_forDropDown() {
	return $this->RowsActive();
    }
    
    // -- RECORDS -- //
    // ++ URL OPTIONS ++ //
    
    protected function PageOption_ShowOnlyActive() {
	$s = $this->PageObject()->PathArg('page');
	return ($s == KS_ACTION_RESTOCK_EXPECTED);
    }
    
}

class VCR_RstkReq extends vcrRstkReq {
    use ftFrameworkAccess;
    use ftLinkableRecord;
    use ftLoggableRecord;
    use vtLoggableAdminObject;
    use vtRestockRecords_admin;

    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name($arArgs=NULL) {
	$sName = $this->Name();
	$sDescr = $this->Descr();
	return $this->SelfLink($sName,$sDescr,$arArgs);
    }

    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    // CALLBACK for dropdown box display
    public function ListItem_Text() {
	return $this->SummaryLine_short();
    }
    // CALLBACK for AdminRows_forItem()
    public function SortingKey() {
	$dtCreated = $this->Value('WhenCreated');
	return is_null($dtCreated)?($this->Value('WhenOrdered')):$dtCreated;
    }

    // -- CALLBACKS -- //
    // ++ CLASS NAMES ++ //

    protected function ReceivedsClass($id=NULL) {
	return KS_ADMIN_CLASS_RESTOCKS_RECEIVED;
    }
    protected function RequestItemsClass() {
	return KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS;
    }
    
    // -- CLASS NAMES -- //
    // ++ ARRAY CALCULATIONS ++ //
    
    // PUBLIC so Table object can use it
    public function Array_forFilterMenu() {
	$yrLast = NULL;
	$arSupp = array();
	
	while ($this->NextRow()) {
	    $rcSupp = $this->SupplierRecord();
	    $sSuppKey = $rcSupp->CatKey();
	
	    // build array for Suppliers menubar
	    if (!array_key_exists($sSuppKey,$arSupp)) {
		$arSupp[$sSuppKey]['vals'] = $rcSupp->Values();
		$arSupp[$sSuppKey]['text'] = 1;
	    } else {
		$arSupp[$sSuppKey]['text']++;
	    }
	    
	    // gather data for Years menubar
	    $sSortDate	= $this->Date_forSorting();
	    if (is_null($sSortDate)) {
		$yrSort = NULL;
	    } else {
		$dtSort	= strtotime($sSortDate);
		$yrSort	= date('Y',$dtSort);
	    }
	    if ($yrLast != $yrSort) {
		$yrLast = $yrSort;
		$arYear[$yrSort] = 1;
	    } else {
		$arYear[$yrSort]++;
	    }
	}
	$this->RewindRows();
	$ar = array('supp'=>$arSupp,'date'=>$arYear);
	return $ar;
    }

    // -- ARRAY CALCULATIONS -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function SupplierLink() {
	if ($this->HasSupplier()) {
	    return $this->SupplierRecord()->SelfLink_name();
	} else {
	    return '<i>(not set)</i>';
	}
    }
    protected function WarehouseLink() {
	$rcWhse = $this->WarehouseRecord();
	if (is_null($rcWhse)) {
	    return '<i>(not set)</i>';
	} else {
	    return $rcWhse->SelfLink_name();
	}
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ADMIN WEB UI ++ //
    
    //+multiple+//
    
    public function AdminRows() {
	$oPage = $this->PageObject();

	/* 2017-01-17 old
	$arBase = array(
	  'supp' => FALSE,
	  'year' => FALSE
	  );
    
	$arMenu = array(
	  new clsActionLink_option(
	    $arBase,	// other stuff to always appear in URL, regardless of section's menu state
	    KS_NEW_REC,	// LinkKey: value that the group should be set to when this link is activated
	    'id',	// GroupKey: group's identifier string in URL (.../iGroupKey:iLinkKey/...)
			  // if NULL, presence of link key (.../iLinkKey/...) is a flag
	    NULL,	// DispOff: text to display when link is not activated - defaults to LinkKey
	    NULL,	// DispOn: text to display when link is activated - defaults to DispOff
	    'enter a new request from scratch'	// description -- appears in hover-over popup
	    ),
	  );
	$oPage->PageHeaderWidgets($arMenu);
	*/
	
	$oMenu = new fcHeaderMenu();	// for putting menu in a section header
	  // $sLinkKey,$sGroupKey=NULL,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL
	  $oMenu->SetNode($ol = new fcMenuOptionLink('edit','do',NULL,'cancel','edit this supplier'));	// TO BE REVISED
	    $ol->SetBasePath($this->SelfURL());
    
	$out = NULL;
	if ($this->hasRows()) {
	    $idSuppCurr = NULL;	// temporary
	    $out .= <<<__END__
<table class=listing>
  <tr>
    <td></td>
    <th colspan=2>When</th>
    <th colspan=2>Delivery</th>
    <td></td>
    <th colspan=2>Purchase Order #</th>
    <th rowspan=2>Supplier's<br>Order #</th>
  </tr>
  <tr>
    <th>ID</th>
    <th title="when created">cre</th>
    <th title="when ordered">ord</th>
    <th>sent to</th>
    <th>via</th>
    <th title="supplier">S</th>
    <th title="our purchase order #">ours</th>
    <th title="supplier's purchase order #">theirs</th>
    <th title="estimated order charge">$ Est</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    $yrLast = 0;
	    while ($this->NextRow()) {
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$row = $this->GetFieldValues();
		$ftID = $this->SelfLink();

		$sSortDate = $this->Date_forSorting();
		if (is_null($sSortDate)) {
		    $yrSort = NULL;
		    $yrShow = 'no date';
		    // these should both be NULL, but just in case of a coding error...
		    $ftWhenCre = $this->WhenCreated();
		    $ftWhenOrd = $this->WhenOrdered();
		} else {
		    $dtSort = strtotime($sSortDate);
		    $yrSort	= date('Y',$dtSort);
		    $yrShow = $yrSort;
		    $ftWhenCre = fcDate::DefaultYear($this->WhenCreated(),$yrSort);
		    $ftWhenOrd = fcDate::DefaultYear($this->WhenOrdered(),$yrSort);
		}
		if ($yrLast != $yrSort) {
		    $yrLast = $yrSort;
		    $out .= '<tr><td colspan=5 class="table-section-header">'.$yrShow.'</td></tr>';
		}

		$ftDest = $this->WarehouseLink();
		$ftShipVia = $row['CarrierDescr'];
		$ftOurPO = $row['PurchOrdNum'];
		$ftSupp = $this->SupplierCatKey();
		$ftSuppPO = $row['SuppPONum'];
		$ftSuppOrd = $row['SuppOrdNum'];
		$ftCostEst = $row['TotalCalcMerch'];
		$ftNotes = $row['Notes'];

	      
		$out .= <<<__END__
  <tr class="$cssClass">
    <td>$ftID</td>
    <td>$ftWhenCre</td>
    <td>$ftWhenOrd</td>
    <td>$ftDest</td>
    <td>$ftShipVia</td>
    <td>$ftSupp</td>
    <td>$ftOurPO</td>
    <td>$ftSuppPO</td>
    <td>$ftSuppOrd</td>
    <td align=right>$ftCostEst</td>
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
    
    //-multiple-//
    //+single+//
    
    public function AdminPage() {
	$oPage = $this->PageObject();
	
	$isNew = $this->IsNew();
	
	$frm = $this->PageForm();
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$arActs = array(
	  new clsActionLink_option(array(),'edit'),
	  new clsActionLink_option(array(),	// show controls for entering things
	    'enter',	// link key
	    'do',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'enter things'	// popup description
	    ),

	  );
	$oPage->PageHeaderWidgets($arActs);
	if ($this->IsNew()) {
	    $sTitle = 'Enter new Restock Request';
	} else {
	    $sTitle = 'Restock Request '.$this->OurPurchaseOrderNumber().' (ID '.$this->GetKeyValue().')';
	}
	$oPage->TitleString($sTitle);
	
	$out = NULL;

	$strAction = $oPage->PathArg('do');
	$doEdit = $oPage->PathArg('edit');
	$doForm = $doEdit || $isNew;
	$doSave = $oPage->ReqArgBool('btnSave');
	if ($doSave) {
	    $this->PageForm()->Save();
	    $this->SelfRedirect();
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

	    $htForm = NULL;
	    if ($doEnter) {
		switch ($strType) {
		  case 'items':
		    $htEnter = $this->RenderEnterItemsForm();
		    break;
/*
		  case 'rcd':
		    $out .= $this->EnterRecd();
		    break;
*/
		}
		$out .= "\n<table align=right class=listing><tr><td>$htEnter</td></tr></table>";
	    }

	    /* 2016-01-18 I... don't get what $doStamp was for.
	    
	    if ($doStamp) {
		// we do stamps in a box
		$out .= '<table align=right width=30%><tr><td><h3>'.$strDescr.'</h3>';
	    } */
	    
	    if ($doAction) {
	    
		/*
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
		} */
		if ($doEnter) {
		    // 2016-01-18 This should probably be rewritten using the text parser thingy.
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
		    $out .= 
		      '<form method=post action="'.$urlForm.'">'
		      .'Log notes:<br>'
		      .'<textarea rows=3 cols=30 name=notes>'
			.fcString::EncodeForHTML($txtNotes)
		      .'</textarea>'
		      .$htXtra
		      .'<input type=hidden name=type value="'.$strType.'">'
		      .'<br><input type=submit name=btnStamp value="Stamp It">'
		      .'</form>'
		      ;
		}
	    }
	    /*
	    if ($doStamp) {
		$out .= '</td></tr></table>';
	    } */
	    //$out .= '<hr>';
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doForm);
	$arCtrls['ID'] = $this->SelfLink();

	if ($doForm) {
	    $out .= "\n<form method=post>";
	} else {
	    // TIMESTAMPS can be set to NOW when not editing, if record is active
	    $arLink = array(
	      'do'	=> 'mark');
	    $isActive = $this->IsActive();
	    
	    $fMarkLink = function($sField,$sText,$sPopup) use ($isActive,&$arLink,&$arCtrls) {
		$sVal = $this->Value($sField);
		if ($isActive && is_null($sVal)) {
		    $arLink['type'] = $sText;
		    $url = $this->SelfURL($arLink);
		    $htLink = clsHTML::BuildLink($url,'stamp',$sPopup);
		    $arCtrls[$sField] = ifEmpty($sVal,'['.$htLink.']');
		}
	    };
	    
	    $fMarkLink('WhenOrdered','order','mark as ordered');
	    $fMarkLink('WhenConfirmed','confirm','mark order as confirmed');
	    $fMarkLink('WhenKilled','kill','kill this order');
	    $fMarkLink('WhenConfirmed','confirm','mark order as confirmed');
	    $fMarkLink('WhenClosed','close','mark order as closed');
	    $fMarkLink('WhenOrphaned','orphan','mark order as orphaned');

	    /*
	    // if Request has not been placed with Supplier yet, provide a handy action link
	    $sVal = $this->WhenOrdered();
	    if ($isActive && is_null($sVal)) {
		$arLink['type'] = 'order';
		$url = $this->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'stamp','mark as ordered');
		$arCtrls['WhenOrdered'] = ifEmpty($sVal,'['.$htLink.']');
	    }
	    
	    $sVal = $this->WhenConfirmed();
	    if ($isActive && is_null($sVal)) {
		$arLink['type'] = 'confirm';
		$url = $this->SelfURL($arLink);
		$htLink = clsHTML::BuildLink($url,'stamp','mark order as confirmed');
		$arCtrls['WhenConfirmed'] = ifEmpty($sVal,'['.$htLink.']');
	    }*/
	    
	    // TODO: other timestamps
	}
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doForm) {
	    $out .=
		"\n<input type=submit name=btnSave value='Save'>"
		."\n</form>"
		;
	} else {
	}
	
	if (!$isNew) {
	    $out .=
	      //$oPage->ActionHeader('Shipments Received')
	      $this->AdminReceived($this->GetKeyValue())
	      .$oPage->ActionHeader('Items in Request')
	      .$this->AdminItems()
	      .$oPage->ActionHeader('Event Log')
	      .$this->EventListing()
	      ;
	}
	
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<div class="form-block">
<table class=listing><tr><td>
<ul>
<li> <b>Our PO#</b>: [[PurchOrdNum]]	</li>
<li> <b>Supp PO#</b>: [[SuppPONum]]	</li>
<li> <b>Supp Ord#</b>: [[SuppOrdNum]]	</li>

<li> <b>from Supplier</b>:	[[ID_Supplier]]	</li>
<li> <b>to Warehouse</b>:	[[ID_Warehouse]]	</li>
<li> <b>Carrier</b>:		[[CarrierDescr]]	</li>
<li> <b>Total Calc Merch</b>:	[[TotalCalcMerch]]	</li>
<li> <b>Total Est Final</b>:	[[TotalEstFinal]]	</li>
<li> <b>Paid with</b>: 		[[PayMethod]]	</li>
<li> <b>Locked?</b>:		[[isLocked]]	</li>

<li> <b>Timestamps</b>:
<table>
  <tr><td align=right><b>Created</b>:</td><td>	[[WhenCreated]]</td></tr>
  <tr><td align=right><b>Ordered</b>:</td><td>	[[WhenOrdered]]</td></tr>
  <tr><td align=right><b>Confirmed</b>:</td><td>	[[WhenConfirmed]]</td></tr>
  <tr><td align=right><b>Killed</b>:</td><td>	[[WhenKilled]]</td></tr>
  <tr><td align=right><b>Closed</b>:</td><td>	[[WhenClosed]]</td></tr>
  <tr><td align=right><b>Orphaned</b>:</td><td>	[[WhenOrphaned]]</td></tr>
  <tr><td align=right><b>First ETA</b>:</td><td>[[WhenExpectedOrig]]</td></tr>
  <tr><td align=right><b>Latest ETA</b>:</td><td>[[WhenExpectedFinal]]</td></tr>
</table>
<li> <b>Notes</b>: [[Notes]]
</ul>
</td></tr></table>
</div>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage; 
    }

    /*-----
      ACTION: Build the editing form
    */
    private $frmPage;
    private function PageForm() {
	// create fields & controls
	if (empty($this->frmPage)) {
	
	    $frm = new fcForm_DB($this);
	    
		$oField = new fcFormField_Num($frm,'ID_Supplier');
		    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		    $oCtrl->Records($this->SupplierRecords_all());
		    
		$oField = new fcFormField_Num($frm,'ID_Warehouse');
		    $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		    $oCtrl->Records($this->WarehouseRecords_all());
		    
		$oField = new fcFormField_Text($frm,'PurchOrdNum');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'10'));
		$oField = new fcFormField_Text($frm,'SuppOrdNum');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'20'));
		$oField = new fcFormField_Text($frm,'SuppPONum');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'20'));
		$oField = new fcFormField_Text($frm,'CarrierDescr');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'20'));
		$oField = new fcFormField_Num($frm,'TotalCalcMerch');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'6'));
		$oField = new fcFormField_Num($frm,'TotalEstFinal');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'6'));
		$oField = new fcFormField_Text($frm,'PayMethod');
		    $oCtrl = new fcFormControl_HTML_Text($oField,array('size'=>'20'));
		$oField = new fcFormField_Time($frm,'WhenCreated');
		$oField = new fcFormField_Time($frm,'WhenOrdered');
		$oField = new fcFormField_Time($frm,'WhenConfirmed');
		$oField = new fcFormField_Time($frm,'WhenKilled');
		$oField = new fcFormField_Time($frm,'WhenClosed');
		$oField = new fcFormField_Time($frm,'WhenOrphaned');
		$oField = new fcFormField_Time($frm,'WhenExpectedOrig');
		$oField = new fcFormField_Time($frm,'WhenExpectedFinal');
		$oField = new fcFormField_Num($frm,'isLocked');
		    $oCtrl = new fcFormControl_HTML_CheckBox($oField,array());
		$oField = new fcFormField_Text($frm,'Notes');
		    $oCtrl = new fcFormControl_HTML_TextArea($oField,array('height'=>3,'width'=>30));
	
	    $this->frmPage = $frm;
	}
	return $this->frmPage;
    }
    
    //-single-//
    //+contents+//
    
    /*----
      ACTION: Renders table of items in restock request, with administrative controls
      HISTORY:
	2016-01-07 The display function this calls needs to be rewritten or replaced.
    */
    protected function AdminItems_maybe() {
	$out = $this->ContentsTable()->AdminItems('ID_Parent='.$this->GetKeyValue());
	
	// link to enter (more) items (should this be here?);
	$arLink['do'] = 'enter';
	$arLink['type'] = 'items';
	$oPage = $this->Engine()->App()->Page();
	$out .= ' [<a href="'.$oPage->SelfURL($arLink,TRUE).'">enter items</a>]';
	return $out;
    }
    /*----
      FUTURE: Move this into the Request Items admin class; there is some duplicated functionality.
      HISTORY:
	2016-01-07 moved some stuff here from AdminRows_weird()
	2016-01-15 renamed back to AdminItems() -- this is apparently not a duplicate
	  of vcraRstkReqItem::AdminRows_forLCItem(), although the help links are the same.
    */
    protected function AdminItems() {
	$out = '';
	$rsLine = $this->RequestItemTable()->GetData('ID_Parent='.$this->GetKeyValue());
	if ($rsLine->hasRows()) {
	
	    // links to help pages
	    $ftNeed = '<a href="'
	      .KWP_HELP_TERMS.'restock/request/need" title="quantity we needed to restock">need</a>';
	    $ftCust = '<a href="'
	      .KWP_HELP_TERMS.'restock/request/cust" title="quantity spoken for by customers">cust</a>';
	    $ftOrd = '<a href="'
	      .KWP_HELP_TERMS.'restock/request/ord" title="quantity requested from supplier">ord</a>';
	    $ftExp = '<a href="'
	      .KWP_HELP_TERMS.'restock/request/exp" title="quantity we expect to receive">exp</a>';
	    /*
	    $ftNeed = '<a href="'.KWP_HELP_TERMS.'rstk/need">need</a>';
	    $ftCust = '<a href="'.KWP_HELP_TERMS.'rstk/cust">cust</a>';
	    $ftOrd = '<a href="'.KWP_HELP_TERMS.'rstk/ord">ord</a>';
	    $ftExp = '<a href="'.KWP_HELP_TERMS.'rstk/exp">exp</a>';
	    */
	
	    $out .= <<<__END__
<table class=listing>
  <tr>
    <td colspan=3></td>
    <td colspan=5 align=center bgcolor=#eeeeee>Quantities</td>
  </tr>
  <tr>
    <th>ID</th>
    <th>Item</th>
    <th>Description</th>
    <th>$ftNeed</th>
    <th>$ftCust</th>
    <th>$ftOrd</th>
    <th>$ftExp</th>
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

		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$ftID = $rcLine->SelfLink();
		$ftItem = $rcItem->SelfLink($rcItem->CatNum());
		$ftDescr = $rcLine->Value('Descr');
		$qtyNeed = $rcLine->Value('QtyNeed');
		$qtyCust = $rcLine->Value('QtyCust');
		$qtyOrd = $rcLine->Value('QtyOrd');
		$qtyExp = $rcLine->Value('QtyExp');
		$ftIsGone = clsHTML::fromBool($rcLine->Value('isGone'));
		$ftCostExp = vcCartLine_form::FormatMoney($rcLine->Value('CostExpPer'));
		$strNotes = $rcLine->Value('Notes');

		$out .= <<<__END__
  <tr class="$cssClass">
    <td>$ftID</td>
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
    protected function RenderEnterItemsForm() {
	$out = <<<__END__
Enter restock items:
<form method=post>
  <textarea name=req-text rows=30></textarea>
  <br><input type=submit name=btnParse value="Parse">
</form>
__END__;
	return $out;
    }

    //-contents-//
    //+dependent+//
    
    protected function AdminReceived() {
	$id = $this->GetKeyValue();
	$rc = $this->ReceivedRecords();
	$rc->RequestID($id);	// so it can correctly render "new" link
	$out = $rc->AdminRows();

	if (is_null($out)) {
	    $out = "\nNo restock shipments received.";
	}

	// include action link for creating new received restock
	$rcNew = $rc->Table()->SpawnItem();
	//$arArgs = array('req'=>$id);
	//$out .= '[ '.$rcNew->SelfLink('add','add a received restock shipment',$arArgs).' ]';

	return $out;
    }
    
    //-dependent-//

    // -- ADMIN WEB UI -- //

}
