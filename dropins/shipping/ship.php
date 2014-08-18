<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Shipments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class VCT_Shipments extends clsVbzTable {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_shipmt');
	  $this->KeyName('ID');
	  $this->ClassSng('VCR_Shipment');
	  $this->ActionKey(KS_ACTION_SHIPMENT);
    }
    protected function _newItem() {
      throw new exception('Who calls this?');
      return new clsCatPage($this);
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function GetActive($iSort=NULL) {
	$objRows = $this->GetData('WhenClosed IS NULL',NULL,$iSort);
	return $objRows;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	/*
	$objSection = new clsWikiSection($objPage,'Shipments');
	$objSection->ToggleAdd('open','shipments that have not yet been closed');
	$objSection->ToggleAdd('shut','shipments that have been closed');
	$objSection->SectionAdd('or');
	$objSection->ToggleAdd('ded','dedicated shipments');
	$objSection->ToggleAdd('hold','on-hold shipments');
	//$objSection->ActionAdd('view');
	*/
	// set up header action-links
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option(array(),'open','show',NULL,NULL,'shipments that have not yet been closed'),
	  new clsActionLink_option(array(),'shut','show',NULL,NULL,'shipments that have not yet been closed'),
	  new clsAction_section('or'),
	  new clsActionLink_option(array(),'ded','show',NULL,NULL,'shipments that have not yet been closed'),
	  new clsActionLink_option(array(),'hold','show',NULL,NULL,'shipments that have not yet been closed'),
	  new clsAction_section('or'),
	  new clsActionLink_option(array(),'all','show',NULL,NULL,'all shipments'),
	  );
	$out = $oPage->ActionHeader('Shipments',$arActs);

	$sShow = $oPage->PathArg('show');

	if ($sShow == 'all') {
	    //$doShowOpen = $doShowShut = $doShowDed = $doShowHold = TRUE;
	    // There is probably a more elegant way to handle this.
	    $doShowOpen = $doShowShut = TRUE;
	} else {
	    $doShowOpen = ($sShow == 'open');
	    $doShowShut = ($sShow == 'shut');
	    $doShowDed = ($sShow == 'ded');
	    $doShowHold = ($sShow == 'hold');
	}

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
		$out .= <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Code</th>
    <th>Status</th>
    <th>Created</th>
    <th>Shipped</th>
    <th>Closed</th>
    <th title="description / notes">Notes</th>
__END__;
		$isOdd = TRUE;
		while ($objRecs->NextRow()) {
		    $id = $objRecs->ID;
		    //$wtID = SelfLink_Page('shipmt','id',$id,$id);
		    $wtID = $objRecs->AdminLink();
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		    $wtCode = $objRecs->Abbr;
    //		$wtStatus = ($objRecs->isDedicated==TRUE?'D':'') . ($objRecs->isOnHold==TRUE?'H':'');
		    $wtStatus = (ord($objRecs->isDedicated)=='1'?'D':'') . (ord($objRecs->isOnHold)=='1'?'H':'');
		    //$wtWhenCre = TimeStamp_HideTime($objRecs->WhenCreated);
		    //$wtWhenShp = TimeStamp_HideTime($objRecs->WhenShipped);
		    //$wtWhenCls = TimeStamp_HideTime($objRecs->WhenClosed);
		    $wtWhenCre = clsDate::NzDate($objRecs->WhenCreated);
		    $wtWhenShp = clsDate::NzDate($objRecs->WhenShipped);
		    $wtWhenCls = clsDate::NzDate($objRecs->WhenClosed);

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
//		    $out .= "\n|- style=\"$wtStyle\"\n| ".$wtID.' || '.$wtCode.' || '.$wtStatus.' || '.$wtWhenCre.' || '.$wtWhenShp.' || '.$wtWhenCls.' || '.$wtDescr;
		    $out .= <<<__END__
  <tr style="$wtStyle">
    <td>$wtID</td>
    <td>$wtCode</td>
    <td>$wtStatus</td>
    <td>$wtWhenCre</td>
    <td>$wtWhenShp</td>
    <td>$wtWhenCls</td>
    <td>$wtDescr</td>
  </tr>
__END__;
		    $isOdd = !$isOdd;
		}
		$out .= "\n</table>";
	    } else {
		$out .= 'No shipments have been created yet.';
	    }
	} else {
	    $out .= 'No filters active - nothing to show.';
	}
	return $out;
    }
}
class VCR_Shipment extends clsVbzRecs {
    private $frmPage;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->frmPage = NULL;
    }

    // -- SETUP -- //
    // ++ BOILERPLATE ++ //
