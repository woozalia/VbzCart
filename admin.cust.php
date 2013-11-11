<?php
/*
  FILE: admin.cust.php -- customer administration for VbzCart
  HISTORY:
    2010-10-16 Extracted customer classes from SpecialVbzAdmin.php
*/
/*
clsLibMgr::Add('vbz.cust',	KFP_LIB_VBZ.'/base.cust.php',__FILE__,__LINE__);
clsLibMgr::Load('vbz.cust'	,__FILE__,__LINE__);
*/

define('KS_DESCR_IS_NULL','<span style="color: grey; font-style: italic;">(none)</span>');
define('KS_DESCR_IS_BLANK','<span style="color: grey; font-style: italic;">(blank)</span>');

/*
 CUSTOMER DATA
*/
class VbzAdminCusts extends clsCusts {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCust');
	  $this->ActionKey('cust');
    }
    public function ActionKey($iName=NULL) {
	if (!is_null($iName)) {
	    $this->ActionKey = $iName;
	}
	return $this->ActionKey;
    }
    public function GetRecs_forUser($idUser) {
	$sqlFilt = '(ID_User='.$idUser.') AND (ID_Repl IS NULL)';
	$rs = $this->GetData($sqlFilt);
	return $rs;
    }
    public function GetAddrs_forUser($idUser) {
	$sql = 'SELECT a.* FROM '
	  .$this->Table->SQLName().' AS c '
	  .'LEFT JOIN cust_addrs AS a '
	  .'ON a.ID_Cust=c.ID '
	  .'WHERE '
	    .'(c.ID_User='.$idUser.') AND '
	    .'(WhenExp IS NULL)';
	
    }
}
class VbzAdminCust extends clsCust {
    protected $objForm;

