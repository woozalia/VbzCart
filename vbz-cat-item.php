<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Items
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-item.php from base.cat.php
*/

require_once('vbz-const-cart.php');

class clsItems extends clsVbzTable {

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_items');
	  $this->KeyName('ID');
	  $this->ClassSng('clsItem');
    }
    /*----
      ACTION: Finds the Item with the given CatNum, and returns a clsItem object
    */
    public function Get_byCatNum($iCatNum) {
	$sqlCatNum = $this->objDB->SafeParam(strtoupper($iCatNum));
	$objItem = $this->GetData('CatNum="'.$sqlCatNum.'"');
	if ($objItem->HasRows()) {
	    $objItem->NextRow();
	    return $objItem;
	} else {
	    return NULL;
	}
    }
    public function Search_byCatNum($iCatNum) {
	$sqlCatNum = $this->objDB->SafeParam(strtoupper($iCatNum));
	$objItem = $this->GetData('CatNum LIKE "%'.$sqlCatNum.'%"');
	if ($objItem->HasRows()) {
	    return $objItem;
	} else {
	    return NULL;
	}
    }
    /*----
      RETURNS: Table header for list of available items on catalog Title pages
      HISTORY:
	2011-01-24 created/corrected from code in Title page-display function
    */
    static public function Render_TableHdr() {
	return "\n<tr>"
	  .'<th align=left>Option</th>'
	  .'<th>Status</th>'
	  .'<th align=center><i>List<br>Price</th>'
	  .'<th align=center class=title-price>Our<br>Price</th>'
	  .'<th align=center class=orderQty>Order<br>Qty.</th>'
	  .'</tr>';
    }
}
/* ===============
 CLASS: clsItem
 NOTES:
  * "in stock" always refers to stock for sale, not stock which has already been purchased
  * 2009-12-03: The above note does not clarify anything.
  * Four methods were moved here from clsShopCartLine in shop.php: ItemSpecs(), ItemDesc(), ItemDesc_ht(), ItemDesc_wt()
    They are used for displaying a full description of an item, in both shop.php and SpecialVbzAdmin
*/
class clsItem extends clsDataSet {
// object cache
    private $objTitle;
    private $objItTyp;
    private $objItOpt;

    public function CatNum() {
	return $this->Value('CatNum');
    }
    public function DescSpecs(array $iSpecs=NULL) {
	if (is_null($iSpecs)) {
	    $this->objTitle	= $this->Title();
	    $this->objItTyp	= $this->ItTyp();
	    $this->objItOpt	= $this->ItOpt();

	    $out['tname']	= $this->objTitle->Name;
	    $out['ittyp']	= $this->objItTyp->Name($this->Qty);
	    $out['itopt']	= $this->objItOpt->Descr;
	    return $out;
	} else {
	    return $iSpecs;
	}
    }
    public function DescLong(array $iSpecs=NULL) {	// plaintext
	if (is_null($this->Value('Descr'))) {
	    $sp = $this->DescSpecs($iSpecs);

	    $strItOpt = $sp['itopt'];

	    $out = '"'.$sp['tname'].'" ('.$sp['ittyp'];
	    if (!is_null($strItOpt)) {
		$out .= ' - '.$strItOpt;
	    }
	    $out .= ')';
	} else {
	    $out = $this->Value('Descr');
	}

	return $out;
    }
    public function DescLong_ht(array $iSpecs=NULL) {	// as HTML
	$sp = $this->DescSpecs($iSpecs);

	$htTitleName = '<i>'.$this->Title()->LinkName().'</i>';
	$strItOpt = $sp['itopt'];

	$out = $htTitleName.' ('.$sp['ittyp'];
	if (!is_null($strItOpt)) {
	    $out .= ' - '.$strItOpt;
	}
	$out .= ')';

	return $out;
    }
    /*-----
      ASSUMES:
	  This item is ForSale, so isForSale = true and (qtyForSale>0 || isInPrint) = true
      HISTORY:
	  2011-01-24 Renamed Print_TableRow() -> Render_TableRow; corrected to match header
    */
    public function Render_TableRow() {
	$arStat = $this->AvailStatus();
	$strCls = $arStat['cls'];

	$id = $this->KeyValue();
	$sCatNum = $this->Value('CatNum');
	$htDescr = $this->Value('ItOpt_Descr');
	$htStat = $arStat['html'];
	$htPrList = DataCurr($this->Value('PriceList'));
	$htPrSell = DataCurr($this->Value('PriceSell'));
	
	$out = "\n<tr class=$strCls><!-- ID=$id -->"
	  ."\n\t<td>&emsp;$htDescr</td>"
	  ."\n\t<td>$htStat</td>"
	  ."\n\t<td align=right><i>$htPrList</i></td>"
	  ."\n\t<td align=right>$htPrSell</td>"
	  ."\n\t<td>"
	    .'<input size=3 name="'
	    .KSF_CART_ITEM_PFX.$sCatNum.KSF_CART_ITEM_SFX
	    .'"></td>'
	  ."\n\t</tr>"
	  ;
	return $out;
    }
    /*----
      ACTION: Returns an array with human-friendly text about the item's availability status
      RETURNS:
	array['html']: status text, in HTML format
	array['cls']: class to use for displaying item row in a table
      USED BY: Render_TableRow()
      NOTE: This probably does not actually need to be a separate method; I thought I could reuse it to generate
	status for titles, but that doesn't make sense. Maybe it will be easier to adapt, though, as a separate method.
      HISTORY:
	2010-11-16 Modified truth table for in-print status so that if isInPrint=FALSE, then status always shows
	  "out of print" even if isCurrent=FALSE. What happens when a supplier has been discontinued? Maybe we need to
	  check that separately. Wait for an example to come up, for easier debugging.
	2011-01-24 Corrected to use cat_items fields
    */
    private function AvailStatus() {
//echo 'SQL=['.$this->sqlMake.']';
//echo '<pre>'.print_r($this->Row,TRUE).'</pre>';
      $qtyInStock = $this->Value('QtyIn_Stk');
	if ($qtyInStock) {
	    $strCls = 'inStock';
	    $strStk = $qtyInStock.' in stock';
	} else {
	    $strCls = 'noStock';
	    $strStk = 'none in stock';
	}
	$isInPrint = $this->Value('isInPrint');
	if ($isInPrint) {
	    if ($this->Value('isCurrent')) {
		    if ($qtyInStock) {
			$txt = $strStk.'; more available';
		    } else {
			$txt = '<a title="explanation..." href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'">available, not in stock</a>';
		    }
	    } else {
		if ($qtyInStock) {
		    $txt = $strStk.'; in-print status uncertain';
		} else {
		    $txt = $strStk.'; availability uncertain';
		}
	    }
	} else {
	    if (is_null($isInPrint)) {
		$txt = '<b>'.$strStk.'</b> - <i>possibly out of print</i>';
	    } else {
		$txt = '<b>'.$strStk.'</b> - <i>out of print!</i>';
	    }
	}
	$arOut['html'] = $txt;
	$arOut['cls'] = $strCls;
	return $arOut;
    }

