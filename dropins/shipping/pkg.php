<?php
/*
  FILE: pkg.php -- standalone/dropin package administration for VbzCart
  HISTORY:
    2010-10-15 Extracted package classes from SpecialVbzAdmin.php
    2013-12-15 Adapting for drop-in system
    2014-04-20 Extracted package-line classes to pkg-line.php
*/

define('KS_ACTION_PKG_LINES_EDIT','edit.lines');
define('KS_ACTION_PKG_LINES_ADD','add.lines');

class clsPackages extends clsVbzTable {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_pkgs');
	  $this->KeyName('ID');
	  $this->ClassSng('clsPackage');
	  $this->ActionKey('pkg');
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->ExecArgs($arArgs);
	$out = $this->RenderSearch();
	return $out;
    }
    private $arExec;
    // PUBLIC so recordset can call it
    public function ExecArgs(array $arArgs=NULL) {
	if (!is_null($arArgs)) {
	    $this->arExec = $arArgs;
	}
	return $this->arExec;
    }

    // -- DROP-IN API -- //
    // ++ DATA RECORD ACCESS ++ //

    // TODO: Rename to OrderRecord()
    public function GetOrder($iID) {
	$rsPkgs = $this->GetData('ID_Order='.$iID);
	$rsPkgs->OrderID($iID);	// make sure this is set, regardless of whether there is data
	return $rsPkgs;
    }
    /*----
      RETURNS: empty Package object
      TODO: Rename to EmptyRecord()
      HISTORY:
	2011-10-08 created so order message editing can display pkg drop-down that includes "no pkg" as an option
    */
    public function GetEmpty() {
	$rc = $this->SpawnItem();
	return $rc;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ CALCULATIONS ++ //

    public function NextSeq_forOrder($idOrder) {
	$sqlSelf = $this->NameSQL();
	$sql = "SELECT Seq FROM $sqlSelf WHERE (ID_Order=$idOrder) ORDER BY Seq DESC LIMIT 1;";
	$rc = $this->DataSQL($sql);
	if ($rc->HasRows()) {
	    $rc->FirstRow();	// load the only row
	    $seq = $rc->Seq();	// get the Seq value
	} else {
	    $seq = 0;
	}
	return ($seq + 1);
    }

    // -- CALCULATIONS -- //
    // ++ ADMIN WEB UI ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$sPfx = $this->ActionKey();
	$sCNStDate = $sPfx.'-when-start';
	$sCNFiDate = $sPfx.'-when-finish';
	$sCNLimit = $sPfx.'-limit';
	$sCNSort = $sPfx.'-sort';

	$sSrchSt = clsHTTP::Request()->GetText($sCNStDate);
	$sSrchFi = clsHTTP::Request()->GetText($sCNFiDate);
	$nLimit = clsHTTP::Request()->GetInt($sCNLimit);
	$sSort = clsHTTP::Request()->GetText($sCNSort);

	if (is_null($sSort)) {
	    $sSort = 'ID DESC';		// default sorting
	}

	$doSearch = (!empty($sSrchSt) || !empty($sSrchFi) || !empty($nLimit));
	$htSrchSt = '"'.htmlspecialchars($sSrchSt).'"';
	$htSrchFi = '"'.htmlspecialchars($sSrchFi).'"';
	$htSort = '"'.htmlspecialchars($sSort).'"';

	// build forms

	$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$htSearchForm = <<<__END__
<form method=post>
  earliest:
  <input name="$sCNStDate" size=40 value=$htSrchSt>
  latest:
  <input name="$sCNFiDate" size=40 value=$htSrchFi>
  maximum # of results:
  <input name="$sCNLimit" size=5 value=$nLimit>
  sort by:
  <input name="$sCNSort" size=40 value=$htSort>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	$out = $htSearchHdr.$htSearchForm;

	if ($doSearch) {
	    $oSQL = new clsSQLFilt('AND');
	    if (!empty($sSrchSt)) {
		$oSQL->AddCond('WhenStarted >= '.SQLValue($sSrchSt));
	    }
	    if (!empty($sSrchFi)) {
		$oSQL->AddCond('WhenFinished >= '.SQLValue($sSrchFi));
	    }
	    $oSQL->Order($sSort);
	    $oSQL->Limit($nLimit);
	    $sql = $oSQL->RenderQuery();

	    $rs = $this->DataSet($sql);
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      . '<b>SQL</b>: '.$rs->sqlMake
	      .$rs->AdminRows();
	}

	return $out;
    }
}
class clsPackage extends clsVbzRecs {

    // ++ SETUP ++ //

    /*----
      ACTION: Initialize a blank object from available inputs
      CALLED BY: URL-parsing method in Page object
    */
    public function InitFromInput() {
	$oPage = $this->Engine()->App()->Page();
	$idOrd = NULL;

	// initialize order ID field
//	$idOrd = $oPage->PathArg(KS_PAGE_KEY_ORDER);
	$oPath = $this->Engine()->App()->Page()->PathObj();
	if ($oPath->KeyExists(KS_PAGE_KEY_ORDER)) {
	    $idOrd = $oPath->GetInt(KS_PAGE_KEY_ORDER);
	}
	if (empty($idOrd)) {
	    $idOrd = clsHTTP::Request()->GetInt(KS_PAGE_KEY_ORDER);
	}
	if (!empty($idOrd)) {
//	    $this->Value('ID_Order',$idOrd);
	    $this->OrderID($idOrd);
	}

/*
	if ($oPath->KeyExists(KS_PAGE_KEY_ORDER)) {
	    $idOrd = $oPath->Value(KS_PAGE_KEY_ORDER);
	    $this->OrderID($idOrd);
	}
	*/
    }

    // -- SETUP -- //
    // ++ OBJECT STATUS ACCESS ++ //

    private $doFetch;
    protected function DoFetch($fYes=NULL) {
	if (!is_null($fYes)) {
	    $this->doFetch = $fYes;
	}
	return $this->doFetch;
    }
    private $doReplace;
    protected function DoReplace($fYes=NULL) {
	if (!is_null($fYes)) {
	    $this->doReplace = $fYes;
	}
	return $this->doReplace;
    }

    // -- OBJECT STATUS ACCESS -- //
    // ++ BOILERPLATE AUXILIARY ++ //

