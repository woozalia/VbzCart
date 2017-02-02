<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling Items
    VCA_Items
    VCR_Item
    clsAdminItems_info_Cat - adds an ActionKey to clsItems_info_Cat
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2013-12-15 Renamed from vbz-mw-item.php to item.php for drop-in system.
*/
class VCA_Items extends clsItems {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'VCR_Item';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CATALOG_ITEM;
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$out = $this->RenderSearch()
	  .$this->RenderReports()
	  ;
	return $out;
    }
    
    // -- DROP-IN API -- //
    // ++ DATA TABLE ACCESS ++ //
    
    /*----
      PUBLIC so Records class can access it too (fewer functions to define)
    */
    public function StockLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINES,$id);
    }
    protected function ImageInfoQuery() {
	return $this->Engine()->Make('vcqtImagesInfo');
    }
    
    // -- DATA TABLE ACCESS -- //
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      REPLACES: http://htyp.org/VbzCart/queries/qryItems_needed_forStock
    */
    protected function SQL_forNeeded_forStock() {
	$tStkLines = $this->StockLineTable();
	$sqlJoin = $tStkLines->SQL_forItems_inStock_forSale();
	$sqlItems = $this->NameSQL();
	$sql = "\nSELECT"
	  ."\n  i.ID,"
	  ."\n  i.QtyMin_Stk,"
	  ."\n  s.QtyForSale"
	  ."\n FROM $sqlItems AS i"
	  ."\n  LEFT JOIN ($sqlJoin) AS s"
	  ."\n  ON s.ID_Item=i.ID"
	  ."\n WHERE i.isForSale AND ((i.QtyMin_Stk - s.QtyForSale) > 0)";
	return $sql;
    }
    // -- SQL CALCULATIONS -- //
    // ++ RECORDS ++ //
    
    protected function Records_Active_noPrice() {
	$qo = $this->ItemInfoQuery()->SQO_forSale();

	$qof = $qo->Select()->Fields();
	$qof->ClearFields();
	$qof->SetFields($this->ItemInfoQuery()->Fields_forRender());
	
	$qo->Terms()->Filters()->AddCond('PriceSell IS NULL');
	$qo->Terms()->UseTerm(new fcSQLt_Sort(array('i.CatNum')));

	$sql = $qo->Render();
    
	$rs = $this->DataSQL($sql);
	return $rs;
    }	
    
    // -- RECORDS -- //
    // ++ ARRAYS ++ //

    /*-----
      RETURNS: Array of items we need to restock in order to fill stock minimums
    */
    public function Needed_forStock_array() {
	$sql = $this->SQL_forNeeded_forStock();
	//$sql = 'SELECT * FROM qryItems_needed_forStock';
	//$rs = $this->Engine()->DataSet($sql);
	$rs = $this->DataSQL($sql);
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$id = $rs->GetKeyValue();
		$arOut[$id]['min'] = $rs->Qty_forStock_minimum();
		$arOut[$id]['got'] = $rs->Value('QtyForSale');	// calculated field
		$arOut[$id]['need'] = $arOut[$id]['min'] - $arOut[$id]['got'];
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }
    
    // -- ARRAYS -- //
    // ++ ADMIN WEB UI ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();
	
	$sCat = $oPage->ReqArgText('inCatNum');
	$sDsc = $oPage->ReqArgText('inDescr');
	$htCat = NULL;
	$htDsc = NULL;
	$htResults = NULL;
	if (!empty($sCat) || !empty($sDsc)) {
	    $sqlFilt = NULL;
	    if (!empty($sCat)) {
	      // prepare the search parameter
		$sqlCat = $this->Engine()->SanitizeAndQuote("$sCat%");
		$htCat = '"'.fcString::EncodeForHTML($sCat).'"';
		$sqlFilt = "(CatNum LIKE $sqlCat) OR (Supp_CatNum LIKE $sqlCat)";
	    }
	    if (!empty($sDsc)) {
		$sqlDsc = $this->Engine()->SanitizeAndQuote("%$sDsc%");
		$htDsc = fcString::EncodeForHTML($sDsc);
		if (!is_null($sqlFilt)) {
		    $sqlFilt .= "OR ";
		}
		$sqlFilt .= "(Descr LIKE $sqlDsc) OR (ItOpt_Descr LIKE $sqlDsc)";
	    }
	    
	    if (is_null($sqlFilt)) {
	    } else {
		// do the search
		$rs = $this->GetData($sqlFilt);
		$htResults = $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
		  .$rs->AdminRows();
	    }
	}
	
	$out = $oPage->SectionHeader('Search')
	  .<<<__END__

