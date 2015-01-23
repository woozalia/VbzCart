PURPOSE: code discarded from request.php

2013-03-09 These appear to be the MW versions of the request admin classes; no longer needed.

class clsAdminRstkReqs extends clsRstkReqs {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminRstkReq');
    }
    /*-----
      ACTION: Creates a new restock request
      ASSUMES: PO# has already been validated -- not a duplicate, not blank
      RETURNS: object for newly-created request
      INPUT:
	iSupp = ID of supplier
	iPONum = our purchase order #
	iItems = array of item data
	  iItems[ID] = qty we want to order for this item
    */
    public function Create($iSupp,$iPONum,array $iItems) {
	$arNew = array(
	  'ID_Supplier'	=> $iSupp,
	  'PurchOrdNum'	=> SQLValue($iPONum),
	  'WhenCreated'	=> 'NOW()'
	  );
	$this->Insert($arNew);		// new restock request
	$id = $this->objDB->NewID(__METHOD__);
	$objNew = $this->GetItem($id);	// load the request record

	// got the ID for the master record; now add the item records:
	foreach ($iItems as $id => $qty) {
	    $objNew->AddItem($id,$qty);
	}
	return $objNew;
    }
}
class clsAdminRstkReq extends clsRstkReq {

    // ++ FIELD ACCESS ++ //

    /*----
      NOTES:
	2010-10-11 Possibly this should be renamed something like AdminLink_standard
	2010-10-28 renamed it from AdminLink() to AdminLink_friendly()
    */
    public function AdminLink_friendly() {
	global $vgOut;

	$arLink = array(
	  'page'	=> 'rstk-req',
	  'id'		=> $this->ID
	  );
	//$txtShow = is_null($iShow)?($this->ID):$iShow;
	$txtPopup = 'view restock request #'.$this->ID;
	if (is_null($this->PurchOrdNum)) {
	    $txtShow = '<small>id</small>'.$this->ID;
	} else {
	    $txtShow = $this->PurchOrdNum;
	    $txtPopup .= ' - PO# '.$this->PurchOrdNum;
	}
	if (!is_null($this->SuppOrdNum)) {
	    $txtPopup .= ' - SO# '.$this->SuppOrdNum;
	}
	$out = $vgOut->SelfLink($arLink,$txtShow,$txtPopup);
	return $out;
    }

    // -- FIELD ACCESS -- //
    // ++ TABLE OBJECTS ++ //

    public function LinesTbl() {
	return $this->objDB->RstkReqItems();
    }

    // -- TABLE OBJECTS -- //
    // ++ RECORDSET OBJECTS ++ //

    public function SuppObj() {
	static $objSupp;

	$idSupp = $this->Row['ID_Supplier'];
	$doGet = TRUE;
	if (is_object($objSupp)) {
	    if ($objSupp->ID = $idSupp) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $objSupp = $this->objDB->Suppliers()->GetItem($idSupp);
	}
	return $objSupp;
    }
    // -- RECORDSET OBJECTS -- //
    // ++ ADMIN INTERFACE ++ //

