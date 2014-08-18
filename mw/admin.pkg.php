<?php
/*
  FILE: admin.pkg.php -- package administration for VbzCart
  HISTORY:
    2010-10-15 Extracted package classes from SpecialVbzAdmin.php
*/
class clsPackages extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_pkgs');
	  $this->KeyName('ID');
	  $this->ClassSng('clsPackage');
	  $this->ActionKey('pkg');
    }
    public function GetOrder($iID) {
	$objRows = $this->GetData('ID_Order='.$iID);
	$objRows->ID_Order = $iID;	// make sure this is set, regardless of whether there is data
	return $objRows;
    }
    /*----
      RETURNS: empty Package object
      HISTORY:
	2011-10-08 created so order message editing can display pkg drop-down that includes "no pkg" as an option
    */
    public function GetEmpty() {
	$rc = $this->SpawnItem();
	return $rc;
    }
}
class clsPackage extends clsDataSet {
    private $objOrd;
    private $objShip;

    /*====
      BOILERPLATE: event logging
      HISTORY:
	2011-02-18 replaces earlier (incomplete) boilerplate logging
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
    public function AdminURL($iarArgs=NULL) {
	return clsAdminData_helper::_AdminURL($this,$iarArgs);
    }
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect($iarArgs=NULL) {
	return clsAdminData_helper::_AdminRedirect($this,$iarArgs);
    }
    // no, this isn't really a boilerplate function, but we'll make it one eventually
    public function AdminLink_name($iPopup=NULL,array $iarArgs=NULL) {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $txt = $this->Number();
	    return $this->AdminLink($txt,$iPopup,$iarArgs);
	}
    }
    // --/BOILERPLATE--
    /*####
      Basic field functions
    */
    /*----
      FUTURE: need to define this more rigorously. Should it still return TRUE
	if the package has been checked into a shipment?
    */
    public function IsActive() {
	return is_null($this->Value('WhenVoided'));
    }
    public function IsVoid() {
	return !is_null($this->Value('WhenVoided'));
    }
    public function IsChecked() {
	return !is_null($this->Value('WhenChecked'));
    }
    public function Number() {
	$out = $this->OrderObj()->Number;
	$out .= '-';
	$intSeq = $this->Seq;
	$out .= empty($intSeq)?'*':$this->Seq;
	return $out;
    }
    // ORDER superdata
    public function OrderObj() {
	$doLoad = TRUE;
	$idObj = $this->Value('ID_Order');
	if (isset($this->objOrd)) {
	    $doLoad = ($idObj != $this->objOrd->ID);
	}
	if ($doLoad) {
	    $this->objOrd = $this->objDB->Orders()->GetItem($idObj);
	}
	if ($this->objOrd->IsNew()) {
	    throw new exception('Order object has no ID; package ID_Order=['.$idObj.']');
	}
	return $this->objOrd;
    }

    // SHIPMENT superdata
    public function ShipObj() {
	$doLoad = TRUE;
	$idObj = $this->ID_Shipment;
	if (isset($this->objShip)) {
	    $doLoad = ($idObj != $this->objShip->ID);
	}
	if ($doLoad) {
	    $this->objShip = $this->objDB->Shipmts()->GetItem($idObj);
	}
	return $this->objShip;
    }

    // LINE subdata
    public function LinesData($iFilt=NULL) {
	$objTbl = $this->objDB->PkgLines();
	$sqlFilt = 'ID_Pkg='.$this->KeyValue();
	if (!is_null($iFilt)) {
	    $sqlFilt = "($sqlFilt) AND ($iFilt)";
	}
	$objRows = $objTbl->GetData($sqlFilt);
	return $objRows;
    }
    /*----
      RETURNS: TRUE iff the package contains some items
	Currently returns TRUE as soon as a line with >0 items in it is found;
	  does not add up the rest of the package.
      HISTORY:
	2011-10-08 created so we can put packages back into stock
    */
    public function ContainsItems() {
	if ($this->IsNew()) {
	    return FALSE;
	} else {
	    $rc = $this->LinesData('WhenVoided IS NULL');
	    if ($rc->HasRows()) {
		$qty = 0;
		while ($rc->NextRow()) {
		    $qty += $rc->ItemQty();
		    if ($qty > 0) {
			return TRUE;
		    }
		}
	    } else {
		return FALSE;
	    }
	}
    }
    /*----
      RETURNS: total quantity of items currently in the package
	This is mainly used for reality-check display purposes.
      HISTORY:
	2011-10-08 created so we can put packages back into stock
    */
    public function ItemQty() {
	$rc = $this->LinesData();
	if ($rc->HasRows()) {
	    $qty = 0;
	    while ($rc->NextRow()) {
		$qty += $rc->ItemQty();
	    }
	} else {
	    $qty = 0;
	}
	return $qty;
    }
    public function NextLineSeq() {
	$objRows = $this->LinesData();
	$seq = NextSeq($objRows);
	return $seq;
    }
    public function AddLine($iItem, $iOLine) {
	$seq = $this->NextLineSeq();
	$arIns = array(
	  'ID_Pkg'	=> $this->KeyValue(),
	  'ID_Item'	=> $iItem,
	  'ID_OrdLine'	=> $iOLine
	  );
	$objPLines = $this->objDB->PkgLines();
	$objPLines->Insert($arIns);
	return $objPLines->LastID();
    }

