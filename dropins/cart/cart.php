<?php
/*
  FILE: cart.php -- shopping cart administration
  HISTORY:
    2010-10-15 Extracted shopping cart classes from SpecialVbzAdmin.php
    2011-12-24 DataScripting brought closer to sanity; mostly working.
    2014-01-15 adapting as a drop-in module
*/
class VC_Carts extends clsShopCarts {

    // ++ INITIALIZATION ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCart');
    }

    // -- INITIALIZATION -- //
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

	$this->Name('qryCarts_info');
	$rs = $this->GetData(NULL,NULL,'ID DESC');
	$out = $rs->AdminLines();
	return $out;
    }

    // -- ADMIN UI -- //
}
class VbzAdminCart extends clsShopCart {
    private $rcCust;
    private $htOut;

    // ++ INITIALIZATION ++ //
    protected function InitVars() {
	parent::InitVars();
	$this->rcCust = NULL;
	$this->htOut = NULL;
    }
    // ++ BOILERPLATE ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }

    // -- BOILERPLATE -- //
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
	if (clsDropInManager::FeatureLoaded(KS_FEATURE_USER_SESSION_ADMIN)) {
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
	return KS_CLASS_ADMIN_CART_EVENTS;
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make($this->OrdersClass(),$id);
    }
//    protected function SessionTable($id=NULL) {
//	return $this->Engine()->Make($this->SessionsClass(),$id);
//    }
    protected function CustomerTable($id=NULL) {
	return $this->Engine()->Make($this->CustomersClass(),$id);
    }
    protected function EventTable($id=NULL) {
	return $this->Engine()->Make($this->EventsClass(),$id);
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function SessObj() {
	throw new exception('SessObj() is deprecated; call SessionRecord().');
    }
//    public function SessionRecord() {
//	$idSess = $this->SessionID();
//	$rcSess = $this->SessionTable($idSess);
//	return $rcSess;
//    }
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
		if ($this->rcCust->KeyValue() == $idCust) {
		    $doLoad = FALSE;
		}
	    }
	    if ($doLoad) {
		$this->rcCust = $this->CustomerTable()->SpawnItem();
		$this->rcCust->KeyValue($idCust);
	    }
	}
	return $this->rcCust;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ADMIN UI COMPONENTS ++ //

    /*----
      PURPOSE: So shop-UI class can provide admin-related information
	In the base class, this is stubbed off.
	Admin-UI class should not call this.
    */
    protected function AdminEcho($sText) {
	$this->htOut .= $sText;
    }
    public function ToOrder(clsOrder $oOrd) {
	parent::ToOrder($oOrd);
	return $this->htOut;
    }
    /*-----
      PURPOSE: Display the cart's items as a table
    */
    public function RenderItemRows() {
	//$objItems = new VbzAdminCartLines($this->objDB);
	$tbl = $this->LineTable();
	return $tbl->Table_forCart($this->KeyValue());
    }
    /*---
      PURPOSE: Display the cart's data lines as a table
    */
    public function RenderFieldsRows() {
	$tbl = $this->FieldTable();
	return $tbl->Table_forCart($this->KeyValue());
    }
    /*---
      PURPOSE: Display the cart's events as a table
    */
    public function RenderEventRows() {
	$objTbl = $this->EventTable();
	$objRows = $objTbl->GetData('ID_Cart='.$this->ID);
	return $objRows->AdminTable();
    }
    /*----
      ACTION: Render links for importing cart into an order
    */
    public function Links_forSetup($idOrder) {
	$oPage = $this->Engine()->App()->Page();
	$urlBase = $oPage->SelfURL();
	$sAct = $this->Table()->ActionKey();
	$id = $this->KeyValue();
	$htID = $this->AdminLink();

	// _PageLink_URL($sPage,[$id],[$iarArgs])
	// BuildLink($urlBase,$sText,[$sDescr])
	$arLink = array(KS_PAGE_KEY_ORDER=>$idOrder);

	$arLink['do'] = 'use';
	$urlAct	= clsMenuData_helper::_PageLink_URL($sAct,$id,$arLink);
	$htUse	= clsHTML::BuildLink($urlBase.$urlAct,'use','use this cart');
/*
	$arLink['do'] = 'view';
	$urlAct	= clsMenuData_helper::_PageLink_URL($sAct,$id,$arLink);
	$htView	= clsHTML::BuildLink($urlBase.$urlAct,'view','view this cart');
*/

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
	    $rc = $this->SessionRecord();
	    $val = $rc->AdminLink();
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
	$id = $this->KeyValue();
	$wtID = $this->AdminLink();

	$htWhenCre = $this->Value('WhenCreated');
	$htWhenOrd = $this->Value('WhenOrdered');
	$htWhenUpd = $this->Value('WhenUpdated');
	$htWhenVoid = $this->Value('WhenVoided');
	$htDataCount = $this->Value('DataCount');
	$htItemCount = $this->Value('ItemCount');

	// TODO: This should probably be cached
	$objSess = $this->SessionRecord();
	if (clsDropInManager::FeatureLoaded(KS_FEATURE_USER_SESSION_ADMIN)) {
	    $wtSess = $objSess->AdminLink();
	} else {
	    $wtSess = $objSess->KeyValue();
	}

	if (is_null($this->OrderID())) {
	    $htOrd = '<i>n/a</i>';
	} else {
	    $rcOrd = $this->OrderRecord();
	    $htOrd = $rcOrd->AdminLink();
	}

	$rcCust = $this->CustomerRecord(FALSE);
	$wtCust = $rcCust->AdminLink();

	$out = <<<__END__
  <tr style="$cssStyle">
    <td>$wtID</td>
    <td>$htWhenCre</td>
    <td>$htWhenOrd</td>
    <td>$htWhenUpd</td>
    <td>$htWhenVoid</td>
    <td>$wtSess</td>
    <td>$htOrd</td>
    <td>$wtCust</td>
    <td>$htDataCount</td>
    <td>$htItemCount</td>
  </tr>
__END__;
	return $out;
    }
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$id = $this->KeyValue();

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
	  case 'use':
	    $idOrd = $oPage->PathArg('ord');
	    $oOrd = $this->OrderTable($idOrd);
	    $out = 'Importing Cart '.$this->AdminLink().' to Order #'.$oOrd->AdminLink_name().' (ID '.$idOrd.')';
	    $out .= $this->ToOrder($oOrd);
	    break;
	  case 'find-ord':
	    // find the order which was created from this cart (2014-01-27 is this even needed anymore?)
	    $out .= 'Looking up order...';
	    $sql = 'ID_Cart='.$this->KeyValue();
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
	  default:
	    $doShow = TRUE;
	}
	if ($doShow) {
	    $out = $oPage->SectionHeader('Cart');

	    $arURL = array(
		'page'	=> 'cart',
		'id'	=> $id,
		'do'	=> 'text'
		);
	    $url = $oPage->SelfURL($arURL);
	    $htText = clsHTML::BuildLink($url,'as text','show the cart as plain text');

	    if ($this->IsVoided()) {
		$htVoid = NULL;
	    } else {
		$arURL = array(
		    'page'	=> 'cart',
		    'id'	=> $id,
		    'do'	=> 'void'
		    );
		$url = $oPage->SelfURL($arURL);
		$htVoid = ' ['.clsHTML::BuildLink($url,'void','void the cart now').']';
	    }

	    $rcSess = $this->SessionRecord();
	    $htSess = $rcSess->AdminLink();

	    $htCust = $this->CustomerRecord(FALSE)->AdminLink();

	    if (is_null($this->Value('ID_Order'))) {
		$url = $oPage->SelfURL(array('do'=>'find-ord'));
		$htOrd = clsHTML::BuildLink($url,'find order!');
	    } else {
		$rcOrder = $this->OrderRecord();
		$htOrd = $rcOrder->AdminLink_name();
	    }

	    $sWhenCreated = $this->Value('WhenCreated');
	    $sWhenOrdered = $this->Value('WhenOrdered');
	    $sWhenUpdated = $this->Value('WhenUpdated');
	    $sWhenViewed = $this->Value('WhenViewed');
	    $sWhenVoided = $this->Value('WhenVoided').$htVoid;
	    $out .= <<<__END__
<ul>
<li><b>ID</b>: $id [$htText]</li>
<li><b>When Created</b>: $sWhenCreated</li>
<li><b>When Ordered</b>: $sWhenOrdered</li>
<li><b>When Updated</b>: $sWhenUpdated</li>
<li><b>When Viewed</b>: $sWhenViewed</li>
<li><b>When Voided</b>: $sWhenVoided</li>
<li><b>Session</b>: $htSess</li>
<li><b>Order</b>: $htOrd</li>
<li><b>Customer</b>: $htCust</li>
</ul>
__END__;
	    $out .=
	      $oPage->SectionHeader('Items')
	      .$this->RenderItemRows()
	      .$oPage->SectionHeader('Fields')
	      .$this->RenderFieldsRows()
	      .$oPage->SectionHeader('Events - system')
	      .$this->EventListing()
	      .$oPage->SectionHeader('Events - cart')
	      .$this->RenderEventRows();
	} // END if($doShow)
	return $out;
    }
}
