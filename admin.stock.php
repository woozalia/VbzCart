<?php
/*
  LIBRARY: admin.stock.php - classes for administering stock in VbzCart
  HISTORY:
    2010-11-03 extracted classes from SpecialVbzAdmin.php:
      VbzStockPlace(s), VbzStockBin(s), VbzStockBinLog, VbzStockBinEvent, VbzAdminStkItem(s), clsStkLog, clsStockEvent
*/
/*
clsLibMgr::Add('vbz.stock',	KFP_LIB_VBZ.'/base.stock.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsStkItems','vbz.stock');
clsLibMgr::Add('vbz.cat',	KFP_LIB_VBZ.'/base.cat.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsSuppliers','vbz.cat');
*/
/* *****
 STOCK MANAGEMENT
*/
class VbzStockPlaces extends clsTableCache {
    protected $idEvent;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(ksTbl_stock_places);
	  $this->KeyName('ID');
	  $this->ClassSng('VbzStockPlace');
	  $this->ActionKey('place');
    }

    /*====
      BOILERPLATE: cache management (table)
    */
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
    // /BOILERPLATE
    // ====

    public function ActionKey($iName=NULL) {
	if (!is_null($iName)) {
	    $this->ActionKey = $iName;
	}
	return $this->ActionKey;
    }
    //-----
    public function DropDown($iName,$iDefault=NULL,$iFilt=NULL,$iSort='Name') {
	$objRows = $this->GetData($iFilt,NULL,$iSort);
	return $objRows->DropDown($iName,$iDefault);
    }
    public function AdminPage() {
	global $wgOut;
/*
	$out = '==Stock Locations=='."\n";
	$wgOut->addWikiText($out,TRUE);	$out = '';
*/
	$out = $this->Listing();
	$wgOut->addHTML($out,TRUE);	$out = '';
    }
    /*-----
      ACTION: Show list of all Places within the given parent Place
    */
    public function Listing($iParent=0,$iTitle='Stock Locations') {
	global $wgRequest;
	global $vgPage,$vgOut;

	if ($iParent == 0) {
	    $htHdrLv = 2;
	} else {
	    $htHdrLv = 3;
	}

	$strAdd = $vgPage->Arg('add');
	$doAdd = ($strAdd == 'place');

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$iTitle,NULL,$htHdrLv);
	//$objSection->ActionAdd('add');
	$out = $objSection->Generate();

	// check for changes via form submission
	if ($wgRequest->getBool('btnAddPlace')) {
	    $strName = $wgRequest->getText('plName');
	    $strDesc = $wgRequest->getText('plDesc');
	    $sqlParent = (empty($iParent))?'NULL':SQLValue($iParent);
	    $arIns = array(
	      'Name'	=> SQLValue($strName),
	      'Descr'	=> SQLValue($strDesc),
	      'ID_Parent'	=> $sqlParent
	      );
	    $arEv = array(
	      'descr'	=> 'Adding new Place ['.$strName.']',
	      'where'	=> __METHOD__,
	      'code'	=> '+PL'
	      );

	    $doEvent = ($iParent != 0);
	    if ($doEvent) {
		$objParent = $this->GetItem($iParent);
		$objParent->StartEvent($arEv);	// no record object to receive an event
	    }
	    $this->Insert($arIns);
	    $id = $this->LastID();
	    if ($doEvent) {
		$arEv = array(
		  'id'	=> $id
		  );
		$objParent->FinishEvent($arEv);
	    }
	}

	// we have to get all the data, to get all sub-places recursively:
	$objRow = $this->GetData(NULL,NULL,'Name');

	$gotSome = FALSE;

	if ($objRow->hasRows() || $doAdd) {
	    while ($objRow->NextRow()) {
		//$arRows[$objRow->ID] = $objRow->Row;
		$idParent = $objRow->Value('ID_Parent');
		$idRow = $objRow->KeyValue();
		$arData[$idRow] = $objRow->RowCopy();
		if (is_null($idParent)) {
		    $arTree[0][] = $idRow;
		} else {
		    $arTree[$idParent][] = $idRow;
		}
	    }
	    $hasRows = isset($arTree[$iParent]);
	    $doTable = $hasRows || $doAdd;
	    if ($doTable) {
		$gotSome = TRUE;
		if ($doAdd) {
		    $arLink = $vgPage->Args(array('page'));
		    //$arLink['add'] = FALSE;
		    $urlSelf = $vgPage->SelfURL($arLink);
		    $out .= '<form method=post action="'.$urlSelf.'">';
		}
		$out .= "\n<table class=sortable><tr><th>ID</th><th>name</th><th>description</tr>";
		$out .= $this->ListSubStkPlaces($arData,$arTree,$iParent);
		if ($doAdd) {
		    $out .= '<tr><td><i>new</i></td><td><input name=plName size=8></td><td><input name=plDesc size=30></td></tr>';
		}
		$out .= "\n</table>";
		if ($doAdd) {
		    $out .= '<input type=submit name=btnAddPlace value="Add Place">';
		    $out .= '</form>';
		}
	    }
	}
	if (!$gotSome) {
	    $out .= 'none found';
	}
	$arLink = $vgPage->SelfArray();
	$arLink['add'] = 'place';
	$out .= ' ['.$vgOut->SelfLink($arLink,'add a new one').']';
	return $out;
    }
    private function ListSubStkPlaces($iData, $iTree, $iBranch=0, $iInd=0) {
	global $vgPage;

	$vgPage->UseHTML();

	$ind = $iInd+1;
	$out = '';

	if (isset($iTree[$iBranch])) {
	    foreach($iTree[$iBranch] AS $key=>$ID) {
		$objRow = $iData[$ID];
  /*
		$htPlacePfx = '[[{{FULLPAGENAME}}/page'.KS_CHAR_URL_ASSIGN.'place/id'.KS_CHAR_URL_ASSIGN.$ID.'|';
		$htPlaceName = $htPlacePfx.$row['Name'].']]';
  */
		$htPlace = $objRow->AdminLink_name();
		$out .= "\n<tr>"
		  .'<td>'.$ID.'</td>'
		  .'<td><font style="color: grey;">'.str_repeat('+&nbsp;',$iInd).'</font>'.$htPlace.'</td>'
		  .'<td>'.$objRow->Descr.'</td>'
		  .'</tr>';
		$out .= $this->ListSubStkPlaces($iData,$iTree,$ID,$ind);
	    }
	}
	return $out;
    }
}
class VbzStockPlace extends clsDataSet {
    protected $objParent;

    /*====
      BOILERPLATE: self-linkage
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminRedirect($this,$iarArgs);
    }
    /*====
      BOILERPLATE: event logging
      HISTORY:
	2010-10-30 was using old boilerplate event-handling methods; now using helper class boilerplate
	  Event methods removed from plural class; helper-class methods added to singular class
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
    /*====
      BOILERPLATE: cache management (recordset)
    */
    /*----
      ACTION: update the cache record to show that this table has been changed
    */
    protected function CacheStamp($iCaller) {
	$this->Table->CacheStamp($iCaller);
    }
    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp(__METHOD__);
    }
    // /BOILERPLATE
    //====

    /*====
      SECTION: boilerplate extensions
    */
