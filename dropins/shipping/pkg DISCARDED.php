<?php
    public function AdminPage_OLD() {
	$oPage = $this->Engine()->App()->Page();
	$isNew = FALSE;

	// do actions first, so we can redirect to actionless URL
	$strDo = $oPage->PathArg('do');
	$doFetch = ($strDo == 'fetch');
	$doSave = clsHTTP::Request()->GetBool('btnSave');
	$doUnfetch = clsHTTP::Request()->GetBool('btnUnfetch');
	$doAddPkg = clsHTTP::Request()->GetBool('btnAdd');
	$doSimulate = clsHTTP::Request()->GetBool('btnSim');
	$doEdit = $oPage->PathArg('edit');
	$doNew = ($oPage->PathArg('id') == 'new');	// is this different from $doAddPkg?

	$doStockLookup = $doNew || $doFetch;
	$doForm = $doNew || $doFetch || $doEdit;

	$doRedir = FALSE;
	$out = NULL;

	$doStore = FALSE;
	if ($doUnfetch) {
	    $idBin = clsHTTP::Request()->GetInt('bin');
	    if ($idBin == 0) {
		$out .= 'Please select a destination bin.';
	    } else {
		$out .= $this->Move_toBin($idBin);
		$doRedir = TRUE;
	    }
	}

	switch ($strDo) {
	  case 'charge':
	    if ($this->HasCharges()) {
		$out .= '<h3>Warning</h3>Attempting to add charges when charges have been added. Remove them first.';
	    } else {
		$out .= $this->AdminDoCharge();
		$doRedir = TRUE;
	    }
	    break;
	  case 'uncharge':
	    $out .= $this->AdminUnCharge();
	    $doRedir = TRUE;
	    break;
	  case 'unfetch':
	    $qty = $this->ItemQty();
	    $out .= '<form method=post>'
	      .'Return '.$qty.' item'.Pluralize($qty).' to bin '
	      . $this->BinTable()->DropDown_active()
	      . '<input type=submit name=btnUnfetch value="Do It"></form>';
/* If a nicer form is actually needed, document the scenario.

This could probably be put closer to the original link, so it's easier to find...
...and it could also be highlighted somehow. Maybe we need an "action box" widget
to draw attention to incipient actions, i.e. controls that only appear when an action
is being set up.
*/
	    break;
	  case 'void':
	    $ar = array(
	      'descr'	=> 'voiding the package',
	      'code'	=> 'VOID',
	      'where'	=> __METHOD__,
	      );
	    $this->StartEvent($ar);
	    $ar = array('WhenVoided' => 'NOW()');
	    $this->Update($ar);
	    $this->FinishEvent();
	    $out .= 'Package voided.';
	    $doRedir = TRUE;
	    break;
	  case 'check-in':
	    $ar = array(
	      'descr'	=> 'package checked in to shipment',
	      'code'	=> 'CHK',
	      'where'	=> __METHOD__,
	      );
	    $this->StartEvent($ar);
	    $ar = array('WhenChecked' => 'NOW()');
	    $this->Update($ar);
	    $this->FinishEvent();
	    $out .= 'Package checked in.';
	    $doRedir = TRUE;
	    break;
	}

	// save/simulate changes:
	if ($doSave) {
	    $this->BuildEditForm(FALSE);
	    $this->AdminSave();		// save edit to existing package
	    $doRedir = TRUE;
	} elseif ($doAddPkg) {
	    $out .= $this->AdminFill(TRUE);	// create new package from user input
	    $doRedir = TRUE;
	    $isNew = TRUE;
	} elseif ($doSimulate) {
	    $out .= $this->AdminFill(FALSE);	// create new package from user input
	    $doRedir = TRUE;
	}

	// if any actions done, redirect to actionless URL:
	if ($doRedir) {
	    setcookie('action-msgs',$out,0,$this->AdminURL());
	    $this->AdminRedirect();
	} else {
	    if (array_key_exists('action-msgs',$_COOKIE)) {
		$out .= $_COOKIE['action-msgs'];
		setcookie('action-msgs',FALSE,0,$this->AdminURL());	// delete the cookie
	    }
	}
	$id = $this->KeyValue();
	$wpWiki = KWP_WIKI_PRIVATE;
	$out .= <<<__END__
<br><b>Reports</b>: [<a href="$wpWiki/VBZHQ:Reports/packing slip?pkg=$id">packing slip</a>]
__END__;

	// do the header, with edit link if appropriate
	//$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	/* 2014-04-10 this seems inappropriate
	$arPath = array();	// not sure if anything is needed here
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPath,'edit')
	  );
	$out .= $oPage->ActionHeader('Packages',$arActs);
