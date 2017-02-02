<?php
/*
  HISTORY:
    2014-09-28 split off from orders.php
*/
class vctOrderLines extends vcShopTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'ord_lines';
    }
    protected function SingularName() {
	return 'vcrOrderLine';
    }
    
    // -- CEMENTING -- //
    // ++ RECORDS ++ //
    
    public function Find_byOrder_andItem($idOrder,$idItem) {
 	$sqlFilt = "(ID_Order=$idOrder) AND (ID_Item=$idItem)";
	$rc = $this->SelectRecords($sqlFilt);
	$rc->NextRow();		// load the row
	return $rc;
    }
    /*----
      PUBLIC so Order record can access it
      USED BY: Order record (nothing else as of 2016-11-05)
    */
    public function FetchRecords_forOrderID($idOrder) {
    	return $this->SelectRecords('ID_Order='.$idOrder);
    }
    
    // -- RECORDS -- //

}
class vcrOrderLine extends vcShopRecordset {

    // ++ SETUP ++ //

    public function Init_fromCartLine(vcrShopCartLine $rcCLine) {
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
    // ++ FIELD VALUES ++ //

    protected function GetItemID() {
	return $this->GetFieldValue('ID_Item');
    }
    protected function QtyOrd() {
	return $this->GetFieldValue('QtyOrd');
    }
    protected function SequenceNumber() {
	return $this->Value('Seq');
    }
    /*----
      RETURNS: selling price
	if order line has no price, falls back to catalog item
      HISTORY:
	2011-03-23 created for "charge for package" process
	2014-08-22 modified to allow writing
	2015-11-08 Re-simplifying:
	  * Writing from here seems like a bad idea; if needed, document need.
	  * Order Lines should always have a price, because it should reflect
	    the price at the time of ordering (catalog price may change).
    */
    public function PriceSell() {
	return $this->GetFieldValue('Price');
    }
    protected function DescrText() {
	return $this->GetFieldValue('Descr');
    }
    // ACTION: Get the stored "s/h per package" value; do no calculations.
    protected function GetShippingPerPackage() {
	return $this->GetFieldValue('ShipPkg');
    }
    public function SetShippingPerPackage($prc) {
	$this->SetFieldValue('ShipPkg',$prc);
    }
    // ACTION: Get the stored "s/h per unit" value; do no calculations.
    protected function GetShippingPerUnit() {
	return $this->GetFieldValue('ShipItm');
    }
    protected function SetShippingPerUnit($prc) {
	$this->SetFieldValue('ShipItm',$prc);
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: shipping per-package price
	if order line has no per-package price, falls back to catalog item
	and writes it to the local recordset but does not save.
      HISTORY:
	2011-03-23 created for "charge for package" process
	2014-08-22 modified to allow writing
	2016-11-05 split into 3 methods:
	  GetShippingPerPackage(): read order line's stored value
	  SetShippingPerPackage(): write order line's stored value
	  FigureShippingPerPackage(): look up value to use if value is unknown by order line
    */
    protected function FigureShippingPerPackage() {
	$prc = $this->GetShippingPerPackage();
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->ShPerPkg();
	    $this->SetShippingPerPackage($prc);
	}
	return $prc;
    }
    /* 2016-11-05 old code
    public function ShPerPkg($prc=NULL) {
	$prc = $this->Value('ShipPkg',$prc);
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->ShPerPkg();
	    $this->Value('ShipPkg',$prc);
	}
	return $prc;
    }*/
    /*----
      RETURNS: shipping per-item price -- defaults to catalog item's data
	unless specified in package line
      HISTORY:
	2011-03-23 created for "charge for package" process
	2014-08-22 modified to allow writing
	2016-11-05 split into 3 methods:
	  GetShippingPerUnit(): read order line's stored value
	  SetShippingPerUnit(): write order line's stored value
	  FigureShippingPerUnit(): look up value to use if value is unknown by order line
    */
    protected function FigureShippingPerUnit() {
	$prc = $this->GetShippingPerUnit();
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->ShPerItm();
	    $this->SetShippingPerUnit($prc);
	}
	return $prc;
    }
    /* 2016-11-05 old code
    public function ShPerItm($prc=NULL) {
	$prc = $this->Value('ShipItm',$prc);
	if (is_null($prc)) {
	    $prc = $this->ItemRecord()->ShPerItm();
	    $this->Value('ShipItm',$prc);
	}
	return $prc;
    }*/
    protected function SetCatNum($sVal) {
	return $this->SetFieldValue('CatNum',$sVal);
    }
    protected function GetCatNum() {
	return $this->GetFieldValue('CatNum');
    }
    protected function Descr($sText=NULL) {
	$val = $this->ValueNz('Descr',$sText);
	if (is_null($sText)) {
	    $val = $this->ItemRecord()->Descr();
	    $this->Value('Descr',$val);
	}
	return $val;
    }
    protected function DescrHTML() {
	return $this->ItemRecord()->Description_forOrder();
    }
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

