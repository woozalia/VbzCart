<?php
/*
  PURPOSE: manager class for Cart field groups
  HISTORY:
    2016-06-16 split off from cart.data.fg.php (formerly cart.xdata.php)
*/

class vcCartDataManager {

    // ++ SETUP ++ //

    public function __construct(vcrShopCart $rcCart, vcShipCountry $oZone) {
	$this->SetCartRecord($rcCart);
	$this->SetShipZone($oZone);
    }
    private $rcCart;
    protected function SetCartRecord(vcrShopCart $rcCart) {
	$this->rcCart = $rcCart;
    }
    protected function GetCartRecord() {
	return $this->rcCart;
    }
    private $oZone;
    protected function SetShipZone(vcShipCountry $oZone) {
	$this->oZone = $oZone;
    }
    protected function GetShipZone() {
	return $this->oZone;
    }
    private $oBlob;
    protected function GetBlobObject() {
	if (empty($this->oBlob)) {
	    $oBlob = new fcBlobField();
	    $oBlob->SetString($this->GetBlobString());
	    $this->oBlob = $oBlob;
	}
	return $this->oBlob;
    }
    protected function GetBlobString() {
	return $this->GetCartRecord()->GetSerialBlob();
    }
    protected function SetBlobString($s) {
	$this->GetCartRecord()->SetSerialBlob($s);
    }
    /*----
      ACTION: unserialize the blob and store it locally as an array
      PUBLIC because... well, maybe there's a better way to do this,
	but I don't know what it is. Cart objects need to be able to
	update the blob...
      HISTORY:
	2016-06-06 I have no idea why everything but the first line was commented out. Uncommented.
    */
    public function FetchBlob() {
	$sBlob = $this->GetBlobString();
	if (is_null($sBlob)) {
	    $this->SetBlobArray(array());	// no data yet
	} else {
	    $this->SetBlobArray(unserialize($sBlob));
	}
    }
    /*----
      ACTIONS:
	* serialize the local array
	* save serialized array back to the blob object
	* save serialized array back to recordset field
	(Don't update recordset; caller should do that.)
      PUBLIC because... see FetchBlob()
    */
    public function StoreBlob() {
	$sBlob = serialize($this->GetBlobArray());
	$this->SetBlobString($sBlob);
    }
//    private $arBlob;
    protected function GetBlobArray() {
    /*
	if (empty($this->arBlob)) {
	    $this->arBlob = array();	// required for initializing stuff
	}
	return $this->arBlob; //*/
	return $this->GetBlobObject()->GetArray();
    }
    /*----
      USED BY: $this->FetchBlob()
    */
    protected function SetBlobArray(array $ar) {
	$this->arBlob = $ar;
    }
    
    // -- SETUP -- //
    // ++ SUBSETS ++ //
    
    private $oBuyer;
    public function BuyerObject(vcShipCountry $oZone=NULL) {
	if (is_object($oZone)) { throw new exception('Stop passing oZone.'); }
	if (empty($this->oBuyer)) {
	    $this->oBuyer = new vcCartData_Buyer($this->GetBlobArray(),$this->GetShipZone());
	}
	return $this->oBuyer;
    }
    private $oRecip;
    public function RecipObject(vcShipCountry $oZone=NULL) {
	if (is_object($oZone)) { throw new exception('Stop passing oZone.'); }
	if (empty($this->oRecip)) {
	    $this->oRecip = new vcCartData_Recip($this->GetBlobArray(),$this->GetShipZone());
	}
	return $this->oRecip;
    }

    // -- SUBSETS -- //
    // ++ FORM I/O ++ //
    
    /*----
      ACTION:
      NOTE: Call FetchBlob() before calling this, and StoreBlob() when done with all updates.
    */
    public function UpdateBlob(vcCartDataFieldGroup $oData) {
	$arForm = $oData->GetFormArray();
	if (is_array($arForm)) {
//	    $this->SetBlobArray(array_merge($this->GetBlobArray(),$ar));
	    $oBlob = $this->GetBlobObject();
	    $oBlob->MergeArray($arForm);
	}
    }
    
    // -- FORM I/O -- //
    // ++ DEBUGGING ++ //
    
    public function RenderBlob() {
	return $this->GetBlobObject()->Render();
    }

}