/*
    public function AdminLink_name() {
	return $this->AdminLink($this->Value('Name'));
    }
*/
    /*====
      SECTION: basic functions
    */
    public function InitNew() {
	$this->ID = 0;
	$this->ID_Parent = NULL;
    }
    /*----
      RETURNS; TRUE if this place AND all ancestors are marked active (isActive=TRUE).
	If any ancestors are isActive=FALSE, then this one is not active even if it is
	marked isActive=TRUE. This lets us control activation for an entire area and all its contents
	with one change.
    */
    public function IsActive() {
	if ($this->Row['isActive']) {
	    if ($this->HasParent()) {
		return $this->ParentObj()->IsActive();
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
    public function HasParent(VbzStockPlace $iObj=NULL) {
	if (is_object($iObj)) {
	    if ($this->HasParent()) {
		if ($this->ID_Parent == $iObj->KeyValue()) {
		    return TRUE;
		} else {
		    $obj = $this->ParentObj();
		    return $obj->HasParent($iObj);
		}
	    } else {
		return FALSE;
	    }
	} else {
	    return !is_null($this->Value('ID_Parent'));
	}
    }
    /*----
      HISTORY:
	2010-11-30 Added object caching
    */
    public function ParentObj() {
	if ($this->HasParent()) {
	    $obj = $this->objParent;
	    if (!is_object($obj)) {
		$objPlaces = new VbzStockPlaces($this->objDB);
		$obj = $objPlaces->GetItem($this->Value('ID_Parent'));
	    }
	    $this->objParent = $obj;
	} else {
	    $obj = NULL;
	}
	return $obj;
    }
    public function AdminLink_name() {
	$ftLink = $this->AdminLink($this->Value('Name'));
	if ($this->Row['isActive']) {
	    $htStyleActv = '';
	} else {
	    $htStyleActv = 'text-decoration: line-through;';
	}
	if ($this->HasParent()) {
	    if (!$this->ParentObj()->IsActive()) {
		$htStyleActv .= ' background-color: #aaaaaa;';
	    }
	}
	return '<span style="'.$htStyleActv.'">'.$ftLink.'</span>';
    }
    /*====
      SECTION: user interface
    */
    /*-----
      ACTION: Display information about the current Place
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$strName = $this->Value('Name');
	$strTitle = 'Stock Location '.$this->KeyValue().': '.$strName;

	$vgPage->UseHTML();	// forms require HTML
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit','edit '.$strName);
	$objSection->ToggleAdd('inventory','list all inventory of location '.$strName,'inv');
	$out = $objSection->Generate();

	$doEdit = $vgPage->Arg('edit');
	$doUpd = $vgPage->Arg('update');
	$doInv = $vgPage->Arg('inv');
	$doSave = $wgRequest->GetBool('btnSave');

	if ($doEdit || $doSave) {
	    $this->BuildEditForm(FALSE);
	    if ($doSave) {
		$out .= $this->objForm->Save();
		//$this->CacheStamp(__METHOD__.'@'.__LINE__);
	    }
	}

	if ($doUpd) {
	    // update any calculated fields
	    $arUpd = array(
	      'isEnabled' => ($this->ParentObj()->IsActive())?'TRUE':'FALSE'
	      );
	    $this->Update($arUpd);
	    $this->AdminRedirect();
	}

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $htPath = $vgPage->SelfURL($arLink,TRUE);
	    $out .= "\n<form method=post action=\"$htPath\">";

	    $objForm = $this->objForm;

	    //$sqlFilt = is_null($this->ID_Parent)?NULL:('ID_Parent != '.$this->ID_Parent);
	    //$ctrlParent = $this->Table->DropDown('ID_Parent',$this->ID_Parent,$sqlFilt);
	    $ctrlParent = $this->DropDown_meParent('ID_Parent');

	    $ctrlActv = $objForm->Render('isActive');
	    $ctrlName = $objForm->Render('Name');
	    $ctrlDescr = $objForm->Render('Descr');
   
	} else {
	    if ($this->HasParent()) {
		$objParent = $this->ParentObj();
		$ctrlParent = $objParent->AdminLink($objParent->Value('Name'));
	    } else {
		$ctrlParent = 'root';
	    }
	    $ctrlActv = NoYes($this->Value('isActive'));
	    $ctrlName = $strName;
	    $ctrlDescr = htmlspecialchars($this->Value('Descr'));
	}
	if ($this->HasParent()) {
	    $isEnabled = $this->Value('isEnabled');		// enabled flag is set?
	    $doEnabled = $this->ParentObj()->IsActive();	// enabled flag *should be* set?
	    $ctrlActv .= ' <b>Enabled</b>: '.NoYes($isEnabled);
	    if ($isEnabled != $doEnabled) {
		$arLink = $vgPage->Args(array('page','id'));
		$arLink['update'] = TRUE;
		$urlUpd = $vgPage->SelfURL($arLink,TRUE);

		$ctrlActv .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.NoYes($doEnabled);
	    }
	}

	$out .= $vgOut->TableOpen();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Active</b>','align=right');
	  $out .= $vgOut->TblCell($ctrlActv);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Parent</b>','align=right');
	  $out .= $vgOut->TblCell($ctrlParent);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Name</b>','align=right');
	  $out .= $vgOut->TblCell($ctrlName);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Description</b>','align=right');
	  $out .= $vgOut->TblCell($ctrlDescr);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TableShut();

	if ($doEdit) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '</form>';
	}

	$vgOut->AddText($out);	$out = '';

	if ($doInv) {
	    // for now, this only looks at bins in the immediate location
	    // later, we might want to allow for iterating through sub-locations too
	    $objSection = new clsWikiSection($objPage,'Inventory',NULL,3);
	    $out .= $objSection->Generate();
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

	    $wgOut->addHTML($out);	$out = '';
	}

	$out = $this->SubsList();
	$wgOut->addHTML($out);	$out = '';
	$out = "\n===Bins===\n";
	$wgOut->addWikiText($out,TRUE);	$out = '';
	$out .= $this->BinsList();
	$wgOut->addHTML($out,TRUE);	$out = '';
	$out = "\n===Events===\n";
	$wgOut->addWikiText($out,TRUE);	$out = '';
	$out .= $this->EventListing();
	$vgOut->addText($out);	$out = '';
    }
    /*----
      ACTION: build admin edit form controls
    */
    protected function BuildEditForm($iNew) {
	global $vgOut;

	// create fields & controls

	if (is_null($this->objForm)) {
	    //$vData = $iNew?NULL:$this->Row;
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objFlds = new clsFields($vData);
	    //$objCtrls = new clsCtrls($objFlds);

	    $objForm->AddField(new clsFieldNum('ID_Parent'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsFieldBool_Int('isEnabled'),	new clsCtrlHTML());	// this will not actually be rendered
	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>50)));

	    //$this->objFields = $objFlds;
	    //$this->objCtrls = $objCtrls;
	    $this->objForm = $objForm;
	}
    }
    /*=====
      ACTION: Returns name plus some parental context
    */
    public function NameLong() {
	$out = $this->Name;
	if (!is_null($this->ID_Parent)) {
	    $out .= ' &larr; '.$this->Table->GetItem($this->ID_Parent)->Name;
	}
	return $out;
    }
    public function NameLong_text() {
	$out = $this->Name;
	if (!is_null($this->ID_Parent)) {
	    $out .= ' < '.$this->Table->GetItem($this->ID_Parent)->Name;
	}
	return $out;
    }

    /*-----
      ACTION: Show a dropdown list consisting of the rows in the current dataset
      NOTE: WHO USES THIS?
    */
    public function DropDown($iName,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    while ($this->NextRow()) {
		$out .= DropDown_row($this->ID,$this->NameLong_text(),$iDefault);
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No locations matching filter';
	}
	return $out;
    }
    /*-----
      ACTION: Render a dropdown list of all rows, with the current row as the default
    */
    public function DropDown_meDefault($iName) {
	$objRows = $this->Table->GetData(NULL,NULL,'Name');
	return $objRows->DropDown($iName,$this->ID);
    }
    /*----
      ACTION: Render a dropdown list of all rows, except:
	* do not include the current row
	* do not include any descendants of this row
	Also, use ID_Parent as the default.
    */
    public function DropDown_meParent($iName) {
	$objRows = $this->Table->GetData('ID != '.$this->ID,NULL,'Name');
	while ($objRows->NextRow()) {
	    if (!$objRows->HasParent($this)) {
		$arRows[$objRows->KeyValue()] = $objRows->NameLong_text();
	    }
	}
	return DropDown_arr($iName,$arRows,$this->Row['ID_Parent'],'--ROOT--');
    }
    // DEPRECATED -- use DropDown_meDefault()
    public function DropDown_All($iName) {
	return $this->DropDown_meDefault($iName);
    }
    /*-----
      ACTION: Show table of all Places inside this Place (via Places->Listing())
    */
    public function SubsList() {
	$objPlaces = $this->Engine()->Places();
	$out = $objPlaces->Listing($this->KeyValue(),'Sub-Locations');
	return $out;
    }
    /*----
      RETURNS: dataset of Bins in the current Place
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function BinsData() {
	$sqlFilt = 'ID_Place='.$this->Value('ID');
	$tbl = $this->Engine()->Bins();
	$rs = $tbl->GetData($sqlFilt);
	return $rs;
    }
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
		    $qtySum = nzArray($arOut,$idItem);
		    $arOut[$idItem] = $qtySum + $qty;
		}
	    } else {
		$ftNoStk .= '['.$rs->AdminLink_name().']';
	    }
	}
	$this->ftNoStk = $ftNoStk;
	return $arOut;
    }
    /*-----
      ACTION: Show table of all Bins inside this Place (via Bins->Listing())
    */
    public function BinsList() {
	$objBins = $this->Engine()->Bins();
	$out = $objBins->List_forPlace($this->KeyValue());
	return $out;
    }
}
class VbzStockBins extends clsAdminTable {
    protected $idEvent;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(ksTbl_stock_bins);
	  $this->KeyName('ID');
	  $this->ClassSng('VbzStockBin');
	  $this->ActionKey('bin');
    }

    /*====
      BOILERPLATE: cache management (table)
    */
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
    // /BOILERPLATE
    // ====

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
    /*----
      NOTE: For listing multiple items, use Info_forItems()
    */
    public function Qty_ofItem($iItem) {
	$sql = 'SELECT QtyExisting FROM qryStk_items_remaining WHERE ID_Item='.$iItem;
	$objRow = $this->objDB->DataSet($sql);
	if ($objRow->HasRows()) {
	    $objRow->NextRow();
	    $out = $objRow->QtyExisting;
	} else {
	    $out = 0;
	}
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
	$objRows->AdminList(array('do.place'=>TRUE));
    }
    /*-----
      ACTION:
	Show table of all bins within the given Place
	Show form to allow user to move selected bins
    */
    public function List_forPlace($iPlace) {
	$sqlFilt = 'ID_Place='.$iPlace;

	$objRows = $this->DataSet_Info($sqlFilt);
	$objRows->AdminList(array('do.place'=>FALSE));
    }
}
class VbzStockBin extends clsDataSet {
    protected $idEvent;

