<?php
/*
  FILE: pkg-line.php - package lines (items in package)
  HISTORY:
    2014-04-20 extracted from pkg.php
*/

define('KS_ACTION_PKG_LINE_VOID','line.void');
define('KS_PATH_ARG_PKG_LINE','pkg.line');	// when a pline ID must be included in a different page type

class clsPkgLines extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ord_pkg_lines';
    }
    // CEMENT
    protected function SingularName() {
	return 'clsPkgLine';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_PKG_LINE;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	// nothing yet
    }
    public function AdminList_forPackage($idPkg) {
	$rs = $this->SelectRecords('ID_Pkg='.$idPkg);
	$rs->PackageID_default($idPkg);	// for new rows
	return $rs->AdminList();
    }
    
    // -- ADMIN UI -- //
    
}
class clsPkgLine extends vcAdminRecordset {
    use ftFrameworkAccess;

    // ++ CALLBACKS ++ //
    
    public function MenuExec() {
	return $this->AdminPage();
    }
    
    // -- CALLBACKS -- //
    // ++ TRAIT AUXILIARY ++ //
    
    public function SelfLink_name() {
	$sName = $this->NameString();
	return $this->SelfLink($sName);
    }
    
    // -- TRAIT AUXILIARY -- //
    // ++ FIELD DEFAULTS ++ //

    private $idPkg;
    public function PackageID_default($idPkg=NULL) {
	if (!is_null($idPkg)) {
	    if (empty($idPkg)) {
		throw new exception("Default Package ID is being set to [$idPkg] (BLANK).");
	    }
	    $this->idPkg = $idPkg;
	}
	return $this->idPkg;
    }

    // -- FIELD DEFAULTS -- //
    // ++ FIELD VALUES ++ //

