<?php
/*
  HISTORY:
    2014-02-13 split address classes off from cust.php
    2017-01-06 somewhat updated
*/
class vctAdminMailAddrs extends vctMailAddrs implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcrAdminMailAddr';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CUST_ADDR;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return 'Nothing written for this yet.';
    }

    // -- EVENTS -- //
    
}
class vcrAdminMailAddr extends vcrCustAddr implements fiLinkableRecord {
    use ftLinkableRecord;
    //use ftLoggableRecord;

    // ++ BOILERPLATE HELPERS ++ //
/*
    public function AdminLink($sText=NULL,$sPopup=NULL,array $arArgs=NULL) {
	$out = parent::AdminLink($sText,$sPopup,$arArgs);
	if ($this->IsVoid()) {
	    $out = "<span class='voided'>$out</span>";
	}
	return $out;
    } */
    public function SelfLink_name() {
	$strVal = $this->AsSingleLine();
	return $this->SelfLink($strVal);
    }

    // -- BOILERPLATE HELPERS -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    public function ListItem_Text() {
	return $this->AsSingleLine();
    }
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }

    // -- CALLBACKS -- //
    // ++ CLASS NAMES ++ //
    
    protected function CustomersClass() {
	return KS_CLASS_ADMIN_CUSTOMERS;
    }

    // -- CLASS NAMES -- //
    // ++ DATA FIELD VALUES ++ //
    
    protected function WhenActive() {
	return $this->Value('WhenAct');
    }
    protected function WhenExpires() {
	return $this->Value('WhenExp');
    }
    protected function LabelString() {
	return $this->Value('Label');
    }
    
    // -- DATA FIELD VALUES -- //
    // ++ DATA FIELD CALCULATIONS ++ //
    
    protected function HasCustomer() {
	return !is_null($this->GetCustID());
    }
    
    // -- DATA FIELD CALCULATIONS -- //
    // ++ WEB ADMIN UI ++ //

    public function AdminPage() {
	//$strAction = $vgPage->Arg('do');
	//$doAdd = ($strAction == 'add');
	$oPage = $this->Engine()->App()->Page();
	$idCust = $oPage->PathArg('cust');
	$frmEdit = $this->EditForm($idCust);

	// if we're saving, the page gets restarted, so get that out of the way:
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	if ($doSave) {
	    $frmEdit->Save();
	    $sMsg = $frmEdit->MessagesString();
	    $this->SelfRedirect(NULL,$sMsg);
	}
	
	$isNew = $this->IsNew();
	
	$sDo = $oPage->PathArg('do');
	$doVoid = ($sDo == 'void');
	$doEdit = ($sDo == 'edit') || $isNew;
	
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
	    $sTitle = 'Address #'.$this->GetKeyValue();
	}
	
	// set up titlebar menu
	$arActs = array(
	  // 		array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  //new clsActionLink_option(array(),'merge',	'do'),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->Skin()->SetPageTitle($sTitle);
		
	// 2016-06-11 new version begins here
	
	
	$out = NULL;
	
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$htID = $this->SelfLink();
	$arCtrls['ID'] = $htID;
	
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}
	if (!is_null($idCust)) {	// customer set from outside - not editable
	    $this->CustID($idCust);
	    $arCtrls['ID_Cust']	= $this->CustomerRecord()->SelfLink();
	}
	    
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	/* 2016-06-11 old version
	
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
*/
	if ($doEdit) {
	    if ($isNew) {
		$out .= '<input type=submit name="btnSave" value="Create">';
	    } else {
		$out .= '<input type=submit name="btnSave" value="Save">';
	    }
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    //$out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$out .= $this->AdminPage_Lists($isNew);

	$out .= '<hr><span class=footer-stats>generated by '.__FILE__.':'.__LINE__.'</span>';

	return $out;
    }
    // NOTE: this is read-only; use page to edit or create
    public function AdminRow_forCust($isOdd) {
	if ($this->IsVoid()) {
	    $htRowCSS = 'void';
	} else {
	    $htRowCSS = $isOdd?'odd':'even';
	}
	$htID = $this->SelfLink();
	$dt = $this->WhenActive();
	$ftAct = (empty($dt))?'-':(date('Y-m-d',$dt));
	$dt = $this->WhenExpires();
	$ftExp = (empty($dt))?'-':(date('Y-m-d',$dt));
	$ftAbbr = fcString::EncodeForHTML($this->LabelString());
	$ftFull = $this->AsSingleLine_withName();
	$out = "\n<tr class=$htRowCSS><td>$htID</td><td>$ftAct</td><td>$ftExp</td><td>$ftAbbr</td><td>$ftFull</td></tr>";
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
    private $frmEdit;
    private function EditForm($idCust=NULL) {
	if (is_null($this->frmEdit)) {
	    // create fields & controls

	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Text($oForm,'Name');
	      $oField = new fcFormField_Num($oForm,'ID_Cust');
		if (!is_null($idCust)) {
		    $oField->SetValue($idCust);
		    $oField->ControlObject()->Editable(FALSE);
		}
	      $oField = new fcFormField_Time($oForm,'WhenAct');
	      $oField = new fcFormField_Time($oForm,'WhenExp');
	      $oField = new fcFormField_Time($oForm,'WhenVoid');
	      $oField = new fcFormField_Text($oForm,'Street');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>40));
	      $oField = new fcFormField_Text($oForm,'Town');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>24));
	      $oField = new fcFormField_Text($oForm,'State');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>16));
	      $oField = new fcFormField_Text($oForm,'Zip');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>14));
	      $oField = new fcFormField_Text($oForm,'Extra');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));
	      $oField = new fcFormField_Text($oForm,'Country');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>16));
	      $oField = new fcFormField_Text($oForm,'Descr');
	      
	      // calculated fields
	      $oField = new fcFormField_Time($oForm,'WhenEnt');
		$oField->ControlObject()->Editable(FALSE);
	      $oField = new fcFormField_Time($oForm,'WhenUpd');
		$oField->ControlObject()->Editable(FALSE);
	      $oField = new fcFormField_Text($oForm,'Full');
		$oField->ControlObject()->Editable(FALSE);
	      $oField = new fcFormField_Text($oForm,'Search');
		$oField->ControlObject()->Editable(FALSE);
	      $oField = new fcFormField_Text($oForm,'Search_raw');
		$oField->ControlObject()->Editable(FALSE);
	    
	    /* 2016-06-11 old version
	    
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

	    $this->frmEdit = $objForm; */
	    $this->frmEdit = $oForm;
	}
	return $this->frmEdit;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>[[Name]]</td></tr>
  <tr><td align=right><b>Customer</b>:</td><td>[[ID_Cust]]</td></tr>
  <tr><td align=right><b>When Active</b>:</td><td>[[WhenAct]]</td></tr>
  <tr><td align=right><b>When Expires</b>:</td><td>[[WhenExp]]</td></tr>
  <tr><td align=right><b>When Voided</b>:</td><td>[[WhenVoid]]</td></tr>
  <tr><td align=right><b>Street</b>:</td><td>[[Street]]</td></tr>
  <tr><td align=right><b>Town</b>:</td><td>[[Town]]</td></tr>
  <tr><td align=right><b>State</b>:</td><td>[[State]]</td></tr>
  <tr><td align=right><b>Postal Code</b>:</td><td>[[Zip]]</td></tr>
  <tr><td align=right><b>Country</b>:</td><td>[[Country]]</td></tr>
  <tr><td align=right><b>Instructions</b>:</td><td>[[Extra]]</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>[[Descr]]</td></tr>
  <tr><td colspan=2 class=table-section-header>Calculated:</td></tr>
  <tr><td align=right><b>Full</b>:</td><td>[[Full]]</td></tr>
  <tr><td align=right><b>Searchable (raw)</b>:</td><td>[[Search_raw]]</td></tr>
  <tr><td align=right><b>Searchable</b>:</td><td>[[Search]]</td></tr>
  <tr><td colspan=2 class=table-section-header>Indicia:</td></tr>
  <tr><td align=right><b>When Created</b>:</td><td>[[WhenEnt]]</td></tr>
  <tr><td align=right><b>When Updated</b>:</td><td>[[WhenUpd]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    /* 2016-06-12 This would appear to be redundant now.
    protected function AdminSave() {
	// update generated fields: searchable, searchable raw, full
	$arUpd = $this->CalcUpdateArray();

	$this->Value('Full',$arUpd['Full']);
	$this->Value('Search',$arUpd['Search']);
	$this->Value('Search_raw',$arUpd['Search_raw']);

	// boilerplate: save the data
	$out = $this->EditForm()->Save();
	return $out;
    }*/
}