    /*====
      BOILERPLATE: self-linkage
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminRedirect($this,$iarArgs);
    }
    /*====
      BOILERPLATE: event logging
      HISTORY:
	2011-02-18 converted to helper-class boilerplate
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
    public function StartEvent(array $iArgs,array $iEdits) {
	return $this->Log()->StartEvent($iArgs,$iEdits);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    /*====
      BOILERPLATE: cache management (recordset)
    */
    /*----
      ACTION: update the cache record to show that this table has been changed
    */
    protected function CacheStamp($iCaller) {
	$this->Table->CacheStamp($iCaller);
    }
    /*----
      PURPOSE: intercepts the Update() function to update the cache timestamp
    */
    public function Update(array $iSet,$iWhere=NULL) {
	parent::Update($iSet,$iWhere);
	$this->CacheStamp(__METHOD__);
    }
    // /BOILERPLATE
    //====


    //====
    // EXTENSIONS to boilerplate
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
    // /EXTENSIONS
    //====

    // FIELD FUNCTIONS -- simple
    public function Name() {
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
    /*----
      RETURNS: TRUE IFF bin is in an active Place
	In other words, returns how the isEnabled flag *should* be set.
    */
    public function IsValid() {
	return $this->PlaceObj()->IsActive();
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
    /*----
      HISTORY:
	2011-03-19 Return a Spawned item if ID_Place is not set -- presuming
	  we need the object for other purposes besides its current values
    */
    public function PlaceObj() {
	//$objPlaces = new VbzStockPlaces($this->objDB);
	if ($this->HasValue('ID_Place')) {
	    $objTbl = $this->Engine()->Places();
	    $obj = $objTbl->GetItem($this->Value('ID_Place'));
	    return $obj;
	} else {
	    return $this->objDB->Places()->SpawnItem();
	}
    }
    public function StkItems_data() {
	return $this->Engine()->StkItems()->Data_forBin($this->Value('ID'));
    }
    // BUSINESS RULES
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
    /*----
      DEPRECATED -- use VbzStockBins::Qty_ofItem()
    */
    public function Qty_ofItem($iItem) {
	return $this->Table->Qty_ofItem($iItem);
/*
	$sql = 'SELECT QtyExisting FROM qryStk_items_remaining WHERE ID_Item='.$iItem;
	$objRow = $this->objDB->DataSet($sql);
	if ($objRow->HasRows()) {
	    $objRow->NextRow();
	    $out = $objRow->QtyExisting;
	} else {
	    $out = 0;
	}
	return $out;
*/
    }
    /*-----
      ACTION: Move the current bin to the given Place, and log the move
    */
    public function MoveTo($iPlace) {
	$objPlace = $this->objDB->Places()->GetItem($iPlace);
	$txtEv = 'Moving from [#'.$this->ID_Place.'='.$this->PlaceObj()->Name.'] to [#'.$iPlace.'='.$objPlace->Name.']';

	$arEv = array(
	  'descr' => $txtEv,
	  'where' => __METHOD__,
	  'code'  => 'MV'
	  );
	$this->StartEvent($arEv);

	$arUpd['ID_Place'] = $iPlace;
	$this->Update($arUpd);

	// log the move - old style
	$idPlaceOld = $this->ID_Place;
	$idPlaceNew = $iPlace;
	$this->LogEvent($idPlaceOld,$idPlaceNew,$txtEv);

	$this->FinishEvent();
    }
    // ADMINISTRATIVE FUNCTIONS
    /*-----
      ACTION: Display bin details and contents (via this->Contents())
      HISTORY:
	2011-04-01 moved AdminInventory() up so it takes place before record is displayed
    */
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	$doSave = $wgRequest->getVal('btnSave');
	$isNew = $this->IsNew();
	$doAdd = $isNew;
	$doEdit = ($vgPage->Arg('edit')) || $isNew;
	$doUpd = $vgPage->Arg('update');
	$strDo = $vgPage->Arg('do');

	// process any data-changing user input -- goes before the main header
	$out = '';
	$vgPage->UseHTML();	// this must be called before BuildEditForm()
//	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		if ($this->IsNew()) {
		    $this->objForm->NewVals(array('WhenCreated'=>'NOW()'));
		} else {
		    //$this->objForm->NewVals(array('WhenCreated'=>'NOW()'));
		    // LATER: have a WhenChanged field for any time stuff has moved
		}
		$out .= $this->objForm->Save();	// save edit
		$this->AdminRedirect();	// 2011-11-05 does "return" make this work now?
		return;
	    }
//	}

	$doEnabled = $this->PlaceObj()->IsActive();
	if ($doUpd) {
	    // update any calculated fields
	    $arUpd = array(
	      'isEnabled' => $doEnabled?"b'1'":"b'0'"
	      );
	    $this->Update($arUpd);
	    $this->AdminRedirect();
	}

/*
  LATER TOFIX
  For some reason, $this->AdminInventory() is resetting $this->objForm.
  It shouldn't be doing this, but it looks like it may take some time to figure out
    why it is doing this... so for now, just snag the object while it's still there.
*/
	$objForm = $this->objForm;

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
	    $strName = 'New bin';
	    $dtWhenCreated = NULL;
	} else {
	    $id = $this->KeyValue();
	    $strCode = $this->Value('Code');
	    $strName = 'Stock Bin '.$id.' - '.$strCode;
	    $dtWhenCreated = $this->Value('WhenCreated');
	}
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
/*
	$objSection = new clsWikiSection($objPage,$strName);
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('edit');
	//$objSection->ActionAdd('view');
	$out .= $objSection->Generate();
*/
	$objSection = new clsWikiSection_std_page($objPage,$strName);
	$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));
	$out = $objSection->Render();


	$wgOut->AddHTML($out); $out = '';

	$objPlace = $this->PlaceObj();

	if ($doEdit) {
	    $out .= $objSection->FormOpen();

	    $htCode = $objForm->Render('Code');
	    if ($isNew) {
		$htPlace = $this->objDB->Places()->DropDown('ID_Place');
	    } else {
		$htPlace = $objPlace->DropDown_All('ID_Place');
	    }
	    $htDescr = 	$objForm->Render('Descr');
	    $htNotes = 	$objForm->Render('Notes');
	    $objForm->Ctrl('isEnabled')->Field()->ValBool($doEnabled);	// set the enabled flag to save properly
	    $htStatus =
	      $objForm->Render('isForSale').'for sale '
	      .$objForm->Render('isForShip').'for shipping'
	      .$objForm->Render('isEnabled');
	    $htWhenVoided =	$objForm->Render('WhenVoided');
	    $htWhenTainted =	$objForm->Render('WhenTainted');
	} else {
	    $htCode = $this->AdminLink_name();
	    $htPlace = $objPlace->AdminLink_name();
	    $htDescr = $this->Value('Descr');
	    $htNotes = $this->Value('Notes');
	    $isForSale = $objForm->Ctrl('isForSale')->Field()->ValBool();
	    $isForShip = $objForm->Ctrl('isForShip')->Field()->ValBool();
	    $isEnabled = $objForm->Ctrl('isEnabled')->Field()->ValBool();

	    $htStatus =
	      ' '.($isForSale?'<b>':'<s>').'SELL'.($isForSale?'</b>':'</s>').
	      ' '.($isForShip?'<b>':'<s>').'SHIP'.($isForShip?'</b>':'</s>').
	      ' '.($isEnabled?'<b>':'<s>').'ENABLED'.($isEnabled?'</b>':'</s>');

	    $doEnabled = $this->PlaceObj()->IsActive();
	    if ($isEnabled != $doEnabled) {
		$arLink = $vgPage->Args(array('page','id'));
		$arLink['update'] = TRUE;
		$urlUpd = $vgPage->SelfURL($arLink,TRUE);

		$txtStat = $doEnabled?'enabled':'disabled';
		$htStatus .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.$txtStat;
	    }

	    $dtVoided = $this->Value('WhenVoided');
	    if (is_null($dtVoided)) {
		$htWhenVoided = $vgPage->SelfLink(array('do'=>'void'),'void now');
	    } else {
		$htWhenVoided = $this->Value('WhenVoided');
	    }
	    $htWhenTainted = $this->Value('WhenTainted');
	    $htNotes = htmlspecialchars($this->Value('Notes'));
	}
	// never edited directly:
	$htWhenCreated = $dtWhenCreated;
	$htWhenCounted = $this->ValueNz('WhenCounted');

	$out .= $txtInv;	// display results of inventory count, if any

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>Code</b>:</td><td>$htCode</td></tr>";
	$out .= "\n<tr><td align=right><b>Where</b>:</td><td>$htPlace</td></tr>";
	$out .= "\n<tr><td align=right><b>Status</b>:</td><td>$htStatus</td></tr>";
	if ($doEdit || !is_null($this->Value('Descr'))) {
	    $out .= "\n<tr><td align=right><b>Description</b>:</td><td>$htDescr</td></tr>";
	}
	$out .= "\n<tr><td align=right><b>Created</b>:</td><td>$htWhenCreated</td></tr>";
	$out .= "\n<tr><td align=right><b>Tainted</b>:</td><td>$htWhenTainted</td></tr>";
	$out .= "\n<tr><td align=right><b>Counted</b>:</td><td>$htWhenCounted</td></tr>";
	$out .= "\n<tr><td align=right><b>Voided</b>:</td><td>$htWhenVoided</td></tr>";
	$out .= '</table>';
	if ($doEdit || !is_null($this->Value('Notes'))) {
	    $out .= "\n<b>Notes</b>:<br>$htNotes";
	}

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
	$wgOut->AddHTML($out); $out = '';

