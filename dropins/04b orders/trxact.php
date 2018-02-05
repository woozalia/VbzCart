<?php
/*
  PURPOSE: order transaction classes
  HISTORY:
    2014-01-24 extracted from dropins/order.php to dropins/trxact.php
    2017-01-06 partially updated
*/
// order transactions
class vctOrderTrxacts extends vcAdminTable {
    use ftLinkableTable;
    
    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ord_trxact';
    }
    // CEMENT
    protected function SingularName() {
	return 'VCR_OrderTrxact';
    }
    // CEMENT
    public function GetActionKey() {
	return 'trx';
    }
    
    // -- SETUP -- //
    // ++ TRAIT HELPERS ++ //
    
    /*----
      ACTION: Display a link to edit a new transaction
      NOTE: despite creating a new object, this doesn't actually do anything in the database
      HISTORY:
	2011-03-24 created
    */
    public function SelfLink_toCreate($iText='new',$iPopup='edit a new transaction',array $iArgs=NULL) {
	$rc = $this->SpawnRecordset();
	return $rc->SelfLink($iText,$iPopup,$iArgs);
    }
    
    // -- TRAIT HELPERS -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return 'No table admin functions written (yet?).';	// ability to search by amount might be useful, later
    }

    // -- EVENTS -- //

}
class VCR_OrderTrxact extends vcAdminRecordset {
    use ftFrameworkAccess;
    
    protected $arBal;
    
    // ++ SETUP ++ //
    
    /*----
      NOTES: idOrder and idPkg are optional so that Package Lines can provision transaction objects without
	having to know the Package ID. (The Package object fills these in and saves the records to disk.)
    */
    public function Provision(
      $idType,
      $nAmount,
      $sText,
      $idOrder=NULL,
      $idPkg = NULL
      ) {
	$this->TypeID($idType);
	$this->Amount($nAmount);
	$this->Description($sText);
	$this->SetOrderID($idOrder);
	$this->SetPackageID($idPkg);
    }
    
    // -- SETUP -- //
    // ++ CALLBACKS ++ //

    // WARNING: This will overwrite any previously set value for WhenDone.
    protected function InsertArray($ar = NULL) {
	$ar = parent::InsertArray($ar);
	$ar['WhenDone']	= 'NOW()';
	return $ar;
    }

    // -- CALLBACKS -- //
    // ++ FIELD VALUES ++ //

