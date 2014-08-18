<?php
/*
  LIBRARY: admin.cache.php - administration of cache management tables using MW UI
  HISTORY:
    2010-11-08 extracting from SpecialVbzAdmin
    2010-11-09 renaming "data" to "cache"
	Also renamed everything "Proc" to "Query", but then later decided this was not
	  as great an idea as I originally thought because procs are really the way to do this.
    2010-11-11 renaming "query" back to "proc"
*/

class clsAdminCacheMgr extends clsCacheMgr {
// STATIC methods -- these should later be methods of descendent classes of the component tables
    static public function PageLink($iName,$iPfx) {
	global $vgOut;

	return $vgOut->InternalLink($iPfx.$iName,$iName);
    }
    static public function TableLinkDoc($iName) {
	return self::PageLink($iName,kwp_DocTblPfx);
    }
    static public function ProcLinkDoc($iName) {
	return self::PageLink($iName,kwp_DocPrcPfx);
    }
// OVERRIDE methods
    protected function NewTblTables($iName) {
	return new clsAdminCacheTables($this, $iName);
    }
    protected function NewTblProcs($iName) {
	return new clsAdminCacheProcs($this,$iName);
    }
    protected function NewTblFlows($iName) {
	return new clsAdminCacheFlows($this,$iName);
    }
    protected function NewTblEvents($iName) {
	return new clsAdminCacheEvents($this,$iName);
    }
// DYNAMIC methods
    public function MenuDispatch($iAction,$iID=NULL) {
	switch ($iAction) {
	  case 'cache':
	    $this->AdminPage();
	    break;
	  case 'cache.tbl':
	    $obj = $this->Tables->GetItem($iID);
	    $obj->AdminPage();
	    break;
	  case 'cache.proc':
	    $obj = $this->Procs->GetItem($iID);
	    $obj->AdminPage();
	    break;
	}
    }
    public function AdminPage() {
	global $wgRequest, $wgOut;
	global $vgPage, $vgOut;
	$vgPage->UseWiki();

	$out = '==Cache Management=='."\n";
	$wgOut->addWikiText($out,TRUE);	$out = '';

	$dbVBZ = $this->Engine();

// check for action requests
	$strDo = $vgPage->Arg('do');
	//$strTbls = $vgPage->Arg('id.table');
	if ($strDo != '') {
	    $idTbl = $vgPage->Arg('id');
	    if (is_numeric($idTbl)) {
		$objTbl = $this->Tables->GetItem($idTbl);
	    }
	    switch ($strDo) {
	      case 'update':
		$out .= $objTbl->UpdateData_Verbose();
		$out .= '<br>Update of '.$objTbl->AdminLink_Name().' complete.';
		break;
	      case 'stamp':
		$arRes = $objTbl->UpdateTime(__METHOD__);
		$out .= 'Stamped '.$objTbl->AdminLink_Name();
		$out .= "\n* '''Was''': ".$arRes['was'];
		$out .= "\n* '''SQL''': ".$arRes['sql'];
		break;
	      case 'clear':
		$arRes = $objTbl->ClearTime(__METHOD__);
		$out .= 'Cleared '.$objTbl->AdminLink_Name();
		$out .= "\n* '''Was''': ".$arRes['was'];
		$out .= "\n* '''SQL''': ".$arRes['sql'];
		break;
/*	      case 'table-info':
		$out = $this->AdminTable($idTbl);
		break;
	      case 'table-update':
		$out = $this->UpdateTable($idTbl,'Special:VbzAdmin');
		break;
	      case 'table-stamp';
		$out = $dbVBZ->StampTable($idTbl);
		break;
*/
	    }
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	} else {
// display the current status
	    $out = '===Cache Tables==='."\n";
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	    //$out = $this->ShowTables($strTbls);
	    $out = $this->ShowTables();
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	    $out = '===Cache Procedures==='."\n";
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	    $this->ShowProcs();
	}
	$dbVBZ->Shut();
    }

