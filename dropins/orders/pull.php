<?php
/*
  FILE: dropins/orders/pull.php -- customer order pulls administration dropin for VbzCart
    includes OrderPullType classes
  HISTORY:
    2014-02-22 split off OrderPull classes from order.php
*/

class VCT_OrderPulls extends clsTable {
    const TableName='ord_pull';

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_OrderPull');
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }

    // -- SETUP -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function TypeTable($id=NULL) {
	return $this->Engine()->Make('clsOrderPullTypes');
    }
/* use TypeTable()
    public function Types() {
	if (!isset($this->objTypes)) {
	    $this->objTypes = $this->TypeTable();
	}
	return $this->objTypes;
    }
    */

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*-----
      ACTION: Get all Pull records for an Order
    */
    public function GetOrder($iID) {
	$rsPulls = $this->GetData('ID_Ord='.$iID);
	$rsPulls->OrderID($iID);	// make sure this is set, regardless of whether there is data
	return $rsPulls;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTIONS ++ //

    protected function Add($iOrdID, $iTypeID, $iNotes) {
    // ACTION: This *only* adds a Pull record; use Pull() to also mark the Order record

	$sUserName = $this->Engine()->App()->User()->UserName();
	$sqlNotes = $this->Engine()->SafeParam($iNotes);
	$arIns = array(
	  'ID_Ord'	=> $iOrdID,
	  'ID_Type'	=> $iTypeID,
	  'WhenPulled'	=> 'NOW()',
	  'NotesPull'	=> SQLValue($iNotes),
	  'VbzUser'	=> SQLValue($sUserName),
	  'SysUser'	=> '"'.$_SERVER["SERVER_NAME"].'"',
	  'Machine'	=> '"'.$_SERVER["REMOTE_ADDR"].'"'
	  );
	$this->Insert($arIns);
	$this->ID = $this->Engine()->NewID();
    }
    public function Pull(VbzAdminOrder $rcOrder, $iType, $sNotes) {
	$this->Add($rcOrder->KeyValue(),$iType,$sNotes);
	$iOrder->Pull($this->KeyValue());
    }

    // -- ACTIONS -- //
}
class VCR_OrderPull extends clsDataSet {
    private $objTypes;
    private $objOrd;

    // ++ DATA FIELD ACCESS ++ //

    public function OrderID($id=NULL) {
	return $this->Value('ID_Ord',$id);
    }
    public function IsPulled() {
	return (!is_null($this->Value('WhenPulled')) && is_null($this->Value('WhenFreed')));
    }
    public function TypeName() {
	return $this->TypeRecord()->Value('Name');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function TypeTable($id=NULL) {
	return $this->Engine()->Make('clsOrderPullTypes',$id);
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function TypeRecord() {
	$idType = $this->Value('ID_Type');
	if (is_null($idType)) {
	    return NULL;
	} else {
	    return $this->TypeTable($idType);
	}
    }

    // -- DATA RECORDS ACCESS -- //

    /*=====
      USAGE: Must be called only on the ACTIVE pull - get that from the order object
    */
    public function UnPull($iNotes) {
	global $vgUserName;
	if ($this->IsPulled()) {
	    $sqlNotes = $this->objDB->SafeParam('(by '.$vgUserName.') '.$iNotes);
	    $arUpd = array('WhenFreed' => 'NOW()','NotesFree' => $sqlNotes);
	    $this->Update($arUpd);
	    return NULL;
	} else {
	    $this->objDB->LogEvent(__METHOD__,'Notes="'.$iNotes.'"','attempting double-release','DRL',TRUE,FALSE);
	    return 'attempting double-release of Pull ID '.$this->ID;
	}
    }
}
class clsOrderPullTypes extends clsTableCache {
    const TableName='ord_pull_type';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsOrderPullType');
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }
    public function ComboBox($iName,$iWhich=NULL) {
	$objRows = $this->GetData();
	$out = '<select name="'.$iName.'">';
	while ($objRows->NextRow()) {
	    $id = $objRows->KeyValue();
	    if ($id == $iWhich) {
		$htSelect = " selected";
	    } else {
		$htSelect = '';
	    }
	    $htName = $objRows->Value('Name');
	    $out .= "\n<option$htSelect value=\".$id>$htName</option>";
	}
	$out .= "\n</select>";
	return $out;
    }
}
class clsOrderPullType extends clsDataSet {
}
