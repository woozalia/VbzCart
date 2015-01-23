<?php
/*
  HISTORY:
    2014-02-13 split address classes off from cust.php
*/
class VCT_MailAddrs extends clsCustAddrs {

    // ++ SETUP ++ //

    /*----
      HISTORY:
	2011-04-17 added ActionKey()
    */
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_MailAddr');
	  $this->ActionKey('addr');
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //

    protected function MailAddrsClass() {
	return KS_CLASS_MAIL_ADDRS;
    }

    // -- CLASS NAMES -- //
}
class VCR_MailAddr extends clsCustAddr {
    protected $objForm;

    //*** BOILERPLATE begin
    /*====
      SECTION: event logging
      HISTORY:
	2011-09-02 adding boilerplate event logging using helper class
    */ /* 2014-04-22 these are now inherited
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
    } */
    /*=====
      SECTION: admin links
    */
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
	2011-09-02 Writing AdminPage()
    */
//    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
//	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
//    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-03-31 copied from VbzAdminOrderTrxact to VbzAdminCust
	2012-01-03 copied from VbzAdminCust to clsAdminCustAddr
    *//*
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    } */
    //*** BOILERPLATE end

    // ++ BOILERPLATE HELPERS ++ //

    public function AdminLink($sText=NULL,$sPopup=NULL,array $arArgs=NULL) {
	$out = parent::AdminLink($sText,$sPopup,$arArgs);
	if ($this->IsVoid()) {
	    $out = "<span class='voided'>$out</span>";
	}
	return $out;
    }
    public function AdminLink_name() {
	$strVal = $this->AsSingleLine();
	return $this->AdminLink($strVal);
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
    // ++ WEB ADMIN UI ++ //

    public function AdminPage() {
	//$strAction = $vgPage->Arg('do');
	//$doAdd = ($strAction == 'add');
	$oPage = $this->Engine()->App()->Page();
	$isNew = is_null($this->KeyValue());
	$doEdit = $oPage->PathArg('edit') || $isNew;
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$strAct = $oPage->PathArg('do');

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
	    $sTitle = 'New Address';
	} else {
	    $sTitle = 'Address #'.$this->ID;
	}
/*
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strName);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();
*/
	// set up titlebar menu
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // 		array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  //new clsActionLink_option(array(),'merge',	'do'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString($sTitle);

	if ($doEdit || $doSave) {
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
	    $out .= "\n<form method=post>";
	    $objForm = $this->EditForm();

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
	    $ftCust	= $this->CustomerRecord()->AdminLink();
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
		//$vgPage->ArgsToKeep(array('page','id'));
		//$ftLink = $vgPage->SelfLink(array('do'=>'void'),'void now','void this address');
		$ftLink = $this->AdminLink('void now','void this address',array('do'=>'void'));
		$ctrlWhenVoid = " [ $ftLink ]";
	    }
	}

	$out = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>
  <tr><td align=right><b>Customer</b>:</td><td>$ftCust</td></tr>
  <tr><td align=right><b>When Active</b>:</td><td>$ftWhenAct</td></tr>
  <tr><td align=right><b>When Expires</b>:</td><td>$ftWhenExp</td></tr>
  <tr><td align=right><b>When Voided</b>:</td><td>$ctrlWhenVoid</td></tr>
  <tr><td align=right><b>Street</b>:</td><td>$ftStreet</td></tr>
  <tr><td align=right><b>Town</b>:</td><td>$ftTown</td></tr>
  <tr><td align=right><b>State</b>:</td><td>$ftState</td></tr>
  <tr><td align=right><b>Postal Code</b>:</td><td>$ftZip</td></tr>
  <tr><td align=right><b>Country</b>:</td><td>$ftCountry</td></tr>
  <tr><td align=right><b>Instructions</b>:</td><td>$ftExtra</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>$ftDescr</td></tr>
  <tr><td align=right><b>Full</b>:</td><td>$ftFull</td></tr>
  <tr><td align=right><b>Searchable (raw)</b>:</td><td>$ftSearchRaw</td></tr>
  <tr><td align=right><b>Searchable</b>:</td><td>$ftSearch</td></tr>
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

	$out .= $this->AdminPage_Lists($isNew);

	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';

	return $out;
    }
    /*----
      ACTION: Displays related list data
      HISTORY:
	2011-09-02 created
    */
    function AdminPage_Lists($iIsNew) {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();
	$out = '';
	if (!$iIsNew) {
	    // event listing
	    $out .= $oPage->SectionHeader('Events');
	    $out .= $this->EventListing();
	}
	return $out;
    }
    /*----
      HISTORY:
	2010-11-17 adapted from clsCtgGroup to clsAdminCustAddr
    */
    private function EditForm() {
	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_recs($this);

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
	return $this->objForm;
    }
    protected function AdminSave() {
	// update generated fields: searchable, searchable raw, full
	$arUpd = $this->CalcUpdateArray();

	$this->Value('Full',$arUpd['Full']);
	$this->Value('Search',$arUpd['Search']);
	$this->Value('Search_raw',$arUpd['Search_raw']);

	// boilerplate: save the data
	$out = $this->EditForm()->Save();
	return $out;
    }
}
