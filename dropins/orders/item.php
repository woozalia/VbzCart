<?php
/*
  FILE: dropins/orders/order.php -- customer order administration dropin for VbzCart
  HISTORY:
    2014-02-22 split off OrderItem classes from order.php
    2015-04-21 manually merged changes accidentally not synced from Rizzo
*/
class VCA_OrderItems extends clsOrderLines {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCA_OrderItem');	// override parent
	  $this->ActionKey(KS_PAGE_KEY_ORDER_LINE);
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->RenderSearch();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ ADMIN WEB UI ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();

	$sPfx = $this->ActionKey();
	$htSearchOut = NULL;

	$sSearchName = $sPfx.'-needle';
	$sInput = $oPage->ReqArgText($sSearchName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forText($sInput);
	    $htSearchOut .= $rs->Listing('No matching order item records.');
	}
	$htFind = '"'.htmlspecialchars($sInput).'"';

	// build forms

	$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$htSearchForm = <<<__END__
<form method=post>
  NOT YET IMPLEMENTED - Search for orders containing (description or catalog #):
  <input name="$sSearchName" size=40 value=$htFind>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	$out = $htSearchHdr.$htSearchForm;
	if (!is_null($htSearchOut)) {
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      .$htSearchOut;
	}

	return $out;
    }
    // TODO: why is this here instead of with the related functions in VCA_OrderItem?
    static public function CaptureEdit() {
	$oReq = clsHTTP::Request();

	// always capture these fields
	$arOut['ID_Item']	= $oReq->GetInt('ID_Item');
	$arOut['CatNum']	= $oReq->GetText('CatNum');
	$arOut['QtyOrd']	= $oReq->GetInt('QtyOrd');
	$arOut['Descr']		= $oReq->GetText('Descr');
	$arOut['Price']		= $oReq->GetText('Price');
	$arOut['PerItem']	= $oReq->GetText('PerItem');
	$arOut['PerPkg']	= $oReq->GetText('PerPkg');
	return $arOut;
    }
    /*----
      HISTORY:
	2015-03-14 This originally checked the input data (iarFields) for an ID, and determined whether
	a new Order record was called for based on whether that was set or not. This doesn't make
	any sense, since the Order being saved to may exist, and in that case we wouldn't want
	to create a new one. I am therefore changing this logic to check $rcOrder->IsNew() instead.
      TODO: why is this here instead of with the related functions in VCA_OrderItem?
	See EditForm(), RenderEdit_inline()
    */
    static public function SaveEdit(clsOrder $rcOrder, array $arFields, clsOrderLine $rcLine=NULL) {
	if (is_null($rcLine)) {
	    $rcLine = $rcOrder->SpawnLineRecord();
	}
	$rcLine->Values($arFields);
	$isNew = $rcLine->IsNew();
	if ($isNew) {
	    $intSeq = $rcOrder->NextLineSeq();
	} else {
	    $intSeq = $rcLine->Seq();
	}
	$strCatNum = $rcLine->CatNum();
	$intQty = $rcLine->QtyOrd();
	$arUpd = array(
	  'ID_Order'	=> $rcOrder->KeyValue(),
	  'Seq'		=> $intSeq,
	  'ID_Item'	=> $rcLine->ItemID(),
	  'CatNum'	=> SQLValue($strCatNum),
	  'Descr'	=> SQLValue($rcLine->DescrText()),
	  'QtyOrd'	=> $intQty,
	  'Price'	=> SQLValue($arFields['Price']),
	  'ShipPkg'	=> SQLValue($arFields['PerPkg']),
	  'ShipItm'	=> SQLValue($arFields['PerItem']),
	  'WhenAdded'	=> 'NOW()',
	  );
	if ($isNew) {
	    // LOG THE ATTEMPT
	    $arEv = array(
	      'code'	=> 'ADI',
	      'descr'	=> 'adding cat #'.$strCatNum.' qty '.$intQty,
	      'where'	=> __METHOD__
	      );
	    $rcOrder->StartEvent($arEv);
	    // CREATE THE RECORD
	    $rcOrder->LineTable()->Insert($arUpd);
	} else {
	    // LOG THE ATTEMPT
	    $arEv = array(
	      'code'	=> 'UDI',
	      'descr'	=> 'updating cat #'.$strCatNum.' qty '.$intQty,
	      'where'	=> __METHOD__
	      );
	    $iOrder->StartEvent($arEv);
	    // UPDATE THE RECORD
	    if (!is_object($rcLine)) {
		$idLine = $arFields['ID'];
		$rcLine = $rcOrder->Table()->GetItem($idLine);
	    }
	    $rcLine->Update($arUpd);
	}
	// LOG COMPLETION
	$rcOrder->FinishEvent();
    }

