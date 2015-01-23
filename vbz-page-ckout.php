<?php
/*
  FILE: ckout.php
  HISTORY:
    2010-02-21 split off from shop.php (via checkout/index.php)
    2010-12-24 Fixed call to Update() -- array is now required
    2013-02-20 mostly working, but "ship to card" and "ship to self" logic is becoming unmanageable.
      Going to gut this and significantly rework it as a single form.
    2013-04-12 ended up with two forms still, but somewhat simplified logic

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

require_once('config-admin.php');
require_once('vbz-const-user.php');

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
    // ++ ABSTRACT OVERRIDES ++ //

    protected function BaseURL() {
	return KWP_CKOUT;
    }
    protected function MenuPainter_new() {
	// do nothing - checkout does not use menu functions
    }
    protected function PreSkinBuild() {
	// this may not be needed
    }
    protected function PostSkinBuild() {
	// this may not be needed
    }

    // -- ABSTRACT OVERRIDES -- //
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
    // ++ OBJECT ACCESS ++ //

    protected function SysLog() {
	return $this->Data()->Syslog();
    }
    protected function OrderRecord() {
	$rcCart = $this->CartRecord_current_orError();
	return $rcCart->OrderRecord_orDie();
    }
    private function Order() {
	throw new exception('Order() is deprecated; call OrderRecord().');
    }

    // -- OBJECT ACCESS -- //
    // ++ WEB UI: PAGES ++ //

    /*----
      PURPOSE: This displays the cart page in the checkout sequence.
	It only shows up if the user navigates back from the shipping page.
    */
    public function RenderCart() {
	$rcCart = $this->CartRecord_current_orError();
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
	$out =
	  $this->RenderContactSection()
	  .$this->RenderShippingSection()
	  .$this->RenderPayTypeSection()
	  ;
	return $out;
    }
    protected function RenderContactSection() {
	$htHdr = $this->SectionHeader('Contact information:');
	$htFNEmail = KSF_CART_BUYER_EMAIL;	// field name for email address
	$htFNPhone = KSF_CART_BUYER_PHONE;	// field name for phone number

	$rcCartData = $this->CartFields();
	$sValEmail = $rcCartData->BuyerEmailAddress_entered();
	if (is_null($sValEmail)) {	// if this has not already been entered
	    $sValEmail = $this->SessionRecord()->UserEmailAddress();
	}
	$sValPhone = $rcCartData->BuyerPhoneNumber_entered();
	$htValEmail = htmlspecialchars($sValEmail);
	$htValPhone = htmlspecialchars($sValPhone);

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
    }
    /*----
      ACTION: Render the form controls for user to enter shipping information
    */
    protected function RenderShippingSection() {
	$rcCartData = $this->CartFields();

	$hrefForShipping = '<a href="'.KWP_WIKI_PUBLIC.'Shipping_Policies">';

// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;

	$sCustShipMsg = $rcCartData->ShipMsg();

	$this->htmlBeforeAddress = NULL;
	$this->htmlAfterAddress = NULL;
//	$this->htmlAfterAddress = $htIsCard;
//	$this->htmlBeforeContact = $htToSelf;

	$this->doFixedCard = FALSE;
	$this->doFixedSelf = FALSE;
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
	  $this->CartRecord_current_orError()->ShipZoneObj()
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
    }
    /*----
      ACTION: Render the form which lets the user choose how to pay
    */
    protected function RenderPayTypeSection() {
	$out =
	  "\n<!-- BEGIN ".__METHOD__."() - ".__FILE__." LINE ".__LINE__." -->"
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
	  ."\n<!-- END ".__METHOD__."() - ".__FILE__." LINE ".__LINE__." -->"
	  ;
	return $out;
    }
