<?php
/*
  PURPOSE: shopping cart stuff -- base
  HISTORY:
    2012-04-17 extracted from shop.php
    2013-09-13 now using cart-const.php
*/

require_once('cart-const.php');

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
}
class clsShopCart extends clsDataSet {
    protected $objShipZone;
    protected $objCartData;
    private $arDataItem;
    protected $objOrder;
    private $oSess;

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
	$this->objDB->CartLog()->Add($this,$iCode,$iDescr,$strUser);
    }
    //====
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
	return !(is_null($this->WhenVoided));
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
    public function HasCart() {
	return $this->IsCreated();	// may use different criteria later on
    }
    /*----
      TODO: Figure out why $this->HasField('ID_Sess') would ever *not* be true.
    */
    public function HasSession() {
	$ok = FALSE;
	if ($this->HasField('ID_Sess')) {	// why would this ever *not* be true?
	    if ($this->SessID() > 0) {
		$ok = TRUE;
	    }
	}
	return $ok;
    }
    /*----
      NOTE: This is only private because nothing else needs it yet.
	Okay to open it up if something does.
      ASSUMES: row is set
    */
    private function SessID() {
	return $this->Value('ID_Sess');
    }
    /*----
      HISTORY:
	2013-10-13
	  * renamed from Session() to SessObj() for consistency
	  * added caching of session object
    */
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
	    return $jSess;
	} else {
	    $this->oSess = NULL;
	    return NULL;
	}
    }
    // DEPRECATED - use OrderObj()
    public function Order() {
	return $this->OrderObj();
    }
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
	    if ($this->HasCart()) {
		$idZone = $this->CartData()->ShipZone();
		$this->objShipZone->Abbr($idZone);
	    }
	}
	if (is_null($this->objShipZone)) {
	    throw new exception('Internal error: object not created.');
	}
	return $this->objShipZone;
    }
    public function HasLines() {
	$objLines = $this->GetLines();
	if (is_null($objLines)) {
	    return FALSE;
	} else {
	    return $objLines->hasRows();
	}
    }
    public function LineCount() {
	if ($this->HasLines()) {
	    return $this->objLines->RowCount();
	} else {
	    return 0;
	}
    }
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
    public function IsCreated() {
	return ($this->ID > 0);
    }
