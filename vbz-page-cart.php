<?php
/*
  PURPOSE: shopping cart stuff -- store UI
  VARIANT: for display while shopping
    Note that there is no variant for checkout, because
    the checkout page class just calls the cart object to display itself.
  HISTORY:
    2012-05-14 extracted from cart.php
*/
class clsPageBrowse_Cart extends clsVbzPage_Browse {
    // ++ ABSTRACT IMPLEMENTATIONS ++ //
    protected function BaseURL() {
	return KWP_CART_REL;
    }
    protected function MenuPainter_new() {
	// TODO: this needs remediation
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
	// stuff that always gets set
	$this->Skin()->TitleContext('this is your...');
	$this->Skin()->PageTitle('Shopping Cart');
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
	// STEP 1
	$tSess = $this->Data()->Sessions();
	$oSess = $tSess->GetCurrent();
	// STEP 2
	$tCarts = $this->Data()->Carts();
	$tCarts->CheckData();	// check for any form data (added items, recalculations, etc.)

	// this is the regular shopping cart -- it should be editable
	$this->Skin()->Content('cart',$tCarts->RenderCart(TRUE));
    }
}
