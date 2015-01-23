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
    /*----
      RETURNS: TRUE iff cart has been linked to the active Session record
    */
    protected function CartIsRegistered() {
	return $this->SessionRecord()->HasCart();
    }
    /*----
      RETURNS: Record for either the current cart or, if no cart has
	been assigned to the session, a new one. Will not drop
	an existing cart even if it is invalid in some way.
    */
    protected function CartRecord_do_not_drop() {
	$rcSess = $this->SessionRecord();
	$rcCart = $rcSess->CartRecord_Current();
	if (is_null($rcCart)) {
	    $rcCart = $rcSess->CartRecord_required();
	}
	return $rcCart;
    }
    /*----
      RETURNS: Record for the current cart (whether valid or not),
	or NULL if there is no cart assigned to the session.
    */
    protected function CartRecord_current() {
	$rcSess = $this->SessionRecord();
	return $rcSess->CartRecord_Current();
    }
    /*----
      RETURNS: Record for the current cart, if valid, or a new one if not.
      HISTORY:
	2014-10-09 Written, but not actually sure if it's needed.
    */ /*
    protected function CartRecord_valid_orNew() {
	$rcSess = $this->SessionRecord();
	$rcCart = $rcSess->CartRecord_required();
	return $rcCart;
    } */
    protected function CartRecord($iRequire) {
	throw new exception('CartRecord() is deprecated; call one of the variants.');

	$rcSess = $this->SessionRecord();
	if ($iRequire) {
	    return $rcSess->CartRecord_required();
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
	$rcCart = $this->CartRecord_do_not_drop();	// get the current cart (create if absent)
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
    public function RenderCart($bEditable) {
	if ($this->CartIsRegistered()) {
	    $rcCart = $this->CartRecord_current();
	    $out = $rcCart->Render($bEditable);
	} else {
	throw new exception('Is this being called when items are first added?');
	    $out = "<font size=4>You have not put anything in your cart yet.</font>";
	    $sDesc = 'displaying cart - nothing yet; zone '.$this->ShipZoneObj()->Abbr();
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> '0cart',
	      clsSysEvents::ARG_DESCR_START	=> $sDesc,
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      );
	    $this->StartEvent($arEv);
	}
	return $out;
    }

    // -- STORE INTERFACE -- //
}
class clsShopCart extends clsVbzRecs {

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
    // ++ FIELD CALCULATIONS / STATUS ++ //

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
    public function IsCreated() {
	throw new exception('DEPRECATED - use "!IsNew()"');
	return ($this->ID > 0);
    }

    // -- FIELD CALCULATIONS / STATUS -- //
    // ++ CLASS NAMES ++ //

