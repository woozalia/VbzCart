<?php
/*
  FILE: ckout.php
  HISTORY:
    2010-02-21 split off from shop.php (via checkout/index.php)
    2010-12-24 Fixed call to Update() -- array is now required
    2013-02-20 mostly working, but "ship to card" and "ship to self" logic is becoming unmanageable.
      Going to gut this and significantly rework it as a single form.
    2013-04-12 ended up with two forms still, but somewhat simplified logic
    2015-08-28 vbz-const-user.php no longer needed; constants are defined in ferreteria/page.php

 FORM CAPTURE FUNCTIONS:
    * CaptureCart()
    * CaptureShipping()
    * CaptureBilling()

 CHECKOUT PAGE RENDERING FUNCTIONS:
    * RenderCart()	- display the shopping cart
    * RenderShipping()	- user enters shipping info
      * RenderPayType()	- subform for selecting payment method
    * RenderBilling()	- user enters billing/payment info
    * RenderConfirm()	- confirm all information entered
      * RenderOrder($iEditable)
    * ReceiveOrder()	- convert cart fields to order record; send email, show receipt
*/

//require_once('config-admin.php');

define('KI_CKOUT_COLUMN_COUNT',4);

/*=====
  CLASS: clsPageCkOut
  PURPOSE: subclass for generating checkout pages
*/
class clsPageCkout extends clsVbzPage_Admin {
    private $arMissing;

    private $doNavCtrl;	// hide navigation buttons on final confirmation page

    // ++ SETUP ++ //

    public function __construct() {
	parent::__construct();
	$this->Skin()->Sheet('ckout');
	$this->Skin()->PageTitle('checkout');
	$this->arMissing = array();
	$this->PageKey_forShow_clear();
	$this->PageKey_forData_clear();
	$this->bCardMatchesShip = NULL;
    }

    // -- SETUP -- //
    // ++ CEMENTING ++ //

    protected function BaseURL() {
	return KWP_CKOUT;
    }
    protected function PreSkinBuild() {
	// this may not be needed
    }
    protected function PostSkinBuild() {
	// this may not be needed
    }

    // -- CEMENTING -- //
    // ++ STATUS ACCESS ++ //

    public function PageKey_forShow($sSet=NULL) {
	if (!is_null($sSet)) {
	    $this->pgShow = $sSet;
	}
	return $this->pgShow;
    }
    public function PageKey_forShow_clear() {
	$this->pgShow = NULL;
    }
    public function PageKey_forData($sSet=NULL) {
	if (!is_null($sSet)) {
	    $this->pgData = $sSet;
	}
	return $this->pgData;
    }
    protected function PageKey_forData_clear() {
	$this->pgData = NULL;
    }
    protected function PageKey_forData_isSet() {
	return !is_null($this->pgData);
    }

    // -- STATUS ACCESS -- //
    // ++ FUNCTIONS THAT NEED RETHINKING ++ //

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
    protected function CardMatchesShip() {
	if (is_null($this->bCardMatchesShip)) {
	    $rsCFields = $this->CartFields();
	    $isCardAddrBlank = $rsCFields->CardAddrBlank();
	    $doesMatch = TRUE;
	    if ($isCardAddrBlank) {
		$isShipCardSame = $rsCFields->IsShipToCard();	// request to use shipping address for card billing address
		if ($isShipCardSame) {
		    // copy shipping address to card address
		    $rsCFields->CopyShipToCust();
		    $rsCFields->SaveCart();
		} else {
		    $doesMatch = FALSE;
		}
	    } else {
		if ($rsCFields->IsShipToCard()) {
		    // existing card address matches shipping address
		    // $doesMatch is already set
		} else {
		    // clear the "use shipping address as card address" flag
		    $rsCFields->IsShipToCard(FALSE);
		    $doesMatch = FALSE;	 // not blank and doesn't match
		}
	    }
	    $this->bCardMatchesShip = $doesMatch;
	}
	return $this->bCardMatchesShip;
    }

    // -- FUNCTIONS THAT NEED RETHINKING -- //
    // ++ APP FRAMEWORK ++ //

    protected function SysLog() {
	return $this->Data()->Syslog();
    }

    // -- APP FRAMEWORK -- //
    // ++ RECORDS ++ //

    protected function OrderRecord() {
	$rcCart = $this->CartRecord();
	return $rcCart->OrderRecord_orDie();
    }
    private function Order() {
	throw new exception('Order() is deprecated; call OrderRecord().');
    }
    /*----
      RETURNS: the current cart record, if usable.
	If not usable, throws an exception.
      HISTORY:
	2016-03-09 Was named CartRecord_current_orError() and was in vbz-page.php,
	  but I moved it here and renamed it to just CartRecord() since it is only
	  used in the checkout process, which always wants only the current cart.
    */
    protected function CartRecord() {
	$rcCart = $this->CartRecord_current_orNull();
	if (is_null($rcCart)) {
	    throw new exception('We somehow arrived at checkout without a cart being set.');
	}
	return $rcCart;
    }

    // -- RECORDS -- //
    // ++ WEB UI: PAGES ++ //