*/

	if ($doNew) {
	    $doEdit = TRUE;	// new package must be edited before it can be created
	    $isNew = TRUE;

	    // use passed order number
	    /* this should be done by InitFromInput() now
	    $idOrd = $oPage->PathArg('order');
	    if (empty($idOrd)) {
		$idOrd = clsHTTP::Request()->GetInt('order');
	    }
	    $this->Value('ID_Order',$idOrd);
	    */

	    $objOrd = $this->OrderRecord();
	    $strName = 'New package for order #'.$objOrd->Value('Number');
	} else {
	    $id = $this->KeyValue();
	    $isNew = $this->IsNew();
	    $objOrd = $this->OrderRecord();
	    $strName = 'Package '.$id.' - #'.$this->Number();
	}
/*
	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection($objPage,$strName);
	if (!$isNew) {
	    $objSection->ToggleAdd('edit','edit the package record');
	    //$objSection->ToggleAdd('print','print a packing slip');
	}
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';
*/
	//$out .= $oPage->Skin()->SectionHeader($strName,'section-header-sub');
	if ($this->IsNew()) {
	    $arActs = array();	// no actions needed
	} else {
	    // these options aren't useful for a new record
	    clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	    $arPath = array();	// not sure if anything is needed here
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option($arPath,'edit',NULL,NULL,NULL,'edit the package record'),
	      new clsActionLink_option($arPath,'print',NULL,NULL,NULL,'print a packing slip'),
	      );
	}
	$arPath = array();	// not sure if anything is needed here
	$out .= $oPage->ActionHeader($strName,$arActs);

	if ($isNew) {
	    $idShip = NULL;
	} else {
	    $idShip = $this->Value('ID_Shipment');
	    $rcShip = $this->ShipObj();
	}