    // TRANSACTION subdata
    public function AddTrx($iType,$iDescr,$iAmt) {
	$arIns = array(
	  'ID_Order'	=> $this->Value('ID_Order'),
	  'ID_Package'	=> $this->KeyValue(),
	  'ID_Type'	=> $iType,
	  'WhenDone'	=> 'NOW()',
	  'Descr'	=> SQLValue($iDescr),
	  'Amount'	=> $iAmt);
	$this->objDB->OrdTrxacts()->Insert($arIns);
    }
    /*----
      ACTION: Moves the Package's contents to the given Bin
      HISTORY:
	2011-10-08 created so we can return packages to stock
    */
    public function Move_toBin($iBin) {
	$out = NULL;
	$rc = $this->LinesData();
	if ($rc->HasRows()) {

	    $arEv = array(
	      'descr' => 'returning package to bin #'.$iBin,
	      'where' => __METHOD__,
	      'code'  => 'RTN'
	      );
	    //$this->StartEvent($arEv);

	    $tblStk = $this->Engine()->StkItems();

	    $qtyTot = 0;
	    while ($rc->NextRow()) {
		$qtyRow = $rc->ItemQty();
		$out .= "\n* ".$tblStk->Add_fromPkgLine($rc,$iBin);
		$qtyTot += $qtyRow;
	    }
	}
	return $out;
    }
    // UI CONTROLS AND PAGES
    /*----
      HISTORY:
	2011-01-02 Adapted from VbzAdminDept::DropDown to VbzAdminOrderTrxType
	  Control name now defaults to table action key
	2011-03-30 Adapted from VbzAdminOrderTrxType to clsPackage
    */
    public function DropDown_for_data($iName=NULL,$iDefault=NULL,$iNone=NULL,$iAccessFx='Number') {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key
	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		$htShow = $this->$iAccessFx();
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Renders a drop-down control showing all packages for this order, with the
	current record being the default.
    */
    public function DropDown_ctrl($iName=NULL,$iNone=NULL) {
	$dsAll = $this->Table->GetData('ID_Order='.$this->Value('ID_Order'),NULL,'Seq');
	return $dsAll->DropDown_for_data($iName,$this->KeyValue(),$iNone);
    }
    /*----
      HISTORY:
	2011-02-17 Updated to use objForm instead of objFlds/objCtrls
    */
    private function BuildEditForm($iNew) {
	global $vgOut;
	// create fields & controls

	if (empty($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);
/*
	    $vData = $iNew?NULL:$this->Row;
	    $objFlds = new clsFields($vData);
	    $objCtrls = new clsCtrls($objFlds);
*/
	    $objForm->AddField(new clsFieldTime('WhenStarted'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenFinished'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenChecked'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenVoided'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ChgShipItm'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ChgShipPkg'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ShipCost'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('PkgCost'),		new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ShipPounds'),	new clsCtrlHTML(array('size'=>2)));
	    $objForm->AddField(new clsFieldNum('ShipOunces'),	new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsField('ShipNotes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>40)));
	    $objForm->AddField(new clsField('ShipTracking'),	new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsFieldTime('WhenArrived'),	new clsCtrlHTML());

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the package
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      HISTORY:
	2011-05-29 extracted from AdminPage(); will probably need some debugging
    */
    private function AdminDoCharge() {
	$out = '<h3>Adding charges...</h3>';

	$objLines = $this->LinesData();
	if ($objLines->HasRows()) {	// package lines
	    $dlrSaleTot = 0;
	    $dlrItmShTot = 0;
	    $dlrPkgShTot = 0;

	    $txtLog = 'charging for package '.$this->Number().' (ID='.$this->ID.')';
	    $out .= $txtLog;

	    $arEvent = array(
	      'descr'	=> $txtLog,
	      'where'	=> __METHOD__,
	      'code'	=> '+PCH');
	    $this->StartEvent($arEvent);

	    $cntLines = 0;	// for logging only
	    $cntQty = 0;	// for logging only
	    while ($objLines->NextRow()) {
		$idRow = $objLines->ID;
		$objItem = $objLines->ItemObj();	// item data
		// prices in package line data override catalog prices
		//$dlrSale = $objItem->PriceSell;	// sale price
		//$objShCo = $objItem->ShipCostObj();	// shipping cost data
		//$dlrItmSh = $objShCo->PerItem;
		//$dlrPkgSh = $objShCo->PerPkg;
		$dlrSale = $objLines->PriceSell();	// sale price
		$dlrItmSh = $objLines->ShPerItm();
		$dlrPkgSh = $objLines->ShPerPkg();
		$qtyShp = $objLines->QtyShipped;

		$dlrSaleLine = $dlrSale * $qtyShp;
		$dlrItmShLine = $dlrItmSh * $qtyShp;

		$dlrSaleTot += $dlrSaleLine;
		$dlrItmShTot += $dlrItmShLine;
		if ($dlrPkgShTot < $dlrPkgSh) {
		    $dlrPkgShTot = $dlrPkgSh;
		}
		// record the charges applied for this line:
		$arUpd = array(
		  'CostSale' => $dlrSale,
		  'CostShItm' => $dlrItmSh);
		$objLines->Update($arUpd);

		// stats for logging
		$cntLines++;
		$cntQty += $qtyShp;
	    }
	    // create transaction records:
	    if ($cntQty == 1) {
		$txtItemShp = 'cost of item being shipped';
	    } else {
		$txtItemShp = 'total cost of items being shipped';
	    }
	    $this->AddTrx(KI_ORD_TXTYPE_ITEM_SOLD,$txtItemShp,$dlrSaleTot);
	    $this->AddTrx(KI_ORD_TXTYPE_PERITM_SH,'per-item shipping total',$dlrItmShTot);
	    $this->AddTrx(KI_ORD_TXTYPE_PERPKG_SH,'per-package shipping',$dlrPkgShTot);

	    // update package record:
	    $arUpd = array(
	      'ChgShipItm'	=> $dlrItmShTot,
	      'ChgShipPkg'	=> $dlrPkgShTot,
	      'WhenFinished' => 'NOW()');
	    $this->Update($arUpd);

	    // log completion of event:
	    $txtLog = 'sold: '
		.$cntLines.Pluralize($cntLines,' line').', '
		.$cntQty.Pluralize($cntQty,' item').': '
		.'$'.$dlrItmShTot.' itm s/h, $'.$dlrPkgShTot.' pkg s/h';
	    $out .= '<br>'.$txtLog;
	    $arUpd = array('descrfin' => $txtLog);
	    $this->FinishEvent($arUpd);
	} else {
	    $out = 'No items to charge!';
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-05-29 extracted from AdminPage(); will probably need some debugging
    */
    private function AdminUnCharge() {
	$out = '<h3>Removing charges...</h3>';

	$txtLog = 'removing charges for package '.$this->Number().' (ID='.$this->ID.')';
	$out .= $txtLog;

	$arEvent = array(
	  'descr'	=> $txtLog,
	  'where'	=> __METHOD__,
	  'code'	=> '-PCH');
	$this->StartEvent($arEvent);

	$arUpd = array('WhenVoid' => 'NOW()');
	$objTrx = $this->objDB->OrdTrxacts();
	$objTrx->Update($arUpd,'ID_Package='.$this->ID);

	$arUpd = array('WhenFinished' => 'NULL');
	$this->Update($arUpd);

	$this->FinishEvent();
	return $out;
    }
    /*----
      ACTION: Display the administration page for the package currently loaded in the dataset
      HISTORY:
	2011-12-20 fixed editing of new packages
    */
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;
	global $wgScriptPath;

	$vgPage->UseHTML();
	$isNew = FALSE;

	// do actions first, so we can redirect to actionless URL
	$strDo = $vgPage->Arg('do');
	$doFetch = ($strDo == 'fetch');


	$doRedir = FALSE;
	$out = NULL;

	$doStore = FALSE;
	if ($wgRequest->GetBool('btnUnfetch')) {
	    $idBin = $wgRequest->GetInt('bin');
	    if ($idBin == 0) {
		$out .= 'Please select a destination bin.';
	    } else {
		$out .= $this->Move_toBin($idBin);
		$doRedir = TRUE;
	    }
	}

	$hasCharges = !is_null($this->WhenFinished);
	switch ($strDo) {
	  case 'charge':
	    if ($hasCharges) {
		$out .= '<h3>Warning</h3>Attempting to add charges when charges have been added. Remove them first.';
	    } else {
		$out .= $this->AdminDoCharge();
		$doRedir = TRUE;
	    }
	    break;
	  case 'uncharge':
	    $out .= $this->AdminUnCharge();
	    $doRedir = TRUE;
	    break;
	  case 'unfetch':
	    $qty = $this->ItemQty();
	    $out .= '<form method=post>';
	    $out .= 'Return '.$qty.' item'.Pluralize($qty).' to bin ';
	    $out .= $this->Engine()->Bins()->DropDown_active();
	    $out .= '<input type=submit name=btnUnfetch value="Do It"></form>';
/* If a nicer form is actually needed, document the scenario.

This could probably be put closer to the original link, so it's easier to find...
...and it could also be highlighted somehow. Maybe we need an "action box" widget
to draw attention to incipient actions, i.e. controls that only appear when an action
is being set up.
*/
	    break;
	  case 'void':
	    $ar = array(
	      'descr'	=> 'voiding the package',
	      'code'	=> 'VOID',
	      'where'	=> __METHOD__,
	      );
	    $this->StartEvent($ar);
	    $ar = array('WhenVoided' => 'NOW()');
	    $this->Update($ar);
	    $this->FinishEvent();
	    $out .= 'Package voided.';
	    $doRedir = TRUE;
	    break;
	  case 'check-in':
	    $ar = array(
	      'descr'	=> 'package checked in to shipment',
	      'code'	=> 'CHK',
	      'where'	=> __METHOD__,
	      );
	    $this->StartEvent($ar);
	    $ar = array('WhenChecked' => 'NOW()');
	    $this->Update($ar);
	    $this->FinishEvent();
	    $out .= 'Package checked in.';
	    $doRedir = TRUE;
	    break;
	}
	$hasItems = $this->ContainsItems();

	// save/simulate changes:
	if ($wgRequest->getBool('btnSave')) {
	    $this->BuildEditForm(FALSE);
	    $this->AdminSave();		// save edit to existing package
	    $doRedir = TRUE;
	} elseif ($wgRequest->getBool('btnAdd')) {
	    $out .= $this->AdminCreate(TRUE);	// create new package from user input
	    $doRedir = TRUE;
	    $isNew = TRUE;
	} elseif ($wgRequest->getBool('btnSim')) {
	    $out .= $this->AdminCreate(FALSE);	// create new package from user input
	    $doRedir = TRUE;
	}

	// if any actions done, redirect to actionless URL:
	if ($doRedir) {
	    setcookie('action-msgs',$out,0,$this->AdminURL());
	    $this->AdminRedirect();
	} else {
	    if (array_key_exists('action-msgs',$_COOKIE)) {
		$out .= $_COOKIE['action-msgs'];
		setcookie('action-msgs',FALSE,0,$this->AdminURL());	// delete the cookie
	    }
	}
	$wgOut->addHTML($out); $out = '';
	$wgOut->addHTML('<br><b>Reports</b>: [<a href="'.$wgScriptPath.'/VBZHQ:Reports/packing slip?pkg='.$this->ID.'">packing slip</a>]');

	$doEdit = $vgPage->Arg('edit');
	//$doPrint = $vgPage->Arg('print');
	//$doAdd = ($strDo == 'add') || $vgPage->Arg('add');
	$doAdd = ($vgPage->Arg('id') == 'new');

	$doStockLookup = $doAdd || $doFetch;
	$doForm = $doAdd || $doFetch || $doEdit;

	if ($doForm) {
	    $this->BuildEditForm($doAdd);
	}
	$wgOut->addHTML($out); $out = '';

	// do the header, with edit link if appropriate
	$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	if ($doAdd) {
	    $doEdit = TRUE;	// new package must be edited before it can be created
	    $isNew = TRUE;

	    // use passed order number
	    $idOrd = $vgPage->Arg('order');
	    if (empty($idOrd)) {
		$idOrd = $wgRequest->GetInt('order');
	    }
	    $this->Value('ID_Order',$idOrd);

	    $objOrd = $this->OrderObj();
	    $strName = 'New package for order #'.$objOrd->Number;
	} else {
	    $id = $this->ID;
	    $isNew = empty($id);
	    $objOrd = $this->OrderObj();
	    $strName = 'Package '.$id.' - #'.$this->Number();
	}

	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection($objPage,$strName);
	if (!$isNew) {
	    $objSection->ToggleAdd('edit','edit the package record');
	    //$objSection->ToggleAdd('print','print a packing slip');
	}
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';

// calculate display markup
	if ($doForm) {
	    if ($isNew) {
		// idOrd set from URL param
		$idShip = NULL;
	    } else {
		$idOrd = $this->Value('ID_Order');
		$idShip = $this->Value('ID_Shipment');
	    }
	    $htShip = $this->objDB->Shipmts()->GetActive('WhenCreated DESC')->DropDown('shpmt',$idShip);
	    $arLink = array(
	      'edit'	=> FALSE,
	      'add'	=> FALSE,
	      'do'	=> FALSE,
	      'order'	=> FALSE
	      );
	    $htPath = $vgPage->SelfURL($arLink);
	    $out .= "\n<form method=post action=\"$htPath\">";
	    if ($doAdd) {
		$out .= "<input name=order type=hidden value=$idOrd>";
	    }
	} else {
	    $objShip = $this->ShipObj();
	    $idShip = $objShip->ID;
	    $arArgs = array('page'=>'shpmt','id'=>$idShip);
	    //$htShip = $vgPage->SelfLink($arArgs,$objShip->Descr(),'view shipment #'.$idShip);
	    $htShip = $objShip->AdminLink_name();
	}
	$arArgs = array('page'=>'shpmt','id'=>'new');
	$htShip .= ' [<a href="'.$vgPage->SelfURL($arArgs,TRUE).'">new</a>]';

	$out .= "\n<table>";

	// defaults
	$ctrlVoidNow = NULL;
	$ctrlCheckIn = NULL;

	//$htOrder = $objOrd->AdminLink($objOrd->Number);
	$htOrder = $objOrd->AdminLink_name();
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>$htOrder</td></tr>";
	if (!$isNew) {
	    // only display these for an existing package
	    $dtWhenStarted = $this->Value('WhenStarted');
	    $dtWhenFinished = $this->Value('WhenFinished');
	    $dtWhenChecked = $this->Value('WhenChecked');
	    $dtWhenVoided = $this->Value('WhenVoided');

	    if ($doEdit) {
		$objForm = $this->objForm;
		$ctrlWhenStarted = $objForm->Render('WhenStarted');
		$ctrlWhenFinished = $objForm->Render('WhenFinished');
		$ctrlWhenChecked = $objForm->Render('WhenChecked');
		$ctrlWhenVoided = $objForm->Render('WhenVoided');

		$arLink = $vgPage->Args(array('page','id'));
		if (!$this->IsVoid()) {
		    $arLink['do'] = 'void';
		    $ctrlVoidNow = ' ['.$vgOut->SelfLink($arLink,'void now', 'VOID the package (without saving edits)').']';
		}
		if (!$this->IsChecked()) {
		    $arLink['do'] = 'check-in';
		    $ctrlCheckIn = ' ['.$vgOut->SelfLink($arLink,'check in','mark the package as checked in (without saving edits)').']';
		}
	    } else {
		$ctrlWhenStarted = $dtWhenStarted;
		$ctrlWhenFinished = $dtWhenFinished;
		$ctrlWhenChecked = $dtWhenChecked;
		$ctrlWhenVoided = $dtWhenVoided;
	    }

	    $out .= "\n<tr><td align=right><b>When Started</b>:</td><td>".$ctrlWhenStarted.'</td></tr>';
	    $out .= "\n<tr><td align=right><b>When Finished</b>:</td><td>".$ctrlWhenFinished.'</td></tr>';
	    $out .= "\n<tr><td align=right><b>When Checked</b>:</td><td>".$ctrlWhenChecked.$ctrlCheckIn.'</td></tr>';
	    $out .= "\n<tr><td align=right><b>When Voided</b>:</td><td>".$ctrlWhenVoided.$ctrlVoidNow.'</td></tr>';
	}

	// display these for new and existing packages:
	$dlrChgShipItm = $this->ChgShipItm;
	$dlrChgShipPkg = $this->ChgShipPkg;
	$dlrCostShp = $this->ShipCost;
	$dlrCostPkg = $this->PkgCost;
	$intShPounds = $this->ShipPounds;
	$fltShOunces = $this->ShipOunces;
	$htNotes = htmlspecialchars($this->ShipNotes);
	$htTrack = htmlspecialchars($this->ShipTracking);
	$dtWhenArrived = $this->WhenArrived;
	if ($doEdit) {
	    $objForm = $this->objForm;
	    $ctrlChgShipItm	= '$'.$objForm->Render('ChgShipItm');
	    $ctrlChgShipPkg	= '$'.$objForm->Render('ChgShipPkg');
	    $ctrlCostShp	= '$'.$objForm->Render('ShipCost');
	    $ctrlCostPkg	= '$'.$objForm->Render('PkgCost');
	    $ctrlShPounds	= $objForm->Render('ShipPounds');
	    $ctrlShOunces	= $objForm->Render('ShipOunces');
	    $ctrlShWeight	= "$ctrlShPounds pounds $ctrlShOunces ounces";
	    $ctrlNotes		= $objForm->Render('ShipNotes');
	    $ctrlTrack		= $objForm->Render('ShipTracking');
	    $ctrlWhenArrived	= $objForm->Render('WhenArrived');
	} else {
	    $ctrlChgShipItm	= $dlrChgShipItm;
	    $ctrlChgShipPkg	= $dlrChgShipPkg;
	    $ctrlCostShp	= $dlrCostShp;
	    $ctrlCostPkg	= $dlrCostPkg;
	    $ctrlShWeight	=
	      (is_null($intShPounds)?'':"$intShPounds pounds")
	      .(is_null($fltShOunces)?'':" $fltShOunces ounces");
	    $ctrlNotes		= $htNotes;
	    $ctrlTrack		= $htTrack;
	    $ctrlWhenArrived	= $dtWhenArrived;
	}
	$out .= "\n<tr><td align=right><b>Per-item s/h total charged</b>:</td><td>$ctrlChgShipItm</td></tr>";
	$out .= "\n<tr><td align=right><b>Per-pkg s/h smount charged</b>:</td><td>$ctrlChgShipPkg</td></tr>";
	$out .= "\n<tr><td align=right><b>Shipment</b>:</td><td>".$htShip.'</td></tr>';
	$out .= "\n<tr><td align=right><b>Actual shipping cost</b>:</td><td>$ctrlCostShp</td></tr>";
	$out .= "\n<tr><td align=right><b>Actual package cost</b>:</td><td>$ctrlCostPkg</td></tr>";
	$out .= "\n<tr><td align=right><b>Actual shipping weight</b>:</td><td>$ctrlShWeight</td></tr>";
	$out .= "\n<tr><td align=right><b>Delivery tracking #</b>:</td><td>$ctrlTrack</td></tr>";
	$out .= "\n<tr><td colspan=2><b>Notes</b>: $ctrlNotes</td></tr>";
	$out .= "\n<tr><td align=right><b>When arrived</b>:</td><td>$ctrlWhenArrived</td></tr>";

	$out .= "\n</table>";

	if ($doForm) {
	    if ($doAdd || $doFetch) {
		if ($doFetch) {
		    $txtAdd = 'Fetch Items';
		} else {
		    $txtAdd = 'Create Package';
		}
		$out .= '<input type=hidden name=order value='.$this->ID_Order.'>';
	    } else {
		//$out .= '<input type=hidden name=id value="'.$this->ID.'">';
		$out .= '<input type=submit name="btnSave" value="Save">';
	    }
	}

	if ($doStockLookup) {
	    $out .= '<h3>Stock Items for Order</h3>';
	    $out .= $this->AdminStock();
	} else {
	    $arLink = $vgPage->Args(array('page','id'));
	    if ($hasItems) {
		$arLink['do'] = 'unfetch';
		$out .= '['.$vgOut->SelfLink($arLink,'put items back in stock').']';
	    }
	    $arLink['do'] = 'fetch';
	    $out .= '['.$vgOut->SelfLink($arLink,'find items in stock').']';
	    if ($hasCharges) {
		$arLink['do'] = 'uncharge';
		$out .= '['.$vgOut->SelfLink($arLink,'remove package charges').']';
	    } else {
		$arLink['do'] = 'charge';
		$out .= '['.$vgOut->SelfLink($arLink,'charge for package').']';
	    }
	}
	if ($doForm) {
	    if ($doAdd || $doFetch) {
		$out .= '<input type=submit name="btnSim" value="Simulate">';
		$out .= '<input type=submit name="btnAdd" value="'.$txtAdd.'">';
	    }
	    $out .= '</form><hr>';
	}

	$wgOut->AddHTML($out); $out = '';
	if (!$isNew) {
	    // new records have no ID yet, so no dependent records


	    //$wgOut->AddWikiText('===Contents===',TRUE);
	    $objSection = new clsWikiSection($objPage,'Contents','package contents',3);
	    $objSection->ToggleAdd('edit','edit the package contents','edit.lines');
	    $out = $objSection->Generate();
	    $wgOut->AddHTML($out); $out = '';
	    $objTbl = $this->objDB->PkgLines();
	    $objRows = $objTbl->GetData('ID_Pkg='.$this->ID);
	    $out = $objRows->AdminList();
	    $wgOut->AddHTML($out); $out='';

	    $arArgs = array(
	      //'add'		=> $vgPage->Arg('add'),
	      //'form'	=> $vgPage->Arg('form'),
	      'descr'	=> ' for this package',
	      );

	    // transactions
	    $wgOut->addWikiText('===Transactions===',TRUE);
	    $out = $this->TrxListing($arArgs);
	    $vgOut->AddText($out);	$out = '';

	    // events
	    $wgOut->addWikiText('===Events===',TRUE);
	    $out = $this->EventListing();
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	}
    }
    public function TrxData() {
	$objTable = $this->objDB->OrdTrxacts();
	$objRows = $objTable->GetData('ID_Package='.$this->ID);
	return $objRows;
    }
    public function TrxListing($iArgs=NULL) {
	$objRows = $this->TrxData();
	return $objRows->AdminTable($iArgs);
    }
    /*-----
      ACTION: Show what will go into the package, and the existing stock from which it can be filled
    */
    private function AdminStock() {
	// collect data to display:
	$objOrd = $this->OrderObj();
	$objLines = $objOrd->Lines();
	$objPkgs = $objOrd->Pkgs();
	$arOrd = $objOrd->QtysOrdered();
	$arSums = $objPkgs->FigureTotals();

	// display it:
	if (is_array($arOrd)) {
	    $tblStk = $this->objDB->StkItems();

	    $out = "\n<table>";
/* This is what a regular package listing would show, not what we need for "stock to fill ordered items". Save in case needed.
	    $out = '<tr><td colspan=3></td><th colspan=5>Quantities</th></tr>';
	    $out .= '<tr><th>Cat #</th><th>Description</th><th>ord</th><th>shp</th><th>ext</th><th>n/a</th><th>Xed</th></tr>';
*/
/* This is accurate, but I'm trying a more descriptive approach, since there's plenty of line space...
	    $out = '<tr><th>Cat #</th><th>Description</th><th>qty ord</th></tr>';
	    $out .= '<tr><td></td><th><i>bin</i></th><th><i>has qty</i></th><th><i>use qty</i></tr>';
*/
	    foreach ($arOrd as $id => $qty) {
		$objItem = $this->objDB->Items()->GetItem($id);
		//$strLinks = '['.$objItem->AdminLink_HT('admin').']['.$objItem->StoreLink_HT('shop').'] ';
		$qtyOrd = $arOrd[$id];
		$ouLine =
		  '<td>'.$objItem->AdminLink($objItem->CatNum).'</b></td>'.
		  '<td>'.$objItem->StoreLink_HT($objItem->FullDescr()).'</td>'.
		  '<td align=right><b>'.$qtyOrd.'</b> ordered</td>';

		$out .= "\n<tr>".$ouLine.'</tr>';
		// find stock lines for this item
		$objStk = $tblStk->List_ForItem($id);
		if ($objStk->HasRows()) {
		    while ($objStk->NextRow()) {
			$idStk = $objStk->KeyValue();
			$objBin = $objStk->Bin();
			$htStk = $objBin->AdminLink_name().' ('.$objStk->Value('WhName').')';
			$out .= "\n<tr>"
			  .'<td></td><td>'.$htStk.'</td>'
			  .'<td align=right>has <b>'.$objStk->Value('QtyForShip').'</b></i></td>'
			  .'<td>&ndash; use <input name="qty['.$idStk.']" size=2> from here</td>'
			  .'</tr>';
		    }
		} else {
		    $out .= "\n<tr>"
		      .'<td></td><td><i>No stock found</i></td></tr>';
		}
	    }
	    $out .= '</table>';
	} else {
	    $out = 'No items in the order.';
	}
	return $out;
    }
    /*-----
      ACTION: Add up totals for the packages in the dataset.
      RETURNS: Array containing stats for each item in the order
      FORMAT: See AddToSum()
      PURPOSE: Figures out the status of each item in an order (how many remaining to ship, etc.)
    */
    public function FigureTotals() {
	$arSum = NULL;
	if ($this->HasRows()) {
	    $intNew = 0;
	    $intUpd = 0;
	    while ($this->NextRow()) {
		$this->AddToSum($arSum);
	    }
	    return $arSum;
	} else {
	    return NULL;
	}
    }
    /*-----
      ACTION: Adds this package's shipment stats to the $arSum array
      FORMAT: $arSum[item ID][qty-shp|qty-ext|qty-kld|qty-na]
    */
    private function AddToSum(&$arSum) {
	$objRows = $this->LinesData();
	if ($objRows->HasRows()) {
	    while ($objRows->NextRow()) {
		$objRows->AddToSum($arSum);
	    }
	}
    }
    /*-----
      ACTION: Fill the package from user input, creating it if needed
      INPUT: from HTML form
	qty[x]: quantity to use from stock line ID=x
      TO DO: Rename the function AdminFill()
    */
    private function AdminCreate($iReally) {
	global $wgRequest;
	global $sql;	// used in assert() code

	// get order contents and create lookup array
	$idOrder = $wgRequest->getInt('order');
	$idShip = $wgRequest->getInt('shpmt');
//	$idPkg = $wgRequest->getIntOrNull('id');
	$idPkg = $this->ID;
	$this->ID_Order = $idOrder;
	$objRows = $this->OrderObj()->Lines();
	if ($objRows->HasRows()) {
	    while ($objRows->NextRow()) {
		$idRow = $objRows->ID;
		$idItem = $objRows->ID_Item;
		//$arRows[$idRow] = $idItem;
// 2010-10-19 this is apparently still under construction
	    }
	} else {
	    // TO DO: log internal error
	    exit;
	}

	// sum quantities to put into each pkg line
	$qtyStk = $wgRequest->getArray('qty');
	$objStkLines = $this->objDB->StkItems();
	$objStkBins = $this->objDB->Bins();
	if (is_array($qtyStk)) {
	    foreach ($qtyStk as $idStkLine => $qty) {
		// look up item ID for each stock line being used:
		$objStkLine = $objStkLines->GetItem($idStkLine);
		$idItem = $objStkLine->Value('ID_Item');
		// look up requested quantities and index by package row

		// build list of quantities for package
		nzAdd($arItQty[$idItem],$qty);
		// build list of what to pull
		$arStkQty[$idItem][$idStkLine] = $qty;
		// descriptive text of what is being done for this item
		if (isset($arItTxt[$idItem])) {
		    $arItTxt[$idItem] .= ', ';
		} else {
		    $arItTxt[$idItem] = '';
		}
		$objStkBin = $objStkBins->GetItem($objStkLine->Value('ID_Bin'));
		$arItTxt[$idItem] .= '<b>'.$qty.'</b> from '.$objStkBin->AdminLink($objStkBin->Value('Code'));
	    }
	} else {
	    return 'Internal error: qtyStk is not an array.<pre>'.print_r($qtyStk,TRUE).'</pre>';
	}

// TO DO: put in code to make sure we aren't pulling more than requested for each line. Don't create pkg if we are.

	$out = '';
	// show what is being pulled from where into each line
	$objItems = $this->objDB->Items();
	$txtHdr = $iReally?'Pulling Items for Package':'Items to be Pulled for Package';
	$out .= "\n<h2>$txtHdr</h2>\n<ul>";
	foreach ($arItQty as $idItem => $qty) {
	    $objItem = $objItems->GetItem($idItem);
	    $strCatNum = $objItem->CatNum;
	    $out .= '<li>'.$objItem->AdminLink($strCatNum).' (need <b>'.$qty.'</b>): ';
	    $out .= $arItTxt[$idItem];
	}
	$out .= '</ul>';

	if ($iReally) {
	// actually make changes to the database

	    $objOrd = $this->OrderObj();

	    if (is_null($idPkg)) {
		// package ID not set, so create it:
		$seq = $objOrd->NextPkgSeq();
		// log start of creation
		$arEv = array(
		  'descr' => 'new package #'.$seq.' for order #'.$objOrd->Number,
		  'where' => __METHOD__,
		  'code'  => 'NEW'
		  );
		$this->StartEvent($arEv);

		// create the package
		$arIns = array(
		  'Seq'		=> $seq,
		  'ID_Order'	=> $this->ID_Order,
		  'WhenStarted'	=> 'NOW()',
		  'ID_Shipment'	=> $idShip
		  );
		$this->Table->Insert($arIns);
		$id = $this->Table->LastID();
		$this->ID = $id;
	    }

	    // add the lines
	    $strDescr = 'add items';
	    if (!is_null($idPkg)) {
		$strDescr .= ' to package '.$this->Code;
	    }
	    $arEv = array(
	      'descr' => $strDescr,
	      'where' => __METHOD__,
	      'code'  => '+IT'
	      );
	    $this->StartEvent($arEv);
	    foreach ($arStkQty as $idItem => $arLineQty) {

		// find order line for this item
		$idOLine = $objOrd->LineForItem($idItem);
		assert('!is_null($idOLine); /* idItem='.$idItem.' SQL=['.$sql.'] */');

		// create the package line but leave qty blank for now
		$idPLine = $this->AddLine($idItem,$idOLine);
		assert('!is_null($idPLine); /* idItem='.$idItem.' idOLine='.$idOLine.' SQL=['.$sql.'] */');
		$objPLine = $this->objDB->PkgLines()->GetItem($idPLine);
		assert('is_object($objPLine); /* idPLine='.$idPLine.' SQL=['.$sql.'] */');

		$qtyItem = 0;
		foreach ($arLineQty as $idLine => $qty) {
		    $objSLine = $objStkLines->GetItem($idLine);
		    // pull qty from stock line idLine
		    $qtyDone = $objSLine->MoveToPkg(
		      $this->ID,
		      $idPLine,
		      'removing item for package',
		      $qty
		      );

		    // add to total for this item
		    $qtyItem += $qtyDone;
		}
		$arUpd = array(
		  'QtyShipped'		=> $qtyItem,
		  'QtyFromStock'	=> $qtyItem
		  );
		$objPLine->Update($arUpd);
	    }
	    if (is_null($idPkg)) {
		$this->FinishEvent();
	    }
	    $this->Reload();
	}

	return $out;
    }
    /*----
      ACTION: Display the packages listed in the dataset, for inclusion within an admin page
      INPUT: iArgs[]
	descr: description of dataset ("Packages_" e.g. " for this shipment")
	omit: columns to omit from table (currently supported: 'ship', 'ord')
	add: NOT USED
	order: ID of order - needs to be set if you want the ability to add packages
    */
    public function AdminTable(array $iArgs) {
	global $vgPage;

	$vgPage->UseWiki();

	//$doAdd = (nz($iArgs['add']) == 'pkg');
	$strOmit = nz($iArgs['omit']);
	$doShip = ($strOmit != 'ship');
	$doOrd =  ($strOmit != 'ord');

// This is needed for the "add package" link
	if (isset($iArgs['order'])) {
	    $idOrder = $iArgs['order'];
	} else {
	    $idOrder = $this->ID_Order;
	}

	if ($this->hasRows()) {
	    $out = "\n{| class=sortable \n|-\n";
	      $out .= '! ID ';
	      $out .= '|| Seq ';
	      $out .= '|| Started ';
	      $out .= '|| R? ';
	      if ($doShip) {
		$out .= '|| Shipment ';
	      }
	      $out .= '|| chg s/h $ ';
	      $out .= '|| act s/h $ ';
	      $out .= '|| notes';

	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$cssRowStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		if (!$this->IsActive()) {
		    $cssRowStyle .= ' text-decoration:line-through; color: silver;';
		}

		$row = $this->Row;

		$id		= $row['ID'];
		//$wtID		= SelfLink_WT(array('page'=>'pkg','id'=>$id),$id);
		$wtID		= $this->AdminLink();
		$strSeq		= $row['Seq'];
		if ($doOrd) {
		    $objOrd = $this->OrderObj();
		    $wtOrd = $objOrd->Number;	// TO BE FINISHED
		}
		$dtWhenStarted	= $row['WhenStarted'];
		$htStatus	= $row['isReturn']?'R':'';
		if ($doShip) {
		    $idShip		= $row['ID_Shipment'];
		    $objShip		= $this->objDB->Shipmts()->GetItem($idShip);
		    $wtShip		= $objShip->AdminLink($objShip->Abbr);
		}

		$strChgSh	= is_null($row['ChgShipItm'])?'':FormatMoney($row['ChgShipItm']);
		$crChgPkg = $row['ChgShipPkg'];
		if ($crChgPkg != 0) {
		    $strChgSh .= "''+$crChgPkg''";
		}
		$strActSh	= is_null($row['ShipCost'])?'':FormatMoney($row['ShipCost']);
		$crActPkg = $row['PkgCost'];
		if ($crActPkg != 0) {
		    $strActSh .= "''+$crActPkg''";
		}

		$strNotes = $row['ShipNotes'];

		$out .= "\n|- style=\"$cssRowStyle\"";
		$out .= "\n| $wtID || $strSeq || $dtWhenStarted || $htStatus ";
		if ($doShip) {
		    $out .= "|| $wtShip ";
		}
		$out .= "|| $strChgSh || $strActSh || $strNotes ";
	    }
	    $out .= "\n|}";
	    $strAdd = 'Add a new package';
	} else {
	    $strDescr = nz($iArgs['descr']);
	    $out = "\nNo packages".$strDescr.'. ';
	    $strAdd = 'Create one';
	}
	if (!empty($idOrder)) {
	    // if Order ID is known, it may be useful to be able to create a package here:
//	    $out = "'''Internal error''': order ID is not being set in ".__METHOD__;
//	} else {
	    $arLink = array(
	      'page'	=> 'pkg',
	      'order'	=> $idOrder,
	      'id'	=> 'new',
	      'show'	=>FALSE
	      );
	    $out .= $vgPage->SelfLink($arLink,$strAdd);
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-10-20 added event logging using helper class
    */
/*
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
*/
}
// package lines
class clsPkgLines extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_pkg_lines');
	  $this->KeyName('ID');
	  $this->ClassSng('clsPkgLine');
    }
}
class clsPkgLine extends clsDataSet {
    protected $objForm;	// must be declared to prevent overwriting when recordset advances

    /*====
      BOILERPLATE: self-linking
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }

    // FIELDS

    public function IsActive() {
	return is_null($this->Value('WhenVoided'));
    }

    // DEPRECATED - use ItemObj()
    public function Item() {
	return $this->ItemObj();
    }
    protected $objItem, $idItem;
    public function ItemObj() {
	$id = $this->Value('ID_Item');
	$doLoad = TRUE;
	if (!empty($this->idItem)) {
	    if ($this->idItem == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objItem = $this->objDB->Items()->GetItem($id);
	    $this->idItem = $id;
	}
	return $this->objItem;
    }
    /*----
      HISTORY:
	2011-10-08 created for unpacking packages, but seems generally useful
    */
    protected $idPkg, $objPkg;
    public function PkgObj() {
	$id = $this->Value('ID_Pkg');

	$doLoad = TRUE;
	if (!empty($this->idPkg)) {
	    if ($this->idPkg == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objPkg = $this->objDB->Pkgs()->GetItem($id);
	    $this->idPkg = $id;
	}
	return $this->objPkg;
    }
    /*----
      RETURNS: The quantity of items actually present in the package for the current line
	This is currently defined as QtyShipped, but that could change.
      HISTORY:
	2011-10-08 created for returning items to stock
    */
    public function ItemQty() {
	return $this->Value('QtyShipped');
    }
    /*----
      ACTION: Updates the quantity in the package.
	Does not adjust quantities elsewhere; assumes caller is taking care of that.
	For now, does not log an event. Add later.
      HISTORY:
	2011-10-08 written for package return
    */
    public function DoVoid($iDescr) {
	$rcPkg = $this->PkgObj();
	$ar = array(
	  'descr'	=> SQLValue('Voiding line: '.$iDescr),
	  'code'	=> 'VOID',
	  'where'	=> __METHOD__,
	  );
	$rcPkg->StartEvent($ar);

	$ar = array(
	  'WhenVoided'	=> 'NOW()',
	  );
	$this->Update($ar);

	$rcPkg->FinishEvent();
    }
    /*----
      RETURNS: object for order line corresponding to this package line
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    protected $objOrdLine, $idOrdLine;
    public function OrdLineObj() {
	$id = $this->Value('ID_OrdLine');
	$doLoad = TRUE;
	if (!empty($this->idOrdLine)) {
	    if ($this->idOrdLine == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objOrdLine = $this->objDB->OrdLines()->GetItem($id);
	    $this->idOrdLine = $id;
	}
	return $this->objOrdLine;
    }
    /*----
      RETURNS: shipping cost object for this package line
      RULES: prices stored in the line record override the shipping cost object,
	  which is why this method is not public.
    */
/* NO NO, THIS IS SILLY AND UNNECESSARY. LET THE ITEM OBJ DO THE CACHING.
    protected $idSh, $objSh;
    protected function ShipCostObj() {
	$objItem = $this->ItemObj();
	$objSh = $objItem->ShipCostObj();
	$doLoad = TRUE;
	if (!empty($this->idSh)) {
	    $idSh = $objSh->Value('ID');
	    if ($idSh == $this->idSh) {
		$doLoad = FALSE;	// object we need has been loaded already
	    }
	}

	if ($doLoad) {
	    $this->objSh = $objSh;
	    $this->idSh = $objSh->Value('ID');
	}

	return $this->objSh;
    }
*/
    /*----
      RETURNS: selling price
	if package line has no price, falls back to order line
	(if order line has no price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function PriceSell() {
	$prc = $this->Value('CostSale');
	if (is_null($prc)) {
	    $prc = $this->OrdLineObj()->PriceSell();
	}
	return $prc;
    }
    /*----
      RETURNS: shipping per-package price
	currently, package line does not store a per-package price, so falls back to order item
	(if order line has no per-item price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerPkg() {
	return $this->OrdLineObj()->ShPerPkg();
    }
    /*----
      RETURNS: shipping per-item price
	if package line has no per-item price, falls back to order line
	(if order line has no per-item price, falls back to catalog item)
      HISTORY:
	2011-03-23 created for "charge for package" process
    */
    public function ShPerItm() {
	$prc = $this->Value('CostShItm');
	if (is_null($prc)) {
	    $prc = $this->OrdLineObj()->ShPerItm();
	}
	return $prc;
    }

    // ACTIONS
    public function AddToSum(&$arSum) {
	$idItem = $this->ID_Item;
	$row = $this->Row;
	if (is_null($row['WhenVoided'])) {
	    nzAdd($arSum[$idItem]['qty-shp'], $row['QtyShipped']);
	    nzAdd($arSum[$idItem]['qty-ext'], $row['QtyExtra']);
	    nzAdd($arSum[$idItem]['qty-kld'], $row['QtyKilled']);
	    nzAdd($arSum[$idItem]['qty-na'], $row['QtyNotAvail']);
	}
    }
    public function AdminList() {
	global $wgRequest;
	global $vgPage;

	$vgPage->UseHTML();

	$out = NULL;

	$doEdit = $vgPage->Arg('edit.lines');
	$doSave = $wgRequest->GetBool('btnSaveItems');
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();

	    if ($doSave) {
		//$sqlLoad = $this->sqlCreate;
		$ftSaveStatus = $this->AdminSave();
		$this->Reload();	// 2011-02-21 not tested
		//$this->Query($sqlLoad);
		$out .= $ftSaveStatus;
		$didEdit = TRUE;
	    }

	    if ($doEdit) {
		$arLink = $vgPage->Args(array('page','id'));
		$urlForm = $vgPage->SelfURL($arLink,TRUE);
		$out .= '<form method=POST action="'.$urlForm.'">';
	    }
	}
	$isEdit = FALSE;	// set if there is anything to save or revert

	if ($this->hasRows()) {
	    $out .= "\n<table border=1>";
	    $out .= "\n<tr>"
	      .'<th>ID</th>'
	      .'<th>Line ID</th>'
	      .'<th>Item</th>'
	      .'<th>$ sale</th>'
	      .'<th>$ sh itm</th>'
	      .'<th># ship</th>'
	      .'<th># xtra</th>'
	      .'<th># cnc</th>'
	      .'<th># n/a</th>'
	      .'<th># fr stk</th>'
	      .'<th>notes</th>'
	      .'</tr>';

	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		//$ftStyle = '"'.($isOdd?'background:#ffffff;':'background:#eeeeee;').'"';
		$out .= $this->AdminListRow($doEdit,$isOdd);
		$isOdd = !$isOdd;
	    }
	    if ($doEdit) {
		// when editing, show extra row for adding a new record
		$out .= $this->AdminListRow($doEdit,$isOdd,TRUE);
	    }
	    $out .= "\n</table>";
	    $out .= "<small><b>cnc</b> = cancelled | <b>fr stk</b> = taken directly from stock</small>";
	    if ($doEdit) {
	    // form buttons
		$out .= '<br><input type=submit name="btnSaveItems" value="Save">';
		$isEdit = TRUE;
	    }
	} else {
	    $out = 'No items recorded in package.';
	}
	if ($doEdit) {
	// close editing form
	    if ($isEdit) {
		$out .= '<input type=reset value="Revert">';
	    }
	    $out .= "\n</form>";
	}
	return $out;
	return $out;
    }
    /*----
      HISTORY:
	2011-02-19 Created
    */
    protected function AdminListRow($iEdit,$iOdd,$iNew=FALSE) {
	$isOdd = $iOdd;
	$doEdit = $iEdit;

	$out = NULL;

	// get current field values (if any)
	if ($iNew) {
	    $doEdit = TRUE;

	    $isActive = TRUE;
	    $id = 'new';
	    $idLine	= NULL;
	    $idItem	= NULL;
	    $ftItem	= 'new';
	    $prcSale	= NULL;
	    $prcShItm	= NULL;
	    $qtySh	= NULL;
	    $qtyXt	= NULL;
	    $qtyCa	= NULL;
	    $qtyNA	= NULL;
	    $qtySt	= NULL;
	    $txtNotes	= NULL;
	} else {
	    $row = $this->Row;

	    $objItem = $this->ItemObj();

	    $id		= $row['ID'];
	    $idLine	= $row['ID_OrdLine'];
	    $idItem	= $row['ID_Item'];
	    //$objItem	= $this->objDB->Items()->GetItem($idItem);
	    //$wtItem	= SelfLink_WT(array('page'=>'item','id'=>$idItem),$strItem).' '.$objItem->FullDescr();
	    $prcSale	= $row['CostSale'];
	    $prcShItm	= $row['CostShItm'];
	    $qtySh	= $row['QtyShipped'];
	    $qtyXt	= $row['QtyExtra'];
	    $qtyCa	= $row['QtyKilled'];
	    $qtyNA	= $row['QtyNotAvail'];
	    $qtySt	= $row['QtyFromStock'];
	    $txtNotes	= $row['Notes'];
	}
	$cssRowStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
	if (!$this->IsActive()) {
	    $cssRowStyle .= ' text-decoration:line-through; color: silver;';
	}
	$ftRowStyle = '"'.$cssRowStyle.'"';

	if ($doEdit) {
	    $objForm = $this->objForm;
	    $out .= $objForm->RowPrefix();

	    // replace field values with editable versions
	    $ctLine = $objForm->Render('ID_OrdLine');
	    $ctItem = $objForm->Render('ID_Item');
	    $ctSale = $objForm->Render('CostSale');
	    $ctShItm = $objForm->Render('CostShItm');
	    $ctQtySh = $objForm->Render('QtyShipped');
	    $ctQtyXt = $objForm->Render('QtyExtra');
	    $ctQtyCa = $objForm->Render('QtyKilled');
	    $ctQtyNA = $objForm->Render('QtyNotAvail');
	    $ctQtySt = $objForm->Render('QtyFromStock');
	    $ctNotes = $objForm->Render('Notes');
	} else {
	    $strItem	= $objItem->CatNum;
	    $ftItem	= $objItem->AdminLink($strItem).' '.$objItem->FullDescr();

	    $ctLine = $idLine;
	    $ctItem = $ftItem;
	    $ctSale = $prcSale;
	    $ctShItm = $prcShItm;
	    $ctQtySh = $qtySh;
	    $ctQtyXt = $qtyXt;
	    $ctQtyCa = $qtyCa;
	    $ctQtyNA = $qtyNA;
	    $ctQtySt = $qtySt;
	    $ctNotes = $txtNotes;
	}

	// render line
	$out .= "\n<tr style=$ftRowStyle>"
	  ."<td>$id</td><td>$ctLine</td><td>$ctItem</td>"
	  ."<td align=center>$ctSale</td>"
	  ."<td align=center>$ctShItm</td>"
	  ."<td align=center>$ctQtySh</td>"
	  ."<td align=center>$ctQtyXt</td>"
	  ."<td align=center>$ctQtyCa</td>"
	  ."<td align=center>$ctQtyNA</td>"
	  ."<td align=center>$ctQtySt</td>"
	  ."<td>$ctNotes</td>"
	  .'</tr>';
	return $out;
    }
    /*----
      HISTORY:
	2011-02-20 Created
    */
    private function BuildEditForm() {
	global $vgOut;

	// create fields & controls

	if (is_null($this->objCtrls)) {
	    $objForm = new clsForm_DataSet_indexed($this,$vgOut);
/*
	    $objFlds = new clsFields($this->Row);
	    $objCtrls = new clsCtrls($objFlds);
*/
	    $objForm->AddField(new clsFieldNum('ID_OrdLine'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ID_Item'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('CostSale'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('CostShItm'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('QtyShipped'),	new clsCtrlHTML(array('size'=>1)));
	    $objForm->AddField(new clsFieldNum('QtyExtra'),	new clsCtrlHTML(array('size'=>1)));
	    $objForm->AddField(new clsFieldNum('QtyKilled'),	new clsCtrlHTML(array('size'=>1)));
	    $objForm->AddField(new clsFieldNum('QtyNotAvail'),	new clsCtrlHTML(array('size'=>1)));
	    $objForm->AddField(new clsFieldNum('QtyFromStock'),	new clsCtrlHTML(array('size'=>1)));
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML(array('size'=>20)));

	    $this->objForm = $objForm;
	}
    }
    protected function AdminSave() {
	$out = $this->objForm->Save();
	return $out;
    }
}