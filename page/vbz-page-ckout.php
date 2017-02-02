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

// ** 2016-11-01 Recreating these with what seem like sane values; not sure what happened to the originals.
define('KSQ_ARG_PAGE_DEST','dest');
define('KSQ_ARG_PAGE_DATA','data');
// these, I think, define the order in which checkout pages are presented
define('KI_SEQ_CART',0);
define('KI_SEQ_SHIP',1);
define('KI_SEQ_PAY',2);
define('KI_SEQ_CONF',3);
define('KI_SEQ_RCPT',4);

// ** 2016-11-02 moved here from [site config folder]/site-ckout.php:

// http query values
define('KSQ_PAGE_CART','cart');	// shopping cart
define('KSQ_PAGE_SHIP','ship');	// shipping page
define('KSQ_PAGE_PAY','pay');	// payment page
define('KSQ_PAGE_CONF','conf');	// customer confirmation of order
define('KSQ_PAGE_RCPT','rcpt');	// order receipt
// -- optional pages
define('KSQ_PAGE_LOGIN','login');	// user login/profile page
define('KSQ_PAGE_USER','user');	// user login/profile page

// if no page specified, go to the shipping info page (first page after cart):
define('KSQ_PAGE_DEFAULT',KSQ_PAGE_SHIP);

/*=====
  CLASS: vcPageCkOut
  PURPOSE: subclass for generating checkout pages
*/
class vcPageCkout extends vcPageAdmin {
    private $arMissing;

    private $doNavCtrl;	// hide navigation buttons on final confirmation page

    // ++ SETUP ++ //

    public function __construct() {
	parent::__construct();
	$oSkin = $this->GetSkinObject();
	$oSkin->Sheet('ckout');
	$oSkin->SetPageTitle('checkout');
	$this->arMissing = array();
	$this->PageKey_forShow_clear();
	//$this->PageKey_forData_clear();
	$this->bCardMatchesShip = NULL;
    }

    // -- SETUP -- //
    // ++ CEMENTING ++ //

    protected function BaseURL() {
	return KURL_CKOUT;
    }
    protected function PreSkinBuild() {
	// this may not be needed
    }
    protected function PostSkinBuild() {
	// this may not be needed
    }

    // -- CEMENTING -- //
    // ++ STATUS ACCESS ++ //

    /*----
      ACTION: Returns the string that determines which page should be displayed.
	If not expliticly set, returns a default which must have been set earlier.
    */
    public function PageKey_forShow($sSet=NULL) {
	if (!is_null($sSet)) {
	    $this->pgShow = $sSet;
	}
	if (is_null($this->pgShow)) {
	    $this->pgShow = $this->PageKey_forShow_default();
	}
	return $this->pgShow;
    }
    public function PageKey_forShow_clear() {
	$this->pgShow = NULL;
    }
    private $sPgShow_default;
    protected function PageKey_forShow_default($sSet=NULL) {
	if (!is_null($sSet)) {
	    $this->sPgShow_default = $sSet;
	}
	return $this->sPgShow_default;
    }
    /*----
      HISTORY:
	2016-03-27 Making this read-only - it gets the string from $_POST by itself,
	  and does any necessary defaulting
	  Also making protected until we know who else needs it.
    */
    protected function PageKey_forData() {
	// KSQ_PAGE_CART is the default - it's the only page that doesn't identify itself
	return fcHTTP::Request()->GetText(KSQ_ARG_PAGE_DATA,KSQ_PAGE_CART);
    }

