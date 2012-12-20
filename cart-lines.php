<?php
/*
  HISTORY:
    2012-04-17 extracting from shop.php
*/

clsLibMgr::Add('vbz.orders',	KFP_LIB_VBZ.'/orders.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsOrderLines', 'vbz.orders');

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
	$objItems = $this->objDB->Items();
	$objItem = $objItems->Get_byCatNum($iCatNum);
	if (is_null($objItem)) {
// TO DO: log error
echo 'ERROR: Could not find item for catalog #'.$iCatNum.'<br>';
	} else {
	    $sqlCart = $this->objDB->SafeParam($iCart);
	    $sqlWhere = '(ID_Cart='.$sqlCart.') AND (ID_Item='.$objItem->ID.')';
	    $objLine = $this->GetData($sqlWhere,'clsShopCartLine');
	    $objLine->NextRow();	// load the only data row

	    if (!$objLine->hasRows()) {
 		$objLine->ID_Cart=$iCart;
	    }
	    $objLine->ID_Item = $objItem->ID;
	    $objLine->Qty($iQty);
	    $objLine->Build();
	}
    }
}
class clsShopCartLine extends clsDataSet {
    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
// this is necessary so $this->Update() will work
// ...but it should be done by whatever code creates the object
	//$this->Table = $this->objDB->CartLines();
	$this->ID = -1;
    }
    public function IsLoaded() {
	return ($this->hasRows());
    }
    public function Cart() {
	return $this->objDB->Carts()->GetItem($this->ID_Cart);
    }
    public function Item() {
	$doLoad = FALSE;
	if (empty($this->objItem)) {
	    $doLoad = TRUE;
	} elseif ($this->objItem->ID != $this->ID_Item) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->objItem = $this->objDB->Items()->GetItem($this->ID_Item);
	}
	return $this->objItem;
    }
    public function Build() {
	if ($this->IsLoaded()) {
//	if ($this->HasRows()) {
	    $sql = 'UPDATE `'.clsShopCartLines::TableName.'` SET'
	      .' Qty='.$this->Qty
	      .' WhenEdited=NOW()'
		.' WHERE ID='.$this->ID;
	    $this->objDB->Exec($sql);
	} else {
	    $this->Seq = $this->Cart()->LineCount()+1;
	    $sql = 'INSERT INTO `'.clsShopCartLines::TableName
	      .'` (Seq,ID_Cart,ID_Item,Qty,WhenAdded)'
	      .' VALUES('
		.$this->Seq.', '
		.$this->ID_Cart.', '
		.$this->ID_Item.', '
		.$this->Qty.', NOW());';
	    $this->objDB->Exec($sql);
	    $this->ID = $this->objDB->NewID('cartLine.make');
	}
    }
    public function Qty($iQty=NULL) {
	if (!is_null($iQty)) {
	    if ($this->Qty != $iQty) {
		$qtyNew = 0+$iQty;	// make sure it's an integer -- prevent injection attack
		$arrSet['Qty'] = SQLValue($qtyNew);
		$arrSet['WhenEdited'] = 'NOW()';
		$this->Update($arrSet);
		$this->Qty = $qtyNew;
	    }
	}
	return $this->Qty;
    }
