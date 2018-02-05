<?php
/*
  PURPOSE: classes for handling individual lines in received restocks
  HISTORY:
    2014-03-09 split off from request.php
*/

class vctaRstkReqItems extends vctlRstkReqItems implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcraRstkReqItem';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_RESTOCK_REQUEST_ITEM;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return 'Nothing written for this yet; should possibly show all items currently expected?';
    }

    // -- EVENTS -- //
    // ++ CLASSES ++ //
    
    protected function ParentTableClass() {
	return KS_LOGIC_CLASS_RESTOCK_REQUESTS;
    }
    protected function ReceivedClass() {
	return KS_LOGIC_CLASS_RESTOCKS_RECEIVED;
    }
    
    // -- CLASSES -- //
    // ++ TABLES ++ //
    
    protected function ParentTable($id=NULL) {
	return $this->Engine()->Make($this->ParentTableClass(),$id);
    }
    protected function CatalogItemTable($id=NULL) {
	return $this->Engine()->Make($this->CatalogItemsClass(),$id);
    }
    
    // -- TABLES -- //
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      RETURNS: SQL object for creating recordset of active restock-request items,
	i.e. non-voided lines in active restock requests
      REPLACES http://htyp.org/VbzCart/queries/qryRstkItms_active
      PUBLIC so the Expected Items query can use it
    */
    public function SQLobj_Items_active() {
	$qryItems = new fcSQL_Table($this->NameSQL(),'rqi');
	  $qryItems->FieldsArray(
	    array(
	      'ID_Parent'	=> NULL,
	      'ID_Item'		=> NULL,
	      )
	    );
	$qryReqs = new fcSQL_Table($this->ParentTable()->NameSQL(),'rq');
	$qryJoin = new fcSQL_Join($qryReqs,$qryItems,array('rqi.ID_Parent'=>'rq.ID'));
	//$qryJoin->GroupArray(array('rqi.ID_Parent','rqi.ID_Item'));
	
	return $qryJoin;
    }
    /*----
      RETURNS: SQL object for creating recordset of expected restock items,
	i.e. requested items minus amounts already received
    *//* moved
    protected function SQLobj_Items_expected() {
	$qryRCL = new fcSQL_Table($this->ReceivedLinesTable()->NameSQL(),'rcl');
	$qryRC = new fcSQL_Table($this->ReceivedTable()->NameSQL(),'rc');
	
	// items from active restock requests
	$qryActv = $this->SQLobj_Items_active();
	$qryActv->Alias('rqi');	// request items
	
	// lines from received restocks
	$qryJ1 = new fcSQL_Join($qryRCL,$qryRC,array('rcl.ID_Parent'=>'rc.ID'));
	
	// [lines from received restocks] JOIN [items from active restock requests]
	$qryJ2 = new fcSQL_Join($qryJ1,$qryActv,array(
	    'rc.ID_Request'=>'rqi.ID_Parent',
	    'rcl.ID_Item'=>'rqi.ID_Item'
	    )
	  );
	$qryJ2->FieldsArray(
	  array(
	    'SUM(rqi.QtyExp)' => 'QtyExp',
	    'SUM(IFNULL(rcl.QtyRecd,rcl.QtyFiled))' => 'QtyRecd'
	    )
	  );
	  
	return $qryJ2;
    } */
    /*----
      REPLACES: http://htyp.org/VbzCart/queries/qryRstkItms_active
    */ /*
    protected function SQLstr_Items_active() {
	$oSQL = $this->SQLobj_Items_active();
	$sql = $oSQL->Render();
	echo __FILE__.' line '.__LINE__.'<br>';
	die("SQL: <pre>$sql</pre>");
	return $sql;
    } */
    
    // -- SQL CALCULATIONS -- //
    // ++ RECORDSETS ++ //

    /*----
      RETURNS: recordset of active restock items
	i.e. non-voided restock items in active restock requests
      REPLACES http://htyp.org/VbzCart/queries/qryRstkItms_active
    */ /* 2015-12-31 created -- but does anyone need it?
    protected function ItemRecords_active() {
	$sql = $this->SQLstr_Items_active();
	
	die("SQL: $sql");
	
	$rs = $this->DataSQL($sql);
	return $rs;
    } */
    /*----
      RETURNS: recordset of items in currently expected restocks
	i.e. items requested but not yet fulfilled or cancelled
      REPLACES: http://htyp.org/VbzCart/queries/qryRstkItms_expected
    */
    /* old version -- call vctaRRQIs_exp::ItemRecords() instead
    protected function ItemRecords_expected() {
This is the old code, too -- I think I was doing it wrong, and it was still in progress
	$sqlMaster = $this->ParentTable()->SQLstr_Expected();	// requests not filled
	$sqlItems = $this->NameSQL();				// requested items
	$sql = <<<__END__
SELECT * FROM $sqlItems AS rqi
  LEFT JOIN (
$sqlMaster
  ) AS em
  ON rqi.ID_Parent=em.ID
__END__;

	echo __FILE__.' line '.__LINE__.'<br>';
	echo "ItemRecords_expected SQL: <pre>$sql</pre>";
	throw new exception('Remind me what calls this.');
	$rs = $this->Engine()->Query($sql);
	return $rs;
    } */
    
    // -- RECORDSETS -- //
    // ++ CALCULATIONS ++ //

    /*-----
      RETURNS: A list of all active item requests
	* ADD items requested from all open restocks
	* SUBTRACT any items received for those restocks
    */
    public function ListExpected() {
	throw new exception('ListExpected() has been renamed to ExpectedArray().');
    }
    /*----
      ACTION: Gets a recordset of restock items we are still expecting to receive,
	and converts it to an array.
      RETURNS: the resulting array, or NULL if no items are currently expected
      HISTORY:
	2016-01-10 This used to execute qryRstkItms_expected_byItem directly, but stored queries
	are being phased out.
    */ /* 2016-01-10 moved to vctaRRQIs_exp::ExpectedItemArray()
    public function ExpectedArray() {
	$rs = $this->ItemRecords_expected();
	//$sql = 'SELECT * FROM qryRstkItms_expected_byItem';
	//$rs = $this->DataSQL($sql);
	if ($rs->hasRows()) {
	    while ($rs->NextRow()) {
		$idItem = (int)$rs->Value('ID_Item');
		$arOut[$idItem]['ord'] = $rs->Value('QtyExp');	// qty on order
		$arOut[$idItem]['rcd'] = $rs->Value('QtyRecd');	// qty received so far
		$arOut[$idItem]['exp'] = $rs->QtyExp - $rs->QtyRecd;	// qty still expected
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }

    // -- CALCULATIONS -- //
    // ++ ADMIN UI ++ //
    
    /*----
      ACTION: Renders admin table for the items found by the filter ($sqlFilt)
      PUBLIC so it can be called by Request object
    */
    public function AdminItems($sqlFilt) {
	$out = '';
	$rsLine = $this->SelectRecords($sqlFilt);
	$out = $rsLine->AdminRows();
	/*
	if ($rsLine->hasRows()) {
	    $out .= <<<__END__
<table class=listing>
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
		  '['.$rcLine->SelfLink('edit').'] '
		  .$rcItem->SelfLink($rcItem->CatNum());
		$ftDescr = $rcLine->Value('Descr');
		$qtyNeed = $rcLine->Value('QtyNeed');
		$qtyCust = $rcLine->Value('QtyCust');
		$qtyOrd = $rcLine->Value('QtyOrd');
		$qtyExp = $rcLine->Value('QtyExp');
		$ftIsGone = fcHTML::fromBool($rcLine->Value('isGone'));
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
	    $out .= "No items found for $sqlFilt.";
	} */
	return $out;
    }

    // -- ADMIN UI -- //
}
class vcraRstkReqItem extends clsRstkReqItem implements fiLinkableRecord, fiEditableRecord, fiEventAware {
    use ftLinkableRecord;
    use ftSaveableRecord;
    use ftFrameworkAccess;
    use vtRestockLines;

    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    } */

    // -- EVENTS -- //
    // ++ CLASSES ++ //
    
    protected function RequestsClass() {
	return KS_ADMIN_CLASS_RESTOCK_REQUESTS;
    }
    
    // -- CLASSES -- //
    // ++ FIELD LOOKUP ++ //
    
    protected function ParentAdminLink() {
	if ($this->IsNew()) {
	    return 'n/a';
	} else {
	    $rc = $this->ParentRecord();
	    return $rc->SelfLink_name();
	}
    }
    protected function ItemAdminLink() {
	if ($this->IsNew()) {
	    return 'n/a';
	} else {
	    $rc = $this->LCItemRecord();
	    return $rc->SelfLink_name();
	}
    }
    
    // -- FIELD LOOKUP -- //
    // ++ ACTIONS ++ //

    public function AddItem() {
	die('This method not written yet!'); // is it needed now?
    }

    // -- ACTIONS -- //
    // ++ ADMIN UI ++ //

    //+page+//
    
    protected function AdminPage() {
	$oFormIn = fcHTTP::Request();

	$frm = $this->PageForm();
	$doSave = $oFormIn->GetBool('btnSave');
	if ($doSave) {
	    $out .= $frm->Save();
	    $sMsgs = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsgs);
	}
	
	$oMenu = fcApp::Me()->GetHeaderMenu();
	  $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,'cancel','edit request-item record'));
 
	    $doEdit = $ol->GetIsSelected();
 
 /*
	//clsActionLink_option::UseRelativeURL_default(TRUE);
	$arActs = array(
	  new clsActionLink_option(array(),'edit'),
	  );
	$oPage = $this->Engine()->App()->Page();

	$out = $oPage->ActionHeader('Record',$arActs);
	$doEdit = $oPage->PathArg('edit');
*/
	
	// render values/form
	$oTplt = $this->PageTemplate();
	if ($this->IsNew()) {
	    $frm->ClearValues();
	    $doEdit = TRUE;
	} else {
	    $frm->LoadRecord();
	}
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['!ID'] = $this->SelfLink();
	$arCtrls['ID_Parent'] = $this->ParentAdminLink();
	if (!$doEdit || !array_key_exists('ID_Item',$arCtrls)) {
	    $arCtrls['ID_Item'] = $this->ItemAdminLink();
	}

	$out = NULL;
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	  // use if needed
	    //$ctIsGone = fcHTML::fromBool($vIsGone);
	    //$ctCostExp = fcMoney::Format_withSymbol($this->Value('CostExpPer'));
	}

	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .=
	      "\n<input type=submit name=btnSave value='Save'>"
	      ."\n</form>";
	}
	return $out;
    }
    private $frmRow;
    protected function PageForm() {
	if (empty($this->frmRow)) {
	
	    $frm = new fcForm_DB($this);

	      if ($this->UserRecord()->CanDo(KS_PERM_RAW_DATA_EDIT)) {
		  // allow editing Item ID
		  $oField = new fcFormField_Num($frm,'ID_Item');
	      }

	      $oField = new fcFormField_Text($frm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'40'));
	      $oField = new fcFormField_Time($frm,'WhenCreated');
	      $oField = new fcFormField_Time($frm,'WhenVoided');
	      $oField = new fcFormField_Num($frm,'QtyNeed');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'4'));
	      $oField = new fcFormField_Num($frm,'QtyCust');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'4'));
	      $oField = new fcFormField_Num($frm,'QtyOrd');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'4'));
	      $oField = new fcFormField_Num($frm,'QtyExp');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'4'));
	      $oField = new fcFormField_Bit($frm,'isGone');
	      $oField = new fcFormField_Num($frm,'CostExpPer');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'6'));
	      $oField = new fcFormField_Text($frm,'Notes');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>'40'));
	      
	    $this->frmRow = $frm;
	
	/* forms version 1
	    // create fields & controls
	    $frmRow = new clsForm_recs($this);

	    // these are the key fields, so let's not make them editable
	    //$objForm->AddField(new clsFieldNum('ID_Request'),	new clsCtrlHTML(array('size'=>4)));
	    //$objForm->AddField(new clsFieldNum('ID_Item'),	new clsCtrlHTML(array('size'=>4)));
	    $frmRow->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>20)));
	    $frmRow->AddField(new clsField('WhenCreated'),	new clsCtrlHTML(array('size'=>14)));
	    $frmRow->AddField(new clsField('WhenVoided'),	new clsCtrlHTML(array('size'=>14)));
	    $frmRow->AddField(new clsFieldNum('QtyNeed'),	new clsCtrlHTML(array('size'=>4)));
	    $frmRow->AddField(new clsFieldNum('QtyCust'),	new clsCtrlHTML(array('size'=>4)));
	    $frmRow->AddField(new clsFieldNum('QtyOrd'),	new clsCtrlHTML(array('size'=>4)));
	    $frmRow->AddField(new clsFieldNum('QtyExp'),	new clsCtrlHTML(array('size'=>4)));
	    $frmRow->AddField(new clsFieldBool('isGone'),	new clsCtrlHTML_CheckBox());
	    $frmRow->AddField(new clsFieldNum('CostExpPer'),	new clsCtrlHTML(array('size'=>6)));
	    $frmRow->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea());

	    $this->frmRow = $frmRow; //*/
	}
	return $this->frmRow;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<div class="form-block">
