<?php
/*
  FILE: admin.events.php -- handling of MediaWiki-oriented event logging
    Originally written to work with FinanceFerret, but should be compatible with standard event tables.
    Any app-specific code should be moved out into descendant classes.
  HISTORY:
    2010-04-06 clsAdminTable, clsAdminData, clsAdminData_Logged, clsAdminEvents, clsAdminEvent written (in menu.php)
    2010-10-25 clsAdminEvents, clsAdminEvent, clsAdminData_Logged extracted from menu.php
    2013-12-07 rewriting as drop-in module
    2014-02-05 renaming *Syslog to *SysEvents
*/
/*%%%%
  CLASS: clsAdminSyslog
  PURPOSE: Admin interface to system logs
  NOTE that this descends from the event *table* class, not the event *helper* class.
*/
class VCM_Syslog extends clsSysEvents {
    private $rsType;
    private $nMaxRows;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	assert('is_object($iDB)');
	// these defaults are all overridable - must match actual event table schema
	parent::__construct($iDB);
	  $this->Name('event_log');
	  $this->KeyName('ID');
	  $this->ClassSng('VC_SysEvent');	// override parent
	  $this->ActionKey('event');
	$this->rsType = NULL;
	$this->MaxLines(100);			// TODO: make this configurable
    }

    // -- SETUP -- //
    // ++ STATIC ++ //

    static public function SpawnTable(clsVbzData $iEngine,$id=NULL) {
	return $iEngine->Make(__CLASS__,$id);
    }

    // -- STATIC -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ STATUS/OPTIONS ACCESS ++ //

    public function MaxLines($nMax=NULL) {
	if (!is_null($nMax)) {
	    $this->nMaxRows = $nMax;
	}
	return $this->nMaxRows;
    }

    // -- STATUS/OPTIONS ACCESS -- //
    // ++ ADMIN UI ++ //

    protected function AdminPage() {
/* 2014-06-05 does anything call EventListing() directly?
	return $this->EventListing();
    }
    public function EventListing($sTableKey=NULL,$idTableRow=NULL,$iDebug=FALSE) {
	$rsEv = $this->EventData($sTableKey,$idTableRow,$iDebug);
*/
	$rsEv = $this->EventData();
	if ($rsEv->HasRows()) {
	    $out = $rsEv->AdminRows(TRUE);
	} else {
	    $out = 'No events found here.';
	}
	return $out;
    }

    // -- ADMIN UI -- //

    /*----
      RETURNS: dataset consisting of events related to the specific DatSet object given
    */
/* 2014-02-18 this belongs in the base class if it belongs anywhere
    public function EventData($iDebug=FALSE) {
	$rs = $this;
	$sql = '(ModType="'.$rs->Table()->ActionKey().'")';
	if (!$rs->IsNew()) {
	    $sql .= ' AND (ModIndex='.$rs->KeyValue().')';
	}
	if (!$iDebug) {
	    $sql .= ' AND (NOT isDebug)';
	}
	$sql .= ' ORDER BY WhenStarted DESC, WhenFinished DESC';
	return $this->GetData($sql);
    }
    */
/* duplicate functionality
    public function ListPage() {
	global $wgOut;

	$objRow = $this->GetData(NULL,NULL,'ID DESC');
	if ($objRow->hasRows()) {
	    $out = $objRow->AdminRows();
	} else {
	    $out = 'No events logged yet.';
	}
	$wgOut->addWikiText($out,TRUE);
    }
*/
    /*-----
      RETURNS: event arguments translated into field names for use in Insert()
      NOTE: Shouldn't this method be static?
    */
