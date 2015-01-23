<?php
/*
  FILE: admin.ord.php -- customer order administration for VbzCart
  HISTORY:
    2010-10-16 Extracted order management classes from SpecialVbzAdmin.php
*/

// Transaction Types
define('KI_ORD_TXTYPE_ITEM_SOLD',11);	// total cost of items sold
define('KI_ORD_TXTYPE_PERITM_SH',1);	// per-item shipping charge total
define('KI_ORD_TXTYPE_PERPKG_SH',2);	// per-package shipping charge
define('KI_ORD_TXTYPE_PAID_CC',6);	// payment: credit card
/*
clsLibMgr::Add('data-script',		KFP_LIB.'/data-script.php',__FILE__,__LINE__);
clsLibMgr::Load('data-script'		,__FILE__,__LINE__);

clsLibMgr::Add('vbz.ord',	KFP_LIB_VBZ.'/orders.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsOrders', 'vbz.ord');
*/
/* ========================== *\
- CUSTOMER ORDER classes
\* ========================== */
class VbzAdminOrders extends clsOrders {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrder');	// override parent
	  //$this->ActionKey('order');		// this is set to 'ord' in clsOrders
    }
    public function AdminPage() {
 	global $wgOut;
	global $vgPage;

	$vgPage->UseHTML();

	$objPage = new clsWikiFormatter($vgPage);

	$objSection = new clsWikiSection_std_page($objPage,'Orders',3);
	//$objSection->ArgsToKeep(array('show','page','id'));
	$objSection->PageKeys(array('page','id'));
	//$objSection->ToggleAdd('edit','edit the list of groups','edit.ctg');
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array('show.pulled'=>TRUE),'pulled'));
	  $objLink->Popup('show pulled orders');
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array('show.shut'=>TRUE),'shut'));
	  $objLink->Popup('show orders which have been closed');
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array('show.open'=>TRUE),'open'));
	  $objLink->Popup('show orders which have not been completed');
	$out = $objSection->Render();

	$wgOut->addHTML($out);	$out = '';

	$doShow = FALSE;
//	$optShow = $vgPage->Arg('show');
	if ($vgPage->Arg('show.open')) {
	    $arFilt[] = '(ID_Pull IS NULL) AND (WhenClosed IS NULL)';
	    $doShow = TRUE;
	    $doCalc = TRUE;	// do additional calculations to get order's status
	}
	if ($vgPage->Arg('show.shut')) {
	    $arFilt[] = '(WhenClosed IS NOT NULL)';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	if ($vgPage->Arg('show.pulled')) {
	    $arFilt[] = '(ID_Pull IS NOT NULL)';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	if ($doShow) {
	    $sqlFilt = Array_toFilter($arFilt);
	    $objRows = $this->GetData($sqlFilt,NULL,'SortPfx, Number DESC');
	    $cntRows = $objRows->RowCount();
	    $out = 'Showing '.$cntRows.' order'.Pluralize($cntRows).' ('.$sqlFilt.'):';
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	    $out = $objRows->Listing("No records found.",$doCalc);
	} else {
	    $out = 'No filter selected; not displaying any orders.';
	}
	$wgOut->addWikiText($out,TRUE);	$out = '';
    }
    /*-----
      RETURNS: A list of all items needed to fill open orders.
    */
    public function ItemsNeeded() {
	$sqlFilt = '(ID_Pull IS NULL) AND (WhenClosed IS NULL)';
	$objOrd = $this->GetData($sqlFilt,NULL);	// all open orders, one at a time
	$arNeed = NULL;

	while ($objOrd->NextRow()) {
	    $arItems = $objOrd->ItemsNeeded();
	    if (is_array($arItems)) {
		foreach($arItems as $idItem=>$arQtys) {
		    $qtyNeed = $arQtys['qty-ord'] - nz($arQtys['qty-shp']) - nz($arQtys['qty-kld']) - nz($arQtys['qty-na']);
		    if ($qtyNeed > 0) {
			$arNeed[$idItem]['qty-need'] = ((int)nz($arNeed[$idItem])) + $qtyNeed;

			$dtWhen = $objOrd->WhenStarted;
			$dtComp = nz($arNeed[$idItem]['earliest']);
			if (($dtWhen < $dtComp) || is_null($dtComp)) {
			    $arNeed[$idItem]['oldest'] = $dtWhen;
			}
			if ($dtWhen > nz($arNeed[$idItem]['latest'])) {
			    $arNeed[$idItem]['newest'] = $dtWhen;
			}

			$dtWhen = $objOrd->WhenNeeded;
			if (!is_null($dtWhen)) {
			    if ($dtWhen < $arNeed[$idItem]['need-by']) {
				$arNeed[$idItem]['need-by'] = $dtWhen;
			    }
			}
		    }
		}
	    }
	}
	return $arNeed;
    }
}
class VbzAdminOrder extends clsOrder {
    private $objPull;
    private $objCacheTrx;
    private $arTblArgs;
    private $arBalTrx;

    protected function InitVars() {
	parent::InitVars();
	$this->objCacheTrx = new clsObjectCache();
    }

    // === BOILERPLATE: admin link stuff

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name($iPopup=NULL,array $iarArgs=NULL) {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $txt = $this->AdminName();
	    return $this->AdminLink($txt,$iPopup,$iarArgs);
	}
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-12-20 copied from VbzAdminOrderTrxact to VbzAdminOrder
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
    public function AdminName() {
	return $this->Value('Number');
    }

    /* === BOILERPLATE logging functions
      HISTORY:
	2011-09-18 added - copied from VbzAdminOrderItem
    */
    public function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    //----

// dependent data //
    /*-----
      RETURNS: Dataset of lines for this order, sorted by Seq
    */
    public function LinesData($iRefresh=FALSE) {
	if ($iRefresh || !isset($this->objRows)) {
	    $objTbl = $this->objDB->OrdItems();
	    $this->objRows = $objTbl->GetData('ID_Order='.$this->ID,NULL,'Seq');
	}
	return $this->objRows;
    }
    public function HasLines() {
	$objLines = $this->LinesData();
	if (is_null($objLines)) {
	    return FALSE;
	} else {
	    return $objLines->hasRows();
	}
    }
    /*----
      TODO: this is ugly; there are better ways.
	MySQL manual suggests that this should work:
	  SELECT * FROM tbl_name WHERE auto_col IS NULL
    */
    public function NextSeq() {
	if ($this->HasLines()) {
	    $objLines = $this->LinesData();
	    $intMax = 0;
	    while ($objLines->NextRow()) {
		if ($objLines->Seq > $intMax) {
		    $intMax = $objLines->Seq;
		}
	    }
	    return $intMax+1;
	} else {
	    return 1;
	}
    }
    public function NextPkgSeq() {
	$objRows = $this->Pkgs();
	$seq = NextSeq($objRows);
	return $seq;
    }
    public function LineForItem($iItem) {
	$objTbl = $this->objDB->OrdItems();
	$objRows = $objTbl->GetData('(ID_Order='.$this->ID.') AND (ID_Item='.$iItem.')');
	if ($objRows->FirstRow()) {
	    return $objRows->ID;
	} else {
	    return NULL;
	}
    }
    /*=====
      ** DATASETS **
    */
    /*-----
      RETURNS: Dataset of Packages for this order
      FUTURE: Should be named something like PkgsData()
    */
    public function Pkgs() {
	$objTbl = $this->objDB->Pkgs();
	$objRows = $objTbl->GetData('ID_Order='.$this->ID);
	return $objRows;
    }

