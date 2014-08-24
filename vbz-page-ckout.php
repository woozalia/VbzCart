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
	return $this->pgShow = NULL;
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
    // ++ OBJECT ACCESS ++ //

    protected function SysLog() {
	return $this->Data()->Syslog();
    }
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

    // -- OBJECT ACCESS -- //
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
	$rcEv = $this->CartObj(FALSE)->CreateEvent($arEv);
	$this->formShow = $this->PageKey_forShow();
	$this->doNavCtrl = TRUE;
	$this->doBackBtn = TRUE;
	$this->doRefrBtn = FALSE;

	$ht = $this->ContHdr()
	  .$this->Content()
	  .$this->ContFtr();
	$this->Skin()->Content('main',$ht);
    }

    // -- WEB UI: FORM HANDLING -- //
    // ++ WEB UI: MAJOR CHUNKS ++ //

    /*----
      PURPOSE: Render top part of {form and outer table, including <td>}
      TODO: move this stuff into the skin, somehow
    */
    protected function ContHdr() {
	$out = "\n<!-- +".__METHOD__."() in ".__FILE__." -->\n"
	  .$this->StatusBar();

	$urlTarg = KWP_CKOUT;

	$out .= <<<__END__
<form method=post name=checkout action="$urlTarg">
<table class="form-block" id="page-ckout">
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
	$intColumns = 4;	// does this ever vary?

	// TODO: not sure about the logic here
	if (!$this->IsFormComplete() && ($this->PageKey_forShow() == $this->PageKey_forData())) {
	    // NOTE: I've been unable to get the icon to align nicely with the text without using a table.
	    $htMissing = "<tr><td colspan=$intColumns>\n<table>\n<tr><td><img src=".'"'.KWP_ICON_ALERT
	      .'"></td><td valign=middle><span class=alert style="background: yellow"><b>Please fill in the following</b>: '
	      .$this->MissingString()
	      ."</span></td></tr>\n</table>\n</td></tr>";
	} else {
	    $htMissing = '';
	}

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
	    $out .=
	      $htMissing
	      .'<tr><td colspan='.$intColumns.' align=center bgcolor=ffffff class=section-title>'
	      .'<input type=hidden name="'.KSQ_ARG_PAGE_DATA.'" value="'.$this->PageKey_forShow().'">'
	      .$htBackBtn.$htRefrBtn
	      .'<input type=submit name="btn-go-next" value="Next &gt;">';
	}

	//echo "\n</td></tr></table><!-- ".__LINE__." -->";

	$oCart = $this->CartObj_req();
	$idSess = $this->SessObj()->KeyValue();
	$idCart = $oCart->KeyValue();
	$idOrd = $oCart->Value('ID_Order');
	$sOrd = ($idOrd == 0)?'':' Order ID: <b>'.$idOrd.'</b>';
	if ($this->doNavCtrl) {
	    $htLine = NULL;
	} else {
	    $htLine = '<hr>';	// TODO: Make this a Skin function (again)
	}

	$out .= <<<__END__
