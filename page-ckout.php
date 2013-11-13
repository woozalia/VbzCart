<?php
/*
  FILE: ckout.php
  HISTORY:
    2010-02-21 split off from shop.php (via checkout/index.php)
    2010-12-24 Fixed call to Update() -- array is now required
    2013-02-20 mostly working, but "ship to card" and "ship to self" logic is becoming unmanageable.
      Going to gut this and significantly rework it as a single form.
    2013-04-12 ended up with two forms still, but somewhat simplified logic
*/

require_once('config-admin.php');
require_once('vbz-const-user.php');

/*=====
  CLASS: clsPageCkOut
  PURPOSE: subclass for generating checkout pages
*/
class clsPageCkout extends clsVbzPage_Admin {
    private $isLogin;	// this is an attempt to log in

    /*----
      ACTION: get cart object - throw exception if there isn't one
    */
    protected function CartObj_req() {
	if ($this->HasCart()) {
	    return $this->CartObj(TRUE);
	} else {
	    throw new exception('Cart is missing in checkout.');
	}
    }
    /*----
      OUTPUT:
	$inCkout: if TRUE, it's ok to use a cart whose order has been placed.
      INPUT:
	$pgData: The page whose data we're receiving (blank if there is no data)
	$pgShow: The page whose form we're wanting to display
    */
    public function TitleStr() {
	return 'Checkout';
    }
    protected function IsLoggedIn() {
	return $this->SessObj()->HasUser();
    }
    protected function ParseInput() {

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
	$this->isLogin = FALSE;	// default
	if (!$gotPgDest) {
	// destination page unknown, so calculate it from data/source page:
	    if (nz($_POST[KSF_USER_BTN_LOGIN])) {
		// this is a login attempt
		$this->isLogin = TRUE;
		$this->pgShow = $this->pgData;	// stay on same page
	    } elseif (nz($_POST['btn-go-prev'])) {
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
		  case KSQ_PAGE_CONF:
		    $this->pgShow = KSQ_PAGE_PAY;
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
		  case KSQ_PAGE_CONF:
		    $this->pgShow = KSQ_PAGE_RCPT;
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

//	$this->GetObjects();
	if (!$this->HasCart()) {
	    // if cart is not set, don't go past cart display
	    $this->pgShow = KSQ_PAGE_CART;
	    // this could happen if the user loads the checkout URL directly without a cart -- so log the error, but don't raise an exception
	    $this->Data()->LogEvent('ckout.parsequery','pgShow='.$this->pgShow,'cart ID not set','!cart',TRUE,FALSE);
	    http_redirect(KWP_CKOUT_IF_NO_CART,'No cart set; returning to store.');
	}
	$this->CapturePage();

    }
    protected function HandleInput() {
	$this->strWikiPg	= '';
	$this->strSheet	= 'ckout';	// default

	$this->CartObj(FALSE)->LogEvent('page','showing page "'.$this->pgShow.'"');
	$this->formShow = $this->pgShow;
	$this->doNavBar = TRUE;
	$this->doBackBtn = TRUE;
	$this->doRefrBtn = FALSE;

	if ($this->isLogin) {
	    // try to log user in
	    $this->SessObj()->UserLogin($this->sUser,$this->sPass);
echo __FILE__.' line '.__LINE__.'<br>';
	}
    }
    /*----
      OUTPUT:
	$doBackBtn: if TRUE, show the BACK navigation button
	$doRefrBtn:
	$doNavBar:
    */
    public function DoContent() {
	// default options
	$this->doFixedCard = FALSE;
	$this->doFixedCountry = FALSE;	

	$out = NULL;
	$out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	switch ($this->CurPageKey()) {
	  case KSQ_PAGE_CART:	// shopping cart
	    $this->doBackBtn = FALSE;
	    $this->doRefrBtn = TRUE;
	    $out .= $this->RenderCart();
	    break;
	  case KSQ_PAGE_SHIP:	// shipping information
	    $out .= $this->RenderShipping();
	    $out .= $this->RenderPayType();
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
// The normal shopping cart does not specify a target within the checkout sequence
// ...so show the first page which follows the cart page:
	    $out .= $this->RenderShipping();
	}
	$out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	echo $out;
    }
    public function CurPageKey() {
	return $this->pgShow; 
    }
    protected function DoNavBar() {
	$oNav = $this->CreateNavBar();
	// call this after CreateNavBar so child classes can insert stuff first
	$sPage = $this->CurPageKey();
	$oNav->States($sPage,1,3);
	$oNav->Node($sPage)->State(2);
	echo $this->Skin()->RenderNavbar_H($oNav);
    }
    protected function CreateNavBar() {
	$oNav = new clsNavbar_flat();
	  $oi = new clsNavText($oNav,KSQ_PAGE_CART,'Cart');
	  $oi = new clsNavText($oNav,KSQ_PAGE_SHIP,'Shipping');
	  $oi = new clsNavText($oNav,KSQ_PAGE_PAY,'Payment');
	  $oi = new clsNavText($oNav,KSQ_PAGE_CONF,'Final Check');
	  $oi = new clsNavText($oNav,KSQ_PAGE_RCPT,'Receipt');
	$oNav->Decorate('','',' &rarr; ');
	$oNav->CSSClass('nav-item-past',1);
	$oNav->CSSClass('nav-item-active',2);
	$oNav->CSSClass('nav-item-todo',3);
//	$oNav->CSSClass(0,'nav-item');
//	$oNav->CSSClass(1,'nav-item-active');
	return $oNav;
    }
    /*
      RETURNS: not sure anymore; probably HTML to be displayed
	with message identifying which piece(s) of information were mis-entered
      REQUIRES: Input needs to have been parsed so we know what page's data we're capturing
    */
    public function CapturePage() {
	if (empty($this->pgData)) {
	    $out = '';
	} else {
	    $this->CartObj_req()->LogEvent('save','saving data from page '.$this->pgData);
	    switch ($this->pgData) {
	      case KSQ_PAGE_CART:	// shopping cart
		$out = $this->CaptureCart();
		$this->formSeqData = KI_SEQ_CART;	// 0
		break;
	      case KSQ_PAGE_SHIP:	// shipping information
		$out = $this->CaptureShipping();
		$this->formSeqData = KI_SEQ_SHIP;	// 1
		break;
	      case KSQ_PAGE_PAY:	// billing information
		$out = $this->CaptureBilling();
		$this->formSeqData = KI_SEQ_PAY;	// 2
		break;
	      case KSQ_PAGE_CONF:	// confirmation
		$out = NULL;	// no processing needed
		$this->formSeqData = KI_SEQ_CONF;	// 3
		break;
	      default:
		// more likely to be a hacking attempt than an internal error:
		$out = 'Cannot save data from unknown page: ['.$this->pgData.']';
		$this->Data()->LogEvent('ckout.capture','pgData='.$this->pgData,'unknown form ','UNKF',FALSE,TRUE);
	    }
	}
	switch ($this->pgShow) {
	  case KSQ_PAGE_CART:	$formSeqShow = KI_SEQ_CART;	break;
	  case KSQ_PAGE_SHIP:	$formSeqShow = KI_SEQ_SHIP;	break;
	  case KSQ_PAGE_PAY:	$formSeqShow = KI_SEQ_PAY;	break;
	  case KSQ_PAGE_CONF:	$formSeqShow = KI_SEQ_CONF;	break;
	  case KSQ_PAGE_RCPT:	$formSeqShow = KI_SEQ_RCPT;	break;
//	  default: $formSeqShow = 0;	break;
	  default: throw new exception('Does this ever happen?');
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
      PURPOSE: Render top part of {form and outer table, including <td>}
    */
    protected function DoContHdr() {
	echo "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";
	$urlSelf = $_SERVER["REQUEST_URI"];
	$arURI = explode('?',$urlSelf);
	$urlTarg = $arURI[0];	// just get the part before the query
	echo "\n<form method=post name=checkout action='$urlTarg'>";
	parent::DoContHdr();
	// this needs to go after the navbar:
	echo "\n<tr><td align=center>\n<table id=main><tr><td>";
	echo "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";
    }
    /*----
      PURPOSE: Stuff to close out the page contents
      ACTION: Close table row opened in RenderContentHdr(), display standard buttons, close outer table and form
    */
    protected function DoContFtr() {
	echo "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";
	$intColumns = 4;	// does this ever vary?

	if ($this->FieldsMissing() && ($this->pgShow == $this->pgData)) {
	    // NOTE: I've been unable to get the icon to align nicely with the text without using a table.
	    $htMissing = "<tr><td colspan=$intColumns>\n<table>\n<tr><td><img src=".'"'.KWP_ICON_ALERT
	      .'"></td><td valign=middle><span class=alert style="background: yellow"><b>Please fill in the following</b>: '
	      .$this->strMissing
	      ."</span></td></tr>\n</table>\n</td></tr>";
	} else {
	    $htMissing = '';
	}

	if ($this->doNavBar) {
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
	      .'<tr><td colspan='.$intColumns.' align=center bgcolor=ffffff class=section-title>'
	      .'<input type=hidden name="'.KSQ_ARG_PAGE_DATA.'" value="'.$this->pgShow.'">'
	      .$htBackBtn.$htRefrBtn
	      .'<input type=submit name="btn-go-next" value="Next &gt;">';
	} else {
	    $out = '';
	}

	//echo "\n</td></tr></table><!-- ".__LINE__." -->";
	echo $out;
	$oCart = $this->CartObj_req();
	$idSess = $this->SessObj()->KeyValue();
	$idCart = $oCart->KeyValue();
	$idOrd = $oCart->Value('ID_Order');
	$sOrd = ($idOrd == 0)?'':' Order ID: <b>'.$idOrd.'</b>';

	echo "\n</td></tr>";	// close row opened in DoContHdr()
	echo "\n</table>\n<!-- ".__FILE__." line ".__LINE__." -->";	// close outer table and form
	if (!$this->doNavBar) {
	    echo $this->Skin()->Render_HLine();
	}
	echo "\n<span style=\"color: grey; font-size: 10pt;\">Cart ID: <b>$idCart</b> Session ID: <b>$idSess</b>$sOrd</span>";
	echo "\n</td></tr></table></td></tr></table>\n<!-- ".__FILE__." line ".__LINE__." -->\n</form>\n";	// close outer table and form
	echo "\n<!-- -".__METHOD__."() -->\n";
    }
  /* ****
    SECTION: cart-to-order conversion
  */
    /*----
      ACTION: Receive the order:
	* convert the cart data to an order record
	* send confirmation email
	* display order receipt page
    */
    public function ReceiveOrder() {
	echo "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";
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
	echo "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";
    }
    /*----
      ACTION: Create and populate an order from this cart
      HISTORY:
	2011-03-27 fixed bug which was preventing order number from being written to cart.
	  Looking at the cart data, this bug apparently happened on 2010-10-28.
	2013-11-06 Most of the work was being pushed out to clsOrders::CopyCart(),
	  but this seems unnecessary so I'm pulling it back in here.
    */
    protected function MakeOrder() {
throw new exception('Does anything call this? 2013-11-11');
	$objCart = $this->CartObj_req();

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
	//return $objOrders->CopyCart($idOrd,$objCart);	// populate and return new order object
	$objOrd = $objOrders->GetItem($iOrdID);
	$objOrd->CopyCart($objCart);	// copy the actual data to the order record

// should this code be in $objOrd->CopyCart?
	// in session object, set Order ID and clear Cart ID
	// 2011-03-27 wrong. just set Order ID.
	$arUpd = array(
	  'ID_Order'	=> $iOrdID,
	  //'ID_Cart'	=> 'NULL'
	  );
	$objCart->Session()->Update($arUpd);
	// log the event
	$this->Engine()->LogEvent(
	  __METHOD__,
	  '|ord ID='.$iOrdID.'|cart ID='.$objCart->KeyValue(),
	  'Converted cart to order; SQL='.SQLValue($objCart->Session()->sqlExec),
	  'C>O',FALSE,FALSE);

	return $objOrd;	// this is used by the checkout process
    }
  /* ****
    SECTION: checkout pages

      * RenderCart() - display the shopping cart
      * RenderShipping() - user enters shipping info
	* RenderPayType() - subform for selecting payment method
      * RenderBilling() - user enters billing/payment info
      * RenderConfirm() - confirm all information entered
	* RenderOrder($iEditable)
      * ReceiveOrder() - convert cart fields to order record; send email, show receipt

  **** */
    public function RenderCart() {
	unset($this->strMissing);
	if ($this->CartObj_req()->HasLines()) {
	    $out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->";
	    $out .= "\n<table>";
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	    $out .= $this->CartObj_req()->RenderCore(TRUE);
	    $out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	    $out .= "\n</table>";
	    $out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";
	    return $out;
	} else {
	    return 'No items in cart!';
	    // TO DO: log this as a critical error - how did the user get to the checkout with no items?
	}
    }
   /*----
      ACTION: Render the form controls where user can enter shipping information
    */
    public function RenderShipping() {

	$objCartData = $this->CartData();
	if (!$objCartData->IsLoaded()) {
	    throw new exception('Internal error: cart data not loaded.');
	}

	$hrefForShipping = '<a href="'.KWP_WIKI.'Shipping_Policies">';

// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;

	$strCustShipMsg = $this->CartData()->ShipMsg();

	$this->htmlBeforeAddress = NULL;
	$this->htmlAfterAddress = NULL;
//	$this->htmlAfterAddress = $htIsCard;
//	$this->htmlBeforeContact = $htToSelf;

	$this->doFixedCard = FALSE;
	$this->doFixedSelf = FALSE;
	$this->doFixedName = FALSE;

	//$out = '<tr><td colspan=2 class=section-title>Ship-to information:</td></tr>';
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";
	$out .= $this->Skin()->RenderSectionHdr('Shipping information:');

	$objAddrShip = $objCartData->ShipObj(FALSE);

	$isUser = $this->IsLoggedIn();

	if ($isUser) {
	    // allow user to select from existing recipient profiles
	    $tCusts = $this->User()->CustRecs();
	    $out .= '<tr><td colspan=2><b>You can ship to an existing address:</b>'
	      .$tCusts->Render_DropDown()
	      .'<hr></td></tr>';
	} else {
	    // make it easy for user to log in
	    $out .= '<tr><td colspan=2><b>Shopped here before?</b>'
	      .' '.$this->Skin()->RenderLogin()
	      .' or <a href="'.KWP_LOGIN.'" title="login options: reset password, create account">more options</a>.'
	      .'<hr></td></tr>';
	}

	$out .= $this->RenderAddress($objAddrShip,array('do.ship.zone'=>TRUE));

//	$out .= $htToSelf; 

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

	$out .= $this->Skin()->RenderSectionFtr();
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";
	return $out;
    }
    /*----
      ACTION: Render the form which lets the user choose how to pay
    */
    public function RenderPayType() {
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->";
	$out .= $this->Skin()->RenderSectionHdr('Payment type:');

	$isShipCardSame = $this->CartData()->ShipToCard();
	$htChecked = $isShipCardSame?' checked':'';

	$out .= "\n<tr><td align=center>\n"
	  .$this->Skin()->RenderPaymentIcons()
	  ."<table><tr><td>"
	  .'<input name=payType value="'.KSF_PTYP_CARD_HERE.'" type=radio checked disabled> Visa / MasterCard / Discover / American Express - pay here'
	  .'<br>&emsp;<input name="'.KSF_SHIP_IS_CARD.'" type=checkbox value=1'.$htChecked.'>billing address is same as shipping address above'
	  ."</td></tr></table>\n"
	  ."<hr>More payment options will be available soon.\n"
	  ."</td></tr>";

	$out .= $this->Skin()->RenderSectionFtr();
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";
	return $out;
    }
    /*----
      ACTION: looks at "card is shipping" flag and copies shipping address to billing address
	if flag is set and billing is blank.
      RETURNS:
	TRUE if the two addresses match (after copying if necessary).
	FALSE if they don't match -- card address was not blank and didn't match
      USAGE: Should be called before displaying any payment page with a billing address,
	and (I think) whenever processing data from such a page. (Currently, this is only
	the "payment" page.)
      TODO: Should only copy over blank address IF user says card address is same as shipping
    */
    protected function ReconcileCardAndShppg() {
	$isCardAddrBlank = $this->CartData()->CardAddrBlank();
	$doesMatch = TRUE;
	if ($isCardAddrBlank) {
	    $isShipCardSame = $this->CartData()->ShipToCard();	// request to use shipping address for card billing address
	    if ($isShipCardSame) {
		// copy shipping address to card address
		$this->CartData()->CopyShipToCust();
	    } else {
		$doesMatch = FALSE;
	    }
	} else {
	    if ($this->CartData()->CardMatchesShip()) {
		// existing card address matches shipping address
	    } else {
		// clear the "use shipping address as card address" flag
		$this->CartData()->ShipToCard(FALSE);
		$doesMatch = FALSE;	 // not blank and doesn't match
	    }
	}
	$this->doesCartMatchShip = $doesMatch;
	return $doesMatch;
    }
/* NOTE TO SELF: The problem right now is that we need to make sure the shipping address gets SAVED
      to the db when it gets copied to billing.
      I'm starting off trying to make this happen by moving the copying-phase into the CaptureShipping stage.
      That means $doesMatch needs to be saved to a class member, because RenderBilling() needs to know the result.
*/
    public function RenderBilling() {
	$objCartData = $this->CartData();

// copy any needed constants over to variables for parsing:
	$ksfCustCardNum = KSF_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_PAY_CARD_EXP;
	$ksfCardIsShip = KSF_SHIP_IS_CARD;

	$custCardNum = $this->CartData()->CardNum();
	$custCardExp = $this->CartData()->CardExp();
	$isShipCardSame = $this->CartData()->ShipToCard();	// request to use shipping address for card billing address
	$doesShipCardMatch = $this->doesCartMatchShip;

	$out = "\n<!-- ".__METHOD__." in ".__FILE__." -->";
	$out .= $this->Skin()->RenderSectionHdr('Payment information:');

	$out .= <<<__END__
<input type=hidden name="$ksfCardIsShip" value="$isShipCardSame">
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

	$objCont = $this->CartData()->BillObj();

	$this->htmlBeforeAddress = NULL;
	$this->htmlBeforeContact = NULL;

	$this->msgAfterAddr = '<span class=note><font color=ff0000><b>Note</b></font>: please check your most recent credit card statement for exact address!</span>';
//	$this->useButtons = TRUE;
/*
	$this->doFixedCard = $custShipIsCard;
	$this->doFixedSelf = $custShipToSelf;
*/
	$this->doFixedCard = FALSE;
	$this->doFixedSelf = FALSE;
	$this->doFixedName = FALSE;

	$out .= $this->RenderAddress($objCont,array('do.ship.zone'=>FALSE));

	$out .= '</tr>';
	$out .= '</table>';	// close table opened by RenderSectionHdr()
	$out .= "\n<!-- /".__METHOD__." in ".__FILE__." -->";
	//$out .= self::RenderSectionFtr();
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
      INPUT:
	$iEditable: if TRUE, displays buttons to go back to earlier
	  screens for editing; does not actually edit in place.
      NOTE: Don't use this to display order confirmation.
	Use the order object so we only show what really
	went into the order record.
    */
    public function RenderOrder($iEditable) {
	$objCart = $this->CartObj_req();

	assert('is_object($objCart)');
	assert('$objCart->ID != 0; /* ID='.$objCart->ID.' */');

	$objCD = $this->CartData();

	$isShipCard = $objCD->ShipToCard();
	$isShipSelf = $objCD->ShipToSelf();
	$strCustShipMsg = $objCD->ShipMsg();
	$custCardNum = $objCD->CardNum();
	$custCardExp = $objCD->CardExp();
	$isShipCardReally = $this->ReconcileCardAndShppg();

	$idCart = $objCart->ID;
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";
	$out .= "\n<!-- CART ID=$idCart -->\n";
	$htLink = '';

	if ($iEditable) {
	    $htLink = $this->HtmlEditLink(KSQ_PAGE_CART);
	}
	$out .= '<tr><td class=section-title>ITEMS ORDERED:</td><td class=section-title align=right>'.$htLink.'</td></tr>';
	$out .= "\n<!-- ".__FILE__.' line '.__LINE__.' -->';

	$out .= "<tr><td colspan=2>\n<table>\n";
	$out .= $objCart->RenderConfirm();
	$out .= "\n</table>\n</td></tr>";

	if ($iEditable) {
	    $htLink = $this->HtmlEditLink(KSQ_PAGE_SHIP);
	}
	$out .= '<tr><td class=section-title>SHIP TO:</td><td class=section-title align=right>'.$htLink.'</td></tr>';

	$this->doFixedCard = TRUE;
	$this->doFixedSelf = TRUE;
	$this->doFixedName = TRUE;
	$this->htmlBeforeAddress = '';
	$this->htmlBeforeContact = '';

	$out .= $this->RenderAddress($objCD->ShipObj(FALSE),array('do.ship.zone'=>TRUE));

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
	if ($isShipCardReally) {
	    $this->strInsteadOfAddr = 'Credit card address <b>same as shipping address</b>';
	}
	if ($isShipSelf) {
	    $this->strInsteadOfCont = 'Recipient contact information <b>same as buyer\'s -- shipping to self</b>';
	}
	// TODO 2012-05-21: this probably won't look right, and will need fixing
	//	also, are strInsteadOf* strings ^ used in confirmation?
	$out .= $this->RenderAddress($objCD->CustObj(),array('do.ship.zone'=>FALSE));

	if ($iEditable) {
	    $sPgName = KSQ_ARG_PAGE_DATA;
	    $sPgShow = $this->pgShow;
	    $out .= <<<__END__
<tr><td colspan=2 align=center bgcolor=ffffff class=section-title>
<input type=hidden name="$sPgName" value="$sPgShow">
<input type=submit name="btn-go-prev" value="&lt;&lt; Make Changes">
<input type=submit name="btn-go-order" value="Place the Order!">
__END__;
	}
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    }
    /*----
    ARGUMENTS:
      $iAddr
    PROPERTY INPUTS:
    RULES - this documentation is obsolete:
      Pages displayed:
	On page 1 (shipping), all fields are editable.
	On page 2 (payment), some fields may be read-only depending on which "same as" flags the user has checked
	On page 3 (confirmation), all fields are read-only
    */
    protected function RenderAddress(clsPerson $iAddr, array $iOpts) {
	$objCart = $this->CartObj_req();
	$objZone = $objCart->ShipZoneObj();
	assert('is_object($objCart)');
	assert('is_object($objZone)');
	assert('is_object($iAddr)');

	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";

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
	    $arOpts = array_merge($iOpts,array(
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
	      ));

	    if ($this->IsLoggedIn()) {
		$out .= $this->Render_DropDown_Addresses();
	    }

	    $out .= $iAddr->Render($this,$arOpts);
	}

	//$out .= "\n<tr><!-- ".__FILE__.' line '.__LINE__.' -->';
	if (isset($this->msgAfterAddr)) {
	    $out .= '<td colspan=2>'.$this->msgAfterAddr.'</td>';
	}
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    }
    protected function Render_DropDown_Addresses() {
	$id = $this->App()->User()->KeyValue();
	
    }
/*=====
 SECTION: input/data management stuff
 TO DO: Shouldn't all the DetailObj stuff be in the ShopCart class?
 NOTES: This code could be optimized for more speed, as it creates objects which are sometimes
  discarded without being used, but I have chosen to optimize instead for clarity and maintainability.
 FUNCTIONS:
    * CaptureCart()
    * CaptureShipping()
    * CaptureBilling()
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
	return $this->CartObj_req()->CartData();
    }
    public function AddrCard() {
    // REQUIRES: GetDetailObjs() must be called first - (2013-04-12 not sure if this is still true)
	return $this->AddrCardObj();
    }
  /* ****
    SECTION: form-capturing methods
  */
    public function CaptureCart() {
	return $this->Data()->Carts()->CheckData();	// check for any cart data changed
    }
    /*----
      ACTION: Receive user form input, and update the database
    */
    public function CaptureShipping() {
	$objCD = $this->CartData();
	$out = $objCD->CaptureData($this->pgData);
	$this->ReconcileCardAndShppg();	// not sure if this puts the flag in the right place, but it's a start. TODO: verify.
	$objCD->SaveCart();	// update the db from form data

	$objShipZone = $this->CartObj_req()->ShipZoneObj();

	$custName	= $objCD->FormValue(KI_RECIP_NAME);
	$custStreet	= $objCD->FormValue(KI_RECIP_STREET);
	$custState	= $objCD->FormValue(KI_RECIP_STATE);
	$custCity	= $objCD->FormValue(KI_RECIP_CITY);
	$custCountry	= $objCD->FormValue(KI_RECIP_COUNTRY);
	$custEmail	= $objCD->FormValue(KI_RECIP_EMAIL);

	$shipZone	= $objCD->FormValue(KI_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	$custShipToSelf	= $objCD->FormValueNz(KI_RECIP_IS_BUYER);
	$custShipIsCard	= $objCD->FormValueNz(KSI_SHIP_IS_CARD);
	$custZip	= $this->GetFormItem(KSF_RECIP_ZIP);
	$custPhone	= $this->GetFormItem(KSF_RECIP_PHONE);
	$custMessage	= $this->GetFormItem(KSF_SHIP_MESSAGE);

	$objCD = $this->CartData();

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
	$out = $objCD->CaptureData($this->pgData);
	$objCD->SaveCart();	// update the db from form data

	$custCardNum	= $this->GetFormItem(KSF_PAY_CARD_NUM);
	$custCardExp	= $this->GetFormItem(KSF_PAY_CARD_EXP);

	# check for missing data
	$this->CheckField("card number",$custCardNum);
	$this->CheckField("expiration date",$custCardExp);

	if (!$this->CartData()->ShipToCard()) {
	    $custCardName	= $this->GetFormItem(KSF_PAY_CARD_NAME);
	    $custCardStreet	= $this->GetFormItem(KSF_PAY_CARD_STREET);
	    $custCardCity	= $this->GetFormItem(KSF_PAY_CARD_CITY);
	    $custCardState	= $this->GetFormItem(KSF_PAY_CARD_STATE);
	    $custCardZip	= $this->GetFormItem(KSF_PAY_CARD_ZIP);
	    $custCardCountry	= $this->GetFormItem(KSF_PAY_CARD_COUNTRY);

	    # check for missing data
	    $this->CheckField("cardholder's name",$custCardName);
	    $this->CheckField("card's billing address",$custCardStreet);
	    $this->CheckField("card's billing address - city",$custCardCity);
	}
	
	if (!$this->CartData()->ShipToSelf()) {
	    $custEmail	= $this->GetFormItem(KSF_BUYER_EMAIL);
	    $custPhone	= $this->GetFormItem(KSF_BUYER_PHONE);
	}
	$custCheckNum	= $this->GetFormItem(KSF_PAY_CHECK_NUM);
    }
//-----
    protected function HtmlEditLink($iPage,$iText='edit',$iPfx='[',$iSfx=']') {
	$out = $iPfx.'<a href="?'.KSQ_ARG_PAGE_DEST.'='.$iPage.'">'.$iText.'</a>'.$iSfx;
	return $out;
    }
    private function Order() {
	return $this->CartObj_req()->Order();
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
