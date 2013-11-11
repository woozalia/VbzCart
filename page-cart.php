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
/*
    public function TitleStr() {
	return 'Shopping Cart';
    }
    public function TCtxtStr() {
	return 'this is your...';
    }
    protected function NameStr() {
	return 'cart';
    }
*/
    /*----
      ACTION: Cart does not use URL data; all info passed via POST.
	So this implementation does nothing (but must be defined
	so class is not abstract).
    */
    protected function ParseInput() {
	$this->TCtxtStr('this is your...');
	$this->TitleStr('Shopping Cart');
	$this->NameStr('cart');
    }
    /*-----
      ACTION: render HTML header (no directly visible content)
    */
    protected function RenderHtmlHdr() {
	return $this->Skin()->RenderHtmlHdr($this->TitleStr(),'cart');
    }

// process functions
    /*----
      NOTE: To have a message show up only on cart and checkout pages, put it after DoPreContent().
    */
/*
    public function DoPreContent() {
	parent::DoPreContent();
    }
*/
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
    }
    public function DoContent() {
	echo $this->Data()->Carts()->RenderCart();
    }
/*
    public function NavArray() {
	return NULL;
    }
*/
}