/*
    private function CalcSQL($iArgs) {
	if (is_null($iArgs)) {
	    return NULL;
	} else {
	    foreach ($iArgs as $key=>$val) {
		switch ($key) {
		  case 'descr':
		    $sqlKey = 'Descr';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'notes':
		    $sqlKey = 'Notes';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'type':
		    $sqlKey = 'ModType';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'id':
		    $sqlKey = 'ModIndex';
		    $sqlVal = SQLValue($val);	// can be NULL
		    break;
		  case 'where':
		    $sqlKey = 'EvWhere';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'code':
		    $sqlKey = 'Code';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'params':
		    $sqlKey = 'Params';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'error':
		    $sqlKey = 'isError';
		    $sqlVal = SQLValue($val);
		    break;
		  case 'severe':
		    $sqlKey = 'isSevere';
		    $sqlVal = SQLValue($val);
		    break;
		}
		$arIns[$sqlKey] = $sqlVal;
	    }
	    return $arIns;
	}
    }
*/
    /*-----
      ACTION: Logs an event from specs in an array
      INPUT: Array containing any of the following elements:
	'descr': description of event
	'type': type of event (one of the kType* class constants)
	'id': ID of row in table corresponding to event type
	'where': location in code where event is taking place (usually __METHOD__ will do)
	'code': event code unique to type
	'error' (value ignored): if present, event represents an error
	'severe' (value ignored): if present, event represents a severe error
    */
/*
    public function StartEvent(array $iArgs) {
	global $vgUserName;

	$arIns = $this->CalcSQL($iArgs);
	if (empty($arIns)) {
	    return NULL;
	} else {
	    $arIns['WhenStarted'] = 'NOW()';
	    $arIns['WhoNetwork'] = SQLValue($_SERVER['REMOTE_ADDR']);
	    $arIns['WhoAdmin'] = SQLValue($vgUserName);
	    $ok = $this->Insert($arIns);
	    if ($ok) {
		return $this->objDB->NewID(__METHOD__);
	    } else {
		return NULL;
	    }
	}
    }
    public function FinishEvent($iEvent,array $iArgs=NULL) {
	if (is_array($iArgs)) {
	    $arUpd = $this->CalcSQL($iArgs);
	    //$arUpd = array_merge($arUpd,$iArgs);
	}
	$arUpd['WhenFinished'] = 'NOW()';
	$this->Update($arUpd,'ID='.$iEvent);
    }
*/
}
class VC_SysEvent extends clsSysEvent {

    // ++ WEB ADMIN UI ++ //