    /*=====
      ** DATA TABLES **
    */
    /*----
      RETURNS: Data table for credit card charges
    */
    public function ChargeTable() {
	return $this->objDB->CustCharges();
    }
    /*----
      RETURNS: Data table for order charges
    */
    public function TrxactTable() {
	return $this->objDB->OrdTrxacts();
    }
    /*----
      RETURNS: Data table for credit cards
    */
    public function CardTable() {
	return $this->objDB->CustCards();
    }
    /*=====
      ** DATA OBJECTS **
    */
    public function CardObj() {
	return $this->CardTable()->GetItem($this->ID_ChargeCard);
    }
    /*=====
      ** MULTI-ROW LISTINGS **
    */
    public function Listing($iNoneDescr=NULL,$iCalcStats=FALSE) {
	global $vgPage;

	$objRow = $this;
	if ($objRow->hasRows()) {
	    clsModule::LoadFunc('DataCurr');
	    $vgPage->UseWiki();
	    $out = "{| class=sortable \n|-\n! ID || Number || Status || Total Amt || created || closed";
	    if ($iCalcStats) {
		$out .= ' || qty ord || qty ok || qty Xed || QTY OPEN';
	    }
	    $isOdd = TRUE;
	    while ($objRow->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $objRow->Row;

		$id	= $row['ID'];
		$ftID	= $objRow->AdminLink();
		$strNum	= $row['Number'];
		$idPull	= $row['ID_Pull'];
		$strPull = $objRow->PulledText();
		$mnyTotal	= $row['WebTotal_Final'];
		$dtCreate	= $row['WhenStarted'];
		$dtClosed	= $row['WhenClosed'];

		$wtNum = $strNum;
		$strTotal = DataCurr($mnyTotal);
		$strWhenCreated = DataDate($dtCreate);
		$strWhenClosed = DataDate($dtClosed);

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| '''$ftID''' || $wtNum || $strPull || align=right | $strTotal || $strWhenCreated || $strWhenClosed";

		if ($iCalcStats) {
		    $arSum = $this->ItemsNeeded_Summary();
		    if (is_array($arSum)) {
			$qtyOrd = $arSum['qty-ord'];
			$qtyOk = nz($arSum['qty-shp']);
			$qtyXed = nz($arSum['qty-kld']) + nz($arSum['qty-na']);
			$out .= " || align=center | $qtyOrd || align=center | $qtyOk || align=center | $qtyXed";
			$qtyOpen = $qtyOrd - $qtyOk - $qtyXed;
			if ($qtyOpen) {
			    $out .= " || align=center | '''$qtyOpen'''";
			}
		    }
		}
	    }
	    $out .= "\n|}\n";
	} else {
	    if (is_null($iNoneDescr)) {
		$out = 'No order records found.';
	    } else {
		$out = $iNoneDescr;
	    }
	}
	return $out;
    }
    /*-----
      RETURNS: array of items; each item's value is a sub-array containing:
	* the quantity ordered for that item, in 'qty-ord'
	* any other fields listed in $iFields are initialized to zero
      USAGE: The resulting array can then be used as input to packages->FigureTotals(),
	which will adjust the remaining fields to reflect packages sent and received.
    */
    function QtysOrdered() {
	$objTbl = $this->objDB->OrdItems();
	$objRows = $objTbl->GetData('ID_Order='.$this->ID);
	$arQtys = $objRows->QtyArray();
	return $arQtys;
    }
    /*-----
      RETURNS: Array of all items needed to completely fill open orders.
	This excludes any items which have been shipped, cancelled, N/A, etc.
	Array format is the same as packages->FigureTotals(), but with one added key;
	  array[item ID]['qty-ord']
      HISTORY:
	2011-03-24 added call to self::ItemStats_update_line()
    */
    public function ItemsNeeded() {
	// get the quantities requested
	$arQtyOrd = $this->QtysOrdered();
	if (is_array($arQtyOrd)) {
	    // subtract items handled by packages
	    $objPkgs = $this->Pkgs();
	    $arPkgSums = $objPkgs->FigureTotals();
	    foreach ($arQtyOrd as $idItem => $qtyOrd) {
		$arPkgSum = nzArray($arPkgSums,$idItem);
		$qtySum = $qtyOrd;
		if (is_array($arPkgSum)) {
		    $qtySum += nzArray($arPkgSum,'qty-ord',0);
		}
		$arPkgSums[$idItem]['qty-ord'] = $qtySum;
		self::ItemStats_update_line($arPkgSums[$idItem]);
	    }
	    return $arPkgSums;
	} else {
	    return NULL;
	}
    }
    /*-----
      RETURNS: array of returns from packages->FigureTotals()
	IF iItem IS NULL:
	  array[item ID]['qty-shp']
	  array[item ID]['qty-ext']
	  array[item ID]['qty-kld']
	  array[item ID]['qty-na']
	IF iItem is NOT NULL, then only array[iItem] is returned
      USAGE:
	* iItem is used by the function which displays order status for a given item
    */
    public function ItemStats($iItem=NULL) {
	$objPkgs = $this->Pkgs();
	$arPkgSums = $objPkgs->FigureTotals();
	if (is_null($iItem)) {
	    return $arPkgSums;
	} else {
	    $intItem = (int)$iItem;
	    return $arPkgSums[$intItem];
	}
    }
    /*----
      ACTION: Update the item stats based on a single order line or package line
      INPUT:
	iStats = stats for the item being referenced by the line
      OUTPUT: returns and modifies iStats with some additional keys:
	  iStats['qty-used'] is created or updated -- quantity of this item used by lines so far
	  iStats['qty-shp-line'] is created -- quantity to ship for current line
    */
    static function ItemStats_update_line(array &$iStat) {
	$qtyUsed	= (int)nzArray($iStat,'qty-used',0);
	$intQtyShp	= (int)nzArray($iStat,'qty-shp',0)-$qtyUsed;
	$iStat['qty-shp-line'] = $intQtyShp;
	$iStat['qty-used'] = $qtyUsed + $intQtyShp;
	return $iStat;
    }
    /*
      RETURNS: ItemsNeeded() summed across all items
	Format is the same as ItemsNeeded() but without the item dimension.
    */
    function ItemsNeeded_Summary() {
	$arSums = $this->ItemsNeeded();
	if (is_array($arSums)) {
	    foreach ($arSums as $item => $arData) {
		// sum each column in the lines for this package
		foreach ($arData as $name => $qty) {
		    nzAdd($arSum[$name],$qty);
		}
	    }
	    return $arSum;
	} else {
	    return NULL;
	}
    }
    /*----
     RETURNS: TRUE if order has any missing fields and has not been pulled
    */
    public function NeedSetup() {
	$idBuyer = $this->ID_NameBuyer;
	$idRecip = $this->ID_NameRecip;
	$idAddr = $this->ID_ContactAddrRecip;
	return (
	  is_null($this->ID_Pull) && (
	    empty($idBuyer) ||
	    empty($idRecip) ||
	    empty($idAddr)
	    )
	  );
    }
    /*----
      HISTORY:
	2010-12-24 Fixed Update() call
	  Also, sets ID_Pull to NULL if iPull is NULL
    */
    public function Pull($iPull=NULL) {
	if ($iPull != $this->ID_Pull) {
	    if (!is_null($iPull)) {
		$sqlVal = $iPull;
	    } else {
		$sqlVal = 'NULL';
	    }
	    $this->Update(array('ID_Pull'=>$sqlVal));
	}
	return $this->ID_Pull;
    }
    public function Pulled($iPull=NULL) {
	return !is_null($this->ID_Pull);
    }
    public function PullObj() {
	$objPull = $this->objDB->OrdPulls()->GetItem($this->ID_Pull);
	return $objPull;
    }
    public function PulledText() {
	if ($this->Pulled()) {
	    return $this->PullObj()->TypeName();
	} else {
	    return NULL;
	}
    }
    /*----
      RETURNS: customer object for buyer, or NULL if buyer is not set
      HISTORY:
	2011-11-06 now checks to make sure buyer is set
    */
    public function BuyerObj() {
	$idCust = $this->Value('ID_Buyer');
/*
	if (empty($idCust)) {
	    $idCust = $this->Value('ID_Recip');
	}
*/
	if (empty($idCust)) {
	    $rcCust = NULL;
	} else {
	    $rcCust = $this->Engine()->Custs($idCust);
	}

/* 2011-10-08 why did we do it this way before?
	$objName = $this->NameBuyerObj();
	$objCust = $this->objDB->Custs()->GetItem($objName->ID_Cust);
*/
	return $rcCust;
    }
    public function HasRecip() {
	$idCust = $this->Value('ID_Recip');
	return !empty($idCust);
    }
    public function RecipObj() {
	$idCust = $this->Value('ID_Recip');
	$rcCust = $this->Engine()->Custs($idCust);
	return $rcCust;
    }
    private function NameBuyerObj() {
	$objRow = $this->objDB->CustNames()->GetItem($this->ID_NameBuyer);
	return $objRow;
    }
    /*----
      HISTORY:
	2011-11-06 renamed NameBuyerAdminLink -> Buyer_Name_AdminLink
    */
    public function Buyer_Name_AdminLink() {
	$objRow = $this->NameBuyerObj();
	return $objRow->AdminLink_name($this->ID_Buyer);
    }
    /*----
      HISTORY:
	2011-11-06 written for legacy orders that don't have ID_Buyer set
    */
    public function Buyer_Obj_AdminLink($iNone='n/a') {
	$objRow = $this->BuyerObj();
	if (is_object($objRow)) {
	    return $objRow->AdminLink();
	} else {
	    return $iNone;
	}
    }
    /*----
      FUTURE: rename to Recip_Name_Obj
    */
    private function NameRecipObj() {
	$objRow = $this->objDB->CustNames()->GetItem($this->ID_NameRecip);
	return $objRow;
    }
    /*----
      HISTORY:
	2011-11-06 renamed NameRecipAdminLink -> Recip_Name_AdminLink
    */
    private function Recip_Name_AdminLink() {
	$objRow = $this->NameRecipObj();
	return $objRow->AdminLink_name($this->ID_Recip);
    }
    /*----
      HISTORY:
	2011-11-06 written for legacy orders that don't have ID_Recip set
    */
    public function Recip_Obj_AdminLink($iNone='n/a') {
	if ($this->HasRecip()) {
	    $objRow = $this->RecipObj();
	    return $objRow->AdminLink();
	} else {
	    return $iNone;
	}
    }
    /*----
      FUTURE: rename to Recip_Addr_Obj
    */
    private function AddrRecipObj() {
	$objRow = $this->objDB->CustAddrs()->GetItem($this->ID_ContactAddrRecip);
	return $objRow;
    }
    private function CCardStr() {
	$id = $this->ID_ChargeCard;
	if (empty($id)) {
	    return "<i>N/A</i>";	// works with wiki and html
	} else {
	    $objRow = $this->objDB->CustCards()->GetItem($id);
	    return $objRow->SafeString();
	}
    }
    private function CCardLink() {
	$id = $this->ID_ChargeCard;
	if (empty($id)) {
	    return "<i>N/A</i>";	// works with wiki and html
	} else {
	    $objRow = $this->objDB->CustCards()->GetItem($id);
	    $txtOut = $objRow->SafeString();
	    $ftOut = $objRow->AdminLink($txtOut);
	    return $ftOut;
	}
    }
    private function CCardChoose() {
	$idCard = $this->ID_ChargeCard;
	if (empty($idCard)) {
	    $idCard = NULL;
	}
	$objName = $this->NameBuyerObj();
	$idCust = $objName->ID_Cust;
	$out = $this->objDB->CustCards()->DropDown_forCust($idCust,$idCard);
	return $out;
    }
    /*----
      2011-09-18 created -- trying to tidy up order provisioning process
    */
    private function CartList() {

	$htOut = NULL;
	$arErrs = NULL;

	$idCart = $this->ID_Cart;
	if (is_null($idCart)) {
	    $arErrs[] = 'no default cart';
	} else {
	    $obj = $this->Cart();
	    $htOut .= ' [<b>'.$obj->SetupLinks().'</b>]';
	}
	$objCarts = $this->objDB->Carts()->GetData('ID_Order='.$this->ID);
	if ($objCarts->hasRows()) {
	    $arOthers = NULL;
	    $cntOthers = 0;
	    while ($objCarts->NextRow()) {
		$id = $objCarts->KeyValue();
		if ($id != $idCart) {
		    $htOut .= ' ['.$objCarts->SetupLinks().']';
		}
	    }
	} else {
	    $arErrs[] = 'no carts point here';
	}

	$arOut['html'] = $htOut;
	$arOut['errs'] = $arErrs;
	return $arOut;
    }
    /*----
      ACTION: Do part of the order setup
      PHASES:
	btnImport pressed?
	  NO: cart has been chosen; get cart's contact data and look for matches
	  YES: resolution of contact data has been chosen, and we should be able to import now
      FUTURE: Possibly most of the cart import code should be methods of the cart object, not the order object
      NOTES: I'm sure this makes some wrong assumptions about the conditions under which
	various bits of contact information might exist; for now, I've tried to make it
	flexible enough so it can be easily fixed later when the problems show up.

	We have a maximum of two people, (1) the buyer and (2) the recipient.
	If "ship to self" is checked, then we know these are the same people; otherwise we have to check
	both of them.

	What we are doing is checking each person's contact information for matches with known people.
	If there are any matches, we show those matches (along with the information about what matched)
	and allow the administrator to decide if this is the same person or not.
      FIELDS:
	The following fields in the order record ($this) need to be updated after the contact records are created/updated:
	  * ID_Cart <- $idCart
	  * PayType <- always credit card (for now), but I haven't worked out the data design for this, so NULL
	  * These items should be available from the script tree generated by $objTree->Save():
	    * ID_Buyer
	    * ID_Recip
	    * ID_NameBuyer
	    * ID_NameRecip
	    * ID_ContactAddrRecip
	    * ID_ChargeCard
      HISTORY:
	2011-07-28 Apparently at some point in the not-too-dim past, chunks of code were moved from here
	  and into function DoSetupCart_UseContact(), where they now reside happily with their families.
	  I am consequently removing their commented-out husks from this function.
    */
    private function DoSetupCart() {
	global $vgPage;
	global $wgRequest;

	//$acts = new Script_Script();

	$idCart = $vgPage->Arg('cart');
	$objCart = $this->Cart($idCart);
	$objData = $objCart->FieldRecords();

	$doImport = $wgRequest->GetBool('btnImport');

	if ($doImport) {
	    $out = $this->DoCart_Import($objData);
	} else {
	    // at this phase, we're just checking for matches to existing customer data
	    $out = 'Checking cart ID='.$idCart.'...';
	    $out .= $this->DoCart_Match($objData);
	}
	$out .= '<hr><small>generated by '.__FILE__.' line '.__LINE__.'</small>';
	return $out;
    }
    /*----
      ACTION: Either simulate or actually do the import, depending on whether user
	has checked the appropriate option (currently "make the changes")
    */
    protected function DoCart_Import(clsCartVars $objData) {
	throw new exception('DoCart_Import() needs to be rewritten without SQL scripting.');


	global $wgRequest;

	$strCust = $wgRequest->GetText('cust');
	$strShip = $wgRequest->GetText('ship');

	if ($strCust == 'new') {
	    $doNewCust = TRUE;
	    $idCust = NULL;
	} else {
	    $doNewCust = FALSE;
	    $idCust = (int)$strCust;
	}

	if ($strShip == 'new') {
	    $doNewShip = TRUE;
	    $idShip = NULL;
	} else {
	    $doNewShip = FALSE;
	    $idShip = (int)$strShip;
	}

	$out = '<h3>Import Script</h3>';

//	    $objTree = $objCart->GetDetailObjs();

	//$acts = new Script_Script();		// create root script
//	    $objTree->ScriptRoot($acts);		// set root script for tree to use
//echo $objTree->DumpHTML();
//	    $actImp = $objTree->Save();			// generate the import (save) script

	$actImp = $objData->Script_forImport($idCust,$idShip);

	// calculate order updates

	// - static stuff
	$arUpd = array('WhenPrepped' => 'NOW()');	// value with which to update order

	// - dynamic stuff
	$actUpd = new Script_Row_Update($arUpd,$this);
	$actShip = $actImp->Get_byName('person.ship',TRUE);
	if ($objData->IsShipToSelf()) {
	    $actCust = $actShip;
	} else {
	    $actCust = $actImp->Get_byName('person.cust',TRUE);

	}

	// -- ID_Buyer
	if ($doNewCust) {
	    // what we want here is the action which creates or updates the customer record
	    $actBuyer = $actCust->Get_byName('cont.make',TRUE);
	    $actImp->Add(new Script_SQL_Use_ID($actUpd,$actBuyer,'ID_Buyer'));
	} else {
	    $actUpd->Value('ID_Buyer',$idCust);
	}
	// -- ID_Recip
	if ($doNewShip) {
	    // TO BE WRITTEN - script is creating new customer, need to get the ID
	} else {
echo $actImp->Exec(FALSE); die();
	    $actRecip = $actShip->Get_byName('cont.make',TRUE);
	    $actUpd->Value('ID_Recip',$idShip);
	}

/*
AS OF 2011-11-30, ID_NameBuyer and ID_NameRecip are not being successfully added to the update
-- although they do work in the simulation.
*/

	// -- ID_NameBuyer
	$actName = $actCust->Get_byName('name.data',TRUE);
	$actImp->Add(new Script_SQL_Use_ID($actName,$actUpd,'ID_NameBuyer'));
	// -- ID_NameRecip
	$actName = $actShip->Get_byName('name.data',TRUE);
	$actImp->Add(new Script_SQL_Use_ID($actName,$actUpd,'ID_NameRecip'));
	// -- ID_ContactAddrRecip
	$actAddr = $actImp->Get_byName('cust.addr.do',TRUE);
	$actImp->Add(new Script_SQL_Use_ID($actAddr,$actUpd,'ID_ContactAddrRecip'));
	// -- ID_ChargeCard
	$actCard = $actImp->Get_byName('ccard.make',TRUE);
	$actImp->Add(new Script_SQL_Use_ID($actCard,$actUpd,'ID_ChargeCard'));

	$actImp->Add($actUpd);	// add order update script
/*

	$acts = new Script_Script();

	$actMe = new Script_Row_Update(array(),$this);
//	    $actMe->Value('WhenPrepped','NOW()');	// mark the order as "prepped"
	$acts->Add($objShip->DoResolve_Script($actMe,$doShipSelf),'resolve.main');

	if (!$doShipSelf) {
	    $acts->Add($objCust->DoResolve_Script($actMe,FALSE),'resolve.aux');
	}

	/*
	  Script: update the order to point to the new customer data records etc.
	*/
	//$actOrd = $acts->Get_byName('ord.upd',TRUE);
	//$actOrd->ReplaceWith($actMe);

	$doReally = $wgRequest->GetBool('chkReallyDo');
	$out .= 'MAKING CHANGES: '.NoYes($doReally);
	$out .= '<hr>'.$actImp->Exec($doReally).'<hr>';

	return $out;
    }
    /*----
      ACTION: Check given cart data for matches to existing customer records, and allow
	user to choose which existing customer records to use
    */
    protected function DoCart_Match(clsCartVars $objData) {
	$db = $this->Engine();
	$arFound = $objData->FindMatches($db);

	$tblCust = $db->Custs();

	$fListFnd = function(array $arFnd,$iLabel) use($tblCust) {
	    $out = NULL;
	    foreach($arFnd as $type => $list) {
		if (!is_null($out)) {
		    $out .= "\n<br>";
		}
		$hasMatch = is_array($list);
		$strDefault = $hasMatch?'':' checked';
		$out .= "<input$strDefault type=radio name=\"$iLabel\" value=new><b>new</b>";
		if ($hasMatch) {
		    $strMatch = 'match'.Pluralize(count($list),'','es');
		    $out .= "\n<br>$type $strMatch:";
		    foreach ($list as $key => $idCust) {
			$rcCust = $tblCust->GetItem($idCust);
			$id = $rcCust->KeyValue();
			$htID = $rcCust->AdminLink_name();
			$out .= "\n<br>&nbsp;&ndash;&nbsp;<input type=radio name=\"$iLabel\" value=$id>$htID";
		    }
		} else {
		    $out .= "\n<br><s>$type</s>";
		}
	    }
	    return $out;
	};

	$out = '<form method=post>';	// keep additional args in URL until done

	$out .= '<table border=1><tr><th>buyer</th><th>ship-to</th></tr><tr>'
	  .'<td valign=top>'.$fListFnd($arFound['cust'],'cust').'</td>'
	  .'<td valign=top>'.$fListFnd($arFound['ship'],'ship').'</td>'
	  .'</tr></table>';
	// WORKING HERE

/*
	if ($doShipSelf) {
	    $objShip->Descr('buyer/recipient');

	    // TO DO (probably): can CheckPersonMatches be rewritten as a method of $objCust/$objShip?

	    $out .= $vgPage->CheckPersonMatches($objShip,$objCart);
	} else {
//echo 'objCust tree'.$objCust->DumpHTML();
	    $out .= $vgPage->CheckPersonMatches($objCust,$objCart);
	    $out .= $vgPage->CheckPersonMatches($objShip,$objCart);
	}
*/
	$out .= '<br><input type=submit name=btnImport value="Import">';
	$out .= '<input type=checkbox name=chkReallyDo>Make the changes';
	$out .= '</form>';
/* maybe make this showable with a link
	$out .= '<br><h4>buyer tree</h4>'.$objCust->DumpHTML();
	$out .= '<br><h4>shipping tree</h4>'.$objShip->DumpHTML();
*/
	return $out;
    }
/* 2011-11-23 who calls this?
    function DoSetup_Person(clsPerson $iPerson) {
	global $wgRequest;

	$objPerson = $iPerson;

	$strName = $objPerson->FormName();
	$idChosen = $wgRequest->GetText($strName);

	$this->DoSetupCart_Resolve($idChosen);
    }
*/
    /*----
	This will probably replace DoSetupCart_UseContact and DoSetupCart_AllNew
    */
/* 2011-11-23 who calls this?
    function DoSetupCart_Resolve($idChosen) {
	$objDB = $this->objDB;
	$objCart = $this->Cart();
	$objCart->GetDetailObjs();
	$objCust = $objCart->WhoCust();
	$objShip = $objCart->WhoShip();
	$objOrd = $this;

	$actResolve = $objShip->DoResolve_SQL($objDB,$idChosen);

	// WRITING HERE
    }
*/
    /*----
      ACTION: Use the chosen contact record for this contact record
      HISTORY:
	2011-04-17 extracted from DoSetupCart_Match(); using objCart instead of objDB to get customer data
	2011-07-29 not understanding all the elaborate conditionals surrounding $doResolve; using $doImport
	  instead, so that code always gets called when [Import] button is pressed. If this causes problems,
	  *document* the circumstances when changing code.
	2011-09-21 This function is now *only* called when the Import button is pressed, so no need to check.
    */
/* 2011-11-23 who calls this?
    function DoSetupCart_UseContact($iShipSelf) {
	$objDB = $this->objDB;
	$objCart = $this->Cart();
	$objCart->GetDetailObjs();
	$objCust = $objCart->WhoCust();
	$objShip = $objCart->WhoShip();
	$objOrd = $this;

	$out = '';
	//$doShipSelf = $objCart->doShipSelf;
	$doShipSelf = $iShipSelf;

    // import choices have been made, so do the actual import
	$arRtn = $objShip->DoResolve($objDB);
	$strStat = $arRtn['stat'];
	$arUpd = $arRtn['upd-ord'];
	$arUpd['ID_Recip'] = $objShip->SubValue('id');
	$strOrdStat = $strStat;
	$out .= '<br>'.$strStat;
	$objCart->LogEvent('RES',$strStat);
	if ($doShipSelf) {
	    $arUpd['ID_Buyer'] = $objShip->SubValue('id');
	    if (!isset($arUpd['ID_NameRecip'])) {
		$arUpd['ID_NameRecip'] = $arUpd['ID_NameBuyer'];
	    }
	} else {
	    $arRtn = $objCust->DoResolve($objDB);
	    $strStat = $arRtn['stat'];
	    $arUpd = array_merge($arUpd,$arRtn['upd-ord']);
	    $arUpd['ID_Buyer'] = $objCust->SubValue('id');
	    $strOrdStat .= ' | '.$strStat;
	    $out .= '<br>'.$strStat;
	    $objCart->LogEvent('RES',$strStat);
	}
	$arUpd['ID_Cart'] = $objCart->ID;
	$strOrdStat .= ' | ID_Cart='.$objCart->ID;

	$objOrd->StartEvent_Simple('RES','Cart resolved: '.$strOrdStat,__METHOD__);
	$objOrd->Update($arUpd);
	$objOrd->FinishEvent();
	$objOrd->Reload();
    }
    public function DoSetupCart_AllNew(VbzAdminCart $iCart) {
// ** CUSTOMER/SHIPPING INFO
// create address objects, from which we can pull the needed fields:
	$tblCusts = $this->objDB->Custs();
	$this->objDB->GetDetailObjs();
	$isShiptoSelf = $this->objDB->custShipToSelf;	// TRUE = use shipping phone/email for buyer too
	$isShiptoCard = $this->objDB->custShipIsCard;	// TRUE = use shipping address for credit card too

	$objAddrShip = $tblCusts->Make_fromCartAddr($iCart->AddrShipObj());
	$objAddrCard = $tblCusts->Make_fromCartAddr($iCart->AddrCardObj());

	$xtStatus = new xtString();
	$xtStatus->AddSep('ship-addr='.$objAddrShip->ID);
	$xtStatus->AddSep('card-addr='.$objAddrCard->ID);

// get fields which might need to be copied, depending on "ship_is" flags:
	$idNameRecip = $objAddrShip->ID_Name;
	$idEmailRecip = $iCart->DataItem(KI_CART_RECIP_EMAIL);
	$idPhoneRecip = $iCart->DataItem(KI_CART_RECIP_PHONE);
	$xtStatus->AddSep('recip-email='.$idEmailRecip);
	$xtStatus->AddSep('recip-phone='.$idPhoneRecip);
	if ($isShiptoSelf) {
	    $idNameBuyer = $idNameRecip;
	    $idEmailBuyer = $idEmailRecip;
	    $idPhoneBuyer = $idPhoneRecip;
	} else {
	    $idNameBuyer = $objAddrCard->ID_Name;
	    $idEmailBuyer = $iCart->DataItem(KI_CART_BUYER_EMAIL);
	    $idPhoneBuyer = $iCart->DataItem(KI_CART_BUYER_PHONE);
	    $xtStatus->AddSep('buyer-email='.$idEmailBuyer);
	    $xtStatus->AddSep('buyer-phone='.$idPhoneBuyer);
	}
	//$this->LogEvent('NWC','Created all new contact records: '.$xtStatus);
	$out = 'All new contact records: '.$xtStatus;

	//$this->Update($arUpd);
	return $out;
    }
*/
    /*----
      ACTION: Handles previewing and manual sending of order confirmation email
      RETURNS: HTML to display necessary forms and results
    */
    public function AdminPage_email() {
	global $wgRequest;

	$doCustCopy = $wgRequest->GetBool('send-to-cust');
	$txtSubj = $wgRequest->GetText('subject');
	$txtMsgPre = $wgRequest->GetText('email-msg-pre');
	if ($wgRequest->GetBool('btnSend')) {
	// Actually sending the email
	    $txtDoCustCopy = $doCustCopy?'copy customer':'no customer copy';

	    $txtEmailBody = $wgRequest->GetText('email-body');
	    $txtEmailAddrSelf = $wgRequest->GetText('addr-self');
	    $txtEmailAddrCust = $wgRequest->GetText('addr-cust');
	    //$txtEmailSubject = $wgRequest->GetText('subject');

	    // send the email
	    $out = $this->EmailConfirm(TRUE, TRUE, $doCustCopy, $txtEmailAddrSelf, $txtEmailAddrCust, $txtSubj, $txtEmailBody);
	} else {
	    $out = $this->AdminPage_email_preview($doCustCopy,$txtSubj,$txtMsgPre);
	}

	return $out;
    }
    /*----
      RETURNS: HTML to display email preview/editing and sending functionality - does not actually send
      INPUT:
	$iSendCust: boolean indicating whether we are planning to send a copy to the customer
	  This method won't actually send an email, but will indicate this intention.
	$iSubj: subject header for the email
	$iMsgPre: message preamble -- text to be added to the top of the email, before the
	  generated content
      HISTORY:
	2011-09-18 extracted from AdminPage
    */
    public function AdminPage_email_preview($iSendCust,$iSubj,$iMsgPre) {
	$doCustCopy = $iSendCust;
	$txtSubj = $iSubj;
	$txtMsgPre = $iMsgPre;

	$out = NULL;

	$out .= '<form>';
	$hasEmail = $this->Cart()->ContCustObj()->HasEmail();
	if ($hasEmail) {
	    $htCustCopy = $doCustCopy?' checked':'';
	    $out .= '<input type=checkbox name=send-to-cust'.$htCustCopy.'>Send copy to customer<br>';
	} else {
	    $out .= '<input type=checkbox name=send-to-cust disabled>Can\'t send copy to customer (no known email)<br>';
	}
	$arVars = $this->TemplateVars();
	$arHdrs = $this->EmailParams($arVars,$txtSubj,$txtMsgPre);

	$txtSubj	= $arHdrs['subj'];
	$txtMsgPre	= $arHdrs['msg.pre'];
	$txtMsgBody 	= $arHdrs['msg.body'];
	$txtEmailAddr_Self	= $arHdrs['addr.self'];
	$txtEmailAddr_Cust	= $arHdrs['addr.cust'];
	$htSubj = htmlspecialchars($txtSubj);
	$htPre = htmlspecialchars($txtMsgPre);

	$out .= 'Subject: <input name=subject size=40 value="'.$htSubj.'"><br>';
	$out .= 'Message:<textarea name=email-msg-pre rows=10>'.$htPre.'</textarea>';
	$out .= '<input type=submit value="Format email text..." name=btnPrep>';
	$out .= '</form>';

	// Calculate text of email to send:
	$txtEmailBody = $txtMsgPre."\n".$this->RenderReceipt_Text();

	// put together the arguments array:
	$arArgs = array(
	  'to-self'	=> TRUE,		// if TRUE, send a copy to the store admin's address
	  'to-cust'	=> $doCustCopy,		// if TRUE, send a copy to the customer's address
	  'addr-self'	=> $txtEmailAddr_Self,	// email address for store admin
	  'addr-cust'	=> $txtEmailAddr_Cust,	// email address for customer
	  'subject'	=> $txtSubj,		// subject of the email
	  'message'	=> $txtEmailBody	// body of the email
	  );

	// generate the email, but don't send
	$out .= $this->EmailConfirm(FALSE, $arArgs, $this->Log());

	return $out;
    }
    /*----
      HISTORY:
	2011-03-23 adapted from VbzAdminItem to VbzAdminOrderItem

    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldNum('ID_Buyer'),		new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_Recip'),		new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_NameBuyer'),		new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_NameRecip'),		new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_ContactAddrRecip'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('WebTotal_Merch'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('WebTotal_Ship'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('WebTotal_Final'),	new clsCtrlHTML(array('size'=>6)));
/*
	    $objForm->AddField(new clsField('ID_Item'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsField('CatNum'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('Descr'),	new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsField('QtyOrd'),		new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsFieldNum('Price'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ShipPkg'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ShipItm'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('Notes'),	new clsCtrlHTML(array('size'=>40)));
*/
	    $this->objForm = $objForm;
	}
    }
    /*----
      ACTION: Displays the normal admin page
    */
    protected function AdminPage_basic($iDoEdit,clsWikiSection2 $iSection) {
	global $wgOut,$wgRequest;
	global $vgOut,$vgPage;

	$doEdit = $iDoEdit;
	$objSection = $iSection;
	$doSave = $wgRequest->getBool('btnSave');

	$out = NULL;

	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$arCart = $this->CartList();
	$htCart = $arCart['html'];

	if ($this->NeedSetup()) {
	    $txtStatus = 'not set up yet';
	} else {
	    if ($this->Pulled()) {
		$out .= 'This order has been pulled.';
		$txtStatus = 'pulled';
	    } else {
		$out .= 'This order is set up and ready to process.';
		$txtStatus = 'ready to process';
	    }
	}


	// show the order specs

	// - basic order data
/*
	$objCart = $this->Cart();
	$htCart = $objCart->AdminLink();
*/

	// EDITING HERE

	$objAddrRecip = $this->AddrRecipObj();
	$idAddr = $objAddrRecip->ID;
	$arLink = array('page'=>'addr','id'=>$idAddr);
	$ftLink = $vgOut->SelfLink($arLink,$idAddr);

	// check the address's "Full" field (later, this will be manually controlled)
	$strAddrShip = $objAddrRecip->AsString();
	if ($strAddrShip != $objAddrRecip->Full) {
	    $objAddrRecip->UpdateCalc();
	}

	$prcTotMerch = $this->Value('WebTotal_Merch');
	$prcTotShip = $this->Value('WebTotal_Ship');
	$prcTotFinal = $this->Value('WebTotal_Final');

	// do financial calculations first so we can mark up the given totals with status information
	// -- have to set up table arguments
	$this->arTblArgs = array(
	  'add'		=> $vgPage->Arg('add'),
	  'form'	=> $vgPage->Arg('form'),
	  'descr'	=> ' for this order',
	  'omit'	=> 'order'
	  );
	// -- actual financial calculations
	$rsTrx = $this->Data_Trx();
	$ftTrxacts = $this->Trxact_RenderTable();
	$arTrxBal = $this->arBalTrx;

	// - editable order data
	$ftBalBtns = NULL;
	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $htNameBuyer = $objForm->Render('ID_Buyer').' - name:'.$objForm->Render('ID_NameBuyer');
	    $htNameRecip = $objForm->Render('ID_Recip').' - name:'.$objForm->Render('ID_NameRecip');
	    $htAddrShip = $objForm->Render('ID_ContactAddrRecip');

	    $htAmtMerch	= $objForm->Render('WebTotal_Merch');
	    $htAmtShip =  $objForm->Render('WebTotal_Ship');
	    $htAmtTotal =  $objForm->Render('WebTotal_Final');

	    $htCard = $this->CCardChoose();
/*
	    $htAmtMerch = '<input type=text name=WebTotal_Merch width=6 value="'.$prcTotMerch.'">';
	    $htAmtShip = '<input type=text name=WebTotal_Ship width=6 value="'.$prcTotShip.'">';
	    $htAmtTotal = '<input type=text name=WebTotal_Final width=6 value="'.$prcTotFinal.'">';
*/
	} else {
	    $htNameBuyer = $this->Buyer_Name_AdminLink().' - cust ID '.$this->Buyer_Obj_AdminLink();
	    $htNameRecip = $this->Recip_Name_AdminLink().' - cust ID '.$this->Recip_Obj_AdminLink();
	    $htAddrShip = '('.$ftLink.') '.$objAddrRecip->AsSingleLine();
	    $htCard = $this->CCardLink();

	    $fxShowTot = function($prcCalc,$prcSaved,&$okCalc) {
		$intCalc = (int)(round($prcCalc * 100));
		$intSaved = (int)(round($prcSaved * 100));
		if ($intSaved == $intCalc) {
		    $htOut = $prcSaved.'</td><td><font color=green>ok</font>';
		} else {
		    if ($intSaved < $intCalc) {
			$htOut = '<font color=blue>'.$prcSaved.'</font></td><td> under by <b>'.($prcCalc-$prcSaved).'</b>';
		    } else {
			$htOut = '<font color=red>'.$prcSaved.'</font></td><td> over by <b>'.($prcSaved-$prcCalc).'</b>';
		    }
		    $okCalc = FALSE;	// calculations do not match saved balance
		}
		return $htOut;
	    };

/* 2011-03-31 this is wrong -- order totals reflect items ordered, not transactions
	    $htAmtMerch = $fxShowTot($arBalTrx['sale'],$prcTotMerch);
	    $htAmtShip = $fxShowTot($arBalTrx['ship'],$prcTotShip);
	    $htAmtTotal = $fxShowTot($arBalTrx['total'],$prcTotFinal);
*/
	    $rs = $this->LinesData();	// get order lines
	    if ($rs->hasRows()) {
		$arTotItm = $rs->FigureTotals();
	    } else {
		$arTotItm = NULL;
	    }

	    $prcCalcShItm = $arTotItm['sh-itm'];
	    $prcCalcShPkg = $arTotItm['sh-pkg'];
	    $prcCalcShip = $prcCalcShItm + $prcCalcShPkg;
	    $prcCalcSale = $arTotItm['cost-sell'];
	    $prcCalcTotal = $prcCalcShip + $prcCalcSale;

	    $okCalc = TRUE;	// assume balances match
	    $htAmtMerch = $fxShowTot($prcCalcSale,$prcTotMerch,$okCalc);
	    $htAmtShip = $fxShowTot($prcCalcShip,$prcTotShip,$okCalc);
	    $htAmtTotal = $fxShowTot($prcCalcTotal,$prcTotFinal,$okCalc);

	    if ($okCalc) {
		$ftBalBtns .= '<font color=gray>Totals are correct</font>';
		$arBalTrx = array(
		  'sale'	=> $prcCalcSale,
		  'ship'	=> $prcCalcShip,
		  'total'	=> $prcCalcTotal
		  );
	    } else {
		$ftRecalcLink = $this->AdminLink(
		  'recalculate',
		  'add up totals for items in order',
		  array('do'=>'recalc'));
		$ftBalBtns .= '['.$ftRecalcLink.']';
	    }
	}

	// handle actions
	$doAct = $vgPage->Arg('do');
	switch ($doAct) {
	  // 2011-09-24 $arBalTrx seems to reflect order totals, not current transaction balance
	  //case 'charge':	$ftRecalcStat = $this->DoChargeCard($arBalTrx);	    break;
	  case 'charge':	$ftRecalcStat = $this->DoChargeCard($arTrxBal);	    break;
	  case 'recalc':	$ftRecalcStat = $this->DoRecalcBal();	    break;
	  default: $ftRecalcStat = NULL;
	}

	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;
	}

	$htID = $this->AdminLink();

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>";
	$out .= "\n<tr><td align=right><b>Status</b>:</td><td>$txtStatus</td></tr>";
	$out .= "\n<tr><td align=right><b>Cart</b>:</td><td>$htCart</td></tr>";
	$out .= "\n<tr><td align=right><b>When Created</b>:</td><td>".$this->WhenStarted.'</td></tr>';
	if ($this->Pulled()) {
	    $out .= "\n<tr><td align=right><b>Pulled</b>:</td><td>".$this->PulledText().'</td></tr>';
	}
	$out .= "\n<tr><td align=right><b>When Started</b>:</td><td>".$this->WhenStarted.'</td></tr>';
	$out .= "\n<tr><td align=right><b>When Prepared</b>:</td><td>".$this->WhenPrepped.'</td></tr>';
	$out .= "\n<tr><td align=right><b>When Edited</b>:</td><td>".$this->WhenEdited.'</td></tr>';
	$out .= "\n<tr><td align=right><b>When Closed</b>:</td><td>".$this->WhenClosed.'</td></tr>';
	$out .= "\n<tr><td align=right><b>Buyer</b>:</td><td>".$htNameBuyer.'</td></tr>';
	$out .= "\n<tr><td align=right><b>Recipient</b>:</td><td>".$htNameRecip.'</td></tr>';
	$out .= "\n<tr><td align=right><b>Ship to</b>:</td><td>".$htAddrShip.'</td></tr>';
	$out .= "\n<tr><td align=right><b>Payment</b>:</td><td>".$htCard.'</td></tr>';
	$out .= "\n<tr><td align=center colspan=2><table>";
	$out .= "\n<tr><td align=right><b>Merchandise</b>: $</td><td align=right>$htAmtMerch</td></tr>";
	$out .= "\n<tr><td align=right><b>Shipping</b>: $</td><td align=right>$htAmtShip</td></tr>";
	$out .= "\n<tr><td align=right><b>Total</b>: $</td><td align=right>$htAmtTotal</td></tr>";
	if (!is_null($ftBalBtns)) {
	    $out .= "\n<tr><td align=center colspan=2>$ftBalBtns</td></tr>";
	}
	if (isset($ftRecalcStat)) {
	    $out .= "\n<tr><td align=left colspan=2>$ftRecalcStat</td></tr>";
	}
	$out .= "\n</table></td></tr>";
	$out .= "\n</table>";
	if ($doEdit) {
	    $out .= '<input type=submit name=btnSave value="Save">';
	    $out .= '<input type=reset value="Revert">';
	    $out .= '<input type=submit name=btnCancel value="Cancel">';
	    $out .= '</form>';
	}
	$wgOut->AddHTML($out); $out = '';

	$wgOut->addWikiText($this->Item_RenderTable(),TRUE);	// items in order
	$wgOut->addWikiText($this->Pkg_RenderTable(),TRUE);
	$wgOut->addHTML($ftTrxacts);
	$wgOut->addWikiText($this->Msg_RenderTable(),TRUE);
	$wgOut->addWikiText($this->Charge_RenderTable(),TRUE);
	$wgOut->addWikiText($this->Event_RenderTable(),TRUE);
	$wgOut->addWikiText($this->Pull_RenderTable(),TRUE);
	$wgOut->addHTML('<hr><small>generated by '.__FILE__.' line '.__LINE__.'</small>');
    }
    /*----
      PURPOSE: Starting point for admin page
	This method calls other methods to do the work, depending on input
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgOut,$vgPage;
	//global $ksTextEmail;

	$vgPage->UseHTML();
	$out = NULL;

	$strDo = $vgPage->Arg('do');
	if ($strDo == 'cart') {
	    //$out .= '<table width=15% align=right style="border: 1px solid black;"><tr><td>';
	    $out .= '<h2>Cart Setup</h2>';
	    $out .= $this->DoSetupCart();
	    //$out .= '</td></tr></table>';
	}

	$wgOut->AddHTML($out); $out = '';	// DisplayOrderAdmin outputs directly

	$wtSess = SelfLink_Page(KS_PAGE_KEY_SESSION,'id',$this->ID_Sess);
	$wtOrd = SelfLink_Page(KS_PAGE_KEY_ORDER,'id',$this->ID_Order);
	$wtCust = SelfLink_Page('cust','id',$this->ID_Cust);

	$doEdit = $vgPage->Arg('edit');
	$doReceipt = $vgPage->Arg('receipt');
	$doEmail = $vgPage->Arg('email');

	$id = $this->ID;
	$strNum = $this->Number;
	if ($doReceipt) {
	    $strTitle = 'Receipt for Order #'.$strNum;
	} else {
	    $strTitle = 'Order ID '.$id.' - #'.$strNum;
	}
	$strPopup = 'order #'.$strNum;

	// do the header, with edit link if appropriate
	$objPage = new clsWikiFormatter($vgPage);
/* OLD SECTION FORMAT
	$objSection = new clsWikiSection($objPage,$strTitle,$strPopup);
	$objSection->ToggleAdd('receipt','receipt for order #'.$strNum);
	$objSection->ToggleAdd('email');
	$objSection->ToggleAdd('edit');
	$objSection->CommandAdd('reload',array());
	$out = $objSection->Generate();
*/ // NEW SECTION FORMAT
    	$objSection = new clsWikiSection_std_page($objPage,$strTitle,2);
	$objSection->PageKeys(array('page','id'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'receipt','rcpt'));
	  $objLink->Popup('receipt for order #'.$strNum);
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'email'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'edit'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'reload',''));
	$out = $objSection->Render();

	if ($doReceipt) {
	    // display order receipt
	    $out .= $this->RenderReceipt();
	    $wgOut->AddHTML($out); $out = '';
	} elseif ($doEmail) {
	    // manual email confirmation
	    $out .= $this->AdminPage_email();
	    $wgOut->AddHTML($out); $out = '';
	} else {
	    // regular order admin display
	    $wgOut->AddHTML($out); $out = '';	// DisplayOrderAdmin outputs directly
	    $this->AdminPage_basic($doEdit,$objSection);
	}
    }
    /*----
      ACTION: create a credit card charge from the current transaction balance
      ASSUMES: transactions have already been added up
      HISTORY:
	2011-03-31 extracted into separate function
    */
    private function DoChargeCard(array $iBal=NULL) {
	global $vgPage;

	$objCard = $this->CardObj();
	$txtCard = $objCard->ShortDescr();

	//-- do the calculations
	$dlrSale = nzArray($iBal,'sale');
	$dlrShip = nzArray($iBal,'ship');
	$dlrChg = $dlrSale + $dlrShip;
	//-- log the attempt
	$strEvDescr = 'charging ccard '.$txtCard.' $'.$dlrChg.' ($'.$dlrSale.' sale + $'.$dlrShip.' s/h)';
	$this->StartEvent_Simple('CHG',$strEvDescr,__METHOD__);

	$out = $strEvDescr;

	//-- add a transaction to zero the balance
	$strDescr = 'debited '.$txtCard.' on '.date('M j');

	$arIns = array(
	  'ID_Order'	=> $this->ID,
	  'ID_Package'	=> 'NULL',
	  'ID_Type'		=> KI_ORD_TXTYPE_PAID_CC,
	  'WhenDone'	=> 'NOW()',
	  'Descr'		=> SQLValue($strDescr),
	  'Amount'		=> -$dlrChg
	  );
	$this->TrxactTable()->Insert($arIns);
	$idTrx = $this->objDB->NewID();
	$this->Data_Trx_uncache();

	$out .= '<br>Transaction ID '.$idTrx.' created.';

	//-- create the charge
	$arIns = array(
	  'ID_Order'	=> $this->ID,
	  'ID_Card'		=> $this->ID_ChargeCard,
	  'ID_Trxact'	=> $idTrx,
	  'AmtTrx'		=> $dlrChg,
	  'AmtSold'		=> $dlrSale,
	  'AmtShip'		=> $dlrShip,
	  'CardNumExp'	=> SQLValue($objCard->SingleString()),
	  'CardBillAddr'	=> SQLValue($objCard->Address),
	  'WhenEntered'	=> 'NOW()'
	  );
	$this->ChargeTable()->Insert($arIns);
	$idChg = $this->objDB->NewID();

	global $sql;
	$out .= '<br>Charge ID '.$idChg.' added - SQL: '.$sql;

	// log event completion
	$arEv = array('params' => ':TrxID='.$idTrx.':ChgID='.$idChg);
	$this->FinishEvent($arEv);

	return $out;
    }
    /*----
      ACTION: recalculates and updates order totals based on items/quantities ordered
      HISTORY:
	2011-03-31 extracted into separate function
    */
    private function DoRecalcBal() {
	$ftOut = 'Recalculating order totals...<ul>';
	$rs = $this->LinesData();	// get order lines
	if ($rs->hasRows()) {
	    $ar = $rs->FigureTotals();
	    $prcNewShItm = $ar['sh-itm'];
	    $prcNewShPkg = $ar['sh-pkg'];
	    $prcNewShip = $prcNewShItm + $prcNewShPkg;
	    $prcNewSale = $ar['cost-sell'];
	    $prcNewTotal = $prcNewShip + $prcNewSale;

	    $prcOldShip = $this->Value('WebTotal_Ship');
	    $prcOldSale = $this->Value('WebTotal_Merch');
	    $prcOldTotal = $this->Value('WebTotal_Final');

	    $arUpd = NULL;

	    $ftOut .= '<li>Shipping: ';
	    if ($prcOldShip == $prcNewShip) {
		$ftOut .= 'same';
	    } else {
		$ftOut .= '<s>'.$prcOldShip.'</s> &rarr; '.$prcNewShip;
		$arUpd['WebTotal_Ship'] = $prcNewShip;
	    }

	    $ftOut .= '<li>Sale: ';
	    if ($prcOldSale == $prcNewSale) {
		$ftOut .= 'same';
	    } else {
		$ftOut .= '<s>'.$prcOldSale.'</s> &rarr; '.$prcNewSale;
		$arUpd['WebTotal_Merch'] = $prcNewSale;
	    }

	    $ftOut .= '<li>Total: ';
	    if ($prcOldTotal == $prcNewTotal) {
		$ftOut .= 'same';
	    } else {
		$ftOut .= '<s>'.$prcOldTotal.'</s> &rarr; '.$prcNewTotal;
		$arUpd['WebTotal_Final'] = $prcNewTotal;
	    }
	    $ftOut .= '</ul>';

	    if (is_array($arUpd)) {
		$arEv = array(
		    'code'	=> 'TOT',
		    'descr'	=> $ftOut,
		    'where'	=> __METHOD__
		    );
		$this->StartEvent($arEv);
		$this->Update($arUpd);
		$this->Reload();
		$this->FinishEvent();
	    }
	} else {
	    $ftOut .= 'No items found in order!';
	}
	return $ftOut;
    }
    private function SaveNumField($iName) {
	global $wgOut, $wgRequest;

	$didChange = FALSE;
	$cur = $wgRequest->getVal($iName);
	if (is_numeric($cur)) {
	    $old = $this->$iName;
	    if ($cur != $old) {
		$didChange = TRUE;
		$strStat = $iName.' changed from $'.$old.' to $'.$cur;
		$this->LogEvent('EDF',$strStat);
		$this->Update(array(SQLValue($iName)=>SQLValue($cur)));
		$wgOut->AddWikiText($strStat);
	    }
	} else {
	    $strStat = $iName.' not changed from "'.$old.'" - new value "'.$cur.'" is non-numeric.';
	    $this->LogEvent('EDFX',$strStat);
	    $wgOut->AddWikiText($strStat);
	}
	return $didChange;
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
	2011-03-23 copied from clsAdminTopic to VbzAdminOrderItem
	2012-01-02 copied from VbzAdminOrderItem to VbzAdminOrder
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
/* 2012-01-02 old version
    public function AdminSave() {
	global $wgOut, $wgRequest;

	$didChange = FALSE;

	$idCard = $wgRequest->getVal('ccard');
	if ($idCard != $this->ID_ChargeCard) {
	    $didChange = TRUE;
	    $strStat = 'ccard changed from ID '.$this->ID_ChargeCard.' to '.$idCard;
	    //$this->LogEvent('EDF',$strStat);
	    $arEv = array(
	      'code'	=> 'EDF',
	      'descr'	=> $strStat,
	      'where'	=> __METHOD__
	      );
	    $this->StartEvent($arEv);
	    $this->Update(array('ID_ChargeCard'=>$idCard));
	    $wgOut->AddWikiText($strStat);
	    $this->FinishEvent();
	}

	$didChange = $didChange || $this->SaveNumField('WebTotal_Merch');
	$didChange = $didChange || $this->SaveNumField('WebTotal_Ship');
	$didChange = $didChange || $this->SaveNumField('WebTotal_Final');

	if ($didChange) {
	    $this->Update(array('WhenEdited'=>'NOW()'));	// update modification timestamp
	    $this->Reload();					// load the modified data
	}
    }
*/
    public function Item_RenderTable() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$vgPage->UseHTML();

	$strAction = $vgPage->Arg('do');
	$doAddItem = ($strAction == 'add-item');
	if ($doAddItem) {
	    $this->arTblArgs['new'] = TRUE;
	}
	if ($wgRequest->GetBool('btnSaveItem')) {
	    $arFields = VbzAdminOrderItems::CaptureEdit();
	    VbzAdminOrderItems::SaveEdit($this,$arFields);
	}

	$objPage = new clsWikiFormatter($vgPage);
/*
	$objSection = new clsWikiSection($objPage,'Items',NULL,3);
	$objSection->ActionAdd('add','add a new item to the order',NULL,'add-item');
	$out = $objSection->Generate();
*/
    	$objSection = new clsWikiSection_std_page($objPage,'Items',3);
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'add-item','do','add'));
	  $objLink->Popup('add a new item to the order');
	$out = $objSection->Render();

