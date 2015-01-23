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
// DEPRECATED
define('KS_URL_PAGE_ORDER',	'ord');	// must be consistent with events already logged
define('KS_URL_PAGE_ORDERS',	'orders');

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

class clsOrders extends clsDataTable_Menu {

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
    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('orders');
	  $this->KeyName('ID');
	  $this->ClassSng('clsOrder');
	  $this->ActionKey('ord');	// kluge
    }

    // -- SETUP -- //
    // ++ CALCULATIONS ++ //

    /*-----
     FUNCTION: NextOrdSeq()
     ACTION: get the next order sequence number
    */
    private function NextOrdSeq() {
	$objVars = $this->Engine()->VarsGlobal();
	$intOrdLast = $objVars->Val('ord_seq_prev');
	$intOrdThis = $intOrdLast+1;
	$objVars->Val('ord_seq_prev',$intOrdThis);
	return $intOrdThis;
    }

    // -- CALCULATIONS -- //
    // ++ SEARCHING ++ //

    protected function Search_forText($sFind) {
	$sqlFind = SQLValue($sFind.'%');
	$sqlFilt = "(BuyerName LIKE $sqlFind)"
	  ." OR (RecipName LIKE $sqlFind)"
	  ." OR (RecipAddr LIKE $sqlFind)";
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }
    protected function Search_forOrdNum($sFind) {
	$sqlFind = SQLValue(strtoupper($sFind));
	$sqlFilt = "(Number LIKE $sqlFind)";
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }

    // -- SEARCHING -- //
    // ++ ACTION ++ //

    /*-----
     FUNCTION: Create()
     ACTION: create the order record (fill in minimal fields)
    */
    public function Create() {
	$intSeq = $this->NextOrdSeq();
	$strSeq = sprintf(KS_ORD_NUM_FMT,$intSeq);
	$strNum = KC_ORD_NUM_PFX.$strSeq;
	$arIns = array(
	    'Number'	=> SQLValue($strNum),
	    'SortPfx'	=> SQLValue(KC_ORD_NUM_SORT),
	    'WhenStarted'	=> 'NOW()'
	    );
	$id = $this->Insert($arIns);
	if ($id === FALSE) {
	    throw new exception('Internal Error: Could not create order.');
	}
	return $id;
    }
    /*-----
    | FUNCTION: CopyCart()
    | ACTION: fill in the order record with data from the cart
    | DEPRECATED 2013-11-06, unless it turns out to be used
    |	somewhere *besides* the checkout process.
    */
