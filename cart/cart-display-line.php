<?php
/*
  FILE: cart-display-line.php - rendering of individual cart lines
  HISTORY:
    2016-03-28 split off single-line classes from cart-display.php
      because they're a separate line of inheritance
*/
abstract class vcCartItem {
    private $oRoot;

    public function __construct() {
	$this->oRoot = NULL;
    }
    protected function Root() {
	return $this->oRoot;
    }
    abstract public function Render();
}
abstract class vcCartTotal extends vcCartItem {
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
    public function Amount_asDollars() {
	return $this->nAmount/100;
    }
    protected function Text_toShow() {
	return $this->htShow;
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
}

abstract class vcCartTotal_shop extends vcCartTotal {
    private $cssAmount;

    public function __construct(
      $sName,		// unique index for array
      $sDescr,		// description for display
      $nAmount,		// price to show
      $cssAmount='total-amount') {

      parent::__construct($sName,$sDescr,$nAmount);
      $this->cssAmount = $cssAmount;
    }
}

class vcCartTotal_admin extends vcCartTotal {
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
abstract class vcCartLine_base extends vcCartItem {
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
class vcCartLine_static extends vcCartLine_base {

    /*----
      TODO: Somehow eliminate the duplication of code between this and cCartLine_form::Render().
      USAGE: checkout process -- displays static cart
      HISTORY:
	2016-03-25 Verified that this is being called at checkout time.
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
	    $strPrice		= vcCartLine_form::FormatMoney($mnyPrice);
	    $strPerItm		= vcCartLine_form::FormatMoney($mnyPerItm);
	    $strPriceQty	= vcCartLine_form::FormatMoney($mnyPriceQty);
	    $strPerItmQty	= vcCartLine_form::FormatMoney($mnyPerItmQty);
	    $strLineTotal	= vcCartLine_form::FormatMoney($mnyLineTotal);
	    $strShipPkg		= vcCartLine_form::FormatMoney($mnyPerPkg);

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
class vcCartLine_form extends vcCartLine_static {

    private $idLine;

    public function __construct($idLine,$htCatNum,$htDescrip,$nQty,$nPrice,$nShItem,$nShPkg) {
	$this->idLine = $idLine;
	parent::__construct($htCatNum,$htDescrip,$nQty,$nPrice,$nShItem,$nShPkg);
    }
    static public function FormatMoney($iAmt) {
	return fcMoney::Format_withSymbol($iAmt,'<span class="char-currency">$</span>');
    }
    // CALLED BY: standard customer-facing cart
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
	  .'?'.KSF_CART_DELETE.'='.$this->idLine
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
class vcCartLine_text extends vcCartLine_static {

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
	return fcMoney::Format_withSymbol($iAmt,'$','',$nDec);
    }
    /*----
      PUBLIC so OrderLine can call it
    */
    static public function RenderListHeader_text() {
	// 15 | 10 | 8 | 5 | 9 | 11 | 14
	return <<<__END__
                 |          |per-item|     | line    | line
                 | per-item |shipping|     | total   | per-item
 Catalog #       | price    |cost    | qty | sale    | shipping  | FINAL TOTAL
=================+==========+========+=====+=========+===========+==============

__END__;
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