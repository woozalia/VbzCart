<?php
/*
  HISTORY:
    2014-02-13 split phone classes off from cust.php
*/
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
	    $this->logger = new clsLogger_DataSet($this,$this->Engine()->Events());
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
	$id = $this->GetKeyValue();
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
	    $ftCust	= $rcCust->SelfLink_name();
	    $ftActive	= fcString::NoYes($this->Value('isActive'));
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