    /*----
      PURPOSE: This displays the cart page in the checkout sequence.
	It only shows up if the user navigates back from the shipping page.
    */
    public function RenderCart() {
	$rcCart = $this->CartRecord();
	if ($rcCart->HasLines()) {
	    // this is the read-only confirmation version of the cart
	    $htCore = $rcCart->Render(FALSE);
	    return $htCore;
	} else {
	    return 'No items in cart!';
	    // TO DO: log this as a critical error - how did the user get to the checkout with no items?
	}
    }
    /*----
      ACTION: Renders all sections of the shipping information entry page:
	* basic contact info (email, mainly) section
	* shipping section
	* payment type selection
    */
    protected function RenderShippingPage() {
	$rcCart = $this->CartRecord();
	return $rcCart->RenderShippingPage();

	/* 2016-03-10 old version
	$out =
	  $this->RenderContactSection()
	  .$this->RenderShippingSection()
	  .$this->RenderPayTypeSection()
	  ;
	return $out;//*/
    }
    /*----
      CALLED BY: RenderShippingPage() (editable form in checkout sequence)
    */
    /* 2016-03-10 moved/rewritten
    protected function RenderContactSection() {
	$htHdr = $this->SectionHeader('Contact information:');
	$htFNEmail = KSF_CART_BUYER_EMAIL;	// field name for email address
	$htFNPhone = KSF_CART_BUYER_PHONE;	// field name for phone number

	$rcCart = $this->CartRecord();
	
//	$rcCartData = $this->CartFields();
//	$sValEmail = $rcCartData->BuyerEmailAddress_entered(FALSE);
//	if (is_null($sValEmail)) {	// if this has not already been entered
//	    $sValEmail = $this->SessionRecord()->UserEmailAddress();
//	}
	
	$sValPhone = $rcCart->BuyerPhoneNumber_entered(FALSE);
	$sValEmail = $rcCart->BuyerEmailAddress_entered(FALSE);
	if (is_null($sValEmail)) {	// if this has not already been entered
	    $sValEmail = $this->SessionRecord()->UserEmailAddress();
	}
	$htValEmail = fcString::EncodeForHTML($sValEmail);
	$htValPhone = fcString::EncodeForHTML($sValPhone);

	$out =
	  "\n<!-- BEGIN RenderContactSection() - ".__FILE__."  line ".__LINE__." -->\n"
	  .<<<__END__
$htHdr
<table class="form-block" id="shipping">
  <tr>
    <td align=right><b>Email address</b>:</td>
    <td><input size=50 name="$htFNEmail" value="$htValEmail"></td>
  </tr>
  <tr>
    <td align=right><b>Phone number</b>:</td>
    <td><input size=30 name="$htFNPhone" value="$htValPhone"> (optional)</td>
  </tr>
</table>
__END__;

	return $out;
    }//*/
    /*----
      ACTION: Render the form controls for user to enter shipping information
    */
    /* 2016-03-10 moved/rewritten
    protected function RenderShippingSection() {
    throw new exception('2016-03-09 This will need some updating.');
	$rcCartData = $this->CartFields();

	$hrefForShipping = '<a href="'.KWP_WIKI_PUBLIC.'Shipping_Policies">';

// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;

	$sCustShipMsg = $rcCartData->ShipMsg(FALSE);

	//$this->htmlBeforeAddress = NULL;	// 2015-09-01 nothing uses this anymore
	$this->htmlAfterAddress = NULL;
//	$this->htmlAfterAddress = $htIsCard;
//	$this->htmlBeforeContact = $htToSelf;

	$this->doFixedCard = FALSE;
	//$this->doFixedSelf = FALSE; 	// 2015-09-01 nothing uses this anymore
	$this->doFixedName = FALSE;

	$htHdr = $this->SectionHeader('Shipping information:');

	$out =
	  "\n<!-- BEGIN RenderShippingSection() - ".__FILE__."  line ".__LINE__." -->\n"
	  .<<<__END__
$htHdr
<table class="form-block" id="shipping">
__END__;

	$oAddrShip = $rcCartData->RecipFields();

	$htEnter = NULL;
	if ($this->IsLoggedIn()) {
	    // allow user to select from existing recipient profiles
	    $oUser = $this->UserRecord();
	    $rsCusts = $oUser->ContactRecords();
	    if ($rsCusts->RowCount() > 0) {
		$doUseOld = $this->CartFields()->IsRecipOldEntry();
		$htSelOld = $doUseOld?' checked':'';
		$htSelNew = $doUseOld?'':' checked';
		$htCusts = '<input type=radio name="'.KSF_CART_RECIP_CONT_INTYPE.'" value="'.KS_FORM_INTYPE_EXISTING.'"'
		  .$htSelOld
		  .'><b>ship to an existing address:</b>'
		  .$rsCusts->Render_DropDown_Addrs(KSF_CART_RECIP_CONT_CHOICE)
		  //.'SQL:'.$rsCusts->sqlMake
		  ;
		$htEnter = '<input type=radio name="'.KSF_CART_RECIP_CONT_INTYPE.'" value="'.KS_FORM_INTYPE_NEWENTRY.'"'
		  .$htSelNew
		  .'><b>enter new shipping information:</b>';
	    } else {
		$htCusts = 'You currently have no addresses saved in your profile.';
	    }
	    $out .= '<tr><td colspan=2>'
	      .$htCusts
	      .'<span class=logout-inline>'.$this->RenderLogout().'</span>'
	      .'<hr></td></tr>';
	} else {
	    // make it easy for user to log in
	    $out .= '<tr><td colspan=2><b>Shopped here before?</b>'
	      .' '.$this->RenderLogin()
	      .' or <a href="'.KWP_LOGIN.'" title="login options: reset password, create account">more options</a>.'
	      .'<hr></td></tr>';
	}

	$out .= "<tr><td colspan=2>$htEnter</td></tr>";
	$out .= $oAddrShip->RenderAddress(
	  array('do.ship.zone'=>TRUE),
	  $this->CartRecord()->ShipZoneObj()
	  );
	$out .= <<<__END__
<tr><td colspan=2 align=left>
	<font color=#880000>
	<b>Note:</b> If you need any part of your order by a particular date, <b>please tell us</b> in the space below.</font>
	See our {$hrefForShipping}shipping policies</a> for details.
	</td></tr>
<tr><td align=right valign=top>
	Special Instructions:<br>
	</td>
	<td><textarea name="$ksShipMsg" cols=50 rows=5>$sCustShipMsg</textarea></td>
	</tr>
__END__;

	$out .=
	  "\n</table>"
	  .$this->Skin()->SectionFooter()
	  ."\n<!-- END RenderShipping() - ".__FILE__."  line ".__LINE__." -->\n";
	return $out;
    }//*/
    /*----
      ACTION: Render the form which lets the user choose how to pay
    */
    protected function RenderPayTypeSection() {
	$rcCart = $this->CartRecord();
	return $rcCart->RenderPayTypeSection();
	
	/* 2016-03-13 old code
	
	$sWhere = __METHOD__.'() in '.__FILE__;
	$out =
	  "\n<!-- vv $sWhere vv -->"
	  .$this->SectionHeader('Payment type:')
	  ."\n<table class=\"form-block\" id=\"pay-type\">"
	  ;

	$isShipCardSame = $this->CartFields()->IsShipToCard();
	$htChecked = $isShipCardSame?' checked':'';

	$out .= "\n<tr><td align=center>\n"
	  .$this->Skin()->RenderPaymentIcons()
	  ."<table><tr><td>"
	  .'<input name=payType value="'.KSF_CART_PTYP_CARD_HERE.'" type=radio checked disabled> Visa / MasterCard / Discover / American Express - pay here'
	  .'<br>&emsp;<input name="'.KSF_SHIP_IS_CARD.'" type=checkbox value=1'.$htChecked.'>billing address is same as shipping address above'
	  ."</td></tr></table>\n"
	  ."<hr>More payment options will be available soon.\n"
	  ."</td></tr>";

	$out .=
	  "\n</table>"
	  .$this->Skin()->SectionFooter()
	  ."\n<!-- ^^ $sWhere ^^ -->"
	  ;
	return $out;
	//*/
    }
/* NOTE TO SELF: The problem right now is that we need to make sure the shipping address gets SAVED
      to the db when it gets copied to billing.
      I'm starting off trying to make this happen by moving the copying-phase into the CaptureShipping stage.
      That means $doesMatch needs to be saved to a class member, because RenderBilling() needs to know the result.
*/
    public function RenderBilling() {
	$rcCart = $this->CartRecord();
	return $rcCart->RenderBillingSection();
    
      /* 2016-03-13 old code
	$rsCFields = $this->CartFields();

// copy any needed constants over to variables for parsing:
	$ksfCustCardNum = KSF_CART_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_CART_PAY_CARD_EXP;
	$ksfCardIsShip = KSF_SHIP_IS_CARD;

	$custCardNum = $rsCFields->CardNumber();
	$custCardExp = $rsCFields->CardExpiry();
	$isShipCardSame = $rsCFields->IsShipToCard();	// request to use shipping address for card billing address
	$doesShipCardMatch = $this->CardMatchesShip();

	$out = $this->SectionHeader('Payment information:')
	  ."\n<table id=form-billing>";

	if ($this->IsLoggedIn()) {
	    $htEnter = NULL;
	    // allow user to select from existing recipient profiles
	    $oUser = $this->UserRecord();
	    $rsCusts = $oUser->ContactRecords();
	    $htEnter = NULL;
	    if ($rsCusts->RowCount() > 0) {
		$doUseOld = $rsCFields->IsCardOldEntry();
		$htSelOld = $doUseOld?' checked':'';
		$htSelNew = $doUseOld?'':' checked';
		$htCusts = '<input type=radio name="'.KSF_CART_PAY_CARD_INTYPE.'" value="'.KS_FORM_INTYPE_EXISTING.'"'
		  .$htSelOld
		  .'><b>pay with an existing card:</b>'
		  .$rsCusts->Render_DropDown_Cards(KSF_CART_PAY_CARD_CHOICE)
		  //.'SQL:'.$rsCusts->sqlMake
		  ;
		$htEnter = '<input type=radio name="'.KSF_CART_PAY_CARD_INTYPE.'" value="'.KS_FORM_INTYPE_NEWENTRY.'"'
		  .$htSelNew
		  .'><b>enter new payment information:</b>';
	    } else {
		$htCusts = 'You currently have no payment cards saved in your profile.';
	    }
	    $out .= '<tr><td colspan=2>'
	      .$htCusts
	      .'<span class=logout-inline>'.$this->RenderLogout().'</span>'
	      .'<hr></td></tr>';
	}

	$sWhere = __METHOD__."() in ".__FILE__;
	$out .= "\n<!-- vv $sWhere vv -->";
	$out .= <<<__END__
<tr><td colspan=2>$htEnter</td></tr>
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

	$custShipIsCard = $rsCFields->IsShipToCard();
	//$custShipToSelf = $this->FieldRecords()->ShipToSelf();	// what was this used for? probably something fuzzy.

	//$this->htmlBeforeAddress = NULL;	// 2015-09-01 nothing uses this anymore
	//$this->htmlBeforeContact = NULL;	// 2015-09-01 nothing uses this anymore

	$this->msgAfterAddr = '<span class=note><font color=ff0000><b>Note</b></font>: please check your most recent credit card statement for exact address!</span>';
	$this->doFixedCard = FALSE;
	//$this->doFixedSelf = FALSE;	// 2015-09-01 nothing uses this anymore
	//$this->doFixedName = FALSE;

	$ofCont = $rsCFields->BuyerFields();
	$out .= $ofCont->RenderAddress(
	  array(
	    'do.ship.zone'	=> FALSE,
	    'do.fixed.all'	=> FALSE,
	    'do.fixed.name'	=> FALSE,
	    ),
	  $this->CartRecord()->ShipZoneObj()
	  );

	$out .= '</tr>'
	  ."\n</table>";				// SHUT inner table
	$out .= $this->Skin()->SectionFooter();	// SHUT outer table
	$out .= "\n<!-- ^^ $sWhere ^^ -->";
	//$out .= self::RenderSectionFtr();
	return $out;
	//*/
    }
    /*----
      ACTION: Render the "confirm this order" page from the Order record.
    */
    public function RenderConfirm() {
	//$out = $this->RenderOrder(TRUE);
	$rcCart = $this->CartRecord();

	// This renders the confirm page using Cart data, but we normally want Order data. Keeping for reference, but commented out. 2015-09-04
	//$out = $rcCart->RenderConfirm_page();

	// Render the confirm page from Order data:
	$rcOrd = $rcCart->OrderRecord_orConvert();	// create the order record if needed, or get existing one
	$out =
	  clsOrder::RenderConfirm_footer()
	  .$rcOrd->RenderConfirm();
	return $out;
    }
    /*----
      ACTION: Receive the order and render order receipt:
	* convert the cart data to an order record
	* send confirmation email
	* display order receipt page
      CALLED BY: $this->Content(), depending on which page is active
    */
    public function RenderReceipt() {
	$sWhere = __METHOD__."() in ".__FILE__;
	$out = "\n<!-- vv $sWhere vv -->\n";
// convert the cart to an order

	$rcOrd = $this->MakeOrder();
	$arVars = $rcOrd->TemplateVars();		// fetch variables for email
	$arHdrs = $rcOrd->EmailParams($arVars);	// fill in email template with variables

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

	$rcOrd->EmailConfirm(TRUE,$arEmail);
	$out .= $rcOrd->RenderReceipt()
	  ."\n<!-- ^^ $sWhere ^^ -->\n"
	  ;
	return $out;
    }

