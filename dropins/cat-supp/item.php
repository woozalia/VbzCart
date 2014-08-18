<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
/*====
  CLASS: Catalog Management Items
*/
class VCTA_SCItems extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ctg_items');
	  $this->KeyName('ID');
	  $this->ActionKey('cmi');
	  $this->ClassSng('VCRA_SCItem');
    }
    public function Data_forGroup($idGroup) {
	return $this->GetData('ID_Group='.$idGroup,NULL,'Sort');
    }
}
class VCRA_SCItem extends clsDataSet {
    protected $objForm;

    /*%%%%
      SECTION: BOILERPLATE admin HTML
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-09-24 copied from VbzAdminOrderTrxact to clsShipment
	  ...and then to clsCMItem
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    // /boilerplate

    /*%%%%
      SECTION: EXTENSIONS to boilerplate admin
    */
    public function AdminLink_descr() {
	$out = $this->AdminLink($this->Descr);
	if (!$this->IsActive()) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }

    /*%%%%
      SECTION: data access
    */

    public function GroupObj() {
	$id = $this->Value('ID_Group');
	$rc = $this->Engine()->CtgGrps($id);
	return $rc;
    }
    public function ItTypObj() {
	$id = $this->Value('ID_ItTyp');
	$rc = $this->Engine()->ItTyps($id);
	return $rc;
    }
    public function ItOptObj() {
	$id = $this->Value('ID_ItOpt');
	$rc = $this->Engine()->ItOpts($id);
	return $rc;
    }
    public function ShCostObj() {
	$id = $this->Value('ID_ShipCost');
	$rc = $this->Engine()->ShipCosts($id);
	return $rc;
    }

