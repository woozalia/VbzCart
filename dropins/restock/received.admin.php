<?php
/*
  PURPOSE: classes for handling received restocks
  HISTORY:
    2013-12-18 created to reduce confusion
*/

class vctaRstksRcvd extends vctRstksRcvd {
    use ftLinkableTable;
    use vtRestockTable_admin;

    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return 'vcraRstkRcvd';
    }
    // CEMENT (I think)
    public function GetActionKey() {
	return KS_ACTION_RESTOCK_RECEIVED;
    }
    
    // -- SETUP -- //
    // ++ TRAIT HELPERS ++ //
    
    /*----
      HISTORY:
	2010-12-03 Created for receiving process
    */
    public function SelfLink_name() {
	throw new exception('Document who calls this.');
	return $this->SelfLink($this->Name());
    }
    /*----
      RETURNS: recordset that includes all fields needed for admin functions
      NOTE: This may sometimes be called twice -- once to gather data for a summary of some kind,
	the second time to gather filtered data for display.
    */
    protected function AdminRecords($sqlFilt,$sqlSort) {
	$sqlo = new clsSQLFilt('');
	$sqlo->Order($sqlSort);
	$sqlo->AddCond($sqlFilt);
	
	$sqlCond = $sqlo->RenderQuery();
	return $this->DataSet($sqlCond);
    }
    
    // -- TRAIT HELPERS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec() {
	return $this->AdminRows();
    }

    // -- DROP-IN API -- //
    // ++ CLASS NAMES ++ //
    
    protected function RequestsClass() {
	return KS_ADMIN_CLASS_RESTOCK_REQUESTS;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    // PUBLIC so Request object can call it
    public function Records_forRequest($id) {
	return $this->SelectRecords('ID_Request='.$id,$this->SQLstr_Sorter_Date());
    }
    protected function RowsApplicable() {
	// sorting preference: when received, when shipped, when debited
	$rs = $this->SelectRecords(NULL,$this->SQLstr_Sorter_Date().' DESC');
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ADMIN UI ++ //
    
    protected function Fields_toDisplay() {
	return array(
	    'ID'		=> 'ID',
	    'ID_Request'	=> 'Req ID',
	    'SuppInvcNum'	=> 'S.Inv#',	// supplier invoice number
	    'CarrierDescr'	=> 'Carrier',
	    //'InvcEntry' - invoice lines as parseable text - not displayed in listing
	    'WhenShipped'	=> 'Shipped', // when supplier shipped the package
	    'WhenReceived'	=> 'Received', // when package was received from supplier
	    'WhenDebited'	=> 'Debited', // when charge for order was debited
	    'TrackingCode'	=> 'Tracking',	// carrier's tracking number
	    'TotalCalcMerch'	=> 'calculated',	// total cost of merchandise calculated from what was received
/* from supplier invoice */
	    'TotalInvMerch'	=> 'merch',	// total cost of merchandise as invoiced
	    'TotalInvShip'	=> 's/h',	// total shipping cost as invoiced
	    'TotalInvAdj'	=> 'adj',	// total invoice adjustments (merch + s/h + adj = final)
	    'TotalInvFinal'	=> 'final',	// final total on invoice (must match amt paid)
	    'InvcCondition'	=> 'cond',	// paperwork condition: 0 = absent, 1 = partial, 2 = complete
	    'PayMethod'		=> 'paymt',	// how payment was made, if not same as restock request
	    'Notes'		=> 'Notes'	// human-entered notes
	  );
    }
    
    // -- ADMIN UI -- //
}

class vcraRstkRcvd extends vcrRstkRcvd {
    use ftLinkableRecord;
    use ftShowableRecord;
    use ftLoggableRecord;
    use vtRestockRecords_admin;

    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    // CALLBACK for AdminRows_forItem()
    public function SortingKey() {
	return $this->BestDate();
    }

    // -- CALLBACKS -- //
    // ++ TRAIT HELPER ++ //
    
    public function SelfLink_name() {
	return $this->SelfLink($this->Name());
    }
    
    // -- TRAIT HELPER -- //
    // ++ DATA FIELDS ++ //

    // PUBLIC so lines can read it
    public function InvoiceNumber() {
	return $this->Value('SuppInvcNum');
    }
    protected function CarrierText() {
	return $this->Value('CarrierDescr');
    }
    // PUBLIC so lines can read it
    public function WhenShipped() {
	return $this->Value('WhenShipped');
    }
    // PUBLIC so lines can read it
    public function WhenReceived() {
	return $this->Value('WhenReceived');
    }
    // PUBLIC so lines can read it
    public function WhenDebited() {
	return $this->Value('WhenDebited');
    }
    protected function InvoiceTotal_Merch() {
	return $this->Value('TotalInvMerch');
    }
    protected function InvoiceTotal_Final() {
	return $this->Value('TotalInvFinal');
    }
    protected function PaymentMethod_text() {
	return $this->Value('PayMethod');
    }
    protected function NotesText() {
	return $this->Value('Notes');
    }
    
    // -- DATA FIELDS -- //
    // ++ DATA FIELD CALCULATIONS ++ //
    
    // USED FOR self-link
    protected function Name() {
	$s = $this->InvoiceNumber();
	if (is_null($s)) {
	    $s = $this->GetKeyValue();
	} else {
	    $s = "inv#".$s;
	}
	return $this->SupplierRecord()->CatKey().' '.$s;
    }
    protected function InvoiceCondition_short() {
	$vCond = $this->Value('InvcCondition');
	if (is_null($vCond)) {
	    $out = '--';
	} else {
	    $nCond = (int)$vCond;
	    if (($nCond >= 0) && ($nCond < 3)) {
		$ar = array('none','part','full');
		$out = $ar[$nCond];
	    } else {
		$out = "?$vCond?";
	    }
	}
	return $out;
    }
    protected function HasRequest() {
	return !is_null($this->RequestID());
    }
    protected function RequestLink() {
	if ($this->HasRequest()) {
	    $out = $this->RequestRecord()->SelfLink();
	} else {
	    $out = 'n/a';
	}
	return $out;
    }
    // PUBLIC so line records can use it
    public function RequestLink_name() {
	if ($this->HasRequest()) {
	    $out = $this->RequestRecord()->SelfLink_name();
	} else {
	    $out = 'no request';
	}
	return $out;
    }
    protected function DatesAvailable($yrCur=NULL) {
	return
	  fcString::IfPresent(
	    $this->WhenShipped(),
	    'sh&nbsp;'.clsDate::DefaultYear($this->WhenShipped(),$yrCur).'<br>'
	    )
	  .fcString::IfPresent(
	    $this->WhenReceived(),
	    'rc&nbsp;'.clsDate::DefaultYear($this->WhenReceived(),$yrCur).'<br>'
	    )
	  .fcString::IfPresent(
	    $this->WhenDebited(),
	    'db&nbsp;'.clsDate::DefaultYear($this->WhenDebited(),$yrCur)
	    )
	  ;
    }
    protected function BestDate() {
	$dt = $this->WhenShipped();
	if (is_null($dt)) {
	    $dt = $this->WhenReceived();
	}
	if (is_null($dt)) {
	    $dt = $this->WhenDebited();
	}
	return $dt;
    }
    
    // -- DATA FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //
    
    protected function LinesClass() {
	return KS_ADMIN_CLASS_RESTOCK_LINES_RECEIVED;
    }

    // -- CLASS NAMES -- //
    // ++ ARRAYS ++ //
    
    // PUBLIC so Table object can use it
    public function Array_forFilterMenu() {
	$yrLast = NULL;
	$arSupp = array();
	
	while ($this->NextRow()) {
	    if ($this->HasSupplier()) {
		$rcSupp = $this->SupplierRecord();
		if ($rcSupp->HasRows()) {
		    $sSuppKey = $rcSupp->CatKey();
		
		    // build array for Suppliers menubar
		    if (!array_key_exists($sSuppKey,$arSupp)) {
			$arSupp[$sSuppKey]['vals'] = $rcSupp->Values();
			$arSupp[$sSuppKey]['text'] = 1;
		    } else {
			$arSupp[$sSuppKey]['text']++;
		    }
		}
	    }
	    
	    // gather data for Years menubar
	    $sSortDate	= $this->BestDate();
	    if (is_null($sSortDate)) {
		$yrSort = NULL;
	    } else {
		$dtSort	= strtotime($sSortDate);
		$yrSort	= date('Y',$dtSort);
	    }
	    if ($yrLast != $yrSort) {
		$yrLast = $yrSort;
		$arYear[$yrSort] = 1;
	    } else {
		$arYear[$yrSort]++;
	    }
	}
	$this->RewindRows();
	$ar = array('supp'=>$arSupp,'date'=>$arYear);
	return $ar;
    }
    
    // -- ARRAYS -- //
    // ++ ADMIN UI ++ //

    //+ROWS+//
    
    private $nYearLast;
    protected function AdminRows_start() {

	// set up action menu (2016-02-21 not sure if this makes sense anymore)
	$arBase = array(
	  'supp' => FALSE,
	  'year' => FALSE
	  );
	$idReq = $this->RequestID();
	if (!is_null($idReq)) {
	    $arBase[KS_ACTION_RESTOCK_REQUEST] = $idReq;
	}
	$arMenu = array(
	  new clsActionLink_option(
	    $arBase,	// other stuff to always appear in URL, regardless of section's menu state
	    KS_NEW_REC,	// LinkKey: value that the group should be set to when this link is activated
	    'id',	// GroupKey: group's identifier string in URL (.../iGroupKey:iLinkKey/...)
			  // if NULL, presence of link key (.../iLinkKey/...) is a flag
	    NULL,	// DispOff: text to display when link is not activated - defaults to LinkKey
	    NULL,	// DispOn: text to display when link is activated - defaults to DispOff
	    'enter a new received restock from scratch'	// description -- appears in hover-over popup
	    ),
	  );
	/*
	  2016-02-21 TODO: We need a generalized "section header" or "section type" that
	    can also be a page header:
	    When this fx is standalone, it should modify the page header -- but when
	    it is a subsection (e.g. of a Restock Request page), it should just modify
	    the current section. The object should receive a section object that could
	    be either one (by default it would be the Page), and just modify that.
	*/
	//$this->PageObject()->PageHeaderWidgets($arMenu);
	$out = $this->PageObject()->ActionHeader('Restock Shipments Received',$arMenu)
	  ."\n<table class=listing>"
	  ;
	return $out;
    }
    protected function AdminRows_head() {
	return <<<__END__

  <tr>
    <th>ID</th>
    <th title="restock request ID">Req</th>
    <th title="supplier">Supp</th>
    <th title="warehouse">Whse</th>
    <th>Supplier's<br>Invoice #</th>
    <th title="how this was shipped">Carrier</th>
    <th title="sh=shipped rc=received db=debited">When</th>
    <th title="merchandise total from invoice">merch</th>
    <th title="final total from invoice">final</th>
    <th title="condition of paperwork">cond</th>
    <th title="payment method">paymt</th>
    <th>Notes</th>
  </tr>
__END__;
    }
    protected function AdminRows_row() {
    
	// do a year header if necessary
	$sDate = $this->BestDate();
	if (is_null($sDate)) {
	    $yrSort = NULL;
	    $yrShow = 'no date';
	    $htWhen = $this->DatesAvailable();
	} else {
	    $dtSort = strtotime($sDate);
	    $yrSort = date('Y',$dtSort);
	    $yrShow = $yrSort;
	    $htWhen = $this->DatesAvailable($yrSort);
	}
	if ($this->nYearLast != $yrSort) {
	    $this->nYearLast = $yrSort;
	    $yrHdr = '<tr><td colspan=5 class="table-section-header">'.$yrShow.'</td></tr>';
	} else {
	    $yrHdr = NULL;
	}
    
	$cssClass = $this->AdminRow_CSSclass();
	$htID = $this->SelfLink();
	$htReq = $this->RequestLink();
	$htSupp = $this->SupplierCatKey();
	$htWhse = $this->WarehouseName();
	$htInvc = $this->InvoiceNumber();
	$htCarr = $this->CarrierText();
	$htTotMerch = $this->InvoiceTotal_Merch();
	$htTotFinal = $this->InvoiceTotal_Final();
	$htCond = $this->InvoiceCondition_short();
	$htPay = $this->PaymentMethod_text();
	$htNotes = '<span class=line-notes>'.$this->NotesText().'</span>';
	
	return <<<__END__
  $yrHdr
  <tr class="$cssClass">
    <td>$htID</td>
    <td>$htReq</td>
    <td align=center>$htSupp</td>
    <td align=center>$htWhse</td>
    <td>$htInvc</td>
    <td>$htCarr</td>
    <td align=right>$htWhen</td>
    <td align=right>$htTotMerch</td>
    <td align=right>$htTotFinal</td>
    <td align=center>$htCond</td>
    <td>$htPay</td>
    <td>$htNotes</td>
  </tr>
__END__;
    }
    // CALLBACK for AdminRows()
    protected function AdminField($sField) {
	switch ($sField) {
	  case 'ID':
	    $val = $this->SelfLink();
	    break;
	  case 'ID_Request':
	    $val = $this->RequestRecord()->SelfLink();
	    break;
	  case 'Notes':
	    $txt = $this->NotesText();
	    $val = "<small>$txt</small>";
	    break;
	  default:
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    /*----
      ACTION: Renders administration of the current set of records
    */
    public function AdminRows_old() {
	$out = NULL;
	$rs = $this;
	if ($rs->hasRows()) {
	    $out .= <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Invc #</th>
    <th>via</th>
    <th>Tracking</th>
    <th></th>	<!-- blank for Year -->
    <th>Shipped</th>
    <th>Recd</th>
    <th>Debited</th>
    <th>$ Final</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = FALSE;
	    while ($rs->NextRow()) {
		$ftClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$ftID = $rs->SelfLink();
		$ftInvc = $rs->Value('SuppInvcNum');
		$ftShip = $rs->Value('CarrierDescr');
		$ftTrack = $rs->Value('TrackingCode');

		$arWhen = array($rs->Value('WhenShipped'),$rs->Value('WhenReceived'),$rs->Value('WhenDebited'));
		$strWhen = FirstNonEmpty($arWhen);
		$dtWhen = new xtTime($strWhen);
		$yrWhen = $dtWhen->Year();
		$ftYear = $yrWhen;
		$ftWhenShip = clsDate::DefaultYear($rs->Value('WhenShipped'),$yrWhen);
		$ftWhenRecd = clsDate::DefaultYear($rs->Value('WhenReceived'),$yrWhen);
		$ftWhenPaid = clsDate::DefaultYear($rs->Value('WhenDebited'),$yrWhen);

		$ftAmtFinal = $rs->Value('TotalInvFinal');
		$ftNotes = $rs->Value('Notes');

		$out .= <<<__END__
  <tr class="$ftClass">
    <td>$ftID</td>
    <td>$ftInvc</td>
    <td>$ftShip</td>
    <td>$ftTrack</td>
    <td>$ftYear</td>
    <td>$ftWhenShip</td>
    <td>$ftWhenRecd</td>
    <td>$ftWhenPaid</td>
    <td>$ftAmtFinal</td>
    <td>$ftNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	}

	return $out;
    }
    //-ROWS-// - multiple records
    //+BULK+// - invoice bulk entry

    protected function Render_BulkEntry_Invoice($sVal=NULL) {
	$htVal = fcString::EncodeForHTML($sVal);
	return <<<__END__
<form method=POST>
  <textarea name=txtInvcEntry cols=50 rows=20>$htVal</textarea><br>
  <input type=submit name=btnParseInvc value="Parse">
</form>
__END__;
    }
    static protected function HelpLink_EntryFormat() {
	return '(<a href="'
	  .KWP_HELP_SCREEN
	  .'rstk/rcv/single/invoice entry/format'
	  .'" target=help title="click for format information">format</a>)'
	  ;
    }
    /*----
      INPUT: 
	* if btnParseInvc pressed, uses form input
	* if patharg includes 'parse', uses stored InvcEntry field
    */
    protected function Handle_BulkEntry_Invoice() {
	$out = NULL;
	$oPage = $this->PageObject();
	
	if ($oPage->ReqArgBool('btnParseInvc')) {
	    $doParse = TRUE;
	    $sInvcText = trim($oPage->ReqArgText('txtInvcEntry'));
	} else {
	    $doParse = FALSE;
	    $sInvcText = NULL;
	}
	
	$doEnter = FALSE;
	if ($sDoInvc = $oPage->PathArg('invc')) {
	    switch($sDoInvc) {
	      case 'enter':
		$doEnter = TRUE;
		$strParse = 'Enter invoice lines to parse';
		break;
	      case 'parse':
		$doParse = TRUE;
		$sInvcText = trim($this->Value('InvcEntry'));
		break;
	    }
	}
	
	$out = NULL;
	
	if ($doParse) {
	    $arStat = $this->ParseInvoice($sInvcText);
	    //$ctInvcText .= $arStat['show'];
	    $out .= $arStat['show'];
	    $cntNoFnd = $arStat['cnt.nofnd'];
	    if ($cntNoFnd > 0) {
		$doEnter = TRUE;
		$strParse = 'Try again to parse items from text';
	    } else {
		$strParse = NULL;
		die('Are we getting to here?');
		$this->SelfRedirect();	// success -- back to regular page
	    }
	}
	
	if ($doEnter) {
	    $htHelp = self::HelpLink_EntryFormat();
	    $out .= "<b>$strParse</b> $htHelp:"
	      .'<br>'
	      .$this->Render_BulkEntry_Invoice($sInvcText)
	      ;
	}
	
	return $out;
    }
    /*----
      NOTES:
	Ideally there should be no need to handle lost items gracefully, because any items on the invoice
	  should already be in the catalog because they were in the request... but in the real world,
	  (a) sometimes items ship that were not requested, (b) typos happen, (c) stuff gets requested without going
	  through VbzCart first.
      RETURNS: array
	array[show] = results to display
	array[cnt.nofnd] = number of items not found in the catalog data
    */
    protected function ParseInvoice($txtItems) {
	$out = '';
	$xts = new fcStringBlock($txtItems);
	$arInLines = $xts->ParseTextLines(array('line'=>'arrx','blanks'=>" \t\n\r"));
	if (is_array($arInLines)) {
	    $cntLine = 0;
	    $cntFound = 0;	// count of items not found
	    $cntLost = 0;	// count of items not found
	    $txtFound = '';	// list of items found, plaintext format
	    $htFound = '';	// HTML list of items found
	    $txtLost = '';	// list of items not found
	    $htLost = '';	// HTML list of items not found
	    $db = $this->Engine();	// for sanitizing fields
	    
	    $arFieldAliases = array(
	      'QtyOrd'	=> 'InvcQtyOrd',
	      'QtySent'	=> 'InvcQtySent',
	      'CatNo'	=> 'InvcCatNo',
	      'Cat#'	=> 'InvcCatNo',
	      'Descr'	=> 'InvcDescr',
	      'CostPer'	=> 'CostInvPer',
	      'CostTot'	=> 'CostInvTot',
	      // these do not correspond directly to a data field -- additional processing needed:
	      'Style'	=> '!TitleCatKey',
	      'Title'	=> '!TitleCatKey',
	      'Size'	=> '!ItemCatSfx',
	      );
	    
	    // iterate through lines in parsing output, and build $arChg:
	    foreach ($arInLines as $idx => $arInLine) {
		if ($idx == 0) {
		    // first line defines the format - translate any aliases and store in arFormat
		    foreach($arInLine as $idx => $sName) {
			if (array_key_exists($sName,$arFieldAliases)) {
			    $sKey = $arFieldAliases[$sName];
			} else {
			    $sKey = $sName;
			}
			$arFmtCols[$idx] = $sKey;
			$arFmtKeys[$sKey] = $idx;
			$cntCols = count($arFmtCols);
		    }
		    $hasItemCatNum = array_key_exists('InvcCatNo',$arFmtKeys);
		    // TODO: sanity check -- show an error if there isn't InvcCatNo OR !TitleCatKey AND !ItemCatSfx
		    $hasDescription = array_key_exists('InvcDescr',$arFmtKeys);
		} else {
		    $arChg = NULL;
		    $sSCat = NULL;
		    $arOut = NULL;
//echo 'ARINLINE:'.clsArray::Render($arInLine);
		    // iterate through field data
		    foreach ($arInLine as $idxCol => $val) {
			if ($idxCol < $cntCols) {
			    
			    $sCol = $arFmtCols[$idxCol];
			    if (($sCol != '') && ($sCol != '*')) {	// '' and '*' mean "discard column"
				if (clsArray::Exists($arOut,$sCol)) {
				    $val = $arOut[$sCol].' '.$val;
				}
				$arVal[$sCol] = $val;
				if (strpos($sCol,'!') === 0) {
				    // special column -- don't store directly
				    //echo "SKIPPING COLUMN [$sCol].<br>";
				} else {
				    $arChg[$sCol] = $db->SanitizeAndQuote($val);
				}
			    }
			} else {
			    //echo "Column #$idx discarded [$val].<br>";
			}
		    }
		    
		    // get catalog # to find:
		    if ($hasItemCatNum) {
			$sSCat = $arVal['InvcCatNo'];
		    } else {
			$sSCat = $arVal['!TitleCatKey'].'/'.$arVal['!ItemCatSfx'];
			$arVal['InvcCatNo'] = $sSCat;
			$arChg['InvcCatNo'] = $db->SanitizeAndQuote($sSCat);
		    }

		    //echo 'ARVAL:'.clsArray::Render($arVal);
		    //echo 'ARCHG:'.clsArray::Render($arChg); die();

		    // look up item record from scat#
		    if (is_null($sSCat)) {
			$out .= '<br>Error: No supplier catalog # given in line '.$idx;
			$cntLost++;
		    } else {
			$rcSupp = $this->SupplierRecord();
			$rcItem = $rcSupp->GetItem_bySCatNum($sSCat);
			if (is_null($rcItem)) {
			    $cntLost++;
			    $htLost .= '<br>'.$sSCat;
			    if ($hasDescription) {
				$htLost .= ' <small>'.$arVal['InvcDescr'].'</small>';
			    }
			    $txtLost .= ' '.$sSCat;
			} else {
			    $idItem = $rcItem->GetKeyValue();
			    $arChg['ID_Item'] = $idItem;
			    $arChgs[$idx] = $arChg;
			    $cntFound++;
			    $htFound .= '<br>'.$rcItem->SelfLink_CatNum_wDetails();
			    $txtFound .= ' '.$rcItem->CatNum()."(ID=$idItem)";
			}
		    }
		} // -IF data line (not format line)
	    } // -FOR iterate through input parser output

	    $out .= "\n<table class=listing><tr class=odd>";
	    if ($cntFound > 0) {
		$txtDescr = $cntFound.' item'.Pluralize($cntFound).' found:';
		$out .= "  <td valign=top>$txtDescr$htFound</td>";
		$txtFound = $txtDescr.$txtFound;	// for event log
	    }
	    if ($cntLost > 0) {
		$out .= '<td valign=top>Could not find '.$cntLost.' item'.Pluralize($cntLost).':'.$htLost;
	    }
	    $out .= '</tr></table>';
	    if (($cntLost == 0) && ($cntFound > 0)) {
		// log the update and do it
		$arEv = array(
		  'descr'	=> $db->SanitizeAndQuote($txtFound),
		  'code'	=> '"ITB"',	// item bulk entry
		  'where'	=> __METHOD__
		  );
		$rcEv = $this->CreateEvent($arEv);
		$tblLines = $this->LineTable();
		$idRcd = $this->GetKeyValue();
		// disable all existing lines first, to avoid leftovers
		$this->ClearLines();
		$txtErr = $db->getError();
		if (!empty($txtErr)) {
		    $out .= '<br>Error deactivating lines: '.$txtErr;
		    $db->ClearError();
		}
		$cntNew = $cntOld = $cntErr = 0;	// reset counters
		
		// iterate through entered lines
		foreach ($arChgs as $idx => $arChg) {
		    $rsLine = $tblLines->GetData('(ID_Parent='.$idRcd.') AND (InvcLineNo='.$idx.')');
		    $arChg['isActive'] = 'TRUE';
		    if ($rsLine->HasRows()) {
			$rsLine->NextRow();
			$rsLine->Update($arChg);
			$cntOld++;
		    } else {
			$arChg['ID_Parent'] = $idRcd;
			$arChg['InvcLineNo'] = $idx;
			$id = $tblLines->Insert($arChg);
			if (is_bool($id)) {
			    $out .= '<br clear=both>Error inserting line: '
			      .$db->getError()	// this isn't returning anything
			      .'<br>'
			      .'<b>SQL</b>: '.$tblLines->sqlExec
			      .'<br>'
			      ;
			}
			echo 
			$cntNew++;
		    }
		    $txtErr = $db->getError();
		    if (!empty($txtErr)) {
			$out .= "<br>Error in line $idx: $txtErr";
			$out .= '<br> - SQL: '.$tblLines->sql;
			$db->ClearError();
			$cntErr++;
		    }
		} // -FOR

		$txtEvent =
		  $cntNew.' line'.fcString::Pluralize($cntNew).' added, '
		  .$cntOld.' line'.fcString::Pluralize($cntOld).' updated.';
		if ($cntErr > 0) {
		    $txtErrEv = $cntErr.' error'.fcString::Pluralize($cntErr).' - last: '.$txtErr;
		    $txtEvent = $txtErrEv.' '.$txtEvent;
		    $isErr = TRUE;
		} else {
		    $isErr = FALSE;
		}
		$arEv = array(
		  'error'	=> $isErr,
		  'descrfin'	=> $txtEvent
		  );
		$rcEv->Finish($arEv);
		$out .= '<br>'.$txtEvent;
	    } // -IF we're ready to write data
	} else {
	    $out = 'No data lines found.';
	}
	$arOut['show'] = $out;
	$arOut['cnt.nofnd'] = $cntLost;
	return $arOut;
    }
    
    //-BULK-//
    //+PAGE+// - single record

    protected function AdminPage() {
	$oPage = $this->PageObject();
	
	if ($oPage->ReqArgBool('btnSave')) {
	    $id = $this->PageForm()->Save();
	    $this->GetKeyValue($id);
	    $this->SelfRedirect();
	}
	$htEntry = $this->Handle_BulkEntry_Invoice();

	if ($this->PageMode_doFigure()) {
	    $sMsg = $this->FigureTotals();
	    $this->SelfRedirect(NULL,$sMsg);
	}
	
	$isNew = $this->IsNew();
	$doEdit = $this->PageMode_doEdit();
	$doForm = $isNew || $doEdit;
	
	$frm = $this->PageForm();
	
	// DISPLAY THE FORM

	if ($this->IsNew()) {
	    $frm->ClearValues();
	    
	    // check for information to fill in
	    $idReq = $oPage->PathArg(KS_ACTION_RESTOCK_REQUEST);
	    if (!is_null($idReq)) {
		// look up things about the request and use them as defaults
		$rcReq = $this->RequestTable($idReq);
		$this->RequestID($idReq);
		$this->SupplierID($rcReq->SupplierID());
		$this->WarehouseID($rcReq->WarehouseID());
		$frm->LoadRecord();
	    }
	    
	} else {
	    $frm->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frm->RenderControls($doForm);
	$arCtrls['ID'] = $this->SelfLink();
	if ($this->IsNew()) {
	} else {
	    $arCtrls['ID_Request'] = $this->RequestLink_name();
	}
	
	$arActs = array(
	  new clsActionLink_option(
	    array(),	// other stuff to always appear in URL, regardless of section's menu state
	    'edit',	// LinkKey: value that the group should be set to when this link is activated
	    NULL,	// GroupKey: group's identifier string in URL (.../iGroupKey:iLinkKey/...)
			  // if NULL, presence of link key (.../iLinkKey/...) is a flag
	    NULL,	// DispOff: text to display when link is not activated - defaults to LinkKey
	    NULL,	// DispOn: text to display when link is activated - defaults to DispOff
	    'edit this record'	// description -- appears in hover-over popup
	    ),
	  new clsAction_section('Invoice'),
	  new clsActionLink_option(
	    array(),	// other stuff to always appear in URL, regardless of section's menu state
	    'parse',	// LinkKey: value that the group should be set to when this link is activated
	    'invc',	// GroupKey: group's identifier string in URL (.../iGroupKey:iLinkKey/...)
			  // if NULL, presence of link key (.../iLinkKey/...) is a flag
	    NULL,	// DispOff: text to display when link is not activated - defaults to LinkKey
	    NULL,	// DispOn: text to display when link is activated - defaults to DispOff
	    'parse already-entered invoice text'	// description -- appears in hover-over popup
	    ),
	  new clsActionLink_option(
	    array(),	// other stuff to always appear in URL, regardless of section's menu state
	    'enter',	// LinkKey: value that the group should be set to when this link is activated
	    'invc',	// GroupKey: group's identifier string in URL (.../iGroupKey:iLinkKey/...)
			  // if NULL, presence of link key (.../iLinkKey/...) is a flag
	    NULL,	// DispOff: text to display when link is not activated - defaults to LinkKey
	    NULL,	// DispOn: text to display when link is activated - defaults to DispOff
	    'enter and parse invoice text'	// description -- appears in hover-over popup
	    ),
	  );
	$oPage->PageHeaderWidgets($arActs);
	$oPage->TitleString('Received Restock #'.$this->GetKeyValue());

	$out = NULL;
	
	if (!is_null($htEntry)) {
	    $out .= "\n<table align=right><tr><td>$htEntry</td></tr></table>";
	}
	
	if ($doForm) {
	    $out .= "\n<form method=post>";
	}
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();	
	
	if ($doForm) {
	    $out .=
	      '<center><input type=submit name=btnSave value="Save"></center>'
	      .'</form>';
	}
	
	if (!$this->IsNew()) {
	    $out .= $this->AdminLines();
	}
	
	return $out;
    }
    
    protected function PageMode_doEdit() {
	return $this->PageObject()->PathArg('edit');
    }
    protected function PageMode_doFigure() {
	return ($this->PageObject()->PathArg('do') == 'fig');
    }
    
    private $oPageForm;
    protected function PageForm() {
	if (empty($this->oPageForm)) {
	    // create fields & controls
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_Request');
	      /*
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		  // TODO: For anyone without system-debugging permission, make this read-only:
		  $oCtrl->Records($this->RequestTable()->GetData_forDropDown()); */

	      $oField = new fcFormField_Num($oForm,'ID_Supplier');
		  $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		  $oCtrl->Records($this->SupplierRecords_all());
		  
	      $oField = new fcFormField_Num($oForm,'ID_Warehouse');
		  $oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		  $oCtrl->Records($this->WarehouseRecords_all());
		  $oCtrl->AddChoice(NULL,'unknown');

	      $oField = new fcFormField_Text($oForm,'SuppInvcNum');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>10));
	      
	      $oField = new fcFormField_Text($oForm,'CarrierDescr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>10));
	      
	      $oField = new fcFormField_Text($oForm,'InvcEntry');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>20,'cols'=>60));
	      
	      $oField = new fcFormField_Text($oForm,'TrackingCode');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>20));
	      
	      $oField = new fcFormField_Time($oForm,'WhenShipped');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>16));
	      
	      $oField = new fcFormField_Time($oForm,'WhenReceived');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>16));
	      
	      $oField = new fcFormField_Time($oForm,'WhenDebited');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>16));
	      
	      $oField = new fcFormField_Num($oForm,'TotalCalcMerch');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      
	      $oField = new fcFormField_Num($oForm,'TotalEstFinal');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>7));
	      
	      $oField = new fcFormField_Num($oForm,'TotalInvMerch');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      
	      $oField = new fcFormField_Num($oForm,'TotalInvShip');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      
	      $oField = new fcFormField_Num($oForm,'TotalInvAdj');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      
	      $oField = new fcFormField_Num($oForm,'TotalInvFinal');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));
	      
	      $oField = new fcFormField_Num($oForm,'InvcCondition');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		  $oCtrl->AddChoice(NULL,'unknown / not yet entered');
		  $oCtrl->AddChoice(0,'absent (no paperwork found)');
		  $oCtrl->AddChoice(1,'partial (one or more pages missing/illegible)');
		  $oCtrl->AddChoice(2,'complete (all pages located and legible)');

	      $oField = new fcFormField_Text($oForm,'PayMethod');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>10));
	      
	      $oField = new fcFormField_Text($oForm,'Notes');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3));
	      
	    $this->oPageForm = $oForm;
	}
	return $this->oPageForm;
    }
    /*----
      TODO:
	* The original code takes the first line of invoice entry as the invoice format.
	  Maybe this should now be a separate field?
	* When there are images of the invoice, this should link to those.
	  That will require either a kluge to look up pages in the wiki, or else a
	  system for managing non-merch images... which needs to be done eventually.
    */
    private $tpPage;
    protected function PageTemplate() {
	$htEntryHelp = self::HelpLink_EntryFormat();
	$doEdit = $this->PageMode_doEdit();
	
    	$htEdNotes = $doEdit?'<b>Edit notes</b>:<br><textarea name="EvNotes" rows=5></textarea><br>':NULL;
    
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__

<table class=listing>
<tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
<tr><td align=right><b>Request:</b></td><td>[[ID_Request]]</td></tr>
<tr><td align=right><b>from Supplier</b>:</td><td>[[ID_Supplier]]</td></tr>
<tr><td align=right><b>to Warehouse</b>:</td><td>[[ID_Warehouse]]</td></tr>
<tr><td align=right><b>Supplier invc #</b>:</td><td>[[SuppInvcNum]]</td></tr>
<tr><td align=right><b>carrier</b>:</td><td>[[CarrierDescr]]</td></tr>
<tr><td align=right><b>tracking code</b>:</td><td>[[TrackingCode]]</td></tr>

<tr><td colspan=3><hr><b><big>timestamps</big></b></td></tr>
<tr><td></td><td align=right><b>shipped</b>:</td><td>[[WhenShipped]]</td></tr>
<tr><td></td><td align=right><b>received</b>:</td><td>[[WhenReceived]]</td></tr>
<tr><td></td><td align=right><b>debited</b>:</td><td>[[WhenDebited]]</td></tr>

<tr><td colspan=3><hr><b><big>$ totals</big></b></td></tr>
<tr><td></td><td align=right><b>calculated merch</b>: $</td><td>[[TotalCalcMerch]]</td></tr>
<tr><td></td><td align=right><b>estimated final</b>: $</td><td>[[TotalEstFinal]]</td></tr>
<tr><td></td><td align=right><b>invoice merch</b>: $</td><td>[[TotalInvMerch]]</td></tr>
<tr><td></td><td align=right><b>invoice shipping</b>: $</td><td>[[TotalInvShip]]</td></tr>
<tr><td></td><td align=right><b>invoice adjust</b>: $</td><td>[[TotalInvAdj]]</td></tr>
<tr><td></td><td align=right><b>invoice final</b>: $</td><td>[[TotalInvFinal]]</td></tr>
<tr><td align=right><b>invoice condition</b>:</td><td colspan=2>[[InvcCondition]]</td></tr>
<tr><td align=right><b>payment method</b>:</td><td colspan=2>[[PayMethod]]</td></tr>

<tr><td colspan=3 align=center>

<table><tr class=even><td valign=top>
  <b>invoice entry text</b> $htEntryHelp:<br>[[InvcEntry]]
</td><td valign=top>
  <b>other notes</b>:<br>[[Notes]]<br>
  $htEdNotes
</td></tr></table>

</td></tr>
</table>
__END__;

	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt); 
	}
	return $this->tpPage;
    }
    
    //-PAGE-//
    //+LINES+// - received line items
    
    protected function AdminLines() {
	$oPage = $this->Engine()->App()->Page();
	
	// set up the section menu
	$arMenu = array(
	  new clsActionLink_option(array(),
	    'fig',		// link key
	    'do',		// group key
	    'figure',		// display when off
	    NULL,		// display when on
	    'figure totals'	// description (shows as hover-over text)
	    ),
	  );
	// render the section header
	$out = $oPage->ActionHeader('Contents',$arMenu);
	
	$rs = $this->LineRecords();
	$out .= $rs->AdminRows_forParent($this->GetKeyValue());
	
	return $out;
    }
    protected function FigureTotals() {
	$rs = $this->LineRecords();
	$arFig = $rs->FigureTotals();
	
	$out = $arFig['html'];
	$dlrBalNew = $arFig['line.bal'];
	
	$dlrBalOld = $this->Value('TotalCalcMerch');
	if (clsMoney::Same($dlrBalNew,$dlrBalOld)) {	    
	    if (is_null($out)) {
		$out .= 'Invoice total unaffected.';
	    } else {
		$out = 'Invoice lines checked &ndash; all totals correct.';
	    }
	} else {
	    $arUpd = array('TotalCalcMerch' => $dlrBalNew);
	    $this->Update($arUpd);

	    $out .= '<br>Calculated invoice total updated';
	    if (!is_null($dlrBalOld)) {
		$out .= " from <b>$dlrBalOld</b>";
	    }
	    $out .= " to <b>$dlrBalNew</b>.";
	}
	return $out;
    }
    
    //-LINES-//
	
    // -- ADMIN UI -- //
    
    //-PAGE-//
}