/*
    protected function ItemSpecs(array $iSpecs=NULL) {
	if (is_null($iSpecs)) {
	    $this->Item();	// make sure $this->objItem is loaded
	    $this->objTitle	= $this->objItem->Title();
	    $this->objItTyp	= $this->objItem->ItTyp();
	    $this->objItOpt	= $this->objItem->ItOpt();

	    $out['tname']	= $this->objTitle->Name;
	    $out['ittyp']	= $this->objItTyp->Name($this->Qty);
	    $out['itopt']	= $this->objItOpt->Descr;
	    return $out;
	} else {
	    return $iSpecs;
	}
    }
    public function ItemDesc(array $iSpecs=NULL) {	// plaintext
	$sp = $this->ItemSpecs($iSpecs);

	$strItOpt = $sp['itopt'];

	$out = '"'.$sp['tname'].'" ('.$sp['ittyp'];
	if (!is_null($strItOpt)) {
	    $out .= ' - '.$strItOpt;
	}
	$out .= ')';

	return $out;
    }
    public function ItemDesc_ht(array $iSpecs=NULL) {	// as HTML
	$sp = $this->ItemSpecs($iSpecs);

	$htTitleName = '<i>'.$this->objTitle->LinkName().'</i>';
	$strItOpt = $sp['itopt'];

	$out = $htTitleName.' ('.$sp['ittyp'];
	if (!is_null($strItOpt)) {
	    $out .= ' - '.$strItOpt;
	}
	$out .= ')';

	return $out;
    }
    public function ItemDesc_wt(array $iSpecs=NULL) {	// as wikitext
	$sp = $this->ItemSpecs($iSpecs);

	$wtTitleName = "''".$this->objTitle->LinkName_wt()."''";
	$strItOpt = $sp['itopt'];

	$out = $wtTitleName.' ('.$sp['ittyp'];
	if (!is_null($strItOpt)) {
	    $out .= ' - '.$strItOpt;
	}
	$out .= ')';

	return $out;
    }
*/
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
	$idsZone = $iZone->Abbr();
	$this->ShipPkgDest = $objItem->ShipPricePkg($idsZone);
	$this->ShipItmDest = $objItem->ShipPriceItem($idsZone);

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
/*/
// calculate display fields:
	if ($this->Qty) {
	    $this->RenderCalc($iCart->objShipZone);

	    $strQty = $this->Qty;
	    $htLineCtrl = $strQty;

	    $mnyPrice = $this->PriceItem;	// item price
	    $mnyPerItm = $this->ShipItmDest;	// per-item shipping
	    $mnyPerPkg = $this->ShipPkgDest;	// per-pkg minimum shipping
	    $mnyPriceQty = $this->CostItemQty;	// line total sale
	    $mnyPerItmQty = $this->CostShipQty;	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum = $this->CatNum;
	    $strPrice = FormatMoney($mnyPrice);
	    $strPerItm = FormatMoney($mnyPerItm);
	    $strPriceQty = FormatMoney($mnyPriceQty);
	    $strPerItmQty = FormatMoney($mnyPerItmQty);
	    $strLineTotal = FormatMoney($mnyLineTotal);

	    $strShipPkg = FormatMoney($mnyPerPkg);

	    $htDesc = $this->DescHtml;

	    $htDelBtn = '';

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
	}
/**/
    }
    /*----
      ACTION: Render the current cart line as part of an interactive HTML form
    */
    public function RenderForm(clsShopCart $iCart) {
// calculate display fields:
	if ($this->Qty) {
	    $this->RenderCalc($iCart->ShipZoneObj());

	    //$htLineName = 'cart-line-'.$this->Seq;
	    $htLineName = 'qty-'.$this->CatNum;
	    $strQty = $this->Qty;
	    $htLineCtrl = '<input size=2 align=right name="'.$htLineName.'" value='.$strQty.'>';

	    $mnyPrice = $this->PriceItem;	// item price
	    $mnyPerItm = $this->ShipItmDest;	// per-item shipping
	    $mnyPerPkg = $this->ShipPkgDest;	// per-pkg minimum shipping
	    $mnyPriceQty = $this->CostItemQty;	// line total sale
	    $mnyPerItmQty = $this->CostShipQty;	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum = $this->CatNum;
	    $strPrice = FormatMoney($mnyPrice);
	    $strPerItm = FormatMoney($mnyPerItm);
	    $strPriceQty = FormatMoney($mnyPriceQty);
	    $strPerItmQty = FormatMoney($mnyPerItmQty);
	    $strLineTotal = FormatMoney($mnyLineTotal);

	    $strShipPkg = FormatMoney($mnyPerPkg);

	    $htDesc = $this->DescHtml;

	    $htDelBtn = '<span class=text-btn>[<a href="?item='.$this->ID_Item.'&action=del" title="remove '.$strCatNum.' from cart">remove</a>]</span> ';

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
/* (2010-02-18) actually, this is probably superceded by RenderText()
    public function RenderEmail($iCart) {
/* TO DO: to be written; the following is from Perl:
	      $out .= "$intLine. $lineDescText\n";
	      $out .= "Catalog #: $lineCatNum\n";
	      $out .= "Price each        : ".AlignDollars($linePrice)."\n";
	      $out .= "Shipping each     : ".AlignDollars($lineShipItemDest)."\n";
	      $out .= "Min. base shipping: ".AlignDollars($lineShipPkgDest)."\n";
	      if ($lineQty != 1) {
		      $textOrder .= "##### QTY $strQty #####\n";
	      }
	      $out .= "\n";
*/
    }
}