    /*----
      ACTION: Adds a line-item to the current restock request.
	In order to avoid event proliferation, this routine does NOT log events.
	Instead, caller should log an event for each batch of lines added.
      CALLED BY: clsAdminRstkReqs::AdminItemsSave()
      HISTORY:
	2010-12-12 Uncommented and corrected to add items to rstk_req_item instead of rstk_req
    */
    public function AddLine(clsItem $iItem,$iQtyNeed,$iQtyCust,$iQtyOrd) {
	assert('!$iItem->IsNew();');
	$arIns = array(
	  'ID_Restock'	=> $this->ID,
	  'ID_Item'	=> $iItem->ID,
	  'Descr'	=> SQLValue($iItem->DescLong()),
	  'WhenCreated'	=> 'NOW()',
	  'QtyNeed'	=> SQLValue($iQtyNeed),	// can be NULL
	  'QtyCust'	=> SQLValue($iQtyCust),	// can be NULL
	  'QtyOrd'	=> SQLValue($iQtyOrd),	// can be NULL
	  'CostExpPer'	=> SQLValue($iItem->PriceBuy)
	  );
	$this->LinesTbl()->Insert($arIns);
    }
    /*----
      ACTION:
	Make sure the given item is in the restock request, with the given quantities.
	If the item is already in the request, quantities are updated from non-null quantities given.
	If the item is not already in the request, it is added.
    */
    public function MakeLine(clsItem $iItem,$iQtyNeed,$iQtyCust,$iQtyOrd) {
	$idItem = $iItem->ID;
	$objLine = $this->GetLine($idItem);
	if ($objLine->HasRows()) {
	    $out = 'update ';
	    $strFld = '';
	    if (!is_null($iQtyNeed)) {
		$arUpd['QtyNeed'] = $iQtyNeed;
		$strFld = 'need '.$objLine->Value('QtyNeed').'->'.$iQtyNeed;
	    }
	    if (!is_null($iQtyCust)) {
		$arUpd['QtyCust'] = $iQtyCust;
		$strFld = StrCat($strFld,'cust '.$objLine->Value('QtyCust').'->'.$iQtyCust,',');
	    }
	    if (!is_null($iQtyOrd)) {
		$arUpd['QtyOrd'] = $iQtyOrd;
		$strFld = StrCat($strFld,'ord '.$objLine->Value('QtyOrd').'->'.$iQtyOrd,',');
	    }
	    $out .= ' ('.$strFld.')';
	    $objLine->Update($arUpd);
	} else {
	    $out = "add (need=$iQtyNeed,cust=$iQtyCust,ord=$iQtyOrd)";
	    $this->AddLine($iItem,$iQtyNeed,$iQtyCust,$iQtyOrd);
	}
	return $out;
    }
    /*----
      RETURNS: restock request line for the current item in the current restock
      HISTORY:
	2010-11-24 Written for restock request item entry.
    */
    public function GetLine($iItem) {
	$tbl = $this->LinesTbl();
	$sqlFilt = '(ID_Restock='.$this->ID.') AND (ID_Item='.$iItem.')';
	$rs = $tbl->GetData($sqlFilt);
	$rs->NextRow();
	return $rs;
    }
    /*-----
      ACTION: Save the user's edits to the package
      HISTORY:
	2011-03-02 Replaced old code with call to helper object
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);

/* OLD CODE RETIRED 2011-03-02
	global $wgOut;

	// get the form data and note any changes
	$this->objFields->RecvVals();
	// get the list of field updates
	$arUpd = $this->objFields->DataUpdates();
	// log that we are about to update
	$strDescr = 'Edited: '.$this->objFields->DescrUpdates();
	$wgOut->AddWikiText('==Saving Edit==',TRUE);
	$wgOut->AddWikiText($strDescr,TRUE);
	//$wgOut->AddHTML('<pre>'.print_r($arUpd,TRUE).'</pre>');

	$arEv = array(
	  'descr'	=> $strDescr,
	  'where'	=> __METHOD__,
	  'code'	=> 'ED'
	  );
	$this->StartEvent($arEv);
	// update the recordset
	$this->Update($arUpd);
global $sql;
$wgOut->AddWikiText('<br>SQL='.$sql,TRUE);

	$this->Reload();
	// log completion
	$this->FinishEvent();
*/
    }
    protected function EnterItems() {
	global $wgRequest;
	global $vgPage;

	$txtItems = $wgRequest->GetText('items');
	$txtNotes = $wgRequest->GetText('notes');
	$txtFormat = $wgRequest->GetText('format','scat name name price qty');
	$strSepType = $wgRequest->GetText('sepType');
	$doParse	= $wgRequest->GetBool('btnItLookup');	// parse bulk text entry
	$doCheck	= $wgRequest->GetBool('btnItCheck');	// recheck edited data
	$doCreate	= $wgRequest->GetBool('btnItCreate');	// create new items
	$doUpdate	= $wgRequest->GetBool('btnItUpdate');	// update existing item specs
	$doSave		= $wgRequest->GetBool('btnItSave');	// save data to restock

	$htNotes = htmlspecialchars($txtNotes);
	$htItems = htmlspecialchars($txtItems);
	$htFormat = htmlspecialchars($txtFormat);

	$arFormat = explode (' ',$txtFormat);

	$out = '<h3>Enter Items</h3>';

	$arLink = $vgPage->Args(array('page','id','do','type'));
	$urlForm = $vgPage->SelfURL($arLink,TRUE);
	$out .= '<form method=post action="'.$urlForm.'">';

	$htCkdSepTab = NULL;
	$htCkdSepPfx = NULL;
	$txtSepMsg = NULL;

	$arData = NULL;
	if ($doParse) {
	    switch ($strSepType) {
	      case 'tab':
		$arOpts = array('line'=>'arr','blanks'=>"\t",'sep'=>"\t");
		$htCkdSepTab = ' checked';
		break;
	      case 'pfx':
		$arOpts = array('line'=>'arrx','blanks'=>"\t ");
		$htCkdSepPfx = ' checked';
		break;
	      default:
		$arOpts = NULL;
		$txtSepMsg = ' - <b>Please choose one</b>';
	    }
	    if (!is_null($arOpts)) {
		$xts = new xtString($txtItems);
		$arInLines = $xts->ParseTextLines($arOpts);

		$arUCatNums = $wgRequest->GetArray('catnum');

		// parse freeform text into array
		foreach ($arInLines as $idx => $arInLine) {
		    $arRow = NULL;
		    foreach ($arInLine as $col => $val) {
			if (isset($arFormat[$col])) {
			    $strCol = $arFormat[$col];
			    //$out .= '[COL='.$col.' VAL='.trim($val).' =>'.$strCol.']';
			    if (isset($arOut[$strCol])) {
				$val = $arOut[$strCol].' '.$val;
			    }
			    $arRow[$strCol] = $val;
			}
		    }
		    $arData[$idx] = $arRow;
		}
	    }	// if (!is_null($arOpts))
	}	// if ($doParse)

	if ($doParse || $doCheck || $doUpdate || $doCreate || $doSave) {
	    $objSupp = $this->SuppObj();
	    if ($doCheck || $doUpdate || $doCreate || $doSave) {
		$arData = $objSupp->AdminItems_form_receive();
	    }
	}
	if (is_array($arData)) {	// if we've got item data from somewhere...
	    // STAGE 1: check it against the database
	    $arStat = $objSupp->AdminItems_data_check($arData);
	    $arData = $arStat['rows'];
	    $cntOkAdd = $arStat['#add'];
	    $cntOkUse = $arStat['#use'];
	    $cntOkUpd = $arStat['#upd'];
	    $arUse = array();
	    $arUpd = array();
	    $arAdd = array();
	    foreach ($arData as $idx => $row) {
		$st = $row['@state'];
		switch ($st) {
		  case 'use':
		    $arUse[$idx] = $row;
		    break;
		  case 'add':
		    $arAdd[$idx] = $row;
		    break;
		}
		if ($row['@can-upd-scat'] || $row['@can-upd-desc']) {
		    $arUpd[$idx] = $row;
		}
	    }

	    // STAGE 2: make any requested changes to stored data
	    $didChange = FALSE;
	    if ($doUpdate && ($cntOkUpd > 0)) {
		$txtIDs = '';
		foreach ($arUpd as $idx => $row) {
		    $txtIDs .= '\ID='.$row['@obj']->KeyValue();
		}
		$arEv = array(
		  'descr'	=> 'bulk entry item update',
		  'params'	=> $txtIDs,
		  'notes'	=> $txtNotes,
		  'where'	=> __METHOD__,
		  'code'	=> 'iupd'
		  );
		$this->StartEvent($arEv);
		$out .= '<h4>items updated</h4>';
		$out .= $this->objDB->Items()->AdminItems_data_update($arUpd);
		$this->FinishEvent();
	    }
	    if ($doCreate && ($cntOkAdd > 0)) {	// create these items in the catalog
		$cntItems = count($arAdd);
		$strOCats = '';
		foreach ($arAdd as $idx => $row) {
		    $strOCats .= '\ocat='.$row['ocat'];
		    $didChange = TRUE;
		}
		$arEv = array(
		  'descr'	=> 'Creating '.$cntItems.' item'.Pluralize($cntItems),
		  'params'	=> $strOCats,
		  'where'	=> __METHOD__,
		  'code'	=> 'ENT'	// ENTry form
		  );
		$this->StartEvent($arEv);
		$out .= '<h4>items created</h4>';
		$out .= $objSupp->AdminItems_data_add($arAdd);
		$this->FinishEvent();
		$didChange = TRUE;
	    }
	    if ($doSave && ($cntOkUse > 0)) {
		$txtIDs = '';
		foreach ($arUse as $idx => $row) {
		    $txtIDs .= '\ID='.$row['@obj']->KeyValue();
		}
		$arEv = array(
		  'descr'	=> 'Adding '.$cntOkUse.' item'.Pluralize($cntOkUse).' to restock request',
		  'notes'	=> $wgRequest->GetText('notes'),
		  'params'	=> $txtIDs,
		  'where'	=> __METHOD__,
		  'code'	=> '+RRQ'	// adding to restock request
		  );
		$this->StartEvent($arEv);
		$out .= '<h4>changes to requested item list</h4>';
		$arStat = $this->AdminItems_Use($arUse);
		$out .= $arStat['html'];
		$arEv = array(
		  'descrfin'	=> $arStat['text']
		  );
		$this->FinishEvent($arEv);
		$didChange = TRUE;
	    }
	    if ($didChange) {
		$out .= '<h4>current status</h4>';
		// recheck data
		$arStat = $objSupp->AdminItems_data_check($arData);
		$arData = $arStat['rows'];
		$cntOkAdd = $arStat['#add'];
		$cntOkUse = $arStat['#use'];
		$cntOkUpd = $arStat['#upd'];
	    }

	    // STAGE 3: redraw parsed items
	    $out .= $objSupp->AdminItems_form_entry($arData);

	    $out .=
	      $cntOkUse.' item'.Pluralize($cntOkUse).' ready to use, '
	      .$cntOkAdd.' unknown item'.Pluralize($cntOkAdd).' creatable.<br>';

	    $out .= '<input type=submit name=btnItCheck value="Recheck Data">';
	    if ($cntOkAdd > 0) {
		$txtUnit = $cntOkAdd.' Item'.Pluralize($cntOkAdd);
		$out .= '<input type=submit name=btnItCreate value="Create '.$txtUnit.'">';
	    }
	    if ($cntOkUpd > 0) {
		$pl = Pluralize($cntOkUpd);
		$out .= '<input type=submit name=btnItUpdate value="Update Checked Field'.$pl.' ('.$cntOkUpd.' change'.$pl.')">';
	    }
	    if ($cntOkUse > 0) {
		$txtUnit = $cntOkUse.' Item'.Pluralize($cntOkUse);
		$out .= '<input type=submit name=btnItSave value="Add '.$txtUnit.' to Restock">';
	    }
	    $out .= '<hr>';
	}

	$out .= 'Notes:<textarea rows=3 cols=30 name=notes>'.$htNotes.'</textarea>';
	$out .= 'Input format:<input name=format size=30 value="'.$htFormat.'">';
	$out .= ' - field separator: ';
	$arBtns = array(
	  'tab'	=> 'Tab',
	  'pfx'	=> 'Prefix'
	  );
	$out .= RadioBtns('sepType',$arBtns,$strSepType).$txtSepMsg.'<br>';
	$out .= 'Items:<textarea name=items rows=20 cols=10>'.$htItems.'</textarea>';
	$out .= '<br><input type=submit name=btnItLookup value="Look Up Items...">';
	$out .= '</form>';

	return $out;
    }
    /*----
      ACTION: Saves/adds the given list of items to the restock request.
	Items which are already in the request are updated.
      FUTURE: If existing items have non-null quantities, there should be some kind of notice.
	It may be that the user should have a choice whether to update or increment.
    */
    protected function AdminItems_Use(array $iItems) {
	//$out = '<pre>'.print_r($iItems,TRUE).'</pre>';
	$outHTML = '<ul>';
	$outText = '';
	foreach ($iItems as $idx => $row) {
	    $objItem = $row['@obj'];

	    $txt = $this->MakeLine($objItem,NULL,NULL,$row['qty']);
	    $outHTML .= '<li>'.$objItem->AdminLink().' '.$txt;
	    $outText .= "\n".$txt;
	}
	$outHTML .= '</ul>';
	$arOut = array(
	  'text'	=> $outText,
	  'html'	=> $outHTML
	  );
	return $arOut;
    }
