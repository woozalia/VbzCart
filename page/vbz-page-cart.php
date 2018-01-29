<?php
/*
  PURPOSE: shopping cart stuff -- store UI
  VARIANT: for display while shopping
    Note that there is no variant for checkout, because
    the checkout page class just calls the cart object to display itself.
  HISTORY:
    2012-05-14 extracted from cart.php
*/
class vcPageBrowse_Cart extends vcPageContent {

    // ++ SETUP ++ //

/*    public function __construct() {
	parent::__construct();
	//$this->strSideXtra = NULL;
	$this->GetSkinObject()->Sheet('cart');
    } */
    
    // -- SETUP -- //
    // ++ CEMENTING ++ //

    protected function BaseURL() {
	return KWP_CART_REL;
    }
    protected function PreSkinBuild() {
	// TODO: this needs remediation
    }
    protected function PostSkinBuild() {
	// TODO: this needs remediation
    }
    /*----
      ACTION: Cart does not use URL data; all info passed via POST.
	So this implementation does nothing (but must be defined
	so class is not abstract).
    */
    protected function ParseInput() {
	$oSkin = $this->GetSkinObject();
	// stuff that always gets set
	$oSkin->SetTitleContextString('this is your...');
	$oSkin->SetPageTitle('Shopping Cart');
	//$this->NameStr('cart');
    }
    /*----
      ACTION:
	1. Get a session to use.
	2. Have the Carts object check for form input.
      LATER: We might want to do #1 in the "browse" page class
	  so we can show a count of "items in your cart" while shopping.
	  We could also do #2 so that adding items to the cart wouldn't
	  require leaving the current page.
    */
    protected function HandleInput() {
	if ($this->FormInputNeedsCart()) {
	    $useCart = TRUE;
	} else {
	    $useCart = $this->HasCart();
	}
	
	$oSkin = $this->GetSkinObject();
	if ($useCart) {
	    $tCarts = $this->AppObject()->CartTable();
	    $tCarts->HandleCartFormInput();	// check for any form data (added items, recalculations, etc.)

	    // render shopping-style (editable) cart
	    $oSkin->Content('cart',$tCarts->RenderCart(TRUE));
	} else {
	    $sMsg = fcHTTP::DisplayOnReturn();
	    if (is_null($sMsg)) {
		$out = $oSkin->WarningMessage('There is nothing in your cart yet.');
	    } else {
		$out = $oSkin->SuccessMessage($sMsg);
	    }
	    $oSkin->Content('cart',$out);
	}
    }
    
    // -- CEMENTING -- //
    // ++ CALCULATIONS ++ //
    
    protected function FormInputNeedsCart() {
	return vcrShopCart::FormInputNeedsCart();
    }
    
    // -- CALCULATIONS -- //
    
}