/* NOTE TO SELF: The problem right now is that we need to make sure the shipping address gets SAVED
      to the db when it gets copied to billing.
      I'm starting off trying to make this happen by moving the copying-phase into the CaptureShipping stage.
      That means $doesMatch needs to be saved to a class member, because RenderBilling() needs to know the result.
*/
    public function RenderBilling() {
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

	$out .= "\n<!-- ".__METHOD__." in ".__FILE__." -->";
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

	$this->htmlBeforeAddress = NULL;
	$this->htmlBeforeContact = NULL;

	$this->msgAfterAddr = '<span class=note><font color=ff0000><b>Note</b></font>: please check your most recent credit card statement for exact address!</span>';
	$this->doFixedCard = FALSE;
	$this->doFixedSelf = FALSE;
	//$this->doFixedName = FALSE;

	$ofCont = $rsCFields->BuyerFields();
	$out .= $ofCont->RenderAddress(
	  array(
	    'do.ship.zone'	=> FALSE,
	    'do.fixed.all'	=> FALSE,
	    'do.fixed.name'	=> FALSE,
	    ),
	  $this->CartRecord_current_orError()->ShipZoneObj()
	  );

	$out .= '</tr>'
	  ."\n</table>";				// SHUT inner table
	$out .= $this->Skin()->SectionFooter();	// SHUT outer table
	$out .= "\n<!-- /".__METHOD__." in ".__FILE__." -->";
	//$out .= self::RenderSectionFtr();
	return $out;
    }
    /*----
      ACTION: Render the "confirm this order" page (last page before cart is finalized into order)
    */
    public function RenderConfirm() {
	//$out = $this->RenderOrder(TRUE);
	$rcCart = $this->CartRecord_current_orError();
	$out = $rcCart->RenderConfirm_page();
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
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";
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
	  ."\n<!-- -".__METHOD__."() in ".__FILE__." -->\n"
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
	    $this->CartRecord_current_orError()->LogEvent('!FLD','Missing form field: '.$iName);
	    return NULL;
	}
    }
    public function CartFields() {
	return $this->CartRecord_current_orError()->FieldRecords();
    }
    /* 2014-10-11 Is this still being used?
    public function AddrCard() {
    // REQUIRES: GetDetailObjs() must be called first - (2013-04-12 not sure if this is still true)
	return $this->AddrCardObj();
    } */

    // -- FORM / DATA ACCESS -- //
    // ++ FORM CAPTURE ++ //

    /*
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
	      clsSysEvents::ARG_PARAMS		=> 'pgData='.$this->PageKey_forData().'/pgShow='.$this->PageKey_forShow(),
	      );
	    //$oSysEv = $this->SysLog()->CreateEvent($arEv);
	    // the only (legit) way to get here is from the cart page, so... assume a cart has been assigned
	    $rcCart = $this->CartRecord_current_orError();
	    $oSysEv = $rcCart->CreateEvent($arEv);
	    //$rcCart->LogCartEvent('save',$sDesc);
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
		$oSysEv->Finish($arEv);
	    }
	} else {
	    // no data to collect, so no checking needed, so no messages to display
	    $out = '';
	}
	switch ($this->PageKey_forShow()) {
	  case KSQ_PAGE_CART:	$formSeqShow = KI_SEQ_CART;	break;
	  case KSQ_PAGE_SHIP:	$formSeqShow = KI_SEQ_SHIP;	break;
	  case KSQ_PAGE_PAY:	$formSeqShow = KI_SEQ_PAY;	break;
	  case KSQ_PAGE_CONF:	$formSeqShow = KI_SEQ_CONF;	break;
	  case KSQ_PAGE_RCPT:	$formSeqShow = KI_SEQ_RCPT;	break;
//	  default: $formSeqShow = 0;	break;
	  default: throw new exception('Does this ever happen?');
	}
	if (!$this->IsFormComplete()) {
	    // don't advance until all required fields are entered
	    // ok to go backwards, however
	    if ($formSeqShow > $this->formSeqData) {
		$this->PageKey_forShow($this->PageKey_forData());
	    }
	}
	return $out;
    }
    public function CaptureCart() {
	return $this->Data()->Carts()->CheckData();	// check for any cart data changed
    }
    /*----
      ACTION: Receive user form input, and update the database
    */
    public function CaptureShipping() {
	$rsCD = $this->CartFields();
	$out = $rsCD->CaptureData($this->PageKey_forData());
	// 2014-08-25 This is probably just unnecessary now.
	//$this->CardMatchesShip();	// not sure if this puts the flag in the right place, but it's a start. TODO: verify.
	$rsCD->SaveCart();	// update the db from form data

	$objShipZone = $this->CartRecord_current_orError()->ShipZoneObj();

	//$custIntype	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_INTYPE);
	//$custChoice	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_CHOICE);

	$custName	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_NAME);
	$custStreet	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_STREET);
	$custState	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_STATE);
	$custCity	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_CITY);
	$custCountry	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_COUNTRY);
	$custEmail	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_EMAIL);

	$shipZone	= $rsCD->FieldValue_forIndex(KI_CART_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	//$custShipToSelf	= $rsCD->FieldValue_forIndex(KI_CART_RECIP_IS_BUYER);
	//$custShipIsCard	= $rsCD->FieldValue_forIndex(KI_CART_SHIP_IS_CARD);
	//$custZip	= $this->GetFormItem(KSF_CART_RECIP_ZIP);
	//$custPhone	= $this->GetFormItem(KSF_CART_RECIP_PHONE);
	//$custMessage	= $this->GetFormItem(KSF_SHIP_MESSAGE);

	// 2014-07-28 is this necessary? Reloading changed data?
	//$rsCD = $this->FieldRecords();

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
    */
    public function IsFormComplete() {
	if ($this->IsNewEntry()) {
	    return !$this->AreFieldsMissing();
	} else {
	    return TRUE;	// TODO MAYBE: Check for valid address profile
	}
    }
    /*----
      RETURNS: TRUE IFF the submitted form's entry mode is "new"
      USED as part of the process of determining whether a page has
	been completed. If this is FALSE, then the user does not need
	to fill out all the entry fields; they can select an item
	from the drop-down list. If this returns TRUE, then the user
	does need to enter everything, so we have to check for that.
    */
    protected function IsNewEntry() {
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
    */
    protected function AreFieldsMissing() {
	return (count($this->arMissing) > 0);
    }
    /*----
      RETURNS string listing all missing fields
    */
    protected function MissingString($sSep=', ') {
	$out = NULL;
	foreach($this->arMissing as $sField) {
	    if (!is_null($out)) {
		$out .= $sSep;
	    }
	    $out .= $sField;
	}
	return $out;
    }

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

	$oCart = $this->CartRecord_current_orError();
	$idSess = $this->SessObj()->KeyValue();
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
<span class="footer-stats">Cart ID: <b>$idCart</b> Session ID: <b>$idSess</b>$sOrd</span>
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
	      $this->RenderMissing()
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
    protected function RenderMissing() {
	$nCols = KI_CKOUT_COLUMN_COUNT;
	// TODO: not sure about the logic here
	if (!$this->IsFormComplete() && ($this->PageKey_forShow() == $this->PageKey_forData())) {
	    // NOTE: I've been unable to get the icon to align nicely with the text without using a table.
	    $htMissing = "<tr><td colspan=$nCols>\n<table>\n<tr><td><img src=".'"'.KWP_ICON_ALERT
	      .'"></td><td valign=middle><span class=alert style="background: yellow"><b>Please fill in the following</b>: '
	      .$this->MissingString()
	      ."</span></td></tr>\n</table>\n</td></tr>";
	} else {
	    $htMissing = '';
	}
	return $htMissing;
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
	$rcCart = $this->CartRecord_current_orError();	// throw an exception if no cart found

	$tOrders = $this->Data()->Orders();
	$idOrd = $rcCart->OrderID();
	if ($idOrd > 0) {
	    // this may or may not be a problem, but it's a bit unexpected; log it:
	    $arEv = array(
	      'code'	=> 'UNX',
	      'descr'	=> 'ID_Order already set in cart',
	      'params'	=> '\cart='.$rcCart->KeyValue().'\ord='.$idOrd,
	      'where'	=> __METHOD__,
	      );
	    $rcCart->StartEvent($arEv);
	    $rcCart->Update(array('WhenUpdated'=>'NOW()'));
	} else {
	    $idOrd = $tOrders->Create();

	    if (empty($idOrd)) {
		throw new exception('Internal Error: Order creation did not return order ID.');
	    }

	    $arEv = array(
	      'code'	=> 'ORD',
	      'descr'	=> 'assigning order to cart',
	      'params'	=> '\ord='.$idOrd,
	      'where'	=> __METHOD__
	      );
	    $rcCart->StartEvent($arEv);
	    $arUpd = array(
	      'WhenOrdered'	=> 'NOW()',
	      'ID_Order'	=> $idOrd
	      );
	    $rcCart->Update($arUpd);
	    $rcCart->FinishEvent();

	    $rcCart->OrderID($idOrd);
	}
	$rcOrd = $tOrders->GetItem($idOrd);
	$rcCart->ToOrder($rcOrd);	// copy the actual data to the order record
	//$objOrd->CopyCart($objCart);	// copy the actual data to the order record

	// set Order ID in session object
	$arUpd = array(
	  'ID_Order'	=> $idOrd,
	  );
	$rcSess = $rcCart->SessionRecord();
	$rcSess->Update($arUpd);
	// log the event
	$rcCart->LogEvent(
	  __METHOD__,
	  '|ord ID='.$idOrd.'|cart ID='.$rcCart->KeyValue(),
	  'Converted cart to order; SQL='.SQLValue($rcSess->sqlExec),
	  'C>O',FALSE,FALSE);

	return $rcOrd;	// this is used by the checkout process
    }

    // -- ACTIONS -- //
    // ++ DISCARDED FUNCTIONS ++ //


    /*-----
      ACTION: Display what will be going into the order
	Based on cart contents, not order record.
	This is formatted to fit within the checkout sequence.
      INPUT:
	$iEditable: if TRUE, displays buttons to go back to earlier
	  screens for editing; does not actually edit in place.
      NOTE:
	1. Don't use this to display order confirmation.
	Use the order object so we only show what really
	went into the order record.
	2. This seems to duplicate what Order::RenderReceipt() does.
    */ /*
    public function RenderOrder($iEditable) {
    echo __FILE__.' line '.__LINE__.'<br>';
	$objCart = $this->CartRecord_current_orError();

	$idCart = $objCart->KeyValue();
	if ($idCart == 0) {
	    throw new exception('Internal Error: Cart object ID is zero in RenderOrder().');
	}

	$rsCD = $this->FieldRecords();

	$isShipCard = $rsCD->IsShipToCard();
	//$isShipSelf = $rsCD->IsShipToSelf();
	$strCustShipMsg = $rsCD->ShipMsg();
	$custCardNum = $rsCD->CardNumber();
	$custCardExp = $rsCD->CardExpiry();
	$isShipCardReally = $this->CardMatchesShip();
	// TODO: this should probably allow retrieval from stored records... but everything needs to be reorganized
	$sBuyerEmail = $rsCD->BuyerEmailAddress_entered();
	$sBuyerPhone = $rsCD->BuyerPhoneNumber_entered();
	if (is_null($sBuyerPhone)) {
	    $sBuyerPhone = '<i>none</i>';
	}

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

	$out .= $rsCD->RecipFields()->RenderAddress(array('do.ship.zone'=>TRUE));

	if ($iEditable) {
	    $htLink = $this->HtmlEditLink(KSQ_PAGE_PAY);
	}
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
	$out .= $rsCD->BuyerFields()->RenderAddress(array('do.ship.zone'=>FALSE));

	if ($iEditable) {
	    $sPgName = KSQ_ARG_PAGE_DATA;
	    $sPgShow = $this->PageKey_forShow();

	    // TODO: this bit should be in a method like RenderNavButtons()
	    // It appears *instead* of them.
	    $out .= <<<__END__
<tr><td colspan=2 align=center bgcolor=ffffff class=section-title>
<input type=hidden name="$sPgName" value="$sPgShow">
<input type=submit name="btn-go-prev" value="&lt;&lt; Make Changes">
<input type=submit name="btn-go-order" value="Place the Order!">
__END__;
	}
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    } */
    /*----
      ARGUMENTS:
	$iAddr
      PROPERTY INPUTS:
      RULES - this documentation is obsolete:
	Pages displayed:
	  On page 1 (shipping), all fields are editable.
	  On page 2 (payment), some fields may be read-only depending on which "same as" flags the user has checked
	  On page 3 (confirmation), all fields are read-only
      HISTORY:
	2014-10-07 This was apparently moved from clsPerson to Page class, but there seems to be
	  no good reason for this, and considerable reason to keep it in clsPerson. Moving it back.
	  (See also RenderPerson().)
    */ /*
    protected function RenderAddress(clsPerson $oAddr, array $iOpts) {
	$rcCart = $this->CartRecord_current_orError();
	$oZone = $rcCart->ShipZoneObj();
	if (!is_object($oZone)) {
	    throw new exception('Could not retrieve Shipping Zone object in RenderAddress().');
	}

	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n";

	if (isset($this->strInsteadOfAddr)) {
	    $out .= '<tr><td colspan=2>'.$this->strInsteadOfAddr.'</td></tr>';
	} else {
	    $strStateLabel = $oZone->StateLabel();	// "province" etc.
	    if ($oZone->hasState()) {
		$htStateAfter = '';
		$lenStateInput = 3;
		// later, we'll also include how many chars the actual abbr is and use this to say "(use n-letter abbreviation)"
	    } else {
		$htStateAfter = ' (if needed)';
		$lenStateInput = 10;
	    }

	    $isCountryFixed = FALSE;
	    if (empty($strCountry)) {
		if (!$oZone->isDomestic()) {
		    // this code cannot possibly work; it will need rewriting
		    $idxCountry = $oAddr->CountryNode()->DataType();
		    $this->DataItem($idxCountry,$oZone->Country());
		}
		$isCountryFixed = !empty($strCountry);
	    }

	    $hrefForSpam = '<a href="'.KWP_WIKI_PUBLIC.'Anti-Spam_Policy">';
	    $arOpts = array_merge($iOpts,array(
	      'ht.before.addr'	=> $this->htmlBeforeAddress,
	      'ht.after.addr'	=> nz($this->htmlAfterAddress),
	      'ht.after.email'	=> $hrefForSpam.'anti-spam policy</a>',
	      'ht.ship.combo'	=> $oZone->ComboBox(),
	      'ht.after.state'	=> $htStateAfter,
	      'str.zip.label'	=> $oZone->PostalCodeName(),	// US='Zip Code', otherwise="postal code"
	      'str.state.label'	=> $strStateLabel,
	      'len.state.inp'	=> $lenStateInput,
	      'do.fixed.all'	=> $this->doFixedCard,	// TRUE = disable editing
	      'do.fixed.name'	=> $this->doFixedName,
	      'do.fixed.ctry'	=> $this->doFixedCountry,
	      ));

	    $out .= $this->RenderPerson($oAddr,$arOpts);
	}

	//$out .= "\n<tr><!-- ".__FILE__.' line '.__LINE__.' -->';
	if (isset($this->msgAfterAddr)) {
	    $out .= '<td colspan=2>'.$this->msgAfterAddr.'</td>';
	}
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    }
    /*----
      HISTORY:
	2014-10-07 This was apparently moved from clsPerson to Page class, but there seems to be
	  no good reason for this, and considerable reason to keep it in clsPerson. Moving it back.
	  (See also RenderAddress().)
    */ /*
    protected function RenderPerson(clsPerson $oPerson, array $arOptions) {
	// TODO: converting this -- was running inside clsPerson, now running inside Page class

	$htBefAddr	= $arOptions['ht.before.addr'];
	$htAftAddr	= $arOptions['ht.after.addr'];
	$htAftState	= $arOptions['ht.after.state'];
	$htShipCombo	= $arOptions['ht.ship.combo'];
	$strZipLbl	= $arOptions['str.zip.label'];
	$strStateLbl	= $arOptions['str.state.label'];
	$lenStateInp	= $arOptions['len.state.inp'];	// "length" attribute to use for user input field for "state"
	$doFixedCtry 	= $arOptions['do.fixed.ctry'];
	$doFixedName 	= $arOptions['do.fixed.name'];
	$doFixedAll	= $arOptions['do.fixed.all'];
	$doShipZone	= clsArray::Nz($arOptions,'do.ship.zone');

// copy calculated stuff over to variables to make it easier to insert in formatted output:
	$ksName		= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_NAME);
	$ksStreet	= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_STREET);
	$ksCity		= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_CITY);
	$ksState	= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_STATE);
	$ksZip		= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_ZIP);
	$ksCountry	= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_COUNTRY);
	$ksEmail	= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_EMAIL);
	$ksPhone	= $oPerson->Name_forSuffix(_KSF_CART_SFX_CONT_PHONE);

	$strName	= $oPerson->NameValue();
	$strStreet	= $oPerson->StreetValue();
	$strCity	= $oPerson->TownValue();
	$strState	= $oPerson->StateValue();
	$strZip		= $oPerson->ZipcodeValue();
	$strCountry	= $oPerson->CountryValue();

	$doEmail = $oPerson->DoEmail();
	$doPhone = $oPerson->DoPhone();
	$strEmail = $strPhone = NULL;
	if ($doEmail) {
	    $strEmail	= $oPerson->EmailValue();
	}
	if ($doPhone) {
	    $strPhone	= $oPerson->PhoneValue();
	}

	if ($doFixedAll) {
	    $doFixedCtry = TRUE;
	}

	if ($doFixedCtry) {
	    $htCountry = '<b>'.$strCountry.'</b>';
	    $htZone = '';
	} else {
	    $htCountry = '<input name="'.$ksCountry.'" value="'.$strCountry.'" size=20>';
	    $htBtnRefresh = '<input type=submit name="update" value="Update Form">';
//	    $htZone = " shipping zone: $htShipCombo $htBtnRefresh";
	    // this no longer displays the total, so no need for an update button
	    $htZone = $doShipZone?(" shipping zone: $htShipCombo"):'';
	}

	if ($doFixedName) {
	    $out = <<<__END__
<tr><td align=right valign=middle>Name:</td>
	<td><b>$strName</b></td>
	</tr>
__END__;
	} else {
	    $out = <<<__END__
<tr><td align=right valign=middle>Name: </td>
	<td><input name="$ksName" value="$strName" size=50></td>
	</tr>
__END__;
	}
	$out .= $htBefAddr;

	if ($doFixedAll) {
	    $htStreet = "<b>$strStreet</b>";
	    $htCity = "<b>$strCity</b>";
	    $htState = "<b>$strState</b>";
	    $htZip = "<b>$strZip</b>";
	    $htCtry = "<b>$htCountry</b>$htZone";
	    $htEmail = "<b>$strEmail</b> (".$arOptions['ht.after.email'].')';
	    $htPhone = "<b>$strPhone</b> (optional)";
	} else {
	    $htStreet = '<textarea name="'.$ksStreet.'" cols=50 rows=3>'.$strStreet.'</textarea>';
	    $htCity = '<input name="'.$ksCity.'" value="'.$strCity.'" size=20>';
	    $htState = '<input name="'.$ksState.'" value="'.$strState.'" size='.$lenStateInp.'>'.$htAftState;
	    $htZip = '<input name="'.$ksZip.'" value="'.$strZip.'" size=11>';
//	    $htCtry = "$htCountry - change shipping zone: $htShipCombo $htBtnRefresh";
	    $htCtry = $htCountry.$htZone;

	    $htEmail = '<input name="'.$ksEmail.'" value="'.$strEmail.'" size=30> '.$arOptions['ht.after.email'];
	    $htPhone = '<input name="'.$ksPhone.'" value="'.$strPhone.'" size=20> (optional)';
	}

	$out .= <<<__END__
<tr><td align=right valign=middle>Street Address<br>or P.O. Box:</td><td>$htStreet</td></tr>
<tr><td align=right valign=middle>City:</td><td>$htCity</td></tr>
<tr><td align=right valign=middle>$strStateLbl:</td><td>$htState</td></tr>
<tr><td align=right valign=middle>$strZipLbl:</td><td>$htZip</td></tr>
<tr><td align=right valign=middle>Country:</td><td>$htCtry</td></tr>
__END__;

	$out .= $htAftAddr;

// if this contact saves email and phone, then render those too:
	if ($doEmail) {
	    $out .= "<tr><td align=right valign=middle>Email:</td><td>$htEmail</td></tr>";
	}
	if ($doPhone) {
	    $out .= "<tr><td align=right valign=middle>Phone:</td><td>$htPhone</td></tr>";
	}

	return $out;
    } */

}