    /*----
      INPUT:
	$doGeneral: TRUE = show Mod and Index columns
    */
    protected static function AdminRowHeader($doGeneral) {
	$out = "\n<table class=listing><tr><th>ID</th>";
	if ($doGeneral) {
	    $out .= '<th>ModType</th><th>ModIndex</th>';
	}
	$out .= '<th>Start</th><th>Finish</th><th>Who/How</th><th>Where</th></tr>';
	return $out;
    }
    static $sUnknownField = '<span style="color: #888888;">?</span>';
    static $sDateLast;
    /*----
      ASSUMES: there are rows (caller should check this)
      INPUT:
	$doGeneral: TRUE = show Mod and Index columns
      HISTORY:
	2011-03-24 added call to UseHTML()
	  Data is apparently too long to show all at once now; something Clever is needed.
	2013-12-08 adapting from clsAdminEvent (MW event admin class)
    */
    public function AdminRows($doGeneral) {
	if ($this->HasRows()) {
	    //$htUnknown = '<span style="color: #888888;">?</span>';
	    $out = self::AdminRowHeader($doGeneral);

	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    $nRows = 0;
	    $nMax = $this->Table()->MaxLines();
	    $isOver = FALSE;
	    self::$sDateLast = NULL;
	    while ($this->NextRow()) {
		$nRows++;
		if ($nRows > $nMax) {
		    $isOver = TRUE;
		    break;
		}

		$cssStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$out .= $this->AdminRow($doGeneral,$cssStyle);
	    }
	    $out .= "\n</table>";
	    if ($isOver) {
		$nTotal = $this->RowCount();
		$out .= "Showing only $nMax of $nTotal rows.<br>";
	    }
	} else {
	    $out = 'No events found. ';
	}
	$out .= '<b>SQL</b>: '.$this->sqlMake;

	return $out;
    }
    protected function AdminRow($doGeneral,$cssStyle) {
	$row = $this->Values();

	$strSysUser	= $row['WhoAdmin'];
	$strVbzUser	= $row['WhoSystem'];
	$strMachine	= $row['WhoNetwork'];
	$htSysUser	= is_null($strSysUser)?(self::$sUnknownField):$strSysUser;
	$htMachine	= is_null($strMachine)?(self::$sUnknownField):$strMachine;
	$htVbzUser	= is_null($strVbzUser)?(self::$sUnknownField):$strVbzUser;

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

	$out = NULL;

	if ($strDate != self::$sDateLast) {
	  // date header
	    self::$sDateLast = $strDate;
	    $out .= <<<__END__
  <tr class=date-header>
    <td colspan=5><b>$strDate</b></td>
  </tr>
__END__;
	}

	if ($doGeneral) {
	    // data-record columns
	    $sModType = $row['ModType'];
	    $sModIndex = $row['ModIndex'];
	    $htDataCols = <<<__END__
    <td>$sModType</td>
    <td>$sModIndex</td>
__END__;
	} else {
	    $htDataCols = NULL;
	}

	// first 2 columns
	$out .= <<<__END__
  <tr style="$cssStyle">
    <td>$id</td>$htDataCols
    <td>$strTimeSt</td>
    <td>$strTimeFi</td>
    <td>$htWho</td>
    <td><small>$strWhere</small></td>
  </tr>
__END__;

	if (!empty($ftDescr)) {
	    $out .= <<<__END__
  <tr style="$cssStyle">
    <td></td>
    <td colspan=5><b>Mission</b>: $ftDescr</td>
  </tr>
__END__;
	}

	if (!empty($ftDescrFin)) {
	    $out .= <<<__END__
  <tr style="$cssStyle">
    <td></td>
    <td colspan=5><b>Results</b>: $ftDescrFin</td>
  </tr>
__END__;

	}

	if (!empty($strParams)) {
	    $out .= <<<__END__
  <tr style="$cssStyle">
    <td></td>
    <td colspan=5><b>Params</b>: $strParams</td>
  </tr>
__END__;
	}

	if (!empty($strNotes)) {
	    $out .= <<<__END__
  <tr style="$cssStyle">
    <td></td>
    <td colspan=5><b>Notes</b>: $strNotes</td>
  </tr>
__END__;
	}

	return $out;
    }
}
/*
class VC_Syslog_RecordSet_helper {
    private $tLog, $rsData;

    public function __construct(clsSysEvents $tLog, clsDataSet $rsData) {
	$this->tLog = $tLog;
	$this->rsData = $rsData;
    }

    // ++ BOILERPLATE API ++ //

    public function EventListing() {
	$rs = $this->EventRecords();
	return $rs->AdminList();
    }
    public function StartEvent(array $iArgs) {
    }
    public function FinishEvent(array $iArgs=NULL) {
    }

    // -- BOILERPLATE API -- //
    // ++ DATA OBJECT ACCESS ++ //

    protected function LogTable() {
	return $this->tLog;
    }
    protected function DataRecords() {
	return $this->rsData;
    }

    // -- DATA OBJECT ACCESS -- //
    // ++ BUSINESS LOGIC ++ //
    protected function EventRecords() {
	$sTblAct = $this->DataRecords()->Table->ActionKey();
	$idRecord = $this->DataRecords()->KeyValue();
	$sqlFilt = '(ModType="'.$sTblAct.'") AND (ModIndex='.$idRecord.')';
	$rs = $this->LogTable()->GetData($sqlFilt,'WhenStarted,WhenFinished');
	return $rs;
    }
}
*/