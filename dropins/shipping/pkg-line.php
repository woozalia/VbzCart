<?php
/*
  FILE: pkg-line.php - package lines (items in package)
  HISTORY:
    2014-04-20 extracted from pkg.php
*/

define('KS_ACTION_PKG_LINE_VOID','line.void');
define('KS_PATH_ARG_PKG_LINE','pkg.line');	// when a pline ID must be included in a different page type

class clsPkgLines extends clsDataTable_Menu {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_pkg_lines');
	  $this->KeyName('ID');
	  $this->ClassSng('clsPkgLine');
	  $this->ActionKey(KS_ACTION_PKG_LINE);
    }
    public function AdminList_forPackage($idPkg) {
	$rs = $this->GetData('ID_Pkg='.$idPkg);
	$rs->PackageID_default($idPkg);	// for new rows
	return $rs->AdminList();
    }
}
class clsPkgLine extends clsVbzRecs {
    protected $frmEdit;	// must be declared to prevent overwriting when recordset advances

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
    // PUBLIC so Table can set it
    public function PackageID($id=NULL) {
	$idPkg = NULL;
	if ($this->HasValue('ID_Pkg')) {
	    $idPkg = $this->Value('ID_Pkg',$id);
	}
	if (is_null($idPkg)) {
	    $idPkg = $this->PackageID_default();
	}
	return $idPkg;
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
	2015-04-21 updated from simplified code on Rizzo
    */
    protected $rcPkg;
    public function PkgObj() {
	throw new exception('PkgObj() is deprecated - use PackageRecord().');
    }
    protected function PackageRecord() {
	$idPkg = $this->PackageID();
	$rcPkg = $this->rcPkg;

	if (is_null($rcPkg)) {
	    $doLoad = TRUE;
	} elseif ($idPkg == $rcPkg->KeyValue()) {
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
    static protected function AdminListHeader() {
	$out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Line ID</th>
    <th>Item</th>
    <th>$ sale</th>
    <th>$ sh itm</th>
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
    protected function AdminListRow($iEdit,$iOdd,$isNew=FALSE) {
	$isOdd = $iOdd;
	$doEdit = $iEdit;

	$out = NULL;

	// get current field values (if any)
	if ($isNew) {
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
	$sTplt = <<<__END__
__END__;
	$frmEdit = $this->PageForm();
	if ($isNew) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['!cssClass'] = $cssClass;
	$oTplt->VariableValues($arCtrls);
	if (!$doEdit) {
	    if (is_null($rcItem)) {
		$htItem	= 'n/a';
	    } else {
		$sItem		= $rcItem->CatNum();
		$htItem		= $rcItem->AdminLink($sItem).' '.$rcItem->FullDescr();
	    }
	    $oTplt->VariableValue('ID_Item',$htItem);
	}
	$oTplt->VariableValue('ID',$htID);
	$out = $oTplt->Render();
	return $out;
    }

    /* 2015-04-21 old version
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
    } */
    /*----
      HISTORY:
	2011-02-20 Created
	2014-04-10 Modified for standalone admin UI
	2015-04-21 modified for forms v2 (from code on Rizzo)
    */
    private $oForm;
    private function PageForm() {
	if (is_null($this->oForm)) {
	    // FORMS v2
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);
	      $oForm->NewValues(array('ID_Pkg'=>$this->PackageID()));
	      $oField = new fcFormField_Num($oForm,'ID_OrdLine');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ID_Item');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'CostSale');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'CostShItm');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'QtyShipped');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyShipped');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyReturned');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyKilled');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>1));
	      $oField = new fcFormField_Num($oForm,'QtyNotAvail');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>1));
	      $oField = new fcFormField($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>20));

	    /* FORMS v1
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
	    $oForm->NewVals(array('ID_Pkg'=>$this->PackageID()));
	    */
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
  <tr class="<%!cssClass%>">
    <td align=right><%ID%></td>
    <td><%ID_OrdLine%></td>
    <td><%ID_Item%></td>
    <td align=center><%CostSale%></td>
    <td align=center><%CostShItm%></td>
    <td align=center><%QtyShipped%></td>
    <td align=center><%QtyReturned%></td>
    <td align=center><%QtyKilled%></td>
    <td align=center><%QtyNotAvail%></td>
    <td><%Notes%></td>
  </tr>
__END__;
	    $this->tpPage = new fcTemplate_array('<%','%>',$sTplt);
	}
	return $this->tpPage;
    }
    // 2015-02-16 modified for change in Save()
    protected function AdminSave() {
	$oForm = $this->PageForm();
	if ($oForm->Save()) {
	    $this->AdminRedirect();
	}
	return $oForm->htMsg;
    }

    // -- ADMIN UI -- //
}