// calculate display markup
	if ($doForm) {
	    $idOrd = $this->Value('ID_Order');
	    $htShip = $this->ShipmentTable()->GetActive('WhenCreated DESC')->DropDown(NULL,$idShip);
	    $arLink = array(
	      'edit'	=> FALSE,
	      'add'	=> FALSE,
	      'do'	=> FALSE,
	      'order'	=> FALSE
	      );
	    //$htPath = $vgPage->SelfURL($arLink);
	    //$out .= "\n<form method=post action=\"$htPath\">";
	    $out .= "\n<form method=post>";
	    if ($doNew) {
		$out .= "<input name=order type=hidden value=$idOrd>";
	    }
//	    $arArgs['id'] = 'new';	// 2014-04-10 is this right?
	} else {
	    //$idShip = $rcShip->KeyValue();
	    $arArgs['id'] = $idShip;
	    if (is_null($idShip)) {
		$htShip = '<i>N/A</i>';
	    } else {
		$htShip = $rcShip->AdminLink_name();
	    }
	}
	$sShipAct = $this->ShipmentTable()->ActionKey();
	$arArgsShNew = array(
	  'page'=>$sShipAct,
	  'id'=>'new');
	//$arArgs = array('page'=>'shpmt','id'=>'new');
	$htShip .= ' [<a href="'.$oPage->SelfURL($arArgsShNew,TRUE).'">new</a>]';

	$out .= "\n<table>";

	// defaults
	$ctrlVoidNow = NULL;
	$ctrlCheckIn = NULL;

	//$htOrder = $objOrd->AdminLink($objOrd->Number);
	$htOrder = $objOrd->AdminLink_name();
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>$htOrder</td></tr>";
	if (!$isNew) {
	    // only display these for an existing package
	    $dtWhenStarted = $this->Value('WhenStarted');
	    $dtWhenFinished = $this->Value('WhenFinished');
	    $dtWhenChecked = $this->Value('WhenChecked');
	    $dtWhenVoided = $this->Value('WhenVoided');

	    if ($doEdit) {
		$objForm = $this->PageForm();
		$ctrlWhenStarted = $objForm->RenderControl('WhenStarted');
		$ctrlWhenFinished = $objForm->RenderControl('WhenFinished');
		$ctrlWhenChecked = $objForm->RenderControl('WhenChecked');
		$ctrlWhenVoided = $objForm->RenderControl('WhenVoided');

		$arLink = $oPage->PathArgs(array('page','id'));
		if (!$this->IsVoid()) {
		    $arLink['do'] = 'void';
		    $ctrlVoidNow = ' ['.$this->AdminLink('void now', 'VOID the package (without saving edits)',$arLink).']';
		}
		if (!$this->IsChecked()) {
		    $arLink['do'] = 'check-in';
		    $ctrlCheckIn = ' ['.$this->AdminLink('check in','mark the package as checked in (without saving edits)',$arLink).']';
		}
	    } else {
		$ctrlWhenStarted = $dtWhenStarted;
		$ctrlWhenFinished = $dtWhenFinished;
		$ctrlWhenChecked = $dtWhenChecked;
		$ctrlWhenVoided = $dtWhenVoided;
	    }

	    $out .= <<<__END__
<tr><td align=right><b>When Started</b>:</td><td>$ctrlWhenStarted</td></tr>
<tr><td align=right><b>When Finished</b>:</td><td>$ctrlWhenFinished</td></tr>
<tr><td align=right><b>When Checked</b>:</td><td>$ctrlWhenChecked$ctrlCheckIn</td></tr>
<tr><td align=right><b>When Voided</b>:</td><td>$ctrlWhenVoided$ctrlVoidNow</td></tr>
__END__;
	}

	// display these for new and existing packages:
	$dlrChgShipItm = $this->ChgShipItm;
	$dlrChgShipPkg = $this->ChgShipPkg;
	$dlrCostShp = $this->ShipCost;
	$dlrCostPkg = $this->PkgCost;
	$intShPounds = $this->ShipPounds;
	$fltShOunces = $this->ShipOunces;
	$htNotes = htmlspecialchars($this->ShipNotes);
	$htTrack = htmlspecialchars($this->ShipTracking);
	$dtWhenArrived = $this->WhenArrived;
	if ($doEdit) {
	    $fPage = $this->PageForm();
	    $ctrlChgShipItm	= '$'.$fPage->RenderControl('ChgShipItm');
	    $ctrlChgShipPkg	= '$'.$fPage->RenderControl('ChgShipPkg');
	    $ctrlCostShp	= '$'.$fPage->RenderControl('ShipCost');
	    $ctrlCostPkg	= '$'.$fPage->RenderControl('PkgCost');
	    $ctrlShPounds	= $fPage->RenderControl('ShipPounds');
	    $ctrlShOunces	= $fPage->RenderControl('ShipOunces');
	    $ctrlShWeight	= "$ctrlShPounds pounds $ctrlShOunces ounces";
	    $ctrlNotes		= $fPage->RenderControl('ShipNotes');
	    $ctrlTrack		= $fPage->RenderControl('ShipTracking');
	    $ctrlWhenArrived	= $fPage->RenderControl('WhenArrived');
	} else {
	    $ctrlChgShipItm	= $dlrChgShipItm;
	    $ctrlChgShipPkg	= $dlrChgShipPkg;
	    $ctrlCostShp	= $dlrCostShp;
	    $ctrlCostPkg	= $dlrCostPkg;
	    $ctrlShWeight	=
	      (is_null($intShPounds)?'':"$intShPounds pounds")
	      .(is_null($fltShOunces)?'':" $fltShOunces ounces");
	    $ctrlNotes		= $htNotes;
	    $ctrlTrack		= $htTrack;
	    $ctrlWhenArrived	= $dtWhenArrived;
	}
	$htBtnRow = NULL;
	if ($doForm) {
	    if ($doNew || $doFetch) {
		$htBtnRow = '<input type=hidden name=order value='.$this->OrderID().'>';
	    } else {
		//$out .= '<input type=hidden name=id value="'.$this->ID.'">';
		$htBtnRow = <<<__END__
<tr>
  <td align=center colspan=2>
    <input type=submit name="btnSave" value="Save">
  </td>
</tr>
__END__;
	    }
	}
	$out .= <<<__END__
