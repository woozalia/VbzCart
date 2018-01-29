<?php
/*
  LIBRARY: place.php - classes for managing stock places
  HISTORY:
    2010-11-03 extracted classes from SpecialVbzAdmin.php:
      VbzStockPlace(s), VbzStockBin(s), VbzStockBinLog, VbzStockBinEvent, VbzAdminStkItem(s), clsStkLog, vcrStockEvent (was clsStockEvent)
    2013-11-30 adapting from admin.stock.php (MW admin version)
    2014-03-22 renamed stock.php -> place.php; split Bin classes off into bin.php
*/
/* *****
 STOCK MANAGEMENT
*/

class vctAdminStockPlaces extends vcAdminTable {
    use ftExecutableTwig;
    use ftLoggableTable;

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'stk_places';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrAdminStockPlace';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_STOCK_PLACE;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE: CACHE TABLES ++ //

    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */ /* 2017-03-27 I don't think we're using cache tables anymore.
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	//$this->CacheStamp();
	$this->Touch();
    } */
    /*----
      ACTION: update the cache record to show that this table has been changed
      NOTES:
	Must be public so it can be called by recordset type.
      HISTORY:
	2014-08-05 commented out as redundant
	2014-08-09 ...but apparently it's not; does not seem to be defined anywhere else.
    */ /* 2017-03-27 I don't think we're using cache tables anymore.
    public function CacheStamp() {
	$objCache = $this->Engine()->CacheMgr();
	$objCache->UpdateTime_byTable($this);
    } */

