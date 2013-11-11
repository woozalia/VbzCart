<?php
/*
  PURPOSE: VbzCart's extensions to Session functionality
    i.e. anything involving the ID_Cart or ID_Order fields
  HISTORY:
    2013-11-09 created
*/
class cVbzSessions extends clsUserSessions {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('cVbzSession');
    }
}
class cVbzSession extends clsUserSession {
    private $oCart;

    public function SetCart($iID) {
	$this->Value('ID_Cart',$iID);
	$this->Update(array('ID_Cart'=>$iID));
    }
    protected function CartID() {
	return $this->Value('ID_Cart');
    }
    /*----
      RETURNS: TRUE iff a cart is currently attached to this session
    */
    public function HasCart() {
	return (!is_null($this->CartID()));
    }
    /*----
      ACTION: return an object for the current cart ID, or NULL if ID is NULL.
      ASSUMES: If there is a cart object already, it is the correct one for this session.
    */
    public function CartObj_Current() {
	$oCart = $this->oCart;
	if (is_null($oCart)) {
	    $tCarts = $this->Engine()->Carts();
	    if (!is_null($this->Value('ID_Cart'))) {
		$oCart = $tCarts->GetItem($this->CartID());
	    }
	}
	$this->oCart = $oCart;
	return $oCart;
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
    public function CartObj_toUse() {
// if there's a cart for this session, load it; otherwise create a new one but don't save it:

	$oCart = $this->CartObj_Current();
	if (is_null($oCart) || !$oCart->HasRows() || $oCart->IsLocked()) {
	    // if no cart, or cart is locked, get a new one:
	    //$oCart = $this->Engine()->Carts()->SpawnItem();
	    //$oCart->InitNew($this->ID);
	    //$idCart = $oCart->KeyValue();
	    $idSess = $this->KeyValue();
	    $idCart = $this->Engine()->Carts()->Create($idSess);
	    $this->SetCart($idCart);
	    $this->oCart = $oCart;
	}
	return $oCart;
    }
    /*----
      ACTION: Permanently detach the cart from this session.
    */
    public function DropCart() {
	$oCart = $this->CartObj_Current();
	$oCart->DoVoid();
	$ar = array('ID_Cart'=>'NULL');
	$this->Update($ar);
    }
}