<form method=get>
  Search items by:
  <br>catalog #: <input name=inCatNum size=20 value=$htCat>
  <br>description: <input name=inDescr size=50 value=$htDsc>
  <br><input type=submit name=btnSearch value="Search...">
</form>
__END__;
	$out .= $htResults;
	
	return $out;
    }
    protected function RenderReports() {
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

	$arMenu = array(
	  new clsAction_section('Show'),
	  new clsActionLink_option(
	    array(),
	    'no-price',	// link key (value)
	    'show',	// group key
	    NULL,	// display when off
	    NULL,	// display when on
	    'show active items with no price set'	// pop-up description
	    )
	  );
	$out .= $oPage->SectionHeader('Reports',$arMenu);
	
	$sShow =$oPage->PathArg('show');
	
	if ($sShow == 'no-price') {
	    $rs = $this->Records_Active_noPrice();
	    $out .= $oPage->SectionHeader('active, no price',NULL,'section-header-subsub')
	      .$rs->AdminRows()
	      ;
	}
	
	return $out;
    }
    public function Listing_forTitle($idTitle,array $arOptions=NULL) {
	$rs = $this->Records_forTitle($idTitle);
	return $rs->AdminRows(NULL,$arOptions);
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
    /* 2015-11-26 This would need rewriting, and is probably obsolete anyway
    public function AdminItems_data_update(array $iItems) {
	$out = "\n<ul>";
	foreach ($iItems as $idx => $row) {
	    $obj = $row['@obj'];
	    $id = $obj->GetKeyValue();
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
    } */

    // -- ADMIN WEB UI -- //
    
}
class VCR_Item extends clsItem {
    use ftLinkableRecord, ftLoggableRecord, ftShowableRecord;
    use vtTableAccess_Supplier;

    private $idEvent;

    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name() {
	$sText = $this->CatNum();
	if (empty($sText)) {
	    $sText = $this->TitleRecord()->Name();
	}
	return $this->SelfLink($sText);
    }
    /*----
      PURPOSE: Like AdminLink_name(). but okay to take up more room to provide more info
      HISTORY:
	2010-11-24 created
    */
    public function SelfLink_friendly() {
	$sItem = $this->SelfLink_CatNum_only();
	$sFull = $sItem.' '.$this->Description_long_generic_html();
	return $sFull;
    }
    /*----
      HISTORY:
	2010-11-27 created
	
    */
    public function SelfLink_CatNum_only() {
	return $this->SelfLink($this->CatNum());
    }
    public function SelfLink_CatNum_wDetails() {
	return $this->SelfLink($this->CatNum(),$this->Description_long_generic_attribtext());
    }

    // -- TRAIT HELPERS -- //
    // ++ CALLBACK ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- CALLBACK -- //
    // ++ APP FRAMEWORK ++ //
    
    protected function PageObject() {
	return $this->Engine()->App()->Page();
    }
    
    // -- APP FRAMEWORK -- //
    // ++ FIELD VALUES ++ //

