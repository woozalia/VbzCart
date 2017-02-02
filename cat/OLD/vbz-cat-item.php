<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Items
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-item.php from base.cat.php
*/

require_once(KFP_LIB_VBZ.'/const/vbz-const-cart.php');

class clsItems extends vcVbzTable_shop {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_items');
	  $this->KeyName('ID');
	  $this->ClassSng('clsItem');
    }

    // -- SETUP -- //
    // ++ QUERIES ++ //
    
    protected function ItemInfoTable() {
	return $this->Engine()->Make('vcqtItemsInfo');
    }
    
    // -- QUERIES -- //
    // ++ RECORDS ++ //

    //++dependent++//
    
    public function Records_forTitle($idTitle) {
	// "dumped" records are effectively no longer in a Title
	$sqlFilt = "(IFNULL(isDumped,0)=0) AND (ID_Title=$idTitle)";
	$rs = $this->GetRecords($sqlFilt,'ItOpt_Sort');
	return $rs;
    }
    
    //--dependent--//
    //++search++//
    
    /*----
      ACTION: Finds the Item with the given CatNum, and returns a clsItem object
      INPUT:
	$sCatNum: CatNum to find (case-insensitive)
	$doAll: if FALSE (default), stops after first record (faster)
    */
    public function Get_byCatNum($sCatNum,$doAll=FALSE) {
	$sqlCatNum = $this->Engine()->SanitizeAndQuote(strtoupper($sCatNum));
	$sql = "WHERE CatNum=$sqlCatNum";
	if (!$doAll) {
	    $sql .= ' LIMIT 1';
	}
	$rcItem = $this->DataSet($sql);
	if ($rcItem->HasRows()) {
	    if (!$doAll) {	// only load first row if we're only expecting one
		$rcItem->NextRow();
	    }
	    return $rcItem;
	} else {
	    return NULL;
	}
    }
    public function Search_byCatNum($sCatNum) {
	$sqlCatNum = $this->Engine()->SafeParam(strtoupper($sCatNum));
	$rsItem = $this->GetData('CatNum LIKE "%'.$sqlCatNum.'%"');
	if ($rsItem->HasRows()) {
	    return $rsItem;
	} else {
	    return NULL;
	}
    }
    
    //--search--//
    
    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: Figure various statistics for the recordset returned by the given filter
      RETURNS: recordset containing statistics
	* minimum price
	* maximum price
	* list of item options
      INPUT: SQL filter for records to be included in the calculations
      USAGE: Internal -- caller should filter out inactive records
	and do any appropriate caching
      PUBLIC so Title can call it
      HISTORY:
	2016-02-10 Revised to get stock quantity from stock records instead of item records
    */ /* replacing this with item-info call (GetRecords_forStats())
    public function StatsFor($sqlFilt) {
	//$qtII = $this->
	$qoStats = $this->ItemInfoTable()->SQLobj_Stats('ID_ItTyp');
	$sql = $qoStats->Render();
	// rewrite in progress
/*    
	die('SQL: <pre>'.$qoStats->Render().'</pre>');
    
	//$sqlStock = vctStockItems::SQLtext_forItemStatus();

	$sql = <<<__END__
SELECT ID_ItTyp,
    MIN(PriceBuy) AS PriceBuy_min,
    MAX(PriceBuy) AS PriceBuy_max,
    SUM(QtyTotal) AS Qty_InStock,
    GROUP_CONCAT(DISTINCT CatSfx SEPARATOR ", ") AS SfxList
  FROM $sqlThis AS i
    LEFT JOIN ($sqlStock) AS st ON i.ID=st.ID_Item
  WHERE $sqlFilt
 GROUP BY ID_ItTyp WITH ROLLUP
__END__;

//*/
    /*
	$sql = 'SELECT ID_ItTyp,'
	  .' MIN(PriceBuy) AS PriceBuy_min,'
	  .' MAX(PriceBuy) AS PriceBuy_max,'
	  .' SUM(QtyIn_Stk) AS Qty_InStock,'
	  .' GROUP_CONCAT(DISTINCT CatSfx SEPARATOR ", ") AS SfxList'
	  .' FROM '.$this->NameSQL()
	  .' WHERE '.$sqlFilt
	  .' GROUP BY ID_ItTyp WITH ROLLUP'; //*/