    // BOILERPLATE BEGIN
    // -- event logging (added 2011-09-21)
    /*----
      2011-10-09 for some reason, in this class we have to check for empty(logger)
	instead of !is_object(logger)
    */
    protected function Log() {
	if (empty($this->logger)) {
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
    // -- admin links
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
	2013-10-21 Pretty sure this is boilerplate, so moving it to that section.
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-03-31 copied from VbzAdminOrderTrxact to VbzAdminCust
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    /*----
      RETURNS: URL for the admin page, without any options
      HISTORY:
	2011-03-31 copied from clsAdminTopic to VbzAdminCust
    */
    public function AdminURL() {
	return clsAdminData::_AdminURL($this);
    }
    // BOILERPLATE END

    /*----
      HISTORY:
	2011-09-21 created for admin page (to show ID_Repl value)
    */
    public function AdminLink_name() {
	$strText = $this->KeyValue().': '.$this->NameStr().' - '.$this->AddrLine();
	return $this->AdminLink($strText);
    }
    /*----
      HISTORY:
	2011-09-21 created for cart import page (to show which cust records are redirects)
    */
    public function AdminLink_status() {
	if ($this->HasRepl()) {
	    $htOut = '<s>'.$this->AdminLink().'</s> &rarr; '.$this->AdminLink_Repl();
	} else {
	    $htOut = $this->AdminLink_name();
	}
	return $htOut;
    }
    /*----
      ACTION: Render all records in set as a table
    */
    public function Render_asTable() {
	if ($this->HasRows()) {
	    $qRows = $this->RowCount();
	    $ht = 'You have <b>'.$qRows.'</b> customer profile'.Pluralize($qRows).':';
	    $ht .= "\n<table>\n<tr><th>Name</th><th>Email(s)</th><th>Address</th><th>When Created</th></tr>";
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
    }
    protected function AddrStr() {
	$rc = $this->AddrObj();
	$ht = $rc->AsString(' / ');
	return $ht;
    }
  /*====
    /SECTION
  */
/* OLD EVENT LOGGING FORMAT
DOES ANYTHING USE THIS?
2011-09-21 commenting it out and replacing with boilerplate

    public function StartEvent($iWhere,$iCode,$iDescr) {
	$arEvent = array(
	  'where'	=> $iWhere,
	  'code'	=> $iCode,
	  'descr'	=> $iDescr,
	  'type'	=> $this->Table->ActionKey(),
	  'id'		=> $this->ID
	  );
	$this->idEvent = $this->objDB->Events()->StartEvent($arEvent);
    }
    public function FinishEvent() {
	$this->objDB->Events()->FinishEvent($this->idEvent);
    }
    public function EventTable() {
	$objTable = $this->objDB->Events();
	$strType = $this->Table->ActionKey();
	$objRows = $objTable->GetData('(ModType="'.$strType.'") AND (ModIndex='.$this->ID.')',NULL,'ID DESC');
	return $objRows->AdminRows();
    }
*/
    public function NameObj() {
	return $this->objDB->CustNames()->GetItem($this->Value('ID_Name'));
    }
    public function NameStr() {
	$obj = $this->NameObj();
	if (is_object($obj)) {
	    $txt = $obj->Name;
	    return empty($txt)?KS_DESCR_IS_BLANK:$txt;
	} else {
	    return KS_DESCR_IS_NULL;
	}
    }
    public function AddrObj() {
	return $this->Engine()->CustAddrs()->GetItem($this->Value('ID_Addr'));
    }
    public function AddrLine() {
	$obj = $this->AddrObj();
	if (is_object($obj)) {
	    $txt = $obj->AsSingleLine();
	    return empty($txt)?KS_DESCR_IS_BLANK:$txt;
	} else {
	    return KS_DESCR_IS_NULL;
	}
    }
    public function EmailStr() {
	$tbl = $this->Engine()->CustEmails();
	$rs = $tbl->Find_forCust($this->KeyValue());
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
    public function ReplObj() {
	$rc = $this->Table()->GetItem($this->Value('ID_Repl'));
	return $rc;
    }
    public function AdminLink_Repl() {
	if ($this->HasRepl()) {
	    return $this->ReplObj()->AdminLink_name();
	} else {
	    return 'none';
	}
    }
    /*----
      ACTION: merge this customer's into the given customer record
      ASSUMES: idOther is a valid ID that is not the current record's ID
      RETURNS: a datascript for doing the merge
      HISTORY:
	2012-01-08 started
	2013-11-06 replacing data-scripting with transactions
    */
    protected function DoMergeInto($idOther) {
	//$acts = new Script_Script();

	$idThis = $this->KeyValue();
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

      $rs = $this->Names();
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
    /*----
      ACTION: displays a form for merging this customer's data with another customer record,
	and for handling the data returned by that form.
      HISTORY:
	2012-01-08 started
    */
    protected function MergeForm() {
	global $wgOut,$wgRequest;

	$idThis = $this->KeyValue();

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
	global $wgOut,$wgRequest;
	global $vgPage;

	if ($this->hasRow()) {

	    $doAct = $vgPage->Arg('do');
	    $doEdit = $vgPage->Arg('edit');
	    $doSave = $wgRequest->GetBool('btnSave');
	    $doMerge = ($doAct == 'merge');

	    $vgPage->UseHTML();

	    if ($doMerge) {
		$this->MergeForm();
	    }

	    if ($doEdit || $doSave) {
		$this->BuildEditForm();
		if ($doSave) {
		    $ftSaveStatus = $this->AdminSave();
		}
	    }

	    $strName = $this->NameStr();
	    if ($this->IsNew()) {
		$strTitle = 'New Customer';
	    } else {
		if (empty($strName)) {
		    $strTitle = 'Unnamed Customer ID '.$this->KeyValue();
		} else {
		    $strTitle = $this->NameStr();
		}
	    }

	    $objPage = new clsWikiFormatter($vgPage);
	    //$objSection = new clsWikiAdminSection($strName);
	    $objSection = new clsWikiSection($objPage,$strTitle);
	    $objSection->ToggleAdd('edit');
	    $objSection->ActionAdd('merge');

	    $strAddr = $this->AddrLine();

	    // do the header, with edit link if appropriate
	    $doEdit = $vgPage->Arg('edit');
	    $id = $this->KeyValue();

	    $out = $objSection->Generate();
	    $wgOut->AddHTML($out); $out = '';

	    // get editable or non-editable formatted values for each field

	    if ($doEdit) {
		$out .= $objSection->FormOpen();

		$ftName = $this->objForm->Ctrl('ID_Name')->Render();
		$ftAddr = $this->objForm->Ctrl('ID_Addr')->Render();
		$ftRepl = $this->objForm->Ctrl('ID_Repl')->Render();
		$ftNotes = $this->objForm->Ctrl('Notes')->Render();
	    } else {
		$ftName = $this->NameObj()->AdminLink_name();
		$ftAddr = $this->AddrObj()->AdminLink_name();
		$ftRepl = $this->AdminLink_Repl();
		$ftNotes = $this->Value('Notes');
	    }
	    $strWhenCre = $this->Value('WhenCreated');
	    $strWhenChg = $this->Value('WhenChanged');
	    $out .= "\n<table>";
	    if ($doEdit || $this->HasRepl()) {
		$out .= "\n<tr style=\"background: #ffff88;\"><td align=right><b>Repaced by</b>:</td><td>$ftRepl</td></tr>";
	    }
	    $out .= "\n<tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>";
	    $out .= "\n<tr><td align=right><b>Address</b>:</td><td>$ftAddr</td></tr>";
	    $out .= "\n<tr><td align=right><b>Created</b>:</td><td>$strWhenCre</td></tr>";
	    $out .= "\n<tr><td align=right><b>Changed</b>:</td><td>$strWhenChg</td></tr>";
	    $out .= "\n<tr><td align=right><b>Notes</b>:</td><td>$ftNotes</td></tr>";
	    $out .= "\n</table>";

	    if ($doEdit) {
		$out .= '<input type=submit name="btnSave" value="Save">';
		$out .= '<input type=reset value="Reset">';
		$out .= '</form>';
	    }

	    $rs = $this->Aliases_rs();
	    if ($rs->HasRows()) {
		$out .= '<b>Aliases</b>:';
		while ($rs->NextRow()) {
		    $out .= ' '.$rs->AdminLink();
		}
	    } else {
		$out .= '<small><i>This customer has no aliases.</i></small>';
	    }

	    $wgOut->AddHTML($out); $out = '';

	    $id = $this->KeyValue();
	    $sfx = ' for customer ID='.$id;

	    $objSection = new clsWikiSection($objPage,'Orders','orders'.$sfx,3);
	    $out = $objSection->Generate();
	    $wgOut->AddHTML($out); $out = '';
	    $out .= $this->AdminOrders();
	    $wgOut->AddHTML($out); $out = '';


	    $objSection = new clsWikiSection($objPage,'Mailing','mailing addresses'.$sfx,3);
	    $objSection->ToggleAdd('new',NULL,'new-addr');
	    $out .= $objSection->Generate();
	    $out .= $this->AdminAddrs();

	    $out .= '<table width=100%><tr>';

	    $out .= '<td valign=top bgcolor=#ccccff>';
	    $objSection = new clsWikiSection($objPage,'Emails','email addresses'.$sfx,3);
	    $objSection->ToggleAdd('new',NULL,'new-email');
	    $out .= $objSection->Generate();
	    $out .= $this->AdminEmails();
	    $out .= '</td>';

	    $out .= '<td valign=top bgcolor=#ffffcc>';
	    $objSection = new clsWikiSection($objPage,'Phones','phone numbers'.$sfx,3);
	    $objSection->ToggleAdd('new',NULL,'new-phone');
	    $out .= $objSection->Generate();
	    $out .= $this->AdminPhones();
	    $out .= '</td>';

	    $out .= '<td valign=top bgcolor=#ccffcc>';
	    $objSection = new clsWikiSection($objPage,'Names','names used by customer'.$sfx,3);
	    $objSection->ToggleAdd('new',NULL,'new-name');
	    $out .= $objSection->Generate();
	    $out .= $this->AdminNames();
	    $out .= '</td>';

	    $out .= '</tr></table>';

	    $wgOut->AddHTML($out); $out = '';

	    $objSection = new clsWikiSection($objPage,'Cards','credit cards'.$sfx,3);
	    $objSection->ToggleAdd('new',NULL,'new-card');
	    $out = $objSection->Generate();
	    $out .= $this->AdminCards();
	    $wgOut->AddHTML($out); $out = '';

	    $wgOut->addWikiText('===Events===',TRUE);
	    $wgOut->addHTML($this->EventListing(),TRUE);
	} else {
	    $out = "No customer data has been loaded.";
	}
	$out .= '<hr><small>generated by '.__FILE__.' line '.__LINE__.'</small>';
	$wgOut->AddWikiText($out,TRUE);
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-09-21 adapred from VbzAdminTitle for VbzAdminCust
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());
	    //$objCtrls = $objForm;

	    $objForm->AddField(new clsFieldNum('ID_Name'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_Addr'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_Repl'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea());

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*-----
      NOTES: The fact that core_orders only records cust_name.ID rather than core_custs.ID
	makes this rather more complicated than it needs to be.
    */
    private function AdminOrders() {
	global $wgOut;
	global $vgPage;

	$arOrd = $this->Orders_array();

	// display results
	$out = '';
	if (is_array($arOrd)) {
	    $out = "\n{| class=sortable\n|-\n! ID || # || Role || Status";
	    $isOdd = TRUE;
	    $obj = $this->Engine()->Orders()->SpawnItem();
	    foreach ($arOrd as $id => $row) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$strNum = $row['Number'];
		$wtNum = SelfLink_WT(array('page'=>KS_URL_PAGE_ORDER,'id'=>$id),$strNum);
		$strRoles = $row['roles'];
/*
		$strRoles = '';
		foreach ($arRoles as $type=>$cnt) {
		    $strRoles .= $type;
		}
*/

		$obj->Values($row);
		$strPull = $obj->PulledText();

		$out .= "\n|- style=\"$wtStyle\"\n|$id||$wtNum||$strRoles||$strPull";
	    }
	    $out .= "\n|}";
	} else {
	    $out = "\nNo orders for this customer!";
	}
	$wgOut->AddWikiText($out,TRUE);
	return NULL;
    }
    /*-----
      TO DO: add "new" functionality (link exists, but no code -- copy from AdminEmails()
    */
    private function AdminAddrs() {
	global $vgPage;

	$objRows = $this->Addrs();
	$out = "\n<table><tr><th>ID</th><th>active</th><th>expires</th><th>abbr</th><th>Full</th></tr>";
	while ($objRows->NextRow()) {
	    $htRowCSS = is_null($objRows->WhenVoid)?'':' style=" text-decoration: line-through; color: #666666;"';
	    $ftAct = (empty($objRows->WhenAct))?'-':(date('Y-m-d',$objRows->WhenAct));
	    $ftExp = (empty($objRows->WhenExp))?'-':(date('Y-m-d',$objRows->WhenExp));
	    $ftAbbr = htmlspecialchars($objRows->Name);
	    $ftFull = $objRows->AsSingleLine();
	    $out .= "\n<tr$htRowCSS><td>".$objRows->AdminLink()."</td><td>$ftAct</td><td>$ftExp</td><td>$ftAbbr</td><td>$ftFull</td></tr>";
	}
	$out .= "\n</table>";
	return $out;
    }
    private function AdminEmails() {
	global $wgRequest;
	global $vgPage;

	$tbl = $this->objDB->CustEmails();

	if ($wgRequest->getBool('btnAddEmail')) {
	    $txtEmail = $wgRequest->getText('email');
	    $txtAbbr = $wgRequest->getText('abbr');
	    $isAct = $wgRequest->getBool('active');
	    $arData = array(
	      'Name'	 => SQLValue($txtAbbr),
	      'isActive' => SQLValue($isAct)
	      );
	    $this->StartEvent(__METHOD__,'+EM','Adding email address '.$txtEmail);
	    $idNew = $tbl->Make_fromData($this->ID,$txtEmail,$arData);
	    $this->FinishEvent();

	    $this->AdminRedirect();

	    $doAdd = FALSE;
	} else {
	    $doAdd = $vgPage->Arg('new-email');
	}

	$objRows = $this->Emails();

	$out = "\n<table style=\"background: #ddddff;\"><tr><th>ID</th><th>A?</th><th>abbr</th><th>Email</th></tr>";
	while ($objRows->NextRow()) {
	    $ftAct = ($objRows->isActive)?'&radic;':'x';
	    $ftAbbr = htmlspecialchars($objRows->Name);
	    $ftEmail = $objRows->AsHTML();
	    $out .= "\n<tr><td>".$objRows->AdminLink()."</td><td>$ftAct</td><td>$ftAbbr</td><td>$ftEmail</td></tr>";
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
	global $wgRequest;
	global $vgPage;

	if ($wgRequest->getBool('btnAddPhone')) {
	    $txtPhone = $wgRequest->getText('full');
	    assert('!empty($txtPhone)');
	    $txtDescr = $wgRequest->getText('descr');
	    $txtAbbr = $wgRequest->getText('abbr');
	    $isAct = $wgRequest->getBool('active');
	    $arData = array(
	      'ID_Cust'		=> $this->Value('ID'),
	      'Phone'		=> SQLValue($txtPhone),
	      'Name'		=> SQLValue($txtAbbr),
	      'isActive'	=> SQLValue($isAct),
	      'Descr'		=> SQLValue($txtDescr)
	      );

	    $this->StartEvent(__METHOD__,'+PH','Adding phone number '.$txtPhone);
	    $idNew = $objTbl->Make_fromData($this->ID,$txtPhone,$arData);
	    //$idNew = $objTbl->Make($arData);	// Make() is protected -- but would this work?
	    $this->FinishEvent();

	    $this->AdminRedirect();

	    $doAdd = FALSE;
	} else {
	    $doAdd = $vgPage->Arg('new-phone');
	}

	$objRows = $this->Phones();

	$doTbl = $objRows->HasRows() || $doAdd;
	if ($doTbl) {
	    $out = "\n<table style=\"background: #ffffdd;\"><tr><th>ID</th><th>A?</th><th>abbr</th><th>Phone</th><th>Description</th></tr>";
	    while ($objRows->NextRow()) {
		$ftAct = ($objRows->isActive)?'&radic;':'x';
		$ftAbbr = htmlspecialchars($objRows->Name);
		$ftFull = htmlspecialchars($objRows->Phone);
		$ftDescr = htmlspecialchars($objRows->Descr);
		$out .= "\n<tr><td>".$objRows->AdminLink()."</td><td>$ftAct</td><td>$ftAbbr</td><td>$ftFull</td><td>$ftDescr</td></tr>";
	    }
	    if ($doAdd) {
		$out .= '<form method=post action="'.$this->AdminURL().'">';
		$ftAct = '<input type=checkbox name=active checked>';
		$ftAbbr = '<input name=abbr size=4>';
		$ftFull = '<input name=full size=15>';
		$ftDescr = '<input name=descr size=30>';
		$out .= "\n<tr><td>new</td><td>$ftAct</td><td>$ftAbbr</td><td>$ftFull</td><td>$ftDescr</td>"
		  ."<td><input type=submit name=btnAddPhone value=Add></td></tr>";
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
	global $wgRequest;
	global $vgPage;

	if ($wgRequest->getBool('btnAddName')) {
	    $txtFull = $wgRequest->getText('full');
	    assert('!empty($txtFull)');
	    $txtSrch = $objTbl->Searchable();	// this might not work
	    $isAct = $wgRequest->getBool('active');
	    $arData = array(
	      'ID_Cust'		=> $this->Value('ID'),
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
	    $doAdd = $vgPage->Arg('new-phone');
	}

	$objRows = $this->Names();
	$out = "\n<table style=\"background: #ddffdd;\"><tr><th>ID</th><th>A?</th><th>Name</th><th>Search</th></tr>";
	while ($objRows->NextRow()) {
	    $ftAct = ($objRows->isActive)?'&radic;':'x';
	    $ftFull = htmlspecialchars($objRows->Name);
	    $ftSrch = htmlspecialchars($objRows->NameSrch);
	    $out .= "\n<tr><td>".$objRows->AdminLink()."</td><td>$ftAct</td><td>$ftFull</td><td>$ftSrch</td></tr>";
	}
	if ($doAdd) {
	    $out .= '<form method=post action="'.$this->AdminURL().'">';
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
    */
    private function AdminCards() {
	global $wgRequest;
	global $vgPage;

	if ($wgRequest->getBool('btnAddCard')) {
	    $txtAbbr = $wgRequest->getText('abbr');
	    $isAct = $wgRequest->getBool('active');
	    $strInv = $wgRequest->getText('WhenInv');
	    $txtNum = $wgRequest->getText('CardNum');
	    $txtExp = $wgRequest->getText('CardExp');
	    $sqlExp = clsCustCards::ExpDateSQL($txtExp);
	    $idAddr = $wgRequest->getIntOrNull('idAddr');
	    $idName = $wgRequest->getIntOrNull('idName');
	    $txtNotes = $wgRequest->getText('notes');
	    $arData = array(
	      'Name'	 => SQLValue($txtAbbr),
	      'isActive' => SQLValue($isAct),
	      'Notes'	=> SQLValue($txtNotes),
	      'ID_Addr'	=> SQLValue($idAddr),
	      'ID_Name'	=> SQLValue($idName),
	      'CardExp' => SQLValue($sqlExp)
	      );
	    $txtSafe = clsCustCards::SafeDescr_Short($txtNum,$txtExp);
	    $objPay = new clsPayment();
	      $objPay->MakeAddr($idAddr);
	      $objPay->MakeNum($txtNum);
	      $objPay->MakeExp($txtExp);
	    $this->StartEvent(__METHOD__,'+CC','Adding credit card '.$txtSafe);
	    $idNew = $objTbl->Make($this->ID,$objPay);
	    $this->FinishEvent();

	    $doAdd = FALSE;
	} else {
	    $doAdd = $vgPage->Arg('new-card');
	}

	$objRows = $this->Cards();
	$out = "\n<table><tr><th>ID</th><th>A?</th><th>abbr</th><th>Number/Exp</th><th>Address</th><th>Notes</th></tr>";
	while ($objRows->NextRow()) {
	    $ftAct = ($objRows->isActive)?'&radic;':'x';
	    $ftAbbr = htmlspecialchars($objRows->Name);
	    $ftFull = htmlspecialchars(clsCustCards::SafeDescr_Short($objRows->CardNum,$objRows->CardExp));
	    $ftNotes = htmlspecialchars($objRows->Notes);
	    $out .= "\n<tr>"
	      ."<td>".$objRows->AdminLink()."</td>"
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
}
// customer names
class clsAdminCustNames extends clsCustNames {
    /*----
      HISTORY:
	2011-04-17 added ActionKey()
    */
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminCustName');	// override parent
	  $this->ActionKey('cust.name');
    }
    /*----
      HISTORY:
	2012-01-05 adapted from VbzAdminTitles_info_Cat to clsAdminCustNames
    */
    public function SearchPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strThis = 'SearchCustName';

	$strForm = $wgRequest->GetText('form');
	$doForm = ($strForm == $strThis);

	$strField = 'txtSearch.'.$strThis;
	$strFind = $wgRequest->GetText($strField);
	$htFind = '"'.htmlspecialchars($strFind).'"';

	$vgPage->UseHTML();
	$out = "\n<h2>Customer Name Search</h2>"
	  ."\n<form method=post>"
	  ."\nSearch for:"
	  ."\n<input name=$strField size=40 value=$htFind>"
	  ."\n<input type=hidden name=form value=$strThis>"
	  ."\n<input type=submit name=btnSearch value=Go>"
	  ."\n</form>";
	$wgOut->AddHTML($out); $out = '';

	if ($doForm && !empty($strFind)) {
	    $out .= "\n<br><b>Searching customer names for</b> $htFind:<br>";
	    $wgOut->AddHTML($out); $out = '';

	    $tblMain = $this;

	    $arRows = NULL;

	    $rs = $tblMain->Search($strFind);
global $sql; $out .= 'SQL: '.$sql;
	    if ($rs->HasRows()) {
		while ($rs->NextRow()) {
		    $id = $rs->ID;
		    $arRows[$id] = $rs->Values();
		}
	    }

	    if (!is_null($arRows)) {
		$obj = $tblMain->SpawnItem();

		$out .= '<ul>';
		foreach ($arRows as $id => $row) {
		    $obj->Values($row);
		    $out .= "\n<li>".$obj->AdminLink_details();
		}
		$out .= '</ul>';
	    }

	    $wgOut->AddHTML($out); $out = '';
	}

	return $out;
    }
}
class clsAdminCustName extends clsCustName {

    // == BOILERPLATE
    /*----
      HISTORY:
	2011-09-21 added for customer admin page
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    // == boilerplate auxiliaries
    /*----
      HISTORY:
	2011-06-03 'name' -> 'cust.name'
	2011-09-21 renaming from AdminLink() to AdminLink_details() (an earlier note had suggested doing this)
    */
    public function AdminLink_details($iCustID=NULL) {
	global $vgOut;

	$objText = $vgOut;
	$objName = $this;
	$idName = $objName->ID;
	if (is_null($iCustID)) {
	    $idCust = $objName->ID_Cust;
	} else {
	    $idCust = $iCustID;
	}

	$arCust = array('page'=>'cust','edit'=>FALSE,'id'=>$idCust);
	$arName = array('page'=>'cust.name','edit'=>FALSE,'id'=>$idName);
/*
	$htCont = $objText->SelfURL(array('page'=>'cust','edit'=>FALSE,'id'=>$idCust),TRUE);
	$htName = $objPage->SelfURL(array('page'=>'name','edit'=>FALSE,'id'=>$idName),TRUE);
*/
	$out = 
	  '[C '.$objText->SelfLink($arCust,$idCust).']'.
	  '[N '.$objText->SelfLink($arName,$idName).'] '.$objName->Name;
	return $out;
    }
    /*----
      HISTORY:
	2011-09-21 written for customer admin page
    */
    public function AdminLink_name() {
	return $this->AdminLink($this->Name);
    }
    // == INFORMATION RETRIEVAL
    public function CustObj() {
	$idCust = $this->Value('ID_Cust');
	$rc = $this->Engine()->Custs($idCust);
	return $rc;
    }
    // == USER INTERFACE
    /*----
      HISTORY:
	2012-01-04 finally implementing this
    */
    public function AdminPage() {
	//return 'To be written - see '.__FILE__.':'.__LINE__;

	global $wgOut,$wgRequest;
	global $vgOut,$vgPage;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->getVal('btnSave');
	$isNew = $this->IsNew();
	$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	$id = $this->KeyValue();
	$strName = $this->Value('Name');

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,'Customer: '.$strName.' ('.$id.')');
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();

	$wgOut->AddHTML($out); $out = '';

	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$this->AdminSave();
//	    $this->Reload();	// we want to see the new values, not the ones already loaded
	    }
	}

	$ftID = $this->AdminLink();
	$ftSrch = $this->Value('NameSrch');

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ftName	= $objForm->Render('Name');
	    $ftCust	= $objForm->Render('ID_Cust');
	    $ftActive	= $objForm->Render('isActive');
	} else {
	    $rcCust = $this->CustObj();

	    $ftName	= $this->Value('Name');
	    $ftCust	= $rcCust->AdminLink();
	    $ftActive	= NoYes($this->Value('isActive'));
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>";
	$out .= "\n<tr><td align=right><b>Customer</b>:</td><td>$ftCust</td></tr>";
	$out .= "\n<tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>";
	$out .= "\n<tr><td align=right><b>Active</b>:</td><td>$ftActive</td></tr>";
	$out .= "\n<tr><td align=center colspan=2>non-editable data</td></tr>";
	$out .= "\n<tr><td align=right><b>Searchable</b>:</td><td>$ftSrch</td></tr>";
	$out .= "\n</table>";

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

	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';

	$wgOut->AddHTML($out);
    }
    /*----
      HISTORY:
	2010-11-17 adapted from clsCtgGroup to clsAdminCustAddr
	2012-01-04 adapted from clsAdminCustAddr to clsAdminCustName
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    //$objForm->AddField(new clsField('Full'),		new clsCtrlHTML(array('size'=>60)));
	    $objForm->AddField(new clsField('ID_Cust'),		new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML(array('size'=>24)));

	    $this->objForm = $objForm;
	}
    }
}
// payment card information
class VbzAdminCustCards extends clsCustCards_dyn {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCustCard');	// override parent
	  $this->ActionKey('card');
    }
    /*----
      ACTION: Find a matching card from the given number
      RETURNS: DataSet of matching cards (should be maximum of one row)
      HISTORY:
	2011-03-23 Apparently this method existed in the past but got deleted somehow.
	  Rewriting it from scratch.
	2011-12-18 commenting out -- this function already exists in clsCustCards_dyn
    */
/*
    public function Find($iNum) {
	//$num = str_replace (array(' ','-','.'),'',$iNum);
	$num = self::Searchable($iNum);
	$rs = $this->GetData('CardNum="'.$iNum.'"');
	return $rs;
    }
*/
    public function ListPage() {
    // PURPOSE: interface for encrypting credit card data
	global $wgOut;

	$objRow = $this->GetData(NULL,NULL,'ID');
	if ($objRow->hasRows()) {
	    $out = "{| class=sortable \n|-\n! ID || Number || Expiry || CVV || encrypted";
	    $isOdd = TRUE;
	    while ($objRow->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $objRow->Row;

		$id	= $row['ID'];
		$strNum	= $row['CardNum'];
		$strExp = $row['CardExp'];
		$strCVV = $row['CardCVV'];
		$strEncr = htmlspecialchars($row['Encrypted']);

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $id || $strNum || $strExp || $strCVV || $strEncr";
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'No credit cards currently in database.';
	}
	$wgOut->addWikiText($out,TRUE);
    }
    public function DropDown_forCust($iCust,$iCard=NULL) {
	$idCust = $iCust;
	$idCard = $iCard;
	if (empty($idCust)) {
	    $out = '<i>no customer ID</i>';
	} else {
	    $objRows = $this->objDB->CustCards()->GetData('ID_Cust='.$idCust);
	    $out = $objRows->DropDown('ccard',$idCard);
	}
	return $out;
    }
    public function AdminEncrypt() {
    // PURPOSE: interface to encrypt/decrypt sensitive data
	global $wgOut,$wgRequest;

	$canDoBulk = MWX_User::Current()->CanDo('crypt.bulk');
	$canDoKeys = MWX_User::Current()->CanDo('crypt.keys');
	if ($canDoBulk || $canDoKeys) {
	    if (IsWebSecure()) {

		if ($canDoBulk) {

		    $doEncry = $wgRequest->getVal('doEncrypt');
		    $doCheck = $wgRequest->getVal('doCheck');
		    $doClear = $wgRequest->getVal('doClear');
		    $doDecry = $wgRequest->getVal('doDecrypt');

		    if ($doCheck || $doDecry) {	// do we need the private key?
			$sKeyPrv = $wgRequest->getVal('cryptKey');	// private key
			$htKeyPrv = htmlspecialchars($sKeyPrv);
		    } else {
			$htKeyPrv = NULL;
		    }

	// do the selected actions
		    //$this->CryptKey($strKey);
		    if ($doEncry) {
			$this->DoAdminEncrypt();
		    }
		    if ($doCheck) {
			$this->DoAdminCheckCrypt($sKeyPrv);
		    }
		    if ($doClear) {
			$this->DoAdminPlainClear();
		    }
		    if ($doDecry) {
			$this->DoAdminDecrypt($sKeyPrv);
		    }

		    $out = 'Select which tasks to perform. If server times out, select fewer tasks.';
		    $out .= '<form method=post>';
		    $out .= '<input type=checkbox name="doEncrypt">Encrypt sensitive data, and save results';
		    $out .= '<br><input type=checkbox name="doCheck">Verify that encrypted data matches existing plaintext - <b>need private key</b>';

		    $out .= '<br><br>If possible, you should make a local backup of your (unencrypted) data before the next step.';
		    $out .= '<br>At this point, sensitive data is available in the clear, so do <i>not</i> copy exported data over an unsecure connection .';
		    $out .= '<br>(Yes, securely exporting the affected table to a file should be a feature of this tool. Eventually.)';
		    $out .= '<br><input type=checkbox name="doClear">Clear unencrypted fields';

		    $out .= '<br><br>Do this part after exporting/migrating data:';
		    $out .= '<br><input type=checkbox name="doDecrypt">Decrypt and save as plaintext - <b>need private key</b>';

		    $out .= "<br><br>Private key (needed for decryption only): <textarea name=cryptKey cols=64 rows=5>$htKeyPrv</textarea>";
		    $out .= '<br><input type=submit name="btnGo" value="Go">';
		    $out .= '</form>';
		}

		if ($canDoKeys) {
		    $out .= '<h2>Key Generation</h2>';
		    $doKeyGen = $wgRequest->getBool('btnKeyGen');
		    if ($doKeyGen) {
			// see http://us.php.net/manual/en/book.openssl.php
			// Create the keypair
			$res = openssl_pkey_new();

			// Get private key
			openssl_pkey_export($res, $privatekey);

			// Get public key
			$publickey = openssl_pkey_get_details($res);
			$publickey = $publickey["key"];

			// generate filename for storing public key:
			$fn = date('Y-m-d His').' '.MWX_User::Current()->ShortText().'.public.key';
			// Username is included so we know at a glance who generated the public key
			  // and was therefore responsible for saving the private key.

			$fs = KFP_KEYS.'/'.$fn;
			// save the public key
			$nRes = file_put_contents($fs,$publickey);
			if ($nRes > 0) {
			    $this->Engine()->VarsGlobal()->Val('public_key.fspec',$fn);

			    $out .= '<h3>Private key</h3><b>Save this</b> in a secure location:<br><pre>'.$privatekey.'</pre>';
			    $out .= '<h3>Public key</h3>This has been saved in '.$fs.':<br><pre>'.$publickey.'</pre>';
			} else {
			    $out .= '<h3>Key Generation Error</h3>The generated public key could not be saved to the file '.$fs.'. Please check folder permissions.';
			}
		    } else {
			$out .= '<form method=post>';

			$out .= '<br><input type=submit name="btnKeyGen" value="Generate New Keys">';
			$out .= '</form>';
		    }
		}
	    } else {
		$out = '<br><span class=warning>You have permission to use this page, but you need to <a href="'.SecureURL().'">switch to https</a>.</span>';
	    }
	} else {
	    $out .= "You don't have permission to use any of the encryption utilities.";
	}

	$wgOut->AddHTML($out);
    }
    /*----
      ACTION: Encrypt data in all rows and save to Encrypted field
    */
    public function DoAdminEncrypt() {
	global $wgOut;

	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'encrypting sensitive data in ccard records',NULL,FALSE,FALSE);

	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $intChecked = 0;
	    $intChanged = 0;
	    $out = 'Encrypting credit card data in card records:';
	    $out .= "\n* ".$objRow->RowCount().' records to process';
	    $wgOut->addWikiText($out,TRUE); $out=NULL;
	    while ($objRow->NextRow()) {
		$intChecked++;
		$row = $objRow->Row;

		$strNumEncrOld = $row['Encrypted'];
		$objRow->Encrypt(TRUE,FALSE);
		$strNumEncrNew = $objRow->Encrypted;
		if ($strNumEncrOld != $strNumEncrNew) {
		    $intChanged++;
		}
	    }
	    $strStats = $intChecked.' row'.Pluralize($intChecked).' processed, ';
	    $strStats .= $intChanged.' row'.Pluralize($intChanged).' altered';
	    $objLogger->LogEvent(__METHOD__,NULL,$strStats,NULL,FALSE,FALSE);

	    $out .= "\n* $intChecked row".Pluralize($intChecked).' processed';
	    $out .= "\n* $intChanged row".Pluralize($intChanged).' altered';
	    $wgOut->addWikiText($out,TRUE); $out=NULL;

	} else {
	    $objLogger->LogEvent(__METHOD__,NULL,'CustCards: No records found to process',NULL,FALSE,FALSE);
	    $out = 'No credit cards currently in database.';
	}
	$wgOut->addWikiText($out,TRUE); $out = NULL;
	    }
    public function DoAdminCheckCrypt($iPvtKey) {
    // PURPOSE: Verify that encrypted data matches unencrypted data
    //	This was part of AdminCrypt, but it took too long to execute
	global $wgOut;
	global $vgPage;

	$vgPage->UseWiki();	// apparently this isn't set elsewhere

	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'checking encrypted data',NULL,FALSE,FALSE);

	$out = NULL;
	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $objRow = $this->GetData();
	    $intMatched = 0;
	    $intBlank = 0;
	    $intFound = 0;
	    $objRow->Reset();
	    $sBad = NULL;
	    while ($objRow->NextRow()) {
		$intFound++;
		$strPlainOld = $objRow->SingleString();
		$strEncrypted = $objRow->Encrypted;
		if (empty($strEncrypted)) {
		    $intBlank++;
		} else {
		    $objRow->Decrypt(FALSE,$iPvtKey);	// don't overwrite unencrypted data
		    $strNumEncrNew = $objRow->Encrypted;
		    $strPlainNew = $objRow->_strPlain;
		    if ($strPlainOld == $strPlainNew) {
			$intMatched++;
		    } else {
			$sBad .= "\n* ".$objRow->AdminLink().": plain=[$strPlainOld] encrypted=[$strPlainNew]";
		    }
		}
	    }
	    $out .= "* $intMatched card".Pluralize($intMatched).' match';
	    $intRows = $objRow->RowCount();
	    $intBad = $intRows - $intMatched - $intBlank;
	    if ($intBad) { 
		$strStat = $intBad.' card'.Pluralize($intBad);
		$out .= "\n\n'''ERROR''' - $strStat did NOT match!$sBad\n";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' did not match',NULL,FALSE,FALSE);
		// TO DO: If this ever happens, give list of failed cards and some sort of way to figure out what went wrong
	    } else {
		if ($intRows == $intFound) {
		    $strStat = $intRows.' row'.Pluralize($intRows);
		    $out .= "\n\n'''OK''' - $strStat matched";
		    $objLogger->LogEvent(__METHOD__,NULL,$strStat.' matched',NULL,FALSE,FALSE);
		} else {
		    $strStat = $intRows.' row'.Pluralize($intRows).' detected, but only '.$intFound.' row'.Pluralize($intFound).' checked';
		    $out .= "\n\n'''ERROR''' - $strStat!";
		    $objLogger->LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
		}
	    }
	    if ($intBlank) {
		$strStat = $intBlank.' row'.Pluralize($intBlank);
		$out .= "\n<br>'''NOTE''': $strStat have not been encrypted!";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' are not yet encrypted',NULL,FALSE,FALSE);
	    }
	} else {
	    $out = 'No credit cards currently in database.';
	    $objLogger->LogEvent(__METHOD__,NULL,'no data to encrypt',NULL,FALSE,FALSE);
	}
	$wgOut->addWikiText($out,TRUE);
    }
    public function DoAdminPlainClear() {
	global $wgOut;

    // ACTION: Clear plaintext data for all rows that have encrypted data
	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'clearing unencrypted card data',NULL,FALSE,FALSE);

	$arUpd = array(
	  'CardNum' => 'NULL',
	  'CardExp' => 'NULL',
	  'CardCVV' => 'NULL'
	  );
	$this->Update($arUpd,'Encrypted IS NOT NULL');
	$intRows = $this->objDB->RowsAffected();
	$strStat = $intRows.' row'.Pluralize($intRows).' modified';
	$out = "\n\n'''OK''': $strStat";
	$wgOut->addWikiText($out,TRUE); $out=NULL;


	$objLogger->LogEvent(__METHOD__,NULL,'plaintext data cleared from card records, '.$strStat,NULL,FALSE,FALSE);
    }
    public function DoAdminDecrypt($iPvtKey) {
	global $wgOut;

	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'decrypting data',NULL,FALSE,FALSE);

	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $out = "\n\nDecrypting cards: ";
	    $intFound = 0;
	    while ($objRow->NextRow()) {
		$intFound++;
		$objRow->Decrypt(TRUE,$iPvtKey);	// decrypt and save
		$strNumEncrNew = $objRow->Encrypted;
	    }
	    $intRows = $objRow->RowCount();
	    $intMissing = $intRows - $intFound;
	    if ($intMissing) {
		$strStat = $intFound.' row'.Pluralize($intFound).' out of '.$intRows.' not decrypted!';
		$out .= "'''ERROR''' - $strStat!";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
	    } else {
		$strStat = $intRows.' row'.Pluralize($intRows);
		$out .= "'''OK''' - $strStat decrypted successfully";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' decrypted successfully',NULL,FALSE,FALSE);
	    }
	    $wgOut->addWikiText($out,TRUE);
	} else {
	    $wgOut->addWikiText('No credit cards to decrypt!',TRUE);
	}
    }
}
class VbzAdminCustCard extends clsCustCard {
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      HISTORY:
	2011-02-16 Replaced existing code with boilerplate/helper code
    */
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
    public function DropDown($iName,$iWhich=NULL) {
	$out = '<select name="'.$iName.'">';
	while ($this->NextRow()) {
	    if ($this->ID == $iWhich) {
		$htSelect = " selected";
	    } else {
		$htSelect = '';
	    }
	    $out .= '<option'.$htSelect.' value="'.$this->ID.'">'.$this->SafeString().'</option>';
	}
	$out .= '</option>';
	return $out;
    }
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgOut,$vgPage;

