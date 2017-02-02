<?php
/*
  HISTORY:
    2012-04-17 extracting from shop.php
*/

require_once(KFP_LIB_VBZ.'/const/vbz-const-cart.php');

class vctShopCartLines extends vcShopTable {
    use ftLoggableTable;
    use vtFrameworkAccess;

    // ++ CEMENTING ++ //
    
    public function GetActionKey() {
	return 'c.line';
    }
    
    // ++ OVERRIDES ++ //
    
    protected function TableName() {
	return 'shop_cart_line';
    }
    protected function SingularName() {
	return 'vcrShopCartLine';
    }

    // -- OVERRIDES -- //
    // ++ CLASS NAMES ++ //
    
    protected function ItemsClass() {
	return 'clsItems';
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    protected function ItemTable() {
	return $this->GetConnection()->MakeTableWrapper($this->ItemsClass());
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    // 2014-02-28 is this actually needed?
    /* 2016-10-31 Nothing uses this.
    public function Find_byCart_andItem($idCart,$idItem) {
 	$sqlFilt = "(ID_Cart=$idCart) AND (ID_Item=$idItem)";
	$rc = $this->GetData($sqlFilt);
	$rc->NextRow();		// load the row
	return $rc;
    }*/

    // -- RECORDS -- //
    // ++ ACTION ++ //

    public function Add($idCart, $sCatNum, $nQty) {
	throw new exception('2016-10-31 Add() is deprecated; call MakeLine().');
    }
    public function MakeLine($idCart, $sCatNum, $nQty) {
	$arEv = array(
	  fcrEvent::KF_CODE		=> 'add',
	  fcrEvent::KF_DESCR_START	=> 'adding to cart: cat# '.$sCatNum.' qty '.$nQty,
	  fcrEvent::KF_WHERE		=> __FILE__.' line '.__LINE__,
	  );
	$rcEv = $this->CreateEvent($arEv);

	$tItems = $this->ItemTable();
	$rcItem = $tItems->Get_byCatNum($sCatNum);
	if (is_null($rcItem)) {
	    // log failure
	    $arEv = array(
	      fcrEvent::KF_DESCR_FINISH	=> 'Could not retrieve item record for cat# "'.$sCatNum.'".',
	      fcrEvent::KF_IS_ERROR	=> TRUE,
	      fcrEvent::KF_IS_SEVERE	=> TRUE,	// for now; might downgrade later
	      );
	    $rcEv->Finish($arEv);
	    echo '<b>Failed SQL</b>: '.$tItems->sql.'<br>';
	    throw new exception('ERROR: Could not find item for catalog #'.$sCatNum.'.');
	} else {
	    $sqlCart = $this->GetConnection()->Sanitize_andQuote($idCart);
	    $idItem = $rcItem->GetKeyValue();
	    $sqlWhere = "(ID_Cart=$sqlCart) AND (ID_Item=$idItem)";
	    $rcLine = $this->SelectRecords($sqlWhere);

	    if ($rcLine->hasRows()) {
		$rcLine->NextRow();	// load the only data row
		$sAction = 'found';
	    } else {
 		$rcLine->SetCartID($idCart);
		$rcLine->SetItemID($rcItem->GetKeyValue());
 		$sAction = 'created';
	    }
	    $rcLine->SetQtyOrd($nQty);

	    $rcLine->Save();
	    
	    // log success
	    $arEv = array(
	      fcrEvent::KF_DESCR_FINISH	=> $sAction.' line ID '.$rcLine->GetKeyValue()
	      );
	    $rcEv->Finish($arEv);
	    
	    return $rcLine;
	}
    }

    // -- ACTION -- //
}
// PURPOSE: so vcrShopCartLine can override trait methods
class vcrShopCartLine_base extends vcShopRecordset {
    use ftSaveableRecord;
}
class vcrShopCartLine extends vcrShopCartLine_base {

    private $rcItem;
    private $rcShCost;
    private $arItSp;	// cache for ItemSpecs()

    // ++ INITIALIZATION ++ //

/*    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
    } */
    protected function InitVars() {
	parent::InitVars();
	$this->rcItem = NULL;
	$this->rcShCost = NULL;
    }

    // -- INITIALIZATION -- //
    // ++ CLASS NAMES ++ //

    protected function CartsClass() {
	return 'vctCarts_ShopUI';
    }
    protected function ItemsClass() {
	return 'clsItems';
    }
    protected function OrderLinesClass() {
	return 'vctOrderLines';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    protected function CartTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CartsClass(),$id);
    }
    protected function ItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ItemsClass(),$id);
    }
    protected function OrderLineTable($id=NULL) {
	return $this->Engine()->Make($this->OrderLinesClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ DATA RECORDS ++ //

    public function CartRecord() {
	return $this->CartTable($this->GetCartID());
    }
    /*----
      PUBLIC so order line can look up item values in order to initialize itself
    */
    public function ItemRecord() {
	$doLoad = FALSE;
	if (is_null($this->rcItem)) {
	    $doLoad = TRUE;
	} elseif ($this->rcItem->GetKeyValue() != $this->GetItemID()) {
	    $doLoad = TRUE;
	}
	if ($doLoad) {
	    $this->rcItem = $this->ItemTable($this->GetItemID());
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
    // ++ FIELD VALUES ++ //

    public function SetCartID($id) {
	$this->SetFieldValue('ID_Cart',$id);
    }
    public function GetCartID() {
	return $this->GetFieldValue('ID_Cart');
    }
    public function SetItemID($id) {
	$this->SetFieldValue('ID_Item',$id);
    }
    public function GetItemID() {
	return $this->GetFieldValue('ID_Item');
    }
    protected function SetSequence($nVal) {
	return $this->SetFieldValue('Seq',$nVal);
    }
    public function GetSequence() {
	return $this->GetFieldValue('Seq');
    }
    /*----
      HISTORY:
	2013-11-10 Changed so it doesn't write to the db, but just sets a flag.
	2015-09-02 For coding clarity, renamed Qty() to QtyOrd() even though there are
	  no other quantity fields in this table. (*Package* Lines have multiple quantity fields.
	  Order Lines and Cart Lines do not.)
      TODO: Preventing injection attacks should happen where the input is pulled
	from $_REQUEST, not here (just get it as "0+<var>") -- make sure it does happen there.
    */
    public function Qty($nQty=NULL) {
	throw new exception('Qty() is deprecated; for code clarity, function is now called QtyOrd().');
    }
    public function SetQtyOrd($nQty) {
	$this->SetFieldValue('Qty',$nQty);
    }
    public function GetQtyOrd() {
	return $this->GetFieldValue('Qty');
    }
    // PUBLIC so Cart object can access it when converting Cart Lines to Order Lines
    public function GetWhenAdded() {
	return $this->GetFieldValue('WhenAdded');
    }
    // PUBLIC so Cart object can access it when converting Cart Lines to Order Lines
    public function GetWhenEdited() {
	return $this->GetFieldValue('WhenEdited');
    }

    // -- FIELD VALUES -- //
    // ++ FOREIGN FIELDS ++ //
      //++item specs++//

    // NOTE: Don't refer to "price" without being clear whether it's in/wholesale or out/retail.
    
    // 2015-10-18 is this used?
    protected function ItemPriceIn() {
	throw new exception('2016-11-01 This does not appear to be in use.');
	return $this->ItemRecord()->PriceBuy();
    }
    protected function ItemPriceOut() {
	return $this->ItemRecord()->PriceSell();
    }
    /*----
      RETURNS: item's per-unit shipping cost
	NOT adjusted for destination ("base")
    */
    protected function ItemShip_perUnit_base() {
	return $this->ShipCostRecord()->PerUnit();
    }
    // RETURNS: item's per-unit s/h cost, adjusted for zone
    protected function ItemShip_perUnit_forZone() {
	$oZone = $this->CartRecord()->ShipZoneObject();
	$nMult = $oZone->PerItemFactor();
	return $this->ItemShip_perUnit_base() * $nMult;
    }
    /*----
      RETURNS: item's per-package minimum shipping cost
	NOT adjusted for destination ("base")
      WAS: PUBLIC so Cart object can access it
	2016-03-08 Marking as "protected" until I can determine why
	  this needs to be public but perUnit does not.
    */
    protected function ItemShip_perPackage_base() {
	return $this->ShipCostRecord()->PerPkg();
    }
    // RETURNS: item's per-unit s/h cost, adjusted for zone
    protected function ItemShip_perPackage_forZone() {
	$oZone = $this->CartRecord()->ShipZoneObject();
	$nMult = $oZone->PerPackageFactor();
	return $this->ItemShip_perPackage_base() * $nMult;
    }
    /*----
      PUBLIC so Cart object can access it for order conversion
    */
    public function CatNum() {
	return $this->ItemRecord()->CatNum();
    }
    protected function DescHtml() {
	return $this->ItemRecord()->Description_forCart();
    }
    /*----
      PUBLIC because the Order object uses it during cart->order conversion
    */
    public function DescText() {
	return $this->ItemRecord()->Description_forCart_text();
    }

    // -- FOREIGN FIELDS -- //
    // ++ FIELD CALCULATIONS ++ //

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
      TODO: is this adjusted for destination?
    *//* 2016-03-08 Does not seem to be used.
    public function ItemShip_perUnit_forQty() {
	throw new exception('Does the caller expect this to be adjusted for the shipping destination?');
	return $this->ItemShip_perUnit() * $this->Qty();
    }//*/
    protected function InsertArray($arOut=NULL) {
	$arOut = parent::InsertArray($arOut);
	$arOut['WhenAdded'] = 'NOW()';
	if ($this->IsNew()) {
	    $arOut['Seq'] = $this->CartRecord()->LineCount()+1;
	}
	return $arOut;
    }
    protected function UpdateArray($arOut=NULL) {
	$arOut = parent::UpdateArray($arOut);
	$arOut['WhenEdited'] = 'NOW()';
	return $arOut;
    }
    private $prcShip_Unit;
    public function ShipCost_Unit_forDest(vcShipCountry $iZone) {
	if (empty($this->prcShip_Unit)) {
	    $rcItem = $this->ItemRecord();
	    $this->prcShip_Unit = $rcItem->ShipPriceUnit($iZone);
	}
	return $this->prcShip_Unit;
    }
    // PUBLIC so Cart object can access it when migrating data to Order record
    private $prcShip_Pkg;
    public function ShipCost_Pkg_forDest(vcShipCountry $iZone) {
	if (empty($this->prcShip_Pkg)) {
	    $rcItem = $this->ItemRecord();
	    $this->prcShip_Pkg = $rcItem->ShipPricePkg($iZone);
	}
	return $this->prcShip_Pkg;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      USAGE: called from vcrShopCart::AddItem() when an item needs to be saved to the cart
      HISTORY:
	2013-11-10 written to replace Build()
    */ /* 2016-11-01 moved to InsertArray()
    public function Save() {
	if ($this->IsNew()) {
	    $this->Seq($this->CartRecord()->LineCount()+1);
	}
	return parent::Save();
    }*/
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
	2015-09-02
	  * Decided not to cache ItemPrice (not sure why that was needed anyway)
	  * Saving (caching?) of Item details, and cost-calculations, no longer needed either.
	2016-06-12 This was expecting an old-style Shipping Zone object -- but not using it.
	  Removed that requirement for now, but it may need to be re-implemented if foreign shipping
	  does not display properly after order conversion.
    */
    public function RenderCalc() {
	$rcItem = $this->ItemRecord();
	$txtItemDesc = $rcItem->Description_forCart_text();
	$htmItemDesc = $rcItem->Description_forCart_html();

	$this->SetFieldValue('CatNum',$rcItem->CatNum());
    }

    public function ItemDescLong_text() {
	return $this->ItemRecord()->Description_forCart();
    }
    /*----
      PUBLIC because Cart object calls it to display a static cart at checkout
      RETURNS: populated rendering object for the current line
    */
    public function GetRenderObject_static() {
	$oLine = new vcCartLine_static(
	  $this->CatNum(),
	  $this->DescText(),
	  $this->GetQtyOrd(),
	  $this->ItemPriceOut(),
	  $this->ItemShip_perUnit_forZone(),
	  $this->ItemShip_perPackage_forZone()
	  );
	return $oLine;
    }
    /*----
      PUBLIC because Cart object calls it to display an editable cart
      TODO: There's got to be a cleaner way of adding just one more argument...
    */
    public function GetRenderObject_editable() {
	$oLine = new vcCartLine_form(
	  $this->GetKeyValue(),
	  $this->CatNum(),
	  $this->DescText(),
	  $this->GetQtyOrd(),
	  $this->ItemPriceOut(),
	  $this->ItemShip_perUnit_forZone(),
	  $this->ItemShip_perPackage_forZone()
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
    public function RenderStatic() {
	return $this->GetRenderObject_static()->Render();
    }
    /*----
      ACTION: Render the current cart line as part of an interactive HTML form
    */
    public function RenderForm(vcrShopCart $iCart) {
// calculate display fields:
	if ($this->Qty) {
	    $oLine = $this->GetRenderObject_editable();
	    return $oLine->Render();
	}
    }

    // -- STORE UI -- //
}