//	$objTbl = $this->objDB->OrdItems();
//	$objRows = $objTbl->GetData('ID_Order='.$this->ID,NULL,'Seq');
	$objRows = $this->LinesData();
	$arSum = $this->ItemStats();	// $arSum[item ID][qty-shp|qty-ext|qty-kld|qty-na]
	$out .= $objRows->AdminTable_forOrder($arSum,$this->arTblArgs);

	$wgOut->AddHTML($out);
	return NULL;
    }
    // PACKAGE subdata
    public function Pkg_RenderTable() {
	$objTable = new clsPackages($this->objDB);
	$objRows = $objTable->GetOrder($this->ID);
	return "\n===Packages===\n".$objRows->AdminTable($this->arTblArgs);
    }
    // TRANSACTION subdata
    /*----
      RETURNS: recordset of transaction lines
    */
    protected function Data_Trx() {
	$cache = $this->objCacheTrx;
	$id = $this->KeyValue();
	if (!$cache->IsCached($id)) {
	    $tbl = $this->objDB->OrdTrxacts();
	    $rs = $tbl->GetData('ID_Order='.$id,NULL,'WhenDone, ID');
	    $cache->Object($rs,$id);
	}
	return $cache->Object();
    }
    /*----
      ACTION: clears the cache so data will be reloaded on next request
      HISTORY:
	2011-03-31 created so we can refresh transaction data after updating
    */
    protected function Data_Trx_uncache() {
	$cache = $this->objCacheTrx;
	$cache->Clear();
    }
    /*----
      RETURNS: formatted code of header and table showing transactions for this order
    */
    public function Trxact_RenderTable() {
	global $vgPage,$vgOut;

	$id = $this->KeyValue();
	//$objTable = new VbzAdminOrderTrxacts($this->objDB);
	//$objTable = $this->objDB->OrdTrxacts();
	//$objRows = $objTable->GetData('ID_Order='.$id,NULL,'WhenDone, ID');
	$rs = $this->Data_Trx();
	$this->arTblArgs['order'] = $id;

	$vgPage->UseHTML();

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Transactions',NULL,3);
	$arLink = array(
	  'page'	=> $rs->Table->ActionKey(),
	  'order'	=> $id,
	  'id'		=> 'new'
	  );
	//$objSection->ActionAdd('add','add a new transaction for this order',NULL,'add-trx');
	$objSection->CommandAdd('add...',$arLink,'add a new transaction for this order');
	$out = $objSection->Generate();

	$out .= $rs->AdminTable($this->arTblArgs,$this);

	if ($rs->HasBalance()) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $arLink['do'] = 'charge';
	    $arBalTrx = $rs->Balances();
	    $prcChg = $arBalTrx['total'];
	    if ($prcChg > 0) {
		$ftChargeLink = $this->AdminLink(
		  'charge for balance',
		  'create a credit card charge for the $'.$prcChg.' current balance',
		  array('do'=>'charge'));
		$out .= "[ $ftChargeLink ]";
	    }
	    $this->arBalTrx = $arBalTrx;
	} else {
	    $this->arBalTrx = NULL;
	}

	return $out;
    }
    // MESSAGE subdata
    public function Msg_RenderTable() {
	$objTable = new VbzAdminOrderMsgs($this->objDB);
	$objRows = $objTable->GetData('ID_Ord='.$this->ID);
	return "\n===Messages===\n".$objRows->AdminTable($this->arTblArgs);
    }
    public function Charge_RenderTable() {
	// get table showing existing charges
	$objTable = new VbzAdminOrderChgs($this->objDB);
	$objRows = $objTable->GetData('ID_Order='.$this->ID);
	$out = $objRows->AdminTable($this->arTbliArgs);
	return "\n===Charges===\n".$out;
    }
    public function Event_RenderTable() {
	$objTable = new VbzAdminOrderEvents($this->objDB);
	$objRows = $objTable->GetData('ModType="ord" AND ModIndex='.$this->ID,NULL,'IFNULL(WhenStarted,WhenFinished) DESC');
	return "\n===Events===\n".$objRows->AdminTable($this->arTblArgs);
    }
