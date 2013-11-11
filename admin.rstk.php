<?php
/*
  FILE: admin.rstk.php -- restock functionality for VbzCart
  HISTORY:
    2010-10-17 Extracted restock classes from SpecialVbzAdmin.php
*/
/*
if (defined('LIBMGR')) {
    clsLibMgr::Add('time',		KFP_LIB.'/time.php',__FILE__,__LINE__);
    clsLibMgr::Load('time'		,__FILE__,__LINE__);
}
*/
/*
 RESTOCK MANAGEMENT
 CLASSES:
  * restock requests:
    clsRstkReq(s) - basic data functions, UI-agnostic
    clsAdminRstkReq(s) - administration functions specific to wiki UI
    * restock request line-items:
    clsRstkReqItem(s)
*/
class clsRstkReqs extends clsTable {
    //const TableName='rstk_req';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsRstkReq');
	  $this->Name('rstk_req');
	  $this->KeyName('ID');
	  $this->ActionKey('rstk-req');
    }
    public function RowsActive($iSupp=NULL) {
	$this->Name('qryRstks_active');
	//$sql = 'SELECT * FROM qryRstks_active ORDER BY WhenCreated DESC,WhenOrdered DESC';
	//$objRows = $this->objDB->DataSet($sql,$this->ClassSng());
	if (is_null($iSupp)) {
	    $sqlFilt = NULL;
	} else {
	    $sqlFilt = 'ID_Supplier='.$iSupp;
	}
	$objRows = $this->GetData($sqlFilt,NULL,'WhenCreated DESC,WhenOrdered DESC');
	assert('is_object($objRows->Table);');
	return $objRows;
    }
    public function RowsInactive() {
	$this->Name('qryRstks_inactive');
	//$sql = 'SELECT * FROM qryRstks_inactive ORDER BY WhenCreated DESC,WhenOrdered DESC';
	//$objRows = $this->objDB->DataSet($sql,$this->ClassSng());
	$objRows = $this->GetData(NULL,NULL,'WhenCreated DESC,WhenOrdered DESC');
	assert('is_object($objRows->Table);');
	return $objRows;
    }
}
class clsRstkReq extends clsDataSet {
    /*----
      RETURNS: String identifying the request in a user-friendly way
      NOTE: This can be enhanced by borrowing from Access code, which added some more info.
    */
    public function Name() {
	return $this->PurchOrdNum;
    }
}
class clsAdminRstkReqs extends clsRstkReqs {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminRstkReq');
    }
    /*-----
      RETURNS: A list of all items needed, calculated from:
	* ADD items needed to fill open orders -- VbzAdminOrders::ItemsNeeded()
	* ADD items needed to fill stock minimums -- VbzAdminStkItems::Needed();
	* SUBTRACT open items in active restock requests
    */
    public function ItemsNeeded() {
	// get items needed to fill customer orders:
	$arForOrd = $this->objDB->Orders()->ItemsNeeded();
	// get items needed to replenish stock:
	$arForStk = $this->objDB->StkItems()->Needed();
	// get items already in restock requests
	$arReqOrd = $this->objDB->RstkReqItems()->ListExpected();

	// build final list by combining the above lists:
	$arItemsOrd = array_keys($arForOrd);
	$arItemsStk = array_keys($arForStk);
	$arItemsReq = array_keys($arReqOrd);
	$arUnion = array_merge($arItemsOrd,$arItemsStk,$arItemsReq);
	// get stock info on all items listed
	$arItems = $this->objDB->Bins()->Info_forItems($arUnion);
	

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
    public function AdminItemsNeeded() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

// display appropriate header
	$idSupp = $vgPage->Arg('supp');
	if (empty($idSupp)) {
	    $out = '<h2>all items needed</h2>';
	} else {
	    $objSupp = $this->objDB->Suppliers()->GetItem($idSupp);
	    $out = '<h2>'.$objSupp->Name.' items needed</h2>';
// handle any special display functions
	    $out .= $this->AdminItemsSave($objSupp);
// 2013-11-05 I'm guessing that this only applies when we're filtering by supplier
	}

	$arNeed = $this->ItemsNeeded();

	$vgPage->UseHTML();


// now show the current data
	if (is_array($arNeed)) {
// SHUFFLE THE DATA AND LOOK UP NEEDED ITEM INFO
	    $arData = $this->FigureItemsNeeded_fromArray($arNeed,$idSupp);
	    // display menu showing needed quantities for each supplier
	    $out .= $this->RenderSupplierMenu($arData,$idSupp);
	    // display the needed-items list for the current supplier (or all of them)
	    $out .= $this->RenderItemsNeeded_fromArray($arData['item']);

	    if (isset($objSupp)) {
		// only allow adding items if they all belong to the same supplier
		$out .= '<hr>';
		// display option to add items to existing request
		$out .= '<input type=submit name="btnAddTo" value="Add Items"> to this request:';
		$out .= $this->RowsActive_dropdown(array('supp'=>$idSupp));
		$out .= '<br>- OR -<br>';
		$out .= '<input type=submit name="btnCreate" value="Create Request" />';
		$out .= ' with PO # <input type=text size=10 name=ponum /> from these items';
		

		// display option to create a new request
		$arLast = $objSupp->LastReq();
		$objByPO = $arLast['by purch ord'];
		$objByOD = $arLast['by ord date'];
		$out .= ' (';
		if ($objByPO->ID == $objByOD->ID) {
		    $out .= 'Last request: <b>'.$objByPO->PurchOrdNum.'</b>';
		} else {
		    $out .= 'Last requests: <b>'.$objByPO->PurchOrdNum.'</b> (by PO#), <b>'.$objByOD->PurchOrdNum.'</b> (by order date)';
		}
		$out .= ')';
	    }
/**/
	    $out .= '</form>';
	} else {
	    $out = 'There are no items needed for open orders.';
	}
	$wgOut->AddHtml($out);
    }
    /*----
      ACTION: Do any data-modification actions that need to be done before the data is displayed --
	typically in response to form input.
      INPUT: Supplier object, just for logging the event
      RETURNS: text to display (status messages)
    */
    protected function AdminItemsSave(clsSupplier $iSupp) {
	global $wgRequest;

	$doAddTo = $wgRequest->getBool('btnAddTo');
	$doCreate = $wgRequest->getBool('btnCreate');

	$out = NULL;

	if ($doCreate) {
	    $strPONum = $wgRequest->getText('ponum');
	    if (empty($strPONum)) {
		$out .= '<b>Please enter a purchase order number</b> for the new restock request.<br>';
	    } else {
		$sqlPONum = $this->objDB->SafeParam(strtoLower($strPONum));
		$objFnd = $this->objDB->RstkReqs()->GetData('LOWER(PurchOrdNum)="'.$sqlPONum.'"');
		$doCreate = ($objFnd->RowCount() == 0);
		if (!$doCreate) {
		    $out .= '"'.$strPONum.'" has already been used as a purchase order number. <b>Please choose a new one</b>.<br>';
		}
	    }
	}

	$arItemsNeed = $wgRequest->GetArray('item_need');	// items needed total
	$arItemsCOrd = $wgRequest->GetArray('item_sold');	// items pre-sold (ordered by customers)

	$doAddItems = $doCreate || $doAddTo;
	if ($doAddItems) {
	    // build quick text list of items for event log
	    $strItems = '';
	    foreach ($arItemsNeed as $id => $qty) {
		$strItems .= '/'.$id.'='.$qty;
	    }
	}

	if ($doCreate) {
	    $objSupp = $iSupp;

	    // log the attempt
	    $arEv = array(
	      'descr'	=> 'Creating restock request PO# '.$strPONum,
	      'where'	=> __METHOD__,
	      'code'	=> '+RREQ',
	      'params'	=> ':items:'.$strItems
	      );
	    $objSupp->StartEvent($arEv);
	    $out .= 'Creating restock request PO# <b>'.$strPONum.'</b> for these items:';
	    $objRstk = $this->objDB->RstkReqs()->Create($iSupp->ID,$strPONum,$arItemsNeed);
	    $idNew = $objRstk->ID;
	    $arEv = array('descrfin'	=> 'ID='.$idNew);
	    $objSupp->FinishEvent($arEv);
	} elseif ($doAddTo) {
	    $idRstk = $wgRequest->GetInt('req');
	    $objRstk = $this->objDB->RstkReqs()->GetItem($idRstk);
	    $strPONum = $objRstk->PurchOrdNum;
	    $htReq = $objRstk->AdminLink_friendly();
	    $out .= 'Adding these items to restock request PO# <b>'.$htReq.'</b>:';
	}
	if ($doAddItems) {	 // adding items to a restock which exists *now*
	    // log the attempt
	    $arEv = array(
	      'descr'	=> 'Adding items',
	      'where'	=> __METHOD__,
	      'code'	=> '+IT',
	      'params'	=> ':items:'.$strItems
	      );
	    $objRstk->StartEvent($arEv);
	    // add items to the restock:
	    $xts = new xtString();
	    foreach ($arItemsNeed as $id => $qtyNeed) {
		$objItem = $this->objDB->Items()->GetItem($id);
		$out .= ' '.$objItem->AdminLink($objItem->CatNum);
		$qtySold = $arItemsCOrd[$id];
		$objRstk->AddLine($objItem,$qtyNeed,$qtySold);
	    }
	}
	return $out;
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
	$objItems = $this->objDB->Items();
	$arNeed = $iItems;
	$out = NULL;
	foreach ($arNeed as $item => $arData) {
	    if ($arData['need'] + $arData['req-exp'] > 0) {
		$objItem = $objItems->GetItem($item);
		$txtCatNum = $objItem->CatNum;
		$objSupp = $objItem->Supplier();
		if (is_object($objSupp)) {
		    $idSupp = $objSupp->ID;
		    $strCatKey = $objSupp->CatKey;
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
    /*----
      ACTION: Render the supplier items-needed filter menu
      INPUT:
	* iData : array in format produced by igureItemsNeeded_fromArray()
	* iSupp: currently chosen supplier (if any) -- gets highlighted
      FUTURE: This could be simplified somewhat by using $vgPage->SelfLinkMenu()
    */
    protected function RenderSupplierMenu(array $iData,$iSupp) {
	global $vgOut;

	$arSupp = $iData['supp'];

	$out = '<b>Show</b>: ';
	$arLink = array(
	    'page'	=> 'rstk.need');
	$htLink = '['.$vgOut->SelfLink($arLink,'*all*','Show items for all suppliers').']';
	
	$out .= $vgOut->Selected(empty($iSupp),$htLink);

	foreach ($arSupp as $key => $arData) {	// key = supplier catkey
	    $obj = $arData['obj'];
	    $idSupp = $obj->ID;
	    $arLink['supp'] = $idSupp;
	    $htLink = $vgOut->SelfLink($arLink,$key,'show only '.$obj->Name.' items');
	    $htOut = ' ['.$htLink.'-'.$arData['count'].']';
	    $out .= $vgOut->Selected(($iSupp == $idSupp),$htOut);
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
	      .'<td '.$htClrC.' align=center>(<b>'.$arData['req-exp'].'</b>)</td>'

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
//    private $idEvent;

    /*----
      HISTORY:
	2010-10-28 added event logging using helper class
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    /*----
      HISTORY:
	2010-11-03 Created as call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
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
    public function IsActive() {
	return
	  (is_null($this->WhenKilled)) &&
	  (is_null($this->WhenClosed)) && 
	  (is_null($this->WhenOrphaned));
    }
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
    public function WhseObj() {
	$idWhse = $this->Row['ID_Warehouse'];
	$objWhse = $this->objDB->Suppliers()->GetItem($idWhse);
	return $objWhse;
    }
    // LINES methods
    public function LinesTbl() {
	return $this->objDB->RstkReqItems();
    }
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
      ACTION: Build the editing form
    */
    private function BuildEditForm($iNew) {
	global $vgOut;

	// create fields & controls
	if (is_null($this->objFlds)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);
/*
	    $vData = $iNew?NULL:$this->Row;
	    $objFlds = new clsFields($vData);
	    $objCtrls = new clsCtrls($objFlds);
*/

	    $objForm->AddField(new clsField('PurchOrdNum'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('SuppOrdNum'),	new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsField('SuppPONum'),	new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsField('CarrierDescr'),	new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsFieldNum('TotalCalcMerch'),	new clsCtrlHTML(array('size'=>'6')));
	    $objForm->AddField(new clsFieldNum('TotalEstFinal'),	new clsCtrlHTML(array('size'=>'6')));
	    $objForm->AddField(new clsField('PayMethod'),		new clsCtrlHTML(array('size'=>'20')));
	    $objForm->AddField(new clsFieldTime('WhenOrdered'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenConfirmed'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenKilled'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenClosed'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenOrphaned'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenExpectedOrig'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenExpectedFinal'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('isLocked'),		new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsField('Notes'),	new clsCtrlHTML_TextArea(array('height'=>3,'width'=>30)));

	    $this->objForm = $objForm;
	}
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
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Restock Request '.$this->PurchOrdNum.' (ID '.$this->ID.')');
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	$strAction = $vgPage->Arg('do');
	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');
	if ($doEdit || $doSave) {
	    $this->BuildEditForm(FALSE);
	    if ($doSave) {
		$this->AdminSave();
	    }
	}

	$doActBox = FALSE;
	$doAction = FALSE;
	$doStamp = FALSE;
	$doEnter = FALSE;
	$strType = $vgPage->Arg('type');
	switch ($strAction) {
	  case 'mark':
	    $doActBox = TRUE;
	    $doStamp = TRUE;	// show the controls for setting a stamp
	    break;
	  case 'enter':
	    $doActBox = TRUE;
	    $doEnter = TRUE;	// show the controls for entering stuff
	    break;
/*
	  case 'add':
	    $doEnter = TRUE;
	    break;
*/
	}
	if ($wgRequest->getBool('btnStamp')) {
	    $doActBox = TRUE;
	    $doAction = TRUE;
	    $doStamp = TRUE;	// receive timestamp data and use it
	}
	if ($doActBox) {
	    $htXtra = '';
	    if ($doStamp) {
		// we're doing a timestamp
		switch ($strType) {
		  case 'order':
		    $strDescr = 'Mark as Ordered';
		    $htXtra = '<br>Payment: <input type=text name=PayMeth size=20>';
		    $sqlField = 'WhenOrdered';
		    break;
		  case 'confirm':
		    $strDescr = 'Mark as Confirmed';
		    $htXtra = '<br>Supplier PO #: <input type=text name=SuppPONum size=20>';
		    $htXtra .= '<br>Supplier Ord #: <input type=text name=SuppOrdNum size=20>';
		    $sqlField = 'WhenConfirmed';
		    break;
		  case 'kill':
		    $strDescr = 'Mark as Cancelled';
		    $sqlField = 'WhenKilled';
		    break;
		  case 'close':
		    $strDescr = 'Close the Order';
		    $sqlField = 'WhenClosed';
		    break;
		  case 'orphan':
		    $strDescr = 'Mark as Orphaned';
		    $sqlField = 'WhenOrphaned';
		    break;
		}
	    }
	    if ($doEnter) {
		switch ($strType) {
		  case 'items':
		    $out .= $this->EnterItems();
		    break;
/*
		  case 'rcd':
		    $out .= $this->EnterRecd();
		    break;
*/
		}
	    }

	    if ($doStamp) {
		// we do stamps in a box
		$out .= '<table align=right width=30%><tr><td><h3>'.$strDescr.'</h3>';
	    }
	    if ($doAction) {
		if ($doStamp) {
		    $arUpd[$sqlField] = 'NOW()';
		    if ($wgRequest->GetCheck('SuppPONum')) {
			$arUpd['SuppPONum'] = $wgRequest->GetText('SuppPONum');
		    }
		    if ($wgRequest->GetCheck('SuppOrdNum')) {
			$arUpd['SuppOrdNum'] = GetText('SuppOrdNum');
		    }
		    $strNotes = $wgRequest->GetText('notes');
		    if (!empty($strNotes)) {
			$strDescr .= ': '.$strNotes;
		    }
		    $this->StartEvent(__METHOD__,'mark-'.$strType,$strDescr);
		    $this->Update($arUpd);
		    $this->Reload();
		    $this->FinishEvent();
		    $out .= 'Order marked';
		    if (!empty($strNotes)) {
			$out .= ', with notes: <b>'.$strNotes.'</b>';
		    }
		    $out .= ' (event ID #'.$this->idEvent.')';
		}
		if ($doEnter) {
		    $txtList = $wgRequest->GetText('items');
		    $xts = new xtString($txtList);
		    $xts->ReplaceSequence("\t ",' ');
		    $txtList = $xts->Value();
		    $arLines = explode("\n", $txtList);
		    $tblSupp = $this->SupplierObj();
		    foreach ($arLines as $line) {
			$strLine = rtrim($line,';#!');	// remove comments
			$strLine = trim($strLine);	// remove leading & trailing whitespace
			$arLine = explode(' ',$strLine);
			$strCat = $arLine[0];
			if (!empty($strCat)) {
			    $intQty = isset($arLine[1])?((int)$arLine[1]):1;
			    $objItem = $tblSupp->FindItems($arCatNums);

			    $out .= '<tr><td>';
			}
		    }
		}
	    } else {
		if ($doStamp) {
		    $arLink = $vgPage->Args(array('page','id'));
		    $urlForm = $vgPage->SelfURL($arLink,TRUE);
		    $out .= '<form method=post action="'.$urlForm.'">';
		    $out .= 'Log notes:<br>';
		    $out .= '<textarea rows=3 cols=30 name=notes>'.htmlspecialchars($txtNotes).'</textarea>';
		    $out .= $htXtra;
		    $out .= '<input type=hidden name=type value="'.$strType.'">';
		    $out .= '<br><input type=submit name=btnStamp value="Stamp It">';
		    $out .= '</form>';
		}
	    }
	    $out .= '</td></tr></table><hr>';
	}

	$htOurPO = htmlspecialchars($this->PurchOrdNum);
	$htSupPO = htmlspecialchars($this->SuppPONum);
	$htSupOr = htmlspecialchars($this->SuppOrdNum);
	$htCarrier = htmlspecialchars($this->CarrierDescr);
	$htCostMerch = DataCurr($this->TotalCalcMerch);	// calculated cost of merchandise
	$htCostFinal = DataCurr($this->TotalEstFinal);	// estimated final total (s/h usually uncertain)
	$htPayMethod = htmlspecialchars($this->PayMethod);
	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $htPath = $vgPage->SelfURL($arLink,TRUE);
	    $out .= "\n<form method=post action=\"$htPath\">";

	    $ctWhse = $this->WhseObj()->DropDown();

	    $frm = $this->objForm;

	    $ctrlOurPO		= $frm->Render('PurchOrdNum');
	    $ctrlSupPO		= $frm->Render('SuppPONum');
	    $ctrlSupOr		= $frm->Render('SuppOrdNum');
	    $ctrlCarrier	= $frm->Render('CarrierDescr');
	    $ctrlCostMerch	= $frm->Render('TotalCalcMerch');
	    $ctrlCostFinal	= $frm->Render('TotalEstFinal');
	    $ctrlPayMethod	= $frm->Render('PayMethod');

	    // timestamps
	    $ctrlWhenOrdered	= $frm->Render('WhenOrdered');
	    $ctrlWhenConfirmed	= $frm->Render('WhenConfirmed');
	    $ctrlWhenKilled	= $frm->Render('WhenKilled');
	    $ctrlWhenClosed	= $frm->Render('WhenClosed');
	    $ctrlWhenOrphaned	= $frm->Render('WhenOrphaned');

	    $ctrlNotes		= $frm->Render('Notes');
	} else {
	    $ctWhse = $this->WhseObj()->AdminLink_name();

	    $ctrlOurPO = $htOurPO;
	    $ctrlSupPO = $htSupPO;
	    $ctrlSupOr = $htSupOr;
	    $ctrlCarrier = $htCarrier;
	    $ctrlCostMerch = $htCostMerch;
	    $ctrlCostFinal = $htCostFinal;
	    $ctrlPayMethod = $htPayMethod;

	    // TIMESTAMPS
	    $arLink = array(
	      'page'	=> 'rstk-req',
	      'id'		=> $this->ID,
	      'do'		=> 'mark');
	    $isActive = $this->IsActive();

	    $arLink['type'] = 'order';
	    $txtVal = $this->WhenOrdered;
	    if ($isActive && is_null($txtVal)) {
		$ctrlWhenOrdered = ifEmpty($txtVal,'['.$vgOut->SelfLink($arLink,'stamp','mark as ordered').']');
	    } else {
		$ctrlWhenOrdered = $txtVal;
	    }

	    $arLink['type'] = 'confirm';
	    $txtVal = $this->WhenConfirmed;
	    if ($isActive && is_null($txtVal)) {
		$ctrlWhenConfirmed = ifEmpty($txtVal,'['.$vgOut->SelfLink($arLink,'stamp','mark order as confirmed').']');
	    } else {
		$ctrlWhenConfirmed = $txtVal;
	    }

	    $arLink['type'] = 'kill';
	    $txtVal = $this->WhenKilled;
	    if ($isActive && is_null($txtVal)) {
		$ctrlWhenKilled = ifEmpty($txtVal,'['.$vgOut->SelfLink($arLink,'kill','kill the order').']');
	    } else {
		$ctrlWhenKilled = $txtVal;
	    }

	    $arLink['type'] = 'close';
	    $txtVal = $this->WhenClosed;
	    if ($isActive && is_null($txtVal)) {
		$ctrlWhenClosed = ifEmpty($txtVal,'['.$vgOut->SelfLink($arLink,'close','close the order').']');
	    } else {
		$ctrlWhenClosed = $txtVal;
	    }

	    $arLink['type'] = 'orphan';
	    $txtVal = $this->WhenOrphaned;
	    if ($isActive && is_null($txtVal)) {
		$ctrlWhenOrphaned = ifEmpty($txtVal,'['.$vgOut->SelfLink($arLink,'stamp','mark this order as orphaned').']');
	    } else {
		$ctrlWhenOrphaned = $txtVal;
	    }

	    $ctrlNotes = $this->Notes;
	}
	$ftWhenCreated = $this->WhenCreated;
	assert('!is_null($this->Row["ID_Supplier"]);');
	$objSupp = $this->SuppObj();
	$ctSupp = $objSupp->AdminLink_name();
	$strSupp = $objSupp->Value('Name');
	$htSuppWiki = $vgOut->InternalLink($strSupp,'wiki',$strSupp.' info on wiki, if any');

	$out .= '<ul>';
	$out .= "<li> <b>Our PO#</b>: ".$ctrlOurPO;
	$out .= "<li> <b>Supp PO#</b>: ".$ctrlSupPO;
	$out .= "<li> <b>Supp Ord#</b>: ".$ctrlSupOr;

	$out .= "<li> <b>from Supplier</b>: $ctSupp".' ('.$htSuppWiki.')';
	$out .= "<li> <b>to Warehouse</b>: $ctWhse";
	$out .= "<li> <b>Carrier</b>: ".$ctrlCarrier;
	$out .= "<li> <b>Total Calc Merch</b>: ".$ctrlCostMerch;
	$out .= "<li> <b>Total Est Final</b>: ".$ctrlCostFinal;
	$out .= "<li> <b>Paid with</b>: ".$ctrlPayMethod;


	$out .= "<li> <b>Timestamps</b>:";
	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>Created</b>:</td><td>"	.$ftWhenCreated.'</tr>';
	$out .= "\n<tr><td align=right><b>Ordered</b>:</td><td>"	.$ctrlWhenOrdered.'</tr>';
	$out .= "\n<tr><td align=right><b>Confirmed</b>:</td><td>"	.$ctrlWhenConfirmed.'</tr>';
	$out .= "\n<tr><td align=right><b>Killed</b>:</td><td>"		.$ctrlWhenKilled.'</tr>';
	$out .= "\n<tr><td align=right><b>Closed</b>:</td><td>"		.$ctrlWhenClosed.'</tr>';
	$out .= "\n<tr><td align=right><b>Orphaned</b>:</td><td>"	.$ctrlWhenOrphaned.'</tr>';
	$out .= "\n</table>";
	$out .= "\n<li> <b>Notes</b>: ".$ctrlNotes;
	$out .= "\n</ul>";

	if ($doEdit) {
	    $out .= '<input type=submit value="Save" name=btnSave>';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out); $out = '';
	$out = '<h3>Shipments Received</h3>';
	$out .= $this->AdminRcd();
	$wgOut->AddHTML($out); $out = '';

	$out = '<h3>Items in Request</h3>';
	$out .= $this->AdminItems();
	$wgOut->AddHTML($out); $out = '';

	$out = $vgOut->Header('Event Log',3);
	$out .= $this->EventListing();
	$vgOut->AddText($out);
    }
    /*----
      ACTION: Renders administration of restock shipments received
    */
    protected function AdminRcd() {
	global $vgPage;

	$out = NULL;
	$tblRcd = $this->objDB->RstkRcds();
	$objRows = $tblRcd->GetData('ID_Restock='.$this->ID);
	if ($objRows->hasRows()) {
	    $out .= '<table>';
	    $out .= '<tr><th>ID</th>'
	      .'<th>Invc #</th>'
	      .'<th>via</th>'
	      .'<th>Tracking</th>'
	      .'<th></th>' // blank for Year
	      .'<th>Shipped</th>'
	      .'<th>Recd</th>'
	      .'<th>Debited</th>'
	      .'<th>$ Final</th>'
	      .'<th>Notes</th>'
	      .'</tr>';
	    $isOdd = FALSE;
	    while ($objRows->NextRow()) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$ftID = $objRows->AdminLink();
		$ftInvc = $objRows->SuppInvcNum;
		$ftShip = $objRows->CarrierDescr;
		$ftTrack = $objRows->TrackingCode;

		$arWhen = array($objRows->WhenShipped,$objRows->WhenReceived,$objRows->WhenDebited);
		$strWhen = FirstNonEmpty($arWhen);
		$dtWhen = new xtTime($strWhen);
		$yrWhen = $dtWhen->Year();
		$ftYear = $yrWhen;
		$ftWhenShip = Date_DefaultYear($objRows->WhenShipped,$yrWhen);
		$ftWhenRecd = Date_DefaultYear($objRows->WhenReceived,$yrWhen);
		$ftWhenPaid = Date_DefaultYear($objRows->WhenDebited,$yrWhen);

		$ftAmtFinal = $objRows->TotalInvFinal;
		$ftNotes = $objRows->Notes;

		$out .= '<tr style="'.$ftStyle.'">'
		  ."<td>$ftID</td>"
		  ."<td>$ftInvc</td>"
		  ."<td>$ftShip</td>"
		  ."<td>$ftTrack</td>"
		  ."<td>$ftYear</td>"
		  ."<td>$ftWhenShip</td>"
		  ."<td>$ftWhenRecd</td>"
		  ."<td>$ftWhenPaid</td>"
		  ."<td>$ftAmtFinal</td>"
		  ."<td>$ftNotes</td>"
		  .'</tr>';
	    }
	    $out .= '</table>';
	}
/*
	$arLink = $vgPage->Args(array('page','id'));
	$arLink['do'] = 'add';
	$arLink['type'] = 'rcd';
	$out .= ' [<a href="'.$vgPage->SelfURL($arLink,TRUE).'">add a received restock shipment</a>]';
*/
	$objNew = $tblRcd->SpawnItem();
	$arArgs = array('req'=>$this->ID);
	$out .= '[ '.$objNew->AdminLink('add a received restock shipment',NULL,$arArgs).' ]';

	return $out;
    }
    /*----
      ACTION: Renders table of items in restock request, with administrative controls
    */
    protected function AdminItems() {
	global $vgPage;

	$out = '';
	$objLine = $this->objDB->RstkReqItems()->GetData('ID_Restock='.$this->ID);
	if ($objLine->hasRows()) {
	    $out .= '<table>';
	    $out .= '<tr><td colspan=2></td><td colspan=5 align=center bgcolor=#eeeeee>Quantities</td></tr>';
	    $out .= '<tr>'
	      .'<th>Item</th>'
	      .'<th>Description</th>'
	      .'<th>need</th>'
	      .'<th>cust</th>'
	      .'<th>ord</th>'
	      .'<th>exp</th>'
	      .'<th>gone</th>'
	      .'<th>$ ea</th>'
	      .'<th>Notes</th>'
	      .'</tr>';

	    while ($objLine->NextRow()) {
		$idItem = $objLine->Value('ID_Item');
		$objItem = $this->objDB->Items()->GetItem($idItem);
		$key = $objItem->Value('CatNum');
		$arSort[$key]['item'] = $objItem;
		$arSort[$key]['line'] = $objLine->Values();
	    }
	    ksort($arSort);

	    $isOdd = FALSE;
	    foreach ($arSort as $key => $data) {
		$objItem = $data['item'];
		$objLine->Values($data['line']);
		assert('is_object($objLine);');

		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$ftItem = $objItem->AdminLink($objItem->CatNum);
		$ftDescr = $objLine->Value('Descr');
		$qtyNeed = $objLine->Value('QtyNeed');
		$qtyCust = $objLine->Value('QtyCust');
		$qtyOrd = $objLine->Value('QtyOrd');
		$qtyExp = $objLine->Value('QtyExp');
		$ftIsGone = $objLine->Value('isGone')?'&radic':'';
		$ftCostExp = DataCurr($objLine->Value('CostExpPer'));
		$strNotes = $objLine->Value('Notes');

		$out .= '<tr style="'.$ftStyle.'">'
		  ."<td>$ftItem</td>"
		  ."<td>$ftDescr</td>"
		  ."<td>$qtyNeed</td>"
		  ."<td>$qtyCust</td>"
		  ."<td>$qtyOrd</td>"
		  ."<td>$qtyExp</td>"
		  ."<td>$ftIsGone</td>"
		  ."<td align=right>$ftCostExp</td>"
		  ."<td>$strNotes</td>"
		  .'</tr>';
	    }
	    $out .= '</table>';
	} else {
	    $out .= 'No items known for this restock request.';
	}
	$arLink = $vgPage->Args(array('page','id'));
	$arLink['do'] = 'enter';
	$arLink['type'] = 'items';
	$out .= ' [<a href="'.$vgPage->SelfURL($arLink,TRUE).'">enter items</a>]';
	return $out;
    }
    public function AdminList($iHideCols=NULL) {
	global $vgPage;
	global $wgOut;

	assert('is_object($this->Table);');
	clsModule::LoadFunc('Date_DefaultYear');

	if ($this->hasRows()) {
	    $vgPage->UseHTML();
	    $out = '<table>';
	    $out .= '<tr>'
	      .'<th>ID</th>'
	      .'<th>Crea.</th>'
	      .'<th>Ord.</th>'
	      .'<th>sent to</th>'
	      .'<th>via</th>'
	      .'<th>our PO#</th>'
	      .'<th>their PO#</th>'
	      .'<th>Supp. Ord#</th>'
	      .'<th>$ Est</th>'
	      .'<th>Notes</th></tr>';
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    $yrLast = 0;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;
		$id = $row['ID'];
		$ftID = $this->AdminLink();

		$strOver = is_null($row['WhenOrdered'])?$row['WhenCreated']:$row['WhenOrdered'];
		$dtOver = strtotime($strOver);
		$yrOver	= date('Y',$dtOver);
		if ($yrLast != $yrOver) {
		    $yrLast = $yrOver;
		    $out .= '<tr style="background: #444466; color: #ffffff;"><td colspan=5><b>'.$yrOver.'</b></td></tr>';
		}
		$ftWhenCre = Date_DefaultYear($row['WhenCreated'],$yrOver);
		$ftWhenOrd = Date_DefaultYear($row['WhenOrdered'],$yrOver);

		$idWH = $row['ID_Warehouse'];
		$objDest = $this->objDB->Suppliers()->GetItem($idWH);
		$ftDest = $objDest->AdminLink($objDest->CatKey,'manage '.$objDest->Name);
		$ftShipVia = $row['CarrierDescr'];
		$ftOurPO = $row['PurchOrdNum'];
		$ftSuppPO = $row['SuppPONum'];
		$ftSuppOrd = $row['SuppOrdNum'];
		$ftCostEst = $row['TotalCalcMerch'];
		$ftNotes = $row['Notes'];

		$out .= '<tr style="'.$wtStyle.'">'
		  ."<td>$ftID</td>"
		  ."<td>$ftWhenCre</td>"
		  ."<td>$ftWhenOrd</td>"
		  ."<td>$ftDest</td>"
		  ."<td>$ftShipVia</td>"
		  ."<td>$ftOurPO</td>"
		  ."<td>$ftSuppPO</td>"
		  ."<td>$ftSuppOrd</td>"
		  ."<td>$ftCostEst</td>"
		  ."<td><small>$ftNotes</small></td></tr>";
	    }
	    $out .= '</table>';
	} else {
	    $out = 'No requests found.';
	}
	$wgOut->AddHTML($out);
	return NULL;
    }
}
// restock request line-items:
class clsRstkReqItems extends clsTable_indexed {
   const TableName='rstk_req_item';

   public function __construct($iDB) {
	$objIdxr = new clsIndexer_Table_multi_key($this);
	parent::__construct($iDB,$objIdxr);
	  $this->ClassSng('clsRstkReqItem');
	  $this->Name(self::TableName);
	  $objIdxr->KeyNames(array('ID_Restock','ID_Item'));
    }
}
class clsRstkReqItem extends clsRecs_indexed {
    public function ReqObj() {
	$objReqs = $this->objDB->RstkReqs();
	$objItem = $objReqs->GetItem('ID='.$this->ID_Item);	// ALERT: shouldn't this also filter for request ID?
	return $objItem;
    }
}
class clsAdminRstkReqItems extends clsRstkReqItems {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsAdminRstkReqItem');
    }
    /*-----
      RETURNS: A list of all active item requests
	* ADD items requested from all open restocks
	* SUBTRACT any items received for those restocks
    */
    public function ListExpected() {
	$sql = 'SELECT * FROM qryRstkItms_expected_byItem';
	$objRows = $this->objDB->DataSet($sql);
	if ($objRows->hasRows()) {
	    while ($objRows->NextRow()) {
		$idItem = (int)$objRows->ID_Item;
		$arOut[$idItem]['ord'] = $objRows->QtyExp;	// qty on order
		$arOut[$idItem]['rcd'] = $objRows->QtyRecd;	// qty received so far
		$arOut[$idItem]['exp'] = $objRows->QtyExp - $objRows->QtyRecd;	// qty still expected
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }
}
class clsAdminRstkReqItem extends clsRstkReqItem {
    public function AddItem() {
	die('This method not written yet!');
    }
    public function AdminList($iNoneTxt='No restock requests found') {
	global $wgOut;

	if ($this->hasRows()) {
	    // links to documentation
	    $wtNeed = '[['.kwp_DocTermPfx.'rstk/need|need]]';
	    $wtCust = '[['.kwp_DocTermPfx.'rstk/cust|cust]]';
	    $wtOrd = '[['.kwp_DocTermPfx.'rstk/ord|ord]]';
	    $wtExp = '[['.kwp_DocTermPfx.'rstk/exp|exp]]';

	    $out = "\n{|";
	    $out .= "\n|-\n! colspan=3 | -                    || colspan=4 | Quantities";
	    $out .= "\n|-\n! Req ID || our PO # || their PO # || $wtNeed || $wtCust || $wtOrd || $wtExp || Created || Ord || Kld || Shut || Orph || Notes";
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$arItem = $this->Values();
		$idReq = $this->Value('ID_Restock');
		$objReq = $this->Engine()->RstkReqs()->GetItem($idReq);
		$dtCreated = $objReq->Value('WhenCreated');
		$dtSumm = is_null($dtCreated)?($objReq->Value('WhenOrdered')):$dtCreated;
		$key = $dtSumm.'.'.$this->Value('ID_Item').'.'.$idReq;
		$arSort[$key]['item'] = $arItem;
		$arSort[$key]['req'] = $objReq->Values();
	    }
	    arsort($arSort);
	    $objItem = $this->Engine()->Items()->SpawnItem();
	    $objReq = $this->Engine()->RstkReqs()->SpawnItem();
	    foreach ($arSort as $key=>$data) {
		$arItem = $data['item'];
		$arReq = $data['req'];
		$objItem->Values($arItem);
		$objReq->Values($arReq);

		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$idReq = $objItem->Value('ID_Restock');

		$objReq = $this->objDB->RstkReqs()->GetItem($idReq);
		$ftID = $objReq->AdminLink();
		$txtOurPO = $objReq->Value('PurchOrdNum');
		$txtTheirPO = $objReq->Value('SuppOrdNum');
		$qtyNeed = $objItem->Value('QtyNeed');
		$qtyCust = $objItem->Value('QtyCust');
		$qtyOrd = $objItem->Value('QtyOrd');
		$qtyExp = $objItem->Value('QtyExp');

		$txtWhenCre = $objReq->Value('WhenCreated');
		$dtCre = strtotime($txtWhenCre);
		$yrCre = date('Y',$dtCre);

		clsModule::LoadFunc('Date_DefaultYear');

		$txtWhenOrd = $objReq->Value('WhenOrdered');
		$ftWhenOrd = Date_DefaultYear($txtWhenOrd,$yrCre);

		$txtWhenKld = $objReq->Value('WhenKilled');
		$ftWhenKld = Date_DefaultYear($txtWhenKld,$yrCre);

		$txtWhenClo = $objReq->Value('WhenClosed');
		$ftWhenClo = Date_DefaultYear($txtWhenClo,$yrCre);

		$txtWhenOrph = $objReq->Value('WhenOrphaned');
		$ftWhenOrph = Date_DefaultYear($txtWhenOrph,$yrCre);

		$txtNotes = $objItem->Value('Notes');

		$out .= "\n|- style=\"$wtStyle\"".
		    "\n| ".$ftID.
		    ' || '.$txtOurPO.
		    ' || '.$txtTheirPO.
		    ' || align=center | '.$qtyNeed.
		    ' || align=center | '.$qtyCust.
		    ' || align=center | '.$qtyOrd.
		    ' || align=center | '.$qtyExp.
		    ' || '.$txtWhenCre.
		    ' || '.$ftWhenOrd.
		    ' || '.$ftWhenKld.
		    ' || '.$ftWhenClo.
		    ' || '.$ftWhenOrph.
		    ' || '.$txtNotes;
	    }
	    $out .= "\n|}";
	} else {
	    $out = $iNoneTxt;
	}
	$wgOut->AddWikiText($out,TRUE);
    }
}
class clsRstkRcds extends clsTable {
    const TableName='rstk_rcd';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsRstkRcd');
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ActionKey('rstk-rcd');
    }
    public function LinesTbl() {
	return $this->objDB->RstkRcdLines();
    }
}
class clsRstkRcd extends clsDataSet {
    /*----
      HISTORY:
	2010-11-26 boilerplate event logging
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    /*----
      HISTORY:
	2010-11-24 created
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      HISTORY:
	2010-12-03 Created for receiving process
    */
    public function AdminLink_name() {
	return $this->AdminLink($this->Name());
    }
    /*----
      RETURNS: a "name" for the restock shipment, which currently consists of
	the supplier's catkey plus invoice number
    */
    public function Name() {
	$out = $this->SuppObj()->CatKey.'-'.$this->Row['SuppInvcNum'];
	return $out;
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    // FIELDS
    /*----
      RETURNS: object for the line in this received restock which has InvcLineNo=iLine
    */
    public function LineObj($iLine) {
	$sqlFilt = '(ID_RstkRcd='.$this->ID.') AND (InvcLineNo='.$iLine.')';
	$obj = $this->LinesTbl()->GetData($sqlFilt);
	$obj->NextRow();
	return $obj;
    }
    public function LinesTbl() {
	return $this->objDB->RstkRcdLines();
    }
    public function ReqObj() {
	static $objReq;

	$doGet = TRUE;
	$idReq = $this->Row['ID_Restock'];
	if (is_object($objReq)) {
	    if ($objReq->ID = $idReq) {
		$doGet = FALSE;
	    }
	}
	if ($doGet) {
	    $tblReqs = $this->objDB->RstkReqs();
	    $objReq = $tblReqs->GetItem($idReq);
	}
	return $objReq;
    }
    public function SuppObj() {
	$objReq = $this->ReqObj();
	if (is_object($objReq)) {
	    return $objReq->SuppObj();
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Deactivate all line-items for this restock shipment
      USAGE: Do this before refreshing from parsed invoice text.
    */
    public function ClearLines() {
	$arUpd = array('isActive'=>'FALSE');
	$tblLines = $this->LinesTbl();
	$tblLines->Update($arUpd,'ID_RstkRcd='.$this->ID);
    }
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$isNew = $this->IsNew();
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');
	$idReq = $vgPage->Arg('req');
	$doParse = $vgPage->Arg('parse');
	$doParseInvc = ($doParse == 'invoice');

	$vgPage->UseHTML();

	if ($isNew) {
	    $strTitle = 'New Shipment Received';
	    if ($idReq != 0) {
		$this->Value('ID_Restock',$idReq);
		$objReq = $this->objDB->RstkReqs()->GetItem($idReq);
		$strTitle .= ' from PO# '.$objReq->PurchOrdNum;
	    }
	} else {
	    $strTitle = 'Restock Received inv# '.$this->SuppInvcNum.' (ID '.$this->ID.')';
	}

	// save edits before showing data
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	$vgOut->AddText($out); $out = '';

	$objReq = $this->ReqObj();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    // read-only fields
	    $ctReq = $objReq->AdminLink_friendly().$frm->Render('ID_Restock');
	    // editable fields
	    $ctSuppInvc = $frm->Render('SuppInvcNum');
	    $ctCarrier = $frm->Render('CarrierDescr');
	    $ctInvcText = $frm->Render('InvcEntry');
	    $ctTracking = $frm->Render('TrackingCode');
	    $ctWhenShip = $frm->Render('WhenShipped');
	    $ctWhenRecd = $frm->Render('WhenReceived');
	    $ctWhenDebt = $frm->Render('WhenDebited');
	    $ctCalcMerch = $frm->Render('TotalCalcMerch');
	    $ctEstFinal = $frm->Render('TotalEstFinal');
	    $ctInvMerch = $frm->Render('TotalInvMerch');
	    $ctInvShip = $frm->Render('TotalInvShip');
	    $ctInvAdj = $frm->Render('TotalInvAdj');
	    $ctInvFinal = $frm->Render('TotalInvFinal');
	    $ctInvCond = $frm->Render('InvcCondition');
	    $ctPayMeth = $frm->Render('PayMethod');
	    $ctNotes = $frm->Render('Notes');
	} else {
	    $ctReq = $objReq->AdminLink_friendly();
	    $ctSuppInvc = htmlspecialchars($this->Row['SuppInvcNum']);
	    $ctCarrier = htmlspecialchars($this->Row['CarrierDescr']);

	    // invoice text block
	    $ctInvcText = '<pre>'.htmlspecialchars(trim($this->Row['InvcEntry'])).'</pre>';
	    if (!empty($this->Row['InvcEntry'])) {	// this will probably have to count rows -- must be >1
		if ($doParseInvc) {
		    $arStat = $this->ParseInvoice();
		    $ctInvcText .= $arStat['show'];
		    $cntNoFnd = $arStat['cnt.nofnd'];
		    if ($cntNoFnd > 0) {
			$strParse = 'Try again to parse items from text';
		    } else {
			$strParse = NULL;
		    }
		} else {
		    $strParse = 'parse items from this text';
		}
		if (is_null($strParse)) {
		    $ctInvcText .= '<hr>';
		} else {
		    $arLink = $vgPage->Args(array('page','id'));
		    $arLink['parse'] = 'invoice';
		    $urlLink = $vgPage->SelfURL($arLink,TRUE);
		    $ctInvcText .= ' [<a href="'.$urlLink.'">'.$strParse.'</a>]<hr>';
		}
	    }
	    // To avoid confusion, we only allow parsing of the saved text.
	    // Otherwise it would have to be a button = more programming.

	    $ctTracking = htmlspecialchars($this->Row['TrackingCode']);
	    $ctWhenShip = $this->Row['WhenShipped'];
	    $ctWhenRecd = $this->Row['WhenReceived'];
	    $ctWhenDebt = $this->Row['WhenDebited'];
	    $ctCalcMerch = $this->Row['TotalCalcMerch'];
	    $ctEstFinal = $this->Row['TotalEstFinal'];
	    $ctInvMerch = $this->Row['TotalInvMerch'];
	    $ctInvShip = $this->Row['TotalInvShip'];
	    $ctInvAdj = $this->Row['TotalInvAdj'];
	    $ctInvFinal = $this->Row['TotalInvFinal'];
	    $ctInvCond = $this->Row['InvcCondition'];
	    $ctPayMeth = htmlspecialchars($this->Row['PayMethod']);
	    $ctNotes = htmlspecialchars($this->Row['Notes']);
	}
	// non-editable fields:
	$ctID = $this->AdminLink();

	if ($this->IsNew()) {
	    $ftWikiInvcNum = '';
	} else {
//	    $txtInvcNum = $this->Row['SuppInvcNum'];
	    //$objReq = $this->ReqObj();
//	    $txtWikiInvcNum = $this->SuppObj()->CatKey.'-'.$txtInvcNum;
	    $txtWikiInvcNum = $this->Name();
	    $mwoCatg = Category::newFromName('invoices/'.$txtWikiInvcNum);
	    $mwoTitle = $mwoCatg->getTitle();
	    $ftWikiInvcNum = ' - ';
	    if ($mwoTitle === FALSE) {
		$ftWikiInvcNum .= $txtWikiInvcNum.': no wiki pages';
	    } else {
		$cntFiles = $mwoCatg->getFileCount();
		$txtFiles = $cntFiles.' file'.Pluralize($cntFiles);
		$wtCatg = '[[:'.$mwoTitle->getPrefixedText().'|'.$txtFiles.']]';
		$ftWikiInvcNum .= $wgOut->parseInLine($wtCatg);
	    }
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ctID</td></tr>";
	$out .= "\n<tr><td align=right><b>Request</b></td><td>$ctReq</td></tr>";
	$out .= "\n<tr><td align=right><b>Supplier invc #</b>:</td><td>$ctSuppInvc$ftWikiInvcNum</td></tr>";
	$out .= "\n<tr><td align=right><b>carrier</b>:</td><td>$ctCarrier</td></tr>";
	$out .= "\n<tr><td align=right><b>tracking code</b>:</td><td>$ctTracking</td></tr>";
	$out .= "\n<tr><td colspan=3><hr><b><big>timestamps</big></b></td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>shipped</b>:</td><td>$ctWhenShip</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>received</b>:</td><td>$ctWhenRecd</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>debited</b>:</td><td>$ctWhenDebt</td></tr>";
	$out .= "\n<tr><td colspan=3><hr><b><big>$ totals</big></b></td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>calculated merch</b>: $</td><td>$ctCalcMerch</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>estimated final</b>: $</td><td>$ctEstFinal</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice merch</b>: $</td><td>$ctInvMerch</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice shipping</b>: $</td><td>$ctInvShip</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice adjust</b>: $</td><td>$ctInvAdj</td></tr>";
	$out .= "\n<tr><td></td><td align=right><b>invoice final</b>: $</td><td>$ctInvFinal</td></tr>";
	$out .= "\n<tr><td align=right><b>invoice condition</b>:</td><td>$ctInvCond</td></tr>";
	$out .= "\n</table>";
	if ($doParseInvc) {
	    $out .= '<hr>';	// bracket the invoice entry area to show that something's happening there
	}
	$out .= "\n<b>invoice entry</b> (first line is format): $ctInvcText";
	$out .= "\n<b>other notes</b>: $ctNotes";

	if ($doEdit) {
	    $out .= '<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out); $out = '';

	if (!$isNew) {
	    $objSection = new clsWikiSection($objPage,'Items Received',NULL,3);
	    //$objSection->ToggleAdd('stock','move received items into stock','move');
	    $out = $objSection->Generate();
	    $out .= $this->AdminItems();
	    $wgOut->AddHTML($out); $out = '';

	    $out = $vgOut->Header('Event Log',3);
	    $out .= $this->EventListing();
	    $vgOut->AddText($out);
	}
	return NULL;
    }
    /*----
      HISTORY:
	2010-11-25 adapted from VbzAdminTitle
    */
    protected function BuildEditForm() {
	global $vgPage,$vgOut;

	if (is_null($this->objForm)) {
	    // must be set before using $vgOut
	    $vgPage->UseHTML();
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    // We will eventually have this saved with the supplier data.
	    // For now, using the LB format to start with.
	    $txtInvcFmt = '\InvcQtyOrd\InvcQtySent\InvcCatNo\InvcDescr\CostInvPer\CostInvTot';

	    $objForm->AddField(new clsField('ID_Restock'),$objCtrl = 	new clsCtrlHTML_Fixed());
	      if (isset($this->Row['ID_Restock'])) {
		  $objReq = $this->ReqObj();
		  $objCtrl->Field()->Value($objReq->ID);
		  $objCtrl->Text_Show($objReq->AdminLink_friendly());
	      }
	    $objForm->AddField(new clsField('SuppInvcNum'),		new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsField('CarrierDescr'),		new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsField('InvcEntry',$txtInvcFmt),	new clsCtrlHTML_TextArea(array('rows'=>20)));
	    $objForm->AddField(new clsField('InvcTextFmt'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('TrackingCode'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsFieldTime('WhenShipped'),		new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsFieldTime('WhenReceived'),	new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsFieldTime('WhenDebited'),		new clsCtrlHTML(array('size'=>16)));
	    $objForm->AddField(new clsFieldNum('TotalCalcMerch'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalEstFinal'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvMerch'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvShip'),		new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvAdj'),		new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('TotalInvFinal'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('InvcCondition'),$objCtrl = new clsCtrlHTML_DropDown());
	      $arDrop = array(
		0 => 'absent (no paperwork found)',
		1 => 'partial (one or more pages missing/illegible)',
		2 => 'complete (all pages located and legible)');
	      $objCtrl->Data_Rows($arDrop);
	      $objCtrl->Text_Choose('unknown / not yet entered');
	    $objForm->AddField(new clsField('PayMethod'),		new clsCtrlHTML(array('size'=>10)));
	    $objForm->AddField(new clsField('Notes'),			new clsCtrlHTML_TextArea(array('height'=>3)));

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the package
    */
    public function AdminSave() {
	global $vgOut,$wgRequest;

	$out = $this->objForm->Save($wgRequest->GetText('EvNotes'));
	$vgOut->AddText($out);
    }
    /*----
      ACTION: Renders table of items in restock request, with administrative controls
    */
    protected function AdminItems() {
	global $wgRequest;
	global $vgPage,$vgArg;

	$out = '';

	if ($wgRequest->GetBool('btnMove')) {
	    $out .= $this->AdminItems_handle_input();
	}

	$objRows = $this->Table->LinesTbl()->GetData('ID_RstkRcd='.$this->ID);
	$arLink = $vgPage->Args(array('page','id'));
	$urlForm = $vgPage->SelfURL($arLink,TRUE);
	$out .= '<form action="'.$urlForm.'" method=POST>';
	if ($objRows->hasRows()) {
	    $out .= '<table>';
	    $out .= '<tr>'
	      .'<td colspan=3></td>'	// ID, line, item
	      .'<td colspan=6 align=center bgcolor=#eeeeee>Invoice</td>'	// scat#, qty ord, qty sent, descr, per, tot
	      .'<td colspan=5 align=center bgcolor=#eeeeee>Actual</td>'		// cost act tot, cost act bal, act qty recd, qty filed
	      .'</tr>';
	    $out .= '<tr>'
	      .'<td colspan=4></td>'	// ID, line, item, scat#
	      .'<td colspan=2 align=center bgcolor=#eeeeee>Qtys</td>'	// inv qty ord, inv qty sent
	      .'<td colspan=1></td>'	// descr
	      .'<td colspan=2 align=center bgcolor=#eeeeee>Cost</td>'	// per, tot
	      .'<td colspan=3 align=center bgcolor=#eeeeee>Qtys</td>'	// act qty recd, qty filed
	      .'<td colspan=2 align=center bgcolor=#eeeeee>Cost</td>'	// act tot, act bal
	      .'</tr>';
	    $out .= '<tr>'
	      .'<th>Line</th>'
	      .'<th>ID</th>'
	      .'<th>Item</th>'
	      .'<th>SCat#</th>'
	      .'<th>ord</th>'
	      .'<th>sent</th>'
	      .'<th>Description</th>'
	      .'<th>$ ea</th>'
	      .'<th>$ line</th>'
	      .'<th>recd</th>'
	      .'<th>to move</th>'
	      .'<th>moved</th>'
	      .'<th>$ line</th>'
	      .'<th>$ bal</th>'
	      .'<th>Notes</th>'
	      .'</tr>';

	    while ($objRows->NextRow()) {
		$key = $objRows->Row['InvcLineNo'];
		$arSort[$key] = $objRows->RowCopy();
	    }
	    ksort($arSort);

	    $isOdd = FALSE;
	    $cntInputs = 0;
	    foreach ($arSort as $key => $obj) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$ftID = $obj->AdminLink();
		$objItem = $obj->ItemObj();
		$ftItem = $objItem->AdminLink($objItem->CatNum);
		$idxLine = $obj->Row['InvcLineNo'];
		$txtSCat = $obj->Row['InvcCatNo'];
		$qtyInvOrd = $obj->Row['InvcQtyOrd'];
		$qtyInvSent = $obj->Row['InvcQtySent'];
		$txtDescr = $obj->Row['InvcDescr'];
		$costInvPer = $obj->Row['CostInvPer'];
		$costInvTot = $obj->Row['CostInvTot'];
		$costActTot = $obj->Row['CostActTot'];
		$costActBal = $obj->Row['CostActBal'];

		$qtyRecd = $obj->Row['QtyRecd'];
		$qtyFiled = $obj->Row['QtyFiled'];
		$qtyLeft = $qtyRecd - $qtyFiled;

		$htQtyFiled = $qtyFiled;

		if (is_null($qtyRecd)) {
		    $htQtyRecd = '<input name="QtyRecd['.$idxLine.']" size=1 value="'.$qtyRecd.'">';
		    $cntInputs++;
		} else {
		    $htQtyRecd = $qtyRecd;
		}

		if (($qtyLeft > 0) || (is_null($qtyRecd))) {
		    $htQtyToMove = '<input name="QtyToMove['.$idxLine.']" size=1>';
		    $cntInputs++;
		} else {
		    $htQtyToMove = 'n/a';
		}

		$strNotes = $obj->Row['Notes'];

		$out .= '<tr style="'.$ftStyle.'">'
		  ."<td>$idxLine.</td>"
		  ."<td>$ftID</td>"
		  ."<td>$ftItem</td>"
		  ."<td>$txtSCat</td>"
		  ."<td align=center>$qtyInvOrd</td>"
		  ."<td align=center>$qtyInvSent</td>"
		  ."<td>$txtDescr</td>"
		  ."<td align=right>$costInvPer</td>"
		  ."<td align=right>$costInvTot</td>"
		  ."<td align=center>$htQtyRecd</td>"
		  ."<td align=center>$htQtyToMove</td>"
		  ."<td align=center>$htQtyFiled</td>"
		  ."<td align=right>$costActTot</td>"
		  ."<td align=right>$costActBal</td>"
		  ."<td>$strNotes</td>"
		  .'</tr>';
	    }
	    $out .= '</table>';
	    if ($cntInputs > 0) {
		$out .= '<input type=submit name=btnMove value="Update and/or Move"> Destination for move: ';
		$out .= $this->objDB->Bins()->DropDown_active();
	    }
	    $out .= '</form>';
	} else {
	    $out .= 'No items entered for this restock shipment.';
	}
/*
2010-11-27 We're probably not going to use this. The parsable invoice text lets us add new items as needed,
  plus allows for documentation (in comments) of what happened.
  	$arLink = $vgPage->Args(array('page','id'));
	$arLink['do'] = 'enter';
	$arLink['type'] = 'items';
	$out .= ' [<a href="'.$vgPage->SelfURL($arLink,TRUE).'">enter items</a>]';
*/
	return $out;
    }
    /*----
      ACTION: handles input from the user on the AdminItems() form
	* update received quantities
	* move items into selected stock bin
      HISTORY:
	2010-12-01 Created
    */
    protected function AdminItems_handle_input() {
	global $wgRequest;

	$out = NULL;

	$arRecd = $wgRequest->GetArray('QtyRecd');
	$arMove = $wgRequest->GetArray('QtyToMove');
	$idBin = $wgRequest->GetInt('bin');

	$cntRecd = count($arRecd);
	$cntMove = count($arMove);
	$txtEvent = 'Processing '
	  .$cntRecd.' item'.Pluralize($cntRecd).' to receive and '
	  .$cntMove.' item'.Pluralize($cntMove).' to move...';

	if ($cntRecd + $cntMove > 0) {
	    $arEv = array(
	      'descr'	=> $txtEvent,
	      'code'	=> 'ITM',
	      'where'	=> __METHOD__
	      );
	    $this->StartEvent($arEv);

	    $tblLines = $this->Table->LinesTbl();

	    // update received quantities
	    $cntRecd = 0;
	    foreach ($arRecd as $num => $qty) {
		$objLine = $this->LineObj($num);
		if ($objLine->HasRows()) {
		    if ($qty != $objLine->Value('QtyRecd')) {
			$arChg = array(
			  'QtyRecd'	=> $qty
			  );
			$objLine->Update($arChg);
			$cntRecd++;
		    }
		} else {
		    $out = 'Data Error: no rows found for line #'.$num.'.<br> - SQL: '.$objLine->sqlFilt;
		}
	    }

	    // move items into stock
	    $cntMove = 0;
	    foreach ($arMove as $num => $qty) {
		$objLine = $this->LineObj($num);
		if ($qty > 0) {
		    $out .= $objLine->Move_toBin($idBin,$qty);
		    $cntMove++;
		}
	    }
	  
	    $txtEv = '<br> - Done: '
	      .$cntRecd.' item'.Pluralize($cntRecd).' received and '
	      .$cntMove.' item'.Pluralize($cntMove).' moved.';
	    $arEv = array(
	      'descrfin'	=> $txtEv
	      );
	    $this->FinishEvent($arEv);
	    $out .= $txtEv;
	} else {
	    $out = 'No input to process.';
	}
	return $out;
    }
    /*----
      NOTES:
	Theoretically there should be no need to handle lost items gracefully, because any items on the invoice
	  should already be in the catalog because they were in the request... but in the real world,
	  (a) sometimes items ship that were not ordered, (b) typos happen, (c) stuff gets ordered without going
	  through VbzCart first.
      RETURNS: array
	array[show] = results to display
	array[cnt.nofnd] = number of items not found in the catalog data
    */
    protected function ParseInvoice() {
	$out = '';
	$txtItems = $this->Row['InvcEntry'];
	$xts = new xtString($txtItems);
	$arInLines = $xts->ParseTextLines(array('line'=>'arrx','blanks'=>"\t\n"));
	if (is_array($arInLines)) {
	    $cntLine = 0;
	    $cntFound = 0;	// count of items not found
	    $cntLost = 0;	// count of items not found
	    $txtFound = '';	// list of items found, plaintext format
	    $htFound = '';	// list of items found, HTML format
	    $txtLost = '';	// list of items not found
	    foreach ($arInLines as $idx => $arInLine) {
		if ($cntLine == 0) {
		    $arFormat = $arInLine;
		} else {
		    $arChg = NULL;
		    $strSCat = NULL;
		    foreach ($arInLine as $col => $val) {
			if (isset($arFormat[$col])) {
			    $strCol = $arFormat[$col];
			    //$out .= '[COL='.$col.' VAL='.trim($val).' =>'.$strCol.']';
			    if (isset($arOut[$strCol])) {
				$val = $arOut[$strCol].' '.$val;
			    }
			    $arChg[$strCol] = SQLValue($val);
			    if ($strCol == 'InvcCatNo') {
				$strSCat = $val;
			    }
			}
		    }

		    // look up item record from scat#
		    if (is_null($strSCat)) {
			$out .= '<br>Error: No supplier catalog # given in line '.$idx;
			$cntLost++;
		    } else {
			$objSupp = $this->SuppObj();
			$objItem = $objSupp->GetItem_bySuppCatNum($strSCat,'VbzAdminItem');
			if (is_null($objItem)) {
			    $cntLost++;
			    $txtLost .= ' '.$strSCat;
			} else {
			    $arChg['ID_Item'] = $objItem->ID;
			    $arChgs[$cntLine] = $arChg;
			    $cntFound++;
			    $htFound .= ' '.$objItem->AdminLink_CatNum();
			    $txtFound .= ' '.$objItem->CatNum.'(ID='.$objItem->ID.')';
			}
		    }
		}
		$cntLine++;
	    }
	    if ($cntFound > 0) {
		$txtDescr = 'Processing '.$cntFound.' item'.Pluralize($cntFound).':';
		$out .= $txtDescr.$htFound;
		$txtFound = $txtDescr.$txtFound;
	    }
	    if ($cntLost > 0) {
		$out .= '<br>Could not find '.$cntLost.' item'.Pluralize($cntLost).':'.$txtLost;
	    }
	    $out .= '<br>';
	    if (($cntLost == 0) && ($cntFound > 0)) {
		// log the update and do it
		$arEv = array(
		  'descr'	=> SQLValue($txtFound),
		  'code'	=> '"ITB"',	// item bulk entry
		  'where'	=> __METHOD__
		  );
		$this->StartEvent($arEv);
		$tblLines = $this->Table->LinesTbl();
		$idRcd = $this->Row['ID'];
		// disable all existing lines first, to avoid leftovers
		$this->ClearLines();
		$txtErr = $this->objDB->getError();
		if (!empty($txtErr)) {
		    $out .= '<br>Error deactivating lines: '.$txtErr;
		    $this->objDB->ClearError();
		}
		$cntNew = $cntOld = $cntErr = 0;	// reset counters
		foreach ($arChgs as $idx => $arChg) {
		    $dsLine = $tblLines->GetData('(ID_RstkRcd='.$idRcd.') AND (InvcLineNo='.$idx.')');
		    $arChg['isActive'] = 'TRUE';
		    if ($dsLine->HasRows()) {
			$dsLine->NextRow();
			$dsLine->Update($arChg);
			$cntOld++;
		    } else {
			$arChg['ID_RstkRcd'] = $idRcd;
			$arChg['InvcLineNo'] = $idx;
			$tblLines->Insert($arChg);
			$cntNew++;
		    }
		    $txtErr = $this->objDB->getError();
		    if (!empty($txtErr)) {
			$out .= '<br>Error in line '.$idx.': '.$txtErr;
			$out .= '<br> - SQL: '.$tblLines->sql;
			$this->objDB->ClearError();
			$cntErr++;
		    }
		}

		$txtEvent = 
		  $cntNew.' line'.Pluralize($cntNew).' added, '
		  .$cntOld.' line'.Pluralize($cntOld).' updated.';
		if ($cntErr > 0) {
		    $txtErrEv = $cntErr.' error'.Pluralize($cntErr).' - last: '.$txtErr;
		    $txtEvent = $txtErrEv.' '.$txtEvent;
		    $isErr = TRUE;
		} else {
		    $isErr = FALSE;
		}
		$arEv = array(
		  'error'	=> $isErr,
		  'descrfin'	=> $txtEvent
		  );
		$this->FinishEvent($arEv);
		$out .= '<br>'.$txtEvent;
	    }
	} else {
	    $out = 'No data lines found.';
	}
	$arOut['show'] = $out;
	$arOut['cnt.nofnd'] = $cntLost;
	return $arOut;
    }
}
class clsRstkRcdLines extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsRstkRcdLine');
	  $this->Name('rstk_rcd_line');
	  $this->KeyName('ID');
	  $this->ActionKey('rstk-rcd-line');
    }
}
class clsRstkRcdLine extends clsDataSet {
    /*----
      HISTORY:
	2010-11-28 Created from boilerplate
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      HISTORY:
	2010-11-28 Created for viewing data
    */
    public function ItemObj() {
	$idItem = $this->Row['ID_Item'];
	$objItem = $this->objDB->Items()->GetItem($idItem);
	return $objItem;
    }
    /*----
      INPUT:
	iBin: ID of destination bin
	iQty: quantity to move
      HOW: lets VbzAdminStkItems do all the work
      HISTORY:
	2010-12-01 Created for RstkRcd's items-admin form
    */
    public function Move_toBin($iBin,$iQty) {
	assert('!empty($iQty);');
	return $this->objDB->StkItems()->Add_fromRestock($iBin,$iQty,$this);
    }
    /*----
      ACTION: files the given quantity by:
	* incrementing QtyFiled
	* upping QtyRecd if QtyFiled is greater (later: make user confirm this?)
      DOES NOT: create an event -- doesn't really have enough information for this to be useful.
      HISTORY:
	2010-12-02 Created for Move_toBin() -> VbzAdminStkItems::Add_fromRestock()
      USAGE: for larger processes to use -- does not log event or make sure quantities end up anywhere
    */
    public function DoFile_Qty($iQty) {
	$qtyRecd = $this->Value('QtyRecd');
	$qtyFiled = $this->Value('QtyFiled');

  	$qtyFiledNow = $qtyFiled + $iQty;
	$arChg = array(
	  'QtyFiled'	=> $qtyFiledNow
	  );

	if ($qtyFiledNow > $qtyRecd) {
	    $qtyRecdNow = $qtyFiledNow;
	    $arChg['QtyRecd'] = $qtyRecdNow;
	}

	return $this->Update($arChg);
    }
}