    protected function LinesClass() {
	return 'clsShopCartLines';
    }
    protected function FieldsClass() {
	return 'clsCartVars';
    }
    protected function OrdersClass() {
	return 'clsOrders';
    }
    protected function CustomersClass() {
	return 'clsCusts';
    }
    protected function CustomerCardsClass() {
	return 'clsCustCards_dyn';
    }
    protected function CustomerAddressesClass() {
	return 'clsCustAddrs';
    }
    protected function SessionsClass() {
	return 'cVbzSessions';
    }
    protected function CartLogClass() {
	return 'clsCartLog';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function LineTable($id=NULL) {
	return $this->Engine()->Make($this->LinesClass(),$id);
    }
    protected function FieldTable($id=NULL) {
	return $this->Engine()->Make($this->FieldsClass(),$id);
    }
    protected function OrderTable($id=NULL) {
    	return $this->Engine()->Make($this->OrdersClass(),$id);
    }
    protected function CustomerTable($id=NULL) {
    	return $this->Engine()->Make($this->CustomersClass(),$id);
    }
    protected function CustomerCardTable($id=NULL) {
	return $this->Engine()->Make($this->CustomerCardsClass(),$id);
    }
    protected function CustomerAddressTable($id=NULL) {
	return $this->Engine()->Make($this->CustomerAddressesClass(),$id);
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
      2014-09-27 changing it back to public because checkout page needs to be able to update Session record
	when an order is created (MakeOrder()).
    */
    public function SessionRecord() {
	$idSess = $this->SessionID();
	$rc = $this->SessionsTable($idSess);
	return $rc;
    }
    /*----
      NOTE: Cart ID will be null when we haven't yet been assigned a new cart record.
	This now returns NULL when that happens.
    */
    public function CartData() {
	throw new exception('CartData() is deprecated; call FieldRecords().');
    }
    public function FieldRecords() {
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
    protected function HasOrder() {
	return !is_null($this->OrderID());
    }
    /*----
      RETURNS: Order object
      NOTE: The cache-checking is probably unnecessary.
    */
    public function OrderObj() {
	throw new exception('OrderObj() is deprecated; call OrderRecord().');
    }
    /*----
      TODO: Run tests to see if caching is necessary.
	Maybe log accesses temporarily to see if cache is being used or not.
    */
    public function OrderRecord() {
	$doGet = TRUE;
	$idOrder = $this->OrderID();
	if (isset($this->rcOrder)) {
	    if ($this->rcOrder->KeyValue() == $idOrder) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->rcOrder = $this->OrderTable($idOrder);
	}
	return $this->rcOrder;
    }
    public function OrderRecord_orDie() {
	if ($this->HasOrder()) {
	    $rcOrd = $this->OrderRecord();
	    if (is_object($rcOrd)) {
		return $rcOrd;
	    } else {
		throw new exception('Internal Error: OrderRecord() returned a non-object.');
	    }
	} else {
	    throw new exception('Order ID not set in cart #'.$this->KeyValue().'.');
	}
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
	    $sidZone = $this->FieldRecords()->ShipZone_code();
	    $this->oShipZone->Abbr($sidZone);
	}
	if (is_null($this->oShipZone)) {
	    throw new exception('Internal error: object not created.');
	}
	return $this->oShipZone;
    }

    // -- OTHER OBJECTS ACCESS -- //
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
	    $rcCD = $this->FieldTable()->FieldRows($this->KeyValue());
	    $this->ShipZoneObj()->Abbr($rcCD->ShipZone_code());
	}
	if ($doCheckout) {
	    $this->LogCartEvent('ck1','going to checkout');
	    clsHTTP::Redirect(KWP_CKOUT);
	    $this->LogCartEvent('ck2','sent redirect to checkout');
	}
    }

    // -- ACTIONS -- //
    // ++ SHOPPING WEB UI ++ //

