<?php
/*
  LIBRARY: place.php - classes for managing stock places
  HISTORY:
    2010-11-03 extracted classes from SpecialVbzAdmin.php:
      VbzStockPlace(s), VbzStockBin(s), VbzStockBinLog, VbzStockBinEvent, VbzAdminStkItem(s), clsStkLog, clsStockEvent
    2013-11-30 adapting from admin.stock.php (MW admin version)
    2014-03-22 renamed stock.php -> place.php; split Bin classes off into bin.php
*/
/* *****
 STOCK MANAGEMENT
*/

/*%%%%
  TODO: Add cacheing. This was originally descended from a cached-table class which is now deprecated.
*/
class VCM_StockPlaces extends clsVbzTable {

    // ++ SETUP ++ //

    protected $idEvent;
    private $arArgs;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('stk_places');
	  $this->KeyName('ID');
	  $this->ClassSng('VCM_StockPlace');
	  $this->ActionKey(KS_ACTION_STOCK_PLACE);
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
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminListing();
	return $out;
    }
    protected function Arg($sName) {
	if (is_array($this->arArgs) && array_key_exists($sName,$this->arArgs)) {
	    return $this->arArgs[$sName];
	} else {
	    return NULL;
	}
    }

    // -- DROP-IN API -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function GetData_active() {
	$sqlFilt = 'isActive';	// isEnabled is apparently not being set reliably
	return $this->GetData($sqlFilt,NULL,'Name');	// sort by Name
    }
    public function GetData_all() {
	return $this->GetData(NULL,NULL,'Name');	// sort by Name
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ WEB UI COMPONENTS ++ //

    //-----
    public function DropDown($iName,$iDefault=NULL,$iFilt=NULL,$iSort='Name') {
	$rsRows = $this->GetData($iFilt,NULL,$iSort);
	return $rsRows->DropDown($iName,$iDefault);
    }

    // -- WEB UI COMPONENTS -- //
    // ++ ADMIN WEB UI ++ //

    /*-----
      ACTION: Show list of all Places within the given parent Place
    */
    public function AdminListing($iParent=0) {
	if ($iParent == 0) {
	    $htHdrLv = 2;
	} else {
	    $htHdrLv = 3;
	}
	$oPage = $this->Engine()->App()->Page();

	$sDo = $oPage->PathArg('do');
	$doAdd = ($sDo == 'new');

	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arMenu = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option(array(),'new','do',NULL,'','create a new Place'),
	  );
	$out = '';
	$oPage->PageHeaderWidgets($arMenu);

	// check for changes via form submission
	if (array_key_exists('btnAddPlace',$_REQUEST)) {
	    $this->AdminListing_handleAdd();
	}

	// we have to get all the data, to get all sub-places recursively:
	$objRow = $this->GetData(NULL,NULL,'Name');

	$gotSome = FALSE;

	if ($objRow->hasRows() || $doAdd) {
	    while ($objRow->NextRow()) {
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
		    $out .= '<form method=post>';
		}
		$out .= "\n<table><tr><th>ID</th><th>name</th><th>description</tr>";

		// render the listing
		$out .= $this->AdminListing_renderSubs($arData,$arTree,$iParent);

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
	}
/*	//$arLink = $oPage->SelfArray();
	$arLink['add'] = 'place';
	$url = $oPage->SelfURL($arLink);
	$htLink = clsHTML::BuildLink($url,'add a new one');
	$out .= " [$htLink]";
	*/
	return $out;
    }
    private function AdminListing_handleAdd() {
	$strName = $_REQUEST['plName'];
	$strDesc = $_REQUEST['plDesc'];
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
    private function AdminListing_renderSubs($iData, $iTree, $iBranch=0, $iInd=0) {
	$ind = $iInd+1;
	$out = '';

	if (isset($iTree[$iBranch])) {
	    foreach($iTree[$iBranch] AS $key=>$ID) {
		$objRow = $iData[$ID];
		$htPlace = $objRow->AdminLink_name();
		$sInd = str_repeat('&ndash;&nbsp;',$iInd);
		$sDesc = $objRow->Value('Descr');
		$out .= <<<__END__
<tr>
  <td>$ID</td>
  <td><font style="color: grey;">$sInd</font>$htPlace</td>
  <td>$sDesc</td>
</tr>
__END__;
		$out .= $this->AdminListing_renderSubs($iData,$iTree,$ID,$ind);
	    }
	}
	return $out;
    }

    // -- ADMIN WEB UI -- //
}
class VCM_StockPlace extends clsVbzRecs {
    protected $objParent;

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ DATA FIELD ACCESS ++ //

