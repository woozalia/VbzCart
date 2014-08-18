<?php
/*
  PURPOSE: classes for handling needed restocks
  HISTORY:
    2014-03-09 split off from request.php
*/

class VCM_RstksNeeded extends VCT_RstkReqs {

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminItemsNeeded();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function OrderTable() {
	return $this->Engine()->Make(KS_CLASS_ORDERS);
    }
    protected function LCatItemTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_ITEMS,$id);
    }
    protected function StockBinTable() {
	return $this->Engine()->Make(KS_CLASS_STOCK_BINS);
    }
    protected function StockItemTable() {
	return $this->Engine()->Make(KS_CLASS_STOCK_LINES);
    }
    protected function RequestTable($id=NULL) {
    	return $this->Engine()->Make(KS_CLASS_RESTOCKS_REQUESTED,$id);
    }
    protected function RequestItemTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_RESTOCK_REQ_ITEMS,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ SIMPLE CALCULATIONS ++ //

    /*----
      RETURNS: recordset containing, for the given supplier:
	* last restock, when sorted by PO#
	* last restock, when sorted by order date
	If there is only one record, then these two criteria apply to the same record.
    */
    protected function Last_forSupp($idSupp) {
	$sqlPfx = 'SELECT * FROM '.KS_TABLE_RESTOCK_REQUEST.' WHERE ID_Supplier='.$idSupp.' ORDER BY ';
	$sqlSfx = ' DESC LIMIT 1';

	$sqlPart1 = $sqlPfx.'PurchOrdNum'.$sqlSfx;
	$sqlPart2 = $sqlPfx.'WhenOrdered'.$sqlSfx;	// or maybe this should be WhenCreated?

	$sql = "($sqlPart1) UNION ($sqlPart2)";

	$rs = $this->Engine()->DataSet($sql,$this->ClassSng());

	return $rs;
    }

    // -- SIMPLE CALCULATIONS -- //
    // ++ ADMIN INTERFACE ++ //

    public function AdminItemsNeeded() {
	$oPage = $this->Engine()->App()->Page();

// display appropriate header
	$idSupp = $oPage->PathArg('supp');
	if (empty($idSupp)) {
	    $out = '<h2>all items needed</h2>';
	} else {
	    $rcSupp = $this->Engine()->Suppliers($idSupp);
	    $out = '<h2>'.$rcSupp->Value('Name').' items needed</h2>';
// handle any special display functions
	    $out .= $this->AdminItemsSave($rcSupp);
// 2013-11-05 I'm guessing that this only applies when we're filtering by supplier
	}

	$arNeed = $this->ItemsNeeded();
// now show the current data
	if (is_array($arNeed)) {
// SHUFFLE THE DATA AND LOOK UP NEEDED ITEM INFO
	    $arData = $this->FigureItemsNeeded_fromArray($arNeed,$idSupp);
	    // display menu showing needed quantities for each supplier
	    $out .= $this->RenderSupplierMenu($arData,$idSupp);
	    // display the needed-items list for the current supplier (or all of them)
	    $out .= $this->RenderItemsNeeded_fromArray($arData['item']);
	    if (isset($rcSupp)) {
		// only allow adding items if they all belong to the same supplier
		$out .= '<hr>';
		// display option to add items to existing request
		$out .= '<input type=submit name="btnAddTo" value="Add Items"> to this request:';
		$out .= $this->RowsActive_dropdown(array('supp'=>$idSupp));
		$out .= '<br>- OR -<br>';
		$out .= '<input type=submit name="btnCreate" value="Create Request" />';
		$out .= ' with PO # <input type=text size=10 name=ponum /> from these items';


		// display option to create a new request
		$rcLast = $this->Last_forSupp($idSupp);
		$cntRows = $rcLast->RowCount();
		if ($cntRows == 0) {
		    // no previous restocks; not sure how to handle this yet
		    $out .= ' (no previous restocks for this supplier)';
		} elseif ($cntRows == 1) {
		    // two criteria produce the same result
		    $rcLast->NextRow();		// load the row
		    $out .= ' (previous restock was <b>'.$rcLast->Value('PurchOrdNum').'</b>)';
		} elseif ($cntRows == 2) {
		    $rcLast->NextRow();		// load first row
		    // one of these is not the latest, but we don't know which one yet.
		    $poLastByOD = $poLastByPO = $rcLast->Value('PurchOrdNum');

		    $dtLatest = $rcLast->Value('WhenOrdered');
		    $rcLast->NextRow();		// load second row
		    $poLatest = $rcLast->Value('PurchOrdNum');
		    if ($rcLast->Value('WhenOrdered') > $dtLatest) {
			// this is latest-by-OD
			$poLastByOD = $poLatest;
		    } else {
			$poLastByPO = $poLatest;
		    }
		    $out .= " - most recent restocks were <b>$poLastByPO</b> (by PO#), <b>$poLastByOD</b> (by order date)";
		}
	    }
	    $out .= '</form>';
	} else {
	    $out = 'There are no items needed for open orders.';
	}
	return $out;
    }
    /*----
      ACTION: Do any data-modification actions that need to be done before the data is displayed --
	typically in response to form input.
      INPUT: Supplier object, just for logging the event
      RETURNS: text to display (status messages)
    */
    protected function AdminItemsSave(clsSupplier $rcSupp) {
	$oPage = $this->Engine()->App()->Page();
	$oSkin = $oPage->Skin();

	$doAddTo = $oPage->ReqArgBool('btnAddTo');
	$doCreate = $oPage->ReqArgBool('btnCreate');

	$db = $this->Engine();

	$out = NULL;

	if ($doCreate) {
	    $strPONum = $oPage->ReqArgText('ponum');
	    if (empty($strPONum)) {
		$out .= $oSkin->WarningMessage('Please enter a purchase order number</b> for the new restock request.');
	    } else {
		$sqlPONum = $db->SafeParam(strtoLower($strPONum));
		$rcReq = $this->RequestTable()->GetData('(LOWER(PurchOrdNum)="'.$sqlPONum.'") AND (WhenKilled IS NULL)');
		$doCreate = ($rcReq->RowCount() == 0);
		if (!$doCreate) {
		    $rcReq->NextRow();	// load the first row (should be the onlhy one)
		    $htPO = $rcReq->AdminLink($rcReq->PurchaseOrderNumber());
		    $out .= $oSkin->WarningMessage('"'.$htPO.'" has already been used as a purchase order number. <b>Please choose a new one</b>.');
		}
	    }
	}

	$arItemsNeed = $oPage->ReqArgArray('item_need');	// items needed total
	$arItemsCOrd = $oPage->ReqArgArray('item_sold');	// items pre-sold (ordered by customers)

	$doAddItems = $doCreate || $doAddTo;
	if ($doAddItems) {
	    // build quick text list of items for event log
	    $strItems = '';
	    foreach ($arItemsNeed as $id => $qty) {
		$strItems .= '/'.$id.'='.$qty;
	    }
	}

	if ($doCreate) {
	    // log the attempt
	    $arEv = array(
	      'descr'	=> 'Creating restock request PO# '.$strPONum,
	      'where'	=> __METHOD__,
	      'code'	=> '+RREQ',
	      'params'	=> ':items:'.$strItems
	      );
	    $rcSupp->StartEvent($arEv);
	    $htMsg = 'Creating restock request PO# <b>'.$strPONum.'</b> for these items:';
	    $rcReq = $this->RequestTable()->Create($rcSupp->KeyValue(),$strPONum,$arItemsNeed);
	    $idNew = $rcReq->KeyValue();
	    $arEv = array('descrfin'	=> 'ID='.$idNew);
	    $rcSupp->FinishEvent($arEv);
	} elseif ($doAddTo) {
	    $idRstk = $oPage->ReqArgInt('req');
	    $rcReq = $this->RequestTable($idRstk);
	    $htReq = $rcReq->AdminLink_name();
	    $htMsg = 'Items added to restock request PO# <b>'.$htReq.'</b>:';
	}
	if ($doAddItems) {	 // adding items to a restock which exists *now*
	    // log the attempt
	    $arEv = array(
	      clsSysEvents::ARG_DESCR_START	=> 'Adding items',
	      clsSysEvents::ARG_WHERE	=> __METHOD__,
	      clsSysEvents::ARG_CODE		=> '+IT',
	      clsSysEvents::ARG_PARAMS	=> ':items:'.$strItems
	      );
	    $rcReq->StartEvent($arEv);
	    // add items to the restock:
	    $xts = new xtString();
	    $cntAdded = 0;
	    foreach ($arItemsNeed as $id => $qtyNeed) {
		$rcLCItem = $this->LCatItemTable($id);
		$htMsg .= ' '.$rcLCItem->AdminLink($rcLCItem->CatNum);
		$qtySold = $arItemsCOrd[$id];
		$ok = $rcReq->AddItem($rcLCItem,$qtyNeed,$qtySold);
		if ($ok !== FALSE) {
		    $cntAdded++;
		}
	    }
	    $out .= $oSkin->SuccessMessage($htMsg);
	    $arEv = array(
	      clsSysEvents::ARG_DESCR_FINISH	=> $cntAdded.' item'.Pluralize($cntAdded).' added'
	      );
	    $rcReq->FinishEvent($arEv);
	}
	return $out;
    }
    /*----
      ACTION: Render the supplier items-needed filter menu
      INPUT:
	* iData : array in format produced by igureItemsNeeded_fromArray()
	* iSupp: currently chosen supplier (if any) -- gets highlighted
      FUTURE: This could be simplified somewhat by using $vgPage->SelfLinkMenu()
    */
    protected function RenderSupplierMenu(array $iData,$iSupp) {
	$oPage = $this->Engine()->App()->Page();

	$arSupp = $iData['supp'];

	$out = '<b>Show</b>: ';

	$arLink = array(
	  'page'	=> KS_ACTION_RESTOCK_NEED
	  );
	$urlLink = $oPage->SelfURL($arLink);
	$htLink = clsHTML::BuildLink($urlLink,'*all*','Show items for all suppliers');
	//$htLink = '['.$vgOut->SelfLink($arLink,'*all*','Show items for all suppliers').']';
	$htCtrl = "[$htLink]";

	$out .= clsHTML::FlagToFormat($htCtrl,empty($iSupp));

	foreach ($arSupp as $key => $arData) {	// key = supplier catkey
	    $obj = $arData['obj'];
	    $idSupp = $obj->ID;

	    $arLink['supp'] = $idSupp;
	    $urlLink = $oPage->SelfURL($arLink);
	    $htLink = clsHTML::BuildLink($urlLink,$key,'show only '.$obj->Name.' items');
	    //$htLink = $vgOut->SelfLink($arLink,$key,'show only '.$obj->Name.' items');
	    $sCount = $arData['count'];
	    $htCtrl = "[$htLink-$sCount]";

	    $out .= ' '.clsHTML::FlagToFormat($htCtrl,($iSupp == $idSupp));
	}
	return $out;
    }
    /*----
      ACTION: Render items needed for restock
      INPUT: item array in format produced by FigureItemsNeeded_fromArray
    */
    protected function RenderItemsNeeded_fromArray(array $iItems) {
	global $vgOut;

	$arCat = $iItems;

	$htClrA = 'bgcolor=#ddddff';
	$htClrB = 'bgcolor=#ddffdd';
	$htClrC = 'bgcolor=#ffdddd';
// FINALLY, RENDER:
	$out = '<form method=post><table>';
	$out .= '<tr>'
	  .'<th colspan=1></th>'
	  .'<th colspan=12 align=center bgcolor=#aaaaaa>quantities</th>'
	  .'</tr>';
	$out .= '<tr>'
	  .'<td colspan=1></td>'
	  .'<td colspan=5 align=center bgcolor=#aaaaff>orders</td>'
	  .'<td colspan=3 align=center bgcolor=#aaffaa>stock</td>'
	  .'<td colspan=3 align=center bgcolor=#ffaaaa>restocks</td>'
	  .'</tr>';
	$out .= '<tr>'
	  .'<th>Cat# /Description</th>'
	  .'<td '.$htClrA.'>ord</td>'
	  .'<td '.$htClrA.'>shp</td>'
	  .'<td '.$htClrA.'>kld</td>'
	  .'<td '.$htClrA.'>n/a</td>'
	  .'<th '.$htClrA.'>+need</th>'
	  .'<td '.$htClrB.'>min</td>'
	  .'<td '.$htClrB.'>got</td>'
	  .'<th '.$htClrB.'>+need</th>'
	  .'<td '.$htClrC.'>ord</td>'
	  .'<td '.$htClrC.'>rcd</td>'
	  .'<th '.$htClrC.'>-exp</th>'
	  .'<th>NEED</th>'
	  .'<th>deadline</th>'
	  .'<th>oldest</th>'
	  .'<th>newest</th>'
	  .'<th>cost</th>'
	  .'<th>status</th>'
	  .'</tr>';

	foreach ($iItems as $txtCatNum => $arItem) {
	    $objItem = $arItem['obj'];
	    $arData = $arItem['data'];

	    $htCatNum = $objItem->AdminLink($txtCatNum);
	    $htDescr = $objItem->FullDescr_HTML(' - ');
	    $dtOldest = $arData['oldest'];
	    $dtNewest = $arData['newest'];
	    if ($dtNewest == $dtOldest) {
		$htNewest = '--';
	    } else {
		$htNewest = $dtNewest;
	    }
	    $htCost = $objItem->PriceBuy;
	    $htStatus = '';
	    if ($objItem->isInPrint) {
		$htStatus = 'OK';
	    } else {
		$htStatus = '<b>N/A</b>';
	    }
	    if ($objItem->isCloseOut) {
		$htStatus .= ' CLOSEOUT';
	    }
	    if ($objItem->isCurrent) {
		$htStatus .= ' &radic;';
	    } else {
		$htStatus .= ' NOT-CURRENT';
	    }
	    $qtyCOrd = $arData['ord-need'];	// qty needed for customer order(s)
	    $qtyNeed = $arData['need'];	// qty needed overall (including stock)
	    $out .= '<input type=hidden name=item_need['.$objItem->ID.'] value="'.$qtyNeed.'">';
	    $out .= '<input type=hidden name=item_sold['.$objItem->ID.'] value="'.$qtyCOrd.'">';
	    $nReqExp = $arData['req-exp'];
	    if ($nReqExp > 0) {
		$htReqExp = "(<b>$nReqExp</b>)";
	    } else {
		$htReqExp = NULL;
	    }
	    $out .= '<tr>'
	      .'<td>'.$htCatNum.'<br><small>'.$htDescr.'</small></td>'

	      .'<td '.$htClrA.' align=center>'.$arData['ord-req'].'</td>'
	      .'<td '.$htClrA.' align=center>'.$arData['ord-shp'].'</td>'
	      .'<td '.$htClrA.' align=center>'.$arData['ord-kld'].'</td>'
	      .'<td '.$htClrA.' align=center>'.$arData['ord-na'].'</td>'
	      .'<td '.$htClrA.' align=center><b>'.$qtyCOrd.'</b></td>'

	      .'<td '.$htClrB.' align=center>'.$arData['stk-min'].'</td>'
	      .'<td '.$htClrB.' align=center>'.$arData['stk-got'].'</td>'
	      .'<td '.$htClrB.' align=center><b>'.$arData['stk-need'].'</b></td>'

	      .'<td '.$htClrC.' align=center>'.$arData['req-ord'].'</td>'
	      .'<td '.$htClrC.' align=center>'.$arData['req-rcd'].'</td>'
	      ."<td $htClrC align=center>$htReqExp</td>"

	      .'<td align=center><b>'.$qtyNeed.'</b></td>'
	      .'<td>'.nz($arData['need-by']).'</td>'
	      .'<td>'.$dtOldest.'</td>'
	      .'<td align=center>'.$htNewest.'</td>'
	      .'<td align=right>$'.$htCost.'</td>'
	      .'<td>'.$htStatus.'</td>'
	      .'</tr>';
	}
	$out .= '</table>';
	return $out;
    }
    /*----
      ACTION: Renders drop-down box of active requests
      RETURNS: HTML code
      INPUT:
	iArgs = array of optional arguments
	  iArgs['name'] = name to use for HTML control
	  iArgs['val'] = value to show as default
	  iArgs['supp'] = ID of supplier - if set, show only requests for this supplier
    */
    public function RowsActive_dropdown(array $iArgs=NULL) {
	$iName = isset($iArgs['name'])?($iArgs['name']):'req';
	$iVal = isset($iArgs['val'])?($iArgs['val']):NULL;
	$iSupp = isset($iArgs['supp'])?($iArgs['supp']):NULL;

	$objRows = $this->RowsActive($iSupp);
	if ($objRows->hasRows()) {
	    $out = "\n".'<select name="'.$iName.'">';
	    while ($objRows->NextRow()) {
		$id = $objRows->ID;
		$htSel = ($iVal == $id)?' selected':'';
		$out .= "\n".'<option value='.$id.$htSel.'>'.$objRows->Name().'</option>';
	    }
	    $out .= "\n".'</select>';
	    return $out;
	} else {
	    return NULL;
	}
    }

    // -- ADMIN INTERFACE -- //
    // ++ COMPLEX CALCULATIONS ++ //

    /*-----
      RETURNS: A list of all items needed, calculated from:
	* ADD items needed to fill open orders -- VbzAdminOrders::ItemsNeeded()
	* ADD items needed to fill stock minimums -- VbzAdminStkItems::Needed();
	* SUBTRACT open items in active restock requests
    */
    public function ItemsNeeded() {
	//$db = $this->Engine();

	// get items needed to fill customer orders:
	$arForOrd = $this->OrderTable()->ItemsNeeded();
	// get items needed to replenish stock:
	$arForStk = $this->StockItemTable()->Needed();
	// get items already in restock requests
	$arReqOrd = $this->RequestItemTable()->ListExpected();

	// build final list by combining the above lists:
	$arItemsOrd = array_keys($arForOrd);
	$arItemsStk = array_keys($arForStk);
	$arItemsReq = array_keys($arReqOrd);
	$arUnion = array_merge($arItemsOrd,$arItemsStk,$arItemsReq);
	// get stock info on all items listed
	$arItems = $this->StockBinTable()->Info_forItems($arUnion);


	foreach($arUnion as $idx => $id) {
	    $arItems[$id]['ord-req'] = nz($arForOrd[$id]['qty-ord']);
	    $arItems[$id]['ord-shp'] = nz($arForOrd[$id]['qty-shp']);
	    $arItems[$id]['ord-kld'] = nz($arForOrd[$id]['qty-kld']);
	    $arItems[$id]['ord-na'] = nz($arForOrd[$id]['qty-na']);
	    $arItems[$id]['oldest'] = nz($arForOrd[$id]['oldest']);
	    $arItems[$id]['newest'] = nz($arForOrd[$id]['newest']);
	    $qtyOrdNeed = nz($arForOrd[$id]['qty-need']);
	    $arItems[$id]['ord-need'] = $qtyOrdNeed;

	    $arItems[$id]['stk-min'] = nz($arForStk[$id]['min']);
	    //$arItems[$id]['stk-got'] = nz($arForStk[$id]['got']);
	    $arItems[$id]['stk-got'] = nz($arItems[$id]['for-ship']);
	    //$qtyStkNeed = nz($arForStk[$id]['need']);
	    $qtyStkNeed = $arItems[$id]['stk-min'] - $arItems[$id]['stk-got'];
	    $arItems[$id]['stk-need'] = $qtyStkNeed;

	    $arItems[$id]['req-ord'] = nz($arReqOrd[$id]['ord']);
	    $arItems[$id]['req-rcd'] = nz($arReqOrd[$id]['rcd']);
	    $qtyReqExp = nz($arReqOrd[$id]['exp']);
	    $arItems[$id]['req-exp'] = $qtyReqExp;

	    $arItems[$id]['need'] = $qtyOrdNeed + $qtyStkNeed - $qtyReqExp;
	}
	return $arItems;
    }

    /*----
      ACTION: Does counting, sorting, and general rearranging of needed-items data
      INPUT:
	iItems = item array in format produced by ItemsNeeded()
	iSupp = supplier we're filtering for (NULL = use all)
      RETURNS: array
	['supp']
	  [supplier catkey]
	    ['obj'] = supplier object
	    ['count'] = # of items needed to order from that supplier
	['item']
	  [item cat #]
	    ['obj'] = item object
	    ['data'] = item data from iItems
    */
    protected function FigureItemsNeeded_fromArray(array $iItems,$iSupp) {
	$objItems = $this->LCatItemTable();
	$arNeed = $iItems;
	$out = NULL;
	foreach ($arNeed as $item => $arData) {
	    if ($arData['need'] + $arData['req-exp'] > 0) {
		$objItem = $objItems->GetItem($item);
		$txtCatNum = $objItem->CatNum();
		$objSupp = $objItem->SupplierRecord();
		if (is_object($objSupp)) {
		    $idSupp = $objSupp->KeyValue();
		    $strCatKey = $objSupp->CatKey();
		    if (!isset($arSupps[$strCatKey])) {
			$arSupps[$strCatKey] = $objSupp;
			$arSuppSort[] = $strCatKey;
		    }
		    if (empty($iSupp)) {
			$doUse = TRUE;
		    } else {
			$doUse = ($idSupp == $iSupp);
		    }

		    if ($doUse) {
			$arItem[$txtCatNum]['obj'] = $objItem;
			$arItem[$txtCatNum]['data'] = $arData;
			//$arSort[] = $txtCatNum;
			$objSuppReq = $objSupp;
		    }
		    $arCount[$idSupp] = nz($arCount[$idSupp])+1;
		} else {
		    // This should never happen. Handle better later, if it does.
		    echo '<br><b>VbzAdmin ERROR</b>: No Supplier object for item ID='.$objItem->AdminLink();
		    $objTitle = $objItem->Title();
		}
	    }
	}
	sort($arSuppSort,SORT_STRING);
	foreach ($arSuppSort as $key) {
	    $objSupp = $arSupps[$key];
	    $arSupp[$key] = array(
	      'obj' => $objSupp,
	      'count' => $arCount[$objSupp->ID]
	      );
	}
	if (isset($arItem)) {
	    ksort($arItem,SORT_STRING);
	    $arOut['item'] = $arItem;
	} else {
	    $arOut['item'] = array();
	}
	$arOut['text'] = $out;
	$arOut['supp'] = $arSupp;
	return $arOut;
    }

    // -- COMPLEX CALCULATIONS -- //
}