    public function ShowProcs() {
	global $vgPage,$vgOut;

	$vgPage->UseHTML();

	$tblProcs = $this->Procs;

	$objRows = $tblProcs->DataSet();	// get all rows

	if ($objRows->hasRows()) {
	    $out = $vgOut->TableOpen('class=sortable');
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('procedure name');
	      $out .= $vgOut->TblCell('A?');
	      $out .= $vgOut->TblCell('Clr?');
	      $out .= $vgOut->TblCell('notes');
	    $out .= $vgOut->TblRowShut();
	    while ($objRows->NextRow()) {
		$out .= $vgOut->TblRowOpen();
		  //$ftName = $this->ProcLinkDoc($objRows->Name);
		  $ftName = $objRows->AdminLink_Name();
		  $isActive = $objRows->Value('isActive');
		  if (!$isActive) {
		      $ftName = '<s>'.$ftName.'</s>';
		  }
		  $out .= $vgOut->TblCell($ftName);
		  $out .= $vgOut->TblCell($isActive?'&radic;':'-');
		  $out .= $vgOut->TblCell(($objRows->Value('doesClear'))?'&radic;':'-');
		  $out .= $vgOut->TblCell($objRows->Value('Notes'));
		$out .= $vgOut->TblRowShut();
	    }
	    $out .= $vgOut->TableShut();
	} else {
	    $out .= 'No procedures found!';
	}
	$vgOut->AddText($out);
    }
    /*----
      FUTURE:
	Rename ShowTables() -> AdminTables()
	Move most of the code into clsAdminTables
    */
    public function ShowTables() {
	global $vgPage,$vgOut;

	$objPage = $vgPage;
	$vgPage->UseWiki();

	//$objTbls = $this->objDB->DataSet('SELECT * FROM '.$this->Tables->Name().' WHERE Name != "" ORDER BY Name');
	$objTbls = $this->Tables->GetData('Name != ""',NULL,'Name');

	if ($objTbls->hasRows()) {
	    $out = "{| class=sortable \n|-\n! table name || last update || sources || targets || notes";
	    while ($objTbls->NextRow()) {
		if ($objTbls->Value('isActive')) {
		    //$strTbl = $objTbls->Name;
		    $idTbl =  $objTbls->KeyValue();

		    $objSrces = $this->Sources($idTbl);
		    $objTargs = $this->Targets($idTbl);
		    $cntSrces = $objSrces->RowCount();
		    $cntTargs = $objTargs->RowCount();

// table info open/shut calculations
//		    $doInfo = isset($lstTbls[$idTbl]);

		    $htTblName = $objTbls->AdminLink_Name();

		    // common elements in action links
		    $arLink = $vgPage->Args(array('page'));
		    $arLink['id'] = $idTbl;

// source table list:
		    if ($cntSrces) {
/*
			$arLink = array(
			  'id'	=> $idTbl,
			  'do'	=> 'table-update'
			  );
			$txtSrces .= $objPage->SelfLink($arLink,'update').' ('.$cntSrces.')';
*/
			$arLink['do'] = 'update';
			$ftLink = $vgOut->SelfLink($arLink,'update','update this table from its sources');
			$txtSrces = $cntSrces.'&rarr;'.$ftLink;
		    } else { $txtSrces = ''; }
// target table list:

		    if ($cntTargs) {
			$arLink['do'] = 'clear';
			$ftLink1 = $vgOut->SelfLink($arLink,'un','clear the time-updated stamp for this table');

			$arLink['do'] = 'stamp';
			$ftLink2 = $vgOut->SelfLink($arLink,'stamp','update the time-updated stamp for this table');

			$txtTargs = '('.$ftLink1.')'.$ftLink2.'&rarr;'.$cntTargs;
		    } else { $txtTargs = ''; }

		    $out .= "\n|-\n| ".$htTblName. ' || ' . $objTbls->Value('WhenUpdated') .
		      ' || '.$txtSrces.
		      ' || '.$txtTargs.
		      ' || '.$objTbls->Value('Notes');

		    // if table is selected to show info, add a row for that:
/*
		    if ($doInfo) {
			$out .= "\n|-\n| colspan=5 |";
			if ($objSrces->hasRows()) {
			    $out .= "\n* '''Sources''':";
			    while ($objSrces->NextRow()) {
				$objSrce = 	$objDataMgr->Tables->GetItem($objSrces->ID_Srce);
				if ($objSrce->isActive) {
				    $strName = $objSrce->Name;
				    $out .= ' [['.kwp_DocTblPfx.$strName.'|'.$strName.']]';
				} else {
				    $out .= " ''N/A id=".$objSrces->ID_Srce."''";
				}
			    }
			}
			if ($objTargs->hasRows()) {
			    $out .= "\n* '''Targets''':";
			    while ($objTargs->NextRow()) {
				$objTarg = 	$objDataMgr->Tables->GetItem($objTargs->ID_Dest);
				if ($objTarg->isActive) {
				    $strName = $objTarg->Name;
				    $out .= ' [['.kwp_DocTblPfx.$strName.'|'.$strName.']]';
				} else {
				    $out .= " ''N/A id=".$objTargs->ID_Dest."''";
				}
			    }
			}
		    }
*/
		}
	    }
	    $out .= "\n|}";
	} else {
	    $out .= 'ERROR: Mysterious lack of data';
	}
	return $out;
    }
}
class clsAdminCacheTables extends clsCacheTables {
    protected $arMap,$arCkd;

