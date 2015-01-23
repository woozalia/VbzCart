<?php
/*
  PURPOSE: classes for handling individual lines in received restocks
  HISTORY:
    2014-03-09 split off from request.php
*/
class clsRstkReqItems extends clsTable_indexed {

    // ++ SETUP ++ //

   public function __construct($iDB) {
	$objIdxr = new clsIndexer_Table_multi_key($this);
	parent::__construct($iDB,$objIdxr);
	  $this->ClassSng('clsRstkReqItem');
	  $this->Name('rstk_req_item');
	  $objIdxr->KeyNames(array('ID_Restock','ID_Item'));
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function CatItemsClass() {
	if (clsDropInManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItems';
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    /*
    protected function CatItemTable($id=NULL) {
	return $this->Engine()->Make($this->CatItemsClass(),$id);
    }*/

    // -- DATA TABLE ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Add the given item to the restock request, with the given parameters
      OUTPUT FIELDS:
	QtyNeed: this is calculated by the called'
	QtyCust: quantity needed to fill customer orders
	  This should already have been adjusted for quantity in stock.
	QtyOrd: quantity we'll actually order -- for now, assume same as QtyNeed
      NOTE: Not sure if these take supplier restock minimum into account
    */
    public function AddItem($idRestock,clsItem $rcItem,$qtyNeed,$qtySold) {
	$idItem = $rcItem->KeyValue();
	$sDesc = $rcItem->DescLong();
	$prcCost = $rcItem->PriceBuy();	// cost to us
	$ar = array(
	  'ID_Restock'	=> $idRestock,
	  'ID_Item'	=> $idItem,
	  'Descr'     	=> SQLValue($sDesc),  // Item description as given at time of shopping
	  'WhenCreated'	=> 'NOW()',// when this line was added
	  'WhenVoided'	=> 'NULL',	// when this line was voided; not NULL = ignore this line",
	// quantities - data from creation of restock
	  'QtyNeed'	=> $qtyNeed,	// quantity needed, either for an order or to keep stock at desired level
	  'QtyCust'	=> SQLValue($qtySold),	// quantity needed just to fill customer orders
	  'QtyOrd'	=> $qtyNeed,	// quantity actually ordered from supplier
	  'QtyExp'	=> 'NULL',	// quantity actually expected, if supplier doesn't have enough available to fill the order
	  'isGone'	=> 'NULL',	// YES = item discontinued, no more available (if info from source other than invoice)
	// cost information
	  'CostExpPer'	=> $prcCost,	// expected per-item cost
 	  );
 	$ok = $this->Insert($ar);
 	if (!$ok) {
	    echo 'SQL FAILED: '.$this->sqlExec.'<br>';
 	}
 	return $ok;
    }
    /*----
      ACTION: Adds a line-item to the current restock request.
	In order to avoid event proliferation, this routine does NOT log events.
	Instead, caller should log an event for each batch of lines added.
      CALLED BY: clsAdminRstkReqs::AdminItemsSave()
      HISTORY:
	2010-12-12 Uncommented and corrected to add items to rstk_req_item instead of rstk_req
    */
    /* to be adapted
    public function AddLine(clsItem $iItem,$iQtyNeed,$iQtyCust,$iQtyOrd) {
	assert('!$iItem->IsNew();');
	$arIns = array(
	  'ID_Restock'	=> $this->ID,
	  'ID_Item'	=> $iItem->ID,
	  'Descr'	=> SQLValue($iItem->DescLong()),
	  'WhenCreated'	=> 'NOW()',
	  'QtyNeed'	=> SQLValue($iQtyNeed),	// can be NULL
	  'QtyCust'	=> SQLValue($iQtyCust),	// can be NULL
	  'QtyOrd'	=> SQLValue($iQtyOrd),	// can be NULL
	  'CostExpPer'	=> SQLValue($iItem->PriceBuy)
	  );
	$this->LinesTbl()->Insert($arIns);
    }
    */
}
class clsRstkReqItem extends clsRecs_indexed {
    // ++ DATA FIELDS ACCESS ++ //

    protected function ParentID() {
	return $this->Value('ID_Restock');
    }
    protected function ItemID() {
	return $this->Value('ID_Item');
    }
    protected function ParentAdminLink() {
	$rc = $this->ParentRecord();
	return $rc->AdminLink_name();
    }
    protected function ItemAdminLink() {
	$rc = $this->ItemRecord();
	return $rc->AdminLink_name();
    }

    // -- DATA FIELDS ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function RequestsClass() {
	return KS_CLASS_RESTOCKS_REQUESTED;
    }
    protected function ItemsClass() {
	if (clsDropInManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItem';
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function ParentTable($id=NULL) {
	return $this->Engine()->Make($this->RequestsClass(),$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    protected function RestockRequestTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_RESTOCKS_REQUESTED,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function ReqObj() {
	throw new exception('ReqObj() is deprecated; use RequestRecord().');
    }
    protected function ParentRecord() {
	$id = $this->ParentID();
	$rc = $this->ParentTable($id);
	//$rcItem = $tReqs->GetItem('ID='.$this->ItemID());	// ALERT: shouldn't this also filter for request ID?
	return $rc;
    }
    protected function ItemRecord() {
	$id = $this->ItemID();
	$rc = $this->ItemTable($id);
	return $rc;
    }

    // -- DATA RECORDS ACCESS -- //
}
class VCT_RstkReqItems extends clsRstkReqItems {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_RstkReqItem');
	  $this->ActionKey(KS_ACTION_RESTOCK_REQUEST_ITEM);
    }

    // -- SETUP -- //
    // ++ CALCULATIONS ++ //

    /*-----
      RETURNS: A list of all active item requests
	* ADD items requested from all open restocks
	* SUBTRACT any items received for those restocks
    */
    public function ListExpected() {
	$sql = 'SELECT * FROM qryRstkItms_expected_byItem';
	$objRows = $this->objDB->DataSet($sql);
	if ($objRows->hasRows()) {
	    while ($objRows->NextRow()) {
		$idItem = (int)$objRows->ID_Item;
		$arOut[$idItem]['ord'] = $objRows->QtyExp;	// qty on order
		$arOut[$idItem]['rcd'] = $objRows->QtyRecd;	// qty received so far
		$arOut[$idItem]['exp'] = $objRows->QtyExp - $objRows->QtyRecd;	// qty still expected
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }

    // -- CALCULATIONS -- //
}
class VCR_RstkReqItem extends clsRstkReqItem {

    // ++ BOILERPLATE: self-URLs ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminRedirect($this,$iarArgs);
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
    // ++ ACTIONS ++ //

    public function AddItem() {
	die('This method not written yet!'); // is it needed now?
    }

    // -- ACTIONS -- //
    // ++ ADMIN WEB INTERFACE ++ //

    protected function PageForm() {
	if (empty($this->frmRow)) {
	    // create fields & controls
	    $frmRow = new clsForm_recs($this);

	    // these are the key fields, so let's not make them editable
	    //$objForm->AddField(new clsFieldNum('ID_Restock'),	new clsCtrlHTML(array('size'=>4)));
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

	    $this->frmRow = $frmRow;
	}
	return $this->frmRow;
    }
    protected function AdminPage() {
	clsActionLink_option::UseRelativeURL_default(TRUE);
	$arActs = array(
	  new clsActionLink_option(array(),'edit'),
	  );
	$oPage = $this->Engine()->App()->Page();

	$out = $oPage->ActionHeader('Record',$arActs);

	//$oMItm = $oPage->MenuNode();

	$doEdit = $oPage->PathArg('edit');
	$doSave = $oPage->ReqArgBool('btnSave');

	if ($doEdit || $doSave) {
	    if ($doSave) {
		$out .= $this->PageForm()->Save();
	    }
	}

	// render values/form
	$out .= "\n<table>";

	if ($doEdit) {
	    $out .= "\n<form method=post>";

	    $oForm = $this->PageForm();
	    $ctWhenCre = $oForm->RenderControl('WhenCreated');
	    $ctWhenVoi = $oForm->RenderControl('WhenVoided');
	    $ctQtyNeed = $oForm->RenderControl('QtyNeed');
	    $ctQtyCust = $oForm->RenderControl('QtyCust');
	    $ctQtyOrd = $oForm->RenderControl('QtyOrd');
	    $ctQtyExp = $oForm->RenderControl('QtyExp');
	    $ctIsGone = $oForm->RenderControl('isGone');
	    $ctCostExp = $oForm->RenderControl('CostExpPer');
	    $ctNotes = $oForm->RenderControl('Notes');
	} else {
	    $ctWhenCre = $this->Value('WhenCreated');
	    $ctWhenVoi = $this->Value('WhenVoided');
	    $ctQtyNeed = $this->Value('QtyNeed');
	    $ctQtyCust = $this->Value('QtyCust');
	    $ctQtyOrd = $this->Value('QtyOrd');
	    $ctQtyExp = $this->Value('QtyExp');
	    $vIsGone = $this->Value('isGone');
	    $ctIsGone = clsHTML::fromBool($vIsGone);
	    $ctCostExp = clsMoney::Format_withSymbol($this->Value('CostExpPer'));
	    $ctNotes = $this->Value('Notes');
	}


	$ctRstk = $this->ParentAdminLink();
	$ctItem = $this->ItemAdminLink();

	$out .= <<<__END__
<tr><td align=right><b>Restock</b>:</td><td>$ctRstk</td></tr>
<tr><td align=right><b>Item</b>:</td><td>$ctItem</td></tr>
<tr><td align=right><b>Created</b>:</td><td>$ctWhenCre</td></tr>
<tr><td align=right><b>Voided</b>:</td><td>$ctWhenVoi</td></tr>
<tr><td align=right><b>Qty Needed</b>:</td><td>$ctQtyNeed</td></tr>
<tr><td align=right><b>Qty for Cust</b>:</td><td>$ctQtyCust</td></tr>
<tr><td align=right><b>Qty Ordered</b>:</td><td>$ctQtyOrd</td></tr>
<tr><td align=right><b>Qty Expected</b>:</td><td>$ctQtyExp</td></tr>
<tr><td align=right><b>Is Gone</b>:</td><td>$ctIsGone</td></tr>
<tr><td align=right><b>Cost Per</b>:</td><td>$ctCostExp</td></tr>
<tr><td colspan=2><b>Notes</b>:<br> $ctNotes</td></tr>
</table>
__END__;
	if ($doEdit) {
	    $out .=
	      "\n<tr><td align=center colspan=2><input type=submit name=btnSave value='Save'></td></tr>"
	      ."\n</form>";
	}
	return $out;
    }
    public function AdminList($iNoneTxt='No restock requests found') {
	global $wgOut;

	if ($this->hasRows()) {
	    // links to documentation
	    $ftNeed = '<a href="'.KWP_TECHDOC_PREFIX_TERMS.'rstk/need">need</a>';
	    $ftCust = '<a href="'.KWP_TECHDOC_PREFIX_TERMS.'rstk/cust">cust</a>';
	    $ftOrd = '<a href="'.KWP_TECHDOC_PREFIX_TERMS.'rstk/ord">ord</a>';
	    $ftExp = '<a href="'.KWP_TECHDOC_PREFIX_TERMS.'rstk/exp">exp</a>';

	    $out = <<<__END__
<table class=listing>
  <tr>
    <th colspan=3>-</th>
    <th colspan=4>Quantities</th>
  </tr>
  <tr>
    <th>Req ID</th>
    <th>our PO #</th>
    <th>their PO #</th>
    <th>$ftNeed</th>
    <th>$ftCust</th>
    <th>$ftOrd</th>
    <th>$ftExp</th>
    <th>Created</th>
    <th>Ord</th>
    <th>Kld</th>
    <th>Shut</th>
    <th>Orph</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$arItem = $this->Values();
		$idReq = $this->Value('ID_Restock');
		$objReq = $this->RestockRequestTable($idReq);
		$dtCreated = $objReq->Value('WhenCreated');
		$dtSumm = is_null($dtCreated)?($objReq->Value('WhenOrdered')):$dtCreated;
		$key = $dtSumm.'.'.$this->Value('ID_Item').'.'.$idReq;
		$arSort[$key]['item'] = $arItem;
		$arSort[$key]['req'] = $objReq->Values();
	    }
	    arsort($arSort);
	    $objItem = $this->Engine()->Items()->SpawnItem();
	    //$objReq = $this->RestockRequestTable()->SpawnItem();
	    foreach ($arSort as $key=>$data) {
		$arItem = $data['item'];
		$arReq = $data['req'];
		$objItem->Values($arItem);
		$objReq->Values($arReq);

		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$idReq = $objItem->Value('ID_Restock');

		$objReq = $this->RestockRequestTable($idReq);
		$ftID = $objReq->AdminLink();
		$txtOurPO = $objReq->Value('PurchOrdNum');
		$txtTheirPO = $objReq->Value('SuppOrdNum');
		$qtyNeed = $objItem->Value('QtyNeed');
		$qtyCust = $objItem->Value('QtyCust');
		$qtyOrd = $objItem->Value('QtyOrd');
		$qtyExp = $objItem->Value('QtyExp');

		$txtWhenCre = $objReq->Value('WhenCreated');
		$dtCre = strtotime($txtWhenCre);
		$yrCre = date('Y',$dtCre);

		clsModule::LoadFunc('Date_DefaultYear');

		$txtWhenOrd = $objReq->Value('WhenOrdered');
		$ftWhenOrd = Date_DefaultYear($txtWhenOrd,$yrCre);

		$txtWhenKld = $objReq->Value('WhenKilled');
		$ftWhenKld = Date_DefaultYear($txtWhenKld,$yrCre);

		$txtWhenClo = $objReq->Value('WhenClosed');
		$ftWhenClo = Date_DefaultYear($txtWhenClo,$yrCre);

		$txtWhenOrph = $objReq->Value('WhenOrphaned');
		$ftWhenOrph = Date_DefaultYear($txtWhenOrph,$yrCre);

		$txtNotes = $objItem->Value('Notes');

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$ftID</td>
    <td>$txtOurPO</td>
    <td>$txtTheirPO</td>
    <td align=center>$qtyNeed</td>
    <td align=center>$qtyCust</td>
    <td align=center>$qtyOrd</td>
    <td align=center>$qtyExp</td>
    <td>$txtWhenCre</td>
    <td>$ftWhenOrd</td>
    <td>$ftWhenKld</td>
    <td>$ftWhenClo</td>
    <td>$ftWhenOrph</td>
    <td>$txtNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = $iNoneTxt;
	}
	return $out;
    }

    // -- ADMIN WEB INTERFACE -- //
}
