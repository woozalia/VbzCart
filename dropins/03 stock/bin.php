<?php
/*
  LIBRARY: place.php - classes for managing stock places
  HISTORY:
    2014-03-22 extracted Bin classes from place.php
*/
class vctAdminStockBins extends vctStockBins implements fiEventAware, fiLinkableTable, fiInsertableTable {
    use ftLinkableTable;
    use ftLoggableTable;
    use ftExecutableTwig;

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcrAdminStockBin';
    }
    public function GetActionKey() {
	return KS_ACTION_STOCK_BIN;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Stock Bins');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec() {
	return $this->AdminPage();
    } */

    // -- EVENTS -- //
    // ++ TABLES ++ //
    
    protected function BinInfoTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_STOCK_BINS_INFO);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      RETURNS: Dataset with extra information
      HISTORY:
	2017-03-23 now using a query object rather than a stored query
    */
    public function SelectInfoRecords($sqlFilt=NULL) {
	//$sqlFilt = is_null($iFilt)?'':' WHERE '.$iFilt;
	//$sql = 'SELECT * FROM qryStk_Bins_w_info'.$sqlFilt.' ORDER BY Code';
	$tInfo = $this->BinInfoTable();
	$rs = $tInfo->SelectStatusRecords($sqlFilt);
	return $rs;
    }
    public function Search_byCode($sCode) {
	$sqlCode = $this->Engine()->SanitizeAndQuote(strtoupper($sCode));
	$rs = $this->SelectRecords('UPPER(Code) = '.$sqlCode);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ARRAYS ++ //

    /*----
      ACTION: Returns stock data for the given list of items
      NOTE: We might later have this return all items in stock
	if iItems is NULL, but at the moment I can't think of
	a use for this.
    */
    public function Info_forItems(array $iItems) {
	throw new exception('2017-03-23 Is anything still calling this? It will need updating.');
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

    // -- ARRAYS -- //
    // ++ CALCULATIONS ++ //

    /*----
      NOTE: For listing multiple items, use Info_forItems()
    */
    public function Qty_ofItem($idItem) {
	throw new exception('2017-03-16 This will need fixing also.');
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
	throw new exception('2017-03-23 Is anything still calling this? It will need updating.');
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
	throw new exception('2017-03-23 Is anything still calling this? It will need updating.');
	// set up input parameters
	$iName		= empty($iArgs['name'])	? ($this->ActionKey()) 	: $iArgs['name'];
	$iDefault	= empty($iArgs['def'])	? NULL 			: $iArgs['def'];
	$iChoose	= empty($iArgs['def'])	? NULL 			: $iArgs['def'];
	$iFilt		= empty($iArgs['filt'])	? 'WhenVoided IS NULL'	: $iArgs['filt'];
	$iSort		= empty($iArgs['sort'])	? 'Code'		: $iArgs['sort'];

	$rs = $this->GetData($iFilt,NULL,$iSort);
	$strName = is_null($iName)?($this->ActionKey()):$iName;

	$fnFilt = function(vcrAdminStockBin $rcBin) {
	    return $rcBin->PlaceRecord()->IsActivated();
	};

	return $rs->DropDown($iName,$iDefault,$iChoose,$fnFilt);
    }
    /*----
      ACTION: Displays all bins
      USED BY: main menu
      HISTORY:
	2011-03-03 renamed from ListPage() to AdminPage(), to work with menu regularization
    */
    public function AdminPage($sqlFilt=NULL) {
	$rs = $this->SelectInfoRecords($sqlFilt);
	return $rs->AdminList(NULL)
	  . $this->EventListing()
	  ;
    }

    // -- ADMIN WEB UI -- //

}
class vcrAdminStockBin_trait extends  vcrStockBin {
    use ftSaveableRecord;	// we need to override GetStorableValues_toInsert()
}
class vcrAdminStockBin extends vcrAdminStockBin_trait implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftLoggableRecord;
    use vtAdminStockBin;
    use ftExecutableTwig;

    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	if ($this->IsNew()) {
	    $doEdit = TRUE;
	    $sCode = NULL;
	    $htTitle = 'New bin';
	    $sTitle = $htTitle;
	} else {
	    $id = $this->GetKeyValue();
	    $sCode = $this->GetFieldValue('Code');
	    $htTitle = "Stock Bin $id - $sCode";
	    $sTitle = "$sCode (bin $id)";
	    //$sName = $sCode;
	}
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    } */

    // -- EVENTS -- //
    // ++ CLASS NAMES ++ //

    protected function CTitlesClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_CLASS_CATALOG_TITLES;
	} else {
	    return 'vctTitles';
	}
    }
    protected function CItemsClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.lcat')) {
	    return KS_ADMIN_CLASS_LC_ITEMS;
	} else {
	    return KS_LOGIC_CLASS_LC_ITEMS;
	}
    }
    protected function StockLinesClass() {
	return KS_CLASS_STOCK_LINES;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

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
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_STOCK_BIN_LOG,$id);
    }
    protected function StockInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_STOCK_LINES_INFO);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    protected function StockRecords() {
	$id = $this->GetKeyValue();
	$sql = "(ID_Bin=$id) AND (Qty > 0)";
	$rs = $this->LineTable()->FetchRecords($sql);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ARRAYS ++ //
    
    /*----
      RETURNS: an array of what's in stock, summed by Item
    */
    protected function ItemArray() {
	$rs = $this->StockRecords();
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$idItem = $rs->ItemID();
		$qty = $rs->Qty();
		fcArray::NzSum($arOut,$idItem,$qty);
	    }
	} else {
	    $arOut = NULL;
	}
	return $arOut;
    }
    
    // -- ARRAYS -- //
    // ++ FIELD VALUES ++ //

    public function Name() {	// alias, for now
	throw new exception('2017-03-24 This alias seems like a bad idea. Document necessity if it emerges.');
	return $this->GetFieldValue('Code');
    }
    protected function Code() {
	return $this->GetFieldValue('Code');
    }
    protected function WhenCreated() {
	return $this->Value('WhenCreated');
    }
    protected function WhenCounted() {
	return $this->Value('WhenCounted');
    }

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //
    
    public function NameLong() {
	$out = $this->Value('Code');
	$txtDescr = $this->Value('Descr');
	if (!is_null($txtDescr)) {
	    $out .= ': '.$txtDescr;
	}
	return $out;
    }
    // PUBLIC so Stock Line records can access it in admin view
    public function Place_isActive() {
	throw new exception('2017-04-19 Call HasActivePlace() instead');
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    return FALSE;
	} else {
	    return $rcPlace->IsActive();
	}
    }
    public function PlaceName() {
	$rc = $this->PlaceRecord();
	return $rc->Name();
    }
    protected function Place_SelfLink_name($htNA='<i>n/a</i>') {
	$rcPlace = $this->PlaceRecord();
	if (is_null($rcPlace)) {
	    return $htNA;
	} else {
	    return $rcPlace->SelfLink_name();
	}
    }
    
    // -- FIELD CALCULATIONS -- //
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
	$rs = $this->StockRecords();
	return $rs->CountStock();
    }
    public function Qty_forItem($idItem,$nDefault=0) {
	$qty = $this->LineTable()->ItemQty_inBin($this->GetKeyValue(),$idItem);
	if (is_null($qty)) {
	    return $nDefault;
	} else {
	    return $qty;
	}
    }

    // -- CALCULATIONS -- //
    // ++ DATA WRITE ++ //
    
    // CALLBACK
    public function GetStorableValues_toInsert() {
	$ar = parent::GetStorableValues_toInsert();
	$ar['WhenCreated'] = time();
	return $ar;
    }
    // CALLBACK
    public function GetStorableValues_toUpdate() {
	$ar = parent::GetStorableValues_toUpdate();
	$ar['WhenEdited'] = time();
	return $ar;
    }

    // -- DATA WRITE -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Remove the given quantity of the given Catalog Item from the current Bin.
      USAGE: It is the caller's responsibility to ensure that the quantity removed is added somewhere else.
      PUBLIC so that Package can use it to remove items during the packaging process.
    */
    public function Remove($qty,$idItem,vcrStockLineEvent $oEvent=NULL) {
	return $this->LineTable()->Remove($qty,$idItem,$this->GetKeyValue(),$oEvent);
    }
    public function Add($qty,$idItem,vcrStockLineEvent $oEvent=NULL) {
	return $this->LineTable()->Add($qty,$idItem,$this->GetKeyValue(),$oEvent);
    }
    /*----
      ACTION: Move the current bin to the given Place, and log the move
      NOTE: 2017-05-07 This will probably need to be updated to use EventPlex.
    */
    public function MoveTo($idDest) {
	$rcDest = $this->PlaceTable($idDest);
	$txtEv = 'Moving from [#'
	  .$this->GetPlaceID()
	  .'='
	  .$this->PlaceRecord()->NameString()
	  .'] to [#'
	  .$idDest
	  .'='
	  .$rcDest->NameString()
	  .']'
	  ;

	$arEv = array(
	  'descr' => $txtEv,
	  'where' => __METHOD__,
	  'code'  => 'MV'
	  );
	$rcEv = $this->StartEvent($arEv);

	$arUpd['ID_Place'] = $idDest;
	$this->Update($arUpd);

	// log the move - old style
	$idPlaceOld = $this->GetPlaceID();
	$idPlaceNew = $idDest;
	$this->LogEvent($idPlaceOld,$idPlaceNew,$txtEv);

	$rcEv->Finish();
    }

    // -- ACTIONS -- //
    // ++ UI COMPONENTS ++ //
    
    // PUBLIC for other types to use in editable forms
    public function ListItem_Text() {
	return $this->NameLong();
    }
    // PUBLIC for other types to use in editable forms
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    
    // -- UI COMPONENTS -- //
    // ++ ADMIN WEB UI ++ //
    
    //++single++//
    
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    if ($this->IsNew()) {
		$sTimes = '';
	    } else {
		$sTimes = <<<__END__
  <tr><td align=right><b>Created</b>:</td><td>[#WhenCreated#]</td></tr>
  <tr><td align=right><b>Edited</b>:</td><td>[#WhenEdited#]</td></tr>
  <tr><td align=right><b>Tainted</b>:</td><td>[#WhenTainted#]</td></tr>
  <tr><td align=right><b>Counted</b>:</td><td>[#WhenCounted#]</td></tr>
  <tr><td align=right><b>Voided</b>:</td><td>[#WhenVoided#]</td></tr>
__END__;
	    }
	    $sTplt = <<<__END__
<table class=content>
  <tr><td align=right><b>Code</b>:</td><td>[#Code#]</td></tr>
  <tr><td align=right><b>Where</b>:</td><td>[#ID_Place#]</td></tr>
  <tr><td align=right><b>Status</b>:</td><td>[#Status#]</td></tr>
$sTimes
  [#DescRow#]
</table>
[#NotesRow#]
<br>
__END__;
	    $this->tpPage = new fcTemplate_array('[#','#]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      HISTORY:
	2010-11-01 adapted from vcrAdminPackage (formerly clsPackage)
    */
    private $oForm;
    private function PageForm() {
	// create fields & controls
	if (is_null($this->oForm)) {
	    $oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();

	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_Place');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		// TODO: maybe this should be doAll *only* for read-only mode?
		// Do we want to show inactive Places? Current policy: only if we're already in one, and not a new record
		$doAll = $this->IsNew() ? FALSE : (!$this->HasActivePlace());
		$oCtrl->SetRecords($this->PlaceTable()->SelectRecords_forDropDown(!$doAll));
		// TODO: change 'id-place' to action constant
		$idPlace = $oPathIn->GetInt('id-place');
		if (!is_null($idPlace)) {
		    $oCtrl->Editable(FALSE);
		    $oField->SetValue($idPlace,TRUE);
		}
		
	      $oField = new fcFormField_Text($oForm,'Code');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>8));
		
	      $oField = new fcFormField_Text($oForm,'Descr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>30));
		
	      
	      if (!$this->IsNew()) {
		  $oField = new fcFormField_Time($oForm,'WhenCreated');
		    $oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
		      $oCtrl->Editable(FALSE);
		    
		  $oField = new fcFormField_Time($oForm,'WhenEdited');
		    $oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
		      $oCtrl->Editable(FALSE);
		  
		  $oField = new fcFormField_Time($oForm,'WhenVoided');
		    //$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
		    
		  $oField = new fcFormField_Time($oForm,'WhenCounted');
		    //$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
		  
		  $oField = new fcFormField_Time($oForm,'WhenTainted');
		    //$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());
	      }
		
	      $oField = new fcFormField_Bit($oForm,'isForSale');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());
		
	      $oField = new fcFormField_Bit($oForm,'isForShip');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());
		
	      $oField = new fcFormField_Bit($oForm,'isEnabled');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());
		
	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    /*-----
      ACTION: Display bin details and contents (via this->AdminContents())
      HISTORY:
	2011-04-01 moved AdminInventory() up so it takes place before record is displayed
    */
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$isNew = $this->IsNew();
	if (!$isNew) {

	    $sCode = $this->GetFieldValue('Code');
	
	    $oMenu = fcApp::Me()->GetHeaderMenu();
	      $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('mode','Action'));
		$oGrp->SetChoice($ol = new fcHeaderChoice('edit','edit specs for '.$sCode));
		  $doEdit = $ol->GetIsSelected();
					      // ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
		$oGrp->SetChoice($ol = new fcHeaderChoice('inv','list all inventory for bin '.$sCode,'inventory'));
		  $doInv = $ol->GetIsSelected();
		  
	    // menu choices for places other than the header
	    $omDoVoid = new fcMenuOptionLink('do','void',NULL,NULL,'immediately void '.$sCode);
	      $doVoid = $omDoVoid->GetIsSelected();
	} else {
	    $doEdit = TRUE;
	    $doInv = FALSE;
	    $doVoid = FALSE;
	}

	$rcPlace = $this->PlaceRecord();

	// TODO: a lot of this logic should probably be inside the if-not-new condition
	
	$doSave = $oFormIn->GetBool('btnSave');
	$doAdd = $isNew;
	//$isEditReq = $oPathIn->GetBool('edit');
	//$doEdit = $isEditReq || $isNew;
	$doUpd = $oPathIn->GetBool('update');
	//$strDo = $oPathIn->GetString('do');
	$idPlace = $oPathIn->GetInt('id-place');
	$hasPlace = !is_null($idPlace);
	if ($isNew && $hasPlace) {
	    $this->SetPlaceID($idPlace);	// set value for control to use as default
	}
	
	$frm = $this->PageForm();

	// process any data-changing user input -- goes before the main header
	$out = '';
	if ($doSave) {
	    // check to see if we're moving the bin -- log it, if so
	    if (!$this->IsNew()) {
		// only pre-existing bins can be "moved"
		$idPlaceNew = $this->PageForm()->EnteredValue($this->GetKeyValue(),'ID_Place');
		$idPlaceOld = $this->GetPlaceID();
		if ($idPlaceNew != $idPlaceOld) {
		    // we're moving the bin -- log it:
		    $this->MoveTo($idPlaceNew);	// actually, this also moves it... redundant
		}
	    }
	/*
	  TODO:
	    "WhenCreated" should be set when new record is saved.
	    "WhenUpdated" should be set when record is altered (not including timestamps).
	    "WhenChanged" should be set when contents have changed. (Field does not yet exist.)
	*/
	    $frm->Save();	// save edit
	    $out .= $frm->MessagesString();
	    if ($isNew) {
		// only redirect to self if there isn't something more useful
		if ($hasPlace) {	// If we have a default Place, use that.
		    $rcPlace = $this->PlaceRecord();
		    $rcPlace->SelfRedirect(NULL,$out);
		    return;
		}
	    }
	    $this->SelfRedirect(NULL,$out);
	    return;
	}

	if ($isNew) {
	    $doEnabled = FALSE;
	} else {
	    $doEnabled = $this->HasActivePlace();
	    if ($doUpd) {
		// update any calculated fields
		$arUpd = array(
		  'isEnabled' => $doEnabled?"b'1'":"b'0'"
		  );
		$this->Update($arUpd);
		$this->SelfRedirect();
	    }
	}

	$out .= $this->AdminInventory();
	if ($doVoid) {
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

	// Set up rendering objects
	$frm = $this->PageForm();
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $frm->FieldObject('isEnabled')->SetValue($doEnabled,TRUE);	// set the enabled flag to save properly
	    $htForSale = $frm->GetControlObject('isForSale')->Render(TRUE);
	    $htForShip = $frm->GetControlObject('isForShip')->Render(TRUE);
	    $htEnabled = $frm->GetControlObject('isEnabled')->Render(TRUE); 
	    $htStatus = "[$htForSale for sale][$htForShip for shipping][$htEnabled enabled]";
	} else {
	    // customize the form data:
	    $arCtrls['Code'] = $this->SelfLink_name();

	    $isForSale = $this->IsSellable();
	    $isForShip = $this->IsShippable();
	    //$isEnabled = $this->IsEnabled();
	    $htStatus =
	      ' '.($isForSale?'<b>SELL</b>':'<s>sell</s>')
	      .' '.($isForShip?'<b>SHIP</b>':'<s>ship</s>')
	      //.' '.($isEnabled?'<b>':'<s>').'ENABLED'.($isEnabled?'</b>':'</s>')
	      ;

	    $doEnabled = $this->HasActivePlace();
	    /* 2017-03-24 isEnabled field is deprecated
	    if ($isEnabled != $doEnabled) {
		$arLink = $oPage->PathArgs(array('page','id'));
		$arLink['update'] = TRUE;
		$urlUpd = $oPage->SelfURL($arLink,TRUE);

		$txtStat = $doEnabled?'enabled':'disabled';
		$htStatus .= ' - <b><a href="'.$urlUpd.'">update</a></b> - should be '.$txtStat;
	    } */

	    $dtVoided = $this->GetFieldValue('WhenVoided');
	    if (is_null($dtVoided)) {
		//$htWhenVoided = $oPage->SelfLink(array('do'=>'void'),'void now');
		/* 2017-03-22 old
		$url = $oPage->SelfURL(array('do'=>'void'));
		$htWhenVoided = fcHTML::BuildLink($url,'void now');
		*/
		$htWhenVoided = $omDoVoid->Render();	// hope this works...
	    } else {
		$htWhenVoided = $dtVoided;
	    }
	    $htWhenTainted = $this->GetFieldValue('WhenTainted');
	}

	$htDescRow = $htNotesRow = NULL;
	if ($doEdit || !is_null($this->GetFieldValue('Descr'))) {
	    $htDescRow = "<tr><td align=right><b>Description</b>:</td><td>[#Descr#]</td></tr>";
	}
	if ($doEdit || !is_null($this->GetFieldValue('Notes'))) {
	    $htNotesRow = "\n<b>Notes</b>:<br>[#Notes#]";
	}

	//$arCtrls['Place'] = $htPlace;
	$arCtrls['Status'] = $htStatus;
	$arCtrls['DescRow'] = $htDescRow;
	$arCtrls['NotesRow'] = $htNotesRow;

	// render the template
	$oTplt->SetVariableValues($arCtrls);
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

	    $out .= $this->AdminContents()
	      . (new fcSectionHeader('History'))->Render()
	      . $this->RenderEvents()	// bin event log - needs to be merged with system log
	      . $this->EventListing()	// universal log
	      ;
	}
	return $out;
    }
    
    //--single--//
    //++multi++//
    
    /*----
      ACTION: Displays the current dataset in multi-row format, with administrative controls
      HISTORY:
	2010-10-30 written; replaces code in VbzStockBins
      INPUT:
	$iArgs
	  'do.place': TRUE = show place column; FALSE = all in one place, don't bother to list it
    */
    public function AdminList($idPlace=NULL) {
	throw new exception('2017-03-23 Is anything still calling this?');
	$isPage = is_null($idPlace);
	$arActs = array(
	  new clsActionLink_option(
	    array(),		// additional link data
	    'edit.bins',	// link key
	    NULL,		// group key
	    'edit',		// display when off
	    NULL		// display when on
	    ),
	  new clsActionLink_option(
	    array(),		// additional link data
	    'add.bin',		// link key
	    NULL,		// group key
	    'add',		// display when off
	    NULL,		// display when on
	    'add a new bin'	// popup description
	    ),
	  new clsAction_section('show'),
	  new clsActionLink_option(
	    array(),		// additional link data
	    'show.no-use',	// link key
	    NULL,		// group key
	    'unusable',		// display when off
	    NULL,		// display when on
	    'show unusable bins'	// popup description
	    ),
//	  new clsActionLink_option(array(),'inv',NULL,'list all inventory of location '.$strName)
	  );

	$oPage = $this->Engine()->App()->Page();
	$out = NULL;
	
	if ($isPage) {
	    $oPage->PageHeaderWidgets($arActs);
	} else {
	    $out .= $oPage->ActionHeader('Bins',$arActs);
	}
	
	$rs = $this;
	if ($rs->hasRows()) {
	    $doShowNoUse = $oPage->PathArg('show.no-use');
	
	    //$doPlace = nz($iArgs['do.place']);
	    $doPlace = is_null($idPlace);
	    $htPlace = NULL;

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
	    $isOdd = FALSE;
	    while ($rs->NextRow()) {
		$row = $rs->Row;
		$cssClass = $isOdd?'odd':'even';
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

		$isActive = $rs->IsActive();
		$isUsable = $rs->IsUsable();
		if ($isUsable || $doShowNoUse) {
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
		    $htCode = $rs->SelfLink_name();
		    $htWhenMade = clsDate::NzDate($row['WhenCreated']);
		    $htWhenVoid = clsDate::NzDate($row['WhenVoided']);
		    $htWhenCount = clsDate::NzDate($row['WhenCounted']);
		    $htWhenTaint = clsDate::NzDate($row['WhenTainted']);

		    if ($doPlace) {
			$rcPlace = $rs->PlaceRecord();
			if (is_null($rcPlace)) {
			    $sPlace = '<i>root</i>';
			} else {
			    $sPlace = $rcPlace->SelfLink_name();
			}
			$htActive = $rs->StatusCode();
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
		    $htDesc = fcString::EncodeForHTML($row['Descr']);

		    $out .= <<<__END__
  <tr class=$cssClass>
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
		}	// -IF showing
	    }	// -WHILE rows
	    $out .= "\n</table>";
	    $out .= "\n<input type=submit name=btnSelBins value=\"Move items to...\">";
	    $arLink = array(
	      'page'	=> KS_ACTION_STOCK_BIN,
	      'id'	=> KS_NEW_REC,
	      );
	    if (!is_null($idPlace)) {
		$arLink['id-place'] = $idPlace;
	    }
	    $url = $oPage->SelfURL($arLink);
	    $htLink = fcHTML::BuildLink($url,'create new bin');
	    $out .= "[ $htLink ]";
	    $out .= "\n</form>";
	} else {
	    $out .= 'No bins found.';
	}
	
	// Show Move-Bin(s) section, if active:
	$out .= $this->AdminListSave();
	// TODO: process form input separately from displaying it, so we can save CPU

	return $out;
    }
    
    //--multi--//
    //++related++//
    
    /*-----
      ACTION: Display bin contents
      TODO: The iterative part of this should probably be refactored into a line-item-info class.
	Also, determine whether header should be shown *after* counting number of rows that will appear.
    */
    public function AdminContents() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$sHdr = 'Bin Contents';
	$sBinName = $this->LabelString();
	
	// header/menu
	$oMenu = new fcHeaderMenu();
	$oHdr = new fcSectionHeader($sHdr,$oMenu);
	
	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Show'));
	    $oGrp->SetChoice($ol = new fcHeaderChoice('rmvd', "show items that have been removed from $sBinName",'removed'));
	      $doShowRmvd = $ol->GetIsSelected();

	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('mode','Action'));
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('move.items',"move items from $sBinName to another bin",'move'));
	      $doMoveForm = $ol->GetIsSelected();
	    $oGrp->SetChoice($ol = new fcHeaderChoice('count.items',"record inventory count for $sBinName",'count'));
	      // apparently this is used in $this->AdminInventory()

	$out = $oHdr->Render();

	/* 2017-03-22 old
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),
	    'move.items',
	    'do',
	    'move',
	    NULL,
	    "move items from $sBinName to another bin"
	    ),
	  new clsActionLink_option(array(),
	    'inv',
	    'do',
	    'count',
	    NULL,
	    "record inventory count for $sBinName"
	    ),
	  new clsAction_section('show'),
	  new clsActionLink_option(array(),
	    'rmv',	// link key
	    'show',	// group key
	    'removed',	// display when off
	    NULL,	// display when on
	    "show items that have been removed from $sBinName"	// popup description
	    ),
	  );
	$out .= $oPage->ActionHeader($sHdr,$arActs);

	$sDo = $oPage->PathArg('do');
	$sShow = $oPage->PathArg('show');
	$doShowRmvd = ($sShow == 'rmv');
	$doMoveForm = ($sDo == 'move.items');
	*/
	$doConfForm = $oFormIn->GetBool('btnMove');
	$doMoveNow = $oFormIn->GetBool('btnConf');

	$idBin = $this->GetKeyValue();
	if (empty($idBin)) {
	    $out .= "<b>Internal Error</b>: No bin ID has been set.";
	} else {
	    $doForm = !$doMoveNow && ($doMoveForm || $doConfForm);

	    if ($doForm) {
		$out .= '<form method=post>';
		$out .= '<div align=right>';
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
		    $rcDest = $this->Table()->GetItem($idDest);
		    $strCodeThis = $this->Code();
		    $strCodeDest = $rcDest->Code();

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
			$rcDest->LogEvent_SamePlace($txtDescr);
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
			    $out .= '<input type=hidden name=notes value="'.fcString::EncodeForHTML($strNotes).'">';
			}
		    }
		    if ($doConfForm) {
			$out .= '<input type=submit name="btnConf" value="Confirm Move">';
		    }
		} else {
		    $out .= 'No items were listed for moving.';
		}
	    }

	    $rs = $this->StockInfoQuery()->GetRecords_forBinExhibit($idBin,$doShowRmvd);
	    $out .= $rs->AdminRows_forBin($doMoveForm);
	    
/*	    
	    $rs = $tItemInfo->DataSQL($qoItems->Render());
	    
	    $sqlSource = KS_TBL_STOCK_LINES.' AS si LEFT JOIN qryCat_Items AS ci ON si.ID_Item=ci.ID';
	    $sqlCols = 'si.*, ci.CatNum AS ItCatNum, ci.ID_Title, ci.Title_Name AS TiName';
	    $sql = 'SELECT '.$sqlCols.
		' FROM '.$sqlSource.
		' WHERE ID_Bin='.$idBin.
		' ORDER BY WhenRemoved,ItCatNum';

	    $rs = $this->Engine()->DataSet($sql,'vcVbzRecs_admin');//*/

	    if ($rs->hasRows()) {
	    
//		$tItems = $this->CItemTable();
//		$tTitles = $this->TitleTable();
//		$rs->Table = $this->LineTable();
		if ($doMoveForm) {
		    $rsBins = $this->Table()->GetActive();
		    $out .= 'To: '.$rsBins->DropDown(KS_ACTION_STOCK_BIN);
		    $out .= '<br>Notes: <input type=text name="notes" size=40>';
		    $out .= '<br><input type=submit name="btnMove" value="Select for Moving" />';
		}
		if ($doForm) {
		    $out .= '</div>';
		}
		$ftList = NULL;
/*		$out .= "<table class=listing><tr>\n".
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

		while ($rs->NextRow()) {
		    $row = $rs->Values();

		    $id		= $row['ID'];
		    $htID	= $rs->SelfLink();
		    $idItem 	= $row['ID_Item'];
		    $txtCatNum	= is_null($row['CatNum'])?"<i>".$row['ItCatNum']."</i>":$row['CatNum'];

		    $rcItem	= $tItems->GetItem($idItem);
		    $htCatNum	= $rcItem->SelfLink($txtCatNum);

		    $isActive	= is_null($row['WhenRemoved']);
		    $hasAnything = ($isActive && ($row['Qty'] > 0));
		    if ($hasAnything || $doShowRmvd) {
		    
			// calculate line formatting:
			$htStyle = $isOdd?'background:#ffffff;':'background:#dddddd;';
			$isOdd = !$isOdd;
			//$isActive = is_null($row['WhenRemoved']) && ($row['Qty'] > 0);
			if (!$hasAnything) {
			    $htStyle .= ' color: #888888;';
			    $htStyle .= ' text-decoration: line-through;';
			}
			$htActive	= fcHTML::fromBool($isActive);
			
			$txtQty	= $row['Qty'];

			$sTitle = $row['TiName'];
			$rcTitle = $tTitles->GetItem($rs->Value('ID_Title'));
			$htTitle	= $rcTitle->SelfLink($sTitle);

			$txtWhenAdded	= clsDate::NzDate($row['WhenAdded']);
			$txtWhenChged	= clsDate::NzDate($row['WhenChanged']);
			$txtWhenCnted	= clsDate::NzDate($row['WhenCounted']);
			$txtWhenRmved	= clsDate::NzDate($row['WhenRemoved']);
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
		}
		$out .= "\n</table>";
		if (!is_null($ftList)) {
		    $out .= "<b>Text list</b> (active only): $ftList<br>";
		}//*/
		if ($doForm) {
		    $out .= "\n</form>";
		}
	    } else {
		$out .= 'No stock found.';
	    }//*/
	    // TODO: Make SQL display an option
	    //$out .= '<br><span class="line-stats">'.$sql.'</span>';
	}
	return $out;
    }
    /*----
      RETURNS: Rendering of stock bin events for this bin
    */
    public function RenderEvents() {
	$t = $this->BinLog();
	$rs = $t->SelectRecords('ID_Bin='.$this->GetKeyValue());
	return $rs->RenderRows();		// stock bin event listing
    }
    
    //--related--//
    //++forms++//
    
    /*----
      ACTION: process bulk inventory entries from an array
      OUTPUT: array
	[list.text] (txtInvList) = reconstruction of list from parsed array (show in textbox if not done)
	[msg.text] = text to display
    */
    protected function AdminInventory_bulk(array $arLines) {
	throw new exception('AdminInventory_bulk() is being rewritten under other names.');
	// we have an inventory to process
	
	// build an array of what needs to be entered

	$txtInvList = '';	// rebuild text list after processing
	$cntNew = 0;		// number of recognized entries in list
	$cntUnk = 0;		// number of unrecognized entries in list
	$qtyNew = 0;		// total quantity for "new" items
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
	    $rcItem = $tItems->Get_byCatNum($catnum);
//echo 'Item: '.$objItem->AdminLink();
	    $txtInvList .= "$catnum\t$qty\t";
	    if (is_null($rcItem)) {
		$txtInvList .= '! not found';
		$cntUnk++;
	    } else {
		$txtInvList .= '; OK';
		$idItem = $rcItem->GetKeyValue();
		$arAll[$idItem]['new'] = nz($arAll[$idItem]['new']) + $qty;
		$cntNew++;
		$qtyNew += $qty;
	    }
	    $txtInvList .= "\n";
	}
	// show results of inventory, for approval
	//$out .= $vgOut->Header('Inventory Count Results',3);

	$idBin = $this->GetKeyValue();
	
	// get list of items currently in bin (according to db):
	$sqlLines = '(ID_Bin='.$idBin.') AND (Qty > 0)';
	$rsLines = $this->StockItemTable()->FetchRecords($sqlLines);
	if ($rsLines->HasRows()) {
	    while ($rsLines->NextRow()) {
		$idItem = $rsLines->ItemID();
		$arAll[$idItem]['old'] = nz($arAll[$idItem]['old']) + $rsLines->Qty();
	    }
	}

	if (is_array($arAll)) {	// if any inventory lines have been processed...
	    $htRes .= '<input type=hidden name=inv value="'.fcString::EncodeForHTML($txtInvList).'">';

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
		$intOld = clsArray::NzInt($arQty,'old');
		$intNew = clsArray::NzInt($arQty,'new');
		$rcItem = $this->ItemTable($id);
		$htItem = $rcItem->AdminLink($rcItem->CatNum).' '.$rcItem->FullDescr_HTML();
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
    }
    /*----
      ACTION:
	* checks each line to see if it is a recognized item
	* accumulates list of recognized items, with total quantities of each
	* accumulates counts of recognized and unrecognized lines
	* rebuilds plaintext entry, with annotations to indicate each line's status
	
      RETURNS: array
	[cnt.unk] = count of lines with unrecognized catalog numbers
	[cnt.fnd] = count of lines with recognized catalog numbers
	[arr.fnd] = array of recognized items
	[txt.inv] = rebuilt plaintext inventory data
    */
    protected function AdminInventory_check(array $arLines) {
	$tItems = $this->CItemTable();
	$cntUnk = 0;
	$cntFnd = 0;
	$arFnd = NULL;
	$txtInv = NULL;
	$txtUnk = NULL;

	foreach ($arLines as $idx => $arLine) {
	    $catnum = strtoupper($arLine[0]);
	    $qty = (int)clsArray::Nz($arLine,1,1);	// if 2nd element doesn't exist, use value '1'
	    $rcItem = $tItems->Get_byCatNum($catnum);
	    $txtInv .= "$catnum\t$qty\t";
	    if (is_null($rcItem)) {
		$txtInv .= '! not found';
		$cntUnk++;
		$txtUnk .= ' '.$catnum;
	    } else {
		$txtInv .= '; OK';
		$idItem = $rcItem->GetKeyValue();
		clsArray::NzSum($arFnd,$idItem,$qty);	// accumulate total quantities for each item
		$cntFnd++;
	    }
	    $txtInv .= "\n";
	}
	$arOut = array(
	  'cnt.unk'	=> $cntUnk,
	  'cnt.fnd'	=> $cntFnd,
	  'arr.fnd'	=> $arFnd,
	  'txt.inv'	=> $txtInv,	// put this back in the form
	  'txt.unk'	=> $txtUnk	// simple listing of unrecognized catnums
	  );
	return $arOut;
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
	2015-11-27 Almost-completely rewritten.
      NOTE that there is no sanity-checking done on the final stage -- I'm going to assume, for now,
	that a logged-in user with inventory powers wouldn't deliberately hack the inventory counting process,
	and the sanity-check process before that should weed out any accidental garbage data.
    */
    protected function AdminInventory() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$sDo = $oPathIn->GetString('do');
	
	$doInvEnter = ($sDo == 'inv');
	$doInvCheck = $oFormIn->GetBool('btnInvLookup');
	$doInvSave = $oFormIn->GetBool('btnInvSave');
	if ($doInvSave) {
	    $doInvEnter = FALSE;
	}
	$txtInvList = $oFormIn->GetString('inv');	// raw data
		
	$out = NULL;
	
	if ($doInvEnter || $doInvCheck) {
	
	    // default instructions above the entry form:
	    $htEntryMsg = 'one line per item: catalog #, optionally followed by a space and the quantity (default=1)';

	  // figure out what we're actually doing
	  
	    $htInvList = fcString::EncodeForHTML($txtInvList);	// sanitized for re-display
	    if (is_null($txtInvList)) {
		// if there's nothing entered, we can't check or save it
		$doInvCheck = FALSE;
		//$doInvSave = FALSE;
	    } else {
		$xts = new fcStringBlock($txtInvList);
		$arLines = $xts->ParseTextLines(array('line'=>'arr'));
		if (!is_array($arLines)) {
		    // if the data can't be parsed, we can't check or save it
		    $doInvCheck = FALSE;
		    //$doInvSave = FALSE;
		}
	    }
	    
	    if ($doInvCheck) {
	    
	      // PHASE II -- inventory text parsed; now check it against catalog
		$arStats = $this->AdminInventory_check($arLines);
		$cntUnk = $arStats['cnt.unk'];
		$htInvList = $arStats['txt.inv'];	// get rebuit/annotated inventory
		if ($cntUnk == 0) {
		    // PHASE II-A -- all inventory lines accounted for; get user confirmation to save inventory
		    $arEntry = $arStats['arr.fnd'];
		    $arStock = $this->ItemArray();
		    $out .= $oPage->ActionHeader('Inventory Changes')
		      ."\n<form method=post>"
		      .$this->Inventory_RenderStatus($arStock,$arEntry)
		      ."\n<input type=submit name=btnInvSave value='Save Changes'>"
		      ."\n</form>"
		      ;
		    $doInvEnter = FALSE;
		} else {
		    // there were unrecognized entries -- continue PHASE I
		    $doInvEnter = TRUE;
		    $doInvCheck = FALSE;
		    $doInvSave = FALSE;
		    $txtSng = 'one catalog # was';
		    $txtPlr = $cntUnk.' catalog #s were';
		    $txtUnk = $arStats['txt.unk'];
		    $htEntryMsg = fcString::Pluralize($cntUnk,$txtSng,$txtPlr).' not recognized:'.$txtUnk;
		}
	    }
	    if ($doInvEnter) {
	      // PHASE I

		$out .= (new fcSectionHeader('Inventory Count Entry'))->Render()
		  ."\n<form method=post>"
		  ."\n$htEntryMsg<br>"
		  ."\n<textarea name=inv rows=20>$htInvList</textarea>"
		  ."\n<input type=submit name=btnInvLookup value='Lookup...'>"
		  ."\n</form>"
		  ;
	    }
	} elseif ($doInvSave) {
	  // PHASE III
	    $arQtys = $_POST['qty'];
	    $this->Inventory_Save($arQtys);
	    $this->SelfRedirect();
	} else {
	    $out = NULL;	// nothing to do
	}
	return $out;
    }
    /*----
      ACTION: Updates the stock records from the given item counts.
      INPUT: array of all Items previously or currently in the current Bin
	[item ID] = quantity
    */
    protected function Inventory_Save(array $arItems) {
	$arEv = array(
	  'code'	=> 'INV',
	  'descr'	=> 'Inventory: '.clsArray::Render($arItems),
	  'where'	=> __METHOD__
	);
	$rcEv = $this->CreateEvent($arEv);
	
	$nUpd = 0;
	$nDup = 0;
	$nNew = 0;
	
	$idBin = $this->GetKeyValue();
	
	foreach ($arItems as $idItem => $qty) {
	    $sqlLines = "(ID_Bin=$idBin) AND (ID_Item=$idItem) AND (Qty > 0)";
	    $rsLines = $this->LineTable()->FetchRecords($sqlLines);	// stock lines for this item
	    if ($rsLines->HasRows()) {
		// old records exist -- update them
		// -- make sure there's only one record. If more than one, update the first and delete others.
		$isFirst = TRUE;
		while ($rsLines->NextRow()) {

		    if ($isFirst) {
			$isFirst = FALSE;
			// -- update the first record
			$rsLines->UpdateQty($qty,'inventory count');
			$nUpd++;
		    } else {
			$rsLines->UpdateQty(0,'inventory: zeroing duplicate item');
			$nDup++;
		    }

		}
	    } else {
		// no record; create a new record
		$this->InventoryAdd($idItem,$qty);
		$nNew++;
	    }
	}
	$sMsg =
	  $nUpd.' updated, '
	  .$nDup.' zeroed, '
	  .$nNew.' new'
	  ;
	$rcEv->Finish(array(fcrEvent::KF_DESCR_FINISH=>$sMsg));
    }
    protected function Inventory_RenderStatus(array $arStk=NULL, array $arEnt) {
	// make an array of all items either in stock or newly entered
	$arAll = clsArray::Merge($arStk,$arEnt);

	// make a new array indexed by catalog number
	$tItems = $this->CItemTable();
	foreach ($arAll as $id => $qty) {	// $qty is a dummy, here
	    $rc = $tItems->GetItem($id);
	    $sCatNum = $rc->CatNum();
	    $arCat[$sCatNum] = $rc->Values();
	}
	
	ksort($arCat);	// sort by catalog number
	
	$out = "\n<table class=listing><tr><th>Item</th><th>was</th><th>now</th><th>status</th></tr>";
	$isOdd = FALSE;
	$nSame = 0;
	foreach ($arCat as $sCatNum => $arItem) {
	    $rc->Values($arItem);
	    $id = $arItem['ID'];
	    $htItem = $rc->SelfLink($sCatNum);
	    $qOld = clsArray::Nz($arStk,$id,0);
	    $qNew = clsArray::Nz($arEnt,$id,0);
	    
	    if ($qOld == $qNew) {
		$nSame++;
		if ($qNew > 0) {
		    $sStatus = 'match';
		} else {
		    $sStatus = '(ok)';
		}
		$htStatus = "<font color=green>$htStatus</font>";
		$htRes = "\n    <td colspan=2 align=center>= $qNew =</td><td align=center>$htStatus</td>";
	    } else {
		if ($qOld < $qNew) {
		    $qDiff = $qNew-$qOld;
		    $htStatus = '<font color=green>'.$qDiff.' new</font>';
		} else {
		    if ($qNew == 0) {
			$htStatus = '<font color=red>MISSING</font>';
		    } else {
			$htStatus = '<font color=orange>'.($qOld-$qNew).' missing</font>';
		    }
		}
		$htStatus .= "<input type=hidden name=qty[$id] value=$qNew>";
		$htRes = "\n    <td align=right>$qOld</td><td align=right>$qNew</td><td>$htStatus</td>";
	    }
	    $css = $isOdd?'odd':'even';
	    $out .= "\n  <tr class=$css>\n    <td>$htItem</td>$htRes\n  </tr>";
	}
	$out .= "\n</table>";
	return $out;
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
	$this->LineTable()->InventoryAdd($this->GetKeyValue(),$iItem,$iQty);
    }
    /*-----
      DEPRECATED - use StartEvent() / FinishEvent()
    */
    public function LogEvent($iSrce,$iDest,$iDescr=NULL) {
	return $this->BinLog()->LogEvent($this->GetKeyValue(),$iSrce,$iDest,$iDescr);
    }
    /*-----
      ACTION: Log an event where the bin stays in the same place
    */
    public function LogEvent_SamePlace($iDescr) {
	$idPlace = $this->GetPlaceID();
	$this->LogEvent($idPlace,$idPlace,$iDescr);
    }

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
		$arOut[$this->GetKeyValue()] = $this->NameLong();
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
	    $out = fcHTML::DropDown_arr($iName,$arRows,$iDefault,$iChoose);
	} else {
	    $out = 'No locations found';
	}
	return $out;
    }

    // -- WEB UI CONTROLS -- //
}
