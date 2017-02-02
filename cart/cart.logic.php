<?php
/*
  PURPOSE: shopping cart classes: business logic
  HISTORY:
    2012-04-17 extracted from shop.php
    2013-09-13 now using vbz-const-ckout.php (formerly cart-const.php)
    2016-03-07 Renamed from cart.php to cart.logic.php; extracted some stuff to cart.shop.php.
*/

require_once(KFP_LIB_VBZ.'/const/vbz-const-cart.php');
require_once(KFP_LIB_VBZ.'/const/vbz-const-ckout.php');

// ShopCart
class vctShopCarts extends vcShopTable {
    use ftFrameworkAccess;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'shop_cart';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrShopCart';
    }

    // -- SETUP -- //
    // ++ TABLES ++ //

    protected function LinesTable() {
	return $this->Engine()->CartLines();
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      RETURNS: Record for either the current cart or, if no cart has
	been assigned to the session, a new one. Will not drop
	an existing cart even if it is invalid in some way.
    */
    protected function CartRecord_required_allow_invalid() {
	$rcSess = $this->SessionRecord();
	$rcCart = $rcSess->CartRecord_Current();
	if (is_null($rcCart)) {
	    $rcCart = $rcSess->CartRecord_required();
	}
	return $rcCart;
    }
    protected function CartRecord_optional_allow_invalid() {
	$rcSess = $this->SessionRecord();
	$rcCart = $rcSess->CartRecord_Current();
	return $rcCart;
    }
    /*----
      RETURNS: Record for the current cart (whether valid or not),
	or NULL if there is no cart assigned to the session.
      USED BY cart.shop Render()
    */
    protected function CartRecord_current() {
	$rcSess = $this->SessionRecord();
	return $rcSess->CartRecord_Current();
    }//*/

    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //
    
    /*----
      RETURNS: TRUE iff cart has been linked to the active Session record
    */
    protected function CartIsRegistered() {
	return $this->SessionRecord()->HasCart();
    }
    
    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: creates a new Cart record
      INPUT: $idSess = value for ID_Sess
      RETURNS: ID of new record
      USAGE: called from Session object (cVbzSession)
	because all requests for carts ultimately go through there
      HISTORY:
	2013-11-09 significant redesign of initialization process for carts and sessions
    */
    public function Create($idSess) {
	$arIns = array(
	  'WhenCreated'	=> 'NOW()',
	  'ID_Sess'	=> $idSess
	  );
	$idNew = $this->Insert($arIns);
	if ($idNew === FALSE) {
	    throw new exception('Could not add new cart record in database for Session ID '.$idSess.'.');
	}
	return $idNew;
    }

    // -- ACTIONS -- //
}
class vcrShopCart extends vcShopRecordset {
    use ftLoggableRecord;
    use ftFrameworkAccess;

    protected $rcOrder;
    private $rsLines;

