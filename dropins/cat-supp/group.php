<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
class VCTA_SCGroups extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ctg_groups');
	  $this->KeyName('ID');
	  $this->ClassSng('VCRA_SCGroup');
	  $this->ActionKey('ctg.grp');
    }
    public function Active_forSupplier($iSupp,$iSort=NULL) {
	  $objRows = $this->GetData('isActive AND (ID_Supplier='.$iSupp.')',NULL,$iSort);
	  return $objRows;
    }
}
class VCRA_SCGroup extends clsDataSet {
    private $frmPage;

    // ++ SETUP ++ //

    protected function InitVars() {
	$this->frmPage = NULL;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminRedirect($this,$iarArgs);
    }
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }

    // -- BOILERPLATE -- //
    // ++ BOILERPLATE EXTENSIONS ++ //

    public function AdminLink_friendly(array $iarArgs=NULL) {
	$out = $this->AdminLink($this->Name,NULL,$iarArgs);
	if (!$this->isActive) {
	    $out = '<s>'.$out.'</s>';
	}
	return $out;
    }
    public function AdminLink_name() {
	return $this->AdminLink_friendly();
    }

    // -- BOILERPLATE EXTENSIONS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function SuppObj() {
	return $this->objDB->Suppliers()->GetItem($this->ID_Supplier);
    }
    /*----
      RETURNS: DataSet of CM Items for this CM Group
    */
    protected function DataItems() {
	//return $this->objDB->CMItems()->GetData('ID_Group='.$this->ID);
	return $this->objDB->CMItems()->Data_forGroup($this->ID);
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ACTION ++ //

    /*----
      ACTION: Adds a record (title+source) using the given data.
    */
    public function Add($iTitle,$iSource) {
	$tblCT = $this->objDB->CtgTitles();
	return $tblCT->Add($iTitle,$this->ID,$iSource);
    }

    // -- ACTION -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

	$isNew = is_null($this->KeyValue());
	$doEdit = $oPage->PathArg('edit') || $isNew;
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$idSupp = $oPage->PathArgInt('supp');
	if (!empty($idSupp)) {
	    $this->Value('ID_Supplier',$idSupp);
	}

	if ($isNew) {
	    $strName = 'New Group';
	} else {
	    $strName = 'Group: '.$this->Name;
	}
/*
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,$strName);
	//$out = $objSection->HeaderHtml_Edit();
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();
*/
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	//  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	if (!$isNew) {

	    $arActs = array(
	      new clsActionLink_option(array(),'edit')
	      );
	    $out .= $oPage->ActionHeader('Current Record',$arActs);


//	    $objSection = new clsWikiSection($objPage,'Current Record',NULL,3);
//	    $objSection->ToggleAdd('edit');
//	    $out .= $objSection->Generate();
	}
	if ($doEdit || $doSave) {
	    if ($doSave) {
		$this->AdminSave();
		$this->AdminRedirect();
	    }
	}
	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id','supp'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $objForm = $this->objForm;

	    $htActv = $this->objForm;
	    $htName = $objForm->Render('Name');
	    $htDescr = $objForm->Render('Descr');
	    $htActv = $objForm->Render('isActive');
	    $htCode = $objForm->Render('Code');
	    $htSort = $objForm->Render('Sort');
	} else {
	    $htActv = NoYes($this->Value('isActive'));
	    $htName = htmlspecialchars($this->Value('Name'));
	    $htDescr = htmlspecialchars($this->Descr);
	    $htCode = htmlspecialchars($this->Code);
	    $htSort = htmlspecialchars($this->Sort);
	}
	// non-editable fields:
	$htID = $this->ID;
	$htSupp = $this->SuppObj()->AdminLink_name();

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
	}
	if (!$isNew) {
	    // group item listing
//	    $objSection = new clsWikiSection($objPage,'Items',NULL,3);
//	    $objSection->ToggleAdd('edit','edit items','edit.items');
//	    $out .= $objSection->Generate();

	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option(array(),'edit.items',NULL,'edit')
	      );
	    $out .= $oPage->ActionHeader('Items',$arActs);

	    $out .= $this->AdminItems();
	}

	return $out;
    }
    protected function AdminSave() {
	$out = $this->PageForm()->Save();
	return $out;
    }
    /*----
      HISTORY:
	2010-11-17 adapted from VbzAdminCatalog
    */
    private function PageForm() {
	if (is_null($this->frmPage)) {
	    $frmPage = new clsForm_recs($this);

	    $frmPage->AddField(new clsField('Name'),			new clsCtrlHTML());
	    $frmPage->AddField(new clsField('Descr'),			new clsCtrlHTML());
	    $frmPage->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());
	    $frmPage->AddField(new clsField('Code'),			new clsCtrlHTML());
	    $frmPage->AddField(new clsField('Sort'),			new clsCtrlHTML());

	    $this->$frmPage = $frmPage;
	}
	return $this->$frmPage;
    }
    /*----
      INPUT:
	iContext = array of fields (array[name]=val) that are always fixed (can be empty)
	  These are set when inserting new records.
      HISTORY:
	2011-02-06 added editing ability
    */
    public function AdminList($iEdit,array $iContext) {
	global $wgRequest;
	global $wgOut;

	$out = NULL;
	$txtEdit = NULL;
	if ($iEdit) {
	    $out .= '<form method=post>';
	}

	if ($wgRequest->GetBool('btnCtgChange')) {
	    $arUpd = SQLValue($wgRequest->GetArray('ctg-upd'));
	    $arIns = SQLValue($wgRequest->GetArray('ctg-ins'));
	    if (is_array($arUpd)) {
		foreach ($arUpd as $id => $row) {
		    $this->Table->Update($row,'ID='.$id);
		    $out .= '<br>'.$this->Table->sqlExec;
		}
	    }
	    if (is_array($arIns)) {
		foreach ($arIns as $idx => $row) {
		    $row = array_merge($row,$iContext);
		    $this->Table->Insert($row);
		    $out .= '<br>'.$this->Table->sqlExec;
		}
	    }
	    $iEdit = FALSE;	// saving changes -- turn off edit controls
	    // this has not yet been tested
	}
	if ($wgRequest->GetBool('btnCtgCheck')) {
	    $txtEdit = $wgRequest->GetText('txtCtgGrps');
	    $arLines = preg_split("/\n/",$txtEdit);

	    $xts = new xtString();
	    $htTbl = '<table border=1><tr><th>ID</th><th>A?</th><th>Code</th><th>Sort</th><th>Name</th><th>Description</th></tr>';
	    $arFlds = array('isActive','Code','Sort','Name','Descr');
	    $arDiff = NULL;
	    $cntRowChg = 0;
	    $idxIns = 0;
	    foreach ($arLines as $line) {
		$xts->Value = $line;
		$arRow = $xts->Xplode();

		list($id,$isActive,$code,$sort,$name,$descr) = $arRow;
		if (empty($id)) {
		    // new row - add to list of changes
		    $cntRowChg++;
		    //$htRow .= '<tr><td><b>NEW</b></td>';
		    $htRow = '<tr><td>NEW</td>';
		    $idxFld = 0;
		    $idxIns++;
		    foreach ($arFlds as $key) {
			$idxFld++;
			$vNew = $arRow[$idxFld];
			$htVal = htmlspecialchars($vNew);
			$ctVal = $htVal.'<input type=hidden name="ctg-ins['.$idxIns.']['.$key.']" value="'.$htVal.'">';
			$htRow .= '<td><b>'.$ctVal.'</b></td>';
		    }
		    //$sql = $this->Table->SQL_forInsert($arIns);
		    $htRow .= '</tr>';
		    //$htRow .= '<tr><td colspan=6>'.$sql.'</td></tr>';
		    $htTbl .= $htRow;
		} else {
		    $obj = $this->Table->GetItem($id);

		    $chg = NULL;
		    $htRow = '<tr><td>'.$obj->AdminLink().'</td>';
		    $idxFld = 0;
		    foreach ($arFlds as $key) {
			$htRow .= '<td>';
			$idxFld++;
			$vNew = $arRow[$idxFld];
			if ($obj->Value($key) != $vNew) {
			    $chg .= ' '.$key;
			    $htVal = htmlspecialchars($vNew);
			    $ctVal = $htVal.'<input type=hidden name="ctg-upd['.$id.']['.$key.']" value="'.$htVal.'">';
			    $htRow .= '<s>'.htmlspecialchars($obj->Value($key)).'</s><b>'.$ctVal.'</b>';
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
			$txt = "\n\t".$this->KeyValue()
			  ."\t".$this->Value('isActive')
			  ."\t".$this->Value('Code')
			  ."\t".$this->Value('Sort')
			  ."\t".$this->Value('Name')
			  ."\t".$this->Value('Descr');
			$out .= htmlspecialchars($txt);
		    }
		} else {
		    $out .= htmlspecialchars($txtEdit);
		}
		$out .= '</textarea>';
		$out .= '<input type=submit name=btnCtgCheck value="Review Changes">';
		$out .= '</form>';
	    } else {
		$out .= "<table class=sortable><tr><th>ID</th><th>A?</th><th>#it</th><th>Code</th><th>Sort</th><th>Name</th><th>Description</th>";
		$isOdd = TRUE;
		while ($this->NextRow()) {
		    $isOdd = !$isOdd;
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isActive = $this->isActive;
		    if (!$isActive) {
			$wtStyle .= ' color: #888888;';
		    }

		    $rsItems = $this->DataItems();
		    $rcItems = $rsItems->RowCount();
		    $htItems = ($rcItems==0)?'<span style="color: red; font-weight: bold;">0</span>':$rcItems;
		    $wtID = $this->AdminLink();
		    $out .= "\n<tr style=\"$wtStyle\">".
			"\n<td>$wtID</td>".
			'<td>'.($isActive?'&radic;':'').'</td>'.
			'<td>'.$htItems.'</td>'.
			'<td>'.$this->Code.'</td>'.
			'<td>'.$this->Sort.'</td>'.
			'<td>'.$this->Name.'</td>'.
			'<td>'.$this->Descr.'</td>'.
			'</tr>';
		}
		$out .= '</table>';
	    }
	} else {
	    $out = 'No groups found.';
	}
	$wgOut->AddHTML($out);
	return NULL;
    }
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
	return $rsRows->AdminRows(array(
	  'ID_Group'	=> $this->KeyValue(),
	  'ID_Supplier'	=> $this->Value('ID_Supplier')
	  ),$this->Log());
    }
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
		$out .= "\n<SELECT NAME=\"$iName\"$htAttr MULTIPLE>";
		while ($this->NextRow()) {
		    $id = $this->ID;
		    $htShow = htmlspecialchars($this->Name);
		    $out .= "\n<OPTION value=$id>$htShow</option>";
		}
		$out .= "\n</SELECT>";
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
    /*----
      HISTORY:
	2011-02-08 adapted from VbzAdminDept::DropDown()
    */
    public function DropDown($iName=NULL,$iDefault=NULL,$iNone=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key

	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->KeyValue();
		$htShow = $this->Value('Name');
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
     }

     // -- ADMIN WEB UI -- //
}