    // -- BOILERPLATE: CACHE TABLES -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Stock Places');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminListing();
    }

    // -- EVENTS -- //
    // ++ RECORDS ++ //

    protected function SelectRecords_active() {
	return $this->SelectRecords('isActivated','Name');	// sort by Name
    }
    protected function SelectRecords_sorted() {
	return $this->SelectRecords(NULL,'Name');	// sort by Name
    }
    /*----
      USED BY: Bin record admin page
      HISTORY: 
	2017-04-20 Renamed from GetData_forDropDown() -> SelectRecords_forDropDown()
	2017-06-30 Changed field list to *, because fields have changed and this is more maintainable.
    */
    public function SelectRecords_forDropDown($onlyActive) {
	$sqlActv = $onlyActive?' WHERE isActivated':NULL;	// "isActivated" = thisPlace + parentalPlaces
	//$sql = 'SELECT ID, Name, isSelfActive, isActiveSpace, isActivated, ID_Parent FROM '.$this->TableName_cooked().$sqlActv;
	// refine later if needed
	$sql = 'SELECT * FROM '.$this->TableName_cooked().$sqlActv.' ORDER BY Name';
	return $this->FetchRecords($sql);
    }

    // -- RECORDS -- //
    // ++ INTERNAL STATES ++ //

    private $arChg;
    protected function ResetStatusUpdates() {
	$this->arChg = array();
    }
    // PUBLIC so recordset can access it
    public function RememberStatusUpdate(vcrAdminStockPlace $rc) {
	$id = $rc->GetKeyValue();
	$this->arChg[$id] = $rc->GetFieldValues();
    }
    protected function GetUpdateCount() {
	return count($this->arChg);
    }
    protected function RenderUpdates() {
	$rc = $this->SpawnRecordset();
	$nChg = count($this->arChg);
	if ($nChg > 0) {
	    $out = $nChg.' status update'.fcString::Pluralize($nChg).':';
	    $ht = NULL;
	    $s = NULL;
	    foreach ($this->arChg as $id => $arPlace) {
		$rc->SetFieldValues($arPlace);
		$ch = $rc->IsActivated()?'+':'-';
		$ht .= ' '.$ch.$rc->SelfLink();
		$s .= ' '.$ch.$rc->GetKeyValue();
	    }
	    $arOut = array(
	      'html'	=> $ht,
	      'text'	=> $out.$s
	      );
	} else {
	    $out = 'There were no status updates.';
	    $arOut = array(
	      'html'	=> $out,
	      'text'	=> $out
	      );
	}
	return $arOut;
    }
    public function LogUpdateResults($idEvent) {
	$arOut = $this->RenderUpdates();
	if ($this->GetUpdateCount() > 0) {
	    fcApp::Me()->FinishEvent($idEvent,KS_EVENT_SUCCESS,$arOut['text']);
	} else {
	    fcApp::Me()->FinishEvent($idEvent,KS_EVENT_NO_CHANGE,'no changes were needed');
	}
	return '<div class=content>'.$arOut['html'].'</div>';
    }
    
    // -- INTERNAL STATES -- //
    // ++ WEB UI COMPONENTS ++ //

    //-----
    public function DropDown($iName,$iDefault=NULL,$iFilt=NULL,$iSort='Name') {
	throw new exception('2017-04-20 Does anything still call this?');
	$rsRows = $this->GetData($iFilt,NULL,$iSort);
	return $rsRows->DropDown($iName,$iDefault);
    }

    // -- WEB UI COMPONENTS -- //
    // ++ WEB UI SECTIONS ++ //

    //++forms++//
    
    // 2017-04-22 This will need updating.
    private function AdminListing_handleAdd($idParent) {
	$sName = $_REQUEST['plName'];
	$sDesc = $_REQUEST['plDesc'];
	$db = $this->Engine();
	
	$hasParent = !empty($idParent);
	
	$sqlParent = $hasParent?$db->SanitizeAndQuote($idParent):'NULL';
	$arIns = array(
	  'Name'	=> $db->SanitizeAndQuote($sName),
	  'Descr'	=> $db->SanitizeAndQuote($sDesc),
	  'ID_Parent'	=> $sqlParent
	  );
	  
	$sEvent = "Adding new Place [$sName]";
	if ($hasParent) {
	    $sParent = $this->ParentRecord()->NameString();
	    $sEvent .= " inside [$sParent]";
	}
	$arEv = array(
	  'descr'	=> $sEvent,
	  'where'	=> __METHOD__,
	  'code'	=> '+PL'
	  );

	if ($hasParent) {
	    $rcParent = $this->GetItem($idParent);
	    $rcEv = $rcParent->CreateEvent($arEv);
	}
	
	$id = $this->Insert($arIns);
	
	if ($hasParent) {
	    $arEv['id']	= $id;
	    $rcEv->Finish($arEv);
	}
	$rcThis = $this->GetItem($id);
	$rcEv = $rcThis->CreateEvent($arEv);
	$rcEv->Finish();
	
	return $sEvent.': ID='.$id;
    }

    //--forms--//
    
    // -- WEB UI SECTIONS -- //
    // ++ WEB UI PAGES ++ //

    /*-----
      ACTION: Show list of all Places, optionally within the given parent Place
    */
    public function AdminListing($idParent=0) {
	$isPage = ($idParent == 0);
	$oFormIn = fcHTTP::Request();	

	// header/section & menu

	if ($isPage) {
	    $oMenu = fcApp::Me()->GetHeaderMenu();
	} else {
	    $oMenu = new fcHeaderMenu();
	    $oHdr = new fcSectionHeader('Sub-Locations',$oMenu);
	}
	
	// + menu
	
	// ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('do','Actions'));
	  
	    $oGrp->SetChoice($ol = new fcHeaderChoice('new','create a new Place'));
	      $doAdd = $ol->GetIsSelected();
	      
	    $oGrp->SetChoice($ol = new fcHeaderChoice('recalc','recalculate activation status for all Places'));
	      $doRecalc = $ol->GetIsSelected();
	  
	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Manage'));
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('flat','show as flat listing'));
	      $doFlat = $ol->GetIsSelected();
	    $oGrp->SetChoice($ol = new fcHeaderChoice('inact','include inactive places','inactive'));
	      $doInact = $ol->GetIsSelected();
	    
	// - menu
	
	if ($isPage) {
	    $out = NULL;
	} else {
	    $out = $oHdr->Render();
	}
	    
	// - header
	
	$doAddSave = $oFormIn->GetBool('btnAddPlace');
	
	// check for changes via form submission
	if ($doAddSave) {
	    $sMsg = $this->AdminListing_handleAdd($idParent);
	    $this->SelfRedirect(NULL,$sMsg);
	}
	
	if ($doRecalc) {
	    $idEv = fcApp::Me()->CreateEvent(KS_EVENT_VBZCART_CASCADE_UPDATE,'auto-updating activation for all Places');
	
	    // ASSUMES: There are records.
	    $rs = $this->SelectRecords();
	    $ar = $rs->FetchRows_asKeyedArray();
	    $rc = $this->SpawnRecordset();
	    $this->ResetStatusUpdates();
	    
	    foreach ($ar as $id => $arRow) {
		$rc->SetFieldValues($arRow);
		if (!$rc->HasParent()) {
		    $rc->UpdateActivation();
		}
	    }

	    $out .= $this->LogUpdateResults($idEvent);
	}

	// get a list of all the Places, so we can then organize it hierarchically:
	if ($doInact) {
	    $rsPlaces = $this->SelectRecords_sorted();
	} else {
	    $rsPlaces = $this->SelectRecords_active();
	}

	$gotSome = FALSE;

	if ($doAdd) {
	    $out .= (new fcSectionHeader('Add New Place'))->Render()
	      . <<<__END__
<form method=post>
  <table class=content>
    <tr><td align=right><b>Name</b>:</td>	<td><input name=plName size=8></td></tr>
    <tr><td align=right><b>Description</b>:</td><td><input name=plDesc size=30></td></tr>
    <tr><td colspan=2 align=center><input type=submit name=btnAddPlace value="Add Place"></td></tr>
  </table>
</form>
__END__;
	    $out .= (new fcSectionHeader('Existing'))->Render();
	}
	if ($doFlat) {
	    $out .= $rsPlaces->AdminRows();
	} else {
	    $out .= $rsPlaces->AdminTree($idParent);
	}
	/*
	if ($rsPlaces->hasRows() || $doAdd) {
	    while ($rsPlaces->NextRow()) {
		$idParent = $rsPlaces->Value('ID_Parent');
		$idRow = $rsPlaces->GetKeyValue();
		$arData[$idRow] = $rsPlaces->Values();
		if (is_null($idParent)) {
		    $arTree[0][] = $idRow;
		} else {
		    $arTree[$idParent][] = $idRow;
		}
	    }
	    $hasRows = isset($arTree[$idParent]);
	    $doTable = $hasRows || $doAdd;
	    if ($doTable) {
		$gotSome = TRUE;
		if ($doAdd) {
		    $out .= '<form method=post>';
		}
		$out .= "\n<table><tr><th>ID</th><th>name</th><th>description</tr>";

		// render the listing
		$out .= $this->AdminListing_renderSubs($arData,$arTree,$idParent);

		if ($doAdd) {
		    $out .= '<tr><td><i>new</i></td><td><input name=plName size=8></td><td><input name=plDesc size=30></td></tr>';
		}
		$out .= "\n</table>";
		if ($doAdd) {
		    $out .= '<center><input type=submit name=btnAddPlace value="Add Place"></center>';
		    $out .= '</form>';
		}
	    }
	}
	if (!$gotSome) {
	    $out .= 'none found';
	} */
	
	if ($isPage) {
	    $out .= $this->EventListing();
	    // If this is a section of someone else's page, let them do the event listing appropriate for that context.
	}
	
	return $out;
    }
    
    // -- WEB UI PAGES -- //
}
class vcrAdminStockPlace extends vcAdminRecordset implements fiEventAware {
    use ftLoggableRecord;
    use ftLoggedRecord;		// automatically log edits
    use ftExecutableTwig;
    use ftShowableRecord;

    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$id = $this->GetKeyValue();
	$sName = $this->NameString();
	$sTitle = "plc$id: $sName";
	$htTitle = "Place #$id: $sName";

	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle('Suppliers');
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ FIELD VALUES ++ //

