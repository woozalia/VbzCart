<?php
/*
  PURPOSE: handles display/processing of form for managing Order Holds
  HISTORY:
    2017-06-04 started
*/

// PURPOSE: Order-oriented UI elements for managing Holds
class vcOrderHoldsForm {
    
    public function __construct(vcrOrder $rcOrder) {
	$this->SetOrderRecord($rcOrder);
    }
    
    // ++ SETUP ++ //
    
    private $rcOrder;
    protected function SetOrderRecord($rc) {
	$this->rcOrder = $rc;
    }
    protected function GetOrderRecord() {
	return $this->rcOrder;
    }
    protected function GetOrderID() {
	return $this->GetOrderRecord()->GetKeyValue();
    }
    
    // -- SETUP -- //
    // ++ TABLES ++ //
    
    protected function HoldTable() {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper(KS_CLASS_ORDER_HOLDS);
    }
    
    // -- TABLES -- //
    // ++ INPUT ++ //

    /*----
      PURPOSE: determines whether we need to process data from the Pull-editing form
    */
    protected function isRequestSubmitted() {
	return $this->isInvokeRequested()
	  || $this->isReleaseRequested()
	  ;
    }
    protected function isInvokeRequested() {
	return fcHTTP::Request()->GetBool('btnInvoke');
    }
    protected function isReleaseRequested() {
	return fcHTTP::Request()->GetBool('btnRelease');
    }
    /*----
      PURPOSE: determines whether the Hold-management form should be displayed
      NOTE: We don't bother to check whether form data has been submitted, because when that happens
	the form data is processed and then the page is reloaded, so we never get this far.
    */
    protected function isFormRequested() {
	return (fcApp::Me()->GetKioskObject()->GetInputObject()->GetString('form') == 'pull');
    }

    // -- INPUT -- //
    // ++ OUTPUT ++ //
 
    // API
    public function AdminRequest() {
	$tHolds = $this->HoldTable();
	
	if ($this->isRequestSubmitted()) {
	    $sNotes = $oPage->ReqArgText('notes');
	    
	    if ($this->isInvokeRequested()) {
		$idType = $oPage->ReqArgInt('type');
		$sTypeName = $tPulls->Name_forType($idType);
		$sMsg = "Pulled order as <b>$sTypeName</b>";
		if (!empty($sNotes)) {
		    $ftNotes = fcString::EncodeForHTML($sNotes);
		    $sMsg .= " with note &ldquo;<b>$ftNotes</b>&rdquo;.";
		}
		$this->Pull($idType,$sNotes);
	    } elseif ($this->isReleaseRequested()) {
		$sErr = $this->UnPull($sNotes);
		if (is_null($sErr)) {
		    // release successful
		    $sMsg = NULL;
		} else {
		    $sMsg .= "<b>Error</b>: $sErr";
		}
	    }
	    $this->SelfRedirect(array('form'=>FALSE),$sMsg);
	}
	
	// nothing above this displays output because it gets redirected
	$out = NULL;
	    
	if ($this->isFormRequested()) {
	    $isOrderPulled = $this->Pulled();
	    
	    $sAction = $isOrderPulled?'Release':'Pull';
	
	    $out .= "\n<table align=right class=listing><tr><td>"
	      .$oPage->ActionHeader($sAction.' this Order')
	      ;
   
	    if ($isOrderPulled) {
		$out .= <<<__END__
<form method=POST>
Notes:<br>
<textarea name=notes width=40 rows=5></textarea><br>
<input type=submit name=btnFree value="Release">
 - click to release order pulled at
__END__;
		$out .= ' '.$this->PullRecord()->Value('WhenPulled');
	    } else {
		$htTypes = $tPulls->DropDown_Types('type');
		$out .= <<<__END__
<form method=POST>
<b>Type of pull</b>: $htTypes<br>
Notes: <textarea name=notes cols=40 rows=5></textarea><br>
<input type=submit name=btnPull value="Pull">
 - click to pull order #
__END__;
		$out .= $this->Value('Number');
	    }
	    $out .= "\n</form>"
	      ."\n</td></tr></table>"
	      ;
	}
	return $out;
    }
    /*----
      API
      HISTORY:
	2017-06-04 partly adapted from old Pulls admin form; will need more work
    */
    public function AdminRows() {
	$rs = $this->HoldTable()->Records_forOrder($this->GetOrderID());
    
	$oHdr = new fcSectionHeader('Hold History');
	$out = $oHdr->Render();

	//$rcOrder = $this->GetOrderRecord();

	// build "place a hold" control
	
	$sMsg = 'Place a Hold on this order';
	$arLink['form'] = 'hold';
	$url = $this->GetOrderRecord()->SelfURL($arLink);		// 2017-04-11 not tested
	$htLink = fcHTML::BuildLink($url,$sMsg);
	
	if ($rs->hasRows()) {
	    $out .= <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>What</th>
    <th>When</th>
    <th>Notes</th>
  </tr>
__END__;
//	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
//		$cssClass = $isOdd?'odd':'even';
//		$isOdd = !$isOdd;

		$out .= $rs->AdminRow();
//		$out .= "\n<tr><td colspan=4>[ $htLink ]</td></tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out .= "\n<div class=content>No pulls. [ $htLink ]</div>";
	}
		
	return $out;
    }

    // -- OUTPUT -- //

}

class vcHoldRecordForm extends fcForm_DB {

    public function AdminRecord() {
	$rc = $this->GetRecordsObject();
	// save edits before showing events
    }
    
}