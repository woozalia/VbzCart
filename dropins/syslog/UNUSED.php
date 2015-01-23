<?php
/*
  These are classes that have been removed from syslog.php
  as it was being adapted from events.php.
*/
class clsAdminEvent extends clsAdminData {
    /*----
      ASSUMES: there are rows (caller should check this)
      HISTORY:
	2011-03-24 added call to UseHTML()
	  Data is apparently too long to show all at once now; something Clever is needed.
    */
    public function AdminRows() {
	global $vgPage, $vgOut;

	$vgPage->UseHTML();

	$htUnknown = '<span style="color: #888888;">?</span>';
	$out = $vgOut->TableOpen();
	$out .= $vgOut->TblRowOpen(NULL,TRUE);
	$out .= $vgOut->TblCell('ID');
	$out .= $vgOut->TblCell('Start');
	$out .= $vgOut->TblCell('Finish');
	$out .= $vgOut->TblCell('Who/How');
	$out .= $vgOut->TblCell('Where');
	$out .= $vgOut->TblRowShut();
	$isOdd = TRUE;
	$strDateLast = NULL;
	$objRow = $this;
	while ($objRow->NextRow()) {
	    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
	    $isOdd = !$isOdd;

	    $row = $objRow->Row;

	    $strSysUser	= $row['WhoAdmin'];
	    $strVbzUser	= $row['WhoSystem'];
	    $strMachine	= $row['WhoNetwork'];
	    $htSysUser	= is_null($strSysUser)?$htUnknown:$strSysUser;
	    $htMachine	= is_null($strMachine)?$htUnknown:$strMachine;
	    $htVbzUser	= is_null($strVbzUser)?$htUnknown:$strVbzUser;

	    $ftDescr	= $row['Descr'];
	    $ftDescrFin	= $row['DescrFin'];
	    $strNotes	= $row['Notes'];
	    $id		= $row['ID'];
	    $strWhenSt	= $row['WhenStarted'];
	    $strWhenFi	= $row['WhenFinished'];
	    $strWhere	= $row['EvWhere'];
	    $htWho		= $htVbzUser.'/'.$htSysUser.'@'.$htMachine;
	    $strParams	= $row['Params'];

	    $dtWhenSt	= strtotime($strWhenSt);
	    $dtWhenFi	= strtotime($strWhenFi);
	    $strDate	= date('Y-m-d',empty($dtWhenSt)?$dtWhenFi:$dtWhenSt);
	    $strTimeSt = empty($dtWhenSt)?'':date('H:i',$dtWhenSt);
	    $strTimeFi = empty($dtWhenFi)?'':date('H:i',$dtWhenFi);
	    if ($strDate != $strDateLast) {
		$strDateLast = $strDate;
		$out .= $vgOut->TblRowOpen('style="background: #444466; color: #ffffff;"');
		$out .= $vgOut->TblCell("<b>$strDate</b>",'colspan=5');
		$out .= $vgOut->TblRowShut();
	    }

	    $out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
	    $out .= $vgOut->TblCell($id);
	    $out .= $vgOut->TblCell($strTimeSt);
	    $out .= $vgOut->TblCell($strTimeFi);
	    $out .= $vgOut->TblCell($htWho);
	    $out .= $vgOut->TblCell("<small>$strWhere</small>");
	    if (!empty($ftDescr)) {
		$out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
		$out .= $vgOut->TblCell('');
		$out .= $vgOut->TblCell("<b>Mission</b>: $ftDescr",'colspan=5');
		$out .= $vgOut->TblRowShut();
	    }
	    if (!empty($ftDescrFin)) {
		$out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
		$out .= $vgOut->TblCell('');
		$out .= $vgOut->TblCell("<b>Results</b>: $ftDescrFin",'colspan=5');
		$out .= $vgOut->TblRowShut();
	    }
	    if (!empty($strParams)) {
		$out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
		$out .= $vgOut->TblCell('');
		$out .= $vgOut->TblCell("<b>Params</b>: $strParams",'colspan=5');
		$out .= $vgOut->TblRowShut();
	    }
	    if (!empty($strNotes)) {
		$out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"');
		$out .= $vgOut->TblCell('');
		$out .= $vgOut->TblCell("<b>Notes</b>: $strNotes",'colspan=5');
		$out .= $vgOut->TblRowShut();
	    }
	}
	$out .= $vgOut->TableShut();
	return $out;
    }
}
/*====
 2010-10-20 This should be deprecated or rewritten to use clsLogger_DataSet
*/
abstract class clsAdminData_Logged extends clsAdminData {
    //protected $idEvent;
    protected $logger;

    //abstract protected function Events();		// RETURNS a clsAdminEvents object
    /*=====
      INPUT: Array containing any of the following elements:
	'descr': description of event
	'where': location in code where event is taking place (usually __METHOD__ will do)
	'code': event code unique to type
	'error' (value ignored): if present, event represents an error
	'severe' (value ignored): if present, event represents a severe error
    */
/*
 2010-11-02 replacing these with helper class calls
    public function StartEvent(array $iarArgs) {
	$arArgs = $iarArgs;
	$arArgs['type'] = $this->Table->ActionKey;	// TO DO: de-couple this from URL keys
	$arArgs['id'] = $this->KeyValue();
	$this->idEvent = $this->Events()->StartEvent($arArgs);
	return $this->idEvent;
    }
    public function FinishEvent(array $iarArgs=NULL) {
	$this->Events()->FinishEvent($this->idEvent,$iarArgs);
	unset($this->idEvent);
    }
*/
    /*====
      SECTION: event logging
      HISTORY:
	2010-11-02 replacing direct code with helper class calls
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
/*
    public function EventListing() {
	$objRows = $this->Events()->ObjData($this);
	if ($objRows->HasRows()) {
	    $out = $objRows->AdminRows();
	} else {
	    $out = 'No events found for this.';
	}
	return $out;
    }
    public static function _EventListing(clsDataSet $iObj) {
	$obj = new clsAdminData_Logged();
	CopyObj($iObj,$obj);
	return $obj->EventListing();
    }
*/
}