/*
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
*/
    // -- BOILERPLATE -- //
    // ++ BOILERPLATE AUXILIARY ++ //

    public function AdminLink_name() {
	return $this->AdminLink($this->ShortName());
    }

    // -- BOILERPLATE AUXILIARY -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ FIELD ACCESS ++ //

    public function ShortName() {
	return $this->Value('Abbr');
    }

    // -- FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PACKAGES,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function PkgsData() {
	$tbl = $this->Packagetable();
	$rs = $tbl->GetData('(ID_Shipment='.$this->KeyValue().') AND (WhenVoided IS NULL)');
	return $rs;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ADMIN UI ++ //

    /*-----
      TO DO:
	* Convert old-style edit/header to new, wherever that is
      HISTORY:
	2011-03-29 renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

// check for form data
	$doNew = ($oPage->PathArg('id') == 'new');
	$didAdd = FALSE;

// check for more actions:
	$doEdit = ($doNew || $oPage->PathArg('edit'));
	$doClose = $oPage->PathArg('close');
	$doSave = $oPage->ReqArgBool('btnSave');

// get basic record information to make title
	$htAbbr = htmlspecialchars($this->Value('Abbr'));
	$htDescr = htmlspecialchars($this->Value('Descr'));
	$htNotes = htmlspecialchars($this->Value('Notes'));
	if ($doNew) {
	    $sID = 'NEW';
	    $sTitle = 'NEW Shipment';
	} else {
	    if ($didAdd) {
		$sID = $this->AdminLink();
	    } else {
		$sID = $this->KeyValue();
	    }
	    $sTitle = 'Shipment '.$htAbbr;
	}

	// set up header action-links
	$arPath = $oPage->PathArgs();
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPath,'edit')
	  );
	$out = $oPage->ActionHeader($sTitle,$arActs);

	if ($doClose) {
	    // add up totals
	    $objRecs = $this->PkgsData();
	    if ($objRecs->HasRows()) {
		$out .= $oPage->SectionHeader('Closing Shipment');
		$ok = TRUE;
		$dlrShip = 0;
		$dlrPack = 0;
		$cntPkgErr = 0;
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
		    $out .= '<br>Cost totals: '.$strUpd;
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
		$out .= 'No packages in shipment; nothing to close.';
	    }

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
		$out .= '<br>Shipment updated - SQL: '.$sql;
		$this->Reload();

		// log the completion
		$this->FinishEvent();
	    }
	}

	if ($doEdit || $doSave) {
	    if ($doSave) {
		$this->AdminSave();
		$this->AdminRedirect(array('edit'=>FALSE));
	    }
	}

	// values which are always static
	if ($doEdit) {
	    // open editing form
	    $sqlID = $doNew?'new':$this->ID;
	    $arLink = array(
	      'edit' => FALSE,
	      'id' => $sqlID
	      );
	    //$htPath = $oPage->SelfURL($arLink);
	    $out .= "\n<form method=post>";
	    // code for editable values
/*
	    $strAbbr = '<input name=abbr type=text size=16 value="'.$htAbbr.'">';
	    $strDescr = '<input name=descr type=text size=50 value="'.$htDescr.'">';
	    $strNotes = '<textarea name=notes width=50 height=3>'.$htNotes.'</textarea>';
*/
	    $objForm = $this->PageForm();
	    $ctrlAbbr	= $objForm->RenderControl('Abbr');
	    $ctrlDescr	= $objForm->RenderControl('Descr');
	    $ctrlNotes	= $objForm->RenderControl('Notes');

	    $ctrlWhenCre	= $objForm->RenderControl('WhenCreated');
	    $ctrlWhenShp	= $objForm->RenderControl('WhenShipped');
	    $ctrlCostRcpt	= '$'.$objForm->RenderControl('ReceiptCost');
	    $ctrlCostOuts	= '$'.$objForm->RenderControl('OutsideCost');
	    $ctrlCostOrdr	= '$'.$objForm->RenderControl('OrderCost');
	    $ctrlCostSupp	= '$'.$objForm->RenderControl('SupplCost');
	    $ctrlCarrier	= $objForm->RenderControl('Carrier');

	    $ctrlIsDedic	= $objForm->RenderControl('isDedicated');
	    $ctrlIsOnHold	= $objForm->RenderControl('isOnHold');
	    $ctrlStatus = $ctrlIsDedic.'dedicated '.$ctrlIsOnHold.'on hold';

	    $htClose = '';
	} else {
	    // Only allow closing the shipment if we're not editing.
	    // Clicking a link from edit mode loses any edits.
	    if (is_null($this->WhenClosed)) {
		$arLink = array(
		  'close' => TRUE,
		  );
		$htClose = ' ['.$this->AdminLink('close the shipment','set closing timestamp and add up totals',$arLink).']';
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

//	$out .= WikiSectionHdr_Edit($strTitle,$doEdit);	// old style
//	$out .= "\n<h2>$strTitle</h2>";
//	$out .= $oPage->Skin()->SectionHeader($strTitle);

	$out .= <<<__END__
<table>
<tr><td align=right valign=top><b>ID</b>:</td><td>$sID</td></tr>
<tr><td align=right valign=top><b>Name</b>:</td><td>$ctrlAbbr</td></tr>
<tr><td align=right valign=top><b>Description</b>:</td><td>$ctrlDescr</td></tr>
<tr><td align=right valign=top><b>Type</b>:</td><td>$ctrlStatus</td></tr>
__END__;
	if (!$doNew) {
	    $out .= <<<__END__
<tr><td align=right valign=top><b>When Created</b>:</td><td>$ctrlWhenCre</td></tr>
<tr><td align=right valign=top><b>When Shipped</b>:</td><td>$ctrlWhenShp</td></tr>
<tr><td align=right valign=top><b>When Closed</b>:</td><td>$ctrlWhenClo</td></tr>
<tr><td align=right valign=top><b>Receipt Cost</b>:</td><td>$ctrlCostRcpt</td></tr>
<tr><td align=right valign=top><b>Outside Cost</b>:</td><td>$ctrlCostOuts</td></tr>
<tr><td align=right valign=top><b>Order Cost</b>:</td><td>$ctrlCostOrdr</td></tr>
<tr><td align=right valign=top><b>Supplier Cost</b>:</td><td>$ctrlCostSupp</td></tr>
__END__;
	}
	$out .= <<<__END__
<tr><td align=right valign=top><b>Carrier</b>:</td><td>$ctrlCarrier</td></tr>
<tr><td align=right valign=top><b>Notes</b>:</td><td>$ctrlNotes</td></tr>
</table>
__END__;
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

	if (!$doNew) {
	    $out .= $oPage->SectionHeader('Packages');
	    $out .= $this->PackageListing();

	    $out .= $oPage->SectionHeader('Events');
	    $out .= $this->EventListing();
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-02-17 Updated to use objForm instead of objFlds/objCtrls
	2014-04-10 Modified for standalone admin framework.
    */
    private function PageForm() {
	if (is_null($this->frmPage)) {
	    $frm = new clsForm_recs($this);
	    $frm->AddField(new clsFieldTime('WhenCreated'),	new clsCtrlHTML());
	    $frm->AddField(new clsFieldTime('WhenShipped'),	new clsCtrlHTML());
	    $frm->AddField(new clsFieldNum('ReceiptCost'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsFieldNum('OutsideCost'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsFieldNum('OrderCost'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsFieldNum('SupplCost'),	new clsCtrlHTML(array('size'=>5)));
	    $frm->AddField(new clsField('Carrier'),		new clsCtrlHTML());
	    $frm->AddField(new clsField('Abbr'),		new clsCtrlHTML(array('size'=>16)));
	    $frm->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>50)));
	    $frm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea(array('height'=>3,'width'=>50)));
	    $frm->AddField(new clsFieldBool('isDedicated'),	new clsCtrlHTML_CheckBox());
	    $frm->AddField(new clsFieldBool('isOnHold'),	new clsCtrlHTML_CheckBox());
	    $this->frmPage = $frm;
	}
	return $this->frmPage;
    }
    /*-----
      ACTION: Save the user's edits to the shipment
      HISTORY:
	2011-02-17 Retired old custom code; now using objForm helper object
    */
    private function AdminSave() {
	$out = $this->PageForm()->Save();
	return $out;
    }
    public function PackageListing() {
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
    /*
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }*/
    public function DropDown($sName=NULL,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $sName = is_null($sName)?($this->Table()->ActionKey()):$sName;
	    $out = '<select name="'.$sName.'">'."\n";
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
