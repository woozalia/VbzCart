<?php
/*
  FILE: pkg.php -- standalone/dropin package administration for VbzCart
  HISTORY:
    2010-10-15 Extracted package classes from SpecialVbzAdmin.php
    2013-12-15 Adapting for drop-in system
    2014-04-20 Extracted package-line classes to pkg-line.php
    2017-01-06 partially updated
*/

define('KS_ACTION_PKG_LINES_EDIT','edit.lines');
define('KS_ACTION_PKG_LINES_ADD','add.lines');

class clsPackages extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ord_pkgs';
    }
    // CEMENT
    protected function SingularName() {
	return 'clsPackage';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_PACKAGE;
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

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
	throw new exception('2016-03-06 Does anything actually call this?');
	if (!is_null($arArgs)) {
	    $this->arExec = $arArgs;
	}
	return $this->arExec;
    }

    // -- DROP-IN API -- //
    // ++ RECORDS ++ //

    // TODO: Rename to OrderRecord()
    public function GetOrder($iID) {
	$rsPkgs = $this->GetData('ID_Order='.$iID);
	$rsPkgs->SetOrderID($iID);	// make sure this is set, regardless of whether there is data
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

    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //

    /*----
      RETURNS: The next Seq number to be used for a Package in the given Order
    */
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
	$htSrchSt = '"'.fcString::EncodeForHTML($sSrchSt).'"';
	$htSrchFi = '"'.fcString::EncodeForHTML($sSrchFi).'"';
	$htSort = '"'.fcString::EncodeForHTML($sSort).'"';

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
class clsPackage extends vcAdminRecordset {
    use ftFrameworkAccess;

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
	    $this->SetOrderID($idOrd);
	}

/*
	if ($oPath->KeyExists(KS_PAGE_KEY_ORDER)) {
	    $idOrd = $oPath->Value(KS_PAGE_KEY_ORDER);
	    $this->SetOrderID($idOrd);
	}
	*/
    }

    // -- SETUP -- //
    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name($iPopup=NULL,array $iarArgs=NULL) {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $txt = $this->Number();
	    return $this->SelfLink($txt,$iPopup,$iarArgs);
	}
    }

    // -- TRAIT HELPERS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //

    protected function LCatItemsClass() {
	if (fcDropinManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return KS_LOGIC_CLASS_LC_ITEMS;
	}
    }
    protected function StockItemsClass() {
	if (fcDropinManager::IsModuleLoaded('vbz.stock')) {
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
	if (fcDropinManager::IsModuleLoaded('vbz.stock')) {
	    return KS_CLASS_STOCK_BINS;
	} else {
	    return NULL;	// class not available
	    // TODO: handle this gracefully
	}
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

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

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      RETURNS: record object for Order to which this Package belongs
      TODO: 2016-09-11 This probably could be simplified. We can assume the Order ID will not change during a given page-load.
    */
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
      RETURNS: Record for the Shipment to which this Package is assigned, or NULL if not assigned
    */
    private $rcShip;
    protected function ShipmentRecord() {
	$doLoad = TRUE;
	if ($this->HasShipment()) {
	    $idShip = $this->Value('ID_Shipment');
	    if (isset($this->rcShip)) {
		$doLoad = ($idShip != $this->rcShip->KeyValue());
	    }
	    if ($doLoad) {
		$this->rcShip = $this->ShipmentTable($idShip);
	    }
	} else {
	    $this->rcShip = NULL;
	}
	return $this->rcShip;
    }
    /*----
      RETURNS: recordset of available Shipments
	This isn't specific to this Package.
    */
    protected function ShipmentRecords() {
	return $this->ShipmentTable()->ActiveRecords('WhenCreated DESC');
    }
    /*----
      RETURNS: recordset of transaction lines for this package
    */
    private $rsTrx;
    protected function TransactionRecords() {
	$id = $this->GetKeyValue();
	if (empty($this->rsTrx)) {
	    $tbl = $this->TrxactTable();
	    $rs = $tbl->GetData('ID_Package='.$id,NULL,'WhenDone, ID');
	    $this->rsTrx = $rs;
	}
	return $this->rsTrx;
    }
    /*----
      RETURNS: LINE subdata recordset
    */
    public function LinesData($iFilt=NULL) {
	throw new exception('LinesData() is deprecated; call LineRecords(). Also, does this really need to be public?');
    }
    protected function LineRecords($iFilt=NULL) {
	$objTbl = $this->LineTable();
	$sqlFilt = 'ID_Pkg='.$this->GetKeyValue();
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
	$sqlFiltUnused = "(IFNULL(QtyShipped,0)=0) AND (IFNULL(QtyReturned,0)=0) ORDER BY ID LIMIT 1";
	$idPkg = $this->GetKeyValue();

	// first, look only at inactive lines already assigned to this package
	$sqlFilt = "(ID_Pkg=$idPkg) AND $sqlFiltUnused";
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
	    $idPkg = $this->GetKeyValue();
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

    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //

    // PUBLIC so Package Table can call it
    public function SetOrderID($id) {
	$this->SetValue('ID_Order',$id);
    }
    // PUBLIC so Transaction can access it
    public function GetOrderID() {
	return $this->GetValue('ID_Order');
    }
    // RETURNS: total charged for sale of all items
    protected function Charge_forItemSale($n=NULL) {
	return $this->Value('ChgItmSale',$n);
    }
    // RETURNS: total charged for per-item s/h
    protected function Charge_forShipping_Items($n=NULL) {
	return $this->Value('ChgShipItm',$n);
    }
    // RETURNS: amount charged for package s/h
    protected function Charge_forShipping_Package($n=NULL) {
	return $this->Value('ChgShipPkg',$n);
    }
    // RETURNS: actual cost of postage
    protected function Cost_forShipping_Postage() {
	return $this->Value('ShipCost');
    }
    // RETURNS: actual (estimated) cost of packaging/handling
    protected function Cost_forShipping_Package() {
	return $this->Value('PkgCost');
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
    public function WhenStarted() {
	return $this->Value('WhenStarted');
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
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function HasWeight() {
	return !is_null($this->ShipPounds()) || !is_null($this->ShipOunces());
    }
    protected function ShipID() {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    return $this->Value('ID_Shipment');
	}
    }
    protected function HasShipment() {
	return (!is_null($this->ShipID()));
    }
    protected function ShipmentLink() {
	if ($this->HasShipment()) {
	    $rcShip = $this->ShipmentRecord();
	    $out = $rcShip->SelfLink_name();
	} else {
	    $out = 'shpmt n/a';
	}
	return $out;
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
    public function NameString() {	// alias - preferred
	return $this->Number();
    }
    // RETURNS: total charged for sale of items
    protected function Charge_forItemSale_html() {
	$val = $this->Charge_forItemSale();
	return is_null($val)
	  ?'-'
	  :clsMoney::Format_withSymbol($val)
	  ;
    }
    /* // RETURNS: total charged for per-item shipping, HTML format
    protected function Charge_forShippingItem_html() {
	$val = $this->Value('ChgShipItm');
	return is_null($val)
	  ?'-'
	  :clsMoney::Format_withSymbol($val);
    }
    // RETURNS: per-package shipping charge, HTML format
    protected function Charge_forShippingPackage_html() {
	$val = $this->Value('ChgShipPkg');
	return is_null($val)
	  ?'-'
	  :clsMoney::Format_withSymbol($val);
	if (!empty($dlrChgPkg)) {
	    $sChgSh .= "<i>+$crChgPkg</i>";
	}
	$strActSh	= is_null($row['ShipCost'])?'':clsMoney::Format_withSymbol($row['ShipCost']);
    }//*/
    // RETURNS: All charges for shipping, HTML format
    protected function Charges_forShipping_html() {
	$dlrItm = $this->Charge_forShipping_Items();
	$dlrPkg = $this->Charge_forShipping_Package();

	$sItm = is_null($dlrItm)?'':fcMoney::Format_withSymbol($dlrItm).'i';
	$sPkg = is_null($dlrPkg)?'':fcMoney::Format_withSymbol($dlrPkg).'p';
	
	$out = fcString::ConcatArray('+',array($sItm,$sPkg));
	return $out;
    }
    protected function Costs_forShipping_html() {
	$dlrPost = $this->Cost_forShipping_Postage();
	$dlrPack = $this->Cost_forShipping_Package();

	$sPst = is_null($dlrPost)?'':fcMoney::Format_withSymbol($dlrPost).'po';
	$sPkg = is_null($dlrPack)?'':fcMoney::Format_withSymbol($dlrPack).'pk';
	
	$out = fcString::ConcatArray('+',array($sPst,$sPkg));
	return $out;
    }

    // -- FIELD CALCULATIONS -- //
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
	$rs = $this->LineRecords();
	if ($rs->HasRows()) {
	    $qty = 0;
	    while ($rs->NextRow()) {
		$qty += $rs->QtyInPackage();
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
    /*-----
      ACTION: Add up totals for the packages in the dataset.
      RETURNS: Array containing stats for each item in the order
      FORMAT: See AddToSum()
      PURPOSE: Figures out the status of each item in an order (how many remaining to ship, etc.)
      HISTORY:
	2016-08-13 Moved from Package Table class to Package Records class (how did it even get in Table class?).
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
      HISTORY:
	2016-08-13 Moved from Package Table class to Package Records class (how did it even get in Table class?).
    */
    private function AddToSum(&$arSum) {
	$rs = $this->LineRecords();
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$rs->AddToSum($arSum);
	    }
	}
    }
    /*-----
      HISTORY:
	2016-08-13 Moved from Package Table class to Package Records class (how did it even get in Table class?).
    */
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
	  'ID_Pkg'	=> $this->GetKeyValue(),
	  'ID_Item'	=> $iItem,
	  'ID_OrdLine'	=> $iOLine
	  );
	$tLines->Insert($arIns);
	return $tLines->LastID();
    }

    /*----
      ACTION: Add a transaction record for this package
      PUBLIC so Package Line can call it
    */
    public function AddTrx($sType,$sDescr,$nAmt) {
	$arIns = array(
	  'ID_Order'	=> $this->Value('ID_Order'),
	  'ID_Package'	=> $this->GetKeyValue(),
	  'ID_Type'	=> $sType,
	  'WhenDone'	=> 'NOW()',
	  'Descr'	=> $this->Engine()->SanitizeAndQuote($sDescr),
	  'Amount'	=> $nAmt);
	$this->OrderTrxactTable()->Insert($arIns);
    }
    /*----
      ACTION: Adds transactions for this package (contents + s/h)
    */
    protected function DoCharges() {
	$rsLines = $this->LineRecords();
	$arTrx = $rsLines->AddItemCharges();
	$out = NULL;
	
	if (count($arTrx) > 0) { 
	    
	    // calculate and create transactions for package
	    
	    foreach ($arTrx as $rcTrx) {
		$out .= $rcTrx->Description();
		$rcTrx->SetPackage($this);
		$rcTrx->Save();

	    // update the package record with totals
		$nAmt = $rcTrx->Amount();
		switch ($rcTrx->TypeID()) {
		  case vcraOrderTrxType::ITEM:
		    $this->Charge_forItemSale($nAmt);
		    break;
		  case vcraOrderTrxType::SH_EA:
		    $this->Charge_forShipping_Items($nAmt);
		    break;
		  case vcraOrderTrxType::SH_PK:
		    $this->Charge_forShipping_Package($nAmt);
		    break;
		}
	    }
	    // save updated Package fields:
	    $this->Save();
	} else {
	    $out = 'Cannot charge - no items in package.';
	}
		
	return $out;
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
    /*----
      ACTION: Check this Package into the given Shipment
	Sets WhenChecked, ID_Shipment
    */
    protected function CheckInShipment($idShip) {
	$arChg = array(
	  'WhenChecked'	=> 'NOW()',
	  'ID_Shipment' => $idShip,
	  );
	$this->Update($arChg);
    }

    // -- ACTIONS -- //
    // ++ ADMIN UI ++ //
    
      //++pieces++//

    /*----
      HISTORY:
	2011-01-02 Adapted from VbzAdminDept::DropDown to VbzAdminOrderTrxType
	  Control name now defaults to table action key
	2011-03-30 Adapted from VbzAdminOrderTrxType to clsPackage
	2016-08-13 This function is probably obsolete, but I won't know for sure
	  until some other functions that use it are rewritten.
    */
    public function DropDown_for_data($iName=NULL,$iDefault=NULL,$iNone=NULL,$iAccessFx='Number') {
	$strName = is_null($iName)?($this->Table()->ActionKey()):$iName;	// control name defaults to action key
	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->GetKeyValue();
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
      USED BY Transaction records - for selecting Package record (2016-09-18: not sure this makes sense; what if Trx has no Pkg?)
      TODO: update Transaction record edit form to use Ferreteria forms, then remove this function.
    */
    public function DropDown_ctrl($iName=NULL,$iNone=NULL) {
	$rsAll = $this->Table()->GetData('ID_Order='.$this->Value('ID_Order'),NULL,'Seq');
	return $rsAll->DropDown_for_data($iName,$this->GetKeyValue(),$iNone);
    }
    
      //--pieces--//
      //++options++//
    
    /*----
      SET BY: SingleRecordMenuArray()
    */
    private $doFetch;
    protected function Option_DoFetch($fYes=NULL) {
	if (!is_null($fYes)) {
	    $this->doFetch = $fYes;
	}
	return $this->doFetch;
    }
    /*----
      SET BY: SingleRecordMenuArray()
    */
    private $doReplace;
    protected function Option_DoReplace($fYes=NULL) {
	if (!is_null($fYes)) {
	    $this->doReplace = $fYes;
	}
	return $this->doReplace;
    }
    /*----
      SET BY: Admin_DoActions()
    */
    private $doCheckIn;
    protected function Option_DoCheckIn($fYes=NULL) {
	if (!is_null($fYes)) {
	    $this->doCheckIn = $fYes;
	}
	return $this->doCheckIn;
    }
    /*----
      SET BY: POST data
      TRUE WHEN: The database should be modified, rather than just showing what would happen if it were.
    */
    protected function Option_ReallyAct() {
	return clsHTTP::Request()->GetBool('doReally');
    }
    /*----
      SET BY: AdminCheckInput()
      TRUE WHEN: The stock-handling form has been submitted
      MEANING: There has been a request to move Stock Items between a Stock Bin and a Package
    */
    protected function Option_HandleStock() {
	return clsHTTP::Request()->GetBool('btnStock');
    }
    /*----
      TRUE WHEN: The package-handling form has been submitted,
	i.e the package record is being saved from having been edited directly
      MEANING: can't remember at the moment; to be documented -- but
	probably at least includes when items are unavailable or cancelled.
    */
    protected function Option_HandlePackage() {
	return clsHTTP::Request()->GetBool('btnPackg');
    }
      
      //--options--//
      //++single++//
    
    /*----
      ACTION: Display the administration page for the package currently loaded in the dataset
	This is sort of the "shell" or "glue" method; it calls other methods to do most of the work.
      HISTORY:
	2011-12-20 fixed editing of new packages
    */
    public function AdminPage() {
	$oPage = $this->PageObject();
	$oPath = $oPage->PathObj();

	// $oPage->PathArg() can be rewritten as $oPath->Value()
	//$isNew = ($oPage->PathArg('id') == KS_NEW_REC);
	$isNew = $this->IsNew();
	$doEdit = $oPage->PathArg('edit');
	
	$doReally = $this->Option_ReallyAct();
	$doStock = $this->Option_HandleStock();
	$doPackg = $this->Option_HandlePackage();
	
	// handle user inputs (either act immediately or set options)
	$this->Admin_DoActions();

	$out = NULL;

	// 2016-03-01 This may be redundant now.
	$sMsgs = clsHTTP::DisplayOnReturn();
	if (!is_null($sMsgs)) {
	    $out .= $sMsgs.'<hr>';
	}

	if ($doStock) {
	    $out .= $this->AdminStock_doRequest($doReally);
	}
	if ($doPackg) {
	    $out .= $this->AdminPage_doRequest($doReally);
	}

	$doForm = $isNew || $doEdit;		// show record editing controls
	$doSLookup = $isNew || $this->Option_DoFetch();	// show stock lookup controls

	$oSkin = $this->SkinObject();
	if ($isNew) {
	    $doEdit = TRUE;	// override: new package must be edited before it can be created
	}
	$out .= ''
	  // actions in progress should come first
	  . $this->AdminPage_stock_fetch()
	  . $this->AdminPage_stock_replace()
	  // then the main record
	  . $this->AdminPage_values_header($doForm,$isNew)
	  . $this->AdminPage_values($doForm,$doEdit)
	  . $this->AdminPage_values_footer($doForm)
	  // then the package contents
	  . $this->AdminPage_Lines()
	  . $this->AdminRows_Trxact()
	  ;
	return $out;
    }
    /*----
      HISTORY:
	2011-02-17 Updated to use objForm instead of objFlds/objCtrls
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	    // FORMS v2
	    $oForm = new fcForm_DB($this);
	      $oField = new fcFormField_Time($oForm,'WhenStarted');
		//$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenFinished');
		//$oCtrl = new fcFormControl_HTML($oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenChecked');
		//$oCtrl = new fcFormControl_HTML($oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenVoided');
		//$oCtrl = new fcFormControl_HTML($oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenArrived');
		//$oCtrl = new fcFormControl_HTML($oField,array());
	      $oField = new fcFormField_Num($oForm,'ChgItmSale');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ChgShipItm');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ChgShipPkg');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipCost');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'PkgCost');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_Num($oForm,'ShipPounds');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>2));
	      $oField = new fcFormField_Num($oForm,'ShipOunces');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>3));
	      $oField = new fcFormField_Text($oForm,'ShipNotes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));
	      $oField = new fcFormField_Text($oForm,'ShipTracking');
		//$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));

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
							<td><#!ShipWeight#></tr>
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

	//++single - elements++//

    // TODO: figure out if $doForm should be used
    protected function AdminPage_values_header($doForm,$isNew) {
	$oSkin = $this->SkinObject();
	$htForm = $doForm?("\n<form method=post name=".__METHOD__.'>'):NULL;
	if ($isNew) {
	    $rcOrd = $this->OrderRecord();
	    $sOrd = $rcOrd->Value('Number');
	    $oSkin->SetBrowserTitle('pkg for ord #'.$sOrd);
	    $oSkin->SetPageTitle('New Package for Order #'.$sOrd);

	    $idOrd = $this->GetOrderID();
	    $htForm .= "\n<input name=order type=hidden value=$idOrd>";
	    $arActs = array();	// no actions needed
	} else {
	    $id = $this->GetKeyValue();

	    $idPkg = $this->GetKeyValue();
	    $sPkg = $this->Number();
	    $oSkin->SetBrowserTitle("pkg #$sPkg (id$idPkg)");
	    $oSkin->SetPageTitle("Package #$sPkg (ID #$idPkg)");
	    
	    // these options aren't useful for a new record, so only add them here:
	    $arPath = array();	// not sure if anything is needed here
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option($arPath,'edit',NULL,NULL,NULL,'edit the package record'),
	      new clsActionLink_option($arPath,'print',NULL,NULL,NULL,'print a packing slip'),
	      );
	}
	$this->PageObject()->PageHeaderWidgets($arActs);

//	$out = $this->PageObject()->ActionHeader($sTitle,$arActs)
	$out = $htForm
	  ."\n<table>"
	  ;
	return $out;
    }
    /*----
      ACTION: display the actual data for the current package record
	Should possibly be renamed something like AdminPage_core().
      RETURNS: rendered display of package record data
      TODO: Action links should only show up if user has appropriate privileges.
      NOTE: Description copied from old version; is *probably* correct...
      HISTORY:
	2016-09-04 Changing action links so they only show up in non-edit mode, rather than only in edit mode.
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
	    $htShip = $this->ShipmentTable()->ActiveRecords('WhenCreated DESC')->DropDown(NULL,$this->ShipID());
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
		$rcShip = $this->ShipmentRecord();
		$htShip = $rcShip->SelfLink_name();
	    }
	}
	
	$ctrlWeight = '<#ShipPounds#> pounds <#ShipOunces#> ounces</td>';

	// 2016-09-04 This *was* set to only display action links in edit mode; now reversing that.
	if ($doEdit) {
	    $ctrlVoidNow = NULL;
	    $ctrlCheckIn = NULL;
	} else {
	    $ctrlVoidNow = $this->Render_DoVoid();
	    if ($this->Option_DoCheckIn()) {
		$ctrlCheckIn = $this->Render_ShipmentLinks();
	    } else {
		$ctrlCheckIn = $this->Render_DoCheckIn();
	    }
	    if (!$this->HasWeight()) {
		$ctrlWeight = '-';
	    }
	}

	// customize the form data:
	$arCtrls['!shipment'] = $htShip;
	$arCtrls['!order'] = $this->OrderRecord()->SelfLink_name();
	$arCtrls['!VoidNow'] = $ctrlVoidNow;
	$arCtrls['!CheckIn'] = $ctrlCheckIn;
	$arCtrls['!ShipWeight'] = $ctrlWeight;

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

	$out .= 
	  "\n</table>"
	  ."\n</form>"
	  ;
	return $out;
    }
    
	//--single - elements--//
	//++single - controls++//
	
    /*----
      ACTION:
	* creates the array for the single-record-page header menu
	* sets internal options based on the state of menu items
      RETURNS: menu item array
    */
    protected function SingleRecordMenuArray() {
	$arActs = array(
	  // (array $arData,$sLinkKey,$sGroupKey=NULL,$sDispOff=NULL,$sDispOn=NULL,$sDescr=NULL)
	  new clsActionLink_option(array(),KS_ACTION_PKG_LINES_EDIT,NULL,'edit',NULL,'edit the package contents'),
	  new clsActionLink_option(array(),KS_ACTION_PKG_LINES_ADD,NULL,'add',NULL,'add package lines'),
	  new clsAction_section('action'),
	  $oMnuSend = new clsActionLink_option(array(),'pack','do',NULL,NULL,'add items from stock'),
	  $oMnuRecv = new clsActionLink_option(array(),'unpack','do',NULL,NULL,'return items to stock'),
	  );
	
	// 2016-08-26 There should probably be a better way of doing this.
	$oPage = $this->PageObject();
	$oMnuSend->Page($oPage);
	$oMnuRecv->Page($oPage);
	
	$this->Option_DoFetch($oMnuSend->Selected());
	$this->Option_DoReplace($oMnuRecv->Selected());
	return $arActs;
    }
    protected function Render_DoVoid() {
	if ($this->IsNew()) {
	    // can't VOID a package with no ID
	    $out = NULL;
	} elseif ($this->IsVoid()) {
	    $out = NULL;
	} else {
	    $arLink['do'] = 'void';
	    $out = ' ['.$this->SelfLink('void now', 'VOID the package (without saving edits)',$arLink).']';
	}
	return $out;
    }
    protected function Render_DoCheckIn() {
	if ($this->IsNew()) {
	    $out = NULL;
	} elseif ($this->IsChecked()) {
	    $out = NULL;
	} else {
	    $arLink['do'] = 'check-in';
	    $out = ' ['.$this->SelfLink('check in','mark the package as checked in (without saving edits)',$arLink).']';
	}
	return $out;
    }
    // ACTION: shows available Shipments, with each Shipment as an action-link to check the Package in to that Shipment.
    protected function Render_ShipmentLinks() {
	$rsShip = $this->ShipmentRecords();
	$out = "\nCheck in to: ";
	while ($rsShip->NextRow()) {
	    $sShip = $rsShip->ShortName();
	    $arAct['ship.id'] = $rsShip->GetKeyValue();
	    $htLink = $this->SelfLink($sShip,'assign package to shipment '.$sShip,$arAct);
	    $htAct = "[ $htLink ]";
	    $out .= $htAct;
	}
	return $out;
    }

	//--single - controls--//
	//++single - subsidiaries++//
    
    protected function AdminPage_Lines() {
	if ($this->IsNew()) {
	    $out = NULL;
	} else {
	    $oPage = $this->PageObject();
	    $oPath = $oPage->PathObj();

	    $arPath = array();	// not sure if anything is needed here
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      
	      // 2016-09-19 multi-line editing currently doesn't work, so disabling the menu entry.
	      //new clsActionLink_option($arPath,KS_ACTION_PKG_LINES_EDIT,NULL,'edit',NULL,'edit the package contents'),
	      
	      new clsActionLink_option($arPath,KS_ACTION_PKG_LINES_ADD,NULL,'add',NULL,'add package lines'),	// TODO: should be admins only
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

	    $out = $this->PageObject()->ActionHeader('Contents',$arActs);

	    $tbl = $this->LineTable();
	    $idPkg = $this->GetKeyValue();
	    $out .= $tbl->AdminList_forPackage($idPkg);
	}

	return $out;
    }
    /*----
      NOTES: I originally had this showing only the transactions for the current Package, but that results in (1) an inaccurate balance
	for charging, if there were any non-Package-specific transactions, and (2) not showing payments made against the charges for
	the current Package.
	
	For now, we'll just show all non-void transactions for the Order, same as in the Order admin page (except that shows voided records
	as well). Later, transactions for the current Package can be highlighted, and other transactions can be summarized by default.
      HISTORY:
	2016-09-16 Modified to show all transactions for Order
    */
    protected function AdminRows_Trxact() {
	$id = $this->GetKeyValue();
    
	// display transactions
	//$rs = $this->TransactionRecords();
	$rs = $this->OrderRecord()->TransactionRecords('WhenVoid IS NULL');
/*	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array('pkg'=>$id),'add-trx',	'do','add',	NULL,'add a new transaction for this package'),
	  ); 2016-09-11 implement later */
	$arActs = NULL;
	$out = $this->PageObject()->ActionHeader('Transactions',$arActs);
	$out .= $rs->AdminTable($this);
	return $out;
    }

	//--single - subsidiaries--//
	//++single - actions++//

    protected function AdminPage_stock_fetch() {
	if ($this->Option_DoFetch()) {
	    $out =
	      $this->PageObject()->SectionHeader('Stock Items for Order',NULL,'section-header-sub')
	      . $this->AdminStock();
	} else {
	    $out = NULL;
	}
	return $out;
    }
    protected function AdminPage_stock_replace() {
	$out = NULL;
	if ($this->Option_DoReplace()) {
	    $out .=
	      $this->PageObject()->SectionHeader('Package Items Received',NULL,'section-header-sub')
	      . $this->AdminReturn();
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-05-29 extracted from AdminPage(); will probably need some debugging
	2016-09-04 This will need to be gutted and rewritten to use DoCharges (formerly AddCharges()).
	2016-09-11 Rewriting done (not tested); decided it's not a high priority to log this as an event
	  when the transactions provide a record (especially if they're never deleted but only voided).
    */
    private function AdminDoCharge() {
	$out = '<h3>Adding charges...</h3>';
	
	$out .= $this->DoCharges();

	/* 2016-09-11 old version
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
	*/
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
	$objTrx->Update($arUpd,'ID_Package='.$this->GetKeyValue());

	$arUpd = array('WhenFinished' => 'NULL');
	$this->Update($arUpd);

	$this->FinishEvent();
	return $out;
    }
    /*----
      ACTION: Respond to immediate action requests.
	Actually do the action in some cases (typically: where page needs to be reloaded anyway).
	In others (typically: where a form needs to be displayed), set a flag to indicate what should be done.
    */
    protected function Admin_DoActions() {
	$oPage = $this->PageObject();
	$oPath = $oPage->PathObj();
	$sDo = $oPath->GetText('do');

	$this->Option_DoFetch(FALSE);
	$this->Option_DoReplace(FALSE);

	$arPage = array(
	  'do'		=> FALSE,
	  'edit'	=> FALSE,
	  );
	switch($sDo) {
	  case 'void':		// void this package
	    $this->DoVoid();
	    $this->SelfRedirect($arPage);
	    break;
	  case 'pack':			// get (more) items from stock
	    $this->Option_DoFetch(TRUE);	// trigger stock-fetch form
	    break;
	  case 'charge':	// create transactions for package contents
	    $this->AdminDoCharge();
	    $this->SelfRedirect();
	    break;
	  case 'uncharge':	// remove transactions for this package
	    $this->AdminUnCharge();
	    $this->SelfRedirect();
	    break;
	  case 'replace':	// put items back in stock
	    $this->Option_DoReplace(TRUE);	// trigger stock-replace form
	    break;
	  case 'check-in':
	     $oPage = $this->PageObject();
	     if ($oPage->PathArg_exists('ship.id')) {
		$idShip = $oPage->PathArg('ship.id');
		$this->CheckInShipment($idShip);
		$this->SelfRedirect();
		die();
	    }
	    $this->Option_DoCheckIn(TRUE);
	    break;
	}
    }

	//--single - actions--//

      //--single--//
      //++multiple++//

    /*----
      RETURNS: HTML for viewing multiple Package records
    */
    public function AdminRows(array $arFields = NULL, array $arOptions = NULL) {
	/* 2016-09-19 This belongs in AdminPage_lines(), where it is already duplicated.
	$arActs = $this->AdminMenuSetup();

	$hasItems = $this->ContainsItems();
	$sSfx = $this->Table()->ActionKey();
	$sActDo = 'do.'.$sSfx;
	if ($hasItems) {
	    if ($this->HasCharges()) {
		$arActs[] = new clsActionLink_option($arPath,
		  'uncharge',$sActDo,NULL,NULL,'remove charges for this package');
	    } else {
		$arActs[] = new clsActionLink_option($arPath,
		  'charge',$sActDo,NULL,NULL,'add charges for this package');
	    }
	    $arActs[] = new clsActionLink_option($arPath,
	      'replace',$sActDo,NULL,NULL,'put items back in stock');
	} */
	$arActs = NULL;
	
	$out = $this->PageObject()->ActionHeader('Packages',$arActs);

	$doStock = $this->Option_HandleStock();
	if ($doStock) {
	    $doReally = $this->Option_ReallyAct();
	    $out .= $this->AdminStock_doRequest($doReally);
	}

	return $out
	  . $this->AdminPage_stock_fetch()
	  . $this->AdminPage_stock_replace()
	  .parent::AdminRows($arFields,$arOptions)
	  ;
    }
    // CALLBACK
    public function AdminRow_CSSclass() {
	if ($this->IsVoid()) {
	    return 'state-inactive';
	} else {
	    return parent::AdminRow_CSSclass();
	}
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
    /*----
      TODO:
	* should probably be split into separate methods for UI and logic/action
	* will need some updating in order to work at all
    */
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
	$sTotSale = fcMoney::Format_withSymbol($oTot->SaleAmt());
	$sPerItem = fcMoney::Format_withSymbol($oTot->PerItemAmt());
	$sPerPkg = fcMoney::Format_withSymbol($oTot->PerPkgAmt());
	$sFinalTotal = fcMoney::Format_withSymbol($oTot->FinalTotal());

	$out = "\n<li>Updating package record...</li>";
	$db = $this->Engine();
	$arUpd = array(
	  'ChgShipItm'	=> $db->SanitizeAndQuote($oTot->PerItemAmt()),
	  'ChgShipPkg'	=> $db->SanitizeAndQuote($oTot->PerPkgAmt()),
	  'ChgItmSale'	=> $db->SanitizeAndQuote($oTot->SaleAmt()),
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
	$sPlur = fcString::Pluralize($qItems);
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
		      $rcStkEv->EventID($rcSysEv->GetKeyValue());
		      $rcStkEv->ItemID($idLCItem);
		      $rcStkEv->OtherContID($this->GetKeyValue());		// might be NULL (new package)
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
		    $s2 = $doReally?(fcString::Pluralize($qty,'is','are').' being pulled'):'would be pulled';
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
	    $idNew = $rcPkg->GetKeyValue();
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
	    $idPkg = $this->GetKeyValue();
	    if ($doReally) {
		$htPkg = $this->SelfLink();
		$out .= "\n<li>Modifying package $htPkg...";
	    } else {
		$sPkg = '<b>'.$this->Name().'</b> (ID='.$this->GetKeyValue().')';
		$out .= "\n<li>Package $sPkg would be updated.";
	    }
	}

	foreach ($arItem as $idLCItem => $qty) {
	    // order line is needed for a couple of reasons
	    $rcOLine = $this->OrderRecord()->LineRecord_forItem($idLCItem);

	    $idOLine = $rcOLine->GetKeyValue();
	    $rcPLine = $this->LineRecord_forItem($idLCItem);	// this can be NULL, even for existing pkgs
	    $rcItem = $this->LCatItemTable($idLCItem);
	    $sItem = $rcItem->CatNum();

	    // look up order item; calculate package charge amounts

	    if (is_null($rcOLine) or ($rcOLine->IsNew())) {
		throw new exception('Line item object not found for ID=['.$idLCItem.'].');
	    }

	    $dlrPrice = $rcOLine->PriceSell();
	    $dlrPerItm = $rcOLine->ShipPerItem();
	    $dlrPerPkg = $rcOLine->ShipPerPackage();
	    $sPrice = fcMoney::Format_withSymbol($dlrPrice);
	    $sPerItm = fcMoney::Format_withSymbol($dlrPerItm);
	    $sPerPkg = fcMoney::Format_withSymbol($dlrPerPkg);
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
		$out .= 'updating package line ID='.$rcPLine->SelfLink().'... ';
		//$out .= '<pre>'.print_r($arPL,TRUE).'</pre>';
		$idOLine_pkg = $rcPLine->Value('ID_OrdLine');
		$idPLine = $rcPLine->GetKeyValue();

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
		$sPkg = '<b>'.$this->Name().'</b> (ID='.$this->GetKeyValue().')';
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
	$oPage = $this->PageObject();
	$htErr = $ok?NULL:($oPage->Skin()->ErrorMessage($sErr));
	$out .= "\n</ul>$htErr<br clear=left>";	// kluge so title doesn't wrap to the right
	if (!is_null($idNew)) {
	    // redirect to new package's page
	    $rcNew = $this->Table()->GetItem($idNew);
	    $rcNew->SelfRedirect(NULL,$out);
	}
	return $out;
    }
    /*----
      ACTION: Set up a new Package record.
      RETURNS: record object
      USAGE: Don't call directly; call Admin_NewOutgoing(), which should probably be renamed something like CreatePackage_outgoing().
      TODO: Rename this something like CreatePackage(), if that's not already taken. (If it is, what does it do?)
    */
    protected function Admin_NewRecord($doReally,$arArgs) {
	$nSeq = $this->OrderRecord()->NextPackageSeq();
	$arDefault = array(
	  'ID_Order'	=> $this->GetOrderID(),
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
	    $rcPkg->GetKeyValue($id);
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
	throw new exception('2016-09-11 This does not seem to be in use. Rename to TransactionListing() if it is.');
	$rs = $this->TransactionRecords();
	return $rs->AdminTable($iArgs);
    }
    /*-----
      ACTION: Show what will go into the package, and the existing stock from which it can be filled
      INPUT:
	$sBtnText = text for button to add stock to package
	  If NULL, no form is displayed.
      OUTPUT: returns HTML
      HISTORY:
	2016-08-25 This formerly had an option to be read-only (i.e. no form), but I couldn't find anything that was using that,
	  so I have now removed it. The form is now always displayed.
    */
    protected function AdminStock() {
	$arNeed = $this->FigureNeeded();

	$out = "\n<form method=post name=".__METHOD__.'>';
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
		  '<td>'.$rcItem->SelfLink($rcItem->CatNum()).'</b></td>'.
		  '<td>'.$rcItem->StoreLink_HT($rcItem->FullDescr()).'</td>'.
		  '<td align=right><b>'.$qtyNeed.'</b> needed</td>'
		  ;

		$out .= "\n<tr>".$ouLine.'</tr>';
		// find stock lines for this item
		if ($qtyNeed > 0) {
		    $qtyNeedTotal += $qtyNeed;
		    $rsStk = $tblStk->Records_forItem_info($id);
		    if ($rsStk->HasRows()) {
			// compile list of bins/qtys for each item
			$arBinStk = NULL;
			while ($rsStk->NextRow()) {
			    $idBin = $rsStk->BinID();
			    clsArray::NzSum($arBinStk,$idBin,$rsStk->QtyForShip());
			}
			foreach ($arBinStk as $idBin => $qty) {
			    $rcBin = $this->BinTable($idBin);
			    $htStk = $rcBin->SelfLink_name().' ('.$rcBin->PlaceName().')';
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
	    if ($qtyNeedTotal > 0) {
		$out .= <<<__END__
  <tr>
    <td>
      <span title="actually make changes; unchecked means just simulate">
	<input type=checkbox name="doReally">do it for real
      </span>
    </td>
    <td align=right colspan=3>
      <input type=submit name="btnStock" value="Fetch Stock" />
    </td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>"
	      ."\n</form>"
	      ;
	} else {
	    $out = 'The order has no needed items.';
	}
	return $out;
    }
    /*----
      INPUT:
	$arQtys[idPL]: quantity to remove from package line ID #idPL
	$arBins[idPL]: ID of bin into which items should be moved from package line ID #idPL
    */
    protected function AdminReturn_do(array $arQtys,array $arBins) {
	$idPkg = $this->GetKeyValue();
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
	    $idItem = $rcPL->GetKeyValue();
	    $sItem = $rcItem->CatNum();

	    $idBin = $arBins[$idPL];
	    $rcBin = $this->BinTable($idBin);
	    $sBin = $rcBin->Name();

	    $sDescEv = "quantity <b>$qty</b> of item <b>$sItem</b> from package <b>$sPkg</b> to bin <b>$sBin</b>";

	    // start a stock log event - we know pretty much all of the parameters except destination stock line
	    $rcStkEv = $this->StockItemLog()->CreateAddition($qty,$idBin,clsStkLog::chTypePkg);
	      $rcStkEv->EventID($rcSysEv->GetKeyValue());
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
	if ($this->IsNew()) return;	// so we don't always have to check
    
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
	    $sBtnText = 'Look up bin'.fcString::Pluralize($qLines);
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
		$id = $rsLines->GetKeyValue();
		$htID = $rsLines->SelfLink();
		$rcItem = $rsLines->ItemRecord();
		$htItem = $rcItem->SelfLink_name();
		$qtyShp = $rsLines->QtyShipped();
		$qtyRtn = fcArray::Nz($arItemQty,$id);
		$sBin = fcArray::Nz($arItemBin,$id);

		$htBinFnd = NULL;
		if (!is_null($sBin)) {
		    // look it up to get bin ID
		    $rcBin = $this->BinTable()->Search_byCode($sBin);
		    if ($rcBin->RowCount() == 1) {
			$rcBin->NextRow();	// load first/only row
			$htBinFnd = $rcBin->SelfLink_name();
			$idBin = $rcBin->GetKeyValue();
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
    
    // CALLBACK
    protected function AdminRows_start(array $arOptions=NULL) {
	return "\n<table class=listing>";
    }
    // CALLBACK
    //public function AdminRows_rows(array $arFields,array $arOptions=NULL) {
   // }
    /*----
      CALLBACK
      PURPOSE: always called after attempt to display rows, whether or not there were any
      ACTION: If 'can.add' is set, displays a link to create a new Package for this Shipment.
    */
    protected function AdminRows_after(array $arOptions=NULL) {
	$out = NULL;
	if (clsArray::nz($arOptions,'can.add',FALSE)) {
	    if ($this->hasRows()) {
		$sAdd = 'Add a new package';
	    } else {
		$sAdd = 'Create one';
	    }
	    
	    $idOrder = $arOptions['order.id'];
	    $arLink = array(
	      'page'		=> 'pkg',
	      KS_PAGE_KEY_ORDER	=> $idOrder,
	      'id'		=> KS_NEW_REC,
	      'show'		=> FALSE
	      );
	    $oPage = $this->Engine()->App()->Page();
	    $url = $oPage->SelfURL($arLink);
	    $out = clsHTML::BuildLink($url,$sAdd,'create a new package');
	}
	return $out;
    }
    // CALLBACK
    // who uses it except AdminRows_settings_columns_default()?
    public function AdminRows_fields() {
	$ar = array(
	  '!ID'		=> 'ID',	// show link
	  '!ord'	=> 'Order #',
	  'Seq'	=> 'Seq',
	  'WhenStarted'	=> 'Started',
	  '!isReturn'	=> 'R?',
	  '!qItems'	=> '<span title="total # of items">qty</span>',	// ItemQty()
	  '!idShip'	=> 'Shipment',	// need to override to show link or drop-down
	  '!prcSale'	=> 'sale $',	// need to override to call Charge_forItemSale_html()
	  '!ChgShipPkg'	=> 'chg s/h $',	// need to format
	  '!ShipCost'	=> 'act s/h $',	// need to format
	  'ShipNotes'	=> 'notes'
	);
	return $ar;
    }
    // CALLBACK
    public function AdminRows_settings_columns_default() {
	return $this->AdminRows_fields();
    }
    // CALLBACK
    protected function AdminField($sField,array $arOptions=NULL) {
	if (substr($sField,0,1) == '!') {
	    switch($sField) {
	      case '!ID':
		$val = $this->SelfLink();
		break;
	      case '!ord':
		if (isset($arOptions['order'])) {
		    $idOrd = $arOptions['order'];
		} else {
		    $idOrd = $this->GetOrderID();
		}
		if ($idOrd == 0) {	// when does this happen?
		    $val = '<span class=error>N/A</span>';
		} else {
		    $rcOrd = $this->OrderRecord();
		    $val = $rcOrd->SelfLink_name();
		}
		break;
	      case '!isReturn':
		$val = $this->Value('isReturn')?'R':'';
		break;
	      case '!qItems':
		$val = $this->ItemQty();
		break;
	      case '!idShip':
		$val = $this->ShipmentLink();
		break;
	      case '!prcSale':
		$val = $this->Charge_forItemSale_html();
		break;
	      case '!ShipCost':
		$val = $this->Costs_forShipping_html();
		break;
	      case '!ChgShipPkg':
		$val = $this->Charges_forShipping_html();
		break;
	      default:
		die('Need to define ['.$sField.']');
	    }
	    return "<td>$val</td>";
	} else {
	    return parent::AdminField($sField);
	}
    }

    // -- ADMIN UI -- //

}