    public function AdminLink_name($iPopup=NULL,array $iarArgs=NULL) {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $txt = $this->Number();
	    return $this->AdminLink($txt,$iPopup,$iarArgs);
	}
    }

    // -- BOILERPLATE AUXILIARY -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ APP FRAMEWORK ACCESS ++ //

    protected function Page() {
	return $this->Engine()->App()->Page();
    }

    // -- APP FRAMEWORK ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function LCatItemsClass() {
	if (clsDropinManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItems';
	}
    }
    protected function StockItemsClass() {
	if (clsDropinManager::ModuleLoaded('vbz.stock')) {
	    return KS_CLASS_STOCK_LINES;
	} else {
	    return NULL;	// requires stock module
	}
    }
    protected function OrdersClass() {
	return KS_CLASS_ORDERS;
    }
    protected function ShipmentsClass() {
	return KS_CLASS_SHIPMENTS;
    }
    protected function TrxactsClass() {
	return KS_CLASS_ORDER_TRXS;
    }
    protected function BinsClass() {
	if (clsDropinManager::ModuleLoaded('vbz.stock')) {
	    return KS_CLASS_STOCK_BINS;
	} else {
	    return NULL;	// class not available
	    // TODO: handle this gracefully
	}
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make($this->OrdersClass(),$id);
    }
    protected function OrderLineTable($id=NULL) {
	return $this->OrderTable()->LineTable($id);
    }
    protected function ShipmentTable($id=NULL) {
	return $this->Engine()->Make($this->ShipmentsClass(),$id);
    }
    protected function TrxactTable($id=NULL) {
	return $this->Engine()->Make($this->TrxactsClass(),$id);
    }
    /*----
      RETURNS: LINE subdata table (order lines)
    */
    public function LineTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PACKAGE_LINES,$id);
    }
    public function BinTable($id=NULL) {
	return $this->Engine()->Make($this->BinsClass(),$id);
    }
    protected function LCatItemTable($id=NULL) {
	return $this->Engine()->Make($this->LCatItemsClass(),$id);
    }
    protected function StockItemTable() {
	return $this->Engine()->Make($this->StockItemsClass());
    }
    protected function StockItemLog($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINE_LOG,$id);
    }


    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      RETURNS: ORDER superdata record
    */
    public function OrderObj() {
	throw new exception('OrderObj() is deprecated; call OrderRecord().');
    }
    private $rcOrd;
    protected function OrderRecord() {
	$doLoad = TRUE;
	$idObj = $this->ValueNz('ID_Order');
	if (is_null($idObj)) {
	   $this->rcOrd = NULL;
	} else {
	    if (isset($this->rcOrd)) {
		$doLoad = ($idObj != $this->rcOrd->KeyValue());
	    }
	    if ($doLoad) {
		$this->rcOrd = $this->OrderTable($idObj);
	    }
	    if ($this->rcOrd->IsNew()) {
		throw new exception('Order object has no ID; package ID_Order=['.$idObj.']');
	    }
	}
	return $this->rcOrd;
    }
    /*----
      RETURNS: SHIPMENT superdata record
    */
    public function ShipObj() {
	throw new exception('ShipObj() is deprecated; call ShipRecord().');
    }
    private $rcShip;
    protected function ShipRecord() {
	$doLoad = TRUE;
	$idObj = $this->Value('ID_Shipment');
	if (isset($this->rcShip)) {
	    $doLoad = ($idObj != $this->rcShip->KeyValue());
	}
	if ($doLoad) {
	    $this->rcShip = $this->ShipmentTable($idObj);
	}
	return $this->rcShip;
    }

    public function TrxactRecords() {
	$tbl = $this->TrxactTable();
	$rs = $tbl->GetData('ID_Package='.$this->KeyValue());
	return $rs;
    }
    /*----
      RETURNS: LINE subdata recordset
    */
    public function LinesData($iFilt=NULL) {
	throw new exception('LinesData() is deprecated; call LineRecords(). Also, does this really need to be public?');
    }
    protected function LineRecords($iFilt=NULL) {
	$objTbl = $this->LineTable();
	$sqlFilt = 'ID_Pkg='.$this->KeyValue();
	if (!is_null($iFilt)) {
	    $sqlFilt = "($sqlFilt) AND ($iFilt)";
	}
	$objRows = $objTbl->GetData($sqlFilt);
	return $objRows;
    }
    /*----
      ACTION: Find an unused (inactive) line record, if any.
    */
    protected function LineRecord_unused() {
	$sqlUnused = "(IFNULL(QtyShipped,0)=0) AND (IFNULL(QtyReturned,0)=0) ORDER BY ID LIMIT 1";
	$idPkg = $this->KeyValue();

	// first, look only at inactive lines already assigned to this package
	$sqlFilt = "(ID_Pkg=$idPkg) AND $sqlUnused";
	$rc = $this->LineTable()->GetData($sqlFilt);
	if ($rc->RowCount() == 0) {
	    // if nothing found, then look for *any* inactive lines
	    $sqlFilt = $sqlFiltUnused;
	    $rc = $this->LineTable()->GetData($sqlFilt);
	    if ($rc->RowCount() == 0) {
		return NULL;
	    }
	}
	return $rc;
    }
    protected function LineRecord_forItem($idItem) {
	if ($this->IsNew()) {
	    $rc = NULL;
	} else {
	    $idPkg = $this->KeyValue();
	    $sqlFilt = "(ID_Pkg=$idPkg) AND (ID_Item=$idItem)";
	    $rc = $this->LineTable()->GetData($sqlFilt);
	    if ($rc->RowCount() == 0) {
		$rc = $this->LineRecord_unused();
		if (!is_null($rc)) {
		    $rc->NextRow();
		    // re-initialize the line with the necessary pkg, item, & ord line
		    $rc->Update(array(
		      'ID_Pkg'	=> $idPkg,
		      'ID_Item'	=> $idItem,
		      ));
		 }
	    } else {
		$rc->FirstRow();	// load the row
	    }
	}
	return $rc;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ FIELD ACCESS ++ //

    // PUBLIC so Package Table can call it
    public function OrderID($id=NULL) {
	return $this->Value('ID_Order',$id);
    }
    protected function ShipID() {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    return $this->Value('ID_Shipment');
	}
    }
    protected function ShipPounds() {
	return $this->Value('ShipPounds');
    }
    protected function ShipOunces() {
	return $this->Value('ShipOunces');
    }
    protected function ShipNotes() {
	return $this->Value('ShipNotes');
    }
    protected function ShipTracking() {
	return $this->Value('ShipTracking');
    }
    protected function WhenArrived() {
	return $this->Value('WhenArrived');
    }
    /*----
      FUTURE: need to define this more rigorously. Should it still return TRUE
	if the package has been checked into a shipment?
    */
    public function IsActive() {
	return is_null($this->Value('WhenVoided'));
    }
    public function IsVoid() {
	return !is_null($this->Value('WhenVoided'));
    }
    public function IsChecked() {
	return !is_null($this->Value('WhenChecked'));
    }
    public function Seq() {
	return $this->Value('Seq');
    }
    public function Number() {
	$out = $this->OrderRecord()->Number();
	$out .= '-';
	$intSeq = $this->Seq();
	$out .= empty($intSeq)?'*':$intSeq;
	return $out;
    }
    public function Name() {	// alias
	return $this->Number();
    }

    // -- FIELD ACCESS -- //
    // ++ CALCULATIONS ++ //

    /*----
      RETURNS: TRUE iff the package contains some items
	Currently returns TRUE as soon as a line with >0 items in it is found;
	  does not add up the rest of the package.
      HISTORY:
	2011-10-08 created so we can put packages back into stock
    */
    public function ContainsItems() {
	if ($this->IsNew()) {
	    return FALSE;
	} else {
	    $rc = $this->LineRecords();
	    if ($rc->HasRows()) {
		$qty = 0;
		while ($rc->NextRow()) {
		    $qty += $rc->QtyShipped();
		    if ($qty > 0) {
			return TRUE;
		    }
		}
	    } else {
		return FALSE;
	    }
	}
    }
    protected function HasCharges() {
	return !is_null($this->ValueNz('WhenFinished'));
    }
    /*----
      RETURNS: total quantity of items currently in the package
	This is mainly used for reality-check display purposes.
      HISTORY:
	2011-10-08 created so we can put packages back into stock
    */
    public function ItemQty() {
	$rc = $this->LineRecords();
	if ($rc->HasRows()) {
	    $qty = 0;
	    while ($rc->NextRow()) {
		$qty += $rc->QtyInPackage();
	    }
	} else {
	    $qty = 0;
	}
	return $qty;
    }
    public function NextLineSeq() {
	throw new exception('Who uses NextLineSeq()?');
	$rs = $this->LineRecords();
	$seq = NextSeq($rs);
	return $seq;
    }

    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    public function DoVoid() {
	$arUpd = array('WhenVoided'=>'NOW()');
	$this->Update($arUpd);
    }
    public function AddLine($iItem, $iOLine) {
	$tLines = $this->LineTable();
	//$seq = $tLines->NextID();
	$arIns = array(
	  'ID_Pkg'	=> $this->KeyValue(),
	  'ID_Item'	=> $iItem,
	  'ID_OrdLine'	=> $iOLine
	  );
	$tLines->Insert($arIns);
	return $tLines->LastID();
    }

    // TRANSACTION subdata
    public function AddTrx($iType,$iDescr,$iAmt) {
	$arIns = array(
	  'ID_Order'	=> $this->Value('ID_Order'),
	  'ID_Package'	=> $this->KeyValue(),
	  'ID_Type'	=> $iType,
	  'WhenDone'	=> 'NOW()',
	  'Descr'	=> SQLValue($iDescr),
	  'Amount'	=> $iAmt);
	$this->OrderTrxactTable()->Insert($arIns);
    }
    /*----
      ACTION: Moves the Package's contents to the given Bin
      HISTORY:
	2011-10-08 created so we can return packages to stock
    */
    public function Move_toBin($iBin) {
	$out = NULL;
	$rc = $this->LineRecords();
	if ($rc->HasRows()) {

	    $arEv = array(
	      'descr' => 'returning package to bin #'.$iBin,
	      'where' => __METHOD__,
	      'code'  => 'RTN'
	      );
	    //$this->StartEvent($arEv);

	    $tblStk = $this->StockItemTable();

	    $qtyTot = 0;
	    while ($rc->NextRow()) {
		$qtyRow = $rc->ItemQty();
		$out .= "\n* ".$tblStk->Add_fromPkgLine($rc,$iBin);
		$qtyTot += $qtyRow;
	    }
	}
	return $out;
    }

    // -- ACTIONS -- //
    // ++ ADMIN UI ++ //

    /*----
      HISTORY:
	2011-01-02 Adapted from VbzAdminDept::DropDown to VbzAdminOrderTrxType
	  Control name now defaults to table action key
	2011-03-30 Adapted from VbzAdminOrderTrxType to clsPackage
    */
    public function DropDown_for_data($iName=NULL,$iDefault=NULL,$iNone=NULL,$iAccessFx='Number') {
	$strName = is_null($iName)?($this->Table()->ActionKey()):$iName;	// control name defaults to action key
	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		$htShow = $this->$iAccessFx();
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Renders a drop-down control showing all packages for this order, with the
	current record being the default.
    */
    public function DropDown_ctrl($iName=NULL,$iNone=NULL) {
	$rsAll = $this->Table()->GetData('ID_Order='.$this->Value('ID_Order'),NULL,'Seq');
	return $rsAll->DropDown_for_data($iName,$this->KeyValue(),$iNone);
    }
    /*----
      HISTORY:
	2011-02-17 Updated to use objForm instead of objFlds/objCtrls
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	    // FORMS v2
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);
	      //$oForm->NewValues(array('ID_Pkg'=>$this->PackageID()));
	      $oField = new fcFormField_Time($oForm,'WhenStarted');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenFinished');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenChecked');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenVoided');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenArrived');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Num($oForm,'ChgItmSale');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ChgShipItm');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ChgShipPkg');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipCost');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'PkgCost');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipPounds');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>2));
	      $oField = new fcFormField_Num($oForm,'ShipOunces');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>3));
	      $oField = new fcFormField($oForm,'ShipNotes');
		$oCtrl = new fcFormControl_HTML_TextArea($oForm,$oField,array('rows'=>3,'cols'=>60));
	      $oField = new fcFormField($oForm,'ShipTracking');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>20));

	    // FORMS v1
/*	    $frmPage = new clsForm_recs($this);
	    $frmPage->AddField(new clsFieldTime('WhenStarted'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('WhenFinished'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('WhenChecked'),	new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldTime('WhenVoided'),		new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldNum('ChgItmSale'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsFieldNum('ChgShipItm'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsFieldNum('ChgShipPkg'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsFieldNum('ShipCost'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsFieldNum('PkgCost'),		new clsCtrlHTML(array('size'=>5)));
	    $frmPage->AddField(new clsFieldNum('ShipPounds'),		new clsCtrlHTML(array('size'=>2)));
	    $frmPage->AddField(new clsFieldNum('ShipOunces'),		new clsCtrlHTML(array('size'=>3)));
	    $frmPage->AddField(new clsField('ShipNotes'),		new clsCtrlHTML_TextArea(array('rows'=>3,'cols'=>60)));
	    $frmPage->AddField(new clsField('ShipTracking'),		new clsCtrlHTML(array('size'=>20)));
	    $frmPage->AddField(new clsFieldTime('WhenArrived'),	new clsCtrlHTML());
*/
	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    if ($this->IsNew()) {
		// no timestamps yet on a new order
		$sTimes = NULL;
	    } else {
		$sTimes = <<<__END__
  <tr><td align=right><b>When Started</b>:</td>	<td><#WhenStarted#></td></tr>
  <tr><td align=right><b>When Finished</b>:</td>	<td><#WhenFinished#></td></tr>
  <tr><td align=right><b>When Checked</b>:</td>	<td><#WhenChecked#><#!CheckIn#></td></tr>
  <tr><td align=right><b>When Voided</b>:</td>		<td><#WhenVoided#><#!VoidNow#></td></tr>
__END__;
	    }

	    $sTplt = <<<__END__
  <tr><td align=right><b>Order</b>:</td>		<td><#!order#></td></tr>
$sTimes
  <tr><td align=right><b>Sale price</b>:</td>		<td><#ChgItmSale#></td></tr>
  <tr><td align=right><b>Per-item s/h total charged</b>:</td>
							<td><#ChgShipItm#></td></tr>
  <tr><td align=right><b>Per-pkg s/h amount charged</b>:</td>
							<td><#ChgShipPkg#></td></tr>
  <tr><td align=right><b>Shipment</b>:</td>		<td><#!shipment#></td></tr>
  <tr><td align=right><b>Actual shipping cost</b>:</td>
							<td><#ShipCost#></td></tr>
  <tr><td align=right><b>Actual package cost</b>:</td>	<td><#PkgCost#></td></tr>
  <tr><td align=right><b>Actual shipping weight</b>:</td>
							<td><#ShipPounds#> pounds <#ShipOunces#> ounces</td></tr>
  <tr><td align=right><b>Delivery tracking #</b>:</td>	<td><#ShipTracking#></td></tr>
  <tr><td align=right><b>When arrived</b>:</td>	<td><#WhenArrived#></td></tr>
  <tr><td colspan=2><b>Notes</b>:<br><#ShipNotes#></td></tr>
__END__;
	    $this->tpPage = new fcTemplate_array('<#','#>',$sTplt);
	}
	return $this->tpPage;
    }
    /*-----
      ACTION: Save the user's edits to the package
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
	2015-02-16 modified for change in Save()
    */
    private function AdminSave() {
	$oForm = $this->PageForm();
	if ($oForm->Save()) {
	    $this->AdminRedirect();
	}
	return $oForm->htMsg;
    }
    /*----
      HISTORY:
	2011-05-29 extracted from AdminPage(); will probably need some debugging
    */
    private function AdminDoCharge() {
	$out = '<h3>Adding charges...</h3>';

	$objLines = $this->LineRecords();
	if ($objLines->HasRows()) {	// package lines
	    $dlrSaleTot = 0;
	    $dlrItmShTot = 0;
	    $dlrPkgShTot = 0;

	    $txtLog = 'charging for package '.$this->Number().' (ID='.$this->ID.')';
	    $out .= $txtLog;

	    $arEvent = array(
	      'descr'	=> $txtLog,
	      'where'	=> __METHOD__,
	      'code'	=> '+PCH');
	    $this->StartEvent($arEvent);

	    $cntLines = 0;	// for logging only
	    $cntQty = 0;	// for logging only
	    while ($objLines->NextRow()) {
		$idRow = $objLines->KeyValue();
		$objItem = $objLines->ItemRecord();	// item data
		// prices in package line data override catalog prices
		//$dlrSale = $objItem->PriceSell;	// sale price
		//$objShCo = $objItem->ShipCostObj();	// shipping cost data
		//$dlrItmSh = $objShCo->PerItem;
		//$dlrPkgSh = $objShCo->PerPkg;
		$dlrSale = $objLines->PriceSell();	// sale price
		$dlrItmSh = $objLines->ShPerItm();
		$dlrPkgSh = $objLines->ShPerPkg();
		$qtyShp = $objLines->QtyShipped();

		$dlrSaleLine = $dlrSale * $qtyShp;
		$dlrItmShLine = $dlrItmSh * $qtyShp;

		$dlrSaleTot += $dlrSaleLine;
		$dlrItmShTot += $dlrItmShLine;
		if ($dlrPkgShTot < $dlrPkgSh) {
		    $dlrPkgShTot = $dlrPkgSh;
		}
		// record the charges applied for this line:
		$arUpd = array(
		  'CostSale' => $dlrSale,
		  'CostShItm' => $dlrItmSh);
		$objLines->Update($arUpd);

		// stats for logging
		$cntLines++;
		$cntQty += $qtyShp;
	    }
	    // create transaction records:
	    if ($cntQty == 1) {
		$txtItemShp = 'cost of item being shipped';
	    } else {
		$txtItemShp = 'total cost of items being shipped';
	    }
	    $this->AddTrx(KI_ORD_TXTYPE_ITEM_SOLD,$txtItemShp,$dlrSaleTot);
	    $this->AddTrx(KI_ORD_TXTYPE_PERITM_SH,'per-item shipping total',$dlrItmShTot);
	    $this->AddTrx(KI_ORD_TXTYPE_PERPKG_SH,'per-package shipping',$dlrPkgShTot);

	    // update package record:
	    $arUpd = array(
	      'ChgShipItm'	=> $dlrItmShTot,
	      'ChgShipPkg'	=> $dlrPkgShTot,
	      'WhenFinished' => 'NOW()');
	    $this->Update($arUpd);

	    // log completion of event:
	    $txtLog = 'sold: '
		.$cntLines.Pluralize($cntLines,' line').', '
		.$cntQty.Pluralize($cntQty,' item').': '
		.'$'.$dlrItmShTot.' itm s/h, $'.$dlrPkgShTot.' pkg s/h';
	    $out .= '<br>'.$txtLog;
	    $arUpd = array('descrfin' => $txtLog);
	    $this->FinishEvent($arUpd);
	} else {
	    $out = 'No items to charge!';
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-05-29 extracted from AdminPage(); will probably need some debugging
    */
    private function AdminUnCharge() {
	$out = '<h3>Removing charges...</h3>';

	$txtLog = 'removing charges for package '.$this->Number().' (ID='.$this->ID.')';
	$out .= $txtLog;

	$arEvent = array(
	  'descr'	=> $txtLog,
	  'where'	=> __METHOD__,
	  'code'	=> '-PCH');
	$this->StartEvent($arEvent);

	$arUpd = array('WhenVoid' => 'NOW()');
	$objTrx = $this->OrderTrxactTable();
	$objTrx->Update($arUpd,'ID_Package='.$this->KeyValue());

	$arUpd = array('WhenFinished' => 'NULL');
	$this->Update($arUpd);

	$this->FinishEvent();
	return $out;
    }
    /*----
      ACTION: Do any immediate-result actions
    */
    protected function Admin_DoActions() {
	$oPage = $this->Page();
	$oPath = $oPage->PathObj();
	$doAct = $oPath->GetText('do');

	$this->DoFetch(FALSE);
	$this->DoReplace(FALSE);

	$arPage = array(
	  'do'		=> FALSE,
	  'edit'	=> FALSE,
	  );
	switch($doAct) {
	  case 'void':		// void this package
	    $this->DoVoid();
	    $this->AdminRedirect($arPage);
	    break;
	  case 'pack':			// get (more) items from stock
	    $this->DoFetch(TRUE);	// trigger stock-fetch form
	    break;
	  case 'charge':	// create transactions for package contents
	    // TODO
	    break;
	  case 'uncharge':	// remove transactions for this package
	    // TODO
	    break;
	  case 'replace':	// put items back in stock
	    $this->DoReplace(TRUE);	// trigger stock-replace form
	    break;
	}
    }
    protected function Render_DoVoid() {
	if ($this->IsVoid()) {
	    $out = NULL;
	} else {
	    $arLink['do'] = 'void';
	    $out = ' ['.$this->AdminLink('void now', 'VOID the package (without saving edits)',$arLink).']';
	}
	return $out;
    }
    protected function Render_DoCheckIn() {
	if ($this->IsChecked()) {
	    $out = NULL;
	} else {
	    $arLink['do'] = 'check-in';
	    $out = ' ['.$this->AdminLink('check in','mark the package as checked in (without saving edits)',$arLink).']';
	}
	return $out;
    }
    /*----
      ACTION: Display the administration page for the package currently loaded in the dataset
      HISTORY:
	2011-12-20 fixed editing of new packages
    */
    public function AdminPage() {
	$oPage = $this->Page();
	$oPath = $oPage->PathObj();

	// $oPage->PathArg() can be rewritten as $oPath->Value()
	$isNew = ($oPage->PathArg('id') == 'new');
	$doEdit = $oPage->PathArg('edit');
	$this->Admin_DoActions();

	$doReal = clsHTTP::Request()->GetBool('doReally');	// not a simulation?
	$doStock = clsHTTP::Request()->GetBool('btnStock');	// stock-action button
	$doPackg = clsHTTP::Request()->GetBool('btnPackg');	// package-action button
	$doFetch = $this->DoFetch();
	$doReplace = $this->DoReplace();

	$out = NULL;

	$sMsgs = clsHTTP::DisplayOnReturn();
	if (!is_null($sMsgs)) {
	    $out .= $sMsgs.'<hr>';
	}

	if ($doStock) {
	    $out .= $this->AdminStock_doRequest($doReal);
	}
	if ($doPackg) {
	    $out .= $this->AdminPage_doRequest($doReal);
	}

	$doForm = $isNew || $doEdit;	// show record editing controls
	$doSLookup = $isNew || $doFetch;	// show stock lookup controls

	if ($isNew) {
	    $doEdit = TRUE;	// override: new package must be edited before it can be created

	    $rcOrd = $this->OrderRecord();
	    $sTitle = 'New package for order #'.$rcOrd->Value('Number');
	} else {
	    $id = $this->KeyValue();

	    $sTitle = 'Package '.$id.' - #'.$this->Number();
	}

	$out .= ''
	  // actions in progress should come first
	  . $this->AdminPage_stock_fetch($doSLookup)
	  . $this->AdminPage_stock_replace($doReplace)
	  // then the main record
	  . $this->AdminPage_values_header($doForm,$sTitle,$isNew)
	  . $this->AdminPage_values($doForm,$doEdit)
	  . $this->AdminPage_values_footer($doForm)
	  // then the package contents
	  . $this->AdminPage_Lines();
	return $out;
    }
    protected function AdminPage_Lines() {
	if ($this->IsNew()) {
	    $out = NULL;
	} else {
	    $oPage = $this->Page();
	    $oPath = $oPage->PathObj();

	    $arPath = array();	// not sure if anything is needed here
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option($arPath,KS_ACTION_PKG_LINES_EDIT,NULL,'edit',NULL,'edit the package contents'),
	      new clsActionLink_option($arPath,KS_ACTION_PKG_LINES_ADD,NULL,'add',NULL,'add package lines'),
	      new clsAction_section('action'),
	      new clsActionLink_option($arPath,'pack','do',NULL,NULL,'add items from stock'),
	      );

	    $hasItems = $this->ContainsItems();
	    if ($hasItems) {
		if ($this->HasCharges()) {
		    $arActs[] = new clsActionLink_option($arPath,
		      'uncharge','do',NULL,NULL,'remove charges for this package');
		} else {
		    $arActs[] = new clsActionLink_option($arPath,
		      'charge','do',NULL,NULL,'add charges for this package');
		}
		$arActs[] = new clsActionLink_option($arPath,
		  'replace','do',NULL,NULL,'put items back in stock');
	    }

	    $out = $this->Page()->ActionHeader('Contents',$arActs);

	    $tbl = $this->LineTable();
	    $idPkg = $this->KeyValue();
	    $out .= $tbl->AdminList_forPackage($idPkg);
	    /*
	    $rs = $tbl->GetData('ID_Pkg='.$this->KeyValue());
	    $rs->PackageID($idPkg);	// for new rows
	    $out .= $rs->AdminList();
	    */
	}

	return $out;
    }
    protected function AdminPage_values_header($doForm,$sTitle,$isNew) {
	if ($isNew) {
	    $htFormLead = "<input name=order type=hidden value=$idOrd>";
	    $arActs = array();	// no actions needed
	} else {
	    $htFormLead = NULL;
	    // these options aren't useful for a new record
	    clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	    $arPath = array();	// not sure if anything is needed here
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option($arPath,'edit',NULL,NULL,NULL,'edit the package record'),
	      new clsActionLink_option($arPath,'print',NULL,NULL,NULL,'print a packing slip'),
	      );
	}
	$out = $this->Page()->ActionHeader($sTitle,$arActs)
	  ."\n<form method=post name=".__METHOD__.'>'
	  .$htFormLead
	  ."\n<table>";
	return $out;
    }
    /* 2015-04-21 old version
    protected function AdminPage_values_header($sTitle,$doActs) {
	if ($doActs) {
	    // these options aren't useful for a new record
	    clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	    $arPath = array();	// not sure if anything is needed here
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option($arPath,'edit',NULL,NULL,NULL,'edit the package record'),
	      new clsActionLink_option($arPath,'print',NULL,NULL,NULL,'print a packing slip'),
	      );
	} else {
	    $arActs = array();	// no actions needed
	}
	$out = $this->Page()->ActionHeader($sTitle,$arActs);
	$out .= "\n<table>";
	return $out;
    }*/
    protected function AdminPage_values_footer($doForm) {
	$out = NULL;

	if ($doForm) {
	    $out .= <<<__END__
<tr>
  <td align=center colspan=2>
    <input type=submit name="btnPackg" value="Save">
  </td>
</tr>
__END__;
	}

	$out .= "\n</form>\n</table>";
	return $out;
    }
    /*----
      ACTION: display the values for the current package record
      RETURNS: rendered display of package record data
      NOTE: Description copied from old version; is *probably* correct...
    */
    protected function AdminPage_values($doForm,$doEdit) {
	$isNew = $this->IsNew();

	// Set up rendering objects
	$frmEdit = $this->PageForm();
	if ($isNew) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);

	if ($doForm) {
	    $idOrd = $this->Value('ID_Order');
	    $htShip = $this->ShipmentTable()->GetActive('WhenCreated DESC')->DropDown(NULL,$this->ShipID());
	    $arLink = array(
	      'edit'	=> FALSE,
	      'add'	=> FALSE,
	      'do'	=> FALSE,
	      'order'	=> FALSE
	      );
	} else {
	    $idShip = $this->ShipID();
	    $arArgs['id'] = $idShip;
	    if (is_null($idShip)) {
		$htShip = '<i>N/A</i>';
	    } else {
		$htShip = $rcShip->AdminLink_name();
	    }
	}

	if ($doEdit) {
	    $ctrlVoidNow = $this->Render_DoVoid();
	    $ctrlCheckIn = $this->Render_DoCheckIn();
	} else {
	    $ctrlVoidNow = NULL;
	    $ctrlCheckIn = NULL;
	}

	// customize the form data:
	$arCtrls['!shipment'] = $htShip;
	$arCtrls['!order'] = $this->OrderRecord()->AdminLink_name();
	$arCtrls['!VoidNow'] = $ctrlVoidNow;
	$arCtrls['!CheckIn'] = $ctrlCheckIn;

	$oTplt->VariableValues($arCtrls);
	$out = $oTplt->Render();

	if ($doForm) {	// form buttons
	    $htBtnReset = $isNew?NULL:'<input type=reset value="Reset">';
	    $out .= "\n"
	      .'<tr><td colspan=2><input type=submit name="btnSaveItems" value="Save">'
	      .$htBtnReset
	      .'</td></tr>'
	      ."\n</form>";
	}
	return $out;
    }
    /*----
      ACTION: display the values for the current package record
      RETURNS: rendered display of package record data
    */
    protected function AdminPage_values_OLD($doForm,$doEdit) {
	$out = NULL;

	if ($doForm) {
	    $idOrd = $this->Value('ID_Order');
	    $htShip = $this->ShipmentTable()->GetActive('WhenCreated DESC')->DropDown(NULL,$this->ShipID());
	    $arLink = array(
	      'edit'	=> FALSE,
	      'add'	=> FALSE,
	      'do'	=> FALSE,
	      'order'	=> FALSE
	      );
	    //$htPath = $vgPage->SelfURL($arLink);
	    //$out .= "\n<form method=post action=\"$htPath\">";
	    $out .= "\n<form method=post name=".__METHOD__.'>';
	    if ($this->IsNew()) {
		$out .= "<input name=order type=hidden value=$idOrd>";
	    }
//	    $arArgs['id'] = 'new';	// 2014-04-10 is this right?
	} else {
	    //$idShip = $rcShip->KeyValue();
	    $idShip = $this->ShipID();
	    $arArgs['id'] = $idShip;
	    if (is_null($idShip)) {
		$htShip = '<i>N/A</i>';
	    } else {
		$htShip = $rcShip->AdminLink_name();
	    }
	}

	$htOrder = $this->OrderRecord()->AdminLink_name();
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>$htOrder</td></tr>";
	if (!$this->IsNew()) {
	    // only display these for an existing package

	    $ctrlCheckIn = NULL;
	    $ctrlVoidNow = NULL;

	    if ($doEdit) {
		$objForm = $this->PageForm();
		$ctrlWhenStarted = $objForm->RenderControl('WhenStarted');
		$ctrlWhenFinished = $objForm->RenderControl('WhenFinished');
		$ctrlWhenChecked = $objForm->RenderControl('WhenChecked');
		$ctrlWhenVoided = $objForm->RenderControl('WhenVoided');

		$oPage = $this->Engine()->App()->Page();
		$arLink = $oPage->PathArgs(array('page','id'));

		$ctrlVoidNow = $this->Render_DoVoid();
		$ctrlCheckIn = $this->Render_DoCheckIn();
	    } else {
		$dtWhenStarted = $this->Value('WhenStarted');
		$dtWhenFinished = $this->Value('WhenFinished');
		$dtWhenChecked = $this->Value('WhenChecked');
		$dtWhenVoided = $this->Value('WhenVoided');

		$ctrlWhenStarted = $dtWhenStarted;
		$ctrlWhenFinished = $dtWhenFinished;
		$ctrlWhenChecked = $dtWhenChecked;
		$ctrlWhenVoided = $dtWhenVoided;
	    }

	    $out .= <<<__END__
<tr><td align=right><b>When Started</b>:</td><td>$ctrlWhenStarted</td></tr>
<tr><td align=right><b>When Finished</b>:</td><td>$ctrlWhenFinished</td></tr>
<tr><td align=right><b>When Checked</b>:</td><td>$ctrlWhenChecked$ctrlCheckIn</td></tr>
<tr><td align=right><b>When Voided</b>:</td><td>$ctrlWhenVoided$ctrlVoidNow</td></tr>
__END__;
	}

	// display these for new and existing packages:
	$intShPounds = $this->ShipPounds();
	$fltShOunces = $this->ShipOunces();
	$htNotes = htmlspecialchars($this->ShipNotes());
	$htTrack = htmlspecialchars($this->ShipTracking());
	$dtWhenArrived = $this->WhenArrived();
	if ($doEdit) {
	    $fPage = $this->PageForm();
	    $ctrlChgSale	= '$'.$fPage->RenderControl('ChgItmSale');
	    $ctrlChgShipItm	= '$'.$fPage->RenderControl('ChgShipItm');
	    $ctrlChgShipPkg	= '$'.$fPage->RenderControl('ChgShipPkg');
	    $ctrlCostShp	= '$'.$fPage->RenderControl('ShipCost');
	    $ctrlCostPkg	= '$'.$fPage->RenderControl('PkgCost');
	    $ctrlShPounds	= $fPage->RenderControl('ShipPounds');
	    $ctrlShOunces	= $fPage->RenderControl('ShipOunces');
	    $ctrlShWeight	= "$ctrlShPounds pounds $ctrlShOunces ounces";
	    $ctrlNotes		= $fPage->RenderControl('ShipNotes');
	    $ctrlTrack		= $fPage->RenderControl('ShipTracking');
	    $ctrlWhenArrived	= $fPage->RenderControl('WhenArrived');
	} else {
	    $ctrlChgSale	= $this->Value('ChgItmSale');
	    $ctrlChgShipItm	= $this->Value('ChgShipItm');
	    $ctrlChgShipPkg	= $this->Value('ChgShipPkg');
	    $ctrlCostShp	= $this->Value('ShipCost');
	    $ctrlCostPkg	= $this->Value('PkgCost');
	    $ctrlShWeight	=
	      (is_null($intShPounds)?'':"$intShPounds pounds")
	      .(is_null($fltShOunces)?'':" $fltShOunces ounces");
	    $ctrlNotes		= $htNotes;
	    $ctrlTrack		= $htTrack;
	    $ctrlWhenArrived	= $dtWhenArrived;
	}

	$out .= <<<__END__
<tr><td align=right><b>Sale price</b>:</td><td>$ctrlChgSale</td></tr>
<tr><td align=right><b>Per-item s/h total charged</b>:</td><td>$ctrlChgShipItm</td></tr>
<tr><td align=right><b>Per-pkg s/h amount charged</b>:</td><td>$ctrlChgShipPkg</td></tr>
<tr><td align=right><b>Shipment</b>:</td><td>$htShip</td></tr>
<tr><td align=right><b>Actual shipping cost</b>:</td><td>$ctrlCostShp</td></tr>
<tr><td align=right><b>Actual package cost</b>:</td><td>$ctrlCostPkg</td></tr>
<tr><td align=right><b>Actual shipping weight</b>:</td><td>$ctrlShWeight</td></tr>
<tr><td align=right><b>Delivery tracking #</b>:</td><td>$ctrlTrack</td></tr>
<tr><td align=right><b>When arrived</b>:</td><td>$ctrlWhenArrived</td></tr>
<tr><td colspan=2><b>Notes</b>:<br>$ctrlNotes</td></tr>
__END__;

	return $out;
    }
    protected function AdminPage_stock_fetch($do) {
	$out = NULL;
	if ($do) {
	    $out .=
	      $this->Page()->SectionHeader('Stock Items for Order',NULL,'section-header-sub')
	      . $this->AdminStock('Fetch Stock');
	}
	return $out;
    }
    protected function AdminPage_stock_replace($do) {
	$out = NULL;
	if ($do) {
	    $out .=
	      $this->Page()->SectionHeader('Package Items Received',NULL,'section-header-sub')
	      . $this->AdminReturn();
	}
	return $out;
    }
    /*----
      ACTION: Save edits to package
    */
    protected function AdminPage_doRequest() {
	$this->AdminSave();		// save edit to existing package
    }
    protected function AdminStock_getRequest() {
	return clsHTTP::Request()->GetArray('qty');
    }
    // TODO: move to ACTIONS
    protected function UpdateTotals() {
	$oTot = new clsPackageTotal();
	$rs = $this->LineRecords();
	while ($rs->NextRow()) {
	    $qty = $rs->QtyShipped();
	    $dlrPrice = $rs->CostSale();
	    $dlrPerItm = $rs->ShipPerItem();
	    $dlrPerPkg = $rs->ShipPerPackage();
	    $oTot->Add($qty,$dlrPrice,$dlrPerItm,$dlrPerPkg);
	}
	$sTotSale = clsMoney::Format_withSymbol($oTot->SaleAmt());
	$sPerItem = clsMoney::Format_withSymbol($oTot->PerItemAmt());
	$sPerPkg = clsMoney::Format_withSymbol($oTot->PerPkgAmt());
	$sFinalTotal = clsMoney::Format_withSymbol($oTot->FinalTotal());

	$out = "\n<li>Updating package record...</li>";
	$arUpd = array(
	  'ChgShipItm'	=> SQLValue($oTot->PerItemAmt()),
	  'ChgShipPkg'	=> SQLValue($oTot->PerPkgAmt()),
	  'ChgItmSale'	=> SQLValue($oTot->SaleAmt()),
	  );
	$this->Update($arUpd);
	$sqlExec = $this->sqlExec;

	$out .= <<<__END__
<li>Charges for this package:</li>
<ul>
<li>Total sale: <b>$sTotSale</b></li>
<li>Per item s/h: <b>$sPerItem</b></li>
<li>Per package s/h: <b>$sPerPkg</b></li>
<li>FINAL TOTAL: <b>$sFinalTotal</b></li>
</ul>
<li>done. (SQL: $sqlExec)</li>
__END__;
	return $out;
    }
    /*----
      ACTION: Handle stock-related user requests.
	Currently this only means pulling items from stock.
      HISTORY:
	2014-06-09 Removed attempt at enclosing in a transaction --
	  things are still being written to the db even when
	  the transaction is cancelled.
    */
    protected function AdminStock_doRequest($doReally) {
	$arReq = $this->AdminStock_getRequest();
	$out = "\n<ul class=log>\n<li>Handling stock request:</li>\n<ul>";
	$ok = TRUE;
	$sErr = NULL;	// use this to describe any problem

	if ($doReally) {
	    $sReq = print_r($arReq,TRUE);
	    $arEv = array(
	      'code'	=> '+pack',
	      'descr'	=> 'packing items',
	      'params'	=> $sReq,
	      );
	    $rcSysEv = $this->CreateEvent($arEv);
	}

	// process the request as needed; report what was requested
	$qItems = count($arReq);
	$sPlur = Pluralize($qItems);
	$out .= "\n<li><b>$qItems</b> stock pull$sPlur requested:\n<ul>";

	// for each item in the request...
	foreach ($arReq as $idLCItem => $arBins) {
	    $rcItem = $this->LCatItemTable($idLCItem);
	    $sItem = $rcItem->CatNum();
	    $qtyItem = 0;

	// arBins: same cat item may be found in more than one bin; user specifies how much to fetch from each
	    foreach ($arBins as $idBin => $qty) {
		$qtyItem += $qty;
		$rcBin = $this->BinTable($idBin);
		$sBin = $rcBin->Name();
		$sDescBin = "quantity <b>$qty</b> of item <b>$sItem</b> from bin <b>$sBin</b>";

		if ($doReally) {
		    // set up stock log event object with all the stuff we do know
		    $rcStkEv = $this->StockItemLog()->CreateRemoval($qty,$idBin,clsStkLog::chTypePkg);
		      $rcStkEv->EventID($rcSysEv->KeyValue());
		      $rcStkEv->ItemID($idLCItem);
		      $rcStkEv->OtherContID($this->KeyValue());		// might be NULL (new package)
		      $rcStkEv->DescrBase($sDescBin.': ');
		}

		$out .= "\n<li>$sDescBin.</li>"
		  ."\n<ul>";
		$qInBin = $rcBin->Qty_forItem($idLCItem);
		if ($qInBin < $qty) {
		    // pulling out more than there are -- error
		    $ok = FALSE;
		    $sErr = "\n<li>Bin only has <b>$qInBin</b>; cannot pull <b>$qty</b>. Please modify your request.</li>";
		    $out .= $sErr;
		} else {
		    $s1 = ($qInBin == $qty)?'it':"$qty of them";
		    $s2 = $doReally?(Pluralize($qty,'is','are').' being pulled'):'would be pulled';
		    $out .= "\n<li>Found <b>$qInBin</b> in bin; $s1 $s2.</li>";
		    if ($doReally) {
			$arLog = $rcBin->Remove($qty,$idLCItem,$rcStkEv);	// this will complete the event too
			if (!is_null($arLog)) {
			    $out .= "\n<ul>";
			    foreach ($arLog as $ht) {
				$out .= "\n<li>$ht</li>";
			    }
			    $out .= "\n</ul>";
			} else {
			    $out .= "\nStock removal <b>mysteriously failed</b>.";
			}
		    }
		}
		$out .= "\n</ul>";
	    }
	    $arItem[$idLCItem] = $qtyItem;
	}
	$out .= "\n</ul>";

	if ($this->IsNew()) {
	    $rcPkg = $this->Admin_NewOutgoing($doReally);
	    $idNew = $rcPkg->KeyValue();
	    $nSeq = $rcPkg->Seq();
	    $idPkg = $idNew;
	    if ($doReally) {
		$out .= "\n<li>A new package record (seq #$nSeq, ID=$idPkg) has been created.";
		$this->Values($rcPkg->Values());
	    } else {
		$out .= "\n<li>A new package record (seq #$nSeq) would be created.";
	    }
	} else {
	    $idNew = NULL;
	    $idPkg = $this->KeyValue();
	    if ($doReally) {
		$htPkg = $this->AdminLink();
		$out .= "\n<li>Modifying package $htPkg...";
	    } else {
		$sPkg = '<b>'.$this->Name().'</b> (ID='.$this->KeyValue().')';
		$out .= "\n<li>Package $sPkg would be updated.";
	    }
	}

	foreach ($arItem as $idLCItem => $qty) {
	    // order line is needed for a couple of reasons
	    $rcOLine = $this->OrderRecord()->LineRecord_forItem($idLCItem);

	    $idOLine = $rcOLine->KeyValue();
	    $rcPLine = $this->LineRecord_forItem($idLCItem);	// this can be NULL, even for existing pkgs
	    $rcItem = $this->LCatItemTable($idLCItem);
	    $sItem = $rcItem->CatNum();

	    // look up order item; calculate package charge amounts

	    if (is_null($rcOLine) or ($rcOLine->IsNew())) {
		throw new exception('Line item object not found for ID=['.$idLCItem.'].');
	    }

	    $dlrPrice = $rcOLine->Price();
	    $dlrPerItm = $rcOLine->ShipPerItem();
	    $dlrPerPkg = $rcOLine->ShipPerPackage();
	    $sPrice = clsMoney::Format_withSymbol($dlrPrice);
	    $sPerItm = clsMoney::Format_withSymbol($dlrPerItm);
	    $sPerPkg = clsMoney::Format_withSymbol($dlrPerPkg);
	    $sPkgDescr = "$sPrice + $sPerItm s/h, $sPerPkg min pkg s/h";

	    // create package lines as needed:

	    // prepare the update/insert array (whether or not it will be used):
	    $arPL = array(
	      'ID_Pkg'		=> $idPkg,
	      'ID_OrdLine'	=> $idOLine,
	      'ID_Item'		=> $idLCItem,
	      'QtyShipped'	=> $qty,
	      'CostSale'	=> $dlrPrice,
	      'CostShItm'	=> $dlrPerItm,
	      'CostShPkg'	=> $dlrPerPkg,
	      );

	    $out .= "\n<li>Item $sItem, quantity $qty: ";
	    if (is_null($rcPLine)) {
		if ($doReally) {
		    // set key fields for new record
		    // create the new record
		    $idLine = $this->LineTable()->Insert($arPL);
		    // display results
		    $out .= "package line ID=$idLine added: $sPkgDescr";
		} else {
		    $out .= "package line would be created: $sPkgDescr";
		}
	    } else {
		$out .= 'updating package line ID='.$rcPLine->AdminLink().'... ';
		//$out .= '<pre>'.print_r($arPL,TRUE).'</pre>';
		$idOLine_pkg = $rcPLine->Value('ID_OrdLine');
		$idPLine = $rcPLine->KeyValue();

		if ($idOLine_pkg != $idOLine) {
		    $out .= "(recycled)... ";
		}

		// if line is being recycled, record an event for that:
		if ($doReally) {
		    if ($idOLine_pkg != $idOLine) {
			$arEv = array(
			  'code'	=> 'recyc',
			  'descr'	=> 'recycling a package line',
			  'params'	=> "oline ID - old: [$idOLine_pkg] / new: [$idOLine]",
			  );
			  $rcSysEv_rcy = $rcPLine->CreateEvent($arEv);
		    } else {
			$rcSysEv_rcy = NULL;
		    }
		    // update the record
		    $rcPLine->Update($arPL);
		    // display the results
		    $out .= ' updated.';
		} else {
		    $out .= ' package line would be updated';
		}
	    }
	}

	$out .= "\n</ul>";

	if ($doReally) {
	    // If we're writing data, then package record has already been created.
	    $out .= $this->UpdateTotals();
	} else {
	    if ($this->IsNew()) {
		$out .= "\n<li>The new package record";
	    } else {
		$sPkg = '<b>'.$this->Name().'</b> (ID='.$this->KeyValue().')';
		$out .= "\n<li>Package $sPkg";
	    }
	    $out .= ' would be updated with the above information.</li>';
	}

	$out .= "\n<li>Processing complete.</li>";

	if ($doReally) {
	    if ($ok) {
		//$this->Engine()->TransactionSave();
		$sStat = 'All changes were successful.';
	    } else {
		//$this->Engine()->TransactionKill();
		$sStat = 'There was an error.';
	    }
	    $out .= "\n<li>$sStat.</li>";
	    $arEv = array(
	      'descrfin'	=> $sStat
	      );
	    $rcSysEv->Finish($arEv);
	} else {
	    if ($ok) {
		$out .= "\n<li>Changes would be saved to database.</li>";
	    } else {
		$out .= "\n<li>Changes would <b>not</b> be saved to database.</li>";
	    }
	}
	$htErr = $ok?NULL:($oPage->Skin()->ErrorMessage($sErr));
	$out .= "\n</ul>$htErr<br clear=left>";	// kluge so title doesn't wrap to the right
	if (!is_null($idNew)) {
	    // redirect to new package's page
	    $rcNew = $this->Table()->GetItem($idNew);
	    $rcNew->AdminRedirect(NULL,$out);
	}
	return $out;
    }
    protected function Admin_NewRecord($doReally,$arArgs) {
	$nSeq = $this->OrderRecord()->NextPackageSeq();
	$arDefault = array(
	  'ID_Order'	=> $this->OrderID(),
	  'Seq'		=> $nSeq,
	  'WhenStarted'	=> 'NOW()',
	  );

	$arIns = clsArray::Merge($arDefault,$arArgs);

	// create an object to return with the new values
	$rcPkg = $this->Table()->SpawnItem();
	$rcPkg->Values($arIns);
	if ($doReally) {
	    $id = $this->Table()->Insert($arIns);
	    if (empty($id)) {
		echo 'SQL='.$this->Table()->sqlExec
		  .'<br>ERROR: <b>'.$this->Engine()->getError().'</b>'
		  .'<br>INSERT array:<pre>'.print_r($arIns,TRUE).'</pre>'
		  ;
		throw new exception('Package record was not created.');
	    }
	    $rcPkg->KeyValue($id);
	}
	return $rcPkg;
    }
    /*----
      LATER: we'll need a CreateIncoming as well...
    */
    protected function Admin_NewOutgoing($doReally,$arArgs=NULL) {
	$arDefault = array(
	  'isReturn'	=> 'FALSE',
	  );
	$arIns = clsArray::Merge($arDefault,$arArgs);
	$rc = $this->Admin_NewRecord($doReally,$arIns);

	return $rc;
    }
    public function TrxListing($iArgs=NULL) {
	$objRows = $this->TrxactRecords();
	return $objRows->AdminTable($iArgs);
    }
    /*-----
      ACTION: Show what will go into the package, and the existing stock from which it can be filled
      INPUT:
	$sBtnText = text for button to add stock to package
	  If NULL, no form is displayed.
    */
    protected function AdminStock($sBtnText=NULL) {
	// collect data to display:
/*	$rcOrd = $this->OrderRecord();
	$arOrd = $rcOrd->QtysOrdered();
	$arSums = $rcOrd->PackageRecords(FALSE)->FigureTotals();
*/
	$arNeed = $this->FigureNeeded();

	$doForm = !is_null($sBtnText);
	if ($doForm) {
	    $out = "\n<form method=post name=".__METHOD__.'>';
	} else {
	    $out = NULL;
	}
	//$out .= 'Sums:<pre>'.print_r($arSums,TRUE).'</pre>';
	$out .= "\n<table class=listing>";

	if (is_array($arNeed)) {
	    $arReq = $this->AdminStock_getRequest(); // for re-displaying entered quantities
	    $tblStk = $this->StockItemTable();

/* This is what a regular package listing would show, not what we need for "stock to fill ordered items". Save in case needed.
	    $out = '<tr><td colspan=3></td><th colspan=5>Quantities</th></tr>';
	    $out .= '<tr><th>Cat #</th><th>Description</th><th>ord</th><th>shp</th><th>ext</th><th>n/a</th><th>Xed</th></tr>';
*/
/* This is accurate, but I'm trying a more descriptive approach, since there's plenty of line space...
	    $out = '<tr><th>Cat #</th><th>Description</th><th>qty ord</th></tr>';
	    $out .= '<tr><td></td><th><i>bin</i></th><th><i>has qty</i></th><th><i>use qty</i></tr>';
*/
	    $qtyNeedTotal = 0;
	    foreach ($arNeed as $id => $qty) {
		$rcItem = $this->LCatItemTable($id);
		$qtyNeed = $arNeed[$id];
		$ouLine =
		  '<td>'.$rcItem->AdminLink($rcItem->CatNum()).'</b></td>'.
		  '<td>'.$rcItem->StoreLink_HT($rcItem->FullDescr()).'</td>'.
		  '<td align=right><b>'.$qtyNeed.'</b> needed</td>';

		$out .= "\n<tr>".$ouLine.'</tr>';
		// find stock lines for this item
		if ($qtyNeed > 0) {
		    $qtyNeedTotal += $qtyNeed;
		    $rsStk = $tblStk->List_ForItem($id);
		    if ($rsStk->HasRows()) {
			// compile list of bins/qtys for each item
			$arBinStk = NULL;
			while ($rsStk->NextRow()) {
			    $idBin = $rsStk->BinID();
			    clsArray::NzSum($arBinStk,$idBin,$rsStk->QtyForShip());
			}
			foreach ($arBinStk as $idBin => $qty) {
			    $rcBin = $this->BinTable($idBin);
			    $htStk = $rcBin->AdminLink_name().' ('.$rcBin->PlaceName().')';
			    $htQtyFnd = $qty;
			    if (count($arReq) > 0) {
				$htQtyReq = $arReq[$id][$idBin];
			    } else {
				$htQtyReq = NULL;
			    }
			    $out .= <<<__END__
  <tr>
    <td></td>
    <td>$htStk</td>
    <td align=right>has <b>$htQtyFnd</b></i></td>
    <td>&ndash; use <input name="qty[$id][$idBin]" value="$htQtyReq" size=2> from here</td>
  </tr>
__END__;
			}
		    } else {
			$out .= <<<__END__
  <tr>
    <td></td><td><i>No stock found</i></td>
  </tr>
__END__;
		    }
		}
	    }

	    $htBtns = NULL;
	    //$doForm = $doNew || $doFetch || $doEdit;
	    if ($doForm) {
//		if ($doNew || $doFetch) {
//		    if ($doFetch) {
//			$txtAdd = 'Fetch Items';
//		    } else {
//			$txtAdd = 'Create Package';
//		    }
		if ($qtyNeedTotal > 0) {
		    $out .= <<<__END__
  <tr>
    <td>
      <span title="actually make changes; unchecked means just simulate">
	<input type=checkbox name="doReally">do it for real
      </span>
    </td>
    <td align=right colspan=3>
      <input type=submit name="btnStock" value="$sBtnText" />
    </td>
  </tr>
__END__;
//		}
		}
		$out .= '</form>';
	    }

	    $out .= "\n</table>";	// TODO: this is in the wrong order; table should close before form
	} else {
	    $out = 'No items in the order.';
	}
	return $out;
    }
    /*----
      INPUT:
	$arQtys[idPL]: quantity to remove from package line ID #idPL
	$arBins[idPL]: ID of bin into which items should be moved from package line ID #idPL
    */
    protected function AdminReturn_do(array $arQtys,array $arBins) {
	$idPkg = $this->KeyValue();
	$sPkg = $this->Name();
	$sReq = print_r($_REQUEST,TRUE);
	$arEv = array(
	  'code'	=> '-pack',
	  'descr'	=> "removing from package $sPkg (ID=$idPkg)",
	  'params'	=> $sReq,
	  );
	$rcSysEv = $this->CreateEvent($arEv);

	$out = NULL;
	$ok = TRUE;
	foreach ($arQtys as $idPL => $qty) {
	    $rcPL = $this->LineTable($idPL);

	    // check for package underflow
	    $qInPkg = $rcPL->QtyInPackage();
	    if ($qInPkg < $qty) {
		$out .= "\n<li>Package only has <b>$qInPkg</b>; cannot return <b>$qty</b>. Please modify your request.</li>";
		$ok = FALSE;
	    }

	    $rcItem = $rcPL->ItemRecord();
	    $idItem = $rcPL->KeyValue();
	    $sItem = $rcItem->CatNum();

	    $idBin = $arBins[$idPL];
	    $rcBin = $this->BinTable($idBin);
	    $sBin = $rcBin->Name();

	    $sDescEv = "quantity <b>$qty</b> of item <b>$sItem</b> from package <b>$sPkg</b> to bin <b>$sBin</b>";

	    // start a stock log event - we know pretty much all of the parameters except destination stock line
	    $rcStkEv = $this->StockItemLog()->CreateAddition($qty,$idBin,clsStkLog::chTypePkg);
	      $rcStkEv->EventID($rcSysEv->KeyValue());
	      $rcStkEv->ItemID($idItem);
	      $rcStkEv->StockBinID($idBin);
	      $rcStkEv->OtherContID($idPkg);
	      $rcStkEv->OtherLineID($idPL);
	      //$rcStkEv->Write($sDescEv);	// can't save the record until we know Qty(Line)Before

	    // POLICY: better to end up with a low miscount than a high miscount, so remove before add

	    // remove item from package
	    $rcPL->Remove($qty,$rcStkEv);
	    // add item to bin
	    $rcBin->Add($qty,$rcPL->ItemID(),$rcStkEv);

	    // finish the stock log event
	    $rcStkEv->Finish();
	}
	$arEv = array(
	  'error'	=> !$ok,
	  'descrfin'	=> $out,
	  );
	$rcSysEv->Finish($arEv);
    }
    protected function AdminReturn() {
	$arItemQty = clsHTTP::Request()->GetArray('itemQty');
	$arItemBin = clsHTTP::Request()->GetArray('itemBin');
	$arItemBinID = clsHTTP::Request()->GetArray('itemBinID');

	$doReturn = clsHTTP::Request()->GetBool('btnStkReturn');
	if ($doReturn) {
	    // do the return and then redirect
	    $this->AdminReturn_do($arItemQty,$arItemBinID);
	}

	$rsLines = $this->LineRecords();
	$qLines = $rsLines->RowCount();

	if (count($arItemBin) == 0) {
	    $sBtnText = 'Look up bin'.pluralize($qLines);
	    $sBtnName = 'btnStkLookup';
	} else {
	    $sBtnText = 'Return to Stock';
	    $sBtnName = 'btnStkReturn';
	}

	if ($rsLines->HasRows()) {
	    $out ="\n<form method=post name=".__METHOD__.">\n"
		. <<<__END__
  <table class=listing>
    <tr><td colspan=2><th colspan=2>quantity</th></tr>
    <tr><th>ID</th><th>Item</th><th title="shipped">sh</th><th title="quantity to return to stock">rtn</th><th>Bin Code</tr>
__END__;
	    while ($rsLines->NextRow()) {
		$id = $rsLines->KeyValue();
		$htID = $rsLines->AdminLink();
		$rcItem = $rsLines->ItemRecord();
		$htItem = $rcItem->AdminLink_name();
		$qtyShp = $rsLines->QtyShipped();
		$qtyRtn = clsArray::Nz($arItemQty,$id);
		$sBin = clsArray::Nz($arItemBin,$id);

		$htBinFnd = NULL;
		if (!is_null($sBin)) {
		    // look it up to get bin ID
		    $rcBin = $this->BinTable()->Search_byCode($sBin);
		    if ($rcBin->RowCount() == 1) {
			$rcBin->NextRow();	// load first/only row
			$htBinFnd = $rcBin->AdminLink_name();
			$idBin = $rcBin->Keyvalue();
		    }
		}

		if (is_null($htBinFnd)) {
		    // user has not entered a bin yet, or user entered a bin that was not found
		    if (empty($sBin)) {
			$htBinFnd = NULL;
		    } else {
			$htBinFnd = " (&lquo;$sBin&rquo; not found)";
		    }
		    $htEntry = <<<__END__
      <td><input name="itemQty[$id]" size=1 value="$qtyRtn"></td>
      <td><input name="itemBin[$id]" size=8 value="$sBin">$htBinFnd</td>
__END__;
		} else {
		    if (empty($qtyRtn)) {
			$htRtn = "<i>$qtyShp<i>";
			$qtyRtn = $qtyShp;
		    } else {
			$htRtn = $qtyRtn;
		    }
		    $htEntry = <<<__END__
      <td align=right><input name="itemQty[$id]" type=hidden value="$qtyRtn">$htRtn</td>
      <td><input name="itemBinID[$id]" type=hidden value="$idBin">$htBinFnd</td>
__END__;
		}

		$out .= <<<__END__
    <tr>
      <td>$htID</td>
      <td>$htItem</td>
      <td align=right>$qtyShp</td>
$htEntry
    </tr>
__END__;
	    }

	    $out .= <<<__END__
  </table>
  <input type=submit name="$sBtnName" value="$sBtnText">
</form>
__END__;
	} else {
	    $out = 'Package has no contents.';
	}
	return $out;
    }
    /*----
      ACTION: Display the packages listed in the dataset, for inclusion within an admin page
      INPUT: iArgs[]
	descr: description of dataset ("Packages_" e.g. " for this shipment")
	omit: columns to omit from table (currently supported: 'ship', 'ord')
	add: NOT USED
	order: ID of order - needs to be set if you want the ability to add packages
    */
    public function AdminTable(array $iArgs) {
	throw new exception("AdminTable() is deprecated; call AdminRows().");
    }
    // TODO: This is currently never set (was $this->arArgs); figure out what's going on and normalize.
    protected function PageArgs() {
	throw new exception('PageArgs() has been replaced by $this->Table()->ExecArgs().');
    }
    /*----
      NOTE: $arFields is here to maintaing compatibility with new parent function. This could probably
	be rewritten to take advantage of that service, but for now it just implements it from scratch.
    */
    public function AdminRows(array $arFields) {
	$arArgs = $this->Table()->ExecArgs();

	//$doAdd = (nz($iArgs['add']) == 'pkg');
	$strOmit = clsArray::nz($arArgs,'omit');
	$doShip = ($strOmit != 'ship');
	$doOrd =  ($strOmit != 'ord');

// This is needed for the "add package" link
	if (isset($arArgs['order'])) {
	    $idOrder = $arArgs['order'];
	} else {
	    $idOrder = $this->OrderID();
	}

	if ($this->hasRows()) {
	    if ($doShip) {
		$htShip = '<th>Shipment</th>';
	    } else {
		$htShip = NULL;
	    }

	    if ($doOrd) {
		$htOrd = '<th>Order #</th>';
	    } else {
		$htOrd = NULL;
	    }

	    $out = <<<__END__
<table class=sortable>
  <tr>
    <th>ID</th>
    $htOrd
    <th>Seq</th>
    <th>Started</th>
    <th>R?</th>
    <th title="total # of items">qty</th>
    $htShip
    <th>sale $</th>
    <th>chg s/h $</th>
    <th>act s/h $</th>
    <th>notes</th>
  </tr>
__END__;

	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$cssRowStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$cssRowClass = NULL;
		if ($this->IsActive()) {
		    $cssRowClass = 'active';
		} else {
		    //$cssRowStyle .= ' text-decoration:line-through; color: red;';
		    $cssRowClass = 'voided';
		}

		$row = $this->Values();

		$id		= $row['ID'];
		$wtID		= $this->AdminLink();
		$strSeq		= $row['Seq'];
		if ($doOrd) {
		    if ($this->OrderID() == 0) {
			$htOrdVal = '<span class=error>N/A</span>';
		    } else {
			$rcOrd = $this->OrderRecord();
			$htOrdVal = $rcOrd->AdminLink_name();
		    }
		    $htOrdCell = "<td>$htOrdVal</td>";
		}
		$dtWhenStarted	= $row['WhenStarted'];
		$htStatus	= $row['isReturn']?'R':'';
		$htQtyPkg	= $this->ItemQty();
		if ($doShip) {
		    $idShip		= $row['ID_Shipment'];
		    if (is_null($idShip)) {
			$wtShip = '<i>not assigned</i>';
		    } else {
			$objShip	= $this->ShipmentTable($idShip);
			$wtShip		= $objShip->AdminLink($objShip->Abbr);
		    }
		}
		$sSale 		= is_null($row['ChgItmSale'])?'-':clsMoney::BasicFormat($row['ChgItmSale']);
		$strChgSh	= is_null($row['ChgShipItm'])?'-':clsMoney::BasicFormat($row['ChgShipItm']);
		$crChgPkg = $row['ChgShipPkg'];
		if ($crChgPkg != 0) {
		    $strChgSh .= "<i>+$crChgPkg</i>";
		}
		$strActSh	= is_null($row['ShipCost'])?'':clsMoney::BasicFormat($row['ShipCost']);
		$crActPkg = $row['PkgCost'];
		if ($crActPkg != 0) {
		    $strActSh .= "<i>+$crActPkg</i>";
		}

		$strNotes = $row['ShipNotes'];

		if ($doShip) {
		    $htShipCell = "<td>$wtShip</td>";
		} else {
		    $htShipCell = NULL;
		}

		$out .= <<<__END__
  <tr style="$cssRowStyle" class="$cssRowClass">
    <td>$wtID</td>
    $htOrdCell
    <td>$strSeq</td>
    <td>$dtWhenStarted</td>
    <td>$htStatus</td>
    <td>$htQtyPkg</td>
    $htShipCell
    <td>$sSale</td>
    <td>$strChgSh</td>
    <td>$strActSh</td>
    <td>$strNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	    $strAdd = 'Add a new package';
	} else {
	    $strDescr = nz($arArgs['descr']);
	    $out = "\nNo packages".$strDescr.'. ';
	    $strAdd = 'Create one';
	}
	if (!empty($idOrder)) {
	    // if Order ID is known, it may be useful to be able to create a package here:
//	    $out = "'''Internal error''': order ID is not being set in ".__METHOD__;
//	} else {
	    $arLink = array(
	      'page'		=> 'pkg',
	      KS_PAGE_KEY_ORDER	=> $idOrder,
	      'id'		=> 'new',
	      'show'		=> FALSE
	      );
	    $oPage = $this->Engine()->App()->Page();
	    $url = $oPage->SelfURL($arLink);
	    $out .= clsHTML::BuildLink($url,$strAdd,'create a new package');
	}
	return $out;
    }

    // -- ADMIN UI -- //
    // ++ CALCULATIONS ++ //

    protected function FigureNeeded() {
	$rcOrd = $this->OrderRecord();
	$arNeed = $rcOrd->QtysOrdered();
	$arSums = $rcOrd->PackageRecords(FALSE)->FigureTotals();

	foreach ($arNeed as $id => $qty) {
	  // subtract out any quantities already shipped, cancelled, or not available
	    if (is_array($arSums) && array_key_exists($id,$arSums)) {
		$arInfo = $arSums[$id];
		$qtyDone = $arInfo['qty-shp'] + $arInfo['qty-kld'] + $arInfo['qty-na'];
		$arNeed[$id] -= $qtyDone;
	    }
	}

	return $arNeed;
    }
    /*-----
      ACTION: Add up totals for the packages in the dataset.
      RETURNS: Array containing stats for each item in the order
      FORMAT: See AddToSum()
      PURPOSE: Figures out the status of each item in an order (how many remaining to ship, etc.)
    */
    public function FigureTotals() {
	$arSum = NULL;
	if ($this->HasRows()) {
	    $intNew = 0;
	    $intUpd = 0;
	    while ($this->NextRow()) {
		$this->AddToSum($arSum);
	    }
	    return $arSum;
	} else {
	    return NULL;
	}
    }
    /*-----
      ACTION: Adds this package's shipment stats to the $arSum array
      FORMAT: $arSum[item ID][qty-shp|qty-rtn|qty-kld|qty-na]
    */
    private function AddToSum(&$arSum) {
	$objRows = $this->LineRecords();
	if ($objRows->HasRows()) {
	    while ($objRows->NextRow()) {
		$objRows->AddToSum($arSum);
	    }
	}
    }

    // -- CALCULATIONS -- //

}
