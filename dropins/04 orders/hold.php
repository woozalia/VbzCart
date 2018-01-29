<?php
/*
  FILE: dropins/orders/pull.php -- customer order pulls administration dropin for VbzCart
    includes OrderPullType classes
  HISTORY:
    2014-02-22 split off OrderPull classes from order.php
    2017-01-06 updated somewhat
    2017-06-04 rewriting for Order Holds
*/

class vctOrderHolds extends fctSubEvents implements fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // CEMENT
    protected function SingularName() {
	return 'vcrOrderHold';
    }
//    public function TableName_Cooked() {	// db table name sanitized with backticks
//	return '`'.$this->TableName().'`';
 //   }
   // CEMENT
    protected function TableName() {
	return 'event_vc_ord_hold';
    }
    // OVERRIDE
    protected function SourceString_forSelect() {
	return $this->TableName_Cooked().' AS h LEFT JOIN event AS e ON h.ID_Event=e.ID';
    }
    public function GetActionKey() {
	return KS_ACTION_KEY_ORDER_HOLD;
    }
    /*----
      CEMENT
      RETURNS: key for type of event -- like an ActionKey, but only unique within Event sub-types
	This is used in the SQL SELECT statement as an alias for each sub-event table.
    */
    public function GetTypeKey() {
	return 'vc-hold';
    }
    /*----
      CEMENT
      RETURNS: array of field-names to be included in the JOIN statement (not including ID_Event)
    */
    protected function FieldArray() {
	return array('ID_Order','ID_Type','isRelease','doHoldRstk','doHoldChrg','doContact','doExamine','Notes');   
    }

    // -- SETUP -- //
    // ++ RECORDS ++ //

    public function Records_forOrder($idOrder) {
	$sqlThis = $this->SourceString_forSelect();
	$sql = <<<__END__
SELECT
  e.ID,
  e.WhenStart,
  e.ID_Session,
  e.ID_Acct,
  e.Descrip,
  h.ID_Order,
  h.ID_Type,
  h.isRelease,
  h.doHoldRstk,
  h.doHoldChrg,
  h.doContact,
  h.doExamine,
  h.Notes
FROM $sqlThis
WHERE h.ID_Order=$idOrder
ORDER BY e.WhenStart DESC;
__END__;
	$rs = $this->FetchRecords($sql);
	return $rs;
    }

    // -- RECORDS -- //
}
class vcrOrderHold extends vcAdminRecordset implements fiEventAware { // maybe needs to be fcRecord_keyed_single_integer
    use ftExecutableTwig;	// dispatch events

    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$id = $this->GetKeyValue();
	$sTitle = 'hold #'.$id;
	$htTitle = 'Order Hold #'.$id;
	
	$oPage = fcApp::Me()->GetPageObject();
//	$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ TABLE ++ //
    
