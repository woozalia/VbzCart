<?php
/*
  PURPOSE: shopping cart classes: shopping UI
  HISTORY:
    2016-03-07 Split off some methods from clsShopCart[s] (later renamed vctShopCart[s])
    2018-02-19 General updating shenanigans; require cart.const.php
*/
require_once('cart.const.php');

define('KS_CLASS_LOGGER','vctCartLog');

class vctShopCarts extends vctCarts {
    use ftLoggableTable;

    // ++ SETUP ++ //
    
    // CEMENT
    public function GetActionKey() {
	return 'cart';
    }
    // OVERRIDE
    protected function SingularName() {
	return 'vcrShopCart';
    }

    // -- SETUP -- //
    // ++ CLASSES ++ //

    protected function GetEventsClass() {
	return KS_CLASS_LOGGER;
    }

    // -- CLASSES -- //
    // ++ SHOPPING UI ++ //

    /*----
      ACTION: 
	1. We're doing stuff to the current cart, so fetch it or create a new one.
	2. Check form input to see if anything needs to be done to it.
	3. Save any Cart changes; make sure Session record is tracking the Cart ID.
      ASSUMES: We have form input that actually requires a cart record. (Caller should check this;
	don't call if cart not required.)
      HISTORY:
	2013-11-09 moved from clsShopCart to clsShopCarts (later renamed vctShopCarts and then vctCarts)
    */
    public function HandleCartFormInput() {
	$rcCart = $this->GetCartRecord_toWrite();
	if (!is_object($rcCart)) {
	    throw new exception('Could not obtain writeable cart.');	// this pretty much should never happen
	}
	// TODO: save Cart ID to Session record!
	$rcCart->HandleFormInput();	// NOTE: this may redirect, exiting the code without returning
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
	    $rcCart = $this->GetCartRecord_ifWriteable();
	    $rcCart->SetEditable($bEditable);
	    $out = $rcCart->Render();
	} else {
	throw new exception('Is this being called when items are first added?');
	// 2018-02-24 The rest of this can't work anymore...
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
class vcrShopCart extends vcrCart {
    use ftSaveableRecord;

    // ++ STATUS ++ //
    
    private $bEditable;
    public function SetEditable($b) {
	$this->bEditable = $b;
    }
    protected function GetEditable() {
	return $this->bEditable;
    }
    
    // -- STATUS -- //
    // ++ FRAMEWORK ++ //
    
    protected function GetPageObject() {
	return vcApp::Me()->GetPageObject();
    }

    // -- FRAMEWORK -- //
    // ++ CLASSES ++ //

    protected function GetEventsClass() {
	return KS_CLASS_LOGGER;
    }

    // -- CLASSES -- //
    // ++ INPUT ++ //

    static protected function FoundInputButton_AddToCart() {
	return vcGlobals::Me()->FoundInputButton_AddToCart();
    }
    /*----
      ACTION: Looks at the received form data to see if a cart should be required.
	For now, I'm assuming that any in-cart functions (such as delete, recalculate,
	etc.) do *not* require a new cart because the cart would already exist,
	but the rules can be fine-tuned as needed.
    */
    static public function FormInputNeedsCart() {
	return self::FoundInputButton_AddToCart();
    /*
	$yes = array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
	return $yes;
    */
    }
    /*----
      ACTION: Do whatever needs to be done to the current cart based on the form input
	1. Check form input to see what (if anything) needs to be done.
	2. Save any changes back to the Cart and Lines records.
    */
    public function HandleFormInput() {
// check for input
	// - buttons
	$doAddItems	= self::FoundInputButton_AddToCart();	// array_key_exists(KSF_CART_BTN_ADD_ITEMS,$_POST);
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
	    $arItems = vcGlobals::Me()->GetCartItemsInput();
	    // add each non-empty item
	    $db = $this->GetConnection();
	    foreach ($arItems as $key => $val) {
		if (!empty($val)) {
		    $nVal = (int)0+$val;
		    $sCatNum = $db->SanitizeString($key);	// prevent SQL injection when adding to cart items table
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
	    $this->CreateEvent('ck1','going to checkout');
	    fcHTTP::Redirect(vcGlobals::Me()->GetWebPath_forCheckoutPage());
	    // not sure if PHP returns from a redirect; this might never get executed:
	    $this->CreateEvent('ck2','sent redirect to checkout');
	} elseif ($doRefresh) {
	    $this->UpdateTimestamp();	// record that the cart was updated
	    // if we changed anything, redirect to a clean URL with no POST data:
	    fcHTTP::Redirect(vcGlobals::Me()->GetWebPath_forCartPage());
	}
    }
    
    // -- INPUT -- //
    // ++ OUTPUT ++ //

    /*----
      FUNCTION: DisplayObject()
      CALLED BY this.Render() (below)
    */
    private $oPainter;
    protected function DisplayObject() {
    
	if (empty($this->oPainter)) {
	    $bEditable = $this->GetEditable();
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
    public function Render() {
	$id = $this->GetKeyValue();
	$oPage = $this->GetPageObject();
	$oPage->AddFooterStat('cart',$id);
	$oPage->AddFooterStat('sess',$this->GetSessionID());
	$out = NULL;
	if ($this->HasLines()) {
	    $oPainter = $this->DisplayObject();
	    $out .= $oPainter->Render();
	} else {
	    $sMsg = "You have a cart (#$id), but it's empty. We're not quite sure how that happened.";
	    $out = $oPage->AddWarningMessage($sMsg);
	    $sWhat = 'displaying cart - empty; zone '.$this->GetZoneCode();
	    /*
	    $arEv = array(
	      fcrEvent::KF_CODE		=> 'disp',
	      fcrEvent::KF_DESCR_START	=> $sWhat,
	      fcrEvent::KF_WHERE		=> __FILE__.' line '.__LINE__,
	      fcrEvent::KF_IS_ERROR		=> TRUE,
	      );
	    $this->CreateEvent($arEv);
	    */
	    $this->CreateEvent('empty',$sWhat);
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

	//$oPage = $this->GetPageObject();
	$out =
	  (new fcSectionHeader('Contact information:'))->Render()
	  .$oCD_Buyer->RenderContact(TRUE)	// edit email/phone
	  .(new fcSectionHeader('Shipping information:'))->Render()
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
	
	$oPage = $this->GetPageObject(); 
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
    public function RenderPayTypeSection() {
	$oPage = $this->GetPageObject();
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