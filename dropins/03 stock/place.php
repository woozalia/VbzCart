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

/*%%%%
  TODO: Add cacheing. This was originally descended from a cached-table class which is now deprecated.
*/
class VCM_StockPlaces extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'stk_places';
    }
    // CEMENT
    protected function SingularName() {
	return 'VCM_StockPlace';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_STOCK_PLACE;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE: CACHE TABLES ++ //

    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	//$this->CacheStamp();
	$this->Touch();
    }
    /*----
      ACTION: update the cache record to show that this table has been changed
      NOTES:
	Must be public so it can be called by recordset type.
      HISTORY:
	2014-08-05 commented out as redundant
	2014-08-09 ...but apparently it's not; does not seem to be defined anywhere else.
    */
    public function CacheStamp() {
	$objCache = $this->Engine()->CacheMgr();
	$objCache->UpdateTime_byTable($this);
    }

    // -- BOILERPLATE: CACHE TABLES -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminListing();
    }
    protected function Arg($sName) {
	throw new exception('Does anything call this?');
	if (is_array($this->arArgs) && array_key_exists($sName,$this->arArgs)) {
	    return $this->arArgs[$sName];
	} else {
	    return NULL;
	}
    }

    // -- DROP-IN API -- //
    // ++ RECORDS ++ //

    protected function GetRecords_active() {
	$sqlFilt = 'isActive';	// isEnabled is apparently not being set reliably
	return $this->GetData($sqlFilt,NULL,'Name');	// sort by Name
    }
    protected function GetRecords_all() {
	return $this->SelectRecords(NULL,'Name');	// sort by Name
    }
    public function GetData_forDropDown($onlyActive) {
	$sqlActv = $onlyActive?' WHERE isActive':NULL;
	$sql = 'SELECT ID, Name, isActive, ID_Parent FROM '.$this->NameSQL().$sqlActv;	// refine later if needed
	return $this->DataSQL($sql);
    }

    // -- RECORDS -- //
    // ++ WEB UI COMPONENTS ++ //

    //-----
    public function DropDown($iName,$iDefault=NULL,$iFilt=NULL,$iSort='Name') {
	$rsRows = $this->GetData($iFilt,NULL,$iSort);
	return $rsRows->DropDown($iName,$iDefault);
    }

    // -- WEB UI COMPONENTS -- //
    // ++ WEB UI SECTIONS ++ //

    //++forms++//
    
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
    public function AdminListing($idParent=0,$sHeader=NULL) {
	
	$isPage = ($idParent == 0);
	
	$oPage = $this->Engine()->App()->Page();

	$sDo = $oPage->PathArg('do');
	$doAdd = ($sDo == 'new');
	$doAddSave = $oPage->HTTP_RequestObject()->GetBool('btnAddPlace');
	$sShow = $oPage->PathArg('show');
	$doFlat = ($sShow == 'flat');
	$doInact = ($sShow == 'inact');
	
	// check for changes via form submission
//	if (array_key_exists('btnAddPlace',$_REQUEST)) {
	if ($doAddSave) {
	    $sMsg = $this->AdminListing_handleAdd($idParent);
	    $this->SelfRedirect(NULL,$sMsg);
	}

	$arMenu = array(
	  new clsActionLink_option(
	    array(),			// additional link data
	    'new',			// link key
	    'do',			// group key
	    NULL,			// display when off
	    NULL,			// display when on
	    'create a new Place'	// popup description
	    ),
	  new clsAction_section('Show'),
	  new clsActionLink_option(
	    array(),			// additional link data
	    'flat',			// link key
	    'show',			// group key
	    NULL,			// display when off
	    NULL,			// display when on
	    'show as flat listing'	// popup description
	    ),
	  new clsActionLink_option(
	    array(),			// additional link data
	    'inact',			// link key
	    'show',			// group key
	    'inactive',			// display when off
	    NULL,			// display when on
	    'include inactive places'	// popup description
	    ),
	  );
	$out = NULL;
	
	if ($isPage) {
	    $oPage->PageHeaderWidgets($arMenu);
	} else {
	    $out .= $oPage->ActionHeader($sHeader,$arMenu);
	}

	// get a list of all the Places, so we can then organize it hierarchically:
	if ($doInact) {
	    $rsPlaces = $this->GetRecords_all();
	} else {
	    $rsPlaces = $this->GetRecords_active();
	}

	$gotSome = FALSE;

	if ($doAdd) {
	    $out .= $oPage->ActionHeader('Add New Place')
	      . <<<__END__
<form method=post>
  <table>
    <tr><td align=right><b>Name</b>:</td>	<td><input name=plName size=8></td></tr>
    <tr><td align=right><b>Description</b>:</td><td><input name=plDesc size=30></td></tr>
    <tr><td colspan=2 align=center><input type=submit name=btnAddPlace value="Add Place"></td></tr>
  </table>
</form>
__END__;
	    $out .= $oPage->ActionHeader('Existing');
	}
	if ($doFlat) {
	    $out .= $rsPlaces->AdminRows($this->AdminFields());
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
	return $out;
    }
    
    // -- WEB UI PAGES -- //
}
class VCM_StockPlace extends vcAdminRecordset {
    protected $objParent;

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ DATA FIELDS ++ //