/*
	$rs = $this->DataSQL($sql);
	return $rs;
    } 
//*/
    // -- CALCULATIONS -- //
    // ++ WEB UI ++ //

    /*----
      RETURNS: Table header for list of available items on catalog Title pages
      HISTORY:
	2011-01-24 created/corrected from code in Title page-display function
    */
    static public function Render_TableHdr_forTitle() {
	return <<<__END__

  <tr>
    <th align=left>Option</th>
    <th>Status</th>
    <th align=center><i>List<br>Price</i></th>
    <th align=center class=title-price>Our<br>Price</th>
    <th align=center class=orderQty>Order<br>Qty.</th>
    </tr>
__END__;
    }

    // -- WEB UI -- //
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
    // ++ FIELD VALUES ++ //

    // PUBLIC so Title record can set it when creating new Item record
    public function TitleID($id=NULL) {
	return $this->Value('ID_Title',$id);
    }
    // PUBLIC so Title record can set it when creating new Item record
    public function SupplierID($id=NULL) {
	return $this->Value('ID_Supp',$id);
    }
    /*----
      PUBLIC because table::StatsFor_Title() calls it
    */
    public function ItemTypeID() {
	return $this->Value('ID_ItTyp');
    }
    protected function ItemOptionID() {
	return $this->Value('ID_ItOpt');
    }
    protected function CatKey() {
	return $this->Value('CatSfx');
    }
    protected function HasCatKey() {
	return $this->HasValue('CatSfx');
    }
    public function CatNum() {
	return $this->Value('CatNum');
    }
    public function IsForSale() {
	// 2016-02-10 temporarily re-enabling this so it won't be emitting errors all night
	throw new exception('Shop classes should not be using IsForSale() anymore.');
	//return $this->Value('isForSale');
	return $this->Value('isAvail');
    }
    public function Description() {
	return $this->Value('Descr');
    }
    public function Descr() {	// TODO: deprecate; replace with Description()
	return $this->Value('Descr');
    }
    public function PriceBuy() {
	return $this->Value('PriceBuy');
    }
    /*----
      PUBLIC because Order object uses it for Cart->Order conversion
    */
    public function PriceSell() {
	return $this->Value('PriceSell');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD LOOKUP ++ //

    protected function ItOptDescr() {
	$rc = $this->ItemOptionRecord();
	if (is_null($rc)) {
	    return NULL;
	} else {
	    if ($rc->IsNew()) {
		$idItOpt = $this->ItemOptionID();
		return "<i>itOpt #$idItOpt n/f</i>";
	    } else {
		return $rc->DescrFull();
	    }
	}
    }

    // -- FIELD LOOKUP -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function HasTitle() {
	return !empty($this->TitleID());
    }
    protected function Title_CatNum() {
	if ($this->HasTitle()) {
	    $out = $this->TitleRecord()->CatNum();
	} else {
	    $out = $this->SupplierRecord()->CatKey()
	      .'??'
	      ;
	}
	return $out;
    }
    protected function CatNum_calc($sSep='-') {
	return fcString::ConcatArray($sSep,array($this->Title_CatNum(),$this->CatKey()));
    }
    public function ShipCostID() {
	return $this->Value('ID_ShipCost');
    }
    public function DescSpecs() {
	throw new exception('DescSpecs() is deprecated; call individual functions as needed.');

	if (is_null($this->arSpecs)) {
	$this->rcTitle	= $this->Title();
	$this->rcItTyp	= $this->ItemTypeRecord();
	$this->rcItOpt	= $this->ItemOptionRecord();

	$out['tname']	= $this->rcTitle->Name;
	$out['ittyp']	= $this->rcItTyp->Name($this->Qty);
	$out['itopt']	= $this->rcItOpt->Descr;
	return $out;
	}
    }
    public function DescLong() {
	$sDesc = $this->Descr();
	if (is_null($sDesc)) {
//	    $sp = $this->DescSpecs($iSpecs);

//	    $strItOpt = $sp['itopt'];

	    $sName = $this->TitleRecord()->NameText();
	    $sItTyp = $this->ItemTypeRecord()->Name();
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

	$htTitleName = '<i>'.$this->TitleRecord()->LinkName().'</i>';
	$sItOpt = $this->ItOptDescr();

	$out = $htTitleName.' ('.$this->ItemTypeRecord()->Name();
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
	throw new exception('ShipPriceItem() is deprecated; call ShipPriceUnit() instead.');
    }
    public function ShipPriceUnit(clsShipZone $iZone) {
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
	return $this->ShipCostRecord()->PerItem();
    }
    /*----
      RETURNS: The item's per-package shipping price, with no zone calculations
    */
    public function ShPerPkg() {
	return $this->ShipCostRecord()->PerPkg();
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function ItemTypesClass() {
	return 'clsItTyps';
    }
    protected function ItemOptionsClass() {
	return 'clsItOpts';
    }
    protected function ShipCostsClass() {
	return 'clsShipCosts';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function ItemTypeTable($id=NULL) {
	return $this->Engine()->Make($this->ItemTypesClass(),$id);
    }
    protected function ItemOptionTable($id=NULL) {
	return $this->Engine()->Make($this->ItemOptionsClass(),$id);
    }
    protected function ShipCostTable($id=NULL) {
	return $this->Engine()->Make($this->ShipCostsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    // DEPRECATED - use TitleObj()
    public function Title() {
	throw new exception('Title() is deprecated; use TitleRecord().');
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
//	return $this->TitleRecord()->SupplierRecord();	// OLD
	return $this->SupplierTable($this->SupplierID());
    }
    public function ItTyp() {
      throw new exception('ItTyp() is deprecated; use ItemTypeRecord().');
    }
    public function ItTypRecord() {
      throw new exception('ItTypRecord() is deprecated; use ItemTypeRecord().');
    }
    // PUBLIC so Titles object can use it when searching
    public function ItemTypeRecord() {
	$id = $this->ItemTypeID();
	$rc = $this->ItemTypeTable($id);
	return $rc;
    }
    public function ItOpt() {
      throw new exception('ItOpt() is deprecated; use ItOptRecord().');
    }
    public function ItOptRecord() {
      throw new exception('ItOptRecord() is deprecated; use ItemOptionRecord().');
    }
    protected function ItemOptionRecord() {
	$id = $this->ItemOptionID();
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->ItemOptionTable($id);
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
	if (is_null($this->rcShCost)) {
	    $this->rcShCost = $this->ShipCostTable($this->ShipCostID());
	}
	return $this->rcShCost;
    }

    // -- RECORDS -- //
    /*-----
      ASSUMES:
	  This item is ForSale, so isForSale = true and (qtyForSale>0 || isInPrint) = true
      HISTORY:
	  2011-01-24 Renamed Print_TableRow() -> Render_TableRow; corrected to match header
    *//* 2016-01-23 moved to vcrStockItem
    public function Render_TableRow() {
	$arStat = $this->AvailStatus();
	$strCls = $arStat['cls'];

	$id = $this->KeyValue();
	$sCatNum = $this->Value('CatNum');
	$htDescr = $this->Value('ItOpt_Descr');
	$htStat = $arStat['html'];
	$htPrList = clsMoney::Format_withSymbol($this->Value('PriceList'));
	$htPrSell = clsMoney::Format_withSymbol($this->Value('PriceSell'));

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
    } */
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
    *//* 2016-01-23 moved to vcrStockItem
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
    } */
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
