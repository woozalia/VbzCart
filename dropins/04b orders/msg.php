<?php
/*
  FILE: dropins/orders/msg.php -- customer order messages administration dropin for VbzCart
  HISTORY:
    2014-02-22 split off OrderMsg classes from order.php
    2017-01-06 updated somewhat
*/

class vctAdminOrderMsgs extends vctOrderMsgs implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // CEMENT
    public function GetActionKey() {
	return KS_PAGE_KEY_ORDER_MSG;
    }
    // OVERRIDE
    protected function SingularName() {
	return 'vcrAdminOrderMsg';
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
    // ++ ADMIN INTERFACE ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$sPfx = $this->ActionKey();
	$htSearchOut = NULL;

	$sSearchName = $sPfx.'-needle';
	$sInput = $oPage->ReqArgText($sSearchName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forText($sInput);
	    $htSearchOut .= $rs->Listing('No matching message records.');
	}
	$htFind = '"'.fcString::EncodeForHTML($sInput).'"';

	// build forms

	$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$htSearchForm = <<<__END__
<form method=post>
  Search for messages containing:
  <input name="$sSearchName" size=40 value=$htFind>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	$out = $htSearchHdr.$htSearchForm;
	if (!is_null($htSearchOut)) {
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      .$htSearchOut;
	}

	return $out;
    }

    // -- ADMIN INTERFACE -- //
}
class vcrAdminOrderMsg extends vcrOrderMsg implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftExecutableTwig;	// dispatch events
    use ftSaveableRecord;	// implements ChangeFieldValues()

    // ++ STATIC ++ //

    /*----
      RETURNS: descriptive text for the given media type
    */
    static private $arTypes;
    static protected function TypeText($idMedia) {
	if (empty($idMedia)) {
	    $sType = "Media not set!";
	} else {
	    if (empty(self::$arTypes)) {
		$ar = array(
		  vctOrders::MT_INSTRUC	=> 'instructions on submitted order',
		  vctOrders::MT_PKSLIP	=> 'packing slip annotations',
		  vctOrders::MT_EMAIL	=> 'email',
		  vctOrders::MT_PHONE	=> 'phone call',
		  vctOrders::MT_MAIL	=> 'snail mail',
		  vctOrders::MT_FAX	=> 'faxed message',
		  vctOrders::MT_LABEL	=> 'shipping label (for delivery instructions)',
		  vctOrders::MT_INT	=> 'internal use - stored, not sent'
		  );
		self::$arTypes = $ar;
	    }
	    $sType = self::$arTypes[$idMedia];
	}
	return $sType;
    }

    // -- STATIC -- //
    // ++ EVENTS ++ //

    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$id = $this->GetKeyValue();
	$rcOrd = $this->OrderRecord();
	$sOrd = $rcOrd->NameString();

	$sTitle = "msg id$id (ord $sOrd";
	$htTitle = "Order $sOrd";
	if ($this->HasPackage()) {
	    $rcPkg = $this->PackageRecord();
	    $sPkgSeq = $rcPkg->Seq();
	    $htTitle .= " Package #".$sPkgSeq;
	    
	    $sTitle .= ' '.$sPkgSeq;
	}
	$sTitle .= ')';
	$htTitle .= " message #$id";
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function Render() {
	return $this->AdminPage();
    }

    // -- EVENTS -- //
    // ++ FIELD VALUES ++ //
    
    protected function GetOrderID() {
	return $this->GetFieldValue('ID_Ord');
    }
    protected function GetPackageID() {
	return $this->GetFieldValue('ID_Pkg');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function HasPackage() {
	return !is_null($this->GetPackageID());
    }
    protected function PackageLink() {
	if ($this->HasPackage()) {
	    $rc = $this->PackageRecord();
	    return $rc->SelfLink_name();
	} else {
	    return '<i>(not package-specific)</i>';
	}
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function OrderTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_ORDERS,$id);
    }
    protected function PackageTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_PACKAGES,$id);
    }
    protected function MediaTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_ORDER_MSG_MEDIA,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    private $idOrd, $rcOrd;
    public function OrdObj() {
	throw new exception('OrdObj() is deprecated; call OrderRecord().');
    }
    protected function OrderRecord() {
	$id = $this->GetOrderID();

	$doLoad = TRUE;
	if (!empty($this->idOrd)) {
	    if ($this->idOrd == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcOrd = $this->OrderTable($id);
	    $this->idOrd = $id;
	}
	return $this->rcOrd;
    }
    private $idPkg, $rcPkg;
    public function PkgObj() {
	throw new exception('PkgObj() is deprecated; call PackageRecord().');
    }
    protected function PackageRecord() {
	$id = $this->Value('ID_Pkg');

	$doLoad = TRUE;
	if (!empty($this->idPkg)) {
	    if ($this->idPkg == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $tPkgs = $this->PackageTable();
	    if (is_null($id)) {
		$rcPkg = $tPkgs->GetEmpty();
		$idPkg = NULL;
		// fake package will need to know the order #
		$rcPkg->Value('ID_Order',$this->Value('ID_Ord'));
	    } else {
		$rcPkg = $tPkgs->GetRecord_forKey($id);
		$idPkg = $id;
	    }
	    $this->idPkg = $idPkg;
	    $this->rcPkg = $rcPkg;
	}
	return $this->rcPkg;
    }
    // RETURNS: Recordset of media types
    protected function MediaRecords() {
	return $this->MediaTable()->SelectRecords();
    }

    // -- DATA RECORD ACCESS -- //
    // ++ ADMIN INTERFACE ++ //

    public function AdminTable(array $iArgs=NULL) {
	if ($this->hasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <td>ID</td>
    <td>Pkg</td>
    <td>Media</td>
    <td>From / To</td>
    <td>Subject</td>
    <td>When</td>
    <td>Message / Notes</td>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$htID = $this->SelfLink();
		$row = $this->GetFieldValues();
		$idPkg = $row['ID_Pkg'];
		$idMed = $row['ID_Media'];
		$sMed = self::TypeText($idMed);
		$strFrom = $row['TxtFrom'];
		$strTo = $row['TxtTo'];
		$strSubj = $row['TxtRe'];
		$strWhenCreated = $row['WhenCreated'];
		$strMessage = $row['Message'];
		$strNotes = $row['Notes'];

		$htWho = $strFrom;
		$htWho .= ' &rarr; ';
		$htWho .= $strTo;

// (2011-10-08) this tried to show everything -- takes up too much space
		$htMsg = '&ldquo;'.$strMessage.'&rdquo;';
		if (!empty($strNotes)) {
		    $htMsg .= " ''$strNotes''";
		}
// */
		$strMessage = str_replace("\n",' / ',$strMessage);
		$lenMsg = strlen($strMessage);
		if ($lenMsg > 40) {
		    $txtMsgShow = fcString::EncodeForHTML(substr($strMessage,0,20));
		    $txtMsgShow .= ' <font color=#aaa>...</font> ';
		    $txtMsgShow .= fcString::EncodeForHTML(substr($strMessage,-20));
		} else {
		    $txtMsgShow = fcString::EncodeForHTML($strMessage);
		}
		$ftMsg = $txtMsgShow;

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$htID</td>
    <td>$idPkg</td>
    <td>$sMed</td>
    <td>$htWho</td>
    <td>$strSubj</td>
    <td>$strWhenCreated</td>
    <td>$ftMsg</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $strDescr = fcArray::Nz($iArgs,'descr');
	    $out = "\nNo messages$strDescr.";
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-10-08 created so we can tidy up the Order admin page a bit
	2017-11-19 This will need some updating.
    */
    public function AdminPage() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();

	$doEdit = $oPathIn->GetBool('edit');
	$doSave = $oFormIn->GetBool('btnSave');

	$oMenu = fcApp::Me()->GetHeaderMenu();
				// ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	$oMenu->SetNode($ol = new fcMenuOptionLink('do','edit',NULL,'cancel','edit the message'));
	  $doEdit = $ol->GetIsSelected();

	  /* 2017-11-20 old format
	$arPath = array();	// not sure if anything is needed here
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPath,'edit',NULL,NULL,NULL,'edit the message'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	*/
	$idMsg = $this->GetKeyValue();
	$sOrdNum = $this->OrderRecord()->NameString();
	$sTitle = "msg$idMsg ord#$sOrdNum";
//	$oPage->Skin()->SetPageTitle($sTitle);

	// save edits before showing events
	$frm = $this->PageForm();
	if ($doSave) {
	    $frm->Save();
	    $sMsgs = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsgs);
	}

	if ($this->IsNew()) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	$arCtrls['!Order'] = $this->OrderRecord()->SelfLink_name();
	$arCtrls['!Package'] = $this->PackageLink();

	$out = NULL;

	if ($doEdit) {
	    $out .= '<form method=post>';
	} else {
	    $arCtrls['ID_Media']	= self::TypeText($this->GetFieldValue('ID_Media'));	// do this right later
	}
/*
	$out = NULL;
	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>"		.$ctOrd.'</tr>';
	$out .= "\n<tr><td align=right><b>Package</b>:</td><td>"	.$ctPkg.'</tr>';
	$out .= "\n<tr><td align=right><b>Media</b>:</td><td>"		.$ctMedia.'</tr>';
	$out .= "\n<tr><td align=right><b>When Created</b>:</td><td>"	.$ctWhenCre.'</tr>';
	$out .= "\n<tr><td align=right><b>When Entered</b>:</td><td>"	.$ctWhenEnt.'</tr>';
	$out .= "\n<tr><td align=right><b>When Relayed</b>:</td><td>"	.$ctWhenRly.'</tr>';
	$out .= "\n<tr><td align=right><b>Notes</b>:</td><td>"		.$ctNotes.'</tr>';
	$out .= "\n<tr><td align=right><b>Message</b>:</td></tr>";
	$out .= "\n</table>";
	$out .= "<table align=center><tr><td><pre>$ctMsg</pre></td></tr></table>";
//*/
	$oTplt->SetVariableValues($arCtrls);
	$out = $oTplt->RenderRecursive();
	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=form-record>
  <tr><td align=right><b>Order</b>:</td><td>		[[!Order]]</td></tr>
  <tr><td align=right><b>Package</b>:</td><td>		[[!Package]]</td></tr>
  <tr><td align=right><b>Media</b>:</td><td>		[[ID_Media]]</td></tr>
  <tr><td align=right><b>When Created</b>:</td><td>	[[WhenCreated]]</td></tr>
  <tr><td align=right><b>When Entered</b>:</td><td>	[[WhenEntered]]</td></tr>
  <tr><td align=right><b>When Relayed</b>:</td><td>	[[WhenRelayed]]</td></tr>
  <tr><td align=right><b>Notes</b>:</td><td>		[[Notes]]</td></tr>
  <tr><td align=right><b>Message</b>:</td></tr>
  <tr><td colspan=2>[[Message]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
	2011-10-08 Re-adapted for VbzAdminOrderMsg
	2014-11-09 Modified for Ferreteria framework
	2016-03-06 updated to Ferreteria forms v2
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
 	    $oForm = new fcForm_DB($this);
 	    
	      $oField = new fcFormField_Num($oForm,'TxtFrom');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
		
	      $oField = new fcFormField_Num($oForm,'TxtTo');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
		
	      $oField = new fcFormField_Num($oForm,'TxtRe');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
		
	      $oField = new fcFormField_Num($oForm,'ID_Media');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->SetRecords($this->MediaRecords());
	      
	      $oField = new fcFormField_Num($oForm,'doRelay');
		$oCtrl = new fcFormControl_HTML_CheckBox($oField,array());

	      $oField = new fcFormField_Time($oForm,'WhenCreated');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());

	      $oField = new fcFormField_Time($oForm,'WhenEntered');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());

	      $oField = new fcFormField_Time($oForm,'WhenRelayed');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array());

	      $oField = new fcFormField_Text($oForm,'Message');
		$oCtrl = new fcFormControl_HTML($oField,array());

	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array());

	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
}
