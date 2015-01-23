<?php
/*
  FILE: order-total.php -- helper class for checking Cart/Order totals
  HISTORY:
    2014-02-23 adapted from code in order.php
    2014-09-14 Moved dropins/orders/total.php to cart-line-total.php
    2014-10-12 ...except apparently I didn't. Done now.
    2014-10-26 Adding ability to display cart lines too; the idea is that
      both Carts and Orders should use this code to display the cart contents/totals.
*/

class cCartDisplay {
    private $doAllMatch;
    private $arItems;

    public function __construct() {
	$this->doAllMatch = TRUE;
    }
    public function AddItem(cCartItem $oItem) {
	$sName = $oItem->Name();
	$this->arItems[$sName] = $oItem;
    }
    // this probably belongs in a descendant class named something like cCartDisplay_admin
    public function FoundMismatch($bMismatch=NULL) {
	if (!is_null($bMismatch)) {
	    $this->doAllMatch = $bMismatch;
	}
	return $this->doAllMatch;
    }
    /*----
      RETURNS: Rendering of all cart total objects
	This generally means order total, s/h total(s), and final total.
    */
    public function RenderItems() {
	$out = NULL;
	foreach ($this->arItems as $sName => $oTotal) {
	    $out .= $oTotal->Render();
	}
	return $out;
    }
}
abstract class cCartDisplay_full extends cCartDisplay {
    private $oZone;	// shipping zone object
    private $nTotSale;	// sale total
    private $nTotShItm;	// total per-item S/H
    private $nMaxShPkg;	// maximum per-package S/H

    public function __construct(clsShipZone $oZone=NULL) {
	parent::__construct();
	$this->oZone = $oZone;
	$this->nTotSale = NULL;
	$this->nTotShItm = NULL;
	$this->nMaxShPkg = NULL;
    }

    // ++ FIELD ACCCESS ++ //

    protected function ShipZoneObject() {
	return $this->oZone;
    }
    protected function TotalSale() {
	return $this->nTotSale;
    }
    protected function TotalItemShipping() {
	return $this->nTotShItm;
    }
    protected function TotalPackageShipping() {
	return $this->nMaxShPkg;
    }
    protected function TotalShipping() {
	return $this->nTotShItm + $this->nMaxShPkg;
    }
    protected function TotalFinal() {
	return $this->nTotShItm + $this->nMaxShPkg + $this->nTotSale;
    }

    // -- FIELD ACCCESS -- //
    // ++ ACTIONS ++ //

    /*----
      PURPOSE: Like AddItem, but calculates totals
    */
    public function AddLine(cCartLine_base $oLine) {
	$this->AddItem($oLine);
	$this->nTotSale += $oLine->Price_forQty();
	$this->nTotShItm += $oLine->SH_perItem_forQty();
	$this->nMaxShPkg = $oLine->SH_perPkg_Larger($this->nMaxShPkg);
    }
    /*----
      ACTION: Add the calculated totals to the list as Total items
    */ /* NOT USED
    public function AddTotals() {
	$this->AddItem(new clsCartTotal_shop('merch','Merchandise',
	  $this->TotalSale())
	  );
	$this->AddItem(new clsCartTotal_shop('ship','Shipping',
	  $this->TotalShipping())
	  );
	$this->AddItem(new clsCartTotal_shop('final','Total',
	  $this->TotalFinal(),
	  'total-final')
	  );
    } */

    // -- ACTIONS -- //
    // ++ RENDERING ++ //

    /*----
      PURPOSE: does stuff that may appear differently depending on context
    */
    abstract protected function RenderListHeader();
    abstract protected function RenderListFooter();
    abstract protected function RenderTotals();
    abstract protected function RenderTotalsLine();
    abstract protected function RenderShipZone();
    abstract protected function RenderButtonsRow();
    abstract protected function RenderFooter();
    public function Render() {
	$out = static::RenderFormHeader()
	  .$this->RenderListHeader()
	  .$this->RenderItems()
	  .$this->RenderListFooter()
	  .$this->RenderTotals()
	  .$this->RenderFooter()
	  ;
	return $out;
    }