/*
  2010-10-24 Started writing this based on VBA code, but then realized that it doesn't make any sense.
    Credit card debits should be based on the current transaction total, which is figured when the
    transactions table is generated.

    public function BalanceSummary() {
	$dlrSaleTot = 0;
	$dlrItmShipTot = 0;
	$dlrItmShipEstTot = 0;
	if ($this->HasLines()) {
	    $objRows = $this->LinesData();
	    while ($objRows->NextRow()) {
		$dlrSale = $objRows->CostSale;
		$qtyOrd = $objRows->QtyShipped;
		$dlrSaleTot += $dlrSale * $qtyOrd;
		$dlrItmShipEst = $qtyOrd * $objRows->CostShItem;
		$dlrItmShipEstTot += $dlrMerchShipEst;
	    }
	}
    }
*/
    /*----
      FUTURE: This actually *displays* the table -- needs to be renamed Pull_ShowTable()
	or else modified so it returns code instead of displaying it.
    */
    public function Pull_RenderTable() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$wgOut->addWikiText("===Pulls===",TRUE);

	$objTable = new VbzAdminOrderPulls($this->objDB);
//	return $objRows->AdminTable($iArgs,$this);

	$doAdd = (nz($this->arTblArgs['form']) == 'pull');

	if ($wgRequest->getVal('btnPull')) {
	    $doShowForm = FALSE;

	    $idType = $wgRequest->getVal('type');
	    $strNotes = $wgRequest->getVal('notes');
	    $out = "Pulling order as '''".$objTable->Types()->GetItem($idType)->Name."'''";
	    if (!empty($strNotes)) {
		$wtNotes = htmlspecialchars($strNotes);
		$out .= " with note '''$wtNotes'''.";
	    }
	    $wgOut->AddWikiText($out,TRUE);	$out = '';

	    $objTable->Pull($this,$idType,$strNotes);
	    $this->Reload();	// update order's pulled status
	} elseif ($wgRequest->getVal('btnFree')) {
	    $doShowForm = FALSE;
	    $stat = $this->PullObj()->UnPull($strNotes);
	    if (!is_null($stat)) {
		$wgOut->AddWikiText("'''Error''': $stat",TRUE);
	    }
	} else {
	    $doShowForm = $doAdd;
	}

	$objRows = $objTable->GetOrder($this->ID);
	if ($objRows->hasRows()) {
	    $out = "\n{|\n|-\n! ID || What || When || Notes";
	    $isOdd = TRUE;
	    while ($objRows->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $objRows->Row;
		$id = $row['ID'];

		$idType = $row['ID_Type'];
//		$objType = $objRows->Types()->GetItem($idType);
		$strWhat = $objRows->TypeName();

		$htWho = WhoString_wt($row);

		$isRowPulled = $objRows->IsPulled();

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $id "
		  ."|| $strWhat "
		  ."|| {$row['WhenPulled']} "
		  ."|| {$row['NotesPull']}";
		if (!$isRowPulled) {
		    $out .= "\n|-\n| || '''released'''"
		  ."|| {$row['WhenFreed']} "
		  ."|| {$row['NotesFree']}";
		}
	    }
	    $out .= "\n|}";
	} else {
	    $strDescr = nzArray($this->arTblArgs,'descr');
	    $out = "\nNo pulls $strDescr.";
	}
	$wgOut->AddWikiText($out,TRUE);	$out = '';

	$isOrderPulled = $this->Pulled();
	if ($doShowForm) {
	    $arLink = $vgPage->Args();
	    unset($arLink['edit']);
	    unset($arLink['form']);
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);	// this may need to be tweaked to remove unnecessary parameters
	    if ($isOrderPulled) {
		$out = '<form method=POST action="'.$urlForm.'">';
		$out .= ' Details: <textarea name=notes width=40 rows=5></textarea>';
		$out .= '<input type=submit name=btnFree value="Release">';
		$out .= ' - click to release order pulled at '.$this->PullObj()->WhenPulled;
	    } else {
		$out = '<form method=POST action="'.$urlForm.'">';
		$out .= $objTable->Types()->ComboBox('type');
		$out .= ' Details: <textarea name=notes width=40 rows=5></textarea>';
		$out .= '<input type=submit name=btnPull value="Pull">';
		$out .= ' - click to pull order #'.$this->Number;
	    }
	    $out .= '</form>';
	    $wgOut->AddHtml($out);	$out = '';
	} else {
	    if ($isOrderPulled) {
		$strMsg = 'Release this order';
	    } else {
		$strMsg = 'Pull this order';
	    }
	    $arLink = $vgPage->Args();
	    $arLink['form'] = 'pull';
	    $wtPullLink = $vgOut->SelfLink($arLink,$strMsg);
	    $out .= '[ '.$wtPullLink.' ]';
	    $vgOut->AddText($out);	$out = '';
	}
	return $out;
    }
}
class VbzAdminOrderItems extends clsOrderLines {
// CONSTRUCTOR //
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderItem');	// override parent
	  $this->ActionKey('order-item');
    }