    // -- WEB UI: PAGES -- //
    // ++ WEB UI: FORM HANDLING ++ //

    protected function ParseInput() {
	$this->ParseInput_Login();	// check for user-login activity

# which page were we just on
	$gotPgDest = FALSE;
	$gotPgSrce = FALSE;
	if (empty($_GET[KSQ_ARG_PAGE_DEST])) {
	    if (empty($_POST[KSQ_ARG_PAGE_DATA])) {
		$this->PageKey_forData(KSQ_PAGE_CART);	// only the cart doesn't specify itself
	    } else {
		$this->PageKey_forData($_POST[KSQ_ARG_PAGE_DATA]);
		$gotPgSrce = TRUE;
	    }
	} else {
	    $gotPgDest = TRUE;
	    $this->PageKey_forData_clear();	// Currently not expecting any data from a "GET" query
	    $this->PageKey_forShow($_GET[KSQ_ARG_PAGE_DEST]);
	}
	if (!$gotPgDest) {
	// destination page unknown, so calculate it from data/source page:
	    if (nz($_POST[KSF_USER_BTN_LOGIN])) {
		// this is a login attempt
		$this->PageKey_forShow($this->PageKey_forData());	// stay on same page
	    } elseif (nz($_POST['btn-go-prev'])) {
		switch ($this->PageKey_forData()) {
		  case KSQ_PAGE_CART:
		    $this->PageKey_forShow(KSQ_PAGE_CART);	// can't go back any further
		    break;
		  case KSQ_PAGE_SHIP:
		    $this->PageKey_forShow(KSQ_PAGE_CART);
		    break;
		  case KSQ_PAGE_PAY:
		    $this->PageKey_forShow(KSQ_PAGE_SHIP);
		    break;
		  case KSQ_PAGE_CONF:
		    $this->PageKey_forShow(KSQ_PAGE_PAY);
		    break;
		  default:	// source page name not recognized; default to cart
		    $this->PageKey_forShow(KSQ_PAGE_CART);	// can't go back any further
		}
	    } elseif (nz($_POST['btn-go-next'])) {
		switch ($this->PageKey_forData()) {
		  case KSQ_PAGE_CART:
		    $this->PageKey_forShow(KSQ_PAGE_SHIP);
		    break;
		  case KSQ_PAGE_SHIP:
		    $this->PageKey_forShow(KSQ_PAGE_PAY);
		    break;
		  case KSQ_PAGE_PAY:
		    $this->PageKey_forShow(KSQ_PAGE_CONF);
		    break;
		  case KSQ_PAGE_CONF:
		    $this->PageKey_forShow(KSQ_PAGE_RCPT);
		  default:	// source page name not recognized; default to cart
		    $this->PageKey_forShow(KSQ_PAGE_CART);	// can't go back any further
		}
	    } elseif (nz($_POST['btn-go-same'])) {
		$this->PageKey_forShow($this->PageKey_forData());
	    } elseif (nz($_POST['btn-go-order'])) {
		$this->PageKey_forShow(KSQ_PAGE_RCPT);	// receipt page - submits order too
	    } else {
		$this->PageKey_forShow(KSQ_PAGE_DEFAULT);	// default page to display
	    }
	}

	if (!$this->HasCart()) {
	    // if cart is not set, don't go past cart display
	    $this->PageKey_forShow(KSQ_PAGE_CART);
	    // this could happen if the user loads the checkout URL directly without a cart -- so log the error, but don't raise an exception
	    $this->Data()->LogEvent('ckout.parsequery','pgShow='.$this->PageKey_forShow(),'cart ID not set','!cart',TRUE,FALSE);
	    //http_redirect(KWP_CKOUT_IF_NO_CART,'No cart set; returning to store.');
	    clsHTTP::Redirect(KWP_CKOUT_IF_NO_CART);
	}
	$this->CapturePage();
    }
    protected function HandleInput() {
	$this->HandleInput_Login();

	$this->strWikiPg	= '';
	$arEv = array(
	  clsSysEvents::ARG_CODE		=> 'PG-IN',
	  clsSysEvents::ARG_DESCR_FINISH	=> 'showing page "'.$this->PageKey_forShow().'"',
	  clsSysEvents::ARG_WHERE		=> 'HandleInput()',
	  );
	$rcCart = $this->CartRecord_current_orNull();
	if (is_null($rcCart)) {
	    throw new exception('In checkout with no current cart set.');
	    // TODO: Maybe this should just redirect back to /cart, in case user bookmarks a checkout page?
	}
	$rcEv = $rcCart->CreateEvent($arEv);
	$this->formShow = $this->PageKey_forShow();
	$this->doBackBtn = TRUE;
	$this->doRefrBtn = FALSE;

	$htMain = $this->Content();	// do content calculations

	$ht = $this->ContHdr()
	  .$htMain
	  .$this->ContFtr();
	$this->Skin()->Content('main',$ht);
    }