    // -- RENDERING -- //
}
/*%%%%
  PURPOSE: Base abstract class for cart rendering in HTML
*/
abstract class cCartDisplay_full_HTML extends cCartDisplay_full {
    protected function RenderFooter() {
	return KHT_CART_FTR;
    }
    static protected function RenderFormHeader() {
	//$urlCart = KWP_CART_REL;
	$out = <<<__END__
<form method=post id=cart>
  <table class=border>
    <tr><td>
      <table class=cart>
	<tr><td align=center valign=middle>
	  <table class=cart-data>
__END__
	.static::RenderTableHeader()
	;
	return $out;
    }
    static protected function RenderTableHeader() {
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
    protected function RenderTotals() {
	$sTotalMerch	= cCartLine_form::FormatMoney($this->TotalSale());
	$sShipPkg	= cCartLine_form::FormatMoney($this->TotalPackageShipping());
	$sShipItems	= cCartLine_form::FormatMoney($this->TotalItemShipping());
	$sOrdTotal	= cCartLine_form::FormatMoney($this->TotalFinal());
	$sLineTotal	= cCartLine_form::FormatMoney($this->TotalSale() + $this->TotalItemShipping());

	$oZone = $this->ShipZoneObject();
	if (is_null($oZone)) {
	    $sTotalDesc = '<b>order total</b>';
	    $sShipDesc = 's/h package cost:';
	} else {
	    $sShipZone = $this->ShipZoneObject()->Text();
	    $sTotalDesc = '<b>order total if shipping to '.$sShipZone.'</b>:';
	    $sShipDesc = $sShipZone.' s/h package cost:';
	}
	$htFirstTot = $this->RenderTotalsLine();

	return
	  <<<__END__
<tr>$htFirstTot
<td align=right class=total-amount>$sTotalMerch</td>
<td align=right class=total-amount>$sShipItems</td>
<td align=right class=total-amount>$sLineTotal</td>
<td align=right>&dArr;</td>
<tr>
<td align=right  class=total-desc colspan=7>$sShipDesc</td>
<td align=right  class=total-amount>$sShipPkg</td>
<td align=right>&crarr;</td>
</tr>
<tr>
<td align=right  class=total-desc colspan=7>$sTotalDesc</td>
<td align=right  class=total-final>$sOrdTotal</td>
</tr>
__END__
	  .'<tr><td colspan=6>'
	  .$this->RenderShipZone()
	  .'</td></tr>'
	  .$this->RenderButtonsRow()
	  ;
    }
}
/*%%%%
  PURPOSE: Dynamic shopping cart that can be edited, for actual shopping
*/
class cCartDisplay_full_shop extends cCartDisplay_full_HTML {
    protected function RenderListHeader() { return NULL; }	// for now
    protected function RenderListFooter() { return NULL; }	// for now
    protected function RenderTotalsLine() {
	    //$htDelAll = '<span class=text-btn>[<a href="?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_CART.'" title="remove all items from cart">remove all</a>]</span>';
	$htDelAll = '<span class=text-btn><a href="?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_CART.'" title="remove all items from cart">remove all</a></span>';
	$htFirstTot = "<td align=left>$htDelAll</td><td align=right class=total-desc colspan=4>totals:</td>";
	return $htFirstTot;
    }
    protected function RenderShipZone() {
	return 'Shipping destination: '.$this->ShipZoneObject()->ComboBox();
    }
    protected function RenderButtonsRow() {
	return KHT_CART_BTNS_ROW;
    }
}
/*%%%%
  PURPOSE: Static shopping cart that can't be edited, for checkout area
*/
class cCartDisplay_full_ckout extends cCartDisplay_full_HTML {
    protected function RenderListHeader() { return NULL; }	// for now
    protected function RenderListFooter() { return NULL; }	// for now
    protected function RenderTotalsLine() {
	    return '<td align=right class=total-desc colspan=5>totals:</td>';
    }
    protected function RenderShipZone() {
	$oZone = $this->ShipZoneObject();
	if (is_null($oZone)) {
	    return NULL;
	} else {
	    $sZone = $oZone->Text();
	    return "Shipping costs shown assume shipment to <b>$sZone</b> address.";
	}
    }
    protected function RenderButtonsRow() {
	return NULL;
    }
}
class cCartDisplay_full_TEXT extends cCartDisplay_full {
    protected function RenderFooter() {
	return NULL;	// messages to be displayed after cart contents can go here
    }
    static protected function RenderFormHeader() {
	//return cCartLine_text::RenderListHeader_text();
	return NULL;
    }
    protected function RenderListHeader() {
	return cCartLine_text::RenderListHeader_text();
    }
    protected function RenderListFooter() {
	return cCartLine_text::RenderListFooter_text();
    }
    protected function RenderTotalsLine() {
	return str_repeat(' ',26).'         TOTALS: |';
    }
    protected function RenderButtonsRow() {
	return NULL;
    }
    protected function RenderTotals() {
	$sTotalMerch	= cCartLine_text::FormatMoney($this->TotalSale(),7);
	$sShipItems	= cCartLine_text::FormatMoney($this->TotalItemShipping(),10);
	$sLineTotal	= cCartLine_text::FormatMoney($this->TotalSale() + $this->TotalItemShipping(),13);
	$sShipPkg	= cCartLine_text::FormatMoney($this->TotalPackageShipping(),13);
	$sOrdTotal	= cCartLine_text::FormatMoney($this->TotalFinal(),13);

	$oZone = $this->ShipZoneObject();
	if (is_null($oZone)) {
	    $sTotalDesc = 'order total:';
	    $sShipDesc = 's/h per pkg:';
	} else {
	    $sShipZone = $this->ShipZoneObject()->Text();
	    $sTotalDesc = "order total if shipping to $sShipZone:";
	    $sShipDesc = $sShipZone.' s/h per pkg:';
	}
	$sShipDesc = str_pad($sShipDesc,66,' ',STR_PAD_LEFT).$sShipPkg;
	$sTotalDesc = str_pad($sTotalDesc,66,' ',STR_PAD_LEFT).$sOrdTotal;
	$sFirstTot = $this->RenderTotalsLine();

	// these lines will need sprintf formatting
	return
	  <<<__END__
$sFirstTot $sTotalMerch |$sShipItems |$sLineTotal
$sShipDesc
$sTotalDesc
__END__
	  .$this->RenderShipZone()
	  .$this->RenderButtonsRow()
	  ;
    }
    protected function RenderShipZone() {
    }
}
abstract class cCartItem {
    private $oRoot;

