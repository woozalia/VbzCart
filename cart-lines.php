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
    public function Add($iCart, $iCatNum, $iQty) {
	$objItems = $this->Engine()->Items();
	$objItem = $objItems->Get_byCatNum($iCatNum);
	if (is_null($objItem)) {
// TO DO: log error properly
	    throw new exception('ERROR: Could not find item for catalog #'.$iCatNum.'.');
	} else {
	    $sqlCart = $this->objDB->SafeParam($iCart);
	    $sqlWhere = '(ID_Cart='.$sqlCart.') AND (ID_Item='.$objItem->ID.')';
	    $objLine = $this->GetData($sqlWhere);
	    $objLine->NextRow();	// load the only data row

	    if (!$objLine->hasRows()) {
 		$objLine->Value('ID_Cart',$iCart);
	    }
	    $objLine->ID_Item = $objItem->ID;
	    $objLine->Qty($iQty);
	    $objLine->Save();
	}
    }
}
class clsShopCartLine extends clsDataSet {
    private $oItem;
    private $isChgd;

    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
	//$this->ID = -1;
	$this->isChgd = FALSE;
    }
    public function IsLoaded() {
	return ($this->hasRows());
    }
    public function CartID() {
	return $this->Value('ID_Cart');
    }
    public function Cart() {
	return $this->Engine()->Carts($this->CartID());
    }
    protected function ItemID() {
	return $this->Value('ID_Item');
    }
    public function Item() {
	$doLoad = FALSE;
	if (empty($this->oItem)) {
	    $doLoad = TRUE;
	} elseif ($this->oItem->KeyValue() != $this->ItemID()) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->oItem = $this->Engine()->Items($this->ItemID());
	}
	return $this->oItem;
    }
    /*----
      HISTORY:
	2013-11-10 Changed so it doesn't write to the db, but just sets a flag.
    */
    public function Qty($iQty=NULL) {
	if (!is_null($iQty)) {
	    $qtyNew = 0+$iQty;	// make sure it's an integer -- prevent injection attack
	    if ($this->ValueNz('Qty') != $qtyNew) {
		$this->isChgd = TRUE;
		$this->Value('Qty',$qtyNew);
	    }
	}
	return $this->Value('Qty');
    }
    /*----
      USAGE: called from clsShopCart::AddItem() when an item needs to be saved to the cart
      HISTORY:
	2013-11-10 written to replace Build()
    */
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
	    $this->Value('Seq',$this->Cart()->LineCount()+1);
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
    /*-----
      PURPOSE: Do calculations necessary for rendering the cart line
      USED BY:
	* the shopping cart form
	* the final order display
	* the conversion from cart to order
    */
    public function RenderCalc(clsShipZone $iZone) {
	$arItem = $this->Item()->DescSpecs();
	$txtItemDesc = $this->Item()->DescLong($arItem);
	$htmItemDesc = $this->Item()->DescLong_ht($arItem);

	$objItem = $this->Item();
	$this->PriceItem = $objItem->PriceSell;
	$this->Value('CatNum',$objItem->CatNum);

// save for copying to order line object:
	$this->DescText = $txtItemDesc;
	$this->DescHtml = $htmItemDesc;

// save so cart can figure totals;
	//$idsZone = $iZone->Abbr();
	$this->ShipPkgDest = $objItem->ShipPricePkg($iZone);
	$this->ShipItmDest = $objItem->ShipPriceItem($iZone);

// calculate costs:
	$this->CostItemQty = $this->Qty * $this->PriceItem;
	$this->CostShipQty = $this->Qty * $this->ShipItmDest;

    }
    /*
      ACTION: Render the current cart line using static HTML (no form elements; read-only)
      HISTORY:
	2011-04-01 adapting this to use clsOrdLine->RenderStatic()
    */
    public function RenderHtml(clsShopCart $iCart) {
	$objOLine = $this->Engine()->OrdLines()->SpawnItem();
	$objZone = $iCart->ShipZoneObj();
	$this->RenderCalc($objZone);	// calculate some needed fields
	$objOLine->Init_fromCartLine($this);
	return $objOLine->RenderStatic($objZone);
    }
    /*----
      ACTION: Render the current cart line as part of an interactive HTML form
    */
    public function RenderForm(clsShopCart $iCart) {
// calculate display fields:
	if ($this->Qty) {
	    $this->RenderCalc($iCart->ShipZoneObj());

	    //$htLineName = 'cart-line-'.$this->Seq;
	    $htLineName = KSF_CART_ITEM_PFX.$this->CatNum.KSF_CART_ITEM_SFX;
	    $sQty = $this->Value('Qty');
	    $htLineCtrl = '<input size=2 align=right name="'.$htLineName.'" value='.$sQty.'>';

	    $mnyPrice = $this->Value('PriceItem');		// item price
	    $mnyPerItm = $this->Value('ShipItmDest');		// per-item shipping
	    $mnyPerPkg = $this->Value('ShipPkgDest');		// per-pkg minimum shipping
	    $mnyPriceQty = $this->Value('CostItemQty');	// line total sale
	    $mnyPerItmQty = $this->Value('CostShipQty');	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum = $this->CatNum;
	    $strPrice = FormatMoney($mnyPrice);
	    $strPerItm = FormatMoney($mnyPerItm);
	    $strPriceQty = FormatMoney($mnyPriceQty);
	    $strPerItmQty = FormatMoney($mnyPerItmQty);
	    $strLineTotal = FormatMoney($mnyLineTotal);

	    $strShipPkg = FormatMoney($mnyPerPkg);

	    $htDesc = $this->DescHtml;

	    $htDelBtn = '<span class=text-btn>['
	      .'<a href="'
	      .'?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_LINE
	      .'&'.KSF_CART_EDIT_LINE_ID.'='.$this->KeyValue()
	      .'" title="remove '
	      .$strCatNum
	      .' from cart">remove</a>]</span> ';

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
/**/
	}
    }
    public function RenderText(clsShopCart $iCart,$iFmt) {
	if ($this->Qty) {
	    $this->RenderCalc($iCart->ShipZoneObj());

	    $dlrPrice = $this->PriceItem;	// item price
	    $dlrPerItm = $this->ShipItmDest;	// per-item shipping
	    $dlrPerPkg = $this->ShipPkgDest;	// per-pkg minimum shipping
	    $dlrPriceQty = $this->CostItemQty;	// line total sale
	    $dlrPerItmQty = $this->CostShipQty;	// line total per-item shipping
	    $dlrLineTotal = $dlrPriceQty + $dlrPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $ftCatNum = $this->CatNum;
	    $ftPrice = FormatMoney($dlrPrice);
	    $ftPerItm = FormatMoney($dlrPerItm);
	    $ftQty = $this->Qty;
	    $ftPriceQty = FormatMoney($dlrPriceQty);	// price x qty
	    $ftPerItmQty = FormatMoney($dlrPerItmQty);	// per-item shipping x qty
	    $ftLineTotal = FormatMoney($dlrLineTotal);

	    $ftShipPkg = FormatMoney($dlrPerPkg);

	    $ftDesc = $this->DescText;

	    $out = "\n".sprintf($iFmt,$ftCatNum,$ftPrice,$ftPerItm,$ftQty,$ftPriceQty,$ftPerItmQty,$ftLineTotal);
	    $out .= "\n - $ftDesc";
	    return $out;
	}
    }
}