// == FORM HANDLING STUFF
    /*----
      NOTE: If shipping zone requested by customer isn't affecting the cart, the
	problem is probably happening here.
    */
    public function CheckData() {
// check for buttons
	$doCheckout = isset($_POST['finish']);
	$isCart = (isset($_POST['recalc']) || $doCheckout);
	$isZoneSet = FALSE;	// have we set the zone from stored data?
// check for specific actions
	if (isset($_GET['action'])) {
	    $strDo = $_GET['action'];
	    switch ($strDo) {
	      case 'del':
		$intItem = 0+$_GET['item'];
		$this->GetLines();
		$this->objLines->Update(array('Qty'=>0),'ID_Item='.$intItem);
		$this->LogEvent('del','deleting from cart: ID '.$intItem);
		break;
	      case 'delcart';
		$this->LogEvent('clr','voiding cart');
		$this->ID = -1;
		$this->objSess->DropCart();
		break;
	    }
	} else {
	    foreach ($_POST as $key => $val) {
    // check for added items:
		if (substr($key,0,4) == 'qty-') {
		    if (($val != '') && ($val != 0)) {
			$sqlCatNum = $this->objDB->SafeParam(substr($key,4));
			if ($isCart) {
			    // zero out all items, so only items in visible cart will be retained:
			    $this->ZeroAll();
			}
			$this->AddItem($sqlCatNum,$val);
		    }
		} elseif ($key == KSF_SHIP_ZONE) {
//		    $custShipZone	= $this->GetFormItem(KSF_SHIP_ZONE);
		    $custShipZone	= $val;
		    $this->ShipZoneObj()->Abbr($custShipZone);
		    $isZoneSet = TRUE;
		}
	    }
	}
	if (!$isZoneSet) {
	    // reload the shipping zone if we don't already know it
	    if ($this->HasCart()) {
		$this->ShipZoneObj()->Abbr($this->CartData()->ShipZone());
	    }
	}
	if ($doCheckout) {
	    $this->LogEvent('ck1','going to checkout');
	    $objSess = $this->Session();
	    http_redirect(KWP_CKOUT);
	    $this->LogEvent('ck2','sent redirect to checkout');
	}
    }
    public function ZeroAll() {
	$this->Update(array('Qty'=>0),'ID_Cart='.$this->ID);
    }
    public function AddItem($iCatNum,$iQty) {
	$this->Build();	// make sure there's a record for the cart, get ID
	$objCartLines = $this->objDB->CartLines();
	$objCartLines->Add($this->ID,$iCatNum,$iQty);
	$this->LogEvent('add','adding to cart: cat# '.$iCatNum.' qty '.$iQty);
    }
    /*-----
      ACTION:
	* make sure there is a cart record
	* update the quantity, if there is one
    */
    public function Build() {
	$id = $this->ID;
	if (empty($id)) {
	    $this->Create();
	}
    }
    public function Create() {
	$sql =
	  'INSERT INTO `'.clsShopCarts::TableName.'` (WhenCreated,ID_Sess)'.
	  'VALUES(NOW(),'.$this->ID_Sess.');';
	$this->objDB->Exec($sql);
	$this->ID = $this->objDB->NewID('carts.create');
	$objSess = $this->objDB->Sessions()->GetCurrent();
	if (!is_object($objSess->Table)) {
	    throw new exception('Session object has no table for Cart ID='.$this->Value('ID'));
	}
	$objSess->SetCart($this->ID);
    }
    public function RenderHdr() {
	$out = "\n\n".'<!-- Cart ID='.$this->KeyValue().' | Session ID='.$this->ID_Sess." -->";
	$out .= "\n<center><table class=border><tr><td><table class=cart><tr><td align=center valign=middle>";
	$out .= "\n<form method=post action='./'>";
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
	$ksfCustCardNum = KSF_CUST_CARD_NUM;
	$ksfCustCardExp = KSF_CUST_CARD_EXP;

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

	$strShipName = $objData->ShipAddrName();

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

	$rsLine = $this->objLines;
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
	$this->CartData()->CostTotalItem();
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

	//$objSess = $this->Session();	// 2013-10-14 never used
	
	if ($iAsForm) {
	    $htDelAll = '<span class=text-btn>[<a href="?action=delcart" title="remove all items from cart">remove all</a>]</span>';
	    $htFirstTot = "<td align=left>$htDelAll</td><td align=right class=total-desc colspan=4>totals:</td>";
	    $htZoneCombo = 'Shipping destination: '.$this->objShipZone->ComboBox();
	} else {
	    $htFirstTot = '<td align=right class=total-desc colspan=5>totals:</td>';
	    $htZoneCombo = 'Shipping costs shown assume shipment to <b>'.$this->objShipZone->Text().'</b> address.';
	}
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
    public function Render() {
// return rendering of current contents of cart
	$ok = FALSE;
	if ($this->ID) {
	    if ($this->HasLines()) {
		$ok = TRUE;
	    }
	}

	if ($ok) {
# get information for that destination type:
	    $out = $this->RenderHdr();
	    $out .= $this->RenderCore(TRUE);
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	    $out .= $this->RenderFtr();
	} else {
	    if ($this->IsCreated()) {
		if (is_null($this->objLines)) {
		    $out = "<font size=4>Internal error - cart data not available!</font>";
		    $this->LogEvent('disp',"can't display cart - no data!");
		    $this->objDB->Events()->LogEvent('cart.render','','cart data unavailable','cdna',TRUE,TRUE);
		} else {
		    $out = "<font size=4>Your cart is empty.</font>";
		    $this->LogEvent('disp','displaying cart - empty; zone '.$this->ShipZoneObj()->Abbr());
		}
	    } else {
		$out = "<font size=4>You have not put anything in your cart yet.</font>";
		$this->LogEvent('disp','displaying cart - nothing yet; zone '.$this->ShipZoneObj()->Abbr());
	    }
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
	    $this->objDB->LogEvent('cart.renderconf',$txtParams,'cart empty at confirmation','cec',TRUE,TRUE);	// also sends email alert
	    $out = '<font color=red>INTERNAL ERROR</font>: cart data has become separated from browser. The webmaster has been notified.';
	}
	return $out;
    }
}
