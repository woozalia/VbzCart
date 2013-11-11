<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Items
    VbzAdminItems
    VbzAdminItem
    clsAdminItems_info_Cat - adds an ActionKey to clsItems_info_Cat
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class VbzAdminItems extends clsItems {
// STATIC section //
    /*----
      HISTORY:
	2010-10-13 Re-enabled as a boilerplate call
    */
    public static function AdminLink($iID,$iShow=NULL,$iPopup=NULL) {
	return clsAdminTable::AdminLink($iID,$iShow,$iPopup);
    }
// DYNAMIC section //
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminItem');
	  $this->ActionKey('item');
    }
    public function Listing_forTitle(clsVbzTitle $iTitleObj) {
	$objTitle = $iTitleObj;
	$idTitle = $objTitle->ID;
	//$cntRow = 0;

	$objRecs = $this->GetData('(IFNULL(isDumped,0)=0) AND (ID_Title='.$idTitle.')','VbzAdminItem','ItOpt_Sort');
	return $objRecs->AdminList();
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
class VbzAdminItem extends clsItem {
    private $idEvent;

    /*----
      HISTORY:
	2010-10-11 Added iarArgs parameter
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
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
    /*----
      HISTORY:
	2010-11-06 replaced old event logging with boilerplate calls to helper class
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
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
    public function Data_forStkItems() {
	$rs = $this->Engine()->StkItems()->Data_forItem($this->KeyValue());
	return $rs;
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
    public function AdminList() {
	global $vgPage,$vgOut;
	global $sql;

	$cntRow = 0;

	if ($this->HasRows()) {
	    $out = $vgOut->TableOpen('class=sortable');
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('ID');
	      $out .= $vgOut->TblCell('Cat #');
	      $out .= $vgOut->TblCell('Status');
	      $out .= $vgOut->TblCell('Description');
	      $out .= $vgOut->TblCell('$ buy');
	      $out .= $vgOut->TblCell('$ sell');
	    $out .= $vgOut->TblRowShut();
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$id = $this->ID;
		//$wtID = SelfLink_Page('item','id',$id,$id);
		$wtID = $this->AdminLink();
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$isActive = $this->isForSale;
		$isCurrent = $this->isCurrent;
		$isInPrint = $this->isInPrint;
		$isPulled = $this->isPulled;
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
		$out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
		  $out .= $vgOut->TblCell($wtID);
		  $out .= $vgOut->TblCell($this->CatNum);
		  $out .= $vgOut->TblCell($strStatus,'align=center');
		  $out .= $vgOut->TblCell($this->ItOpt_Descr);
		  $out .= $vgOut->TblCell(DataCurr($this->PriceBuy));
		  $out .= $vgOut->TblCell(DataCurr($this->PriceSell));
		$out .= $vgOut->TblRowShut();
		if (!is_null($this->Descr)) {
		    $out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
		      $out .= $vgOut->TblCell($this->Descr,'colspan=4');
		    $out .= $vgOut->TblRowShut();
		}

		$isOdd = !$isOdd;
		//$objLine = $objRecs->CloneFields();
		$objLine = clone $this;
	    }
	    $out .= $vgOut->TableShut();
	} else {
	    $out = 'No items listed. (SQL='.$sql.')';
	}
	return $out;
    }
    public function Title() {
	return $this->objDB->Titles()->GetItem($this->ID_Title);
    }
    public function ItTyp() {
	return $this->objDB->ItTyps()->GetItem($this->ID_ItTyp);
    }
    public function FullDescr($iSep=' ') {
	$objTitle = $this->Title();
	return $objTitle->Name.$iSep.$this->ItOpt_Descr;
    }
    public function FullDescr_HTML($iSep=' ') {
	$objTitle = $this->Title();
	$objItTyp = $this->ItTyp();
	if ($objItTyp->IsNew()) {
	    $out = '(no item type)';
	} else {
	    $out =
    //	  $objTitle->SelfLink($objTitle->Name).$iSep.
	      $objTitle->AdminLink($objTitle->Name).$iSep.
	      $objItTyp->Row['NameSng'].$iSep.
	      $this->ItOpt_Descr;
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
	global $wgOut,$wgRequest;
	global $vgPage;

	$strCatNum = $this->CatNum;

	//$this->ReceiveForm();

	$vgPage->UseHTML();
	$objTitle = $this->Title();
	$strTitleName = $objTitle->Name;
	$ftTitleLink = (is_object($objTitle))?($objTitle->AdminLink($strTitleName)):'title N/A';
	$strTitle = 'Item: '.$strCatNum.' ('.$this->ID.') - '.$this->FullDescr();

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();

	$strAction = $vgPage->Arg('do');
	$doAdd = ($strAction == 'add');
	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$this->AdminSave();
	    }
	}

	$ftPriceSell = $this->PriceSell;	// this can be edited if item is not in print
	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

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
	$ftTitle = $ftTitleLink;
	$ftPriceBuy = $this->PriceBuy;
	$ftItOpt = $this->ID_ItOpt;
	$ftIODescr = $this->ItOpt_Descr;
	$ftIOSort = $this->ItOpt_Sort;
	$ftGrpCode = $this->GrpCode;
	$ftGrpDescr = $this->GrpDescr;
	$ftGrpSort = $this->GrpSort;

	$out .= '<ul>';
	$out .= '<li> <b>ID</b>: '.$this->ID;
	$out .= '<li> <b>Cat #</b>: '.$ftCatNum.' <b>suffix</b>: '.$ftCatSfx;
	$out .= '<li> <b>Description</b>: '.$ftDescr;
	$out .= '<li> <b>Prices</b>: '.$ftItOpt;
	$out .= '<ul>';
	  $out .= '<li> <b>Buy</b>: '.$ftPriceBuy;
	  $out .= '<li> <b>Sell</b>: '.$ftPriceSell;
	  $out .= '<li> <i><b>List</b>: '.$ftPriceList.'</i>';
	$out .= '</ul>';
	$out .= '<li> <b>Item Option</b>: '.$ftItOpt;
	$out .= '<ul>';
	  $out .= '<li> <b>Descr</b>: '.$ftIODescr;
	  $out .= '<li> <b>Sort</b>: '.$ftIOSort;
	$out .= '</ul>';
	$out .= '<li> <b>Group</b> - <b>code</b>: '.$ftGrpCode.' <b>descr</b>: '.$ftGrpDescr.' <b>sort</b>: '.$ftGrpSort;
	$out .= '<li> <b>Title</b>: '.$ftTitle;
	$out .= '<li> <b>Stk Min</b>: '.$ftStkMin;
	$out .= '<li> <b>Status</b>:';
	if ($this->isMaster) {	$out .= ' MASTER'; }
	if ($this->isForSale) {	$out .= ' FOR-SALE'; }
	if ($this->isInPrint) {	$out .= ' IN-PRINT'; }
	if ($this->isCloseOut) {	$out .= ' CLOSEOUT'; }
	if ($this->isPulled) {	$out .= ' PULLED'; }
	if ($this->isDumped) {	$out .= ' DUMPED'; }
	$out .= '<li> <b>Supplier Cat #</b>:'.$ftSCatNum;
	$out .= '</ul>';

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out);
	$wgOut->AddHTML("<h3>Stock</h3>");
	$wgOut->AddWikiText($this->StockListing(),TRUE);
	$wgOut->AddHTML("<h3>Orders</h3>");
	$wgOut->AddWikiText($this->OrderListing(),TRUE);
	$wgOut->AddHTML("<h3>Restocks</h3>");
	$wgOut->AddWikiText($this->RestockListing(),TRUE);
	$wgOut->AddHTML('<h3>Events</h3>');
	$wgOut->AddWikiText($this->EventListing(),TRUE);
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
	$out = $this->objDB->StkItems()->Listing_forItem($this);
	return $out;
    }
    public function OrderListing() {
	$objTbl = $this->objDB->OrdItems();
	$objRows = $objTbl->GetData('ID_Item='.$this->ID);
	$out = $objRows->AdminTable_forItem();
	return $out;
    }
    public function RestockListing() {
	$objTbl = $this->objDB->RstkReqItems();
	$objRows = $objTbl->GetData('ID_Item='.$this->ID);
	$out = $objRows->AdminList('No restocks found for this item');
	return $out;
    }
    /*----
      RETURNS: wikitext which links to the catalog page for the item
      NOTE: Since the store doesn't yet have pages for each item,
	    this returns a link to the item's Title page
      FUTURE: create StoreLink() function which uses RichText object
    */
    public function StoreLink_WT($iText) {
	return '[[vbznet:cat/'.$this->Title()->URL_part().'|'.$iText.']]';
    }
// FUNCTION DEPRECATED - remove eventually
    public function AdminLink_WT($iText) {
	return '[[{{FULLPAGENAME}}/page'.KS_CHAR_URL_ASSIGN.'item/id'.KS_CHAR_URL_ASSIGN.$this->ID.'|'.$iText.']]';
    }
// FUNCTION DEPRECATED - remove eventually
    public function StoreLink_HT($iText) {
	return '<a href="'.KWP_CAT.$this->Title()->URL_part().'" title="browse in store">'.$iText.'</a>';
    }
}
class clsAdminItems_info_Cat extends clsItems_info_Cat {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ActionKey('item');
    }
}