	if ($wgRequest->getVal('btnSave')) {
	    $this->AdminSave();	// save edit
	    $this->Reload();	// we want to see the new values, not the ones already loaded
	}

	$doEdit = $vgPage->Arg('edit');
	$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	$id = $this->ID;

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,'Credit Card ID '.$id);
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();

	$objCust = $this->Cust();
	$objAddr = $this->Addr();
	$objName = $this->Name();

	$id = $this->ID;

	$ftTagVal = htmlspecialchars($this->Name);
	$ftNumVal = htmlspecialchars($this->CardNum);
	if ($this->CardExp == '') {
	    $ftExpVal = '';
	} else {
	    $utExpVal = strtotime($this->CardExp);
	    $ftExpVal = date('n/y',$utExpVal);
	}
	$ftCVVVal = $this->CardCVV;
	$ftOwnVal = htmlspecialchars($this->OwnerName);
	$ftAddrVal = htmlspecialchars($this->Address);
	$ftNotes = htmlspecialchars($this->Notes);

	if ($doEdit) {
	    $out .= '<form method=post action="'.$htPath.'">';
	    $ftTag = '<input name="tag" size=5 value="'.$ftTagVal.'">';
	    $ftCust = '<input name="cust" size=5 value="'.$this->ID_Cust.'">';	// LATER: drop-down
	    $ftAddr = '<input name="addr" size=5 value="'.$this->ID_Addr.'">';	// LATER: drop-down
	    $ftName = '<input name="name" size=5 value="'.$this->ID_Name.'">';	// LATER: drop-down
	    $ftNum = '<input name="num" size=19 value="'.$ftNumVal.'">';
	    $ftExp = '<input name="exp" size=5 value="'.$ftExpVal.'">';
	    $ftCVV = ' CVV <input name="cvv" size=3 value="'.$ftCVVVal.'">';
	    $ftOwnName = '<input name="owner" size=30 value="'.$ftOwnVal.'">';
	    $ftAddrTxt = '<textarea name="addrtxt" height=3 width=20>'.$ftAddrVal.'</textarea>';
	    $ftNotes = '<textarea name="notes" height=3 width=40>'.$ftNotes.'</textarea>';
	} else {
	    $ftTag = $ftTagVal;
	    $ftCust = $objCust->AdminLink_name();
	    $ftAddr = $objAddr->AdminLink($objAddr->AsSingleLine(' / '));
	    $ftName = $objName->AdminLink($objCust->KeyValue());
	    $ftNum = $ftNumVal;
	    $ftExp = $ftExpVal;
	    $ftCVV = empty($ftCVVVal)?'':' CVV '.$ftCVVVal;
	    $ftOwnName = $ftOwnVal;
	    $ftAddrTxt = '<pre>'.$ftAddrVal.'</pre>';
	    $ftNotes = $ftNotes;
	}
	$out .= '<table>';
	$out .= '<tr><td align=right><b>ID</b>:</td><td>'.$id.'</td></tr>';
	$out .= '<tr><td align=right><b>Tag</b>:</td><td>'.$ftTag.'</td></tr>';
	$out .= '<tr><td align=right><b>Customer</b>:</td><td>'.$ftCust.'</td></tr>';
	$out .= '<tr><td align=right><b>Address</b>:</td><td>'.$ftAddr.'</td></tr>';
	$out .= '<tr><td align=right><b>Name</b>:</td><td>'.$ftName.'</td></tr>';
	$out .= '<tr><td align=right><b>Number</b>:</td><td>'.$ftNum.' x '.$ftExp.$ftCVV.'</td></tr>';
	$out .= '<tr><td align=right><b>Owner Name</b>:</td><td>'.$ftOwnName.'</td></tr>';
	$out .= '<tr><td align=right><b>Address Text</b>:</td><td>'.$ftAddrTxt.'</td></tr>';
	$out .= '<tr><td colspan=2><b>Notes</b>:<br>'.$ftNotes.'</td></tr>';
	$out .= '</table>';
	if ($doEdit) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '<input type=reset value="Revert">';
	    $out .= '<input type=submit name=btnCancel value="Cancel">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out); $out = '';
	$wgOut->addWikiText('===Events===',TRUE);
	$wgOut->addWikiText($this->EventListing(),TRUE);
	$out = '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';
	$wgOut->AddHTML($out); $out = '';
    }
