<?php
/*
  PURPOSE: order transaction classes
  HISTORY:
    2014-01-24 extracted from dropins/order.php to dropins/trxact.php
*/
// order transactions
class VCT_OrderTrxacts extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_OrderTrxact');	// override parent
	  $this->Name('ord_trxact');
	  $this->KeyName('ID');
	  $this->ActionKey('trx');
    }
    /*----
      ACTION: Display a link to edit a new transaction
      HISTORY:
	2011-03-24 created
    */
    public function AdminLink_create($iText='new',$iPopup='edit a new transaction',array $iArgs=NULL) {
	$obj = $this->SpawnItem();
	return $obj->AdminLink($iText,$iPopup,$iArgs);
    }
}
class VCR_OrderTrxact extends clsDataSet {
    protected $arBal;

    // ++ BOILERPLATE: linking ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData::_AdminRedirect($this,$iarArgs);
    }

    // ++ BOILERPLATE: logging ++ //

    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }

    // -- BOILERPLATE -- //
    // ++ FIELD ACCESS ++ //

    protected function OrderID() {
	return $this->Value('ID_Order');
    }

    // -- FIELD ACCESS -- //
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
    // ++ DATA TABLE ACCESS ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make($this->OrdersClass(),$id);
    }
    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make($this->PackagesClass(),$id);
    }
    protected function TypeTable($id=NULL) {
	return $this->Engine()->Make($this->TypesClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //

    // ++ DATA RECORDS ACCESS ++ //

    public function OrderObj() {
	return $this->OrderTable($this->OrderID());
    }
    public function PkgObj() {
	return $this->PackageTable($this->Value('ID_Package'));
    }
    /*----
      TODO: This should probably be cached
    */
    public function TypeObj() {
	$obj = $this->TypeTable($this->Value('ID_Type'));
	return $obj;
    }
    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
    */
    private function BuildEditForm() {
	global $vgOut;

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
    /*-----
      ACTION: Save the user's edits to the transaction
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::AdminSave()
	2011-01-02 Replaced with VbzAdminItem::AdminSave() version
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      HISTORY:
	2011-01-01 Created
    */
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage;

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

	$objOrd = $this->OrderObj();
	$objPkg = $this->PkgObj();
	$objType = $this->TypeObj();

	$ctOrd = $objOrd->AdminLink_name();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    $ctPkg	= $objPkg->DropDown_ctrl('ID_Package','--not package-specific--');
	    $strNone 	= $isNew?'-- choose --':NULL;
	    $ctType	= $objType->DropDown_ctrl('ID_Type',NULL,$strNone);
	    $ctOrd	.= $frm->Render('ID_Order');
	    $ctWhenDone	= $frm->Render('WhenDone');
	    $ctWhenVoid	= $frm->Render('WhenVoid');
	    $ctDescr	= $frm->Render('Descr');
	    $ctAmt	= $frm->Render('Amount');
	} else {
	    $ctPkg = $objPkg->AdminLink_name();
	    $ctType	= $objType->Name;
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
    public function AdminTable(array $iArgs=NULL, clsVbzRecs $iContext=NULL) {
	$oPage = $this->Engine()->App()->Page();

	$strDo = $oPage->PathArg('trx.do');
	if ($strDo == 'void') {
	    $idVoid = $oPage->PathArg('trx.id');
	    $objTrx = $this->Table()->GetItem($idVoid);
	    $objOrd = $objTrx->OrderObj();
	    $arEv = array(
	      'descr'	=> 'voiding',
	      'params'	=> ':trx='.$idVoid,
	      'code'	=> 'TVOID'
	      );
	    $objOrd->StartEvent($arEv);
	    $objTrx->Update(array('WhenVoid'=>'NOW()'));
	    $objOrd->FinishEvent();
	    if (is_null($iContext)) {
		$this->Reload();
	    } else {
		$iContext->AdminRedirect();
	    }
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
	    $dlrBal = 0;
	    $dlrBalSale = 0;
	    $dlrBalShip = 0;
	    $dlrBalPaid = 0;
	    $arVoidLink = $oPage->PathArgs(array('page','id'));
	    $arVoidLink['trx.do'] = 'void';
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$idOrd = $this->Value('ID_Order');	// save for later

		$row = $this->Row;
		$id = $row['ID'];
		$ftID = $this->AdminLink();
		$idPkg = $row['ID_Package'];
		//$idType = $row['ID_Type'];
		$objType = $this->TypeObj();
		$htType = $objType->DocLink();
		$strWhenDone = $row['WhenDone'];
		$strWhenVoid = $row['WhenVoid'];
		$dlrAmt = $row['Amount'];
		$strAmount = clsMoney::BasicFormat($dlrAmt);
		$strDescr = $row['Descr'];

		if (is_null($row['WhenVoid'])) {

// 2011-03-31 This data needs redesigning. See notes on ord_trx_type table.
		    IncMoney($dlrBal,$dlrAmt);
		    if ($objType->isShipg != chr(0)) {
			IncMoney($dlrBalShip,$dlrAmt);
		    } elseif ($objType->isCash == chr(0)) {
			IncMoney($dlrBalSale,$dlrAmt);
		    } else {
			IncMoney($dlrBalPaid,$dlrAmt);
		    }
		    // make a link to void this transaction
		    $arVoidLink['trx.id'] = $this->ID;
		    $url = $oPage->SelfURL($arVoidLink,FALSE);
		    $htWhenVoid = '[ '.clsHTML::BuildLink($url,'void it','void this transaction').' ]';
		} else {
		    $wtStyle .= ' color:#888888;';
		    // just show the void timestamp (later: link to un-void it)
		    $htWhenVoid = $strWhenVoid;
		}

		//$out .= "\n|- style=\"$wtStyle\"";
		//$out .= "\n| $id || $idPkg || $htType || $strWhenDone || $strWhenVoid || align=right | $strAmount || $strDescr ";
		$out .= <<<__END__
  <tr style="$wtStyle">
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
	    $htNew = $this->Table->AdminLink_create('new','add a transaction',$arTrx);
	    $ftBal = clsMoney::BasicFormat($dlrBal);
	    $ftBalSale = clsMoney::BasicFormat($dlrBalSale,'$');
	    $ftBalShip = clsMoney::BasicFormat($dlrBalShip,'$','+');
	    $ftBalPaid = clsMoney::BasicFormat(-$dlrBalPaid,'$','+');
	    $out .= <<<__END__
  <tr>
    <td>[ $htNew ]</td>
    <td align=right colspan=4><b>Total</b>:</td>
    <td align=right>$<b>$ftBal</b></td>
    <td><i>($ftBalSale sale $ftBalShip s/h $ftBalPaid paid)</i></td>
  </tr>
</table>
__END__;
	    // save calculated totals
	    $this->arBal['sale'] = $dlrBalSale;
	    $this->arBal['ship'] = $dlrBalShip;
	    $this->arBal['total'] = $dlrBalSale + $dlrBalShip;
	} else {
	    $this->arBal = NULL;
	    $strDescr = nz($iArgs['descr']);
	    $out = "\nNo transactions$strDescr.";
	}
	return $out;
    }
    public function Balances() {
	return $this->arBal;
    }
    public function HasBalance() {
	return is_array($this->arBal);
    }
}
class VCT_OrderTrxTypes extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_OrderTrxType');
	  $this->Name('ord_trx_type');
	  $this->KeyName('ID');
    }
    /*####
      Boilerplate cache functions
    */
    /*
    protected $objCache;
    protected function Cache() {
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
    public function GetData_Cached($iWhere=NULL,$iClass=NULL,$iSort=NULL) {
	return $this->Cache()->GetItem($iWhere,$iClass,$iSort);
    }
    */
}
class VCR_OrderTrxType extends clsDataSet {
    /*::::
      Simple field access functions
    */
    public function NameShort() {
	return $this->Value('Code');
    }
    public function NameLong() {
	return $this->Value('Code').' '.$this->Value('Descr');
    }
    public function DocLink($iText=NULL) {
	global $vgOut;

	$txtCode = (is_null($iText))?$this->Value('Code'):$iText;
	// TODO: should integrate with a wiki module to detect if the wiki page is actually available
	return clsHTML::BuildLink(KWT_DOC_TRX_TYPES.'/'.$this->Value('Code'),$txtCode,$this->Value('Descr'));
    }
    /*----
      HISTORY:
	2011-01-02 Adapted from VbzAdminDept::DropDown
	  Control name now defaults to table action key
    */
    public function DropDown_for_data($iName=NULL,$iDefault=NULL,$iNone=NULL,$iAccessFx='NameShort') {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key
	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->ID;
		$htAbbr = (is_null($this->PageKey))?'':($this->PageKey.' ');
		$htShow = $htAbbr.$this->$iAccessFx();
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Renders a drop-down control showing all transaction types, with the
	current record being the default.
    */
    public function DropDown_ctrl($iName=NULL,$iNone=NULL) {
	$dsAll = $this->Table->GetData(NULL,NULL,'Code');
	return $dsAll->DropDown_for_data($iName,$this->ID,$iNone,'NameLong');
    }
}
