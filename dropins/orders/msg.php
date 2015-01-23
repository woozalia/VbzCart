<?php
/*
  FILE: dropins/orders/msg.php -- customer order messages administration dropin for VbzCart
  HISTORY:
    2014-02-22 split off OrderMsg classes from order.php
*/

class VCT_OrderMsgs extends clsTable {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_OrderMsg');	// override parent
	  $this->Name('ord_msg');
	  $this->KeyName('ID');
	  $this->ActionKey(KS_PAGE_KEY_ORDER_MSG);
    }

    // -- SETUP -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->RenderSearch();
	return $out;
    }

    // -- DROP-IN API -- //
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
	$htFind = '"'.htmlspecialchars($sInput).'"';

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
class VCR_OrderMsg extends clsDataRecord_Menu {

    // ++ STATIC ++ //

    /*----
      RETURNS: descriptive text for the given media type
    */
    static private $arTypes;
    static protected function TypeText($idMedia) {
	if (empty(self::$arTypes)) {
	    $ar = array(
	      clsOrders::MT_INSTRUC	=> 'instructions on submitted order',
	      clsOrders::MT_PKSLIP	=> 'packing slip annotations',
	      clsOrders::MT_EMAIL	=> 'email',
	      clsOrders::MT_PHONE	=> 'phone call',
	      clsOrders::MT_MAIL	=> 'snail mail',
	      clsOrders::MT_FAX		=> 'faxed message',
	      clsOrders::MT_LABEL	=> 'shipping label (for delivery instructions)',
	      clsOrders::MT_INT		=> 'internal use - stored, not sent'
	      );
	    self::$arTypes = $ar;
	}
	return self::$arTypes[$idMedia];
    }

    // -- STATIC -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function OrderTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ORDERS,$id);
    }
    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_PACKAGES,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    private $idOrd, $rcOrd;
    public function OrdObj() {
	throw new exception('OrdObj() is deprecated; call OrderRecord().');
    }
    protected function OrderRecord() {
	$id = $this->Value('ID_Ord');

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
		$rcPkg = $tPkgs->GetItem($id);
		$idPkg = $id;
	    }
	    $this->idPkg = $idPkg;
	    $this->rcPkg = $rcPkg;
	}
	return $this->rcPkg;
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

		$row = $this->Row;
		//$id = $row['ID'];
		$htID = $this->AdminLink();
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
		    $txtMsgShow = htmlspecialchars(substr($strMessage,0,20));
		    $txtMsgShow .= ' <font color=#aaa>...</font> ';
		    $txtMsgShow .= htmlspecialchars(substr($strMessage,-20));
		} else {
		    $txtMsgShow = htmlspecialchars($strMessage);
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
	    $strDescr = nz($iArgs['descr']);
	    $out = "\nNo messages$strDescr.";
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-10-08 created so we can tidy up the Order admin page a bit
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');

	$arPath = array();	// not sure if anything is needed here
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPath,'edit',NULL,NULL,NULL,'edit the message'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString('Order/Package Message #'.$this->KeyValue());

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $ftSaveStatus = $this->AdminSave();
	}

	$rcPkg = $this->PackageRecord();

	if ($doEdit) {
	    $out .= '<form method=post>';
	    $frm = $this->EditForm();

	    $ctPkg	= $rcPkg->DropDown_ctrl('ID_Pkg','--not package-specific--');
	    $ctMedia	= $frm->Render('ID_Media');
	    $ctWhenCre	= $frm->Render('WhenCreated');
	    $ctWhenEnt	= $frm->Render('WhenEntered');
	    $ctWhenRly	= $frm->Render('WhenRelayed');
	    $ctMsg	= $frm->Render('Message');
	    $ctNotes	= $frm->Render('Notes');
	} else {
	    $ctPkg	= $rcPkg->AdminLink_name();
	    $ctMedia	= self::TypeText($this->Value('ID_Media'));	// do this right later
	    $ctWhenCre	= $this->Value('WhenCreated');
	    $ctWhenEnt	= $this->Value('WhenEntered');
	    $ctWhenRly	= $this->Value('WhenRelayed');
	    $ctMsg	= htmlspecialchars($this->Value('Message'));
	    $ctNotes	= $this->Value('Notes');
	}
	$ctOrd = $this->OrderRecord()->AdminLink_name();

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

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	return $out;
    }
    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
	2011-10-08 Re-adapted for VbzAdminOrderMsg
	2014-11-09 Modified for Ferreteria framework
    */
    private function EditForm() {
	if (is_null($this->frmEdit)) {
	    $frm = new clsForm_recs($this);

	    $frm->AddField(new clsFieldNum('ID_Pkg'),		new clsCtrlHTML());
	    $frm->AddField(new clsFieldNum('ID_Media'),	new clsCtrlHTML());
	    $frm->AddField(new clsField('TxtFrom'),		new clsCtrlHTML());
	    $frm->AddField(new clsField('TxtTo'),		new clsCtrlHTML());
	    $frm->AddField(new clsField('TxtRe'),		new clsCtrlHTML());
	    $frm->AddField(new clsFieldBool('doRelay'),	new clsCtrlHTML_CheckBox());
	    $frm->AddField(new clsFieldTime('WhenCreated'),	new clsCtrlHTML());
	    $frm->AddField(new clsFieldTime('WhenEntered'),	new clsCtrlHTML());
	    $frm->AddField(new clsFieldTime('WhenRelayed'),	new clsCtrlHTML());
	    $frm->AddField(new clsField('Message'),		new clsCtrlHTML_TextArea());
	    $frm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea());

	    $this->frmEdit = $frm;
	}
	return $this->frmEdit;
    }
    /*-----
      ACTION: Save the user's edits to the transaction
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::AdminSave()
	2011-01-02 Replaced with VbzAdminItem::AdminSave() version
	2011-10-08 Copied to VbzAdminOrderMsg
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
}