    public function TypeID($id=NULL) {
	throw new exception('2017-06-05 Call GetTypeID() or SetTypeID().');
    }
    protected function SetTypeID($id) {
	return $this->SetFieldValue('ID_Type',$id);
    }
    // PUBLIC so Package objects can update their record of amounts charged
    public function GetTypeID() {
	return $this->GetFieldValue('ID_Type');
    }
    protected function SetOrderID($id) {
	$this->SetValue('ID_Order',$id);
    }
    protected function GetOrderID() {
	return $this->GetFieldValueNz('ID_Order');
    }
    protected function SetPackageID($id) {
	$this->SetValue('ID_Package',$id);
    }
    protected function GetPackageID() {
	return $this->GetFieldValueNz('ID_Package');
    }
    public function Amount($n=NULL) {
	throw new exception('2017-06-05 Call GetAmount() or SetAmount() instead.');
    }
    protected function SetAmount($n) {
	return $this->SetFieldValue('Amount',$n);
    }
    // PUBLIC so Package objects can update their record of amounts charged
    public function GetAmount() {
	return $this->GetFieldValue('Amount');
    }
    public function Description($s=NULL) {
	throw new exception('2017-06-05 Call GetAboutString() or SetAboutString() instead.');
    }
    // NOTE: Was "PUBLIC so newly-created Transaction record objects can be listed without being in a recordset" - but not sure if this is read or write
    protected function SetAboutString($s) {
	return $this->SetFieldValue('Descr',$s);
    }
    protected function GetAboutString() {
	return $this->GetFieldValue('Descr');
    }
    // MEANS: When the transaction took place
    protected function WhenDone() {
	return $this->GetFieldValue('WhenDone');
    }
    protected function WhenVoided() {
	return $this->GetFieldValue('WhenVoid');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    // PUBLIC so Package objecs can finish provisioning unsaved Transactions
    public function SetPackage(vcrAdminPackage $rcPkg) {
	$this->SetPackageID($rcPkg->GetKeyValue());
	$this->SetOrderID($rcPkg->GetOrderID());
    }
    protected function IsActive() {
	return is_null($this->WhenVoided());
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ BALANCE CALCULATIONS ++ //
    
    private $dlrBal, $dlrBalSale, $dlrBalShip, $dlrBalPaid;
    protected function ClearBalances() {
	$this->dlrBal = $this->dlrBalSale = $this->dlrBalShip = $this->dlrBalPaid = NULL;
    }
    protected function AddToBalances() {
	$dlrAmt = $this->GetAmount();
	
	fcMoney::Sum($this->dlrBal,$dlrAmt);
	
	// add to category sums
	$rcType = $this->TypeRecord();
	if ($rcType->IsShipping()) {
	    fcMoney::Sum($this->dlrBalShip,$dlrAmt);
	} elseif ($rcType->IsSale()) {
	    fcMoney::Sum($this->dlrBalSale,$dlrAmt);
	} elseif ($rcType->IsPayment()) {
	    fcMoney::Sum($this->dlrBalPaid,$dlrAmt);
	} else {
	    throw new exception('Internal error: unknown transaction type category.');
	}
    }
    protected function HasBalances() {
	return !is_null(GetBalance_Final());
    }
    // PUBLIC so Order record object can get amount to charge
    public function GetBalance_Final() {
	return $this->dlrBal;
    }
    protected function GetBalance_Sale() {
	return $this->dlrBalSale;
    }
    // PUBLIC so Order record object can get s/h amount (for filling out debit form, which asks)
    public function GetBalance_Ship() {
	return $this->dlrBalShip;
    }
    protected function GetBalance_Paid() {
	return $this->dlrBalPaid;
    }
    protected function GetBalance_Final_string() {
	return fcMoney::Format_withSymbol($this->GetBalance_Final());
    }
    // RETURNS: string showing balances for each category
    protected function GetBalances_summary_string() {
	$ftBalSale	= fcMoney::Format_withSymbol($this->GetBalance_Sale(),'$');
	$ftBalShip	= fcMoney::Format_withSymbol($this->GetBalance_Ship(),'$','+');
	$ftBalPaid	= fcMoney::Format_withSymbol(-$this->GetBalance_Paid(),'$','+');
	
	return "$ftBalSale sale $ftBalShip s/h $ftBalPaid paid";
    }
    /*----
      USED BY Package record object, when calculating amount to charge debit card
      INPUT: recordset (self)
    */
    public function CalculateBalances() {
	while ($this->NextRow()) {
	    if ($this->IsActive()) {
		$this->AddToBalances();
	    }
	}
    }
    
    // -- BALANCE CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function OrdersClass() {
	return KS_CLASS_ORDERS;
    }
    protected function PackagesClass() {
	return KS_CLASS_PACKAGES;	// TODO: fail gracefully if vbz.ship isn't available
    }
    protected function TypesClass() {
	return KS_CLASS_ORDER_TRX_TYPES;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make($this->OrdersClass(),$id);
    }
    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make($this->PackagesClass(),$id);
    }
    protected function TypeTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TypesClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function OrderObj() {
	throw new exception('Call OrderRecord() instead of OrderObj().');
    }
    protected function OrderRecord() {
	return $this->OrderTable($this->GetOrderID());
    }
    public function PkgObj() {
	throw new exception('Call PackageRecord() instead of PkgObj().');
    }
    protected function PackageRecord() {
	return $this->PackageTable($this->Value('ID_Package'));
    }
    /*----
      TODO: This should probably be cached
    */
    public function TypeObj() {
	throw new exception('Call TypeRecord() instead of TypeObj().');
    }
    protected function TypeRecord() {
	$rc = $this->TypeTable($this->GetTypeID());
	return $rc;
    }
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //

    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
    */
    private function EditForm() {
	if (is_null($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldNum('ID_Order'),	new clsCtrlHTML_Fixed());
	    $objForm->AddField(new clsFieldNum('ID_Package'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Type'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenDone'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenVoid'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('Amount'),		new clsCtrlHTML(array('size'=>6)));

	    $this->objForm = $objForm;
	}
    }
    /*----
      HISTORY:
	2011-01-01 Created
    */
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage;

	throw new exception('2016-09-11 This will need rewriting to use Ferreteria.');
	// get status from URL
	$isNew = $this->IsNew();
	//$strAction = $vgPage->Arg('do');
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');

	if ($isNew) {
	    $strTitle = 'New Transaction';
	    $this->Value('ID_Order',$vgPage->Arg('id.ord'));
	} else {
	    $strTitle = 'Transaction #'.$this->ID;
	}

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();


	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$rcOrd = $this->OrderRecord();
	$rcPkg = $this->PkgObj();
	$rcType = $this->TypeObj();

	$ctOrd = $rcOrd->SelfLink_name();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    $ctPkg	= $rcPkg->DropDown_ctrl('ID_Package','--not package-specific--');
	    $strNone 	= $isNew?'-- choose --':NULL;
	    $ctType	= $rcType->DropDown_ctrl('ID_Type',NULL,$strNone);
	    $ctOrd	.= $frm->Render('ID_Order');
	    $ctWhenDone	= $frm->Render('WhenDone');
	    $ctWhenVoid	= $frm->Render('WhenVoid');
	    $ctDescr	= $frm->Render('Descr');
	    $ctAmt	= $frm->Render('Amount');
	} else {
	    $ctPkg = $objPkg->AdminLink_name();
	    $ctType	= $rcType->Name;
	    $ctWhenDone	= $this->Value('WhenDone');
	    $ctWhenVoid	= $this->Value('WhenVoid');
	    $ctDescr	= $this->Value('Descr');
	    $ctAmt	= $this->Value('Amount');
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>"		.$ctOrd.'</tr>';
	$out .= "\n<tr><td align=right><b>Package</b>:</td><td>"	.$ctPkg.'</tr>';
	$out .= "\n<tr><td align=right><b>Type</b>:</td><td>"		.$ctType.'</tr>';
	$out .= "\n<tr><td align=right><b>When Done</b>:</td><td>"	.$ctWhenDone.'</tr>';
	$out .= "\n<tr><td align=right><b>When Voided</b>:</td><td>"	.$ctWhenVoid.'</tr>';
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>"	.$ctDescr.'</tr>';
	$out .= "\n<tr><td align=right><b>Amount</b>:</td><td>$"	.$ctAmt.'</tr>';
	$out .= "\n</table>";

	if ($doEdit) {
	    $out .= '<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out);
	return NULL;
    }
    /*----
      MINOR BUG: When deleting, the first row of the resultset is not shown.
	This is due to Reload() advancing the pointer. We need some way to
	prevent that action. (2010-10-25)
    */
    public function AdminTable(fiLinkable $rcPage=NULL) {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	if (is_null($rcPage)) {
	    $rcPage = $this;
	}

	$strDo = $oPathIn->GetString('trx.do');
	if ($strDo == 'void') {
	    $idVoid = $oPathin->GetString('trx.id');
	    $rcTrx = $this->GetTableWrapper()->GetRecord_forKey($idVoid);
	    $rcOrd = $rcTrx->OrderRecord();
	    $arEv = array(
	      'descr'	=> 'voiding',
	      'params'	=> ':trx='.$idVoid,
	      'code'	=> 'TVOID'
	      );
	    $rcEv = $rcOrd->CreateEvent($arEv);		// TODO 2017-04-11: use EventPlex
	    $rcTrx->Update(array('WhenVoid'=>'NOW()'));
	    //$rcOrd->FinishEvent();
	    $rcEv->Finish();				// TODO same

	    $rcPage->SelfRedirect();
	}

	if ($this->hasRows()) {
	    //$out = "\n{| class=sortable \n|-\n! ID || Pkg || Type || When Done || When Void || Amount || Description";
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Pkg</th>
    <th>Type</th>
    <th>When Done</th>
    <th>When Void</th>
    <th>Amount</th>
    <th>Description</th>
  </tr>
__END__;

	    $isOdd = TRUE;
	    $this->ClearBalances();
	    //$arVoidLink['trx.do'] = 'void';
	    $this->RewindRows();
	    while ($this->NextRow()) {
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$id = $this->GetKeyValue();
		$ftID = $this->SelfLink();
		$idOrd = $this->GetOrderID();	// save for later
		$idPkg = $this->GetPackageID();
		$rcType = $this->TypeRecord();
		$htType = $rcType->DocLink();
		$strWhenDone = $this->WhenDone();
		$strWhenVoid = $this->WhenVoided();
		$dlrAmt = $this->GetAmount();
		$strAmount = fcMoney::Format_withSymbol($dlrAmt);
		$strDescr = $this->GetAboutString();

		if ($this->IsActive()) {
		    $this->AddToBalances();
		    // make a link to void this transaction
		    $arVoidLink['trx.id'] = $this->GetKeyValue();
		    //$url = $oPage->SelfURL($arVoidLink,FALSE);
		    //$htWhenVoid = '[ '.fcHTML::BuildLink($url,'void it','void this transaction').' ]';
		    $htWhenVoid = $this->SelfLink('void it','void this transaction',array('trx.do' => 'void'));
		} else {
		    $cssClass = 'voided';
		    // just show the void timestamp (later: link to un-void it)
		    $htWhenVoid = $strWhenVoid;
		}

		$out .= <<<__END__
  <tr class="$cssClass">
    <td>$ftID</td>
    <td>$idPkg</td>
    <td>$htType</td>
    <td>$strWhenDone</td>
    <td align=center>$htWhenVoid</td>
    <td align=right>$strAmount</td>
    <td align=right>$strDescr</td>
  </tr>
__END__;
	    }
	    $arTrx = array(
	      'id.ord' => $idOrd
	      );
	    $dlrBal = $this->GetBalance_Final();
	    
	    $htNew = $this->GetTableWrapper()->SelfLink_toCreate('new','add a transaction',$arTrx);
	    $ftBalFinal	= $this->GetBalance_Final_string();
	    $ftBalCatgs = $this->GetBalances_summary_string();
	    
	    // show additional action-options
	    //$arLink = $oPage->PathArgs(array('page','id'));
	    //$arLink['do'] = 'charge';
	    $prcChg = $dlrBal;
	    if ($prcChg > 0) {
		$ftChargeLink = $rcPage->SelfLink(
		  'charge for balance',
		  'create a credit card charge for the $'.$prcChg.' current balance',
		  array('do'=>'charge'));
		$htChargeCtrl = "[ $ftChargeLink ]";
	    } else {
		$htChargeCtrl = '(nothing to charge)';
	    }
	    
	    $out .= <<<__END__
  <tr>
    <td colspan=4>[ $htNew ] $htChargeCtrl</td>
    <td colspan=1 align=right><b>Balance</b>:</td>
    <td colspan=1 align=right><b>$ftBalFinal</b></td>
    <td><i>($ftBalCatgs)</i></td>
  </tr>
</table>
__END__;
	} else {
	    $out = "<div class=content>No transactions found.</div>";
	}
	return $out;
    }
    
    // -- WEB UI -- //
    
}