</td></tr>
</table>
$htLine
<span class="footer-stats">Cart ID: <b>$idCart</b> Session ID: <b>$idSess</b>$sOrd</span>
</form>
__END__;

	return $out;
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
	$out .= "\n<!-- ".__FILE__." line ".__LINE__." -->";
	switch ($this->PageKey_forShow()) {
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
	    $this->doNavCtrl = FALSE;
	    $out .= $this->RenderConfirm();
	    break;
	  case KSQ_PAGE_RCPT:	// order receipt
	    $this->doNavCtrl = FALSE;
	    $out .= $this->ReceiveOrder();
	    break;
	  default:
// The normal shopping cart does not specify a target within the checkout sequence
// ...so show the first page which follows the cart page:
	    $out .= $this->RenderShipping();
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

    // -- WEB UI: MAJOR CHUNKS -- //

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
	if ($this->PageKey_forData_isSet()) {
	    $out = '';
	} else {
	    $sDesc = 'saving data from page '.$this->PageKey_forData();
	    // log as system event
	    $arEv = array(
	      clsSysEvents::ARG_CODE		=> 'CK-PG-REQ',	// checkout page request
	      clsSysEvents::ARG_DESCR_START	=> $sDesc,
	      clsSysEvents::ARG_WHERE		=> 'ckout.CapturePage',
	      clsSysEvents::ARG_PARAMS		=> 'pgData='.$this->PageKey_forData().'/pgShow='.$this->PageKey_forShow(),
	      );
	    //$oSysEv = $this->SysLog()->CreateEvent($arEv);
	    $rcCart = $this->CartObj_req();
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
	    // ok to back up, however
	    if ($formSeqShow > $this->formSeqData) {
		$this->PageKey_forShow($this->PageKey_forData());
	    }
	}
	return $out;
    }
  /* ****
    SECTION: cart-to-order conversion
  */
    /*----
      ACTION: Receive the order:
	* convert the cart data to an order record
	* send confirmation email
	* display order receipt page
      CALLED BY: $this->Content(), depending on which page is active
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
	$rcCart = $this->CartObj_req();	// throw an exception if no cart found

	assert('$rcCart->ID > 0;');

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
	    $this->rcCart->Update(array('WhenUpdated'=>'NOW()'));
	} else {
	    $idOrd = $tOrders->Create();

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
	$objOrd = $tOrders->GetItem($iOrdID);
	$this->ToOrder($objOrd);	// copy the actual data to the order record
	//$objOrd->CopyCart($objCart);	// copy the actual data to the order record

	// set Order ID in session object
	$arUpd = array(
	  'ID_Order'	=> $iOrdID,
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
	$rcCartData = $this->CartData();

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
	  "\n<!-- BEGIN RenderShipping() - ".__FILE__."  line ".__LINE__." -->\n"
	  .<<<__END__
$htHdr
<table class="form-block" id="shipping">
__END__;

	$oAddrShip = $rcCartData->RecipFields();

	if ($this->IsLoggedIn()) {
	    $htEnter = NULL;
	    // allow user to select from existing recipient profiles
	    $oUser = $this->UserRecord();
	    $rsCusts = $oUser->CustRecs();
	    if ($rsCusts->RowCount() > 0) {
		$doUseNew = $this->CartData()->IsRecipNewEntry();
		$htSelOld = $doUseNew?'':' checked';
		$htSelNew = $doUseNew?' checked':'';
		$htCusts = '<input type=radio name="info-source" value="old"'
		  .$htSelOld
		  .'><b>ship to an existing address:</b>'
		  .$rsCusts->Render_DropDown_Addrs('id_addr_ship')
		  //.'SQL:'.$rsCusts->sqlMake
		  ;
		$htEnter = '<input type=radio name="info-source" value="new"'
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
	$out .= $this->RenderAddress($oAddrShip,array('do.ship.zone'=>TRUE));
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
    public function RenderPayType() {
	$out =
	  "\n<!-- BEGIN ".__METHOD__."() - ".__FILE__." LINE ".__LINE__." -->"
	  .$this->SectionHeader('Payment type:')
	  ."\n<table class=\"form-block\" id=\"pay-type\">"
	  ;

	$isShipCardSame = $this->CartData()->IsShipToCard();
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
	throw new exception('Call CardMatchesShip() instead.');
    }
    protected function CardMatchesShip() {
	if (is_null($this->bCardMatchesShip)) {
	    $isCardAddrBlank = $this->CartData()->CardAddrBlank();
	    $doesMatch = TRUE;
	    if ($isCardAddrBlank) {
		$isShipCardSame = $this->CartData()->IsShipToCard();	// request to use shipping address for card billing address
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
		    $this->CartData()->IsShipToCard(FALSE);
		    $doesMatch = FALSE;	 // not blank and doesn't match
		}
	    }
	    $this->bCardMatchesShip = $doesMatch;
	}
	return $this->bCardMatchesShip;
    }
/* NOTE TO SELF: The problem right now is that we need to make sure the shipping address gets SAVED
      to the db when it gets copied to billing.
      I'm starting off trying to make this happen by moving the copying-phase into the CaptureShipping stage.
      That means $doesMatch needs to be saved to a class member, because RenderBilling() needs to know the result.
*/
    public function RenderBilling() {
	$objCartData = $this->CartData();

// copy any needed constants over to variables for parsing:
	$ksfCustCardNum = KSF_CART_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_CART_PAY_CARD_EXP;
	$ksfCardIsShip = KSF_SHIP_IS_CARD;

	$custCardNum = $this->CartData()->CardNumber();
	$custCardExp = $this->CartData()->CardExpiry();
	$isShipCardSame = $this->CartData()->IsShipToCard();	// request to use shipping address for card billing address
	$doesShipCardMatch = $this->CardMatchesShip();

	$out = $this->SectionHeader('Payment information:')
	  ."\n<table id=form-billing>";

	if ($this->IsLoggedIn()) {
	    $htEnter = NULL;
	    // allow user to select from existing recipient profiles
	    $oUser = $this->UserRecord();
	    $rsCusts = $oUser->CustRecs();
	    $htEnter = NULL;
	    if ($rsCusts->RowCount() > 0) {
		$doUseNew = $this->CartData()->IsBuyerNewEntry();
		$htSelOld = $doUseNew?'':' checked';
		$htSelNew = $doUseNew?' checked':'';
		$htCusts = '<input type=radio name="info-source" value="old"'
		  .$htSelOld
		  .'><b>pay with an existing card:</b>'
		  .$rsCusts->Render_DropDown_Cards('id_card')
		  //.'SQL:'.$rsCusts->sqlMake
		  ;
		$htEnter = '<input type=radio name="info-source" value="new"'
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

	$custShipIsCard = $this->CartData()->IsShipToCard();
	//$custShipToSelf = $this->CartData()->ShipToSelf();	// what was this used for? probably something fuzzy.

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

	$ofCont = $this->CartData()->BuyerFields();
	$out .= $this->RenderAddress($ofCont,array('do.ship.zone'=>FALSE));

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

	$isShipCard = $objCD->IsShipToCard();
	//$isShipSelf = $objCD->IsShipToSelf();
	$strCustShipMsg = $objCD->ShipMsg();
	$custCardNum = $objCD->CardNumber();
	$custCardExp = $objCD->CardExpiry();
	$isShipCardReally = $this->CardMatchesShip();

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

	$out .= $this->RenderAddress($objCD->RecipFields(),array('do.ship.zone'=>TRUE));

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
	/*
	if ($isShipSelf) {
	    $this->strInsteadOfCont = 'Recipient contact information <b>same as buyer\'s -- shipping to self</b>';
	}
	*/
	// TODO 2012-05-21: this probably won't look right, and will need fixing
	//	also, are strInsteadOf* strings ^ used in confirmation?
	$out .= $this->RenderAddress($objCD->BuyerFields(),array('do.ship.zone'=>FALSE));

	if ($iEditable) {
	    $sPgName = KSQ_ARG_PAGE_DATA;
	    $sPgShow = $this->PageKey_forShow();
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

	    $hrefForSpam = '<a href="'.KWP_WIKI_PUBLIC.'Anti-Spam_Policy">';
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

/* 2013-11-23 I don't think this is being used anymore
	    if ($this->IsLoggedIn()) {
		$out .= $this->Render_DropDown_Addresses();
	    }
*/

	    //$out .= $iAddr->Render($this,$arOpts);
	    $out .= $this->RenderPerson($iAddr,$arOpts);
	}

	//$out .= "\n<tr><!-- ".__FILE__.' line '.__LINE__.' -->';
	if (isset($this->msgAfterAddr)) {
	    $out .= '<td colspan=2>'.$this->msgAfterAddr.'</td>';
	}
	$out .= "\n<!-- -".__METHOD__."() in ".__FILE__." -->\n";

	return $out;
    }
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
	$ksName		= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_NAME);
	$ksStreet	= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_STREET);
	$ksCity		= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_CITY);
	$ksState	= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_STATE);
	$ksZip		= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_ZIP);
	$ksCountry	= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_COUNTRY);
	$ksEmail	= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_EMAIL);
	$ksPhone	= $oPerson->NameForSuffix(_KSF_CART_SFX_CONT_PHONE);

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
	    $this->CartObj(FALSE)->LogEvent('!FLD','Missing form field: '.$iName);
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
	$rcCD = $this->CartData();
	$tCD = $rcCD->Table();
	$out = $tCD->CaptureData($this->PageKey_forData());
	$this->ReconcileCardAndShppg();	// not sure if this puts the flag in the right place, but it's a start. TODO: verify.
	$tCD->SaveCart();	// update the db from form data

	$objShipZone = $this->CartObj_req()->ShipZoneObj();

	//$custIntype	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_INTYPE);
	//$custChoice	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_CHOICE);

	$custName	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_NAME);
	$custStreet	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_STREET);
	//$custState	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_STATE);
	$custCity	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_CITY);
	$custCountry	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_COUNTRY);
	$custEmail	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_EMAIL);

	$shipZone	= $rcCD->FieldValue_forIndex(KI_CART_SHIP_ZONE);
	  $objShipZone->Abbr($shipZone);
	//$custShipToSelf	= $rcCD->FieldValue_forIndex(KI_CART_RECIP_IS_BUYER);
	//$custShipIsCard	= $rcCD->FieldValue_forIndex(KI_CART_SHIP_IS_CARD);
	//$custZip	= $this->GetFormItem(KSF_CART_RECIP_ZIP);
	//$custPhone	= $this->GetFormItem(KSF_CART_RECIP_PHONE);
	//$custMessage	= $this->GetFormItem(KSF_SHIP_MESSAGE);

	// 2014-07-28 is this necessary? Reloading changed data?
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
	$out = $objCD->CaptureData($this->PageKey_forData());
	$objCD->SaveCart();	// update the db from form data

	$custCardNum	= $this->GetFormItem(KSF_CART_PAY_CARD_NUM);
	$custCardExp	= $this->GetFormItem(KSF_CART_PAY_CARD_EXP);

	# check for missing data
	$this->CheckField("card number",$custCardNum);
	$this->CheckField("expiration date",$custCardExp);

	if (!$this->CartData()->IsShipToCard()) {
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

	if (!$this->CartData()->IsShipToSelf()) {
	    $custEmail	= $this->GetFormItem(KSF_CART_BUYER_EMAIL);
	    $custPhone	= $this->GetFormItem(KSF_CART_BUYER_PHONE);
	}
	$custCheckNum	= $this->GetFormItem(KSF_CART_PAY_CHECK_NUM);
    }
//-----
    protected function HtmlEditLink($iPage,$iText='edit',$iPfx='[',$iSfx=']') {
	$out = $iPfx.'<a href="?'.KSQ_ARG_PAGE_DEST.'='.$iPage.'">'.$iText.'</a>'.$iSfx;
	return $out;
    }
    private function Order() {
	return $this->CartObj_req()->Order();
    }

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
    protected function IsNewEntry() {
	switch ($this->PageKey_forShow()) {
	  case KSQ_PAGE_CART:	return FALSE;
	  case KSQ_PAGE_SHIP:	return $this->CartData()->IsRecipNewEntry();
	  case KSQ_PAGE_PAY:	return $this->CartData()->IsBuyerNewEntry();
	  case KSQ_PAGE_CONF:	return FALSE;
	  case KSQ_PAGE_RCPT:	return FALSE;
	  default: throw new exception('Does this ever happen?');
	}
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
}
