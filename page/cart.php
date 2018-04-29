<?php
/*
  PURPOSE: shopping cart stuff -- store UI
  VARIANT: for display while shopping
    Note that there is no variant for checkout, because
    the checkout page class just calls the cart object to display itself.
  HISTORY:
    2012-05-14 extracted from cart.php
    2018-02-25 moved vcAppShop_cart here
*/
class vcAppShop_cart extends vcAppShop {
    protected function GetPageClass() {
	return 'vcPage_Cart';
    }
    // TODO: not sure a Kiosk is needed for this class
    protected function GetKioskClass() {
	return 'vcMenuKiosk_catalog';	// probably redundant
    }
}

class vcPage_Cart extends vcCatalogPage {

    // ++ SETUP ++ //

    // OVERRIDE
    protected function OnRunCalculations(){
	$this->UseStyleSheet('cart');
    }
    // CEMENT
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_Cart';
    }

    // -- SETUP -- //
    // ++ WEB OUTPUT ++ //

    // TO BE WRITTEN
    public function AddFooterStat($sText) {
	// TODO: implement
    }

    // -- WEB OUTPUT -- //
}
class vcTag_html_Cart extends vcTag_html_catalog {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTag_body() {
	return 'vcTag_body_Cart';
    }

    // -- SETUP -- //

}
class vcTag_body_Cart extends vcTag_body_shop {
    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forPageContent() {
	return 'vcPageContent_Cart';
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //

    // CreateElements: parent creates header, navbar, content
    // CEMENT
    protected function OnRunCalculations(){}
    
    // -- EVENTS -- //
}
class vcPageContent_Cart extends vcPageContent {

    // ++ EVENTS ++ //

    protected function OnRunCalculations() {
	//$this->FigureExhibitPage_fromInput();
	$this->ParseInput();
	$this->HandleInput();
    }
    
    // -- EVENTS -- //
    // ++ FRAMEWORK ++ //
    
    protected function GetPageObject() {
	return fcApp::Me()->GetPageObject();
    }
    protected function FormInputNeedsCart() {
	return vcrShopCart::FormInputNeedsCart();
    }
    protected function HasCart() {
	return $this->GetSessionRecord()->HasCart();
    }
    
    // -- FRAMEWORK -- //
    // ++ CLASSES ++ //
    
    protected function CartsClass() {
	return 'vctShopCarts';
    }

    // ++ CLASSES ++ //
    // ++ TABLES ++ //
    
    public function CartTable() {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper($this->CartsClass());
    }

    // -- TABLES -- //
    // ++ INPUT ++ //

    /*----
      CEMENT
      ACTION: Cart does not use URL data; all info passed via POST.
	So this implementation does nothing (but must be defined
	so class is not abstract).
    */
    protected function ParseInput() {
	$oPage = $this->GetPageObject();
	// stuff that always gets set
	$oPage->SetContentTitleContext('this is your...');
	$oPage->SetPageTitle('Shopping Cart');
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
	//$oPage = $this->GetPageObject();
	if ($this->FormInputNeedsCart()) {
	    $useCart = TRUE;
	} else {
	    $useCart = $this->HasCart();
	}
	
	$out = NULL;
	if ($useCart) {
	    $tCarts = $this->CartTable();
	    $tCarts->HandleCartFormInput();	// check for any form data (added items, recalculations, etc.)

	    // render shopping-style (editable) cart, if we haven't redirected
	    $out = $tCarts->RenderCart(TRUE);
	} else {
	    $sMsg = fcHTTP::DisplayOnReturn();
	    $oPage = $this->GetPageObject();
	    if (is_null($sMsg)) {
		$oPage->AddWarningMessage('There is nothing in your cart yet.');
	    } else {
		$oPage->AddSuccessMessage($sMsg);
	    }
	}
	//$this->SetValue($out);
	$this->SetValue($out);
    }
    
    // -- INPUT -- //
    
}
