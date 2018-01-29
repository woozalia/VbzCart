<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
class vctaSCGroups extends vcAdminTable {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'ctg_groups';
    }
    // CEMENT
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_GROUP;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_GROUP;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
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
    // ++ RECORDS ++ //
    
    // TODO: Rename this to Records_forSupplier_active() OSLT
    public function Active_forSupplier($idSupp,$sqlSort=NULL) {
	$sqlFilt = "isActive AND (ID_Supplier=$idSupp)";
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    public function ActiveRecords() {
	$sqlFilt = 'isActive';
	$sqlSort = 'Sort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$rs = $this->SelectRecords();
	
	/*
	$arCols = array(
	  'ID'		=> 'ID',
	  'ID_Supplier'	=> 'Supplier',
	  'Code'	=> 'Code',
	  'isActive'	=> 'A?',
	  'Name'	=> 'Name',
	  'Descr'	=> 'Description',
	  );
	return $rs->AdminRows($arCols); */
	
	return $rs->AdminList(FALSE,array());
    }
    
    // -- WEB UI -- //

}
class vcraSCGroup extends vcAdminRecordset implements fiEventAware {
    use ftLinkableRecord;
    use ftExecutableTwig;	// dispatch events
    //use ftLoggableRecord;

    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	if ($this->IsNew()) {
	    $sTitle = 'New SC Group';
	} else {
	    $sTitle = 'SC Group: '.$this->NameString();
	}
	
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle($sTitle);
	//$oPage->SetBrowserTitle($sTitle);
	//$oPage->SetContentTitle($htTitle);
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
    // ++ FIELD VALUES ++ //

    // PUBLIC so SCTitle listing can use it
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
    protected function IsActive() {
	return $this->GetFieldValue('isActive');
    }
    protected function CodeString() {
	return $this->GetFieldValue('Code');
    }
    protected function SortString() {
	return $this->GetFieldValue('Sort');
    }
    protected function Description() {
	return $this->GetFieldValue('Descr');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    // TRAIT HELPER
    public function SelfLink_friendly(array $iarArgs=NULL) {
	$out = $this->SelfLink($this->NameString(),NULL,$iarArgs);
	if (!$this->IsActive()) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }
    // TRAIT HELPER
    public function SelfLink_name() {
	return $this->SelfLink_friendly();
    }
    // CALLBACK
    public function ListItem_Text() {
	return $this->NameString();
    }
    // CALLBACK
    public function ListItem_Link() {
	return $this->SelfLink($this->ListItem_Text());
    }

    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //
    
    protected function SCItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_ITEMS,$id);
    }
    protected function SupplierTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_CATALOG_SUPPLIERS,$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function SuppObj() {
	throw new exception('Call SupplierRecord() instead.');
	return $this->Engine()->Suppliers()->GetItem($this->ID_Supplier);
    }
    protected function SupplierRecord() {
	return $this->SupplierTable($this->SupplierID());
    }
    /*----
      RETURNS: DataSet of CM Items for this CM Group
    */
    // TODO: Rename this something more descriptive, like SCItemRecords().
    protected function DataItems() {
	return $this->SCItemTable()->Data_forGroup($this->GetKeyValue());
    }

    // -- RECORDS -- //
    // ++ ACTION ++ //

    /*----
      ACTION: Adds a record (title+source) using the given data.
    */
    /* 2016 updated, but actually I think it makes more sense to do this in the SC Source Table
    public function MakeTitle($idLCTitle,$idSource) {
	$t = $this->Engine()->SCTitleTable();
	return $t->Add($idLCTitle,$this->GetKeyValue(),$idSource);
    } */

    // -- ACTION -- //
    // ++ ADMIN WEB UI ++ //

    //++single++//
    
    public function AdminPage() {
//	$out = NULL;

	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$isNew = $this->IsNew();

	if ($isNew) {
	    $doEdit = TRUE;
	} else {
	    $oMenu = fcApp::Me()->GetHeaderMenu();
	    $oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,'cancel','edit this record'));
    
	      $doEdit = $ol->GetIsSelected();
	}

//	$doEdit = $oPage->PathArg('edit') || $isNew;
	$doSave = $oFormIn->GetBool('btnSave');

	$idSupp = $oPathIn->GetInt('supp');
	if (!empty($idSupp)) {
	    $this->SetFieldValue('ID_Supplier',$idSupp);
	}