    public function __construct() {
	$this->oRoot = NULL;
    }
    protected function Root() {
	return $this->oRoot;
    }
    abstract public function Render();
}
abstract class clsCartTotal extends cCartItem {
    private $sName;
    private $sDescr;
    private $nAmount;
    private $htShow;
    private $htStatus;

    public function __construct(
      $sName,		// unique index for array
      $sDescr,		// description for display
      $nAmount,		// price to show
      $htShow=NULL) {
	parent::__construct();
	$this->sName = $sName;
	$this->sDescr = $sDescr;
	$this->htShow = $htShow;
	$this->nAmount = (int)(round($nAmount * 100));
    }
    public function Name() {
	return $this->sName;
    }
    protected function Text_toShow() {
	return $this->htShow;
    }
    // TODO: This hasn't been tested and won't work as written. Test or eliminate.
    public function Check() {
	throw new exception('Does anything call this?');
	$intCalc = $this->AmountCents();
	$intSaved = $this->nSaved;
	if ($intSaved == $intCalc) {
	    $htOut = $prcSaved.'</td><td><font color=green>ok</font>';
	} else {
	    if ($intSaved < $intCalc) {
		$htOut = '<font color=blue>'.$prcSaved.'</font></td><td> under by <b>'.($prcCalc-$prcSaved).'</b>';
	    } else {
		$htOut = '<font color=red>'.$prcSaved.'</font></td><td> over by <b>'.($prcSaved-$prcCalc).'</b>';
	    }
	    $this->Root()->FoundMismatch(TRUE);	// calculations do not match saved balance
	}
	return $htOut;
    }
    protected function RenderDescr() {
	return $this->sDescr;
    }
    protected function AmountCents() {
	return $this->nAmount;
    }
    protected function AmountDollars() {
	return $this->AmountCents()/100;
    }
    protected function RenderAmount() {
    throw new exception('Does anyone call this?');
	return	cCartLine_form::FormatMoney($this->AmountDollars());
    }
}

class clsCartTotal_shop extends clsCartTotal {
    private $cssAmount;

    public function __construct(
      $sName,		// unique index for array
      $sDescr,		// description for display
      $nAmount,		// price to show
      $cssAmount='total-amount') {

      parent::__construct($sName,$sDescr,$nAmount);
      $this->cssAmount = $cssAmount;
    }
    public function Render() {
      throw new exception('Who calls this?');
	$htNum = $this->RenderAmount();
	$htDescr = $this->RenderDescr();
	$sClass = __CLASS__;
	return <<<__EOL__
  <tr phpclass="$sClass">
    <td colspan=7 align=right class=total-desc>
      <b>$htDescr</b>: $
    </td>
    <td align=right class="{$this->cssAmount}">
      $htNum
    </td>
    <td align=right>&dArr;</td>
  </tr>
__EOL__;
    }
}

class clsCartTotal_admin extends clsCartTotal {
    private $nSaved;

