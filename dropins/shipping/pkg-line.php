<?php
/*
  FILE: pkg-line.php - package lines (items in package)
  HISTORY:
    2014-04-20 extracted from pkg.php
*/

define('KS_ACTION_PKG_LINE_VOID','line.void');
define('KS_ACTION_PKG_LINES_EDIT','edit.lines');
define('KS_PATH_ARG_PKG_LINE','pkg.line');

class clsPkgLines extends clsDataTable_Menu {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_pkg_lines');
	  $this->KeyName('ID');
	  $this->ClassSng('clsPkgLine');
    }
}
class clsPkgLine extends clsVbzRecs {
    protected $frmEdit;	// must be declared to prevent overwriting when recordset advances

    // ++ FIELD ACCESS ++ //

    public function IsActive() {
	return (($this->QtyShipped() + $this->QtyReturned()) != 0);
    }
    /*----
      RETURNS: The quantity of items in the package, for the current line, when it was shipped.
    */
    public function QtyShipped() {
	return $this->Value('QtyShipped');
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
	return $this->Value('QtyReturned');
    }
    public function QtyKilled() {
	return $this->Value('QtyKilled');
    }
    public function QtyNotAvail() {
	return $this->Value('QtyNotAvail');
    }
    public function ItemID() {
	return $this->Value('ID_Item');
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

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: selling price
	if package line has no price, falls back to order line
	(if order line has no price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function PriceSell() {
	$prc = $this->Value('CostSale');
	if (is_null($prc)) {
	    $prc = $this->OrdLineObj()->PriceSell();
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
	return $this->OrdLineObj()->ShPerPkg();
    }
    /*----
      RETURNS: shipping per-item price
	if package line has no per-item price, falls back to order line
	(if order line has no per-item price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerItm() {
	$prc = $this->Value('CostShItm');
	if (is_null($prc)) {
	    $prc = $this->OrdLineObj()->ShPerItm();
	}
	return $prc;
    }
    public function AddToSum(&$arSum) {
	$idItem = $this->ItemID();
	if ($this->IsActive()) {
	    NzAdd($arSum[$idItem]['qty-shp'], $this->QtyShipped());
	    NzAdd($arSum[$idItem]['qty-rtn'], $this->QtyReturned());
	    NzAdd($arSum[$idItem]['qty-kld'], $this->QtyKilled());
	    NzAdd($arSum[$idItem]['qty-na'], $this->QtyNotAvail());
	}
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	if (clsDropinManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItems';
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PACKAGES,$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

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
    */
    protected $idPkg, $rcPkg;
    public function PkgObj() {
	throw new exception('PkgObj() is deprecated - use PackageRecord().');
    }
    protected function PackageRecord() {
	$idPkg = $this->Value('ID_Pkg');

	if (is_null($idPkg)) {
	    $this->rcPkg = NULL;
	} else {
	    $doLoad = TRUE;
	    if (!empty($this->idPkg)) {
		if ($this->idPkg == $idPkg) {
		    $doLoad = FALSE;
		}
	    }
	    if ($doLoad) {
		$this->rcPkg = $this->PackageTable($idPkg);
		$this->idPkg = $idPkg;
	    }
	}
	return $this->rcPkg;
    }
    /*----
      RETURNS: object for order line corresponding to this package line
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    protected $objOrdLine, $idOrdLine;
    public function OrdLineObj() {
	$id = $this->Value('ID_OrdLine');
	$doLoad = TRUE;
	if (!empty($this->idOrdLine)) {
	    if ($this->idOrdLine == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objOrdLine = $this->objDB->OrdLines()->GetItem($id);
	    $this->idOrdLine = $id;
	}
	return $this->objOrdLine;
    }

    // -- DATA RECORD ACCESS -- //
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

    // -- ACTIONS -- //
    // ++ ADMIN UI ++ //

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

	$doEdit = $oPage->PathArg(KS_ACTION_PKG_LINES_EDIT);
	$doSave = clsHTTP::Request()->GetBool('btnSaveItems');
	if ($doEdit || $doSave) {
	    if ($doSave) {
		//$sqlLoad = $this->sqlCreate;
		$ftSaveStatus = $this->AdminSave();
		$this->AdminRedirect($arAct,$ftSaveStatus);	// clear the action from the URL
		//$this->Reload();	// 2011-02-21 not tested
		//$this->Query($sqlLoad);
		$out .= $ftSaveStatus;
		$didEdit = TRUE;
	    }

	    if ($doEdit) {
		$arLink = $oPage->PathArgs(array('page','id'));
		$out .= '<form method=POST>';
	    }
	}
	$isEdit = FALSE;	// set if there is anything to save or revert
	if ($this->hasRows()) {
	    $out .= <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Line ID</th>
    <th>Item</th>
    <th>$ sale</th>
    <th>$ sh itm</th>
    <th><abbr title="quantity currently in the package">#pkg</abbr></th>
    <th><abbr title="quantity that were returned by customer">#rtn</th>
    <th><abbr title="quantity that have been cancelled"># cnc</abbr></th>
    <th># n/a</th>
    <th>notes</th>
  </tr>
__END__;

	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$out .= $this->AdminListRow($doEdit,$isOdd);
		$isOdd = !$isOdd;
	    }
	    if ($doEdit) {
		// when editing, show extra row for adding a new record
		$out .= $this->AdminListRow($doEdit,$isOdd,TRUE);
	    }
	    $out .= "\n</table>";
	    //$out .= "<small><b>cnc</b> = cancelled | <b>fr stk</b> = taken directly from stock</small>";
	    if ($doEdit) {
	    // form buttons
		$out .= '<br><input type=submit name="btnSaveItems" value="Save">';
		$isEdit = TRUE;
	    }
	} else {
	    $out = 'No items recorded in package.';
	}
	if ($doEdit) {
	// close editing form
	    if ($isEdit) {
		$out .= '<input type=reset value="Revert">';
	    }
	    $out .= "\n</form>";
	}
	return $out;
    }
    /*----
      ACTION: renders various editing controls (links) for the current record
    */
    protected function AdminCtrls() {
	$arLink = array(
	  'do'		=> KS_ACTION_PKG_LINE_VOID,
	  KS_PATH_ARG_PKG_LINE	=> $this->KeyValue()
	  );
	$ht = $this->PackageRecord()->AdminLink('V','void this line',$arLink);
	return "[$ht]";
    }
    /*----
      HISTORY:
	2011-02-19 Created
    */
    protected function AdminListRow($iEdit,$iOdd,$iNew=FALSE) {
	$isOdd = $iOdd;
	$doEdit = $iEdit;

	$out = NULL;

	// get current field values (if any)
	if ($iNew) {
	    $doEdit = TRUE;

	    $isActive = TRUE;
	    $htID 	= '<i>new</i>';
	    $idLine	= NULL;
	    $idItem	= NULL;
	    $ftItem	= '<i>new</i>';
	    $prcSale	= NULL;
	    $prcShItm	= NULL;
	    $qtySh	= NULL;	// qty shipped / currently in pkg
	    $qtyRt	= NULL;	// qty returned by customer
	    $qtyCa	= NULL;	// qty cancelled
	    $qtyNA	= NULL;	// qty not available
	    $txtNotes	= NULL;
	} else {
	    $row = $this->Values();
	    $rcItem = $this->ItemRecord();	// might be NULL for some defective pkgline records

	    $htID	= $row['ID'].$this->AdminCtrls();

	    $idLine	= $row['ID_OrdLine'];
	    $idItem	= $row['ID_Item'];
	    $prcSale	= $row['CostSale'];
	    $prcShItm	= $row['CostShItm'];
	    $qtySh	= $this->QtyShipped();
	    $qtyRt	= $this->QtyReturned();
	    $qtyCa	= $row['QtyKilled'];
	    $qtyNA	= $row['QtyNotAvail'];
	    $txtNotes	= $row['Notes'];
	    $isActive	= $this->IsActive();
	}
	if ($isActive) {
	    $cssClass = $isOdd?'odd':'even';
	} else {
	    $cssClass = 'void';
	}

	if ($doEdit) {
	    $frmEdit = $this->PageForm();
	    $out .= $frmEdit->RowPrefix();

	    // replace field values with editable versions
	    $ctLine = $frmEdit->RenderControl('ID_OrdLine');
	    $ctItem = $frmEdit->RenderControl('ID_Item');
	    $ctSale = $frmEdit->RenderControl('CostSale');
	    $ctShItm = $frmEdit->RenderControl('CostShItm');
	    $ctQtySh = $frmEdit->RenderControl('QtyShipped');
	    $ctQtyRt = $frmEdit->RenderControl('QtyReturned');
	    $ctQtyCa = $frmEdit->RenderControl('QtyKilled');
	    $ctQtyNA = $frmEdit->RenderControl('QtyNotAvail');
	    $ctNotes = $frmEdit->RenderControl('Notes');
	} else {
	    if (is_null($rcItem)) {
		$strItem	= 'n/a';
		$ftItem		= 'n/a';
	    } else {
		$strItem	= $rcItem->CatNum();
		$ftItem		= $rcItem->AdminLink($strItem).' '.$rcItem->FullDescr();
	    }

	    $ctLine = $idLine;
	    $ctItem = $ftItem;
	    $ctSale = $prcSale;
	    $ctShItm = $prcShItm;
	    $ctQtySh = $qtySh;
	    $ctQtyRt = $qtyRt;
	    $ctQtyCa = $qtyCa;
	    $ctQtyNA = $qtyNA;
	    $ctNotes = $txtNotes;
	}

	// render line
	$out .= <<<__END__
  <tr class="$cssClass">
    <td>$htID</td>
    <td>$ctLine</td>
    <td>$ctItem</td>
    <td align=center>$ctSale</td>
    <td align=center>$ctShItm</td>
    <td align=center>$ctQtySh</td>
    <td align=center>$ctQtyRt</td>
    <td align=center>$ctQtyCa</td>
    <td align=center>$ctQtyNA</td>
    <td>$ctNotes</td>
  </tr>
__END__;
	return $out;
    }
    /*----
      HISTORY:
	2011-02-20 Created
	2014-04-10 Modified for standalone admin UI
    */
    private function PageForm() {
	if (is_null($this->oForm)) {
	    $oForm = new clsForm_recs_indexed($this);
	    $oForm->AddField(new clsFieldNum('ID_OrdLine'),	new clsCtrlHTML(array('size'=>5)));
	    $oForm->AddField(new clsFieldNum('ID_Item'),	new clsCtrlHTML(array('size'=>5)));
	    $oForm->AddField(new clsFieldNum('CostSale'),	new clsCtrlHTML(array('size'=>5)));
	    $oForm->AddField(new clsFieldNum('CostShItm'),	new clsCtrlHTML(array('size'=>5)));
	    $oForm->AddField(new clsFieldNum('QtyShipped'),	new clsCtrlHTML(array('size'=>1)));
	    $oForm->AddField(new clsFieldNum('QtyReturned'),	new clsCtrlHTML(array('size'=>1)));
	    $oForm->AddField(new clsFieldNum('QtyKilled'),	new clsCtrlHTML(array('size'=>1)));
	    $oForm->AddField(new clsFieldNum('QtyNotAvail'),	new clsCtrlHTML(array('size'=>1)));
	    $oForm->AddField(new clsField('Notes'),		new clsCtrlHTML(array('size'=>20)));

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    protected function AdminSave() {
	$out = $this->PageForm()->Save();
	return $out;
    }

    // -- ADMIN UI -- //
}