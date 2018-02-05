<?php
/*
  FILE: dropins/orders/order.php -- customer order administration dropin for VbzCart
  TODO: rename classes and table as "*line[s]" instead of "*item[s]"
  HISTORY:
    2014-02-22 split off OrderItem classes from order.php
    2015-04-21 manually merged changes accidentally not synced from Rizzo
*/
class vctAdminOrderLines extends vctOrderLines implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcrAdminOrderLine';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_PAGE_KEY_ORDER_LINE;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->RenderSearch();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->RenderSearch();
	return $out;
    } */

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
	$htFind = '"'.fcString::EncodeForHTML($sInput).'"';

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
    // TODO: why is this here instead of with the related functions in vcrAdminOrderLine?
    static public function CaptureEdit() {
	throw new exception('2017-05-31 Does anything still call this?');
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
      TODO: why is this here instead of with the related functions in vcrAdminOrderLine?
	See EditForm(), RenderEdit_inline()
    */
    static public function SaveEdit(vcrOrder $rcOrder, array $arFields, vcrOrderLine $rcLine=NULL) {
	throw new exception('2017-05-31 Does anything actually call this now?');
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
	  'ID_Order'	=> $rcOrder->GetKeyValue(),
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
class vcrAdminOrderLine extends vcrOrderLine implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftLoggableRecord;	// display record-specific event log
    use ftFrameworkAccess;
    use ftExecutableTwig;	// dispatch events
    use ftSaveableRecord;	// implements ChangeFieldValues()

    // ++ SETUP ++ //

    private $rcOrd;
    protected function InitVars() {
	$this->rcOrd = NULL;
	$this->doNewEntry = FALSE;
	parent::InitVars();
    }

    // -- SETUP -- //
    // ++ TRAIT HELPERS ++ //
    
    public function SelfLink_name() {
	$sName = $this->NameString();
	return $this->SelfLink($sName);
    }
    
    // -- TRAIT HELPERS -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$rcOrd = $this->OrderRecord();
	$sOrd = $rcOrd->NameString();
	$sSeq = $this->SequenceNumber();
	$htTitle = "Order $sOrd line # $sSeq";
	$sTitle = "ord $sOrd-$sSeq";
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ OPTIONS ++ //

    public function Want_ShowNewEntry($bShow=NULL) {
	if (!is_null($bShow)) {
	    $this->doNewEntry = $bShow;
	}
	return $this->doNewEntry;
    }
    private $doNewEntry;
    protected function Set_NewEntryVisible($bShow) {
	$this->doNewEntry = $bShow;
    }
    protected function Get_NewEntryVisible() {
    }

    // -- OPTIONS -- //
    // ++ FIELD VALUES ++ //


    protected function GetOrderID() {
	return $this->GetFieldValue('ID_Order');
    }
    protected function ItemID() {
	return $this->GetFieldValue('ID_Item');
    }
    public function QtyOrdered() {
	return $this->GetFieldValue('QtyOrd');
    }
    public function ShipPerItem() {
	throw new exception('2017-04-11 Call GetShippingPerUnit() instead.');
	return $this->Value('ShipItm');
    }
    public function ShipPerPackage() {
	throw new exception('2017-04-11 Call GetShippingPerPackage() instead.');
	return $this->Value('ShipPkg');
    }
    public function NotesText() {
	return $this->GetFieldValue('Notes');
    }
    protected function HasNotes() {
	return ($this->NotesText() != NULL);
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function NameString() {
	$sName = $this->OrderRecord()->NameString()
	  .'-'
	  .$this->SequenceNumber()
	  ;
	return $sName;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ARRAY CALCULATIONS ++ //

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
		$arOut[$idItem] = $this->GetFieldValue('QtyOrd') + fcArray::Nz($arOut,$idItem,0);
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }

    // -- ARRAY CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function OrdersClass() {
	return KS_CLASS_ORDERS;
    }
    protected function ItemsClass() {
	if (fcApp::Me()->GetDropInManager()->HasModule('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return 'vctItems';
	}
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function OrderTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->OrdersClass(),$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ItemsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
	2016-03-07 Yes, sometimes the Order record does change. The one example encountered
	  so far is when you're iterating through Orders for a given Item.
    */
    public function OrderRecord() {
	$doLoad = TRUE;
	$id = $this->GetOrderID();
	if (!is_null($this->rcOrd)) {
	    if ($this->rcOrd->GetKeyValue() == $id) {
		$doLoad = FALSE;
//	    } else {
//		throw new exception('This should never happen, should it?');
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
	    if ($this->rcItem->GetKeyValue() == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcItem = $this->ItemTable($id);
	}
	return $this->rcItem;
    }

    // -- RECORDS -- //
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
	    $oForm = new fcForm_DB($this);
	      $oField = new fcFormField_Num($oForm,'Seq');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>3));
	      $oField = new fcFormField_Num($oForm,'ID_Item');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>8));
	      $oField = new fcFormField_Text($oForm,'CatNum');
		$oCtrl = new fcFormControl_HTML($oField,array());
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));
	      $oField = new fcFormField_Num($oForm,'QtyOrd');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>3));
	      $oField = new fcFormField_Num($oForm,'Price');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipPkg');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipItm');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));

	    $this->frmEdit = $oForm;
	}
	return $this->frmEdit;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=record-block>
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
		'<td><input name="Descr"	size=30	value="'.fcString::EncodeForHTML($iarArgs['Descr']).'"></td>';
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
	  .'<td><input name="CatNum"	size=10 value="'.fcString::EncodeForHTML($iarArgs['CatNum']).'"></td>'
	  .$ftPart1
	  .$ftPart2
	  .'<td><input name="Qty"	size=2 value="'.fcString::EncodeForHTML($iarArgs['Qty']).'"></td>'
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
	2016-02-29 brought AdminSave() back in and tidied it up
    */
    public function AdminPage() {
	// save edits before rendering anything
	$doSave = fcHTTP::Request()->GetBool('btnSave');
	if ($doSave) {
	    $oForm = $this->EditForm();
	    if ($oForm->Save()) {
		$sMsgs = $oForm->MessagesString();
		$this->SelfRedirect(NULL,$sMsgs);
	    }
	}

	$oMenu = fcApp::Me()->GetHeaderMenu();
	$oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,'cancel','edit line record'));
	  $doEdit = $ol->GetIsSelected();
	
	$out = NULL;