//	$out = "===Contents===\n";
	if (!$isNew) {
	    $objFmt = new clsWikiFormatter($vgPage);
	    $sHdr = 'items in '.$strName;
/*
	    $objHdr = new clsWikiSection($objFmt,$sHdr,3);
	    $objHdr->ActionAdd('move','move items from '.$strName.' to another one',NULL,'move.items');
	    $objHdr->ToggleAdd('count','record inventory count for '.$strName,'inv');
	    $out = $objHdr->Generate();
*/
	    $objSection = new clsWikiSection_std_page($objPage,$sHdr,3);
	    //$arLink = array('edit'=>TRUE)
	    $oLink = new clsWikiSectionLink_option(array(),'move.items','do','move');
	      //(array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	      $oLink->Popup('move items from '.$strName.' to another one');
	      $objSection->AddLink_local($oLink);
	    $oLink = new clsWikiSectionLink_option(array(),'inv','do','count');
	      $oLink->Popup('record inventory count for '.$strName);
	      $objSection->AddLink_local($oLink);
	    $out = $objSection->Render();

	    $wgOut->AddHTML($out); $out = '';
	    $wgOut->addHTML($this->Contents());
	    $wgOut->addWikiText('===History===',TRUE);
	    $vgOut->addText($this->EventTable(),TRUE);	// this needs to be merged with the universal log
	    $vgOut->addText($this->EventListing());	// universal log
	}
    }
    /*----
      HISTORY:
	2010-11-01 adapted from clsPackage
    */
    private function BuildEditForm() {
	global $vgOut;
	// create fields & controls

	if (is_null($this->objForm)) {
	    //$vData = $iNew?NULL:$this->Row;
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());
	    $objForm->AddField(new clsFieldNum('ID_Place'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Code'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>30)));
	    $objForm->AddField(new clsFieldTime('WhenVoided'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenTainted'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldBool('isForSale'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsFieldBool('isForShip'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsFieldBool('isEnabled'),	new clsCtrlHTML_Hidden());
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>40)));

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
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
	global $vgPage,$vgOut;

	$vgPage->UseHTML();

	$out = $this->AdminListSave();	// make any requested changes

	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,'Stock Bins');
	//$objSection->ActionAdd('edit');
	//$objSection->ActionAdd('add...','add a new bin');
	$out .= $objSection->Generate();
	$vgOut->addText($out);	$out = '';

	$objRow = $this;
	if ($objRow->hasRows()) {
	    $doPlace = nz($iArgs['do.place']);
	    $arLink = $vgPage->Args(array('page'));
	    //$arLink['edit'] = FALSE;
	    $urlSelf = $vgPage->SelfURL($arLink);
	    // for this list, we always display the form
	    $out .= "\n".'<form method=post action="'.$urlSelf.'">';

	    $out .= $vgOut->TableOpen('class=sortable');
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('ID');
	      $out .= $vgOut->TblCell('status');
	      if ($doPlace) {
		  $out .= $vgOut->TblCell('where');
	      }
	      $out .= $vgOut->TblCell('code');
	      $out .= $vgOut->TblCell('qtys');
	      $out .= $vgOut->TblCell('description');
	      $out .= $vgOut->TblCell('when<br>created');
	      $out .= $vgOut->TblCell('when<br>tainted');
	      $out .= $vgOut->TblCell('when<br>counted');
	      $out .= $vgOut->TblCell('when<br>voided');
	     $out .= $vgOut->TblRowShut();

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
		    $objPlace = $objRow->PlaceObj();
		    $htPlace = $objPlace->AdminLink_name();
/*
		    if ($objPlace->IsActive()) {
			$htActive = $chActive;
		    } else {
			$htActive = $isActive?'<font color=red>x</font>':'';
			$htCellPfx = '<s>';
			$htCellSfx = '</s>';
		    }
*/
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

		$out .= $vgOut->TblRowOpen('style="'.$htStyle.'"');
		  $out .= $vgOut->TblCell($htCellPfx.$htID.$htCellSfx);
		  $out .= $vgOut->TblCell($htActive);
		  if ($doPlace) {
		      $out .= $vgOut->TblCell($htPlace);
		  }
		  $out .= $vgOut->TblCell($htCellPfx.$htCode.$htCellSfx);
		  $out .= $vgOut->TblCell($htCellPfx.$htQty.$htCellSfx);
		  $out .= $vgOut->TblCell($htCellPfx.'<small>'.$row['Descr'].'</small>'.$htCellSfx);
		  $out .= $vgOut->TblCell($htCellPfx.$htWhenMade.$htCellSfx);
		  $out .= $vgOut->TblCell($htWhenTaint);
		  $out .= $vgOut->TblCell($htWhenCount);
		  $out .= $vgOut->TblCell($htWhenVoid);
		$out .= $vgOut->TblRowShut();
	    }
	    $out .= $vgOut->TableShut();
	    $out .= "\n<input type=submit name=btnSelBins value=\"Move to...\">";
	    $arLink = array(
	      'page'	=> 'bin',
	      'id'	=> 'new'
	      );
	    $out .= '[ '.$vgOut->SelfLink($arLink,'create new bin').' ]';
	    $out .= "\n</form>";
	}

	$vgOut->addText($out);	$out = '';
    }
    /*-----
      ACTION: Display bin contents
    */
    public function Contents() {
	global $wgRequest;
	global $vgPage;

	$doMoveForm = ($vgPage->Arg('do') == 'move.items');
	$doConfForm = $wgRequest->getVal('btnMove');
	$doMoveNow = $wgRequest->getVal('btnConf');

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
		$arLine = $wgRequest->getIntArray('line');
		//$arLine = $_POST['line'];
		if (is_array($arLine)) {
		    $idDest = $wgRequest->getInt('bin');
		    $strNotes = $wgRequest->getText('notes');
		    $objDest = $this->objDB->Bins()->GetItem($idDest);
		    $strCodeThis = $this->Code;
		    $strCodeDest = $objDest->Code;

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
			    $objStkLine = $this->objDB->StkItems()->GetItem($id);
			    $idItem = $objStkLine->Value('ID_Item');
			    $objItem = $this->objDB->Items()->GetItem($idItem);
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
		    $out .= 'No items listed to move.';
		}
	    }

	    $sqlSource = ksTbl_stock_items.' AS si LEFT JOIN qryCat_Items AS ci ON si.ID_Item=ci.ID';
	    $sqlCols = 'si.*, ci.CatNum AS ItCatNum, ci.ID_Title, ci.Title_Name AS TiName';
	    $sql = 'SELECT '.$sqlCols.
		' FROM '.$sqlSource.
		' WHERE ID_Bin='.$idBin.
		' ORDER BY WhenRemoved,ItCatNum';

	    $objRow = $this->objDB->DataSet($sql,'VbzAdminItem');
	    if ($objRow->hasRows()) {
		$tblItems = $this->Engine()->Items();
		$objRow->Table = $this->objDB->StkItems();
		if ($doMoveForm) {
		    $objBins = $this->objDB->Bins()->GetActive();
		    $out .= 'To: '.$objBins->DropDown('bin');
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
		    $objItem	= $tblItems->GetItem($idItem);
		    $txtCatNum	= is_null($row['CatNum'])?"<i>".$row['ItCatNum']."</i>":$row['CatNum'];
		    $htCatNum	= $objItem->AdminLink($txtCatNum);
		    $isActive	= is_null($row['WhenRemoved']);
		    $htActive	= $isActive?'&radic;':'';
		    $txtQty	= $row['Qty'];
		    //$txtTitle	= $objRow->StoreLink_HT($row['TiName']);
		    $txtTitle	 = $objRow->Title()->AdminLink($row['TiName']);
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
		    $out .= "\n<tr style=\"$htStyle\">"
		      ."<td>$htCk</td>"
		      ."<td>$htID</td>"
		      ."<td align=center>$htActive</td>"
		      ."<td>$htCatNum</td>"
		      ."<td>$txtQty</td>"
		      ."<td>$txtTitle</td>"
		      ."<td>$txtWhenAdded</td>"
		      ."<td>$txtWhenChged</td>"
		      ."<td>$txtWhenCnted</td>"
		      ."<td>$txtWhenRmved</td>"
		      .'</tr>';
		}
		$out .= '</table>';
		if (!is_null($ftList)) {
		    $out .= '<b>Text list</b> (active only):'.$ftList;
		}
		if ($doForm) {
		    $out .= '</form>';
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
	global $wgRequest;
	global $vgPage,$vgOut;

	$doInvEnter = ($vgPage->Arg('inv'));
	$doInvSave = $wgRequest->GetBool('btnInvSave');

	$out = NULL;
	if ($doInvEnter || $doInvSave) {

	    // show results of user input, if any:
	    $txtInvList = $wgRequest->GetText('inv');
	    $cntUnk = 0;
	    if (!is_null($txtInvList)) {
		//$arLines = ParseTextLines($txtInvList);
		$xts = new xtString($txtInvList);
		$arLines = $xts->ParseTextLines(array('line'=>'arr'));
//echo '<br>TXTINVLIST=['.$txtInvList.']';
//echo 'ARLINES:<pre>'.print_r($arLines,TRUE).'</pre>';
		if (is_array($arLines)) {
		    // we have an inventory to process
		
		    $txtInvList = '';	// rebuild text list after processing
		    $cntNew = 0;
		    $qtyNew = 0;
		    $arAll = NULL;
		    $tblItems = $this->objDB->Items();
		    foreach ($arLines as $idx => $arLine) {
//echo 'ARLINE:<pre>'.print_r($arLine,TRUE).'</pre>';
			$catnum = strtoupper($arLine[0]);
			if (array_key_exists(1,$arLine)) {
			    $qty = (int)$arLine[1];
			} else {
			    $qty = 1;
			}
//echo 'CATNUM=['.$catnum.']';
			$objItem = $tblItems->Get_byCatNum($catnum);
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
	global $wgRequest;
	global $vgPage;

	$out = '';
	if ($wgRequest->getBool('btnSelBins')) {
	    $arBins = $wgRequest->getIntArray('bin');
	    $out .= '<form method=post>';	 // this is an additional form, not the main one
	    $out .= '<b>Bins</b>:';
	    foreach ($arBins as $idBin => $zero) {
		$objBin = $this->Table->GetItem($idBin);
		$out .= ' '.$objBin->AdminLink($objBin->Code).'<input type=hidden name="bin['.$idBin.']" value=1>';
	    }
	    $out .= '<br><b>Notes</b> (optional):<br><textarea name=notes height=2 width=40></textarea>';
	    $htPlaces = $this->objDB->Places()->DropDown('place');
	    $out .= "\n<br><input type=submit name=btnMoveBins value=\"Move to:\">$htPlaces";
	    $out .= '</form>';
	}
	if ($wgRequest->getBool('btnMoveBins')) {
	    $arBins = $wgRequest->getIntArray('bin');
	    $idPlace = $wgRequest->getIntOrNull('place');
	    $txtNotes = $wgRequest->getText('notes');
	    $objPlace = $this->objDB->Places()->GetItem($idPlace);
	    $htPlace = $objPlace->AdminLink($objPlace->Name);

	    // create overall event:
	    $txtBins = '';
	    $htBins = '';
	    foreach ($arBins as $idBin => $zero) {
		$objBin = $this->Table->GetItem($idBin);
		$txtBins .= ' '.$objBin->Code;
		$htBins .= ' '.$objBin->AdminLink($objBin->Code);
		$arBins[$idBin] = $objBin->RowCopy();	// so we don't have to look them up again
	    }
	    $sqlDescr = 'Moving bins to [#'.$idPlace.'='.$objPlace->Name.']:'.$txtBins;
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
	return $out;
    }
    /*-----
      DEPRECATED - use StartEvent() / FinishEvent()
    */
    public function LogEvent($iSrce,$iDest,$iDescr=NULL) {
	return $this->objDB->BinLog()->LogEvent($this->ID,$iSrce,$iDest,$iDescr);
    }
    /*-----
      ACTION: Log an event where the bin stays in the same place
    */
    public function LogEvent_SamePlace($iDescr) {
	$this->LogEvent($this->ID_Place,$this->ID_Place,$iDescr);
    }
/*
    public function SelfLink($iText) {
	return SelfLink_Page('bin','id',$this->ID,$iText);
    }
*/
    // USER INTERFACE CONTROLS
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
    public function EventTable() {
	$objTbl = $this->objDB->BinLog();
	$objRows = $objTbl->GetData('ID_Bin='.$this->KeyValue());
	$objRows->Listing();
    }
}

class VbzStockBinLog extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('stk_bin_history');
	  $this->KeyName('ID');
	  $this->ClassSng('VbzStockBinEvent');
    }
    public function LogEvent($iBin,$iSrce,$iDest,$iDescr=NULL) {
	global $vgUserName;

	$arData = array(
	    'ID_Bin'	=> $iBin,
	    'WhenDone'	=> 'NOW()',
	    'WhoAdmin'	=> SQLValue($vgUserName),
	    'WhoNetwork'=> SQLValue($_SERVER['REMOTE_ADDR']),
	    'ID_Srce'	=> $iSrce,
	    'ID_Dest'	=> $iDest,
	    'Descr'	=> SQLValue($iDescr)
	    );
	return $this->Insert($arData);
    }
}

class VbzStockBinEvent extends clsDataSet {
    public function Listing() {
	global $wgOut;
	global $vgOut;

	if ($this->hasRows()) {
	    $objPlaces = $this->objDB->Places();

	    $out = $vgOut->TableOpen();
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	    $out .= $vgOut->TblCell('ID');
	    $out .= $vgOut->TblCell('When');
	    $out .= $vgOut->TblCell('From');
	    $out .= $vgOut->TblCell('To');
	    $out .= $vgOut->TblCell('What');
	    $out .= $vgOut->TblRowShut();
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;
		$id = $row['ID'];
		$ftWhen = $row['WhenDone'];
		$idSrce = $row['ID_Srce'];
		$idDest = $row['ID_Dest'];
		$objSrce = $objPlaces->GetItem($idSrce);
		if (is_null($objSrce)) {
		    $strSrce = $vgOut->Italic('??').$idSrce;
		} else {
		    //$strSrce = $objSrce->AdminLink($objSrce->Value('Name'));
		    $strSrce = $objSrce->AdminLink_name();

		}
		if ($idSrce == $idDest) {
		    $ftToFrom = $vgOut->TblCell($strSrce,'colspan=2 align=center');
		} else {
		    $objDest = $objPlaces->GetItem($idDest);
		    if (is_null($objDest)) {
			$strDest = $vgOut->Italic('??').$idDest;
		    } else {
			//$strDest = $objDest->AdminLink($objDest->Value('Name'));
			$strDest = $objDest->AdminLink_name();
		    }
		    $ftToFrom = $vgOut->TblCell($strSrce).$vgOut->TblCell($strDest);
		}

		$ftWhat = $row['Descr'];
		if (isset($row['Notes'])) {
		    $ftWhat .= ' '.$vgOut->Italic($row['Notes']);
		}

		$out .= $vgOut->TblRowOpen('style="'.$ftStyle.'"');
		$out .= $vgOut->TblCell($id);
		$out .= $vgOut->TblCell($ftWhen);
		$out .= $vgOut->TblCell($ftToFrom);
		$out .= $vgOut->TblCell($ftWhat);
	    }
	    $out .= $vgOut->TableShut();
	} else {
	    //$out = 'No events found';
	    $out = NULL;
	}
	$vgOut->AddText($out);	$out = '';
    }
}
class VbzAdminStkItems extends clsStkItems {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminStkItem');
	  $this->ActionKey('stk-item');
    }
    // EVENT LOGGING
    public function StockLog() {
	return $this->objDB->StkLog();
    }
    // ----
    /*----
      RETURNS: recordset of stock items for the given Bin
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function Data_forBin($iBin) {
	$rs = $this->GetData('ID_Bin='.$iBin);
	return $rs;
    }
    /*----
      PURPOSE: retrieve a dataset of stock for the given title
      HISTORY:
	2012-02-03 created
    */
    public function Data_forTitle($iTitle) {
	$sql = 'SELECT * FROM qryStk_lines_Title_info WHERE ID_Title='.$iTitle;
	return $this->DataSQL($sql);
    }
    /*----
      ACTION: Add a line item from an inventory count
	We don't know where it came from; probably the result of a bug or miscount somewhere along the line.
      USAGE: ONLY for adding an item discovered during an inventory count. Don't use for creating records for any other purpose.
      NOTE: Does not create event record; caller should do that. This is mainly for logistical reasons -- there may be multiple
	lines for a given item, and I haven't bothered to try matching them up; the algorithm just finds one of them, updates its
	quantity, and zeroes the rest. Ideally, it would distribute quantities found to match existing quantities to the
	greatest possible extent, and create a new record for any overflow. This would make it easier to track every time a given
	item was counted, It might be more sensible to just look at when the item's current box was last inventoried, because
	presumably the item is definitely there when it is put into the box -- but we don't know if other quantities of the
	same Item are correct. In the long term, we probably either need to track individual pieces, or else prohibit separate
	line numbers for multiple pieces of the same Item.
      FUTURE: Rename from InventoryAdd to Add_fromInventory
    */
    public function InventoryAdd($iBin,$iItem,$iQty) {
	$ar = $this->Datarray_Add_base($iBin,$iItem,$iQty,'NOW()');
	return $this->Insert($ar);
    }
    protected function Datarray_Add_base($iBin,$iItem,$iQty,$iWhenCounted=NULL) {
	$arIns = array(
	  'ID_Bin'	=> $iBin,
	  'ID_Item'	=> $iItem,
	  'Qty'		=> $iQty,
	  'WhenAdded'	=> 'NOW()',
	  'WhenCounted'	=> SQLValue($iWhenCounted),
	  );
	return $arIns;
    }
    /*----
      ACTION: Add a line from a package back into stock
	* removes the quantity from the package
	* creates a new stock line for that quantity
      ASSUMES: we want to add ALL items from the row.
      HISTORY:
	2011-10-08 written
    */
    public function Add_fromPkgLine(clsPkgLine $iSrce,$iBin) {
	$idItem = $iSrce->Value('ID_Item');
	$objItem = $iSrce->ItemObj();
	$objPkg = $iSrce->PkgObj();
	$qty = $iSrce->ItemQty();

	// - log start of event
	$objBin = $this->objDB->Bins($iBin);
	$txtEv = 'Moving qty '.$qty
	  .' of item '.$objItem->CatNum()
	  .' from package '.$objPkg->AdminLink_name()
	  .' to bin '.$objBin->AdminLink_name();
	$out = $txtEv;
	$arEv = array(
	  'descr'	=> $txtEv,
	  'code'	=> 'MV-'.clsStkLog::chTypeRstk,
	  'where'	=> __METHOD__
	  );
	$objBin->StartEvent($arEv);

	$iSrce->DoVoid('Moving to bin ID='.$iBin.' ('.$objBin->Name().')');
	$ar = $this->Datarray_Add_base($iBin,$idItem,$qty);

	$this->Insert($ar);

	$objBin->FinishEvent();
	return $out;
    }
    /*----
      ACTION: Add a line from a received restock
      NOTE: Does create event record
      INPUT:
	iBin: ID of destination bin
	iItem: ID of item
	iQty: quantity being moved
	iLine: ID of received restock line
    */
    public function Add_fromRestock($iBin,$iQty,clsRstkRcdLine $iLine) {
	$iItem = $iLine->Value('ID_Item');
	$iRstk = $iLine->Value('ID_RstkRcd');

	assert('!empty($iQty);');
	assert('!is_null($iItem);');

	// LOG event start

	// - core event log:
	$objRstk = $this->objDB->RstkRcds($iRstk);
	$objBin = $this->objDB->Bins($iBin);
	$txtEv = '<br>Moving qty '.$iQty.' from restock '.$objRstk->AdminLink_name().' to bin '.$objBin->AdminLink_name();
	$out = $txtEv;
	$arEv = array(
	  'descr'	=> $txtEv,
	  'code'	=> 'MV-'.clsStkLog::chTypeRstk,
	  'where'	=> __METHOD__
	  );
	$idEvCore = $objBin->StartEvent($arEv);

	// CHANGE the data

	// - remove quantity from restock:
	$ok = $iLine->DoFile_Qty($iQty);

	if ($ok) {
	    // - insert row in stock:
	    $arIns = array(
	      'ID_Bin'	=> $iBin,
	      'ID_Item'	=> $iItem,
	      'Qty'		=> $iQty,
	      'WhenAdded'	=> 'NOW()',
	      'WhenCounted'	=> 'NOW()'
	      );
	    $this->Insert($arIns);
	    $idNew = $this->objDB->NewID();
	}

/* NOTE: (2010-12-03) The stock event log just isn't set up to handle logging for
  a stock line that hasn't been created yet...
  so, for now, we'll create the damn thing and *then* do LogEvent_Start().
Later on, when stock logging is rewritten to be properly integrated with universal logging,
  this will be handled more gracefully.
Creating the stock line requires removing the item from the restock first, because it's much worse
  to end up with phantom items in stock than to have stuff we don't realize we have.
So that's why LogEvent_Start() is called after the event has actually happened.
*/
	if ($ok) {
	    // - stock event log:
	    $objLog = $this->StockLog();
	    $idEvent = $objLog->LogEvent_Start(
	      $idNew,				// stock line (not known yet)
	      $iBin,			// stock bin
	      clsStkLog::chTypeRstk,	// other type = restock
	      $iRstk,			// other container
	      $iLine->ID,		// other line
	      $txtEv,			// event description
	      $iQty,			// quantity being moved
	      $idEvCore);		// unified logging event ID
	    assert('!empty($idEvent);');

	    $arEv = array(
	      'descrfin'	=> 'OK'
	      );
	    $out .= ' - OK';
	} else {  
	    $txtErr = $this->objDB->getError();
	    $objDB->ClearError();
	    $arEv = array(
	      'descrfin'	=> 'Error: '.$txtErr,
	      'error'		=> TRUE
	      );
	    $out .= '<br> - Error: '.$txtErr;
	}

	// LOG event completion:
	if ($ok) {
	    $arStkEv = array(
	      'ID_StkLine'	=> $idNew
	      );
	} else {
	    $arStkEv = NULL;
	}
	$objLog->LogEvent_Finish($idEvent,$arStkEv);
	$objBin->FinishEvent($arEv);
	return $out;
    }
    /*----
      HISTORY:
	2012-03-11 created for title stock summary
    */
    public function Data_forItem($iID,$iSort=NULL) {
	$rs = $this->GetData('ID_Item='.$iID,NULL,$iSort);
	return $rs;
    }
    /*----
      HISTORY:
	2011-01-30 Replaced references to "Descr" field with "Notes" -- I can only think that this
	  is what was originally intended.
    */
    public function Listing_forItem($iObj) {
	global $wgOut;
	global $vgPage;
	global $sql;

	$obj = $iObj;
	$id = $obj->ID;

	$vgPage->UseWiki();

//	$objRecs = $this->GetData('ID_Item='.$id,NULL,'WhenRemoved,WhenAdded,WhenChanged');
	$objRecs = $this->Data_forItem($id,'WhenRemoved,WhenAdded,WhenChanged');
	if ($objRecs->HasRows()) {
	    $out = "{| class=sortable\n|-\n! ID || Bin || Qty || Added || Changed || Counted || Removed";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$id = $objRecs->KeyValue();

		$objBin = $objRecs->Bin();
		//$htBin = $objBin->AdminLink($objBin->Code);
		$htBin = $objBin->AdminLink_name();

//		$wtID = SelfLink_Page('stk-line','id',$id,$id);
		$ftID = $objRecs->AdminLink();
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$intQty = $objRecs->Value('Qty');

		$txtNotes = $objRecs->Value('Notes');
		$isActive = is_null($objRecs->Value('WhenRemoved')) && ($intQty > 0) && $objBin->IsActive();
		$isValid = $objBin->PlaceObj()->IsActive();
		if (!$isActive) {
		    $wtStyle .= ' color: #888888;';
		}
		if ($isValid) {
		    $wtStyleCell = '';
		} else {
		    $wtStyleCell = 'style="text-decoration: line-through;"';
		}
		$out .= "\n|- style=\"$wtStyle\"".
		    "\n| $wtStyleCell | ".$ftID.
		    ' || '.$wtStyleCell.' | '.$htBin.
		    ' || '.$intQty.
		    ' || '.DataDate($objRecs->Value('WhenAdded')).
		    ' || '.DataDate($objRecs->Value('WhenChanged')).
		    ' || '.DataDate($objRecs->Value('WhenCounted')).
		    ' || '.DataDate($objRecs->Value('WhenRemoved'));
		if (!is_null($txtNotes)) {
		    $out .= "\n|- style=\"$wtStyle\"\n| colspan=4 | ".$txtNotes;
		}

		$isOdd = !$isOdd;
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'There has never been any stock for this item.<br><small><b>SQL</b>: '.$sql.'</small>';
	}
	return $out;
    }
    /*-----
      RETURNS: List of items needed to fill stock minimums
    */
    public function Needed() {
	$sql = 'SELECT * FROM qryItems_needed_forStock';
	$objRecs = $this->objDB->DataSet($sql);
	if ($objRecs->HasRows()) {
	    while ($objRecs->NextRow()) {
		$id = $objRecs->ID;
		$arOut[$id]['min'] = $objRecs->QtyMin_Stk;
		$arOut[$id]['got'] = $objRecs->QtyForSale;
		$arOut[$id]['need'] = $arOut[$id]['min'] - $arOut[$id]['got'];
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }
}
/*####
  HISTORY:
    2011-03-29 changed parent from clsAdminData to clsDataSet
      clsAdminData apparently does not work with form-building routines,
	and I don't *think* it's needed anymore.
*/
class VbzAdminStkItem extends clsDataSet {
    /*====
      BOILERPLATE: event logging
      HISTORY:
	2011-02-18 replaces earlier (incomplete) boilerplate logging
	2011-03-29 copied from clsPackage to VbzAdminStkItems
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
    /*====
      BOILERPLATE: self-linking
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    // ==== /BOILERPLATE

    // DEPRECATED -- use BinObj()
    public function Bin() {
	return $this->BinObj();
    }
    public function BinObj() {
	$objBins = $this->objDB->Bins();
	return $objBins->GetItem($this->Value('ID_Bin'));
    }
    // DEPRECATED -- use ItemObj()
    public function Item() {
	return $this->ItemObj();
    }
    public function ItemObj() {
	return $this->objDB->Items()->GetItem($this->Value('ID_Item'));
    }
    /*----
      RETURNS: quantity actually in stock
	If WhenRemoved is not NULL, then qty is zero.
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function Qty_inStock() {
	if (!is_null($this->Value('WhenRemoved'))) {
	    return 0;
	} else {
	    return $this->Value('Qty');
	}
    }
    /*----
      ACTION: count all (active, valid) stock in the current recordset
      INPUT: recordset
      RETURNS: array of Item quantities
	array[item id] = quantity in stock
      HISTORY:
	2011-03-28 created for Place inventory
    */
    public function CountStock() {
	$arOut = NULL;
	while ($this->NextRow()) {
	    $qty = $this->Qty_inStock();
	    if ($qty > 0) {
		$idItem = $this->Value('ID_Item');
		$qtySum = nzArray($arOut,$idItem);
		$arOut[$idItem] = $qtySum + $qty;
	    }
	}
	return $arOut;
    }
    /*-----
      ACTION: Move this stock item line to the given bin and log the change
    */
    public function MoveToBin($iBin,$iDescr=NULL) {
	$objLog = $this->objDB->StkLog();
	$id = $this->Value('ID');
	$idEvent = $objLog->LogEvent_Start(
	  $id,		// stock line
	  $this->Value('ID_Bin'),	// stock bin
	  clsStkLog::chTypeBin,	// other type
	  $iBin,		// other container
	  $id,		// other line -- reusing same line, just changing bins
	  $iDescr,$this->Value('Qty'));
	$arUpd = array(
	  'ID_Bin'	=> $iBin,
	  'WhenChanged'	=> 'NOW()');
	$this->Update($arUpd);
	$objLog->LogEvent_Finish($idEvent);
    }
    /*----
      USED BY: inventory counting process
      HISTORY:
	2011-03-03
	  Changed overloaded self-references to Value()
	  Added iDescr and iQty to arg list -- caller will probably need modifying next
	2011-03-19 Was duplicate of $iQty ($iQty,$iDescr,$iQty) - not sure what happened. Removed 2nd one.
    */
    public function UpdateQty($iQty,$iDescr) {
	$objLog = $this->objDB->StkLog();
	// log the attempt
	$idThis = $this->Value('ID');
	$idBin = $this->Value('ID_Bin');
	$idEvent = $objLog->LogEvent_Start(
	  $idThis,	// stock line
	  $idBin,	// stock bin
	  clsStkLog::chTypeBin,	// other type
	  $idBin,	// other container
	  $idThis,	// other line
	  $iDescr,$iQty);

	// make the change
	$arUpd = array(
	  'Qty'	=> $iQty,
	  'WhenCounted' => 'NOW()');
	if ($iQty != $this->Value('Qty')) {
	    $arUpd['WhenChanged'] = 'NOW()';
	}
	if ($iQty == 0) {
	    $arUpd['WhenRemoved'] = 'NOW()';
	}
	$this->Update($arUpd);

	// log the completion
	$objLog->LogEvent_Finish($idEvent);
    }
    /*-----
      ACTION: Move this stock item line to the given package line and log the change
      INPUT:
	iPkg: package into which this stock line is being moved
	iLine: package line into which stock line is being moved
	iDescr: reason for move (description of activity)
	iQty: requested quantity
      RETURNS: quantity actually moved
      RULES:
	Caller handles creation of package line and putting quantity in it.
	This function handles stock record adjustment:
	  iQty =< $this->Qty: $this->Qty -= iQty, return iQty
	  iQty > $this->Qty: $this->Qty = 0, return old $this->Qty
    */
    public function MoveToPkg($iPkg,$iPLine,$iDescr=NULL,$iQty) {
	$objLog = $this->objDB->StkLog();
	$idEvent = $objLog->LogEvent_Start(
	  $this->KeyValue(),		// stock line
	  $this->Value('ID_Bin'),	// bin
	  clsStkLog::chTypePkg,	// other type
	  $iPkg,		// other container
	  $iPLine,		// other line
	  $iDescr,$this->Value('Qty'));

	// do quantity calculations
	$qtyOld = $this->Value('Qty');
	if ($iQty >= $qtyOld) {
	    $qtyNew = 0;
	    $qtyRtn = $qtyOld;
	    $sqlWhenRemoved = 'NOW()';	// the last units have been removed
	} else {
	    $qtyNew = $qtyOld - $iQty;
	    $qtyRtn = $iQty;
	    $sqlWhenRemoved = 'NULL';	// still some quantity left
	}
	$arUpd = array(
	  'Qty'		=> $qtyNew,
	  'WhenChanged'	=> 'NOW()',
	  'WhenRemoved'	=> $sqlWhenRemoved
	  );
	$this->Update($arUpd);
	$objLog->LogEvent_Finish($idEvent);
	return $qtyRtn;
    }
    /*----
      HISTORY:
	2011-03-29 adapted from clsPackage to VbzAdminStkItem
    */
    private function BuildEditForm() {
	global $vgOut;
	// create fields & controls

	if (empty($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldNum('Qty'),		new clsCtrlHTML(array('size'=>2)));
	    $objForm->AddField(new clsFieldTime('WhenAdded'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenChanged'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenCounted'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenRemoved'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('Cost'),		new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsField('CatNum'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>40)));

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the package
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
	2011-03-29 copied from clsPackage to VbzAdminStkItem
    */
    private function AdminSave($iNotes) {
	global $vgOut;

	$out = $this->objForm->Save($iNotes);
	$vgOut->AddText($out);
    }
    /*----
      HISTORY:
	2011-03-29
	  fixing bugs due to class API change
	  adding edit capability
	  removed check for exactly one record; seems to never be a problem
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$vgPage->UseHTML();

	if ($wgRequest->getBool('btnSave')) {
	    $this->BuildEditForm();
	    $this->AdminSave($wgRequest->GetText('EvNotes'));		// save edit to existing package
	}
	$doEdit = $vgPage->Arg('edit');;

	$objPage = new clsWikiFormatter($vgPage);

	$id = $this->KeyValue();
	$strName = "Stock Item ID #$id";

	$objSection = new clsWikiSection($objPage,$strName);
	$objSection->ToggleAdd('edit','edit the stock item record');
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';

	$objItem = $this->Item();
	$wtItem = $objItem->FullDescr();

	$objBin = $this->Bin();
	//$wtBin = $objBin->AdminLink($objBin->NameLong());
	$wtBin = $objBin->AdminLink_name();

	$intQty		= $this->Value('Qty');
	$dtWhenAdded	= $this->Value('WhenAdded');
	$dtWhenChged	= $this->Value('WhenChanged');
	$dtWhenCnted	= $this->Value('WhenCounted');
	$dtWhenRmved	= $this->Value('WhenRemoved');
	$prcCost	= $this->Value('Cost');
	$txtCatNum	= $this->Value('CatNum');
	$txtNotes	= $this->Value('Notes');

	if ($doEdit) {
	    $this->BuildEditForm();
	    $objForm = $this->objForm;

	    $out .= $objSection->FormOpen();

	    $ctQty	=  $objForm->Render('Qty');
	    $ctWhenAdded = $objForm->Render('WhenAdded');
	    $ctWhenChged = $objForm->Render('WhenChanged');
	    $ctWhenCnted = $objForm->Render('WhenCounted');
	    $ctWhenRmved = $objForm->Render('WhenRemoved');
	    $ctCost	= $objForm->Render('Cost');
	    $ctCatNum	= $objForm->Render('CatNum');
	    $ctNotes	= $objForm->Render('Notes');
	} else {
	    $ctQty = $intQty;
	    $ctWhenAdded = $dtWhenAdded;
	    $ctWhenChged = $dtWhenChged;
	    $ctWhenCnted = $dtWhenCnted;
	    $ctWhenRmved = $dtWhenRmved;
	    $ctCost = $prcCost;
	    $ctCatNum = $txtCatNum;
	    $ctNotes = htmlspecialchars($txtNotes);
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>Item</b>:</td><td>$wtItem</td></tr>";
	$out .= "\n<tr><td align=right><b>Bin</b>:</td><td>$wtBin</td></tr>";
	$out .= "\n<tr><td align=right><b>Qty</b>:</td><td>$ctQty</td></tr>";
	$out .= "\n<tr><td align=right><b>Time stamps</b>:</td></tr>";
	$out .= "\n<tr><td align=center colspan=2>";
	  $out .= "\n<table>";
	  $out .= "\n<tr><td align=right><b>Added</b>:</td><td>$ctWhenAdded</td></tr>";
	  $out .= "\n<tr><td align=right><b>Changed</b>:</td><td>$ctWhenChged</td></tr>";
	  $out .= "\n<tr><td align=right><b>Counted</b>:</td><td>$ctWhenCnted</td></tr>";
	  $out .= "\n<tr><td align=right><b>Removed</b>:</td><td>$ctWhenRmved</td></tr>";
	  $out .= "\n</table>";
	$out .= "\n</td></tr>";
	$out .= "\n<tr><td align=right><b>Cost</b>:</td><td>$ctCost</td></tr>";
	$out .= "\n<tr><td align=right><b>Cat #</b>:</td><td>$ctCatNum</td></tr>";
	$out .= "\n<tr><td align=right><b>Notes</b>:</td><td>$ctNotes</td></tr>";
	$out .= "\n</table>";

	if ($doEdit) {
	    // This does not appear to be saving in the event log. It may be that forms
	    // are not case-sensitive, in which case the record's notes may be overwriting it or something.
	    $out .= 'Notes for event log:<textarea name=EvNotes rows=3></textarea>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out);

	$wgOut->AddWikiText('===Stock History===');
	$vgPage->UseWiki();
	$wgOut->AddWikiText($this->DoHistory(),TRUE);
	$wgOut->AddWikiText('===General Events===');
	$wgOut->AddWikiText($this->EventListing());
    }
    /*----
      PURPOSE: display an admin listing of stock items in the current recordset
      HISTORY:
	2012-02-03 created for Title's display of stock
    */
    public function AdminList(array $iOpts) {
	$strNone = NzArray($iOpts,'none','No stock found.');

	$out = NULL;
	if ($this->HasRows()) {
	    $out .= '<table><th>Stk ID</th><th>Item</th><th>Qty</th><th>Bin</th><th>Added</th><th>Changed</th><th>Counted</th><th>Removed</th><th>Cost</th></tr>';
	    while ($this->NextRow()) {
		$htID = $this->AdminLink();
		$htItem = $this->ItemObj()->AdminLink_name();
		$htQty = $this->Value('Qty');
		$htBin = $this->BinObj()->AdminLink_name();
		$htWhenAdd = $this->Value('WhenAdded');
		$htWhenChg = $this->Value('WhenChanged');
		$htWhenCnt = $this->Value('WhenCounted');
		$htWhenRmv = $this->Value('WhenRemoved');
		$htCost = $this->Value('Cost');

		$out .= "\n<tr>"
		  ."\n<td>$htID</td>"
		  ."\n<td>$htItem</td>"
		  ."\n<td>$htQty</td>"
		  ."\n<td>$htBin</td>"
		  ."\n<td>$htWhenAdd</td>"
		  ."\n<td>$htWhenChg</td>"
		  ."\n<td>$htWhenCnt</td>"
		  ."\n<td>$htWhenRmv</td>"
		  ."\n<td>$htCost</td>"
		  ."\n</tr>"
;
		if ($this->HasValue('Notes')) {
		    $htNotes = $this->Value('Notes');
		    $out .= '<tr><td></td><td colspan=8>'.$htNotes.'</td></tr>';
		}
	    }
	    $out .= '</table>';
	} else {
	    $out = $strNone;
	}
	return $out;
    }
    public function DoHistory() {
	$objTbl = $this->objDB->StkLog();
	$objRows = $objTbl->GetData('ID_StkLine='.$this->KeyValue());
	return $objRows->AdminList();
    }
}

class clsStkLog extends clsTable {
    const chTypeBin = 'L';
    const chTypePkg = 'P';
    const chTypeRstk = 'R';
    const chTypeMult = 'M';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(ksTbl_stock_hist_items);
	  $this->KeyName('ID');
	  $this->ClassSng('clsStockEvent');
    }
    public function LogEvent_Start($iStkLine,$iBin,$iOtherType,$iOtherCont,$iOtherLine,$iDescr=NULL,$iQty,$iEvent=NULL) {
	global $vgUserName;

	$idsOthCont = $iOtherType.'.'.$iOtherCont;
	$objStkLine = $this->objDB->StkItems()->GetItem($iStkLine);
	$idItem = $objStkLine->Value('ID_Item');
	assert('!empty($idItem); /* iStkLine='.$iStkLine.' */');
	$objBin = $this->objDB->Bins()->GetItem($iBin);
	$qtyInBin = $objBin->Qty_ofItem($idItem);

	$arData = array(
	  'ID_Event'	=> SQLValue($iEvent),
	  'ID_StkBin'	=> $iBin,
	  'ID_StkLine'	=> $iStkLine,
	  'CH_OthType'	=> SQLValue($iOtherType),
	  'ID_OthCont'	=> $iOtherCont,
	  'ID_OthLine'	=> $iOtherLine,
	  'IDS_OthCont'	=> SQLValue($idsOthCont),
	  'ID_Item'	=> $idItem,
	  'QtyAdded'	=> $iQty,
	  'QtyBefore'	=> SQLValue($objStkLine->Value('Qty')),	// might be NULL
	  'QtyBinBefore'=> $qtyInBin,
	  'WhenStarted'	=> 'NOW()',
	  'What'	=> SQLValue($iDescr),
	  'WhoAdmin'	=> SQLValue($vgUserName),
	  'WhoNetwork'	=> SQLValue($_SERVER['REMOTE_ADDR'])
	  );
	$this->Insert($arData);
	$idEvent = $this->objDB->NewID('clsStkLog.LogEvent_Start');
	assert('!empty($idEvent);');
	return $idEvent;
    }
    public function LogEvent_Finish($iEvent,array $iData=NULL) {
	$objEvent = $this->GetItem($iEvent);
	$idStkLine = $objEvent->Value('ID_StkLine');
	$objStkLine = $this->objDB->StkItems()->GetItem($idStkLine);
	$idItem = $objStkLine->Value('ID_Item');
	assert('!empty($idItem); /* ID_StkLine='.$idStkLine.' */');
	$idBin = $objStkLine->Value('ID_Bin');
	$objBin = $this->objDB->Bins()->GetItem($idBin);
	$qtyInBin = $objBin->Qty_ofItem($idItem);

	$arAdd = array(
	  'QtyAfter'		=> $objStkLine->Value('Qty'),
	  'QtyBinAfter'		=> $qtyInBin,
	  'WhenFinished'	=> 'NOW()'
	  );
	if (is_array($iData)) {
	    $arData = array_merge($iData,$arAdd);
	} else {
	    $arData = $arAdd;
	}
	$this->Update($arData,'ID='.$iEvent);
    }
}
class clsStockEvent extends clsDataSet {
    public function AdminList() {
	if ($this->HasRows()) {
	    $out = "{|\n|-\n! ID "
	      .'|| Stk ID '
	      .'|| Item '
	      .'|| Home Bin '
	      .'|| Other '
	      .'|| pre-qty '
	      .'|| delta '
	      .'|| post-qty '
	      .'|| started '
	      .'|| finished'
	      .'|| description';
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		$idStk = $this->Value('ID_StkLine');
		$objStk = $this->objDB->StkItems()->GetItem($idStk);
		$objBin = $objStk->Bin();
		$objItem = $objStk->Item();

		//$wtBin = $objBin->SelfLink($objBin->Value('Code'));
		$wtBin = $objBin->AdminLink_name();
		$wtItem = $objItem->Value('CatNum');

		$wtQtyPre = $this->Value('QtyBefore');
		if ($this->HasValue('QtyBinBefore')) {
		    $wtQtyPre .= '(b:'.$this->Value('QtyBinBefore').')';
		}
		$wtQtyPost = $this->Value('QtyAfter');
		if ($this->HasValue('QtyBinAfter')) {
		    $wtQtyPost .= '(b:'.$this->Value('QtyBinAfter').')';
		}
		$qtyAdd = $this->Value('QtyAdded');
		if ($qtyAdd > 0) {
		    $wtQtyAdded = '+'.$qtyAdd;
		} elseif ($qtyAdd < 0) {
		    $wtQtyAdded = $qtyAdd;
		} else {
		    $wtQtyAdded = '-';
		}

		$ftWhenStart = $this->Value('WhenStarted');
		$ftWhenFinish = $this->Value('WhenFinished');
		$ftWhat = $this->Value('What');
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$out .= "\n|- style=\"$wtStyle\"".
		  "\n| $id".
		  "\n| $idStk".
		  "\n| $wtItem".
		  "\n| $wtBin".
		  "\n| ''to do''".
		  "\n| $wtQtyPre".
		  "\n| $wtQtyAdded".
		  "\n| $wtQtyPost".
		  "\n| $ftWhenStart".
		  "\n| $ftWhenFinish".
		  "\n| $ftWhat";
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'none';
	}
	return $out;
    }
}