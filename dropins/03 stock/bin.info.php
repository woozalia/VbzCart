<?php
/*
  PURPOSE: Admin interface for Bin-with-info (vcqtStockBinsInfo / vcqrStockBinInfo)
  HISTORY:
    2017-03-23 Created as part of the process of replacing stored queries
*/
class vcqtAdminStockBinsInfo extends vcqtStockBinsInfo implements fiLinkableTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vcqrAdminStockBinInfo';
    }
    public function GetActionKey() {
	return KS_ACTION_STOCK_BIN;
    }

    // -- SETUP -- //
    // ++ ADMIN UI ++ //

    /*-----
      ACTION:
	Render table of all bins within the given Place
	Show form to allow user to move selected bins
    */
    public function List_forPlace($idPlace) {
	$sqlFilt = 'ID_Place='.$idPlace;

	$rs = $this->SelectStatusRecords($sqlFilt);
	return $rs->AdminList($idPlace);
    }

    // -- ADMIN UI -- //
}
class vcqrAdminStockBinInfo extends vcqrStockBinInfo implements fiLinkableRecord {
    use ftLinkableRecord;
    use vtAdminStockBin;

    // ++ ADMIN UI ++ //

    /*----
      ACTION: Displays the current dataset in multi-row format, with administrative controls
      HISTORY:
	2010-10-30 written; replaces code in VbzStockBins
	2012-01-11 We used to show the breakdown of quantity for each status (for sale, for ship, existing),
	  but after thinking this through for a bit I realized this was pointless -- the only granularity here
	  is the status of the box, not individual items. If items exist at all (i.e. are Active), then the
	  status of the box is the sole determinant of whether they are also for ship or for sale.

	  So now we just show the existing quantity.
	2017-03-23 Moved here from vcrAdminStockBin
      INPUT:
	$idPlace: if NULL, show place column; if set, then all Bins are in one Place, so we don't need a column
    */
    public function AdminList($idPlace=NULL) {
	$hasPlace = !is_null($idPlace);
	//$isPage = is_null($idPlace);
	
	if ($hasPlace) {
	    // this is a listing of Bins for a given Place
	    $oMenu = new fcHeaderMenu();
	    $oHdr = new fcSectionHeader('Bins',$oMenu);
	} else {
	    // this is a standalone listing of all Bins
	    //fcApp::Me()->GetPageObject()->SetPageTitle('Bins');	// probably redundant
	    $oMenu = fcApp::Me()->GetHeaderMenu();
	}
	$sActKey = $this->GetTableWrapper()->GetActionKey();
	$sActPfx = $sActKey.'.';
	
	$oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('do','Action'));
					      // ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	  $oGrp->SetChoice($ol = new fcHeaderChoice($sActPfx.'edit','not sure','edit'));
	  $oGrp->SetChoice($ol = new fcHeaderChoice($sActPfx.'add','add another bin','add'));

	$oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Display'));
	  $oGrp->SetChoice($ol = new fcHeaderChoice($sActPfx.'no-use','display unusable bins','invalid'));
	    $doShowNoUse = $ol->GetIsSelected();
	
	  
	if ($hasPlace) {
	    // Bin listing for Place - need subheader
	    $out = $oHdr->Render();
	} else {
	    // standalone page - page header is rendered automatically
	    $out = NULL;
	}
	