//	$sDo = $oPage->PathArg('do');
//	$doEdit = ($sDo == 'edit');
	$doForm = $doEdit;

	$rcOrd = $this->OrderRecord();

	//$sTitle = 'Order '.$rcOrd->NameString().' line #'.$this->Value('Seq');

	/*
	// set up titlebar menu
	//clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString($sTitle);
	*/

//	$ftWho = $this->Value('VbzUser');
//	$ftWhere = $this->Value('Machine');

	$frmEdit = $this->EditForm();
	$frmEdit->LoadRecord();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	$arCtrls['!ord'] = $rcOrd->SelfLink_name();
	$doItemEdit = FALSE;
	if ($doEdit) {
	    if ($this->UserRecord()->CanDo(KS_PERM_EVENTS_EDIT)) {
		$doItemEdit = TRUE;
		// if user is an admin, allow setting ID_Item directly.
	    }
	}
	if (!$doItemEdit) {
	    // otherwise, always show friendly-link to the current Item
	    $rcItem = $this->ItemRecord();
	    $arCtrls['ID_Item'] = $rcItem->SelfLink_friendly();
	}

	if ($doForm) {
	    $out .= '<form method=post>';
	}
	$tplt = $this->PageTemplate();
	$tplt->SetVariableValues($arCtrls);
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
	    //$out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	// events
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
//	  new clsActionLink_option(array(),'add-item',		'do','add',	NULL,'add a new item to the order'),
	  );
	//$out .= $oPage->ActionHeader('Events',$arActs);
	$out .= $this->EventListing();

	return $out;
    }
    /*-----
      INPUT:
	$iArgs needs to be documented
    */
    public function AdminTable_forOrder() {
	$oFormIn = fcHTTP::Request();
	$out = NULL;

	$doNew = $this->Want_ShowNewEntry();	// displaying extra stuff for adding new line
	$nRows = $this->RowCount();
	$doRows = $nRows > 0;

	if ($doRows) {
	    $this->RewindRows();	// rewind to before first row
	    // no, I don't know why it doesn't remember the last row loaded
	    $this->NextRow();		// load first row to get order ID
	    $arPkgSums = $this->OrderRecord()->PackageSums();
	    $hasPkgs = is_array($arPkgSums);
	} else {
	    $hasPkgs = FALSE;
	}

	$doLookup = $oFormIn->GetBool('btnLookup');
	$doSave =  $oFormIn->GetBool('btnSaveItem');
	$doCapture = $doLookup || $doSave;	// is there form data to capture?
	if ($doRows || $doNew) {
	    if ($hasPkgs) {
		$htQtyHdr = '<th colspan=6>Quantities</th>';
	    } else {
		$htQtyHdr = '<th>qty</th>';
	    }
	    $out = <<<__END__
<table class=listing>
  <tr><td colspan=7></td>$htQtyHdr</tr>
  <tr>
    <th>ID</th>
    <th title="line sequence number">#</th>
    <th title="our catalog number">Cat #</th>
    <th>Description</th>
    <th>price</th>
    <th>per-item</th>
    <th>per-pkg</th>
    <th title="quantity ordered">ord</th>
__END__;
	    if ($hasPkgs) {
		$out .= <<<__END__
    <th title="quantity shipped"><i>shp</i></th>
    <th title="quantity returned"><i>rtn</i></th>
    <th title="quantity cancelled (killed)"><i>kld</i></th>
    <th title="quantity not available"><i>n/a</i></th>
    <th>OPEN</th>
__END__;
	    }
	    $out .= "\n  </tr>";

	    if ($doCapture) {
		$arFields = $this->GetTableWrapper()->CaptureEdit();
	    } else {
		$arFields = NULL;
	    }
	    if ($doRows) {
		$rcItem = $this->ItemTable()->SpawnRecordset();
		$isOdd = TRUE;
		$this->RewindRows();
		while ($this->NextRow()) {
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isOdd = !$isOdd;

		    $row = $this->GetFieldValues();
		    $id = $row['ID'];
		    $nSeq = $this->SequenceNumber();
		    $idItem = $this->ItemID();
		    $ftID = $this->SelfLink();
		    $strCatNum = $this->GetCatNum();
		    $hasNotes = $this->HasNotes();
		    if ($hasNotes) {
			$sNotes = $this->NotesText();
			$htNotes = fcString::EncodeForHTML($sNotes);
		    }
		    $rcItem->SetKeyValue($idItem);
		    $htCatNum = $rcItem->SelfLink($strCatNum);
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
			    $htOrd = $this->SelfLink();
			    $htPkgCells = "<td colspan=5><b>Warning</b>: package data is missing for ID $idItem</td>";
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
		    $arFields['Descr'] = $rsItems->Description_forOrder();
		    $arFields['Price'] = fcMoney::Format_number($rsItems->PriceSell());
		    $rcShipCode = $rsItems->ShipCostRecord();
		    $arFields['ID_Item'] = $rsItems->GetKeyValue();
		    $arFields['PerItem'] = fcMoney::Format_number($rcShipCode->PerUnit());
		    $arFields['PerPkg'] = fcMoney::Format_number($rcShipCode->PerPkg());
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
    /*----
      ACTION: Displays a listing of order-related data for each item in the recordset.
      USAGE: called by an LCItem object to display orders for that item
    */
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
		$idOrd = $this->GetOrderID();
		$rcOrd = $this->OrderTable()->GetRecord_forKey($idOrd);
		$key = $rcOrd->GetFieldValue('WhenStarted').$rcOrd->Number();
		$arOrd[$key]['line'] = $this->Values();
		$arOrd[$key]['ord'] = $rcOrd->Values();
	    }
	    krsort($arOrd);
	    foreach ($arOrd as $key => $data) {
		$rcLine = $this;
		$rcLine->SetFieldValues($data['line']);
		$rcOrd->SetFieldValues($data['ord']);
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		//$wtOrd = $vgPage->SelfLink_WT(array('page'=>'order','id'=>$idOrd),$objOrd->Number);
		$wtOrd = $rcOrd->SelfLink($rcOrd->Number());
		$rcBuyer = $rcOrd->BuyerRecord();
		if (is_object($rcBuyer)) {
		    $wtOrd .= ': '.$rcBuyer->SelfLink($rcBuyer->NameString());
		} else {
		    $wtOrd .= ' <i>(no buyer!)</i>';
		}
		$wtStatus = $rcOrd->PulledText();

		$arStats = $rcOrd->PackageSums($rcLine->ItemID());
		$qtyOrd = $rcLine->GetFieldValue('QtyOrd');
		$qtyShp = fcArray::Nz($arStats,'qty-shp');
		$qtyRtn = fcArray::Nz($arStats,'qty-rtn');
		$qtyKld = fcArray::Nz($arStats,'qty-kld');
		$qtyNA = fcArray::Nz($arStats,'qty-na');
		$qtyOpen = $qtyOrd - $qtyShp - $qtyKld - $qtyNA;
		$wtOpen = empty($qtyOpen)?'-':"<b>$qtyOpen</b>";
		$wtWhen = $rcOrd->WhenStarted();

		$strPrice = $rcLine->PriceSell();

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
}
