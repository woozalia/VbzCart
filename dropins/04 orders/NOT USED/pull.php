<?php
/*
  FILE: dropins/orders/pull.php -- customer order pulls administration dropin for VbzCart
    includes OrderPullType classes
  HISTORY:
    2014-02-22 split off OrderPull classes from order.php
    2017-01-06 updated somewhat
*/

class vctAdminOrderPulls extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ord_pull';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrAdminOrderPull';
    }
    // CEMENT
    public function GetActionKey() {
	return 'ord-pull';
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return 'No admin interface yet.';
    }
    
    
    // -- EVENTS -- //
    // ++ TABLES ++ //

    protected function TypeTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDER_PULL_TYPES,$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*-----
      ACTION: Get all Pull records for an Order
    */
    public function GetOrder($id) {
	$rsPulls = $this->SelectRecords('ID_Ord='.$id);
	$rsPulls->SetOrderID($id);	// make sure this is set, regardless of whether there is data
	return $rsPulls;
    }

    // -- RECORDS -- //
    // ++ FIELD LOOKUP ++ //

    public function Name_forType($id) {
	return $this->TypeTable($id)->NameString();
    }

    // -- FIELD LOOKUP -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: This *only* adds a Pull record; use Pull() to also mark the Order record
      HISTORY:
	2017-03-28 This will need y2017 remediation.
    */
    protected function Add($idOrder, $idType, $sNotes) {

	$db = $this->Engine();
	$sUserName = $db->App()->User()->LoginName();
	
	$arIns = array(
	  'ID_Ord'	=> $idOrder,
	  'ID_Type'	=> $idType,
	  'WhenPulled'	=> 'NOW()',
	  'NotesPull'	=> $db->SanitizeAndQuote($sNotes),
	  'VbzUser'	=> $db->SanitizeAndQuote($sUserName),
	  'SysUser'	=> $db->SanitizeAndQuote($_SERVER["SERVER_NAME"]),
	  'Machine'	=> $db->SanitizeAndQuote($_SERVER["REMOTE_ADDR"])
	  );
	$id = $this->Insert($arIns);
	
	if ($id === FALSE) {
	    echo 'ERROR: '.$db-> getError();
	    echo '<br>SQL: '.$this->sqlExec;
	    die();
	}
	return $id;
    }
    /*----
      ACTION: Create a new Pull record from the given specs.
      RETURNS: ID of new record
      USAGE: Should only ever be called by an Order object.
	To release a Pull, call [pull record object]->UnPull().
    */
    public function AddPull($idOrder,$idType,$sNotes) {
	$idPull = $this->Add($idOrder,$idType,$sNotes);
	return $idPull;
    }

    // -- ACTIONS -- //
    // ++ WEB UI ++ //

    /*----
      ACTION: render a combobox of pull-types
      PUBLIC so Order objects can use it
    */
    public function DropDown_Types($sName) {
	return $this->TypeTable()->ComboBox($sName);
    }

    // -- WEB UI -- //

}
class vcrAdminOrderPull extends vcAdminRecordset {

    // ++ FIELD VALUES ++ //

    public function SetOrderID($id) {
	return $this->SetFieldValue('ID_Ord',$id);
    }
    public function GetOrderID() {
	return $this->GetFieldValue('ID_Ord');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    public function IsPulled() {
	return (!is_null($this->GetFieldValue('WhenPulled')) && is_null($this->GetFieldValue('WhenFreed')));
    }
    public function TypeName() {
	return $this->TypeRecord()->GetFieldValue('Name');
    }

    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //

    protected function TypeTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper('vctOrderPullTypes',$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function TypeRecord() {
	$idType = $this->GetFieldValue('ID_Type');
	if (is_null($idType)) {
	    return NULL;
	} else {
	    return $this->TypeTable($idType);
	}
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    /*----
      USAGE: Use only on a Pull object which is currently active.
      RETURNS: NULL or error string.
	For now, caller should assume success regardless of return value.
	(This may change later.)
    */
    public function UnPull($sNotes) {
	if ($this->IsPulled()) {
	    $sUser = $this->Engine()->App()->LoginName();

	    $sqlNotes = $this->Engine()->SafeParam("(by $sUser) $sNotes");
	    $arUpd = array(
	      'WhenFreed' => 'NOW()',
	      'NotesFree' => "'$sqlNotes'"
	      );
	    $this->Update($arUpd);
	    return NULL;
	} else {
	    $this->Engine()->LogEvent(__METHOD__,'Notes="'.$sNotes.'"','attempting double-release','DRL',TRUE,FALSE);
	    return 'attempting double-release of Pull ID '.$this->GetKeyValue();
	}
    }
    
    // -- ACTIONS -- //

}
