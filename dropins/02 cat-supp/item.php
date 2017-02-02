<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
/*====
  CLASS: Catalog Management Items
*/
class VCTA_SCItems extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ctg_items';
    }
    // CEMENT
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_ITEM;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_ITEM;
    }
    
    // -- SETUP -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminPage();
    }

    // -- CALLBACKS -- //
    // ++ RECORDS ++ //
    
    public function Data_forGroup($idGroup) {
	return $this->GetData('ID_Group='.$idGroup,NULL,'Sort');
    }
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$rs = $this->SelectRecords(NULL,'Sort');
	$out = $rs->AdminRows();
	return $out;
    }

    // -- WEB UI ++ //
}
class VCRA_SCItem extends vcAdminRecordset {

    // ++ TRAIT HELPERS ++ //

    public function SelfLink_descr() {
	$out = $this->SelfLink($this->Description());
	if (!$this->IsActive()) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }
    
    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminPage();
    }

    // -- CALLBACKS -- //
    // ++ FIELD VALUES ++ //
    
    protected function GroupID($v=NULL) {
	return $this->Value('ID_Group',$v);
    }
    
    // -- FIELD VALUES -- //
    // ++ CLASS NAMES ++ //
    
    protected function ItemTypesClass() {
	return KS_ADMIN_CLASS_LC_ITEM_TYPES;
    }
    protected function ItemOptionsClass() {
	return KS_ADMIN_CLASS_LC_ITEM_OPTIONS;
    }
    protected function ShipCostsClass() {
	return KS_ADMIN_CLASS_SHIP_COSTS;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    protected function SCGroupTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_GROUPS,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function SCGroupRecord() {
	$id = $this->Value('ID_Group');
	$rc = $this->SCGroupTable($id);
	return $rc;
    }
    public function ItemTypeRecord() {
	$id = $this->Value('ID_ItTyp');
	$rc = $this->ItemTypeTable($id);
	return $rc;
    }
    public function ItemOptionRecord() {
	$id = $this->Value('ID_ItOpt');
	$rc = $this->ItemOptionTable($id);
	return $rc;
    }
    public function ShipCostRecord() {
	$id = $this->Value('ID_ShipCost');
	$rc = $this->ShipCostTable($id);
	return $rc;
    }

    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    //++multi++//

    /*----
      INPUT:
	arOptions:
	  ['context']: array of values that apply to all rows (e.g. for creating new records)
	    [key] = value
	  ['logger']: logging object (is this necessary anymore?)
      FUTURE: This function should also display its own header, so we can have all the action-link naming in one place
      HISTORY:
	2012-03-08 we DO need ID_Supplier in the Context, in order to show the list of groups to copy from --
	  but we have to strip it out before passing it on to the row editor
	2016-02-02 rewritten to use Ferreteria forms - removed iContext and iLogger args for now
    */
//    public function AdminRows(array $iContext, clsLogger_DataSet $iLogger=NULL) {
    public function AdminRows(array $arFields = NULL, array $arOptions = NULL) {
	$oPage = $this->Engine()->App()->Page();
	
	$arContext = clsArray::Nz($arOptions,'context');
	$iLogger = clsArray::Nz($arOptions,'logger');
    
	// get URL input
	$doSave = clsHTTP::Request()->getBool('btnSaveItems');
	$doCopy = clsHTTP::Request()->getBool('btnCopyFrom');

	// handle edit form input:
	if ($doSave) {
	    $arUpdate = clsHTTP::Request()->getArray('update');
	    $arActive = clsHTTP::Request()->getArray('isActive');

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
	    $idGrp = clsHTTP::Request()->GetIntOrNull('group_model');
	    $rsItems = $this->Table()->Data_forGroup($idGrp);
	    if ($rsItems->HasRows()) {
		$objGrp = $this->CtgGrps($idGrp);
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
		$strKeyName = $rsItems->Table()->GetKeyName();
		while ($rsItems->NextRow()) {
		    $rc++; $rtxt.='['.$rsItems->GetKeyValue();
		    $arRow = $rsItems->Values();
		    // unset row key
		    unset($arRow[$strKeyName]);
		    // overwrite any default values from context
		    foreach ($arContext as $key => $val) {
			if (array_key_exists($key,$arRow)) {
			    $arRow[$key] = $val;
			}
		    }
		    // build insert array by iterating through row's fields
		    $db = $this->GetConnection();
		    foreach ($arRow as $key => $val) {
			$arIns[$key] = $db->SanitizeAndQuote($val);
		    }
		    // do the update
		    $idNew = $this->Table()->Insert($arIns);
		    $out .= '<br><b>SQL</b>: '.$this->Table()->sql;
		    $rtxt .= '->'.$idNew.']';
		}
		$txtDescr = $rc.' item'.fcString::Pluralize($rc).' copied:'.$rtxt;
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
	if ($doSave) {
	    $sqlLoad = $this->sqlCreate;
	    $frm = $this->PageForm();
	    $frm->Save();
	    $out .= $frm->MessagesString();
	    $didEdit = TRUE;
	    $this->SelfRedirect(NULL,$out);
	}

	// display rows
	$isOdd = TRUE;
	$out .= "\n<table class=listing>";
	$htAfter = NULL;
	if ($this->HasRows()) {
	    $out .= self::LineHeader();

	    while ($this->NextRow()) {
		$isOdd = !$isOdd;
		$out .= $this->AdminRow($isOdd);
	    }

	} else {
	    $out .= '<tr><td colspan=10>No items found.</td></tr>';
	    if ($doEdit && array_key_exists('ID_Supplier',$arContext)) {
		$htAfter .= '<input type=submit name="btnCopyFrom" value="Copy items from:">';
		$idSupp = $arContext['ID_Supplier'];

		$objRows = $this->CtgGrps()->Active_forSupplier($idSupp,'Sort');
		$htAfter .= $objRows->DropDown('group_model');
	    }
	}
	
	/* 2016-02-03 no longer used
	// Show a line for entering a new item:
	$rcNew = $this->Table()->SpawnItem();
	$rcNew->ValuesSet($arContext);	// copy any values in the Context
	$out .= $rcNew->AdminRow($isOdd,$doEdit);
	*/

	$out .= "\n</table>";

	return $out;
    }
    /*----
      HISTORY:
	2016-02-03 Updated to current Ferreteria forms
	  Also eliminating edit option, as the layout gets too wide to edit by row.
	  Going with the standard page-form instead.
    */
    protected function AdminRow($isOdd) {

	$frm = $this->RecordForm();

	$out = NULL;
	
	$frm->LoadRecord();
	$oTplt = $this->LineTemplate();
	$arCtrls = $frm->RenderControls(FALSE);	// line-view is read-only
	$arCtrls['!cssClass'] = $isOdd?'odd':'even';
	$arCtrls['!ID'] = $this->SelfLink();
	
	// render the template
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	/* 2016-02-02 old version (slightly updated for clarity)
	if ($doEdit) {
	    $frm = $this->RecordForm();

	    //$out .= $objForm->RowPrefix();

	    $ftActive = $frm->Render('isActive');
	    $ftSort = $frm->Render('Sort');

	    $keyForm = $doNew?KS_NEW_REC:$this->GetKeyValue();

	    $htNameSfx = '['.$keyForm.']';
	    $idItTyp = $this->ValueNz('ID_ItTyp');
	    $idItOpt = $this->ValueNz('ID_ItOpt');
	    $idShip =  $this->ValueNz('ID_ShipCost');
	    $ftItTyp = $this->ItemTypeTable()->DropDown('ID_ItTyp'.$htNameSfx,$idItTyp,'--choose a type--');
	    $ftItOpt = $this->ItemOptionTable()->DropDown('ID_ItOpt'.$htNameSfx,$idItOpt,'--choose--');
	    $ftShip = $this->ShipCostTable()->DropDown('ID_ShipCost'.$htNameSfx,$idShip,'--choose--');

	    $ftDescr = $frm->Render('Descr');
	    $ftPriceBuy = $frm->Render('PriceBuy');
	    $ftPriceSell = $frm->Render('PriceSell');
	    $ftPriceList = $frm->Render('PriceList');
	} else {
	    $rcItTyp = $this->ItemTypeTable($this->ItemTypeID());
	    $rcItOpt = $this->ItemOptionTable($this->ItemOptionID());
	    $rcShip = $this->ShipCostTable($this->ShipCostID());
	    $ftActive = $this->Value('isActive')?'&radic;':'-';
	    $ftSort = $this->Value('Sort');

	    $ftItTyp = $rcItTyp->Name();
	    $ftItOpt = $rcItOpt->CatKey().' - '.$rcItOpt->Description();
	    $ftShip = '(i'.$rcShip->PerItem().'+p'.$rcShip->PerPkg().') '.$rcShip->Description();

	    $ftDescr = $this->Description();
	    $ftPriceBuy = DataCurr($this->PriceBuy());
	    $ftPriceSell = DataCurr($this->PriceSell());
	    $ftPriceList = DataCurr($this->PriceList());
	}

	$htID = $this->SelfLink();
	$out .= <<<__END__
  <tr$htAttr>
    <td>$htID</td>
    <td>$ftActive</td>
    <td>$ftSort</td>
    <td>$ftItTyp</td>
    <td>$ftItOpt</td>
    <td>$ftDescr</td>
    <td align=right>$ftPriceBuy</td>
    <td align=right>$ftPriceSell</td>
    <td align=right>$ftPriceList</td>
    <td>$ftShip</td>
  </tr>
__END__;
*/
	return $out;
    }
    /*----
      HISTORY:
	2010-11-18 adapted from VbzAdminItem
	2016-02-02 updated
	2016-02-03 This will be used for the page form as well (if all goes according to plan),
	  and for the read-only line view (line view is too wide in edit mode).
    */
    private $oForm;
    private function RecordForm() {

	if (empty($this->oForm)) {
	
	    $oForm = new fcForm_DB($this);
	
	      $oField = new fcFormField_Num($oForm,'isActive');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());

	      $oField = new fcFormField_Num($oForm,'ID_Group');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->SCGroupTable()->ActiveRecords());

	      $oField = new fcFormField_Num($oForm,'ID_ItTyp');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ItemTypeTable()->ActiveRecords());

	      $oField = new fcFormField_Num($oForm,'ID_ItOpt');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ItemOptionTable()->ActiveRecords());
		
	      $oField = new fcFormField_Text($oForm,'Descr');
	      
	      $oField = new fcFormField_Text($oForm,'Sort');
	      
	      $oField = new fcFormField_Num($oForm,'PriceBuy');

	      $oField = new fcFormField_Num($oForm,'PriceSell');

	      $oField = new fcFormField_Num($oForm,'PriceList');

	      $oField = new fcFormField_Num($oForm,'ID_ShipCost');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ShipCostTable()->ActiveRecords());
		