// STATIC section //
    /*-----
      ARGS:
	$iFull: show all editable fields? (FALSE = only show fields which can't be looked up)
      TO DO: In case this is ever used more generally, probably *all* fields
	should be protected by htmlspecialchars()
      HISTORY:
	2011-03-23 fixed the order of the last 4 fields -- qty goes *after* prices
    */
    static public function RenderEdit_inline($iFull,array $iarArgs=NULL) {
	if ($iFull) {
	    // show the specs for final approval
	    $ftPart1 =
		'<td><input name="Descr"	size=30	value="'.htmlspecialchars($iarArgs['Descr']).'"></td>';
	    $ftPart2 = '<input type=hidden name="ID_Item" value="'.$iarArgs['ID_Item'].'">'
	      .'<td><input name="Price"		size=5	value="'.$iarArgs['Price'].'"></td>'
	      .'<td><input name="PerItem"	size=5	value="'.$iarArgs['PerItem'].'"></td>'
	      .'<td><input name="PerPkg"	size=5	value="'.$iarArgs['PerPkg'].'"></td>';
	    $btnSave = '<td><input type=submit name="btnSaveItem" value="Save"></td>';
	} else {
	    $ftPart1 = '<td><input type=submit name="btnLookup" value="Check..."></td>';
	    $ftPart2 = '<td colspan=3 align=center><i>to be looked up</i></td>';
	    $btnSave = '';
	}
	$out = "\n<tr>"
	  .'<td align=center>new</td>'
	  .'<td><input name="CatNum"	size=10 value="'.htmlspecialchars($iarArgs['CatNum']).'"></td>'
	  .$ftPart1
	  .$ftPart2
	  .'<td><input name="Qty"	size=2 value="'.htmlspecialchars($iarArgs['Qty']).'"></td>'
	  .$btnSave
	  .'</tr>';
	return $out;
    }
    static public function CaptureEdit() {
	global $wgRequest;
	// always capture these fields
	$arOut['ID_Item'] = $wgRequest->GetIntOrNull('ID_Item');
	$arOut['CatNum'] = $wgRequest->GetText('CatNum');
	$arOut['Qty'] = $wgRequest->GetIntOrNull('Qty');
	$arOut['Descr'] = $wgRequest->GetText('Descr');
	$arOut['Price'] = $wgRequest->GetText('Price');
	$arOut['PerItem'] = $wgRequest->GetText('PerItem');
	$arOut['PerPkg'] = $wgRequest->GetText('PerPkg');
	return $arOut;
    }
    static public function SaveEdit(clsOrder $iOrder, array $iarFields, clsOrderLine $iLine=NULL) {
	$isOld = isset($iarFields['ID']);
	if ($isOld) {
	    $intSeq = (int)$iarFields['Seq'];
	} else {
	    $intSeq = $iOrder->NextSeq();
	}
	$strCatNum = $iarFields['CatNum'];
	$intQty = (int)$iarFields['Qty'];
	$arUpd = array(
	  'ID_Order'	=> $iOrder->ID,
	  'Seq'		=> $intSeq,
	  'ID_Item'	=> (int)$iarFields['ID_Item'],
	  'CatNum'	=> SQLValue($iarFields['CatNum']),
	  'Descr'	=> SQLValue($iarFields['Descr']),
	  'QtyOrd'	=> $intQty,
	  'Price'	=> SQLValue($iarFields['Price']),
	  'ShipPkg'	=> SQLValue($iarFields['PerPkg']),
	  'ShipItm'	=> SQLValue($iarFields['PerItem']),
	  'WhenAdded'	=> 'NOW()',
	  );
	if ($isOld) {
	    // LOG THE ATTEMPT
	    $arEv = array(
	      'code'	=> 'UDI',
	      'descr'	=> 'updating cat #'.$strCatNum.' qty '.$intQty,
	      'where'	=> __METHOD__
	      );
	    $iOrder->StartEvent($arEv);
	    // UPDATE THE RECORD
	    if (is_object($iLine)) {
		$objLine = $iLine;
	    } else {
		$idLine = $iarFields['ID'];
		$objLine = $this->GetItem($idLine);
	    }
	    $objLine->Update($arUpd);
	} else {
	    // LOG THE ATTEMPT
	    $arEv = array(
	      'code'	=> 'ADI',
	      'descr'	=> 'adding cat #'.$strCatNum.' qty '.$intQty,
	      'where'	=> __METHOD__
	      );
	    $iOrder->StartEvent($arEv);
	    // CREATE THE RECORD
	    $iOrder->objDB->OrdLines()->Insert($arUpd);
	}
	// LOG COMPLETION
	$iOrder->FinishEvent();
    }

}
class VbzAdminOrderItem extends clsOrderLine {
    /*----
      HISTORY:
	2011-03-23 boilerplate event logging added - copied from clsAdminTopic
    */
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->EventListing();
    }
    //----
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
    */
    protected $objOrd;
    public function OrderObj() {
	$doLoad = TRUE;
	$id = $this->Value('ID_Order');
	if (isset($this->objOrd)) {
	    if ($this->objOrd->KeyValue() == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objOrd = $this->Engine()->Orders($id);
	}
	return $this->objOrd;
    }
    /*----
      HISTORY:
	2011-03-23 created for AdminPage()
    */
    protected $objItem;
    public function ItemObj() {
	$doLoad = TRUE;
	$id = $this->Value('ID_Item');
	if (isset($this->objItem)) {
	    if ($this->objItem->KeyValue() == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objItem = $this->Engine()->Items($id);
	}
	return $this->objItem;
    }
    /*----
      HISTORY:
	2011-03-23 adapted from VbzAdminItem to VbzAdminOrderItem
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('Seq'),		new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsField('ID_Item'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsField('CatNum'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('Descr'),	new clsCtrlHTML(array('size'=>40)));
	    $objForm->AddField(new clsField('QtyOrd'),		new clsCtrlHTML(array('size'=>3)));
	    $objForm->AddField(new clsFieldNum('Price'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ShipPkg'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('ShipItm'),	new clsCtrlHTML(array('size'=>5)));
	    $objForm->AddField(new clsFieldNum('Notes'),	new clsCtrlHTML(array('size'=>40)));

	    $this->objForm = $objForm;
	}
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
	2011-03-23 copied from clsAdminTopic to VbzAdminOrderItem
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$vgPage->UseHTML();
	$out = NULL;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objOrd = $this->OrderObj();

	$strTitle = 'Order '.$objOrd->AdminName().' item #'.$this->Value('Seq');

	$objPage = new clsWikiFormatter($vgPage);
/*
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();
*/
    	$objSection = new clsWikiSection_std_page($objPage,$strTitle,3);
	//$objSection->PageKeys(array('page','id'));
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array(),'edit'));
	//  $objLink->Popup('receipt for order #'.$strNum);
	$out = $objSection->Render();

	$wgOut->AddHTML($out); $out = '';
	$vgOut->AddText($ftSaveStatus);

	$ftID = $this->AdminLink();
	$ftOrd = $objOrd->AdminLink_name();
	$ftWho = $this->Value('VbzUser');
	$ftWhere = $this->Value('Machine');
	if ($doEdit) {
	    $out .= $objSection->FormOpen();
	    $objForm = $this->objForm;

	    $ftSeq	= $objForm->Render('Seq');
	    $ftItem	= $objForm->Render('ID_Item');
	    $ftCatNum	= $objForm->Render('CatNum');
	    $ftDescr	= $objForm->Render('Descr');
	    $ftQty	= $objForm->Render('QtyOrd');
	    $ftPrice	= $objForm->Render('Price');
	    $ftShPkg	= $objForm->Render('ShipPkg');
	    $ftShItm	= $objForm->Render('ShipItm');
	    $ftNotes	= $objForm->Render('Notes');
	} else {
	    $objItem = $this->ItemObj();

	    $ftSeq	= $this->Value('Seq');
	    $ftItem	= $objItem->AdminLink_friendly();
	    $ftCatNum	= $this->Value('CatNum');
	    $ftDescr	= $this->Value('Descr');
	    $ftQty	= $this->Value('QtyOrd');
	    $ftPrice	= $this->Value('Price');
	    $ftShPkg	= $this->Value('ShipPkg');
	    $ftShItm	= $this->Value('ShipItm');
	    $ftNotes	= $this->Value('Notes');
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>ID</b>:</td><td>$ftID</td></tr>";
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>$ftOrd</td></tr>";
	$out .= "\n<tr><td align=right><b>Seq</b>:</td><td>$ftSeq</td></tr>";
	$out .= "\n<tr><td align=right><b>Item</b>:</td><td>$ftItem</td></tr>";
	$out .= "\n<tr><td align=right><b>Cat #</b>:</td><td>$ftCatNum</td></tr>";
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>$ftDescr</td></tr>";
	$out .= "\n<tr><td align=right><b>Qty ordered</b>:</td><td>$ftQty</td></tr>";
	$out .= "\n<tr><td align=right><b>Price</b>:</td><td>$ $ftPrice</td></tr>";
	$out .= "\n<tr><td align=right><b>s/h per pkg</b>:</td><td>$ $ftShPkg</td></tr>";
	$out .= "\n<tr><td align=right><b>s/h per item</b>:</td><td>$ $ftShItm</td></tr>";
	$out .= "\n<tr><td align=right><b>Notes</b>:</td><td>$ftNotes</td></tr>";
	$out .= "\n<tr><td align=right><b>Admin</b>:</td><td>$ftWho</td></tr>";
	$out .= "\n<tr><td align=right><b>Where</b>:</td><td>$ftWhere</td></tr>";
	$out .= "\n</table>";

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	// events
	$objSection = new clsWikiSection($objPage,'Events',NULL,3);
	$out .= $objSection->Generate();
	$out .= $this->EventListing();

	$wgOut->addHTML($out);	$out = '';
    }
    /*-----
      INPUT:
	$iStats is the result of calling Order::ItemStats()
	$iArgs needs to be documented
    */
    public function AdminTable_forOrder(array $iStats=NULL,array $iArgs=NULL) {
	global $wgRequest;

	$doNew = isset($iArgs['add']);	// displaying extra stuff for adding new line
	$doRows = $this->hasRows();

	$doLookup = $wgRequest->GetBool('btnLookup');
	$doSave =  $wgRequest->GetBool('btnSaveItem');
	$doCapture = $doLookup || $doSave;	// is there form data to capture?
	$hasPkgs = is_array($iStats);

	if ($doRows || $doNew) {
	    $out = "\n<table>";
	    $out .= "\n<tr><td colspan=6></td><th colspan=6>Quantity</td></tr>";
	    $out .= "\n<tr>"
	      .'<th>ID</th>'
	      .'<th>Cat #</th>'
	      .'<th>Description</th>'
	      .'<th>price</th>'
	      .'<th>per-item</th>'
	      .'<th>per-pkg</th>'
	      .'<th>ord</th>';
	    if ($hasPkgs) {
		$out .=
		  '<th><i>shp</i></th>'
		  .'<th><i>ext</i></th>'
		  .'<th><i>kld</i></th>'
		  .'<th><i>n/a</i></th>'
		  .'<th>OPEN</th>'
		  ;
	    }
	    $out .= '</tr>';

	    if ($doCapture) {
		$arFields = VbzAdminOrderItems::CaptureEdit();
	    } else {
		$arFields = NULL;
	    }
	    if ($doRows) {
		$arSum = $iStats;
		$isOdd = TRUE;
		while ($this->NextRow()) {
		    $wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		    $isOdd = !$isOdd;

		    $row = $this->Row;
		    $id = $row['ID'];
		    $idItem = $this->ID_Item;
		    $ftID = $this->AdminLink();
		    $strCatNum = $row['CatNum'];
		    $htCatNum = VbzAdminItems::AdminLink($idItem,$strCatNum);
		    $strDescr = $row['Descr'];
		    $strPrice = $row['Price'];
		    $strPerItem = $row['ShipPkg'];
		    $strPerPkg = $row['ShipItm'];
		    $intQtyOrd = (int)$row['QtyOrd'];
		    $strQtyOrd = $intQtyOrd;
		    // calculated fields
		    if ($hasPkgs) {
			//$arSumItem = $arSum[$idItem];
			$arSumItem = VbzAdminOrder::ItemStats_update_line($arSum[$idItem]);
			//$qtyUsed = (array_key_exists('qty-used',$arSumItem))?($arSumItem['qty-used']):0;

			//$intQtyShp = (int)$arSumItem['qty-shp']-$qtyUsed;
			$intQtyShp = (int)$arSumItem['qty-shp-line'];
			$intQtyExt = (int)$arSumItem['qty-ext'];
			$intQtyKld = (int)$arSumItem['qty-kld'];
			$intQtyNA  = (int)$arSumItem['qty-na'];
			$intQtyOpen = $intQtyOrd - $intQtyShp - $intQtyKld - $intQtyNA;
			$strQtyOpen = ($intQtyOpen == 0)?'-':('<font color=red><b>'.$intQtyOpen.'</b></font>');
		    }

		    //$out .= "\n|- style=\"$wtStyle\"";
		    //$out .= "\n| $ftID || $htCatNum || $strDescr || $strQtyOrd || $strPrice || $strPerItem || $strPerPkg ";
		    $out .= "\n<tr style=\"$wtStyle\">"
		      ."<td>$ftID</td>"
		      ."<td>$htCatNum</td>"
		      ."<td>$strDescr</td>"
		      ."<td align=right>$strPrice</td>"
		      ."<td align=right>$strPerItem</td>"
		      ."<td align=right>$strPerPkg</td>"
		      ."<td align=center>$strQtyOrd</td>";
		    if ($hasPkgs) {
			$out .=
			  "<td align=center>$intQtyShp</td>"
			  ."<td align=center>$intQtyExt</td>"
			  ."<td align=center>$intQtyKld</td>"
			  ."<td align=center>$intQtyNA</td>"
			  ."<td align=center>$strQtyOpen</td>";
		    }
		    $out .= "</tr>";
		}
	    }
	    if ($doNew) {
		// need to initialize these, one way or another:
		/* or do we?
		$intQty = nz($arFields['Qty']);
		$ftDescr = nz($arFields['Descr']);
		$ftPrice = nz($arFields['Price']);
		$ftPerItem = nz($arFields['PerItem']);
		$ftPerPkg = nz($arFields['PerPkg']);
		*/
		$out .= '<form method=post>';
		$doFull = $doLookup;
		if ($doLookup) {
		    // look up the item specs
		    $strCatNum = nz($arFields['CatNum']);
		    $objItems = $this->objDB->Items()->GetData('CatNum="'.$strCatNum.'"');
		    $doFull = $objItems->HasRows();
		    if (!$doFull) {
			$out .= 'No match found for cat # '.$strCatNum;
		    }
		    $objItems->FirstRow();
		    $arFields['Descr'] = $objItems->DescLong();
		    $arFields['Price'] = FormatMoney($objItems->PriceSell);
		    $objShipCode = $objItems->ShCost();
		    $arFields['ID_Item'] = $objItems->ID;
		    $arFields['PerItem'] = FormatMoney($objShipCode->PerItem);
		    $arFields['PerPkg'] = FormatMoney($objShipCode->PerPkg);
		} else if ($doSave) {
		    // gloopwy
		}
		$out .= VbzAdminOrderItems::RenderEdit_inline($doFull,$arFields);
		$out .= '</form>';
	    }
	    $out .= "\n</table>";
	} else {
	    $strDescr = $iArgs->Value('descr');
	    $out = "\nNo items$strDescr!";
	}
	return $out;
    }
    public function AdminTable_forItem() {
	global $vgPage;

	if ($this->hasRows()) {
	    $vgPage->UseWiki();
	    $out = "\n{| \n|-\n! When || Order || Status || price || align=right | qtys: || Ord || Shp || Ext || Xed || N/A || Open! ";
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$idOrd = $this->ID_Order;
		$objOrd = $this->objDB->Orders()->GetItem($idOrd);
		$key = $objOrd->WhenStarted.$objOrd->Number;
		$arOrd[$key]['line'] = $this->RowCopy();
		$arOrd[$key]['ord'] = $objOrd;
	    }
	    krsort($arOrd);
	    foreach ($arOrd as $key => $data) {
		$objLine = $data['line'];
		$objOrd = $data['ord'];
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		//$wtOrd = $vgPage->SelfLink_WT(array('page'=>'order','id'=>$idOrd),$objOrd->Number);
		$wtOrd = $objOrd->AdminLink($objOrd->Number);
		$objBuyer = $objOrd->BuyerObj();
		if (is_object($objBuyer)) {
		    $wtOrd .= ': '.$objBuyer->AdminLink($objBuyer->NameStr());
		} else {
		    $wtOrd .= ' <i>(no buyer!)</i>';
		}
		$wtStatus = $objOrd->PulledText();

		$arStats = $objOrd->ItemStats($objLine->ID_Item);
		$qtyOrd = $objLine->Value('QtyOrd');
		$qtyShp = nzArray($arStats,'qty-shp');
		$qtyExt = nzArray($arStats,'qty-ext');
		$qtyKld = nzArray($arStats,'qty-kld');
		$qtyNA = nzArray($arStats,'qty-na');
		$qtyOpen = $qtyOrd - $qtyShp - $qtyKld - $qtyNA;
		$wtOpen = empty($qtyOpen)?'-':"'''$qtyOpen'''";
		$wtWhen = $objOrd->WhenStarted;

		$strPrice = $objLine->Price;

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $wtWhen || $wtOrd || $wtStatus || $strPrice || "
		  ."|| align=center | $qtyOrd "
		  ."|| align=center | $qtyShp "
		  ."|| align=center | $qtyExt "
		  ."|| align=center | $qtyKld "
		  ."|| align=center | $qtyNA "
		  ."|| align=center | $wtOpen ";
	    }
	} else {
	    $out = "\nNo orders found for this item";
	}
	return $out;
    }
