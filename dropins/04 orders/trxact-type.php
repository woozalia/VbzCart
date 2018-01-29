<?php
/*
  PURPOSE: classes for order transaction types
  HISTORY:
    2015-10-19 extracted from trxact.php
*/
class vctOrderTrxTypes extends vcAdminTable {
    use ftExecutableTwig;

    // ++ SETUP ++ //

    protected function TableName() {
	return 'ord_trx_type';
    }
    protected function SingularName() {
	return 'vcraOrderTrxType';
    }
    public function GetActionKey() {
	return KS_PAGE_KEY_ORDER_TRX_TYPE;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //

    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Transaction Types');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();	// TODO: to be written
    }

    // -- EVENTS -- //
}
class vcraOrderTrxType extends vcAdminRecordset {

    // ++ CONSTANTS ++ //

    // these must match what's in the table
    const SH_EA = 1;	// used by Package Line
    const SH_PK = 2;	// used by Package Line
    const ITEM = 11;	// used by Package Line
    
    // -- CONSTANTS -- //
    // ++ FIELD VALUES ++ //

    protected function CategoryIndex() {
	return $this->GetFieldValue('Catg');
    }
    protected function AboutString() {
	return $this->GetFieldValue('Descr');
    }
    protected function CodeString() {
	return $this->GetFieldValue('Code');
    }
    public function NameShort() {
	return $this->CodeString();
    }
    public function NameLong() {
	return $this->CodeString().' '.$this->AboutString();
    }
/*    public function IsShipping() {
	return $this->Value('isShipg') != chr(0);
    }*/
    public function IsCash() {
	return $this->Value('isCash') != chr(0);
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    public function DocLink($iText=NULL) {
	$htCode = (is_null($iText))?$this->CodeString():$iText;
	// TODO: should integrate with a wiki module to detect if the wiki page is actually available
	$url = vcGlobals::Me()->GetWebPath_forAdminDocs_TransactionType($this->CodeString());
	return fcHTML::BuildLink($url,$htCode,$this->AboutString());
    }
    public function IsSale() {
	return $this->CategoryIndex() == 1;
    }
    public function IsShipping() {
	return $this->CategoryIndex() == 2;
    }
    public function IsPayment() {
	return $this->CategoryIndex() == 3;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ WEB UI: COMPONENTS ++ //
    
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
    
    // -- WEB UI: COMPONENTS -- //

}
