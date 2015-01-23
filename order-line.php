<?php
/*
  HISTORY:
    2014-09-28 split off from orders.php
*/
class clsOrderLines extends clsVbzTable {
    const TableName='ord_lines';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsOrderLine');
    }
    public function Find_byOrder_andItem($idOrder,$idItem) {
 	$sqlFilt = "(ID_Order=$idOrder) AND (ID_Item=$idItem)";
	$rc = $this->GetData($sqlFilt);
	$rc->NextRow();		// load the row
	return $rc;
    }
}
class clsOrderLine extends clsVbzRecs {

    // ++ SETUP ++ //

    public function Init_fromCartLine(clsShopCartLine $rcCLine) {
	// some fields get copied over directly
	$arNames = array(
	  'Seq'		=> 'Seq',
	  'ID_Item'	=> 'ID_Item',
	  'Qty'		=> 'QtyOrd',
//	  'CatNum'	=> 'CatNum',
//	  'PriceItem'	=> 'Price',
//	  'ShipPkgDest'	=> 'ShipPkg',
//	  'ShipItmDest'	=> 'ShipItm',
//	  'DescText'	=> 'Descr',
	  //'DescHtml'	=> 'DescrHTML'	// we may eventually add this field
	  );
	foreach($arNames as $srce => $dest) {
	    $val = $rcCLine->Value($srce);
	    $this->Value($dest,$val);
	}
	// these are looked up at checkout time
	$rcItem = $rcCLine->ItemRecord();
	$this->CatNum($rcCLine->CatNum());
	$this->ShPerPkg($rcCLine->ItemShip_perPkg());
	$this->ShPerItm($rcCLine->ItemShip_perPkg());
	$this->Descr($rcCLine->DescText());
    }

    // -- SETUP -- //
    // ++ DATA FIELDS ++ //