/*
    protected function SaveEnteredLines() {
	global $wgRequest;

	$arScat = $wgRequest->GetArray('scatnum');
	$arTitle = $wgRequest->GetArray('rdescr');
	$arQty = $wgRequest->GetArray('rqty');
	$arPrice = $wgRequest->GetArray('rprice');
	$strNotes = $wgRequest->GetText('notes');

	$cntLines = count($arScat);
	if ($cntLines > 0) {
	    $strEvDesc = 'Processing '.$cntLines.' entered line'.Pluralize($cntLines);
	    $out = $strEvDesc.':<br>';

	    $arEv = array(
	      'descr'	=> SQLValue($strEvDesc),
	      'notes'	=> SQLValue($strNotes),
	      'code'	=> '"ORD"'
	      );
	    $this->StartEvent($arEv);
	    $cntDone = 0;
	    $cntErr = 0;

	    foreach ($arScat as $id => $scat) {
		$sqlTitle = SQLValue($arTitle[$id]);
		$intQtyOrd = $arQty[$id];
		$fltPrice = $arPrice[$id];
		$arIns = array(
		  'ID_Restock'	=> $this->ID,
		  'ID_Item'	=> $id,
		  'Descr'	=> $sqlTitle,
		  'WhenCreated'	=> 'NOW()',
		  'QtyOrd'	=> $intQtyOrd,
		  'CostExpPer'	=> $fltPrice);

		// check for duplicates
		$rsLine = $this->GetLine($id);
		$objItem = $this->objDB->Items()->GetItem($id);
		if ($rsLine->HasRows()) {
		    $out .= $objItem->AdminLink_friendly().' has already been entered as line '.$rsLine->AdminLink();
		} else {
		    $out .= $objItem->AdminLink_friendly();
//$sql = $this->LinesTbl()->SQL_forInsert($arIns);
//$out .= 'SQL: '.$sql; $ok = TRUE;
		    $ok = $this->LinesTbl()->Insert($arIns);
		    if ($ok) {
			$cntDone++;
			$out .= ' - added.';
		    } else {
			$cntErr++;
			$out .= ' - ERROR: '.$this->objDB->getError();
			$this->objDB->ClearError();
		    }
		}
		$out .= '<br>';
	    }

	    $strEvDesc = $cntErr.' error'.Pluralize($cntErr).', '.$cntDone.' line'.Pluralize($cntDone).' saved';
	    $out .= $strEvDesc.'.';
	    $arEv = array(
	      'descrfin'	=> SQLValue($strEvDesc),
	      'error'		=> ($cntErr > 0)
	      );
	    $this->FinishEvent($arEv);

	    return $out;
	} else {
	    return NULL;	// nothing to process
	}
    }
*/
}
