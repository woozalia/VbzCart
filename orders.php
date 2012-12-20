<?php
/*
  PURPOSE: ORDER MANAGEMENT CLASSES
  LATER: Split into 3 parts:
    (1) core pieces, like table name
    (2) pieces only used by cart
    (3) (already done - class VbzAdminOrders) pieces only used by admin system
  HISTORY:
    2012-04-17 extracting from shop.php
*/
// ORDER MESSAGE TYPES
// these reflect the values in the ord_msg_media table
/*
define('KSI_ORD_MSG_INSTRUC',	1);	// Instructions in submitted order
define('KSI_ORD_MSG_PKSLIP',	2);	// Packing slip
*/
define('KSI_ORD_MSG_EMAIL',	3);	// Email
/*
define('KSI_ORD_MSG_PHONE',	4);	// Phone call
define('KSI_ORD_MSG_MAIL',	5);	// Snail mail
define('KSI_ORD_MSG_FAX',	6);	// Faxed message
define('KSI_ORD_MSG_LABEL',	7);	// Shipping label (for delivery instructions)
define('KSI_ORD_MSG_INT',	8);	// internal use - stored, not sent
*/
/* ======================
*/
class clsOrders extends clsTable {
    const TableName='core_orders';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsOrder');
	  $this->ActionKey(KS_URL_PAGE_ORDER);
    }
    /*-----
    | FUNCTION: Create()
    | ACTION: create the order record (fill in minimal fields)
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
	$this->Insert($arIns);
	$id = $this->objDB->NewID();
	assert('$id > 0');
	return $id;
    }
    /*-----
    | FUNCTION: NextOrdSeq()
    | ACTION: get the next order sequence number
    */
    private function NextOrdSeq() {
	$objVars = $this->objDB->VarsGlobal();
	$intOrdLast = $objVars->Val('ord_seq_prev');
	$intOrdThis = $intOrdLast+1;
	$objVars->Val('ord_seq_prev',$intOrdThis);
	return $intOrdThis;
    }
    /*-----
    | FUNCTION: Populate()
    | ACTION: fill in the order record with data from the cart
    */
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
}
class clsOrder extends clsVbzRecs {
    /*----
      NOTE: "Code" is now an integer. It used to be a string.
      HISTORY:
	2010-10-08 Moved here from VbzAdminOrder
    */
/*
    public function StartEvent($iWhere,$iCode,$iDescr,$iNotes=NULL) {
	$arEvent = array(
	  //'type'	=> clsEvents::kTypeOrd,
	  'type'	=> $this->Table->ActionKey(),
	  'id'		=> $this->ID,
	  'where'	=> $iWhere,
	  'code'	=> $iCode,
	  'descr'	=> $iDescr
	  );
	if (!is_null($iNotes)) {
	    $arEvent['notes'] = $iNotes;
	}
	$this->idEvent = $this->objDB->Events()->StartEvent($arEvent);
    }
*/
    protected function StartEvent_Simple($iCode,$iDescr,$iWhere) {
	//$this->objDB->OrderLog()->Add($this->ID,$iCode,$iDescr);
	$arEv = array(
	  'descr'	=> $iDescr,
	  'type'	=> $this->Table->ActionKey(),
	  'id'		=> $this->KeyValue(),
	  'where'	=> $iWhere,
	  'code'	=> $iCode);
//	$this->objDB->Events()->StartEvent($arEv);
	$this->StartEvent($arEv);
    }
    /*====
      HISTORY:
	2010-12-05 boilerplate event logging added to VbzAdminSupplier
	2011-03-23 copied from VbzAdminSupplier to VbzAdminOrder
	2011-03-27 moved from VbzAdminOrder to clsOrder
    */
    public function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    //====
/*
    protected function FinishEvent(array $iArgs=NULL) {
	$this->objDB->Events()->FinishEvent($iArgs);
    }
*/
    /*----
      HISTORY:
	2010-10-08 Moved here from VbzAdminOrder; supercedes existing protected function
	  which might not have been working properly anyway (no idEvent)
    */
/* 2011-03-27 this is now redundant
    public function FinishEvent(array $iArgs=NULL) {
	$this->objDB->Events()->FinishEvent($this->idEvent,$iArgs);
    }
*/
    /*-----
      TO DO: Explain why this object sometimes doesn't know what the cart is yet
	(presumably happens during the cart->order transfer process)
      USED BY:
	Created for VbzAdmin, where it is sometimes necessary to pass the cart ID (why?)
	Now used by this class for displaying order receipt.
    */
    protected function Cart($iID=NULL) {
	if (is_null($iID)) {
	    $idCart = $this->ID_Cart;
	} else {
	    $idCart = $iID;
	}

	$doLoad = TRUE;
	if (isset($this->objCart)) {
	    if ($this->objCart->ID == $idCart) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $objCart = $this->objDB->Carts()->GetItem($idCart);
//	    $this->objDB->Cart($objCart);	// 2012-04-19 dunno what this did
	    $this->objCart = $objCart;
	}
	return $objCart;
    }