    protected function QtyOrd() {
	return $this->Value('QtyOrd');
    }
    /*----
      RETURNS: selling price
	if order line has no price, falls back to catalog item
      HISTORY:
	2011-03-23 created for "charge for package" process
	2014-08-22 modified to allow writing
    */
    public function PriceSell($prc=NULL) {
	$prc = $this->ValueNz('Price',$prc);
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->PriceSell();
	    $this->Value('Price',$prc);
	}
	return $prc;
    }
    /*----
      RETURNS: shipping per-package price
	if order line has no per-package price, falls back to catalog item
      HISTORY:
	2011-03-23 created for "charge for package" process
	2014-08-22 modified to allow writing
    */
    public function ShPerPkg($prc=NULL) {
	$prc = $this->Value('ShipPkg',$prc);
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->ShPerPkg();
	    $this->Value('ShipPkg',$prc);
	}
	return $prc;
    }
    /*----
      RETURNS: shipping per-item price -- defaults to catalog item's data
	unless specified in package line
      HISTORY:
	2011-03-23 created for "charge for package" process
	2014-08-22 modified to allow writing
    */
    public function ShPerItm($prc=NULL) {
	$prc = $this->Value('ShipItm',$prc);
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->ShPerItm();
	    $this->Value('ShipItm',$prc);
	}
	return $prc;
    }
    protected function CatNum($sVal=NULL) {
	$val = $this->Value('CatNum',$sVal);
	return $val;
    }
    protected function Descr($sText=NULL) {
	$val = $this->ValueNz('Descr',$sText);
	if (is_null($sText)) {
	    $val = $this->ItemRecord()->Descr();
	    $this->Value('Descr',$val);
	}
	return $val;
    }
    protected function DescrText() {
	return $this->Value('Descr');
    }
    protected function DescrHTML() {
	return $this->ItemRecord()->DescLong_ht();
    }

    // -- DATA FIELDS -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function PriceSell_forQty() {
	return $this->PriceSell() * $this->QtyOrd();
    }
    protected function ShPerItm_forQty() {
	return $this->ShPerItm() * $this->QtyOrd();
    }

    // -- FIELD CALCULATIONS -- //
    // ++ FIELD ARRAYS ++ //

    /*----
      RETURNS: array of calculated values for this order line
	array[sh-pkg]: shipping charge per package
	array[sh-itm.qty]: shipping charge per item, adjusted for quantity ordered
	array[cost-sell.qty]: selling cost, adjusted for quantity ordered
      USED BY: so far, only admin functions (shopping functions use Cart objects, not Order)
    */
    public function FigureStats() {
	$qty = $this->QtyOrd();
	if ($qty != 0) {
	    $prcShPkg = $this->ShPerPkg();
	} else {
	    // none of this item in package, so don't require this minimum
	    $prcShPkg = 0;
	}
	$arOut['sh-pkg'] = $prcShPkg;
	$arOut['sh-itm.qty'] = $this->ShPerItm() * $qty;
	$arOut['cost-sell.qty'] = $this->PriceSell() * $qty;
	return $arOut;
    }
    /*----
      ACTION: Figures totals for the current rowset
      USED BY: so far, only admin functions (shopping functions use Cart objects, not Order)
      RETURNS: array in same format as FigureStats(), except with ".qty" removed from index names
    */
    public function FigureTotals() {
	$arSum = NULL;
	while ($this->NextRow()) {
	    $ar = $this->FigureStats();

	    $prcShItmSum = nzArray($arSum,'sh-itm',0);
	    $prcShPkgMax = nzArray($arSum,'sh-pkg',0);
	    $prcSaleSum = nzArray($arSum,'cost-sell',0);

	    $prcShItmThis = $ar['sh-itm.qty'];
	    $prcShPkgThis = $ar['sh-pkg'];
	    $prcSaleThis = $ar['cost-sell.qty'];

	    $arSum['sh-itm'] = $prcShItmSum + $prcShItmThis;
	    $arSum['cost-sell'] = $prcSaleSum + $prcSaleThis;
	    if ($prcShPkgMax < $prcShPkgThis) {
		$prcShPkgMax = $prcShPkgThis;
	    }
	    $arSum['sh-pkg'] = $prcShPkgMax;
	}
	return $arSum;
    }

    // -- FIELD ARRAYS -- //
    // ++ CLASS NAMES ++ //

    protected function ItemsClass() {
	return 'clsItems';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ DATA RECORDS ++ //

    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
    */
    private $rcItem, $idItem;
    public function ItemObj() {
	throw new exception('ItemObj() is deprecated; call ItemRecord().');
    }
    public function ItemRecord() {
	$doLoad = TRUE;
	$id = $this->Value('ID_Item');
	if (isset($this->idItem)) {
	    if ($this->idItem == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcItem = $this->ItemTable($id);
	    $this->idItem = $id;
	}
	return $this->rcItem;
    }

    // -- DATA RECORDS -- //
    // ++ WEB SHOPPING UI ++ //

    /*----
      PUBLIC because Cart object calls it to display a static cart at checkout
      RETURNS: populated rendering object for the current line
    */
    public function GetRenderObject_static() {
	$oLine = new cCartLine_static(
	  $this->CatNum(),
	  $this->DescrHTML(),
	  $this->QtyOrd(),
	  $this->PriceSell(),
	  $this->ShPerItm(),
	  $this->ShPerPkg()
	  );
	return $oLine;
    }
    /*----
      PUBLIC because Order class calls it to render the order confirmation email
      RETURNS: populated rendering object for the current line
    */
    public function GetRenderObject_text() {
	$oLine = new cCartLine_text(
	  $this->CatNum(),
	  $this->DescrText(),
	  $this->QtyOrd,
	  $this->PriceSell(),
	  $this->ShPerItm(),
	  $this->ShPerPkg()
	  );
	return $oLine;
    }
    /*----
      ACTION: Render the current order line using static HTML (no form elements; read-only)
      NAMING: The word "static" is possibly intended to distinguish this from an admin method that allows editing.
	In the future, users may be able to edit orders after submitting them... but there will be a design review first.
      HISTORY:
	2011-04-01 adapted from clsShopCartLine::RenderHtml() (now RenderStatic()) to clsOrderLine::RenderStatic()
    */
    public function RenderStatic(/*clsShipZone $iZone*/) {
	throw new exception('RenderStatic() is deprecated; call RenderStatic_row() or RenderStatic_rows().');
    }
    public function RenderStatic_rows() {
	$out = cCartDisplay_full::RenderHeader();
	while($this->NextRow()) {
	    $out .= $this->RenderStatic_row();
	}
	return $out;
    }
    public function RenderStatic_row() {
// calculate display fields:
	$nQtyOrd = $this->QtyOrd();
	if ($nQtyOrd > 0) {
	    //$this->RenderCalc($iZone);
echo 'GOT TO HERE';
	    // TODO: convert to use cart-display.php
	    $oCartLine = new cCartLine_static(
	      $this->CatNum(),
	      $this->Descr(),
	      $nQtyOrd,
	      $this->PriceSell(),
	      $this->ShPerItm(),
	      $this->ShPerPkg()
	      );
	    return $oCartLine->Render();
/*
	    $htLineQty = $nQtyOrd;

	    $mnyPrice = $this->PriceSell();	// item price
	    $mnyPerItm = $this->ShPerItm();	// per-item shipping
	    $mnyPerPkg = $this->ShPerPkg();	// per-pkg minimum shipping
	    $mnyPriceQty = $mnyPrice * $nQtyOrd;		// line total sale
	    $mnyPerItmQty = $mnyPerItm * $nQtyOrd;	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum = $this->CatNum();
	    $strPrice = FormatMoney($mnyPrice);
	    $strPerItm = FormatMoney($mnyPerItm);
	    $strPriceQty = FormatMoney($mnyPriceQty);
	    $strPerItmQty = FormatMoney($mnyPerItmQty);
	    $strLineTotal = FormatMoney($mnyLineTotal);

	    $strShipPkg = FormatMoney($mnyPerPkg);

	    $htDesc = $this->DescrHTML();

	    $htDelBtn = '';

	    $out = <<<__END__
<tr>
<td>$htDelBtn$strCatNum</td>
<td>$htDesc</td>
<td class=cart-price align=right>$strPrice</td>
<td class=shipping align=right>$strPerItm</td>
<td class=qty align=right>$htLineQty</td>
<td class=cart-price align=right>$strPriceQty</td>
<td class=shipping align=right>$strPerItmQty</td>
<td class=total align=right>$strLineTotal</td>
<td class=shipping align=right>$strShipPkg</td>
</tr>
__END__;
	    return $out;
*/
	}
    }

    // -- WEB SHOPPING UI -- //
    // ++ EMAIL CONFIRMATION UI ++ //

    /*----
      RETURNS: Rendered text for all lines in the current Order Lines recordset.
    */
    public function RenderText_lines($sFmt) {
	if ($this->HasRows()) {
	    $out = cCartLine_text::RenderListHeader_text();
	    while ($this->NextRow()) {
		$out .= $this->RenderText_line($sFmt);
	    }
	} else {
	    $out = "Internal error: no lines in cart.";
	    // TODO: send an alert email and log the error
	}
	return $out;
    }
    /*----
      RETURNS: Rendered text for the current (single) Order Line record.
    */
    protected function RenderText_line($sFmt) {
	$out = NULL;
	$ftCatNum	= $this->CatNum();

	if ($this->QtyOrd() == 0) {
	    $out = $ftCatNum.' - removed from cart';
	} else {
	    // only show lines with nonzero quantity

	    $oCartLine = new cCartLine_text(
	      $this->CatNum(),
	      $this->Descr(),
	      $this->QtyOrd(),
	      $this->PriceSell(),
	      $this->ShPerItm(),
	      $this->ShPerPkg(),
	      $sFmt
	      );
	    return $oCartLine->Render();
/*

	    $dlrPrice = ;	// item price
	    $dlrPerItm = ;	// per-item shipping
	    $dlrPerPkg = ;	// per-pkg minimum shipping
	    $dlrPriceQty = $this->PriceSell_forQty();	// line total sale
	    $dlrPerItmQty = $this->ShPerItm_forQty();	// line total per-item shipping
	    $dlrLineTotal = $dlrPriceQty + $dlrPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $ftCatNum	= $this->CatNum();
	    $ftPrice	= clsMoney::BasicFormat($dlrPrice);
	    $ftPerItm	= clsMoney::BasicFormat($dlrPerItm);
	    $ftQty	= $this->QtyOrd();
	    $ftPriceQty	= clsMoney::BasicFormat($dlrPriceQty);	// price x qty
	    $ftPerItmQty = clsMoney::BasicFormat($dlrPerItmQty);	// per-item shipping x qty
	    $ftLineTotal = clsMoney::BasicFormat($dlrLineTotal);

	    $ftShipPkg = clsMoney::BasicFormat($dlrPerPkg);



	    $ftDesc = $this->Descr();

	    $out = "\n".sprintf($sFmt,
	      $ftCatNum,
	      $ftPrice,
	      $ftPerItm,
	      $ftQty,
	      $ftPriceQty,
	      $ftPerItmQty,
	      $ftLineTotal
	      );
	    $out .= "\n - $ftDesc";
	    return $out;
*/
	}
    }

    // -- EMAIL CONFIRMATION UI -- //
}
