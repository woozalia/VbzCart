<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Shipments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class clsShipments extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_shipmt');
	  $this->KeyName('ID');
	  $this->ClassSng('clsShipment');
	  $this->ActionKey('shpmt');
    }
    protected function _newItem() {
      return new clsCatPage($this);
    }
    public function GetActive($iSort=NULL) {
	$objRows = $this->GetData('WhenClosed IS NULL',NULL,$iSort);
	return $objRows;
    }
    public function AdminPage() {
	global $wgOut;
	global $vgPage;

	$vgPage->UseHTML();

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Shipments');
	$objSection->ToggleAdd('open','shipments that have not yet been closed');
	$objSection->ToggleAdd('shut','shipments that have been closed');
	$objSection->SectionAdd('or');
	$objSection->ToggleAdd('ded','dedicated shipments');
	$objSection->ToggleAdd('hold','on-hold shipments');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();
	$wgOut->addHTML($out);	$out = '';

	$vgPage->UseWiki();

	$doShowOpen = $vgPage->Arg('open');
	$doShowShut = $vgPage->Arg('shut');
	$doShowDed = $vgPage->Arg('ded');
	$doShowHold = $vgPage->Arg('hold');

	if ($doShowOpen || $doShowShut || $doShowDed || $doShowHold) {

//	$out = '==Shipments==';
//	$wgOut->addWikiText($out,TRUE);	$out = '';
	    $doShowAll = ($doShowOpen && $doShowShut);
	    $sqlFilt = NULL;	// show everything
	    if (!$doShowAll) {
		if ($doShowOpen) {
		    $sqlFilt = '(WhenClosed IS NULL)';
		}
		if ($doShowShut) {
		    $sqlFilt = '(WhenClosed IS NOT NULL)';
		}
		if ($doShowDed) {
		    if (!is_null($sqlFilt)) {
			$sqlFilt .= ' OR';
		    }
		    $sqlFilt .= ' isDedicated';
		}
		if ($doShowHold) {
		    if (!is_null($sqlFilt)) {
			$sqlFilt .= ' OR';
		    }
		    $sqlFilt .= ' isOnHold';
		}
	    }
	    $out .= '<b>Filter</b>: '.$sqlFilt."\n";

	    $objRecs = $this->GetData($sqlFilt,NULL,'ID DESC');
	    if ($objRecs->HasRows()) {
		$out .= "{| class=sortable\n|-\n! ID || Code || Status || Created || Shipped || Closed || Descr/Notes";
		$isOdd = TRUE;
		while ($objRecs->NextRow()) {
		    $id = $objRecs->ID;
		    //$wtID = SelfLink_Page('shipmt','id',$id,$id);
		    $wtID = $objRecs->AdminLink();
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		    $wtCode = $objRecs->Abbr;
    //		$wtStatus = ($objRecs->isDedicated==TRUE?'D':'') . ($objRecs->isOnHold==TRUE?'H':'');
		    $wtStatus = (ord($objRecs->isDedicated)=='1'?'D':'') . (ord($objRecs->isOnHold)=='1'?'H':'');
		    $wtWhenCre = TimeStamp_HideTime($objRecs->WhenCreated);
		    $wtWhenShp = TimeStamp_HideTime($objRecs->WhenShipped);
		    $wtWhenCls = TimeStamp_HideTime($objRecs->WhenClosed);
		    $wtDescr = $objRecs->Descr;
		    if (!empty($objRecs->Notes)) {
			$wtDescr .= " ''".$objRecs->Notes."''";
		    }
		    $isActive = is_null($objRecs->WhenShipped);
		    if ($isActive) {
			//later: show link to ship/close it
			$wtStyle .= ' color: #002266;';
		    } else {
		    }
		    $out .= "\n|- style=\"$wtStyle\"\n| ".$wtID.' || '.$wtCode.' || '.$wtStatus.' || '.$wtWhenCre.' || '.$wtWhenShp.' || '.$wtWhenCls.' || '.$wtDescr;
		    $isOdd = !$isOdd;
		}
		$out .= "\n|}";
	    } else {
		$out = 'No shipments have been created yet.';
	    }
	} else {
	    $out .= 'No filters active - nothing to show.';
	}
	$wgOut->addWikiText($out,TRUE);	$out = '';
    }
}
class clsShipment extends clsVbzRecs {
    /*----
      HISTORY:
	2010-10-11 Added iarArgs parameter
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name() {
	return $this->AdminLink($this->ShortName());
    }
    public function ShortName() {
	return $this->Value('Abbr');
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-09-24 copied from VbzAdminOrderTrxact to clsShipment
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    /*-----
      TO DO:
	* Convert old-style edit/header to new, wherever that is
      HISTORY:
	2011-03-29 renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$vgPage->UseHTML();

// check for form data
	$doNew = ($vgPage->Arg('id') == 'new');
	$didAdd = FALSE;

// 2011-09-02 This was the old code for saving a new record. Keeping it in case tweaks are needed to the new code.
/*
	if ($wgRequest->getBool('btnCreate')) {
	    $wgOut->AddWikiText('==Creating Shipment==',TRUE);
	    // create new record from form data
	    $strAbbr = $wgRequest->getText('abbr');
	    $strDescr = $wgRequest->getText('descr');
	    $strNotes = $wgRequest->getText('notes');

	    $arEv = array(
	      'descr'	=> 'new shipment',
	      'where'	=> __METHOD__,
	      'code'	=> 'new'
	      );
	    $this->StartEvent($arEv);
	    $arIns = array(
	      'id'		=> 0,
	      'WhenCreated'	=> 'NOW()',
	      'Abbr'		=> SQLValue($strAbbr),
	      'Descr'		=> SQLValue($strDescr),
	      'Notes'		=> SQLValue($strNotes)
	      );
	    $this->Table->Insert($arIns);
	    $this->ID = $this->Table->LastID();
	    $wgOut->AddWikiText('New ID='.$this->ID,TRUE);
	    $this->Reload();	// load the newly-created record
	    $didAdd = TRUE;	// a new record has just been created
	    $this->FinishEvent(array('id'=>$this->ID));
	}
*/

// check for more actions:
	$doEdit = ($doNew || $vgPage->Arg('edit'));
	$doClose = $vgPage->Arg('close');
	$doSave = $wgRequest->getBool('btnSave');