    /*%%%%
      SECTION: ADMIN code
    */
/* *sigh* this actually isn't needed... the Group page lets you edit by row.
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	//$strAction = $vgPage->Arg('do');
	//$doAdd = ($strAction == 'add');
	$isNew = is_null($this->ID);
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');

	if ($isNew) {
	    $strName = 'New Group Item';
	} else {
	    $strName = 'Group Item: '.$this->Value('Descr');
	}

	$objPage = new clsWikiFormatter($vgPage);
	$vgPage->UseHTML();
	$out = NULL;

	$objSection = new clsWikiSection_std_page($objPage,'Group Item record');
	$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));
	$out .= $objSection->Render();

	if ($doEdit || $doSave) {
	    if ($isNew) {
		$idGrp = $vgPage->Arg('grp');
		$arNew = array('ID_Group' => $idGrp);
	    } else {
		$arNew = array();	// new record data not needed
	    }

	    $this->BuildEditForm($arNew);
	    if ($doSave) {
		$this->AdminSave();
		$this->AdminRedirect();
	    }
	}
	if ($doEdit) {
	    $out .= '<form>';
	    $objForm = $this->objForm;

	    $htAct = $objForm->Render('isActive');
	    $htDesc = $objForm->Render('Descr');
	    $htSort = $objForm->Render('Sort');
	    $htPrcBuy = $objForm->Render('PriceBuy');
	    $htPrcSell = $objForm->Render('PriceSell');
	    $htPrcList = $objForm->Render('PriceList');
	} else {
	    $htAct = NoYes($this->Value('isActive'));
	    $htDesc = htmlspecialchars($this->Value('Descr'));
	    $htSort = htmlspecialchars($this->Value('Sort'));
	    $htPrcBuy = DataCurr($this->Value('PriceBuy'),'');
	    $htPrcSell = DataCurr($this->Value('PriceSell'),'');
	    $htPrcList = DataCurr($this->Value('PriceList'),'');
	}
	// non-editable fields:
	$htID = $this->ID;
	$rcGrp = $this->GroupObj();
	$rcItt = $this->ItTypObj();
	$rcOpt = $this->ItOptObj();
	$rcShp = $this->ShCostObj();
	$htGrp = $rcGrp->AdminLink_name();
	//$htItt = $rcItt->AdminLink_name();
	$htItt = $rcItt->Name();
	//$htOpt = $rcOpt->AdminLink_name();
	$htOpt = $rcOpt->DescrFull();

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>";
	$out .= "\n<tr><td align=right><b>Active</b>:</td><td>$htAct</td></tr>";
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>$htDesc</td></tr>";
	$out .= "\n<tr><td align=right><b>Sorting</b>:</td><td>$htSort</td></tr>";
	$out .= "\n<tr><td align=right><b>Group</b>:</td><td>$htGrp</td></tr>";
	$out .= "\n<tr><td align=right><b>Item Type</b>:</td><td>$htItt</td></tr>";
	$out .= "\n<tr><td align=right><b>Item Option</b>:</td><td>$htOpt</td></tr>";
	$out .= "\n<tr><td align=right><b>Purchase Price</b>: $</td><td>$htPrcBuy</td></tr>";
	$out .= "\n<tr><td align=right><b>Selling Price</b>: $</td><td>$htPrcSell</td></tr>";
	$out .= "\n<tr><td align=right><b>List Price</b>:$</td><td>$htPrcList</td></tr>";
	$out .= '</table>';

	if ($doEdit) {
	    if ($isNew) {
		$out .= '<input type=submit name="btnSave" value="Create">';
	    } else {
		$out .= '<input type=submit name="btnSave" value="Save">';
	    }
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out);
    }
*/
    /*----
      INPUT:
	iContext: array of values that apply to all rows (e.g. for creating new records)
	  iContext[key] = value
      FUTURE: This function should also display its own header, so we can have all the action-link naming in one place
      2012-03-08 we DO need ID_Supplier in the Context, in order to show the list of groups to copy from --
	but we have to strip it out before passing it on to the row editor
    */
    public function AdminRows(array $iContext, clsLogger_DataSet $iLogger=NULL) {
	global $wgRequest;
	global $vgPage,$vgOut;

	// get URL input
	$doEdit = ($vgPage->Arg('edit.items'));
	$doSave = $wgRequest->getBool('btnSaveItems');
	$doCopy = $wgRequest->getBool('btnCopyFrom');

	// handle edit form input:
	if ($doSave) {
	    $arUpdate = $wgRequest->getArray('update');
	    $arActive = $wgRequest->getArray('isActive');

	    if (count($arActive > 0)) {
		// add any reactivated rows to the update list
		foreach ($arActive as $id => $null) {
		    $arUpdate[$id] = TRUE;
		}
	    }
	}

	$out = NULL;
	$didEdit = FALSE;
	$sqlMake = $this->sqlMake;

	// handle copying request
	if ($doCopy) {
	    $idGrp = $wgRequest->GetIntOrNull('group_model');
	    $rsItems = $this->Table->Data_forGroup($idGrp);
	    if ($rsItems->HasRows()) {
		$objGrp = $this->Engine()->CtgGrps($idGrp);
		$out .= 'Copying from group ['.$objGrp->AdminLink_friendly().']: ';
		if (!is_null($iLogger)) {
		    $arEv = array(
		      'descr'	=> 'Copying rows from group ID='.$idGrp,
		      'code'	=> 'CPY',
		      'params'	=> '\group='.$idGrp,
		      'where'	=> __METHOD__
		      );
		    $iLogger->StartEvent($arEv);
		}
		$rc=0; $rtxt='';
		$strKeyName = $rsItems->Table->KeyName();
		while ($rsItems->NextRow()) {
		    $rc++; $rtxt.='['.$rsItems->KeyValue();
		    $arRow = $rsItems->Values();
		    // unset row key
		    unset($arRow[$strKeyName]);
		    // overwrite any default values from context
		    foreach ($iContext as $key => $val) {
			if (array_key_exists($key,$arRow)) {
			    $arRow[$key] = $val;
			}
		    }
		    // build insert array by iterating through row's fields
		    foreach ($arRow as $key => $val) {
			$arIns[$key] = SQLValue($val);
		    }
		    // do the update
		    $this->Table->Insert($arIns);
		    $out .= '<br><b>SQL</b>: '.$this->Table->sqlExec;
		    $idNew = $this->Table->LastID();
		    $rtxt .= '->'.$idNew.']';
		}
		$txtDescr = $rc.' item'.Pluralize($rc).' copied:'.$rtxt;
		$out .= $txtDescr;
		if (!is_null($iLogger)) {
		    $arEv = array(
		      'descrfin'	=> $txtDescr
		      );
		    $iLogger->FinishEvent($arEv);
		}
	    }
	    $didEdit = TRUE;
	}

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $oContext = $iContext;
	    unset($oContext['ID_Supplier']);
	    $this->BuildEditForm($iContext);
	    if ($doSave) {
		$sqlLoad = $this->sqlCreate;
		$ftSaveStatus = $this->AdminSave();
		//$this->Query($sqlLoad);
		$out .= $ftSaveStatus;
		$didEdit = TRUE;
	    }
	}
	if ($didEdit) {
	    $this->sqlMake = $sqlMake;
	    $this->Reload();
	    $this->StartRows();	// make sure no rows got skipped
	}