    // -- WEB UI: FORM HANDLING -- //
    // ++ FORM / DATA ACCESS ++ //

    public function GetFormItem($iName) {
	if (isset($_POST[$iName])) {
	    return $_POST[$iName];
	} else {
	    $this->CartRecord()->LogEvent('!FLD','Missing form field: '.$iName);
	    return NULL;
	}
    }
    /*
    public function CartFields() {
	return $this->CartRecord()->FieldsObject();
    }//*/

    // -- FORM / DATA ACCESS -- //
    // ++ FORM CAPTURE ++ //

    /*
      ACTION: Receives form input and determines where to dispatch it.
      RETURNS: not sure anymore; probably HTML to be displayed
	with message identifying which piece(s) of information were mis-entered
      REQUIRES: Input needs to have been parsed so we know what page's data we're capturing
    */
    public function CapturePage() {
	if ($this->PageKey_forData_isSet()) {
	    $sDesc = 'saving data from page '.$this->PageKey_forData();
	    // log as system event
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> 'CK-PG-REQ',	// checkout page request
	      clsSysEvents::ARG_DESCR_START	=> $sDesc,
	      clsSysEvents::ARG_WHERE		=> 'ckout.CapturePage',
	      clsSysEvents::ARG_PARAMS		=> array(
		'pgData'	=> $this->PageKey_forData(),
		'pgShow'	=> $this->PageKey_forShow()
		)
	      );
	    $rcCart = $this->CartRecord();
	    $rcSysEv = $rcCart->CreateEvent($arEv);
	    switch ($this->PageKey_forData()) {
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
		$out = 'Cannot save data from unknown page: ['.$this->PageKey_forData().']';
		//$rcCart->LogCartEvent('UNKF',$sDesc);
		$arEv = array(
		  clsSysEvents::ARG_DESCR_FINISH	=> 'page not recognized',
		  clsSysEvents::ARG_IS_ERROR		=> TRUE,
		  );
		$rcSysEv->Finish($arEv);
	    }
	    
	    $arMissed = $rcCart->GetMissingArray();
	    if (count($arMissed > 0)) {
		$okToAdvance = FALSE;
		$sList = NULL;
		foreach ($arMissed as $ctrl) {
		    $sAlias = $ctrl->DisplayAlias();
		    if (!is_null($sList)) {
			$sList .= ', ';
		    }
		    $sList .= $sAlias;
		}
		$sMsg = '<b>Some information is missing</b> &ndash; we really need the following items in order to process your order correctly: <b>'
		  .$sList
		  .'</b>'
		  ;
		$this->FormAlertMessage($sMsg);
	    } else {
		// For now, we can move on if there aren't any fields missing.
		$okToAdvance = TRUE;
		// Later there might be other reasons not to advance.
	    }
	
	} else {
	    // no data to collect, so no checking needed, so no messages to display
	    $out = '';
	}
	$sPgShow = $this->PageKey_forShow();
	switch ($sPgShow) {
	  case KSQ_PAGE_CART:	$formSeqShow = KI_SEQ_CART;	break;
	  case KSQ_PAGE_SHIP:	$formSeqShow = KI_SEQ_SHIP;	break;
	  case KSQ_PAGE_PAY:	$formSeqShow = KI_SEQ_PAY;	break;
	  case KSQ_PAGE_CONF:	$formSeqShow = KI_SEQ_CONF;	break;
	  case KSQ_PAGE_RCPT:	$formSeqShow = KI_SEQ_RCPT;	break;
//	  default: $formSeqShow = 0;	break;
	  default: throw new exception("Page key is [$sPgShow]. How did we get here?");
	}
	if (!$okToAdvance) {
	    // don't advance until all required fields are entered
	    // ok to go backwards, however
	    if ($formSeqShow > $this->formSeqData) {
		// user tried to advance -- stay on the same page
		$this->PageKey_forShow($this->PageKey_forData());
	    }
	}
	return $out;
    }
    public function CaptureCart() {
	return $this->Data()->Carts()->HandleCartFormInput();	// check for any cart data changed
    }
    /*----
      ACTION: Receive user form input, and update the database
      TODO: Shouldn't some of these fields *always* be set?
    */
    public function CaptureShipping() {
	$rcCart = $this->CartRecord();
	$rcCart->CaptureShippingPage();
	/*
    throw new exception('2016-03-09 This will need some rewriting.');
	$rsCD = $this->CartFields();
	$out = $rsCD->CaptureData($this->PageKey_forData());
	// 2014-08-25 This is probably just unnecessary now.
	//$this->CardMatchesShip();	// not sure if this puts the flag in the right place, but it's a start. TODO: verify.
	$rsCD->SaveCart();	// update the db from form data

	$objShipZone = $this->CartRecord()->ShipZoneObj();

	//$custIntype	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_INTYPE);
	//$custChoice	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_CHOICE);

	// get saved shipping data, where it exists:

	$custName	= $rsCD->FieldValue_forIndex_nz(KI_CART_RECIP_NAME);
	$custStreet	= $rsCD->FieldValue_forIndex_nz(KI_CART_RECIP_STREET);
	$custState	= $rsCD->FieldValue_forIndex_nz(KI_CART_RECIP_STATE);
	$custCity	= $rsCD->FieldValue_forIndex_nz(KI_CART_RECIP_CITY);
	$custCountry	= $rsCD->FieldValue_forIndex_nz(KI_CART_RECIP_COUNTRY);
	$custEmail	= $rsCD->FieldValue_forIndex_nz(KI_CART_RECIP_EMAIL);

	$shipZone	= $rsCD->FieldValue_forIndex_nz(KI_CART_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	//$custShipToSelf	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_IS_BUYER);
	//$custShipIsCard	= $rsCD->FieldValue_forIndex(KI_CART_SHIP_IS_CARD);
	//$custZip	= $this->GetFormItem(KSF_CART_RECIP_ZIP);
	//$custPhone	= $this->GetFormItem(KSF_CART_RECIP_PHONE);
	//$custMessage	= $this->GetFormItem(KSF_SHIP_MESSAGE);

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
	}//*/
    }
    public function CaptureBilling() {
	$rsCD = $this->CartFields();
	$out = $rsCD->CaptureData($this->PageKey_forData());
	$rsCD->SaveCart();	// update the db from form data

	$custCardNum	= $this->GetFormItem(KSF_CART_PAY_CARD_NUM);
	$custCardExp	= $this->GetFormItem(KSF_CART_PAY_CARD_EXP);

	# check for missing data
	$this->CheckField("card number",$custCardNum);
	$this->CheckField("expiration date",$custCardExp);

	if (!$rsCD->IsShipToCard()) {
	    $custCardName	= $this->GetFormItem(KSF_CART_PAY_CARD_NAME);
	    $custCardStreet	= $this->GetFormItem(KSF_CART_PAY_CARD_STREET);
	    $custCardCity	= $this->GetFormItem(KSF_CART_PAY_CARD_CITY);
	    $custCardState	= $this->GetFormItem(KSF_CART_PAY_CARD_STATE);
	    $custCardZip	= $this->GetFormItem(KSF_CART_PAY_CARD_ZIP);
	    $custCardCountry	= $this->GetFormItem(KSF_CART_PAY_CARD_COUNTRY);

	    # check for missing data
	    $this->CheckField("cardholder's name",$custCardName);
	    $this->CheckField("card's billing address",$custCardStreet);
	    $this->CheckField("card's billing address - city",$custCardCity);
	}
/* 2014-08-28 IsShipToSelf is currently deprecated
	if (!$rsCD->IsShipToSelf()) {
	    $custEmail	= $this->GetFormItem(KSF_CART_BUYER_EMAIL);
	    $custPhone	= $this->GetFormItem(KSF_CART_BUYER_PHONE);
	} */
	$custCheckNum	= $this->GetFormItem(KSF_CART_PAY_CHECK_NUM);
    }

    // -- FORM CAPTURE -- //
    // ++ FORM CHECKING ++ //
