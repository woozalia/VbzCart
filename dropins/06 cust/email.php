<?php
/*
  HISTORY:
    2014-02-13 split email classes off from cust.php
    2017-03-28 y2017 remediation
*/
class vctAdminEmailAddrs extends vctCustEmails implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcrAdminEmailAddr';
    }
    public function GetActionKey() {
	return KS_ACTION_CUST_EMAIL;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return 'Nothing written yet.';	// should probably be a search function
    }

    // -- EVENTS -- //

}
class vcrAdminEmailAddr extends vcrCustEmail {
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
//    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
//	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
//    }
    public function AsHTML() {
	$txtEmail = $this->Email;
	$out = '<a href="mailto:'.$txtEmail.'">'.$txtEmail.'</a>';
	return $out;
    }
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	$doSave = clsHTTP::Request()->GetBool('btnSave');

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$htPath = $this->SelfURL(array('edit'=>!$doEdit));
	$id = $this->GetKeyValue();

	// set up titlebar menu
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'edit'),
	  );
	$sTitle = 'Credit Card ID '.$id;
	$out = $oPage->ActionHeader($sTitle,$arActs);

	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $objForm = $this->PageForm;

	    $ftSeq	= $objForm->Render('Seq');
	}
	$out .= 'To be written - see '.__FILE__.':'.__LINE__;
	return $out;
    }
}
