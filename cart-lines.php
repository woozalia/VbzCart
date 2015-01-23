<?php
/*
  HISTORY:
    2012-04-17 extracting from shop.php
*/

require_once('vbz-const-cart.php');

class clsShopCartLines extends clsTable {
    const TableName='shop_cart_line';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsShopCartLine');
	$this->seqCart = 0;
    }

    // ++ DATA RECORDS ACCESS ++ //

    // 2014-02-28 is this actually needed?
    public function Find_byCart_andItem($idCart,$idItem) {
 	$sqlFilt = "(ID_Cart=$idCart) AND (ID_Item=$idItem)";
	$rc = $this->GetData($sqlFilt);
	$rc->NextRow();		// load the row
	return $rc;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTION ++ //

    public function Add($iCart, $iCatNum, $iQty) {
	$tItems = $this->Engine()->Items();
	$rcItem = $tItems->Get_byCatNum($iCatNum);
	if (is_null($rcItem)) {
// TO DO: log error properly
	    throw new exception('ERROR: Could not find item for catalog #'.$iCatNum.'.');
	} else {
	    $sqlCart = $this->Engine()->SafeParam($iCart);
	    $idItem = $rcItem->KeyValue();
	    $sqlWhere = "(ID_Cart=$sqlCart) AND (ID_Item=$idItem)";
	    $rcLine = $this->GetData($sqlWhere);

	    if ($rcLine->hasRows()) {
		$rcLine->NextRow();	// load the only data row
	    } else {
 		$rcLine->Value('ID_Cart',$iCart);
	    }
	    $rcLine->ItemID($rcItem->KeyValue());
	    $rcLine->Qty($iQty);

	    $rcLine->Save();
	}
    }

    // -- ACTION -- //
}
class clsShopCartLine extends clsDataSet {
    private $rcItem;
    private $rcShCost;