/*
    public function Link($iText) {
	$out = SelfLink_WT(array('page'=>'item','id'=>$this->ID_Item),$iText);
	return $out;
    }
*/
    /*----
      RETURNS: array contining sum of quantities ordered for each item in the order
      HISTORY:
	2011-03-24 fixed bug where multiple rows of same item were not being handled properly
	  Quantities are now added instead of latest overwriting previous value.
    */
    public function QtyArray() {
	if ($this->hasRows()) {
	    $arOut = NULL;
	    while ($this->NextRow()) {
		$idItem = $this->ID_Item;
		$arOut[$idItem] = $this->QtyOrd + nzArray($arOut,$idItem,0);
	    }
	    return $arOut;
	} else {
	    return NULL;
	}
    }
}
// order transactions
class VbzAdminOrderTrxacts extends clsTable {
    //const TableName='ord_trxact';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderTrxact');	// override parent
	  $this->Name('ord_trxact');
	  $this->KeyName('ID');
	  $this->ActionKey('trx');
    }
    /*----
      ACTION: Display a link to edit a new transaction
      HISTORY:
	2011-03-24 created
    */
    public function AdminLink_create($iText='new',$iPopup='edit a new transaction',array $iArgs=NULL) {
	$obj = $this->SpawnItem();
	return $obj->AdminLink($iText,$iPopup,$iArgs);
    }
}
class VbzAdminOrderTrxact extends clsDataSet {
    protected $arBal;

  /*%%%%
    SECTION: boilerplate admin functions
  */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
  /*%%%%
    SECTION: boilerplate event logging
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
    /*%%%%
      SECTION: basic field access methods
    */
    public function OrderObj() {
	return $this->objDB->Orders()->GetItem($this->ID_Order);
    }
    public function PkgObj() {
	return $this->objDB->Pkgs()->GetItem($this->ID_Package);
    }
    public function TypeObj() {
	$obj = $this->objDB->OrdTrxTypes()->GetItem_Cached($this->ID_Type);
//echo '[ID_Type is null: '.is_null($this->ID_Type).'] [obj is object:'.is_object($obj).']';
	return $obj;
    }
    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldNum('ID_Order'),	new clsCtrlHTML_Fixed());
	    $objForm->AddField(new clsFieldNum('ID_Package'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Type'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenDone'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenVoid'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Descr'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('Amount'),		new clsCtrlHTML(array('size'=>6)));

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the transaction
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::AdminSave()
	2011-01-02 Replaced with VbzAdminItem::AdminSave() version
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      HISTORY:
	2011-01-01 Created
    */
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage;

	// get status from URL
	$isNew = $this->IsNew();
	//$strAction = $vgPage->Arg('do');
	$doEdit = $vgPage->Arg('edit') || $isNew;
	$doSave = $wgRequest->GetBool('btnSave');

	if ($isNew) {
	    $strTitle = 'New Transaction';
	    $this->Value('ID_Order',$vgPage->Arg('id.ord'));
	} else {
	    $strTitle = 'Transaction #'.$this->ID;
	}

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strTitle);
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();


	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objOrd = $this->OrderObj();
	$objPkg = $this->PkgObj();
	$objType = $this->TypeObj();

	$ctOrd = $objOrd->AdminLink_name();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    $ctPkg	= $objPkg->DropDown_ctrl('ID_Package','--not package-specific--');
	    $strNone 	= $isNew?'-- choose --':NULL;
	    $ctType	= $objType->DropDown_ctrl('ID_Type',NULL,$strNone);
	    $ctOrd	.= $frm->Render('ID_Order');
	    $ctWhenDone	= $frm->Render('WhenDone');
	    $ctWhenVoid	= $frm->Render('WhenVoid');
	    $ctDescr	= $frm->Render('Descr');
	    $ctAmt	= $frm->Render('Amount');
	} else {
	    $ctPkg = $objPkg->AdminLink_name();
	    $ctType	= $objType->Name;
	    $ctWhenDone	= $this->Value('WhenDone');
	    $ctWhenVoid	= $this->Value('WhenVoid');
	    $ctDescr	= $this->Value('Descr');
	    $ctAmt	= $this->Value('Amount');
	}

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>"		.$ctOrd.'</tr>';
	$out .= "\n<tr><td align=right><b>Package</b>:</td><td>"	.$ctPkg.'</tr>';
	$out .= "\n<tr><td align=right><b>Type</b>:</td><td>"		.$ctType.'</tr>';
	$out .= "\n<tr><td align=right><b>When Done</b>:</td><td>"	.$ctWhenDone.'</tr>';
	$out .= "\n<tr><td align=right><b>When Voided</b>:</td><td>"	.$ctWhenVoid.'</tr>';
	$out .= "\n<tr><td align=right><b>Description</b>:</td><td>"	.$ctDescr.'</tr>';
	$out .= "\n<tr><td align=right><b>Amount</b>:</td><td>$"	.$ctAmt.'</tr>';
	$out .= "\n</table>";

	if ($doEdit) {
	    $out .= '<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out);
	return NULL;
    }
    /*----
      MINOR BUG: When deleting, the first row of the resultset is not shown.
	This is due to Reload() advancing the pointer. We need some way to
	prevent that action. (2010-10-25)
    */
    public function AdminTable(array $iArgs=NULL, clsVbzRecs $iContext=NULL) {
	global $vgPage,$vgOut;

	$strDo = $vgPage->Arg('trx.do');
	if ($strDo == 'void') {
	    $idVoid = $vgPage->Arg('trx.id');
	    $objTrx = $this->Table->GetItem($idVoid);
	    $objOrd = $objTrx->OrderObj();
	    $arEv = array(
	      'descr'	=> 'voiding',
	      'params'	=> ':trx='.$idVoid,
	      'code'	=> 'TVOID'
	      );
	    $objOrd->StartEvent($arEv);
	    $objTrx->Update(array('WhenVoid'=>'NOW()'));
	    $objOrd->FinishEvent();
	    if (is_null($iContext)) {
		$this->Reload();
	    } else {
		$iContext->AdminRedirect();
	    }
	}

	if ($this->hasRows()) {
	    //$out = "\n{| class=sortable \n|-\n! ID || Pkg || Type || When Done || When Void || Amount || Description";
	    $out = $vgOut->TableOpen('class=sortable')
	      .$vgOut->TblRowOpen(NULL,TRUE)
	      .$vgOut->TblCell('ID')
	      .$vgOut->TblCell('Pkg')
	      .$vgOut->TblCell('Type')
	      .$vgOut->TblCell('When Done')
	      .$vgOut->TblCell('When Void')
	      .$vgOut->TblCell('Amount')
	      .$vgOut->TblCell('Description')
	      .$vgOut->TblRowShut();

	    $isOdd = TRUE;
	    $dlrBal = 0;
	    $dlrBalSale = 0;
	    $dlrBalShip = 0;
	    $dlrBalPaid = 0;
	    $arVoidLink = $vgPage->Args(array('page','id'));
	    $arVoidLink['trx.do'] = 'void';
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$idOrd = $this->Value('ID_Order');	// save for later

		$row = $this->Row;
		$id = $row['ID'];
		$ftID = $this->AdminLink();
		$idPkg = $row['ID_Package'];
		//$idType = $row['ID_Type'];
		$objType = $this->TypeObj();
		$htType = $objType->DocLink();
		$strWhenDone = $row['WhenDone'];
		$strWhenVoid = $row['WhenVoid'];
		$dlrAmt = $row['Amount'];
		clsModule::LoadFunc('FormatMoney');
		$strAmount = FormatMoney($dlrAmt);
		$strDescr = $row['Descr'];

		if (is_null($row['WhenVoid'])) {

// 2011-03-31 This data needs redesigning. See notes on ord_trx_type table.
		    IncMoney($dlrBal,$dlrAmt);
		    if ($objType->isShipg != chr(0)) {
			IncMoney($dlrBalShip,$dlrAmt);
		    } elseif ($objType->isCash == chr(0)) {
			IncMoney($dlrBalSale,$dlrAmt);
		    } else {
			IncMoney($dlrBalPaid,$dlrAmt);
		    }
		    // make a link to void this transaction
		    $arVoidLink['trx.id'] = $this->ID;
		    $htWhenVoid = '[ '.$vgOut->SelfLink($arVoidLink,'void it','void this transaction').' ]';
		} else {
		    $wtStyle .= ' color:#888888;';
		    // just show the void timestamp (later: link to un-void it)
		    $htWhenVoid = $strWhenVoid;
		}

		//$out .= "\n|- style=\"$wtStyle\"";
		//$out .= "\n| $id || $idPkg || $htType || $strWhenDone || $strWhenVoid || align=right | $strAmount || $strDescr ";
		$out .= $vgOut->TblRowOpen('style="'.$wtStyle.'"')
		  .$vgOut->TblCell($ftID)
		  .$vgOut->TblCell($idPkg)
		  .$vgOut->TblCell($htType)
		  .$vgOut->TblCell($strWhenDone)
		  .$vgOut->TblCell($htWhenVoid,'align=center')
		  .$vgOut->TblCell($strAmount,'align=right')
		  .$vgOut->TblCell($strDescr)
		  .$vgOut->TblRowShut();
	    }
	    $arTrx = array(
	      'id.ord' => $idOrd
	      );
	    $ftNew = $this->Table->AdminLink_create('new','add a transaction',$arTrx);
	    $out .= $vgOut->TblRowOpen()
	      .$vgOut->TblCell('[ '.$ftNew.' ]')
	      .$vgOut->TblCell('<b>Total</b>:','align=right colspan=4')
	      .$vgOut->TblCell('$<b>'.FormatMoney($dlrBal).'</b>','align=right')
	      .$vgOut->TblCell('<i>('
		  .FormatMoney($dlrBalSale,'$').' sale '
		  .FormatMoney($dlrBalShip,'$'.'+').' s/h '
		  .FormatMoney(-$dlrBalPaid,'$'.'+').' paid'
		  .')</i>')
	      .$vgOut->TblRowShut();
	    $out .= $vgOut->TableShut();
	    // save calculated totals
	    $this->arBal['sale'] = $dlrBalSale;
	    $this->arBal['ship'] = $dlrBalShip;
	    $this->arBal['total'] = $dlrBalSale + $dlrBalShip;
	} else {
	    $this->arBal = NULL;
	    $strDescr = nz($iArgs['descr']);
	    $out = "\nNo transactions$strDescr.";
	}
	return $out;
    }
    public function Balances() {
	return $this->arBal;
    }
    public function HasBalance() {
	return is_array($this->arBal);
    }
}
class VbzAdminOrderTrxTypes extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderTrxType');
	  $this->Name('ord_trx_type');
	  $this->KeyName('ID');
    }
    /*####
      Boilerplate cache functions
    */
    protected $objCache;
    protected function Cache() {
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
    public function GetData_Cached($iWhere=NULL,$iClass=NULL,$iSort=NULL) {
	return $this->Cache()->GetItem($iWhere,$iClass,$iSort);
    }
}
class VbzAdminOrderTrxType extends clsDataSet {
    /*::::
      Simple field access functions
    */
    public function NameShort() {
	return $this->Value('Code');
    }
    public function NameLong() {
	return $this->Value('Code').' '.$this->Value('Descr');
    }
    public function DocLink($iText=NULL) {
	global $vgOut;

	$txtCode = (is_null($iText))?$this->Code:$iText;
	return $vgOut->WikiLink(KWT_DOC_TRX_TYPES.'/'.$this->Code,$txtCode,$this->Descr);
    }
    /*----
      HISTORY:
	2011-01-02 Adapted from VbzAdminDept::DropDown
	  Control name now defaults to table action key
    */
    public function DropDown_for_data($iName=NULL,$iDefault=NULL,$iNone=NULL,$iAccessFx='NameShort') {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key
	if ($this->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($this->NextRow()) {
		$id = $this->ID;
		$htAbbr = (is_null($this->PageKey))?'':($this->PageKey.' ');
		$htShow = $htAbbr.$this->$iAccessFx();
		$out .= DropDown_row($id,$htShow,$iDefault);
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Renders a drop-down control showing all transaction types, with the
	current record being the default.
    */
    public function DropDown_ctrl($iName=NULL,$iNone=NULL) {
	$dsAll = $this->Table->GetData(NULL,NULL,'Code');
	return $dsAll->DropDown_for_data($iName,$this->ID,$iNone,'NameLong');
    }
}
// order messages
class VbzAdminOrderMsgs extends clsTable {
    //const TableName='ord_msg';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderMsg');	// override parent
	  $this->Name('ord_msg');
	  $this->KeyName('ID');
	  $this->ActionKey('omsg');
    }
}
class VbzAdminOrderMsg extends clsDataSet {
    /*====
      Boilerplate AdminLink function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    // /boilerplate

    // FIELD access
    private $idOrd, $objOrd;
    public function OrdObj() {
	$id = $this->Value('ID_Ord');

	$doLoad = TRUE;
	if (!empty($this->idOrd)) {
	    if ($this->idOrd == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->objOrd = $this->objDB->Orders()->GetItem($id);
	    $this->idOrd = $id;
	}
	return $this->objOrd;
    }
    private $idPkg, $objPkg;
    public function PkgObj() {
	$id = $this->Value('ID_Pkg');

	$doLoad = TRUE;
	if (!empty($this->idPkg)) {
	    if ($this->idPkg == $id) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $tblPkgs = $this->objDB->Pkgs();
	    if (is_null($id)) {
		$this->objPkg = $tblPkgs->GetEmpty();
		$this->idPkg = NULL;
		// fake package will need to know the order #
		$this->objPkg->Value('ID_Order',$this->Value('ID_Ord'));
	    } else {
		$this->objPkg = $tblPkgs->GetItem($id);
		$this->idPkg = $id;
	    }
	}
	return $this->objPkg;
    }

    public function AdminTable(array $iArgs=NULL) {
	if ($this->hasRows()) {
	    $out = "\n{| class=sortable \n|-\n! ID || Pkg || Media || From / To || Subject || When || Message / Notes";
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;
		//$id = $row['ID'];
		$htID = $this->AdminLink();
		$idPkg = $row['ID_Pkg'];
		$idMed = $row['ID_Media'];
		$strFrom = $row['TxtFrom'];
		$strTo = $row['TxtTo'];
		$strSubj = $row['TxtRe'];
		$strWhenCreated = $row['WhenCreated'];
		$strMessage = $row['Message'];
		$strNotes = $row['Notes'];

		$htWho = $strFrom;
		$htWho .= ' &rarr; ';
		$htWho .= $strTo;

/* (2011-10-08) this tried to show everything -- takes up too much space
		$htMsg = '&ldquo;'.$strMessage.'&rdquo;';
		if (!empty($strNotes)) {
		    $htMsg .= " ''$strNotes''";
		}
*/
		$strMessage = str_replace("\n",' / ',$strMessage);
		$lenMsg = strlen($strMessage);
		if ($lenMsg > 40) {
		    $txtMsgShow = htmlspecialchars(substr($strMessage,0,20));
		    $txtMsgShow .= ' <font color=#aaa>...</font> ';
		    $txtMsgShow .= htmlspecialchars(substr($strMessage,-20));
		} else {
		    $txtMsgShow = htmlspecialchars($strMessage);
		}
		$ftMsg = $txtMsgShow;

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $htID || $idPkg || $idMed || $htWho || $strSubj || $strWhenCreated || $ftMsg ";
	    }
	} else {
	    $strDescr = nz($iArgs['descr']);
	    $out = "\nNo messages$strDescr.";
	}
	return $out;
    }
    /*----
      HISTORY:
	2011-10-08 created so we can tidy up the Order admin page a bit
    */
    public function AdminPage() {
	global $wgRequest,$wgOut;
	global $vgPage;

	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Order/Package Message #'.$this->KeyValue());
	$objSection->ToggleAdd('edit');
	$out = $objSection->Generate();

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$objPkg = $this->PkgObj();

	if ($doEdit) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $urlForm = $vgPage->SelfURL($arLink,TRUE);
	    $out .= '<form method=post action="'.$urlForm.'">';
	    $frm = $this->objForm;

	    $ctPkg	= $objPkg->DropDown_ctrl('ID_Pkg','--not package-specific--');
	    $ctMedia	= $frm->Render('ID_Media');
	    $ctWhenCre	= $frm->Render('WhenCreated');
	    $ctWhenEnt	= $frm->Render('WhenEntered');
	    $ctWhenRly	= $frm->Render('WhenRelayed');
	    $ctMsg	= $frm->Render('Message');
	    $ctNotes	= $frm->Render('Notes');
	} else {
	    $ctPkg	= $objPkg->AdminLink_name();
	    $ctMedia	= $this->Value('ID_Media');	// do this right later
	    $ctWhenCre	= $this->Value('WhenCreated');
	    $ctWhenEnt	= $this->Value('WhenEntered');
	    $ctWhenRly	= $this->Value('WhenRelayed');
	    $ctMsg	= htmlspecialchars($this->Value('Message'));
	    $ctNotes	= $this->Value('Notes');
	}
	$ctOrd = $this->OrdObj()->AdminLink_name();

	$out .= "\n<table>";
	$out .= "\n<tr><td align=right><b>Order</b>:</td><td>"		.$ctOrd.'</tr>';
	$out .= "\n<tr><td align=right><b>Package</b>:</td><td>"	.$ctPkg.'</tr>';
	$out .= "\n<tr><td align=right><b>Media</b>:</td><td>"		.$ctMedia.'</tr>';
	$out .= "\n<tr><td align=right><b>When Created</b>:</td><td>"	.$ctWhenCre.'</tr>';
	$out .= "\n<tr><td align=right><b>When Entered</b>:</td><td>"	.$ctWhenEnt.'</tr>';
	$out .= "\n<tr><td align=right><b>When Relayed</b>:</td><td>"	.$ctWhenRly.'</tr>';
	$out .= "\n<tr><td align=right><b>Notes</b>:</td><td>"		.$ctNotes.'</tr>';
	$out .= "\n<tr><td align=right><b>Message</b>:</td></tr>";
	$out .= "\n</table>";
	$out .= "<table align=center><tr><td><pre>$ctMsg</pre></td></tr></table>";

	if ($doEdit) {
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$wgOut->AddHTML($out);
	return NULL;
    }
    /*-----
      ACTION: Build the record editing form
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::BuildEditForm()
	2011-01-02 Re-adapted from VbzAdminItem::BuildEditForm()
	2011-10-08 Re-adapted for VbzAdminOrderMsg
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsFieldNum('ID_Pkg'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('ID_Media'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('TxtFrom'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('TxtTo'),		new clsCtrlHTML());
	    $objForm->AddField(new clsField('TxtRe'),		new clsCtrlHTML());
	    $objForm->AddField(new clsFieldBool('doRelay'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsFieldTime('WhenCreated'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenEntered'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenRelayed'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('Message'),		new clsCtrlHTML_TextArea());
	    $objForm->AddField(new clsField('Notes'),		new clsCtrlHTML_TextArea());

	    $this->objForm = $objForm;
	}
    }
    /*-----
      ACTION: Save the user's edits to the transaction
      HISTORY:
	2011-01-01 Adapted from clsAdminRstkReq::AdminSave()
	2011-01-02 Replaced with VbzAdminItem::AdminSave() version
	2011-10-08 Copied to VbzAdminOrderMsg
    */
    private function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
}
// order charges
class VbzAdminOrderChgs extends clsTable {
    const TableName='cust_charges';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderChg');	// override parent
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ActionKey('chg');
    }
    /*----
      HISTORY:
	2011-03-24 adapted from SpecialVbzAdmin::doCharges()
    */
    public function AdminPage() {
	global $wgOut;
	global $vgPage;

	$doAll = $vgPage->Arg('all');
	$strMnuAll = $vgPage->SelfLink_WT(array('all' => TRUE),'all');
	$strMnuUnp = $vgPage->SelfLink_WT(array('all' => FALSE),'to process');

//	$objTbl = new VbzAdminOrderChgs($this->DB());
	$objTbl = $this;
	if ($doAll) {
	    $strMenu = "$strMnuUnp .. '''$strMnuAll'''";
	    $strDescr = ' in database';
	    $sqlFilt = NULL;
	} else {
	    $strMenu = "'''$strMnuUnp''' .. $strMnuAll";
	    $strDescr = ' to be processed';
	    $sqlFilt = '(WhenDecided IS NULL) AND ((WhenXmitted IS NULL) OR isSuccess) AND (WhenVoided IS NULL) AND (WhenHeldUp IS NULL)';
	}
	$objRows = $objTbl->GetData($sqlFilt,NULL,'ID DESC');
	$wgOut->AddWikiText("'''Show Charges''': $strMenu", TRUE);
	$arArgs['descr'] = $strDescr;
	$wgOut->AddWikiText($objRows->AdminTable($arArgs),TRUE);
    }
    /*----
      NOTE: This code was adapted heavily from VbzAdminCustCards::DoAdminEncrypt. They should probably both use a helper class or something.
	...except that all the encryption stuff is being rewritten anyway.
    */