    public function __construct(
      $sName,
      $sDescr,
      $prcCalc,
      $prcSaved,
      $htShow) {
	parent::__construct(
	  $sName,
	  $sDescr,
	  $prcCalc
	  );
	$this->htShow = $htShow;
	$this->nSaved = (int)(round($prcSaved * 100));
    }

    public function Render() {
	$htNum = $this->DisplayValue();
	$htDescr = $this->RenderDescr();
	$sClass = __CLASS__;
	return <<<__EOL__
  <tr phpclass="$sClass">
    <td align=right>
      <b>$htDescr</b>: $
    </td>
    <td align=right>
      $htNum
    </td>
  </tr>
__EOL__;
    }

    protected function RenderSaved() {
	return	sprintf('%0.2f',$this->nSaved/100);
    }
    protected function DisplayValue() {
	if (is_null($this->Text_toShow())) {
	    return $this->RenderSaved();
	} else {
	    return $this->Text_toShow();
	}
    }
}

/*%%%%
  USAGE: base class for non-interactive displays
*/
abstract class cCartLine_base extends cCartItem {
    private $htCatNum;
    private $htDescrip;
    private $nQty;	// item quantity
    private $nPrice;	// item price
    private $nShItem;	// per-item s/h charge
    private $nShPkg;	// per-package s/h minimum charge for this item

    public function __construct($htCatNum,$htDescrip,$nQty,$nPrice,$nShItem,$nShPkg) {
	$this->htCatNum = $htCatNum;
	$this->htDescrip = $htDescrip;
	$this->nQty = $nQty;
	$this->nPrice = $nPrice;
	$this->nShItem = $nShItem;
	$this->nShPkg = $nShPkg;
    }
    public function Name() {
	return $this->CatNum();
    }
    protected function CatNum() {
	return $this->htCatNum;
    }
    protected function Descrip() {
	return $this->htDescrip;
    }
    protected function Qty() {
	return $this->nQty;
    }
    protected function Price() {
	return $this->nPrice;
    }
    protected function SH_perItem() {
	return $this->nShItem;
    }
    protected function SH_perPackage() {
	return $this->nShPkg;
    }
    public function Price_forQty() {
	return $this->Price() * $this->Qty();
    }
    public function SH_perItem_forQty() {
	return $this->SH_perItem() * $this->Qty();
    }
    public function SH_perPkg_Larger($nSH_perPkg) {
	if ($nSH_perPkg > $this->SH_perPackage()) {
	    return $nSH_perPkg;
	} else {
	    return $this->SH_perPackage();
	}
    }
}
class cCartLine_static extends cCartLine_base {

    /*----
      TODO: Somehow eliminate the duplication of code between this and cCartLine_form::Render().
      USAGE: checkout process -- displays static cart
    */
    public function Render() {
	$nQtyOrd = $this->Qty();
	if ($nQtyOrd > 0) {
	    $htLineQty = $nQtyOrd;

	    $mnyPrice = $this->Price();		// item price
	    $mnyPerItm = $this->SH_perItem();		// per-item shipping
	    $mnyPerPkg = $this->SH_perPackage();	// per-pkg minimum shipping
	    $mnyPriceQty = $mnyPrice * $nQtyOrd;	// line total sale
	    $mnyPerItmQty = $mnyPerItm * $nQtyOrd;	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum		= $this->CatNum();
	    $strPrice		= cCartLine_form::FormatMoney($mnyPrice);
	    $strPerItm		= cCartLine_form::FormatMoney($mnyPerItm);
	    $strPriceQty	= cCartLine_form::FormatMoney($mnyPriceQty);
	    $strPerItmQty	= cCartLine_form::FormatMoney($mnyPerItmQty);
	    $strLineTotal	= cCartLine_form::FormatMoney($mnyLineTotal);
	    $strShipPkg		= cCartLine_form::FormatMoney($mnyPerPkg);

	    $htDesc = $this->Descrip();

	    $htDelBtn = '';
	    $sClass = __CLASS__;
	    $out = <<<__END__
<tr phpclass="$sClass">
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
	}
    }
}
/*%%%%
  USAGE: customer-facing shopping cart display
*/
class cCartLine_form extends cCartLine_static {

    private $idLine;

