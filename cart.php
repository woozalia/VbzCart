<?php
/*
  PURPOSE: shopping cart stuff -- base
  HISTORY:
    2012-04-17 extracted from shop.php
    2013-09-13 now using vbz-const-ckout.php (formerly cart-const.php)
*/

require_once('vbz-const-cart.php');
require_once('vbz-const-ckout.php');

// ShopCart
class clsShopCarts extends clsTable {
    const TableName='shop_cart';

    // ++ INITIALIZATION ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsShopCart');
	  $this->ActionKey('cart');
    }

    // -- INITIALIZATION -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function LinesTable() {
	return $this->Engine()->CartLines();
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    // ALIAS for now; TODO: remove
    protected function SessObj() {
	throw new exception('SessObj() is deprecated; call SessionRecord().');
    }
    /*----
      USED BY: self, as part of the cart-finding process. We have to look up
	the cookie-defined session record to see if it already has a cart attached.
	This isn't like the usual table-row lookup-from-an-ID process, so it makes
	sense to keep it in the App object.
    */
    protected function SessionRecord() {
	return $this->Engine()->App()->Session();
    }
    protected function CartExists() {
	return $this->SessionRecord()->HasCart();
    }
    protected function CartObj($iRequire) {
	throw new exception('CartObj() is deprecated; call CartRecord().');
    }
    protected function CartRecord($iRequire) {
	$rcSess = $this->SessionRecord();
	if ($iRequire) {
	    return $rcSess->CartRecord_toUse();
	} else {
	    return $rcSess->CartRecord_Current();
	}
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: creates a new Cart record
      INPUT: $idSess = value for ID_Sess
      RETURNS: ID of new record
      USAGE: called from Session object (cVbzSession)
	because all requests for carts ultimately go through there
      HISTORY:
	2013-11-09 significant redesign of initialization process for carts and sessions
    */
    public function Create($idSess) {
	$arIns = array(
	  'WhenCreated'	=> 'NOW()',
	  'ID_Sess'	=> $idSess
	  );
	$idNew = $this->Insert($arIns);
	if ($idNew === FALSE) {
	    throw new exception('Could not add new cart record in database for Session ID '.$idSess.'.');
	}
	return $idNew;
    }

    // -- ACTIONS -- //
    // ++ CALCULATIONS ++ //


    /*----
      ACTION: Check form input to see if anything needs to be done to the current Cart.
      HISTORY:
	2013-11-09 moved from clsShopCart to clsShopCarts
    */
    public function CheckData() {
	$rcCart = $this->CartRecord(TRUE);	// get the current cart (create if absent)
	if (!is_object($rcCart)) {
	    throw new exception('Could not retrieve or create cart.');
	}
	$rcCart->CheckFormInput();
    }

    // -- CALCULATIONS -- //
    // ++ STORE INTERFACE ++ //

    // TODO: move to a descendant class

    /*----
      HISTORY:
	2013-11-10 Significant change to assumptions. A cart object now only exists
	  to represent a cart record in the database. The cart table object now handles
	  situations where there is no cart record.
    */
    public function RenderCart() {
	if ($this->CartExists()) {
	    $out = $this->CartRecord(FALSE)->Render();
	} else {
	    $out = "<font size=4>You have not put anything in your cart yet.</font>";
	    $this->LogEvent('disp','displaying cart - nothing yet; zone '.$this->ShipZoneObj()->Abbr());
	}
	return $out;
    }

    // -- STORE INTERFACE -- //
}
class clsShopCart extends clsVbzRecs {

    // ++ STATIC ++ //

    static public function FormatMoney($iAmt) {
	$sAmt = FormatMoney($iAmt);	// just formats the numbers
	return '<span class="char-currency">$</span>'.$sAmt;
    }

    // --STATIC--
    // ++DYNAMIC++

    protected $oShipZone;
    protected $rsFields;
    private $arDataItem;
    protected $rcOrder;
    private $oSess;
    private $oLines;

    protected $hasDetails;	// customer details have been loaded?