    public function __construct(clsCacheMgr $iMgr,$iName) {
	parent::__construct($iMgr,$iName);
	  $this->ClassSng('clsAdminCacheTable');
	  $this->ActionKey('cache.tbl');
    }

// INFORMATION methods

    /*----
      RETURNS: recordset of targets with more recently-updated sources
      HISTORY:
	2011-12-21 created for update-cat maintenance script
    */
    public function UpdatedTargets() {
	// map out the cache and find out what needs to be run initially
	Write('Mapping the cache...');
	$rcFlows = $this->Mgr()->Flow->GetData();	// get table of all flows
	$this->arMap = $rcFlows->FullMap();
	$this->arCkd = array();

	WriteLn(' ok');
	$arRun = $this->arMap['run'];
	$cntRun = count($arRun);
	if ($cntRun > 0) {
	    WriteLn('Starting with '.$cntRun.' update'.Pluralize($cntRun).':');
	    $this->RunUpdates($arRun);
	}
    }
    /*----
      INPUT:
	$arRun: array of procs that need to be run
      NOTE: This does not take a rigorous approach to preventing loops.
	It just cuts off execution after the same Proc has been executed
	more than a preset number of times. There's probably a very tidy
	algorithmic way to figure out what order everything should be executed
	in while rigorously preventing recursion, but I don't have time
	to figure it out right now.
      HISTORY:
	2011-12-22 created for command line utility
	2011-12-24 allowing limited number of duplicate Procs
    */
    public function RunUpdates(array $arRun) {
	$tblT = $this->Mgr()->Tables;
	$rcT = $tblT->SpawnItem();
	$arDone = array();
	do {
	    $isMore = FALSE;
	    $arRunNext = array();

	    // display run list
	    WriteLn("----------\nTO RUN:");
	    foreach ($arRun as $idP => $objP) {
		$txt = ' '.$objP->NameFull();
		$msg = NULL;
		if (array_key_exists($idP,$arDone)) {
		    $cnt = $arDone[$idP];
		    if ($cnt > 2) {
			$msg = ' - LOOP DETECTED; quitting.';
			die($txt.$msg."\n");
		    }
		    $msg = ' - repeat #'.$cnt;
		    $arDone[$idP]++;
		} else {
		    $arDone[$idP] = 1;
		}
		WriteLn($txt.$msg);
	    }

	    foreach ($arRun as $idP => $objP) {
		Write('PROC: '.$objP->NameFull());
		$arEx = $objP->Execute(TRUE);	// FALSE = debug mode -- don't actually write data
		WriteLn(' - '.$arEx['text']);

		if (array_key_exists('targ',$arEx)) {
		    $arT = $arEx['targ'];	// target tables updated
		    if (count($arT) > 0) {
    //		    WriteLn(' - updated:');
			// get list of procs that supply those tables
			foreach ($arT as $idT => $rowT) {
			    WriteLn(' - updated TABLE: ['.$idT.'] '.$rowT['Name']);
			    $rcT->Values($rowT);
			    $arP = $rcT->TargProcs();
			    if (is_array($arP)) {
				foreach ($arP as $dummy => $rowP) {
				    WriteLn(' -- used by PROC: '.$rowP->NameFull());
				}
				$arRunNext = ArrayJoin($arRunNext,$arP,TRUE,TRUE);	// replace = yes (probably n/a), append = yes
    //			    $isMore = TRUE;
			    }
			}
		    }
		}

		if (array_key_exists($idP,$arRunNext)) {
		    WriteLn(' - removing '.$objP->NameFull().' from next run list');
		    unset($arRunNext[$idP]);
		    $txt = '';
		    foreach ($arRunNext as $idP => $objP) {
			$txt .= ' '.$idP;
		    }
		    WriteLn(' - list is now:'.$txt);
		}

	    }
	    // ultimately, this list may need to be sorted too:
	    $arRun = $arRunNext;
	    $isMore = Count($arRun) > 0;
/*
	    Write('TO RUN NEXT:');
	    foreach ($arRun as $id => $objP) {
		Write(' '.$objP->Name());
	    }
	    WriteLn();
*/
	} while ($isMore);
    }
/*
    protected function FindFlowSources(array $iSrce) {
	$arOut = array();
	foreach ($iSrce as $id => $obj) {
//	    Write(' -- source ID='.$id.' - '.$obj->Value('Name'));
	    if ($obj->IsActive()) {
//		WriteLn('');
		$ar = $this->FindRoots($obj);
		foreach ($ar as $id => $row) {
		    if (!array_key_exists($id,$arOut)) {
			$arOut[$id] = $row;
		    }
		}
	    } else {
//		WriteLn(' - INACTIVE');
	    }
	}
	return $arOut;
    }
*/
}
class clsAdminCacheTable extends clsCacheTable {

// BOILERPLATE methods

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_Name($iPopup=NULL,array $iarArgs=NULL) {
	return $this->AdminLink($this->Value('Name'),$iPopup,$iarArgs);
    }
    public function DocLink() {
	return $this->Mgr()->TableLinkDoc($this->Value('Name'));
    }

// ACTION methods

