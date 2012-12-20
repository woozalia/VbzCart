<?php
/*
  FILE: ckout.php
  HISTORY:
    2010-02-21 split off from shop.php (via checkout/index.php)
    2010-12-24 Fixed call to Update() -- array is now required
*/

clsLibMgr::Add('vbz.page.cart',	KFP_LIB_VBZ.'/page-cart.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsPageCart', 'vbz.page.cart');

/*=====
  CLASS: clsPageCkOut
  PURPOSE: subclass for generating checkout pages
*/
class clsPageCkout extends clsPageCart {
/*
    protected $objCust;	// person who placed the order
    protected $objShip;	// person who is receiving the order (may be the same)
*/

    /*----
      NOTE: needs to be public so clsPerson can access it
    */
    public function CartObj() {
	return $this->objCart;	// document where this is set!
    }
    /*----
      OUTPUT:
	$inCkout: if TRUE, it's ok to use a cart whose order has been placed.
    */
    protected function ParseInput() {
# Two important concepts:
#	1. $pgData: The page whose data we're receiving (blank if there is no data)
#	2. $pgShow: The page whose form we're wanting to display

# which page were we just on
	$gotPgDest = FALSE;
	$gotPgSrce = FALSE;
	if (empty($_GET[KSQ_ARG_PAGE_DEST])) {
	    if (empty($_POST[KSQ_ARG_PAGE_DATA])) {
		$this->pgData = KSQ_PAGE_CART;	// only the cart doesn't specify itself
	    } else {
		$this->pgData = $_POST[KSQ_ARG_PAGE_DATA];
		$gotPgSrce = TRUE;
	    }
	} else {
	    $gotPgDest = TRUE;
	    $this->pgData = NULL;	// Currently not expecting any data from a "GET" query
	    $this->pgShow = $_GET[KSQ_ARG_PAGE_DEST];
	}
	if (!$gotPgDest) {
	// destination page unknown, so calculate it from data/source page:
	    if (nz($_POST['btn-go-prev'])) {
		switch ($this->pgData) {
		  case KSQ_PAGE_CART:
		    $this->pgShow = KSQ_PAGE_CART;	// can't go back any further
		    break;
		  case KSQ_PAGE_SHIP:
		    $this->pgShow = KSQ_PAGE_CART;
		    break;
		  case KSQ_PAGE_PAY:
		    $this->pgShow = KSQ_PAGE_SHIP;
		    break;
		  default:	// source page name not recognized; default to cart
		    $this->pgShow = KSQ_PAGE_CART;	// can't go back any further
		}
	    } elseif (nz($_POST['btn-go-next'])) {
		switch ($this->pgData) {
		  case KSQ_PAGE_CART:
		    $this->pgShow = KSQ_PAGE_SHIP;
		    break;
		  case KSQ_PAGE_SHIP:
		    $this->pgShow = KSQ_PAGE_PAY;
		    break;
		  case KSQ_PAGE_PAY:
		    $this->pgShow = KSQ_PAGE_CONF;
		    break;
		  default:	// source page name not recognized; default to cart
		    $this->pgShow = KSQ_PAGE_CART;	// can't go back any further
		}
	    } elseif (nz($_POST['btn-go-same'])) {
		$this->pgShow = $this->pgData;
	    } elseif (nz($_POST['btn-go-order'])) {
		$this->pgShow = KSQ_PAGE_RCPT;	// receipt page - submits order too
	    } else {
		$this->pgShow = KSQ_PAGE_DEFAULT;	// default page to display
	    }
	}

	switch ($this->pgShow) {
	  case KSQ_PAGE_CART:
	    $this->inCkout = FALSE;
	    break;
	  case KSQ_PAGE_SHIP:
	    $this->inCkout = TRUE;
	    break;
	  case KSQ_PAGE_PAY:
	    $this->inCkout = TRUE;
	    break;
	  case KSQ_PAGE_CONF:
	    $this->inCkout = TRUE;
	    break;
	  default:	// we shouldn't have to do this... something isn't properly rigorous here.
	    $this->inCkout = TRUE;
	    break;
	}

	$this->GetObjects(__METHOD__);	// needs to know what phase we;re in
	if (!($this->CartObj()->ID > 0)) {
//	if (!is_object($this->objCart)) {
	    // if cart is not set, don't go past cart display
	    $this->pgShow = KSQ_PAGE_CART;
	    $this->LogEvent('ckout.parsequery','pgShow='.$this->pgShow,'cart ID not set','!cart',TRUE,TRUE);
	}
	$this->CapturePage();

    }
    protected function HandleInput() {
	$this->strWikiPg	= '';
	$this->strTitle	= 'Checkout';	// Displayed title (page header)
	$this->strName	= 'checkout';	// HTML title
	$this->strTitleContext	= 'this is the secure'; // 'Tomb of the...';
	$this->strHdrXtra	= '';
	$this->strSideXtra	= ''; //'<dt><b>Cat #</b>: '.$this->strReq;
	$this->strSheet	= 'ckout';	// default

	$this->CartObj()->LogEvent('page','showing page "'.$this->pgShow.'"');
	$this->formShow = $this->pgShow;
	$this->doNavBar = TRUE;
	$this->doBackBtn = TRUE;
	$this->doRefrBtn = FALSE;
    }
    /*----
      OUTPUT:
	$doBackBtn: if TRUE, show the BACK navigation button
	$doRefrBtn:
	$doNavBar:
    */
    public function DoContent() {
	$out = NULL;

	// default options
	$this->doFixedCard = FALSE;
	$this->doFixedCountry = FALSE;

	switch ($this->pgShow) {
	  case KSQ_PAGE_CART:	// shopping cart
	    $this->doBackBtn = FALSE;
	    $this->doRefrBtn = TRUE;
	    $out .= $this->RenderCart();
	    break;
	  case KSQ_PAGE_SHIP:	// shipping information
	    $out .= $this->RenderShipping();
	    break;
	  case KSQ_PAGE_PAY:	// billing information
	    $out .= $this->RenderBilling();
	    break;
	  case KSQ_PAGE_CONF:	// confirm order
	    $this->doNavBar = FALSE;
	    $out .= $this->RenderConfirm();
	    break;
	  case KSQ_PAGE_RCPT:	// order receipt
	    $this->doNavBar = FALSE;
	    $out .= $this->ReceiveOrder();
	    break;
	  default:
	    //$out .= $this->RenderCart();	// cart seems like a safe fallback - avoids showing any customer data
	    // more likely to be a hacking attempt than an internal error:
	    //$this->LogEvent('ckout.render','pgShow='.$this->pgShow,'unknown form type','FUNK',FALSE,TRUE);
// The normal shopping cart does not specify a target within the checkout sequence
// ...so show the first page which follows the cart page:
	    $out .= $this->RenderShipping();
	}
	echo $out;
    }
    /*----
      PURPOSE: Render top part of {form and outer table, including <td>}
    */
    protected function RenderContentHdr() {
	$out = '<!-- +RenderContentHdr() -->';
	$out .= '<center><form action="./" name=checkout method=post><table class=ckout-outer><tr><td>';
	$out .= '<!-- -RenderContentHdr() -->';
	echo $out;
    }
    /*----
      PURPOSE: Close table row opened in RenderContentHdr(), display standard buttons, close outer table and form
    */
    protected function RenderContentFtr() {
	echo '<!-- +RenderContentFtr() -->';

	if ($this->FieldsMissing()) {
	    $htMissing = '<tr><td colspan=2><table><tr><td><img src="'.KWP_ICON_ALERT
	      .'"></td><td valign=middle><span class=alert><b>Please fill in the following</b></span>: '
	      .$this->strMissing
	      .'</td></tr></table></td></tr>';
	} else {
	    $htMissing = '';
	}

	if ($this->doNavBar) {
	    $strPgDataName = KSQ_ARG_PAGE_DATA;
	    if ($this->doBackBtn) {
		$htBackBtn = '<input type=submit name="btn-go-prev" value="&lt; Back">';
	    } else {
		$htBackBtn = '';
	    }
	    if ($this->doRefrBtn) {
		$htRefrBtn = '<input type=submit name="btn-go-same" value="Update">';
	    } else {
		$htRefrBtn = '';
	    }
	    $out =
	      $htMissing
	      .'<tr><td colspan=2 align=center bgcolor=ffffff class=section-title>'
	      .'<input type=hidden name="'.$strPgDataName.'" value="'.$this->pgShow.'">'
	      .$htBackBtn.$htRefrBtn
	      .'<input type=submit name="btn-go-next" value="Next &gt;">';
	} else {
	    $out = '';
	}

	echo '</td></tr>';	// close table row opened by RenderContentHdr()
	echo $out;
	echo '</table></form>';	// close outer table and form
	echo '<!-- END RenderContentFtr() -->';
    }
    public function DoSidebar() {
	echo '<!-- DoSidebar() -->';
/* maybe we're not currently using this...
	$imgRoot = '';
	$strNotYet = '<img src="'.$imgRoot.'/tools/img/icons/nulbox.gif">';
	$strDone = '<img src="'.$imgRoot.'/tools/img/icons/chkbox.gif" title="done">';
	$strDoNow = '<img src="'.$imgRoot.'/tools/img/icons/curbox.gif" title="do this now">';

	$pfx_step2 = $strNotYet;
	$pfx_step3 = $strNotYet;
	if ($did_step1) {
		$pfx_step1 = $strDone;
		if ($did_step2) {
			$pfx_step2 = $strDone;
			$pfx_step3 = $strDoNow;
		} else {
			$pfx_step2 = $strDoNow;
		}	
	} else {
		$pfx_step1 = $strDoNow;
	}

	$out = <<<__END__
	<script language="JavaScript1.2" src="{$imgRoot}/tools/js/lib.js"></script>
	<table class=border align=left background="{$imgRoot}/tools/img/bg/lines/" cellpadding=5><tr><td>
	<table background="" bgcolor=000000  cellpadding=3 bgcolor=000000><tr><td>
	<table background="" cellpadding=5 bgcolor=ffffff><tr><td>

	<span class=menu-text>
	<span class=menu-dept>Steps</span>

	<br>-- $pfx_step1 1. enter <b>shipping</b>
	<br>-- $pfx_step2 2. enter <b>payment</b>
	<br>-- $pfx_step3 3. print <b>receipt</b>

	<p><span class=menu-dept>Information</span>
	$linkToShipping
	$linkToPrivacy
	$linkToSecurity
	$linkToReturns
	<p><span class=menu-dept>Other Choices</span>
	<br>-- <a href="http://vbz.net/cart/?action=delcart"><b>clear &amp; restart</b> your order</a>
	<br>-- <a href="http://vbz.net/about/contact/email/"><b>send</b> us a <b>message</b></a>

	</td></tr></table>
	</td></tr></table>
	</td></tr></table>
__END__;
	echo $out;
*/
    }
    /*==========
    // cart-to-order conversion //
    */
    /*----
      ACTION: Receive the order:
	* convert the cart data to an order record
	* send confirmation email
	* display order receipt page
    */
    public function ReceiveOrder() {
// convert the cart to an order

	$objOrd = $this->MakeOrder();

	$arVars = $objOrd->TemplateVars();		// fetch variables for email
	$arHdrs = $objOrd->EmailParams($arVars);	// fill in email template with variables

// email the receipt, if email address is available
    // do this before displaying the receipt -- if the customer sees the receipt, the email is sent
	// args: $iReally, $iSendToSelf, $iSendToCust, $iAddrSelf, $iAddrCust, $iSubject, $iMessage
	$arEmail = array(
	  'to-self'	=> TRUE,
	  'to-cust'	=> TRUE,
	  'addr-self'	=> $arHdrs['addr.self'],
	  'addr-cust'	=> $arHdrs['addr.cust'],
	  'subject'	=> $arHdrs['subj'],
	  'message'	=> $arHdrs['msg.body']
	  );

	$objOrd->EmailConfirm(TRUE,$arEmail,$objOrd->Log());
	$objOrd->ShowReceipt();
    }
    /*----
      ACTION: Create an order from this cart
      HISTORY:
	2011-03-27 fixed bug which was preventing order number from being written to cart.
	  Looking at the cart data, this bug apparently happened on 2010-10-28.
    */
    protected function MakeOrder() {
	$objCart = $this->CartObj();

	assert('is_object($objCart);');
	assert('$objCart->ID > 0;');

	$objOrders = $this->Data()->Orders();
	$idOrd = $objCart->Value('ID_Order');
	if ($idOrd > 0) {
	    // this may or may not be a problem, but it's a bit unexpected; log it:
	    $arEv = array(
	      'code'	=> 'UNX',
	      'descr'	=> 'ID_Order already set in cart',
	      'params'	=> '\cart='.$objCart->KeyValue().'\ord='.$idOrd,
	      'where'	=> __METHOD__,
	      );
	    $objCart->StartEvent($arEv);
	    $idOrd = $objCart->ID_Order;
	    $this->objCart->Update(array('WhenUpdated'=>'NOW()'));
	} else {
	    $idOrd = $objOrders->Create();
	    
	    $arEv = array(
	      'code'	=> 'ORD',
	      'descr'	=> 'assigning order to cart',
	      'params'	=> '\ord='.$idOrd,
	      'where'	=> __METHOD__
	      );
	    $objCart->StartEvent($arEv);
	    $arUpd = array(
	      'WhenOrdered'	=> 'NOW()',
	      'ID_Order'	=> $idOrd
	      );
	    $objCart->Update($arUpd);
	    $objCart->FinishEvent();

	    $objCart->Value('ID_Order',$idOrd);
	}
	return $objOrders->CopyCart($idOrd,$objCart);	// return new order object
    }
    /*==========
    // different pages //
    */
    public function RenderCart() {
	unset($this->strMissing);
	if ($this->CartObj()->HasLines()) {
	    $out = '<table><tr><td colspan=2>';
	    $out .= $this->CartObj()->RenderCore(TRUE);
	    $out .= '</td></tr></table>';
	    return $out;
	} else {
	    return 'No items in cart!';
	    // TO DO: log this as a critical error - how did the user get to the checkout with no items?
	}
    }
    private function RenderForm_IsCard_ckbox($iOn) {
	$htIsCardVal = $iOn?' checked':'';
	$out = 
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_IS_CARD
	  .'" type=checkbox'
	  .$htIsCardVal
	  .'>The above &uarr; is <b>also my credit card billing address</b>.</td></tr>';
	return $out;
    }
    private function RenderForm_ToSelf_ckbox($iOn) {
	$htToSelfVal = $iOn?' checked':'';
	$out =
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_TO_SELF
	  .'" type=checkbox'
	  .$htToSelfVal
	  .'>I am <b>shipping this to myself</b> (use same contact info for recipient)</td></tr>';
	return $out;
    }
    private function RenderForm_IsCard_button($iOn) {
	if ($iOn) {
	    $txtIsCard = 'Enter Different Address for Card';
	} else {
	    $txtIsCard = 'Use Shipping Address for Card';
	}

	$out = 
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_IS_CARD
	  .'" type=submit value="'
	  .$txtIsCard
	  .'"></td></tr>';
	return $out;
    }
    private function RenderForm_ToSelf_button($iOn) {
	if ($iOn) {
	    $txtToSelf = 'Shipping to Someone Else';
	} else {
	    $txtToSelf = 'Shipping To Myself';
	}

	$out =
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_TO_SELF
	  .'" type=submit value="'
	  .$txtToSelf
	  .'"></td></tr>';
	return $out;
    }
    public function RenderShipping() {

	$objCartData = $this->CartData();
	if (!$objCartData->IsLoaded()) {
	    throw new exception('Internal error: cart data not loaded.');
	}

	$hrefForShipping = '<a href="'.KWP_WIKI.'Shipping_Policies">';

// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;

	$htIsCard = $this->RenderForm_IsCard_ckbox($objCartData->ShipToCard());
	$htToSelf = $this->RenderForm_ToSelf_ckbox($objCartData->ShipToSelf());
/*
	$htIsCardVal = ($objCartData->ShipToCard())?' checked':'';
	$htToSelfVal = ($objCartData->ShipToSelf())?' checked':'';

	$htIsCard = 
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_IS_CARD
	  .'" type=checkbox'
	  .$htIsCardVal
	  .'>The above &uarr; is <b>also my credit card billing address</b>.</td></tr>';
	$htToSelf =
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_TO_SELF
	  .'" type=checkbox'
	  .$htToSelfVal
	  .'>I am <b>shipping this to myself</b> (use same contact info for recipient)</td></tr>';
*/

	$strCustShipMsg = $this->CartData()->ShipMessage();

	$this->htmlBeforeAddress = NULL;
	$this->htmlAfterAddress = $htIsCard;
//	$this->htmlBeforeContact = $htToSelf;

	$this->doFixedCard = FALSE;
	$this->doFixedSelf = FALSE;
	$this->doFixedName = FALSE;

	$out = '<tr><td colspan=2 class=section-title>Ship-to information:</td></tr>';

	$objAddrShip = $objCartData->ShipObj(FALSE);

	$out .= $this->RenderAddress($objAddrShip);

	$out .= $htToSelf; 

	$out .= <<<__END__
<tr><td colspan=2 align=left>
	<font color=#880000>
	<b>Note:</b> If you need any part of your order by a particular date, <b>please tell us</b> in the space below.</font>
	See our {$hrefForShipping}shipping policies</a> for details.
	</td></tr>
<tr><td align=right valign=top>
	Special Instructions:<br>
	</td>
	<td><textarea name="$ksShipMsg" cols=50 rows=5>$strCustShipMsg</textarea></td>
	</tr>
__END__;
	return $out;
    }
    public function RenderBilling() {
	$objCartData = $this->CartData();

// copy any needed constants over to variables for parsing:
	$ksfCustCardNum = KSF_CUST_CARD_NUM;
	$ksfCustCardExp = KSF_CUST_CARD_EXP;

	$custCardNum = $this->CartData()->CardNum();
	$custCardExp = $this->CartData()->CardExp();

	$out = <<<__END__
<tr><td colspan=2 class=section-title>Payment information:</td></tr>
<tr><td align=right valign=middle>We accept:</td>
	<td>
	<img align=absmiddle src="/tools/img/cards/logo_ccVisa.gif" title="Visa">
	<img align=absmiddle src="/tools/img/cards/logo_ccMC.gif" title="MasterCard">
	<img align=absmiddle src="/tools/img/cards/logo_ccAmex.gif" title="American Express">
	<img align=absmiddle src="/tools/img/cards/logo_ccDiscover.gif" title="Discover / Novus">
	</td></tr>
<tr><td align=right valign=middle>Card Number:</td>
	<td><input id="cardnum" name="$ksfCustCardNum" value="$custCardNum" size=24>
	Expires: <input id="cardexp" name="$ksfCustCardExp" value="$custCardExp" size=6> (mm/yy)
	</td></tr>
<tr><td colspan=2><span class=note><b>Tip</b>: It's okay to use dashes or spaces in the card number - reduces typing errors!</span></td></tr>
__END__;

	$custShipIsCard = $this->CartData()->ShipToCard();
	$custShipToSelf = $this->CartData()->ShipToSelf();

	$htIsCard = $this->RenderForm_IsCard_button($custShipIsCard);
	$htToSelf = $this->RenderForm_ToSelf_button($custShipToSelf);

/*
	// get shipping address object
	if ($custShipIsCard) {
	    $objAddr = $this->CartData()->ShipObj();
//	    $txtIsCard = 'Enter Different Address for Card';
	} else {
	    $objAddr = $this->CartData()->CustObj();
//	    $txtIsCard = 'Use Shipping Address for Card';
	}
*/
	// get contact (customer) object
/*
	if ($custShipToSelf) {
	    $objCont = $this->CartData()->DestObj();	// use shipping destination for billing address
	} else {
	    $objCont = $this->CartData()->CustObj();	// customer has separate billing address
	}
*/
	$objCont = $this->CartData()->BillObj();
/*
	$htIsCard = 
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_IS_CARD
	  .'" type=submit value="'
	  .$txtIsCard
	  .'"></td></tr>';
	$htToSelf =
	  '<tr><td colspan=2><input name="'
	  .KSF_SHIP_TO_SELF
	  .'" type=submit value="'
	  .$txtToSelf
	  .'"></td></tr>';
*/

	$this->htmlBeforeAddress = $htIsCard;
	$this->htmlBeforeContact = $htToSelf;
	$this->msgAfterAddr = '<span class=note><font color=ff0000><b>Note</b></font>: please check your most recent credit card statement for exact address!</span>';
//	$this->useButtons = TRUE;
	$this->doFixedCard = $custShipIsCard;
	$this->doFixedSelf = $custShipToSelf;
	$this->doFixedName = FALSE;

	$out .= $this->RenderAddress($objCont);

	$out .= '</tr>';
	return $out;
    }
    /*----
      ACTION: Render the "confirm this order" page (last page before cart is finalized into order)
    */
    public function RenderConfirm() {
	$out = $this->RenderOrder(TRUE);
	return $out;
    }
// PAGE DISPLAY ELEMENTS //
// -- common display functions
    /*-----
      ACTION: Display what will be going into the order
	Based on cart contents, not order record.
	This is formatted to fit within the checkout sequence.
      NOTE: Don't use this to display order confirmation.
	Use the order object so we only show what really
	went into the order record.
    */
    public function RenderOrder($iEditable) {
	$objCart = $this->CartObj();

	assert('is_object($objCart)');
	assert('$objCart->ID != 0; /* ID='.$objCart->ID.' */');

	$objCD = $this->CartData();

	$isShipCard = $objCD->ShipToCard();
	$isShipSelf = $objCD->ShipToSelf();
/*
// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;
	$ksfCustCardNum = KSF_CUST_CARD_NUM;
	$ksfCustCardExp = KSF_CUST_CARD_EXP;

// get non-address field data:
*/
	$strCustShipMsg = $objCD->ShipMessage();
	$custCardNum = $objCD->CardNum();
	$custCardExp = $objCD->CardExp();

	$out = '<!-- CART ID='.$objCart->ID.' -->';
	$htLink = '';

	if ($iEditable) {
	    $htLink = $this->HtmlEditLink(KSQ_PAGE_CART);
	}
	$out .= '<tr><td class=section-title>ITEMS ORDERED:</td><td class=section-title align=right>'.$htLink.'</td></tr>';

	$out .= '<tr><td colspan=2><table>';
	$out .= $objCart->RenderConfirm();
	$out .= '</table></td></tr>';

	if ($iEditable) {
	    $htLink = $this->HtmlEditLink(KSQ_PAGE_SHIP);
	}
	$out .= '<tr><td class=section-title>SHIP TO:</td><td class=section-title align=right>'.$htLink.'</td></tr>';

	$this->doFixedCard = TRUE;
	$this->doFixedSelf = TRUE;
	$this->doFixedName = TRUE;
	$this->htmlBeforeAddress = '';
	$this->htmlBeforeContact = '';

	$out .= $this->RenderAddress($objCD->ShipObj(FALSE));

	if ($iEditable) {
	    $htLink = $this->HtmlEditLink(KSQ_PAGE_PAY);
	}
	$out .= <<<__END__
<tr><td align=right valign=top>
	Special Instructions:<br>
	</td>
	<td>$strCustShipMsg</td>
	</tr>
<tr><td class=section-title>PAYMENT:</td><td class=section-title align=right>$htLink</td></tr>
<tr><td align=right valign=middle>Card Number:</td>
	<td><b>$custCardNum</b>
	- Expires: <b>$custCardExp</b>
	</td></tr>
__END__;
// if card address is different from shipping, then show it too:
// if not shipping to self, then show recipient's phone and/or email:
	if ($isShipCard) {
	    $this->strInsteadOfAddr = 'Credit card address <b>same as shipping address</b>';
	}
	if ($isShipSelf) {
	    $this->strInsteadOfCont = 'Recipient contact information <b>same as buyer\'s -- shipping to self</b>';
	}
	// TODO 2012-05-21: this probably won't look right, and will need fixing
	//	also, are strInsteadOf* strings ^ used in confirmation?
	$out .= $this->RenderAddress($objCD->CustObj());

	if ($iEditable) {
/* using "btn-go-pay" is a kluge. I'm trying to go back to the payment page,
  but "btn-go-prev" goes too far. This goes back to the shipping info page,
  which is acceptable for now.
*/
	    $out .= <<<__END__
<tr><td colspan=2 align=center bgcolor=ffffff class=section-title>
<input type=submit name="btn-go-pay" value="&lt;&lt; Make Changes">
<input type=submit name="btn-go-order" value="Place the Order!">
</td></tr>
__END__;
	}
	$out .= '</table></form>';

	return $out;
    }
    /*----
    ARGUMENTS:
      $iAddr
    PROPERTY INPUTS:
    RULES:
      Pages displayed:
	On page 1 (shipping), all fields are editable.
	On page 2 (payment), some fields may be read-only depending on which "same as" flags the user has checked
	On page 3 (confirmation), all fields are read-only
    */
    protected function RenderAddress(clsPerson $iAddr) {
	$objCart = $this->CartObj();
	$objZone = $objCart->ShipZoneObj();
	assert('is_object($objCart)');
	assert('is_object($objZone)');
	assert('is_object($iAddr)');

	$out = '';

	if (isset($this->strInsteadOfAddr)) {
	    $out .= '<tr><td colspan=2>'.$this->strInsteadOfAddr.'</td></tr>';
	} else {
	    $strStateLabel = $objZone->StateLabel();	// "province" etc.
	    if ($objZone->hasState()) {
		$htStateAfter = '';
		$lenStateInput = 3;
		// later, we'll also include how many chars the actual abbr is and use this to say "(use n-letter abbreviation)"
	    } else {
		$htStateAfter = ' (if needed)';
		$lenStateInput = 10;
	    }

	    $isCountryFixed = FALSE;
	    if (empty($strCountry)) {
		if (!$objZone->isDomestic()) {
		    // this code may need some checking
		    $idxCountry = $iAddr->CountryNode()->DataType();
		    $this->DataItem($idxCountry,$objZone->Country());
		}
		$isCountryFixed = !empty($strCountry);
	    }
/*
	    if ($this->doFixedCard) {
		// make country fixed, regardless of country code, if it has been set earlier:
		$isCountryFixed = TRUE;
	    }
*/

	    $hrefForSpam = '<a href="'.KWP_WIKI.'Anti-Spam_Policy">';
	    $arOpts = array(
	      'ht.before.addr'	=> $this->htmlBeforeAddress,
	      'ht.after.addr'	=> nz($this->htmlAfterAddress),
	      'ht.after.email'	=> $hrefForSpam.'anti-spam policy</a>',
	      'ht.ship.combo'	=> $objZone->ComboBox(),
	      'ht.after.state'	=> $htStateAfter,
	      'str.zip.label'	=> $objZone->PostalCodeName(),	// US='Zip Code', otherwise="postal code"
	      'str.state.label'	=> $strStateLabel,
	      'len.state.inp'	=> $lenStateInput,
	      'do.fixed.all'	=> $this->doFixedCard,	// TRUE = disable editing
	      'do.fixed.name'	=> $this->doFixedName,
	      'do.fixed.ctry'	=> $this->doFixedCountry,
	      );
/*
	    $iAddr->htmlBeforeAddress = $this->htmlBeforeAddress;
	    $iAddr->doFixed = $this->doFixedCard;
	    $iAddr->htShipCombo = $objZone->ComboBox();
	    $iAddr->doFixedName = $this->doFixedName;
*/
	    $out .= $iAddr->Render($this,$arOpts);
	}

	if (isset($this->msgAfterAddr)) {
	    $out .= '<td colspan=2>'.$this->msgAfterAddr.'</td>';
	}

	return $out;
    }
    protected function RenderAddress_OLD($iAddr,$iContact) {

	$objCart = $this->CartObj();
	$objZone = $objCart->ShipZoneObj();
	assert('is_object($objCart)');
	assert('is_object($objZone)');
	assert('is_object($iAddr)');
	assert('is_object($iContact)');

	$out = '';

	if (isset($this->strInsteadOfAddr)) {
	    $out .= '<tr><td colspan=2>'.$this->strInsteadOfAddr.'</td></tr>';
	} else {
	    $iAddr->strStateLabel = $objZone->StateLabel();	// "province" etc.
	    if ($objZone->hasState()) {
		$iAddr->strStatePost = '';
		$iAddr->lenStateField = 3;
	    } else {
		$iAddr->strStatePost = ' (if needed)';
		$iAddr->lenStateField = 10;
	    }

	    $isCountryFixed = FALSE;
	    if (empty($strCountry)) {
		if (!$objZone->isDomestic()) {
		    // this code may need some checking
		    $idxCountry = $iAddr->CountryNode()->DataType();
		    $this->DataItem($idxCountry,$objZone->Country());
		}
		$isCountryFixed = !empty($strCountry);
	    }
	    if ($this->doFixedCard) {
		// make country fixed, regardless of country code, if it has been set earlier:
		$isCountryFixed = TRUE;
	    }
	    $iAddr->doFixedCountry = $isCountryFixed;

	    // TO DO: change this to "postal code" outside US
	    $iAddr->strZipLabel = $objZone->PostalCodeName();	// US='Zip Code', otherwise="postal code"

	    $hrefForSpam = '<a href="'.KWP_WIKI.'Anti-Spam_Policy">';

	    $iAddr->htmlBeforeAddress = $this->htmlBeforeAddress;
	    $iAddr->doFixed = $this->doFixedCard;
	    $iAddr->htShipCombo = $objZone->ComboBox();
	    $iAddr->doFixedName = $this->doFixedName;

	    $out .= $iAddr->Render($this);
	}

	if (isset($this->msgAfterAddr)) {
	    $out .= '<td colspan=2>'.$this->msgAfterAddr.'</td>';
	}

	if (isset($this->strInsteadOfCont)) {
	    $out .= '<tr><td colspan=2>'.$this->strInsteadOfCont.'</td></tr>';
	} else {
	    $out .= $this->htmlBeforeContact;

	    $iContact->doFixed = $this->doFixedSelf;
	    $out .= $iContact->Render($this->CartObj());
	}

	return $out;
    }

/*=====
 SECTION: input/data management stuff
 TO DO: Shouldn't all the DetailObj stuff be in the ShopCart class?
 NOTES: This code could be optimized for more speed, as it creates objects which are sometimes
  discarded without being used, but I have chosen to optimize instead for clarity and maintainability.
*/
/*===================*\
 * FORM/DATA METHODS *
\*===================*/
    public function GetFormItem($iName) {
	if (isset($_POST[$iName])) {
	    return $_POST[$iName];
	} else {
	    $this->objCart->LogEvent('!FLD','Missing form field: '.$iName);
	    return NULL;
	}
    }
    public function CartData() {
	return $this->objCart->CartData();
    }
/*
    public function DataItem($iType,$iVal=NULL,$iForce=FALSE) {
	assert('is_numeric($iType)');
	return $this->objCart->DataItem($iType,$iVal,$iForce);
    }
    public function DataItem_HTML($iType) {
	return htmlspecialchars($this->DataItem($iType));
    }
*/
    /*
      RETURNS: not sure anymore; probably HTML to be displayed
	with message identifying which piece(s) of information were mis-entered
      REQUIRES: Input needs to have been parsed so we know what page's data we're capturing
    */
    public function CapturePage() {
	if (empty($this->pgData)) {
	    $out = '';
	} else {
	    $this->objCart->LogEvent('save','saving data from page '.$this->pgData);
	    switch ($this->pgData) {
	      case KSQ_PAGE_CART:	// shopping cart
		$out = $this->CaptureCart();
		$this->formSeqData = 0;
		break;
	      case KSQ_PAGE_SHIP:	// shipping information
		$out = $this->CaptureShipping();
		$this->formSeqData = 1;
		break;
	      case KSQ_PAGE_PAY:	// billing information
		$out = $this->CaptureBilling();
		$this->formSeqData = 2;
		break;
	      default:
		// more likely to be a hacking attempt than an internal error:
		$out = 'Cannot save data from unknown page: ['.$this->pgData.']';
		$this->LogEvent('ckout.capture','pgData='.$this->pgData,'unknown form ','FUNK',FALSE,TRUE);
	    }
	}
	switch ($this->pgShow) {
	  case KSQ_PAGE_CART:	$formSeqShow = 0;	break;
	  case KSQ_PAGE_SHIP:	$formSeqShow = 1;	break;
	  case KSQ_PAGE_PAY:	$formSeqShow = 2;	break;
	  case KSQ_PAGE_CONF:	$formSeqShow = 3;	break;
	  case KSQ_PAGE_RCPT:	$formSeqShow = 4;	break;
	  default: $formSeqShow = 0;	break;
	}
	if ($this->FieldsMissing()) {
	    // don't advance until all required fields are entered
	    // ok to back up, however
	    if ($formSeqShow > $this->formSeqData) {
		$this->pgShow = $this->pgData;
	    }
	}
	return $out;
    }
    /*----
      DEPRECATED -- use clsShopCart->GetDetailObjs() instead
    */
/*
    public function GetDetailObjs() {
	$objAddrShip = $this->AddrShipObj();
	$objAddrCard = $this->AddrCardObj();
	$objContDest = $this->ContDestObj();
	$objContCust = $this->ContCustObj();

	$this->objCust = new clsPerson('cust','buyer');
	$this->objShip = new clsPerson('ship','recipient');

	$arFields = array(
	  'num'		=> new clsCartField($this, KSI_CUST_CARD_NUM,	KSF_CUST_CARD_NUM),
	  'exp'		=> new clsCartField($this, KSI_CUST_CARD_EXP,	KSF_CUST_CARD_EXP)
	  );
	$objPayment = new clsPayment($arFields);

	$this->objCust->Node('payment', $objPayment);	// the buyer always has the credit card
	$this->objShip->Node('contact', $objContDest);	// shipping information is always specified

	$objContDest->Addr = $objAddrShip;	// shipping address

	$this->custShipIsCard	= $this->DataItem(KSI_SHIP_IS_CARD);
	$this->custShipToSelf	= $this->DataItem(KSI_SHIP_TO_SELF);

	if ($this->custShipIsCard) {
	    $objPayment->Node('addr', $objAddrShip);	// use shipping address for card
	} else {
	    $objPayment->Node('addr', $objAddrCard);	// use separate address for card
	}

	$this->objShip->Node('name', $objAddrShip->Name());

	if ($this->custShipToSelf) {
	    // don't use separate person data; re-use buyer contact info plus shipping address
	    $this->objShip->Node('payment', $objPayment);	// the only buyer field the recipient doesn't have
	    $this->objCust = $this->objShip;
	    //$objContDest->Node('addr', $objAddrShip);			// shipping address
	    $this->objShip->Node('contact', $objContDest);
	    $objContDest->Node('addr', $objAddrShip);
	} else {
	    $this->objShip->Node('contact', $objContDest);
	    $this->objCust->Node('contact', $objContCust);
	    $this->objCust->Node('name', $objAddrCard->Name());
	}
    }
*/
/* 2012-05-19 who uses this?
    public function AddrShip() {
    // REQUIRES: GetDetailObjs() must be called first
//	return $this->objAddrShip;
//	return $this->objShip->Contact->Addr;	// commented 2010-02-18
	return $this->AddrShipObj();	// trying this 2010-02-18
    }
*/
    public function AddrCard() {
    // REQUIRES: GetDetailObjs() must be called first
/*
	if ($this->custShipIsCard) {
	    return $this->objAddrShip;
	} else {
	    return $this->objAddrCard;
	}
*/
//	return $this->objCust->Payment->Addr;	// commented 2010-02-18
	return $this->AddrCardObj();
    }
/*
    public function WhoCust() {
	return $this->objCust;
    }
    public function WhoShip() {
	return $this->objShip;
    }
*/
/*
 SECTION: methods for capturing form data
*/
    public function CaptureCart() {
	return $this->Cart()->CheckData();	// check for any cart data changed
    }
    /*----
      ACTION: Receive user form input, and update the database
    */
    public function CaptureShipping() {
	$objCD = $this->CartData();
	$out = $objCD->CaptureData();

	// TODO: somehow we need to pull out specific fields to check

/*
	$objAddr = $this->CartData()->ShipObj(FALSE);
	$objAddr->Capture($this);
*/
	$objShipZone = $this->Cart()->ShipZoneObj();

	$custName	= $objCD->FormValue(KSI_ADDR_SHIP_NAME);
	$custStreet	= $objCD->FormValue(KSI_ADDR_SHIP_STREET);
	$custState	= $objCD->FormValue(KSI_ADDR_SHIP_STATE);
	$custCity	= $objCD->FormValue(KSI_ADDR_SHIP_CITY);
	$custCountry	= $objCD->FormValue(KSI_ADDR_SHIP_COUNTRY);
	$custEmail	= $objCD->FormValue(KSI_CUST_SHIP_EMAIL);

/*
	$shipZone	= $objCD->FormValue(KSF_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	$custShipToSelf	= $objCD->FormValue(KSF_SHIP_TO_SELF);
	$custShipIsCard	= $objCD->FormValue(KSF_SHIP_IS_CARD);
	$custZip	= $this->GetFormItem(KSF_ADDR_SHIP_ZIP);
	$custPhone	= $this->GetFormItem(KSF_CUST_SHIP_PHONE);
	$custMessage	= $this->GetFormItem(KSF_SHIP_MESSAGE);

	$objCD = $this->CartData();

	$objCD->ShipZone($shipZone);
	$objCD->ShipAddrName($custName);
	$objCD->ShipAddrStreet($custStreet);
	$objCD->ShipAddrTown($custCity);
	$objCD->ShipAddrState($custState);
	$objCD->ShipAddrZip($custZip);
	$objCD->ShipAddrCountry($custCountry);

	$objCD->ShipToSelf($custShipToSelf);
	$objCD->ShipToCard($custShipIsCard);
	$objCD->ShipEmail($custEmail);
	$objCD->ShipPhone($custPhone);
	$objCD->ShipMessage($custMessage);
*/
	$this->CheckField('name',$custName);
	$this->CheckField('street address',$custStreet);
	$this->CheckField('city',$custCity);
	if (($custState == '') && ($objShipZone->hasState())) {
		$this->AddMissing($objShipZone->StateLabel());
	}
	if (!$objShipZone->isDomestic()) {
	    $this->CheckField('country',$custCountry);
	}
	if (!is_null($custEmail)) {	// if we received a value...
	    $this->CheckField('email',$custEmail);	// ...make sure it's not blank
	}
    }
    public function CaptureBilling() {
	$objCD = $this->CartData();
	$out = $objCD->CaptureData();

	// TODO: make sure all necessary fields were filled in

/*
	$objAddr = $this->CartData()->CustObj();
	$objAddr->Capture($this);
	$objPay = $this->CartData()->PayObj();
	$objPay->Capture($this);
*/
/*
	$this->custShipToSelf	= $this->DataItem(KSI_SHIP_TO_SELF);
	$this->custShipIsCard	= $this->DataItem(KSI_SHIP_IS_CARD);

	$custCardNum	= $this->GetFormItem(KSF_CUST_CARD_NUM);
	$custCardExp	= $this->GetFormItem(KSF_CUST_CARD_EXP);

	if (!$this->custShipIsCard) {
	    $custCardName	= $this->GetFormItem(KSF_CUST_CARD_NAME);
	    $custCardStreet	= $this->GetFormItem(KSF_CUST_CARD_STREET);
	    $custCardCity	= $this->GetFormItem(KSF_CUST_CARD_CITY);
	    $custCardState	= $this->GetFormItem(KSF_CUST_CARD_STATE);
	    $custCardZip	= $this->GetFormItem(KSF_CUST_CARD_ZIP);
	    $custCardCountry	= $this->GetFormItem(KSF_CUST_CARD_COUNTRY);

	    # save current values
	    $this->DataItem(KSI_CUST_CARD_NAME,$custCardName);
	    $this->DataItem(KSI_CUST_CARD_STREET,$custCardStreet);
	    $this->DataItem(KSI_CUST_CARD_CITY,$custCardCity);
	    $this->DataItem(KSI_CUST_CARD_STATE,$custCardState);
	    $this->DataItem(KSI_CUST_CARD_ZIP,$custCardZip);
	    $this->DataItem(KSI_CUST_CARD_COUNTRY,$custCardCountry);

	    # check for missing data
	    $this->CheckField("cardholder's name",$custCardName);
	    $this->CheckField("card's billing address",$custCardStreet);
	    $this->CheckField("card's billing address - city",$custCardCity);
	}
	
	if (!$this->custShipToSelf) {
	    $custEmail	= $this->GetFormItem(KSF_CUST_PAY_EMAIL);
	    $custPhone	= $this->GetFormItem(KSF_CUST_PAY_PHONE);

	    # save current values
	    $this->DataItem(KSI_CUST_PAY_EMAIL,$custEmail);
	    $this->DataItem(KSI_CUST_PAY_PHONE,$custPhone);
	}
	$custCheckNum	= $this->GetFormItem(KSF_CUST_CHECK_NUM);
*/
/*
# handle unconditional fields:
	# save current values
	$this->CartData()->CardNum($custCardNum);
	$this->CartData()->CardExp($custCardExp);
	$this->CartData()->CheckNum($custCheckNum);

	$this->CheckField("credit card number",$custCardNum);
	$this->CheckField("credit card expiration date",$custCardExp);
	# save list of missing fields
//	$this->DataItem(KSI_CUST_MISSING,$this->strMissing,TRUE);

	// TODO: where do these get used?
	$this->custCardNum	= $custCardNum;
	$this->custCardExp	= $custCardExp;
	$this->custCheckNum	= $custCheckNum;
*/
    }
// 2010-09-12 These may not be needed anymore.
// 2010-10-17 Yes, they are (at least AddrShipObj()) 
// 2011-11-27 time to untangle this stuff
    /*----
      USED BY: RenderShipping() line 264
    */
/*
    public function AddrShipObj() {
	return $this->objCart->AddrShipObj();
    }
    public function AddrCardObj() {
	return $this->objCart->AddrCardObj();
    }
    public function ContDestObj() {
	return $this->objCart->ContDestObj();
    }
    public function ContCustObj() {
	return $this->objCart->ContCustObj();
    }
*/
//-----
    protected function HtmlEditLink($iPage,$iText='edit',$iPfx='[',$iSfx=']') {
	$out = $iPfx.'<a href="?'.KSQ_ARG_PAGE_DEST.'='.$iPage.'">'.$iText.'</a>'.$iSfx;
	return $out;
    }
    private function Order() {
	return $this->objCart->Order();
    }
//=== handling of missing fields
    public function AddMissing($iText) {
	if (!$this->FieldsMissing()) {
	    $this->strMissing = $iText;
	} else {
	    $this->strMissing .= ', '.$iText;
	}
    }
    public function CheckField($iText,$iValue) {
	if ($iValue == '') {
		$this->AddMissing($iText);
	}
    }
    public function FieldsMissing() {
	return isset($this->strMissing);
    }
}