    // DEPRECATED - use TitleObj()
    public function Title() {
	return $this->TitleObj();
    }
    public function TitleObj() {
	$doLoad = TRUE;
	if (is_object($this->objTitle)) {
	    if ($this->objTitle->ID == $this->ID_Title) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objTitle = $this->objDB->Titles()->GetItem($this->ID_Title);
	}
	return $this->objTitle;
    }
  public function Supplier() {
      return $this->TitleObj()->Supplier();
  }
  public function ItTyp() {
      $doLoad = TRUE;
      if (is_object($this->objItTyp)) {
	  if ($this->objItTyp->ID == $this->ID_ItTyp) {
	      $doLoad = FALSE;
	  }
      }
      if ($doLoad) {
	  $this->objItTyp = $this->objDB->ItTyps()->GetItem($this->ID_ItTyp);
      }
      return $this->objItTyp;
  }
  public function ItOpt() {
    $doLoad = TRUE;
    if (is_object($this->objItOpt)) {
      if ($this->objItOpt->ID == $this->ID_ItOpt) {
        $doLoad = FALSE;
      }
    }
    if ($doLoad) {
      $this->objItOpt = $this->objDB->ItOpts()->GetItem($this->ID_ItOpt);
    }
    return $this->objItOpt;
  }
    // DEPRECATED - use ShipCostObj()
    public function ShCost() {
	return $this->ShipCostObj();
    }
    /*----
      HISTORY:
	2010-10-19 created from contents of ShCost()
    */
    public function ShipCostObj() {
	$doLoad = FALSE;
	if (empty($this->objShCost)) {
	    $doLoad = TRUE;
	} elseif ($this->objShCost->ID != $this->ID_ShipCost) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->objShCost = $this->objDB->ShipCosts()->GetItem($this->ID_ShipCost);
	}
	return $this->objShCost;
    }
    /*----
      RETURNS: The item's per-item shipping price for the given shipping zone
      FUTURE: Rename to ShPerItm_forZone()
    */
    public function ShipPriceItem(clsShipZone $iZone) {
	return $iZone->CalcPerPkg($this->ShPerItm());
    }
    /*----
      RETURNS: The item's per-package shipping price for the given shipping zone
      FUTURE: Rename to ShPerPkg_forZone()
    */
    public function ShipPricePkg(clsShipZone $iZone) {
	return $iZone->CalcPerPkg($this->ShPerPkg());
    }
    /*----
      RETURNS: The item's base per-item shipping price (no zone calculations)
    */
    public function ShPerItm() {
	return $this->ShipCostObj()->PerItem();
    }
    /*----
      RETURNS: The item's per-package shipping price, with no zone calculations
    */
    public function ShPerPkg() {
	return $this->ShipCostObj()->PerPkg();
    }
}
/*====
  PURPOSE: clsItems with additional catalog information
*/
class clsItems_info_Cat extends clsItems {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryCat_Items');
	  //$this->ClassSng('clsItem_info_Cat');
    }
}
