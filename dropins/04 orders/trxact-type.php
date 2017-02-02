<?php
/*
  PURPOSE: classes for order transaction types
  HISTORY:
    2015-10-19 extracted from trxact.php
*/
class VCT_OrderTrxTypes extends vcAdminTable {

    /*
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng();
	  $this->Name();
	  $this->KeyName('ID');
    }*/
    protected function TableName() {
	return 'ord_trx_type';
    }
    protected function SingularName() {
	return 'vcraOrderTrxType';
    }
    public function GetActionKey() {
	return KS_PAGE_KEY_ORDER_TRX_TYPE;
    }
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
	return $this->Value('Catg');
    }
    public function NameShort() {
	return $this->Value('Code');
    }
    public function NameLong() {
	return $this->Value('Code').' '.$this->Value('Descr');
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
	$txtCode = (is_null($iText))?$this->Value('Code'):$iText;
	// TODO: should integrate with a wiki module to detect if the wiki page is actually available
	return clsHTML::BuildLink(KWT_DOC_TRX_TYPES.'/'.$this->Value('Code'),$txtCode,$this->Value('Descr'));
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