/* 2010-10-25 wrote this accidentally. Try it sometime.
    public function AdminPage() {
	global $vgPage,$vgOut;

	if (is_null($this->WhenXmitted)) {
	    $strActDescr = 'Process ';
	} else {
	    if (is_null($this->WhenDecided)) {
		$strActDescr = 'Confirm ';
	    } else {
		$strActDescr = '';
	    }
	}
	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Credit Card Charge',NULL,3);
	//$objSection->ToggleAdd('edit','edit image records','edit.img');
	$out .= $objSection->Generate();

	$strCard = $this->CardTypeName().' '.$this->CardNum.$vgOut->Italic(' exp ').$this->ShortExp();
	$out .= $vgOut->TableOpen();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Customer ID</b>:','align=right');
	  $out .= $vgOut->TblCell($this->CustObj()->AdminLink());
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Name on card</b>:','align=right');
	  $out .= $vgOut->TblCell($this->OwnerName);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('record:','align=right');
	  $out .= $vgOut->TblCell($this->NameObj()->AdminLink());
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Number</b>:','align=right');
	  $out .= $vgOut->TblCell($strCard);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Address</b>:','align=right');
	  $out .= $vgOut->TblCell($this->Address);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('record:','align=right');
	  $out .= $vgOut->TblCell($this->AddrObj()->AdminLink());
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TableShut();

	$vgOut->AddText($out);
    }
*/
    public function AdminSave() {
	global $wgRequest;

      // capture form input
	$txtTag = $wgRequest->GetText('tag');
	$idCust = $wgRequest->GetIntOrNull('cust');
	$idAddr = $wgRequest->GetIntOrNull('addr');
	$idName = $wgRequest->GetIntOrNull('name');
	$txtNum = $wgRequest->GetText('num');
	$txtExp = $wgRequest->GetText('exp');
	$dtExp = clsCustCards::ExpDate($txtExp);
	$sqlExp = is_object($dtExp)?($dtExp->Format('Y-m-d')):'';
	$txtCVV = $wgRequest->GetText('cvv');
	$txtOwnName = $wgRequest->GetText('owner');
	$txtAddrTxt = $wgRequest->GetText('addrtxt');
	$txtNotes = $wgRequest->GetText('notes');
	
      // build update request
	$arUpd = array(
	  'Name'	=> SQLValue($txtTag),
	  'ID_Cust'	=> SQLValue($idCust),
	  'ID_Addr'	=> SQLValue($idAddr),
	  'ID_Name'	=> SQLValue($idName),
	  'CardNum'	=> SQLValue($txtNum),	// LATER: strip out punctuation
	  'CardExp'	=> SQLValue($sqlExp),
	  'CardCVV'	=> SQLValue($txtCVV),
	  'OwnerName'	=> SQLValue($txtOwnName),
	  'Address'	=> SQLValue($txtAddrTxt),
	  'Notes'	=> SQLValue($txtNotes)
	  );
	$strDescr = 'admin edit';
	if (!empty($txtNotes)) {
	    $strDescr .= ': '.$txtNotes;
	}
	$arEv = array(
	  'descr'	=> $strDescr,
	  'where'	=> __METHOD__,
	  'code'	=> 'UPD'
	  );
	$this->StartEvent($arEv);	// log that we're attempting a change
	$this->Update($arUpd);		// attempt the edit
	$this->FinishEvent();		// log completion
    }
    public function Cust() {
	$doLoad = TRUE;
	if (isset($this->objCust)) {
	    if ($this->objCust->ID == $this->ID_Cust) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objCust = $this->objDB->Custs()->GetItem($this->ID_Cust);
	}
	return $this->objCust;
    }
    public function Addr() {
	$doLoad = TRUE;
	if (isset($this->objAddr)) {
	    if ($this->objAddr->ID == $this->ID_Addr) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objAddr = $this->objDB->CustAddrs()->GetItem($this->ID_Addr);
	}
	return $this->objAddr;
    }
    public function Name() {
	$doLoad = TRUE;
	if (isset($this->objName)) {
	    if ($this->objName->ID == $this->ID_Name) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objName = $this->objDB->CustNames()->GetItem($this->ID_Name);
	}
	return $this->objName;
    }
}
class clsAdminCustAddrs extends clsCustAddrs {
    /*----
      HISTORY:
	2011-04-17 added ActionKey()
    */
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminCustAddr');
	  $this->ActionKey('addr');
    }
