<?php
/*
  LIBRARY: place.php - classes for managing stock places
  HISTORY:
    2014-03-22 extracted Bin classes from place.php
*/
class VCM_StockBins extends clsVbzTable {

    // ++ SETUP ++ //

    protected $idEvent;
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('stk_bins');
	  $this->KeyName('ID');
	  $this->ClassSng('VCM_StockBin');
	  $this->ActionKey(KS_ACTION_STOCK_BIN);
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->Listing();
	return $out;
    }
    public function Listing() {
	return $this->AdminPage();
    }
    protected function Arg($sName) {
	if (is_array($this->arArgs) && array_key_exists($sName,$this->arArgs)) {
	    return $this->arArgs[$sName];
	} else {
	    return NULL;
	}
    }

    // -- DROP-IN API -- //
    // ++ BOILERPLATE: cache management (table) ++ //

    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp();
    }
    /*----
      ACTION: update the cache record to show that this table has been changed
      NOTES:
	Must be public so it can be called by recordset type.
    */
    public function CacheStamp($iCaller) {
	$objCache = $this->Engine()->CacheMgr();
	$objCache->UpdateTime_byTable($this,$iCaller);
    }

    // -- BOILERPLATE -- //
    // ++ DATA RECORDS ++ //

    /*----
      RETURNS: Dataset of active bins
    */
    public function GetActive() {
	$objRows = $this->GetData('(WhenVoided IS NULL) AND isForShip',NULL,'Code');
	return $objRows;
    }
    /*----
      RETURNS: Dataset with extra information
    */
    public function DataSet_Info($iFilt=NULL) {
	$sqlFilt = is_null($iFilt)?'':' WHERE '.$iFilt;
	$sql = 'SELECT * FROM qryStk_Bins_w_info'.$sqlFilt.' ORDER BY Code';
	$objRows = $this->DataSQL($sql);
	//$objRow = $dbVBZ->DataSet('SELECT * FROM '.ksQry_stock_bins_wInfo.$sqlFilt.' ORDER BY Code','VbzStockBin');
	//$objRow->Table = $dbVBZ->Bins();	// for AdminLink()
	//$objRow->Table = $this;
	return $objRows;
    }
    public function Search_byCode($sCode) {
	$sqlCode = SQLValue(strtoupper($sCode));
	$rs = $this->GetData('UPPER(Code) = '.$sqlCode);
	//echo 'ROWS=['.$rs->RowCount().'] SQL: '.$rs->sqlMake;
	return $rs;
    }

    // -- DATA RECORDS -- //
    // ++ DATA ARRAYS ++ //

    /*----
      ACTION: Returns stock data for the given list of items
      NOTE: We might later have this return all items in stock
	if iItems is NULL, but at the moment I can't think of
	a use for this.
    */
    public function Info_forItems(array $iItems) {
	$sqlList = NULL;
	foreach ($iItems as $id) {
	    if (!is_null($sqlList)) {
		$sqlList .= ',';
	    }
	    $sqlList .= $id;
	}
	$sql = 'SELECT * FROM qryStk_byItem_byBin WHERE ID_Item IN ('.$sqlList.')';
	$dsItems = $this->objDB->DataSet($sql);

	$arOut = NULL;
	while ($dsItems->NextRow()) {
	    $idBin = $dsItems->Row['ID_Bin'];
	    $idItem = $dsItems->Row['ID_Item'];
	    $objBin = $this->GetItem($idBin);
	    if ($objBin->IsActive_and_Enabled()) {
		$qtyForSale = $dsItems->Row['QtyForSale'];
		$qtyForShip = $dsItems->Row['QtyForShip'];
		if (isset($arOut[$idItem])) {
		    $arItem = $arOut[$idItem];
		    $arItem['for-sale'] += $qtyForSale;
		    $arItem['for-ship'] += $qtyForShip;
		} else {
		    $arItem['for-sale'] = $qtyForSale;
		    $arItem['for-ship'] = $qtyForShip;
		}
		$arOut[$idItem] = $arItem;
	    }
	}
	return $arOut;
    }

    // -- DATA ARRAYS -- //
    // ++ CALCULATIONS ++ //

    /*----
      NOTE: For listing multiple items, use Info_forItems()
    */
    public function Qty_ofItem($idItem) {
	$sql = "SELECT QtyExisting FROM qryStk_items_remaining WHERE ID_Item=$idItem";
	$objRow = $this->Engine()->DataSet($sql);
	if ($objRow->HasRows()) {
	    $objRow->NextRow();
	    $out = $objRow->QtyExisting;
	} else {
	    $out = 0;
	}
	return $out;
    }

    // -- CALCULATIONS -- //
    // ++ ADMIN WEB UI ++ //

    /*----
      HISTORY:
	2010-11-30 Created for moving restock shipment items into stock
      FILTER: Does not show voided Bins, but does not check if Place is active.
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iFilt='WhenVoided IS NULL',$iSort='Code') {
	$objRows = $this->GetData($iFilt,NULL,$iSort);
	$strName = is_null($iName)?($this->ActionKey()):$iName;
	return $objRows->DropDown($strName,$iDefault);
    }
    /*----
      ACTION: Same as DropDown(), but only show active bins in active locations.
	2010-12-01 Created
      INPUT: array of optional elements
	array[name]: name to use when rendering HTML dropdown control
	array[def]: ID of row to select by default
	array[choose]: text to use for no-choice; NULL means don't show no-choice
	array[filt]: filter to apply to SQL when fetching dataset (WHERE clause)
	array[sort]: SQL to use for sorting (ORDER BY clause)
    */
    public function DropDown_active(array $iArgs=NULL) {
	// set up input parameters
	$iName		= empty($iArgs['name'])	? ($this->ActionKey()) 	: $iArgs['name'];
	$iDefault	= empty($iArgs['def'])	? NULL 			: $iArgs['def'];
	$iChoose	= empty($iArgs['def'])	? NULL 			: $iArgs['def'];
	$iFilt		= empty($iArgs['filt'])	? 'WhenVoided IS NULL'	: $iArgs['filt'];
	$iSort		= empty($iArgs['sort'])	? 'Code'		: $iArgs['sort'];

	$objRows = $this->GetData($iFilt,NULL,$iSort);
	$strName = is_null($iName)?($this->ActionKey()):$iName;

	$fnFilt = function(VbzStockBin $iBin) {
	    return $iBin->PlaceObj()->IsActive();
	};

	return $objRows->DropDown($iName,$iDefault,$iChoose,$fnFilt);
    }
    /*-----
      ACTION:
	Render table of all bins within the given Place
	Show form to allow user to move selected bins
    */
    public function List_forPlace($iPlace) {
	$sqlFilt = 'ID_Place='.$iPlace;

	$objRows = $this->DataSet_Info($sqlFilt);
	$out = $objRows->AdminList(array('do.place'=>FALSE));
	return $out;
    }
    /*----
      ACTION: Displays all bins
      USED BY: main menu
      HISTORY:
	2011-03-03 renamed from ListPage() to AdminPage(), to work with menu regularization
    */
    public function AdminPage($iFilt=NULL) {
	global $vgOut;

	$objRows = $this->DataSet_Info($iFilt);
	return $objRows->AdminList(array('do.place'=>TRUE));
    }

    // -- ADMIN WEB UI -- //

}
class VCM_StockBin extends clsVbzRecs {
    protected $idEvent;

