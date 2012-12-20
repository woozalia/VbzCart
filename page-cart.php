<?php
/*
  PURPOSE: shopping cart stuff -- store UI
  HISTORY:
    2012-05-14 extracted from cart.php
*/
if (!defined('LIBMGR')) {
    require(KFP_LIB.'/libmgr.php');
}

clsLibMgr::Add('vbz.pages',	KFP_LIB_VBZ.'/pages.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsVbzSkin_Standard', 'vbz.pages');
clsLibMgr::Add('vbz.shop',	KFP_LIB_VBZ.'/shop.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsShopSessions', 'vbz.shop');

class clsPageCart extends clsVbzSkin_Standard {
    protected $objSess;
    protected $objCart;

    /*----
      ACTION: Cart does not use URL data; all info passed via POST.
	So this implementation does nothing (but must be defined
	so class is not abstract).
    */
    protected function ParseInput() {}

// process functions
    /*----
      NOTE: To have a message show up only on cart and checkout pages, put it after DoPreContent().
    */
    public function DoPreContent() {
	$this->inCkout = FALSE;	// (2011-04-01) not sure why this is sometimes not getting set
	parent::DoPreContent();
    }
    /*----
      INPUT: $iCaller is for debugging and is discarded; caller should pass __METHOD__ as the argument.
    */
    public function GetObjects($iCaller) {
	$tbl = $this->Data()->Sessions();
	$tbl->Page($this);
	$this->objSess = $tbl->GetCurrent();	// get whatever session is currently applicable (existing or new)
	$this->objCart = $this->objSess->CartObj();
	$this->objCart->objSess = $this->objSess;	// used for logging
    }
    public function Cart($iObj=NULL) {
	if (!is_null($iObj)) {
	    $this->objCart = $iObj;
	}
	return $this->objCart;
    }
    protected function HandleInput() {
	$this->GetObjects(__METHOD__);
	$this->objCart->CheckData();	// check for any form data (added items, recalculations, etc.)

	$this->strSheet	= 'cart';	// cart stylesheet has a few different things in it

	$this->strWikiPg	= '';
	$this->strTitle	= 'Shopping Cart';	// Displayed title (page header)
	$this->strName	= 'shopping cart';	// HTML title
	$this->strTitleContext	= 'this is your'; // 'Tomb of the...';
	$this->strHdrXtra	= '';
	$this->strSideXtra	= ''; //'<dt><b>Cat #</b>: '.$this->strReq;
	$this->strSheet	= KSQ_PAGE_CART;	// default
    }
    public function DoContent() {
	echo $this->objCart->Render();
    }
}
class clsSessions_StoreUI extends clsShopSessions {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsSession_StoreUI');
    }

    public function Page(clsVbzSkin $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
}
class clsSession_StoreUI extends clsShopSession {
    /*----
      RETURNS: TRUE if the cart is usable within the current context
	If we're displaying checkout stuff, it's okay to use a cart
	  which has already been turned into an order.
	If we're still in the cart-editing phase, then we need to
	  fetch a new cart if the old one has been ordered (or, later,
	  give the user options -- add to existing order, edit existing order,
	  create completely new order...).
      NOTE: This is actually a UI function, even though it just returns a boolean.
    */
    private function IsCartUsable($iCart) {
	if ($this->Table()->Page()->inCkout) {
	    // for voided, still need a new cart; ordered is ok
	    return !$iCart->IsVoided();
	} else {
	    // ordered or voided means we need a new cart
	    return !$iCart->IsLocked();
	}
    }
    /*
      NOTE: This is actually a store UI function, even though it returns an object.
	If any non-store-UI code needs to get a cart object, they should get it more
	  directly (via Order, Session, etc.)
    */
    public function CartObj() {
// if there's a cart for this session, load it; otherwise create a new one but don't save it:
	if (!isset($this->objCart)) {
	    $objCarts = $this->objDB->Carts();
	    if (!is_null($this->ID_Cart)) {
		$objCart = $objCarts->GetItem($this->ID_Cart);
/*
		// KLUGE for testing - should not normally be necessary:
		if ($this->objCart->ID_Sess==0) {
		    $this->objCart->ID_Sess = $this->ID;
		    $this->objCart->Update(array('ID_Sess'=>$this->ID));
		}
*/
		if (!$this->IsCartUsable($objCart)) {
		    $this->ID_Cart = NULL;	// get a new cart if the order is locked
		}
	    }
	    if (is_null($this->ID_Cart)) {
		$objCart = $this->objDB->Carts()->SpawnItem();
		$objCart->InitNew($this->ID);
	    }
	}
	assert('is_object($objCart);');	// we should always have a cart at this point
	$this->objCart = $objCart;
	return $objCart;
    }

}