    public function OrderLineID() {
	return $this->GetFieldValue('ID_OrdLine');
    }
    /*----
      RETURNS: The quantity of items in the package, for the current line, when it was shipped.
    */
    public function QtyShipped() {
	return $this->GetFieldValue('QtyShipped');
    }
    /*----
      RETURNS: The quantity of items actually present in the package for the current line
	This is currently the same as QtyShipped, but that could (theoretically) change.
      HISTORY:
	2011-10-08 created for returning items to stock
	2014-06-12 renamed from ItemQty() -> QtyShipped() because I was rewriting
	  the returning-items-to-stock code and expected it to be called that.
	2014-06-17 Created QtyInPackage() as an alias of QtyShipped() and moved this description
	  there, because it more accurately applies.
    */
    public function QtyInPackage() {	// ALIAS for now
	return $this->QtyShipped();
    }
    /*----
      RETURNS: The quantity of items that are or were in a return package
    */
    public function QtyReturned() {
	return $this->GetFieldValue('QtyReturned');
    }
    public function QtyKilled() {
	return $this->GetFieldValue('QtyKilled');
    }
    public function QtyNotAvail() {
	return $this->GetFieldValue('QtyNotAvail');
    }
    public function ItemID() {
	return $this->GetFieldValue('ID_Item');
    }
    public function CostSale() {
	return $this->Value('CostSale');
    }
    public function ShipPerItem() {
	return $this->Value('CostShItm');
    }
    public function ShipPerPackage() {
	return $this->Value('CostShPkg');
    }
    public function NotesText() {
	return $this->GetFieldValue('Notes');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: The quantity the customer should pay for
      NOTE: It used to be that we had a separate column for items shipped for free.
	That has been done away with, but in case of future changes this method will
	always be unambiguously the quantity to charge for.
    */
    protected function QtySold() {
	return $this->QtyShipped();
    }
    protected function NameString() {
	$sPkg = $this->PackageRecord()->NameString();
	return $sPkg.'-'.$this->ItemID();
    }
    public function HasOrderLine() {
	return !is_null($this->OrderLineID());
    }
    public function IsActive() {
	return (($this->QtyShipped() + $this->QtyReturned()) != 0);
    }
    // PUBLIC so Table can set it
    // 2017-06-30 This will throw an error now when used -- maybe nobody is using it?
    public function SetPackageID($id=NULL) {
	$this->SetValue('ID_Pkg',$id);
    }
    protected function GetPackageID() {
	$idPkg = $this->GetFieldValueNz('ID_Pkg');
	if (is_null($idPkg)) {
	    $idPkg = $this->PackageID_default();
	}
	return $idPkg;
    }
    /*----
      RETURNS: selling price
	if package line has no price, falls back to order line
	(if order line has no price, falls back to catalog item)
      TODO: It's sloppy if we still need this. There should be a way to update the package line's
	price from the item record, and the "charge for this package" process should display an error
	if a package line's price isn't set.
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function PriceSell() {
	$prc = $this->Value('CostSale');
	if (is_null($prc)) {
	    $prc = $this->OrderLineRecord()->PriceSell();
	}
	return $prc;
    }
    /*----
      RETURNS: shipping per-package price
	currently, package line does not store a per-package price, so falls back to order item
	(if order line has no per-item price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerPkg() {
	throw new exception('2016-09-04 Is anyone still calling this?');
	return $this->OrderLineRecord()->ShPerPkg();
    }
    /*----
      RETURNS: shipping per-item price
	if package line has no per-item price, falls back to order line
	(if order line has no per-item price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerItm() {
	throw new exception('2016-09-04 Is anyone still calling this?');
	$prc = $this->Value('CostShItm');
	if (is_null($prc)) {
	    $prc = $this->OrderLineRecord()->ShPerItm();
	}
	return $prc;
    }
    /*----
      RETURNS: Line total for item sale (item price x quantity sold)
    */
    protected function LineAmount_ItemCost() {
	return $this->CostSale() * $this->QtySold();
    }
    /*----
      RETURNS: Line total for per-item s/h (per-item s/h x quantity sold)
    */
    protected function LineAmount_ItemShip() {
	return $this->ShipPerItem() * $this->QtySold();
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ CALCULATIONS ++ //

    /*----
      HISTORY:
	2016-03-01 This used to only count active rows ($this->IsActive()), but then we get "missing
	  data" for package lines with all zero quantities. We now include all lines in the summation
	  in order to get package data for every line in the order, even if it's all zeros.
    */
    public function AddToSum(&$arSum) {
	$idItem = $this->ItemID();
	fcArray::NzSum($arSum[$idItem],'qty-shp',$this->QtyShipped());
	fcArray::NzSum($arSum[$idItem],'qty-rtn',$this->QtyReturned());
	fcArray::NzSum($arSum[$idItem],'qty-kld',$this->QtyKilled());
	fcArray::NzSum($arSum[$idItem],'qty-na',$this->QtyNotAvail());
    }

    // -- CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	if (fcDropinManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return KS_LOGIC_CLASS_LC_ITEMS;
	}
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function PackageTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_PACKAGES,$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    protected function OrderLineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDER_LINES,$id);
    }
    protected function TransactionTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDER_TRXS,$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function ItemRecord() {
	$id = $this->Value('ID_Item');
	if (is_null($id) || ($id == 0)) {
	    return NULL;
	} else {
	    return $this->ItemTable($id);
	}
    }
    /*----
      HISTORY:
	2011-10-08 created for unpacking packages, but seems generally useful
	2015-04-21 updated from simplified code on Rizzo
    */
    protected $rcPkg;
    public function PkgObj() {
	throw new exception('PkgObj() is deprecated - use PackageRecord().');
    }
    public function PackageRecord() {
	$idPkg = $this->GetPackageID();
	$rcPkg = $this->rcPkg;

	if (is_null($rcPkg)) {
	    $doLoad = TRUE;
	} elseif ($idPkg == $rcPkg->GetKeyValue()) {
	    $doLoad = FALSE;
	} else {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->rcPkg = $this->PackageTable($idPkg);
	}
	return $this->rcPkg;
    }
    /*----
      RETURNS: object for order line corresponding to this package line
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    private $rcOrdLine, $idOrdLine;
    protected function OrderLineRecord() {
	$id = $this->Value('ID_OrdLine');
	$doLoad = TRUE;
	if (!empty($this->idOrdLine)) {
	    if ($this->idOrdLine == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcOrdLine = $this->OrderLineTable($id);
	    $this->idOrdLine = $id;
	}
	return $this->rcOrdLine;
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Just removes (subtracts) the given quantity from the current line and saves it.
	No event logging, no sanity-checking.
    */
    public function Remove($nQty) {
	$qNew = $this->QtyInPackage()-$nQty;
	$this->QtyInPackage($qNew);
	$arUpd = array(
	  'QtyShipped'	=> $qNew,
	  );
	$this->Update($arUpd);
    }
    /*----
      ACTION: Updates the quantity in the package.
	Does not adjust quantities elsewhere; assumes caller is taking care of that.
      HISTORY:
	2011-10-08 written for package return
    */
    public function DoVoid($iDescr) {
	$rcPkg = $this->PackageRecord();
	$ar = array(
	  'descr'	=> SQLValue('Voiding line: '.$iDescr),
	  'code'	=> 'VOID',
	  'where'	=> __METHOD__,
	  );
	$rcPkg->StartEvent($ar);

	$ar = array(
	  'WhenVoided'	=> 'NOW()',
	  );
	$this->Update($ar);

	$rcPkg->FinishEvent();
    }
    /*----
      ACTION: Calculates charges for the current package-line recordset, and creates transactions for them.
	Charges calculated:
	  * SALE TOTAL
	  * PER-ITEM S/H TOTAL
	  * PER-PKG S/H AMOUNT
      INPUT: recordset (self)
      RETURNS: array of provisioned Transaction record objects to save
      PUBLIC so Package can call it (Package generates recordset, AddItemCharges operates on recordset).
    */
    public function AddItemCharges() {
	$qRows = $this->RowCount();
	$nItemSaleTot = 0;
	$nItemShipTot = 0;
	$nPkgShip = 0;
	$sSaleText = NULL;
	$sShipText = NULL;
	while ($this->NextRow()) {
	    $rcItem = $this->ItemRecord();

	    // accumulate item sale total
	    $nItemSale = $this->LineAmount_ItemCost();
	    $nItemSaleTot += $nItemSale;
	    	    
	    // accumulate item s/h total
	    $nItemShip = $this->LineAmount_ItemShip();
	    $nItemShipTot += $nItemShip;

	    // assemble descriptions for totals
	    if (!is_null($sSaleText)) {
		$sSaleText .= '+';
		$sShipText .= '+';
	    }
	    if ($qRows > 1) {
		$sSaleText .= '(';
		$sShipText .= '(';
	    }
	    $qty = $this->QtySold();
	    $sSaleText .= $qty.'x'.$this->CostSale();
	    $sShipText .= $qty.'x'.$this->ShipPerItem();
	    if ($qRows > 1) {
		$sSaleText .= ')';
		$sShipText .= ')';
	    }

	    // calculate package s/h amount
	    $nPkgShip_line = $this->ShipPerPackage();
	    if ($nPkgShip_line > $nPkgShip) {
		$nPkgShip = $nPkgShip_line;
	    }
	}
	
	$sDescr = 'SALE TOTAL: '.$sSaleText;
	$arTrx[] = $this->SpawnTransaction(vcraOrderTrxType::ITEM,$nItemSaleTot,$sDescr);

	$sDescr = 'PER-ITEM S/H: '.$sShipText;
	$arTrx[] = $this->SpawnTransaction(vcraOrderTrxType::SH_EA,$nItemShipTot,$sDescr);
	
	$sDescr = 'PER-PACKAGE S/H';
	$arTrx[] = $this->SpawnTransaction(vcraOrderTrxType::SH_PK,$nPkgShip,$sDescr);
	
	return $arTrx;
    }
    protected function SpawnTransaction(
      $idType,
      $nAmount,
      $sText
      ) {
	$rc = $this->TransactionTable()->SpawnItem();
	$rc->Provision($idType,$nAmount,$sText);
	return $rc;
    }

    // -- ACTIONS -- //
    // ++ ADMIN UI ++ //
    
    //++common++//

    private $oForm;
    private function RecordForm() {
	if (is_null($this->oForm)) {
	    // FORMS v2
	    $oForm = new fcForm_DB($this);
	    /* 2016-03-06 These should probably not be editable, except maybe by admins (cross bridge upon arrival).
	      $oField = new fcFormField_Num($oForm,'ID_Pkg');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ID_Item');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ID_OrdLine');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      //*/
	      // 2016-09-19 apparently necessary for editing multiple Package Line records (in rows)
	      $oField = new fcFormField_Num($oForm,'ID_Item');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
		//...which currently doesnt work and is therefore disabled (no menu entry)

	      $oField = new fcFormField_Num($oForm,'CostSale');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'CostShItm');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'CostShPkg');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'QtyShipped');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyShipped');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyReturned');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyKilled');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyNotAvail');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>1));
	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    
    //--common--//
    //++single++//
    
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=listing>
  <tr><td align=right><b>ID</b>:</td>		<td><%ID%></td></tr>
  <tr><td align=right><b>Package</b>:</td>	<td><%Package%></td></tr>
  <tr><td align=right><b>Item</b>:</td>		<td><%ID_Item%></td></tr>
  <tr><td align=right><b>Order Line</b>:</td>	<td><%OrdLine%></td></tr>
  
  <tr><td colspan=2><b>Charges</b>:</td></tr>
  <tr><td align=right><b>Sale amount</b>:</td>		<td><%CostSale%></td></tr>
  <tr><td align=right><b>Per-item s/h</b>:</td>		<td><%CostShItm%></td></tr>
  <tr><td align=right><b>Per-pkg s/h</b>:</td>		<td><%CostShPkg%></td></tr>
  
  <tr><td colspan=2><b>Quantities</b>:</td></tr>
  <tr><td align=right><b>Shipped</b>:</td>		<td><%QtyShipped%></td></tr>
  <tr><td align=right><b>Returned</b>:</td>		<td><%QtyReturned%></td></tr>
  <tr><td align=right><b>Cancelled</b>:</td>		<td><%QtyKilled%></td></tr>
  <tr><td align=right><b>Not Available</b>:</td>	<td><%QtyNotAvail%></td></tr>

  <tr><td colspan=2><b>Notes</b>:</td></tr>
  <tr><td colspan=2><%Notes%></td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('<%','%>',$sTplt);
	}
	return $this->tpPage;
    }
    protected function AdminPage() {
	$oPage = $this->PageObject();
	
	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');

	$frm = $this->RecordForm();
	if ($doSave) {
	    $frm->Save();
	    $sMsgs = $frm->MessagesStrings();
	    $this->SelfRedirect(NULL,$sMsgs);
	}
	$id = $this->GetKeyValue();
	$oPage->Skin()->SetBrowserTitle('PL#'.$id);
	$oPage->Skin()->SetPageTitle('Package Line #'.$id);

	$arActs = array(
	  new clsActionLink_option(array(),    // an "edit" link
	    'edit',			// $sLinkKey
	    NULL,			// $sGroupKey
	    NULL,			// $sDispOff
	    NULL,			// $sDispOn
	    'edit this package line'	// $sDescr - shows as hover-over text
	    ),
	  );
	$oPage->PageHeaderWidgets($arActs);
	
	$frm->LoadRecord();
	
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	$arCtrls['Package'] = $this->PackageRecord()->SelfLink_name();
	$arCtrls['ID_Item'] = $this->ItemRecord()->SelfLink_name();
	$arCtrls['OrdLine'] = $this->OrderLineRecord()->SelfLink();

	$out = NULL;
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	}
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '</form>';
	}
	if (!$this->IsNew()) {
	    // show any dependent listings
	    $out .= $this->EventListing();
	}
	
	return $out;
}
    
    //--single--//
    //++multi++//

    public function AdminList() {
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

	$sDo = $oPage->PathArg('do');

	$doLineVoid = ($sDo == KS_ACTION_PKG_LINE_VOID);
	if ($doLineVoid) {
	    $idLine = $oPage->PathArg(KS_PATH_ARG_PKG_LINE);
	    $rcLine = $this->Table()->GetItem($idLine);
	    $rcLine->DoVoid('direct void');
	    $arAct = array('do'=>FALSE);
	    $this->AdminRedirect($arAct);	// clear the action from the URL
	}

	$doSave = clsHTTP::Request()->GetBool('btnSaveItems');
	$doEdit = $oPage->PathArg(KS_ACTION_PKG_LINES_EDIT);
	$doAdd = $oPage->PathArg(KS_ACTION_PKG_LINES_ADD);
	$doForm = $doEdit || $doAdd;
	if ($doEdit || $doSave) {
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
		$this->AdminRedirect($arAct,$ftSaveStatus);	// clear the action from the URL
		$out .= $ftSaveStatus;
		$didEdit = TRUE;
	    }
	}
	if ($doForm) {
	    //$arLink = $oPage->PathArgs(array('page','id'));
	    $out .= '<form method=POST>';
	}
	$isEdit = FALSE;	// set if there is anything to save or revert
	if ($this->hasRows()) {
	    $out .= static::AdminListHeader();
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$out .= $this->AdminListRow($doEdit,$isOdd);
		$isOdd = !$isOdd;
	    }
	    if ($doAdd) {
		// adding: show extra row for adding a new record
		$out .= $this->AdminListRow($doEdit,$isOdd,TRUE);
	    }
	    $out .= "\n</table>";
	    if ($doForm) {
	    // form buttons
		$out .= '<br><input type=submit name="btnSaveItems" value="Save">';
		$isEdit = TRUE;
	    }
	} else {
	    $out = 'No items recorded in package.';
	}
	if ($doForm) {
	// close editing form
	    if ($isEdit) {
		$out .= '<input type=reset value="Reset">';
	    }
	    $out .= "\n</form>";
	}
	return $out;
    }
    static protected function AdminListHeader() {
	$out = <<<__END__
<table class=listing>
  <tr>
    <th colspan=3></th>
    <th colspan=3>$ charged</th>
    <th colspan=4>quantities</th>
  </tr>
  <tr>
    <th>ID</th>
    <th>Line ID</th>
    <th>Item</th>
    <th>sale</th>
    <th>s/h itm</th>
    <th>s/h pkg</th>
    <th><abbr title="quantity currently in the package">#pkg</abbr></th>
    <th><abbr title="quantity that were returned by customer">#rtn</abbr></th>
    <th><abbr title="quantity that have been cancelled">#cnc</abbr></th>
    <th># n/a</th>
    <th>notes</th>
  </tr>
__END__;
	return $out;
    }
    /*----
      HISTORY:
	2011-02-19 Created
    */
    protected function AdminListRow($doEdit,$isOdd,$isNew=FALSE) {
	$out = NULL;

	// get current field values (if any)
	$isActive = $this->IsActive();	// does this record represent something actually happening?
	if ($isActive) {
	    $cssClass = $isOdd?'odd':'even';
	} else {
	    $cssClass = 'void';
	}
	$frmEdit = $this->RecordForm();
	if ($isNew) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->LineTemplate();
	
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	$arCtrls['OrdLine'] = $this->OrderLineRecord()->SelfLink_name();
	$arCtrls['!cssClass'] = $cssClass;
	if (!$doEdit) {
	    $rcItem = $this->ItemRecord();
	    if (is_null($rcItem)) {	// might be NULL for some defective pkgline records
		$arCtrls['ID_Item']	= 'n/a';
	    } else {
		$arCtrls['ID_Item']	= $rcItem->SelfLink_name().' '.$rcItem->FullDescr();
	    }
	}

	$oTplt->VariableValues($arCtrls);
	$out = $oTplt->Render();

	return $out;
    }

    /*----
      NOTE: Controls need to be as narrow as possible, else the form has a tendency to
	bump down below the nav menu.
      ASSUMES that we don't need to display the Package ID. If this is used for displaying
	Package-Line lists in contexts other than Packages, it will need to do that.
      HISTORY:
	2011-02-20 Created
	2014-04-10 Modified for standalone admin UI
	2015-04-21 modified for forms v2 (from code on Rizzo)
	2016-03-01 Updated control construction.
    */
    private $tpLine;
    protected function LineTemplate() {
	if (empty($this->tpLine)) {
	    $sTplt = <<<__END__
  <tr class="<%!cssClass%>">
    <td align=right><%ID%></td>
    <td><%OrdLine%></td>
    <td><%ID_Item%></td>
    <td align=center><%CostSale%></td>
    <td align=center><%CostShItm%></td>
    <td align=center><%CostShPkg%></td>
    <td align=center><%QtyShipped%></td>
    <td align=center><%QtyReturned%></td>
    <td align=center><%QtyKilled%></td>
    <td align=center><%QtyNotAvail%></td>
    <td><%Notes%></td>
  </tr>
__END__;
	    $this->tpLine = new fcTemplate_array('<%','%>',$sTplt);
	}
	return $this->tpLine;
    }
    // 2015-02-16 modified for change in Save()
    protected function AdminSave() {
	$oForm = $this->RecordForm();
	$oForm->Save();
	$sMsgs = $oForm->MessagesString();
	$rcPkg = $this->PackageRecord();
	$rcPkg->SelfRedirect(NULL,$sMsgs);
    }

    //--multi--//
    
    // -- ADMIN UI -- //
}