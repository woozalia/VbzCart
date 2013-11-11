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

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsShopCart');
	  $this->ActionKey('cart');
    }
    protected function LinesTable() {
	return $this->Engine()->CartLines();
    }
    protected function SessObj() {
	return $this->Engine()->App()->Session();
    }
    protected function CartExists() {
	return $this->SessObj()->HasCart();
    }
    protected function CartObj($iRequire) {
	//return $this->SessObj()->CartObj_toUse($doRequire);
	$oSess = $this->SessObj();
	if ($iRequire) {
	    return $oSess->CartObj_toUse();
	} else {
	    return $oSess->CartObj_Current();
	}
    }
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
    /*----
      ACTION: Check form input to see if anything needs to be done to the current Cart.
      HISTORY:
	2013-11-09 moved from clsShopCart to clsShopCarts
    */
    public function CheckData() {
// check for buttons
	$doAddItems	= array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
	$doRecalc	= array_key_exists(KSF_CART_BTN_RECALC,$_POST);
	$doShipZone	= array_key_exists(KSF_SHIP_ZONE,$_POST);
	$doModify	= array_key_exists(KSF_CART_CHANGE,$_GET);
	$doCheckout	= array_key_exists(KSF_CART_BTN_CKOUT,$_POST);
	$isCart = ($doRecalc || $doCheckout);	// there must already be a cart under these conditions
	$doItems = ($doAddItems || $doRecalc);	// if true, there are items to process
	$isZoneSet = FALSE;	// have we set the zone from stored data?
	$db = $this->Engine();
	$oCart = $this->CartObj(TRUE);	// get the current cart (create if absent)
// check for specific actions
	if ($doItems) {
	    if ($isCart) {
		// zero out all items, so only items in visible cart will be retained:
		$oCart->ZeroAll();
	    }
	    // get the list of items posted
	    $arItems = $_POST[KSF_CART_ITEM_ARRAY_NAME];
	    // add each non-empty item
	    foreach ($arItems as $key => $val) {
		if (!empty($val)) {
		    $sqlCatNum = $db->SafeParam($key);
		    $oCart->AddItem($sqlCatNum,$val);
		}
	    } // END for each item
	// END do add items
	} elseif ($doShipZone) {
	    $custShipZone	= $_POST[KSF_SHIP_ZONE];
	    $oCart->ShipZoneObj()->Abbr($custShipZone);
	    $isZoneSet = TRUE;
	} elseif ($doModify) {
	    // these actions operate on an existing cart
	    if ($this->CartExists()) {
		$strDo = $_GET[KSF_CART_CHANGE];
		switch ($strDo) {
		  case KSF_CART_EDIT_DEL_LINE:
		    $idLine = $_GET[KSF_CART_EDIT_LINE_ID];
		    $oCart->DelLine($idLine);
		    $idCart = $oCart->KeyValue();
		    $oCart->LogEvent('del',"deleting line ID $idLine");
		    break;
		  case KSF_CART_EDIT_DEL_CART;
		    $oCart->LogEvent('clr','voiding cart');
		    //$this->ID = -1;
		    $this->SessObj()->DropCart();
		    break;
		}
	    } else {
		throw new exception('Attempted operation "'.$strDo.'" without a cart.');
	    }
	}
	if (!$isZoneSet && $isCart) {
	    // reload the shipping zone if we don't already know it
	    // 2013-11-10 Is this a kluge? Why wouldn't we already know it?
	    $oCart->ShipZoneObj()->Abbr($oCart->CartData()->ShipZone());
	}
	if ($doCheckout) {
	    $oCart->LogEvent('ck1','going to checkout');
	    http_redirect(KWP_CKOUT);
	    $oCart->LogEvent('ck2','sent redirect to checkout');
	}
    }
    /*----
      HISTORY:
	2013-11-10 Significant change to assumptions. A cart object now only exists
	  to represent a cart record in the database. The cart table object now handles
	  situations where there is no cart record.
    */
    public function RenderCart() {
	if ($this->CartExists()) {
	    $out = $this->CartObj(FALSE)->Render();
	} else {
	    $out = "<font size=4>You have not put anything in your cart yet.</font>";
	    $this->LogEvent('disp','displaying cart - nothing yet; zone '.$this->ShipZoneObj()->Abbr());
	}
	return $out;
    }
}
class clsShopCart extends clsDataSet {
    protected $objShipZone;
    protected $objCartData;
    private $arDataItem;
    protected $objOrder;
    private $oSess;
    private $oLines;

