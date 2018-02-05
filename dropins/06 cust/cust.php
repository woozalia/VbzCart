<?php
/*
  FILE: admin.cust.php -- customer administration for VbzCart
  HISTORY:
    2010-10-16 Extracted customer classes from SpecialVbzAdmin.php
    2014-01-16 adapting as drop-in module
*/

define('KS_DESCR_IS_NULL','(none)');
define('KS_DESCR_IS_BLANK','(blank)');
define('KHT_DESCR_IS_NULL','<span style="color: grey; font-style: italic;">'.KS_DESCR_IS_NULL.'</span>');
define('KHT_DESCR_IS_BLANK','<span style="color: grey; font-style: italic;">'.KS_DESCR_IS_BLANK.'</span>');

/*::::
 CUSTOMER DATA
*/
class vctaCusts extends vctCusts implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcraCust';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CUSTOMER;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->RenderSearch();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec() {
	return $this->RenderSearch();
    } */

    // -- EVENTS -- //
    // ++ RECORDS ++ //

    public function GetRecs_forUser($idUser) {
	$sqlFilt = '(ID_User='.$idUser.') AND (ID_Repl IS NULL)';
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();
	$oSkin = $oPage->Skin();

	$sPfx = $this->ActionKey();
	$sSearchName = $sPfx.'-needle';
	$sFilterName = $sPfx.'-filter';
	$sFind = $oPage->ReqArgText($sSearchName);
	$sFilt = $oPage->ReqArgText($sFilterName);
	$doSearch = (!empty($sFind));
	$doFilter = (!empty($sFilt));
	$htFind = '"'.fcString::EncodeForHTML($sFind).'"';
	$htFilt = '"'.fcString::EncodeForHTML($sFilt).'"';

	// build forms

	$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$htSearchForm = <<<__END__
<form method=post>
  Search for names containing:
  <input name="$sSearchName" size=40 value=$htFind>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	$htFilterHdr = $oPage->SectionHeader('Filter',NULL,'section-header-sub');
	$htFilterForm = <<<__END__
<br>
<form method=get>
  Search filter:<input name="$sFilterName" width=40 value=$htFilt>
  <input type=submit name=btnFilt value="Apply">
</form>
__END__;

	$out = $htSearchHdr.$htSearchForm.$htFilterHdr.$htFilterForm;

	// do the request

	if ($doSearch) {
	    $rs = $this->Search_forText($sFind);
	    $arCols = $rs->ColumnsArray();
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      .$rs->AdminRows($arCols);
	}

	if ($doFilter) {
	    $sqlSort = NULL; // implement later
	    $rs = $this->GetData($sFilt,NULL,$sqlSort);
	    $arCols = $rs->ColumnsArray();
	    $out .= $oPage->SectionHeader('Filter Results',NULL,'section-header-sub')
	      .$rs->AdminRows($arCols);
	}

	return $out;
    }

    // -- ADMIN WEB UI -- //

}
class vcraCust extends vcrCust implements fiLinkableRecord {
    use ftLinkableRecord;
    use ftShowableRecord;
    //use ftLoggableRecord;

    // ++ BOILERPLATE HELPERS ++ //

    /*----
      HISTORY:
	2011-09-21 created for admin page (to show ID_Repl value)
	2014-07-12 simplified output so it just shows name
	  ID and address are displayed in separate columns.
	2016-06-11 Actually, why do we want the Name value to link to the current record?
	  TODO: see if there's a legit use for it under this name.
	  Replacing with NameLink().
    */
    public function SelfLink_name() {
	//$strText = $this->KeyValue().': '.$this->NameStr().' - '.$this->AddrLine();
	$sText = $this->NameString();
	$out = $this->SelfLink($sText);
	return $out;
    }
    /*----
      HISTORY:
	2011-09-21 created for cart import page (to show which cust records are redirects)
    */
    public function SelfLink_status() {
	if ($this->HasRepl()) {
	    $htOut = '<s>'.$this->SelfLink().'</s> &rarr; '.$this->SelfLink_Repl();
	} else {
	    $htOut = $this->SelfLink_name();
	}
	return $htOut;
    }
    public function SelfLink_Repl() {
	if ($this->HasRepl()) {
	    return $this->ReplObj()->SelfLink_name();
	} else {
	    return 'none';
	}
    }
    protected function SelfLink_Addr() {
	if ($this->HasAddr()) {
	    return $this->AddressRecord()->SelfLink_name();
	} else {
	    return 'n/a';
	}
    }

