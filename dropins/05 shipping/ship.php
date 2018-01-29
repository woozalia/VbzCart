<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Shipments
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class vctAdminShipments extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ord_shipmt';
    }
    // CEMENT
    protected function SingularName() {
	return 'VCR_Shipment';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SHIPMENT;
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

    public function ActiveRecords($sqlSort=NULL) {
	$rs = $this->SelectRecords('WhenClosed IS NULL',$sqlSort);
	return $rs;
    }

    // -- RECORDS -- //
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
	//clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option(array(),'open','show',NULL,NULL,'shipments that have not yet been closed'),
	  new clsActionLink_option(array(),'shut','show',NULL,NULL,'shipments that have been closed'),
	  new clsAction_section('or'),
	  new clsActionLink_option(array(),'ded','show',NULL,NULL,'special purpose (dedicated) shipment records'),
	  new clsActionLink_option(array(),'hold','show',NULL,NULL,'shipments currently on hold'),
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

	    $rs = $this->GetData($sqlFilt,NULL,'ID DESC');
	    if ($rs->HasRows()) {
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
		while ($rs->NextRow()) {
		    $cssClass = $isOdd?'odd':'even';
		    $out .= $rs->RenderAdminLine($cssClass);
/*
		    // TODO: convert this block to a function so method can be changed from public to protected
		    $id = $rs->KeyValue();
		    //$wtID = SelfLink_Page('shipmt','id',$id,$id);
		    $ftID = $rs->AdminLink();
		    $cssStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		    $ftCode = $rs->ShortName();
    //		$wtStatus = ($objRecs->isDedicated==TRUE?'D':'') . ($objRecs->isOnHold==TRUE?'H':'');
		    $ftStatus = $rs->StatusString();
		    //$wtWhenCre = TimeStamp_HideTime($objRecs->WhenCreated);
		    //$wtWhenShp = TimeStamp_HideTime($objRecs->WhenShipped);
		    //$wtWhenCls = TimeStamp_HideTime($objRecs->WhenClosed);
		    $ftWhenCre = clsDate::NzDate($rs->WhenCreated());
		    $ftWhenShp = clsDate::NzDate($rs->WhenShipped());
		    $ftWhenCls = clsDate::NzDate($rs->WhenClosed());

		    $ftDescr = $rs->DescriptionText();
		    if ($rs->HasNotes()) {
			$ftDescr .= " <i>".$rs->NotesText()."</i>";
		    }
		    $isActive = $rs->HasBeenShipped();
		    if ($isActive) {
			//later: show link to ship/close it
			$cssStyle .= ' color: #002266;';
		    } else {
		    }
		    $out .= <<<__END__
  <tr style="$cssStyle">
    <td>$ftID</td>
    <td>$ftCode</td>
    <td>$ftStatus</td>
    <td>$ftWhenCre</td>
    <td>$ftWhenShp</td>
    <td>$ftWhenCls</td>
    <td>$ftDescr</td>
  </tr>
__END__;
*/
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
class VCR_Shipment extends vcAdminRecordset {
    //use ftLoggableRecord;
    use ftFrameworkAccess;

    private $frmPage;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->frmPage = NULL;
    }

    // -- SETUP -- //
    // ++ TRAIT HELPERS ++ //

    public function SelfLink_name() {
	return $this->SelfLink($this->ShortName());
    }

    // -- TRAIT HELPERS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ FIELD ACCESS ++ //

    // 2015-09-02 Changing all of these from public to protected until need for public is demonstrated.

    // PUBLIC so Package objects can use it
    public function ShortName() {
	return $this->GetFieldValue('Abbr');
    }
    protected function DescriptionText() {
	return $this->Value('Descr');
    }
    protected function NotesText() {
	return $this->Value('Notes');
    }
    protected function WhenCreated() {
	return $this->Value('WhenCreated');
    }
    protected function WhenShipped() {
	return $this->Value('WhenShipped');
    }
    protected function WhenClosed() {
	return $this->Value('WhenClosed');
    }
    protected function IsDedicated() {
	return (ord($this->Value('isDedicated')) == '1');
    }
    protected function IsOnHold() {
	return (ord($this->Value('isOnHold')) == '1');
    }
    // 2015-10-19 not sure why this exists...
    public function Descr() {
	$out = $this->ShortName();
	return $out;
    }
    protected function ReceiptCost() {
	return $this->Value('ReceiptCost');
    }
    protected function OutsideCost() {
	return $this->Value('OutsideCost');
    }
    protected function OrderCost() {
	return $this->Value('OrderCost');
    }
    protected function SuppliesCost() {
	return $this->Value('SupplCost');
    }
    protected function CarrierName() {
	return $this->Value('Carrier');
    }

    // -- FIELD ACCESS -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function IsOpen() {
	return is_null($this->WhenClosed());
    }
    public function HasNotes() {
	return !is_null($this->NotesText());
    }
    public function HasBeenShipped() {
	return !is_null($this->WhenShipped());
    }
    public function StatusString() {
	return ($this->IsDedicated()?'D':'') . ($this->IsOnHold()?'H':'');
    }

    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //

    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PACKAGES,$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function PkgsData() {
	$tbl = $this->Packagetable();
	$rs = $tbl->GetData('(ID_Shipment='.$this->GetKeyValue().') AND (WhenVoided IS NULL)');
	return $rs;
    }

    // -- RECORDS -- //
    // ++ ADMIN UI ++ //

    /*----
      RETURNS: rendering of a single line for a table listing multiple records
    */
    public function RenderAdminLine($cssClass) {
	$id = $this->GetKeyValue();
	$ftID = $this->SelfLink();
	$ftCode = $this->ShortName();
	$ftStatus = $this->StatusString();
	$ftWhenCre = fcDate::NzDate($this->WhenCreated());
	$ftWhenShp = fcDate::NzDate($this->WhenShipped());
	$ftWhenCls = fcDate::NzDate($this->WhenClosed());

	$ftDescr = $this->DescriptionText();
	if ($this->HasNotes()) {
	    $ftDescr .= " <i>".$this->NotesText()."</i>";
	}
	$isActive = !$this->HasBeenShipped();
	if ($isActive) {
	    //later: show link to ship/close it
	    $cssClass .= '';
	} else {
	    $cssClass .= ' inactive';
	}
	$out = <<<__END__
  <tr class="$cssClass">
    <td>$ftID</td>
    <td>$ftCode</td>
    <td>$ftStatus</td>
    <td>$ftWhenCre</td>
    <td>$ftWhenShp</td>
    <td>$ftWhenCls</td>
    <td>$ftDescr</td>
  </tr>
__END__;
	return $out;
    }
    /*-----
      TO DO:
	* Convert old-style edit/header to new, wherever that is
      HISTORY:
	2011-03-29 renamed from InfoPage() to AdminPage()
    */
    public function AdminPage() {
	$oPage = $this->PageObject();
	$oSkin = $this->SkinObject();

// check for form data
	//$doNew = ($oPage->PathArg('id') == 'new');
	$doNew = $this->IsNew();

// check for more actions:
	$doEdit = ($doNew || $oPage->PathArg('edit'));
	$doClose = $oPage->PathArg('close');
	$doSave = $oPage->ReqArgBool('btnSave');

// get basic record information to make title
	$htAbbr = fcString::EncodeForHTML($this->Value('Abbr'));
	$htDescr = fcString::EncodeForHTML($this->Value('Descr'));
	$htNotes = fcString::EncodeForHTML($this->Value('Notes'));
	if ($doNew) {
	    $htID = 'NEW';
	    $oSkin->SetBrowserTitle('shp NEW');
	    $oSkin->SetPageTitle('NEW Shipment');
	} else {
	    $htID = $this->SelfLink();
	    $id = $this->GetKeyValue();
	    $oSkin->SetBrowserTitle("shp $htAbbr (id$id)");
	    $oSkin->SetPageTitle("Shipment $htAbbr (ID $id)");
	}

	// set up header action-links
	$arPath = $oPage->PathArgs();
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPath,'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

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
	    if (is_null($this->WhenClosed())) {
		$arLink = array(
		  'close' => TRUE,
		  );
		$htClose = ' ['.$this->SelfLink('close the shipment','set closing timestamp and add up totals',$arLink).']';
	    } else {
		$htClose = '';	// already closed
	    }

	    // code for static values
	    $ctrlAbbr = $htAbbr;
	    $ctrlDescr = $htDescr;
	    $ctrlNotes = $htNotes;

	    $ctrlWhenCre = $this->WhenCreated();
	    $ctrlWhenShp = $this->WhenShipped();
	    $ctrlCostRcpt = $this->ReceiptCost();
	    $ctrlCostOuts = $this->OutsideCost();
	    $ctrlCostOrdr = $this->OrderCost();
	    $ctrlCostSupp = $this->SuppliesCost();
	    $ctrlCarrier = $this->CarrierName();

	    $isDedicated = (ord($this->IsDedicated()));
	    $isOnHold = (ord($this->IsOnHold()));
	    if ($isDedicated || $isOnHold) {
		$ctrlStatus = ($isDedicated?'Dedicated':'') . ($isOnHold?' OnHold':'');
	    } else {
		$ctrlStatus = '<i>normal</i>';
	    }
	}
	// non-editable controls
	$ctrlWhenClo = $this->WhenClosed().$htClose;

//	$out .= WikiSectionHdr_Edit($strTitle,$doEdit);	// old style
//	$out .= "\n<h2>$strTitle</h2>";
//	$out .= $oPage->Skin()->SectionHeader($strTitle);

	$out = <<<__END__

<table>
<tr><td align=right valign=top><b>ID</b>:</td><td>$htID</td></tr>
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
	    $out .= $this->PackageListing();
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
	$rsPkgs = $this->PkgsData();
	$arArgs = array(
	  'no.rows.html'	=> 'no packages for this shipment',
	  //'descr'	=> ' for this shipment',
	  //'omit'	=> 'ship',	// omit shipment column -- all from the same shipment
	  //'ord'		=> $this->OrderID()	// Shipments don't have an Order ID
	  //'can.add'	=> $this->IsOpen(),
	  );
	$arCols = $rsPkgs->AdminRows_fields();
	unset($arCols['!idShip']);
	$out = $rsPkgs->AdminRows($arCols,$arArgs);
	return $out;
    }
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
}
