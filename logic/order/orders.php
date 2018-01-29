<?php
/*
  PURPOSE: ORDER MANAGEMENT CLASSES
  LATER: Split into 3 parts:
    (1) core pieces, like table name
    (2) pieces only used by cart
    (3) (already done - class VbzAdminOrders) pieces only used by admin system
  HISTORY:
    2012-04-17 extracting from shop.php
    2013-11-23 moved KS_URL_PAGE_ORDER[S] here from site.php
*/

// ORDER MESSAGE TYPES
// these reflect the values in the ord_msg_media table (said table is probably redundant)

define('KSI_ORD_MSG_INSTRUC',	1);	// Instructions in submitted order
define('KSI_ORD_MSG_PKSLIP',	2);	// Packing slip
define('KSI_ORD_MSG_EMAIL',	3);	// Email
define('KSI_ORD_MSG_PHONE',	4);	// Phone call
define('KSI_ORD_MSG_MAIL',	5);	// Snail mail
define('KSI_ORD_MSG_FAX',	6);	// Faxed message
define('KSI_ORD_MSG_LABEL',	7);	// Shipping label (for delivery instructions)
define('KSI_ORD_MSG_INT',	8);	// internal use - stored, not sent

class vctOrders extends vcBasicTable {
    use vtFrameworkAccess;

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'orders';
    }
    protected function SingularName() {
	return 'vcrOrder';
    }
    // NOTE: Probably needed for event logging
    public function GetActionKey() {
	return 'ord';
    }

    // ++ STATIC ++ //

    const MT_INSTRUC	= KSI_ORD_MSG_INSTRUC;
    const MT_PKSLIP	= KSI_ORD_MSG_PKSLIP;
    const MT_EMAIL	= KSI_ORD_MSG_EMAIL;
    const MT_PHONE	= KSI_ORD_MSG_PHONE;
    const MT_MAIL	= KSI_ORD_MSG_MAIL;
    const MT_FAX	= KSI_ORD_MSG_FAX;
    const MT_LABEL	= KSI_ORD_MSG_LABEL;
    const MT_INT	= KSI_ORD_MSG_INT;

    // -- STATIC -- //
    // ++ SEARCHING ++ //

    protected function Search_forText($sFind) {
	$sqlFind = $this->GetConnection()->Sanitize_andQuote($sFind.'%');
	$sqlFilt = "(BuyerName LIKE $sqlFind)"
	  ." OR (RecipName LIKE $sqlFind)"
	  ." OR (RecipAddr LIKE $sqlFind)";
	$rs = $this->SelectRecords($sqlFilt);
	return $rs;
    }
    protected function Search_forOrdNum($sFind) {
	$sqlFind = $this->GetConnection()->Sanitize_andQuote(strtoupper($sFind));
	$sqlFilt = "(Number LIKE $sqlFind)";
	$rs = $this->SelectRecords($sqlFilt);
	return $rs;
    }

    // -- SEARCHING -- //
    // ++ ACTION ++ //

    /*-----
     FUNCTION: Create()
     ACTION: create the order record (fill in minimal fields)
    */
    public function Create() {
	$nSeq = $this->AppObject()->SettingsTable()->UseNextOrderSequence();
	$sSeq = sprintf(KS_ORD_NUM_FMT,$nSeq);
	$sNum = KC_ORD_NUM_PFX.$sSeq;
	$db = $this->GetConnection();
	$arIns = array(
	    'Number'		=> $db->Sanitize_andQuote($sNum),
	    'SortPfx'		=> KSQL_ORD_NUM_SORT,
	    'WhenCreated'	=> 'NOW()'
	    );
	$id = $this->Insert($arIns);
	if ($id === FALSE) {
	    throw new exception('Internal Error: Could not create order.');
	}
	return $id;
    }
    
    // -- ACTION -- //
}
class vcrOrder extends vcBasicRecordset {
    //use ftLoggableRecord;
    use vtFrameworkAccess;
    //use vtLoggableAdminObject;

    private $rcCart;	// shopping cart
    private $rcCard;	// payment card

    // ++ INITIALIZATION ++ //

    protected function InitVars() {
	$this->rcCart = NULL;
	$this->rcCard = NULL;
    }

    // -- INITIALIZATION -- //
    // ++ SPECIALIZED EVENT LOGGING ++ //

