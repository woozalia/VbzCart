<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Items
    VCA_Items
    VCR_Item
    clsAdminItems_info_Cat - adds an ActionKey to clsItems_info_Cat
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2013-12-15 Renamed from vbz-mw-item.php to item.php for drop-in system.
*/
class VCA_Items extends clsItems {

    // ++ INITIALIZATION ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_Item');
	  $this->ActionKey('item');
    }

    // -- INITIALIZATION -- //

    public function Listing_forTitle(clsVbzTitle $iTitleObj) {
	$objTitle = $iTitleObj;
	$idTitle = $objTitle->ID;
	//$cntRow = 0;

	//$rs = $this->GetData('(IFNULL(isDumped,0)=0) AND (ID_Title='.$idTitle.')','VbzAdminItem','ItOpt_Sort');
	$rs = $this->GetData('(IFNULL(isDumped,0)=0) AND (ID_Title='.$idTitle.')',NULL,'ItOpt_Sort');
	return $rs->AdminList();
    }
    /*----
      ACTION: Updates fields in given catalog items
	Does not log an event (maybe it should?)
      INPUT: Array of items as returned by AdminItems_data_check()
      RETURNS: HTML to display (messages)
      FUTURE: This possibly should be in a helper class
      HISTORY:
	2011-01-04 partially written
    */
    public function AdminItems_data_update(array $iItems) {
	$out = "\n<ul>";
	foreach ($iItems as $idx => $row) {
	    $obj = $row['@obj'];
	    $id = $obj->KeyValue();
	    $out .= "\n<li>Row $idx ID=$id:\n<ul>";
	    $arUpd = NULL;
	    if (!empty($row['do-upd-scat'])) {
		$arUpd['Supp_CatNum'] = SQLValue($row['scat']);
		$out .= "\n<li>scat# [".$obj->Supp_CatNum.']=>['.$row['scat'].']';
	    }
	    if (!empty($row['do-upd-desc'])) {
		$arUpd['Descr'] = SQLValue($row['name']);
		$out .= "\n<li>descr [".$obj->Descr.']=>['.$row['name'].']';
	    }
	    $out .= "\n</ul>";
	    $obj->Update($arUpd);
	}
	$out .= "\n</ul>";
	return $out;
    }
}
class VCR_Item extends clsItem {
    private $idEvent;

