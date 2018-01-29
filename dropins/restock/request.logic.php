<?php
/*
  FILE: restock requests - business logic
  HISTORY:
    2015-12-31 Split request.php into request.logic.php and request.admin.php
      Earlier history is in request.admin.php.
*/
class vctRstkReqs extends vcBasicTable {
    use vtRestockTable_logic;

    // ++ SETUP ++ //
/*
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng();
	  $this->Name();
	  $this->KeyName('ID');
	  $this->ActionKey();
    } */
    protected function TableName() {
	return KS_TABLE_RESTOCK_REQUEST;
    }
    protected function SingularName() {
	return 'vcrRstkReq';
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //
    
    protected function ReceivedClass() {
	return KS_LOGIC_CLASS_RESTOCKS_RECEIVED;
    }
    
    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //
    
    protected function ReceivedTable($id=NULL) {
	return $this->Engine()->Make($this->ReceivedClass(),$id);
    }
    
    // -- DATA TABLES -- //
    // ++ SQL BUILDING ++ //
    
    /*----
      RETURNS: SQL object for creating recordset of active restock requests
      REPLACES http://htyp.org/VbzCart/queries/qryRstks_active
    *//* this looks like not the way to do it
    public function SQLobj_Active() {
	$oThis = new fcSQL_Table($this->NameSQL());
    }
*/  
    protected function SQLstr_Sorter_Date() {
	return 'IFNULL(WhenCreated,WhenOrdered)';
    }
    /*----
      REPLACES http://htyp.org/VbzCart/queries/qryRstks_active
      USED BY at least two things now...
    */
    protected function SQLstr_Select_Active_NewestFirst($sqlCond = NULL) {
	$sqlSort = $this->SQLstr_Sorter_Date();
	$sqlFilt = is_null($sqlCond)?'':"AND $sqlCond";
	$sqlMe = $this->NameSQL();
	$sql = <<<__END__
SELECT * FROM $sqlMe
  WHERE (WhenClosed IS NULL)
    AND (WhenKilled IS NULL)
    AND (WhenOrphaned IS NULL)
    $sqlFilt
  ORDER BY $sqlSort DESC
__END__;
	return $sql;
    }
    protected function SQLstr_filter_forSupplier($idSupp = NULL,$sqlPrefix=NULL) {
	return is_null($idSupp)?NULL:"$sqlPrefix(ID_Supplier=$idSupp)";
    }
    /*----
      RETURNS: SQL object for creating recordset of active restock requests
      REPLACES http://htyp.org/VbzCart/queries/qryRstks_active
      USES self::SQLstr_Select_Active_NewestFirst()
    */
    public function SQLobj_Active() {
	return new fcSQL_Select($this->SQLstr_Select_Active_NewestFirst(),'rr');
    }
    /*----
      RETURNS: SQL command for creating recordset of restock requests expected,
	i.e. active requests that have not yet been received (no restock-received records)
      PUBLIC so Request-Item table can use it to build list of expected items
    */
    public function SQLstr_Expected() {
	$sqlActv = $this->SQLstr_Select_Active_NewestFirst();	// active restock requests
	$sqlRecd = $this->ReceivedTable()->NameSQL();	// restocks received
	$sql = <<<__END__
SELECT rqa.* FROM (
$sqlActv
  ) AS rqa
  LEFT JOIN $sqlRecd AS rc
  ON rc.ID_Request=rqa.ID
  WHERE rc.ID IS NULL
__END__;
	return $sql;
    }
    
    // -- SQL BUILDING -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      INPUT: $idSupp = Supplier to show; if null, show data for all suppliers
    */
    public function RowsActive_forSupplier($idSupp) {
	$sqlCond = $this->SQLstr_filter_forSupplier($idSupp);
	return $this->RowsActive($sqlCond);
    }
    public function RowsActive($sqlCond=NULL) {
	$sql = $this->SQLstr_Select_Active_NewestFirst($sqlCond);
	$rs = $this->DataSQL($sql);
	if ($this->Engine()->isOk()) {
	    return $rs;
	} else {
	    $sErr = $this->Engine()->getError();
	    throw new exception('SQL Error: '.$sErr);
	}
    }
    public function RowsInactive() {
	$this->Name('qryRstks_inactive');
	$rsRows = $this->GetData(NULL,NULL,'WhenCreated DESC,WhenOrdered DESC');
	return $rsRows;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    public function Create($idSupp,$sPONum,array $arItems) {
	$arNew = array(
	  'ID_Supplier'	=> $idSupp,
	  'PurchOrdNum'	=> SQLValue($sPONum),
	  'WhenCreated'	=> 'NOW()'
	  );
	$this->Insert($arNew);		// new restock request
	$id = $this->Engine()->NewID();
	$rcNew = $this->GetItem($id);	// load the request record

	// got the ID for the master record; now add the item records:
	foreach ($arItems as $id => $qty) {
	    $rcNew->AddItem($id,$qty);
	}
	return $rcNew;
    }

    // -- ACTIONS -- //
}
class vcrRstkReq extends vcBasicRecordset {

    // ++ FIELD ACCESS ++ //

    /*----
      RETURNS: String identifying the request in a user-friendly way
      NOTE: This can be enhanced by borrowing from Access code, which added some more info.
    */
    public function Name() {
	return $this->OurPurchaseOrderNumber();
    }
    public function PurchaseOrderNumber() {
	throw new exception('PurchaseOrderNumber() has been renamed OurPurchaseOrderNumber().');
    }
    protected function OurPurchaseOrderNumber() {
	return $this->GetFieldValue('PurchOrdNum');
    }
    protected function SupplierPurchaseOrderNumber() {
	return $this->Value('SuppPONum');
    }
    protected function SupplierOrderNumber() {
	return $this->Value('SuppOrdNum');
    }
    public function WhenCreated() {
	return $this->GetFieldValue('WhenCreated');
    }
    protected function WhenOrdered() {
	return $this->GetFieldValue('WhenOrdered');
    }
    protected function WhenConfirmed() {
	return $this->Value('WhenConfirmed');
    }
    protected function WhenKilled() {
	return $this->GetFieldValue('WhenKilled');
    }
    protected function WhenClosed() {
	return $this->GetFieldValue('WhenClosed');
    }
    protected function WhenOrphaned() {
	return $this->GetFieldValue('WhenOrphaned');
    }
    protected function WhenExpectedOriginal() {
	return $this->Value('WhenExpectedOrig');
    }
    protected function WhenExpectedFinal() {
	return $this->Value('WhenExpectedFinal');
    }
    protected function CarrierText() {
	return $this->Value('CarrierDescr');
    }
    protected function TotalCostCalculated() {
	return $this->Value('TotalCalcMerch');
    }
    protected function TotalFinalEstimated() {
	return $this->Value('TotalEstFinal');
    }
    protected function PaymentMethodString() {
	return $this->Value('PayMethod');
    }

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      RETURNS: string that can be used as a name for the request
	Normally we'd just use our Purchase Order number, but some requests don't have one.
    */
    public function BestName() {
	$sName = $this->Name();
	if (empty($sName)) {
	    $sName = $this->BestPurchaseOrderNumber();
	    if (empty($sName)) {
		$sName = $this->BestDate();
	    }
	}
	return $sName;
    }
    public function IsActive() {
	return
	  (is_null($this->WhenKilled())) &&
	  (is_null($this->WhenClosed())) &&
	  (is_null($this->WhenOrphaned()));
    }
    // TODO: rename to something like SummaryDetails()
    public function Descr() {
	return $this->OurPurchaseOrderNumber().' created '.$this->WhenCreated();
	// we can add more info later if needed
    }
    /*----
      ACTION: Tries to find a purchase order number, whether ours or theirs; returns the best one found
    */
    protected function BestPurchaseOrderNumber() {
	$out = $this->OurPurchaseOrderNumber();
	if (empty($out)) {
	    $out = $this->SupplierPurchaseOrderNumber();
	    if (empty($out)) {
		$out = 'so '
		  .$this->SupplierOrderNumber()
		  .'ID '.$this->GetKeyValue();
	    } else {
		$out = 'spo '.$out;
	    }
	} else {
	    $out = 'po '.$out;
	}
	return $out;
    }
    /*----
      RETURNS: the best date field that isn't NULL
    */
    protected function BestDate() {
	if (is_null($this->WhenOrdered())) {
	    if (is_null($this->WhenConfirmed())) {
		if (is_null($this->WhenCreated())) {
		    if (is_null($this->WhenExpectedOriginal())) {
			if (is_null($this->WhenExpectedFinal())) {
			    $out = '(date n/a)';
			} else {
			    $out = 'xf'.$this->WhenExpectedFinal();
			}
		    } else {
			$out = 'xo'.$this->WhenExpectedOriginal();
		    }
		} else {
		    $out = 'cr'.$this->WhenCreated();
		}
	    } else {
		$out = 'cf'.$this->WhenConfirmed();
	    }
	} else {
	    $out = 'or'.$this->WhenOrdered();
	}
	return $out;
    }
    // RETURNS: single-line summary suitable for use in drop-downs
    public function SummaryLine_short() {
	$id = $this->GetKeyValue();
	$dt = $this->BestDate();
	$po = $this->BestPurchaseOrderNumber();
	return "($id) $dt - $po";
    }
    protected function Date_forSorting() {
	return is_null($this->WhenCreated())?$this->WhenOrdered():$this->WhenCreated();
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //

    protected function ReceivedsClass() {
	return KS_LOGIC_CLASS_RESTOCKS_RECEIVED;
    }
    protected function RequestItemsClass() {
	return KS_LOGIC_CLASS_RESTOCK_REQ_ITEMS;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function CatalogItemTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_ADMIN_CLASS_LC_ITEMS);
    }
    protected function ReceivedTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ReceivedsClass(),$id);
    }
    protected function RequestItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->RequestItemsClass(),$id);
    }
    protected function ContentsTable($id=NULL) {
	return $this->RequestItemTable($id);	// alias
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      RETURNS: recordset of any wholesale shipments received against this request
    */
    public function ReceivedRecords() {
	$id = $this->GetKeyValue();
	return $this->ReceivedTable()->Records_forRequest($id);
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: adds a line item to the request
      METHOD: defers to item table class
    */
    public function AddItem(vcrItem $rcItem,$qtyNeed,$qtySold) {
	return $this->RequestItemTable()->AddItem($this->GetKeyValue(),$rcItem,$qtyNeed,$qtySold);
    }

    // -- ACTIONS -- //

}