    // ++ INITIALIZATION ++ //

    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
	$this->oShipZone = NULL;
	$this->rsFields = NULL;
	$this->hasDetails = FALSE;
    }
    /*----
      NOTE: This needs to be better documented. (...as in, "what was I thinking??")
    */
    public function InitNew($iSess) {
	$this->ID = 0;
	$this->WhenCreated = NULL;	// not created until saved
	$this->WhenViewed = NULL;
	$this->WhenUpdated = NULL;
	$this->WhenOrdered = NULL;
	$this->ID_Sess = $iSess;
	$this->ID_Order = NULL;
	$this->ID_Cust = NULL;
	$this->oSess = NULL;
	$this->oLines = NULL;
    }

    // -- INITIALIZATION -- //
    // ++ SPECIALIZED LOGGING ++ //

    public function LogEvent($sCode,$sDescr) {
	$this->CartLog()->Add($this,$sCode,$sDescr);
    }

    // -- SPECIALIZED LOGGING -- //
    // ++ STATUS ACCESS ++ //

    public function HasLines() {
	$oLines = $this->LineRecords();
	if (is_null($oLines)) {
	    return FALSE;
	} else {
	    return $oLines->hasRows();
	}
    }
    public function LineCount() {
	if ($this->HasLines()) {
	    return $this->LineRecords()->RowCount();
	} else {
	    return 0;
	}
    }

    // -- STATUS ACCESS -- //
    // ++ DATA FIELDS ACCESS ++ //

    public function OrderID($id=NULL) {
	if (!is_null($id)) {
	    $this->Value('ID_Order',$id);
	}
	return $this->Value('ID_Order');
    }
    protected function SessionID() {
	return $this->Value('ID_Sess');
    }
    protected function CustomerID() {
	return $this->Value('ID_Cust');
    }
    protected function AddrID() {
	return $this->Value('ID_Addr');
    }
    protected function WhenOrdered() {
	return $this->Value('WhenOrdered');
    }
    protected function WhenVoided() {
	return $this->Value('WhenVoided');
    }

    // -- DATA FIELDS ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function LinesClass() {
	return 'clsShopCartLines';
    }
    protected function FieldsClass() {
	return 'clsCartVars';
    }
    protected function CustomerCardsClass() {
	return 'clsCustCards_dyn';
    }
    protected function SessionsClass() {
	return 'clsUserSessions';
    }
    protected function CartLogClass() {
	return 'clsCartLog';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function CartDataTable() {
	return $this->Engine()->Make('clsCartVars');
    }
    protected function LineTable($id=NULL) {
	return $this->Engine()->Make($this->LinesClass(),$id);
    }
    protected function FieldTable($id=NULL) {
	return $this->Engine()->Make($this->FieldsClass(),$id);
    }
    protected function CustomerCardTable($id=NULL) {
	return $this->Engine()->Make($this->CustomerCardsClass(),$id);
    }
    protected function SessionsTable($id=NULL) {
	return $this->Engine()->Make($this->SessionsClass(),$id);
    }
    protected function CartLog() {
	return $this->Engine()->Make($this->CartLogClass());
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      2014-07-18 changing this from public to protected
    */
    protected function SessionRecord() {
	$idSess = $this->SessionID();
	$rc = $this->SessionsTable($idSess);
	return $rc;
    }
    /*----
      NOTE: Cart ID will be null when we haven't yet been assigned a new cart record.
	This now returns NULL when that happens.
    */
    public function CartData() {
	if (is_null($this->rsFields)) {
	    $idCart = $this->KeyValue();

	    if (empty($idCart)) {
//		throw new exception('Internal error: cart ID not set.');
	    } else {
		$tbl = $this->FieldTable();
		$this->rsFields = $tbl->FieldRows($idCart);
	    }
	}
	//$this->rsFields->CartID($this->KeyValue());	// make sure data object knows the Cart ID
	return $this->rsFields;
    }
    /*----
      RETURNS: Order object
      NOTE: The cache-checking is probably unnecessary.
    */
    public function OrderObj() {
	$doGet = TRUE;
	$idOrder = $this->OrderID();
	if (isset($this->rcOrder)) {
	    if ($this->rcOrder->KeyValue() == $idOrder) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->rcOrder = $this->Engine()->Orders($idOrder);
	}
	return $this->rcOrder;
    }
    /*----
      NOTE: It might be more sensible for this to be a method of
	the Cart Fields class, but as far as I know (2014-02-19) only
	the Cart object uses it -- so I'm leaving it this way for now.
    */
    public function LinesObj() {
	throw new exception('LinesObj() is deprecated; use LineRecords().');
    }
    public function LineRecords() {
	if (is_null($this->oLines)) {
	    $this->oLines = $this->LineTable()->GetData('(ID_Cart='.$this->KeyValue().') AND (Qty>0)');
	}
	return $this->oLines;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ OTHER OBJECTS ACCESS ++ //

    public function ShipZoneObj() {
	if (is_null($this->oShipZone)) {
	    $this->oShipZone = new clsShipZone();
	    $sidZone = $this->CartData()->ShipZone_code();
	    $this->oShipZone->Abbr($sidZone);
	}
	if (is_null($this->oShipZone)) {
	    throw new exception('Internal error: object not created.');
	}
	return $this->oShipZone;
    }

    // -- OTHER OBJECTS ACCESS -- //
    // ++ FIELD CALCULATIONS: status ++ //

    /*----
      HISTORY:
	2010-12-31 Created so placed orders do not get "stuck" in user's browser
	2011-02-07 Doesn't work; same cart still comes up (though at least it generates a new order...
	  but it pulls up all the same contact info)
	2011-03-27 Changed flag from ID_Order to WhenOrdered OR WhenVoided, because we don't want to have to clear
	  ID_Order anymore. Carts should retain their order ID.
    */
    public function IsLocked() {
	return $this->IsOrdered() || $this->IsVoided();
    }
    /*----
      RETURNS: TRUE if the cart has been converted to an order
      USED BY: $this->IsLocked() and (something)->IsUsable()
      HISTORY:
	2011-03-27 written for improved handling of cart status at checkout
    */
    public function IsOrdered() {
	return !(is_null($this->WhenOrdered()));
    }
    /*----
      RETURNS: TRUE if the cart has been discarded (voided)
      USED BY: $this->IsLocked() and (something)->IsUsable()
      HISTORY:
	2011-03-27 written for improved handling of cart status at checkout
    */
    public function IsVoided() {
	return !(is_null($this->WhenVoided()));
    }

    // -- FIELD CALCULATIONS: status -- //
    // ++ ACTIONS ++ //

    protected function LogCartEvent($sCode,$sDescr) {
	$this->CartLog()->Add($this,$sCode,$sDescr);
    }
    /*----
      USAGE: called by clsShopCarts->CheckData() when items are found in _POST input
      HISTORY:
	2013-11-10 Removed call to Make(), since we're now assuming that there is a record if we're here.
    */
    public function AddItem($iCatNum,$iQty) {
	$oLines = $this->Engine()->CartLines();
	$oLines->Add($this->KeyValue(),$iCatNum,$iQty);
	$arEv = array(
	  clsSysEvents::ARG_CODE		=> 'add',
	  clsSysEvents::ARG_DESCR_START	=> 'adding to cart: cat# '.$iCatNum.' qty '.$iQty,
	  clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	  );
	$this->StartEvent($arEv);
    }
    /*----
      ACTION:
	* Checks to make sure the given ID is a line currently in this cart
	* If so, sets the quantity of that line to zero, effectively deleting it.
    */
    public function DelLine($idLine) {
	$oLine = $this->Engine()->CartLines($idLine);
	$idCartMe = $this->KeyValue();
	$idCartDel = $oLine->CartID();
	if ($idCartDel == $idCartMe) {
	    // matches -- okay to delete line
	    $oLine->Qty(0);	// zero the qty
	    $oLine->Save();	// save the change
	} else {
	    // mismatch -- either an internal bug or a hacking attempt
	    throw new exception("Attempted to delete item line ID $idLine, which is in cart ID $idCartDel not in cart ID $idCartMe.");
	}
    }
    /*----
      ACTION: Void this cart.
      USAGE: This only marks the record as void, not the
	object fields, so caller must reload the record if
	anything further is to be done with the object.
    */
    public function DoVoid() {
	$ar = array('WhenVoided'=>'NOW()');
	$this->Update($ar);
    }
    public function IsCreated() {
	throw new exception('DEPRECATED - use "!IsNew()"');
	return ($this->ID > 0);
    }

    // ++ WEB UI ++ //
    public function CheckFormInput() {
// check for buttons
	$doAddItems	= array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
	$doRecalc	= array_key_exists(KSF_CART_BTN_RECALC,$_POST);
	$doShipZone	= array_key_exists(KSF_CART_RECIP_SHIP_ZONE,$_POST);
	$doModify	= array_key_exists(KSF_CART_CHANGE,$_GET);
	$doCheckout	= array_key_exists(KSF_CART_BTN_CKOUT,$_POST);
	$isCart = ($doRecalc || $doCheckout);	// there must already be a cart under these conditions
	$doItems = ($doAddItems || $doRecalc);	// if true, there are items to process
	$isZoneSet = FALSE;	// have we set the zone from stored data?

	// check for specific actions
	if ($doItems) {
	    if ($isCart) {
		// zero out all items, so only items in visible cart will be retained:
		$this->ZeroAll();
	    }
	    // get the list of items posted
	    $arItems = $_POST[KSF_CART_ITEM_ARRAY_NAME];
	    //echo 'ITEMS ADDED:<pre>'.print_r($arItems,TRUE).'</pre>';
	    // add each non-empty item
	    foreach ($arItems as $key => $val) {
		if (!empty($val)) {
		    $nVal = (int)0+$val;
		    $sqlCatNum = $this->Engine()->SafeParam($key);
		    $this->AddItem($sqlCatNum,$nVal);
		}
	    } // END for each item
	// END do add items
	} elseif ($doShipZone) {
	    $custShipZone	= $_POST[KSF_CART_RECIP_SHIP_ZONE];
	    $this->ShipZoneObj()->Abbr($custShipZone);
	    $isZoneSet = TRUE;
	} elseif ($doModify) {
	    $strDo = $_GET[KSF_CART_CHANGE];
	    switch ($strDo) {
	      case KSF_CART_EDIT_DEL_LINE:
		$idLine = $_GET[KSF_CART_EDIT_LINE_ID];
		$this->DelLine($idLine);
		$idCart = $rcCart->KeyValue();
		$this->LogEvent('del',"deleting line ID $idLine");
		break;
	      case KSF_CART_EDIT_DEL_CART;
		$this->LogEvent('clr','voiding cart');
		//$this->ID = -1;
		$this->SessionRecord()->DropCart();
		break;
	    }
	}
	if (!$isZoneSet && $isCart) {
	    // reload the shipping zone if we don't already know it
	    // 2013-11-10 Is this a kluge? Why wouldn't we already know it?
	    $rcCD = $this->CartDataTable()->FieldRows($this->KeyValue());
	    $this->ShipZoneObj()->Abbr($rcCD->ShipZone_code());
	}
	if ($doCheckout) {
	    $this->LogCartEvent('ck1','going to checkout');
	    clsHTTP::Redirect(KWP_CKOUT);
	    $this->LogCartEvent('ck2','sent redirect to checkout');
	}
    }

    /*----
      USED BY: $this->CheckData() when "Recalculate" button is pressed
	This zeroes quantities for all cart lines so that only cart lines
	shown in the HTML form AND with nonzero quantities entered will
	actually have nonzero quantities.
    */
    protected function ZeroAll() {
	$ar = array(
	  'Qty'	=> 0
	  );
	$id = $this->KeyValue();
	$this->Update($ar,"ID_Cart=$id");	// apply to all lines for this cart
    }

    // -- ACTIONS -- //
    // ++ SHOPPING UI ++ //

    // TODO: move to a descendant class

    public function RenderHdr() {
	$urlCart = KWP_CART_REL;	// remove any query data
	$idCart = $this->KeyValue();
	$idSess = $this->Value('ID_Sess');
	$out = <<<__END__
<!-- BEGIN Cart ID=$idCart (Session ID=$idSess) -->
<center>
<form method=post action="$urlCart">
  <table class=border>
    <tr><td>
      <table class=cart>
	<tr><td align=center valign=middle>
	  <table class=cart-data>
__END__;
	return $out;
    }
    public function RenderFtr() {
	return KHT_CART_FTR;
    }
    /*----
      ACTION: Render the receipt in HTML
	Shows the order as generated from *cart* data, not what's in the order record.
	...except for the order number.
      TODO: somehow merge clsShopCart::RenderReceipt() with clsOrder::RenderReceipt()
      HISTORY:
	2011-03-27 adapting this from clsOrder::RenderReceipt()
    */
    public function RenderReceipt() {
	$out = NULL;

	$objCart = $this;
	$objOrd = $this->OrderObj();

	// load contact data
	assert('is_object($objOrd);');
	if (($objOrd->ID == 0) || ($objCart->ID == 0)) {
	    throw new exception('Receipt has missing object: Order ID='.$this->Value('ID_Order').', Cart ID='.$objCart->KeyValue());
	}
	$objCart->GetDetailObjs();
	$objPay = $objCart->PersonCustObj()->Payment();
	$objAddrCard = $objCart->AddrCardObj();
	// the next line is a kluge which only works as long as payment is always ccard
	// it's also not clear why GetDetailObjs() isn't loading it properly
	$objPay->Node('addr', $objAddrCard);

	$arVars = array(
	  'ord.num'	=> $objOrd->Number,
	  'timestamp'	=> date(KF_RCPT_TIMESTAMP),
	  'cart.id'	=> $objCart->ID,
	  'sess.id'	=> $objSess->KeyValue(),
	  'cart.detail'	=> $objCart->RenderConfirm(),
	  'ship.name'	=> $objCart->AddrShipObj()->Name()->Value(),
	  'ship.addr'	=> $objCart->AddrShipObj()->AsText("\n<br>"),
	  'pay.name'	=> $objPay->Addr()->Name()->Value(),
	  'pay.spec'	=> $objPay->SafeDisplay(),
	  'url.shop'	=> KWP_HOME_REL,
	  'email.short'	=> 'orders-'.date('Y').'@vbz.net'
	  );
	$objStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$objStrTplt->MarkedValue(KHT_RCPT_TPLT);
	$out = "\n<!-- ORDER ID: ".$objOrd->ID.' / CART ID from order: '.$objCart->ID." -->\n";
	$out .= $objStrTplt->Replace();
	return $out;
    }
    /*-----
      ACTION: Renders the order contents as plaintext, suitable for emailing
    */
    public function RenderOrder_Text() {
// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;
	$ksfCustCardNum = KSF_CART_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_CART_PAY_CARD_EXP;

	$objData = $this->CartData();
// get non-address field data:
	$strCardNum = $objData->CardNum();
	$strCardExp = $objData->CardExp();
	$strCustShipMsg = $objData->ShipMsg();

	$ftCustShipMsg = wordwrap($strCustShipMsg);

	$out = '';
	$out .= "ITEMS ORDERED:\n";

	$out .= $this->RenderCore_Text();

	$this->doFixedCard = TRUE;
	$this->doFixedSelf = TRUE;
	$this->doFixedName = TRUE;
	$this->htmlBeforeAddress = '';
	$this->htmlBeforeContact = '';

// the exact routes by which some fields are fetched may need tweaking...

	$strShipName = $objData->RecipName();

	$objShip = $objData->ShipObj(FALSE);
	$objCust = $objData->CustObj();

	$strCustName = $objCust->NameVal();

	$out .= "\n\nSHIP TO:\n";
//	$out .= '  '.$this->AddrShipObj()->Name()->Value()."\n";
	$out .= '  '.$strShipName."\n";
//	$out .= '  '.$this->AddrShipObj()->AsText("\n  ");
	$out .= '  '.$objShip->Addr_AsText("\n  ");
	$out .= "\n";
	$out .= "\n  Email: ".$objData->ShipEmail();
	$out .= "\n  Phone: ".$objData->ShipPhone();

	$out .= "\n\n  ";
	if (empty($strCustShipMsg)) {
	    $out .= "(No special instructions)";
	} else {
	    $out .= "Special Instructions:\n$ftCustShipMsg";
	}
	$out .= "\n\nPAYMENT:\n  ".clsCustCards::SafeDescr_Long($strCardNum,$strCardExp)."\n";
	$out .= '  '.$strCustName."\n";
	$out .= '  '.$objCust->Addr_AsText("\n  ");
	$out .= "\n";
	$out .= "\n  Email: ".$objData->CustEmail();
	$out .= "\n  Phone: ".$objData->CustPhone();
	return $out;
    }
    public function RenderCore($iAsForm) {
	$strZone = $this->CartData()->ShipZone_code();
	$dlrShipMinCost = 0;

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
	$rsLine = $this->LineRecords();
	$dlrCostTotalItem = NULL;
	$dlrCostTotalShip = NULL;
	$dlrShipMinCost = NULL;
	while ($rsLine->NextRow()) {
	    if ($iAsForm) {
		$out .= $rsLine->RenderForm($this);
	    } else {
		$out .= $rsLine->RenderHtml($this);
	    }
	    $dlrCostShipPkg = $rsLine->ItemShip_perPkg();
	    if ($dlrShipMinCost < $dlrCostShipPkg) {
		$dlrShipMinCost = $dlrCostShipPkg;
	    }
	    $intQty = $rsLine->Qty;
	    $dlrCostTotalItem += $rsLine->ItemSale_forQty();
	    $dlrCostTotalShip += $rsLine->ItemShip_perUnit_forQty();
	}
// save official totals for order creation:
// TO DO: are CostTotalItem and CostTotalShip referenced anywhere else? Make them local if not.
//	But if they are, then why isn't shipMinCost also a field?
	$this->CartData()->CostTotalSale($dlrCostTotalItem);
	$this->CartData()->CostTotalPerItem($dlrCostTotalShip);
	$this->CartData()->CostMaxPerPkg($dlrShipMinCost);

	$oZone = $this->ShipZoneObj();
	$strTotalMerch = self::FormatMoney($dlrCostTotalItem);
	$strItemsShip = self::FormatMoney($dlrCostTotalShip);
	$strTotalItems = self::FormatMoney($dlrCostTotalItem + $dlrCostTotalShip);
	$strShipZone = $oZone->Text();
	$strShipDesc = $strShipZone.' s/h package cost:';
	$strShipPkg = self::FormatMoney($dlrShipMinCost);
	$strTotalDesc = 'order total if shipping to '.$strShipZone.':';
	$strOrdTotal = self::FormatMoney($dlrCostTotalItem + $dlrCostTotalShip + $dlrShipMinCost);

	if ($iAsForm) {
	    //$htDelAll = '<span class=text-btn>[<a href="?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_CART.'" title="remove all items from cart">remove all</a>]</span>';
	    $htDelAll = '<span class=text-btn><a href="?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_CART.'" title="remove all items from cart">remove all</a></span>';
	    $htFirstTot = "<td align=left>$htDelAll</td><td align=right class=total-desc colspan=4>totals:</td>";
	    $htZoneCombo = 'Shipping destination: '.$oZone->ComboBox();
	} else {
	    $htFirstTot = '<td align=right class=total-desc colspan=5>totals:</td>';
	    $htZoneCombo = 'Shipping costs shown assume shipment to <b>'.$oZone->Text().'</b> address.';
	}
// NOTE: Center-aligning the arrows doesn't work aesthetically because the pkg cost is right-aligned.
	$out .= <<<__END__
<tr>$htFirstTot
<td align=right class=total-amount>$strTotalMerch</td>
<td align=right class=total-amount>$strItemsShip</td>
<td align=right class=total-amount>$strTotalItems</td>
<td align=right>&dArr;</td>
</tr>
<tr>
<td align=right  class=total-desc colspan=7>$strShipDesc</td>
<td align=right  class=total-amount>$strShipPkg</td>
<td align=right>&crarr;</td>
</tr>
<tr>
<td align=right  class=total-desc colspan=7>$strTotalDesc</td>
<td align=right  class=total-final>$strOrdTotal</td>
</tr>
<tr><td colspan=6>
$htZoneCombo
</td></tr>
__END__;
	$out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	// LogCartEvent($iCode,$iDescr)
	$sDescr = 'displaying cart, zone '.$oZone->Abbr().' total $'.$strOrdTotal;
	$this->LogCartEvent('disp',$sDescr);
	return $out;
    }
    /*-----
      RETURNS: The contents of the cart as text. Includes column headers and totals.
      USED BY: does anything actually use this, or was it intended for the email confirmation?
    */
    public function RenderCore_Text() {
	$objData = $this->CartData();

	$abbrShipZone = $objData->ShipZone();
	$this->ShipZoneObj()->Abbr($abbrShipZone);
	$oZone = $this->ShipZoneObj();

	$shipMinCost = 0;

	$strLineFmt = '%-16s |%6.2f |%6.2f |%4d |%7.2f |%10.2f |%13.2f';

	if ($this->HasRows()) {
	    $hdr = "\n".sprintf('%-17s|%6s|%5s|%5s|%7s|%10s|%13s'
	      ,'cat #'
	      ,' $ ea. '
	      ,' $ s/h '
	      ,' qty '
	      ,' $ sale '
	      ,' $ s/h tot '
	      ,' $ LINE TOTAL');
	    $out = $hdr;
	    $out .= "\n".str_repeat('-',strlen($hdr));
	    $objLines = $this->GetLines();
	    $dlrSaleTot = 0;	// total sale before shipping
	    $dlrPItmTot = 0;	// per-item shipping total
	    $dlrPPkgMax = 0;	// per-pkg shipping total
	    while ($objLines->NextRow()) {
		$objItem = $objLines->Item();
		$dlrShipItm = $objItem->ShipPriceItem($objZone);
		$dlrShipPkg = $objItem->ShipPricePkg($objZone);

		$out .= $objLines->RenderText($this,$strLineFmt);
		if ($dlrPPkgMax < $dlrShipPkg) {
		    $dlrPPkgMax = $dlrShipPkg;
		}
		$intQty = $objLines->Qty;
		$dlrSaleTot += $objLines->PriceItem;
		$dlrPItmTot += $dlrShipItm*$intQty;
	    }
	    $out .= "\n".str_repeat('=',strlen($hdr));

	    $ftTotalMerch = sprintf('%6.2f',FormatMoney($dlrSaleTot));
	    $ftItemsShip = sprintf('%6.2f',FormatMoney($dlrPItmTot));
	    $ftTotalItems = sprintf('%6.2f',FormatMoney($dlrSaleTot + $dlrPItmTot));
	    $ftShipPkg = sprintf('%6.2f',FormatMoney($dlrPPkgMax));
	    //$ftTotalDesc = 'order total for shipping to '.$ftShipZone.':';
	    $ftOrdTotal = sprintf('%6.2f',FormatMoney($dlrSaleTot + $dlrPItmTot + $dlrPPkgMax));

// these items don't depend on cart contents, but there's no point in calculating them if the cart is empty:
	$ftShipZone = $oZone->Text();
	$ftShipDesc = $ftShipZone.' s/h package cost:';

	//$objSess = $this->Session();

	$ftZone = $oZone->Text();

	$out .= "\n"
	  ."\n *          Sale: $ftTotalMerch"
	  ."\n * S/H -"
	  ."\n  * per item sum: $ftItemsShip"
	  ."\n  * per  package: $ftShipPkg"
	  ."\n========================"
	  ."\n==== FINAL TOTAL: $ftOrdTotal"
	  ."\n\nShipping Zone: $ftZone";
	} else {
	    $out = "\nSorry, we seem to have goofed: this cart appears to have no items in it.";
	    $out .= "\nThe webmaster is being alerted to the problem.";
	}
	return $out;
    }
    /*----
      RETURNS: HTML rendering of cart, including current contents and form controls
      HISTORY:
	2013-11-10 Significant change to assumptions. A cart object now only exists
	  to represent a cart record in the database. Any functions that need to work
	  when there is no record are now handled by the cart table object.
    */
    public function Render() {
	if ($this->HasLines()) {
# get information for that destination type:
	    $out = $this->RenderHdr();
	    $out .= $this->RenderCore(TRUE);
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	    $out .= $this->RenderFtr();
	} else {
	    $id = $this->KeyValue();
	    $out = "<font size=4>Your cart is empty.</font> (cart ID=$id)";
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> 'disp',
	      clsSysEvents::ARG_DESCR_START	=> 'displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr(),
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      );
	    //$this->LogEvent('disp','displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr());
	    $this->StartEvent($arEv);
	}
	return $out;
    }
    /*----
    PURPOSE: Render cart for order confirmation page (read-only, no form controls)
    */
    public function RenderConfirm() {
	if ($this->HasLines()) {
	    $out = $this->RenderCore(FALSE);
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	} else {
	    // log error - you shouldn't be able to get to this point with an empty cart
	    $txtParams = 'Cart ID='.$this->KeyValue().' Order ID='.$this->OrderID();
	    $arEv = array(
	      clsSysEvents::ARG_DESCR_START	=> 'cart empty at confirmation',
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      clsSysEvents::ARG_CODE		=> 'cec',
	      clsSysEvents::ARG_PARAMS		=> $txtParams,
	      clsSysEvents::ARG_IS_ERROR	=> TRUE,
	      clsSysEvents::ARG_IS_SEVERE	=> TRUE,
	      );
	    //$this->LogEvent('cart.renderconf',$txtParams,'cart empty at confirmation','cec',TRUE,TRUE);	// also sends email alert
	    $this->StartEvent($arEv);
	    $out = $this->Engine()->Page()->Skin()->ErrorMessage('<font color=red>INTERNAL ERROR</font>: cart data has become separated from browser. The webmaster has been notified.');
	}
	return $out;
    }

    // -- SHOPPING UI -- //
    // ++ CONVERSION TO ORDER ++ //

    public function ToOrder(clsOrder $oOrd) {
	$this->Engine()->TransactionOpen();
	$this->AdminEcho('Converting cart to order...<br>');
	$ok =
	  $this->ToOrder_Data($oOrd) &&
	  $this->ToOrder_Lines($oOrd);

	if ($ok) {
	    $this->AdminEcho('Conversion complete.<br>');
	    $this->Engine()->TransactionSave();
	} else {
	    $this->AdminEcho('Conversion failed; reverting.<br>');
	    $this->Engine()->TransactionKill();
	}
    }
    /*----
      ACTION: Copy over basic cart information (totals, etc.)
      HISTORY:
	2010-10-06 added cart ID to update -- otherwise final order confirmation page can't find cart data
	2012-05-25 major revision to cart data access -- now using $iCartObj->CartData()
	2013-11-06 this will now import the full order data as well, creating or updating customer records
	  as needed
	2014-01-29 major changes:
	  * adapting to operate from cart object instead of order object
	  * incorporating full cart "import" process
	  * eliminating any attempt to find matching customer profile unless specifically chosen by user
      TODO:
	card data in cart must be *encrypted* after copying
    */
    private function ToOrder_Data(clsOrder $oOrd) {
	$tFields = $this->FieldTable();
	$idOrd = $oOrd->KeyValue();
	$idCart = $this->KeyValue();

	$rsFields = $tFields->FieldRows($idCart);

	/*----
	  Event parameters:
	    'descr'	=> 'Descr',
	    'descrfin'	=> 'DescrFin',
	    'notes'	=> 'Notes',
	    'type'	=> 'ModType',
	    'id'	=> 'ModIndex',
	    'where'	=> 'EvWhere',
	    'code'	=> 'Code',
	    'params'	=> 'Params',
	    'error'	=> 'isError',
	    'severe'	=> 'isSevere',
	*/
	$sDescr = 'Copying fields to order ID '.$idOrd;
	$arEv = array(
	  'code'	=> 'CFO',
	  'descr'	=> $sDescr,
	  'where'	=> __METHOD__,
	  );
	$idEv = $this->StartEvent($arEv);
	$this->AdminEcho($sDescr.'<br>');

	// update the cart record
	$arUpd = array(
	  'ID_Order'	=> $idOrd,
	  'WhenOrdered'	=> 'NOW()',
	  );
	$this->Update($arUpd);

	// get overall order fields
	$curItemTotal = $rsFields->CostTotalSale();
	$curShipItem = $rsFields->CostTotalPerItem();
	$curShipPkg = $rsFields->CostTotalPerPkg();

	// if not using existing customer records, create them
	$idBuyer	= $rsFields->FieldValue_forIndex(KI_CART_BUYER_ID);
	$idRecip	= $rsFields->FieldValue_forIndex(KI_CART_RECIP_ID);
	$sBuyerName	= $rsFields->BuyerName();
	$sRecipName	= $rsFields->RecipName();
	$sRecipAddr	= $rsFields->RecipAddr_text();
	$oAddrBuyer	= $rsFields->BuyerFields();
	$oAddrRecip	= $rsFields->RecipFields();
	$oCardInfo	= $rsFields->PayFields();

	$rcSess = $this->SessionRecord();
	$idUser = $rcSess->UserID();

	$tCust = $this->CustomerTable();
	$tCard = $this->CustomerCardTable();
	if (is_null($idBuyer)) {
	    $tCust->CreateCustomer($idUser,$sBuyerName,$oAddrBuyer);
	    $tCard->CreateRecord($oCardInfo);
	}
	if (is_null($idRecip)) {
	    $tCust->CreateCustomer($idUser,$sRecipName,$oAddrRecip);
	}

	// update the order record

	$arUpd = array(
	  'ID_Cart'		=> $idCart,
	  'WebTotal_Merch'	=> SQLValue($curItemTotal),
	  'WebTotal_Ship' 	=> SQLValue($curShipItem+$curShipPkg),
	  'WebTotal_Final'	=> SQLValue($curItemTotal+$curShipItem+$curShipPkg),
	  'ID_Buyer'		=> SQLValue($idBuyer),	// can be NULL
	  'ID_Recip'		=> SQLValue($idRecip),	// can be NULL
	  'BuyerName'		=> SQLValue($sBuyerName),
	  'RecipName'		=> SQLValue($sRecipName),
	  'RecipAddr'		=> SQLValue($sRecipAddr)
	  );
	$oOrd->Update($arUpd);	// we're assuming the order record exists at this point
	$this->AdminEcho('Order Update SQL: '.$oOrd->sqlExec.'<br>');
	$this->FinishEvent();
	return TRUE;
    }
    /*-----
     ACTION: Create order lines from cart lines
    */
    private function ToOrder_Lines(clsOrder $oOrd) {
	$tCL = $this->LineTable();
	$rsCL = $this->LineRecords();	// shopping cart lines to convert
	$idOrder = $oOrd->KeyValue();	// Order ID
	$oOrd->ZeroLines();		// zero out any existing order lines in case customer edits cart
	//$tOrd = $oOrd->Table();
	$tOL = $oOrd->LineTable();	// Order Lines table
	$out = NULL;

	if ($rsCL->HasRows()) {
/*
define('KS_EVENT_ARG_DESCR_START'	,'descr');
define('KS_EVENT_ARG_DESCR_FINISH'	,'descrfin');
define('KS_EVENT_ARG_NOTES'		,'notes');
define('KS_EVENT_ARG_MOD_TYPE'		,'type');
define('KS_EVENT_ARG_MOD_INDEX'	,'id');
define('KS_EVENT_ARG_WHERE'		,'where');
define('KS_EVENT_ARG_CODE'		,'code');
define('KS_EVENT_ARG_PARAMS'		,'params');
define('KS_EVENT_ARG_IS_ERROR'		,'error');
define('KS_EVENT_ARG_IS_SEVERE'	,'severe');
*/
	      $nLines = $rsCL->RowCount();
	      $sDescr = 'copying '.$nLines.' '.'line'.Pluralize($nLines).' to order ID '.$idOrder;
	      $arEv = array(
		KS_EVENT_ARG_CODE		=> 'CLO',
		KS_EVENT_ARG_DESCR_START	=> $sDescr,
		KS_EVENT_ARG_WHERE	=> __METHOD__,
	      );
	    $this->StartEvent($arEv);
	    $intNew = 0;
	    $intUpd = 0;
	    while ($rsCL->NextRow()) {
		$intSeq = $rsCL->Seq();
		$idItem = $rsCL->ItemID();
		$intQty = $rsCL->Qty();
		$dtWhenAdded = $rsCL->Value('WhenAdded');
		$dtWhenEdited = $rsCL->Value('WhenEdited');

		$rsCL->RenderCalc($this->ShipZoneObj());

		// update array for each ORDER LINE
		$arUpd = array(
		  'CatNum'	=> SQLValue($rsCL->CatNum),
		  'Descr'	=> SQLValue($rsCL->ItemRecord()->DescLong()),
		  'QtyOrd'	=> $intQty,
		  'Price'	=> SQLValue($rsCL->PriceItem),
		  'ShipPkg'	=> SQLValue($rsCL->ShipPkgDest),
		  'ShipItm'	=> SQLValue($rsCL->ShipItmDest),
		  'isShipEst'	=> 'FALSE'
		  );

		// has this item already been transcribed?
		$rcOL = $tOL->Find_byOrder_andItem($idOrder,$idItem);
//		$sqlFilt = '(ID_Order='.$this->KeyValue().') AND (ID_Item='.$idItem.')';
//		$objOrdItems = $tCL->GetData($sqlFilt);
		if ($rcOL->RowCount() > 0)  {
		    // already transcribed -- update existing record
		    $rcOL->Update($arUpd);
		    $sql = $rcOL->sqlExec;
		    $this->AdminEcho("Update SQL: $sql<br>");
		    $intUpd++;
		} else {
		    // not already transcribed -- insert new record
		    $arIns = $arUpd + array(
		      'ID_Order' 	=> $idOrder,
		      'Seq'		=> $intSeq,
		      'ID_Item'		=> $idItem
		      );
		    $tOL->Insert($arIns);
		    $sql = $tOL->sqlExec;
		    $this->AdminEcho("Insert SQL: $sql<br>");
		    $intNew++;
		}
	    }
	    $strLines = $intNew.' order line'.Pluralize($intNew).' created';
	    if ($intUpd > 0) {
		$strLines .= ' and '.$intUpd.' order line'.Pluralize($intUpd).' updated';
	    }
	    $strLines .= ' from cart lines';
	    $this->AdminEcho($strLines.'<br>');
	    $this->FinishEvent(
	      array(
		KS_EVENT_ARG_DESCR_FINISH	=> $strLines
		)
	      );
	} else {
	    $arEv = array(
	      KS_EVENT_ARG_CODE		=> 'CLX',
	      KS_EVENT_ARG_DESCR_START	=> 'No cart lines found at order creation time',
	      KS_EVENT_ARG_WHERE	=> __METHOD__,
	      KS_EVENT_ARG_IS_ERROR	=> TRUE,
	      KS_EVENT_ARG_IS_SEVERE	=> TRUE,
	      );
	    $this->StartEvent($arEv);
	    $out = '<b>There has been an error</b>: your cart contents seem to be missing.';
	    // TODO: make sure this sends an alert email
	}
	return TRUE;
    }

    // -- CONVERSION TO ORDER -- //
    // ++ ADMIN STUB ++ //

    protected function AdminEcho($sText) {
	// do nothing; descendants may choose to display this
    }

    // -- ADMIN STUB -- //
}