    public function __construct($idLine,$htCatNum,$htDescrip,$nQty,$nPrice,$nShItem,$nShPkg) {
	$this->idLine = $idLine;
	parent::__construct($htCatNum,$htDescrip,$nQty,$nPrice,$nShItem,$nShPkg);
    }
    static public function FormatMoney($iAmt) {
	return clsMoney::Format_withSymbol($iAmt,'<span class="char-currency">$</span>');
    }
    public function Render() {
	$sPrice = static::FormatMoney($this->Price());
	$sShItm = static::FormatMoney($this->SH_perItem());
	$sShPkg = static::FormatMoney($this->SH_perPackage());
	$nQty = $this->Qty();

	$nPriceQty = $this->Price() * $nQty;
	$nShItmQty = $this->SH_perItem() * $nQty;
	$nLineTotal = $nPriceQty + $nShItmQty;	// line total including per-item s/h

	$sPriceQty = static::FormatMoney($nPriceQty);
	$sPerItmQty = static::FormatMoney($nShItmQty);
	$sLineTotal = static::FormatMoney($nLineTotal);

	$htCatNum = $this->CatNum();
	$htDescr = $this->Descrip();

	// form-control name for qty entry field:
	$htQtyCtrlName = KSF_CART_ITEM_PFX.$htCatNum.KSF_CART_ITEM_SFX;
	// HTML for qty entry form field
	$htQtyCtrl = '<input size=2 align=right name="'.$htQtyCtrlName.'" value='.$nQty.'>';
	$htDelBtn = '<span class=text-btn>'
	  .'<a href="'
	  .'?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_LINE
	  .'&'.KSF_CART_EDIT_LINE_ID.'='.$this->idLine
	  .'" title="remove '
	  .$htCatNum
	  .' from cart">remove</a></span> ';

	$sClass = __CLASS__;
	$out = <<<__END__
<tr phpclass="$sClass">
<td>$htDelBtn$htCatNum</td>
<td>$htDescr</td>
<td class=cart-price align=right>$sPrice</td>
<td class=shipping align=right>$sShItm</td>
<td class=qty align=right>$htQtyCtrl</td>
<td class=cart-price align=right>$sPriceQty</td>
<td class=shipping align=right>$sPerItmQty</td>
<td class=total align=right>$sLineTotal</td>
<td class=shipping align=right>$sShPkg</td>
</tr>
__END__;
	    return $out;
    }
}
/*%%%%
  USAGE: plaintext cart for email confirmation
*/
class cCartLine_text extends cCartLine_static {

/*
    private $sFmt;
    public function __construct($sCatNum,$sDescrip,$nQty,$nPrice,$nShItem,$nShPkg) {
	parent::__construct($sCatNum,$sDescrip,$nQty,$nPrice,$nShItem,$nShPkg);
	$this->sFmt = $sFmt;
    }
*/
    protected function FormatString() {
	return '%-16s |$%8.2f |$%6.2f |%4d |$%7.2f |$%9.2f |$%12.2f';

    }
    static public function FormatMoney($iAmt,$nDec=5) {
	return clsMoney::Format_withSymbol($iAmt,'$','',$nDec);
    }
    /*----
      PUBLIC so OrderLine can call it
    */
    static public function RenderListHeader_text() {
	return "===Catalog #=====|==$ ea.===|=it.sh==|=qty=|==\$line==|=\$line s/h=|==LINE TOTAL==\n";
    }
    static public function RenderListFooter_text() {
	$nChars = strlen(static::RenderListHeader_text()-1);	// -1 because of \n
	return str_repeat('=',$nChars)."\n";
    }
      /*----
      RETURNS: Plaintext for the current cart line
      PUBLIC so OrderLine can call it
    */
    public function Render() {
	$out = NULL;

	$nPrice	= $this->Price();
	$nShItm	= $this->SH_perItem();
	$nShPkg	= $this->SH_perPackage();
	$nQty	= $this->Qty();

	$nPriceQty = $this->Price() * $nQty;
	$nShItmQty = $this->SH_perItem() * $nQty;
	$nLineTotal = $nPriceQty + $nShItmQty;	// line total including per-item s/h

	$sCatNum = $this->CatNum();
	$sDescr = $this->Descrip();

	//$ftShipPkg = clsMoney::BasicFormat($dlrPerPkg);

	$out = sprintf($this->FormatString(),
	    $sCatNum,
	    $nPrice,
	    $nShItm,
	    $nQty,
	    $nPriceQty,
	    $nShItmQty,
	    $nLineTotal
	    )
	  ."\n - $sDescr\n";
	return $out;
    }
}