    // -- STATUS ACCESS -- //
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
    }
    protected function RenderBillingPage() {
	$rcCart = $this->CartRecord();
	return $rcCart->RenderBillingPage();
    }
    /*----
      ACTION: Render the form which lets the user choose how to pay
    */
    protected function RenderPayTypeSection() {
    throw new exception('2016-04-16 Does this still get called? Is it still needed?');
	$rcCart = $this->CartRecord();
	return $rcCart->RenderPayTypeSection();
    }
    /*----
      ACTION: Render the "confirm this order" page from the Order record.
    */
    protected function RenderConfirm() {
	//$out = $this->RenderOrder(TRUE);
	$rcCart = $this->CartRecord();

	// This renders the confirm page using Cart data, but we normally want Order data. Keeping for reference, but commented out. 2015-09-04
	//$out = $rcCart->RenderConfirm_page();

	// Render the confirm page from Order data:
	$rcOrd = $rcCart->OrderRecord_orConvert();	// create the order record if needed, or get existing one
	$out =
	  $rcOrd->RenderConfirm()
	  .vcrOrder::RenderConfirm_footer()
	  ;
	return $out;
    }
    // ACTION: Do whatever needs to be done to make the order officially received.
    protected function ReceiveOrder() {
	$rcCart = $this->CartRecord();
	$rcOrd = $rcCart->OrderRecord_orDie();	// get existing order record
	$rcOrd->MarkAsPlaced();
    }
    /*----
      ACTION: Receive the order and render order receipt:
	* convert the cart data to an order record
	* send confirmation email
	* display order receipt page
      CALLED BY: $this->RenderContent(), depending on which page is active
      TODO: (2016-08-08) Perhaps the emailing should be a separate function (EmailReceipt()).
    */
    protected function RenderReceipt() {
	$rcCart = $this->CartRecord();
	$rcOrd = $rcCart->OrderRecord_orDie();	// get existing order record
	
	$arVars = $rcOrd->TemplateVars();		// fetch variables for email
	$arHdrs = $rcOrd->EmailParams($arVars);		// fill in email template with variables

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
	
	$out = $rcOrd->RenderReceipt();
	return $out;
    }
    public function RenderReceipt_OLD() {
	throw new exception('Is this still being called? The order has already been converted at this point.');
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
    // ++ WEB UI: PAGE ELEMENTS ++ //

    // PUBLIC so Cart forms can use it
    public function RenderLogin_Controls() {
	$htLoginCore = $this->RenderLogin();
	$urlLogin = KURL_LOGIN;
	$out = <<<__END__
  <b>Shopped here before?</b>
    $htLoginCore
    or <a href="$urlLogin" title="login options: reset password, create account">more options</a>.
__END__;

	return $out;
    }
    /*----
      PUBLIC so Cart forms can use it
      TODO: Make username a link to user config/profile page
    */
    public function RenderLogout_Controls() {
	$rcUser = $this->UserRecord();
	$sUser = $rcUser->UserName();
	$htLogout = $this->GetSkinObject()->RenderLogout();
	$out = "You are logged in as <b>$sUser</b>. (You can $htLogout if you prefer.)";
	return $out;
    }
    // -- WEB UI: PAGE ELEMENTS -- //
    // ++ FORM / DATA ACCESS ++ //

    public function GetFormItem($iName) {
	if (isset($_POST[$iName])) {
	    return $_POST[$iName];
	} else {
	    $this->CartRecord()->LogEvent('!FLD','Missing form field: '.$iName);
	    return NULL;
	}
    }

    // -- FORM / DATA ACCESS -- //
    // ++ FORM DISPATCH ++ //
    
    /*++
      Each of these functions dispatches to page-specific methods.
	* ProcessPage() dispatches to CapturePage() and RenderPage()
	  * CapturePage() dispatches to page-specific Capture*() methods, and also
	    sets the Form Alert to show any missing fields.
	  * RenderPage() dispatches to RenderContent()
	    * RenderContent() dispatches to page-specific Render*() methods
    */

    protected function RenderPage() {
	$out = $this->ContHdr()
	  .$this->RenderContent()
	  .$this->ContFtr()
	  ;
	return $out;
    }
    /*----
      PURPOSE: core form processing & page rendering
      ACTIONS:
	* dispatches to ParseInput_Login() & HandleInput_Login() to render/handle login controls
	* logs a Cart event to record which page was displayed
	* sets PageKey_forShow()
	* dispatches to RenderPage() to render the page
    */
    protected function ProcessPage() {
    
	$this->ParseInput_Login();	// check for user-login activity
	$this->HandleInput_Login();	// not sure why this is separate...

	// Attempt to set page-key-for-show from input form:
	if ($this->IsLoginRequest()) {
	    // this is a login attempt - stay on the same page
	    $this->PageKey_forShow($this->PageKey_forData());
	} else {
	    $gotPgDest = FALSE;
	    $gotPgSrce = FALSE;
	    $sKey = fcHTTP::Request()->GetText(KSQ_ARG_PAGE_DEST);
	    if (is_null($sKey)) {
		// 2016-04-03 it's possible we could just set the key here...
		$this->PageKey_forShow_default(KSQ_PAGE_SHIP);
	    } else {
		$gotPgDest = TRUE;
		$this->PageKey_forShow($sKey);
	    }
	}
	// get actual page to display
	$sShow = $this->PageKey_forShow();
	
	$arEv = array(
	  fcrEvent::KF_CODE		=> 'PG-IN',
	  fcrEvent::KF_DESCR_FINISH	=> 'showing page "'.$sShow.'"',
	  fcrEvent::KF_WHERE		=> 'HandleInput()',
	  );
	$rcCart = $this->CartRecord_current_orNull();
	if (is_null($rcCart)) {
	    $sMsg = $this->AppObject()->MessagesString();
	    if (is_null($sMsg)) {
		// 2016-04-24 This can happen when browser fingerprint changes.
		//throw new exception('In checkout with no current cart set, but there are no error messages.');
		$sMsg = "You don't seem to have a shopping cart yet, so you can't check out. If you had one before, your IP address or browser version may have changed.";
		fcHTTP::DisplayOnReturn($sMsg);
		fcHTTP::Redirect(KWP_CART_REL);
	    } else {
		fcHTTP::DisplayOnReturn($sMsg);
		fcHTTP::Redirect(KWP_CART_REL);
	    }
	}
	$rcEv = $rcCart->CreateEvent($arEv);
	$this->doBackBtn = TRUE;
	$this->doRefrBtn = FALSE;
	
	$this->CapturePage();
	$this->DetectPageRequest();
	$this->HandlePageRequest();

	$this->GetSkinObject()->Content('main',$this->RenderPage());
    }

    /*----
      ACTION: receives form input and determines where to save it
	enforces form rules -- mainly, don't allow advance until all required fields are filled in
      OUTPUT:
	* logs Cart event recording which page's form was received
	* sets $this->PageKey_forShow()
	* sets $this->DidRequestAdvance()
      REQUIRES: Input needs to have been parsed so we know what page's data we're capturing
    */
    protected function CapturePage() {
	$sDesc = 'saving data from page '.$this->PageKey_forData();

	// log as system event
	$arEv = array(
	  fcrEvent::KF_CODE		=> 'CK-PG-REQ',	// checkout page request
	  fcrEvent::KF_DESCR_START	=> $sDesc,
	  fcrEvent::KF_WHERE		=> 'ckout.CapturePage',
	  fcrEvent::KF_PARAMS		=> array(
	    'pgData'	=> $this->PageKey_forData(),
	    'pgShow'	=> $this->PageKey_forShow()
	    )
	  );
	$rcCart = $this->CartRecord();
	$rcSysEv = $rcCart->CreateEvent($arEv);
	$sKey = $this->PageKey_forData();
	//echo "PAGE KEY FOR DATA = [$sKey]<br>";
	switch ($sKey) {
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
	    $this->formSeqData = NULL;
	    // more likely to be a hacking attempt than an internal error:
	    $out = 'Cannot save data from unknown page: ['.$this->PageKey_forData().']';
	    //$rcCart->LogCartEvent('UNKF',$sDesc);
	    $arEv = array(
	      fcrEvent::KF_DESCR_FINISH	=> 'page not recognized',
	      fcrEvent::KF_IS_ERROR	=> TRUE,
	      );
	    $rcSysEv->Finish($arEv);
	}

	return $out;
    }
    /*----
      ACTION: Checks form input to see which (if any) navigation button was pressed,
	and therefore which form is being requested for display next.
	
	Does not enforce rules about whether to advance or not.
    */
    protected function DetectPageRequest() {
	$gotPgDest = FALSE;
	if (fcHTTP::Request()->GetBool(KSQ_ARG_PAGE_DEST)) {
	    $gotPgDest = TRUE;
	    $this->PageKey_forShow(fcHTTP::Request()->GetText(KSQ_ARG_PAGE_DEST));
	}
	if (!$gotPgDest) {
	// destination page unknown, so calculate it from data/source page:
	    if (fcHTTP::Request()->GetBool('btn-go-prev')) {
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
	    } elseif (fcHTTP::Request()->GetBool('btn-go-next')) {
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
		    break;
		  default:	// not sure how we got here; use default
		    $this->PageKey_forShow(KSQ_PAGE_DEFAULT);
		}
	    } elseif (fcHTTP::Request()->GetBool('btn-go-same')) {
		$this->PageKey_forShow($this->PageKey_forData());
	    } elseif (fcHTTP::Request()->GetBool('btn-go-order')) {
		$this->PageKey_forShow(KSQ_PAGE_RCPT);		// receipt page - submits order too
	    } else {
		$this->PageKey_forShow(KSQ_PAGE_DEFAULT);	// default page to display
	    }
	}
    }
    /*----
      ACTION: Based on what page the user is *requesting* (as determined by DetectPageRequest())
	and the current conditions (mainly: was the previous page's form filled out properly?),
	determine what page to display next.
    */
    protected function HandlePageRequest() {
	// figure out if user is trying to advance, and if so whether to allow it:
	$sPgShow = $this->PageKey_forShow();
	
	// TODO: why don't we just say $formSeqShow = $sPgShow, after checking for a valid $sPgShow?
	switch ($sPgShow) {
	  case KSQ_PAGE_CART:	$formSeqShow = KI_SEQ_CART;	break;
	  case KSQ_PAGE_SHIP:	$formSeqShow = KI_SEQ_SHIP;	break;
	  case KSQ_PAGE_PAY:	$formSeqShow = KI_SEQ_PAY;	break;
	  case KSQ_PAGE_CONF:	$formSeqShow = KI_SEQ_CONF;	break;
	  case KSQ_PAGE_RCPT:	$formSeqShow = KI_SEQ_RCPT;	break;
//	  default: $formSeqShow = 0;	break;
	  default: throw new exception("Page key is [$sPgShow]. How did we get here?");
	}
	//echo "FORMSEQDATA=[".$this->formSeqData."] FORMSEQSHOW=[$formSeqShow]<br>";
	$didRequestAdvance = !is_null($this->formSeqData) && ($formSeqShow > $this->formSeqData);
	//$this->DidRequestAdvance($didRequestAdvance);

	$arMissed = $this->CartRecord()->GetMissingArray();
	if (count($arMissed) > 0) {
	    $okToAdvance = FALSE;
	    if ($didRequestAdvance) {
		// The user tried to advance, so alert user to any missing fields:
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
		  //echo 'ARMISSED:'.fcArray::Render($arMissed);
		  //echo 'COUNT: '.count($arMissed).'<br>';
		//die('MISSED STUFF: '.$sMsg);
		$this->FormAlertMessage($sMsg);
	    }
	} else {
	    // For now, we can move on if there aren't any fields missing.
	    $okToAdvance = TRUE;
	    // Later there might be other reasons not to advance.
	}
	
	//echo "REQ ADVANCE?[$didRequestAdvance] OK TO ADVANCE?[$okToAdvance]";
	if ($didRequestAdvance && !$okToAdvance) {
	    // user tried to advance when it wasn't ok -- stay on the same page
	    $this->PageKey_forShow($this->PageKey_forData());
	}
    }
    /*----
      ACTION: 
      OUTPUT:
	$doBackBtn: if TRUE, show the BACK navigation button
	$doRefrBtn:
	$doNavCtrl:
    */
    protected function RenderContent() {
	$out = NULL;
	$this->doNavCtrl = TRUE;	// default
	$sWhere = __METHOD__.'() in '.__FILE__;

	$out .= "\n<!-- vv $sWhere vv -->";

	$sKeyShow = $this->PageKey_forShow();
	switch ($sKeyShow) {
	  case KSQ_PAGE_CART:	// shopping cart
	    $this->doBackBtn = FALSE;
	    $this->doRefrBtn = TRUE;
	    $out .= $this->RenderCart();
	    break;
	  case KSQ_PAGE_SHIP:	// shipping information
	    $out .= $this->RenderShippingPage();
	    break;
	  case KSQ_PAGE_PAY:	// billing information
	    $out .= $this->RenderBillingPage();
	    break;
	  case KSQ_PAGE_CONF:	// confirm order
	    $this->doNavCtrl = FALSE;
	    $out .= $this->RenderConfirm();
	    break;
	  case KSQ_PAGE_RCPT:	// order receipt
	    $this->doNavCtrl = FALSE;
	    $this->ReceiveOrder();		// mark order as received
	    $out .= $this->RenderReceipt();	// display receipt & send by email
	    break;
	  default:
// The normal shopping cart does not specify a target within the checkout sequence
// ...so show the first page which follows the cart page:
	    $out .= $this->RenderShippingPage();
	}
	$out .= "\n<!-- ^^ $sWhere ^^ -->";
	return $out;
    }
    
    // -- FORM DISPATCH -- //
    // ++ SPECIFIC PAGES ++ //
    
    public function CaptureCart() {
	return $this->Data()->Carts()->HandleCartFormInput();	// check for any cart data changed
    }
    public function CaptureShipping() {
	$rcCart = $this->CartRecord();
	$rcCart->CaptureShippingPage();
    }
    public function CaptureBilling() {
	$rcCart = $this->CartRecord();
	$rcCart->CaptureBillingPage();
    
    /* 2016-04-19 old version
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
	$custCheckNum	= $this->GetFormItem(KSF_CART_PAY_CHECK_NUM);
	*/
    }

    // -- SPECIFIC PAGES -- //
    // ++ WEB UI: MAJOR ELEMENTS ++ //

    /*----
      PURPOSE: Render top part of {form and outer table, including <td>}
      TODO: move this stuff into the skin, somehow
      CALLED BY: $this->HandleInput()
    */
    protected function ContHdr() {
	$sWhere = __METHOD__."() in ".__FILE__;
	$out = "\n<!-- vv $sWhere vv -->\n"
	  .$this->StatusBar()
	  ;

	$htNav = $this->RenderNavButtons();
	$urlTarg = KURL_CKOUT;

	$out .= <<<__END__

<form method=post name=checkout action="$urlTarg">
<table class="form-block" id="page-ckout">
$htNav
<tr>
<td>
<!-- ^^ $sWhere ^^ -->
__END__;
	return $out;
    }
    /*----
      PURPOSE: Stuff to close out the page contents
      ACTION: Close table row opened in RenderContentHdr(), display standard buttons, close outer table and form
    */
    protected function ContFtr() {
	$sWhere = __METHOD__."() in ".__FILE__;;
	$out = "\n<!-- vv $sWhere vv -->\n"
	  . $this->RenderNavButtons()
	  ;

	$oCart = $this->CartRecord();
	$idSess = $this->GetSessionRecord()->GetKeyValue();
	$idCart = $oCart->GetKeyValue();
	$idOrd = $oCart->GetOrderID();
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

<!-- ^^ $sWhere ^^ -->

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
    // CALLED BY: $this->ContHdr()
    protected function StatusBar() {
	$oNav = $this->CreateNavBar();
	// call this after CreateNavBar so child classes can insert stuff first
	$sPage = $this->PageKey_forShow();
	$oNav->States($sPage,1,3);
	$oNav->Node($sPage)->State(2);
	return $this->GetSkinObject()->RenderNavbar_H($oNav);
    }

    // -- WEB UI: MAJOR ELEMENTS -- //
    // ++ WEB UI: UI OBJECTS ++ //

    protected function CreateNavBar() {
	$oNav = new fcNavbar_flat();
	  $oi = new fcNavText($oNav,KSQ_PAGE_CART,'Cart');
	  $oi = new fcNavText($oNav,KSQ_PAGE_SHIP,'Shipping');
	  $oi = new fcNavText($oNav,KSQ_PAGE_PAY,'Payment');
	  $oi = new fcNavText($oNav,KSQ_PAGE_CONF,'Final Check');
	  $oi = new fcNavText($oNav,KSQ_PAGE_RCPT,'Receipt');
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
		  'params'	=> '\cart='.$rcCart->GetKeyValue().'\ord='.$idOrd,
		  'where'	=> __METHOD__,
		  );
		$rcEv1 = $rcCart->StartEvent($arEv);
	    } else {
		// order has not been placed yet -- so let's assume customer wants to modify it
		$doNewOrd = FALSE;
		$arEv = array(
		  'code'	=> 'ORU',
		  'descr'	=> 'ID_Order already set in cart: updating existing order',
		  'params'	=> '\cart='.$rcCart->GetKeyValue().'\ord='.$idOrd,
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
	  '|ord ID='.$idOrd.'|cart ID='.$rcCart->GetKeyValue(),
	  'Converted cart to order; SQL='.$sqlSQL,
	  'C>O',FALSE,FALSE);

	// log completion of the outer Cart event
	$rcEv1->Finish();

	return $rcOrd;	// this is used by the checkout process
    }

    // -- ACTIONS -- //

}
