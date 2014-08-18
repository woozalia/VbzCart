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
    private $arStats;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_items');
	  $this->KeyName('ID');
	  $this->ClassSng('clsItem');
	$this->arStats = NULL;
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
      ACTION: Figure various statistics for the requested recordset
      RETURNS: recordset containing statistics
	* minimum price
	* maximum price
	* list of item options
      INPUT: SQL filter for records to be included in the calculations
      USAGE: Internal -- caller should filter out inactive records
	and do any appropriate caching
    */
    protected function StatsFor($sqlFilt) {
	$sql = 'SELECT ID_ItTyp,'
	  .' MIN(PriceBuy) AS PriceBuy_min,'
	  .' MAX(PriceBuy) AS PriceBuy_max,'
	  .' SUM(QtyIn_Stk) AS Qty_InStock,'
	  .' GROUP_CONCAT(DISTINCT CatSfx SEPARATOR ", ") AS SfxList'
	  .' FROM '.$this->NameSQL()
	  .' WHERE '.$sqlFilt
	  .' GROUP BY ID_ItTyp WITH ROLLUP';
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    /*----
      FUTURE: This might be revised to calculate a more thorough listing,
	e.g. it could show options and prices for each item type.
	For now it just compiles a list of all item types, a list of
	all options (for all types), and low and high prices (across
	all types).
      USAGE: This is currently *only* called by StatString_forTitle(),
	so the figuring and storage format can be tweaked according to
	whatever that function needs. Right now it only uses ['summary'],
	so anything else can be reworked as needed.
    */
    protected function StatsFor_Title($idTitle) {
	$arStats = $this->StatsFor_Title_cached($idTitle);
	if (is_null($arStats)) {
	    $sqlFilt = "(ID_Title=$idTitle) AND isForSale";
	    $rsStats = $this->StatsFor($sqlFilt);
	    $arStats = NULL;
	    $sItTyps = NULL;
	    if ($rsStats->RowCount() == 0) {
		$sSummary = 'not available';
	    } else {
		while ($rsStats->NextRow()) {
		    $idItTyp = $rsStats->ItTypID();
		    if (is_null($idItTyp)) {
			// there will be only one row where this happens
			$sOpts = $rsStats->Value('SfxList');
			$prcMin = $rsStats->Value('PriceBuy_min');
			$prcMax = $rsStats->Value('PriceBuy_max');
			// also get stock count -- for now just use calculated field
			$qtyStock = $rsStats->Value('Qty_InStock');
		    } else {
			$rsItTyp = $rsStats->ItTypRecord();
			if (!is_null($sItTyps)) {
			    $sItTyps .= ', ';
			}
			$sItTyps .= $rsItTyp->Name();
		    }
		}
		$arStats['price-min'] = $prcMin;
		$arStats['price-max'] = $prcMax;
		$arStats['opt-list'] = $sOpts;
		$arStats['stock-qty'] = $qtyStock;
		$arStats['types'] = $sItTyps;
		$sPrcMin = clsMoney::BasicFormat($prcMin);
		$sPrcMax = clsMoney::BasicFormat($prcMax);
		if ($sPrcMin == $sPrcMax) {
		    $sPrc = $sPrcMin;
		} else {
		    $sPrc = $sPrcMin.' - '.$sPrcMax;
		}
		if ($qtyStock == 0) {
		    $sStock = 'out of stock';
		} else {
		    $sStock = $qtyStock.' in stock';
		}
		$sSummary = "$sItTyps: $sOpts @ $sPrc ($sStock)";
	    }
	    $arStats['summary'] = $sSummary;
	    $this->StatsFor_Title_cached($idTitle,$arStats);	// save stats
	}
	return $arStats;
    }
    public function StatString_forTitle($idTitle) {
	$arStats = $this->StatsFor_Title($idTitle);
	return $arStats['summary'];
    }
    /*----
      RETURNS: either cached stats or NULL
      INPUT:
	$idTitle = ID of title for which stats are needed
	$arStats = stats array to cache, or NULL
    */
    protected function StatsFor_Title_cached($idTitle,array $arStats=NULL) {
	if (is_null($arStats)) {
	    if (!is_null($this->arStats)) {
		if (array_key_exists('titles',$this->arStats)) {
		    if (array_key_exists($idTitle,$this->arStats)) {
			$arStats = $this->arStats['titles'][$idTitle];
		    }
		}
	    }
	} else {
	    $this->arStats['titles'][$idTitle] = $arStats;
	}
	return $arStats;
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
    private $rcTitle;
    private $rcItTyp;
    private $rcItOpt;
    private $rcShCost;
//    private $arSpecs;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->rcTitle = NULL;
	$this->rcItTyp = NULL;
	$this->rcItOpt = NULL;
	$this->rcShCost = NULL;
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function ItTypsClass() {
	return 'clsItTyps';
    }
    protected function ItOptsClass() {
	return 'clsItOpts';
    }
    protected function ShipCostsClass() {
	return 'clsShipCosts';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function ItTypTable($id=NULL) {
	return $this->Engine()->Make($this->ItTypsClass(),$id);
    }
    protected function ItOptTable($id=NULL) {
	return $this->Engine()->Make($this->ItOptsClass(),$id);
    }
    protected function ShipCostTable($id=NULL) {
	return $this->Engine()->Make($this->ShipCostsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    // DEPRECATED - use TitleObj()
    public function Title() {
	throw new exception('Title() is deprecated; use TitleRecord().');
	//return $this->TitleObj();
    }
    public function TitleObj() {
	throw new exception('TitleObj() is deprecated; use TitleRecord().');
    }
    public function TitleRecord() {
	$doLoad = TRUE;
	$idTitle = $this->TitleID();
	if (is_object($this->rcTitle)) {
	    if ($this->rcTitle->KeyValue() == $idTitle) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcTitle = $this->Engine()->Titles($idTitle);
	}
	return $this->rcTitle;
    }
    public function Supplier() {
      throw new exception('Supplier() is deprecated; use SupplierRecord().');
    }
    public function SupplierRecord() {
	return $this->TitleRecord()->SuppObj();
    }
    public function ItTyp() {
      throw new exception('ItTyp() is deprecated; use ItTypRecord().');
    }
    public function ItTypRecord() {
	$id = $this->ItTypID();
	$rc = $this->ItTypTable($id);
	return $rc;
    }
    public function ItOpt() {
      throw new exception('ItOpt() is deprecated; use ItOptRecord().');
    }
    public function ItOptRecord() {
	$id = $this->ItOptID();
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->ItOptTable($id);
	}
    }
    // DEPRECATED - use ShipCostObj()
    public function ShCost() {
	throw new exception('ShCost() is deprecated; use ShipCostRecord().');
	return $this->ShipCostObj();
    }
    /*----
      HISTORY:
	2010-10-19 created from contents of ShCost()
    */
    public function ShipCostObj() {
	throw new exception('ShipCostObj() is deprecated; use ShipCostRecord().');
    }
    public function ShipCostRecord() {
/*
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
*/
	if (is_null($this->rcShCost)) {
	    $this->rcShCost = $this->ShipCostTable($this->ShipCostID());
	}
	return $this->rcShCost;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ DATA FIELD ACCESS ++ //

    protected function TitleID() {
	return $this->Value('ID_Title');
    }
    /*----
      PUBLIC because table::StatsFor_Title() calls it
    */
    public function ItTypID() {
	return $this->Value('ID_ItTyp');
    }
    protected function ItOptID() {
	return $this->Value('ID_ItOpt');
    }
    public function CatNum() {
	return $this->Value('CatNum');
    }
    public function IsForSale() {
	return $this->Value('isForSale');
    }
    public function Descr() {
	return $this->Value('Descr');
    }
    public function PriceBuy() {
	return $this->Value('PriceBuy');
    }
    protected function Qty_InStock() {
	return $this->Value('QtyIn_Stk');
    }

    // ++ DATA FIELD ACCESS: dependent records ++ //

    protected function ItOptDescr() {
	$rc = $this->ItOptRecord();
	if (is_null($rc)) {
	    return NULL;
	} else {
	    return $rc->DescrFull();
	}
    }

    // -- DATA FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    public function ShipCostID() {
	return $this->Value('ID_ShipCost');
    }
    public function DescSpecs() {
	throw new exception('DescSpecs() is deprecated; call individual functions as needed.');

	if (is_null($this->arSpecs)) {
	$this->rcTitle	= $this->Title();
	$this->rcItTyp	= $this->ItTyp();
	$this->rcItOpt	= $this->ItOpt();

	$out['tname']	= $this->rcTitle->Name;
	$out['ittyp']	= $this->rcItTyp->Name($this->Qty);
	$out['itopt']	= $this->rcItOpt->Descr;
	return $out;
	}
    }
/* 2014-03-02 why do we ever need iSpecs?
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
*/
    public function DescLong() {
	$sDesc = $this->Descr();
	if (is_null($sDesc)) {
//	    $sp = $this->DescSpecs($iSpecs);

//	    $strItOpt = $sp['itopt'];

	    $sName = $this->TitleRecord()->NameText();
	    //$sItTyp = $this->ItTypRecord()->Name($this->Qty());	// there is no Qty field
	    $sItTyp = $this->ItTypRecord()->Name();
	    $sItOpt = $this->ItOptDescr();

//	    $out = '"'.$sp['tname'].'" ('.$sp['ittyp'];
	    $sDesc = '"'.$sName.'" ('.$sItTyp;
	    if (!is_null($sItOpt)) {
		$sDesc .= ' - '.$sItOpt;
	    }
	    $sDesc .= ')';
	}
	return $sDesc;
    }
    /*----
      RETURNS: Item description as HTML
    */
//    public function DescLong_ht(array $iSpecs=NULL) {	// as HTML
    public function DescLong_ht() {
//	$sp = $this->DescSpecs($iSpecs);

	$htTitleName = '<i>'.$this->TitleRecord()->LinkName().'</i>';
//	$strItOpt = $sp['itopt'];
	$sItOpt = $this->ItOptDescr();

//	$out = $htTitleName.' ('.$sp['ittyp'];
//	$out = $htTitleName.' ('.$this->ItTypRecord()->Name($this->Qty());	// there is no Qty field
	$out = $htTitleName.' ('.$this->ItTypRecord()->Name();
	if (!is_null($sItOpt)) {
	    $out .= ' - '.$sItOpt;
	}
	$out .= ')';

	return $out;
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

    // -- FIELD CALCULATIONS -- //
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

	$htCtrlName = KSF_CART_ITEM_PFX.$sCatNum.KSF_CART_ITEM_SFX;
	$out = <<<__END__
  <tr class=$strCls><!-- ID=$id -->
    <td>&emsp;$htDescr</td>
    <td>$htStat</td>
    <td align=right><i>$htPrList</i></td>
    <td align=right>$htPrSell</td>
    <td>
      <input size=3 name="$htCtrlName"></td>
  </tr>
__END__;
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