    /*----
      ACTION: Run $this->UpdateData() and display results
    */
    public function UpdateData_Verbose() {
	global $vgPage, $vgUserName;

	$out = '';
	//$arRes = $this->Update_byID($iID,$vgUserName.': '.__METHOD__);
	$arRes = $this->UpdateData($vgUserName.': '.__METHOD__);
	$out .= $arRes['msgs'];
	if (is_array($arRes['proc'])) {
	    $arDone = $arRes['proc'];
	    $out .= "\n===Update Procedures Run===\n";
	    foreach($arDone AS $obj) {
		$out .= "\n* ".$obj->Value('Name');
	    }
	} else {
	    $out .= '<br>No procedures were executed.';
	}
	if (is_array($arRes['targ'])) {
	    $arDone = $arRes['targ'];
	    $out .= "\n===Tables Updated===\n";
	    foreach($arDone AS $id=>$row) {
		$out .= "\n* ".$row['Name'];
	    }
	} else {
	    $out .= '<br>No tables were updated.';
	}
	return $out;
    }

// ADMIN DISPLAY methods
    public function AdminPage() {
	global $wgRequest;
	global $vgPage,$vgOut;

	$vgPage->UseWiki();

	$out = '==Table: '.$this->Value('Name')."==\n";

	$out .= "'''Note''': ADD and DELETE have not yet been tested, and they do not yet log changes.\n";

	$idTbl = $this->Value('ID');
	$out .= '===Table Record===';
	$out .= "\n* '''ID''': $idTbl";
	$out .= "\n* '''Name''': ".$this->DocLink();
	$out .= "\n* '''Updated''': ".$this->Value('WhenUpdated');
	$out .= "\n* '''Notes''': ".$this->Value('Notes');

	$vgOut->AddText($out); $out='';

// check for any form input (adding flows/procs)
	$doAddSrce = $wgRequest->getBool('btnAddSrce');
	$doAddTarg = $wgRequest->getBool('btnAddTarg');
	if ($doAddSrce || $doAddTarg) {
	    $idProc = $wgRequest->getBool('ID_Proc');
	    $doWrite = $doAddSrce;	// if proc is a source, then it writes to this table
	    $txtNotes = $wgRequest->getText('Notes');
	    $this->Mgr()->Flows->Add($iProc,$this->ID,$doWrite,$txtNotes);
	}

	$vgPage->UseHTML();

// SOURCES cell (left):
	$out .= $vgOut->Header('Table Sources',3);
	$out .= $this->AdminSources();

// TARGETS cell (right):
	$out .= $vgOut->Header('Table Targets',3);
	$out .= $this->AdminTargets();

	$vgOut->AddText($out);
    }
    public function AdminSources() {
	$objRows = $this->Mgr()->Sources($this->KeyValue());
	return $objRows->AdminList('btnAddSrce');
    }
    public function AdminTargets() {
	$objRows = $this->Mgr()->Targets($this->KeyValue());
	return $objRows->AdminList('btnAddTarg');
    }
}
class clsAdminCacheProcs extends clsCacheProcs {
    public function __construct(clsCacheMgr $iMgr,$iName) {
	parent::__construct($iMgr,$iName);
	  $this->ClassSng('clsAdminCacheProc');
	  $this->ActionKey('cache.proc');
    }
}
class clsAdminCacheProc extends clsCacheProc {
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_Name($iPopup=NULL,array $iarArgs=NULL) {
	return $this->AdminLink($this->Value('Name'),$iPopup,$iarArgs);
    }
    public function DocLink() {
	return $this->Mgr()->ProcLinkDoc($this->Value('Name'));
    }
    public function AdminPage() {
	global $vgPage,$vgOut;

	$vgPage->UseHTML();

	$out = $vgOut->Header('Procedure: '.$this->Value('Name'));

	$out .= '<ul>';
	$out .= '<li> <b>ID</b>: '.$this->KeyValue();
	$out .= '<li> <b>Name</b>: '.$this->DocLink();
	$out .= '<li> <b>Active</b>: '.NoYes($this->Value('isActive'));
	$out .= '<li> <b>Clears</b>: '.NoYes($this->Value('doesClear'));
	$out .= '</ul>';

	$vgOut->AddText($out); $out='';

	$rcFlows = $this->Mgr()->Flow->Data_forProc($this->KeyValue());
	if ($rcFlows->HasRows()) {
	    $ar = $rcFlows->PoolMap();
/*
	    while ($rcFlows->NextRow()) {
		$idTbl = $rcFlows->Value('ID_Table');
		$objTbl = $this->Mgr()->Tables->GetItem($idTbl);
		if ($rcFlows->Value('doWrite')) {
		    // the proc writes to these tables
		    $arTarg[$idTbl] = $objTbl;
		} else {
		    // the proc reads from these tables
		    $arSrce[$idTbl] = $objTbl;
		}
	    }
*/
	    $arTarg = $ar['targ'];
	    $arSrce = $ar['srce'];
	    $dtNewest = NULL;
	    $dtOldest = NULL;

	    $out .= $vgOut->Header('Source Tables',3);
	    if (isset($arSrce)) {
		$out .= '<ul>';
		foreach ($arSrce as $id => $objTbl) {
		    $dtThis = $objTbl->Value('WhenUpdated');
		    if ($dtThis > $dtNewest) {
			$dtNewest = $dtThis;
		    }
		    $strTime = (is_null($dtThis)?'<i>never updated</i>':('updated '.$dtThis));
		    $out .= '<li>'.$objTbl->AdminLink_Name().' - '.$strTime;
		}
		$out .= '</ul>';
	    } else {
		$out .= 'No sources found.';
	    }

	    $out .= $vgOut->Header('Target Tables',3);
	    if (isset($arTarg)) {
		$out .= '<ul>';
		foreach ($arTarg as $id => $objTbl) {
		    $dtThis = $objTbl->Value('WhenUpdated');
		    if (($dtThis < $dtOldest) || (is_null($dtOldest))) {
			$dtOldest = $dtThis;
		    }
		    $strTime = (is_null($dtThis)?'<i>never updated</i>':('updated '.$dtThis));
		    $out .= '<li>'.$objTbl->AdminLink_Name().' - '.$strTime;
		}
		$out .= '</ul>';
	    } else {
		$out .= 'No targets found.';
	    }
	    $out .= $vgOut->Header('Status',3);
	    if ($dtOldest < $dtNewest) {
		$out .= '<b>Update needed.</b>';
	    } else {
		$out .= 'This table is up-to-date.';
	    }
	} else {
	    $out .= 'This procedure is not used.';
	}

	$vgOut->AddText($out);
    }
}
class clsAdminCacheFlows extends clsCacheFlows {
    public function __construct(clsCacheMgr $iMgr,$iName) {
	parent::__construct($iMgr,$iName);
	  $this->ClassSng('clsAdminCacheFlow');
	  $this->ActionKey('cache.flow');
    }
}
class clsAdminCacheFlow extends clsCacheFlow {
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Render administrative controls for the current dataset of flows
      INPUT: name of button for adding another row.
	If no name given, does not display button.
      RETURNS: formatted text using $vgOut
    */
    public function AdminList($iAddBtn=NULL) {
	global $vgOut;

	$out = NULL;
	if ($this->hasRows()) {
	    $out .= $vgOut->TableOpen('class=sortable');
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	    $out .= $vgOut->TblCell('Procedure');
	    $out .= $vgOut->TblCell('Action');
	    $out .= $vgOut->TblCell('notes');
	    $out .= $vgOut->TblRowShut();

	    $doBtn = !is_null($iAddBtn);

	    $objMgr = $this->Mgr();
	    assert('is_object($this->objMgr)');
	    $objProcs = $objMgr->Procs;
	    $arProcs = NULL;

	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$row = $this->Row;

		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$htStyle = 'style="'.$ftStyle.'"';
		$isOdd = !$isOdd;

		$idProc = $this->Value('ID_Proc');
		$objProc = $objProcs->GetItem($idProc);
		$ftProc = $objProc->AdminLink_Name();

		// exclusion array for drop-down:
		$arProcs[$idProc] = TRUE;

		$ftAct = $this->AdminLink('del');

		$ftNotes = $this->Value('Notes');

		$out .= $vgOut->TblRowOpen($htStyle);
		$out .= $vgOut->TblCell($ftProc);
		$out .= $vgOut->TblCell($ftAct);
		$out .= $vgOut->TblCell($ftNotes);
		$out .= $vgOut->TblRowShut();
	    }

	    // display a row for adding a proc
	    if ($doBtn) {
		$out .= '<form method=POST>';
		$out .= $vgOut->TblRowOpen();
		$out .= $vgOut->TblCell($objProcs->DropDown('ID_Proc',NULL,$arProcs));
		$out .= $vgOut->TblCell('<input type=submit name="'.$iAddBtn.'" value="Add">');
		$out .= $vgOut->TblCell('<input name="notes">');
		$out .= $vgOut->TblRowShut();
		$out .= '</form>';
	    }
	    $out .= $vgOut->TableShut();
	} else {
	    $out .= 'none';
	}
	return $out;
    }
}

class clsAdminCacheEvents extends clsCacheEvents {
    public function __construct(clsCacheMgr $iMgr,$iName) {
	parent::__construct($iMgr,$iName);
	  $this->ClassSng('clsAdminCacheEvent');
	  $this->ActionKey('cache.event');
    }
}
class clsAdminCacheEvent extends clsCacheEvent {
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
}