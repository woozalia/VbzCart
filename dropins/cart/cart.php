<?php
/*
  FILE: cart.php -- shopping cart administration
  HISTORY:
    2010-10-15 Extracted shopping cart classes from SpecialVbzAdmin.php
    2011-12-24 DataScripting brought closer to sanity; mostly working.
    2014-01-15 adapting as a drop-in module
*/
class VC_Carts extends vctCarts_ShopUI {
    use ftLinkableTable;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcraCart');
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ ADMIN UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	//$this->Name('qryCarts_info');
	$rs = $this->GetData(NULL,NULL,'ID DESC');
	$out = $rs->AdminLines();
	return $out;
    }

    // -- ADMIN UI -- //
}
class vcraCart extends vcrCart_ShopUI {
    use ftLinkableRecord;
    use ftShowableRecord;

    private $rcCust;
    private $htOut;

    // ++ SETUP ++ //
    
    protected function InitVars() {
	parent::InitVars();
	$this->rcCust = NULL;
	$this->htOut = NULL;
    }
    
    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //

    protected function OrdersClass() {
	return KS_CLASS_ORDERS;
    }
    protected function SessionsClass() {
	if (fcDropInManager::IsFeatureLoaded(KS_FEATURE_USER_SESSION_ADMIN)) {
	    return KS_CLASS_ADMIN_USER_SESSIONS;
	} else {
	    return 'cVbzSession';
	}
    }
    /*----
      TODO: fall back to non-admin customer class if admin drop-in is not available
    */
    protected function CustomersClass() {
	return KS_CLASS_ADMIN_CUSTOMERS;
    }
    protected function LinesClass() {
	return KS_CLASS_ADMIN_CART_LINES;
    }
    protected function FieldsClass() {
	return KS_CLASS_ADMIN_CART_FIELDS;
    }
    protected function EventsClass() {
	return $this->AppObject()->EventsClass();
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
/* Defined parentally:
    protected function OrderTable($id=NULL)
    protected function CustomerTable($id=NULL)
*/
    protected function SessionTable($id=NULL) {
	return $this->Engine()->Make($this->SessionsClass(),$id);
    }
    protected function EventTable($id=NULL) {
	return $this->Engine()->Make($this->EventsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      INPUT:
	$doLoad:
	  if FALSE, just set the index from ID_Cust
	  if TRUE, load the corresponding customer record
    */
    public function CustomerRecord($doLoad) {
	$idCust = $this->CustomerID();
	if ($doLoad) {
	    if (is_null($this->rcCust)) {
		$this->rcCust = $this->CustomerTable($idCust);
	    }
	} else {
	    $doLoad = TRUE;
	    if (is_object($this->rcCust)) {
		if ($this->rcCust->GetKeyValue() == $idCust) {
		    $doLoad = FALSE;
		}
	    }
	    if ($doLoad) {
		$this->rcCust = $this->CustomerTable()->SpawnItem();
		$this->rcCust->GetKeyValue($idCust);
	    }
	}
	return $this->rcCust;
    }
    /*----
      RETURNS: Record for the Session ID stored in this record, as opposed to the record for the currently active Session.
    */
    protected function StoredSessionRecord() {
	$id = $this->SessionID();
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->SessionTable($id);
	}
    }

    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //
    
    protected function WhenCreated() {
	return $this->Value('WhenCreated');
    }
    protected function WhenExported() {
	return $this->Value('WhenPorted');
    }
    protected function WhenUpdated() {
	return $this->Value('WhenUpdated');
    }
    protected function FieldData() {
	return $this->Value('FieldData');
    }
    protected function FieldDataLength() {
	return strlen($this->FieldData());	// does strlen() work on blobs?
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function StoredSessionLink() {
	$id = $this->SessionID();
	if (is_null($id)) {
	    return '<i>NULL</i>';
	} else {
	    $rc = $this->SessionTable($id);
	    if ($rc->IsNew()) {
		return '<i><span title="no record">'.$id.'</span></i>';
	    } else {
		return $rc->SelfLink();
	    }
	}
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ADMIN UI COMPONENTS ++ //

    /*----
      PURPOSE: So shop-UI class can provide admin-related information
	In the base class, this is stubbed off.
	Admin-UI class should not call this.
    */
    protected function AdminEcho($sText) {
	$this->htOut .= $sText;
    }
    public function ToOrder(vcrOrder $oOrd) {
	parent::ToOrder($oOrd);
	return $this->htOut;
    }
    /*-----
      PURPOSE: Display the cart's items as a table
    */
    public function RenderItemRows() {
	$tbl = $this->LineTable();
	return $tbl->Table_forCart($this->GetKeyValue());
    }
    /*---
      PURPOSE: Display the cart's data lines as a table
    */
    public function RenderFieldsRows() {
	return NULL;
	/* 2016-06-12 worked when data was in a separate table
	$tbl = $this->FieldTable();
	return $tbl->Table_forCart($this->KeyValue());
	*/
    }
    /*---
      PURPOSE: Display the cart's events as a table
    */
    public function RenderEventRows() {
	$tbl = $this->EventTable();
	$rs = $tbl->SelectRecords('ID_Cart='.$this->GetKeyValue());
	return $rs->AdminTable();
    }
    /*----
      ACTION: Render links for importing cart into an order
      HISTORY:
	2016-08-09 This is now only invoked if there are Carts pointing to this Order that are not pointed back at by the Order.
	  This shouldn't happen, and I think the code we now have doesn't do this anymore (did it ever?).
      TODO: Check to confirm that this really isn't needed, even for legacy Orders, and remove. (Also remove checking for
	duplicates in the Order admin function.)
    */
    public function Links_forSetup($idOrder) {
	$oPage = $this->Engine()->App()->Page();
	$sAct = $this->Table()->ActionKey();
	$id = $this->GetKeyValue();
	$htID = $this->SelfLink();

	// _PageLink_URL($sPage,[$id],[$iarArgs])
	// BuildLink($urlBase,$sText,[$sDescr])
	$arLink = array(KS_PAGE_KEY_ORDER=>$idOrder);

	$htUse = $this->SelfLink(
	  'use',
	  'use this cart',
	  array(
	    'do'	=> 'use.ord',
	    'ord'	=> $idOrder)
	    );

//	$htUse = $vgPage->SelfLink($arLink,'use');
//	$htView = $this->AdminLink('view');
	//$out = "#$id [$htUse] [$htView]";
	$out = "#$htID [$htUse]";

	return $out;
    }

    // -- ADMIN UI COMPONENTS -- //
    // ++ ADMIN UI ++ //

    protected function AdminField($sField) {
	switch ($sField) {
	  case 'ID':
	    $val = $this->AdminLink();
	    break;
	  case 'ID_Sess':
	    $val = $this->StoredSessionLink();
	    break;
	  case 'ID_Order':
	    $rc = $this->OrderRecord();
	    if (is_null($rc)) {
		$val = '<i>-none-</i>';
	    } else {
		$val = $rc->AdminLink_name();
	    }
	    break;
	  case 'ID_Cust':
	    $rc = $this->CustRecord();
	    $val = $rc->AdminLink();
	    break;
	  default:
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    public function AdminLines() {
	if ($this->HasRows()) {
	    $out = <<<__END__
KEY: <b>S</b>=Session | <b>O</b>=Order | <b>C</b>=Customer | <b>#D</b>= # of Data lines | <b>#I</b> = # of Items in cart
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Created</th>
    <th>Ordered</th>
    <th>Updated</th>
    <th>Voided</th>
    <th>S</th>
    <th>O</th>
    <th>C</th>
    <th>#D</th>
    <th>#I</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;
		$out .= $this->AdminLine($wtStyle);
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No carts have been created yet.';
	}
	return $out;
    }
    protected function AdminLine($cssStyle) {
	$id = $this->GetKeyValue();
	$wtID = $this->SelfLink();

	$htWhenCre = $this->Value('WhenCreated');
	$htWhenPort = $this->WhenExported();
	$htWhenUpd = $this->Value('WhenUpdated');
	$htWhenVoid = $this->Value('WhenVoided');
	$htDataBytes = $this->FieldDataLength();
	$htItemCount = $this->LineCount();

	// TODO: This should probably be cached
	if (fcDropInManager::IsFeatureLoaded(KS_FEATURE_USER_SESSION_ADMIN)) {
	    $wtSess = $this->StoredSessionLink();
	} else {
	    $wtSess = $this->SessionID();
	}

	if (is_null($this->GetOrderID())) {
	    $htOrd = '<i>n/a</i>';
	} else {
	    $rcOrd = $this->OrderRecord();
	    $htOrd = $rcOrd->SelfLink();
	}

	$rcCust = $this->CustomerRecord(FALSE);
	$wtCust = $rcCust->SelfLink();

	$out = <<<__END__
  <tr style="$cssStyle">
    <td>$wtID</td>
    <td>$htWhenCre</td>
    <td>$htWhenPort</td>
    <td>$htWhenUpd</td>
    <td>$htWhenVoid</td>
    <td>$wtSess</td>
    <td>$htOrd</td>
    <td>$wtCust</td>
    <td>$htDataBytes</td>
    <td>$htItemCount</td>
  </tr>
__END__;
	return $out;
    }
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$id = $this->GetKeyValue();

	if ($id == 0) {
	    throw new exception('Object has no ID');
	}

	$sDo = $oPage->PathArg('do');

	$out = NULL;
	$doShow = FALSE;	// by default, don't show the record data after an action
	switch ($sDo) {
	  case 'text':
	    $out = '<pre>'.$this->RenderOrder_Text().'</pre>';
	    break;
	  case 'use.ord':
	    $idOrd = $oPage->PathArg('ord');
	    $rcOrd = $this->OrderTable($idOrd);
	    $out = 'Exporting Cart '
	      .$this->SelfLink()
	      .' to Order #'
	      .$rcOrd->SelfLink_name()
	      ." (ID $idOrd)<br>";
	    $out .= $this->ToOrder($rcOrd);
	    break;
	  case 'find-ord':
	    // find the order which was created from this cart (2014-01-27 is this even needed anymore?)
	    $out .= 'Looking up order...';
	    $sql = 'ID_Cart='.$this->GetKeyValue();
	    $rs = $this->OrderTable()->GetData($sql);
	    if ($rs->HasRows()) {
		$rc = $rs->RowCount();
		if ($rc > 1) {
		    $out .= $rc.' rows found, should be only 1. SQL='.$this->Engine()->sqlExec;
		} else {
		    $rs->NextRow();	// get the first (and only) row
		    $idOrd = $rs->ID;
		    $arEv = array(
		      'code'	=> 'ord-fnd',
		      'descr'	=> 'found order for cart',
		      'params'	=> '\ID='.$idOrd,
		      'where'	=> __METHOD__
		      );
		    $this->StartEvent($arEv);
		    $this->Update(array('ID_Order' => $idOrd));
		    $this->Reload();
		    $this->FinishEvent();
		}
	    } else {
		$out .= ' no orders found! SQL='.$this->Engine->sqlExec;
	    }
	  case 'void':
	    $doShow = TRUE;
	    $arUpd = array(
	      'WhenVoided'	=> 'NOW()'
	      );
	    $this->Update($arUpd);
	    $this->Reload();
	    break;
	  case 'store.fields':
	    $arFields = $this->FieldTable()->Array_forCart($this->GetKeyValue());
	    //$out .= fcArray::Render($arFields);
	    //$out .= 'Serialized:'.serialize($arFields);
	    $sFields = serialize($arFields);
	    $arUpd = array(
	      'FieldData'	=> $this->Engine()->SanitizeAndQuote($sFields)
	      );
	    $this->Update($arUpd);
	    $sMsgs = $this->Engine()->getError();
	    $this->SelfRedirect(NULL,$sMsgs);
	    break;
	  default:
	    $doShow = TRUE;
	}
	if ($doShow) {
	    $out = $oPage->SectionHeader('Cart');
	    $arLink = array('do'=>'text');
	    $htText = '['.$this->SelfLink('as text','show the cart as plain text',$arLink).']';

	    if ($this->IsVoided()) {
		$htVoid = NULL;
	    } else {
		$arLink = array('do'=>'void');
		$htVoid = ' ['.$this->SelfLink('void','void the cart now',$arLink).']';
	    }
	    
	    $arLink = array('do'=>'store.fields');
	    $htFields = ' ['.$this->SelfLink('store','serialize and store',$arLink).']';

	    $htSess = $this->StoredSessionLink();
	    $htCust = $this->CustomerRecord(FALSE)->SelfLink();

	    if (is_null($this->Value('ID_Order'))) {
		$url = $oPage->SelfURL(array('do'=>'find-ord'));
		$htOrd = clsHTML::BuildLink($url,'find order!');
	    } else {
		$rcOrder = $this->OrderRecord();
		$htOrd = $rcOrder->SelfLink_name();
	    }

	    $sWhenCreated = $this->WhenCreated();
	    $sWhenOrdered = $this->WhenExported();
	    $sWhenUpdated = $this->WhenUpdated();
	    $sWhenVoided = $this->WhenVoided().$htVoid;
	    $out .= <<<__END__

<table class=listing>
  <tr><td align=right><b>ID</b>:</td>		<td>$id $htText</td></tr>
  <tr><td align=right><b>When Created</b>:</td>	<td>$sWhenCreated</td></tr>
  <tr><td align=right><b>When Ordered</b>:</td>	<td>$sWhenOrdered</td></tr>
  <tr><td align=right><b>When Updated</b>:</td>	<td>$sWhenUpdated</td></tr>
  <tr><td align=right><b>When Voided</b>:</td>	<td>$sWhenVoided</td></tr>
  <tr><td align=right><b>Session</b>:</td>	<td>$htSess</td></tr>
  <tr><td align=right><b>Order</b>:</td>	<td>$htOrd</td></tr>
  <tr><td align=right><b>Customer</b>:</td>	<td>$htCust</td></tr>
  <tr><td align=right><b>Field Data</b>:</td>	<td>$htFields</td></tr>
</table>
__END__;
	    $sFields = $this->Value('FieldData');
	    if (is_null($sFields)) {
		$htFields = NULL;
	    } else {
		$arFields = unserialize($sFields);
		$htFields = $oPage->SectionHeader('Fields')
		  .fcArray::Render($arFields)
		  ;
	    }

	    $out .=
	      $oPage->SectionHeader('Items')
	      .$this->RenderItemRows()
	      //.$oPage->SectionHeader('Fields')
	      //.$this->RenderFieldsRows()
	      .$htFields
	      .$oPage->SectionHeader('Cart Events')
	      .$this->RenderEventRows()
	      //.$oPage->SectionHeader('Events - system')
	      .$this->EventListing()
	      ;
	} // END if($doShow)
	return $out;
    }
}
