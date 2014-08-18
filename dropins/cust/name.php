<?php
/*
  HISTORY:
    2014-02-13 split name classes off from cust.php
*/
class VCT_CustNames extends clsCustNames {
    /*----
      HISTORY:
	2011-04-17 added ActionKey()
    */
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_CustName');	// override parent
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
	$out = <<<__END__
<h2>Customer Search</h2>
<form method=post>
Name or email address:
<input name=$strField size=40 value=$htFind>
<input type=hidden name=form value=$strThis>
<input type=submit name=btnSearch value=Go>
</form>
__END__;
	$wgOut->AddHTML($out); $out = '';

	if ($doForm && !empty($strFind)) {

	    // search customer names

	    $tblMain = $this;
	    $rs = $tblMain->Search($strFind);
	    $out .= 'SQL: '.$rs->sqlMake;
	    $arRows = NULL;
	    while ($rs->NextRow()) {
		$id = $rs->ID;
		$arRows[$id] = $rs->Values();
	    }

	    if (is_null($arRows)) {
		$out .= "\n<br>No matches found for <b>$htFind</b> in customer names.<br>";
	    } else {
		$out .= "\n<br>Matches for <b>$htFind</b> in customer names:<br>";
		$wgOut->AddHTML($out); $out = '';

		$obj = $tblMain->SpawnItem();

		$out .= '<ul>';
		foreach ($arRows as $id => $row) {
		    $obj->Values($row);
		    $out .= "\n<li>".$obj->AdminLink_details();
		}
		$out .= '</ul>';
	    }

	    // search customer emails

	    // TODO

	    $wgOut->AddHTML($out); $out = '';
	}

	return $out;
    }
}
class VCR_CustName extends clsCustName {

    // == BOILERPLATE
    /*----
      HISTORY:
	2011-09-21 added for customer admin page
    */
    /* 2014-07-12 redundant
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminRedirect($this,$iarArgs);
    }*/
    /*====
      BOILERPLATE: event logging
      HISTORY:
	2010-10-30 was using old boilerplate event-handling methods; now using helper class boilerplate
	  Event methods removed from plural class; helper-class methods added to singular class
    */ /* 2014-04-22 now inherited
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->Engine()->App()->Events());
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
    } */
    // --/BOILERPLATE--
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
	$oFmt = new clsWikiFormatter($vgPage);

	$oSect = new clsWikiSection_std_page($oFmt,$strName,2);
	//$oSect->PageKeys(array('page','id'));
	$oLink = $oSect->AddLink_local(new clsWikiSectionLink_keyed(array(),'edit'));
	  $oLink->Popup('edit this customer name');
	$out = $oSect->Render();

/*
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,"Name ID $id");
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();
*/

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
	    $out .= $oSect->FormOpen();
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

	$out .= <<<__END__
<table>
<!-- <tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr> -->
<tr><td align=right><b>Customer</b>:</td><td>$ftCust</td></tr>
<tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>
<tr><td align=right><b>Active</b>:</td><td>$ftActive</td></tr>
<tr><td align=center colspan=2>non-editable data</td></tr>
<tr><td align=right><b>Searchable</b>:</td><td>$ftSrch</td></tr>
</table>
__END__;
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

	$oSect = new clsWikiSection_std_page($oFmt,'Event Log',3);
	$out .= $oSect->Render();
	$out .= $this->EventListing();

	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';

	$wgOut->AddHTML($out);
    }
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
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
	    $objForm->AddField(new clsField('ID_Cust'),	new clsCtrlHTML(array('size'=>40)));
	    //$objForm->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML(array('size'=>24)));
	    //$objForm->AddField(new clsFieldBool_Int('isActive'),	new clsCtrlHTML(array('size'=>24)));
	    $objForm->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML_CheckBox());

	    $this->objForm = $objForm;
	}
    }
}