    public function Lines() {
	$objTbl = $this->objDB->OrdLines();
	$objRows = $objTbl->GetData('ID_Order='.$this->ID);
	return $objRows;
    }
    public function CopyCart(clsShopCart $iCartObj) {
	$this->CopyCartData($iCartObj);
	$this->CopyCartLines($iCartObj);
    }
    /* =====
      ACTION: Copy over basic cart information (totals, etc.)
      HISTORY:
	2010-10-06 added cart ID to update -- otherwise final order confirmation page can't find cart data
	2012-05-25 major revision to cart data access -- now using $iCartObj->CartData()
    */
    private function CopyCartData(clsShopCart $iCartObj) {
	$objData = $iCartObj->CartData();

	$curItemTotal = $objData->CostTotalItem();
	$curShipItem = $objData->CostTotalPerItem();
	$curShipPkg = $objData->CostTotalPerPkg();

	$idCart = $iCartObj->ID;
	$this->Value('ID_Cart',$idCart);

	$arUpd = array(
	  'ID_Cart'		=> $idCart,
	  'WebTotal_Merch'	=> SQLValue($curItemTotal),
	  'WebTotal_Ship' 	=> SQLValue($curShipItem+$curShipPkg),
	  'WebTotal_Final'	=> SQLValue($curItemTotal+$curShipItem+$curShipPkg)
	  );
	$this->StartEvent_Simple('CDC','Copying data from cart ID='.$idCart,__METHOD__);
	$this->Update($arUpd);	// we're assuming the order record exists at this point
	$this->FinishEvent();
    }
    /*-----
     ACTION: Create order lines from cart lines
    */
    private function CopyCartLines(clsShopCart $iCartObj) {
	$objRows = $iCartObj->GetLines();	// shopping cart lines to convert
	$objTbl = new clsOrderLines($this->objDB);
	$objTbl->Update(array('QtyOrd'=>0),'ID_Order='.$this->ID);	// zero out any existing order lines in case customer edits cart
	if ($objRows->HasRows()) {
	    $this->StartEvent_Simple('CLC','copying cart lines',__METHOD__);
	    $intNew = 0;
	    $intUpd = 0;
	    while ($objRows->NextRow()) {
		$intSeq = $objRows->Seq;
		$idItem = $objRows->ID_Item;
		$intQty = $objRows->Qty;
		$dtWhenAdded = $objRows->WhenAdded;
		$dtWhenEdited = $objRows->WhenEdited;

		$objRows->RenderCalc($iCartObj->ShipZoneObj());

		$arUpd = array(
		  'CatNum'	=> SQLValue($objRows->CatNum),
		  'Descr'	=> SQLValue($objRows->Item()->DescLong()),
		  'QtyOrd'	=> $intQty,
		  'Price'	=> SQLValue($objRows->PriceItem),
		  'ShipPkg'	=> SQLValue($objRows->ShipPkgDest),
		  'ShipItm'	=> SQLValue($objRows->ShipItmDest),
		  'isShipEst'	=> 'FALSE'
		  );

		// has this item already been transcribed?
		$sqlFilt = '(ID_Order='.$this->ID.') AND (ID_Item='.$idItem.')';
		$objOrdItems = $objTbl->GetData($sqlFilt);
		if ($objOrdItems->RowCount() > 0)  {
		    // already transcribed -- update existing record
		    $objTbl->Update($arUpd,$sqlFilt);
		    $intUpd++;
		} else {
		    // not already transcribed -- insert new record
		    $arIns = $arUpd + array(
		      'ID_Order' 	=> $this->ID,
		      'Seq'		=> $intSeq,
		      'ID_Item'		=> $idItem
		      );
		    $objTbl->Insert($arIns);
		    $intNew++;
		}
	    }
	    $strLines = $intNew.' order line'.Pluralize($intNew).' created';
	    if ($intUpd > 0) {
		$strLines .= ' and '.$intUpd.' order line'.Pluralize($intUpd).' updated';
	    }
	    $strLines .= ' from cart lines';
	    $this->FinishEvent(array('descr'=>$strLines));
	} else {
	    $this->StartEvent_Simple('CL0','No cart lines found at order creation time',__METHOD__);
	    $out = '<b>There has been an error</b>: your cart contents seem to be missing.';
	    // TO DO: send email alerting webmaster; print more helpful message.
	}
    }
    /*----
      PURPOSE: nomenclatural purity. I could have just added the display code to RenderReceipt(),
	but it seemed like a good idea to have a function that just generates the output without
	displaying it. It also seemed like a good idea not to add the code to the calling function,
	because that's untidy, So here we are, with a function shorter than its comments.
      NOTE: admin functions will generally want to handle the display themselves, so it is good practice
	to be able to retrieve the output to be displayed without displaying it.
	It may be redundant to have ShowReceipt() at all, however.
    */
    public function ShowReceipt() {
	$out = $this->RenderReceipt();
	echo $out;
    }
    /*----
      ACTION: Render the receipt in HTML
	Shows the order as generated from *cart* data, not what's in the order record.
	...except for the order number.
    */
    public function RenderReceipt() {
	$out = NULL;

// 2010-10-17 This should have been loaded earlier, but I don't have time to track it down now.
//	The problem is that it forgets the cart ID otherwise.
	$this->Reload();

	// get core objects, for code clarity/portability
	$objOrd = $this;
	$objCart = $this->Cart();
	$idOrder = $this->KeyValue();
	$idCart = $this->Value('ID_Cart');
	$objData = $objCart->CartData();
	$objShip = $objData->ShipObj(FALSE);
	$objPay = $objData->PayObj();

	if (!is_object($objCart)) {
	    throw new exception("Receipt could not get cart object: Cart ID=[$idCart]");
	}
	if (($objOrd->IsNew()) || ($objCart->IsNew())) {
	    throw new exception("Receipt has missing object: Order ID=[$idOrder] Cart ID=[$idCart]");
	}

/*
	$objCart->GetDetailObjs();
	$objPay = $objCart->PersonCustObj()->Payment();
	$objAddrCard = $objCart->AddrCardObj();
	// the next line is a kluge which only works as long as payment is always ccard
	// it's also not clear why GetDetailObjs() isn't loading it properly
	$objPay->Node('addr', $objAddrCard);
*/

	$arVars = array(
	  'ord.num'	=> $objOrd->Number,
	  'timestamp'	=> date(KF_RCPT_TIMESTAMP),
	  'cart.id'	=> $objCart->ID,
	  'cart.detail'	=> $objCart->RenderConfirm(),
	  'ship.name'	=> $objData->ShipAddrName(),
	  'ship.addr'	=> $objShip->Addr_AsText("\n<br>"),
	  'pay.name'	=> $objData->CustName(),
	  'pay.spec'	=> $objPay->SafeDisplay(),
	  'email.short'	=> 'orders-'.date('Y').'@vbz.net'
	  );
	$objStrTplt = new clsStringTemplate_array(NULL,NULL,$arVars);
	$objStrTplt->MarkedValue(KHT_RCPT_TPLT);
	$out = "<!-- ORDER ID: [$idOrder] / CART ID from order: [$idCart] -->";
	$out .= $objStrTplt->Replace();
	return $out;

//	return $objCart->RenderReceipt();
    }
    /*----
      RETURNS: Email address to use for this order (includes order number in address, as a way of tracking
	spam and possibly later as a way of automatically filing order-related correspondence)
    */
    public function EmailAddr() {
	return 'order-'.$this->Number.'@vbz.net';
    }
    /*----
      RETURNS: Order receipt (from cart data) in text-only format, suitable for emailing
      HISTORY:
	2010-09-13 writing started
    */
    public function RenderReceipt_Text() {
	$out = $this->Cart()->RenderOrder_Text();
	return $out;
    }
    /*----
      RETURNS: array of values needed to plug into email templates
      USED BY:
	cart class, to generate confirmation email
	order admin class, to display/generate confirmation email
    */
    public function TemplateVars() {
//	global $ksTextEmail;
	$this->Reload(); // 2010-10-28 kluge attempt - see also RenderReceipt()

	$objCart = $this->Cart();
	$objData = $objCart->CartData();
	$objCust = $objData->CustObj();
	$objPay = $objData->PayObj();

	assert('is_object($objCart)');
	assert('is_object($objCart)');
	assert('is_object($objPay)');
/*
	$objCardAddr = $objCart->AddrCardObj();
	$objCustCont = $objCart->ContCustObj();

	assert('is_object($objCardAddr)');
	assert('is_object($objCustCont)');
*/
	$arVars = array(
	  'cust-name' => $objCust->NameVal(),
	  'cust-email' => $objData->CustEmail(),
	  'orders-email' => $this->EmailAddr(),
	  'ord-num' => $this->Number
	  );

	foreach($arVars as $key => $val) {
	    if (!is_string($val)) {
		echo '<br>Variable <b>'.$key.'</b> is not a string. It is a '.get_class($val).'.<br>';
/*
		echo 'objCardAddr:'.$objCardAddr->DumpHTML();
		echo 'objCustCont:'.$objCustCont->DumpHTML();
		echo 'ROOT:'.$objCardAddr->Root()->DumpHTML();
*/
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
	    $txtSubj = $objStrTplt->Replace(KS_TPLT_EMAIL_SUBJECT);
	}
	$arOut['subj'] = $txtSubj;

	$txtMsgPre = $iMsgPre;
	if (empty($txtMsgPre)) {
	    $txtMsgPre = $objStrTplt->Replace(KS_TPLT_EMAIL_MSG_TOP);
	}
	$arOut['msg.pre'] = $txtMsgPre;

	$arVars['subject'] = $txtSubj;
	$objStrTplt->List = $arVars;

	//$objStrTplt->MarkedValue(KS_TEXT_EMAIL_ADDR_SELF);
	$txtAddr_Self = $objStrTplt->Replace(KS_TPLT_EMAIL_ADDR_SELF);
	$arOut['addr.self'] = $txtAddr_Self;

	//$objStrTplt->MarkedValue(KS_TEXT_EMAIL_ADDR_CUST);
	$txtAddr_Cust = $objStrTplt->Replace(KS_TPLT_EMAIL_ADDR_CUST);
	$arOut['addr.cust'] = $txtAddr_Cust;

	// Calculate text of email to send:
	$txtEmailBody = $txtMsgPre."\n".$this->RenderReceipt_Text();
	$arOut['msg.body'] = $txtEmailBody;

	return $arOut;
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
    public function EmailConfirm($iReally, array $iParams, clsLogger $iLog) {
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
	      'descr'	=> 'manual order email (self:'.$txtSelfCopy.' cust:'.$txtCustCopy.') Subject: '.$txtSubj,
	      'code'	=> 'OEM',
	      'where'	=> __METHOD__
	      );
	    $iLog->StartEvent($arEv);

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
    public function LogMessage($iPackage,$iMethod,$iTxtFrom,$iTxtTo,$iSubject,$iMessage) {
	$this->objDB->OrdMsgs()->Add(
	  $this->ID,
	  $iPackage,
	  $iMethod,
	  $iTxtFrom,
	  $iTxtTo,
	  $iSubject,
	  $iMessage);
    }
}
class clsOrderLines extends clsTable {
    const TableName='ord_lines';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsOrderLine');
    }
}
class clsOrderLine extends clsDataSet {
    public function Init_fromCartLine(clsShopCartLine $iLine) {
	// some fields get copied over directly
	$arNames = array(
	  'Seq'		=> 'Seq',
	  'ID_Item'	=> 'ID_Item',
	  'Qty'		=> 'QtyOrd',
	  'CatNum'	=> 'CatNum',
	  'PriceItem'	=> 'Price',
	  'ShipPkgDest'	=> 'ShipPkg',
	  'ShipItmDest'	=> 'ShipItm',
	  'DescText'	=> 'Descr',
	  //'DescHtml'	=> 'DescrHTML'	// we may eventually add this field
	  );
	foreach($arNames as $srce => $dest) {
	    $val = $iLine->Value($srce);
	    $this->Value($dest,$val);
	}
    }
    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
    */
    protected $objItem, $idItem;
    public function ItemObj() {
	$doLoad = TRUE;
	$id = $this->Value('ID_Item');
	if (isset($this->idItem)) {
	    if ($this->idItem == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objItem = $this->Engine()->Items($id);
	    $this->idItem = $id;
	}
	return $this->objItem;
    }
    /*----
      RETURNS: selling price
	if order line has no price, falls back to catalog item
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function PriceSell() {
	$prc = $this->Value('Price');
	if (is_null($prc)) {
	    $prc = $this->ItemObj()->PriceSell();
	}
	return $prc;
    }
    /*----
      RETURNS: shipping per-package price
	if order line has no per-package price, falls back to catalog item
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerPkg() {
	$prc = $this->Value('ShipPkg');
	if (is_null($prc)) {
	    $prc = $this->ItemObj()->ShPerPkg();
	}
	return $prc;
    }
    /*----
      RETURNS: shipping per-item price -- defaults to catalog item's data
	unless specified in package line
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerItm() {
	$prc = $this->Value('ShipItm');
	if (is_null($prc)) {
	    $prc = $this->ItemObj()->ShPerItm();
	}
	return $prc;
    }
    /*----
      RETURNS: array of calculated values for this order line
	array[sh-pkg]: shipping charge per package
	array[sh-itm.qty]: shipping charge per item, adjusted for quantity ordered
	array[cost-sell.qty]: selling cost, adjusted for quantity ordered
      USED BY: so far, only admin functions (shopping functions use Cart objects, not Order)
    */
    public function FigureStats() {
	$qty = $this->Value('QtyOrd');
	if ($qty != 0) {
	    $prcShPkg = $this->ShPerPkg();
	} else {
	    // none of this item in package, so don't require this minimum
	    $prcShPkg = 0;
	}
	$arOut['sh-pkg'] = $prcShPkg;
	$arOut['sh-itm.qty'] = $this->ShPerItm() * $qty;
	$arOut['cost-sell.qty'] = $this->PriceSell() * $qty;
	return $arOut;
    }
    /*----
      ACTION: Figures totals for the current rowset
      USED BY: so far, only admin functions (shopping functions use Cart objects, not Order)
      RETURNS: array in same format as FigureStats(), except with ".qty" removed from index names
    */
    public function FigureTotals() {
	$arSum = NULL;
	while ($this->NextRow()) {
	    $ar = $this->FigureStats();

	    $prcShItmSum = nzArray($arSum,'sh-itm',0);
	    $prcShPkgMax = nzArray($arSum,'sh-pkg',0);
	    $prcSaleSum = nzArray($arSum,'cost-sell',0);

	    $prcShItmThis = $ar['sh-itm.qty'];
	    $prcShPkgThis = $ar['sh-pkg'];
	    $prcSaleThis = $ar['cost-sell.qty'];

	    $arSum['sh-itm'] = $prcShItmSum + $prcShItmThis;
	    $arSum['cost-sell'] = $prcSaleSum + $prcSaleThis;
	    if ($prcShPkgMax < $prcShPkgThis) {
		$prcShPkgMax = $prcShPkgThis;
	    }
	    $arSum['sh-pkg'] = $prcShPkgMax;
	}
	return $arSum;
    }
    /*----
      ACTION: Render the current order line using static HTML (no form elements; read-only)
      HISTORY:
	2011-04-01 adapted from clsShopCartLine::RenderHtml() to clsOrderLine::RenderStatic()
    */
    public function RenderStatic(clsShipZone $iZone) {
// calculate display fields:
	$qty = $this->Value('QtyOrd');
	if ($qty) {
	    //$this->RenderCalc($iZone);

	    $htLineCtrl = $qty;

	    $mnyPrice = $this->Value('Price');	// item price
	    $mnyPerItm = $this->Value('ShipItm');	// per-item shipping
	    $mnyPerPkg = $this->Value('ShipPkg');	// per-pkg minimum shipping
	    $intQty = $this->Value('QtyOrd');
	    $mnyPriceQty = $mnyPrice * $intQty;		// line total sale
	    $mnyPerItmQty = $mnyPerItm * $intQty;	// line total per-item shipping
	    $mnyLineTotal = $mnyPriceQty + $mnyPerItmQty;	// line total overall (does not include per-pkg minimum)

	    $strCatNum = $this->Value('CatNum');
	    $strPrice = FormatMoney($mnyPrice);
	    $strPerItm = FormatMoney($mnyPerItm);
	    $strPriceQty = FormatMoney($mnyPriceQty);
	    $strPerItmQty = FormatMoney($mnyPerItmQty);
	    $strLineTotal = FormatMoney($mnyLineTotal);

	    $strShipPkg = FormatMoney($mnyPerPkg);

	    $htDesc = $this->Value('Descr');	// was 'DescHtml', but that field doesn't exist here

	    $htDelBtn = '';

	    $out = <<<__END__
<tr>
<td>$htDelBtn$strCatNum</td>
<td>$htDesc</td>
<td class=cart-price align=right>$strPrice</td>
<td class=shipping align=right>$strPerItm</td>
<td class=qty align=right>$htLineCtrl</td>
<td class=cart-price align=right>$strPriceQty</td>
<td class=shipping align=right>$strPerItmQty</td>
<td class=total align=right>$strLineTotal</td>
<td class=shipping align=right>$strShipPkg</td>
</tr>
__END__;
	    return $out;
	}
    }
}

/*----------
  CLASS PAIR: order messages (table ord_msg)
*/
class clsOrderMsgs extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_msg');
	  $this->KeyName('ID');
	  $this->ClassSng('clsOrderMsg');
    }
    /*----
      ACTION: Adds a message to the order
      INPUT:
	$iMedia: ord_msg.ID_Media
    */
    public function Add($iOrder,$iPackage,$iMedia,$iTxtFrom,$iTxtTo,$iSubject,$iMessage) {
	global $vgUserName;

	$arIns = array(
	  'ID_Ord'	=> $iOrder,
	  'ID_Pkg'	=> SQLValue($iPackage),	// might be NULL
	  'ID_Media'	=> SQLValue($iMedia),
	  'TxtFrom'	=> SQLValue($iTxtFrom),
	  'TxtTo'	=> SQLValue($iTxtTo),
	  'TxtRe'	=> SQLValue($iSubject),
	  'doRelay'	=> 'FALSE',	// 2010-09-23 this field needs to be re-thought
	  'WhenCreated'	=> 'NOW()',	// later: add this as an optional argument, if needed
	  'WhenEntered'	=> 'NOW()',
	  'WhenRelayed' => 'NULL',
	  'Message'	=> SQLValue($iMessage)
	  );
	$this->Insert($arIns);
    }
}
class clsOrderMsg extends clsDataSet {
}