    // ++ INITIALIZATION ++ //

    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
	//$this->ID = -1;
	$this->rcItem = NULL;
	$this->rcShCost = NULL;
    }

    // -- INITIALIZATION -- //
    // ++ CLASS NAMES ++ //

    protected function CartsClass() {
	return 'clsShopCart';
    }
    protected function ItemsClass() {
	return 'clsItems';
    }
    protected function OrderLinesClass() {
	return 'clsOrderLines';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    protected function CartTable($id=NULL) {
	return $this->Engine()->Make($this->CartsClass(),$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    protected function OrderLineTable($id=NULL) {
	return $this->Engine()->Make($this->OrderLinesClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ DATA RECORDS ++ //

    public function CartRecord() {
	return $this->Engine()->Carts($this->CartID());
    }
    /*----
      PUBLIC so order line can look up item values in order to initialize itself
    */
    public function ItemRecord() {
	$doLoad = FALSE;
	if (is_null($this->rcItem)) {
	    $doLoad = TRUE;
	} elseif ($this->rcItem->KeyValue() != $this->ItemID()) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->rcItem = $this->ItemTable($this->ItemID());
	    $this->arItSp = NULL;
	}
	return $this->rcItem;
    }
    protected function ShipCostRecord() {
	if (is_null($this->rcShCost)) {
	    $this->rcShCost = $this->ItemRecord()->ShipCostRecord();
	}
	return $this->rcShCost;
    }
    /*----
      ASSUMES: Item object has already been loaded
    */
    public function ItemSpecs() {
	throw new exception('Who calls this?');
	if (is_null($this->arItSp)) {
	    $this->arItSp = $this->rcItem->DescSpecs();
	}
	return $this->arItSp;
    }

    // -- DATA RECORDS -- //
    // ++ FIELD ACCESS ++ //

    public function CartID($id=NULL) {
	return $this->Value('ID_Cart',$id);
    }
    public function ItemID($id=NULL) {
	return $this->Value('ID_Item',$id);
    }
    public function Seq($nVal=NULL) {
	return $this->Value('Seq',$nVal);
    }
    /*----
      HISTORY:
	2013-11-10 Changed so it doesn't write to the db, but just sets a flag.
      TODO: Preventing injection attacks should happen where the input is pulled
	from $_REQUEST, not here.
    */
    public function Qty($nQty=NULL) {
    /* 2014-07-04 This should all be redundant now, I think.
	if (!is_null($nQty)) {
	    $qtyNew = 0+$nQty;	// make sure it's an integer -- prevent injection attack
	    if ($this->ValueNz('Qty') != $qtyNew) {
		$this->Value('Qty',$qtyNew);
	    }
	}
	*/
	return $this->Value('Qty',$nQty);
    }

    // ++ item specs

    protected function ItemPrice() {
	return $this->ItemRecord()->PriceBuy();
    }
    /*----
      RETURNS: item's per-unit shipping cost
      TODO: Rename ItemShip_perUnit() -> SH_perItem()
    */
    protected function ItemShip_perUnit() {
	return $this->ShipCostRecord()->PerItem();
    }
    /*----
      RETURNS: item's per-package minimum shipping cost
      PUBLIC so Cart object can access it
      TODO: Rename ItemShip_perPkg() -> SH_perPkg()
    */
    public function ItemShip_perPkg() {
	return $this->ShipCostRecord()->PerPkg();
    }
    /*----
      PUBLIC so Cart object can access it for order conversion
    */
    public function CatNum() {
	return $this->ItemRecord()->CatNum();
    }
    protected function DescHtml() {
	return $this->ItemRecord()->DescLong_ht();
    }
    /*----
      PUBLIC because the Order object uses it during cart->order conversion
    */
    public function DescText() {
	return $this->ItemRecord()->DescLong();
    }

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    public function IsLoaded() {
	throw new exception('Who uses this? Is it different from IsNew()?');
	return ($this->hasRows());
    }
    public function ItemPriceBuyQty() {
	return $this->ItemRecord()->PriceBuy() * $this->Qty();
    }
    /*----
      RETURNS: line total sale
      TODO: rename ItemSale_forQty() -> ItemPrice_forQty()
      PUBLIC so Cart object can access it
    */
    public function ItemSale_forQty() {
	return $this->ItemPrice() * $this->Qty();
    }
    /*----
      RETURNS: line total per-unit shipping
      PUBLIC so Cart object can access it
    */
    public function ItemShip_perUnit_forQty() {
	return $this->ItemShip_perUnit() * $this->Qty();
    }
    protected function UpdateArray() {
	$arOut = parent::UpdateArray();
	if ($this->IsNew()) {
	    $arOut['WhenAdded'] = 'NOW()';
	} else {
	    $arOut['WhenEdited'] = 'NOW()';
	}
	return $arOut;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      USAGE: called from clsShopCart::AddItem() when an item needs to be saved to the cart
      HISTORY:
	2013-11-10 written to replace Build()
    */
    public function Save() {
	if ($this->IsNew()) {
	    $this->Seq($this->CartRecord()->LineCount()+1);
	}
	return parent::Save();
    }
/* 2014-07-05 old version
    public function Save() {
	if ($this->IsLoaded()) {

	    if ($this->isChgd) {
		// only do the update if Qty has changed
		$ar = array(
		    'Qty'		=> $this->Qty(),
		    'WhenEdited'	=> 'NOW()'
		    );
		$this->Update($ar);
	    }
	    $id = $this->KeyValue();
	} else {
	    $this->Value('Seq',$this->CartRecord()->LineCount()+1);
	    $ar = array(
	      'Seq'	=> $this->Value('Seq'),
	      'ID_Cart'	=> $this->CartID(),
	      'ID_Item'	=> $this->ItemID(),
	      'Qty'	=> $this->Qty(),
	      'WhenAdded'	=> 'NOW()'
	      );
	    $id = $this->Table->Insert($ar);
	    $this->KeyValue($id);		// save ID of new record
	}
	return $id;	// probably not used, but good form
    }
*/
    // -- ACTIONS -- //
    // ++ STORE UI ++ //

    /*----
      PURPOSE: renders header for cart contents table
      USAGE: Cart or Order
    */
    static public function RenderHeader() {
	throw new exception('CartLine::RenderHeader() is deprecated; call CartDisplay::RenderTableHeader().');
	$out = <<<__END__
<tr>
<th><big>cat #</big></th>
<th><big>description</big></th>
<th>price<br>each</th>
<th><small>per-item<br>s/h ea.</small></th>
<th>qty.</th>
<th><small>purchase<br>line total</small></th>
<th><small>per-item<br>s/h line total</small></th>
<th>totals</th>
<th><small>pkg s/h<br>min.</small></th>
</tr>
__END__;
	return $out;
    }
    /*-----
      PURPOSE: Do calculations necessary for rendering the cart line
      USED BY:
	* the shopping cart form
	* the final order display
	* the conversion from cart to order
      HISTORY:
	2014-07-01 disabling this -- this isn't a good way to do what it does
	2014-09-14 Needed for cart-to-order conversion, so reviving it.
    */
    public function RenderCalc(clsShipZone $iZone) {
	$rcItem = $this->ItemRecord();
	//$arItem = $rcItem->DescSpecs();
	//$txtItemDesc = $rcItem->DescLong($arItem);
	$txtItemDesc = $rcItem->DescLong();
	//$htmItemDesc = $rcItem->DescLong_ht($arItem);
	$htmItemDesc = $rcItem->DescLong_ht();

	$this->PriceItem = $rcItem->PriceSell;
	$this->Value('CatNum',$rcItem->CatNum);

// save for copying to order line object:
	$this->DescText = $txtItemDesc;
	$this->DescHtml = $htmItemDesc;

// save so cart can figure totals;
	//$idsZone = $iZone->Abbr();
	$this->ShipPkgDest = $rcItem->ShipPricePkg($iZone);
	$this->ShipItmDest = $rcItem->ShipPriceItem($iZone);

// calculate costs:
	$this->CostItemQty = $this->ItemSale_forQty();
	$this->CostShipQty = $this->ItemShip_perUnit_forQty();
    }

    public function ItemDescLong_text() {
	return $this->ItemRecord()->DescLong();
    }
    /*----
      PUBLIC because Cart object calls it to display a static cart at checkout
      RETURNS: populated rendering object for the current line
    */
    public function GetRenderObject_static() {
	$oLine = new cCartLine_static(
	  $this->CatNum(),
	  $this->DescText(),
	  $this->Qty(),
	  $this->ItemPrice(),
	  $this->ItemShip_perUnit(),
	  $this->ItemShip_perPkg()
	  );
	return $oLine;
    }
    /*----
      PUBLIC because Cart object calls it to display an editable cart
      TODO: There's got to be a cleaner way of adding just one more argument...
    */
    public function GetRenderObject_editable() {
	$oLine = new cCartLine_form(
	  $this->KeyValue(),
	  $this->CatNum(),
	  $this->DescText(),
	  $this->Qty(),
	  $this->ItemPrice(),
	  $this->ItemShip_perUnit(),
	  $this->ItemShip_perPkg()
	  );
	return $oLine;
    }
    /*
      ACTION: Render the current cart line using static HTML (no form elements; read-only)
      HISTORY:
	2011-04-01 adapting this to use clsOrdLine->RenderStatic()
	2014-08-22 simplifying a bit
      USED BY: Checkout procedure -- displays cart contents for order confirmation
      TODO: This could probably use some rethinking, given other changes.
    */
    public function RenderStatic(/*clsShopCart $iCart*/) {
//	$rcOLine = $this->OrderLineTable()->SpawnItem();
	//$objZone = $iCart->ShipZoneObj();
	//$this->RenderCalc($objZone);	// calculate some needed fields

	// set up a cart-line-display object from the shop-cart-line
	//$rcItem = $this->ItemRecord();

	//$rcOLine->Init_fromCartLine($this);
	//return $rcOLine->RenderStatic_row();
	return $this->GetRenderObject_static()->Render();
    }
    /*----
      ACTION: Render the current cart line as part of an interactive HTML form
    */
    public function RenderForm(clsShopCart $iCart) {
// calculate display fields:
	if ($this->Qty) {
	    $oLine = $this->GetRenderObject_editable();
	    /*
	    //$this->RenderCalc($iCart->ShipZoneObj());

	    //$htLineName = 'cart-line-'.$this->Seq;
	    $htLineName = KSF_CART_ITEM_PFX.$this->CatNum().KSF_CART_ITEM_SFX;
	    $sQty = $this->Value('Qty');
	    $htLineCtrl = '<input size=2 align=right name="'.$htLineName.'" value='.$sQty.'>';

	    $mnyPrice = $this->ItemPrice();			// item price
	    $mnyPerItm = $this->ItemShip_perUnit();		// per-item shipping
	    $mnyPerPkg = $this->ItemShip_perPkg();		// per-pkg minimum shipping
	    $mnyPriceQty = $this->ItemSale_forQty();		// line total sale
	    $mnyPerItmQty = $this->ItemShip_perUnit_forQty();	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum = $this->CatNum;
	    $strPrice = clsShopCart::FormatMoney($mnyPrice);
	    $strPerItm = clsShopCart::FormatMoney($mnyPerItm);
	    $strPriceQty = clsShopCart::FormatMoney($mnyPriceQty);
	    $strPerItmQty = clsShopCart::FormatMoney($mnyPerItmQty);
	    $strLineTotal = clsShopCart::FormatMoney($mnyLineTotal);

	    $strShipPkg = clsShopCart::FormatMoney($mnyPerPkg);

	    $htDesc = $this->DescHtml();

	    $htDelBtn = '<span class=text-btn>'
	      .'<a href="'
	      .'?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_LINE
	      .'&'.KSF_CART_EDIT_LINE_ID.'='.$this->KeyValue()
	      .'" title="remove '
	      .$strCatNum
	      .' from cart">remove</a></span> ';

	    $out = <<<__END__
<tr>
<td>$htDelBtn$strCatNum</td>
<td>$htDesc</td>
<td class=cart-price align=right>$strPrice</td>
<td class=shipping align=right>$strPerItm</td>
<td class=qty align=right>$htLineCtrl</td>
<td class=cart-price align=right>$strPriceQty</td>
<td class=shipping align=right>$strPerItmQty</td>
<td class=total align=right>$strLineTotal</td>
<td class=shipping align=right>$strShipPkg</td>
</tr>
__END__;
	    return $out;
	    */
	    return $oLine->Render();
/**/
	}
    }
    /*
      2014-10-19 Disabled this because email confirmation should always be generated from Order records.

    public function RenderText($sFmt) {
	if ($this->Qty) {
	    //$this->RenderCalc($iCart->ShipZoneObj());

	    $dlrPrice = $this->ItemPrice();	// item price
	    $dlrPerItm = $this->ItemShip_perUnit();	// per-item shipping
	    $dlrPerPkg = $this->ItemShip_perPkg();	// per-pkg minimum shipping
	    $dlrPriceQty = $this->ItemSale_forQty();	// line total sale
	    $dlrPerItmQty = $this->ItemShip_perUnit_forQty();	// line total per-item shipping
	    $dlrLineTotal = $dlrPriceQty + $dlrPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $ftCatNum = $this->CatNum();
	    $ftPrice = FormatMoney($dlrPrice);
	    $ftPerItm = FormatMoney($dlrPerItm);
	    $ftQty = $this->Qty();
	    $ftPriceQty = FormatMoney($dlrPriceQty);	// price x qty
	    $ftPerItmQty = FormatMoney($dlrPerItmQty);	// per-item shipping x qty
	    $ftLineTotal = FormatMoney($dlrLineTotal);

	    $ftShipPkg = FormatMoney($dlrPerPkg);

	    $ftDesc = $this->DescText();

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
	}
    } */

    // -- STORE UI -- //
}