    // ++ BOILERPLATE ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->Engine()->App()->Events());
	}
	return $this->logger;
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

    // -- BOILERPLATE -- //
    // ++ BOILERPLATE EXTENSION ++ //

    public function AdminLink_name() {
	$strName = $this->CatNum();
	if (empty($strName)) {
	    $strName = $this->TitleObj()->Name();
	}
	return $this->AdminLink($strName);
    }
    /*----
      PURPOSE: Like AdminLink_name(). but okay to take up more room to provide more info
      HISTORY:
	2010-11-24 created
    */
    public function AdminLink_friendly() {
	$strItem = $this->AdminLink_CatNum();
	$strFull = $strItem.' '.$this->DescLong_ht();
	return $strFull;
    }
    /*----
      HISTORY:
	2010-11-27 created
    */
    public function AdminLink_CatNum() {
	return $this->AdminLink($this->CatNum,$this->DescLong());
    }

    // -- BOILERPLATE EXTENSION -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ DATA FIELD ACCESS ++ //

    protected function IsCurrent() {
	return $this->Value('isCurrent');
    }
    protected function IsInPrint() {
	return $this->Value('isInPrint');
    }
    protected function IsPulled() {
	return $this->Value('isPulled');
    }
    /*----
      ACTION:
	Return the current supplier catalog number
	If a value is given, update it to the new value first (returns old value)
	If an event array is given, log the event
    */
    public function SCatNum($iVal=NULL,array $iEvent=NULL) {
	$strOld = $this->Supp_CatNum;
	if (!is_null($iVal)) {
	    if (is_array($iEvent)) {
		$iEvent['params'] = nz($iEvent['params']).':old=['.$strOld.']';
		$iEvent['code'] = 'SCN';
		$this->StartEvent($iEvent);
	    }
	    $arUpd = array('Supp_CatNum'=>SQLValue($iVal));
	    $this->Update($arUpd);
	    if (is_array($iEvent)) {
		$this->FinishEvent();
	    }
	}
	return $strOld;
    }
    public function FullDescr($iSep=' ') {
	$rcTitle = $this->TitleRecord();
	return $rcTitle->NameText().$iSep.$this->Value('ItOpt_Descr');
    }
    public function FullDescr_HTML($iSep=' ') {
	$rcTitle = $this->TitleRecord();
	$rcItTyp = $this->ItTyp();
	if ($rcItTyp->IsNew()) {
	    $out = '(no item type)';
	} else {
	    $out =
	      $rcTitle->AdminLink($rcTitle->NameString()).$iSep.
	      $rcItTyp->Row['NameSng'].$iSep.
	      $this->ItOpt_Descr();
	}
	return $out;
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function StockItemTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINES,$id);
    }
    protected function OrderItemTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDER_ITEMS,$id);
    }
    protected function RestockRequestItemTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_RESTOCK_REQ_ITEMS,$id);
    }
    protected function StockEventTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function TitleRecord() {
	return $this->TitleTable($this->Value('ID_Title'));
    }
    public function ItTyp() {
	return $this->objDB->ItTyps()->GetItem($this->ID_ItTyp);
    }
    public function Data_forStkItems() {
	$rs = $this->Engine()->StkItems()->Data_forItem($this->KeyValue());
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminList() {
	$cntRow = 0;

	if ($this->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Cat #</th>
    <th>Status</th>
    <th>Stock</th>
    <th>Description</th>
    <th>$ buy</th>
    <th>$ sell</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		//$wtID = SelfLink_Page('item','id',$id,$id);
		$wtID = $this->AdminLink();
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$isActive = $this->IsForSale();
		$isCurrent = $this->IsCurrent();
		$isInPrint = $this->IsInPrint();
		$isPulled = $this->IsPulled();
		if ($isActive) {
		    $cntRow++;
		} else {
		    $wtStyle .= ' color: #888888;';
		}
		if (!$isInPrint) {
		    $wtStyle .= ' font-style: italic;';
		}
		if ($isPulled) {
		    $wtStyle .= ' text-decoration: line-through;';
		}
		$strStatus = '';
		if ($isActive)	{ $strStatus .= '<span title="A=active" style="color:green;">A</span>';	}
		if ($isCurrent) { $strStatus .= '<span title="C=current" style="color: #006600;">C</span>';	}
		if ($isInPrint) { $strStatus .= '<span title="P=in print" style="color: blue;">P</span>';	}
		if ($isPulled)	{ $strStatus .= '<span title="U=pulled" style="color: red;">U</span>';	}
		$sQty = $this->Qty_InStock();
		$sCatNum = $this->CatNum();
		$sItOpt = $this->ItOpt_Descr;
		$sPriceBuy = DataCurr($this->PriceBuy);
		$sPriceSell = DataCurr($this->PriceSell);
		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$wtID</td>
    <td>$sCatNum</td>
    <td align=center>$strStatus</td>
    <td>$sQty</td>
    <td>$sItOpt</td>
    <td align=right>$sPriceBuy</td>
    <td align=right>$sPriceSell</td>
  </tr>
__END__;
		if (!is_null($this->Descr)) {
		    $out .= "\n  <tr style='$wtStyle'>"
		      .'<td colspan=4>'.$this->Descr.'</td>'
		      .'</tr>';
		}

		$isOdd = !$isOdd;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No items found. (SQL='.$sql.')';
	}
	return $out;
    }

/* 2010-11-11 This should be completely obsolete, right?
    protected function ReceiveForm() {
	global $wgOut,$wgRequest;

	if ($wgRequest->getCheck('btnSave')) {
	    $intQtyNew = $wgRequest->GetInt('StkMin');
	    $arUpd['QtyMin_Stk'] = $intQtyNew;
	    $intQtyOld = $this->QtyMin_Stk;
	    if ($intQtyOld == $intQtyNew) {
		$out = 'No change requested; min qty is already '.$intQtyOld.'.';
	    } else {
		$strDescr = 'Updating per-item stock minimum from '.$intQtyOld.' to '.$intQtyNew;
		$out = $strDescr;
		$this->StartEvent(__METHOD__,'UPD',$strDescr);
		$this->Update($arUpd);
		$this->Reload();
		$this->FinishEvent();
	    }
	    $wgOut->AddWikiText($out);
	}
    }
*/
    /*----
      HISTORY:
	2011-02-24 Finally renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$strCatNum = $this->CatNum;

	$objTitle = $this->TitleRecord();
	$strTitleName = $objTitle->Value('Name');
	$ftTitleLink = (is_object($objTitle))?($objTitle->AdminLink($strTitleName)):'title N/A';
	$strTitle = 'Item: '.$strCatNum.' ('.$this->ID.') - '.$this->FullDescr();
/*
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();
*/
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	$strAction = $oPage->PathArg('do');
	$doAdd = ($strAction == 'add');
	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');

	if ($doEdit || $doSave) {
	    if ($doSave) {
		$this->AdminSave();
	    }
	}

	$ftPriceSell = $this->PriceSell;	// this can be edited if item is not in print
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $objForm = $this->PageForm();

	    $ftCatNum = $objForm->Render('CatNum');
	    $ftCatSfx = $objForm->Render('CatSfx');
	    $ftDescr = $objForm->Render('Descr');
	    if (!$this->isInPrint) {
		$ftPriceSell = $objForm->Render('PriceSell');
	    }
	    $ftPriceList = $objForm->Render('PriceList');
	    $ftSCatNum = $objForm->Render('Supp_CatNum');
	    $ftStkMin = $objForm->Render('QtyMin_Stk');
	} else {
	    $ftCatNum = $this->CatNum;
	    $ftCatSfx = $this->CatSfx;
	    $ftDescr = $this->Descr;
	    $ftPriceList = $this->PriceList;
	    $ftSCatNum = $this->Supp_CatNum;
	    $ftStkMin = $this->QtyMin_Stk;
	}
	// non-editable fields:
	$ftID = $this->AdminLink();
	$ftTitle = $ftTitleLink;
	$ftPriceBuy = $this->PriceBuy;
	$ftItOpt = $this->ID_ItOpt;
	$ftIODescr = $this->ItOpt_Descr;
	$ftIOSort = $this->ItOpt_Sort;
	$ftGrpCode = $this->GrpCode;
	$ftGrpDescr = $this->GrpDescr;
	$ftGrpSort = $this->GrpSort;

	$sStatus = NULL;
	if ($this->isMaster) {	$sStatus .= ' MASTER'; }
	if ($this->isForSale) {	$sStatus .= ' FOR-SALE'; }
	if ($this->isInPrint) {	$sStatus .= ' IN-PRINT'; }
	if ($this->isCloseOut) {$sStatus .= ' CLOSEOUT'; }
	if ($this->isPulled) {	$sStatus .= ' PULLED'; }
	if ($this->isDumped) {	$sStatus .= ' DUMPED'; }

	$out = <<<__END__