    public function ParentID() {
	return $this->GetFieldValue('ID_Parent');
    }
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    protected function IsSelfActive() {
	return $this->GetFieldValue('isSelfActive');
    }
    /*----
      PURPOSE: stored value of IsActiveSpace() && IsSelfActive()
      PUBLIC so Bin admin UI can fetch it
    */
    public function IsActivated() {
	//throw new exception('2017-06-30 This field no longer exists.'); Actually, it does. 2017-09-04
	return $this->GetFieldValue('isActivated');
    }
    /*----
      PUBLIC so Stock Bin can read it
      NOTE: 2017-05-07 Very tempted to rename this IsSpaceActive(), for consistency with IsSelfActive().
    */
    public function IsActiveSpace() {
	return $this->GetFieldValue('isActiveSpace');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    // CALLBACK for dropdown list
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    // CALLBACK for dropdown list
    public function ListItem_Text() {
	if ($this->HasParent()) {
	    $rcParent = $this->ParentRecord();
	    $sParent = ' &larr; '.$rcParent->NameString();
	} else {
	    $sParent = '';
	}
	return $this->GetKeyValue().': '.$this->NameString().$sParent;
    }
    /*----
      NOTES:
	* Think of this as ShouldSelfBeMarkedAsBeingInsideActiveSpace().
	* This should only be used by single-record admin fx().
	  Normally, activation status flags should never go out of sync
	  because contained spaces are updated whenever record is saved.
    */
    public function IsParentActive() {
	if ($this->HasParent()) {
	    return $this->ParentRecord()->IsSelfActive();
	} else {
	    return TRUE;	// root space is definitionally active
	}
    }
    /*----
      2017-04-22 This is now OBSOLETE.
      RETURNS; TRUE if this place AND all ancestors are marked active (isActive=TRUE).
	If any ancestors are isActive=FALSE, then this one is not active even if it is
	marked isActive=TRUE. This lets us control activation for an entire area and all its contents
	with one change.
    */
    public function IsActive() {
	throw new exception('2017-04-22 Call IsSelfActive() or IsActiveSpace() instead.');
	if ($this->GetFieldValue('isActive')) {
	    if ($this->HasParent()) {
		return $this->ParentRecord()->IsActive();
	    } else {
		return TRUE;
	    }
	} else {
	    return FALSE;
	}
    }
    /*----
      PURPOSE: determine whether this Place is inside another Place
      RETURNS: TRUE if this object has a non-null ID_Parent, FALSE otherwise
      HISTORY:
	2017-04-22 Split off the descends-from overloaded functionality into IsInside(),
	  and made both methods PROTECTED until need for PUBLIC is documented
    */
    protected function HasParent() {
	return !is_null($this->ParentID());
    }
    /*----
      RETURNS: TRUE if $rcPlace is an ancestor of this object, FALSE otherwise
    */
    protected function IsInside(vcrAdminStockPlace $rcPlace) {
	if ($this->HasParent()) {
	    if ($this->ParentID() == $rcPlace->GetKeyValue()) {
		return TRUE;
	    } else {
		$rcParent = $this->ParentRecord();
		return $rcParent->IsInside($rcPlace);
	    }
	} else {
	    return FALSE;
	}
    }
    protected function GetParentLink() {
	if ($this->HasParent()) {
	    return $this->ParentRecord()->SelfLink_name();
	} else {
	    return '(root)';
	}
    }
    /*----
      ACTION: Returns name plus some parental context
    */
    public function NameLong() {
	throw new exception('2017-05-07 Is anything calling this?');
	$out = $this->NameString();
	if ($this->HasParent()) {
	    $out .= ' &larr; '.$this->Table()->GetItem($this->ParentID())->NameString();
	}
	return $out;
    }
    public function NameLong_text() {
	throw new exception('2017-05-07 Is anything calling this?');
	$out = $this->NameString();
	if ($this->HasParent()) {
	    $out .= ' < '.$this->Table()->GetItem($this->ParentID())->NameString();
	}
	return $out;
    }
    public function SelfLink_name() {
	$ftLink = $this->SelfLink($this->NameString());
	if ($this->IsSelfActive()) {
	    $htStyleActv = '';
	    $sStatus = 'self is active';
	} else {
	    $htStyleActv = 'text-decoration: line-through;';
	    $sStatus = 'self is not active';
	}
	if ($this->HasParent()) {
	    if (!$this->ParentRecord()->IsSelfActive()) {
		$htStyleActv .= ' background-color: #aaaaaa;';
		$sStatus .= ', parent is not active';
	    }
	}
	return "<span style='$htStyleActv' title='$sStatus'>$ftLink</span>";
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ CLASSES ++ //
    
    protected function BinsClass() {
	return KS_CLASS_STOCK_BINS;
    }
    protected function LCItemsClass() {
	return KS_ADMIN_CLASS_LC_ITEMS;
    }
    
    // -- CLASSES -- //
    // ++ TABLES ++ //

    
    // TODO: need a BinInfoTable fx()
    protected function BinTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->BinsClass(),$id);
    }
    protected function BinInfoTable() {
    	return $this->GetConnection()->MakeTableWrapper('vcqtAdminStockBinsInfo');
    }
    protected function LCItemTable($id=NULL) {
	return $this->Engine()->Make($this->LCItemsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      HISTORY:
	2010-11-30 Added object caching
	2016-01-22 changed from public to protected
	2017-09-03 rewrote so parent is properly fetched for new rows;
	  $rcParent is now private.
    */
    private $rcParent=NULL;
    protected function ParentRecord() {
	if ($this->HasParent()) {
	    $doFetch = FALSE;
	    if (is_null($this->rcParent)) {
		$doFetch = TRUE;
	    } elseif ($this->ParentID() != $this->rcParent->GetKeyValue()) {
		$doFetch = TRUE;
	    }
	    if ($doFetch) {
		$tPlaces = $this->GetTableWrapper();
		$this->rcParent = $tPlaces->GetRecord_forKey($this->ParentID());
	    }
	} else {
	    $this->rcParent = NULL;
	}
	return $this->rcParent;
    }
    /*----
      RETURNS: recorset of potential parent records for this record
	We basically do as much filtering as possible without doing any JOINs:
	get only records which aren't self AND don't have self as parent.
	This won't prevent reference-loops, but makes them less likely and trims the list down a bit.
      USED BY: drop-down list
    */
    protected function PotentialParentRecords() {
	$id = $this->GetKeyValue();
	// get records which aren't self and don't have self as parent
	$sqlFilt = "(ID != $id) AND (IFNULL(ID_Parent,0) != $id)";
	$rs = $this->GetTableWrapper()->SelectRecords($sqlFilt,'Name');
	return $rs;
    }
    /*----
      RETURNS: recordset of Places directly contained by this one
    */
    protected function SelectContainedRecords() {
	return $this->GetTableWrapper()->SelectRecords('ID_Parent='.$this->GetKeyValue());
    }
    /*----
      RETURNS: dataset of Bins in the current Place
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function BinsData() {
	$sqlFilt = 'ID_Place='.$this->GetKeyValue();
	$tbl = $this->BinTable();
	$rs = $tbl->GetData($sqlFilt);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: count all stock in the current Place
      RETURNS: array of Item quantities
	array[item id] = quantity in stock
      OUTPUT:
	$this->ftNoStk: formatted text listing any bins with no stock
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function CountStock() {
	$arOut = NULL;
	$ftNoStk = NULL;
	$rs = $this->BinsData();
	while ($rs->NextRow()) {
	    $arBin = $rs->CountStock();
	    if (is_array($arBin)) {
		foreach ($arBin as $idItem => $qty) {
		    $qtySum = clsArray::Nz($arOut,$idItem);
		    $arOut[$idItem] = $qtySum + $qty;
		}
	    } else {
		$ftNoStk .= '['.$rs->SelfLink_name().']';
	    }
	}
	$this->StockCount_NoStock_text($ftNoStk);
	return $arOut;
    }

    // -- CALCULATIONS -- //
    // ++ CALCULATION RESULTS ++ //
    
    private $ftNoStk;
    protected function StockCount_NoStock_text($s=NULL) {
	if (!is_null($s)) {
	    $this->ftNoStk = $s;
	}
	return $this->ftNoStk;
    }

    // -- CALCULATION RESULTS -- //
    // ++ DATA WRITE ++ //
    
    /*----
      ACTION:
	* Update isActiveSpace based on $doActive
	* Update isActivated based on isActiveSpace and isActiveSelf
	* For each contained Place, call UpdateActivation().
      INPUT:
	$doActive should be equal to $this->IsParentActive().
	  It exists to minimize repeat lookups of parent records
	  while we're recursing through the tree (we can pass down
	  the value already known, rather than requiring each child
	  to look it up). In other words, if you know the value,
	  then pass it; otherwise, don't.
    */
    public function UpdateActivation($doActive=NULL) {
	if (is_null($doActive)) {
	    $doActive = $this->IsParentActive();
	}
	$doActivate = $doActive && $this->IsSelfActive();
	$isChg = $this->IsActivated() != $doActivate;
	if ($isChg) {
	    $arOld = $this->GetFieldValues();
	    $arUpd = array(
	      'isActiveSpace'	=> $doActive,
	      'isActivated'	=> $doActivate
	      );
	    $this->Update($arUpd);
	    $arData = array(
	      'before'	=> $arOld,
	      'change'	=> $arUpd
	      );
	    fcApp::Me()->CreateEvent(KS_EVENT_VBZCART_CASCADE_UPDATE,'activation recalculated',$arData);
	    $this->GetTableWrapper()->RememberStatusUpdate($this);
	}
	$rs = $this->SelectContainedRecords();
	while ($rs->NextRow()) {
	    $rs->UpdateActivation($doActivate);
	}
    }

    // -- DATA WRITE -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: update the cache record to show that this table has been changed
    */
    /*
    protected function CacheStamp() {
	$this->Table()->CacheStamp();
    }
    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */ /*
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp(__METHOD__);
    }

    // -- ACTIONS -- //
    // ++ WEB UI COMPONENTS ++ //

    /*-----
      ACTION: Show a dropdown list consisting of the rows in the current dataset
      USED BY: Moving a list of Bins in a Place
    */
    public function DropDown($sName,$vDefault=NULL) {
    
	$arRows = NULL;
	if ($this->HasRows()) {
	    while ($this->NextRow()) {
		$key = $this->GetKeyValue();
		$val = $this->NameLong_text();
		$arRows[$key] = $val;
	    }
	    $out = fcHTML::DropDown_arr($sName,$arRows,$vDefault,$sChoose=NULL);
	} else {
	    $out = 'No locations matching filter';
	}
    
    /* 2016-01-21 old version
	$ofPlace = new clsFieldNum($sName,$vDefault);
	$ofPlace->ValStore($vDefault);
	$arRows = NULL;
	if ($this->HasRows()) {
	    while ($this->NextRow()) {
		$key = $this->GetKeyValue();
		$val = $this->NameLong_text();
		$arRows[$key] = $val;
	    }
	}
	$ocDrop = new clsCtrlHTML_DropDown(NULL,$arRows);
	$ocDrop->Field($ofPlace);
	$ocDrop->Text_NoRows('No locations matching filter');
	$out = $ocDrop->Render();
/* even older version
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    while ($this->NextRow()) {
		$out .= DropDown_row($this->ID,$this->NameLong_text(),$iDefault);
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No locations matching filter';
	}
*/
	return $out;
    }
    /*-----
      ACTION: Render a dropdown list of all rows, with the current row as the default
    */
    public function DropDown_meDefault($iName,$useInactive) {
	if ($useInactive) {
	    $rs = $this->Table()->GetData(NULL,NULL,'Name');	// sort by Name
	} else {
	    $rs = $this->Table()->GetRecords_active();
	    if (!is_object($rs)) {
		throw new exception('Internal error: no Place data found.');
	    }
	}
	return $rs->DropDown($iName,$this->GetKeyValue());
    }
    /*----
      ACTION: Render a dropdown list of all rows, except:
	* do not include the current row
	* do not include any descendants of this row
	Also, use ID_Parent as the default.
    */
    public function DropDown_meParent($iName) {
	throw new exception('2017-04-22 Is anything still calling this?');
	$rs = $this->Table()->GetData('ID != '.$this->GetKeyValue(),NULL,'Name');
	while ($rs->NextRow()) {
	    if (!$rs->IsInside($this)) {
		$arRows[$rs->GetKeyValue()] = $rs->NameLong_text();
	    }
	}
	return fcHTML::DropDown_arr($iName,$arRows,$this->ParentID(),'--ROOT--');
    }
    /*-----
      ACTION: Show table of all Places inside this Place (via Places->AdminListing())
      TODO: Rename to something that suggests rendered output rather than an array.
    */
    public function SubsList() {
	$tPlaces = $this->GetTableWrapper();
	$out = $tPlaces->AdminListing($this->GetKeyValue());
	return $out;
    }
    /*-----
      ACTION: Show table of all Bins inside this Place (via Bins->Listing())
      TODO: Rename to something that suggests rendered output rather than an array.
    */
    public function BinsList() {
	$tBins = $this->BinInfoTable();
	$out = $tBins->List_forPlace($this->GetKeyValue());
	return $out;
    }

    // -- WEB UI COMPONENTS -- //
    // ++ WEB UI PAGES ++ //

    /*-----
      ACTION: Display information about the current Place
    */
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$sName = $this->GetFieldValue('Name');
	$sTitle = 'Stock Location '.$this->GetKeyValue().': '.$sName;

	fcApp::Me()->GetPageObject()->SetPageTitle($sTitle);

	$oMenu = fcApp::Me()->GetHeaderMenu();

	  // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,NULL,'edit record for '.$sName));
	    $doEdit = $ol->GetIsSelected();
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','update',NULL,NULL,'update calculated status flags (this and contents)'));
	    $doUpd = $ol->GetIsSelected();
	    $olUpd = $ol;
          $oMenu->SetNode($ol = new fcMenuOptionLink('do','inv',NULL,NULL,'list all inventory in '.$sName));
	    $doInv = $ol->GetIsSelected();

	$doSave = $oFormIn->GetBool('btnSave');

	$out = NULL;
	
	$frm = $this->PageForm();
	if ($doSave) {
	    throw new exception('2017-04-22 Now we need to update status of all contained Places.');
	    $id = $frm->Save();
	    $sMsgs = $frm->MessagesString();
	    if (!is_null($sMsgs)) {
		$sMsgs .= '<br>';
	    }
	    $sMsgs .= "Place ID $id saved.";
	    $this->SelfRedirect(NULL,$sMsgs);
	}

	if ($doUpd) {
	    // update this and all contained spaces, with logging

	    // - log that we're doing this
	    $sName = $this->NameString();
	    $idEv = fcApp::Me()->CreateEvent(KS_EVENT_VBZCART_CASCADE_UPDATE,"updating activations for Place '$sName'");
	    $this->UpdateActivation();		// do the updates
	    $out .= $this->GetTableWrapper()->LogUpdateResults($idEv);	// log the results
	}
	
	if ($doInv) {
	    // for now, this only looks at bins in the immediate location
	    // later, we might want to allow for iterating through sub-locations too
	    $oHdr = new fcSectionHeader('Inventory');
	    $out .= $oHdr->Render();

	    $arStk = $this->CountStock();
	    ksort($arStk);
	    $tbl = $this->LCItemTable();
	    foreach ($arStk as $idItem => $qty) {
		$rc = $tbl->GetItem($idItem);
		$out .= ' '.($rc->SelfLink()).':'.$qty;
	    }

	    if (!is_null($this->ftNoStk)) {
		$out .= '<div class=content><b>No stock found</b> in: '
		  .$this->ftNoStk
		  .'</div>'
		  ;
	    }
	}

	// Set up rendering objects

	if ($this->IsNew()) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}

	if (!$this->isActiveSpace() && !$this->HasParent()) {
	    // if we're root, then space is definitionally active - ask user to recalculate
	    $arCtrls['!SpaceStatus'] = '<span class=error>= ERROR!</span> '.$olUpd->Render();
	} else {
	    $arCtrls['!SpaceStatus'] = '(calculated)';
	}
	
	// render the template
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '</form>';
	}