    // ++ BOILERPLATE: cache management (recordset) ++ //

    /*----
      ACTION: update the cache record to show that this table has been changed
    */
    protected function CacheStamp($iCaller) {
	$this->Table()->CacheStamp($iCaller);
    }
    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp(__METHOD__);
    }

    // -- BOILERPLATE -- //
    // ++ BOILERPLATE EXTENSIONS ++ //

    public function AdminLink_name() {
	$ftLink = $this->AdminLink($this->Name());

	$htStyleActv = NULL;
	$isActive = $this->IsActive();
	$isEnabled = $this->IsEnabled();
	if (!$isActive) {	// has not been voided
	    $htStyleActv = 'text-decoration: line-through;';
	}
	if (!$isEnabled) {	// is in a valid Place
	    $htStyleActv .= ' background-color: #aaaaaa;';
	}

	$ftSfx = NULL;
	$isValid = $this->IsValid();
	if ($isValid != $isEnabled) {
	    $ftStat = $isValid?'enabled':'disabled';
	    $ftSfx .= ' <span title="update needed - should be '.$ftStat.'" style="color: red; font-weight: bold;">!!</span>';
	}

	if (is_null($htStyleActv)) {
	    return $ftLink.$ftSfx;
	} else {
	    return '<span style="'.$htStyleActv.'">'.$ftLink.'</span>'.$ftSfx;
	}
    }

    // -- BOILERPLATE EXTENSIONS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //

    protected function CTitlesClass() {
	if (clsDropInManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_TITLES;
	} else {
	    return 'clsVbzTitles';
	}
    }
    protected function CItemsClass() {
	if (clsDropInManager::ModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_ITEMS;
	} else {
	    return 'clsItems';
	}
    }
    protected function PlacesClass() {
	return KS_CLASS_STOCK_PLACES;
    }
    protected function StockLinesClass() {
	return KS_CLASS_STOCK_LINES;
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function PlaceTable($id=NULL) {
	return $this->Engine()->Make($this->PlacesClass(),$id);
    }
    protected function CItemTable($id=NULL) {
	return $this->Engine()->Make($this->CItemsClass(),$id);
    }
    protected function LineTable($id=NULL) {
	return $this->Engine()->Make($this->StockLinesClass(),$id);
    }
    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make($this->CTitlesClass(),$id);
    }
    protected function BinLog($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_STOCK_BIN_LOG,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA FIELD ACCESS ++ //

    public function Name() {	// alias, for now
	return $this->Value('Code');
    }
    protected function Code() {
	return $this->Value('Code');
    }
    public function IsActive() {
	return is_null($this->Value('WhenVoided'));
    }
    /*----
      RETURNS: value of isEnabled flag
    */
    public function IsEnabled() {
	return ord($this->Value('isEnabled'));
    }
    protected function IsForSale() {
	return ord($this->Value('isForSale'));
    }
    protected function IsForShip() {
	return ord($this->Value('isForShip'));
    }
    /*----
      RETURNS: TRUE IFF bin is in an active Place
	In other words, returns how the isEnabled flag *should* be set.
    */
    public function IsValid() {
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    return FALSE;
	} else {
	    return $rcPlace->IsActive();
	}
    }
    public function IsActive_and_Enabled() {
	if ($this->IsActive()) {
	    if ($this->IsEnabled()) {
		return TRUE;
	    }
	}
	return FALSE;
    }
    public function IsRelevant() {
	if ($this->IsActive_and_Enabled()) {
	    return $this->IsValid();
	} else {
	    return FALSE;
	}
    }
    public function StatusCode() {
	$out = '';
	if ($this->IsValid()) {
	    $out .= 'V';
	}
	if ($this->IsActive()) {
	    $out .= 'A';
	}
	return $out;
    }
    public function NameLong() {
	$out = $this->Value('Code');
	$txtDescr = $this->Value('Descr');
	if (!is_null($txtDescr)) {
	    $out .= ': '.$txtDescr;
	}
	return $out;
    }
    public function PlaceID() {
	return $this->Value('ID_Place');
    }
    public function PlaceName() {
	$rc = $this->PlaceRecord();
	return $rc->Name();
    }
    protected function Place_isActive() {
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    return FALSE;
	} else {
	    return $rcPlace->IsActive();
	}
    }
    protected function Place_AdminLink_name($htNA='<i>n/a</i>') {
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    return $htNA;
	} else {
	    return $rcPlace->AdminLink_name();
	}
    }
    protected function WhenCreated() {
	return $this->Value('WhenCreated');
    }
    protected function WhenCounted() {
	return $this->Value('WhenCounted');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      HISTORY:
	2011-03-19 Return a Spawned item if ID_Place is not set -- presuming
	  we need the object for other purposes besides its current values
    */
    public function PlaceObj() {
	throw new exception('PlaceObj() is deprecated; use PlaceRecord() instead.');
    }
    /*----
      PUBLIC because Package object needs it
    */
    public function PlaceRecord() {
	if ($this->HasValue('ID_Place')) {
	    $idPlc = $this->Value('ID_Place');
	    $rc = $this->PlaceTable($idPlc);
	    return $rc;
	} else {
	    return $this->PlaceTable()->SpawnItem();
	}
    }
    // 2014-05-26 who uses this? use LineTable() instead of Engine()->StkItems()
/*
    public function StkItems_data() {
	return $this->Engine()->StkItems()->Data_forBin($this->Value('ID'));
    }
*/
    // -- DATA RECORDS ACCESS -- //
    // ++ CALCULATIONS ++ //

    /*----
      ACTION: count all stock in the current Bin
      INPUT: recordset is set to Bin to be counted
      RETURNS: array of Item quantities
	array[item id] = quantity in stock
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function CountStock() {
	$rs = $this->StkItems_data();
	return $rs->CountStock();
    }
    public function Qty_forItem($idItem) {
	$qty = $this->LineTable()->ItemQty_inBin($this->KeyValue(),$idItem);
	return $qty;
    }

    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Remove the given quantity of the given Catalog Item from the current Bin.
      USAGE: It is the caller's responsibility to ensure that the quantity removed is added somewhere else.
      PUBLIC so that Package can use it to remove items during the packaging process.
    */
    public function Remove($qty,$idItem,clsStockEvent $oEvent=NULL) {
	return $this->LineTable()->Remove($qty,$idItem,$this->KeyValue(),$oEvent);
    }
    public function Add($qty,$idItem,clsStockEvent $oEvent=NULL) {
	return $this->LineTable()->Add($qty,$idItem,$this->KeyValue(),$oEvent);
    }
    /*----
      ACTION: Move the current bin to the given Place, and log the move
    */
    public function MoveTo($idPlace) {
	$objPlace = $this->PlaceTable($idPlace);
	$txtEv = 'Moving from [#'.$this->PlaceID().'='.$this->PlaceRecord()->Name.'] to [#'.$idPlace.'='.$objPlace->Name.']';

	$arEv = array(
	  'descr' => $txtEv,
	  'where' => __METHOD__,
	  'code'  => 'MV'
	  );
	$this->StartEvent($arEv);

	$arUpd['ID_Place'] = $idPlace;
	$this->Update($arUpd);

	// log the move - old style
	$idPlaceOld = $this->PlaceID();
	$idPlaceNew = $idPlace;
	$this->LogEvent($idPlaceOld,$idPlaceNew,$txtEv);

	$this->FinishEvent();
    }

    // -- ACTIONS -- //
    // ++ ADMIN WEB UI ++ //

    protected function Place_DropDown_All($sName=NULL) {
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    $rsPlaces = $this->PlaceTable()->GetData();
	    return $rsPlaces->DropDown($sName);
	} else {
	    return $rcPlace->DropDown_All($sName);
	}
    }
    /*----
      RETURNS: Rendering of HTML for drop-down list
	for all Places, with the current Place as the default
    */
    protected function Place_DropDown($sName=NULL) {
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    $rsPlaces = $this->PlaceTable()->GetData_active();
	    return $rsPlaces->DropDown($sName);
	} else {
	    return $rcPlace->DropDown_meDefault($sName,FALSE);
	}
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    if ($this->IsNew()) {
		$sTimes = '';
	    } else {
		$sTimes = <<<__END__
  <tr><td align=right><b>Created</b>:</td><td>[#WhenCreated#]</td></tr>
  <tr><td align=right><b>Tainted</b>:</td><td>[#WhenTainted#]</td></tr>
  <tr><td align=right><b>Counted</b>:</td><td>[#WhenCounted#]</td></tr>
  <tr><td align=right><b>Voided</b>:</td><td>[#WhenVoided#]</td></tr>
__END__;
	    }
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>Code</b>:</td><td>[#Code#]</td></tr>
  <tr><td align=right><b>Where</b>:</td><td>[#Place#]</td></tr>
  <tr><td align=right><b>Status</b>:</td><td>[#Status#]</td></tr>
  $sTimes
  [#DescRow#]
</table>
[#NotesRow#]
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }
    /*-----
      ACTION: Display bin details and contents (via this->Contents())
      HISTORY:
	2011-04-01 moved AdminInventory() up so it takes place before record is displayed
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$rcPlace = $this->PlaceRecord();

	$doSave = $oPage->ReqArgBool('btnSave');
	$isNew = $this->IsNew();
	$doAdd = $isNew;
	$doEdit = ($oPage->PathArg('edit')) || $isNew;
	$doUpd = $oPage->PathArg('update');
	$strDo = $oPage->PathArg('do');

	// process any data-changing user input -- goes before the main header
	$out = '';
	if ($doSave) {
	    if ($this->IsNew()) {
		$this->EditForm()->NewVals(array('WhenCreated'=>'NOW()'));
	    } else {
		//$this->objForm->NewVals(array('WhenCreated'=>'NOW()'));
		// LATER: have a WhenChanged field for any time stuff has moved
	    }
	    $out .= $this->EditForm()->Save();	// save edit
	    $this->AdminRedirect();	// 2011-11-05 does "return" make this work now?
	    return;
	}

	$doEnabled = $this->Place_isActive();
	if ($doUpd) {
	    // update any calculated fields
	    $arUpd = array(
	      'isEnabled' => $doEnabled?"b'1'":"b'0'"
	      );
	    $this->Update($arUpd);
	    $this->AdminRedirect();
	}

	$txtInv = $this->AdminInventory();
	if ($strDo == 'void') {
	    $arEv = array(
	      'descr'	=> 'Voiding this bin',
	      'code'	=> 'VOID',
	      'where'	=> __METHOD__
	      );
	    $this->StartEvent($arEv);
	    $arUpd = array(
	      'WhenVoided'	=> 'NOW()'
	      );
	    $this->Update($arUpd);
	    $this->Reload();
	    $this->FinishEvent();
	}

	// do the header
	if ($doAdd) {
	    $doEdit = TRUE;
	    $strCode = NULL;
	    $sTitle = 'New bin';
	    $sName = 'new bin';
	    $dtWhenCreated = NULL;
	} else {
	    $id = $this->KeyValue();
	    $strCode = $this->Value('Code');
	    $sTitle = 'Stock Bin '.$id.' - '.$strCode;
	    $sName = $strCode;
	    $dtWhenCreated = $this->Value('WhenCreated');
	}

	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	    // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),'edit'),
//	  new clsActionLink_option(array(),'inv',NULL,'inventory',NULL,'list all inventory of location '.$strName)
	  );

	$out .= $oPage->ActionHeader($sTitle,$arActs);

	// Set up rendering objects
	$frmEdit = $this->PageForm();
	if ($isNew) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);

	if ($doEdit) {
	    $out .= "\n<form method=post>";

	    //$htCode = $oForm->RenderControl('Code');
	    $htPlace = $this->Place_DropDown('ID_Place');
	    //$htDescr = 	$oForm->RenderControl('Descr');
	    //$htNotes = 	$oForm->RenderControl('Notes');
	    $oForm->Ctrl('isEnabled')->Field()->ValBool($doEnabled);	// set the enabled flag to save properly
	    $htStatus =
	      $oForm->RenderControl('isForSale').'for sale '
	      .$oForm->RenderControl('isForShip').'for shipping'
	      .$oForm->RenderControl('isEnabled');
	    //$htWhenVoided =	$oForm->RenderControl('WhenVoided');
	    //$htWhenTainted =	$oForm->RenderControl('WhenTainted');
	} else {
	    // customize the form data:
	    $arCtrls['Code'] = $this->AdminLink_name();

	    $htPlace = $this->Place_AdminLink_name();
	    //$htDescr = $this->Value('Descr');
	    //$htNotes = $this->Value('Notes');
	    //$isForSale = $oForm->Ctrl('isForSale')->Field()->ValBool();
	    //$isForShip = $oForm->Ctrl('isForShip')->Field()->ValBool();
	    //$isEnabled = $oForm->Ctrl('isEnabled')->Field()->ValBool();
	    $isForSale = $this->IsForSale();
	    $isForShip = $this->IsForShip();
	    $isEnabled = $this->IsEnabled();
	    $htStatus =
	      ' '.($isForSale?'<b>':'<s>').'SELL'.($isForSale?'</b>':'</s>').
	      ' '.($isForShip?'<b>':'<s>').'SHIP'.($isForShip?'</b>':'</s>').
	      ' '.($isEnabled?'<b>':'<s>').'ENABLED'.($isEnabled?'</b>':'</s>');

	    $doEnabled = $this->Place_isActive();
	    if ($isEnabled != $doEnabled) {
		$arLink = $oPage->PathArgs(array('page','id'));
		$arLink['update'] = TRUE;
		$urlUpd = $oPage->SelfURL($arLink,TRUE);

		$txtStat = $doEnabled?'enabled':'disabled';
		$htStatus .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.$txtStat;
	    }

	    $dtVoided = $this->Value('WhenVoided');
	    if (is_null($dtVoided)) {
		//$htWhenVoided = $oPage->SelfLink(array('do'=>'void'),'void now');
		$url = $oPage->SelfURL(array('do'=>'void'));
		$htWhenVoided = clsHTML::BuildLink($url,'void now');
	    } else {
		$htWhenVoided = $this->Value('WhenVoided');
	    }
	    $htWhenTainted = $this->Value('WhenTainted');
	    //$htNotes = htmlspecialchars($this->Value('Notes'));
	}

	$htDescRow = $htNotesRow = NULL;
	if ($doEdit || !is_null($this->Value('Descr'))) {
	    //$htDescRow = "<tr><td align=right><b>Description</b>:</td><td>$htDescr</td></tr>";
	    $htDescRow = "<tr><td align=right><b>Description</b>:</td><td>[#Descr#]</td></tr>";
	}
	if ($doEdit || !is_null($this->Value('Notes'))) {
	    $htNotesRow = "\n<b>Notes</b>:<br>[#Notes#]";
	}

	$arCtrls['Place'] = $htPlace;
	$arCtrls['Status'] = $htStatus;
	$arCtrls['DescRow'] = $htDescRow;
	$arCtrls['NotesRow'] = $htNotesRow;
	$arCtrls['WhenCreated'] = $this->WhenCreated();
	$arCtrls['WhenCounted'] = $this->WhenCounted();

	// render the template
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

/*
	$out .= $txtInv;	// display results of inventory count, if any
	$out .= <<<__END__
<table>
<tr><td align=right><b>Code</b>:</td><td>$htCode</td></tr>
<tr><td align=right><b>Where</b>:</td><td>$htPlace</td></tr>
<tr><td align=right><b>Status</b>:</td><td>$htStatus</td></tr>
$htDescRow
<tr><td align=right><b>Created</b>:</td><td>$htWhenCreated</td></tr>
<tr><td align=right><b>Tainted</b>:</td><td>$htWhenTainted</td></tr>
<tr><td align=right><b>Counted</b>:</td><td>$htWhenCounted</td></tr>
<tr><td align=right><b>Voided</b>:</td><td>$htWhenVoided</td></tr>
</table>
$htNotesRow
__END__;
*/
	if ($doEdit) {
	    if ($doAdd) {
		$out .= '<input type=submit name="btnSave" value="Create">';
	    } else {
		$out .= '<input type=submit name="btnSave" value="Save">';
	    }
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	if (!$isNew) {
	    $sHdr = 'items in '.$sName;
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	      new clsActionLink_option(array(),'move.items'	,'do','move'	,NULL,"move items from $sName to another bin"),
	      new clsActionLink_option(array(),'inv'		,'do','count'	,NULL,"record inventory count for $sName"),
	      );
	    $out .= $oPage->ActionHeader($sHdr,$arActs);

	    $out .= $this->Contents()
	      . $oPage->SectionHeader('History')
	      . $this->RenderEvents()	// bin event log - needs to be merged with system log
	      . $this->EventListing()	// universal log
	      ;
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-11-01 adapted from clsPackage
      TODO: WhenCreated and WhenCounted should never be editable.
    */
    private $oForm;
    private function PageForm() {
	// create fields & controls
	if (is_null($this->oForm)) {
	    // FORMS v2
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);
	      $oField = new fcFormField_Num($oForm,'ID_Place');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>5));
	      $oField = new fcFormField($oForm,'Code');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>8));
	      $oField = new fcFormField($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>30));
	      $oField = new fcFormField_Time($oForm,'WhenVoided');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Time($oForm,'WhenTainted');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array());
	      $oField = new fcFormField_Bit($oForm,'isForSale');
		$oCtrl = new fcFormControl_HTML_CheckBox($oForm,$oField,array());
	      $oField = new fcFormField_Bit($oForm,'isForShip');
		$oCtrl = new fcFormControl_HTML_CheckBox($oForm,$oField,array());
	      $oField = new fcFormField_Bit($oForm,'isEnabled');
		$oCtrl = new fcFormControl_HTML_CheckBox($oForm,$oField,array());
	      $oField = new fcFormField($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oForm,$oField,array('rows'=>3,'cols'=>60));

	    // FORMS v1
/*	    $oForm = new clsForm_recs($this);
	    $oForm->AddField(new clsFieldNum('ID_Place'),		new clsCtrlHTML());
	    $oForm->AddField(new clsField('Code'),		new clsCtrlHTML(array('size'=>8)));
	    $oForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>30)));
	    $oForm->AddField(new clsFieldTime('WhenVoided'),	new clsCtrlHTML());
	    $oForm->AddField(new clsFieldTime('WhenTainted'),	new clsCtrlHTML());
	    $oForm->AddField(new clsFieldBool('isForSale'),	new clsCtrlHTML_CheckBox());
	    $oForm->AddField(new clsFieldBool('isForShip'),	new clsCtrlHTML_CheckBox());
	    $oForm->AddField(new clsFieldBool('isEnabled'),	new clsCtrlHTML_Hidden());
	    $oForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>40)));
*/
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    /*----
      ACTION: Displays the current dataset in multi-row format, with administrative controls
      HISTORY:
	2010-10-30 written; replaces code in VbzStockBins
      INPUT:
	$iArgs
	  'do.place': TRUE = show place column; FALSE = all in one place, don't bother to list it
    */
    public function AdminList(array $iArgs=NULL) {
	$out = $this->AdminListSave();	// make any requested changes


	$arActs = array(
	  // $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL
	  new clsActionLink_option(array(),'edit.bins',NULL,'edit',NULL),
	  new clsActionLink_option(array(),'add.bin',NULL,'add',NULL,'add a new bin'),
//	  new clsActionLink_option(array(),'inv',NULL,'list all inventory of location '.$strName)
	  );

	$oPage = $this->Engine()->App()->Page();

	$out .= $oPage->ActionHeader('Stock Bins',$arActs);

	$objRow = $this;
	if ($objRow->hasRows()) {
	    $doPlace = nz($iArgs['do.place']);
	    $htPlace = NULL;

/* do we actually need a URL here? Assume not for now...
	    $urlSelf = $oPage->SelfURL(array());
	    // for this list, we always display the form
	    $out .= "\n".'<form method=post action="'.$urlSelf.'">';
*/
	    $out .= "\n".'<form method=post>';

	    if ($doPlace) {
		$htPlace = '<th>where</th>';
	    }

	    $out .= <<<__END__
<table class=sortable>
  <tr>
    <th>ID</th>
    <th>status</th>
    $htPlace
    <th>code</th>
    <th>qtys</th>
    <th>description</th>
    <th>when<br>created</th>
    <th>when<br>tainted</th>
    <th>when<br>counted</th>
    <th>when<br>voided</th>
  </tr>
__END__;
	    clsModule::LoadFunc('TimeStamp_HideTime');
	    $isOdd = FALSE;
	    while ($objRow->NextRow()) {
		$row = $objRow->Row;
		$htStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$qtySale = $row['QtyForSale'];
		$qtyShip = $row['QtyForShip'];
		$qtyRec = $row['QtyExisting'];
		$strQty = NULL;

		$qtySaleInt = (int)$qtySale;
		$qtyShipInt = (int)$qtyShip;
		$qtyRecInt = (int)$qtyRec;

/* 2012-01-11 We used to show the breakdown of quantity for each status (for sale, for ship, existing),
  but after thinking this through for a bit I realized this was pointless -- the only granularity here
  is the status of the box, not individual items. If items exist at all (i.e. are Active), then the
  status of the box is the sole determinant of whether they are also for ship or for sale.

  So now we just show the existing quantity.
*/
		$htQty = $qtyRecInt?$qtyRecInt:'-';

		$isActive = $objRow->IsActive();
		if ($isActive) {
		    $htCellPfx = '';
		    $htCellSfx = '';
		    $chActive = '&radic;';
		} else {
		    $htCellPfx = '<s>';
		    $htCellSfx = '</s>';
		    $chActive = '';
		}
		$id = $row['ID'];
		$htID = '<nobr><input type=checkbox name="bin['.$id.']">'.$id.'</nobr>';
		//$htCode = '[[{{FULLPAGENAME}}/page'.KS_CHAR_URL_ASSIGN.'bin/id'.KS_CHAR_URL_ASSIGN.$id.'|'.$row['Code'].']]';
		//$htCode = $objRow->AdminLink($row['Code']);
		$htCode = $objRow->AdminLink_name();
		$htWhenMade = TimeStamp_HideTime($row['WhenCreated']);
		$htWhenVoid = TimeStamp_HideTime($row['WhenVoided']);
		$htWhenCount = TimeStamp_HideTime($row['WhenCounted']);
		$htWhenTaint = TimeStamp_HideTime($row['WhenTainted']);

		if ($doPlace) {
		    $rcPlace = $objRow->PlaceRecord();
		    if (is_null($rcPlace)) {
			$sPlace = '<i>root</i>';
		    } else {
			$sPlace = $rcPlace->AdminLink_name();
		    }
		    $htActive = $objRow->StatusCode();
		} else {
		    $htActive = $this->IsActive()?'&radic;':'<font color=red>x</font>';
		    if ($this->Value('isForSale') == chr(1)) {
			$htActive .= ' sale';
		    }
		    if ($this->Value('isForShip') == chr(1)) {
			$htActive .= ' ship';
		    }
		}
		if ($doPlace) {
		    $htPlace = "<td>$sPlace</td>";
		}
		$htDesc = htmlspecialchars($row['Descr']);

		$out .= <<<__END__
  <tr style="$htStyle">
    <td>$htCellPfx$htID$htCellSfx</td>
    <td>$htActive</td>
    $htPlace
    <td>$htCellPfx$htCode$htCellSfx</td>
    <td>$htCellPfx$htQty$htCellSfx</td>
    <td>$htCellPfx<small>$htDesc</small>$htCellSfx</td>
    <td>$htCellPfx$htWhenMade$htCellSfx</td>
    <td>$htWhenTaint</td>
    <td>$htWhenCount</td>
    <td>$htWhenVoid</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	    $out .= "\n<input type=submit name=btnSelBins value=\"Move to...\">";
	    $arLink = array(
	      'page'	=> KS_ACTION_STOCK_BIN,
	      'id'	=> 'new'
	      );
	    $url = $oPage->SelfURL($arLink);
	    $htLink = clsHTML::BuildLink($url,'create new bin');
	    $out .= "[ $htLink ]";
	    $out .= "\n</form>";
	} else {
	    $out .= 'No bins found.';
	}

	return $out;
    }
    /*-----
      ACTION: Display bin contents
    */
    public function Contents() {
	$oPage = $this->Engine()->App()->Page();

	$sDo = $oPage->PathArg('do');
	$doMoveForm = ($sDo == 'move.items');
	$doConfForm = $oPage->ReqArgBool('btnMove');
	$doMoveNow = $oPage->ReqArgBool('btnConf');

	$idBin = $this->KeyValue();
	if (empty($idBin)) {
	    $out = "'''Internal Error''': No bin ID has been set.";
	} else {
	    $doForm = !$doMoveNow && ($doMoveForm || $doConfForm);

	    if ($doForm) {
		$out = '<form method=post>';
		$out .= '<div align=right>';
	    } else {
		$out = '';
	    }

	    // check for form input
	    if ($doConfForm || $doMoveNow) {
		$doMoveForm = FALSE;	// don't show move form when we're confirming
		// ...because this introduces ambiguity of being able to select new move settings
		$arLine = clsHTTP::Request()->getArray('line');
		//$arLine = $_POST['line'];
		if (is_array($arLine)) {
		    $idDest = clsHTTP::Request()->getInt(KS_ACTION_STOCK_BIN);
		    $strNotes = clsHTTP::Request()->getText('notes');
		    $objDest = $this->Table()->GetItem($idDest);
		    $strCodeThis = $this->Code();
		    $strCodeDest = $objDest->Code();

		    if ($doConfForm) {
			$out .= '<input type=hidden name="bin" value="'.$idDest.'">';
		    } elseif ($doMoveNow) {
			// log the move overall in the bin log:
			$cntItems = count($arLine);
			$txtDescr = 'Moving '.$cntItems.' item'.Pluralize($cntItems).' from '.$strCodeThis.' (here) to '.$strCodeDest;
			if (!empty($strNotes)) { $txtDescr .= ': '.$strNotes; }
			$this->LogEvent_SamePlace($txtDescr);
			$txtDescr = 'Moving '.$cntItems.' item'.Pluralize($cntItems).' from '.$strCodeThis.' to '.$strCodeDest.' (here)';
			if (!empty($strNotes)) { $txtDescr .= ': '.$strNotes; }
			$objDest->LogEvent_SamePlace($txtDescr);
			$out .= 'Moving from ';
		    } else {
			$out .= 'Move from ';
		    }
		    $out .= '<b>'.$strCodeThis.'</b> to <b>'.$strCodeDest.'</b>:<ul>';
		    if (is_array($arLine)) {
			$out .= '<table><tr><td>';
			foreach ($arLine as $id => $val) {
			    $objStkLine = $this->LineTable($id);
			    $idItem = $objStkLine->Value('ID_Item');
			    $objItem = $this->CItemTable($idItem);
			    $out .= '<li> item #'.$id.' - '.$objItem->Value('CatNum').', qty '.$objStkLine->Value('Qty');
			    if ($doConfForm) {
				$out .= '<input type=hidden name="line['.$id.']">';
			    } elseif ($doMoveNow) {
				//$out .= ' (move code goes here)';
				$objStkLine->MoveToBin($idDest);
			    }
			}
			$out .= '</td></tr></table>';
		    }
		    $out .= '</ul>';
		    if (!empty($strNotes)) {
			$out .= '<b>Notes</b>: '.$strNotes.'<br>';
			if ($doConfForm) {
			    $out .= '<input type=hidden name=notes value="'.htmlspecialchars($strNotes).'">';
			}
		    }
		    if ($doConfForm) {
			$out .= '<input type=submit name="btnConf" value="Confirm Move">';
		    }
		} else {
		    $out .= 'No items were listed for moving.';
		}
	    }

	    $sqlSource = KS_TBL_STOCK_LINES.' AS si LEFT JOIN qryCat_Items AS ci ON si.ID_Item=ci.ID';
	    $sqlCols = 'si.*, ci.CatNum AS ItCatNum, ci.ID_Title, ci.Title_Name AS TiName';
	    $sql = 'SELECT '.$sqlCols.
		' FROM '.$sqlSource.
		' WHERE ID_Bin='.$idBin.
		' ORDER BY WhenRemoved,ItCatNum';

	    $objRow = $this->Engine()->DataSet($sql,'clsVbzRecs');
	    if ($objRow->hasRows()) {
		$tItems = $this->CItemTable();
		$tTitles = $this->TitleTable();
		$objRow->Table = $this->LineTable();
		if ($doMoveForm) {
		    $objBins = $this->Table()->GetActive();
		    $out .= 'To: '.$objBins->DropDown(KS_ACTION_STOCK_BIN);
		    $out .= '<br>Notes: <input type=text name="notes" size=40>';
		    $out .= '<br><input type=submit name="btnMove" value="Select for Moving" />';
		}
		if ($doForm) {
		    $out .= '</div>';
		}
		$ftList = NULL;
		$out .= '<small>'.$sql.'</small>';
		$out .= "<table class=sortable><tr>\n".
		    '<th colspan=2>ID</th>'.
		    '<th>A?</th>'.
		    '<th>CatNum</th>'.
		    '<th>qty</th>'.
		    '<th>title</th>'.
		    '<th>when<br>added</th>'.
		    '<th>when<br>changed</th>'.
		    '<th>when<br>counted</th>'.
		    '<th>when<br>removed</th>'.
		    '<th>notes</th>'.
		    '</tr>';
		$isOdd = TRUE;

		while ($objRow->NextRow()) {
		    $row = $objRow->Row;

		    $htStyle = $isOdd?'background:#ffffff;':'background:#dddddd;';
		    $isOdd = !$isOdd;

		    $isActive = is_null($row['WhenRemoved']) && ($row['Qty'] > 0);
		    if (!$isActive) {
			$htStyle .= ' color: #888888;';
			$htStyle .= ' text-decoration: line-through;';
		    }
		    $id		= $row['ID'];
		    $htID	= $objRow->AdminLink($id);
		    $idItem 	= $row['ID_Item'];
		    $txtCatNum	= is_null($row['CatNum'])?"<i>".$row['ItCatNum']."</i>":$row['CatNum'];

		    $rcItem	= $tItems->GetItem($idItem);
		    $htCatNum	= $rcItem->AdminLink($txtCatNum);

		    $isActive	= is_null($row['WhenRemoved']);
		    $htActive	= $isActive?'&radic;':'';
		    $txtQty	= $row['Qty'];

		    $sTitle = $row['TiName'];
		    $rcTitle = $tTitles->GetItem($objRow->Value('ID_Title'));
		    $htTitle	= $rcTitle->AdminLink($sTitle);

		    $txtWhenAdded	= TimeStamp_HideTime($row['WhenAdded']);
		    $txtWhenChged	= TimeStamp_HideTime($row['WhenChanged']);
		    $txtWhenCnted	= TimeStamp_HideTime($row['WhenCounted']);
		    $txtWhenRmved	= TimeStamp_HideTime($row['WhenRemoved']);
		    $txtNotes	= $row['Notes'];

		    if ($doMoveForm && $isActive && (!empty($row['Qty']))) {
			$htCk = '<input type=checkbox name="line['.$id.']" />';
		    } else {
			$htCk = '';
		    }

		    if (is_null($row['WhenRemoved'])) {
			$ftList .= ' '.$idItem;
		    }
		    $out .= <<<__END__
<tr style="$htStyle">
  <td>$htCk</td>
  <td>$htID</td>
  <td align=center>$htActive</td>
  <td>$htCatNum</td>
  <td>$txtQty</td>
  <td>$htTitle</td>
  <td>$txtWhenAdded</td>
  <td>$txtWhenChged</td>
  <td>$txtWhenCnted</td>
  <td>$txtWhenRmved</td>
</tr>
__END__;
		}
		$out .= "\n</table>";
		if (!is_null($ftList)) {
		    $out .= '<b>Text list</b> (active only):'.$ftList;
		}
		if ($doForm) {
		    $out .= "\n</form>";
		}
	    } else {
		$out = 'No stock found.';
	    }
	}
	return $out;
    }
    /*----
      PURPOSE: Handle inventory count stuff
	- display of inventory count input controls
	- processing of entered inventory data
	- display of results
	- saving changes to stock data
      HISTORY:
	2010-11-04 extracted from AdminPage(), where I wrote everything except the data-saving part
	2011-03-03 fixed improper handling of items not found in existing data
	2011-04-01 WhenTainted is now cleared and new WhenCounted field is set to NOW()
    */
    protected function AdminInventory() {
	$oPage = $this->Engine()->App()->Page();

	$doInvEnter = $oPage->PathArg('inv');
	$doInvSave = $oPage->ReqArgBool('btnInvSave');

	$out = NULL;
	if ($doInvEnter || $doInvSave) {

	    // show results of user input, if any:
	    $txtInvList = $oPage->ReqArgText('inv');
	    $cntUnk = 0;
	    if (!is_null($txtInvList)) {
		//$arLines = ParseTextLines($txtInvList);
		$xts = new xtString($txtInvList);
		$arLines = $xts->ParseTextLines(array('line'=>'arr'));
		if (is_array($arLines)) {
		    // we have an inventory to process

		    $txtInvList = '';	// rebuild text list after processing
		    $cntNew = 0;
		    $qtyNew = 0;
		    $arAll = NULL;
		    foreach ($arLines as $idx => $arLine) {
//echo 'ARLINE:<pre>'.print_r($arLine,TRUE).'</pre>';
			$catnum = strtoupper($arLine[0]);
			if (array_key_exists(1,$arLine)) {
			    $qty = (int)$arLine[1];
			} else {
			    $qty = 1;
			}
//echo 'CATNUM=['.$catnum.']';
			$objItem = $tItems->Get_byCatNum($catnum);
//echo 'Item: '.$objItem->AdminLink();
			$txtInvList .= "$catnum\t$qty\t";
			if (is_null($objItem)) {
			    $txtInvList .= '! not found';
			    $cntUnk++;
			} else {
			    $txtInvList .= '; OK';
			    $id = $objItem->ID;
			    $arAll[$id]['new'] = nz($arAll[$id]['new']) + $qty;
			    $cntNew++;
			    $qtyNew += $qty;
			}
			$txtInvList .= "\n";
		    }
		    // show results of inventory, for approval
		    $out .= $vgOut->Header('Inventory Count Results',3);

		    // get list of items currently in bin (according to db):
		    $sqlLines = '(ID_Bin='.$this->ID.') AND (WhenRemoved IS NULL)';
		    $rsLines = $this->objDB->StkItems()->GetData($sqlLines);
		    if ($rsLines->HasRows()) {
			while ($rsLines->NextRow()) {
			    $id = $rsLines->Value('ID_Item');
			    $arAll[$id]['old'] = nz($arAll[$id]['old']) + $rsLines->Value('Qty');
			}
		    }

		    if (is_array($arAll)) {	// if any inventory lines have been processed...
			// open a form for saving the results
			$arLink = $vgPage->Args(array('page','id'));
			$urlForm = $vgPage->SelfURL($arLink,TRUE);
			$htRes = '<form method=post action="'.$urlForm.'">';
			$htRes .= '<input type=hidden name=inv value="'.htmlspecialchars($txtInvList).'">';

			// show detailed results of processing (for found items)
			$intChg = 0;
			$intSame = 0;
			$htRes .= "\n<table><tr><th>Item</th><th>was</th><th>now</th><th>status</th></tr>";

			if ($doInvSave) {
			    $out .= 'Saving changes... ';
			    // write to database
			    // -- log the attempt
			    $arEv = array(
			      'code'	=> 'INV',
			      'descr'	=> 'Inventory: '.$txtInvList,
			      'where'	=> __METHOD__
			    );
			    $this->StartEvent($arEv);
			}

			ksort($arAll);
//echo 'ARALL:<pre>'.print_r($arAll).'</pre>'; die();
			foreach ($arAll as $id => $arQty) {
			    $intOld = nzInt($arQty['old']);
			    $intNew = nzInt($arQty['new']);
			    $objItem = $this->objDB->Items()->GetItem($id);
			    $htItem = $objItem->AdminLink($objItem->CatNum).' '.$objItem->FullDescr_HTML();
			    $htRes .= "\n<tr><td>$htItem</td>";
			    if ($intOld == $intNew) {
				$intSame++;
				if ($intNew > 0) {
				    $htStatus = 'match';
				} else {
				    $htStatus = '(ok)';
				}
				$htRes .= "<td colspan=2 align=center>= $intNew =</td><td align=center><font color=green>$htStatus</font></td>";
			    } else {
				$arChg[$id] = $intNew;
				$intChg++;
				$htRes .= "<td align=center>$intOld</td><td align=center>$intNew</td>";

				// calculate displayed status
				if ($intOld < $intNew) {
				    $intDiff = $intNew-$intOld;
				    $htStatus = '<font color=green>'.$intDiff.' new</font>';
				} else {
				    if ($intNew == 0) {
					$htStatus = '<font color=red>MISSING</font>';
				    } else {
					$htStatus = '<font color=orange>'.($intOld-$intNew).' missing</font>';
				    }
				}
				$htRes .= '<td>'.$htStatus.'</td>';
			    }
			    $htRes .= '</tr>';

			    // do data update
			    if ($doInvSave) {
				$sqlItem = $sqlLines.' AND (ID_Item='.$id.')';
				$objLine = $this->objDB->StkItems()->GetData($sqlItem);
				if ($objLine->HasRows()) {
				    // old record exists -- update it
				    // -- make sure there's only one record. If more than one, update the first and delete others.
				    $isFirst = TRUE;
				    while ($objLine->NextRow()) {

					if ($isFirst) {
					    $isFirst = FALSE;
					    // -- update the first record
					    $objLine->UpdateQty($intNew,'inventory count');
					} else {
					    $objLine->UpdateQty(0,'inventory: zeroing duplicate item');
					}

				    }
				} else {
//echo 'NO INVENTORY ENTERED';
				    // no record; create a new record IF we have a quantity to record
				    if ($intNew > 0) {
					$this->InventoryAdd($id,$intNew);
				    }
				}
			    }
//die();

			}

			$htRes .= '</table>';

			// show approval button, if entries are ok to save:
			if (($cntUnk == 0) && !$doInvSave) {
			    $htRes .= '<tr><td colspan=2 align=right><input type=submit name=btnInvSave value="Save Changes"></td></tr>';
			}

			$htRes .= '</form>';

			// generate summary of findings:
			$htSumm = '<b>'.$qtyNew.'</b> piece'.Pluralize($qtyNew).
			  ' (<b>'.$cntNew.'</b> inventory line'.Pluralize($cntNew).') counted: ';
			if ($intChg == 0) {
			    $htSumm .= 'fresh inventory count matches database; no change.';
			} else {
			    $htSumm .= '<b>'.$intChg.'</b> change'.Pluralize($intChg).' found, ';
			    $htSumm .= '<b>'.$intSame.'</b> item'.Pluralize($intSame).' unchanged.';
			}

			// display summary of findings:
			$out .= $htSumm;
			// display details of findings:
			$out .= $htRes;

			if ($doInvSave) {
			    // -- update bin status
			    $arEv = array(
			      'WhenCounted'	=> 'NOW()',
			      'WhenTainted'	=> 'NULL'
			      );
			    $this->Update($arEv);
			    $this->Reload();
			    // -- log completion, with stats
			    $arEv = array(
			      'descr'	=> 'Inventory: '.$htSumm,
			    );
			    $this->FinishEvent($arEv);
			}
		    }
		} else {
		    $out .= 'No items entered!';
		}
	    }

	    if ($doInvEnter) {
		if ($cntUnk) {
		    $txtSng = 'one catalog # was';
		    $txtPlr = $cntUnk.' catalog #s were';
		    $htEntry = Pluralize($cntUnk,$txtSng,$txtPlr).' not recognized:';
		} else {
		    $htEntry = 'one catalog # per line, space, then quantity (optional; default=1)';
		}
		$out .= $vgOut->Header('Inventory Count Entry',3);
		$arLink = $vgPage->Args(array('page','id','inv'));
		$urlForm = $vgPage->SelfURL($arLink,TRUE);
		$out .= '<form method=post action="'.$urlForm.'">';
		$out .= $htEntry;
		$out .= '<textarea name=inv rows=20>'.htmlspecialchars($txtInvList).'</textarea>';
		$out .= '<input type=submit name=btnInvLookup value="Lookup...">';
		$out .= '</form>';
	    }

	    return $out;
	} else {
	    return NULL;	// nothing to do
	}
    }
    /*----
      RETURNS: Rendering of stock bin events for this bin
    */
    public function RenderEvents() {
	$t = $this->Engine()->Make(KS_CLASS_STOCK_BIN_LOG);
	$rs = $t->GetData('ID_Bin='.$this->KeyValue());
	return $rs->RenderRows();		// stock bin event listing
    }
    /*----
      HISTORY:
	2010-11-04 created
	2011-03-03 changed $this->ID_Bin to $this->Value('ID_Bin')
	2011-04-01 changed $this->Value('ID_Bin') to $this->KeyValue() -- did this ever *work*, before??
      NOTE: Does not create event. At the very least, events would need to support recursion
	so that we could start an event while another is incomplete. It's not yet clear that
	this would be useful, so not bothering.
    */
    public function InventoryAdd($iItem,$iQty) {
	$this->objDB->StkItems()->InventoryAdd($this->KeyValue(),$iItem,$iQty);
    }
    /*----
      ACTION: Process user-input changes to the AdminList
      FUTURE: Should this be a method of the table instead of the rowset?
    */
    protected function AdminListSave() {
	$out = '';

	$doSelBins = array_key_exists('btnSelBins',$_REQUEST);
	$doMoveBins = array_key_exists('btnMoveBins',$_REQUEST);

	if ($doSelBins || $doMoveBins) {
	    $arBins = $_REQUEST[KS_ACTION_STOCK_BIN];

	    if ($doSelBins) {
		$out .= '<form method=post>';	 // this is an additional form, not the main one
		$out .= '<b>Bins</b>:';
		foreach ($arBins as $idBin => $zero) {
		    $objBin = $this->Table->GetItem($idBin);
		    $out .= ' '.$objBin->AdminLink($objBin->Code).'<input type=hidden name="bin['.$idBin.']" value=1>';
		}
		$out .= '<br><b>Notes</b> (optional):<br><textarea name=notes height=2 width=40></textarea>';
		$htPlaces = $this->PlaceTable()->DropDown('ID_Place');
		$out .= "\n<br><input type=submit name=btnMoveBins value=\"Move to:\">$htPlaces";
		$out .= '</form>';
	    }
	    if ($doMoveBins) {
		$idPlace = (int)$_REQUEST['ID_Place'];
		$txtNotes = $_REQUEST['notes'];
		$rcPlace = $this->PlaceTable($idPlace);
		$htPlace = $rcPlace->AdminLink($rcPlace->Value('Name'));

		// create overall event:
		$txtBins = '';
		$htBins = '';
		foreach ($arBins as $idBin => $zero) {
		    $objBin = $this->Table->GetItem($idBin);
		    $txtBins .= ' '.$objBin->Code;
		    $htBins .= ' '.$objBin->AdminLink($objBin->Code);
		    $arBins[$idBin] = $objBin->RowCopy();	// so we don't have to look them up again
		}
		$sqlDescr = 'Moving bins to [#'.$idPlace.'='.$rcPlace->Name.']:'.$txtBins;
		$out .= 'Moving bins'.$htBins.' to [#'.$idPlace.'='.$htPlace.']...';

		$arEv = array(
		  'descr'	=> $sqlDescr,
		  'where'	=> __METHOD__,
		  'code'	=> 'MVL',	// MoVe List
		  'notes'	=> $txtNotes
		  );
		$this->StartEvent($arEv);
		foreach ($arBins as $idBin => $objBin) {
		    $objBin->MoveTo($idPlace);
		}
		$out .= ' done.';
		$this->FinishEvent();
	    }
	}
	return $out;
    }
    /*-----
      DEPRECATED - use StartEvent() / FinishEvent()
    */
    public function LogEvent($iSrce,$iDest,$iDescr=NULL) {
	return $this->BinLog()->LogEvent($this->KeyValue(),$iSrce,$iDest,$iDescr);
    }
    /*-----
      ACTION: Log an event where the bin stays in the same place
    */
    public function LogEvent_SamePlace($iDescr) {
	$idPlace = $this->PlaceID();
	$this->LogEvent($idPlace,$idPlace,$iDescr);
    }
/*
    public function SelfLink($iText) {
	return SelfLink_Page(KS_ACTION_STOCK_BIN,'id',$this->ID,$iText);
    }
*/
    // ++ WEB UI CONTROLS ++ //
    /*----
      ACTION: return an array for displaying a dropdown list of the rows in the current dataset
      RETURNS: array suitable for passing to DropDown_arr()
      ASSUMES: the dataset contains at least one row.
    */
    public function DropDown_to_arr($iFilter=NULL) {
	while ($this->NextRow()) {
	    if (!is_null($iFilter)) {
		$ok = $iFilter($this);
	    } else {
		$ok = TRUE;
	    }
	    if ($ok) {
		$arOut[$this->ID] = $this->NameLong();
	    }
	}
	return $arOut;
    }
    /*----
      ACTION: Show a dropdown list consisting of the rows in the current dataset
    */
    public function DropDown($iName,$iDefault=NULL,$iChoose='--choose a bin--',$iFilter=NULL) {
	$ok = FALSE;
	if ($this->HasRows()) {
	    $arRows = $this->DropDown_to_arr($iFilter);
	    if (count($arRows) > 0) {
		$ok = TRUE;
	    }
	}
	if ($ok) {
	    $out = DropDown_arr($iName,$arRows,$iDefault,$iChoose,$iFilter);
	} else {
	    $out = 'No locations found';
	}
	return $out;
    }
    
    // -- WEB UI CONTROLS -- //
}
