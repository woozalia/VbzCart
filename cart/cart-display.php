<?php
/*
  FILE: cart-display.php (was: order-total.php) -- helper class for checking Cart/Order totals
  HISTORY:
    2014-02-23 adapted from code in order.php
    2014-09-14 Moved dropins/orders/total.php to cart-line-total.php
    2014-10-12 ...except apparently I didn't. Done now.
    2014-10-26 Adding ability to display cart lines too; the idea is that
      both Carts and Orders should use this code to display the cart contents/totals.
*/

class vcCartDisplay {
    private $doAllMatch;
    private $arItems;

    public function __construct() {
	$this->doAllMatch = TRUE;
    }
    public function AddItem(vcCartItem $oItem) {
	$sName = $oItem->Name();
	$this->arItems[$sName] = $oItem;
    }
    // CALLED BY: Cart object, when converting to Order object
    public function GetItem($sName) {
	return $this->arItems[$sName];
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
	if (!is_array($this->arItems)) {
	    throw new exception('Internal error: no items in cart array.');
	}
	foreach ($this->arItems as $sName => $oTotal) {
	    $out .= $oTotal->Render();
	}
	return $out;
    }
}
abstract class vcCartDisplay_full extends vcCartDisplay {
    private $oZone;	// shipping zone object
    private $nTotSale;	// sale total
    private $nTotShItm;	// total per-item S/H
    private $nMaxShPkg;	// maximum per-package S/H

    public function __construct(vcShipCountry $oZone=NULL) {
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
    // PUBLIC so it can be stored in the Order record
    public function TotalSale() {
	return $this->nTotSale;
    }
    // PUBLIC so it can be stored in the Order record
    public function TotalItemShipping() {
	return $this->nTotShItm;
    }
    // PUBLIC so it can be stored in the Order record
    public function TotalPackageShipping() {
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
    public function AddLine(vcCartLine_base $oLine) {
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
abstract class vcCartDisplay_full_HTML extends vcCartDisplay_full {
    protected function RenderFooter() {
	return KHT_CART_FTR;
    }
    static protected function RenderFormHeader() {
	//$urlCart = KWP_CART_REL;
	$out = <<<__END__
<form method=post id=cart>
  <table class=border id=cart-table>
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
	$sTotalMerch	= vcCartLine_form::FormatMoney($this->TotalSale());
	$sShipPkg	= vcCartLine_form::FormatMoney($this->TotalPackageShipping());
	$sShipItems	= vcCartLine_form::FormatMoney($this->TotalItemShipping());
	$sOrdTotal	= vcCartLine_form::FormatMoney($this->TotalFinal());
	$sLineTotal	= vcCartLine_form::FormatMoney($this->TotalSale() + $this->TotalItemShipping());

	$oZone = $this->ShipZoneObject();
	if (is_null($oZone)) {
	    $sTotalDesc = '<b>order total</b>';
	    $sShipDesc = 's/h package cost:';
	} else {
	    $sShipZone = $this->ShipZoneObject()->Description();
	    $sTotalDesc = "<b>order total if shipping $sShipZone</b>:";
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
class vcCartDisplay_full_shop extends vcCartDisplay_full_HTML {
    protected function RenderListHeader() { return NULL; }	// for now
    protected function RenderListFooter() { return NULL; }	// for now
    protected function RenderTotalsLine() {
	$urqCmdDel = KSF_CART_DELETE;
	$urqValAll = KSF_CART_DELETE_ALL;
	$htDelAll = 
	  '<span class=text-btn>'
	  ."<a href='?$urqCmdDel=$urqValAll' title='remove all items from cart'>remove all</a></span>"
	  ;
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
class vcCartDisplay_full_ckout extends vcCartDisplay_full_HTML {
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
	    $sZone = $oZone->Description();
	    return "Shipping costs shown assume shipment <b>$sZone</b>.";
	}
    }
    protected function RenderButtonsRow() {
	return NULL;
    }
}
class vcCartDisplay_full_TEXT extends vcCartDisplay_full {
    protected function RenderFooter() {
	return NULL;	// messages to be displayed after cart contents can go here
    }
    static protected function RenderFormHeader() {
	//return cCartLine_text::RenderListHeader_text();
	return NULL;
    }
    protected function RenderListHeader() {
	return vcCartLine_text::RenderListHeader_text();
    }
    protected function RenderListFooter() {
	return vcCartLine_text::RenderListFooter_text();
    }
    protected function RenderTotalsLine() {
	return str_repeat(' ',26).'         TOTALS: |';
    }
    protected function RenderButtonsRow() {
	return NULL;
    }
    protected function RenderTotals() {
	$sTotalMerch	= vcCartLine_text::FormatMoney($this->TotalSale(),7);
	$sShipItems	= vcCartLine_text::FormatMoney($this->TotalItemShipping(),10);
	$sLineTotal	= vcCartLine_text::FormatMoney($this->TotalSale() + $this->TotalItemShipping(),13);
	$sShipPkg	= vcCartLine_text::FormatMoney($this->TotalPackageShipping(),13);
	$sOrdTotal	= vcCartLine_text::FormatMoney($this->TotalFinal(),13);

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
