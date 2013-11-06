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
    protected function HandleInput() {
	$this->CartObj()->CheckData();	// check for any form data (added items, recalculations, etc.)
    }
    public function DoContent() {
	echo $this->CartObj()->Render();
    }
/*
    public function NavArray() {
	return NULL;
    }
*/
    public function CartObj() {
	//return $this->objCart;	// document where this is set!
	return $this->App()->Session()->CartObj_forShopping();
    }
}
class clsSessions_StoreUI extends clsShopSessions {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsSession_StoreUI');
    }
/* 2013-10-14 Why does the Session need to know the Page? And if it does need it, shouldn't it get it from the Engine?
    public function Page(clsVbzPage $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
*/
}
/*%%%%
  HISTORY:
    2013-10-13 I don't even remember why I needed this to be separate
      from the base class, except that any UI methods should be here
      rathar than there... but the only two methods here (IsCartUsable and 
      CartObj) do not seem to do any UI... so I am moving them back to
      the base class, in order to minimize confusion.
*/
class clsSession_StoreUI extends clsShopSession {
    /*----
      RETURNS: TRUE if the cart is usable within the current context
	This now means that it just asks the cart if it's locked.
	Locked = not usable.
	The rules used to be more complex; keeping them around until
	  I understand if they were really necessary or not.

      OLD RULES:
	If we're displaying checkout stuff, it's okay to use a cart
	  which has already been turned into an order.
	If we're still in the cart-editing phase, then we need to
	  fetch a new cart if the old one has been ordered (or, later,
	  give the user options -- add to existing order, edit existing order,
	  create completely new order...).
      NOTE: This is actually a UI function, even though it just returns a boolean.
    */
}