	if ($doClose) {
	    // add up totals
	    $objRecs = $this->PkgsData();
	    if ($objRecs->HasRows()) {
		$wgOut->AddWikiText('==Closing Shipment==',TRUE);
		$ok = TRUE;
		$dlrShip = 0;
		$dlrPack = 0;
		$cntPkgErr = 0;
		$out = '';
		while ($objRecs->NextRow()) {
		    $out .= '<br>Pkg ID '.$objRecs->AdminLink();
		    if (is_null($objRecs->ShipCost)) {
			$out .= ': no shipping cost!';
			$ok = FALSE;
			$cntPkgErr++;
		    } elseif (is_null($objRecs->PkgCost)) {
			$out .= ': no materials cost!';
			$ok = FALSE;
			$cntPkgErr++;
		    } else {
			$dlrShip += $objRecs->ShipCost;
			$dlrPack += $objRecs->PkgCost;
		    }
		}
		if (!$ok) {
		    $doIncomplete = $vgPage->Arg('incomplete');
		    if ($doIncomplete) {
			$ok = TRUE;
			$out .= '<br><b>Admin accepts incomplete data</b>: closing anyway';
		    }
		}
		if ($ok) {
		    $strUpd = '$'.$dlrShip.' shipping, $'.$dlrPack.' materials';
		    if ($doIncomplete) {
			$strUpd .= ' (incomplete pkg data accepted)';
		    }
		    $out = '<br>Cost totals: '.$strUpd;
		} else {
		    $out .= '<br>'.$cntPkgErr.Pluralize($cntPkgErr,' package has',' packages have').' missing information.';
		    $arLink = array(
		      'close' => TRUE,
		      'incomplete' => TRUE,
		      );
		    $htClose = ' ['.$vgPage->SelfLink($arLink,'close anyway','close with incomplete package data').']';
		    $out .= $htClose;
		}
	    } else {
		$ok = FALSE;
		$out = 'No packages in shipment; nothing to close.';
	    }
	    $wgOut->AddHTML($out); $out=NULL;

	    if ($ok) {
		// log the attempt
		$arEv = array(
		  'descr'	=> 'closing shipment: '.$strUpd,
		  'where'	=> __METHOD__,
		  'code'	=> 'CLO'
		  );
		$this->StartEvent($arEv);

		// fill in stats
		$arUpd = array(
		  'WhenClosed' => 'NOW()',
		  'OrderCost' => $dlrShip,
		  'SupplCost' => $dlrPack
		  );
		$this->Update($arUpd);
		global $sql;
		$out = '<br>Shipment updated - SQL: '.$sql;
		$this->Reload();

		// log the completion
		$this->FinishEvent();
		$wgOut->AddWikiText($out,TRUE);	$out=NULL;
	    }
	}

	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$this->AdminSave();
	    }
	}

	$htAbbr = htmlspecialchars($this->Abbr);
	$htDescr = htmlspecialchars($this->Descr);
	$htNotes = htmlspecialchars($this->Notes);
	// values which are always static
	if ($doNew) {
	    $strID = 'NEW';
	    $strTitle = 'NEW Shipment';
	} else {
	    if ($didAdd) {
		$strID = $this->AdminLink();
	    } else {
		$strID = $this->ID;
	    }
	    $strTitle = 'Shipment '.$htAbbr;
	}
	$out = '';
	if ($doEdit) {
	    // open editing form
	    $sqlID = $doNew?'new':$this->ID;
	    $arLink = array(
	      'edit' => FALSE,
	      'id' => $sqlID
	      );
	    $htPath = $vgPage->SelfURL($arLink);
	    $out .= "\n<form method=post action=\"$htPath\">";
	    // code for editable values
/*
	    $strAbbr = '<input name=abbr type=text size=16 value="'.$htAbbr.'">';
	    $strDescr = '<input name=descr type=text size=50 value="'.$htDescr.'">';
	    $strNotes = '<textarea name=notes width=50 height=3>'.$htNotes.'</textarea>';
*/
	    $objForm = $this->objForm;
	    $ctrlAbbr	= $objForm->Render('Abbr');
	    $ctrlDescr	= $objForm->Render('Descr');
	    $ctrlNotes	= $objForm->Render('Notes');

	    $ctrlWhenCre	= $objForm->Render('WhenCreated');
	    $ctrlWhenShp	= $objForm->Render('WhenShipped');
	    $ctrlCostRcpt	= '$'.$objForm->Render('ReceiptCost');
	    $ctrlCostOuts	= '$'.$objForm->Render('OutsideCost');
	    $ctrlCostOrdr	= '$'.$objForm->Render('OrderCost');
	    $ctrlCostSupp	= '$'.$objForm->Render('SupplCost');
	    $ctrlCarrier	= $objForm->Render('Carrier');

	    $ctrlIsDedic	= $objForm->Render('isDedicated');
	    $ctrlIsOnHold	= $objForm->Render('isOnHold');
	    $ctrlStatus = $ctrlIsDedic.'dedicated '.$ctrlIsOnHold.'on hold';

	    $htClose = '';
	} else {
	    // Only allow closing the shipment if we're not editing.
	    // Clicking a link from edit mode loses any edits.
	    if (is_null($this->WhenClosed)) {
		$arLink = array(
		  'close' => TRUE,
		  );
		$htClose = ' ['.$vgPage->SelfLink($arLink,'close the shipment','set closing timestamp and add up totals').']';
	    } else {
		$htClose = '';	// already closed
	    }

	    // code for static values
	    $ctrlAbbr = $htAbbr;
	    $ctrlDescr = $htDescr;
	    $ctrlNotes = $htNotes;

	    $ctrlWhenCre = $this->WhenCreated;
	    $ctrlWhenShp = $this->WhenShipped;
	    $ctrlCostRcpt = $this->ReceiptCost;
	    $ctrlCostOuts = $this->OutsideCost;
	    $ctrlCostOrdr = $this->OrderCost;
	    $ctrlCostSupp = $this->SupplCost;
	    $ctrlCarrier = $this->Carrier;

	    $isDedicated = (ord($this->isDedicated));
	    $isOnHold = (ord($this->isOnHold));
	    if ($isDedicated || $isOnHold) {
		$ctrlStatus = ($isDedicated?'Dedicated':'') . ($isOnHold?' OnHold':'');
	    } else {
		$ctrlStatus = '<i>normal</i>';
	    }
	}
	// non-editable controls
	$ctrlWhenClo = $this->WhenClosed.$htClose;

	$out .= WikiSectionHdr_Edit($strTitle,$doEdit);	// old style