    // WRITABLE so we can set when adding Items for a Title.
    // PUBLIC because parent is.
    public function SupplierID($id=NULL) {
	return $this->Value('ID_Supp',$id);
    }
    protected function IsMaster() {
	return $this->Value('isMaster');
    }
    public function IsAvailable() {
	return $this->Value('isAvail');
    }
    protected function IsCurrent() {
	return $this->Value('isCurrent');
    }
    protected function IsInPrint() {
	return $this->Value('isInPrint');
    }
    protected function IsCloseOut() {
	return $this->Value('isCloseOut');
    }
    protected function IsPulled() {
	return $this->Value('isPulled');
    }
    protected function IsDumped() {
	return $this->Value('isDumped');
    }
    protected function CatSfx() {
	return $this->Value('CatSfx');
    }
    protected function ItOptSort() {
	return $this->Value('ItOpt_Sort');
    }
    /*----
      RETURNS: the minimum quantity we've decided to keep in stock for the Item in this Line
      PUBLIC so it can be shown in Stock listings
    */
    public function Qty_forStock_minimum() {
	return $this->Value('QtyMin_Stk');
    }
    protected function SupplierCatNum() {
	return $this->Value('Supp_CatNum');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function HasSupplier() {
	return !empty($this->SupplierID());
    }
    protected function HasTitle() {
	return !empty($this->TitleID());
    }
    protected function Title_CatNum() {
	$hasTitle = FALSE;
	if ($this->HasTitle()) {
	    $rc = $this->TitleRecord();
	    if ($rc->IsNew()) {
		$id = $this->TitleID();
		$sTCat = "<abbr title='Title ID $id not found'>!T$id</abbr>";
	    } else {
		$out = $rc->CatNum();
		$hasTitle = TRUE;
	    }
	} else {
	    $sTCat .= '<abbr title="Title ID not set">?T</abbr>';
	}
	if (!$hasTitle) {
	    if ($this->HasSupplier()) {
		$rc = $this->SupplierRecord();
		if ($rc->IsNew()) {
		    $id = $this->SupplierID();
		    $sSCat = "<abbr title='Supplier ID $id not found'>!S$id</abbr>";
		} else {
		    $sSCat = $this->SupplierRecord()->CatKey();
		}
	    } else {
		$sSCat = '<abbr title="Supplier ID not set">?S</abbr>';
	    }
	    $out = $sSCat.$sTCat;
	}
	return $out;
    }
    protected function CatNum_calc($sSep='-') {
	return fcString::ConcatArray($sSep,array($this->Title_CatNum(),$this->CatKey()));
    }
    public function IsForSale() {
	return $this->IsAvailable() || ($this->Qty_InStock() > 0);
    }
    /*----
      HISTORY:
	2016-02-10 Revised to calculate quantity directly from stock records
    */
    protected function Qty_InStock() {
	$sql = vcqtStockLinesInfo::SQL_forItemStatus('ID_Item='.$this->GetKeyValue());
	$rc = $this->Engine()->DataSet($sql);
	if ($rc->HasRows()) {
	    if ($rc->RowCount() > 1) {
		throw new exception('Internal data error: This should not happen.');
	    }
	    $rc->NextRow();	// get the only row
	    return $rc->Value('QtyForSale');
	} else {
	    return NULL;
	}
    }
    /*----
      PUBLIC so Restock Items Needed can use it
      TODO: The rules should probably be documented.
	What is the difference between {is-available, out-of-print} and {is-closeout}?
	For now, I've marked out-of-print as "RETIRED".
    */
    public function StatusSummaryLine() {
	$out = NULL;
	if ($this->IsAvailable()) {
	    $out = 'OK';
	} else {
	    $out = '<b>N/A</b>';
	}
	if (!$this->isInPrint()) {
	    $out .= ' RETIRED';
	}
	if ($this->isCloseOut()) {
	    $out .= ' CLOSEOUT';
	}
	if ($this->isCurrent()) {
	    $out .= ' <span title="current">&radic;</span>';
	} else {
	    $out .= ' NOT-CURRENT';
	}
	return $out;
    }
    public function FullDescr($sSep=' ') {
	$rcTitle = $this->TitleRecord();
	if ($rcTitle->HasRows()) {
	    $sTitle = $rcTitle->NameText();
	} else {
	    $sTitle = 'NO TITLE!';
	}
	return $sTitle.$sSep.$this->ItemOptionString_forDetails();
    }
    public function FullDescr_HTML($iSep=' ') {
	$rcTitle = $this->TitleRecord();
	$rcItTyp = $this->ItemTypeRecord();
	if ($rcItTyp->IsNew()) {
	    $out = '(no item type)';
	} else {
	    $out =
	      $rcTitle->SelfLink($rcTitle->NameString()).$iSep.
	      $rcItTyp->NameSingular().$iSep.
	      $this->ItOptString_general();
	}
	return $out;
    }
    protected function HasCatSfx() {
	return !is_null($this->CatSfx());
    }
    protected function HasItOptSort() {
	return !is_null($this->ItOptSort());
    }
    protected function HasDescription() {
	return !is_null($this->Description());
    }
    protected function StatusText() {
	if ($this->IsNew()) {
	    $sStatus = '(new)';
	} else {
	    $sStatus = NULL;
	    if ($this->IsMaster()) {	$sStatus .= ' MASTER'; }
	    if ($this->IsCurrent()) { 	$sStatus .= ' CURRENT';	}
	    if ($this->IsAvailable()) {	$sStatus .= ' AVAILABLE'; }
	    if ($this->IsInPrint()) {	$sStatus .= ' IN-PRINT'; }
	    if ($this->IsCloseOut()) {	$sStatus .= ' CLOSEOUT'; }
	    if ($this->IsPulled()) {	$sStatus .= ' PULLED'; }
	    if ($this->IsDumped()) {	$sStatus .= ' DUMPED'; }
	}
	return $sStatus;
    }
    protected function StatusChars() {
	$sStatus = NULL;
	if ($this->IsMaster()) {	$sStatus .= 'M'; }
	if ($this->IsCurrent()) {
	  $sStatus .= '<span title="C=current" style="color: #006600;">C</span>';	}
	if ($this->IsAvailable()) {
	  $sStatus .= '<span title="A=available from supplier" style="color:green;">A</span>';	}
	if ($this->IsInPrint()) {
	  $sStatus .= '<span title="P=in print" style="color: blue;">P</span>';	}
	if ($this->IsCloseOut()) {
	  $sStatus .= '<span title="T=closeout" style="color: grey;">G</span'; }
	if ($this->IsPulled())	{
	  $sStatus .= '<span title="U=pulled" style="color: red;">U</span>';	}
	if ($this->IsDumped()) {
	  $sStatus .= '<span title="X=dumped" style="color: red;">X</span>'; }
	return $sStatus;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //
    
    protected function SuppliersClass() {
	return KS_CLASS_CATALOG_SUPPLIERS;
    }
    protected function TitlesClass() {
	return KS_CLASS_CATALOG_TITLES;
    }
    protected function ItemTypesClass() {
	return KS_ADMIN_CLASS_LC_ITEM_TYPES;
    }
    protected function ItemOptionsClass() {
	return KS_ADMIN_CLASS_LC_ITEM_OPTIONS;
    }
    protected function ShipCostsClass() {
	return KS_ADMIN_CLASS_SHIP_COSTS;
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function StockItemTable($id=NULL) {
	throw new exception('StockItemTable() has been renamed StockLineTable().');
    }
    protected function StockLineTable($id=NULL) {
	return $this->Table()->StockLineTable($id);
    }
    // ItemTypeTable($id=NULL) is defined by parent class
    // ItemOptionTable($id=NULL) is defined by parent class
    protected function OrderLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDER_LINES,$id);
    }
    protected function RestockRequestItemTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_RESTOCK_REQ_ITEMS,$id);
    }
    protected function ReceivedRestockItemTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED,$id);
    }
    protected function StockEventTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }
    protected function OrdersQuery() {
	return $this->Engine()->Make(KS_CLASS_JOIN_LCITEM_ORDERS);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function TitleRecord() {
	return $this->TitleTable($this->TitleID());
    }
    public function ItemTypeRecord() {
	return $this->ItemTypeTable($this->ItemTypeID());
    }
    public function Data_forStkItems() {
	return $this->StockItemTable()->Data_forItem($this->GetKeyValue());
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //
    
    /*----
      ACTION: Recalculate the Item's catalog stats from related record settings:
	* CatSfx (if not set) = Opt.CatKey
	* CatNum = Title.CatNum & CatSfx (rules subject to refinement)
      USAGE: Ideally, this should only be needed for cleaning up old data; new data
	entered manually or via Supplier Catalog Management should have everything
	set properly.
    */
    public function UpdateCatSpecs(array $arSel=NULL) {
	$msg = NULL;
	$okAll = TRUE;
	$db = $this->Engine();
	while ($this->NextRow()) {
	    $id = $this->GetKeyValue();
	    if (is_null($arSel) || array_key_exists($id,$arSel)) {
		$msg .= "<b>Item ID ".$this->SelfLink().'</b>: ';
		if ($this->HasCatSfx()) {
		    $sSfx = $this->CatSfx();
		} else {
		    $sSfx = $this->ItemOptionRecord()->CatKey();
		    $arUpd['CatSfx'] = "'$sSfx'";
		    $msg .= 'CatSfx: ['.$this->CatSfx()."]&rarr;[$sSfx] ";
		}
		if (!$this->HasItOptSort()) {
		    $s = $this->ItemOptionRecord()->SortKey();
		    $arUpd['ItOpt_Sort'] = $db->SanitizeAndQuote($s);
		    $msg .= 'Opt Sort: ['.$this->ItOptSort()."]&rarr;[$s] ";
		}
		if (!$this->HasDescription()) {
		    $sOpt= $this->ItemOptionRecord()->Description();
		    $s = fcString::ConcatArray(' - ',array($sSfx,$sOpt));
		    $arUpd['ItOpt_Descr'] = $db->SanitizeAndQuote($sOpt);
		    $arUpd['Descr'] = $db->SanitizeAndQuote($s);
		    $msg .= 'Opt Descr: ['.$this->ItOptString_general()."]&rarr;[$sOpt] "
		      .'Descr: ['.$this->Description()."]&rarr;[$s] ";
		}
		// always recalculate CatNum
		$sTCat = $this->TitleRecord()->CatNum();
		$s = fcString::ConcatArray('-',array($sTCat,$sSfx));
		$arUpd['CatNum'] = "'$s'";
		$msg .= 'CatNum: ['.$this->CatNum()."]&rarr;[$s]";
		
		$ok = $this->Update($arUpd);
		if (!$ok) {
		    $okAll = FALSE;
		    $msg .= '<br><b>Error</b>: '.$db->getError()
		      .'<br><b>SQL</b>: '.$this->sqlExec
		      ;
		    $db->ClearError();
		}
		$msg .= '<br>';
	    }
	}
	$oSkin = $this->PageObject()->Skin();
	if ($okAll) {
	    $out = $oSkin->RenderSuccess($msg);
	} else {
	    $out = $oSkin->RenderError($msg);
	}
	return $out;
    }
    
    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //
    
    //++multiple++//

    /*
    public function AdminRows() {	// ALIAS
	return $this->AdminList();
    }*/
    protected function AdminRows_settings_columns_default() {
	return array(
	    'ID'	=> 'ID',
	    'LCatNum'	=> 'Our Cat #',
	    'SCatNum'	=> 'Supp Cat #',
	    'status'	=> 'Status',
	    'QtyStock'	=> 'Stock',
	    'ItOptDescr'	=> 'Description',
	    'PriceBuy'	=> '$ buy',
	    'PriceSell'	=> '$ sell',
	  );
    }
    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    protected function AdminField($sField) {
	$htAttr = NULL;
	switch($sField) {
	  case 'ID':
	    if ($this->AdminRows_settings_option('dochk')) {
		$id = $this->GetKeyValue();
		$htName = $this->AdminRows_settings_option('chkname').'['.$id.']';
		$ctrl = "<input type=checkbox name=$htName>";
	    } else { $ctrl = NULL; }
	    $val = $ctrl.$this->SelfLink();
	    break;
	  case 'LCatNum':
	    $val = $this->CatNum();
	    break;
	  case 'SCatNum':
	    $val = $this->SupplierCatNum();
	    break;
	  case 'status':
	    $val = $this->StatusChars();
	    $htAttr = ' align=center';
	    break;
	  case 'QtyStock':
	    $val = $this->Qty_InStock();
	    $htAttr = ' align=right';
	    break;
	  case 'ItOptDescr':
	    $val = $this->ItemOptionString_forDetails();
	    break;
	  case 'PriceBuy':
	    $val = clsMoney::Format_withSymbol($this->PriceBuy());
	    $htAttr = ' align=right';
	    break;
	  case 'PriceSell':
	    $val = clsMoney::Format_withSymbol($this->PriceSell());
	    $htAttr = ' align=right';
	}
	return "<td$htAttr>$val</td>";
    }
    protected function AdminRow_CSSclass() {
	static $isOdd = FALSE;
	
	$sClass = NULL;
	if ($this->IsForSale()) {
	    // normal -- checkbook stripe
	    $isOdd = !$isOdd;
	    $sClass = $isOdd?'odd':'even';
	} else {
	    //$wtStyle .= ' color: #888888;';
	    $sClass .= ' inactive';
	}
	if (!$this->IsInPrint()) {
	    //$wtStyle .= ' font-style: italic;';
	    $sClass .= ' out-of-print';
	}
	if ($this->IsPulled()) {
	    //$wtStyle .= ' text-decoration: line-through;';
	    $sClass .= ' pulled';
	}
	return $sClass;
    }
    public function AdminList() {
    throw new exception('Who is still calling this? Call AdminRows() instead.');
	$cntRow = 0;

	if ($this->HasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Our Cat #</th>
    <th>Supp Cat #</th>
    <th>Status</th>
    <th>Stock</th>
    <th>Description</th>
    <th>$ buy</th>
    <th>$ sell</th>
  </tr>
__END__;
	    $oTplt = $this->LineTemplate();
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$sClass = $isOdd?'odd':'even';
		$isActive = $this->IsForSale();
		$isCurrent = $this->IsCurrent();
		$isInPrint = $this->IsInPrint();
		$isPulled = $this->IsPulled();
		if ($isActive) {
		    $cntRow++;
		} else {
		    //$wtStyle .= ' color: #888888;';
		    $sClass .= ' inactive';
		}
		if (!$isInPrint) {
		    //$wtStyle .= ' font-style: italic;';
		    $sClass .= ' out-of-print';
		}
		if ($isPulled) {
		    //$wtStyle .= ' text-decoration: line-through;';
		    $sClass .= ' pulled';
		}
		$sStatus = '';
		if ($isActive)	{ $sStatus .= '<span title="A=active" style="color:green;">A</span>';	}
		if ($isCurrent) { $sStatus .= '<span title="C=current" style="color: #006600;">C</span>';	}
		if ($isInPrint) { $sStatus .= '<span title="P=in print" style="color: blue;">P</span>';	}
		if ($isPulled)	{ $sStatus .= '<span title="U=pulled" style="color: red;">U</span>';	}
		$sPriceBuy = clsMoney::Format_withSymbol($this->PriceBuy());
		$sPriceSell = clsMoney::Format_withSymbol($this->PriceSell());

		$arCtrls = array(
		  'ID' 		=> $this->SelfLink(),
		  'LCatNum'	=> $this->CatNum(),
		  'SCatNum'	=> $this->SupplierCatNum(),
		  'status'	=> $sStatus,
		  'QtyStock'	=> $this->Qty_InStock(),
		  'ItOptDescr'	=> $this->ItOptString_general(),
		  'PriceBuy'	=> $sPriceBuy,
		  'PriceSell'	=> $sPriceSell
		  );
		$oTplt->VariableValues($arCtrls);
		$out .= "\n  <tr class='$sClass'>"
		  .$oTplt->RenderRecursive()
		  ."\n  </tr>";

		if (!is_null($this->Descr())) {
		    $out .= "\n  <tr class='$sClass'>"
		      .'<td colspan=4>'.$this->Descr().'</td>'
		      .'</tr>';
		}

		$isOdd = !$isOdd;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No items found.<br><span class="line-stats"><b>SQL</b>:'.$this->sqlMake.'</span>';
	}
	return $out;
    }
    private $tpLine;
    protected function LineTemplate() {
    throw new exception('Is anything still calling this? AdminRows() does not seem to use it.');
	if (empty($this->tpLine)) {
	    $sTplt = <<<__END__
    <td>[[ID]]</td>
    <td>[[LCatNum]]</td>
    <td>[[SCatNum]]</td>
    <td align=center>[[status]]</td>
    <td align=right>[[QtyStock]]</td>
    <td>[[ItOptDescr]]</td>
    <td align=right>[[PriceBuy]]</td>
    <td align=right>[[PriceSell]]</td>
__END__;
	    $this->tpLine = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpLine;
    }
    
    //--multiple--//
    //++single++//

    /*----
      HISTORY:
	2011-02-24 Finally renamed from InfoPage() to AdminPage()
    */
    public function AdminPage(array $arOpts=NULL) {
	$oPage = $this->PageObject();

	if ($this->IsNew()) {
	    $sTitle = 'new Item';
	    $this->TitleID($oPage->PathArg('title'));	// get Title ID, if given
	} else {
	    $sCatNum = $this->CatNum();
	    $sTitle = 'Item #'.$this->GetKeyValue().': '.$sCatNum.' - '.$this->FullDescr();
	}

	$oPage->Skin()->SetPageTitle($sTitle);

	$sAction = $oPage->PathArg('do');
	$doAdd = ($sAction == 'add');
	$isNew = $this->IsNew();
	$doEdit = $isNew || $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');

	if ($doEdit || $doSave) {
	    if ($doSave) {
		$this->PageForm()->Save();
		if (array_key_exists('url.return',$arOpts)) {
		    clsHTTP::Redirect($arOpts['url.return']);
		} else {
		    // remove the form submission from the current state:
		    $this->SelfRedirect();
		}
	    }
	}

      // build the form
	$frmEdit = $this->PageForm();
	if ($isNew) {
	    $frmEdit->ClearValues();
	    
	    if (clsArray::Exists($arOpts,'id.title')) {
		// if we're adding an Item to a specific Title, pre-load some defaults:
		$idTitle = $arOpts['id.title'];
		$this->TitleID($idTitle);
		$rcTitle = $this->TitleRecord();
		$this->SupplierID($rcTitle->SupplierID());
		// TODO: maybe these defaults should also be read-only.
		$frmEdit->LoadRecord();
	    }
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate($doEdit);
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	//$arCtrls['!Status'] = $this->StatusText();

	$oTplt->VariableValues($arCtrls);
	$htForm = $oTplt->RenderRecursive();

	if ($doEdit) {
	    $htRec = <<<__END__
<form method=post>
$htForm
<input type=submit name="btnSave" value="Save">
<input type=reset value="Reset">
</form>
__END__;
	} else {
	    $htRec = $htForm;
	}
	// make a header for the record box
	$sHdr = $doEdit?
	  (($this->IsNew())?'Add New Item':'Edit Item #'.$this->GetKeyValue())
	  :
	  'specs for Item #'.$this->GetKeyValue();
	$arActs = array(
	  new clsActionLink_option(
	    array(),
	    'edit',	// link key (value)
	    NULL,	// group key
	    NULL,	// display when off
	    'cancel',	// display when on
	    'edit this item'	// pop-up description
	    )
	  );
	$htHdr = $oPage->ActionHeader($sHdr,$arActs);
	// put the form inside a box so the list indentations will work
	$out = <<<__END__
<table class=listing><tr><td>
$htHdr
$htRec
</td></tr></table>
__END__;
	if (!$this->IsNew()) {
	    $out .=
	      $this->StockListing()
	      .$this->OrderListing()
	      .$this->RestockListing()
	      .$this->EventListing()
	      .$this->MovementListing()
	      ;
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin
	2016-01-14 Presumably at some point this was rewritten to use Ferreteria forms (didn't exist in 2010).
      NOTES:
	"PriceBuy" was originally only editable if the item was *not* in print.
	  At the moment, this seems unnecessary (2015-11-22)
    */
    private $frmPage;
    private function PageForm() {

	if (is_null($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);
	    /*
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40)); //*/
	      $oField = new fcFormField_Text($oForm,'CatSfx');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Text($oForm,'CatNum');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>18));
	      $oField = new fcFormField_Time($oForm,'WhenCreated');
		$oField->ControlObject()->Editable(FALSE);	// TODO: make editable if zero
	      $oField = new fcFormField_Time($oForm,'WhenUpdated');
		$oField->ControlObject()->Editable(FALSE);

	      // bit fields:
	      
	      $oField = new fcFormField_BoolInt($oForm,'isAvail');
		$oField->ControlObject()->DisplayStrings('AVAIL');
	      $oField = new fcFormField_BoolInt($oForm,'isInPrint');
		$oField->ControlObject()->DisplayStrings('IN-PRINT');
	      $oField = new fcFormField_BoolInt($oForm,'isCloseOut');
		$oField->ControlObject()->DisplayStrings('CLOSEOUT');
	      $oField = new fcFormField_BoolInt($oForm,'isCurrent');
		$oField->ControlObject()->DisplayStrings('CURRENT');
	      $oField = new fcFormField_BoolInt($oForm,'isMaster');
		$oField->ControlObject()->DisplayStrings('MASTER');
	      $oField = new fcFormField_BoolInt($oForm,'isPulled');
		$oField->ControlObject()->DisplayStrings('PULLED');
		//$oField->OkToWrite(FALSE);
	      $oField = new fcFormField_BoolInt($oForm,'isDumped');
		$oField->ControlObject()->DisplayStrings('DUMPED');
		
	      $oField = new fcFormField_Num($oForm,'PriceSell');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      $oField = new fcFormField_Num($oForm,'PriceList');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      $oField = new fcFormField_Text($oForm,'Supp_CatNum');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	      $oField = new fcFormField_Num($oForm,'QtyMin_Stk');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>2));

	      // TODO: users without advanced permissions should not be able to edit these
	      $oField = new fcFormField_Num($oForm,'PriceBuy');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
		
	      $oField = new fcFormField_Num($oForm,'ID_ShipCost');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ShipCostTable()->DropDown_Records());
		
	      $oField = new fcFormField_Num($oForm,'ID_Supp');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->SupplierTable()->DropDown_Records());
		$oCtrl->AddChoice(NULL,'not set!');
		
	      $oField = new fcFormField_Num($oForm,'ID_Title');
		//$oField->OkToWrite(FALSE);
		$oField->SetDefault($this->TitleID());
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$sqlFilt = 'ID_Supp='.$this->SupplierID();
		$oCtrl->Records($this->TitleTable()->GetData_forDropDown($sqlFilt));
		
	      $oField = new fcFormField_Num($oForm,'ID_ItTyp');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ItemTypeTable()->DropDown_Records());
		$oCtrl->AddChoice(NULL,'not set!');
		
	      $oField = new fcFormField_Num($oForm,'ID_ItOpt');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ItemOptionTable()->GetData_forDropDown());
		$oCtrl->AddChoice(NULL,'no option');
		
	      $oField = new fcFormField_Text($oForm,'ItOpt_Descr');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

	      $oField = new fcFormField_Text($oForm,'ItOpt_Sort');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

	      $oField = new fcFormField_Text($oForm,'GrpCode');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));

	      $oField = new fcFormField_Text($oForm,'GrpDescr');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));

	      $oField = new fcFormField_Text($oForm,'GrpSort');
		//$oField->OkToWrite(FALSE);
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));		
		
	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));

	      $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate($doEdit) {
	if (empty($this->tpPage)) {
	    if ($doEdit) {
		$sStatus = <<<__END__
		
<table><tr>
<td>[[isAvail]]available
<br>[[isMaster]]master
<br>[[isInPrint]]in print
<br>[[isCloseOut]]closeout
<br>[[isCurrent]]current
<br>[[isPulled]]pulled
<br>[[isDumped]]dumped
</td>
</tr></table>
__END__;
	    } else {
		$sStatus = '[[isAvail]] [[isMaster]] [[isInPrint]] [[isCloseOut]] [[isCurrent]] [[isPulled]] [[isDumped]]';
	    }
	    $sTplt = <<<__END__
<ul>
  <li> <b>ID</b>: [[ID]]</li>
  <li> <b>Cat Suffix</b>: [[CatSfx]] <b>Full Cat #</b>: [[CatNum]]</li>
  <li> <b>Timestamps</b>:</li>
  <ul>
    <li> <b>created</b> &lt;[[WhenCreated]]&gt;</li>
    <li> <b>updated</b> &lt;[[WhenUpdated]]&gt;</li>
  </ul>
  <li> <b>Prices</b>:</li>
  <ul>
    <li> <b>Buy</b>: [[PriceBuy]]</li>
    <li> <b>Sell</b>: [[PriceSell]]</li>
    <li> <i><b>List</b>: [[PriceList]]</i></li>
    <li> <b>Shipping</b>: [[ID_ShipCost]]</li>
  </ul>
  <li> <b>Supplier</b>: [[ID_Supp]]</li>
  <li> <b>Title</b>: [[ID_Title]]</li>
  <li> <b>Item Type</b>: [[ID_ItTyp]]</li>
  <li> <b>Item Option</b>: [[ID_ItOpt]]</li>
  <ul>
    <li> <b>Descr</b>: [[ItOpt_Descr]]</li>
    <li> <b>Sort</b>: [[ItOpt_Sort]]</li>
  </ul>
  <li> <b>Group</b> - <b>code</b>: [[GrpCode]] <b>descr</b>: [[GrpDescr]] <b>sort</b>: [[GrpSort]]</li>
  <li> <b>Stk Min</b>: [[QtyMin_Stk]]</li>
  <li> <b>Status</b>: $sStatus</li>
  <li> <b>Supplier Cat #</b>: [[Supp_CatNum]]</li>
  <li> <b>Notes</b>:<br>[[Notes]]</li>
</ul>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
    */
    public function AdminSave() {	// this should go away and be replaced by $oForm->Save()
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    
    //--single--//
    //++related++//

    public function StockListing() {
	$out =
	  $this->Engine()->App()->Page()->ActionHeader('Stock')
	  .$this->StockLineTable()->Listing_forItem($this);
	return $out;
    }
    protected function OrderListing() {
	$t = $this->OrdersQuery();
	$out = $this->PageObject()->ActionHeader('Orders / Packages')
	  .$t->AdminRows_forLCItem($this->GetKeyValue())
	  ;
	return $out;
    }
    /* 2016-01-09 old version
    public function OrderListing() {
	$id = $this->GetKeyValue();
	$tbl = $this->OrderLineTable();
	$rs = $tbl->GetData('ID_Item='.$id);
	$out =
	  $this->Engine()->App()->Page()->ActionHeader('Orders')
	  .$rs->AdminTable_forItem();
	return $out;
    } */
    public function RestockListing() {
	$id = $this->GetKeyValue();
	
	$tbl = $this->RestockRequestItemTable();
	$rs = $tbl->GetData('ID_Item='.$id);
	$htReq = $rs->AdminRows_forLCItem();
	
	$tbl = $this->ReceivedRestockItemTable();
	$rs = $tbl->GetData('ID_Item='.$id);
	$htRcd = $rs->AdminRows_forLCItem();

	// TODO: write 2nd part of this
	
	$out =
	  $this->PageObject()->ActionHeader('Restocks')
	  .$htReq.$htRcd;
	/*
	$out =
	  $this->Engine()->App()->Page()->ActionHeader('Restocks')
	  .$rs->AdminRows_forLCItem('No restocks found for this item');
	*/
	return $out;
    }
    /*----
      RENDERS listing of stock movements for this item
    */
    public function MovementListing() {
	$id = $this->GetKeyValue();
	$tbl = $this->StockEventTable();
	return
	  $this->Engine()->App()->Page()->ActionHeader('Movement')
	  .$tbl->Listing_forItem($id);
    }

    //--related--//
    
    // -- ADMIN WEB UI -- //

// FUNCTION DEPRECATED - remove eventually
    public function StoreLink_HT($iText) {
	return '<a href="'.KWP_CAT.$this->TitleRecord()->URL_part().'" title="browse in store">'.$iText.'</a>';
    }
}
/* 2016-01-24 This doesn't seem to be used.
class clsAdminItems_info_Cat extends clsItems_info_Cat {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ActionKey('item');
    }
}
*/