<ul class=listing>
  <li> <b>ID</b>: $ftID</li>
  <li> <b>Cat #</b>: $ftCatNum <b>suffix</b>: $ftCatSfx</li>
  <li> <b>Description</b>: $ftDescr</li>
  <li> <b>Prices</b>: $ftItOpt</li>
  <ul>
    <li> <b>Buy</b>: $ftPriceBuy</li>
    <li> <b>Sell</b>: $ftPriceSell</li>
    <li> <i><b>List</b>: $ftPriceList</i>
  </ul>
  <li> <b>Item Option</b>: $ftItOpt</li>
  <ul>
    <li> <b>Descr</b>: $ftIODescr</li>
    <li> <b>Sort</b>: $ftIOSort</li>
  </ul>
  <li> <b>Group</b> - <b>code</b>: $ftGrpCode <b>descr</b>: $ftGrpDescr <b>sort</b>: $ftGrpSort</li>
  <li> <b>Title</b>: $ftTitle</li>
  <li> <b>Stk Min</b>: $ftStkMin</li>
  <li> <b>Status</b>: $sStatus</li>
__END__;
	$out .= "\n  <li> <b>Supplier Cat #</b>:$ftSCatNum";
	$out .= "\n</ul>";

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	//$oSkin = $oPage->Skin();
	$out .=
	  $oPage->SectionHeader('Stock',NULL,'section-header-sub')
	  .$this->StockListing()
	  .$oPage->SectionHeader('Orders',NULL,'section-header-sub')
	  .$this->OrderListing()
	  .$oPage->SectionHeader('Restocks',NULL,'section-header-sub')
	  .$this->RestockListing()
	  .$oPage->SectionHeader('Events',NULL,'section-header-sub')
	  .$this->EventListing()
	  .$oPage->SectionHeader('Movement',NULL,'section-header-sub')
	  .$this->MovementListing()
	  ;

	return $out;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());

	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsField('CatNum'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('CatSfx'),		new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('PriceSell'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('PriceList'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Supp_CatNum'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('QtyMin_Stk'),	new clsCtrlHTML(array('size'=>3)));

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    public function StockListing() {
	$out = $this->StockItemTable()->Listing_forItem($this);
	return $out;
    }
    public function OrderListing() {
	$id = $this->KeyValue();
	$tbl = $this->OrderItemTable();
	$rs = $tbl->GetData('ID_Item='.$id);
	$out = $rs->AdminTable_forItem();
	return $out;
    }
    public function RestockListing() {
	$id = $this->KeyValue();
	$tbl = $this->RestockRequestItemTable();
	$rs = $tbl->GetData('ID_Item='.$id);
	$out = $rs->AdminList('No restocks found for this item');
	return $out;
    }
    /*----
      RENDERS listing of stock movements for this item
    */
    public function MovementListing() {
	$id = $this->KeyValue();
	$tbl = $this->StockEventTable();
	return $tbl->Listing_forItem($id);
    }

    // -- ADMIN WEB UI -- //

    /*----
      RETURNS: wikitext which links to the catalog page for the item
      NOTE: Since the store doesn't yet have pages for each item,
	    this returns a link to the item's Title page
      FUTURE: create StoreLink() function which uses RichText object
    */ /*
    public function StoreLink_WT($iText) {
	return '[[vbznet:cat/'.$this->Title()->URL_part().'|'.$iText.']]';
    } */
/*    public function AdminLink_WT($iText) {
	return '[[{{FULLPAGENAME}}/page'.KS_CHAR_URL_ASSIGN.'item/id'.KS_CHAR_URL_ASSIGN.$this->ID.'|'.$iText.']]';
    }
*/
// FUNCTION DEPRECATED - remove eventually
    public function StoreLink_HT($iText) {
	return '<a href="'.KWP_CAT.$this->TitleRecord()->URL_part().'" title="browse in store">'.$iText.'</a>';
    }
}
class clsAdminItems_info_Cat extends clsItems_info_Cat {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ActionKey('item');
    }
}