    // -- ADMIN WEB UI -- //

}
class VCA_OrderItem extends clsOrderLine {
    private $doNewEntry;

    // ++ INITIALIZATION ++ //

    private $rcOrd;
    protected function InitVars() {
	$this->rcOrd = NULL;
	$this->doNewEntry = FALSE;
	parent::InitVars();
    }

    // -- INITIALIZATION -- //
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
    protected function NotesText() {
	return $this->Value('Notes');
    }
    protected function HasNotes() {
	return ($this->NotesText() != NULL);
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
    protected $rcItem;
    public function ItemObj() {
	throw new exception('ItemObj() is deprecated; call ItemRecord().');
    }
    public function ItemRecord() {
	$doLoad = TRUE;
	$id = $this->ItemID();
	if (isset($this->rcItem)) {
	    if ($this->rcItem->KeyValue() == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcItem = $this->ItemTable($id);
	}
	return $this->rcItem;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ ADMIN INTERFACE ++ //

    /*----
      HISTORY:
	2011-03-23 adapted from VbzAdminItem to VbzAdminOrderItem
    */
    private $frmEdit;
    private function EditForm() {
	if (empty($this->frmEdit)) {
	    // FORMS v2
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);
	      $oField = new fcFormField_Num($oForm,'Seq');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>3));
	      $oField = new fcFormField_Num($oForm,'ID_Item');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>8));
	      $oField = new fcFormField($oForm,'CatNum');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>40));
	      $oField = new fcFormField_Num($oForm,'QtyOrd');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>3));
	      $oField = new fcFormField_Num($oForm,'Price');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipPkg');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipItm');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>40));

	    /* FORMS v1
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
*/
	    $this->frmEdit = $oForm;
	}
	return $this->frmEdit;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td><#ID#></td></tr>
  <tr><td align=right><b>Order</b>:</td><td><#!ord#></td></tr>
  <tr><td align=right><b>Seq</b>:</td><td><#Seq#></td></tr>
  <tr><td align=right><b>Item</b>:</td><td><#ID_Item#></td></tr>
  <tr><td align=right><b>Cat #</b>:</td><td><#CatNum#></td></tr>
  <tr><td align=right><b>Description</b>:</td><td><#Descr#></td></tr>
  <tr><td align=right><b>Qty ordered</b>:</td><td><#QtyOrd#></td></tr>
  <tr><td align=right><b>Price</b>:</td><td>$ <#Price#></td></tr>
  <tr><td align=right><b>s/h per pkg</b>:</td><td>$ <#ShipPkg#></td></tr>
  <tr><td align=right><b>s/h per item</b>:</td><td>$ <#ShipItm#></td></tr>
  <tr><td align=right><b>Notes</b>:</td><td><#Notes#></td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('<#','#>',$sTplt);
	}
	return $this->tpPage;
    }
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
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
	2011-03-23 copied from clsAdminTopic to VbzAdminOrderItem
	2015-02-16 this probably needs some rewriting (I don't think $vgOut is still a thing)
    */
    public function AdminSave() {
	global $vgOut;

	$out = NULL;
	$oForm = $this->EditForm();
	if ($oForm->Save()) {
	    // TODO: $oForm->htMsg should be stuffed into the redirect text-saver, wherever that's gotten to...
	    $out = $this->AdminRedirect();
	} else {
	    $out = $oForm->htMsg;
	}
	return $out;
    }
    public function AdminPage() {
	// save edits before showing events
	$ftSaveStatus = NULL;
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	if ($doSave) {
	    $ftSaveStatus = $this->AdminSave();
	    $this->Redirect();
	}
	$out = NULL;
	$oPage = $this->Engine()->App()->Page();

	$sDo = $oPage->PathArg('do');
	$doEdit = ($sDo == 'edit');
	$doForm = $doEdit;

	$rcOrd = $this->OrderRecord();

	$sTitle = 'Order '.$rcOrd->AdminName().' line #'.$this->Value('Seq');

	// set up titlebar menu
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString($sTitle);

	$out .= $ftSaveStatus;	// 2015-03-15 this probably doesn't work

//	$ftWho = $this->Value('VbzUser');
//	$ftWhere = $this->Value('Machine');

	$frmEdit = $this->EditForm();
	$frmEdit->LoadRecord();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->AdminLink();
	$arCtrls['!ord'] = $rcOrd->AdminLink_name();
	if ($doEdit) {
	    $rcItem = $this->ItemRecord();
	    $arCtrls['ID_Item']	= $rcItem->AdminLink_friendly();
	}

	if ($doForm) {
	    $out .= '<form method=post>';
	}
	$tplt = $this->PageTemplate();
	$tplt->VariableValues($arCtrls);
	$out .= $tplt->Render();

	/*
	if ($doEdit) {
	    $out .= '<form method=post>';
	    $objForm = $this->EditForm();

	    $ftSeq	= $objForm->RenderControl('Seq');
	    $ftItem	= $objForm->RenderControl('ID_Item');
	    $ftCatNum	= $objForm->RenderControl('CatNum');
	    $ftDescr	= $objForm->RenderControl('Descr');
	    $ftQty	= $objForm->RenderControl('QtyOrd');
	    $ftPrice	= $objForm->RenderControl('Price');
	    $ftShPkg	= $objForm->RenderControl('ShipPkg');
	    $ftShItm	= $objForm->RenderControl('ShipItm');
	    $ftNotes	= $objForm->RenderControl('Notes');
	} else {
	    $rcItem = $this->ItemRecord();

	    $ftSeq	= $this->Value('Seq');
	    $ftItem	= $rcItem->AdminLink_friendly();
	    $ftCatNum	= $this->Value('CatNum');
	    $ftDescr	= $this->Value('Descr');
	    $ftQty	= $this->Value('QtyOrd');
	    $ftPrice	= $this->Value('Price');
	    $ftShPkg	= $this->Value('ShipPkg');
	    $ftShItm	= $this->Value('ShipItm');
	    $ftNotes	= $this->Value('Notes');
	}
*/
	/*
	$out .= <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>
  <tr><td align=right><b>Order</b>:</td><td>$ftOrd</td></tr>
  <tr><td align=right><b>Seq</b>:</td><td>$ftSeq</td></tr>
  <tr><td align=right><b>Item</b>:</td><td>$ftItem</td></tr>
  <tr><td align=right><b>Cat #</b>:</td><td>$ftCatNum</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>$ftDescr</td></tr>
  <tr><td align=right><b>Qty ordered</b>:</td><td>$ftQty</td></tr>
  <tr><td align=right><b>Price</b>:</td><td>$ $ftPrice</td></tr>
  <tr><td align=right><b>s/h per pkg</b>:</td><td>$ $ftShPkg</td></tr>
  <tr><td align=right><b>s/h per item</b>:</td><td>$ $ftShItm</td></tr>
  <tr><td align=right><b>Notes</b>:</td><td>$ftNotes</td></tr>
  <tr><td align=right><b>Admin</b>:</td><td>$ftWho</td></tr>
  <tr><td align=right><b>Where</b>:</td><td>$ftWhere</td></tr>
</table>
__END__;
*/
	if ($doForm) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	// events
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
//	  new clsActionLink_option(array(),'add-item',		'do','add',	NULL,'add a new item to the order'),
	  );
	$out .= $oPage->ActionHeader('Events',$arActs);
	$out .= $this->EventListing();

	return $out;
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
    <th title="line sequence number">#</th>
    <th title="our catalog number">Cat #</th>
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
		    $nSeq = $this->SeqNum();
		    $idItem = $this->Value('ID_Item');
		    $ftID = $this->AdminLink();
		    $strCatNum = $this->CatNum();
		    $hasNotes = $this->HasNotes();
		    if ($hasNotes) {
			$sNotes = $this->NotesText();
			$htNotes = htmlspecialchars($sNotes);
		    }
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
			if (array_key_exists($idItem,$arPkgSums)) {
			    $arSumItem = $this->OrderRecord()->ItemStats_update_line($arPkgSums[$idItem]);

			    $intQtyShp = (int)$arSumItem['qty-shp-line'];
			    $intQtyRtn = (int)$arSumItem['qty-rtn'];
			    $intQtyKld = (int)$arSumItem['qty-kld'];
			    $intQtyNA  = (int)$arSumItem['qty-na'];
			    $intQtyOpen = $intQtyOrd - $intQtyShp - $intQtyKld - $intQtyNA;
			    $strQtyOpen = ($intQtyOpen == 0)?'-':('<font color=red><b>'.$intQtyOpen.'</b></font>');

			    $htPkgCells = <<<__END__
<td align=center>$intQtyShp</td>
<td align=center>$intQtyRtn</td>
<td align=center>$intQtyKld</td>
<td align=center>$intQtyNA</td>
<td align=center>$strQtyOpen</td>
__END__;
			} else {
			    $htOrd = $this->AdminLink();
			    $htPkgCells = "<td colspan=5><b>Warning</b>: package data is missing for ID $idItem</td>";
			    //echo 'ARPKGSUMS:<pre>'.print_r($arPkgSums,TRUE).'</pre>';
			    //$idOrd = $this->OrderRecord()->KeyValue();
			    //throw new exception("Internal error in order administration (order ID $idOrd, item ID $idItem)");
			}
		    }

		    $out .= <<<__END__
  <tr style="$wtStyle">
    <td>$ftID</td>
    <td align=right>$nSeq</td>
    <td>$htCatNum</td>
    <td>$strDescr</td>
    <td align=right>$strPrice</td>
    <td align=right>$strPerPkg</td>
    <td align=right>$strPerItem</td>
    <td align=center>$strQtyOrd</td>
__END__;
		    if ($hasPkgs) {
			$out .= $htPkgCells;
		    }
		    if ($hasNotes) {
			$out .= "\n  </tr>\n<tr><td><small>note</small></td><td colspan=7><small>$htNotes</small></td>";
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
		    $rsItems = $this->ItemTable()->GetData('CatNum="'.$strCatNum.'"');
		    $doFull = $rsItems->HasRows();
		    if (!$doFull) {
			$out .= 'No match found for cat # '.$strCatNum;
		    }
		    $rsItems->FirstRow();
		    $arFields['Descr'] = $rsItems->DescLong();
		    $arFields['Price'] = FormatMoney($rsItems->PriceSell());
		    $rcShipCode = $rsItems->ShipCostRecord();
		    $arFields['ID_Item'] = $rsItems->KeyValue();
		    $arFields['PerItem'] = FormatMoney($rcShipCode->PerItem());
		    $arFields['PerPkg'] = FormatMoney($rcShipCode->PerPkg());
		} else if ($doSave) {
		    // maybe this is working? it saved a record earlier...
		    //throw new exception('This part still needs to be written. #thanksObama');
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