    protected function StartEvent_Simple($iCode,$iDescr,$iWhere) {
	throw new exception('2016-10-28 This will need updating.');
	//$this->objDB->OrderLog()->Add($this->ID,$iCode,$iDescr);
	$arEv = array(
	  'descr'	=> $iDescr,
	  'type'	=> $this->Table->ActionKey(),
	  'id'		=> $this->GetKeyValue(),
	  'where'	=> $iWhere,
	  'code'	=> $iCode);
	return $this->StartEvent($arEv);
    }
    public function LogMessage($idPackage,$idMedia,$sTxtFrom,$sTxtTo,$sSubject,$sMessage) {
	$this->MessageTable()->Add(
	  $this->GetKeyValue(),
	  $idPackage,
	  $idMedia,
	  $sTxtFrom,
	  $sTxtTo,
	  $sSubject,
	  $sMessage);
    }

    // -- SPECIALIZED EVENT LOGGING -- //
    // ++ CLASS NAMES ++ //

    protected function CartsClass() {
	return 'vctShopCarts';
    }
    protected function LinesClass() {
	return 'vctOrderLines';
    }
    protected function CustomersClass() {
	return 'vctCusts';
    }
    protected function CardsClass() {
	return 'vctCustCards_dyn';
    }
    protected function MessagesClass() {
	return 'vctOrderMsgs';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function CartTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CartsClass(),$id);
    }
    /*----
      PUBLIC because Cart calls it during conversion to Order
    */
    public function LineTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->LinesClass(),$id);
    }
    protected function CustomerTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CustomersClass(),$id);
    }
    protected function CardTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CardsClass(),$id);
    }
    protected function MessageTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->MessagesClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    protected function CartRecord() {
	if ($this->HasCart()) {
	    if (is_null($this->rcCart)) {
		$this->rcCart = $this->CartTable($this->GetCartID());
	    }
	    return $this->rcCart;
	} else {
	    return NULL;
	}
    }
    protected function CartRecord_orError() {
	$rc = $this->CartRecord();
	if (!is_object($rc)) {
	    $idCart = $this->CartID();
	    throw new exception("Could not retrieve object for cart ID $idCart.");
	}
	return $rc;
    }
    public function Lines() {
	throw new exception('Function call deprecated; use LineRecords().');
    }
    public function LineRecords() {
	return $this->LineTable()->FetchRecords_forOrderID($this->GetKeyValue());
    }
    // PUBLIC because cart-to-order process uses it
    public function BuyerRecord() {
	$id = $this->GetBuyerID();
	if (empty($id)) {
	    throw new exception('Requested RecipRecord, but Buyer ID is not set.');
	}
	$rc = $this->CustomerTable($id);
	return $rc;
    }
    // PUBLIC because cart-to-order process uses it
    public function RecipRecord() {
	$id = $this->GetRecipID();
	if (empty($id)) {
	    throw new exception('Requested RecipRecord, but Recip ID is not set.');
	}
	$rc = $this->CustomerTable($id);
	return $rc;
    }
    protected function CardRecord() {
	$idCard = $this->CardID();
	if (empty($idCard)) {
	    throw new exception('Attempting to fetch Card record when Card ID has not been set.');
	}
	return $this->CardTable($idCard);
    }

    // -- DATA RECORD ACCESS -- //
    // ++ FIELDS ++ //

      //++local++//
	//++values++//
    
    /*----
      PUBLIC because Package objects need to access it for admin UI
    */
    public function Number() {
	throw new exception('Number() is deprecated; call NumberString() instead.');
	// TODO: deprecate this; use NumberString() instead.
	return $this->Value('Number');
    }
    public function NumberString() {
	return $this->GetFieldValue('Number');
    }
    protected function CardID() {
	return $this->GetFieldValue('ID_BuyerCard');
    }
    /*----
      HISTORY:
	2014-01-16 This might need to be made writable, but I'm leaving it read-only for now.
	2014-10-05 Cart needs to be able to set this so order object can use it during cart->order conversion.
    */
    public function SetCartID($id) {
	return $this->SetFieldValue('ID_Cart',$id);
    }
    public function GetCartID($id=NULL) {
	return $this->GetFieldValue('ID_Cart');
    }
    
	  //++timestamps++//
    
    protected function WhenCreated() {
	return $this->GetFieldValue('WhenCreated');
    }
    // PUBLIC so tricky item-order query class can use it
    public function WhenPlaced() {
	return $this->GetFieldValue('WhenPlaced');
    }
    // PUBLIC so Table object can use it when listing Orders
    public function WhenNeeded() {
	return $this->Value('WhenNeeded');
    }
    protected function WhenClosed() {
	return $this->GetFieldValue('WhenClosed');
    }
    
	  //--timestamps--//
	  //++buyer++//

    protected function SetBuyerID($id) {
	return $this->SetFieldValue('ID_Buyer',$id);
    }
    protected function GetBuyerID() {
	return $this->GetFieldValue('ID_Buyer');
    }
    protected function BuyerName() {
	return $this->GetFieldValue('BuyerName');
    }

	  //--buyer--//
	  //++recip++//
	  
    /*----
      PUBLIC so cart can set it after order conversion.
	This might be unnecessary later.
    */
    public function SetRecipID($id) {
	return $this->SetFieldValue('ID_Recip',$id);
    }
    protected function GetRecipID() {
	return $this->GetFieldValue('ID_Recip');
    }
    protected function RecipName() {
	return $this->GetFieldValue('RecipName');
    }
    /*----
      HISTORY:
	2016-06-13 Added this for revised Order display.
    */
    protected function RecipAddrID() {
	return $this->GetFieldValue('ID_RecipAddr');
    }
    /*----
      HISTORY:
	2014-12-14 moved this back here from VC_Order (later vcrAdminOrder)
	  Not sure if there is a semantic difference between RecipAddr() and RecipAddr_text().
	2016-06-13 Renamed RecipAddr() to RecipAddrText(); removed RecipAddr_text().
    */
    protected function RecipAddrText() {
	return $this->GetFieldValue('RecipAddr');
    }

	  //--recip--//
	//--values--//
	//++calculations++//
    
    /*----
      PUBLIC so checkout process can access it
    */
    public function IsPlaced() {
	return !is_null($this->WhenPlaced());
    }
    protected function HasBuyer() {
	return (!is_null($this->GetBuyerID()));
    }
    protected function HasRecip() {
	return (!is_null($this->GetRecipID()));
    }
    protected function HasCart() {
	return !is_null($this->GetCartID());
    }
    protected function HasCard() {
	return !is_null($this->CardID());
    }

    /*----
      ACTION: Check imported Order data for integrity issues.
	For now, we're lazy and just make sure that RecipID got set.
      NOTE:
	2016-03-07 Making cart data more fault-tolerant for now (mainly while dealing with old data)
    */
    public function CheckIntegrity() {
	if (!$this->HasRecip()) {
	    echo 'arUpd:'.fcArray::Render($arUpd);
	    throw new exception('Internal Error: Local Recip ID is null after order conversion. This should not happen.');
	}
    }

      //--local--//
      //++foreign++//

    protected function SessionID() {
	return $this->SessionRecord()->GetKeyValue();
    }
    /*----
      TODO: We will probably want to add an email address field to the Order table.
	For now, we pull it from the buyer-Customer data (which stores multiple email addresses
	and doesn't track which one is the primary; this should also be fixed).
    */
    protected function BuyerEmailAddress() {
	if ($this->HasBuyer()) {
	    $rcBuyer = $this->BuyerRecord();
	    return $rcBuyer->EmailsText();
	} else {
	    throw new exception("Internal Error: Expected Buyer record, but there isn't one set.");
	}
    }
    protected function BuyerPhoneNumber($sDefault=NULL) {
	if ($this->HasBuyer()) {
	    $rcBuyer = $this->BuyerRecord();
	    return $rcBuyer->PhonesText();
	} else {
	    return $sDefault;
	}
    }
    /*----
      RETURNS: Text of the latest customer-to-store order message.
    */
    protected function MessageText() {
	$tMsgs = $this->MessageTable();
	$rcMsg = $tMsgs->Record_forOrder($this->GetKeyValue(),vctOrders::MT_INSTRUC);
// 	$rcMsg->NextRow();
	if ($rcMsg->HasRows()) {
	    return $rcMsg->MessageText();
	} else {
	    return NULL;
	}
    }
    // 2014-11-03 what is this used for?
    protected function BuyerAddress_text() {
	if ($this->HasBuyer()) {
	    $rc = $this->BuyerRecord();
	    return $rc->Address_text();
	} else {
	    return NULL;
	}
    }
    protected function RecipAddress_text() {
	if ($this->HasRecip()) {
	    $rc = $this->RecipRecord();
	    return $rc->Address_text("<br>\n");
	} else {
	    return NULL;
	}
    }
    protected function RecipEmailAddress() {
	$rc = $this->RecipRecord();
	return $rc->EmailsText();
    }
    protected function RecipPhoneNumber() {
	$rc = $this->RecipRecord();
	return $rc->PhonesText();
    }

    /*
      HISTORY:
	2014-10-09 this may be duplication of $this->PaymentSafeText(); disabled this.
	2014-10-19 was PaymentSafeText() ever written? Can't find it.
	  Re-enabling this and renaming from CardSummary() to PaymentSafeText().
    */
    protected function PaymentSafeText() {
	if ($this->HasCard()) {
	    $rcCard = $this->CardRecord();
	    $out = $rcCard->SafeDescr_Long();
	} else {
	    $out = '<i>no card</i>';
	}
	return $out;
    }

      //--foreign--//
    // -- FIELDS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Update the Order record to reflect the fact that it has now officially been received.
	* set WhenReceived timestamp
	* TODO: figure out why Cart still shows "[use]" link
    */
    public function MarkAsPlaced() {
	$arUpd = array(
	  'WhenPlaced'	=> 'NOW()',
	  );
	$this->Update($arUpd);
    }
    /*----
      USAGE: during cart->order import process
      NOTE:
	2016-06-26 For now: assume that if the Order already has a Buyer ID, then we just want to use that.
    */
    public function CreateBuyerID($idUser) {
	if ($this->HasBuyer()) {
	    $idContact = $this->GetBuyerID();
	} else {
	    // create BUYER Master record
	    $tCust	= $this->CustomerTable();
	    $idContact	= $tCust->CreateRecord($idUser);
	    $this->SetBuyerID($idContact);	// save locally
	}
	return $idContact;
    }
    /*----
      USAGE: during cart->order import process
      NOTE:
	2016-06-26 For now: assume that if the Order already has a Recip ID, then we just want to use that.
    */
    public function CreateRecipID($idUser) {
	if ($rcOrder->HasRecip()) {
	    $idContact = $rcOrder->RecipID();
	} else {
	    // create RECIP Master record
	    $tCust	= $this->CustomerTable();
	    $idContact	= $tCust->CreateRecord($idUser);
	    $this->RecipID($idContact);	// save locally
	}
	return $idContact;
    }
    /*----
      ACTION: Zero all existing order lines
	This allows reuse of the line records when the same item
	is added back in, eliminating gaps in the numbering (which
	should only happen if there is data corruption).
    */
    public function ZeroLines() {
	$id = $this->GetKeyValue();
	$this->LineTable()->Update(
	  array('QtyOrd'=> 0),	// fields
	  'ID_Order='.$id	// condition
	  );

    }
    // ACTION: Always create a new message record.
    public function AddMessage(
      $idMedia,
      $sTxtFrom,
      $sTxtTo,
      $sSubject,
      $sMessage
      ) {
	$tMsgs = $this->MessageTable();
	$ok = $tMsgs->Add(
	  $this->GetKeyValue(),
	  NULL,			// no package
	  $idMedia,
	  $sTxtFrom,
	  $sTxtTo,
	  $sSubject,
	  $sMessage
	  );
    }
    // ACTION: Create a new message record if the given specs don't match one that already exists. 
    public function CreateOrderInstructions(
      $sMessage
      ) {
	$db = $this->GetConnection();
	$tMsgs = $this->MessageTable();
	
	// check to see if there's already an instruction message for this order:
	$rs = $tMsgs->SelectRecords('ID_Ord='.$this->GetKeyValue().' AND (ID_Pkg IS NULL) AND (ID_Media='.KSI_ORD_MSG_INSTRUC.')');
	if ($rs->HasRows()) {
	    // there is a record -- update it
	    $arUpd = array(
	      'WhenEntered'	=> 'NOW()',
	      'Messsage'	=> $db->Sanitize_andQuote($sMessage),
	    );
	    $rs->NextRow();
	    $rs->Update($arUpd);
	} else {
	    // no record found; create a new one
	    $this->AddMessage(
	      KSI_ORD_MSG_INSTRUC,
	      'them',
	      'us',
	      'order instructions',
	      $sMessage
	      );
	}
    }

    // -- ACTIONS -- //
    // ++ CHECKOUT UI: WEB ++ //

    /*----
      ACTION: Render the order data for the Confirmation page in the checkout process.
      QUESTIONS:
	* Do we ever want to render this *without* RenderConfirm_footer()?
	* Do we want to show the full credit card information here?
    */
    public function RenderConfirm() {
	$out = NULL;

	if (($this->IsNew())) {
	    throw new exception("Internal Error: No Order object at checkout time.");
	}

	$arVars = $this->FillTemplateArray();
	$arVars['ship.addr'] = $this->RecipAddrText();
	$arVars['ship.message']	= $this->MessageText();

	$oStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$oStrTplt->MarkedValue(KHT_ORDER_CONFIRM);
	$out = $oStrTplt->Replace();
	return $out;
    }
    // QUESTION: Can't we just call this from RenderConfirm()?
    static public function RenderConfirm_footer() {
	$ksArgPageData = KSQ_ARG_PAGE_DATA;
	$ksArgPageShow = KSQ_PAGE_CONF;
	$out = <<<__END__
  <tr>
    <td colspan=2 align=center bgcolor=ffffff class=section-title>
      <input type=hidden name="$ksArgPageData" value="$ksArgPageShow">
      <input type=submit name="btn-go-prev" value="&lt; Make Changes">
      <input type=submit name="btn-go-order" value="Place the Order!">
    </td>
  </tr>
__END__;
	return $out;
    }
    /*----
      ACTION: Render the receipt in HTML, based on Order data
      HISTORY:
	2014-11-03 This *was* showing the order as understood by the Cart, but that's not
	  what we should be seeing at this point. Converting it to use Order data.
    */
    public function RenderReceipt() {
	if (($this->IsNew())) {
	    throw new exception("Internal Error: No Order object at checkout time.");
	}
/*
	$idOrder = $this->KeyValue();
	$idCart = $this->Value('ID_Cart');
	$idSess = $this->SessionID(); */
/*
	$arVars = array(
	  'doc.title'	=> 'Order #<b>'.$this->Number().'</b>',
	  'timestamp'	=> date(KF_RCPT_TIMESTAMP),
	  'order.id'	=> $idOrder,
	  'cart.id'	=> $idCart,
	  'sess.id'	=> $idSess,
	  'cart.detail'	=> $this->RenderContents(),
	  'ship.name'	=> $this->RecipName(),
	  'ship.addr'	=> $this->RecipAddress_text(),
	  'pay.name'	=> $this->BuyerName(),
	  'pay.spec'	=> $this->PaymentSafeText(),
	  'url.shop'	=> KWP_HOME_REL,
	  'email.short'	=> 'orders-'.date('Y').'@'.KS_EMAIL_DOMAIN
	  ); */
	$arVars = $this->FillTemplateArray();
	$arVars['ship.addr'] = $this->RecipAddress_text();
	
	/* 2016-08-08 old version
	$oStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$oStrTplt->MarkedValue(KHT_ORDER_RECEIPT);
	$out = $oStrTplt->Replace();
	echo 'TEMPLATE:<br>'.$oStrTplt->Value;
	*/
	$oTplt = new fcTemplate_array();
	$oTplt->MarkedValue(KHT_ORDER_RECEIPT);
	$oTplt->VariableValues($arVars);
	$out = $oTplt->Render();
	return $out;
    }
    protected function FillTemplateArray() {
	$idOrder = $this->GetKeyValue();
	$idCart = $this->GetCartID();
	$idSess = $this->SessionID();
	
	$arVars = array(
	  'doc.title'	=> 'Order #<b>'.$this->NumberString().'</b>',
	  'timestamp'	=> date(KF_RCPT_TIMESTAMP),
	  'order.id'	=> $idOrder,
	  'cart.id'	=> $idCart,
	  'sess.id'	=> $idSess,
	  'cart.detail'	=> $this->RenderContents(),
	  'ship.name'	=> $this->RecipName(),
	  //'ship.addr'	=> $this->RecipAddress_text(),	// different formats
	  'pay.name'	=> $this->BuyerName(),
	  'pay.email'	=> $this->BuyerEmailAddress(),
	  'pay.phone'	=> $this->BuyerPhoneNumber('<i>(none)</i>'),
	  'pay.spec'	=> $this->PaymentSafeText(),
	  'url.shop'	=> KWP_HOME_REL,
	  'email.short'	=> 'orders-'.date('Y').'@'.KS_EMAIL_DOMAIN
	  );
	return $arVars;
    }

    // -- CHECKOUT UI: WEB -- //
    // ++ CHECKOUT UI: EMAIL ++ //

    /*-----
      ACTION: Renders the order contents as plaintext, suitable for emailing
      HISTORY:
	2010-09-13 writing started (...and never finished)
	2014-10-08 Adapting from commented-out Cart class text-receipt method.
	  (Cart class does not *need* a text-receipt method.)
    */
    public function RenderReceipt_Text() {
// copy any needed constants over to variables for parsing:
	$ksShipMsg	= KSF_SHIP_MESSAGE;
	$ksfCustCardNum = KSF_CART_PAY_CARD_NUM;
	$ksfCustCardExp = KSF_CART_PAY_CARD_EXP;

// get non-address field data:
	//$strCardNum = $this->CardSummary();
	$strCustShipMsg = $this->MessageText();

	$ftCustShipMsg = wordwrap($strCustShipMsg);
/*
	$this->doFixedCard = TRUE;
	$this->doFixedSelf = TRUE;
	$this->doFixedName = TRUE;
	$this->htmlBeforeAddress = '';
	$this->htmlBeforeContact = '';
*/
// the exact routes by which some fields are fetched may need tweaking...
	if ($this->IsNew()) {
	    throw new exception('Internal error: attempting to render receipt before Order record is loaded.');
	}

	$strShipName	= $this->RecipName();

	$sRecipAddr	= $this->RecipAddrText();
	$sRecipAddr	= str_replace("\n","\n  ",$sRecipAddr);	// indent lines
	$sRecipEmail	= $this->RecipEmailAddress();
	$sRecipPhone	= $this->RecipPhoneNumber();

	$sBuyerName	= $this->BuyerName();
//	$sBuyerAddr	= $this->BuyerAddress_text();
	$sBuyerEmail	= $this->BuyerEmailAddress();
	$sBuyerPhone	= $this->BuyerPhoneNumber();
	$sPaymentText	= $this->PaymentSafeText();

	$ftCart = $this->RenderContents_Text();

	$out = <<<__END__
ITEMS ORDERED:
$ftCart
============
SHIP TO:
  $strShipName
  $sRecipAddr

  Email: $sRecipEmail
  Phone: $sRecipPhone

__END__;

	if (empty($strCustShipMsg)) {
	    $out .= "(No special instructions)";
	} else {
	    $out .= "--Instructions from you--\n$ftCustShipMsg";
	}
	$out .= <<<__END__

============
ORDERED BY:
  $sBuyerName
  Email: $sBuyerEmail
  Phone: $sBuyerPhone
  Payment: $sPaymentText
__END__;

	return $out;
    }
    /*----
      RETURNS: HTML static (non-form) rendering of Order contents, including Order Lines and totals
      ASSUMES: Cart Lines have been copied over.
    */
    protected function RenderContents() {
	$oPainter = new vcCartDisplay_full_ckout(NULL);
	// render order lines
	$rsLines = $this->LineRecords();
	if (!$rsLines->HasRows()) {
	    $sOrder = $this->Number();
	    throw new exception("Internal error: Order #$sOrder has no items.");
	}
	while($rsLines->NextRow()) {
	    $oLine = $rsLines->GetRenderObject_static();
	    $oPainter->AddLine($oLine);
	}

	return $oPainter->Render();
    }
    /*----
      RETURNS: plaintext rendering of Order Lines (the order's copy of Cart Lines)
    */
    protected function RenderContents_Text() {
	//$rsLines = $this->LineRecords();
	//return $rsLines->RenderText_lines(KS_FMT_TEXT_ORD_LINE);

	$oPainter = new vcCartDisplay_full_TEXT(NULL);

	// render order lines
	$rsLines = $this->LineRecords();
	while($rsLines->NextRow()) {
	    $oLine = $rsLines->GetRenderObject_text();
	    $oPainter->AddLine($oLine);
	}

	return $oPainter->Render();

    }
    /*----
      ACTION: Email an order confirmation (or show what would be sent)
      RETURNS: contents of the email
      HISTORY:
	2010-10-08 moved from VbzAdminOrder to clsOrder
      INPUT:
	iReally: if FALSE, does not actually send the email, but only renders (and returns) it
	iParams: array of specifications for how to send the email
	  iParams['to-self']: if TRUE, send a copy to the store admin's address
	  iParams['to-cust']: if TRUE, send a copy to the customer's address
	  iParams['addr-self']: email address for store admin
	  iParams['addr-cust']: email address for customer
	  iParams['subject']: subject of the email
	  iParams['message']: body of the email
    */
    //public function EmailConfirm($iReally, $iSendToSelf, $iSendToCust, $iAddrSelf, $iAddrCust, $iSubject, $iMessage) {
    public function EmailConfirm($iReally, array $iParams) {
	$iSendToSelf = $iParams['to-self'];
	$iSendToCust = $iParams['to-cust'];
	$iAddrSelf = $iParams['addr-self'];
	$iAddrCust = $iParams['addr-cust'];
	$iSubject = $iParams['subject'];
	$iMessage = $iParams['message'];

	$htEmailAddr_Self = fcString::EncodeForHTML($iAddrSelf);
	$htEmailAddr_Cust = fcString::EncodeForHTML($iAddrCust);

	$htEmailBody = fcString::EncodeForHTML($iMessage);
	$txtSubj = $iSubject;
	$htSubj = fcString::EncodeForHTML($txtSubj);
	$intCustCopy = $iSendToCust?'1':'0';
	$txtCustCopy = $iSendToCust?'YES':'no';
	$txtSelfCopy = $iSendToSelf?'YES':'no';
	$doSend = $iReally;

	$out = NULL;
	$out .= '<table>';
	$out .= "<tr><td align=right><b>Store's email address</b>:</td><td>$htEmailAddr_Self</td></tr>";
	$out .= "<tr><td align=right><b>Customer's email address</b>:</td><td>$htEmailAddr_Cust</td></tr>";
	$out .= "<tr><td align=right><b>Subject</b>:</td><td>$htSubj</td></tr>";
	$out .= "<tr><td align=right><b>Copying to customer?</td><td>$txtCustCopy</td></tr>";
	$out .= '</table>';

	$out .= '<pre>'.$htEmailBody.'</pre>';
	if (!$doSend) {
	    $out .= '<form method=post>';
	    $out .= '<input type=hidden name=email-body value="'.$htEmailBody.'">';
	    $out .= '<input type=hidden name=send-to-cust value="'.$intCustCopy.'">';
	    $out .= '<input type=hidden name=addr-self value="'.$htEmailAddr_Self.'">';
	    $out .= '<input type=hidden name=addr-cust value="'.$htEmailAddr_Cust.'">';
	    $out .= '<input type=hidden name=subject value="'.$htSubj.'">';
	    $out .= '<input type=submit name=btnSend value="Send email">';
	    $out .= '</form>';
	}

	if ($iReally) {
	    // if we're actually sending, then actually send the email and log it:

	    // log attempt to send email (EM: email/manual)
	    $arEv = array(
	      'descr'	=> 'emailing order confirmation (self:'.$txtSelfCopy.' cust:'.$txtCustCopy.') Subject: '.$txtSubj,
	      'code'	=> 'OEM',
	      'where'	=> __METHOD__
	      );
	    $rcEv = $this->CreateEvent($arEv);

	    if ($iSendToCust) {
		// if being sent to customer. record the email in the messages table
	    }
	    $okSelf = TRUE;
	    if ($iSendToSelf) {
		// send our copy of the email
		$okSelf = mail($iAddrSelf,$txtSubj.' (store copy)',$iMessage,"From: $iAddrCust");
	    }
	    $okCust = TRUE;
	    if ($iSendToCust) {
		// log the message we're trying to send
		global $vgUserName;
		$this->LogEmail(NULL,$vgUserName,'customer',$txtSubj,$iMessage);

		// send the message to the customer
		$okCust = mail($iAddrCust,$txtSubj,$iMessage,"From: $iAddrSelf");
	    }

	    // log event completion
	    if ($okSelf && $okCust) {
		$arEv = NULL;
	    } else {
		$arEv = array(
		  'descrfin'	=> "self ok:$okSelf | cust ok:$okCust",
		  'error'	=> TRUE,
		  'severe'	=> TRUE);
	    }
	    $rcEv->Finish($arEv);
	}
	return $out;
    }
    public function LogEmail($iPackage,$iTxtFrom,$iTxtTo,$iSubject,$iMessage) {
	$this->LogMessage(
	  $iPackage,
	  KSI_ORD_MSG_EMAIL,
	  $iTxtFrom,
	  $iTxtTo,
	  $iSubject,
	  $iMessage);
    }

    // -- CHECKOUT UI: EMAIL -- //
    // ++ CHECKOUT UI: auxiliary ++ //

    /*----
      RETURNS: Email address to use for this order (includes order number in address, as a way of tracking
	spam and possibly later as a way of automatically filing order-related correspondence)
      TODO: Rename this so it is clearer that this is the FROM address.
    */
    public function EmailAddr() {
	throw new exception('Does anything call this? It should use the template defined in site.php.');
	return 'order-'.$this->NumberString().'@'.KS_EMAIL_DOMAIN;
    }
    /*----
      RETURNS: array of values needed to plug into email templates
      USED BY:
	cart class, to generate confirmation email
	order admin class, to display/generate confirmation email
    */
    public function TemplateVars() {

	$rcCart = $this->CartRecord_orError();
	
	/* 2016-08-08 old version
	$rsCData = $rcCart->FieldRecords();
	//$rcCust = $this->BuyerRecord();
	$ofPay = $rsCData->PayFields();
	$ofBuyer = $rsCData->RecipFields();
	$sEmailBuyer = $rsCData->BuyerEmailAddress_entered(TRUE);

	$arVars = array(
	  KS_TVAR_CUST_NAME	=> $ofPay->CardNameValue(),
	  //KS_TVAR_CUST_EMAIL	=> $rsCData->CustEmail(),
	  //'orders-email' => $objPay->EmailValue(),
	  KS_TVAR_CUST_EMAIL	=> $sEmailBuyer,
	  KS_TVAR_ORDER_NUMBER	=> $this->Number()
	  ); */
	
	// 2016-08-08 new version
	$oFields = $rcCart->FieldsManager();
	$oBuyer = $oFields->BuyerObject();
	
	$arVars = array(
	  KS_TVAR_CUST_NAME	=> $oBuyer->GetNameFieldValue(),
	  KS_TVAR_CUST_EMAIL	=> $oBuyer->GetEmailFieldValue(),
	  KS_TVAR_ORDER_NUMBER	=> $this->NumberString(),
	  );

	// FOR DEBUGGING -- comment out later
	foreach($arVars as $key => $val) {
	    if (!is_string($val)) {
		if (is_object($val)) {
		    $sType = 'a '.get_class($val);
		} else {
		    $sType = gettype($val);
		}
		echo "<br>Variable <b>$key</b> is not a string. It is $sType.<br>";
		throw new exception('Internal error.');
	    }
	}

	return $arVars;
    }
    /*----
      RETURNS: array of values needed for sending confirmation email
      USED BY:
	cart class, to generate confirmation email
	order admin class, to display/generate confirmation email
    */
    public function EmailParams(array $iarVars, $iSubj=NULL, $iMsgPre=NULL) {
	$arVars = $iarVars;
	$objStrTplt = new clsStringTemplate_array(KS_TPLT_OPEN,KS_TPLT_SHUT,$arVars);

	$txtSubj = $iSubj;
	if (empty($txtSubj)) {
	    //$objStrTplt->MarkedValue(KS_TEXT_EMAIL_SUBJECT);
	    $txtSubj = $objStrTplt->Replace(KS_TPLT_ORDER_EMAIL_SUBJECT);
	}
	$arOut['subj'] = $txtSubj;

	$txtMsgPre = $iMsgPre;
	if (empty($txtMsgPre)) {
	    $txtMsgPre = $objStrTplt->Replace(KS_TPLT_ORDER_EMAIL_MSG_TOP);
	}
	$arOut['msg.pre'] = $txtMsgPre;

	$arVars['subject'] = $txtSubj;
	$objStrTplt->List = $arVars;

	//$objStrTplt->MarkedValue(KS_TEXT_EMAIL_ADDR_SELF);
	$txtAddr_Self = $objStrTplt->Replace(KS_TPLT_ORDER_EMAIL_ADDR_SELF);
	$arOut['addr.self'] = $txtAddr_Self;

	//$objStrTplt->MarkedValue(KS_TEXT_EMAIL_ADDR_CUST);
	$txtAddr_Cust = $objStrTplt->Replace(KS_TPLT_ORDER_EMAIL_ADDR_CUST);
	$arOut['addr.cust'] = $txtAddr_Cust;

	// Calculate text of email to send:
	$txtEmailBody = $txtMsgPre."\n".$this->RenderReceipt_Text();
	$arOut['msg.body'] = $txtEmailBody;

	return $arOut;
    }

    // -- CHECKOUT UI: auxiliary -- //
}
