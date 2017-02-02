<?php
/*
  PURPOSE: VbzCart's extensions to Session functionality
    i.e. anything involving the ID_Cart or ID_Order fields
  HISTORY:
    2013-11-09 created
*/

class cVbzSessions extends fctUserSessions {
/* 2016-10-27 redundant
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('cVbzSession');
    }
    */
    
    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'cVbzSession';
    }

    // -- OVERRIDES -- //

}
class cVbzSession extends fcrUserSession {
    use ftSaveableRecord;
    
    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->ClearCartRecord();
    }
    public function InitNew() {
	parent::InitNew();
	$this->SetFieldValues(array('ID_Cart'=>NULL));
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    /*----
      NOTE: This doesn't actually work the way it should, because it is
	first called *before* the dropins are loaded.
    */
    protected function UsersClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.users')) {
	    return KS_CLASS_ADMIN_USER_ACCOUNTS;
	} else {
	    return 'vcUserTable';
	}
    }
    protected function CartsClass() {
	//return 'vctShopCarts';
	return 'vctCarts_ShopUI';	// this is a bit of a kluge
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function CartTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CartsClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ FIELD ACCESS ++ //

    protected function OrderID() {
	return $this->Value('ID_Order');
    }
    protected function GetCartID() {
	return $this->GetFieldValue('ID_Cart');
    }
    protected function SetCartID($id) {
	$this->SetFieldValue('ID_Cart',$id);
    }
    protected function WhenCreated() {
	return $this->Value('WhenCreated');
    }
    protected function WhenExpires() {
	return $this->Value('WhenExpires');
    }
    protected function WhenClosed() {
	return $this->Value('WhenClosed');
    }

    // -- FIELD ACCESS -- //
    // ++ CART FUNCTIONS ++ //

      //++status++//
    
    /*----
      RETURNS: TRUE iff a cart is currently attached to this session
    */
    public function HasCart() {
	if ($this->IsNew()) {
	    return FALSE;
	} else {
	    return (!is_null($this->GetCartID()));
	}
    }
    
      //--status--//
      //++records++//

    private $rcCart;
    /*----
      RETURNS: The current cart record, regardless of status.
	Only returns NULL if Cart ID is not set.
    */
    protected function CartRecord_asSet() {
	$rcCart = $this->rcCart;
	if (is_null($rcCart)) {
	    if (!$this->IsNew()) {
		$idCart = $this->GetCartID();
		if (!is_null($idCart)) {
		    $rcCart = $this->CartTable($idCart);
		    $this->rcCart = $rcCart;
		}
	    }
	}
	return $rcCart;
    }
    /*----
      ACTION: return an object for the current cart ID if it is usable.
	If the cart ID is NULL or if the cart is voided, returns NULL.
      ASSUMES: If there is a cart object already, it is the correct one for this session.
      HISTORY:
	2013-11-24 when the cart is new, apparently there is no ID_Cart field here.
	  Calling $this->HasValue('ID_Cart') causes an error.
	2014-09-23 Now checks cart status and returns NULL for voided cart.
    */
    public function CartRecord_Current() {
	$rcCart = $this->CartRecord_asSet();
	if (is_null($rcCart)) {
	    $this->rcCart = NULL;
	} else {
	    if ($rcCart->IsVoided()) {
		$this->rcCart = NULL;
	    }
	}
	return $this->rcCart;
    }
    /*----
      ACTION: Return a cart object. If there isn't an associated cart yet, or if
	the current one isn't usable, create a new one.
      NOTE 1: This is actually a store UI function, even though it returns an object.
	If any non-store-UI code needs to get a cart object, they should get it more
	  directly (via Order, Session, etc.)
      NOTE 2: The above note does not make any sense. I suspect that it represents
	bad conceptual design. If the two methods serve different purposes, they should
	be named differently.
      ASSUMES: If there is a cart object already, it is the correct one for this session
	-- unless the order has been locked, in which case we'll get a new one.
    */
    public function CartRecord_required() {
// if there's a cart for this session, load it; otherwise create a new one but don't save it:

	$rcCart = $this->CartRecord_Current();
	//if (is_null($rcCart) || !$rcCart->HasRows() || $rcCart->IsLocked()) {
	if (is_null($rcCart) || $rcCart->IsLocked()) {
	    // if no cart, or cart is locked, get a new one:
	    $idSess = $this->GetKeyValue();
	    $idCart = $this->CartTable()->Create($idSess);
	    $this->SetCartID($idCart);
	    $this->Save();
	    $rcCart = $this->CartRecord_Current();
	    $this->rcCart = $rcCart;
	}
	return $rcCart;
    }

      //--records--//
      //++actions++//

    protected function ClearCartRecord() {
	$this->rcCart = NULL;
    }
    /*----
      ACTION: Permanently detach the cart from this session.
    */
    public function DropCart() {
	$oCart = $this->CartRecord_Current();
	$oCart->DoVoid();
	$ar = array('ID_Cart'=>'NULL');
	$this->Update($ar);
    }

       //--actions--//
    // -- CART FUNCTIONS -- //

}