/* 2016-03-18 obsolete -- cart object now does the checking
    public function AddMissing($sText) {
	$this->arMissing[] = $sText;
    }
    public function CheckField($iText,$iValue) {
	if ($iValue == '') {
	    $this->AddMissing($iText);
	}
    }
    /*----
      RETURNS TRUE iff form has been filled out adequately.
	This currently means that either the "new" area is
	selected AND adequately filled out, or the "old" area
	is selected and there is an option chosen.
    * /
    protected function IsFormComplete() {
	//if ($this->IsNewEntry()) {
	    return !$this->AreFieldsMissing();
	//} else {
	//    return TRUE;	// TODO MAYBE: Check for valid address profile
	//}
    }//*/
    /*----
      RETURNS: TRUE IFF the submitted form's entry mode is "new"
      USED as part of the process of determining whether a page has
	been completed. If this is FALSE, then the user does not need
	to fill out all the entry fields; they can select an item
	from the drop-down list. If this returns TRUE, then the user
	does need to enter everything, so we have to check for that.
    */
    protected function IsNewEntry() {
    throw new exception('2016-03-13 This seems unnecessary.');
	switch ($this->PageKey_forData()) {
	  case KSQ_PAGE_CART:	$out = FALSE; break;
	  case KSQ_PAGE_SHIP:	$out = !$this->CartFields()->IsRecipOldEntry();	break;
	  case KSQ_PAGE_PAY:	$out = !$this->CartFields()->IsCardOldEntry(); 	break;
	  case KSQ_PAGE_CONF:	$out = FALSE; break;
	  case KSQ_PAGE_RCPT:	$out = FALSE; break;
	  // When loading a page from a static link on the confirmation page, there won't be form data.
	  //default: throw new exception('Does this ever happen?');
	  default: $out = FALSE;
	}
	return $out;
    }
    /*----
      RETURNS TRUE iff fields are missing from the "new" area.
	Does not look at which area ("new" entry or "old" drop-down)
	is actually selected.
      USAGE: Internal.
    *//* 2016-03-18 Cart object now does the checking.
    protected function AreFieldsMissing() {
	return (count($this->arMissing) > 0);
    }//*/
    /*----
      RETURNS string listing all missing fields
    */
    /* 2016-03-18 Changing this to a general alert message
    protected function MissingString($sSep=', ') {
	$out = NULL;
	foreach($this->arMissing as $sField) {
	    if (!is_null($out)) {
		$out .= $sSep;
	    }
	    $out .= $sField;
	}
	return $out;
    }//*/

    // -- FORM CHECKING -- //
    // ++ WEB UI: MAJOR ELEMENTS ++ //

    /*----
      PUBLIC so Cart can access it for displaying confirmation page
      RENDERS links to go back to earlier pages, to edit order before submitting
      USED BY: order confirmation page (displayed by Cart object)
    */
    public function HtmlEditLink($iPage,$iText='edit',$iPfx='[',$iSfx=']') {
	$out = $iPfx.'<a href="?'.KSQ_ARG_PAGE_DEST.'='.$iPage.'">'.$iText.'</a>'.$iSfx;
	return $out;
    }
    /*----
      PURPOSE: Render top part of {form and outer table, including <td>}
      TODO: move this stuff into the skin, somehow
    */
    protected function ContHdr() {
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n"
	  .$this->StatusBar()
	  ;

	$htNav = $this->RenderNavButtons();
	$urlTarg = KWP_CKOUT;

	$out .= <<<__END__

<form method=post name=checkout action="$urlTarg">
<table class="form-block" id="page-ckout">
$htNav
<tr>
<td>
__END__;
	return $out;
    }
    /*----
      PURPOSE: Stuff to close out the page contents
      ACTION: Close table row opened in RenderContentHdr(), display standard buttons, close outer table and form
    */
    protected function ContFtr() {
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";

	$out .= $this->RenderNavButtons();

	$oCart = $this->CartRecord();
	$idSess = $this->SessionRecord()->KeyValue();
	$idCart = $oCart->KeyValue();
	$idOrd = $oCart->Value('ID_Order');
	$sOrd = ($idOrd == 0)?'':' Order ID: <b>'.$idOrd.'</b>';
	if ($this->doNavCtrl) {
	    $htLine = NULL;
	} else {
	    $htLine = '<hr>';	// TODO: Make this a Skin function (again)
	}
	$htNav = $this->StatusBar();

	$out .= <<<__END__
</td></tr>
</table>
$htNav
$htLine
<!-- span class="footer-stats">Cart ID: <b>$idCart</b> Session ID: <b>$idSess</b>$sOrd</span -->
</form>
__END__;

	return $out;
    }
    protected function RenderNavButtons() {
	if ($this->doNavCtrl) {
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
	      $this->RenderFormAlert()	// if any
	      .'<tr><td colspan='.KI_CKOUT_COLUMN_COUNT.' align=center bgcolor=ffffff class=section-title>'
	      .'<input type=hidden name="'.KSQ_ARG_PAGE_DATA.'" value="'.$this->PageKey_forShow().'">'
	      .$htBackBtn.$htRefrBtn
	      .'<input type=submit name="btn-go-next" value="Next &gt;">'
	      ;
	} else {
	    $out = NULL;
	}
	return $out;
    }
    /*----
      HISTORY:
	2016-03-18 Changing this from a message specific to missing-form-elements to a general
	  form-alert display. Renamed from RenderMissing() to RenderFormAlert().
	
	  The text "<b>Please fill in the following</b>: " used to be prepended, but now that
	  should be included in FormAlertMessage().
    */
    protected function RenderFormAlert() {
	$nCols = KI_CKOUT_COLUMN_COUNT;
	$sMsg = $this->FormAlertMessage();
	if (is_null($sMsg)) {
	    $out = NULL;
	} else {
	    // NOTE: I've been unable to get the icon to align nicely with the text without using a table.
	    $out = "<tr><td colspan=$nCols>\n<table>\n<tr><td><img src=".'"'.KWP_ICON_ALERT
	      .'"></td><td valign=middle><span class=alert style="background: yellow">'
	      //.$this->MissingString()
	      .$sMsg
	      ."</span></td></tr>\n</table>\n</td></tr>";
	}
	return $out;
    }
    private $htFormAlert;
    protected function FormAlertMessage($ht=NULL) {
	if (is_null($ht)) {
	    if (!isset($this->htFormAlert)) {
		$this->htFormAlert = NULL;
	    }
	} else {
	    $this->htFormAlert = $ht;
	}
	return $this->htFormAlert;
    }
    /*----
      OUTPUT:
	$doBackBtn: if TRUE, show the BACK navigation button
	$doRefrBtn:
	$doNavCtrl:
    */
    protected function Content() {
	// default options
	$this->doFixedCard = FALSE;
	$this->doFixedCountry = FALSE;
	$out = NULL;
	$this->doNavCtrl = TRUE;	// default
	$out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	switch ($this->PageKey_forShow()) {
	  case KSQ_PAGE_CART:	// shopping cart
	    $this->doBackBtn = FALSE;
	    $this->doRefrBtn = TRUE;
	    $out .= $this->RenderCart();
	    break;
	  case KSQ_PAGE_SHIP:	// shipping information
	    $out .= $this->RenderShippingPage();
	    break;
	  case KSQ_PAGE_PAY:	// billing information
	    $out .= $this->RenderBilling();
	    break;
	  case KSQ_PAGE_CONF:	// confirm order
	    $this->doNavCtrl = FALSE;
	    $out .= $this->RenderConfirm();
	    break;
	  case KSQ_PAGE_RCPT:	// order receipt
	    $this->doNavCtrl = FALSE;
	    $out .= $this->RenderReceipt();
	    break;
	  default:
// The normal shopping cart does not specify a target within the checkout sequence
// ...so show the first page which follows the cart page:
	    $out .= $this->RenderShippingPage();
	}
	$out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	return $out;
    }
    protected function StatusBar() {
	$oNav = $this->CreateNavBar();
	// call this after CreateNavBar so child classes can insert stuff first
	$sPage = $this->PageKey_forShow();
	$oNav->States($sPage,1,3);
	$oNav->Node($sPage)->State(2);
	return $this->Skin()->RenderNavbar_H($oNav);
    }

    // -- WEB UI: MAJOR ELEMENTS -- //
    // ++ WEB UI: UI OBJECTS ++ //

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

    // -- WEB UI: UI OBJECTS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Create and populate an order from this cart
	...but only do the bits that are specific to the
	shopping interface; everything else should go in
	Cart::ToOrder() so that the admin UI can use it too.
      CALLED BY: $this->ReceiveOrder()
      HISTORY:
	2011-03-27 fixed bug which was preventing order number from being written to cart.
	  Looking at the cart data, this bug apparently happened on 2010-10-28.
	2013-11-06 Most of the work was being pushed out to clsOrders::CopyCart(),
	  but this seems unnecessary so I'm pulling it back in here.
    */
    protected function MakeOrder() {
	$rcCart = $this->CartRecord();	// throw an exception if no cart found

	$tOrders = $this->OrderTable();
	$idOrd = $rcCart->OrderID();
	$doNewOrd = TRUE;
	if ($idOrd > 0) {
	    // Cart is already connected to an Order - check to see if the Order has been submitted yet:

	    $rcOrd = $tOrders->GetItem($idOrd);
	    if ($rcOrd->IsPlaced()) {
		$arEv = array(
		  'code'	=> 'ORN',
		  'descr'	=> 'Cart is assigned to submitted Order; creating new Order',
		  'params'	=> '\cart='.$rcCart->KeyValue().'\ord='.$idOrd,
		  'where'	=> __METHOD__,
		  );
		$rcEv1 = $rcCart->StartEvent($arEv);
	    } else {
		// order has not been placed yet -- so let's assume customer wants to modify it
		$doNewOrd = FALSE;
		$arEv = array(
		  'code'	=> 'ORU',
		  'descr'	=> 'ID_Order already set in cart: updating existing order',
		  'params'	=> '\cart='.$rcCart->KeyValue().'\ord='.$idOrd,
		  'where'	=> __METHOD__,
		  );
		$rcEv1 = $rcCart->StartEvent($arEv);
		//$rcCart->Update(array('WhenUpdated'=>'NOW()'));	// 2015-09-04 why?
	    }
	}
	if ($doNewOrd) {
	    $idOrd = $tOrders->Create();

	    if (empty($idOrd)) {
		throw new exception('Internal Error: Order creation did not return order ID.');
	    }

	    $rcOrd = $tOrders->GetItem($idOrd);

	    $arEv = array(
	      'code'	=> 'ORD',
	      'descr'	=> 'assigning order to cart',
	      'params'	=> '\ord='.$idOrd,
	      'where'	=> __METHOD__
	      );
	    $rcEv2 = $rcCart->StartEvent($arEv);
	    $arUpd = array(
	      'WhenOrdered'	=> 'NOW()',
	      'ID_Order'	=> $idOrd
	      );
	    $rcCart->Update($arUpd);
	    $rcEv2->Finish();

	    $rcCart->OrderID($idOrd);	// save Order ID to local Cart in case it's important
	    $rcOrd = $tOrders->GetItem($idOrd);
	}
	$rcCart->ToOrder($rcOrd);	// copy the actual data to the order record

	// set Order ID in session object
	$arUpd = array(
	  'ID_Order'	=> $idOrd,
	  );
	$rcSess = $rcCart->SessionRecord();
	$rcSess->Update($arUpd);

	// log the event
	$sqlSQL = $tOrders->Engine()->SanitizeAndQuote($rcSess->sqlExec);
	$rcCart->LogEvent(
	  __METHOD__,
	  '|ord ID='.$idOrd.'|cart ID='.$rcCart->KeyValue(),
	  'Converted cart to order; SQL='.$sqlSQL,
	  'C>O',FALSE,FALSE);

	// log completion of the outer Cart event
	$rcEv1->Finish();

	return $rcOrd;	// this is used by the checkout process
    }

    // -- ACTIONS -- //

}