/* 2016-02-02 old version	
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
*/
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    static protected function LineHeader() {
	return <<<__END__
  <tr>
    <th>ID</th>
    <th title="active?">A?</th>
    <th title="sorting order">S</th>
    <th>Group</th>
    <th>Item Type</th>
    <th>Item Option</th>
    <th>Description</th>
    <th>$ Buy</th>
    <th>$ Sell</th>
    <th>$ List</th>
    <th>S/H charges</th>
  </tr>
__END__;
    }
    /*----
      NOTE: This is only used in read-only mode.
    */
    private $tpLine;
    protected function LineTemplate() {
	if (empty($this->tpLine)) {
	    $sTplt = <<<__END__
  <tr class=[[!cssClass]]>
    <td>[[!ID]]</td>
    <td>[[isActive]]</td>
    <td>[[Sort]]</td>
    <td>[[ID_Group]]</td>
    <td>[[ID_ItTyp]]</td>
    <td>[[ID_ItOpt]]</td>
    <td>[[Descr]]</td>
    <td align=right>[[PriceBuy]]</td>
    <td align=right>[[PriceSell]]</td>
    <td align=right>[[PriceList]]</td>
    <td>[[ID_ShipCost]]</td>
  </tr>
__END__;
	    $this->tpLine = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpLine;
    }
    
    //--multi--//
    //++single++//

    protected function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	
	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$isNew = $this->IsNew();

	if ($isNew) {
	    $sTitle = 'New SCM Item';
	} else {
	    $sTitle = 'SCMI#'.$this->GetKeyValue();
	}

	$arMenu = array(
	    // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL)
	  new clsActionLink_option(array(),
	    'edit',	// link key
	    NULL,	// group key
	    NULL,	// OFF display
	    NULL,	// ON display,
	    'edit this item'	// popup description
	    ),
	  );
	$oPage->TitleString($sTitle);
	$oPage->PageHeaderWidgets($arMenu);

	$frm = $this->RecordForm();

	if ($doSave) {
	    $frm->Save();
	    $this->SelfRedirect($frm->MessagesString());
	}
	
	// Set up rendering objects
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['!ID'] = $this->SelfLink();
	
	$out = NULL;
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}

	// render the template
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $sLabel = $isNew?'Create':'Save';
	    $out .= "\n<input type=submit name='btnSave' value='$sLabel'>"
	      .'</form>'
	      ;
	}
	return $out;
    }
    
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpLine)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td>		<td>[[!ID]]</td></tr>
  <tr><td align=right><b>Active?</b></td>	<td>[[isActive]]</td></tr>
  <tr><td align=right><b>Group</b>:</td>	<td>[[ID_Group]]</td></tr>
  <tr><td align=right><b>Item Type</b>:</td>	<td>[[ID_ItTyp]]</td></tr>
  <tr><td align=right><b>Item Option</b>:</td>	<td>[[ID_ItOpt]]</td></tr>
  <tr><td align=right><b>Description</b>:</td>	<td>[[Descr]]</td></tr>
  <tr><td align=right><b>Sort order</b>:</td>	<td>[[Sort]]</td></tr>
  <tr><td align=right><b>Price we buy</b>:</td>		<td align=right>[[PriceBuy]]</td></tr>
  <tr><td align=right><b>Price we sell</b>:</td>	<td align=right>[[PriceSell]]</td></tr>
  <tr><td align=right><b>List price</b>:</td>		<td align=right>[[PriceList]]</td></tr>
  <tr><td align=right><b>Ship cost code</b>:</td>	<td>[[ID_ShipCost]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }

    //--single--//
    
    // -- WEB UI -- //
}