    protected $hasDetails;	// customer details have been loaded?

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->ClearShipZone();
	$this->hasDetails = FALSE;
    }
	
    // -- SETUP -- //
    // ++ SPECIALIZED LOGGING ++ //

    public function LogEvent($sCode,$sDescr) {
	$this->CartLog()->Add($this,$sCode,$sDescr);
    }

    // -- SPECIALIZED LOGGING -- //
    // ++ FIELD VALUES ++ //

    protected function SetOrderID($id) {
	$this->SetValue('ID_Order',$id);
    }
    // PUBLIC because checkout page uses it
    public function GetOrderID() {
	return $this->GetFieldValue('ID_Order');
    }
    // PUBLIC so Order object can use it at checkout time
    public function GetSessionID() {
	return $this->GetFieldValue('ID_Sess');
    }
    protected function CustomerID() {
	return $this->Value('ID_Cust');
    }
    protected function AddrID() {
	return $this->Value('ID_Addr');
    }
    protected function WhenCreated() {
	return $this->GetFieldValue('WhenCreated');
    }
    protected function WhenOrdered() {
	return $this->Value('WhenOrdered');
    }
    protected function WhenVoided() {
	return $this->GetFieldValue('WhenVoided');
    }
    protected function SetZoneCode($s) {
	return $this->SetFieldValue('ShipZone',$s);
    }
    protected function GetZoneCode() {
	return $this->GetFieldValue('ShipZone');
    }
    // The "serial blob" is a serialized array containing all the form data from checkout (not including the cart contents).
    public function SetSerialBlob($s) {
	$this->SetFieldValue('FieldData',$s);
    }
    public function GetSerialBlob() {
	return $this->GetFieldValue('FieldData');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function HasLines() {
	$rs = $this->LineRecords();
	if (is_null($rs)) {
	    return FALSE;
	} else {
	    return $rs->hasRows();
	}
    }
    public function LineCount() {
	if ($this->HasLines()) {
	    return $this->LineRecords()->RowCount();
	} else {
	    return 0;
	}
    }
    /*----
      HISTORY:
	2010-12-31 Created so placed orders do not get "stuck" in user's browser
	2011-02-07 Doesn't work; same cart still comes up (though at least it generates a new order...
	  but it pulls up all the same contact info)
	2011-03-27 Changed flag from ID_Order to WhenOrdered OR WhenVoided, because we don't want to have to clear
	  ID_Order anymore. Carts should retain their order ID.
    */
    public function IsLocked() {
	return $this->IsOrdered() || $this->IsVoided();
    }
    /*----
      RETURNS: TRUE if the cart has been converted to an order
      USED BY: $this->IsLocked() and (something)->IsUsable()
      HISTORY:
	2011-03-27 written for improved handling of cart status at checkout
    */
    public function IsOrdered() {
	return !(is_null($this->WhenOrdered()));
    }
    protected function HasOrder() {
	return !is_null($this->GetOrderID());
    }
    /*----
      RETURNS: TRUE if the cart has been discarded (voided)
      USED BY: $this->IsLocked() and (something)->IsUsable()
      HISTORY:
	2011-03-27 written for improved handling of cart status at checkout
    */
    public function IsVoided() {
	return !(is_null($this->WhenVoided()));
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function LinesClass() {
	return 'vctShopCartLines';
    }
    protected function FieldsClass() {
	return 'clsCartVars';
    }
    protected function OrdersClass() {
	return 'vctOrders';
    }
    protected function CustomersClass() {
	return 'clsCusts';
    }
    protected function CustomerEmailsClass() {
	return 'clsCustEmails';
    }
    protected function CustomerPhonesClass() {
	return 'clsCustPhones';
    }
    protected function CustomerCardsClass() {
	return 'clsCustCards_dyn';
    }
    protected function CustomerAddressesClass() {
	return 'vctCustAddrs';
    }
    protected function CartLogClass() {
	return 'clsCartLog';
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function LineTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->LinesClass(),$id);
    }
    protected function OrderTable($id=NULL) {
    	return $this->GetConnection()->MakeTableWrapper($this->OrdersClass(),$id);
    }
    protected function CustomerTable($id=NULL) {
    	return $this->GetConnection()->MakeTableWrapper($this->CustomersClass(),$id);
    }
    protected function CustomerCardTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CustomerCardsClass(),$id);
    }
    protected function CustomerAddressTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CustomerAddressesClass(),$id);
    }
    protected function EmailTable() {
	return $this->GetConnection()->MakeTableWrapper($this->CustomerEmailsClass());
    }
    protected function PhoneTable() {
	return $this->GetConnection()->MakeTableWrapper($this->CustomerPhonesClass());
    }
    protected function CartLog() {
	return $this->GetConnection()->MakeTableWrapper($this->CartLogClass());
    }

    // -- TABLES -- //
    // ++ DATA OBJECTS ++ //

    //++shipping zone++//
    
    private $oZone;
    protected function ClearShipZone() {
	$this->oZone = NULL;
    }
    public function ShipZoneObject() {
	if (is_null($this->oZone)) {
	    $sZone = $this->GetZoneCode();
	    $this->oZone = vcShipCountry::Spawn($sZone);
	}
	if (is_null($this->oZone)) {
	    throw new exception('Internal error: Ship Zone object not created.');
	}
	return $this->oZone;
    }
    
    //--shipping zone--//
    //++fields blob++//

    private $oFields;
    public function FieldsManager() {
	if (empty($this->oFields)) {
	    $this->oFields = new vcCartDataManager($this,$this->ShipZoneObject());
	}
	return $this->oFields;
    }
    
    //--fields blob--//
    
    // -- DATA OBJECTS -- //
    // ++ RECORDS ++ //

    /*----
      TODO: Run tests to see if caching is necessary.
	Maybe log accesses temporarily to see if cache is being used or not.
    */
    public function OrderRecord() {
	$doGet = TRUE;
	$idOrder = $this->GetOrderID();
	if (isset($this->rcOrder)) {
	    if ($this->rcOrder->GetKeyValue() == $idOrder) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $this->rcOrder = $this->OrderTable($idOrder);
	}
	return $this->rcOrder;
    }
    /*----
      RETURNS: record for the current Order
	If there's any problem, throws an exception.
	Does *not* create a new Order record.
      TODO: Consider renaming to OrderRecord_old_orDie()
    */
    public function OrderRecord_orDie() {
	if ($this->HasOrder()) {
	    $rcOrd = $this->OrderRecord();
	    if (is_object($rcOrd)) {
		return $rcOrd;
	    } else {
		throw new exception('Internal Error: OrderRecord() returned a non-object.');
	    }
	} else {
	    throw new exception('Order ID not set in cart #'.$this->GetKeyValue().'.');
	}
    }
    /*----
      RETURNS: record for the current Order
	If ID_Order isn't set, then we need to convert Cart data to Order data -- so do that.
    */
    public function OrderRecord_orConvert() {
	if ($this->HasOrder()) {
	    $rcOrd = $this->OrderRecord();
	    // update the order from the cart (may have changed)
	    $this->ToOrder($rcOrd);
	    if (is_object($rcOrd)) {
		return $rcOrd;
	    } else {
		throw new exception('Internal Error: OrderRecord() returned a non-object.');
	    }
	} else {
	    $tOrders = $this->OrderTable();
	    $idOrd = $tOrders->Create();
	    $rcOrd = $tOrders->GetRecord_forKey($idOrd);
	    $this->ToOrder($rcOrd);
	    return $rcOrd;
	}
    }
    public function LineRecords() {
	if (is_null($this->rsLines)) {
	    $this->rsLines = $this->LineTable()->SelectRecords('(ID_Cart='.$this->GetKeyValue().') AND (Qty>0)');
	}
	return $this->rsLines;
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    protected function LogCartEvent($sCode,$sDescr) {
	$this->CartLog()->Add($this,$sCode,$sDescr);
    }
    protected function CheckForDBError($sAction) {
	$db = $this->GetConnection();
	if (!$db->IsOkay()) {
	    $sErr = $db->ErrorString();
	    $sql = $this->sql;
	    echo "<b>DB Error</b>: $sErr<br>"
	      ."<b>SQL</b>: $sql<br>"
	      ;
	    throw new exception("VbzCart data error: Could not $sAction. DB says: [$sErr] SQL: $sql");
	}
    }
    protected function UpdateZone() {
	$arUpd = array(
	  'ShipZone'	=> '"'.$this->ZoneCode().'"',
	  'WhenUpdated'	=> 'NOW()'
	  );
	$this->Update($arUpd);
	$this->CheckForDBError('update Zone');
    }
    protected function UpdateTimestamp() {
	$arUpd = array(
	  'WhenUpdated'	=> 'NOW()'
	  );
	$this->Update($arUpd);
	$this->CheckForDBError('update timestamp');
    }
    /*----
      USAGE: called by vctShopCarts->CheckData() when items are found in _POST input
      HISTORY:
	2013-11-10 Removed call to Make(), since we're now assuming that there is a record if we're here.
    */
    public function AddItem($sCatNum,$nQty) {
	$t = $this->LineTable();
	$t->MakeLine($this->GetKeyValue(),$sCatNum,$nQty);
	
    }
    /*----
      ACTION:
	* Checks to make sure the given ID is a line currently in this cart
	* If so, sets the quantity of that line to zero, effectively deleting it.
    */
    public function DelLine($idLine) {
	$oLine = $this->Engine()->CartLines($idLine);
	$idCartMe = $this->GetKeyValue();
	$idCartDel = $oLine->CartID();
	if ($idCartDel == $idCartMe) {
	    // matches -- okay to delete line
	    $oLine->Qty(0);	// zero the qty
	    $oLine->Save();	// save the change
	} else {
	    // mismatch -- either an internal bug or a hacking attempt
	    throw new exception("Attempted to delete item line ID $idLine, which is in cart ID $idCartDel not in cart ID $idCartMe.");
	}
    }
    /*----
      ACTION: Void this cart.
      USAGE: This only marks the record as void, not the
	object fields, so caller must reload the record if
	anything further is to be done with the object.
    */
    public function DoVoid() {
	$ar = array('WhenVoided'=>'NOW()');
	$this->Update($ar);
    }
    /*----
      USED BY: $this->CheckData() when "Recalculate" button is pressed
	This zeroes quantities for all cart lines so that only cart lines
	shown in the HTML form AND with nonzero quantities entered will
	actually have nonzero quantities.
    */
    protected function ZeroAll() {
	$ar = array(
	  'Qty'	=> 0
	  );
	$id = $this->GetKeyValue();
	$this->Update($ar,"ID_Cart=$id");	// apply to all lines for this cart
    }


    // -- ACTIONS -- //
    // ++ CONVERSION TO ORDER ++ //

    /*----
      NOTE: Transactions don't seem to be working, but I'm leaving them in here anyway
	in case I do get them working in the future.
      TODO: Uncomment transaction statements for production. Also, they don't seem to prevent new records from being created in other tables.
    */
    public function ToOrder(vcrOrder $rcOrd) {
	$db = $this->GetConnection();
	$db->TransactionOpen();
	$this->AdminEcho('Converting cart to order...<br>');
	$ok =
	  $this->ToOrder_Data($rcOrd) &&
	  $this->ToOrder_Lines($rcOrd);

	if ($ok) {
	    $this->AdminEcho('Conversion complete.<br>');
	    $db->TransactionSave();
	} else {
	    $this->AdminEcho('Conversion failed; reverting.<br>');
	    $db->TransactionKill();
	}
    }
    /*----
      ACTION: Copy over basic cart information (totals, etc.)
      PROCESS: The order record is basically blank at this point, so all cart data must be
	updated into it (and its dependent records, as appropriate). The only fields already set
	are Number, SortPfx, and WhenStarted.
      HISTORY:
	2010-10-06 added cart ID to update -- otherwise final order confirmation page can't find cart data
	2012-05-25 major revision to cart data access -- now using $iCartObj->FieldRecords()
	2013-11-06 this will now import the full order data as well, creating or updating customer records
	  as needed
	2014-01-29 major changes:
	  * adapting to operate from cart object instead of order object
	  * incorporating full cart "import" process
	  * eliminating any attempt to find matching customer profile unless specifically chosen by user
	2014-11-30 I had "ID_Recip" marked as "can be NULL" in the update process, but actually
	  the order confirmation expects it to exist -- so if it actually should be NULLable, we need
	  to document why that is and where to get the information from instead.
        2016-05-08 Rewriting to use blob for field data.
      TODO:
	card data in cart must be *encrypted* after copying
    */
    private function ToOrder_Data(vcrOrder $rcOrder) {
	$idOrd = $rcOrder->GetKeyValue();
	$idCart = $this->GetKeyValue();

	/*----
	  Event parameters:
	    'descr'	=> 'Descr',
	    'descrfin'	=> 'DescrFin',
	    'notes'	=> 'Notes',
	    'type'	=> 'ModType',
	    'id'	=> 'ModIndex',
	    'where'	=> 'EvWhere',
	    'code'	=> 'Code',
	    'params'	=> 'Params',
	    'error'	=> 'isError',
	    'severe'	=> 'isSevere',
	*/
	
	$sDescr = 'Copying fields to order ID '.$idOrd;
	$arEv = array(
	  'code'	=> 'CFO',
	  'descr'	=> $sDescr,
	  'where'	=> __METHOD__,
	  );
	$rcEv = $this->CreateEvent($arEv);
	$this->AdminEcho($sDescr.'<br>');

	// UPDATE the CART RECORD (link it to the Order)

	$arUpd = array(
	  'ID_Order'	=> $idOrd,
	  'WhenPorted'	=> 'NOW()',	// Cart record now being transferred to Order
	  );
	$ok = $this->Update($arUpd);

	// UPDATE ORDER SUB-RECORDS:

	// -- CUSTOMER RECORDS

	// TODO: Do NOT create new customer records if user has selected existing ones!

	$tCard = $this->CustomerCardTable();
        
        $oFields = $this->FieldsManager();
        $oBuyer = $oFields->BuyerObject();
        $oRecip = $oFields->RecipObject();
        $doNewBuyer   = $oBuyer->Value_forBillInType_isNew();
        $doNewRecip   = $oRecip->Value_forShipInType_isNew();

	$tAddr = $this->CustomerAddressTable();

	$idUser	= $this->AppObject()->GetUserID();
	
	// export BUYER information
	
	if ($doNewBuyer) {
	    // get/create BUYER Master record
	    $idBuyer = $rcOrder->CreateBuyerID($idUser);
	    
	    if (is_null($idBuyer)) {
		throw new exception('Internal error: no Buyer ID');
	    }
	     
	    // ensure BUYER Address record
	    $rcBuyerAddr = $tAddr->MakeRecord_fromBuyer($idUser,$idBuyer,$oBuyer);
	    
	    // ensure BUYER Name record
	    $rcBuyerName = $rcBuyerAddr->EnsureNameRecord();
	    $rcBuyer = $rcOrder->BuyerRecord();
	    // make sure Name ID is saved
	    $rcBuyer->SetNameID($rcBuyerName->GetKeyValue());
	    $rcBuyer->Save();
	    
	    // ensure BUYER Email record
	    $rcBuyerEmail = $this->EmailTable()->MakeUniqueRecord_fromContact($idUser,$idBuyer,$oBuyer);
	    
	    // ensure BUYER Phone record
	    $rcBuyerPhone = $this->PhoneTable()->MakeUniqueRecord_fromContact($idUser,$idBuyer,$oBuyer);

	    // create Ccard record
	    $tCard = $this->CustomerCardTable();
	    $rcCard	= $tCard->CreateRecord($idUser,$rcBuyerAddr,$oBuyer);
	    $idBuyerCard = $rcCard->GetKeyValue();
	    if ($idBuyerCard === FALSE) {
		throw new exception('Could not create Card record for buyer ID='.$id);
	    }
	    
	    // add a MESSAGE record, if there's an order message
	    $rcOrder->CreateOrderInstructions($oBuyer->GetValue_forOrderMessage());
        } else {
	    throw new exception("This isn't supposed to happen yet.");
        }
	if (is_null($idBuyer)) {
	    throw new LogicException('Internal error: Buyer ID not set during cart-to-order conversion.');
	}
        
        // export RECIP information
        
        if ($doNewRecip) {
	    // get/create RECIP Master record
	    $idRecip = $rcOrder->CreateRecipID($idUser);
	    
	    // ensure RECIP Address record
	    $rcRecipAddr = $tAddr->CreateRecord_fromRecip($idUser,$idRecip,$oRecip);	// $oRecip is the user input
	    $idRecipAddr = $rcRecipAddr->GetKeyValue();
	    
	    // ensure RECIP Name record
	    $rcRecipName = $rcRecipAddr->EnsureNameRecord();
	    $rcRecip = $rcOrder->RecipRecord();
	    $rcRecip->NameID($rcRecipName->GetKeyValue());
	    $rcRecip->Save();
        } else {
	    // user has chosen an existing contact record
	    $idRecip = $oRecip->GetValue_forShipChoice();
	    if (is_numeric($idRecip)) {
		$tCust	= $this->CustomerTable();
		$rcRecipCust = $tCust->GetRecord_forKey($idRecip);
	    } else {
		throw new exception('Internal error: could not retrieve Recipient choice.');
	    }
	    $rcRecipAddr = $rcRecipCust->AddrRecord();
	    $idRecipAddr = $rcRecipCust->GetAddrID();
	    // this gets saved later, but we need it set now so we can get RecipRecord()
	    $rcOrder->SetRecipID($idRecip);
	    $rcRecip = $rcOrder->RecipRecord();
	    $idRecipName = $rcRecip->GetNameID();
	}
	if (is_null($idRecip)) {
	    throw new LogicException('Internal error: Recip ID not set during cart-to-order conversion.');
	}

	/* 2016-05-01 old code
	// if not using existing customer records, create them
	$sBuyerIntype	= $rsFields->GetFieldValue_forIndex(KI_CART_PAY_CARD_INTYPE,FALSE);
	$doNewBuyer	= is_null($sBuyerIntype) || ($sBuyerIntype == KS_FORM_INTYPE_NEWENTRY);
	$sRecipIntype	= $rsFields->GetFieldValue_forIndex(KI_CART_RECIP_INTYPE,FALSE);
	$doNewRecip	= is_null($sRecipIntype) || ($sRecipIntype == KS_FORM_INTYPE_NEWENTRY);
	
	if ($doNewBuyer) {
	    $sBuyerName		= $rsFields->BuyerName();
	    $oAddrBuyer		= $rsFields->BuyerFields();
	    $oCardInfo		= $rsFields->PayFields();
	    // CREATE NEW CUSTOMER RECORD
	    $idBuyer		= $tCust->CreateCustomer($idUser,$sBuyerName,$oAddrBuyer);
	    if ($idBuyer === FALSE) {
		throw new exception('Could not create record for buyer "'.$sBuyerName.'".');
	    }
	    // CREATE NEW CCARD RECORD
	    $idBuyerCard	= $tCard->CreateRecord($idBuyer,$oCardInfo);
	} else {
	    $idBuyerCard	= $rsFields->GetFieldValue_forIndex(KI_CART_PAY_CARD_CHOICE,FALSE);
	    if (is_null($idBuyerCard)) {
		$sMsg = "Cart ID $idCart has no card choice.";
		$this->AdminEcho($sMsg);
		$rcOrd->AddMessage(
		  KSI_ORD_MSG_INT,	// media = internal message
		  'admins',		// TO
		  'import process',	// FROM
		  'missing data',	// RE
		  $sMsg);
		$idBuyer = NULL;
		$sBuyerName = $rsFields->GetBuyerNameString();
	    } else {
		$rcBuyerCard	= $this->CustomerCardTable($idBuyerCard);
		$idBuyer		= $rcBuyerCard->CustID();
		$sBuyerName		= $rcBuyerCard->OwnerName();
	    }
	}
	if ($doNewRecip) {
	    $sRecipName		= $rsFields->RecipName();
	    $sRecipAddr		= $rsFields->RecipAddr_text();
	    $oAddrRecip		= $rsFields->RecipFields();
	    // CREATE NEW RECIPIENT RECORD
	    $idRecip		= $tCust->CreateCustomer($idUser,$sRecipName,$oAddrRecip);
	    if ($idRecip === FALSE) {
		throw new exception('Could not create record for recipient "'.$sRecipName.'".');
	    }
	    $rcRecip		= $this->CustomerTable($idRecip);
	    $idRecipAddr	= $rcRecip->AddrID();
	} else {
	    $idRecipAddr	= $rsFields->GetFieldValue_forIndex(KI_CART_RECIP_CHOICE,FALSE);
	    if (is_null($idRecipAddr)) {
		$sMsg = "Cart ID $idCart has no recipient address choice.";
		$this->AdminEcho($sMsg);
		$rcOrd->AddMessage(
		  KSI_ORD_MSG_INT,	// media = internal message
		  'admins',		// TO
		  'import process',	// FROM
		  'missing data',	// RE
		  $sMsg);
		$idRecip = NULL;
		$sRecipAddr = $rsFields->GetRecipAddressString();
	    } else {
		$rcRecipAddr	= $this->CustomerAddressTable($idRecipAddr);
		$idRecip	= $rcRecipAddr->CustID();
		$sRecipAddr	= $rcRecipAddr->AsString();
	    }
	    $sRecipName		= $rsFields->RecipName();
	}

	// -- MESSAGE RECORD

	// get the shipping message, if there is one (FALSE = not required)
	$sMsg = $this->FieldRecords()->ShipMsg(FALSE);
	if (!is_null($sMsg)) {
	    //$	rcOrd = $this->OrderRecord();
	    $rcOrd->AddMessage(
	      KSI_ORD_MSG_INSTRUC,	// media = order instructions
	      'customer',
	      'shipper',
	      'instructions',
	      $sMsg);
	}
*/
	// order totals
	$oTotals = $this->DisplayObject(FALSE);
	$curItemTotal = $oTotals->TotalSale();
	$curShipItem =  $oTotals->TotalItemShipping();
	$curShipPkg =	$oTotals->TotalPackageShipping();

	// UPDATE the ORDER RECORD

	$db = $this->GetConnection();
	$arUpd = array(
	  'ID_Cart'		=> $idCart,
	  'WebTotal_Merch'	=> $db->Sanitize_andQuote($curItemTotal),
	  'WebTotal_Ship' 	=> $db->Sanitize_andQuote($curShipItem+$curShipPkg),
	  'WebTotal_Final'	=> $db->Sanitize_andQuote($curItemTotal+$curShipItem+$curShipPkg),
	  'ID_Buyer'		=> $db->Sanitize_andQuote($idBuyer),	// can be NULL
	  'ID_Recip'		=> $idRecip,
	  'ID_BuyerCard'	=> $db->Sanitize_andQuote($idBuyerCard),	// this could be NULL when we support other payment methods
	  'ID_RecipAddr'	=> $db->Sanitize_andQuote($idRecipAddr),	// this could be NULL when we support in-store pickup
	  'BuyerName'		=> $db->Sanitize_andQuote($rcBuyerAddr->NameString()),
	  'RecipName'		=> $db->Sanitize_andQuote($rcRecipAddr->NameString()),
	  'RecipAddr'		=> $db->Sanitize_andQuote($rcRecipAddr->AsString()),
	  'WhenCarted'		=> $db->Sanitize_andQuote($this->WhenCreated()),
	  'WhenPorted'		=> 'NOW()',	// when cart record was imported
	  'WhenPlaced'		=> 'NULL',	// when order formally placed -- customer may be revising order, so mark it as not placed yet
	  );
	$rcOrder->Update($arUpd);

	$this->AdminEcho('Order Update SQL: '.$rcOrder->sql.'<br>');
	$rcEv->Finish();
	
	$rcOrder->Reload();	// ...or we could set the fields individually
	$rcOrder->CheckIntegrity();	// look for obvious problems with the imported data

	return TRUE;
    }
    /*-----
     ACTION: Create order lines from cart lines
    */
    private function ToOrder_Lines(vcrOrder $oOrd) {
	$tCL = $this->LineTable();
	$rsCL = $this->LineRecords();	// shopping cart lines to convert
	$idOrder = $oOrd->GetKeyValue();	// Order ID
	$oOrd->ZeroLines();		// zero out any existing order lines in case customer edits cart
	//$tOrd = $oOrd->Table();
	$tOL = $oOrd->LineTable();	// Order Lines table
	$out = NULL;

	if ($rsCL->HasRows()) {
	      $nLines = $rsCL->RowCount();
	      $sS = fcString::Pluralize($nLines);
	      $sDescr = "copying $nLines line$sS to order ID $idOrder";
	      $this->AdminEcho($sDescr.'<br>');
	      $arEv = array(
		fcrEvent::KF_CODE		=> 'CLO',
		fcrEvent::KF_DESCR_START	=> $sDescr,
		fcrEvent::KF_WHERE		=> __METHOD__,
	      );
	    $rcEv = $this->CreateEvent($arEv);
	    $intNew = 0;
	    $intUpd = 0;
	    // apparently something is reading the rows before we get here, so rewind:
	    $rsCL->RewindRows();
	    while ($rsCL->NextRow()) {
		$this->AdminEcho('Proecessing cart line...<br>');
		$intSeq = $rsCL->GetSequence();
		$idItem = $rsCL->GetItemID();
		$intQty = $rsCL->GetQtyOrd();
		$dtWhenAdded = $rsCL->GetWhenAdded();
		$dtWhenEdited = $rsCL->GetWhenEdited();

		$oSZ = $this->ShipZoneObject();
		$rsCL->RenderCalc(/*$oSZ*/);

		// update array for each ORDER LINE
		$rcItem = $rsCL->ItemRecord();		// get item data
		$db = $this->GetConnection();
		$arUpd = array(
		  'CatNum'	=> $db->Sanitize_andQuote($rsCL->CatNum()),
		  'Descr'	=> $db->Sanitize_andQuote($rcItem->Description_forCart_text()),
		  'QtyOrd'	=> $intQty,
		  'Price'	=> $db->Sanitize_andQuote($rcItem->PriceSell()),
		  // pkg shipping cost for current shipping destination:
		  'ShipPkg'	=> $db->Sanitize_andQuote($rsCL->ShipCost_Pkg_forDest($oSZ)),
		  // item shipping cost for current shipping destination:
		  'ShipItm'	=> $db->Sanitize_andQuote($rsCL->ShipCost_Unit_forDest($oSZ)),
		  'isShipEst'	=> 'FALSE'
		  );

		// has this item already been transcribed?
		$rcOL = $tOL->Find_byOrder_andItem($idOrder,$idItem);
//		$sqlFilt = '(ID_Order='.$this->KeyValue().') AND (ID_Item='.$idItem.')';
//		$objOrdItems = $tCL->GetData($sqlFilt);
		if ($rcOL->RowCount() > 0)  {
		    // already transcribed -- update existing record
		    $rcOL->Update($arUpd);
		    $sql = $rcOL->sql;
		    $this->AdminEcho("Update SQL: $sql<br>");
		    $intUpd++;
		} else {
		    // not already transcribed -- insert new record
		    $arIns = $arUpd + array(
		      'ID_Order' 	=> $idOrder,
		      'Seq'		=> $intSeq,
		      'ID_Item'		=> $idItem
		      );
		    $tOL->Insert($arIns);
		    $sql = $tOL->sql;
		    $this->AdminEcho("Insert SQL: $sql<br>");
		    $intNew++;
		}
	    }
	    $sS = fcString::Pluralize($intNew);
	    $strLines = "$intNew order line$sS created";
	    if ($intUpd > 0) {
		$sS = fcString::Pluralize($intUpd);
		$strLines .= " and $intUpd order line$sS updated";
	    }
	    $strLines .= ' from cart lines';
	    $this->AdminEcho($strLines.'<br>');
	    $rcEv->Finish(
	      array(
		fcrEvent::KF_DESCR_FINISH	=> $strLines
		)
	      );
	} else {
	    $arEv = array(
	      fcrEvent::KF_CODE		=> 'CLX',
	      fcrEvent::KF_DESCR_START	=> 'No cart lines found at order creation time',
	      fcrEvent::KF_WHERE	=> __METHOD__,
	      fcrEvent::KF_IS_ERROR	=> TRUE,
	      fcrEvent::KF_IS_SEVERE	=> TRUE,
	      );
	    $this->StartEvent($arEv);
	    $out = '<b>There has been an error</b>: your cart contents seem to be missing.';
	    // TODO: make sure this sends an alert email
	}
	return TRUE;
    }

    // -- CONVERSION TO ORDER -- //
    // ++ STUB ++ //

    protected function AdminEcho($sText) {
	// do nothing; descendants may choose to display this
    }

    // -- STUB -- //
}
