<?php

// 2015-10-21 Removed from pkg.php -- reimplemented using standard callbacks

    /*----
      NOTE: $arFields is here to maintaing compatibility with new parent function. This could probably
	be rewritten to take advantage of that service, but for now it just implements it from scratch.
    */
    public function AdminRows(array $arFields) {
	$arArgs = $this->Table()->ExecArgs();

	//$doAdd = (nz($iArgs['add']) == 'pkg');
	$strOmit = clsArray::nz($arArgs,'omit');
	$doShip = ($strOmit != 'ship');
	$doOrd =  ($strOmit != 'ord');
	$canAddNew = clsArray::nz($arArgs,'can-add',FALSE);

	if ($this->hasRows()) {
	    if ($doShip) {
		$htShip = '<th>Shipment</th>';
	    } else {
		$htShip = NULL;
	    }

	    if ($doOrd) {
		$htOrd = '<th>Order #</th>';
	    } else {
		$htOrd = NULL;
	    }

	    $out = <<<__END__
<table class=sortable>
  <tr>
    <th>ID</th>
    $htOrd
    <th>Seq</th>
    <th>Started</th>
    <th>R?</th>
    <th title="total # of items">qty</th>
    $htShip
    <th>sale $</th>
    <th>chg s/h $</th>
    <th>act s/h $</th>
    <th>notes</th>
  </tr>
__END__;

	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$cssRowStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$cssRowClass = NULL;
		if ($this->IsActive()) {
		    $cssRowClass = 'active';
		} else {
		    //$cssRowStyle .= ' text-decoration:line-through; color: red;';
		    $cssRowClass = 'voided';
		}

	// This is needed for the "add package" link
		if (isset($arArgs['order'])) {
		    $idOrder = $arArgs['order'];
		} else {
		    $idOrder = $this->OrderID();
		}

		$row = $this->Values();

		$id		= $row['ID'];
		$wtID		= $this->SelfLink();
		$strSeq		= $row['Seq'];
		if ($doOrd) {
		    if ($this->OrderID() == 0) {
			$htOrdVal = '<span class=error>N/A</span>';
		    } else {
			$rcOrd = $this->OrderRecord();
			$htOrdVal = $rcOrd->SelfLink_name();
		    }
		    $htOrdCell = "<td>$htOrdVal</td>";
		}
		$dtWhenStarted	= $row['WhenStarted'];
		$htStatus	= $row['isReturn']?'R':'';
		$htQtyPkg	= $this->ItemQty();
		if ($doShip) {
		    $idShip		= $row['ID_Shipment'];
		    if (is_null($idShip)) {
			$wtShip = '<i>not assigned</i>';
		    } else {
			$objShip	= $this->ShipmentTable($idShip);
			$wtShip		= $objShip->SelfLink($objShip->ShortName());
		    }
		}
		$sSale 		= $this->Charge_forItemSale_html();
		//is_null($row['ChgItmSale'])?'-':clsMoney::Format_withSymbol($row['ChgItmSale']);
		$strChgSh	= $this->Charge_forShippingItem_html();
		//is_null($row['ChgShipItm'])?'-':clsMoney::Format_withSymbol($row['ChgShipItm']);
		$crChgPkg = $row['ChgShipPkg'];
		if ($crChgPkg != 0) {
		    $strChgSh .= "<i>+$crChgPkg</i>";
		}
		$strActSh	= is_null($row['ShipCost'])?'':clsMoney::Format_withSymbol($row['ShipCost']);
		$crActPkg = $row['PkgCost'];
		if ($crActPkg != 0) {
		    $strActSh .= "<i>+$crActPkg</i>";
		}

		$strNotes = $row['ShipNotes'];

		if ($doShip) {
		    $htShipCell = "<td>$wtShip</td>";
		} else {
		    $htShipCell = NULL;
		}

		$out .= <<<__END__
  <tr style="$cssRowStyle" class="$cssRowClass">
    <td>$wtID</td>
    $htOrdCell
    <td>$strSeq</td>
    <td>$dtWhenStarted</td>
    <td>$htStatus</td>
    <td>$htQtyPkg</td>
    $htShipCell
    <td>$sSale</td>
    <td>$strChgSh</td>
    <td>$strActSh</td>
    <td>$strNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	    $strAdd = 'Add a new package';
	} else {
	    $strDescr = nz($arArgs['descr']);
	    $out = "\nNo packages".$strDescr.'. ';
	    $strAdd = 'Create one';
	}
	if (!empty($idOrder)) {
	    // if Order ID is known, it may be useful to be able to create a package here:
//	    $out = "'''Internal error''': order ID is not being set in ".__METHOD__;
//	} else {
	    $arLink = array(
	      'page'		=> 'pkg',
	      KS_PAGE_KEY_ORDER	=> $idOrder,
	      'id'		=> 'new',
	      'show'		=> FALSE
	      );
	    $oPage = $this->Engine()->App()->Page();
	    $url = $oPage->SelfURL($arLink);
	    if ($canAddNew) {
		$out .= clsHTML::BuildLink($url,$strAdd,'create a new package');
	    }
	}
	return $out;
    }