	/*
	$out .= "\n<table>";
	if ($doEdit) {
	    $out .= "\n<form method=post>";

	    $oForm = $this->EditForm();

	    $ctrlParent = $this->DropDown_meParent('ID_Parent');

	    $ctrlActv = $oForm->RenderControl('isActive');
	    $ctrlName = $oForm->RenderControl('Name');
	    $ctrlDescr = $oForm->RenderControl('Descr');

	} else {
	    if ($this->HasParent()) {
		$rcParent = $this->ParentRecord();
		$ctrlParent = $rcParent->SelfLink_name();
	    } else {
		$ctrlParent = 'root';
	    }
	    $ctrlActv = fcString::NoYes($this->Value('isActive'));
	    $ctrlName = $strName;
	    $ctrlDescr = fcString::EncodeForHTML($this->Value('Descr'));
	}
	if ($this->HasParent()) {
	    $isEnabled = $this->Value('isEnabled');		// enabled flag is set?
	    $doEnabled = $this->ParentRecord()->IsActive();	// enabled flag *should be* set?
	    $ctrlActv .= ' <b>Enabled</b>: '.fcString::NoYes($isEnabled);
	    if ($isEnabled != $doEnabled) {
		//$arLink = $vgPage->Args(array('page','id'));
		//$arLink['update'] = TRUE;
		$urlUpd = $oPage->SelfURL(array('update'=>TRUE),TRUE);

		$ctrlActv .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.fcString::NoYes($doEnabled);
	    }
	}

	$out .= <<<__END__
  <tr><td align=right><b>Active</b></td><td>$ctrlActv</td></tr>
  <tr><td align=right><b>Parent</b></td><td>$ctrlParent</td></tr>
  <tr><td align=right><b>Name</b></td><td>$ctrlName</td></tr>
  <tr><td align=right><b>Description</b></td><td>$ctrlDescr</td></tr>
__END__;

	if ($doEdit) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '</form>';
	}
	$out .= "\n</table>";

	if ($doInv) {
	    // for now, this only looks at bins in the immediate location
	    // later, we might want to allow for iterating through sub-locations too
	    $out .= $oPage->ActionHeader('Inventory');

	    $arStk = $this->CountStock();
	    ksort($arStk);
	    $tbl = $this->Engine()->Items();
	    foreach ($arStk as $idItem => $qty) {
		$obj = $tbl->GetItem($idItem);
		$out .= ' '.($obj->AdminLink()).':'.$qty;
	    }

	    if (!is_null($this->ftNoStk)) {
		$out .= '<br><b>No stock found</b> in: '.$this->ftNoStk;
	    }
	} */