/*
    public function CopyCart($iOrdID, clsShopCart $iCartObj) {
	assert('$iOrdID > 0');

// ** ITEMS IN CART (convert from cart lines to order items)
	//$objTbl = new clsShopCartLines($this->objDB);
	//$objRows = $objTbl->GetData('ID_Cart='.$this->ID);

	$objOrd = $this->GetItem($iOrdID);
	$objOrd->CopyCart($iCartObj);

// should this code be in $objOrd->CopyCart?
	// in session object, set Order ID and clear Cart ID
	// 2011-03-27 wrong. just set Order ID.
	$arUpd = array(
	  'ID_Order'	=> $iOrdID,
	  //'ID_Cart'	=> 'NULL'
	  );
	$iCartObj->Session()->Update($arUpd);
	// log the event
	$this->Engine()->LogEvent(
	  __METHOD__,
	  '|ord ID='.$iOrdID.'|cart ID='.$iCartObj->KeyValue(),
	  'Converted cart to order; SQL='.SQLValue($iCartObj->Session()->sqlExec),
	  'C>O',FALSE,FALSE);

	return $objOrd;	// this is used by the checkout process
    }
*/
}
class clsOrder extends clsVbzRecs {
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
	//$this->objDB->OrderLog()->Add($this->ID,$iCode,$iDescr);
	$arEv = array(
	  'descr'	=> $iDescr,
	  'type'	=> $this->Table->ActionKey(),
	  'id'		=> $this->KeyValue(),
	  'where'	=> $iWhere,
	  'code'	=> $iCode);
	$this->StartEvent($arEv);
    }
    public function LogMessage($iPackage,$iMethod,$iTxtFrom,$iTxtTo,$iSubject,$iMessage) {
	$this->Engine()->OrdMsgs()->Add(
	  $this->ID,
	  $iPackage,
	  $iMethod,
	  $iTxtFrom,
	  $iTxtTo,
	  $iSubject,
	  $iMessage);
    }

    // -- SPECIALIZED EVENT LOGGING -- //
    // ++ CLASS NAMES ++ //

    protected function CartsClass() {
	return 'clsShopCarts';
    }
    protected function LinesClass() {
	return 'clsOrderLines';
    }
    protected function CustomersClass() {
	return 'clsCusts';
    }
    protected function CardsClass() {
	return 'clsCustCards_dyn';
    }
    protected function MessagesClass() {
	return 'clsOrderMsgs';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function CartTable($id=NULL) {
	return $this->Engine()->Make($this->CartsClass(),$id);
    }
    /*----
      PUBLIC because Cart calls it during conversion to Order
    */
    public function LineTable($id=NULL) {
	return $this->Engine()->Make($this->LinesClass(),$id);
    }
    protected function CustomerTable($id=NULL) {
	return $this->Engine()->Make($this->CustomersClass(),$id);
    }
    protected function CardTable($id=NULL) {
	return $this->Engine()->Make($this->CardsClass(),$id);
    }
    protected function MessageTable($id=NULL) {
	return $this->Engine()->Make($this->MessagesClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    protected function Cart($iID=NULL) {
	throw new exception('Function call deprecated; use CartRecord().');
    }
    protected function CartRecord($iID=NULL) {
	if (!is_null($iID)) {
	    throw new exception('Passing the ID to this function is deprecated. Use CartID().');
	}

	if ($this->HasCart()) {
	    if (is_null($this->rcCart)) {
		$this->rcCart = $this->CartTable($this->CartID());
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
	$tbl = $this->LineTable();
	$rs = $tbl->GetData('ID_Order='.$this->KeyValue());
	return $rs;
    }
    protected function BuyerRecord() {
	$id = $this->BuyerID();
	$rc = $this->CustomerTable($id);
	return $rc;
    }
    protected function RecipRecord() {
	$id = $this->RecipID();
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
    // ++ STATUS ACCESS ++ //

    protected function HasCart() {
	return !is_null($this->CartID());
    }
    protected function HasCard() {
	$idCard = $this->CardID();
	return !empty($idCard);
    }

    // -- STATUS ACCESS -- //
    // ++ DATA FIELD ACCESS (LOCAL) ++ //

    /*----
      PUBLIC because Package objects need to access it for admin UI
    */
    public function Number() {
	return $this->Value('Number');
    }

    // -- BUYER fields
    protected function BuyerID() {
	return $this->Value('ID_Buyer');
    }
    protected function HasBuyer() {
	return (!is_null($this->BuyerID()));
    }
    protected function BuyerName() {
	return $this->Value('BuyerName');
    }
    // BuyerAddr is not a local field

    // -- RECIP fields
    /*----
      PUBLIC so cart can check it after order conversion.
	This might be unnecessary later.
    */
    public function RecipID() {
	return $this->Value('ID_Recip');
    }
    protected function HasRecip() {
	return (!is_null($this->RecipID()));
    }
    protected function RecipName() {
	return $this->Value('RecipName');
    }
    /*----
      HISTORY:
	2014-12-14 moved this back here from VC_Order
	  Not sure if there is a semantic difference between RecipAddr() and RecipAddr_text().
    */
    protected function RecipAddr() {
	return $this->Value('RecipAddr');
    }
    /*----
      HISTORY:
	2014-12-14 not sure if there is a semantic difference between RecipAddr() and RecipAddr_text()
    */
    protected function RecipAddr_text() {
	return $this->Value('RecipAddr');
    }
    protected function CardID() {
	return $this->Value('ID_BuyerCard');
    }
    /*----
      HISTORY:
	2014-01-16 This might need to be made writable, but I'm leaving it read-only for now.
	2014-10-05 Cart needs to be able to set this so order object can use it during cart->order conversion.
    */
    public function CartID($id=NULL) {
	return $this->Value('ID_Cart',$id);
    }

    // -- DATA FIELD ACCESS (LOCAL) -- //
    // ++ DATA FIELD ACCESS (FOREIGN) ++ //

    protected function SessionID() {
	return $this->Engine()->App()->Session()->KeyValue();
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
	    return NULL;
	}
    }
    protected function BuyerPhoneNumber() {
	if ($this->HasBuyer()) {
	    $rcBuyer = $this->BuyerRecord();
	    return $rcBuyer->PhonesText();
	} else {
	    return NULL;
	}
    }
    /*----
      RETURNS: Text of the latest customer-to-store order message.
    */
    protected function MessageText() {
	$tMsgs = $this->MessageTable();
	$rcMsg = $tMsgs->Record_forOrder($this->KeyValue(),clsOrders::MT_INSTRUC);
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

    // -- DATA FIELD ACCESS (FOREIGN) -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Zero all existing order lines
	This allows reuse of the line records when the same item
	is added back in, eliminating gaps in the numbering (which
	should only happen if there is data corruption).
    */
    public function ZeroLines() {
	$id = $this->KeyValue();
	$this->LineTable()->Update(
	  array('QtyOrd'=> 0),	// fields
	  'ID_Order='.$id	// condition
	  );

    }
    public function AddMessage(
      $idMedia,
      $sTxtFrom,
      $sTxtTo,
      $sSubject,
      $sMessage
      ) {
	$tMsgs = $this->MessageTable();
	$ok = $tMsgs->Add(
	  $this->KeyValue(),
	  NULL,			// no package
	  $idMedia,
	  $sTxtFrom,
	  $sTxtTo,
	  $sSubject,
	  $sMessage
	  );
    }

    // -- ACTIONS -- //
    // ++ CHECKOUT UI: WEB ++ //

    /*----
      ACTION: Render the receipt in HTML, based on Order data
      HISTORY:
	2014-11-03 This *was* showing the order as understood by the Cart, but that's not
	  what we should be seeing at this point. Converting it to use Order data.
    */
    public function RenderReceipt() {
	$out = NULL;

	if (($this->IsNew())) {
	    throw new exception("Internal Error: No Order object at checkout time.");
	}

	$idOrder = $this->KeyValue();
	$idCart = $this->Value('ID_Cart');
	$idSess = $this->SessionID();

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
	  );
	$oStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$oStrTplt->MarkedValue(KHT_RCPT_TPLT);

	$out .= $oStrTplt->Replace();
	return $out;
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

	$sRecipAddr	= $this->RecipAddr_text();
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
	    $out .= "--Instructions from you--\n$ftCustShipMsg\n--/instructions--";
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
    */
    protected function RenderContents() {
	$oPainter = new cCartDisplay_full_ckout(NULL);

	// render order lines
	$rsLines = $this->LineRecords();
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

	$oPainter = new cCartDisplay_full_TEXT(NULL);

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

	$htEmailAddr_Self = htmlspecialchars($iAddrSelf);
	$htEmailAddr_Cust = htmlspecialchars($iAddrCust);

	$htEmailBody = htmlspecialchars($iMessage);
	$txtSubj = $iSubject;
	$htSubj = htmlspecialchars($txtSubj);
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
	    $this->StartEvent($arEv);

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
	    $this->FinishEvent($arEv);
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
	return 'order-'.$this->Number.'@vbz.net';
    }
    /*----
      RETURNS: array of values needed to plug into email templates
      USED BY:
	cart class, to generate confirmation email
	order admin class, to display/generate confirmation email
    */
    public function TemplateVars() {
//	global $ksTextEmail;
//	$this->Reload(); // 2010-10-28 kluge attempt - see also RenderReceipt()

	$rcCart = $this->CartRecord_orError();
	$rsCData = $rcCart->FieldRecords();
	//$rcCust = $this->BuyerRecord();
	$ofPay = $rsCData->PayFields();
	$ofBuyer = $rsCData->RecipFields();
	$sEmailBuyer = $rsCData->BuyerEmailAddress_entered();

	$arVars = array(
	  KS_TVAR_CUST_NAME	=> $ofPay->CardNameValue(),
	  //KS_TVAR_CUST_EMAIL	=> $rsCData->CustEmail(),
	  //'orders-email' => $objPay->EmailValue(),
	  KS_TVAR_CUST_EMAIL	=> $sEmailBuyer,
	  KS_TVAR_ORDER_NUMBER	=> $this->Number
	  );

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
