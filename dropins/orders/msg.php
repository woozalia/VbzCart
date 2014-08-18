<?php
/*
  FILE: dropins/orders/msg.php -- customer order messages administration dropin for VbzCart
  HISTORY:
    2014-02-22 split off OrderMsg classes from order.php
*/

class VCT_OrderMsgs extends clsTable {
    //const TableName='ord_msg';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_OrderMsg');	// override parent
	  $this->Name('ord_msg');
	  $this->KeyName('ID');
	  $this->ActionKey('omsg');
    }
}
class VCR_OrderMsg extends clsDataSet {

    // ++ BOILERPLATE ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }

    // -- BOILERPLATE -- //
    // ++ DATA RECORD ACCESS ++ //

    private $idOrd, $objOrd;
    public function OrdObj() {
	$id = $this->Value('ID_Ord');

	$doLoad = TRUE;
	if (!empty($this->idOrd)) {
	    if ($this->idOrd == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objOrd = $this->objDB->Orders()->GetItem($id);
	    $this->idOrd = $id;
	}
	return $this->objOrd;
    }
    private $idPkg, $objPkg;
    public function PkgObj() {
	$id = $this->Value('ID_Pkg');

	$doLoad = TRUE;
	if (!empty($this->idPkg)) {
	    if ($this->idPkg == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $tblPkgs = $this->objDB->Pkgs();
	    if (is_null($id)) {
		$this->objPkg = $tblPkgs->GetEmpty();
		$this->idPkg = NULL;
		// fake package will need to know the order #
		$this->objPkg->Value('ID_Order',$this->Value('ID_Ord'));
	    } else {
		$this->objPkg = $tblPkgs->GetItem($id);
		$this->idPkg = $id;
	    }
	}
	return $this->objPkg;
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
		$strFrom = $row['TxtFrom'];
		$strTo = $row['TxtTo'];
		$strSubj = $row['TxtRe'];
		$strWhenCreated = $row['WhenCreated'];
		$strMessage = $row['Message'];
		$strNotes = $row['Notes'];

		$htWho = $strFrom;
		$htWho .= ' &rarr; ';
		$htWho .= $strTo;

/* (2011-10-08) this tried to show everything -- takes up too much space
		$htMsg = '&ldquo;'.$strMessage.'&rdquo;';
		if (!empty($strNotes)) {
		    $htMsg .= " ''$strNotes''";
		}
*/
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
    <td>$idMed</td>
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
	global $wgRequest,$wgOut;
	global $vgPage;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Order/Package Message #'.$this->KeyValue());
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objPkg = $this->PkgObj();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    $ctPkg	= $objPkg->DropDown_ctrl('ID_Pkg','--not package-specific--');
	    $ctMedia	= $frm->Render('ID_Media');
	    $ctWhenCre	= $frm->Render('WhenCreated');
	    $ctWhenEnt	= $frm->Render('WhenEntered');
	    $ctWhenRly	= $frm->Render('WhenRelayed');
	    $ctMsg	= $frm->Render('Message');
	    $ctNotes	= $frm->Render('Notes');
	} else {
	    $ctPkg	= $objPkg->AdminLink_name();
	    $ctMedia	= $this->Value('ID_Media');	// do this right later
	    $ctWhenCre	= $this->Value('WhenCreated');
	    $ctWhenEnt	= $this->Value('WhenEntered');
	    $ctWhenRly	= $this->Value('WhenRelayed');
	    $ctMsg	= htmlspecialchars($this->Value('Message'));
	    $ctNotes	= $this->Value('Notes');
	}
	$ctOrd = $this->OrdObj()->AdminLink_name();

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

	$wgOut->AddHTML($out);
	return NULL;
    }
    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
	2011-10-08 Re-adapted for VbzAdminOrderMsg
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldNum('ID_Pkg'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Media'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('TxtFrom'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('TxtTo'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('TxtRe'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldBool('doRelay'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsFieldTime('WhenCreated'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenEntered'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenRelayed'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Message'),		new clsCtrlHTML_TextArea());
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea());

	    $this->objForm = $objForm;
	}
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
