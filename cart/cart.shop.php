<?php
/*
  PURPOSE: shopping cart classes: shopping UI
  HISTORY:
    2016-03-07 Split off some methods from clsShopCart[s] (later renamed vctShopCart[s])
*/
class vctCarts_ShopUI extends vctShopCarts {
    use ftFrameworkAccess;
    use ftLoggableTable;

    // ++ SETUP ++ //
/* 2016-10-17 rearranged
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcrCart_ShopUI');
    }
*/    
    // -- SETUP -- //
    // ++ CEMENTING ++ //
    
    public function GetActionKey() {
	return 'shop';
    }
    
    // -- CEMENTING -- //
    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'vcrCart_ShopUI';
    }

    // -- OVERRIDES -- //
    // ++ SHOPPING UI ++ //

    /*----
      ACTION: Check form input to see if anything needs to be done to the current Cart.
      ASSUMES: We have form input that actually requires a cart record. (Caller should check this;
	don't call if cart not required.)
      HISTORY:
	2013-11-09 moved from clsShopCart to clsShopCarts (later renamed vctShopCarts)
    */
    public function HandleCartFormInput() {
	$rcCart = $this->CartRecord_required_allow_invalid();
	if (!is_object($rcCart)) {
	    throw new exception('Could not retrieve or create cart.');
	}
	$rcCart->HandleFormInput();
    }
    /*----
      ACTION: Renders the current cart
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

    // -- SHOPPING UI -- //

}
class vcrCart_ShopUI extends vcrShopCart {
    use ftFrameworkAccess;
    use ftSaveableRecord;

    // ++ CLASS NAMES ++ //

    /* 2017-01-16 not needed unless EventTable() is.
    protected function EventsClass() {
	return $this->AppObject()->EventsClass();
    } */
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    /* 2017-01-16 Document usage, if any.
    protected function EventTable() {
	return $this->GetConnection()->MakeTableWrapper($this->EventsClass());
    } */
    
    // -- TABLES -- //
    // ++ INPUT ++ //

    /*----
      ACTION: Looks at the received form data to see if a cart should be required.
	For now, I'm assuming that any in-cart functions (such as delete, recalculate,
	etc.) do *not* require a new cart because the cart would already exist,
	but the rules can be fine-tuned as needed.
    */
    static public function FormInputNeedsCart() {
	$yes = array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
	return $yes;
    }
    // ACTION Do whatever needs to be done to the current cart based on the form input
    public function HandleFormInput() {
// check for input
	// - buttons
	$doAddItems	= array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
	$doRecalc	= array_key_exists(KSF_CART_BTN_RECALC,$_POST);
	$doCheckout	= array_key_exists(KSF_CART_BTN_CKOUT,$_POST);
	// - other items
	$sShipZone	= fcArray::Nz($_POST,KSF_CART_SHIP_ZONE);
	$doShipZone	= !is_null($sShipZone);
	$sDelete	= fcArray::Nz($_GET,KSF_CART_DELETE);
	$doDelete	= !is_null($sDelete);
	
	$isCart = ($doRecalc || $doCheckout);	// there must already be a cart under these conditions
	$doItems = ($doAddItems || $doRecalc);	// if true, there are items to process
	$doRefresh = FALSE;	// should we refresh the page (clean URL / remove POST)?

	// receive selected shipping zone from form
	if ($doShipZone) {
	    if ($sShipZone != $this->GetZoneCode()) {
		// if it has changed:
		$this->SetZoneCode($sShipZone);	// set it in memory
		//$this->UpdateZone();		// save change back to db
		$this->Save();			// save change back to db
	    }
	}
	
	// check for specific actions
	if ($doItems) {
	    if ($isCart) {
		// zero out all items, so only items in visible cart will be retained:
		$this->ZeroAll();
	    }
	    // get the list of items posted
	    $arItems = $_POST[KSF_CART_ITEM_ARRAY_NAME];
	    // add each non-empty item
	    foreach ($arItems as $key => $val) {
		if (!empty($val)) {
		    $nVal = (int)0+$val;
		    $sCatNum = $this->GetConnection()->Sanitize($key);
		    $this->AddItem($sCatNum,$nVal);
		}
	    } // END for each item
	    $doRefresh = TRUE;
	// END do add items
	} elseif ($doDelete) {
	    if ($sDelete == KSF_CART_DELETE_ALL) {
		$this->LogEvent('clr','voiding cart');
		//$this->ID = -1;
		$this->SessionRecord()->DropCart();
	    } elseif (is_numeric($sDelete)) {
		$idLine = $sDelete;
		$this->DelLine($idLine);
		$idCart = $rcCart->GetKeyValue();
		$this->LogEvent('del',"deleting line ID $idLine");
	    } else {
		throw new exception('Received unexpected delete value "'.$sDelete.'".');
	    }
	    $doRefresh = TRUE;
	}
	
	if ($doCheckout) {
	    $this->LogCartEvent('ck1','going to checkout');
	    clsHTTP::Redirect(KURL_CKOUT);
	    $this->LogCartEvent('ck2','sent redirect to checkout');
	} elseif ($doRefresh) {
	    $this->UpdateTimestamp();	// record that the cart was updated
	    // if we changed anything, redirect to a clean URL with no POST data:
	    clsHTTP::Redirect(KWP_CART_REL);
	}
    }
    
    // -- INPUT -- //
    // ++ OUTPUT ++ //

    /*----
      FUNCTION: DisplayObject()
      CALLED BY this.Render() (below)
    */
    private $oPainter;
    protected function DisplayObject($bEditable) {
	if (empty($this->oPainter)) {
	    $oZone = $this->ShipZoneObject();
	    if ($bEditable) {
		$oPainter = new vcCartDisplay_full_shop($oZone);
	    } else {
		$oPainter = new vcCartDisplay_full_ckout($oZone);
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
	    $this->oPainter = $oPainter;
	}
	return $this->oPainter;
    }
    /*----
      RETURNS: HTML rendering of cart, including current contents and form controls
      CALLED BY table.RenderCart()
      HISTORY:
	2013-11-10 Significant change to assumptions: A cart object now only exists
	  to represent a cart record in the database. Any functions that need to work
	  when there is no record are now handled by the cart table object.
    */
    public function Render($bEditable) {
	$id = $this->GetKeyValue();
	$oSkin = $this->SkinObject();
	$oSkin->AddFooterStat('cart',$id);
	$oSkin->AddFooterStat('sess',$this->GetSessionID());
	if ($this->HasLines()) {
	    $oPainter = $this->DisplayObject($bEditable);
	    $out = $oPainter->Render();
	} else {
	    $sMsg = "You have a cart (#$id), but it's empty. We're not quite sure how that happened.";
	    //$out = "<font size=4>$sMsg</font>;
	    $out = $oSkin->WarningMessage($sMsg);
	    $arEv = array(
	      fcrEvent::KF_CODE		=> 'disp',
	      fcrEvent::KF_DESCR_START	=> 'displaying cart - empty; zone '.$this->GetZoneCode(),
	      fcrEvent::KF_WHERE		=> __FILE__.' line '.__LINE__,
	      fcrEvent::KF_IS_ERROR		=> TRUE,
	      );
	    $this->CreateEvent($arEv);
	}
	return $out;
    }
    
    //++checkout page helpers++//
    
    private $arMissed;
    protected function AddMissing(array $arMissed=NULL) {
	if (is_null($arMissed)) {
	    throw new exception('Who is calling this with a NULL?');
	}
//    protected function AddMissing(array $arMissed) {
	$this->arMissed = array_merge($this->GetMissingArray(),$arMissed);
    }
    public function GetMissingArray() {
	if (empty($this->arMissed)) {
	    $this->arMissed = array();
	}
	return $this->arMissed;
    }
    
    //--checkout page helpers--//
    //++checkout page i/o++//

    /*
      NOTES:
	* *rendering* a page includes *loading* any existing values so they can be displayed.
	* *capturing* a page includes *loading* any existing values so they are not overwritten by blanks.
	  This has to be done by the objects, though, since the field objects are not yet defined here.
    */
    
    public function RenderShippingPage() {
	$oCDMgr = $this->FieldsManager();
	$oCDMgr->FetchBlob();

	$oCD_Buyer = $oCDMgr->BuyerObject();
	$oCD_Recip = $oCDMgr->RecipObject();

	$oPage = $this->PageObject();
	$out =
	  $oPage->SectionHeader('Contact information:')
	  .$oCD_Buyer->RenderContact(TRUE)	// edit email/phone
	  .$oPage->SectionHeader('Shipping information:')
	  .$oCD_Recip->RenderShipping(TRUE)	// edit shipping address / instructions
	  ;
	return $out;
    }
    public function CaptureShippingPage() {
	$oCDMgr = $this->FieldsManager();
	$oCD_Buyer = $oCDMgr->BuyerObject();
	$oCD_Recip = $oCDMgr->RecipObject();
	
	$oCD_Buyer->CaptureContact();	// email/phone
	$oCD_Recip->CaptureShipping();	// shipping address / instructions

	$this->AddMissing($oCD_Buyer->GetMissingArray());
	$this->AddMissing($oCD_Recip->GetMissingArray());
	
	// calculate resulting blob
	$oCDMgr->FetchBlob();
	//echo 'BLOB AFTER FETCH: '.$oCDMgr->RenderBlob();
	$oCDMgr->UpdateBlob($oCD_Buyer);
	$oCDMgr->UpdateBlob($oCD_Recip);
	$oCDMgr->StoreBlob();
	//echo 'SHIPPING VALUES:'.fcArray::Render($this->Values());
	//echo 'SHIPPING UPDATE ARRAY:'.fcArray::Render($this->UpdateArray());
	$this->Save();
	//echo 'SHIPPING SAVE SQL: ['.$this->sqlExec.']<br>';
	//die();
    }
    public function RenderBillingPage() {
	$oCDMgr = $this->FieldsManager();
	$oCDMgr->FetchBlob();	// fetch blob data from db
	
	$oCD_Buyer = $oCDMgr->BuyerObject();
	
	$oPage = $this->PageObject(); 
	$out =
	  $oPage->SectionHeader('Payment information:')
	  .$oCD_Buyer->RenderPayment(TRUE)	// edit payment information
	  ;
	return $out;
    }
    public function CaptureBillingPage() {
	$oCDMgr = $this->FieldsManager();
	$oCD_Buyer = $oCDMgr->BuyerObject();
	$oCD_Buyer->CapturePayment();	// card #/exp, and I *think* name/address
	$this->AddMissing($oCD_Buyer->GetMissingArray());
	// calculate resulting blob
	$oCDMgr->FetchBlob();
	$oCDMgr->UpdateBlob($oCD_Buyer);
	$oCDMgr->StoreBlob();
	$this->Save();
    }
    /*----
      TODO 2016-04-16 Rewrite this as RenderBillingPage, with RenderShippingPage as a model.
    *//* 2016-06-18 apparently the rewriting has happened -- nothing calls this now.
    public function RenderBillingSection() {
	$oPage = $this->PageObject();
	
	$ksfCustCardNum = KSF_CART_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_CART_PAY_CARD_EXP;
	$ksfCardIsShip = KSF_SHIP_IS_CARD;

	$custCardNum = $rsCFields->CardNumber();
	$custCardExp = $rsCFields->CardExpiry();
	$isShipCardSame = $rsCFields->IsShipToCard();	// request to use shipping address for card billing address
	$doesShipCardMatch = $this->CardMatchesShip();

	$out = $oPage->SectionHeader('Payment information:')
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
    }*/
    public function RenderPayTypeSection() {
	$oPage = $this->PageObject();
	$oCDMgr = $this->FieldsManager();
	$oCD_PayType = $oCDMgr->PayTypeObject();
	
	$sWhere = __METHOD__.'() in '.__FILE__;
	$out =
	  "\n<!-- vv $sWhere vv -->"
	  .$oPage->SectionHeader('Payment type:')
	  ."\n<table class='form-block' id='pay-type'>"
	  ;

	$isShipCardSame = $this->FieldsObject()->IsShipToCard();
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
    }
    
    //--checkout page i/o--//
    
    // -- OUTPUT -- //

}