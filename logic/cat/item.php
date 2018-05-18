<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Items
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-item.php from base.cat.php
    2016-12-03 trait for easier Table access in other classes
*/

//require_once(KFP_LIB_VBZ.'/const/vbz-const-cart.php');

trait vtTableAccess_Item {
    protected function ItemsClass() {
	return 'vctItems';
    }
    protected function ItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ItemsClass(),$id);
    }
}

class vctItems extends vcBasicTable {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'cat_items';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrItem';
    }

    // -- SETUP -- //
    // ++ QUERIES ++ //
    
    protected function ItemInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtItemsInfo');
    }
    
    // -- QUERIES -- //
    // ++ RECORDS ++ //

    //++dependent++//
    
    public function Records_forTitle($idTitle) {
	// "dumped" records are effectively no longer in a Title
	$sqlFilt = "(IFNULL(isDumped,0)=0) AND (ID_Title=$idTitle)";
	$rs = $this->SelectRecords($sqlFilt,'ItOpt_Sort');
	return $rs;
    }
    
    //--dependent--//
    //++search++//
    
    /*----
      ACTION: Finds the Item with the given CatNum, and returns a vcrItem object
      INPUT:
	$sCatNum: CatNum to find (case-insensitive)
	$doAll: if FALSE (default), stops after first record (faster)
    */
    public function Get_byCatNum($sCatNum,$doAll=FALSE) {
	$sqlCatNum = $this->GetConnection()->SanitizeValue(strtoupper($sCatNum));
	$sqlFilt = 'CatNum='.$sqlCatNum;
	if ($doAll) {
	    $sqlOther = NULL;
	} else {
	    $sqlOther = 'LIMIT 1';
	}
	$rcItem = $this->SelectRecords($sqlFilt,NULL,$sqlOther);
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
}
/*::::
 NOTES:
  * "in stock" always refers to stock for sale, not stock which has already been purchased
  * 2009-12-03: The above note does not clarify anything.
  * Four methods were moved here from clsShopCartLine in shop.php: ItemSpecs(), ItemDesc(), ItemDesc_ht(), ItemDesc_wt()
    They are used for displaying a full description of an item, in both shop.php and SpecialVbzAdmin
*/
class vcrItem extends vcBasicRecordset {
    use vtTableAccess_ItemType;
    use vtTableAccess_ItemOption;

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
    public function SetTitleID($id) {
	return $this->SetFieldValue('ID_Title',$id);
    }
    protected function GetTitleID() {
	return $this->GetFieldValue('ID_Title');
    }
    // PUBLIC so Title record can set it when creating new Item record
    public function SupplierID($id=NULL) {
	return $this->Value('ID_Supp',$id);
    }
    /*----
      PUBLIC because table::StatsFor_Title() calls it
    */
    public function ItemTypeID() {
	return $this->GetFieldValue('ID_ItTyp');
    }
    protected function ItemOptionID() {
	return $this->GetFieldValue('ID_ItOpt');
    }
    protected function CatKey() {
	return $this->Value('CatSfx');
    }
    protected function HasCatKey() {
	return $this->HasValue('CatSfx');
    }
    public function CatNum() {
	return $this->GetFieldValue('CatNum');
    }
    public function IsForSale() {
	// 2016-02-10 temporarily re-enabling this so it won't be emitting errors all night
	throw new exception('Shop classes should not be using IsForSale() anymore.');
	//return $this->Value('isForSale');
	return $this->Value('isAvail');
    }
    protected function IsInPrint() {
	return $this->GetFieldValue('isInPrint');
    }
    public function Description() {
	return $this->Value('Descr');
    }
    public function Descr() {	// TODO: deprecate; replace with Description()
	return $this->Value('Descr');
    }
    public function PriceBuy() {
	return $this->GetFieldValue('PriceBuy');
    }
    /*----
      PUBLIC because Order object uses it for Cart->Order conversion
    */
    public function PriceSell() {
	return $this->GetFieldValue('PriceSell');
    }
    protected function SCWhenActive() {
	return $this->GetFieldValue('SC_DateActive');
    }
    protected function SCWhenUpdated() {
    	return $this->GetFieldValue('SC_LastUpdate');
    }
    protected function SCWhenExpires() {
    	return $this->GetFieldValue('SC_DateExpires');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function HasItemType() {
	return !is_null($this->ItemTypeID());
    }
    protected function HasItemOption() {
	return !is_null($this->ItemOptionID());
    }
    protected function HasSCExpiration() {
	return !is_null($this->SCWhenExpires());
    }

    // -- FIELD CALCULATIONS -- //
    // ++ FIELD LOOKUP ++ //

    protected function ItemOptionString_forDetails($sPfx=NULL) {
	if ($this->HasItemOption()) {
	    $rc = $this->ItemOptionRecord();
	    if ($rc->IsNew()) {
		$idItOpt = $this->ItemOptionID();
		$sPopup = "item option ID #$idItOpt not found";
		return "$sPfx<span class=record-not-found title='$sPopup'>?$idItOpt?</span>";
	    } else {
		return $sPfx.$rc->Description_forItem();
	    }
	} else {
	    return NULL;	// option not set, but not required either
	}
    }
    protected function ItemTypeString_forDetails_html() {
	if ($this->HasItemType()) {
	    $out = $this->ItemTypeRecord()->Description_forItem();
	} else {
	    $out = '<span class=value-not-set title="No Item Type set!">?IT</span>';
	}
	return $out;
    }
    protected function ItemTypeString_forDetails_text() {
	if ($this->HasItemType()) {
	    $out = $this->ItemTypeRecord()->NameSingular();
	} else {
	    $out = '?IT';
	}
	return $out;
    }

    // -- FIELD LOOKUP -- //
    // ++ FIELD CALCULATIONS ++ //

    public function ShipCostID() {
	return $this->GetFieldValue('ID_ShipCost');
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
    /*----
      HISTORY:
	2016-03-22 This *was* PUBLIC, but now I'm going to make a new rule: public methods
	  that return descriptions should be named so that you can tell who is supposed
	  to call them. They can be aliases of more generically-named protected functions,
	  and that's fine -- but if we can easily tell who is calling what, then we can know
	  more easily what it's supposed to look like.
	  
	  Also, renamed DescrLong() and DescrLong_ht() to Description_long_generic_*()
	  
      FORMAT: plaintext suitable for email or other contexts where no HTML entity conversion
	is done but quote characters are okay.
    */
    protected function Description_long_generic_plaintext() {
	$sName = $this->TitleRecord()->NameText();
	$sItTyp = $this->ItemTypeString_forDetails_text();
	$sItOpt = $this->ItemOptionString_forDetails(' - ');

	$sDesc = '"'.$sName.'" ('.$sItTyp.$sItOpt.')';
	
	return $sDesc;
    }
    /*----
      FORMAT: Text suitable for use in HTML attributes (e.g. title) -- HTML entities okay,
	but no straight quotes (",').
    */
    protected function Description_long_generic_attribtext() {
	$sName = $this->TitleRecord()->NameText();
	$sItTyp = $this->ItemTypeString_forDetails_text();
	$sItOpt = $this->ItemOptionString_forDetails(' &ndash; ');

	$out = '&ldquo;'.$sName.'&rdquo; ('.$sItTyp.$sItOpt.')';
	
	return $out;
    }
    /*----
      RETURNS: Generic full item description
      FORMAT: full HTML (can include links and popup text)
    */
    public function Description_long_generic_html() {
	$htTitleName = '<i>'.$this->TitleRecord()->LinkName().'</i>';
	$htType = $this->ItemTypeString_forDetails_html();
	$htOpt = $this->ItemOptionString_forDetails(' - ');

	$out = $htTitleName." ($htType$htOpt)";

	return $out;
    }
    public function Description_forCart_html() {
	return $this->Description_long_generic_html();
    }
    public function Description_forCart_text() {
	return $this->Description_long_generic_plaintext();
    }
    public function Description_forOrder() {
	return $this->Description_long_generic_html();
    }
    public function Description_forRestock() {
	return $this->Description_long_generic_html();
    }
    /*----
      RETURNS: The item's per-unit shipping price for the given shipping zone
      TODO: Rename to ShipPerUnit_forZone()
    */
    public function ShipPriceUnit(vcShipCountry $iZone) {
	return $iZone->CalcItemCost($this->ShPerItm());
    }
    /*----
      RETURNS: The item's per-package shipping price for the given shipping zone
      TODO: Rename to ShipPerPackage_forZone()
    */
    public function ShipPricePkg(vcShipCountry $iZone) {
	return $iZone->CalcPackageCost($this->ShPerPkg());
    }
    /*----
      RETURNS: The item's base per-item shipping price (no zone calculations)
      TODO: Rename to ShipPerUnit()
    */
    public function ShPerItm() {
	return $this->ShipCostRecord()->PerUnit();
    }
    /*----
      RETURNS: The item's per-package shipping price, with no zone calculations
      TODO: Rename to ShipPerPackage()
    */
    public function ShPerPkg() {
	return $this->ShipCostRecord()->PerPkg();
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    protected function ShipCostsClass() {
	return 'clsShipCosts';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function TitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TitlesClass(),$id);
    }
    protected function ShipCostTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ShipCostsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      HISTORY:
	2016-03-22 Removed caching. If that is needed, it should probably be done at a deeper level.
    */
    public function TitleRecord() {
	return $this->TitleTable($this->GetTitleID());
    }
    public function SupplierRecord() {
	return $this->SupplierTable($this->SupplierID());
    }
    // PUBLIC so Titles object can use it when searching
    public function ItemTypeRecord() {
	$id = $this->ItemTypeID();
	$rc = $this->ItemTypeTable($id);
	return $rc;
    }
    protected function ItemOptionRecord() {
	$id = $this->ItemOptionID();
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->ItemOptionTable($id);
	}
    }
    public function ShipCostRecord() {
	if (is_null($this->rcShCost)) {
	    $idSh = $this->ShipCostID();
	    $idIt = $this->GetKeyValue();
	    $sCatNum = $this->CatNum();
	    if (is_null($idSh)) {
		$sMsg = "Shipping Cost is not set for Item ID $idIt (Cat #$sCatNum).";
		throw new exception($sMsg);
	    }
	    $this->rcShCost = $this->ShipCostTable($this->ShipCostID());
	    if (!($this->rcShCost instanceof vcBasicRecordset)) {
		$sCls = get_class($this->rcShCost);
		$sMsg = "ShipCostTable($idSh) returned class '$sCls' instead of a recordset for Item ID $idIt.";
		throw new exception($sMsg);
	    }
	}
	return $this->rcShCost;
    }

    // -- RECORDS -- //
}
/*====
  PURPOSE: vctItems with additional catalog information
*/
class vctItems_info_Cat extends vctItems {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  throw new exception('Use ItemInfoTable instead of vctItems_info_Cat.');
	  $this->Name('qryCat_Items');
	  //$this->ClassSng('clsItem_info_Cat');
    }
}