/*
2011-03-23 started writing this, then found where the missing code was
    public function Make_fromCartAddr(clsCartAddr $iAddr) {
	$sqlAddrSave = SQLValue($iAddr->AsText());
	$sqlAddrFind = SQLValue($iAddr->AsSearchable());
	$sqlNameSave = SQLValue($iAddr->Name());

	die('to be written');
	$arMake = array(
	  'Full'	=> $sqlStrAddrSave,
	  'Name'	=> $sqlStrNameSave,
	  'Search'	=> $sqlStrAddrFind
	  );
	$sqlFind = 'Search='
	$this->Make($arMake,$strAddr
    }
*/
}
class clsAdminCustAddr extends clsCustAddr {
    protected $objForm;

    //*** BOILERPLATE begin
    /*====
      SECTION: event logging
      HISTORY:
	2011-09-02 adding boilerplate event logging using helper class
    */
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
    /*=====
      SECTION: admin links
    */
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
	2011-09-02 Writing AdminPage()
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-03-31 copied from VbzAdminOrderTrxact to VbzAdminCust
	2012-01-03 copied from VbzAdminCust to clsAdminCustAddr
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    //*** BOILERPLATE end

    public function AdminLink_name() {
	$strVal = $this->AsSingleLine();
	return $this->AdminLink($strVal);
    }

    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage,$vgOut;