//	$out .= "\n<h2>$strTitle</h2>";

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right valign=top><b>ID</b>:</td><td>$strID</td></tr>";
	$out .= "\n<tr><td align=right valign=top><b>Name</b>:</td><td>$ctrlAbbr</td></tr>";
	$out .= "\n<tr><td align=right valign=top><b>Description</b>:</td><td>$ctrlDescr</td></tr>";
	$out .= "\n<tr><td align=right valign=top><b>Type</b>:</td><td>$ctrlStatus</td></tr>";
	if (!$doNew) {
	    $out .= "\n<tr><td align=right valign=top><b>When Created</b>:</td><td>$ctrlWhenCre</td></tr>";
	    $out .= "\n<tr><td align=right valign=top><b>When Shipped</b>:</td><td>$ctrlWhenShp</td></tr>";
	    $out .= "\n<tr><td align=right valign=top><b>When Closed</b>:</td><td>$ctrlWhenClo</td></tr>";
	    $out .= "\n<tr><td align=right valign=top><b>Receipt Cost</b>:</td><td>$ctrlCostRcpt</td></tr>";
	    $out .= "\n<tr><td align=right valign=top><b>Outside Cost</b>:</td><td>$ctrlCostOuts</td></tr>";
	    $out .= "\n<tr><td align=right valign=top><b>Order Cost</b>:</td><td>$ctrlCostOrdr</td></tr>";
	    $out .= "\n<tr><td align=right valign=top><b>Supplier Cost</b>:</td><td>$ctrlCostSupp</td></tr>";
	}
	$out .= "\n<tr><td align=right valign=top><b>Carrier</b>:</td><td>$ctrlCarrier</td></tr>";
	$out .= "\n<tr><td align=right valign=top><b>Notes</b>:</td><td>$ctrlNotes</td></tr>";
	$out .= "\n</table>";
	if ($doEdit) {
	// form buttons
	    if ($doNew) {
		// next line needed only if we have special code for Create vs. Save
		// $out .= '<input type=submit name="btnCreate" value="Create">';
		// 2011-09-02 testing this instead:
		$out .= '<input type=submit name="btnSave" value="Create">';
	    } else {
		$out .= '<input type=submit name="btnSave" value="Save">';
		$out .= '<input type=reset value="Revert">';
	    }
	// close editing form
	    $out .= "\n</form>";
	}
	$wgOut->addHTML($out);	$out = '';

	if (!$doNew) {
	    $wgOut->addWikiText('===Packages===',TRUE);	$out = '';
	    $out .= $this->PkgTable();
	    $wgOut->addWikiText($out,TRUE);	$out = '';

	    $wgOut->addWikiText('===Events===',TRUE);
	    $out = $this->EventListing();
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	}
    }
    /*----
      HISTORY:
	2011-02-17 Updated to use objForm instead of objFlds/objCtrls
    */
    private function BuildEditForm() {
	global $vgOut;

	// create fields & controls

	if (is_null($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldTime('WhenCreated'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenShipped'),	new clsCtrlHTML());
	    //$objCtrls->AddField(new clsFieldTime('WhenClosed'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ReceiptCost'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('OutsideCost'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('OrderCost'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('SupplCost'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsField('Carrier'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Abbr'),	new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsField('Descr'),	new clsCtrlHTML(array('size'=>50)));
	    $objForm->AddField(new clsField('Notes'),	new clsCtrlHTML_TextArea(array('height'=>3,'width'=>50)));
	    $objForm->AddField(new clsFieldBool('isDedicated'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsFieldBool('isOnHold'),	new clsCtrlHTML_CheckBox());

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the shipment
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
/* 2011-02-17 old code
	global $wgOut;

	// get the form data and note any changes
	$objFlds = $this->objCtrls->Fields();
	$objFlds->RecvVals();
	// get the list of field updates
	$arUpd = $objFlds->DataUpdates();
	// log that we are about to update
	$strDescr = 'Edited: '.$objFlds->DescrUpdates();
	$wgOut->AddWikiText('==Saving Edit==',TRUE);
	$wgOut->AddWikiText($strDescr,TRUE);

	$arEv = array(
	  'descr'	=> $strDescr,
	  'where'	=> __METHOD__,
	  'code'	=> 'ED'
	  );
	$this->StartEvent($arEv);
	// update the recordset
	$this->Update($arUpd);
global $sql;
$wgOut->AddWikiText('<br>SQL='.$sql,TRUE);
	$this->Reload();
	// log completion
	$this->FinishEvent();
*/
    }
    public function PkgsData() {
	$objTbl = $this->objDB->Pkgs();
	$objRows = $objTbl->GetData('(ID_Shipment='.$this->KeyValue().') AND (WhenVoided IS NULL)');
	return $objRows;
    }
    public function PkgTable() {
/*
	//$objTbl = new clsPackages($this->objDB);
	$objTbl = $this->objDB->Pkgs();
	$objRows = $objTbl->GetData('ID_Shipment='.$this->ID);
*/
	$objRows = $this->PkgsData();
	$arArgs = array(
	  'descr'	=> ' for this shipment',
	  'omit'	=> '',
	  'ord'		=> $this->ID_Order
	  );
	$out = $objRows->AdminTable($arArgs);
	return $out;
    }
    /*----
      HISTORY:
	2010-10-23 added event logging using helper class
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
    public function DropDown($iName,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">'."\n";
	    while ($this->NextRow()) {
		$id = $this->Value('ID');
		if ($id == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$htDescr = $this->Descr();
		$out .= "<option$htSelect value=\"$id\">$htDescr</option>\n";
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No shipments matching filter';
	}
	return $out;
    }
    public function Descr() {
	$out = $this->Abbr;
	return $out;
    }
}