<tr><td align=right><b>Per-item s/h total charged</b>:</td><td>$ctrlChgShipItm</td></tr>
<tr><td align=right><b>Per-pkg s/h smount charged</b>:</td><td>$ctrlChgShipPkg</td></tr>
<tr><td align=right><b>Shipment</b>:</td><td>$htShip</td></tr>
<tr><td align=right><b>Actual shipping cost</b>:</td><td>$ctrlCostShp</td></tr>
<tr><td align=right><b>Actual package cost</b>:</td><td>$ctrlCostPkg</td></tr>
<tr><td align=right><b>Actual shipping weight</b>:</td><td>$ctrlShWeight</td></tr>
<tr><td align=right><b>Delivery tracking #</b>:</td><td>$ctrlTrack</td></tr>
<tr><td align=right><b>When arrived</b>:</td><td>$ctrlWhenArrived</td></tr>
<tr><td colspan=2><b>Notes</b>: $ctrlNotes</td></tr>
$htBtnRow
</table>
__END__;

	if ($doStockLookup) {
	    $out .= '<h3>Stock Items for Order</h3>';
	    $out .= $this->AdminStock($doEdit,$doNew,$doFetch);
	} else {
	    $arLink = $oPage->PathArgs(array('page','id'));
	    $hasItems = $this->ContainsItems();
	    if ($hasItems) {
		$arLink['do'] = 'unfetch';
		//$out .= '['.$oPage->SelfLink($arLink,'put items back in stock').']';
		$out .= '['.$this->AdminLink('replace','put items back in stock',$arLink).']';
	    }
	    $arLink['do'] = 'fetch';
	    //$out .= '['.$oPage->SelfLink($arLink,'find items in stock').']';
	    $out .= '['.$this->AdminLink('find','find items in stock',$arLink).']';
	    if ($this->HasCharges()) {
		$arLink['do'] = 'uncharge';
		//$out .= '['.$oPage->SelfLink($arLink,'remove package charges').']';
		$out .= '['.$this->AdminLink('uncharge','remove package charges',$arLink).']';
	    } else {
		$arLink['do'] = 'charge';
		//$out .= '['.$oPage->SelfLink($arLink,'charge for package').']';
		$out .= '['.$this->AdminLink('charge','charge for package',$arLink).']';
	    }
	}
	/*
	if ($doForm) {
	    if ($doNew || $doFetch) {
		if ($doFetch) {
		    $txtAdd = 'Fetch Items';
		} else {
		    $txtAdd = 'Create Package';
		}
		$out .= '<input type=submit name="btnSim" value="Simulate">';
		$out .= '<input type=submit name="btnAdd" value="'.$txtAdd.'">';
	    }
	    $out .= '</form><hr>';
	}*/

	if (!$isNew) {
	    // new records have no ID yet, so no dependent records


	    //$objSection = new clsWikiSection($objPage,'Contents','package contents',3);
	    //$objSection->ToggleAdd('edit','edit the package contents','edit.lines');
	    //$out = $objSection->Generate();
	    $arActs = array(
	      // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	      new clsActionLink_option(array(),'edit.lines',NULL,'edit',NULL,'edit the package contents')
	      );
	    $out .= $oPage->ActionHeader('Contents',$arActs);

//	    $objTbl = $this->objDB->PkgLines();
	    $tLines = $this->LineTable();
	    $rs = $tLines->GetData('ID_Pkg='.$id);
	    $out .= $rs->AdminList();

	    $arArgs = array(
	      //'add'		=> $vgPage->Arg('add'),
	      //'form'	=> $vgPage->Arg('form'),
	      'descr'	=> ' for this package',
	      );

	    // transactions
	    $out .= $oPage->Skin()->SectionHeader('Transactions','section-header-sub');
	    //$wgOut->addWikiText('===Transactions===',TRUE);
	    $out .= $this->TrxListing($arArgs);

	    // events
	    $out .= $oPage->Skin()->SectionHeader('System Events','section-header-sub');
	    //$wgOut->addWikiText('===Events===',TRUE);
	    $out .= $this->EventListing();
	}
	return $out;
    }
    /*-----
      ACTION: Fill the package from user input, creating it if needed
      INPUT: from HTML form
	qty[x]: quantity to use from stock line ID=x
      HISTORY:
	2014-05-25 This was only used by the old version of AdminPage().
    */
    private function AdminFill($iReally) {
	// get order contents and create lookup array
	$idOrder = clsHTTP::Request()->getInt('order');
	$idShip = clsHTTP::Request()->getInt('shpmt');
	$idPkg = $this->KeyValue();
	$this->Value('ID_Order',$idOrder);
	$rcOLines = $this->OrderRecord()->LineRecords();
	if ($rsOLines->HasRows()) {
	    while ($rsOLines->NextRow()) {
		$idRow = $rsOLines->KeyValue();
		$idItem = $rsOLines->Value('ID_Item');
		//$arRows[$idRow] = $idItem;
// 2010-10-19 this is apparently still under construction. TODO
	    }
	} else {
	    throw new exception('Attempting to fill package from empty order.');
	}

	// sum quantities to put into each pkg line
	$qtyStk = clsHTTP::Request()->getArray('qty');
	$tSLines = $this->StockItemTable();
	$tSBins = $this->BinTable();
	if (is_array($qtyStk)) {
	    foreach ($qtyStk as $idStkLine => $qty) {
		// look up item ID for each stock line being used:
		$rcSLine = $tSLines->GetItem($idStkLine);
		$idLCItem = $rcSLine->Value('ID_Item');
		// look up requested quantities and index by package row

		// build list of quantities for package
		nzAdd($arItQty[$idLCItem],$qty);
		// build list of what to pull
		$arStkQty[$idLCItem][$idStkLine] = $qty;
		// descriptive text of what is being done for this item
		if (isset($arItTxt[$idLCItem])) {
		    $arItTxt[$idLCItem] .= ', ';
		} else {
		    $arItTxt[$idLCItem] = '';
		}
		$rcSBin = $tSBins->GetItem($rcSLine->Value('ID_Bin'));
		$arItTxt[$idItem] .= '<b>'.$qty.'</b> from '.$rcSBin->AdminLink($rcSBin->Value('Code'));
	    }
	} else {
	    return 'Internal error: qtyStk is not an array.<pre>'.print_r($qtyStk,TRUE).'</pre>';
	}

// TO DO: put in code to make sure we aren't pulling more than requested for each line. Don't create pkg if we are.

	$out = '';
	// show what is being pulled from where into each line
	$objItems = $this->LCatItemTable();
	$txtHdr = $iReally?'Pulling Items for Package':'Items to be Pulled for Package';
	$out .= "\n<h2>$txtHdr</h2>\n<ul>";
	foreach ($arItQty as $idItem => $qty) {
	    $objItem = $objItems->GetItem($idItem);
	    $strCatNum = $objItem->Value('CatNum');
	    $out .= '<li>'.$objItem->AdminLink($strCatNum).' (need <b>'.$qty.'</b>): ';
	    $out .= $arItTxt[$idItem];
	}
	$out .= '</ul>';

	if ($iReally) {
	// actually make changes to the database

	    $objOrd = $this->OrderRecord();

	    if (is_null($idPkg)) {
		// package ID not set, so create it:
		//$seq = $objOrd->NextPkgSeq();
		$seq = $this->Table()->NextID();
		// log start of creation
		$arEv = array(
		  'descr' => 'new package #'.$seq.' for order #'.$objOrd->Number(),
		  'where' => __METHOD__,
		  'code'  => 'NEW'
		  );
		$this->StartEvent($arEv);

		// create the package
		$arIns = array(
		  'Seq'		=> $seq,
		  'ID_Order'	=> $this->OrderID(),
		  'WhenStarted'	=> 'NOW()',
		  'ID_Shipment'	=> $idShip
		  );
		$this->Table()->Insert($arIns);
		$id = $this->Table()->LastID();
		$this->Value('ID',$id);
	    }

	    // add the lines
	    $strDescr = 'add items';
	    if (!is_null($idPkg)) {
		$strDescr .= ' to package '.$this->Code;
	    }
	    $arEv = array(
	      'descr' => $strDescr,
	      'where' => __METHOD__,
	      'code'  => '+IT'
	      );
	    $this->StartEvent($arEv);
	    foreach ($arStkQty as $idItem => $arLineQty) {

		// find order line for this item
		$idOLine = $objOrd->LineID_forItem($idItem);

		// create the package line but leave qty blank for now
		$idPLine = $this->AddLine($idItem,$idOLine);
		$objPLine = $this->LineTable($idPLine);

		$qtyItem = 0;
		foreach ($arLineQty as $idLine => $qty) {
		    $objSLine = $objStkLines->GetItem($idLine);
		    // pull qty from stock line idLine
		    $qtyDone = $objSLine->MoveToPkg(
		      $this->ID,
		      $idPLine,
		      'removing item for package',
		      $qty
		      );

		    // add to total for this item
		    $qtyItem += $qtyDone;
		}
		$arUpd = array(
		  'QtyShipped'		=> $qtyItem,
		  'QtyFromStock'	=> $qtyItem
		  );
		$objPLine->Update($arUpd);
	    }
	    if (is_null($idPkg)) {
		$this->FinishEvent();
	    }
	    $this->Reload();
	}

	return $out;
    }
