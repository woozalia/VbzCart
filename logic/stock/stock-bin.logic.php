<?php
/*
  PURPOSE: basic logic for stock Bins
  USAGE: This class is, so far, only used by the admin interface - though Bins do figure into the shopping interface
    in that Stock Lines are only valid if they're in a valid Bin.
  HISTORY:
    2017-03-23 extracted from the Bins dropin so things can have a sensible inheritance chain
*/

class vctStockBins extends vcBasicTable {

    // ++ SETUP ++ //

    protected function TableName() {
	return 'stk_bins';
    }
    protected function SingularName() {
	return 'vcrStockBin';
    }

    // -- SETUP -- //
    // ++ BOILERPLATE: cache management (table) ++ //

    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */ /* 2017-03-23 I don't think we're using the cache anymore.
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp();
    } */
    /*----
      ACTION: update the cache record to show that this table has been changed
      NOTES:
	Must be public so it can be called by recordset type.
    */ /* 2017-03-23 I don't think we're using the cache anymore.
    public function CacheStamp() {
	$objCache = $this->Engine()->CacheMgr();
	$objCache->UpdateTime_byTable($this);
    } */

    // -- BOILERPLATE -- //
    // ++ RECORDS ++ //
    
    /*----
      RETURNS: Dataset of active bins
      2017-03-23 this will need some updating if it is still in use.
    */
    public function GetActive() {
	$objRows = $this->GetData('(WhenVoided IS NULL) AND isForShip',NULL,'Code');
	return $objRows;
    }
    
    // -- RECORDS -- //

}
class vcrStockBin extends vcBasicRecordset {
    // ++ BOILERPLATE: cache management (recordset) ++ //

    /*----
      ACTION: update the cache record to show that this table has been changed
    */
    protected function CacheStamp($iCaller) {
	throw new exception('2017-03-23 Does anything still call this?');
	$this->Table()->CacheStamp($iCaller);
    }
    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */ /* 2017-03-23 I don't think we're using the cache tables anymore.
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp(__METHOD__);
    } */

    // -- BOILERPLATE -- //
    // ++ FIELD VALUES ++ //
    
    protected function GetPlaceID() {
	return $this->GetFieldValue('ID_Place');
    }
    public function SetPlaceID($id) {
	return $this->SetFieldValue('ID_Place',$id);
    }
    protected function Code() {
	throw new exception('2017-03-24 Code() is deprecated; call LabelString().');
    }
    protected function LabelString() {
	return $this->GetFieldValue('Code');
    }
    protected function IsSellable() {
	return ord($this->GetFieldValue('isForSale'));
    }
    protected function IsShippable() {
	return ord($this->GetFieldValue('isForShip'));
    }
    protected function IsForSale() {
	throw new exception('2017-04-19 Call IsSellable() instead.');
	return ord($this->GetFieldValue('isForSale'));
    }
    protected function IsForShip() {
	throw new exception('2017-04-19 Call IsShippable() instead.');
	return ord($this->GetFieldValue('isForShip'));
    }

    /* 2017-04-19 These belong in the stock line class, if anywhere
    // RETURNS: quantity available to be sold
    protected function QuantityForSale() {
	return $this->GetFieldValue('QtyForSale');
    }
    // RETURNS: quantity available to be shipped (should be .ge. QuantityForSale())
    protected function QuantityForShipping() {
	return $this->GetFieldValue('QtyForShip');
    }
    // RETURNS: quantity that exists overall (should be .ge. QuantityForShipping())
    protected function QuantityExisting() {
	return $this->GetFieldValue('QtyExisting');
    } */
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function StatusString_overall() {
	$out = $this->StatusString_place()
	  .$this->StatusString_local()
	  ;
    }
    protected function StatusString_local() {
	$out = NULL;
	if ($this->SelfIsActive()) {
	    $out .= 'actv';
	}
	if ($this->IsSellable()) {
	    $out .= ' sale';
	}
	if ($this->IsShippable()) {
	    $out .= ' ship';
	}
	return $out;
	// TODO: working here
    }
    protected function StatusString_place() {
	$out = NULL;
	if ($this->HasPlace()) {
	    if ($this->HasActivePlace()) {
		$out .= '<span title="active Place">P</span>';
	    } else {
		$out .= '<span title="inactive Place">p</span>';
	    }
	}
	return $out;
    }
    protected function StatusCode() {
	throw new exception('2017-04-19 Call StatusString_local() or StatusString_overall() instead');
	return $out;
    }
    protected function HasPlace() {
	if ($this->HasRow()) {
	    if ($this->FieldIsSet('ID_Place')) {
		return TRUE;
	    }
	}
	return FALSE;
    }
    // RETURNS: [NOT VOIDED]
    public function SelfIsActive() {
	return !$this->FieldIsNonBlank('WhenVoided');	// sometimes comes up as zero
    }
    /*----
      RETURNS: [Place exists AND is ACTIVE]
      PUBLIC so stock line can access it for admin display
    */
    public function HasActivePlace() {
	if ($this->HasPlace()) {
	    return $this->PlaceRecord()->IsActiveSpace();
	} else {
	    FALSE;
	}
    }
    protected function CanBeUsed() {
	return $this->SelfIsActive() && $this->HasActivePlace();
    }
    
    /* 2017-03-24 These are too confusingly named -- so commenting them all out and replacing them with above methods.
    // RETURNS: [NOT VOIDED]
    public function IsActive() {	// A
	return $this->FieldIsNonBlank('WhenVoided');	// sometimes comes up as zero
    }
    // RETURNS: [PLACE IS ACTIVE]
    public function IsValid() {		// B = has Place AND C
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    return FALSE;
	} else {
	    return $rcPlace->IsActive();
	}
    }
    public function IsEnabled() {	// C
	//return ord($this->Value('isEnabled'));
	return $this->PlaceRecord()->IsActive();	// 2016-02-24 This could be slow...
    }
    // RETURNS: [NOT VOIDED] AND [ENABLED]
    public function IsActive_and_Enabled() {	// D = A AND C
	if ($this->IsActive()) {
	    if ($this->IsEnabled()) {
		return TRUE;
	    }
	}
	return FALSE;
    }
    /*----
      RETURNS: [NOT VOIDED] AND [ENABLED] AND [PLACE IS ACTIVE]
    * /
    public function IsUsable() {		// E = A AND C AND hasPlace AND C
	if ($this->IsActive_and_Enabled()) {
	    return $this->IsValid();
	} else {
	    return FALSE;
	}
    }
    */

    // -- FIELD CALCULATIONS -- //
    // ++ CLASSES ++ //

    protected function PlacesClass() {
	return KS_CLASS_STOCK_PLACES;
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function PlaceTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->PlacesClass(),$id);
    }

    // ++ RECORDS ++ //

    /*----
      PUBLIC because Package object needs it
      HISTORY:
	2011-03-19 Return a Spawned item if ID_Place is not set -- presuming
	  we need the object for other purposes besides its current values
	2017-03-24 No longer spawns a blank item; returns NULL if ID_Place cannot
	  be retrieved. If blank item needed, document the need -- and it might
	  be a good idea to make that a separate method, PlaceRecord_SpawnIfNeeded().
	  
	  Now using HasPlace() (new) to determine if ID_Place can be retrieved.
    */
    public function PlaceRecord() {
	if ($this->HasPlace()) {
	    $idPlc = $this->GetPlaceID();
	    $rc = $this->PlaceTable($idPlc);
	    return $rc;
	}
	return NULL;
    }

    // -- RECORDS -- //

}