	//$rs = $this;
	if ($this->hasRows()) {
	    // summary
	
	    $nRows = $this->RowCount();
	    $sPlur = fcString::Pluralize($nRows);
	    if ($hasPlace) {
		$sMore = ' found in this Place';
		$this->SetPlaceID($idPlace);
		/* 2017-08-10 I don't *think* we need to replace this with anything... but this field is gone.
		if (!$this->PlaceRecord()->IsActivated()) {
		    $sMore .= ', though the Place itself is DISABLED';
		} */
	    } else {
		$sMore = '';
	    }
	    $out .= "<div class=content>$nRows row$sPlur$sMore.</div>";
	    $this->RewindRows();
	
	    $htPlace = NULL;

	    $out .= "\n".'<form method=post>';

	    if (!$hasPlace) {
		$htPlace = '<th>where</th>';
	    }

	    $out .= <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>code</th>
    $htPlace
    <th>status</th>
    <th>qty</th>
    <th>description</th>
    <th>when<br>created</th>
    <th>when<br>tainted</th>
    <th>when<br>counted</th>
    <th>when<br>voided</th>
  </tr>
__END__;
	    $isOdd = FALSE;
	    while ($this->NextRow()) {
		$row = $this->GetFieldValues();
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;
		
		$qtyTotal = (int)$row['QtyTotal'];
		$htQty = $qtyTotal?$qtyTotal:'-';

		$isActive = $this->SelfIsActive();
		if ($hasPlace) {
		    // if we're listing for a particular Place, bypass that Place's status
		    $isUsable = TRUE;
		} else {
		    $isUsable = $this->HasActivePlace();
		    // "isUsable" is actually more like "would be usable if it was active"
		}
		if ($isUsable || $doShowNoUse) {
		    if ($isActive) {
			$htCellPfx = '';
			$htCellSfx = '';
			$chActive = '&radic;';
		    } else {
			$htCellPfx = '<s>';
			$htCellSfx = '</s>';
			$chActive = '';
		    }
		    $id = $row['ID'];
		    $htID = '<nobr><input type=checkbox name="bin['.$id.']">'.$id.'</nobr>';
		    
		    $htCode = $this->SelfLink_name();
		    $htWhenMade = fcDate::NzDate($row['WhenCreated']);
		    $htWhenVoid = fcDate::NzDate($row['WhenVoided']);
		    $htWhenCount = fcDate::NzDate($row['WhenCounted']);
		    $htWhenTaint = fcDate::NzDate($row['WhenTainted']);

		    if ($hasPlace) {
			// showing Bins in a single Place
			$htPlace = '';	// not showing Place column
		    } else {
			// showing Bins in different Places
			$rcPlace = $this->PlaceRecord();
			if (is_null($rcPlace)) {
			    $sPlace = '<i>root</i>';
			} else {
			    $sPlace = $rcPlace->SelfLink_name();
			}
			$htPlace = "<td>$sPlace</td>";
		    }
		    $htActive = $this->StatusString_local();
		    $htDesc = fcString::EncodeForHTML($row['Descr']);

		    $out .= <<<__END__
  <tr class=$cssClass>
    <td>$htCellPfx$htID$htCellSfx</td>
    <td>$htCellPfx$htCode$htCellSfx</td>
    $htPlace
    <td>$htActive</td>
    <td>$htCellPfx$htQty$htCellSfx</td>
    <td>$htCellPfx<small>$htDesc</small>$htCellSfx</td>
    <td>$htCellPfx$htWhenMade$htCellSfx</td>
    <td>$htWhenTaint</td>
    <td>$htWhenCount</td>
    <td>$htWhenVoid</td>
  </tr>
__END__;
		}	// -IF showing

		/* 2017-09-06 old version from when the query was weird and overcomplicated
		$qtySale = $row['QtyForSale'];
		$qtyShip = $row['QtyForShip'];
		$qtyRec = $row['QtyExisting'];
		$strQty = NULL;

		$qtySaleInt = (int)$qtySale;
		$qtyShipInt = (int)$qtyShip;
		$qtyRecInt = (int)$qtyRec;

		$htQty = $qtyRecInt?$qtyRecInt:'-';

		$isActive = $this->SelfIsActive();
		if ($hasPlace) {
		    // if we're listing for a particular Place, bypass that Place's status
		    $isUsable = TRUE;
		} else {
		    $isUsable = $this->HasActivePlace();
		    // "isUsable" is actually more like "would be usable if it was active"
		}
		if ($isUsable || $doShowNoUse) {
		    if ($isActive) {
			$htCellPfx = '';
			$htCellSfx = '';
			$chActive = '&radic;';
		    } else {
			$htCellPfx = '<s>';
			$htCellSfx = '</s>';
			$chActive = '';
		    }
		    $id = $row['ID'];
		    $htID = '<nobr><input type=checkbox name="bin['.$id.']">'.$id.'</nobr>';
		    $htCode = $this->SelfLink_name();
		    $htWhenMade = fcDate::NzDate($row['WhenCreated']);
		    $htWhenVoid = fcDate::NzDate($row['WhenVoided']);
		    $htWhenCount = fcDate::NzDate($row['WhenCounted']);
		    $htWhenTaint = fcDate::NzDate($row['WhenTainted']);

		    if ($hasPlace) {
			// showing Bins in a single Place
			$htPlace = '';	// not showing Place column
		    } else {
			// showing Bins in different Places
			$rcPlace = $this->PlaceRecord();
			if (is_null($rcPlace)) {
			    $sPlace = '<i>root</i>';
			} else {
			    $sPlace = $rcPlace->SelfLink_name();
			}
			/*
			$htActive = $this->StatusCode();
			$htActive = $this->HasActivePlace()?'&radic;':'<font color=red>x</font>';
			if ($this->IsSellable()) {
			    $htActive .= ' sale';
			}
			if ($this->IsShippable()) {
			    $htActive .= ' ship';
			}
			* /
			$htPlace = "<td>$sPlace</td>";
		    }
		    $htActive = $this->StatusString_local();
		    $htDesc = fcString::EncodeForHTML($row['Descr']);

		    $out .= <<<__END__
  <tr class=$cssClass>
    <td>$htCellPfx$htID$htCellSfx</td>
    <td>$htActive</td>
    $htPlace
    <td>$htCellPfx$htCode$htCellSfx</td>
    <td>$htCellPfx$htQty$htCellSfx</td>
    <td>$htCellPfx<small>$htDesc</small>$htCellSfx</td>
    <td>$htCellPfx$htWhenMade$htCellSfx</td>
    <td>$htWhenTaint</td>
    <td>$htWhenCount</td>
    <td>$htWhenVoid</td>
  </tr>
__END__;
		}	// -IF showing
		*/
	    }	// -WHILE rows
	    
	    // build link for new Bin
	    $arLink = array(
	      'page'	=> KS_ACTION_STOCK_BIN,
	      'id'	=> KS_NEW_REC,
	      );
	    if (!is_null($idPlace)) {
		$arLink['id-place'] = $idPlace;
	    }
	    $url = $this->SelfURL($arLink);
	    $htLink = fcHTML::BuildLink($url,'create new bin');
	    
	    $out .= "\n</table>"
	      .'<div class=content>'
	      ."\n<input type=submit name=btnSelBins value=\"Move items to...\">"
	      ."[ $htLink ]"
	      ."\n</div></form>"
	      ;
	} else {
	    $out .= '<div class=content>No bins found.</div>';
	}
	