    // TODO: move web UI fx to a descendant class; base class should be business logic only
/*
    protected function RenderHdr() {
	$urlCart = KWP_CART_REL;
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
	return KHT_CART_FTR
	  .'<div class="footer-stats">Cart ID '
	  .$this->KeyValue()
	  .'</div>';
    }
*/
    /*----
      ACTION: Render an order confirmation
	This is the order as recorded by the CART, just before being converted to an Order.
      NOTE: If this shows an order number, it shouldn't. (2014-10-05)
      HISTORY:
	2011-03-27 adapting this from clsOrder::RenderReceipt()
	2014-10-07 This is wrong; it displays a page that looks just like a receipt, but is based on Cart data.
	  The confirmation page needs to have navigation buttons and links back to modify each section.
	  Commenting this out.
    */ /*
    public function RenderConfirm_page() {
	$out = NULL;
	$rcCart = $this;
	$idCart = $rcCart->KeyValue();
	$idSess = $this->SessionID();

	if (!$this->HasLines()) {
	    throw new exception('Internal Error: cart has no contents at order confirmation time.');
	}
	// load contact data

	$rsCData = $rcCart->FieldRecords();
	$ofRecip = $rsCData->RecipFields();
	$ofPay = $rsCData->PayFields();

	//$ofAddrCard = $objCart->AddrCardObj();
	// the next line is a kluge which only works as long as payment is always ccard
	// it's also not clear why GetDetailObjs() isn't loading it properly
	//$objPay->Node('addr', $objAddrCard);

	$htCart = $rcCart->RenderCore(FALSE);

	$arVars = array(
	  'doc.title'	=> "Cart #<b>$idCart</b>",
	  'timestamp'	=> date(KF_RCPT_TIMESTAMP),
	  'cart.id'	=> $idCart,
	  'sess.id'	=> $idSess,
	  'cart.detail'	=> $htCart,
	  'ship.name'	=> $ofRecip->NameValue(),
	  'ship.addr'	=> $ofRecip->Addr_AsText("\n<br>"),
	  'pay.name'	=> $ofPay->NameValue(),
	  'pay.spec'	=> $ofPay->SafeDisplay(),
	  'url.shop'	=> KWP_HOME_REL,
	  'email.short'	=> 'orders-'.date('Y').'@vbz.net'
	  );
	$oTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$oTplt->MarkedValue(KHT_RCPT_TPLT);
	$out .= $oTplt->Replace();
	return $out;
    } */
    /*----
      ACTION: Render an order confirmation page
	This is the order as recorded by the CART, just before being converted to an Order.
	It should have navigation buttons and links to allow editing of the data.
      HISTORY:
	2014-10-07 Adapting from clsPageCkout::RenderConfirm()
    */
    public function RenderConfirm_page() {
	$out = NULL;
	$rsCD = $this->FieldRecords();

	$isShipCard = $rsCD->IsShipToCard();
	//$isShipSelf = $rsCD->IsShipToSelf();
	$strCustShipMsg = $rsCD->ShipMsg();
	$custCardNum = $rsCD->CardNumber();
	$custCardExp = $rsCD->CardExpiry();
	$isShipCardReally = $rsCD->IsShipToCard();
	// TODO: this should probably allow retrieval from stored records... but everything needs to be reorganized
	$sBuyerEmail = $rsCD->BuyerEmailAddress_entered();
	$sBuyerPhone = $rsCD->BuyerPhoneNumber_entered();
	if (is_null($sBuyerPhone)) {
	    $sBuyerPhone = '<i>none</i>';
	}

	$oPage = $this->Engine()->App()->Page();

	$htLink = $oPage->HtmlEditLink(KSQ_PAGE_CART);
	$out .=
	  "\n<tr><td class=section-title>ITEMS ORDERED:</td><td class=section-title align=right>$htLink</td></tr>"
	  ."\n<tr><td colspan=2>\n"
	  .$this->Render(FALSE)	// non-editable display of cart contents & totals
	  ."\n</td></tr>";

	$htLink = $oPage->HtmlEditLink(KSQ_PAGE_SHIP);
	$out .= "<tr><td class=section-title>SHIP TO:</td><td class=section-title align=right>$htLink</td></tr>";

	//$this->doFixedCard = TRUE;
	$this->doFixedSelf = TRUE;
	//$this->doFixedName = TRUE;
	$this->htmlBeforeAddress = '';
	$this->htmlBeforeContact = '';

	$out .= $rsCD->RecipFields()->RenderAddress(
	  array(
	    'do.ship.zone'	=> TRUE,
	    'do.fixed.all'	=> TRUE,
	    'do.fixed.name'	=> TRUE,
	    ),
	  $this->ShipZoneObj()
	  );

	$htLink = $oPage->HtmlEditLink(KSQ_PAGE_PAY);

	$out .= <<<__END__
<tr><td align=right valign=top>
  Special Instructions:<br>
  </td>
  <td>$strCustShipMsg</td>
  </tr>
<tr><td class=section-title>ORDERED BY:</td><td class=section-title align=right>$htLink</td></tr>
<tr><td align=right valign=middle>Email:</td><td>$sBuyerEmail</td></tr>
<tr><td align=right valign=middle>Phone:</td><td>$sBuyerPhone</td></tr>
<tr><td align=right valign=middle>Card Number:</td>
  <td><b>$custCardNum</b>
  - Expires: <b>$custCardExp</b>
  </td></tr>
__END__;
// if card address is different from shipping, then show it too:
// if not shipping to self, then show recipient's phone and/or email:
	if ($isShipCardReally) {
	    $this->strInsteadOfAddr = 'Credit card address <b>same as shipping address</b>';
	}

	//if ($isShipSelf) {
	//    $this->strInsteadOfCont = 'Recipient contact information <b>same as buyer\'s -- shipping to self</b>';
	//}

	// TODO 2012-05-21: this probably won't look right, and will need fixing
	//	also, are strInsteadOf* strings ^ used in confirmation?
	$out .= $rsCD->BuyerFields()->RenderAddress(
	  array(
	    'do.ship.zone'	=> TRUE,
	    'do.fixed.all'	=> TRUE,
	    'do.fixed.name'	=> TRUE,
	    ),
	  $this->ShipZoneObj()
	  );

	//$sPgName = KSQ_ARG_PAGE_DATA;
	//$sPgShow = $this->PageKey_forShow();

	// TODO: this bit should be in a method like RenderNavButtons()
	// It appears *instead* of them.
	$out .= <<<__END__
<tr><td colspan=2 align=center bgcolor=ffffff class=section-title>
__END__;
//<input type=hidden name="$sPgName" value="$sPgShow">
	$out .= <<<__END__
<input type=submit name="btn-go-prev" value="&lt;&lt; Make Changes">
<input type=submit name="btn-go-order" value="Place the Order!">
__END__;

	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    }
    /*----
      PURPOSE: Render cart for order confirmation page (read-only, no form controls)
    */ /*
    public function RenderConfirm() {
	if ($this->HasLines()) {
	    $out = $this->RenderCore(FALSE);
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	} else {
	    $idCart = $this->KeyValue();
	    $idOrder = $this->OrderID();
	    // log error - you shouldn't be able to get to this point with an empty cart
	    $txtParams = "Cart ID=$idCart Order ID=$idOrder";
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
	    $sMsg = "Cart contents have become separated from current cart (#$idCart). The webmaster has been notified.";
	    $out = $this->Engine()->App()->Page()->Skin()->ErrorMessage('<span class=error>INTERNAL ERROR</span>: '.$sMsg);
	}
	return $out;
    }
    /*-----
      ACTION: Renders the order contents as plaintext, suitable for emailing
    */
    /* this needs to be rewritten to use the Order data, not Cart data
    public function RenderOrder_Text() {
// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;
	$ksfCustCardNum = KSF_CART_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_CART_PAY_CARD_EXP;

	$objData = $this->FieldRecords();
// get non-address field data:
	$strCardNum = $objData->CardNumber();
	$strCardExp = $objData->CardExpiry();
	$strCustShipMsg = $objData->ShipMsg();

	$ftCustShipMsg = wordwrap($strCustShipMsg);

	$this->doFixedCard = TRUE;
	$this->doFixedSelf = TRUE;
	$this->doFixedName = TRUE;
	$this->htmlBeforeAddress = '';
	$this->htmlBeforeContact = '';

// the exact routes by which some fields are fetched may need tweaking...

	$strShipName = $objData->RecipName();

	//$objShip = $objData->ShipObj(FALSE);
	//$objCust = $objData->CustObj();

	$sRecipAddr	= $objData->RecipAddr_text();
	$ofRecip	= $objData->RecipFields();
	$sRecipEmail	= $ofRecip->EmailValue();
	$sRecipPhone	= $ofRecip->PhoneValue();

	$sBuyerName	= $objData->BuyerName();
	$sBuyerAddr	= $objData->BuyerAddr_text();
	$ofBuyer	= $objData->BuyerFields();
	$sBuyerEmail	= $ofBuyer->EmailValue();
	$sBuyerPhone	= $ofBuyer->PhoneValue();

	//$strCustName = $objCust->NameVal();

	$out = "ITEMS ORDERED:\n"
	  .$this->RenderCore_Text()
	  ."\n\nSHIP TO:\n"
	  .'  '.$strShipName."\n"
	  .'  '.$sRecipAddr
	  ."\n"
	  ."\n  Email: $sRecipEmail"
	  ."\n  Phone: $sRecipPhone"
	  ."\n\n  ";

	if (empty($strCustShipMsg)) {
	    $out .= "(No special instructions)";
	} else {
	    $out .= "Special Instructions:\n$ftCustShipMsg";

	}
	$out . "\n\nPAYMENT:\n  ".clsCustCards::SafeDescr_Long($strCardNum,$strCardExp)."\n"
	  .'  '.$sBuyerName."\n"
	  .'  '.$sBuyerAddr
	  ."\n"
	  ."\n  Email: ".$sBuyerEmail
	  ."\n  Phone: ".$sBuyerPhone
	  ;

	return $out;
    }
    */
    /*----
      ACTION: Renders shopping cart contents
    */
    /* 2014-12-08 DEPRECATED
    public function RenderCore($iAsForm) {
	echo 'FORM?['.$iAsForm.']';
	throw new exception('Who calls this?');
	$strZone = $this->FieldRecords()->ShipZone_code();
	$dlrShipMinCost = 0;

	$out = cCartDisplay_full::RenderFormHeader();

	$rsLine = $this->LineRecords();
	$dlrCostTotalItem = NULL;
	$dlrCostTotalShip = NULL;
	$dlrShipMinCost = NULL;
	while ($rsLine->NextRow()) {
	    if ($iAsForm) {
		$out .= $rsLine->RenderForm($this);
	    } else {
		$out .= $rsLine->RenderStatic($this);
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

// 2014-10-11 These need to be saved back to the card fields table, which may be why these three lines are here:
	//$this->FieldRecords()->CostTotalSale($dlrCostTotalItem);
	//$this->FieldRecords()->CostTotalPerItem($dlrCostTotalShip);
	//$this->FieldRecords()->CostMaxPerPkg($dlrShipMinCost);
// ...but they aren't being saved, so I've commented them out for now. Trying this instead:
	$rsCFields = $this->FieldRecords();
	$rsCFields->CostTotalSale($dlrCostTotalItem);
	$rsCFields->CostTotalPerItem($dlrCostTotalShip);
	$rsCFields->CostMaxPerPkg($dlrShipMinCost);

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
    } */
    /*-----
      RETURNS: The contents of the cart as text. Includes column headers and totals.
      USED BY: does anything actually use this, or was it intended for the email confirmation?
      HISTORY:
	2014-10-19 Disabled this because email confirmation should be generated by Order object.
    */ /*
    public function RenderCore_Text() {
	$rsData = $this->FieldRecords();

	$abbrShipZone = $rsData->ShipZone_code();
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
	    $rsLines = $this->LineRecords();
	    $dlrSaleTot = 0;	// total sale before shipping
	    $dlrPItmTot = 0;	// per-item shipping total
	    $dlrPPkgMax = 0;	// per-pkg shipping total
	    while ($rsLines->NextRow()) {
		$rcItem = $rsLines->ItemRecord();
		$dlrShipItm = $rcItem->ShipPriceItem($oZone);
		$dlrShipPkg = $rcItem->ShipPricePkg($oZone);

		$out .= $rsLines->RenderText($strLineFmt);
		if ($dlrPPkgMax < $dlrShipPkg) {
		    $dlrPPkgMax = $dlrShipPkg;
		}
		$intQty = $rsLines->Qty;
		$dlrSaleTot += $rsLines->ItemPrice();
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

	$ftZone = $oZone->Text();

	$out .= <<<__END__

 *          Sale: $ftTotalMerch
 * S/H -"
  * per item sum: $ftItemsShip
  * per  package: $ftShipPkg
========================
==== FINAL TOTAL: $ftOrdTotal

Shipping Zone: $ftZone
__END__;
	} else {
	    $out = "\nSorry, we seem to have goofed: this cart appears to have no items in it.";
	    $out .= "\nThe webmaster is being alerted to the problem.";
	    throw new exception('Order confirmation has no items in it.');
	}
	return $out;
    } */
    /*----
      RETURNS: HTML rendering of cart, including current contents and form controls
      HISTORY:
	2013-11-10 Significant change to assumptions. A cart object now only exists
	  to represent a cart record in the database. Any functions that need to work
	  when there is no record are now handled by the cart table object.
    */
    public function Render($bEditable) {
	$id = $this->KeyValue();
	$this->Engine()->App()->Page()->Skin()->AddFooterStat('cart ID',$id);
	if ($this->HasLines()) {
	    if ($bEditable) {
		$oPainter = new cCartDisplay_full_shop($this->ShipZoneObj());
	    } else {
		$oPainter = new cCartDisplay_full_ckout($this->ShipZoneObj());
	    }
	    $rsLine = $this->LineRecords();
	    while ($rsLine->NextRow()) {
		if ($bEditable) {
		    $oLine = $rsLine->GetRenderObject_editable();
		} else {
		    $oLine = $rsLine->GetRenderObject_static();
		}
		$oPainter->AddLine($oLine);
	    }
	    //$oPainter->AddTotals();	// create display lines showing totals
	    $out = $oPainter->Render();
/*
# get information for that destination type:
	    $out = $this->RenderHdr();
	    $out .= $this->RenderCore(TRUE);
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	    $out .= $this->RenderFtr();
	    */
	} else {
	    $out = "<font size=4>Your cart is empty.</font> (cart ID=$id)";
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> 'disp',
	      clsSysEvents::ARG_DESCR_START	=> 'displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr(),
	      clsSysEvents::ARG_WHERE		=> __FILE__.' line '.__LINE__,
	      );
	    //$this->LogEvent('disp','displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr());
	    // 2014-10-26 Let's only add an event when the cart is actually *changed*.
	    //$this->StartEvent($arEv);
	}
	return $out;
    }

    // -- SHOPPING WEB UI -- //
    // ++ CONVERSION TO ORDER ++ //

    /*----
      NOTE: Transactions don't seem to be working, but I'm leaving them in here anyway
	in case I do get them working in the future.
    */
    public function ToOrder(clsOrder $rcOrd) {
	$this->Engine()->TransactionOpen();
	$this->AdminEcho('Converting cart to order...<br>');
	$rcOrd->CartID($this->KeyValue());	// set cart ID
	$ok =
	  $this->ToOrder_Data($rcOrd) &&
	  $this->ToOrder_Lines($rcOrd);

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
      PROCESS: The order record is basically blank at this point, so all cart data must be
	updated into it (and its dependent records, as appropriate). The only fields already set
	are Number, SortPfx, and WhenStarted.
      HISTORY:
	2010-10-06 added cart ID to update -- otherwise final order confirmation page can't find cart data
	2012-05-25 major revision to cart data access -- now using $iCartObj->FieldRecords()
	2013-11-06 this will now import the full order data as well, creating or updating customer records
	  as needed
	2014-01-29 major changes:
	  * adapting to operate from cart object instead of order object
	  * incorporating full cart "import" process
	  * eliminating any attempt to find matching customer profile unless specifically chosen by user
	2014-11-30 I had "ID_Recip" marked as "can be NULL" in the update process, but actually
	  the order confirmation expects it to exist -- so if it actually should be NULLable, we need
	  to document why that is and where to get the information from instead.
      TODO:
	card data in cart must be *encrypted* after copying
    */
    private function ToOrder_Data(clsOrder $rcOrd) {
	$idOrd = $rcOrd->KeyValue();
	$idCart = $this->KeyValue();

	$rsFields = $this->FieldRecords();

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

	// get overall order fields
	$curItemTotal = $rsFields->CostTotalSale();
	$curShipItem = $rsFields->CostTotalPerItem();
	$curShipPkg = $rsFields->CostMaxPerPkg();

	// CART RECORD UPDATE (link to the Order)

	$arUpd = array(
	  'ID_Order'	=> $idOrd,
	  'WhenOrdered'	=> 'NOW()',
	  );
	$this->Update($arUpd);

	// CUSTOMER RECORDS

	// TODO: Do NOT create new customer records if user has selected existing ones!

	$tCust = $this->CustomerTable();
	$tCard = $this->CustomerCardTable();
	$rcSess = $this->SessionRecord();	// just so we can get user ID
	$idUser = $rcSess->UserID();

	// if not using existing customer records, create them
	$sBuyerIntype	= $rsFields->FieldValue_forIndex(KI_CART_PAY_CARD_INTYPE);
	$doNewBuyer	= ($sBuyerIntype == KS_FORM_INTYPE_NEWENTRY);
	$sRecipIntype	= $rsFields->FieldValue_forIndex(KI_CART_RECIP_INTYPE);
	$doNewRecip	= ($sRecipIntype == KS_FORM_INTYPE_NEWENTRY);

	if ($doNewBuyer) {
	    $sBuyerName		= $rsFields->BuyerName();
	    $oAddrBuyer		= $rsFields->BuyerFields();
	    $oCardInfo		= $rsFields->PayFields();
	    $idBuyer		= $tCust->CreateCustomer($idUser,$sBuyerName,$oAddrBuyer);
	    if ($idBuyer === FALSE) {
		throw new exception('Could not create record for buyer "'.$sBuyerName.'".');
	    }
	    $idBuyerCard	= $tCard->CreateRecord($idBuyer,$oCardInfo);
	} else {
	    $idBuyerCard	= $rsFields->FieldValue_forIndex(KI_CART_PAY_CARD_CHOICE);
	    if (is_null($idBuyerCard)) {
		echo 'CART VALUES: '.$rsFields->DumpVals();
		throw new exception('Internal Error: Card choice expected but not received for cart ID='.$idCart);
	    }
	    $rcBuyerCard	= $this->CustomerCardTable($idBuyerCard);
	    $idBuyer		= $rcBuyerCard->CustID();
	    $sBuyerName		= $rcBuyerCard->OwnerName();
	}
	if ($doNewRecip) {
	    $sRecipName		= $rsFields->RecipName();
	    $sRecipAddr		= $rsFields->RecipAddr_text();
	    $oAddrRecip		= $rsFields->RecipFields();
	    $idRecip		= $tCust->CreateCustomer($idUser,$sRecipName,$oAddrRecip);
	    if ($idRecip === FALSE) {
		throw new exception('Could not create record for recipient "'.$sRecipName.'".');
	    }
	    $rcRecip		= $this->CustomerTable($idRecip);
	    $idRecipAddr	= $rcRecip->AddrID();
	} else {
	    $idRecipAddr	= $rsFields->FieldValue_forIndex(KI_CART_RECIP_CHOICE);
	    $rcRecipAddr	= $this->CustomerAddressTable($idRecipAddr);
	    $idRecip		= $rcRecipAddr->CustID();
	    $sRecipName		= $rsFields->RecipName();
	    $sRecipAddr		= $rcRecipAddr->AsString();
	}

	// MESSAGE RECORD

	$sMsg = $this->FieldRecords()->ShipMsg();
	if (!is_null($sMsg)) {
	    //$	rcOrd = $this->OrderRecord();
	    $rcOrd->AddMessage(
	      KSI_ORD_MSG_INSTRUC,	// media = order instructions
	      'customer',
	      'shipper',
	      'instructions',
	      $sMsg);
	}

	// ORDER UPDATE

	if (is_null($idRecip)) {
	    throw new exception('Internal Inquiry: Recip ID is null in order conversion. Why is this?');
	}

	$arUpd = array(
	  'ID_Cart'		=> $idCart,
	  'WebTotal_Merch'	=> SQLValue($curItemTotal),
	  'WebTotal_Ship' 	=> SQLValue($curShipItem+$curShipPkg),
	  'WebTotal_Final'	=> SQLValue($curItemTotal+$curShipItem+$curShipPkg),
	  // TODO: ^ aren't these totals handled by an object somewhere now?
	  'ID_Buyer'		=> SQLValue($idBuyer),	// can be NULL
	  'ID_Recip'		=> $idRecip,
	  'ID_BuyerCard'	=> SQLValue($idBuyerCard),	// this could be NULL when we support other payment methods
	  'ID_RecipAddr'	=> SQLValue($idRecipAddr),	// this could be NULL when we support in-store pickup
	  'BuyerName'		=> SQLValue($sBuyerName),
	  'RecipName'		=> SQLValue($sRecipName),
	  'RecipAddr'		=> SQLValue($sRecipAddr)
	  );
	$rcOrd->Update($arUpd);
	$this->AdminEcho('Order Update SQL: '.$rcOrd->sqlExec.'<br>');
	$this->FinishEvent();

	$rcOrd->Reload();	// ...or we could set the fields individually
	if (is_null($rcOrd->RecipID())) {
	    echo 'arUpd:<pre>'.print_r($arUpd,TRUE).'</pre>';
	    throw new exception('Internal Error: Local Recip ID is null after order conversion. This should not happen.');
	}

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