// 2016-08-12 Another removed chunk -- old version of rewritten method

    /*----
      ACTION: display the values for the current package record
      RETURNS: rendered display of package record data
    */
    protected function AdminPage_values_OLD($doForm,$doEdit) {
	$out = NULL;

	if ($doForm) {
	    $idOrd = $this->Value('ID_Order');
	    $htShip = $this->ShipmentTable()->GetActive('WhenCreated DESC')->DropDown(NULL,$this->ShipID());
	    $arLink = array(
	      'edit'	=> FALSE,
	      'add'	=> FALSE,
	      'do'	=> FALSE,
	      'order'	=> FALSE
	      );
	    //$htPath = $vgPage->SelfURL($arLink);
	    //$out .= "\n<form method=post action=\"$htPath\">";
	    $out .= "\n<form method=post name=".__METHOD__.'>';
	    if ($this->IsNew()) {
		$out .= "<input name=order type=hidden value=$idOrd>";
	    }
//	    $arArgs['id'] = 'new';	// 2014-04-10 is this right?
	} else {
	    //$idShip = $rcShip->KeyValue();
	    $idShip = $this->ShipID();
	    $arArgs['id'] = $idShip;
	    if (is_null($idShip)) {
		$htShip = '<i>N/A</i>';
	    } else {
		$htShip = $rcShip->AdminLink_name();
	    }
	}

	$htOrder = $this->OrderRecord()->AdminLink_name();
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>$htOrder</td></tr>";
	if (!$this->IsNew()) {
	    // only display these for an existing package

	    $ctrlCheckIn = NULL;
	    $ctrlVoidNow = NULL;

	    if ($doEdit) {
		$objForm = $this->PageForm();
		$ctrlWhenStarted = $objForm->RenderControl('WhenStarted');
		$ctrlWhenFinished = $objForm->RenderControl('WhenFinished');
		$ctrlWhenChecked = $objForm->RenderControl('WhenChecked');
		$ctrlWhenVoided = $objForm->RenderControl('WhenVoided');

		$oPage = $this->Engine()->App()->Page();
		$arLink = $oPage->PathArgs(array('page','id'));

		$ctrlVoidNow = $this->Render_DoVoid();
		$ctrlCheckIn = $this->Render_DoCheckIn();
	    } else {
		$dtWhenStarted = $this->Value('WhenStarted');
		$dtWhenFinished = $this->Value('WhenFinished');
		$dtWhenChecked = $this->Value('WhenChecked');
		$dtWhenVoided = $this->Value('WhenVoided');

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
	$intShPounds = $this->ShipPounds();
	$fltShOunces = $this->ShipOunces();
	$htNotes = fcString::EncodeForHTML($this->ShipNotes());
	$htTrack = fcString::EncodeForHTML($this->ShipTracking());
	$dtWhenArrived = $this->WhenArrived();
	if ($doEdit) {
	    $fPage = $this->PageForm();
	    $ctrlChgSale	= '$'.$fPage->RenderControl('ChgItmSale');
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
	    $ctrlChgSale	= $this->Value('ChgItmSale');
	    $ctrlChgShipItm	= $this->Value('ChgShipItm');
	    $ctrlChgShipPkg	= $this->Value('ChgShipPkg');
	    $ctrlCostShp	= $this->Value('ShipCost');
	    $ctrlCostPkg	= $this->Value('PkgCost');
	    $ctrlShWeight	=
	      (is_null($intShPounds)?'':"$intShPounds pounds")
	      .(is_null($fltShOunces)?'':" $fltShOunces ounces");
	    $ctrlNotes		= $htNotes;
	    $ctrlTrack		= $htTrack;
	    $ctrlWhenArrived	= $dtWhenArrived;
	}

	$out .= <<<__END__
<tr><td align=right><b>Sale price</b>:</td><td>$ctrlChgSale</td></tr>
<tr><td align=right><b>Per-item s/h total charged</b>:</td><td>$ctrlChgShipItm</td></tr>
<tr><td align=right><b>Per-pkg s/h amount charged</b>:</td><td>$ctrlChgShipPkg</td></tr>
<tr><td align=right><b>Shipment</b>:</td><td>$htShip</td></tr>
<tr><td align=right><b>Actual shipping cost</b>:</td><td>$ctrlCostShp</td></tr>
<tr><td align=right><b>Actual package cost</b>:</td><td>$ctrlCostPkg</td></tr>
<tr><td align=right><b>Actual shipping weight</b>:</td><td>$ctrlShWeight</td></tr>
<tr><td align=right><b>Delivery tracking #</b>:</td><td>$ctrlTrack</td></tr>
<tr><td align=right><b>When arrived</b>:</td><td>$ctrlWhenArrived</td></tr>
<tr><td colspan=2><b>Notes</b>:<br>$ctrlNotes</td></tr>
__END__;

	return $out;
    }