<table class=listing>
<table>
  <tr><td align=right><b>ID</b>:</td><td>[[!ID]]</td></tr>
  <tr><td align=right><b>Request</b>:</td><td>[[ID_Parent]]</td></tr>
  <tr><td align=right><b>Item</b>:</td><td>[[ID_Item]]</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>[[Descr]]</td></tr>
  <tr><td align=right><b>Created</b>:</td><td>[[WhenCreated]]</td></tr>
  <tr><td align=right><b>Voided</b>:</td><td>[[WhenVoided]]</td></tr>
  <tr><td align=right><b>Qty Needed</b>:</td><td>[[QtyNeed]]</td></tr>
  <tr><td align=right><b>Qty for Cust</b>:</td><td>[[QtyCust]]</td></tr>
  <tr><td align=right><b>Qty Ordered</b>:</td><td>[[QtyOrd]]</td></tr>
  <tr><td align=right><b>Qty Expected</b>:</td><td>[[QtyExp]]</td></tr>
  <tr><td align=right><b>Is Gone</b>:</td><td>[[isGone]]</td></tr>
  <tr><td align=right><b>Cost Per</b>:</td><td>[[CostExpPer]]</td></tr>
  <tr><td colspan=2><b>Notes</b>:<br>[[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage; 
    }
    
    //-page-//
    //+rows+//

    protected function AdminRows_forItem_NoDataText() {
	return '<div class=content>No restock requests found for this item.</div>';
    }
    protected function AdminRows_forItem_Header() {
	// links to documentation
	$ftNeed = '<a href="'
	  .KWP_HELP_TERMS.'restock/request/need" title="quantity we needed to restock">need</a>';
	$ftCust = '<a href="'
	  .KWP_HELP_TERMS.'restock/request/cust" title="quantity spoken for by customers">cust</a>';
	$ftOrd = '<a href="'
	  .KWP_HELP_TERMS.'restock/request/ord" title="quantity requested from supplier">ord</a>';
	$ftExp = '<a href="'
	  .KWP_HELP_TERMS.'restock/request/exp" title="quantity we expect to receive">exp</a>';

	return <<<__END__
<table class=listing>
  <tr>
    <th colspan=8>Request</th>
    <th colspan=6>Line Item</th>
  </tr>
  <tr>
    <th colspan=3></th>
    <th colspan=5>-- When --</th>
    <th colspan=1></th>
    <th colspan=4>Quantities</th>
  </tr>
  <tr>
    <th title="Request ID">ID</th>
    <th>our PO #</th>
    <th>their PO #</th>
    <th>Created</th>
    <th>Ord</th>
    <th>Kld</th>
    <th>Shut</th>
    <th>Orph</th>
  
    <th title="Line Item ID">ID</th>
    <th title="quantity needed">$ftNeed</th>
    <th title="quantity needed for customer orders">$ftCust</th>
    <th title="quantity ordered from supplier">$ftOrd</th>
    <th title="quantity we expect to receive from supplier">$ftExp</th>
    <th>Notes</th>
  </tr>
__END__;
    }
    protected function AdminRow_forItem(array $arRow) {
	$rcReq = $this->ParentTable()->SpawnRecordset();
	
	$arItem = $arRow['line'];
	$arReq = $arRow['main'];
	$this->SetFieldValues($arItem);
	$rcReq->SetFieldValues($arReq);

	// Request fields

	//if ($rcReq->HasRow()) {
	    $ftReqID = $rcReq->SelfLink();
	    $txtOurPO = $rcReq->GetFieldValue('PurchOrdNum');
	    $txtTheirPO = $rcReq->GetFieldValue('SuppOrdNum');
	    
	    $txtWhenCre = $rcReq->GetFieldValue('WhenCreated');
	    $dtCre = strtotime($txtWhenCre);
	    $yrCre = date('Y',$dtCre);

	    $txtWhenOrd = $rcReq->GetFieldValue('WhenOrdered');
	    $ftWhenOrd = fcDate::DefaultYear($txtWhenOrd,$yrCre);

	    $txtWhenKld = $rcReq->GetFieldValue('WhenKilled');
	    $ftWhenKld = fcDate::DefaultYear($txtWhenKld,$yrCre);

	    $txtWhenClo = $rcReq->GetFieldValue('WhenClosed');
	    $ftWhenClo = fcDate::DefaultYear($txtWhenClo,$yrCre);

	    $txtWhenOrph = $rcReq->GetFieldValue('WhenOrphaned');
	    $ftWhenOrph = fcDate::DefaultYear($txtWhenOrph,$yrCre);
	//}
	
	// Line-item fields
	
	$ftLineID = $this->SelfLink();
	$qtyNeed = $this->GetFieldValue('QtyNeed');
	$qtyCust = $this->GetFieldValue('QtyCust');
	$qtyOrd = $this->GetFieldValue('QtyOrd');
	$qtyExp = $this->GetFieldValue('QtyExp');

	$txtNotes = $this->GetFieldValue('Notes');

	return <<<__END__
    <td>$ftReqID</td>
    <td>$txtOurPO</td>
    <td>$txtTheirPO</td>
    <td>$txtWhenCre</td>
    <td>$ftWhenOrd</td>
    <td>$ftWhenKld</td>
    <td>$ftWhenClo</td>
    <td>$ftWhenOrph</td>
    
    <td>$ftLineID</td>
    <td align=center>$qtyNeed</td>
    <td align=center>$qtyCust</td>
    <td align=center>$qtyOrd</td>
    <td align=center>$qtyExp</td>
    <td>$txtNotes</td>
__END__;
    }
    /*----
      HISTORY:
	2016-01-07 renamed from AdminList() to AdminRows()
	  ...but I still can't figure out what it's for, so abandoning for now.
	  It iterates through a list of items, but shows mostly information from the Request.
	2016-01-09 renamed from AdminRows_weird() to AdminRows_forLCItem(), because that is
	  what it's for: restock information for a given local catalog item
	2017-03-21 This was renamed as *_HIDE some time ago, not sure when. May or may not still be in use.
    */
    public function AdminRows_forLCItem_HIDE($iNoneTxt='No restock requests found') {

	if ($this->hasRows()) {
	    $out = $this->AdminRows_forItem_Header();
	    $arSort = array();
	    while ($this->NextRow()) {
		$this->AdminRows_forItem_Collate($arSort);
	    }
	    // sort the master array
	    arsort($arSort);
	    
	    // iterate through the master array to pull out display data
	    $rcLine = $this;
//	    $rcReq = $this->ParentTable()->SpawnItem();
	    $isOdd = TRUE;
	    foreach ($arSort as $key=>$data) {
	    
		$sRow = $this->AdminRow_forItem($data);
		
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;
		
		$out .= <<<__END__
  <tr class="$cssClass">
$sRow
  </tr>
__END__;

		/*
		$arItem = $data['line'];
		$arReq = $data['req'];
		$rcLine->Values($arItem);
		$rcReq->Values($arReq);
		
		// Request fields

		$ftReqID = $rcReq->SelfLink();
		$txtOurPO = $rcReq->Value('PurchOrdNum');
		$txtTheirPO = $rcReq->Value('SuppOrdNum');
		
		$txtWhenCre = $rcReq->Value('WhenCreated');
		$dtCre = strtotime($txtWhenCre);
		$yrCre = date('Y',$dtCre);

		$txtWhenOrd = $rcReq->Value('WhenOrdered');
		$ftWhenOrd = clsDate::DefaultYear($txtWhenOrd,$yrCre);

		$txtWhenKld = $rcReq->Value('WhenKilled');
		$ftWhenKld = clsDate::DefaultYear($txtWhenKld,$yrCre);

		$txtWhenClo = $rcReq->Value('WhenClosed');
		$ftWhenClo = clsDate::DefaultYear($txtWhenClo,$yrCre);

		$txtWhenOrph = $rcReq->Value('WhenOrphaned');
		$ftWhenOrph = clsDate::DefaultYear($txtWhenOrph,$yrCre);
		
		// Line-item fields
		
		$ftLineID = $rcLine->SelfLink();
		$qtyNeed = $rcLine->Value('QtyNeed');
		$qtyCust = $rcLine->Value('QtyCust');
		$qtyOrd = $rcLine->Value('QtyOrd');
		$qtyExp = $rcLine->Value('QtyExp');


		$txtNotes = $rcLine->Value('Notes');

		$out .= <<<__END__
  <tr class="$cssClass">
    <td>$ftReqID</td>
    <td>$txtOurPO</td>
    <td>$txtTheirPO</td>
    <td>$txtWhenCre</td>
    <td>$ftWhenOrd</td>
    <td>$ftWhenKld</td>
    <td>$ftWhenClo</td>
    <td>$ftWhenOrph</td>
    
    <td>$ftLineID</td>
    <td align=center>$qtyNeed</td>
    <td align=center>$qtyCust</td>
    <td align=center>$qtyOrd</td>
    <td align=center>$qtyExp</td>
    <td>$txtNotes</td>
  </tr>
__END__;
//*/
	    }
	    $out .= "\n</table>";
	} else {
	    $out = $iNoneTxt;
	}
	return $out;
    }
    
    //-rows-//

    // -- ADMIN UI -- //
}