/* OBSOLETE
    public function DoAdminEncrypt() {
	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'encrypting sensitive data in charge log',NULL,FALSE,FALSE);

	$out = NULL;

	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $intChecked = 0;
	    $intChanged = 0;
	    $out .= 'Encrypting credit card data in charge log:';
	    $out .= "\n* ".$objRow->RowCount().' records to process';
	    while ($objRow->NextRow()) {
		$intChecked++;
		$row = $objRow->Row;

		$strNumEncrOld = $row['Encrypted'];
		$objRow->Encrypt(TRUE,FALSE);
		$strNumEncrNew = $objRow->Encrypted;
		if ($strNumEncrOld != $strNumEncrNew) {
		    $intChanged++;
		}
	    }
	    $strStats = $intChecked.' row'.Pluralize($intChecked).' processed, ';
	    $strStats .= $intChanged.' row'.Pluralize($intChanged).' altered';
	    $objLogger->LogEvent(__METHOD__,NULL,$strStats,NULL,FALSE,FALSE);

	    $out .= "\n* $intChecked row".Pluralize($intChecked).' processed';
	    $out .= "\n* $intChanged row".Pluralize($intChanged).' altered';

	} else {
	    $objLogger->LogEvent(__METHOD__,NULL,'CustCharges: No records found to process',NULL,FALSE,FALSE);
	    $out = 'No card data currently in charge log.';
	}
	return $out;
    }
*/
    /*----
      NOTE: This code was adapted heavily from VbzAdminCustCards::DoAdminDecrypt. They should probably both use a helper class or something.
	...except that all the encryption stuff is being rewritten anyway.
    */
/* OBSOLETE
    public function DoAdminDecrypt() {
	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'decrypting data',NULL,FALSE,FALSE);

	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $out = "\n\nDecrypting charges: ";
	    $intFound = 0;
	    while ($objRow->NextRow()) {
		$intFound++;
		$objRow->Decrypt(TRUE);	// decrypt and save
		$strNumEncrNew = $objRow->Encrypted;
	    }
	    $intRows = $objRow->RowCount();
	    $intMissing = $intRows - $intFound;
	    if ($intMissing) {
		$strStat = $intFound.' row'.Pluralize($intFound).' out of '.$intRows.' not decrypted!';
		$out .= "'''ERROR''' - $strStat!";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
	    } else {
		$strStat = $intRows.' row'.Pluralize($intRows);
		$out .= "'''OK''' - $strStat decrypted successfully";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' decrypted successfully',NULL,FALSE,FALSE);
	    }
	} else {
	    $out = 'No charges to decrypt!';
	}
	return $out;
    }
*/
    /*----
      NOTE: This code was adapted heavily from VbzAdminCustCards::AdminPlainClear. They should probably both use a helper class or something.
	...except that all the encryption stuff is being rewritten anyway.
    */