	// Show Move-Bin(s) section, if active:
	$out .= $this->HandleAdminListForm();
	// TODO: process form input separately from displaying it, so we can save CPU

	return $out;
    }
    /*----
      ACTION: Displays the current dataset in multi-row format, with administrative controls
      HISTORY:
	2010-10-30 written; replaces code in VbzStockBins
	2017-04-18 moved vcqrStockBinInfo from vcrAdminStockBin, but not yet sure if it's what we need
	  ...and then on further debugging, realized this is a logic class, not an admin class.
	  Moved to vcqrAdminStockBinInfo, but there is already an AdminList() -- so renamed AdminList_alt() for now.
	    it may be a complete duplication of function.
      INPUT:
	$iArgs
	  'do.place': TRUE = show place column; FALSE = all in one place, don't bother to list it
    */
    public function AdminList_alt($idPlace=NULL) {
	$isPage = is_null($idPlace);
	$doPlace = $isPage;

	$sActPfx = $this->GetTableWrapper()->GetActionKey().'.';

	if ($isPage) {
	    $oMenu = fcApp::Me()->GetHeaderMenu();
	    $out = NULL;
	} else {
	    $oMenu = new fcHeaderMenu();
	    $oHdr = new fcSectionHeader('Bins in place #'.$idPlace,$oMenu);
	    $out = $oHdr->Render();
	}

	$oMenu->SetNode($ol = new fcMenuOptionLink($sActPfx.'do','edit',NULL,NULL,'edit Bin record'));
	$oMenu->SetNode($ol = new fcMenuOptionLink($sActPfx.'do','add',NULL,NULL,'add new Bin record'));
	$oMenu->SetNode($ol = new fcMenuOptionLink($sActPfx.'show','no-use',NULL,NULL,'show unusable Bin records'));
	  $doShowNoUse = $ol->GetIsSelected();

	/*
	$arActs = array(
	  new clsActionLink_option(
	    array(),		// additional link data
	    'edit.bins',	// link key
	    NULL,		// group key
	    'edit',		// display when off
	    NULL		// display when on
	    ),
	  new clsActionLink_option(
	    array(),		// additional link data
	    'add.bin',		// link key
	    NULL,		// group key
	    'add',		// display when off
	    NULL,		// display when on
	    'add a new bin'	// popup description
	    ),
	  new clsAction_section('show'),
	  new clsActionLink_option(
	    array(),		// additional link data
	    'show.no-use',	// link key
	    NULL,		// group key
	    'unusable',		// display when off
	    NULL,		// display when on
	    'show unusable bins'	// popup description
	    ),
//	  new clsActionLink_option(array(),'inv',NULL,'list all inventory of location '.$strName)
	  );

	$oPage = $this->Engine()->App()->Page();
	$out = NULL;
	
	if ($isPage) {
	    $oPage->PageHeaderWidgets($arActs);
	} else {
	    $out .= $oPage->ActionHeader('Bins',$arActs);
	}
	*/
	
	$htPlace = NULL;
	if ($this->hasRows()) {

	    $out .= "\n".'<form method=post>';

	    if ($doPlace) {
		$htPlace = '<th>where</th>';
	    }

	    $out .= <<<__END__
<table class=sortable>
  <tr>
    <th>ID</th>
    <th>status</th>
    $htPlace
    <th>code</th>
    <th>qtys</th>
    <th>description</th>
    <th>when<br>created</th>
    <th>when<br>tainted</th>
    <th>when<br>counted</th>
    <th>when<br>voided</th>
  </tr>
__END__;
	    $isOdd = FALSE;
	    while ($this->NextRow()) {
		//$row = $rs->Row;
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$qtySale = $this->QuantityForSale();
		$qtyShip = $this->QuantityForShipping();
		$qtyRec = $this->QuantityExisting();
		$strQty = NULL;

		$qtySaleInt = (int)$qtySale;
		$qtyShipInt = (int)$qtyShip;
		$qtyRecInt = (int)$qtyRec;

/* 2012-01-11 We used to show the breakdown of quantity for each status (for sale, for ship, existing),
  but after thinking this through for a bit I realized this was pointless -- the only granularity here
  is the status of the box, not individual items. If items exist at all (i.e. are Active), then the
  status of the box is the sole determinant of whether they are also for ship or for sale.

  So now we just show the existing quantity.
*/
		$htQty = $qtyRecInt?$qtyRecInt:'-';

		$isActive = $this->IsActive();
		$isUsable = $this->IsUsable();
		if ($isUsable || $doShowNoUse) {
		    if ($isActive) {
			$htCellPfx = '';
			$htCellSfx = '';
			$chActive = '&radic;';
		    } else {
			$htCellPfx = '<s>';
			$htCellSfx = '</s>';
			$chActive = '';
		    }
		    $id = $this->GetKeyValue();
		    $htID = '<nobr><input type=checkbox name="bin['.$id.']">'.$id.'</nobr>';
		    $htCode = $this->SelfLink_name();
		    $htWhenMade = fcDate::NzDate($this->WhenCreated());
		    $htWhenVoid = fcDate::NzDate($this->WhenVoided());
		    $htWhenCount = fcDate::NzDate($this->WhenCounted());
		    $htWhenTaint = fcDate::NzDate($this->WhenTainted());

		    if ($doPlace) {
			$rcPlace = $this->PlaceRecord();
			if (is_null($rcPlace)) {
			    $sPlace = '<i>root</i>';
			} else {
			    $sPlace = $rcPlace->SelfLink_name();
			}
			$htActive = $this->StatusCode();
		    } else {
			$htActive = $this->IsActive()?'&radic;':'<font color=red>x</font>';
			if ($this->IsForSale()) {
			    $htActive .= ' sale';
			}
			if ($this->IsForShipping()) {
			    $htActive .= ' ship';
			}
		    }
		    if ($doPlace) {
			$htPlace = "<td>$sPlace</td>";
		    }
		    $htDesc = $this->Description();

		    $out .= <<<__END__
  <tr class=$cssClass>
    <td>$htCellPfx$htID$htCellSfx</td>
    <td>$htActive</td>
    $htPlace
    <td>$htCellPfx$htCode$htCellSfx</td>
    <td>$htCellPfx$htQty$htCellSfx</td>
    <td>$htCellPfx<small>$htDesc</small>$htCellSfx</td>
    <td>$htCellPfx$htWhenMade$htCellSfx</td>
    <td>$htWhenTaint</td>
    <td>$htWhenCount</td>
    <td>$htWhenVoid</td>
  </tr>
__END__;
		}	// -IF showing
	    }	// -WHILE rows
	    $out .= "\n</table>";
	    $out .= "\n<input type=submit name=btnSelBins value=\"Move items to...\">";
	    $arLink = array(
	      'page'	=> KS_ACTION_STOCK_BIN,
	      'id'	=> KS_NEW_REC,
	      );
	    if (!is_null($idPlace)) {
		$arLink['id-place'] = $idPlace;
	    }
	    $url = $this->SelfURL($arLink);
	    $htLink = fcHTML::BuildLink($url,'create new bin');
	    $out .= "[ $htLink ]";
	    $out .= "\n</form>";
	} else {
	    $out .= 'No bins found.';
	}
	
	// Show Move-Bin(s) section, if active:
	$out .= $this->AdminListSave();
	// TODO: process form input separately from displaying it, so we can save CPU

	return $out;
    }
    /*----
      ACTION: Process user-input changes to the AdminList
      FUTURE: Should this be a method of the table instead of the rowset?
      HISTORY:
	2017-03-23
	* Moved from vcrAdminStockBin to vcqrAdminStockBinInfo.
	* Renamed from AdminListSave() to HandleAdminListForm()
    */
    protected function HandleAdminListForm() {
	$out = '';

	$doSelBins = array_key_exists('btnSelBins',$_REQUEST);
	$doMoveBins = array_key_exists('btnMoveBins',$_REQUEST);

	if ($doSelBins || $doMoveBins) {
	    $arBins = $_REQUEST[KS_ACTION_STOCK_BIN];

	    if ($doSelBins) {
		$nBins = count($arBins);
		$sBinWord = 'Bin'.fcString::Pluralize($nBins);

		$oPage = $this->Engine()->App()->Page();
		$out .= $oPage->ActionHeader('Move '.$sBinWord);

		$out .= '<form method=post>';	 // this is an additional form, not the main one
		$out .= "<b>$sBinWord</b>:";
		foreach ($arBins as $idBin => $zero) {
		    $rcBin = $this->Table()->GetItem($idBin);
		    $out .= ' '.$rcBin->SelfLink_name().'<input type=hidden name="bin['.$idBin.']" value=1>';
		}
		$out .= '<br><b>Notes</b> (optional):<br><textarea name=notes height=2 width=40></textarea>';
		$htPlaces = $this->PlaceTable()->DropDown('ID_Place');
		$out .= "\n<br><input type=submit name=btnMoveBins value=\"Move to:\">$htPlaces";
		$out .= '</form>';
	    }
	    if ($doMoveBins) {
		$idPlace = (int)$_REQUEST['ID_Place'];
		$txtNotes = $_REQUEST['notes'];
		$rcPlace = $this->PlaceTable($idPlace);
		$htPlace = $rcPlace->SelfLink($rcPlace->Value('Name'));

		// create overall event:
		$txtBins = '';
		$htBins = '';
		foreach ($arBins as $idBin => $zero) {
		    $rcBin = $this->Table()->GetItem($idBin);
		    $sBin = $rcBin->Code();
		    $txtBins .= ' '.$sBin;
		    $htBins .= ' '.$rcBin->SelfLink($sBin);
		    $arBins[$idBin] = $rcBin->RowCopy();	// so we don't have to look them up again
		}
		$sqlDescr = 'Moving bins to [#'.$idPlace.'='.$rcPlace->Name().']:'.$txtBins;
		$out .= 'Moving bins'.$htBins.' to [#'.$idPlace.'='.$htPlace.']...';

		$arEv = array(
		  'descr'	=> $sqlDescr,
		  'where'	=> __METHOD__,
		  'code'	=> 'MVL',	// MoVe List
		  'notes'	=> $txtNotes
		  );
		$this->StartEvent($arEv);
		foreach ($arBins as $idBin => $rcBin) {
		    $rcBin->MoveTo($idPlace);
		}
		$out .= ' done.';
		$this->FinishEvent();
	    }
	}
	return $out;
    }

    // -- ADMIN UI -- //
}