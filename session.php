<?php
/*
  PURPOSE: VbzCart's extensions to Session functionality
    i.e. anything involving the ID_Cart or ID_Order fields
  HISTORY:
    2013-11-09 created
*/

class vcUserSessions extends fctUserSessions {
    
    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'vcUserSession';
    }

    // -- OVERRIDES -- //

}
class vcUserSession extends fcrUserSession {
    use ftSaveableRecord;
    
    // ++ SETUP ++ //

    /* 2018-02-24 I think this is redundant now.
    protected function InitVars() {
	parent::InitVars();
	$this->ClearCartRecord();
    } */
    public function InitNew() {
	parent::InitNew();
	$this->SetFieldValues(array('ID_Cart'=>NULL));
    }

    // -- SETUP -- //
    // ++ CLASSES ++ //

    /*----
      NOTE: This doesn't actually work the way it should, because it is
	first called *before* the dropins are loaded.
      HISTORY:
	2018-02-27 For now, dropins just kind of aren't loaded... so use the base class.
    */
    protected function UsersClass() {
	//if (vcApp::Me()->GetDropinManager()->HasModule('vbz.users')) {
	//    return KS_CLASS_ADMIN_USER_ACCOUNTS;
	//} else {
	    return 'vcUserTable';
	//}
    }
    protected function CartsClass() {
	return 'vctShopCarts';	// this is a bit of a kluge
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function CartTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CartsClass(),$id);
    }

    // -- TABLES -- //
    // ++ FIELD ACCESS ++ //

    protected function OrderID() {
	return $this->Value('ID_Order');
    }
    protected function GetCartID() {
	//return $this->GetFieldValue('ID_Cart');
	return $this->GetStashValue('cart id');
    }
    protected function SetCartID($id) {
	//$this->SetFieldValue('ID_Cart',$id);
	$this->SetStashValue('cart id',$id);
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

    private $rcCart = NULL;
    protected function IsCartCached() {
	return !is_null($this->rcCart);
    }
    protected function GetCartRecord_cached() {
	return $this->rcCart;
    }
    protected function SetCartRecord(vcrCart $rc=NULL) {
	$this->rcCart = $rc;
	if (is_null($rc)) {
	    $this->SetCartID(NULL);
	} else {
	    $this->SetCartID($rc->GetKeyValue());
	}
    }
    protected function SetCartRecord_fromCurrentID() {
	$idCart = $this->GetCartID();		// get its Cart ID
	if (!is_null($idCart)) {			// if it has one, anyway...
	    $rcCart = $this->CartTable($idCart);		// fetch the Cart record
	    $this->SetCartRecord($rcCart);				// cache it here
	}
    }
    /*----
      RETURNS: The current known cart record, regardless of status.
	Does not check for validity, but does fetch record to cache if no record cached.
	Only returns NULL if record not cached and Cart ID is not set.
    */
    protected function GetCartRecord_ifKnown() {
	if (!$this->IsCartCached()) {	// Cart record not cached?
	    if (!$this->IsNew()) {		// Session record has been created?
		$this->SetCartRecord_fromCurrentID();	// fetch/cache the Cart record
	    }
	}
	return $this->rcCart;
    }
    /*----
      ACTION: return an object for the current cart ID if it is writeable.
	If the cart ID is NULL or if the cart is voided, returns NULL.
      ASSUMES: If there is a cart object already, it is the correct one for this session.
      HISTORY:
	2013-11-24 when the cart is new, apparently there is no ID_Cart field here.
	  Calling $this->HasValue('ID_Cart') causes an error.
	2014-09-23 Now checks cart status and returns NULL for voided cart.
    */
    public function GetCartRecord_ifWriteable() {
	$rcCart = $this->GetCartRecord_ifKnown();
	if (!is_null($rcCart)) {
//	    if ($rcCart->IsVoided()) {	// existing cart is void, so...
	    if ($rcCart->IsLocked()) {	// existing cart is not writeable, so...
		$this->SetCartRecord(NULL);	// ...make note that we don't have a writeable cart
		$rcCart = NULL;
	    }
	}
	return $rcCart;
    }
    /*----
      ACTION: get the Cart record-object for this Session
	If there isn't already a Cart or it's not writeable,
	  create one and update the Session record.
    */
    public function GetCartRecord_toWrite() {
	$rcCart = $this->GetCartRecord_ifWriteable();
	if (is_null($rcCart)) {	// If current Cart is not writeable...
	echo 'GOT TO '.__FILE__.' line '.__LINE__.'<br>';
	    // ...then create a new one:
	    $idSess = $this->GetKeyValue();
	    $idCart = $this->CartTable()->Create($idSess);
	echo "SESSION ID=[$idSess] CART ID=[$idCart]<br>";
	    $this->SetCartID($idCart);
	    $this->SetCartRecord_fromCurrentID();
	    $rc = $this->GetCartRecord_cached();
	    $this->Save();
	}
	return $rcCart;
    }

    /*----
      ACTION: Return a cart object. If there isn't an associated cart yet, or if
	the current one isn't usable, create a new one.
      ASSUMES: If there is a cart object already, it is the correct one for this session
	-- unless the order has been locked, in which case we'll get a new one.
    */
    /* 2018-02-26 Redundant now.
    public function CartRecord_required() {
// if there's a cart for this session, load it; otherwise create a new one but don't save it:
	$rcCart = $this->GetCartRecord_ifWriteable();
	if (is_null($rcCart)) {	// if current cart is not writeable...
	    // ...get a new one:
	    $idSess = $this->GetKeyValue();
	    $idCart = $this->CartTable()->Create($idSess);
	    $this->SetCartID($idCart);
	    $this->SetCartRecord_fromCurrentID();
	}
	return $this->rcCart;
    } */

      //--records--//
      //++actions++//

    /* 2018-02-24 I think this is redundant now too.
    protected function ClearCartRecord() {
	$this->SetCartRecord(NULL);
    } */
    /*----
      ACTION: Permanently detach the cart from this session.
    */
    public function DropCart() {
	$oCart = $this->GetCartRecord_ifWriteable();
	$oCart->DoVoid();
	$ar = array('ID_Cart'=>'NULL');
	$this->Update($ar);
    }

       //--actions--//
    // -- CART FUNCTIONS -- //

}