	$out .= 
	  $this->BinsList()
	  .$this->SubsList()
	  .$this->EventListing()
	  ;

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=content>
  <tr><td align=right><b>Name</b>:</td><td>[#Name#]</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>[#Descr#]</td></tr>
  <tr><td align=right><b>Parent</b>:</td><td>[#ID_Parent#]</td></tr>
  <tr><td align=right><b>Active Self</b>:</td><td>[#isSelfActive#]</td></tr>
  <tr><td align=right><b>+ Active Space</b>:</td><td>[#isActiveSpace#] [#!SpaceStatus#]</td></tr>
  <tr><td align=right><b>= Activated</b>:</td><td>[#isActivated#] (calculated)</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      ACTION: build admin edit form controls
    */
    private $oForm;
    protected function PageForm() {
	// create fields & controls

	if (empty($this->oForm)) {
	
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_Parent');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->SetRecords($this->PotentialParentRecords());
		$oCtrl->AddChoice(NULL,'(none)');
	
	      $oField = new fcFormField_BoolInt($oForm,'isSelfActive');
	      $oField = new fcFormField_BoolInt($oForm,'isActiveSpace');
	      $oField = new fcFormField_BoolInt($oForm,'isActivated');
	      $oField = new fcFormField_Text($oForm,'Name');
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>50));
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    /*----
      INPUT:
	$idStart = ID of record to start from -- only show that record and its descendants
	  if 0, show entire tree
    */
    public function AdminTree($idStart=0) {
	$out = NULL;

	if ($this->hasRows()) {
	
	// build the data reference arrays
	
	    while ($this->NextRow()) {
		$idParent = $this->ParentID();
		$id = $this->GetKeyValue();
		$arData[$id] = $this->GetFieldValues();
		if (is_null($idParent)) {
		    $arTree[0][] = $id;
		} else {
		    $arTree[$idParent][] = $id;
		}
	    }
	    
	    $hasRows = array_key_exists($idStart,$arTree);
	    $doTable = $hasRows;
	    if ($doTable) {
		$out .= "\n<table class=listing><tr><th>ID</th><th>name</th><th>description</tr>";

		// render the listing
		$out .= $this->AdminTree_sub($arData,$arTree,$idStart);

		$out .= "\n</table>";
	    }
	}
	if (is_null($out)) {
	    $out .= '<div class=content>none found</div>';
	}
	return $out;
    }
    private $isOdd = FALSE;
    protected function AdminTree_sub(array $arData, array $arTree, $idBranch=0, $nInd=0) {
	$out = '';

	if (array_key_exists($idBranch,$arTree)) {
	    foreach($arTree[$idBranch] AS $key => $id) {
		$arPlace = $arData[$id];
		$this->SetFieldValues($arPlace);
		$htPlace = $this->SelfLink_name();
		$sInd = str_repeat('&ndash;&nbsp;',$nInd);
		$sDesc = $this->GetFieldValue('Descr');
		$this->isOdd = !($isOdd = $this->isOdd);
		$css = $isOdd?'odd':'even';
		$out .= <<<__END__
		
<tr class=$css>
  <td>$id</td>
  <td><font style="color: grey;">$sInd</font>$htPlace</td>
  <td>$sDesc</td>
</tr>
__END__;
		$out .= $this->AdminTree_sub($arData,$arTree,$id,$nInd+1);
	    }
	}
	return $out;
    }
    protected function AdminRows_settings_columns() {
	$arCols = array(
	  'ID'		=> 'ID',
	  'ID_Parent'	=> 'Parent',
	  'isActive'	=> 'Active?',
	  'Name'	=> 'Name',
	  'Descr'	=> 'Description'
	  );
	return $arCols;
    }
    protected function AdminField($sField) {
	switch($sField) {
	  case 'ID':
	    $ht = $this->SelfLink();
	    break;
	  case 'ID_Parent':
	    $ht = $this->GetParentLink();
	    break;
	  case 'isActive':
	    $ht = $this->Render_ActiveStatus();
	    break;
	  default:
	    $ht = $this->GetFieldValue($sField);
	}
	return "<td>$ht</td>";
    }

    // -- ADMIN WEB UI -- //

}