	    $prcShItmSum = clsArray::Nz($arSum,'sh-itm',0);
	    $prcShPkgMax = clsArray::Nz($arSum,'sh-pkg',0);
	    $prcSaleSum = clsArray::Nz($arSum,'cost-sell',0);

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
	return $this->GetConnection()->MakeTableWrapper($this->ItemsClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ DATA RECORDS ++ //

    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
	2016-11-05 now used in checkout process (order conversion/confirmation)
    */
    private $rcItem, $idItem;
    public function ItemObj() {
	throw new exception('ItemObj() is deprecated; call ItemRecord().');
    }
    public function ItemRecord() {
	$doLoad = TRUE;
	$id = $this->GetItemID();
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
      TODO: This seems kind of sloppy. Figure out if it is necessary; fix if not.
      RETURNS: populated rendering object for the current line
    */
    public function GetRenderObject_static() {
	$oLine = new vcCartLine_static(
	  $this->GetCatNum(),
	  $this->DescrHTML(),
	  $this->QtyOrd(),
	  $this->PriceSell(),
	  $this->FigureShippingPerUnit(),
	  $this->FigureShippingPerPackage()
	  );
	return $oLine;
    }
    /*----
      PUBLIC because Order class calls it to render the order confirmation email
      RETURNS: populated rendering object for the current line
    */
    public function GetRenderObject_text() {
	$oLine = new vcCartLine_text(
	  $this->GetCatNum(),
	  $this->DescrText(),
	  $this->QtyOrd(),
	  $this->PriceSell(),
	  $this->FigureShippingPerUnit(),
	  $this->FigureShippingPerPackage()
	  );
	return $oLine;
    }
    /*----
      ACTION: Render the current order line using static HTML (no form elements; read-only)
      NAMING: The word "static" is possibly intended to distinguish this from an admin method that allows editing.
	In the future, users may be able to edit orders after submitting them... but there will be a design review first.
      HISTORY:
	2011-04-01 adapted from clsShopCartLine::RenderHtml() (now vcrShopCart::RenderStatic()) to clsOrderLine::RenderStatic()
    */
    public function RenderStatic(/*clsShipZone $iZone*/) {
	throw new exception('RenderStatic() is deprecated; call RenderStatic_row() or RenderStatic_rows().');
    }
    public function RenderStatic_rows() {
	throw new exception('Is this actually used?');	// 2015-09-04
	$out = vcCartDisplay_full::RenderHeader();
	while($this->NextRow()) {
	    $out .= $this->RenderStatic_row();
	}
	return $out;
    }
    public function RenderStatic_row() {
	throw new exception('Is this actually used?');	// 2015-09-04
// calculate display fields:
	$nQtyOrd = $this->QtyOrd();
	if ($nQtyOrd > 0) {
	    //$this->RenderCalc($iZone);
echo 'GOT TO HERE';
	    // TODO: convert to use cart-display.php
	    $oCartLine = new vcCartLine_static(
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
	    $out = vcCartLine_text::RenderListHeader_text();
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

	    $oCartLine = new vcCartLine_text(
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