    public function ParentID() {
	return $this->Value('ID_Parent');
    }
    public function Name() {
	return $this->Value('Name');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA FIELD CALCULATIONS ++ //
    
    // CALLBACK for dropdown list
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    // CALLBACK for dropdown list
    public function ListItem_Text() {
	return $this->Name();
    }
    /*----
      RETURNS; TRUE if this place AND all ancestors are marked active (isActive=TRUE).
	If any ancestors are isActive=FALSE, then this one is not active even if it is
	marked isActive=TRUE. This lets us control activation for an entire area and all its contents
	with one change.
    */
    public function IsActive() {
	if ($this->Value('isActive')) {
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
      PURPOSE: This function is kind of overloaded. Maybe that's a bad idea, but the name
	suggests two different things depending on whether you pass it something.
      RETURNS:
	if iObj is NULL: TRUE if this object has an ID_Parent, FALSE otherwise
	if iObj is passed: TRUE if iObj is an ancestor of this object, FALSE otherwise
    */
    public function HasParent(VCM_StockPlace $iObj=NULL) {
	if (is_object($iObj)) {
	    if ($this->HasParent()) {
		if ($this->ID_Parent == $iObj->GetKeyValue()) {
		    return TRUE;
		} else {
		    $obj = $this->ParentRecord();
		    return $obj->HasParent($iObj);
		}
	    } else {
		return FALSE;
	    }
	} else {
	    return !is_null($this->ParentID());
	}
    }
    /*----
      ACTION: Returns name plus some parental context
    */
    public function NameLong() {
	$out = $this->Name();
	if ($this->HasParent()) {
	    $out .= ' &larr; '.$this->Table()->GetItem($this->ParentID())->Name();
	}
	return $out;
    }
    public function NameLong_text() {
	$out = $this->Name();
	if ($this->HasParent()) {
	    $out .= ' < '.$this->Table()->GetItem($this->ParentID())->Name();
	}
	return $out;
    }
    public function SelfLink_name() {
	$ftLink = $this->SelfLink($this->Name());
	if ($this->IsActive()) {
	    $htStyleActv = '';
	} else {
	    $htStyleActv = 'text-decoration: line-through;';
	}
	if ($this->HasParent()) {
	    if (!$this->ParentRecord()->IsActive()) {
		$htStyleActv .= ' background-color: #aaaaaa;';
	    }
	}
	return '<span style="'.$htStyleActv.'">'.$ftLink.'</span>';
    }
    
    // -- DATA FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //
    
    protected function BinsClass() {
	return KS_CLASS_STOCK_BINS;
    }
    protected function LCItemsClass() {
	return KS_ADMIN_CLASS_LC_ITEMS;
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function BinTable($id=NULL) {
	return $this->Engine()->Make($this->BinsClass(),$id);
    }
    protected function LCItemTable($id=NULL) {
	return $this->Engine()->Make($this->LCItemsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

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
	$rs = $this->Table()->SelectRecords($sqlFilt,'Name');
	return $rs;
    }
    /*----
      HISTORY:
	2010-11-30 Added object caching
	2016-01-22 changed from public to protected
    */
    protected function ParentRecord() {
	if ($this->HasParent()) {
	    $obj = $this->objParent;
	    if (!is_object($obj)) {
		//$objPlaces = new VbzStockPlaces($this->objDB);
		$tPlaces = $this->Table();
		$obj = $tPlaces->GetItem($this->ParentID());
	    }
	    $this->objParent = $obj;
	} else {
	    $obj = NULL;
	}
	return $obj;
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

    // -- CALCULATION RESULTS - //
    // ++ ACTIONS ++ //

    /*----
      ACTION: update the cache record to show that this table has been changed
    */
    protected function CacheStamp() {
	$this->Table()->CacheStamp();
    }
    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */
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
	    $out = clsHTML::DropDown_arr($sName,$arRows,$vDefault,$sChoose=NULL);
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
	$objRows = $this->Table()->GetData('ID != '.$this->GetKeyValue(),NULL,'Name');
	while ($objRows->NextRow()) {
	    if (!$objRows->HasParent($this)) {
		$arRows[$objRows->GetKeyValue()] = $objRows->NameLong_text();
	    }
	}
	return clsHTML::DropDown_arr($iName,$arRows,$this->ParentID(),'--ROOT--');
    }
    /*-----
      ACTION: Show table of all Places inside this Place (via Places->AdminListing())
      TODO: Rename to something that suggests rendered output rather than an array.
    */
    public function SubsList() {
	$tPlaces = $this->Table;
	$out = $tPlaces->AdminListing($this->GetKeyValue(),'Sub-Locations');
	return $out;
    }
    /*-----
      ACTION: Show table of all Bins inside this Place (via Bins->Listing())
      TODO: Rename to something that suggests rendered output rather than an array.
    */
    public function BinsList() {
	$tBins = $this->BinTable();
	$out = $tBins->List_forPlace($this->GetKeyValue());
	return $out;
    }

    // -- WEB UI COMPONENTS -- //
    // ++ WEB UI PAGES ++ //

    /*-----
      ACTION: Display information about the current Place
    */
    public function AdminPage() {
	$strName = $this->Value('Name');
	$strTitle = 'Stock Location '.$this->GetKeyValue().': '.$strName;

	//clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),'edit'),
	  new clsActionLink_option(array(),'update'),
	  new clsActionLink_option(array(),'inv',NULL,'inventory',NULL,'list all inventory of location '.$strName)
	  );

	$oPage = $this->Engine()->App()->Page();

	$oPage->TitleString($strTitle);
	$oPage->PageHeaderWidgets($arActs);

	$doEdit = $oPage->PathArg('edit');
	$doUpd = $oPage->PathArg('update');
	$doInv = $oPage->PathArg('inv');
	$doSave = $oPage->ReqArgBool('btnSave');

	$out = NULL;
	
	$frm = $this->PageForm();
	if ($doSave) {
	    $id = $frm->Save();
	    $sMsgs = $frm->MessagesString();
	    if (!is_null($sMsgs)) {
		$sMsgs .= '<br>';
	    }
	    $sMsgs .= "Place ID $id saved.";
	    $this->SelfRedirect(NULL,$sMsgs);
	}

	if ($doUpd) {
	    // update any calculated fields
	    $arUpd = array(
	      'isEnabled' => ($this->ParentRecord()->IsActive())?'TRUE':'FALSE'
	      );
	    $this->Update($arUpd);
	    $this->SelfRedirect(NULL,'Updated "enabled".');
	}
	
	if ($doInv) {
	    // for now, this only looks at bins in the immediate location
	    // later, we might want to allow for iterating through sub-locations too
	    $out .= $oPage->ActionHeader('Inventory');

	    $arStk = $this->CountStock();
	    ksort($arStk);
	    $tbl = $this->LCItemTable();
	    foreach ($arStk as $idItem => $qty) {
		$rc = $tbl->GetItem($idItem);
		$out .= ' '.($rc->SelfLink()).':'.$qty;
	    }

	    if (!is_null($this->ftNoStk)) {
		$out .= '<br><b>No stock found</b> in: '.$this->ftNoStk;
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
	} else {
	
	    // check calculated "enabled" field:
	
	    if ($this->HasParent()) {
		$isEnabled = $this->Value('isEnabled');		// enabled flag is set?
		$doEnabled = $this->ParentRecord()->IsActive();	// enabled flag *should be* set?
		//$ctrlActv .= ' <b>Enabled</b>: '.fcString::NoYes($isEnabled);
		if ($isEnabled != $doEnabled) {
		    /*
		    $urlUpd = $oPage->SelfURL(array('update'=>TRUE),TRUE);
		    $ctrlActv .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.fcString::NoYes($doEnabled);
		    */
		    $htUpd = $this->SelfLink('update','click to recalculate the status',array('update'=>TRUE));
		    $arCtrls['isEnabled'] .= " - <b>$htUpd</b> - should be ".fcString::NoYes($doEnabled);
		}
	    }
	}

	// render the template
	$oTplt->VariableValues($arCtrls);
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
	  .$oPage->ActionHeader('Events')
	  .$this->EventListing()
	  ;

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>Name</b>:</td><td>[#Name#]</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>[#Descr#]</td></tr>
  <tr><td align=right><b>Parent</b>:</td><td>[#ID_Parent#]</td></tr>
  <tr><td align=right><b>Active</b>:</td><td>[#isActive#]</td></tr>
  <tr><td align=right><b>Enabled</b>:</td><td>[#isEnabled#]</td></tr>
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
		$oCtrl->Records($this->PotentialParentRecords());
		$oCtrl->AddChoice(NULL,'(none)');
	
	      $oField = new fcFormField_BoolInt($oForm,'isActive');
	      $oField = new fcFormField_BoolInt($oForm,'isEnabled');
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
		$arData[$id] = $this->Values();
		if (is_null($idParent)) {
		    $arTree[0][] = $id;
		} else {
		    $arTree[$idParent][] = $id;
		}
	    }
	    
	    $hasRows = array_key_exists($idStart,$arTree);
	    $doTable = $hasRows;
	    if ($doTable) {
		$out .= "\n<table><tr><th>ID</th><th>name</th><th>description</tr>";

		// render the listing
		$out .= $this->AdminTree_sub($arData,$arTree,$idStart);

		$out .= "\n</table>";
	    }
	}
	if (is_null($out)) {
	    $out .= 'none found';
	}
	return $out;
    }
    protected function AdminTree_sub(array $arData, array $arTree, $idBranch=0, $nInd=0) {
	$out = '';

	if (array_key_exists($idBranch,$arTree)) {
	    foreach($arTree[$idBranch] AS $key => $id) {
		$arPlace = $arData[$id];
		$this->Values($arPlace);
		$htPlace = $this->SelfLink_name();
		$sInd = str_repeat('&ndash;&nbsp;',$nInd);
		$sDesc = $this->Value('Descr');
		$out .= <<<__END__
		
<tr>
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

    // -- ADMIN WEB UI -- //

}