    protected function TypeTable($id) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_ORDER_HOLD_TYPES,$id);
    }
    protected function SessionTable($id) {
	$t = fcApp::Me()->GetSessionTable();
	return $t->GetRecord_forKey($id);
    }
    protected function UserTable($id) {
	$t = fcApp::Me()->UserTable();
	return $t->GetRecord_forKey($id);
    }
    
    // -- TABLE -- //
    // ++ RECORDS ++ //
    
    public function TypeRecord() {
	$idType = $this->GetFieldValue('ID_Type');
	if (is_null($idType)) {
	    return NULL;
	} else {
	    return $this->TypeTable($idType);
	}
    }
    protected function SessionRecord() {
	$id = $this->GetSessionID();
	return $this->SessionTable($id);
    }
    protected function UserRecord() {
	$id = $this->GetUserID();
	return $this->UserTable($id);
    }

    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //
    
    protected function AboutString() {
	return $this->GetFieldValue('Descrip');
    }
    protected function IsRelease() {
	return $this->GetFieldValue('isRelease');
    }
    protected function Status_DoHoldRestock() {
	return $this->GetFieldValue('doHoldRstk');
    }
    protected function Status_DoHoldCharge() {
	return $this->GetFieldValue('doHoldChrg');
    }
    protected function Status_DoContact() {
	return $this->GetFieldValue('doContact');
    }
    protected function Status_DoExamine() {
	return $this->GetFieldValue('doExamine');
    }
    protected function Timestamp() {
	return $this->GetFieldValue('WhenStart');
    }
    protected function NotesString() {
	return $this->GetFieldValue('Notes');
    }
    protected function GetSessionID() {
	return $this->GetFieldValue('ID_Session');
    }
    protected function GetUserID() {
	return $this->GetFieldValue('ID_Acct');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function TypeShortString() {
	return $this->TypeRecord()->DisplayString();
    }
    // RETURNS: TRUE iff there are any active hold conditions
    protected function IsActive() {
	return 
	  $this->Status_DoHoldRestock()
	  || $this->Status_DoHoldCharge()
	  || $this->Status_DoContact()?'[do contact]':NULL
	  || $this->Status_DoExamine()?'[do examine]':NULL
	  ;
    }
    protected function RenderStatus() {
	// for clarity, let's show all the flags first:
	$out =
	  $this->IsRelease()?'RELEASE':'HOLD'
	  .' '
	  .($this->Status_DoHoldRestock()?'<span state=active>+':'<span state=inactive>-')
	  .'RSTK</span> '
	  .($this->Status_DoHoldCharge()?'<span state=active>+':'<span state=inactive>-')
	  .'CHG</span> '
	  .($this->Status_DoContact()?'<span state=active>+':'<span state=inactive>-')
	  .'CONT</span> '
	  .($this->Status_DoExamine()?'<span state=active>+':'<span state=inactive>-')
	  .'EXAM</span> '
	  .' &rarr; '
	  ;
 
	// then the human-readable description (which can be more confusing sometimes):
	if ($this->IsActive()) {
	    $out .=
	      ($this->Status_DoHoldRestock()?'[hold restock]':NULL)
	      .($this->Status_DoHoldCharge()?'[hold charge]':NULL)
	      .($this->Status_DoContact()?'[do contact]':NULL)
	      .($this->Status_DoExamine()?'[do examine]':NULL)
	      ;
	} else {
	    $out .= '(all released)';
	}
	return $out;
    }
    public function RenderSummary() {
	$out = $this->TypeShortString();
	$sAbout = $this->AboutString();
	if (!is_null($sAbout)) {
	    $out .= ': '.$sAbout;
	}
	if ($this->IsRelease()) {
	    return "<s>$out</s> (released)";
	} else {
	    return $out;
	}
    }
    private $isOdd=FALSE;
    public function AdminRow() {
	$isOdd = ($this->isOdd = !$this->isOdd);
	$cssClass = $isOdd?'odd':'even';
	
	$row = $this->GetFieldValues();
//	$id = $row['ID'];
	$htID = $this->SelfLink();
	$sWhat = $this->RenderSummary();
	$sWhen = $this->Timestamp();
	$sNotes = $this->NotesString();	// TODO: what is difference between e.Descrip and h.Notes?

	$sStatus = $this->IsRelease()?'RELEASE':'(pull)';

	// 
	$out = <<<__END__
  <tr class="$cssClass">
    <td>$htID: $sStatus</td>
    <td>$sWhat</td>
    <td>$sWhen</td>
    <td>$sNotes</td>
  </tr>
__END__;
	return $out;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ OUTPUT ++ //

    /*----
      RETURNS: string consisting of a compilation of descriptions of all active Hold records
      TODO: This will needs some reworking if it's going to do what the name implies.
	For now, it shows all holds.
    */
    public function ListActiveHolds() {
	$out = NULL;
	while ($this->NextRow()) {
	    if (!is_null($out)) {
		$out .= '; ';
	    }
	    $out .= $this->RenderSummary();
	}
	return $out;
    }
    // NOTE: There is no production usage-case for needing to edit these directly, so this is just a detail viewer.
    public function AdminPage() {
	$id = $this->GetKeyValue();
	$sWhen = $this->GetFieldValue('WhenStart');
	$htSess = $this->SessionRecord()->SelfLink();
	$htUser = $this->UserRecord()->SelfLink();
	$sAbout = $this->AboutString();
	$isRelease = $this->IsRelease();
/*	$sFlags = 
	  ($isRelease?'-(release)':'+HOLD')
	  .' &rarr; '
	  .$this->RenderStatus()
	  ; */
	$sStatus = $this->RenderStatus();
	$sNotes = $this->NotesString();
    
	$out = <<<__END__
<table class=record-block>
  <tr><td class=form-label>ID</td><td>: $id</td></tr>
  <tr><td class=form-label>Timestamp</td><td>: $sWhen</td></tr>
  <tr><td class=form-label>Session</td><td>: $htSess</td></tr>
  <tr><td class=form-label>User</td><td>: $htUser</td></tr>
  <tr><td class=form-label>About</td><td>: $sAbout</td></tr>
  <tr><td class=form-label>Status</td><td>: $sStatus</td></tr>
  <tr><td class=form-label>Notes</td><td>: $sNotes</td></tr>
</table>
__END__;

	return $out;
    }

    // -- OUTPUT -- //
}

/*
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
    * /
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
    * /
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
    * /
    public function AddPull($idOrder,$idType,$sNotes) {
	$idPull = $this->Add($idOrder,$idType,$sNotes);
	return $idPull;
    }

    // -- ACTIONS -- //
    // ++ WEB UI ++ //

    /*----
      ACTION: render a combobox of pull-types
      PUBLIC so Order objects can use it
    * /
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
    * /
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
*/