    protected $hasDetails;	// customer details have been loaded?

    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
	$this->objShipZone = NULL;
	$this->objCartData = NULL;
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
    /*====
      BLOCK: EVENT HANDLING
      HISTORY:
	2011-03-27 copied from VbzAdminCustCard to VbzAdminCart
	  Then moved from VbzAdminCart to clsShopCart
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    // specialized event logging (deprecated)
    public function LogEvent($iCode,$iDescr,$iUser=NULL) {
	global $vgUserName;

	$strUser = is_null($iUser)?$vgUserName:$iUser;
	$this->Engine()->CartLog()->Add($this,$iCode,$iDescr,$strUser);
    }
    //====
    /*----
      USAGE: called by clsShopCarts->CheckData() when items are found in _POST input
      HISTORY:
	2013-11-10 Removed call to Make(), since we're now assuming that there is a record if we're here.
    */
    public function AddItem($iCatNum,$iQty) {
	$oLines = $this->Engine()->CartLines();
	$oLines->Add($this->KeyValue(),$iCatNum,$iQty);
	$this->LogEvent('add','adding to cart: cat# '.$iCatNum.' qty '.$iQty);
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
	return !(is_null($this->WhenOrdered));
    }
    /*----
      RETURNS: TRUE if the cart has been discarded (voided)
      USED BY: $this->IsLocked() and (something)->IsUsable()
      HISTORY:
	2011-03-27 written for improved handling of cart status at checkout
    */
    public function IsVoided() {
	return !(is_null($this->Value('WhenVoided')));
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
      NOTE: Cart ID will be null when we haven't yet been assigned a new cart record.
	This now returns NULL when that happens.
    */
    public function CartData() {
	if (is_null($this->objCartData)) {
	    $idCart = $this->KeyValue();

	    if (empty($idCart)) {
//		throw new exception('Internal error: cart ID not set.');
	    } else {
		$this->objCartData = new clsCartVars($this->Engine());
		$this->objCartData->LoadCart($idCart);
	    }
	}
	return $this->objCartData;
    }
// == STATUS
/*
    public function HasCart() {
	return $this->IsCreated();	// may use different criteria later on
    }
*/
    /*----
      TODO: Figure out why $this->HasField('ID_Sess') would ever *not* be true.
    */
/*
    public function HasSession() {
	$ok = FALSE;
	if ($this->HasField('ID_Sess')) {	// why would this ever *not* be true?
	    if ($this->SessID() > 0) {
		$ok = TRUE;
	    }
	}
	return $ok;
    }
*/
    /*----
      NOTE: This is only private because nothing else needs it yet.
	Okay to open it up if something does.
      ASSUMES: row is set
    */
/* 2013-11-10 What uses this? Document it.

    private function SessID() {
	return $this->Value('ID_Sess');
    }
*/
    /*----
      HISTORY:
	2013-10-13
	  * renamed from Session() to SessObj() for consistency
	  * added caching of session object
    */
/* 2013-11-10 This needs to be explained, if it is actually needed.
    Shouldn't requests for sessions be going to App()->Session() or something?

    public function SessObj() {
	if ($this->HasSession()) {
	    $doNew = TRUE;
	    if (!is_null($this->oSess)) {
		if ($this->oSess->KeyValue() == $this->SessID()) {
		    $doNew = FALSE;
		    $oSess = $this->oSess;
		}
	    }
	    if ($doNew) {
		$tSess = $this->Engine()->Sessions();
		$oSess = $tSess->GetItem($this->ID_Sess);
		$this->oSess = $oSess;
	    }
	    return $oSess;
	} else {
	    $this->oSess = $this->App()->Session();
	    return $this->oSess;
	}
    }
*/
/*    // DEPRECATED - use OrderObj()
    public function Order() {
	return $this->OrderObj();
    }
*/
    /*----
      RETURNS: Order object
    */
    public function OrderObj() {
	$doGet = TRUE;
	if (isset($this->objOrder)) {
	    if ($this->objOrder->ID == $this->ID_Order) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->objOrder = $this->objDB->Orders()->GetItem($this->ID_Order);
	}
	return $this->objOrder;
    }
    public function ShipZoneObj() {
	if (is_null($this->objShipZone)) {
	    $this->objShipZone = new clsShipZone();
	    $idZone = $this->CartData()->ShipZone();
	    $this->objShipZone->Abbr($idZone);
	}
	if (is_null($this->objShipZone)) {
	    throw new exception('Internal error: object not created.');
	}
	return $this->objShipZone;
    }
    public function HasLines() {
	$oLines = $this->LinesObj();
	if (is_null($oLines)) {
	    return FALSE;
	} else {
	    return $oLines->hasRows();
	}
    }
    public function LineCount() {
	if ($this->HasLines()) {
	    return $this->LinesObj()->RowCount();
	} else {
	    return 0;
	}
    }
    public function LinesObj() {
	if (is_null($this->oLines)) {
	    $this->oLines = $this->Engine()->CartLines()->GetData('(ID_Cart='.$this->KeyValue().') AND (Qty>0)');
	}
	return $this->oLines;
    }
/*
    public function GetLines($iRefresh=TRUE) {
	if ($iRefresh || (!isset($this->objLines))) {
	    if ($this->IsCreated()) {
		//$this->objLines = $this->objDB->CartLines()->GetData('(ID_Cart='.$this->ID.') AND (Qty>0)','clsShopCartLine');
		$this->objLines = $this->objDB->CartLines()->GetData('(ID_Cart='.$this->ID.') AND (Qty>0)');
	    } else {
		$this->objLines = NULL;
	    }
	}
	return $this->objLines;
    }
*/
    public function IsCreated() {
	return ($this->ID > 0);
    }
// == FORM HANDLING STUFF
    public function ZeroAll() {
	$this->Update(array('Qty'=>0),'ID_Cart='.$this->ID);
    }
    public function RenderHdr() {
	$urlCart = KWP_CART_REL;	// remove any query data
	$out = "\n\n".'<!-- Cart ID='.$this->KeyValue().' | Session ID='.$this->Value('ID_Sess')." -->";
	$out .= "\n<center><table class=border><tr><td><table class=cart><tr><td align=center valign=middle>";
	$out .= "\n<form method=post action='$urlCart'>";
	$out .= "\n<table class=cart-data>\n";
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
	$ksfCustCardNum = KSF_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_PAY_CARD_EXP;

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
	$strZone = $this->CartData()->ShipZone();
	$shipMinCost = 0;

	$out = '<tr>'
	  .'<th><big>cat #</big></th>'
	  .'<th><big>description</big></th>'
	  .'<th>price<br>each</th>'
	  .'<th><small>per-item<br>s/h ea.</small></th>'
	  .'<th>qty.</th>'
	  .'<th><small>purchase<br>line total</small></th>'
	  .'<th><small>per-item<br>s/h line total</small></th>'
	  .'<th>totals</th>'
	  .'<th><small>pkg s/h<br>min.</small></th>'
	  .'</tr>';

	$rsLine = $this->LinesObj();
	while ($rsLine->NextRow()) {
	    if ($iAsForm) {
		$out .= $rsLine->RenderForm($this);
	    } else {
		$out .= $rsLine->RenderHtml($this);
	    }
	    if ($shipMinCost < $rsLine->ShipPkgDest) {
		    $shipMinCost = $rsLine->ShipPkgDest;
	    }
	    $intQty = $rsLine->Qty;
	    $this->CostTotalItem += $rsLine->CostItemQty;
	    $this->CostTotalShip += $rsLine->CostShipQty;
	}
// save official totals for order creation:
// TO DO: are CostTotalItem and CostTotalShip referenced anywhere else? Make them local if not.
//	But if they are, then why isn't shipMinCost also a field?
	$this->CartData()->CostTotalSale();
	$this->CartData()->CostTotalPerItem();
	$this->CartData()->CostTotalPerPkg();

	$strTotalMerch = FormatMoney($this->CostTotalItem);
	$strItemsShip = FormatMoney($this->CostTotalShip);
	$strTotalItems = FormatMoney($this->CostTotalItem + $this->CostTotalShip);
	$strShipZone = $this->objShipZone->Text();
	$strShipDesc = $strShipZone.' s/h package cost:';
	$strShipPkg = FormatMoney($shipMinCost);
	$strTotalDesc = 'order total if shipping to '.$strShipZone.':';
	$strOrdTotal = FormatMoney($this->CostTotalItem + $this->CostTotalShip + $shipMinCost);

	if ($iAsForm) {
	    $htDelAll = '<span class=text-btn>[<a href="?'.KSF_CART_CHANGE.'='.KSF_CART_EDIT_DEL_CART.'" title="remove all items from cart">remove all</a>]</span>';
	    $htFirstTot = "<td align=left>$htDelAll</td><td align=right class=total-desc colspan=4>totals:</td>";
	    $htZoneCombo = 'Shipping destination: '.$this->objShipZone->ComboBox();
	} else {
	    $htFirstTot = '<td align=right class=total-desc colspan=5>totals:</td>';
	    $htZoneCombo = 'Shipping costs shown assume shipment to <b>'.$this->objShipZone->Text().'</b> address.';
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
<tr><td colspan=6>$htZoneCombo</td></tr>
__END__;
	$out .= '<!-- '.__FILE__.' line '.__LINE__.' -->';
	$this->LogEvent('disp','displaying cart, zone '.$this->objShipZone->Abbr().' total $'.$strOrdTotal);
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
	$objZone = $this->ShipZoneObj();

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
	$ftShipZone = $this->objShipZone->Text();
	$ftShipDesc = $ftShipZone.' s/h package cost:';

	//$objSess = $this->Session();

	$ftZone = $this->objShipZone->Text();
	//$ftZone = $this->objShipZone->Abbr();
	//$ftZone = $abbrShipZone;

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
	    $out = "<font size=4>Your cart is empty.</font>";
	    $this->LogEvent('disp','displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr());
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
	    $txtParams = 'Cart ID='.$this->ID.' Order ID='.$this->ID_Order;
	    $this->Engine()->LogEvent('cart.renderconf',$txtParams,'cart empty at confirmation','cec',TRUE,TRUE);	// also sends email alert
	    $out = '<font color=red>INTERNAL ERROR</font>: cart data has become separated from browser. The webmaster has been notified.';
	}
	return $out;
    }
}