/*
	if ($isNew) {
	    $sTitle = 'New Group';
	} else {
	    $sTitle = 'Group: '.$this->NameString();
	}
	$oPage->TitleString($sTitle);
*/	
	// Set up rendering objects
	$frm = $this->PageForm();
	if ($isNew) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
		
	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $sMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsg);
	}
/*	
	if (!$isNew) {

	    $arMenu = array(
	      new clsActionLink_option(array(),'edit')
	      );

	    $oPage->PageHeaderWidgets($arMenu);
	}
*/	
	$out = NULL;

	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	}

	// render the template
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $sLabel = $isNew?'Create':'Save';
	    $out .= "\n<input type=submit name='btnSave' value='$sLabel'>"
	      .'</form>'
	      ;
	}
	
	/* 2016-02-02 old version
	if ($doEdit) {
	    $out .= '<form method=post>';

	    $htActv = $this->objForm;
	    $htName = $objForm->Render('Name');
	    $htDescr = $objForm->Render('Descr');
	    $htActv = $objForm->Render('isActive');
	    $htCode = $objForm->Render('Code');
	    $htSort = $objForm->Render('Sort');
	} else {
	    $htActv = fcString::NoYes($this->Value('isActive'));
	    $htName = fcString::EncodeForHTML($this->Value('Name'));
	    $htDescr = fcString::EncodeForHTML($this->Descr);
	    $htCode = fcString::EncodeForHTML($this->Code);
	    $htSort = fcString::EncodeForHTML($this->Sort);
	}
	// non-editable fields:
	$htID = $this->ID;
	$htSupp = $this->SupplierRecord()->SelfLink_name();

	$out .= '<table>';
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>";
	$out .= "\n<tr><td align=right><b>Active</b>:</td><td>$htActv</td></tr>";
	$out .= "\n<tr><td align=right><b>Name</b>:</td><td>$htName</td></tr>";
	$out .= "\n<tr><td align=right><b>Supplier</b>:</td><td>$htSupp</td></tr>";
	$out .= "\n<tr><td align=right><b>Code</b>:</td><td>$htCode</td></tr>";
	$out .= "\n<tr><td align=right><b>Sort</b>:</td><td>$htSort</td></tr>";
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
	} */
	if (!$isNew) {
	    $out .= $this->AdminItems();
	

	/*
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option(
		array(
		  'page'	=> $this->SCItemTable()->ActionKey(),
		  'group'	=> $this->GetKeyValue()
		  ),	// extra link data
		'new',	// link key
		'id',		// group key
		NULL		// show when off
		)
	      );
	    $out .= $oPage->ActionHeader('Items',$arActs);
	*/
	}

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=record-block>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Active</b>:</td><td>[[isActive]]</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>[[Name]]</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>[[Descr]]</td></tr>
  <tr><td align=right><b>Supplier</b>:</td><td>[[ID_Supplier]]</td></tr>
  <tr><td align=right><b>Code</b>:</td><td>[[Code]]</td></tr>
  <tr><td align=right><b>Sort</b>:</td><td>[[Sort]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      HISTORY:
	2010-11-17 adapted from VbzAdminCatalog
	2016-02-02 updated to use latest Ferreteria forms classes
    */
    private $oForm;
    private function PageForm() {
	if (empty($this->oForm)) {

	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Text($oForm,'Name');
	      
	      $oField = new fcFormField_Num($oForm,'ID_Supplier');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->SetRecords($this->SupplierTable()->ActiveRecords());
	      
	      $oField = new fcFormField_Text($oForm,'Descr');
	    
	      $oField = new fcFormField_Num($oForm,'isActive');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());
		
	      $oField = new fcFormField_Text($oForm,'Code');
	      
	      $oField = new fcFormField_Text($oForm,'Sort');
	      
	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    
    //--single--//
    //++multi++//

    /*----
      INPUT:
	iContext = array of fields (array[name]=val) that are always fixed (can be empty)
	  These are set when inserting new records.
      HISTORY:
	2011-02-06 added editing ability
    */
    public function AdminList($iEdit,array $iContext) {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	$t = $this->GetTableWrapper();

	$out = NULL;
	$txtEdit = NULL;
	if ($iEdit) {
	    $out .= "\n<form method=post>";
	}

	$db = $this->GetConnection();
	if ($oFormIn->GetBool('btnCtgChange')) {
	    $arUpd = $db->Sanitize_andQuote($oFormIn->GetArray('ctg-upd'));
	    $arIns = $db->Sanitize_andQuote($oFormIn->GetArray('ctg-ins'));
	    if (is_array($arUpd)) {
		foreach ($arUpd as $id => $row) {
		    $t->Update($row,'ID='.$id);
		    $out .= '<br>'.$t->sql;
		}
	    }
	    if (is_array($arIns)) {
		foreach ($arIns as $idx => $row) {
		    $row = array_merge($row,$iContext);
		    $t->Insert($row);
		    $out .= '<br>'.$t->sql;
		}
	    }
	    $iEdit = FALSE;	// saving changes -- turn off edit controls
	    // this has not yet been tested
	}
	if ($oFormIn->GetBool('btnCtgCheck')) {
	    $txtEdit = $oFormIn->GetString('txtCtgGrps');
	    $arLines = preg_split("/\n/",$txtEdit);

	    $xts = new xtString();
	    $htTbl = <<<__END__

<table class=listing>
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>Code</th>
    <th>Sort</th>
    <th>Name</th>
    <th>Description</th>
  </tr>
__END__;
	    $arFlds = array('isActive','Code','Sort','Name','Descr');
	    $arDiff = NULL;
	    $cntRowChg = 0;
	    $idxIns = 0;
	    $isOdd = FALSE;
	    foreach ($arLines as $line) {
		$xts->SetValue($line);
		$arRow = $xts->Xplode();

		$isOdd = !$isOdd;
		$cssClass = $isOdd?'odd':'even';

		list($id,$isActive,$code,$sort,$name,$descr) = $arRow;
		if (empty($id)) {
		    // new row - add to list of changes
		    $cntRowChg++;
		    //$htRow .= '<tr><td><b>NEW</b></td>';
		    $htRow = "\n  <tr class=$cssClass><td>NEW</td>";
		    $idxFld = 0;
		    $idxIns++;
		    foreach ($arFlds as $key) {
			$idxFld++;
			$vNew = $arRow[$idxFld];
			$htVal = fcString::EncodeForHTML($vNew);
			$ctVal = $htVal.'<input type=hidden name="ctg-ins['.$idxIns.']['.$key.']" value="'.$htVal.'">';
			$htRow .= '<td><b>'.$ctVal.'</b></td>';
		    }
		    $htRow .= '</tr>';
		    $htTbl .= $htRow;
		} else {
		    $rc = $t->GetRecord_forKey($id);

		    $chg = NULL;
		    $htID = $rc->SelfLink();
		    $htRow = "\n  <tr class=$cssClass><td>$htID</td>";
		    $idxFld = 0;
		    foreach ($arFlds as $key) {
			$htRow .= '<td>';
			$idxFld++;
			$vNew = $arRow[$idxFld];
			if ($orc->GetFieldValue($key) != $vNew) {
			    $chg .= ' '.$key;
			    $htVal = fcString::EncodeForHTML($vNew);
			    $ctVal = $htVal.'<input type=hidden name="ctg-upd['.$id.']['.$key.']" value="'.$htVal.'">';
			    $htRow .= '<s>'.fcString::EncodeForHTML($rc->Value($key)).'</s><b>'.$ctVal.'</b>';
			} else {
			    $htRow .= $vNew;
			}
			$htRow .= '</td>';
		    }
		    $htRow .= '<td>diff:'.$chg.'</td></tr>';
		    if (!is_null($chg)) {
			$htTbl .= $htRow;
			$cntRowChg++;
		    }
		}
	    }
	    $htTbl .= '</table>';
	    if ($cntRowChg) {
		$out .= $htTbl;
		$out .= '<input type=submit name="btnCtgChange" value="Save Changes">';
	    } else {
		$out .= 'No changes were detected.<br>';
	    }
	}

	if ($this->HasRows()) {
	    if ($iEdit) {
		$cntRows = $this->RowCount();
		if ($cntRows < 10) {
		    $cntRows = 10;
		}
		$out .= 'each line = [ID - Active? - Code - Sort - Name - Description] as a <a href="http://htyp.org/prefix-separated_list">prefix-separated list</a>';
		$out .= '<textarea name=txtCtgGrps rows='.$cntRows.'>';
		if (is_null($txtEdit)) {
		    while ($this->NextRow()) {
			$txt = "\n\t".$this->GetKeyValue()
			  ."\t".$this->GetFieldValue('isActive')
			  ."\t".$this->GetFieldValue('Code')
			  ."\t".$this->GetFieldValue('Sort')
			  ."\t".$this->GetFieldValue('Name')
			  ."\t".$this->GetFieldValue('Descr');
			$out .= fcString::EncodeForHTML($txt);
		    }
		} else {
		    $out .= fcString::EncodeForHTML($txtEdit);
		}
		$out .= '</textarea>';
		$out .= '<input type=submit name=btnCtgCheck value="Review Changes">';
		$out .= '</form>';
	    } else {
		$out .= <<<__END__

<table class=listing>
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>#it</th>
    <th>Code</th>
    <th>Sort</th>
    <th>Name</th>
    <th>Description</th>
__END__;
		$isOdd = TRUE;
		while ($this->NextRow()) {
		    $isOdd = !$isOdd;
		    $cssClass = $isOdd?'odd':'even';
		    $isActive = $this->isActive();
		    if (!$isActive) {
			$cssClass = 'inactive';
		    }

		    $rsItems = $this->DataItems();
		    $rcItems = $rsItems->RowCount();
		    $htItems = ($rcItems==0)?'<span style="color: red; font-weight: bold;">0</span>':$rcItems;
		    $htID = $this->SelfLink();
		    $out .= "\n<tr class=$cssClass>".
			"\n<td>$htID</td>".
			'<td>'.($isActive?'&radic;':'').'</td>'.
			'<td>'.$htItems.'</td>'.
			'<td>'.$this->CodeString().'</td>'.
			'<td>'.$this->SortString().'</td>'.
			'<td>'.$this->NameString().'</td>'.
			'<td>'.$this->Description().'</td>'.
			'</tr>';
		}
		$out .= '</table>';
	    }
	} else {
	    $out = 'No groups found.';
	}
	return $out;
    }
    
    //--multi--//
    //++dependent++//

    /*----
      ACTION: Render table of CM Items for this CM Group, with admin controls
      HISTORY:
	2012-03-06 removing "ID_Supplier" - CM Items don't have a supplier field.
	  Including this causes new records not to be saved.
	2012-03-08 we DO need ID_Supplier so AdminRows() can show the drop-down list
	  of possible groups to copy from. AdminRows() is responsible for stripping it
	  out when passing it on to the row-editor form.
    */
    protected function AdminItems() {
	$rsRows = $this->DataItems();
	$arOpts = array(
	  'context'	=> array(
	    'ID_Group'		=> $this->GetKeyValue(),
	    'ID_Supplier'	=> $this->GetFieldValue('ID_Supplier')
	    ),
	  );
	return $rsRows->AdminRows(NULL,$arOpts);
    }
    
    //--dependent--//
    //++forms++//

    /*-----
      ACTION: Display dataset in a format which allows multiple independent selections
      INPUT:
	iName = name for the control. If using checkboxes, each checkbox is named iName[ID].
	iAsBox:
	  FALSE: show one checkbox for each record.
	  TRUE: show all in a single multi-select option box
      RETURNS: HTML (NULL if no rows to display)
    */
    public function MultiSelect($iName,$iAsBox,$iAttr=NULL) {
	if ($this->HasRows()) {
	    $out = '';

	    $doBox = $iAsBox;
	    $htAttr = is_null($iAttr)?NULL:(' '.$iAttr);

	    if ($doBox) {
		$htName = $iName.'[]';
		$out .= "\n<select name=\"$htName\"$htAttr multiple=\"multiple\">";
		while ($this->NextRow()) {
		    $id = $this->GetKeyValue();
		    //$sName = "test[$id]";
		    $sName = $id;
		    $htShow = fcString::EncodeForHTML($this->NameString());
		    $out .= "\n<option value='$sName'>$htShow</option>";
		}
		$out .= "\n</select>";
	    } else {
		while ($this->NextRow()) {
		    $out .= '<br><input type=checkbox name="'.$iName.'['.$this->ID.']">'.$this->Name;
		}
	    }
	    return $out;
	} else {
	    return NULL;
	}
    }
    
    //--forms--//
    //++components++//
    
    /*----
      HISTORY:
	2011-02-08 adapted from VbzAdminDept::DropDown()
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iNone=NULL) {
	throw new exception('Does anything still call this?');
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key

	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->GetKeyValue();
		$htShow = $this->Value('Name');
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
     }
     
     //--components--//

     // -- ADMIN WEB UI -- //
}