	$isEdit = FALSE;	// set if there is anything to save or revert

	$arLink = $vgPage->Args(array('page','id'));
	$urlForm = $vgPage->SelfURL($arLink,TRUE);
	$out .= '<form method=POST action="'.$urlForm.'">';

	// display rows
	$isOdd = TRUE;
	$out .= $vgOut->TableOpen();
	$htAfter = NULL;
	if ($this->HasRows()) {
	    $out .= $vgOut->TblRowOpen(NULL,TRUE);
	      $out .= $vgOut->TblCell('ID');
	      $out .= $vgOut->TblCell('<span title="active?">A?</a>');
	      $out .= $vgOut->TblCell('<span title="sorting order">S</span>');
	      $out .= $vgOut->TblCell('Item Type');
	      $out .= $vgOut->TblCell('Item Option');
	      $out .= $vgOut->TblCell('Description');
	      $out .= $vgOut->TblCell('$ Buy');
	      $out .= $vgOut->TblCell('$ Sell');
	      $out .= $vgOut->TblCell('$ List');
	      $out .= $vgOut->TblCell('S/H charges');
	    $out .= $vgOut->TblRowShut();


	    while ($this->NextRow()) {
		$isOdd = !$isOdd;
		$out .= $this->AdminRow($isOdd,$doEdit);

	    }

	    if ($doEdit) {
	    // form buttons
		$isEdit = TRUE;
	    }

	} else {
	    $out .= '<tr><td colspan=10>No items found.</td></tr>';
	    if ($doEdit && array_key_exists('ID_Supplier',$iContext)) {
		$htAfter .= '<input type=submit name="btnCopyFrom" value="Copy items from:">';
		$idSupp = $iContext['ID_Supplier'];

		$objRows = $this->objDB->CtgGrps()->Active_forSupplier($idSupp,'Sort');
		$htAfter .= $objRows->DropDown('group_model');
	    }
	}
	$objNew = $this->Table->SpawnItem();
	$objNew->ID_Group = $this->ID_Group;
	$objNew->objForm = $this->objForm;
	$out .= $objNew->AdminRow($isOdd,$doEdit);

	$out .= $vgOut->TableShut();