	//$strAction = $vgPage->Arg('do');
	//$doAdd = ($strAction == 'add');
	$isNew = is_null($this->ID);
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');
	$strAct = $vgPage->Arg('do');

	$doVoid = ($strAct == 'void');
	if ($doVoid) {
	    $arEv = array(
	      'code'	=> 'VOID',
	      'descr'	=> 'voiding the address',
	      'where'	=> __METHOD__
	      );
	    $this->StartEvent($arEv);
	    $arUpd = array(
	      'WhenVoid'	=> 'NOW()'
	      );
	    $this->Update($arUpd);
	    $this->FinishEvent();
	    $this->AdminRedirect();
	}

	if ($isNew) {
	    $strName = 'New Address';
	} else {
	    $strName = 'Address #'.$this->ID;
	}

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strName);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	$wgOut->AddHTML($out); $out = '';

	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$this->AdminSave();
	    }
	}

	$ftID = $this->AdminLink();
	$ftFull	= $this->Value('Full');
	$ftSearch = $this->Value('Search');
	$ftSearchRaw = $this->Value('Search_raw');

	$dtWhenVoid = $this->Value('WhenVoid');
	$ctrlWhenVoid = $dtWhenVoid;

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ftName	= $objForm->Render('Name');
	    $ftCust	= $objForm->Render('ID_Cust');
	    $ftWhenAct	= $objForm->Render('WhenAct');
	    $ftWhenExp	= $objForm->Render('WhenExp');
	    //$ftFull	= $objForm->Render('Full');
	    $ftStreet	= $objForm->Render('Street');
	    $ftTown	= $objForm->Render('Town');
	    $ftState	= $objForm->Render('State');
	    $ftZip	= $objForm->Render('Zip');
	    $ftExtra	= $objForm->Render('Extra');
	    $ftCountry	= $objForm->Render('Country');
	    $ftDescr	= $objForm->Render('Descr');
	} else {
	    $ftName	= $this->Value('Name');
	    $ftCust	= $this->CustObj()->AdminLink();
	    $ftWhenAct	= $this->Value('WhenAct');
	    $ftWhenExp	= $this->Value('WhenExp');
	    //$ftFull	= $this->Value('Full');
	    $ftStreet	= $this->Value('Street');
	    $ftTown	= $this->Value('Town');
	    $ftState	= $this->Value('State');
	    $ftZip	= $this->Value('Zip');
	    $ftExtra	= $this->Value('Extra');
	    $ftCountry	= $this->Value('Country');
	    $ftDescr	= $this->Value('Descr');

	    if (!$isNew && is_null($dtWhenVoid)) {
		$vgPage->ArgsToKeep(array('page','id'));
		$ftLink = $vgPage->SelfLink(array('do'=>'void'),'void now','void this address');
		$ctrlWhenVoid = " [ $ftLink ]";
	    }
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>";
	$out .= "\n<tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>";
	$out .= "\n<tr><td align=right><b>Customer</b>:</td><td>$ftCust</td></tr>";
	$out .= "\n<tr><td align=right><b>When Active</b>:</td><td>$ftWhenAct</td></tr>";
	$out .= "\n<tr><td align=right><b>When Expires</b>:</td><td>$ftWhenExp</td></tr>";
	$out .= "\n<tr><td align=right><b>When Voided</b>:</td><td>$ctrlWhenVoid</td></tr>";
	$out .= "\n<tr><td align=right><b>Street</b>:</td><td>$ftStreet</td></tr>";
	$out .= "\n<tr><td align=right><b>Town</b>:</td><td>$ftTown</td></tr>";
	$out .= "\n<tr><td align=right><b>State</b>:</td><td>$ftState</td></tr>";
	$out .= "\n<tr><td align=right><b>Postal Code</b>:</td><td>$ftZip</td></tr>";
	$out .= "\n<tr><td align=right><b>Country</b>:</td><td>$ftCountry</td></tr>";
	$out .= "\n<tr><td align=right><b>Instructions</b>:</td><td>$ftExtra</td></tr>";
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>$ftDescr</td></tr>";
	$out .= "\n<tr><td align=right><b>Full</b>:</td><td>$ftFull</td></tr>";
	$out .= "\n<tr><td align=right><b>Searchable (raw)</b>:</td><td>$ftSearchRaw</td></tr>";
	$out .= "\n<tr><td align=right><b>Searchable</b>:</td><td>$ftSearch</td></tr>";
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

	$out .= $this->AdminPage_Lists($objPage,$isNew);

	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';

	$wgOut->AddHTML($out); $out = '';
    }
    /*----
      ACTION: Displays related list data
      HISTORY:
	2011-09-02 created
    */
    function AdminPage_Lists(clsWikiFormatter $iPage, $iIsNew) {
	$out = '';
	if (!$iIsNew) {
	    // event listing
	    $objSection = new clsWikiSection($iPage,'Events',NULL,3);
	    $out .= $objSection->Generate();
	    $out .= $this->EventListing();
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-11-17 adapted from clsCtgGroup to clsAdminCustAddr
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Cust'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldTime('WhenAct'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenExp'),	new clsCtrlHTML());
	    //$objForm->AddField(new clsField('Full'),		new clsCtrlHTML(array('size'=>60)));
	    $objForm->AddField(new clsField('Street'),		new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsField('Town'),		new clsCtrlHTML(array('size'=>24)));
	    $objForm->AddField(new clsField('State'),		new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsField('Zip'),		new clsCtrlHTML(array('size'=>14)));
	    $objForm->AddField(new clsField('Extra'),		new clsCtrlHTML_TextArea());
	    $objForm->AddField(new clsField('Country'),	new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML());

	    $this->objForm = $objForm;
	}
    }
    protected function AdminSave() {
	// update generated fields: searchable, searchable raw, full
	$arUpd = $this->CalcUpdateArray();

	$this->Value('Full',$arUpd['Full']);
	$this->Value('Search',$arUpd['Search']);
	$this->Value('Search_raw',$arUpd['Search_raw']);

	// boilerplate: save the data
	$out = $this->objForm->Save();
	return $out;
    }
}
class clsAdminCustEmails extends clsCustEmails {
    /*----
      HISTORY:
	2011-04-17 added ActionKey()
    */
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminCustEmail');
	  $this->ActionKey('cust.email');
    }
}
class clsAdminCustEmail extends clsCustEmail {
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AsHTML() {
	$txtEmail = $this->Email;
	$out = '<a href="mailto:'.$txtEmail.'">'.$txtEmail.'</a>';
	return $out;
    }
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgOut,$vgPage;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

/*
	if ($wgRequest->getVal('btnSave')) {
	    $this->AdminSave();	// save edit
	    $this->Reload();	// we want to see the new values, not the ones already loaded
	}
*/
	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}


	$doEdit = $vgPage->Arg('edit');
	$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	$id = $this->ID;

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,'Credit Card ID '.$id);
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ftSeq	= $objForm->Render('Seq');

	    return 'To be written - see '.__FILE__.':'.__LINE__;
	}
    }
}
class clsAdminCustPhones extends clsCustPhones {
    /*----
      HISTORY:
	2011-04-17 added ActionKey()
    */
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminCustPhone');
	  $this->ActionKey('cust.phone');
    }
}
class clsAdminCustPhone extends clsCustPhone {
    /* @@@@
      SECTION: BOILERPLATE - event logging
    */
    // -- event logging (added 2011-09-21)
    /*----
      2011-10-09 for some reason, in this class we have to check for empty(logger)
	instead of !is_object(logger)
    */
    protected function Log() {
	if (empty($this->logger)) {
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
    /* @@@@
      SECTION: BOILERPLATE - admin links
    */
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }

    // /boilerplate

    public function CustObj() {
	$idCust = $this->Value('ID_Cust');
	$rc = $this->Engine()->Custs($idCust);
	return $rc;
    }

    /*----
      HISTORY:
	2012-01-04 finally implementing this
	2012-04-21 adapting from Name to Phone
    */
    public function AdminPage() {
	//return 'To be written - see '.__FILE__.':'.__LINE__;

	global $wgOut,$wgRequest;
	global $vgOut,$vgPage;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->getBool('btnSave');
	$isNew = $this->IsNew();
	$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	$id = $this->KeyValue();
	$strName = $this->Value('Name');

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Phone '.$strName.' ('.$id.')');
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	$wgOut->AddHTML($out); $out = '';

	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$out .= $this->objForm->Save();
		$this->AdminRedirect();	// ...so that reloading the page doesn't re-save it
	    }
	}