    // -- BOILERPLATE HELPERS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ METHOD OVERRIDES ++ //

    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    protected function AdminField($sField) {
	switch($sField) {
	  case 'ID':
	    $val = $this->SelfLink();
	    break;
	  case 'ID_Name':
	    $val = $this->NameLink();
	    break;
	  case 'ID_Addr':
	    $val = $this->SelfLink_addr();
	    break;
	  case 'email':
	    $val = $this->EmailStr();
	    break;
	  case 'crea':
	    $val = $this->Value('WhenCreated');
	    break;
	  default:
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }

    // -- METHOD OVERRIDES -- //
    // ++ CLASSES ++ //

    protected function NamesClass() {
	return KS_CLASS_CUST_NAMES_ADMIN;
    }
    protected function CardsClass() {
	return KS_CLASS_CUST_CARDS_ADMIN;
    }
    protected function CartsClass() {
	return KS_CLASS_ADMIN_CARTS;
    }
    protected function OrdersClass() {
	if (fcDropInManager::IsModuleLoaded('vbz.orders')) {
	    return KS_CLASS_ORDERS;
	} else {
	    return 'vctOrders';
	}
    }
    protected function MailAddrsClass() {
	return KS_CLASS_MAIL_ADDRS_ADMIN;
    }
    protected function EmailAddrsClass() {
	return KS_CLASS_EMAIL_ADDRS_ADMIN;
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make($this->OrdersClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    public function AddressRecord() {
	$id = $this->Value('ID_Addr');
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->MailAddrTable($id);
	}
    }
    public function NameObj() {
	throw new exception('Call NameRecord() instead of NameObj().');
	$id = $this->Value('ID_Name');
	if (is_null($id)) {
	    return NULL;
	} else {
	    return $this->NameTable($id);
	}
    }
    public function ReplObj() {
	$rc = $this->Table()->GetItem($this->Value('ID_Repl'));
	return $rc;
    }

    // -- RECORDS -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function AddrStr() {
	$rc = $this->AddressRecord();
	$ht = $rc->AsString(' / ');
	return $ht;
    }
    public function NameStr() {
	throw new exception('NameStr() deprecated; call NameString().');
    }
    public function NameString() {
	$rc = $this->NameRecord();
	if (is_object($rc)) {
	    $txt = $rc->NameString();
	    return empty($txt)?KHT_DESCR_IS_BLANK:$txt;
	} else {
	    return KHT_DESCR_IS_NULL;
	}
    }
    // String to use for page title
    protected function TitleString() {
	$id = $this->GetKeyValue();
	$rc = $this->NameRecord();
	if (is_object($rc)) {
	    $txt = $rc->NameString();
	    $idName = $rc->GetKeyValue();
	    if (empty($txt)) {
		$out = "cust ID $id name ID $idName (no name value)";
	    } else {
		$out = "cust $sName (id$id)";
	    }
	} else {
	    $out = "cust ID $id (no name set)";
	}
	return $out;
    }
    // RETURNS: HTML link to name record
    protected function NameLink() {
	$idName = $this->NameID();
	if (empty($idName)) {
	    return KHT_DESCR_IS_NULL;
	} else {
	    $rcName = $this->NameRecord();
	    return $rcName->SelfLink_name();
	}
    }
    /* 2016-06-26 If needed, move this back to the logic class
    public function AddrLine_text() {
	$obj = $this->AddressRecord();
	if (is_object($obj)) {
	    $txt = $obj->AsSingleLine();
	    return empty($txt)?KS_DESCR_IS_BLANK:$txt;
	} else {
	    return KS_DESCR_IS_NULL;
	}
    } */
    public function AddrLine() {
	$obj = $this->AddressRecord();
	if (is_object($obj)) {
	    $txt = $obj->AsSingleLine();
	    return empty($txt)?KHT_DESCR_IS_BLANK:$txt;
	} else {
	    return KHT_DESCR_IS_NULL;
	}
    }
    public function EmailStr() {
	$tbl = $this->EmailAddrTable();
	$rs = $tbl->Find_forCust($this->GetKeyValue());
	$ht = NULL;
	while ($rs->NextRow()) {
	    if (!is_null($ht)) {
		$ht .= ' ';
	    }
	    $ht .= $rs->Value('Email');
	}
	return is_null($ht)?KS_DESCR_IS_NULL:$ht;
    }
    public function HasRepl() {
	return !is_null($this->Value('ID_Repl'));
    }
    protected function HasAddr() {
	return !is_null($this->Value('ID_Addr'));
    }

    // -- FIELD CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: merge this customer's into the given customer record
      ASSUMES: idOther is a valid ID that is not the current record's ID
      RETURNS: a datascript for doing the merge
      HISTORY:
	2012-01-08 started
	2013-11-06 replacing data-scripting with transactions
    */
    protected function DoMergeInto($idOther) {
	throw new exception('2016-06-26 This will almost certainly need rewriting.');
	//$acts = new Script_Script();

	$idThis = $this->GetKeyValue();
	// always merge into the lower-numbered ID.

      // 1. ORDERS

	$arOrd = $this->Orders_array();
	$arBuyer = array(
	  'ID_Buyer'	=> $idOther
	  );
	$arRecip = array(	//$objSection = new clsWikiAdminSection($strName);

	  'ID_Recip'	=> $idOther
	  );
	$arBoth = array(
	  'ID_Buyer'	=> $idOther,
	  'ID_Recip'	=> $idOther
	  );

	if (is_array($arOrd)) {
	    $tblOrd = $this->Engine()->Orders();
	    foreach ($arOrd as $id => $row) {
		$isBuyer = ($row['ID_Buyer'] == $idThis);
		$isRecip = ($row['ID_Recip'] == $idThis);
		if ($isBuyer && $isRecip) {
		    $arUpd = $arBoth;
		} elseif ($isBuyer) {
		    $arUpd = $arBuyer;
		} else {	// is recip must be true
		    $arUpd = $arRecip;
		}
		$rcOrd = $tblOrd->SpawnItem();
		$rcOrd->Values($row);
		$acts->Add(new Script_Row_Update($arUpd,$rcOrd));
	    }
	}

      // 2. NAMES, EMAILS, PHONES, ADDRS, CARDS
      $arUpd = array('ID_Cust' => $idOther);

      $rs = $this->NameRecords();
      if ($rs->HasRows()) {
	  while ($rs->NextRow()) {
	      $rsc = $rs->RowCopy();
	      $acts->Add(new Script_Row_Update($arUpd,$rsc));
	  }
      }
      $rs = $this->Emails();
      if ($rs->HasRows()) {
	  while ($rs->NextRow()) {
	      $rsc = $rs->RowCopy();
	      $acts->Add(new Script_Row_Update($arUpd,$rsc));
	  }
      }
      $rs = $this->Phones();
      if ($rs->HasRows()) {
	  while ($rs->NextRow()) {
	      $rsc = $rs->RowCopy();
	      $acts->Add(new Script_Row_Update($arUpd,$rsc));
	  }
      }
      $rs = $this->Addrs();
      if ($rs->HasRows()) {
	  while ($rs->NextRow()) {
	      $rsc = $rs->RowCopy();
	      $acts->Add(new Script_Row_Update($arUpd,$rsc));
	  }
      }

      // 3. set ID_Repl
      $arUpd = array('ID_Repl' => $idOther);
      $acts->Add(new Script_Row_Update($arUpd,$this));

      return $acts;
    }

    // -- ACTIONS -- //
    // ++ ADMIN INTERFACE ++ //

    public function ColumnsArray() {
	$arF = array(
	  'ID'		=> 'ID',
	  'ID_Name'	=> 'Name',
	  'ID_Addr'	=> 'Addr',
	  'email'	=> 'Email',
	  'crea'	=> 'When Created',
	);
	return $arF;
    }
    protected function AdminRows_settings_columns() {
	throw new exception('2017-04-16 Is this actually being called?');
	return $this->ColumnsArray();	// seems likely to be a good default
    }
    /*
    protected function AdminRows_start() {
	$ht = 'You have <b>'.$qRows.'</b> customer profile'.Pluralize($qRows).':'
	  . "\n<table class=listing>";
	return $ht;
    }
    protected function AdminField($sField) {
	switch ($sField) {
	  case 'name':
	    return $this->NameStr();
	    break;
	  case 'email':
	    return $this->EmailStr();
	    break;
	  case 'addr':
	    return $this->AddrLine();
	    break;
	  case 'crea':
	    return $this->Value('WhenCreated');
	    break;
	  default:
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    */
    /*----
      ACTION: Render all records in set as a table
    */
    /*
    public function Render_asTable() {
	if ($this->HasRows()) {
	    $qRows = $this->RowCount();
	    $ht = 'You have <b>'.$qRows.'</b> customer profile'.Pluralize($qRows).':';
	    $ht .= "\n<table class=listing>\n<tr><th>Name</th><th>Email(s)</th><th>Address</th><th>When Created</th></tr>";
	    while ($this->NextRow()) {
		$ht .= "\n<tr>".$this->Render_asTableRow().'</tr>';
	    }
	    $ht .= "\n</table>";
	    return $ht;
	} else {
	    return NULL;
	}
    }
    protected function Render_asTableRow() {
	$ht =
	  '<td>'.$this->NameStr().'</td>'
	  .'<td>'.$this->EmailStr().'</td>'
	  //.'<td>'.$this->AddrStr().'</td>'
	  .'<td>'.$this->AddrLine().'</td>'
	  .'<td>'.$this->Value('WhenCreated').'</td>';
	return $ht;
    }*/
    /*----
      ACTION: displays a form for merging this customer's data with another customer record,
	and for handling the data returned by that form.
      HISTORY:
	2012-01-08 started
    */
    protected function MergeForm() {
	global $wgOut,$wgRequest;

	$idThis = $this->GetKeyValue();

	$doPreview = $wgRequest->GetBool('btnMergePreview');
	$doFinish = $wgRequest->GetBool('btnMergeFinish');
	if ($doPreview || $doFinish) {
	    $idOther = $wgRequest->GetIntOrNull('custID');
	    if ($idThis > $idOther) {
		$idSrce = $idThis;
		$idDest = $idOther;
	    } elseif ($idThis < $idOther) {
		$idSrce = $idOther;
		$idDest = $idThis;
	    }

	    $ok = TRUE;
	    if ($doPreview) {
		if (is_null($idOther)) {
		    $out = 'Please enter an ID number to merge with.';
		    $ok = FALSE;
		} elseif ($idThis == $idOther) {
		    $out = 'You have entered the ID of the current customer record. Please enter a different one to merge with.';
		    $ok = FALSE;
		}
	    }

	    if ($ok) {
		$rcSrce = $this->Table()->GetItem($idSrce);
		$rcDest = $this->Table()->GetItem($idDest);
		$htSrce = $rcSrce->AdminLink_status();
		$htDest = $rcDest->AdminLink_status();
		$strEvent = "Merging customer ID $htSrce into ID $htDest - ";

		$acts = $rcSrce->DoMergeInto($idDest);

		if ($doFinish) {
		    $arEv = array(
		      'descr'	=> $strEvent,
		      'where'	=> __METHOD__,
		      'code'	=> 'UPD'
		      );
		    $this->StartEvent($arEv);	// log that we're attempting a change
		    $out = $acts->Exec(TRUE);
		    $this->FinishEvent();
		} else {
		    $out = '<br>'.$acts->Exec(FALSE);
		}

		$wgOut->AddHTML($out); $out = '';
	    }
	}

	$out =  '<form method=post>';
	if ($doPreview) {
	    // ID entered, simulating changes:
	    $out .= $strEvent
	      ."<input type=hidden name=custID value=$idOther>"
	      .'<input type=submit name=btnMergeFinish value="Merge Now">';
	} elseif ($doFinish) {
	    $out .= $strEvent.'done.';
	} else {
	    // first form -- ask for ID to merge with:
	    $out .= 'Merge customer ID '.$idThis.' with ID '
	      .'<input size=5 name=custID>'
	      .'<input type=submit name=btnMergePreview value="Preview Changes...">';
	}
	$out .= '</form>';

	$wgOut->AddHTML($out); $out = '';
    }
    /*
      TO DO: WhenChanged is not updated when record is edited
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$out = NULL;

	$doAct = $oPage->PathArg('do');
	//$doEdit = $oPage->PathArg('edit');
	$doEdit = ($doAct == 'edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$doMerge = ($doAct == 'merge');

	if ($doMerge) {
	    $this->MergeForm();
	}

	$frm = $this->PageForm();
	
	if ($doSave) {
	    // NOTE: This does not handle saving new records. Not sure if it will need to.
	    $frm->Save();
	    $sMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsg);
	}

	// calculate page title
	if ($this->IsNew()) {
	    $sTitle = 'New Customer';
	} else {
	    $sTitle = $this->TitleString();
//	    $id = $this->KeyValue();
//	    if (empty($sName)) {
//		$sTitle = 'Unnamed Customer ID '.$id;
//	    } else {
//		$sTitle = "cust $sName (id$id)";
//	    }
	}

	// set up titlebar menu
	$arActs = array(
	  // 		array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  new clsActionLink_option(array(),'merge',	'do'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->Skin()->SetPageTitle($sTitle);

	// 2016-06-11 new version starts here

	if ($this->IsNew()) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();

	if ($doEdit) {
	    $out .= "\n<form method=post>";

	} else {
	    $arCtrls['ID_Name'] = $this->NameLink();
	    $arCtrls['ID_Addr'] = $this->SelfLink_addr();
	    $arCtrls['ID_Repl'] = $this->SelfLink_Repl();
	}
	
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '</form>';
	}
	$out .= $this->AdminPage_dependents($doAct);

	    // 2016-06-11 new version ends here
	    
	    /* 2016-06-11 old version

	    $strAddr = $this->AddrLine();

	    $sDo = $oPage->PathArg('do');
	    $doEdit = ($sDo == 'edit');
	    $id = $this->KeyValue();

	    // get editable or non-editable formatted values for each field

	    if ($doEdit) {
		$out .= "\n<form method=post>";

		$frmPage = $this->PageForm();
		$ftName = $frmPage->RenderControl('ID_Name');
		$ftAddr = $frmPage->RenderControl('ID_Addr');
		$ftRepl = $frmPage->RenderControl('ID_Repl');
		$ftNotes = $frmPage->RenderControl('Notes');
	    } else {
		$ftName = $this->NameLink();
		$ftAddr = $this->SelfLink_addr();
		$ftRepl = $this->SelfLink_Repl();
		$ftNotes = $this->Value('Notes');
	    }
	    $strWhenCre = $this->Value('WhenCreated');
	    $strWhenChg = $this->Value('WhenChanged');
	    $out .= "\n<table>";
	    if ($doEdit || $this->HasRepl()) {
		$out .= "\n<tr style=\"background: #ffff88;\"><td align=right><b>Repaced by</b>:</td><td>$ftRepl</td></tr>";
	    }
	    $out .= <<<__END__
<tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>
<tr><td align=right><b>Address</b>:</td><td>$ftAddr</td></tr>
<tr><td align=right><b>Created</b>:</td><td>$strWhenCre</td></tr>
<tr><td align=right><b>Changed</b>:</td><td>$strWhenChg</td></tr>
<tr><td align=right><b>Notes</b>:</td><td>$ftNotes</td></tr>
</table>
__END__;
	    if ($doEdit) {
		$out .= '<input type=submit name="btnSave" value="Save">';
		$out .= '<input type=reset value="Reset">';
		$out .= '</form>';
	    }

	    //$id = $this->KeyValue();
	    //$sfx = ' for customer ID='.$id;

	    $out .= $this->AdminPage_dependents();
	} else {
	    $id = $this->KeyValue();
	    $out = "No data found for ID=$id.";
	    throw new exception('This should not happen.');
	}
	*/
	
	$out .= '<hr><span class=footer-stats>generated by '.__FILE__.' line '.__LINE__.'</span>';
	return $out;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-09-21 adapred from VbzAdminTitle for VbzAdminCust
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	    $frm = new fcForm_DB($this);

	    $oField = new fcFormField_Num($frm,'ID_Name');
	      $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->NameRecords());
		$oCtrl->AddChoice(NULL,'-- none --');
	    $oField = new fcFormField_Num($frm,'ID_Addr');
	      $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->AddrRecords(FALSE));	// only list active addresses
		$oCtrl->AddChoice(NULL,'-- none --');
		// TODO: Detect if current selection is inactive, and add it to the list if so
	    $oField = new fcFormField_Num($frm,'ID_Repl');
		// manual ID entry, at least for now
	    $oField = new fcFormField_Time($frm,'WhenCreated');
	    $oField = new fcFormField_Time($frm,'WhenChanged');
	    $oField = new fcFormField_Time($frm,'WhenUpdated');
	    $oField = new fcFormField_Text($frm,'Notes');
	      $oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));
	    
	    /* old version
	    // create fields & controls
	    $objForm->AddField(new clsFieldNum('ID_Name'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_Addr'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_Repl'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea());
	    */

	    $this->frmPage = $frm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=listing>
<tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
<tr><td align=right><b>Replaced by</b>:</td><td>[[ID_Repl]]</td></tr>
<tr><td align=right><b>Name</b>:</td><td>[[ID_Name]]</td></tr>
<tr><td align=right><b>Address</b>:</td><td>[[ID_Addr]]</td></tr>
<tr><td align=right><b>Created</b>:</td><td>[[WhenCreated]]</td></tr>
<tr><td align=right><b>Changed</b>:</td><td>[[WhenChanged]]</td></tr>
<tr><td align=right><b>Updated</b>:</td><td>[[WhenUpdated]]</td></tr>
<tr><td align=right valign=top><b>Notes</b>:</td><td>[[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
	
    }
    /*----
      ACTION: render the dependent-records part of the page
	2016-06-11 This is only called from one place; I'm just splitting it off for readability.
    */
    protected function AdminPage_dependents($sDo) {
	$sWhere = __FILE__.' line '.__LINE__;
	$out = "\n<!-- vv $sWhere vv -->\n";
	
	$oPage = $this->Engine()->App()->Page();

	$rs = $this->AliasRecords();
	if ($rs->HasRows()) {
	    $out .= '<b>Aliases</b>:';
	    while ($rs->NextRow()) {
		$out .= ' '.$rs->SelfLink();
	    }
	} else {
	    $out .= '<small><i>This customer has no aliases.</i></small>';
	}
	
	$out .= $oPage->SectionHeader('Orders',NULL,'section-header-sub');
	$out .= $this->AdminOrders();

	$out .= $oPage->SectionHeader('Carts',NULL,'section-header-sub');
	$out .= $this->AdminCarts();

	// MAILING ADDRESSES
	$out .= $this->AdminAddrs($sDo);

	$out .= '<table width=100%><tr>';
	$out .= '<td valign=top bgcolor=#ccccff>';

	  // EMAIL ADDRESSES
	  $arActs = array(
	    // 		array $arData,$sLinkKey,	$sGroupKey,$sDispOff,$sDispOn,$sDescr
	    new clsActionLink_option(array(),'new-email',	'do','new',NULL,'create a new email address record'),
	    );
	  $out .= $oPage->SectionHeader('Email Addresses',$arActs,'section-header-sub');
	  $out .= $this->AdminEmails();

	$out .= '</td>';
	$out .= '<td valign=top bgcolor=#ffffcc>';

	  // PHONE NUMBERS
	  $arActs = array(
	    // 		array $arData,$sLinkKey,	$sGroupKey,$sDispOff,$sDispOn,$sDescr
	    new clsActionLink_option(array(),'new-phone',	'do','new',NULL,'create a new phone number record'),
	    );
	  $out .= $oPage->SectionHeader('Phones',$arActs,'section-header-sub');
	  $out .= $this->AdminPhones();

	$out .= '</td>';
	$out .= '<td valign=top bgcolor=#ccffcc>';

	  // CUSTOMER NAME RECORDS
	  $arActs = array(
	    // 		array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	    new clsActionLink_option(array(),'new-name',	'do','new',NULL,'create a new name record'),
	    );
	  $out .= $oPage->SectionHeader('Names',$arActs,'section-header-sub');
	  $out .= $this->AdminNames();

	$out .= '</td>';
	$out .= '</tr></table>';

	// CUSTOMER PAYMENT CARDS
	$arActs = array(
	  // 		array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'new-card',	'do','new',NULL,'create a new card record'),
	  );
	$out .= $oPage->SectionHeader('Cards',$arActs,'section-header-sub');
	$out .= $this->AdminCards();

	// SYSTEM EVENTS
	$out .= $oPage->SectionHeader('Events',NULL,'section-header-sub');
//	    $oSect = new clsWikiSection_std_page($oFmt,'Events',2);
	//$out .= $oSect->Render();
	$out .= $this->EventListing();
	
	$out .= "\n<!-- vv $sWhere vv -->\n";
	
	return $out;
    }
    /*-----
      NOTES: The fact that core_orders only records cust_name.ID rather than core_custs.ID
	makes this rather more complicated than it needs to be.
    */
    private function AdminOrders() {
	$arOrd = $this->Orders_array();

	// display results
	$out = '';
	if (is_array($arOrd)) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>#</th>
    <th>Role</th>
    <th>Status</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    $rcOrd = $this->OrderTable()->SpawnItem();
	    foreach ($arOrd as $id => $row) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$rcOrd->Values($row);
		$ftNum = $rcOrd->SelfLink_name();
		$strRoles = $row['roles'];
/*
		$strRoles = '';
		foreach ($arRoles as $type=>$cnt) {
		    $strRoles .= $type;
		}
*/

		$strPull = $rcOrd->PulledText();

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$id</td>
    <td>$ftNum</td>
    <td>$strRoles</td>
    <td>$strPull</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $out = "\nNo orders for this customer!";
	}
	return $out;
    }
    private function AdminCarts() {
	$rs = $this->CartRecords();
	$arF = array(
	  'ID'		=> 'ID',
	  'ID_Sess'	=> 'Session',
	  'ID_Order'	=> 'Order',
	  'ShipZone'	=> 'Zone',
	  'WhenCreated'	=> 'Created',
	  'WhenUpdated'	=> 'Updated',
	  'WhenOrdered'	=> 'Ordered',
	  'WhenVoided'	=> 'Voided',
	  );
	$out = $rs->AdminRows($arF);
	return $out;
    }
    /*-----
      TO DO: add "new" functionality (link exists, but no code -- copy from AdminEmails()
    */
    private function AdminAddrs($sDo) {
	$oPage = $this->Engine()->App()->Page();
	
	//$doNew = ($sDo == 'new-addr');
	$doVoided = ($oPage->PathArg('show-addr') == 'void');
	
	$sAddrKey = $this->MailAddrTable()->ActionKey();
	$arActs = array(
	  // 		array $arData,$sLinkKey,	$sGroupKey,$sDispOff,$sDispOn,$sDescr
	  //new clsActionLink_option(array(),'new-addr',	'do','new',NULL,'create a new mailing address record'),
	  new clsActionLink_option(
	    array(
	      'page'=>$sAddrKey,
	      'cust'=>$this->GetKeyValue(),
	      ),
	    'new',
	    'id',
	    NULL,
	    NULL,
	    'create a new mailing address record'
	    ),
	  new clsAction_section('show'),
	  new clsActionLink_option(array(),'void',	'show-addr','voided',NULL,'show voided records'),
	  );
	$out = $oPage->SectionHeader('Mailing Addresses',$arActs,'section-header-sub');

	$rs = $this->AddrRecords($doVoided);
	$out .= "\n<table class=listing><tr><th>ID</th><th>active</th><th>expires</th><th>abbr</th><th>Full</th></tr>";
	$isOdd = FALSE;
	while ($rs->NextRow()) {
	    $isOdd = !$isOdd;
	    $out .= $rs->AdminRow_forCust($isOdd);
	}
	$out .= "\n</table>";
	return $out;
    }
    private function AdminEmails() {
	$oPage = $this->Engine()->App()->Page();
	$tbl = $this->EmailAddrTable();

	if (clsHTTP::Request()->getBool('btnAddEmail')) {
	    $txtEmail = clsHTTP::Request()->getText('email');
	    $txtAbbr = clsHTTP::Request()->getText('abbr');
	    $isAct = clsHTTP::Request()->getBool('active');
	    $arData = array(
	      'Name'	 => SQLValue($txtAbbr),
	      'isActive' => SQLValue($isAct)
	      );
	    $this->StartEvent(__METHOD__,'+EM','Adding email address '.$txtEmail);
	    $idNew = $tbl->Make_fromData($this->GetKeyValue(),$txtEmail,$arData);
	    $this->FinishEvent();

	    $this->AdminRedirect();

	    $doAdd = FALSE;
	} else {
	    $doAdd = $oPage->PathArg('new-email');
	}

	$objRows = $this->EmailAddrRecords();

	$out = "\n<table style=\"background: #ddddff;\"><tr><th>ID</th><th>A?</th><th>abbr</th><th>Email</th></tr>";
	while ($objRows->NextRow()) {
	    $ftAct = ($objRows->isActive)?'&radic;':'x';
	    $ftAbbr = fcString::EncodeForHTML($objRows->Name);
	    $ftEmail = $objRows->AsHTML();
	    $out .= "\n<tr><td>".$objRows->SelfLink()."</td><td>$ftAct</td><td>$ftAbbr</td><td>$ftEmail</td></tr>";
	}
	if ($doAdd) {
	    $out .= '<form method=post action="'.$this->AdminURL().'">';
	    $ftAct = '<input type=checkbox name=active checked>';
	    $ftAbbr = '<input name=abbr size=4>';
	    $ftEmail = '<input name=email size=30>';
	    $out .= "\n<tr><td>new</td><td>$ftAct</td><td>$ftAbbr</td><td>$ftEmail</td>"
	      ."<td><input type=submit name=btnAddEmail value=Add></td></tr>";
	    $out .= '</form>';
	}

	$out .= "\n</table>";
	return $out;
    }
    /*----
      HISTORY:
	2012-01-08 it seems likely that this will crash when you try to save a new record,
	  as the event-logging call is wrong.
    */
    private function AdminPhones() {
	$oPage = $this->Engine()->App()->Page();

	if (clsHTTP::Request()->getBool('btnAddPhone')) {
	    $txtPhone = clsHTTP::Request()->getText('full');
	    assert('!empty($txtPhone)');
	    $txtDescr = clsHTTP::Request()->getText('descr');
	    $txtAbbr = clsHTTP::Request()->getText('abbr');
	    $isAct = clsHTTP::Request()->getBool('active');
	    $arData = array(
	      'ID_Cust'		=> $this->GetKeyValue(),
	      'Phone'		=> SQLValue($txtPhone),
	      'Name'		=> SQLValue($txtAbbr),
	      'isActive'	=> SQLValue($isAct),
	      'Descr'		=> SQLValue($txtDescr)
	      );

	    $this->StartEvent(__METHOD__,'+PH','Adding phone number '.$txtPhone);
	    $idNew = $objTbl->Make_fromData($this->GetKeyValue(),$txtPhone,$arData);
	    //$idNew = $objTbl->Make($arData);	// Make() is protected -- but would this work?
	    $this->FinishEvent();

	    $this->AdminRedirect();

	    $doAdd = FALSE;
	} else {
	    $doAdd = $oPage->PathArg('new-phone');
	}

	$rs = $this->PhoneNumberRecords();

	$doTbl = $rs->HasRows() || $doAdd;
	if ($doTbl) {
	    $out = <<<__END__
<table class=listing style="background: #ffffdd;">
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>abbr</th>
    <th>Phone</th>
    <th>Description</th>
  </tr>
__END__;
	    while ($rs->NextRow()) {
		$ftAct = ($rs->isActive())?'&radic;':'x';
		$ftAbbr = fcString::EncodeForHTML($rs->NameString());
		$ftFull = fcString::EncodeForHTML($rs->PhoneString());
		$ftDescr = fcString::EncodeForHTML($rs->Description());
		$htID = $rs->SelfLink();
		$out .= <<<__END__
  <tr>
    <td>$htID</td>
    <td>$ftAct</td>
    <td>$ftAbbr</td>
    <td>$ftFull</td>
    <td>$ftDescr</td>
  </tr>
__END__;
	    }
	    if ($doAdd) {
		$out .= '<form method=post>';
		$ftAct = '<input type=checkbox name=active checked>';
		$ftAbbr = '<input name=abbr size=4>';
		$ftFull = '<input name=full size=15>';
		$ftDescr = '<input name=descr size=30>';
		$out .= <<<__END__
  <tr>
    <td>new</td>
    <td>$ftAct</td>
    <td>$ftAbbr</td>
    <td>$ftFull</td>
    <td>$ftDescr</td>
    <td><input type=submit name=btnAddPhone value=Add></td>
  </tr>
__END__;
		$out .= '</form>';
	    }
	    $out .= "\n</table>";
	} else {
	    $out = "\n<i>none</i>";
	}
	return $out;
    }
    /*----
      HISTORY:
	2012-01-08 adapted from AdminPhones()
	  Also, it seems likely that this will crash when you try to save a new record,
	  as the event-logging call is wrong.
    */
    private function AdminNames() {
	if (clsHTTP::Request()->getBool('btnAddName')) {
	    $txtFull = clsHTTP::Request()->getText('full');
	    assert('!empty($txtFull)');
	    $txtSrch = $objTbl->Searchable();	// this might not work
	    $isAct = clsHTTP::Request()->getBool('active');
	    $arData = array(
	      'ID_Cust'		=> $this->GetKeyValue(),
	      'Name'		=> SQLValue($txtFull),
	      'NameSrch'	=> SQLValue($txtSrch),
	      'isActive'	=> SQLValue($isAct),
	      );

	    $this->StartEvent(__METHOD__,'+NA','Adding customer name '.$txtFull);
	    $idNew = $objTbl->Make_fromData($this->ID,$txtFull,$arData);
	    //$idNew = $objTbl->Make($arData);	// Make() is protected -- but would this work?
	    $this->FinishEvent();

	    $this->AdminRedirect();

	    $doAdd = FALSE;
	} else {
	    $oPage = $this->Engine()->App()->Page();
	    $doAdd = $oPage->PathArg('new-phone');
	}

	$rs = $this->NameRecords();
	$out = "\n<table style=\"background: #ddffdd;\"><tr><th>ID</th><th>A?</th><th>Name</th><th>Search</th></tr>";
	while ($rs->NextRow()) {
	    $ftAct = ($rs->isActive())?'&radic;':'x';
	    $ftFull = fcString::EncodeForHTML($rs->NameString());
	    $ftSrch = fcString::EncodeForHTML($rs->NameSearchable());
	    $out .= "\n<tr><td>".$rs->SelfLink()."</td><td>$ftAct</td><td>$ftFull</td><td>$ftSrch</td></tr>";
	}
	if ($doAdd) {
	    $out .= '<form method=post>';
	    $ftAct = '<input type=checkbox name=active checked>';
	    $ftFull = '<input name=full size=15>';
	    $out .= "\n<tr><td>new</td><td>$ftAct</td><td>$ftFull</td>"
	      ."<td><input type=submit name=btnAddName value=Add></td></tr>"
	      .'</form>';
	}
	$out .= "\n</table>";
	return $out;
    }
    /*----
      HISTORY:
	2012-01-08 it seems likely that this will crash when you try to save a new record,
	  as the event-logging call is wrong.
	2017-03-27 Did a partial update, but more will be needed.
    */
    private function AdminCards() {
	$db = $this->GetConnection();
	$oFormIn = fcHTTP::Request();
	if ($oFormIn->getBool('btnAddCard')) {
	    $txtAbbr = $oFormIn->getText('abbr');
	    $isAct = $oFormIn->getBool('active');
	    $strInv = $oFormIn->getText('WhenInv');
	    $txtNum = $oFormIn->getText('CardNum');
	    $txtExp = $oFormIn->getText('CardExp');
	    $sqlExp = vctCustCards::ExpDateSQL($txtExp);
	    $idAddr = $oFormIn->getInt('idAddr');
	    $idName = $oFormIn->getInt('idName');
	    $txtNotes = $oFormIn->getText('notes');
	    $arData = array(
	      'Name'	 => $db->Sanitize_andQuote($txtAbbr),
	      'isActive' => $db->Sanitize_andQuote($isAct),
	      'Notes'	=> $db->Sanitize_andQuote($txtNotes),
	      'ID_Addr'	=> $db->Sanitize_andQuote($idAddr),
	      'ID_Name'	=> $db->Sanitize_andQuote($idName),
	      'CardExp' => $db->Sanitize_andQuote($sqlExp)
	      );
	    $txtSafe = vctCustCards::SafeDescr_Short($txtNum,$txtExp);
	    $objPay = new clsPayment();
	      $objPay->MakeAddr($idAddr);
	      $objPay->MakeNum($txtNum);
	      $objPay->MakeExp($txtExp);
	    $this->StartEvent(__METHOD__,'+CC','Adding credit card '.$txtSafe);
	    $idNew = $objTbl->Make($this->ID,$objPay);
	    $this->FinishEvent();

	    $doAdd = FALSE;
	} else {
	    $oPage = $this->Engine()->App()->Page();
	    $doAdd = $oPage->PathArg('new-card');
	}

	$rs = $this->CardRecords();
	$out = <<<__END__
<table>
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>abbr</th>
    <th>Number/Exp</th>
    <th>Address</th>
    <th>Notes</th>
  </tr>
__END__;
	while ($rs->NextRow()) {
	    $ftAct = ($rs->isActive)?'&radic;':'x';
	    $ftAbbr = fcString::EncodeForHTML($rs->NameString());
	    $ftFull = fcString::EncodeForHTML(
	      vctCustCards::SafeDescr_Short(
		$rs->CardNumber(),$rs->CardExpiry()
		)
	      );
	    $ftNotes = fcString::EncodeForHTML($rs->NotesText());
	    $out .= "\n<tr>"
	      ."<td>".$rs->SelfLink()."</td>"
	      ."<td>$ftAct</td>"
	      ."<td>$ftAbbr</td>"
	      ."<td>$ftFull</td>"
	      ."<td></td>"	// address -- to be implemented
	      ."<td>$ftNotes</td></tr>";
	}
	if ($doAdd) {
	    $out .= '<form>';
	    $ftAct = '<input type=checkbox name=active checked>';
	    $ftAbbr = '<input name=abbr size=4>';
	    $ftFull = '<input name=CardNum size=20>x<input name=CardExp size=5>';
	    $ftNotes = '<input name=descr size=30>';
	    $out .= "\n<tr><td>new</td><td>$ftAct</td><td>$ftAbbr</td><td>$ftFull</td><td></td><td>$ftNotes</td>"
	      ."<td><input type=submit name=btnAddCard value=Add></td></tr>";
	    $out .= '</form>';
	}
	$out .= "\n</table>";
	return $out;
    }

    // -- ADMIN INTERFACE -- //
}
