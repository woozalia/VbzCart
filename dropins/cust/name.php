<?php
/*
  HISTORY:
    2014-02-13 split name classes off from cust.php
    2017-01-06 somewhat updated
*/
class VCT_CustNames extends clsCustNames {
    use ftLinkableTable;
    
    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'VCR_CustName';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CUST_NAME;
    }

    // -- SETUP -- //
    // ++ ADMIN UI ++ //

    /*----
      HISTORY:
	2012-01-05 adapted from VbzAdminTitles_info_Cat to clsAdminCustNames
	2016-03-06 This is still using MediaWiki objects and will need revision. TODO
    */
    public function SearchPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strThis = 'SearchCustName';

	$strForm = $wgRequest->GetText('form');
	$doForm = ($strForm == $strThis);

	$strField = 'txtSearch.'.$strThis;
	$strFind = $wgRequest->GetText($strField);
	$htFind = '"'.fcString::EncodeForHTML($strFind).'"';

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

    // -- ADMIN UI -- //
}
class VCR_CustName extends clsCustName {
    use ftLinkableRecord;
    use ftLoggableRecord;

    // ++ TRAIT HELPERS ++ //
    
    /*----
      HISTORY:
	2011-06-03 'name' -> 'cust.name'
	2011-09-21 renaming from AdminLink() to AdminLink_details() (an earlier note had suggested doing this)
	2016-06-12 Now using constants for page keys. Still probably not being called.
    */
    public function SelfLink_details($iCustID=NULL) {
    // 2015-10-22 this will probably need rewriting
	$rcName = $this;
	$idName = $rcName->GetKeyValue();
	if (is_null($iCustID)) {
	    $idCust = $rcName->ID_Cust;
	} else {
	    $idCust = $iCustID;
	}

	$arCust = array('page'=>KS_ACTION_CUSTOMER,'edit'=>FALSE,'id'=>$idCust);
	$arName = array('page'=>KS_ACTION_CUST_NAME,'edit'=>FALSE,'id'=>$idName);
/*
	$htCont = $objText->SelfURL(array('page'=>'cust','edit'=>FALSE,'id'=>$idCust),TRUE);
	$htName = $objPage->SelfURL(array('page'=>'name','edit'=>FALSE,'id'=>$idName),TRUE);
*/
// here's where it will need it -- $objText came from $vgOut:
	$out =
	  '[C '.$objText->SelfLink($arCust,$idCust).']'.
	  '[N '.$objText->SelfLink($arName,$idName).'] '.$rcName->Name;
	return $out;
    }
    /*----
      HISTORY:
	2011-09-21 written for customer admin page
    */
    public function SelfLink_name() {
	return $this->SelfLink($this->NameString());
    }
    
    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //
    
    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    public function ListItem_Text() {
	return $this->NameString();
    }
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    
    // -- CALLBACKS -- //
    // ++ DATA FIELD VALUES ++ //
    
    // PUBLIC so Customer objects can access it
    public function NameString() {
	return $this->Value('Name');
    }
    public function IsActive() {
	return $this->Value('isActive');
    }
    
    // -- DATA FIELD VALUES -- //
    // ++ DATA RECORDS ++ //
    
    public function CustObj() {
	throw new exception('CustObj() has been renamed CustomerRecord().');
    }
    public function CustomerRecord() {
	$idCust = $this->Value('ID_Cust');
	$rc = $this->CustomerTable($idCust);
	return $rc;
    }
    
    // -- DATA RECORDS -- //
    // ++ DATA TABLES ++ //
    
    public function CustomerTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ADMIN_CUSTOMERS,$id);
    }
    
    // -- DATA TABLES -- //
    // ++ WEB UI ++ //
    
    /*----
      HISTORY:
	2012-01-04 finally implementing this
    */
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();
	$frm = $this->Form();

	$doSave = $oPage->ReqArgBool('btnSave');
	if ($doSave) {
	    $frm->Save();
	    $sMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$sMsg);
	}

	$doEdit = $oPage->PathArg('edit');

	$isNew = $this->IsNew();
	$sTitle = $this->ShortDescr();

	$arActs = array(
	  new clsActionLink_option(array(),'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString($sTitle);
		
	if ($this->IsNew()) {
	    $frm->ClearValues();
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doEdit);

	$arCtrls['ID'] = $this->SelfLink();
	
	$out = NULL;
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	} else {
	    $rcCust = $this->CustomerRecord();
	    $arCtrls['ID_Cust']	= $rcCust->SelfLink();
	}
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {
	    if ($isNew) {
		$out .= '<input type=submit name="btnSave" value="Create">';
	    } else {
		$out .= '<input type=submit name="btnSave" value="Save">';
	    }
	    $out .= '</form>';
	}

//	$oSect = new clsWikiSection_std_page($oFmt,'Event Log',3);
//	$out .= $oSect->Render();
	$out .= $this->EventListing();

	$out .= '<hr><span class=footer-stats>generated by '.__FILE__.' line '.__LINE__.'</span>';

	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
<!-- <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr> -->
<tr><td align=right><b>Customer</b>:</td><td>[[ID_Cust]]</td></tr>
<tr><td align=right><b>Name</b>:</td><td>[[Name]]</td></tr>
<tr><td align=right><b>Active</b>:</td><td>[[isActive]]</td></tr>
<tr><td colspan=2 class=table-section-header>Calculated:</td></tr>
<tr><td align=right><b>Searchable</b>:</td><td>[[NameSrch]]</td></tr>
<tr><td align=right><b>When Created</b>:</td><td>[[WhenEnt]]</td></tr>
<tr><td align=right><b>When Updated</b>:</td><td>[[WhenUpd]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    /*----
      HISTORY:
	2010-11-17 adapted from clsCtgGroup to clsAdminCustAddr
	2012-01-04 adapted from clsAdminCustAddr to clsAdminCustName
	2016-06-12 Updated to current Ferreteria forms.
    */
    private $frm;
    private function Form() {
	if (empty($this->frm)) {
	    $frm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Text($frm,'Name');
	      $oField = new fcFormField_Num($frm,'ID_Cust');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>5));
	      $oField = new fcFormField_BoolInt($frm,'isActive');
	      // calculated fields
	      $oField = new fcFormField_Text($frm,'NameSrch');
		$oField->ControlObject()->Editable(FALSE);
	      $oField = new fcFormField_Time($frm,'WhenEnt');
		$oField->ControlObject()->Editable(FALSE);
	      $oField = new fcFormField_Time($frm,'WhenUpd');
		$oField->ControlObject()->Editable(FALSE);

	    $this->frm = $frm;
	}
	return $this->frm;
    }
    
    // -- WEB UI -- //

}
