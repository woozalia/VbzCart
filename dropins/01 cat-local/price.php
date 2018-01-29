<?php
/*
  PURPOSE: price code functions for VbzCart
  HISTORY:
    2016-01-24 created
    2016-02-01 moved from cat-supp dropin to cat-local dropin
*/

class vctaPriceFx extends vcAdminTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'ctg_prc_funcs';
    }
    // CEMENT
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_PRICE;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_PRICE;
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
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    } */

    // -- EVENTS -- //
    // ++ RECORDS ++ //

    public function AdminRecords() {
	$sqlSort = 'Name';
	$rs = $this->SelectRecords(NULL,$sqlSort);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$rs = $this->GetData();		// get all records, active or not
	$out = $rs->AdminRows($this->AdminFields());
	return $out;
    }
    protected function AdminFields() {
	return array(
	  'ID'		=> 'ID',
	  'Name'	=> 'Name',
	  'PriceFactor'	=> 'Factor',
	  'PriceAddend'	=> 'Added',
	  'PriceRound'	=> 'Round',
	  'Notes'	=> 'Notes'
	  );
    }
    
    // -- ADMIN WEB UI -- //
}
class vcraPriceFx extends vcAdminRecordset {

    // ++ CALLBACKS ++ //

    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    public function ListItem_Link() {
	return $this->SelfLink($this->SummaryText());
    }
    public function ListItem_Text() {
	return $this->SummaryText();
    }

    // -- CALLBACKS -- //
    // ++ FIELD VALUES ++ //
    
    protected function NameString() {
	return $this->GetFieldValue('Name');
    }
    protected function PriceFactor() {
	return $this->GetFieldValue('PriceFactor');
    }
    protected function PriceAddend() {
	return $this->GetFieldValue('PriceAddend');
    }
    protected function PriceRound() {
	return $this->GetFieldValue('PriceRound');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function PriceAddendText() {
	$prc = $this->PriceAddend();
	if ($prc > 0) {
	    return '+'.$prc;
	} else {
	    return $prc;
	}
    }
    protected function SummaryText() {
	return $this->NameString()
	  .' x'.$this->PriceFactor()
	  .' '.$this->PriceAddendText()
	  .' r^ '.$this->PriceRound()
	  ;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ADMIN UI ++ //
    
    //++multiple++//
    
    protected function AdminRows_start(array $arOptions=NULL) {
	return "\n<table class=listing>";
    }
    protected function AdminField($sField,array $arOptions=NULL) {
	if ($sField == 'ID') {
	    $val = $this->SelfLink();
	} else {
	    $val = $this->GetFieldValue($sField);
	}
	return "<td>$val</td>";
    }
    
    //--multiple--//
    //++single++//

    protected function AdminPage() {
    	$oPage = $this->Engine()->App()->Page();

	$doSave = clsHTTP::Request()->GetBool('btnSave');
    	$doEdit = $oPage->PathArg('edit');

	// save edits before showing events
	if ($doSave) {
	    $ftSaveMsg = $this->PageForm()->Save();
	    $this->SelfRedirect($ftSaveMsg);
	}
	
	$arActs = array(
	  new clsActionLink_option(array(),
	    'edit',			// $iLinkKey
	    NULL,			// $iGroupKey
	    NULL,			// $iDispOff
	    'cancel',			// $iDispOn
	    'edit this supplier'	// $iDescr
	    ),
	  );
	$oPage->PageHeaderWidgets($arActs);
    
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->SelfLink();
	
	$out = NULL;
	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'<input type=submit name=btnCancel value="Cancel">'
	      .'</form>'
	      ;
	}
	
	return $out;
    }
    private $oPageForm;
    protected function PageForm() {
	if (empty($this->oPageForm)) {
	    // create fields & controls
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Text($oForm,'Name');
	      
	      $oField = new fcFormField_Num($oForm,'PriceFactor');
	      $oField = new fcFormField_Num($oForm,'PriceAddend');
	      $oField = new fcFormField_Num($oForm,'PriceRound');
	      
	      $oField = new fcFormField_Num($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));
		
	    $this->oPageForm = $oForm;
	}
	return $this->oPageForm;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=listing>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>[[Name]]</td></tr>
  <tr><td align=right><b>Price Factor</b>:</td><td>[[PriceFactor]]</td></tr>
  <tr><td align=right><b>Amount to Add</b>:</td><td>[[PriceAddend]]</td></tr>
  <tr><td align=right><b>Round to Nearest</b>:</td><td>[[PriceRound]]</td></tr>
  <tr><td colspan=2><b>Notes</b>:<br>[[Notes]]</td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    
    //--single--//
    
    // -- ADMIN UI -- //

}