    public function ParentID() {
	return $this->Value('ID_Parent');
    }
    public function Name() {
	return $this->Value('Name');
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
		if ($this->ID_Parent == $iObj->KeyValue()) {
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
    public function AdminLink_name() {
	$ftLink = $this->AdminLink($this->Name());
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

    // -- DATA FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //
    
    function BinsClass() {
	return KS_CLASS_STOCK_BINS;
    }
    
    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    public function BinsTable() {
	return $this->Engine()->Make($this->BinsClass());
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      HISTORY:
	2010-11-30 Added object caching
    */
    public function ParentRecord() {
	if ($this->HasParent()) {
	    $obj = $this->objParent;
	    if (!is_object($obj)) {
		//$objPlaces = new VbzStockPlaces($this->objDB);
		$tPlaces = $this->Table;
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
	$sqlFilt = 'ID_Place='.$this->KeyValue();
	$tbl = $this->Engine()->Bins();
	$rs = $tbl->GetData($sqlFilt);
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
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

    // -- CALCULATIONS -- //
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
    // ++ ADMIN WEB UI CONTROLS ++ //

    /*-----
      ACTION: Show a dropdown list consisting of the rows in the current dataset
      USED BY: Moving a list of Bins in a Place
    */
    public function DropDown($sName,$vDefault=NULL) {
	$cfPlace = new clsFieldNum($sName,$vDefault);
	$arRows = NULL;
	if ($this->HasRows()) {
	    while ($this->NextRow()) {
		$key = $this->KeyValue();
		$val = $this->NameLong_text();
		$arRows[$key] = $val;
	    }
	}
	$ctDrop = new clsCtrlHTML_DropDown(NULL,$arRows);
	$ctDrop->Text_NoRows('No locations matching filter');
	$ctDrop->Field($cfPlace);
	$out = $ctDrop->Render();
/*
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
	    $rcRows = $this->Table()->GetData(NULL,NULL,'Name');	// sort by Name
	} else {
	    $rcRows = $this->Table()->GetData_active();
	}
	return $rcRows->DropDown($iName,$this->KeyValue());
    }
    /*----
      ACTION: Render a dropdown list of all rows, except:
	* do not include the current row
	* do not include any descendants of this row
	Also, use ID_Parent as the default.
    */
    public function DropDown_meParent($iName) {
	$objRows = $this->Table()->GetData('ID != '.$this->KeyValue(),NULL,'Name');
	while ($objRows->NextRow()) {
	    if (!$objRows->HasParent($this)) {
		$arRows[$objRows->KeyValue()] = $objRows->NameLong_text();
	    }
	}
	return DropDown_arr($iName,$arRows,$this->Row['ID_Parent'],'--ROOT--');
    }
    /*-----
      ACTION: Show table of all Places inside this Place (via Places->AdminListing())
      TODO: Rename to something that suggests rendered output rather than an array.
    */
    public function SubsList() {
	$tPlaces = $this->Table;
	$out = $tPlaces->AdminListing($this->KeyValue(),'Sub-Locations');
	return $out;
    }
    /*-----
      ACTION: Show table of all Bins inside this Place (via Bins->Listing())
      TODO: Rename to something that suggests rendered output rather than an array.
    */
    public function BinsList() {
	$tBins = $this->BinsTable();
	$out = $tBins->List_forPlace($this->KeyValue());
	return $out;
    }

    // -- ADMIN WEB UI CONTROLS -- //
    // ++ ADMIN WEB UI PAGE ++ //

    /*-----
      ACTION: Display information about the current Place
    */
    public function AdminPage() {
	$strName = $this->Value('Name');
	$strTitle = 'Stock Location '.$this->KeyValue().': '.$strName;

	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),'edit'),
	  new clsActionLink_option(array(),'update'),
	  new clsActionLink_option(array(),'inv',NULL,'inventory',NULL,'list all inventory of location '.$strName)
	  );

	$oPage = $this->Engine()->App()->Page();

	$out = $oPage->ActionHeader($strTitle,$arActs);

	$doEdit = $oPage->PathArg('edit');
	$doUpd = $oPage->PathArg('update');
	$doInv = $oPage->PathArg('inv');
	$doSave = $oPage->ReqArgBool('btnSave');

	if ($doEdit || $doSave) {
	    $oForm = $this->EditForm();
	    if ($doSave) {
		$out .= $oForm->Save();
	    }
	}

	if ($doUpd) {
	    // update any calculated fields
	    $arUpd = array(
	      'isEnabled' => ($this->ParentRecord()->IsActive())?'TRUE':'FALSE'
	      );
	    $this->Update($arUpd);
	    $this->AdminRedirect();
	}

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
		$objParent = $this->ParentRecord();
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
	    $doEnabled = $this->ParentRecord()->IsActive();	// enabled flag *should be* set?
	    $ctrlActv .= ' <b>Enabled</b>: '.NoYes($isEnabled);
	    if ($isEnabled != $doEnabled) {
		//$arLink = $vgPage->Args(array('page','id'));
		//$arLink['update'] = TRUE;
		$urlUpd = $oPage->SelfURL(array('update'=>TRUE),TRUE);

		$ctrlActv .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.NoYes($doEnabled);
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
	}

	$out .= $oPage->ActionHeader('Sub-locations');
	$out .= $this->SubsList();
	// Bins admin does its own header
	$out .= $this->BinsList();
	$out .= $oPage->ActionHeader('Events');
	$out .= $this->EventListing();

	return $out;
    }
    /*----
      ACTION: build admin edit form controls
    */
    protected function EditForm() {
	// create fields & controls

	if (is_null($this->oForm)) {
	    $oForm = new clsForm_Recs($this);
	    $oForm->AddField(new clsFieldNum('ID_Parent'),	new clsCtrlHTML());
	    $oForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());
	    $oForm->AddField(new clsFieldBool_Int('isEnabled'),	new clsCtrlHTML());	// this will not actually be rendered
	    $oForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    $oForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>50)));
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }

    // -- ADMIN WEB UI -- //

}