/* OBSOLETE
    public function AdminPlainClear() {
	global $wgOut;

    // ACTION: Clear plaintext data for all rows that have encrypted data
	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'clearing unencrypted sensitive data',NULL,FALSE,FALSE);

	$arUpd = array(
	  'CardNumExp' => 'NULL',
	  );
	$this->Update($arUpd,'Encrypted IS NOT NULL');
	$intRows = $this->objDB->RowsAffected();
	$strStat = $intRows.' row'.Pluralize($intRows).' modified';
	$out = "\n\n'''OK''': $strStat";
	$wgOut->addWikiText($out,TRUE);
	$objLogger->LogEvent(__METHOD__,NULL,'plaintext data cleared from card records, '.$strStat,NULL,FALSE,FALSE);
    }
*/
}
class VbzAdminOrderChg extends clsDataSet {
    /*####
      BOILERPLATE: event logging functions
      HISTORY:
	2010-10-25 added event logging using helper class
	2010-10-26 StartEvent(), FinishEvent()
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
  /*%%%%
    SECTION: boilerplate admin functions
    HISTORY:
      2010-10-25 added
  */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    /*----
      ACTION: Redirect to the url of the admin page for this object
      HISTORY:
	2010-11-26 copied from VbzAdminCatalog to clsRstkRcd
	2011-01-02 copied from clsRstkRcd to VbzAdminOrderTrxact
	2011-03-31 copied from VbzAdminOrderTrxact to VbzAdminOrderChg
    */
    public function AdminRedirect(array $iarArgs=NULL) {
	return clsAdminData::_AdminRedirect($this,$iarArgs);
    }
  /*%%%%
    SECTION: field access
  */
    public function CardObj() {
	$idCard = $this->ID_Card;
	return $this->objDB->CustCards()->GetItem($idCard);
    }
    public function OrderObj() {
	$idOrder = $this->ID_Order;
	return $this->objDB->Orders()->GetItem($idOrder);
    }
    /*-----
      ACTION: Renders table for this dataset
      INPUT: iArgs[]
	descr: appended to "no charges" when there are no records in the dataset
    */
    public function AdminTable(array $iArgs=NULL) {
	global $vgPage;

	$vgPage->UseWiki();

	if ($this->hasRows()) {
	    $out = "\n{|\n|-\n! ID || Card || Order || Trx || $ total || $ sold || $ s/h || When Ent. || When Xmt || OK || Notes";
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;


		$row = $this->Row;
		$id = $row['ID'];
		$strCard = $this->SafeCardData();
		$ftCard = $this->CardObj()->AdminLink($strCard);
		$objOrd = $this->OrderObj();
		$ftOrd = $objOrd->AdminLink($objOrd->Number);
		$idTrx = $row['ID_Trxact'];
		$strAmtTotal = $row['AmtTrx'];
		$strAmtSold = $row['AmtSold'];
		$strAmtShip = $row['AmtShip'];
		$strAmtTax = $row['AmtTax'];
		$dtWhenEnt = $row['WhenEntered'];
		$dtWhenXmt = $row['WhenXmitted'];
		$isVoid = !is_null($row['WhenVoided']);
		$isSuccess = $row['isSuccess'];
		$strNotes = $row['Notes'];


		$htID = "'''".$this->AdminLink()."'''";

		if ($isVoid) {
		    $wtStyle .= ' text-decoration: line-through;';
		    $htOk = 'VOID';
		} else {
		    $htOk = $isSuccess?'&radic;':'';
		}


		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $htID "
		  ."|| $ftCard "
		  ."|| $ftOrd "
		  ."|| $idTrx "
		  ."|| align=right | $strAmtTotal "
		  ."|| align=right | $strAmtSold "
		  ."|| align=right | $strAmtShip "
		  ."|| $dtWhenEnt "
		  ."|| $dtWhenXmt "
		  ."|| align=center | $htOk "
		  ."|| $strNotes";
	    }
	} else {
	    $strDescr = nz($iArgs['descr']);
	    $out = "\nNo charges$strDescr.";
	}
	return $out;
    }
    /*----
      HISTORY:
	2013-11-07 The code seems to be expecting a field called "CardNumExp", but there ain't no such.
	  I'm guessing that this table has been revised since then, for improved security, and it looks
	  like what we want is just the contents of the "CardSafe" field.
    */
    protected function SafeCardData() {
	$row = $this->Row;

	if (array_key_exists('CardSafe',$row)) {
	    $out = $row['CardSafe'];
	} else {
	    $out = "''no card data''";
	}
/*
	$idCard = $row['ID_Card'];
	$strCard = $row['CardExp'];

	if (empty($idCard)) {
	    if (empty($strCard)) {
		$out = "''no card data''";
	    } else {
		$out = '...'.substr($strCard,-13);
	    }
	} else {
	    $objCard = $this->objDB->CustCards()->GetItem($idCard);
	    $out = $objCard->SafeString();
	}
*/
	return $out;
    }
    public function AdminPage() {
	global $wgRequest;
	global $vgPage,$vgOut;

	$vgPage->UseHTML();

	$doEdit = $vgPage->Arg('edit');
	$strAct = $vgPage->Arg('do');

	$doVoid = ($strAct == 'void');

	if ($doVoid) {
	    $arEv = array(
	      'code'	=> 'VOID',
	      'descr'	=> 'voiding the charge',
	      'where'	=> __METHOD__
	      );
	    $this->StartEvent($arEv);
	    $arUpd = array(
	      'WhenVoided'	=> 'NOW()'
	      );
	    $this->Update($arUpd);
	    $this->FinishEvent();
	    $this->AdminRedirect();
	}

	if ($wgRequest->getBool('btnSave')) {
	    // handle standard edit
	    $this->BuildEditForm(FALSE);
	    $this->AdminSave();		// save edit to existing package
	} elseif ($wgRequest->getBool('btnUpd')) {
	    // 1: calculate updates to be done
	    $strDescr = 'charge ';

	    // handle charge status update
	    $isAuthOk = TRUE;
	    $isVeriOk = TRUE;
	    $arUpdAuth = array();
	    if ($wgRequest->GetBool('doAccept')) {

		$intAccept = $wgRequest->GetInt('doAccept');
		$isAuthOk = ($intAccept > 0);
		$strAuth = $wgRequest->GetText('Confirmation');
		$strDescr .= '[processed: '.($isAuthOk?'ok':'FAIL').']';
		$arUpdAuth = array(
		  'Confirmation'	=> SQLValue($strAuth),
		  'WhenXmitted'		=> 'NOW()',
		  'isSuccess'		=> SQLValue($isAuthOk)
		  );
	    }
	    $arUpdVeri = array();
	    if ($wgRequest->GetBool('doVerify')) {
		$intVerify = $wgRequest->GetInt('doVerify');
		$chVerify = $wgRequest->GetText('AVSRespCode');
		if ($intVerify != 0) {
		    $isVeriOk = ($intVerify > 0);
		    $strDescr .= '[verified: '.($isVeriOk?'ok':'FAIL').' (code '.$chVerify.')]';
		    $arUpdVeri = array(
		      'AVSRespCode'	=> SQLValue($chVerify),
		      'WhenDecided'	=> 'NOW()',
		      'isAccepted'	=> SQLValue($isVeriOk)
		      );
		}
	    }
	    // 2: log the update attempt
	    $arEv = array(
	      'descr'	=> $strDescr,
	      'where'	=> __METHOD__,
	      'code'	=> 'ED'
	      );
	    $this->StartEvent($arEv);

	    // 3: do the update
	    $arUpd = array_merge($arUpdAuth,$arUpdVeri);
	    $isOk = $isAuthOk && $isVeriOk;
	    if (!$isOk) {
		$arUpd['WhenHeldUp'] = 'NOW()';
	    }
	    $this->Update($arUpd);
	    global $sql;
	    $vgOut->AddText('SQL: '.$sql);
	    $this->Reload();
		// TO DO: PULL ORDER IF WhenHeldUp NOT NULL

	    // 4: log update completion
	    $this->FinishEvent();
	}

	$doProcess = FALSE;
	$doVerify = FALSE;
	if (is_null($this->WhenXmitted)) {
	    $strActDescr = 'Process ';
	    $doProcess = TRUE;
	    $doVerify = TRUE;	// allow processing & verification in one step
	} else {
	    if (is_null($this->WhenDecided)) {
		$strActDescr = 'Verify ';
		$doVerify = TRUE;
	    } else {
		$strActDescr = '';
	    }
	}

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strActDescr.'Card Charge',NULL);
	$objSection->ToggleAdd('edit','edit charge record');
	$out = $objSection->Generate();

	// later, work out some way to make this editable via wiki
	$out .= 'Useful link: [<a href="http://paypal.com">PayPal</a>]<br>';

	$doForm = $doEdit || $doProcess || $doVerify;
	if ($doForm) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $htPath = $vgPage->SelfURL($arLink);
	    $out .= "\n<form method=post action=\"$htPath\">";
	    $this->BuildEditForm(FALSE);
	}

	if ($doProcess) {
	    $ctrlConf = $this->objForm->Render('Confirmation');
	} else {
	    $ctrlConf = $this->Confirmation;
	}
	$chCode = $this->AVSRespCode;
	if ($doVerify) {
	    $ctrlVerif = $this->AddrVerifDropDown('AVSRespCode',$chCode);
	} else {
	    $arCodes = $this->AddrVerifCodes();
	    $ctrlVerif = $chCode.' ('.$arCodes[$chCode].')';
	}

	if ($doEdit) {
	    $objForm = $this->objForm;
	    $ctrlCard		= $this->objForm->Render('CardNumExp');
	    $ctrlAddr		= $this->objForm->Render('CardBillAddr');
	    $ctrlAmtTrx		= $this->objForm->Render('AmtTrx');
	    $ctrlAmtSold	= $this->objForm->Render('AmtSold');
	    $ctrlAmtShip	= $this->objForm->Render('AmtShip');
	    $ctrlAmtTax		= $this->objForm->Render('AmtTax');
	    $ctrlWhenEnt	= $this->objForm->Render('WhenEntered');
	    $ctrlWhenXmt	= $this->objForm->Render('WhenXmitted');
	    $ctrlWhenHld	= $this->objForm->Render('WhenHeldUp');
	    $ctrlWhenDcd	= $this->objForm->Render('WhenDecided');
	    $ctrlWhenVoid	= $this->objForm->Render('WhenVoided');
	} else {
	    $ctrlAmtTrx =  $this->AmtTrx;
	    $ctrlAmtSold = $this->AmtSold;
	    $ctrlAmtShip = $this->AmtShip;
	    $ctrlAmtTax = $this->AmtTax;

	    $ctrlCard = $this->CardNumExp;
	    $ctrlAddr = '<pre>'.$this->CardBillAddr.'</pre>';
	    if ($doProcess) {
		// show accepted/declined choice
		$ctrlWhenXmt =
		  '<input type=radio name=doAccept value=1>accept '
		  .'<input type=radio name=doAccept value=-1>decline';
	    } else {
		$ctrlWhenXmt = $this->WhenXmitted;
	    }
	    if ($doVerify) {
		// show allow/reject choice
		$ctrlWhenDcd =
		  '<input type=radio name=doVerify value=1>allow '
		  .'<input type=radio name=doVerify value=-1>reject'
		  .'<input type=radio name=doVerify value=0>unknown';
	    } else {
		$ctrlWhenDcd = $this->WhenDecided;
	    }
	    $ctrlWhenEnt = $this->WhenEntered;
	    $ctrlWhenHld = $this->WhenHeldUp;
	    $dtWhenVoid = $this->Value('WhenVoided');
	    if (is_null($dtWhenVoid)) {
		$vgPage->ArgsToKeep(array('page','id'));
		$ftLink = $vgPage->SelfLink(array('do'=>'void'),'void now','void this charge without processing');
		$ctrlWhenVoid = " [ $ftLink ]";
	    } else {
		$ctrlWhenVoid = $dtWhenVoid;
	    }
	}

	//$strCard = $this->CardTypeName().' '.$this->CardNum.$vgOut->Italic(' exp ').$this->ShortExp();
	$out .= $vgOut->TableOpen();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Status</b>','align=right');
	  $strStatus = (($this->isSuccess)?' SUCCESS':'').(($this->isTest)?' TEST':'');
	  $out .= $vgOut->TblCell($strStatus);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Order</b>:','align=right');
	  $objOrd = $this->OrderObj();
	  $out .= $vgOut->TblCell($objOrd->AdminLink($objOrd->Number));
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Card</b>:','align=right');
	  $objCard = $this->CardObj();
	  $out .= $vgOut->TblCell($ctrlCard);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('object:','align=right');
	  $objCard = $this->CardObj();
	  $out .= $vgOut->TblCell($objCard->AdminLink($objCard->ShortNumExpName()));
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Address</b>:','align=right valign=top');
	  $out .= $vgOut->TblCell($ctrlAddr);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Total</b>:','align=right');
	  $out .= $vgOut->TblCell('$'.$ctrlAmtTrx);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Sale</b>:','align=right');
	  $out .= $vgOut->TblCell('$'.$ctrlAmtSold);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Shipping</b>:','align=right');
	  $out .= $vgOut->TblCell('$'.$ctrlAmtShip);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Tax</b>:','align=right');
	  $out .= $vgOut->TblCell('$'.$ctrlAmtTax);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Time stamps</b>:','align=right');
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();

	  $ft = $vgOut->TableOpen();
	  $ft .= $vgOut->TblRowOpen();
	    $ft .= $vgOut->TblCell('<b>Entered</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlWhenEnt);
	  $ft .= $vgOut->TblRowShut();
	  $ft .= $vgOut->TblRowOpen();
	    $ft .= $vgOut->TblCell('<b>Processed</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlWhenXmt);
	    $ft .= $vgOut->TblCell('<b>Transaction ID</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlConf);
	  $ft .= $vgOut->TblRowShut();
	  $ft .= $vgOut->TblRowOpen();
	    $ft .= $vgOut->TblCell('<b>Verified</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlWhenDcd);
	    $ft .= $vgOut->TblCell('<b>Verification code</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlVerif);
	  $ft .= $vgOut->TblRowShut();
	  $ft .= $vgOut->TblRowOpen();
	    $ft .= $vgOut->TblCell('<b>Held up</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlWhenHld);
	  $ft .= $vgOut->TblRowShut();
	  $ft .= $vgOut->TblRowOpen();
	    $ft .= $vgOut->TblCell('<b>Voided</b>:','align=right');
	    $ft .= $vgOut->TblCell($ctrlWhenVoid);
	  $ft .= $vgOut->TblRowShut();
	  $ft .= $vgOut->TableShut();

	  $out .= $vgOut->TblCell($ft,'align=center colspan=2 bgcolor=#8888ff');
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TableShut();

	if ($doForm) {
	    if ($doEdit) {
		$out .= '<input type=submit name=btnSave value="Save">';
	    } else {
		$out .= '<input type=submit name=btnUpd value="Update">';
	    }
	    $out .= '</form>';
	}

	$vgOut->AddText($out);

	// events
	$objSection = new clsWikiSection($objPage,'Events',NULL,3);
	$out = $objSection->Generate();
	$vgOut->AddText($out); $out = '';
	$vgPage->UseHTML();
	$out = $this->EventListing();
	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';
	$vgOut->addText($out); $out = '';
    }
    private function BuildEditForm($iNew) {
	global $vgOut;
	// create fields & controls

	if (is_null($this->objFlds)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('CardNumExp'),	new clsCtrlHTML(array('size'=>30)));
	    $objForm->AddField(new clsField('CardBillAddr'),	new clsCtrlHTML_TextArea(array('height'=>4,'width'=>30)));
	    $objForm->AddField(new clsField('Confirmation'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('AVSRespCode'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtTrx'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtSold'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtShip'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtTax'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenEntered'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenXmitted'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenHeldUp'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenDecided'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenVoided'),	new clsCtrlHTML());

	    $this->objForm = $objForm;
	}
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-03-24 copied from VbzAdminItem to VbzAdminOrderChg
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      RETURNS: array of address verification codes
	array[code letter] = description
    */
    protected function AddrVerifCodes() {
	return array(
	  'A' => 'bad zip, ok addr',
	  'B' => 'no zip, ok addr (non-US A)',
	  'C' => 'bad addr+zip (non-US N)',
	  'D' => 'ok addr+zip (non-US X/Y)',
	  'E' => 'F2F only',
	  'F' => 'ok addr+zip (UK X/Y)',
	  'G' => 'unavailable (global)',
	  'I' => 'unavailable (non-US)',
	  'N' => 'bad addr+zip',
	  'P' => 'no zip, ok addr (non-US Z)',
	  'R' => 'retry later',
	  'S' => 'not supported',
	  'U' => 'addr info unavailable',
	  'W' => 'bad addr, ok zip+4',
	  'X' => 'ok addr+zip+4',
	  'Y' => 'ok addr+zip',
	  'Z' => 'bad addr, ok zip'
	  );
    }
    /*----
      RETURNS: HTML for a drop-down box listing all the available codes
    */
    protected function AddrVerifDropDown($iName,$iVal) {
	$out = "\n".'<select name="'.$iName.'">';
	$arCodes = $this->AddrVerifCodes();
	foreach ($arCodes as $code => $descr) {
	    $out .= "\n".'<option value="'.$code.'"';
	    if ($code == $iVal) {
		$out .= ' selected';
	    }
	    $out .= '>'.$code.' - '.$descr.'</option>';
	}
	$out .= '</select>';
	return $out;
    }

    /* *****
      ENCRYPTION
    */

/* OBSOLETE
    public function CryptObj() {
	return $this->Engine()->CryptObj();
    }
*/
    /*----
      NOTE: This code was adapted heavily from clsCustCard::Encrypt. They should probably both use a helper class or something.
    */
/* OBSOLETE
    public function Encrypt($iDoSave,$iDoWipe) {
	if (is_null($this->CardNumExp)) {
	    // do nothing (do we want to raise an error?)
	    // this might happen if card data isn't completely decrypted after a migration or backup
	} else {
	    // encrypt numbers
	    // whatever separator is used, make sure it doesn't have any special meaning to regex
	    //$strRawData = ':'.$this->CardNum.':'.$this->CardCVV.':'.$this->CardExp;
	    $strRawData = $this->Value('CardNumExp');
	    $this->_strPlain = $strRawData;
	    $strEncrypted = $this->CryptObj()->encrypt($strRawData);
	    $this->Encrypted = $strEncrypted;
//echo 'PLAIN:['.$strRawData.'] ENCRYPTED:['.$strEncrypted.']<br>';

	    if ($iDoWipe) {
		$this->CardNumExp = NULL;
	    }
	    if ($iDoSave) {
		$arUpd['Encrypted'] = SQLValue($this->Encrypted);
		if ($iDoWipe) {
		    $arUpd['CardNumExp'] = 'NULL';
		}
		$this->Update($arUpd);
	    }
	}
    }
*/
    /*----
      NOTE: This code was adapted heavily from clsCustCard::Encrypt. They should probably both use a helper class or something.
    */
/* OBSOLETE
    public function Decrypt($iDoSave) {
	$sEncrypted = $this->Encrypted;
	if (empty($sEncrypted)) {
	    $sDecrypted = NULL;
	} else {
	    $sDecrypted = $this->CryptObj()->decrypt($sEncrypted);
	}
	$this->CardNumExp = $sDecrypted;

	if ($iDoSave) {
	    $arUpd['CardNumExp'] = SQLValue($sDecrypted);
	    $this->Update($arUpd);
	}
    }
*/
}
class VbzAdminOrderEvents extends clsSysEvents {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderEvent');
    }
}
class VbzAdminOrderEvent extends clsSysEvent {
    public function AdminTable(array $iarArgs) {
	if ($this->hasRows()) {
	    $out = "\n{|\n|-\n! ID || Started || Finished|| Where || Who || What";
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;
		$id = $row['ID'];

		$htWho = WhoString2_wt($row);
		$htWhat = $row['Descr'];
		if (!empty($row['Notes'])) {
		    $htWhat .= " ''{$row['Notes']}''";
		}

		$strWhenSt	= $row['WhenStarted'];
		$strWhenFi	= $row['WhenFinished'];
		$dtWhenSt	= strtotime($strWhenSt);
		$dtWhenFi	= strtotime($strWhenFi);
		$strDateSt	= is_null($strWhenSt)?'-':(date('Y-m-d',$dtWhenSt));
		$strDateFi	= is_null($strWhenFi)?'-':(date('Y-m-d',$dtWhenFi));
		$strTimeSt	= is_null($strWhenSt)?'-':(date('H:i',$dtWhenSt));
		$strTimeFi	= is_null($strWhenFi)?'-':(date('H:i',$dtWhenFi));
		$strDateLater = empty($dtWhenFi)?$strDateSt:$strDateFi;
		if ($strDateLater != $strDateLast) {
		    $strDateLast = $strDateLater;
		    $out .= "\n|- style=\"background: #444466; color: #ffffff;\"\n| colspan=6 | '''$strDateLast'''";
		}

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $id "
		  ."|| $strTimeSt "
		  ."|| $strTimeFi "
		  ."|| {$row['EvWhere']} "
		  ."|| $htWho "
		  ."|| $htWhat";
	    }
	} else {
	    $strDescr = $iarArgs['descr'];
	    $out = "\nNo events$strDescr.";
	}
	return $out;
    }
}
// order pulls
class VbzAdminOrderPulls extends clsTable {
    const TableName='ord_pull';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminOrderPull');
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }
    public function Types() {
	if (!isset($this->objTypes)) {
	    $this->objTypes = $this->objDB->OrdPullTypes();
	}
	return $this->objTypes;
    }
    /*-----
      ACTION: Get all Pull records for an Order
    */
    public function GetOrder($iID) {
	$objRows = $this->GetData('ID_Ord='.$iID);
	$objRows->ID_Ord = $iID;	// make sure this is set, regardless of whether there is data
	return $objRows;
    }
    protected function Add($iOrdID, $iTypeID, $iNotes) {
    // ACTION: This *only* adds a Pull record; use Pull() to also mark the Order record
	global $vgUserName;

	$sqlNotes = $this->objDB->SafeParam($iNotes);
	$arIns = array(
	  'ID_Ord'	=> $iOrdID,
	  'ID_Type'	=> $iTypeID,
	  'WhenPulled'	=> 'NOW()',
	  'NotesPull'	=> SQLValue($iNotes),
	  'VbzUser'	=> SQLValue($vgUserName),
	  'SysUser'	=> '"'.$_SERVER["SERVER_NAME"].'"',
	  'Machine'	=> '"'.$_SERVER["REMOTE_ADDR"].'"'
	  );
	$this->Insert($arIns);
	$this->ID = $this->objDB->NewID(__METHOD__);
    }
    public function Pull(VbzAdminOrder $iOrder, $iType, $iNotes) {
	$this->Add($iOrder->ID,$iType,$iNotes);
	$iOrder->Pull($this->ID);
    }
}
class VbzAdminOrderPull extends clsDataSet {
    private $objTypes;
    private $objOrd;

    public function IsPulled() {
	return (!is_null($this->WhenPulled) && is_null($this->WhenFreed));
    }
    public function Type() {
	return $this->Table->Types()->GetItem($this->ID_Type);
    }
    public function TypeName() {
	return $this->Type()->Name;
    }
    /*=====
      USAGE: Must be called only on the ACTIVE pull - get that from the order object
    */
    public function UnPull($iNotes) {
	global $vgUserName;
	if ($this->IsPulled()) {
	    $sqlNotes = $this->objDB->SafeParam('(by '.$vgUserName.') '.$iNotes);
	    $arUpd = array('WhenFreed' => 'NOW()','NotesFree' => $sqlNotes);
	    $this->Update($arUpd);
	    return NULL;
	} else {
	    $this->objDB->LogEvent(__METHOD__,'Notes="'.$iNotes.'"','attempting double-release','DRL',TRUE,FALSE);
	    return 'attempting double-release of Pull ID '.$this->ID;
	}
    }
}
class clsOrderPullTypes extends clsTableCache {
    const TableName='ord_pull_type';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsOrderPullType');
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }
    public function ComboBox($iName,$iWhich=NULL) {
	$objRows = $this->GetData();
	$out = '<select name="'.$iName.'">';
	while ($objRows->NextRow()) {
	    $id = $objRows->KeyValue();
	    if ($id == $iWhich) {
		$htSelect = " selected";
	    } else {
		$htSelect = '';
	    }
	    $htName = $objRows->Value('Name');
	    $out .= "\n<option$htSelect value=\".$id>$htName</option>";
	}
	$out .= "\n</select>";
	return $out;
    }
}
class clsOrderPullType extends clsDataSet {
}
