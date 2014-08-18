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
    private $rcCart;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->rcCart = NULL;
    }
    public function InitNew() {
	parent::InitNew();
	$this->ValuesSet(array('ID_Cart'=>NULL));
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    /*----
      NOTE: This doesn't actually work the way it should, because it is
	first called *before* the dropins are loaded.
    */
    protected function UsersClass() {
	if (clsDropInManager::ModuleLoaded('vbz.users')) {
	    return KS_CLASS_ADMIN_USER_ACCOUNTS;
	} else {
	    return 'clsVbzUserTable';
	}
    }


    // ++ FIELD ACCESS ++ //

    /* 2014-07-06 is this really needed still?
    public function SetCart($iID) {
	$this->Value('ID_Cart',$iID);
	$this->Update(array('ID_Cart'=>$iID));
    }*/
    protected function CartID($id=NULL) {
	return $this->Value('ID_Cart',$id);
    }
    /*----
      RETURNS: TRUE iff a cart is currently attached to this session
    */
    public function HasCart() {
	if ($this->IsNew()) {
	    return FALSE;	// not sure if this will work
	} else {
	    return (!is_null($this->CartID()));
	}
    }

    // -- FIELD ACCESS -- //
    // ++ RECORD ACCESS ++ //

    /*----
      ACTION: return an object for the current cart ID, or NULL if ID is NULL.
      ASSUMES: If there is a cart object already, it is the correct one for this session.
      HISTORY:
	2013-11-24 when the cart is new, apparently there is no ID_Cart field here.
	  Calling $this->HasValue('ID_Cart') causes an error.
    */
    public function CartRecord_Current() {
	$rcCart = $this->rcCart;
	if (is_null($rcCart)) {
	    if (!$this->IsNew()) {
		$idCart = $this->CartID();
		if (!is_null($idCart)) {
		    $rcCart = $this->Engine()->Carts($idCart);
		}
	    }
	}
	$this->rcCart = $rcCart;
	return $rcCart;
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
    public function CartRecord_toUse() {
// if there's a cart for this session, load it; otherwise create a new one but don't save it:

	$rcCart = $this->CartRecord_Current();
	if (is_null($rcCart) || !$rcCart->HasRows() || $rcCart->IsLocked()) {
	    // if no cart, or cart is locked, get a new one:
	    $idSess = $this->KeyValue();
	    $idCart = $this->Engine()->Carts()->Create($idSess);
	    $this->CartID($idCart);
	    $this->Save();
	    $rcCart = $this->CartRecord_Current();
	    $this->rcCart = $rcCart;
	}
	return $rcCart;
    }

    // -- RECORD ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Permanently detach the cart from this session.
    */
    public function DropCart() {
	$oCart = $this->CartRecord_Current();
	$oCart->DoVoid();
	$ar = array('ID_Cart'=>'NULL');
	$this->Update($ar);
    }

    // -- ACTIONS -- //
}