	$ftID = $this->AdminLink();
	$ftWhenEnt = $this->Value('WhenEnt');
	$ftWhenUpd = $this->Value('WhenUpd');
	$ftSrch = $this->Value('PhoneSrch');

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ftName	= $objForm->Render('Name');
	    $ftNum	= $objForm->Render('Phone');
	    $ftDesc	= $objForm->Render('Descr');
	    $ftCust	= $objForm->Render('ID_Cust');
	    $ftActive	= $objForm->Render('isActive');
	} else {
	    $rcCust = $this->CustObj();

	    $ftName	= $this->Value('Name');
	    $ftNum	= $this->Value('Phone');
	    $ftDesc	= $this->Value('Descr');
	    $ftCust	= $rcCust->AdminLink_name();
	    $ftActive	= NoYes($this->Value('isActive'));
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>";
	$out .= "\n<tr><td align=right><b>Customer</b>:</td><td>$ftCust</td></tr>";
	$out .= "\n<tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>";
	$out .= "\n<tr><td align=right><b>Phone</b>:</td><td>$ftNum</td></tr>";
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>$ftDesc</td></tr>";
	$out .= "\n<tr><td align=right><b>Active</b>:</td><td>$ftActive</td></tr>";
	$out .= "\n<tr><td align=center colspan=2>non-editable data</td></tr>";
	$out .= "\n<tr><td align=right><b>When Created</b>:</td><td>$ftWhenEnt</td></tr>";
	$out .= "\n<tr><td align=right><b>When Updated</b>:</td><td>$ftWhenUpd</td></tr>";
	$out .= "\n<tr><td align=right><b>Searchable</b>:</td><td>$ftSrch</td></tr>";
	$out .= "\n</table>";

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

	$objSection = new clsWikiSection_std_page($objPage,'Event Log',3);
	//$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));
	$out .= $objSection->Render();
	$out .= $this->EventListing();

	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';

	$wgOut->AddHTML($out);
    }
    /*----
      HISTORY:
	2010-11-17 adapted from clsCtgGroup to clsAdminCustAddr
	2012-01-04 adapted from clsAdminCustAddr to clsAdminCustName
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('Phone'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsField('ID_Cust'),		new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML_CheckBox());

	    $this->objForm = $objForm;
	}
    }
    /*----
      PURPOSE: callback for when new record is created by user's edit
    */
    public function Fields_forCreate() {
	return array('WhenEnt' => 'NOW()');
    }
    /*----
      PURPOSE: callback for when existing record is updated by user's edit
    */
    public function Fields_forUpdate() {
	return array(
	  'WhenUpd' => 'NOW()',
	  'PhoneSrch' => SQLValue(nz(clsCustPhones::Searchable($this->Number()))),	// this may not work the first time
	  );
    }
}