	// close editing form
	$out .= '<input type=submit name="btnSaveItems" value="Save">';
	$out .= '<input type=reset value="Revert">';
	$out .= $htAfter;	// stuff to go after main form
	$out .= "\n</form>";
	return $out;
    }
    protected function AdminRow($iOdd,$iEdit) {
	global $vgOut;

	$doNew = $this->IsNew();
	$doEdit = $iEdit;
	$isOdd = $iOdd;

	if ($doNew && !$doEdit) { return; }

	$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
	$htAttr = 'style="'.$ftStyle.'"';

	$out = NULL;

	if ($doEdit) {
	    $objForm = $this->objForm;

	    $out .= $objForm->RowPrefix();

	    $ftActive = $objForm->Render('isActive');
	    $ftSort = $objForm->Render('Sort');

	    $keyForm = $doNew?'new':$this->KeyValue();

	    $htNameSfx = '['.$keyForm.']';
	    $idItTyp = $this->ValueNz('ID_ItTyp');
	    $idItOpt = $this->ValueNz('ID_ItOpt');
	    $idShip =  $this->ValueNz('ID_ShipCost');
	    $ftItTyp = $this->Engine()->ItTyps()->DropDown('ID_ItTyp'.$htNameSfx,$idItTyp,'--choose a type--');
	    $ftItOpt = $this->Engine()->ItOpts()->DropDown('ID_ItOpt'.$htNameSfx,$idItOpt,'--choose--');
	    $ftShip = $this->Engine()->ShipCosts()->DropDown('ID_ShipCost'.$htNameSfx,$idShip,'--choose--');

	    $ftDescr = $objForm->Render('Descr');
	    $ftPriceBuy = $objForm->Render('PriceBuy');
	    $ftPriceSell = $objForm->Render('PriceSell');
	    $ftPriceList = $objForm->Render('PriceList');
	} else {
	    $objItTyp = $this->objDB->ItTyps()->GetItem($this->ID_ItTyp);
	    $objItOpt = $this->objDB->ItOpts()->GetItem($this->ID_ItOpt);
	    $objShip = $this->objDB->ShipCosts()->GetItem($this->ID_ShipCost);
	    $ftActive = $this->Value('isActive')?'&radic;':'-';
	    $ftSort = $this->Value('Sort');

	    $ftItTyp = $objItTyp->Name();
	    $ftItOpt = $objItOpt->CatKey.' - '.$objItOpt->Descr;
	    $ftShip = '(i'.$objShip->PerItem.'+p'.$objShip->PerPkg.') '.$objShip->Descr;

	    $ftDescr = $this->Descr;
	    $ftPriceBuy = DataCurr($this->PriceBuy);
	    $ftPriceSell = DataCurr($this->PriceSell);
	    $ftPriceList = DataCurr($this->PriceList);
	}

	$out .= $vgOut->TblRowOpen($htAttr);
	$out .= $vgOut->TblCell($this->KeyValue());
	$out .= $vgOut->TblCell($ftActive);
	$out .= $vgOut->TblCell($ftSort);
	$out .= $vgOut->TblCell($ftItTyp);
	$out .= $vgOut->TblCell($ftItOpt);
	$out .= $vgOut->TblCell($ftDescr);
	$out .= $vgOut->TblCell($ftPriceBuy,'align=right');
	$out .= $vgOut->TblCell($ftPriceSell,'align=right');
	$out .= $vgOut->TblCell($ftPriceList,'align=right');
	$out .= $vgOut->TblCell($ftShip);

	return $out;
    }
    /*----
      HISTORY:
	2010-11-18 adapted from VbzAdminItem
    */
    private function BuildEditForm(array $iNewVals) {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet_indexed($this,$vgOut);
	    $arNewVals = $iNewVals;
	    $arNewVals['ID_ItTyp'] = NULL;	// required field for new records
	    $objForm->NewVals($arNewVals);

	    $objForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());
	    //$objForm->AddField(new clsFieldNum('ID_Group'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_ItTyp'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_ItOpt'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsField('Sort'),		new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsFieldNum('PriceBuy'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('PriceSell'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('PriceList'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ID_ShipCost'),	new clsCtrlHTML());

	    $this->objForm = $objForm;
	}
    }
    protected function AdminSave() {
	$out = $this->objForm->Save();
	return $out;
    }

}