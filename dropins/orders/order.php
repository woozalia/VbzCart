<?php
/*
  FILE: dropins/orders/order.php -- customer order administration dropin for VbzCart
  HISTORY:
    2010-10-16 Extracted order management classes from SpecialVbzAdmin.php
    2013-12-15 Adapting for drop-in module system.
*/

// Transaction Types
define('KI_ORD_TXTYPE_ITEM_SOLD',11);	// total cost of items sold
define('KI_ORD_TXTYPE_PERITM_SH',1);	// per-item shipping charge total
define('KI_ORD_TXTYPE_PERPKG_SH',2);	// per-package shipping charge
define('KI_ORD_TXTYPE_PAID_CC',6);	// payment: credit card

class VCM_Orders extends clsOrders {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VC_Order');	// override parent
	  $this->ActionKey(KS_PAGE_KEY_ORDER);
    }

    // ++ CLASS NAMES ++ //

    protected function LinesClass() {
	return KS_CLASS_ORDER_ITEMS;
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLE ACCESS ++ //

    public function LineTable($id=NULL) {
	return $this->Engine()->Make($this->LinesClass(),$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ ADMIN INTERFACE ++ //

    protected function RenderSearch() {
	$oPage = $this->Engine()->App()->Page();
	//$oSkin = $oPage->Skin();

	$sPfx = $this->ActionKey();
	$htSearchOut = NULL;

	$sSearchName = $sPfx.'-needle';
	$sInput = $oPage->ReqArgText($sSearchName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forText($sInput);
	    $htSearchOut .= $rs->Listing('No matching order records.');
	}
	$htFind = '"'.htmlspecialchars($sInput).'"';

	$sOrderName = $sPfx.'-order';
	$sInput = $oPage->ReqArgText($sOrderName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forOrdNum($sInput);
	    $htSearchOut .= $rs->Listing('No matching order records.');
	}
	$htOrd = '"'.htmlspecialchars($sInput).'"';

	// build forms

	$htSearchHdr = $oPage->SectionHeader('Search',NULL,'section-header-sub');
	$htSearchForm = <<<__END__
<form method=post>
  Search for names or addresses containing:
  <input name="$sSearchName" size=40 value=$htFind>
  <input type=submit name=btnSearch value="Go">
</form>
<form method=post>
  Search for order number:
  <input name="$sOrderName" size=6 value=$htOrd>
  <input type=submit name=btnSearch value="Go">
</form>
__END__;

	$out = $htSearchHdr.$htSearchForm;
	if (!is_null($htSearchOut)) {
	    $out .= $oPage->SectionHeader('Search Results',NULL,'section-header-sub')
	      .$htSearchOut;
	}

	return $out;
    }
    public function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	// set up titlebar menu
	$arPage = $oPage->PathArgs();
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option($arPage,'show.pulled',	NULL,'pulled',	NULL,'show pulled orders'),
	  new clsActionLink_option($arPage,'show.open',	NULL,'open',	NULL,'show orders which have not been completed'),
	  new clsActionLink_option($arPage,'show.shut',	NULL,'shut',	NULL,'show orders which have been closed'),
	  );
	$oPage->PageHeaderWidgets($arActs);

	// get current menu selections
	$doShowPulls = $oPage->PathArg('show.pulled');
	$doShowShut = $oPage->PathArg('show.shut');
	$doShowOpen = $oPage->PathArg('show.open');

	$doShow = FALSE;
	if ($doShowOpen) {
	    $arFilt[] = '(ID_Pull IS NULL) AND (WhenClosed IS NULL)';
	    $doShow = TRUE;
	    $doCalc = TRUE;	// do additional calculations to get order's status
	}
	if ($doShowShut) {
	    $arFilt[] = '(WhenClosed IS NOT NULL)';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	if ($doShowPulls) {
	    $arFilt[] = '(ID_Pull IS NOT NULL)';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	if ($doShow) {
	    $sqlFilt = Array_toFilter($arFilt);
	    $objRows = $this->GetData($sqlFilt,NULL,'SortPfx, Number DESC');
	    $cntRows = $objRows->RowCount();
	    $out = 'Showing '.$cntRows.' order'.Pluralize($cntRows).' ('.$sqlFilt.'):';
	    $out .= $objRows->Listing("No records found.",$doCalc);
	} else {
	    $out = 'No filter selected; not displaying any orders.';
	}
	$out .= $this->RenderSearch();
	return $out;
    }

    // -- ADMIN INTERFACE -- //
    // ++ BUSINESS LOGIC ++ //

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

    // -- BUSINESS LOGIC -- //
}
/*%%%%
  HISTORY:
    2010-11-26 copied AdminRedirect() from VbzAdminCatalog to clsRstkRcd
    2011-01-02 copied AdminRedirect() from clsRstkRcd to VbzAdminOrderTrxact
    2011-09-18 boilerplate logging methods added - copied from VbzAdminOrderItem
    2011-12-20 copied AdminRedirect() from VbzAdminOrderTrxact to VbzAdminOrder
    2013-01-14 adapted as dropin module
*/
class VC_Order extends clsOrder {
    private $objPull;
    private $arBalTrx;
    // object cache
    private $rsTrx;	// transaction records
    private $rsLines;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->rsTrx = NULL;
	$this->rsLines = NULL;
    }

    // -- SETUP -- //
    // ++ HELPERS FOR BOILERPLATE FX ++ //

    public function AdminName() {
	return $this->Value('Number');
    }
    public function AdminLink_name($iPopup=NULL,array $iarArgs=NULL) {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $txt = $this->AdminName();
	    return $this->AdminLink($txt,$iPopup,$iarArgs);
	}
    }

    // -- HELPERS FOR BOILERPLATE FX -- //
    // ++ OBJECT STATUS ++ //
    
    private $arTblArgs;
    protected function TableArg($sName,$sVal=NULL) {
	if (!is_null($sVal)) {
	    $this->arTblArgs[$sName] = $sVal;
	}
	return $this->arTblArgs[$sName];
    }
    protected function TableArgs(array $arArgs=NULL) {
	if (!is_null($arArgs)) {
	    $this->arTblArgs = $arArgs;
	}
	return $this->arTblArgs;
    }
    
    private $doEdit;
    protected function DoEdit($fOn=NULL) {
	if (!is_null($fOn)) {
	    $this->doEdit = $fOn;
	}
	return $this->doEdit;
    }
    /*
      TODO: This is only ever set to FALSE -- which may be broken coding.
	Either fix or eliminate.
    */
    private $areTotalsOk;
    protected function AreTotalsOk($fYes=NULL) {
	if (!is_null($fYes)) {
	    $this->areTotalsOk = $fYes;
	}
	return $this->areTotalsOk;
    }
    
    // -- OBJECT STATUS -- //
    // ++ FIELD ACCESS ++ //

    protected function PullID() {
	return $this->Value('ID_Pull');
    }
    protected function CardID() {
	return $this->Value('ID_BuyerCard');
    }
    //--
    protected function QuotedSaleAmt() {
	return $this->Value('WebTotal_Merch');
    }
    protected function QuotedShipAmt() {
	return $this->Value('WebTotal_Ship');
    }
    protected function QuotedFinalAmt() {
	return $this->Value('WebTotal_Final');
    }
    //--
    public function Pulled($iPull=NULL) {
	return !is_null($this->PullID());
    }
    public function PulledText() {
	if ($this->Pulled()) {
	    return $this->PullObj()->TypeName();
	} else {
	    return NULL;
	}
    }
    public function HasRecip() {
	$idCust = $this->Value('ID_Recip');
	return !empty($idCust);
    }
    /*----
      RETURNS: TRUE if order has any important missing fields and has not been pulled
      HISTORY:
	2014-01-17 I'm going to assume, for now, that BuyerName will always be filled in first.
	  This may need to be refined later in light of real-world experience.
    */
    public function NeedSetup() {
	$sBuyer = $this->BuyerName();
	// needs setup if there's no buyer name -- but ignore pulled orders
	return ((is_null($sBuyer)) && !$this->Pulled());
    }
    private function CCardStr() {
	$id = $this->CardID();
	if (empty($id)) {
	    return "<i>N/A</i>";
	} else {
	    $objRow = $this->objDB->CustCards()->GetItem($id);
	    return $objRow->SafeString();
	}
    }

    // -- FIELD ACCESS -- //
    // ++ DATA CLASS NAMES ++ //

    public function TrxactsClass() {
	return KS_CLASS_ORDER_TRXS;
    }
    public function CardsClass() {
	return KS_CLASS_CUST_CARDS;
    }
    protected function CartsClass() {
	if (clsDropInManager::IsReady('vbz.carts')) {
	    return KS_CLASS_ADMIN_CARTS;
	} else {
	    return parent::CartsClass();
	}
    }
    protected function ChargesClass() {
	return KS_CLASS_ORDER_CHARGES;
    }
    protected function MessagesClass() {
	return KS_CLASS_ORDER_MSGS;
    }
    /*----
      TODO: Make this not dependent on the vbz.ship drop-in module
	by testing for its existence and falling back gracefully
	to the non-admin class
    */
    protected function PackagesClass() {
	return KS_CLASS_PACKAGES;
    }
    protected function PullsClass() {
	return KS_CLASS_ORDER_PULLS;
    }

    // -- DATA CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    // TODO: make these protected or document why they are public

    /*----
      PUBLIC to minimize what other classes need to know about internals
      USED BY package class
    */
    public function LineTable($id=NULL) {
	return $this->Table()->LineTable($id);
    }
    protected function CustTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ADMIN_CUSTOMERS,$id);
    }
    public function CardTable($id=NULL) {
	return $this->Engine()->Make($this->CardsClass(),$id);
    }
    public function ChargeTable($id=NULL) {
	return $this->Engine()->Make($this->ChargesClass(),$id);
    }
    public function MessageTable($id=NULL) {
	return $this->Engine()->Make($this->MessagesClass(),$id);
    }
    public function TrxactTable($id=NULL) {
	return $this->Engine()->Make($this->TrxactsClass(),$id);
    }
    protected function PackageTable($id=NULL) {
	return $this->Engine()->Make($this->PackagesClass(),$id);
    }
    protected function PullTable($id=NULL) {
	return $this->Engine()->Make($this->PullsClass(),$id);
    }

    // -- DATA TABLES -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function PullObj() {
	$objPull = $this->PullTable($this->PullID());
	return $objPull;
    }
    /*-----
      RETURNS: Recordset of lines for this order, sorted by Seq
    */
    public function LinesData($iRefresh=FALSE) {
	if ($iRefresh || is_null($this->rsLines)) {
	    $tbl = $this->LineTable();
	    $this->rsLines = $tbl->GetData('ID_Order='.$this->KeyValue(),NULL,'Seq');
	}
	return $this->rsLines;
    }
    public function LineRecord_forItem($idItem) {
	$tbl = $this->LineTable();
	$rc = $tbl->GetData('(ID_Order='.$this->KeyValue().') AND (ID_Item='.$idItem.')');
	$rc->FirstRow();
	return $rc;
    }
    public function LineID_forItem($idItem) {
	$rc = $this->LineRecord_forItem($idItem);
	if ($rc->FirstRow()) {
	    return $rc->KeyValue();
	} else {
	    return NULL;
	}
    }
    /*-----
      RETURNS: Dataset of Packages for this order
      FUTURE: Should be named something like PkgsData()
    */
    public function Pkgs() {
	throw new exception('Pkgs() is deprecated; call PackageRecords().');
    }
    /*----
      INPUT:
	$useVoid: if TRUE, include voided packages in the recordset
    */
    public function PackageRecords($useVoid) {
	$sqlFilt = 'ID_Order='.$this->KeyValue();
	if (!$useVoid) {
	    $sqlFilt = "($sqlFilt) AND (WhenVoided IS NULL)";
	}
	$rs = $this->PackageTable()->GetData($sqlFilt);
	return $rs;
    }
    public function CardObj() {
	throw new exception('CardObj() is deprecated; call CardRecord().');
    }
    public function CardRecord() {
	$idCard = $this->CardID();
	return $this->CardTable($idCard);
    }
    /*----
      RETURNS: customer object for buyer, or NULL if buyer is not set
      HISTORY:
	2011-11-06 now checks to make sure buyer is set
    */
    public function BuyerObj() {	// deprecated alias
	return $this->BuyerRecord();
    }
    protected function BuyerRecord() {
	$idCust = $this->Value('ID_Buyer');
	if (empty($idCust)) {
	    $rcCust = NULL;
	} else {
	    $rcCust = $this->CustTable($idCust);
	}
	return $rcCust;
    }
    public function RecipObj() {
	return $this->RecipRecord();
    }
    protected function RecipRecord() {
	$idCust = $this->Value('ID_Recip');
	$rcCust = $this->CustTable($idCust);
	return $rcCust;
    }
    /*----
      RETURNS: recordset of transaction lines
    */
    protected function Data_Trx() {
	throw new exception('Data_Trx() is deprecated; call TrxRecords().');
    }
    protected function TrxRecords() {
	$id = $this->KeyValue();
	if (is_null($this->rsTrx)) {
	    $tbl = $this->TrxactTable();
	    $rs = $tbl->GetData('ID_Order='.$id,NULL,'WhenDone, ID');
	    $this->rsTrx = $rs;
	}
	return $this->rsTrx;
    }
    protected function BuyerString() {
	$out = $this->Value('BuyerName');
	if (is_null($out)) {
	    if ($this->HasBuyer()) {
		$rc = $this->BuyerRecord();
		$out = '<i>'.$rc->NameString().'</i>';
	    } else {
		$out = '<i>no buyer set</i>';
	    }
	}
	return $out;
    }
    protected function RecipString() {
	$out = $this->Value('RecipName');
	if (is_null($out)) {
	    if ($this->HasRecip()) {
		$rc = $this->RecipRecord();
		$out = '<i>'.$rc->NameString().'</i>';
	    } else {
		$out = '<i>no recipient set</i>';
	    }
	}
	return $out;
    }
    protected function WhoDescrip() {
	$sBuyer = $this->BuyerString();
	$sRecip = $this->RecipString();
	if ($sBuyer == $sRecip) {
	    return $sBuyer;
	} else {
	    return $sBuyer.' &rarr; '.$sRecip;
	}
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ DATA CALCULATIONS ++ //

    public function HasLines() {
	$objLines = $this->LinesData();
	if (is_null($objLines)) {
	    return FALSE;
	} else {
	    return $objLines->hasRows();
	}
    }
    /*----
      RETURNS: The number to use for Seq in the next Package to be created
    */
    public function NextPackageSeq() {
	$nSeq = $this->PackageTable()->NextSeq_forOrder($this->KeyValue());
	return $nSeq;
    }
    public function NextSeq() {
	throw new exception('NextSeq() is deprecated; call table->NextID().');
/*
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
*/
    }
    private $oTotals;
    protected function TotalsObject() {
	if (empty($this->oTotals)) {
	    $doEdit = $this->DoEdit();
	    // calculate line totals
	    $rs = $this->LinesData();	// get order lines
	    if ($rs->hasRows()) {
		$arTotItm = $rs->FigureTotals();

		$prcCalcShItm = $arTotItm['sh-itm'];
		$prcCalcShPkg = $arTotItm['sh-pkg'];
		$prcCalcShip = $prcCalcShItm + $prcCalcShPkg;
		$prcCalcSale = $arTotItm['cost-sell'];
		$prcCalcTotal = $prcCalcShip + $prcCalcSale;

		// get web totals
		$prcTotMerch = $this->Value('WebTotal_Merch');
		$prcTotShip = $this->Value('WebTotal_Ship');
		$prcTotFinal = $this->Value('WebTotal_Final');

		if ($doEdit) {
		    $objForm = $this->PageForm();
		    $htAmtMerch	= $objForm->ControlObject('WebTotal_Merch')->Render(TRUE);
		    $htAmtShip =  $objForm->ControlObject('WebTotal_Ship')->Render(TRUE);
		    $htAmtTotal = $objForm->ControlObject('WebTotal_Final')->Render(TRUE);
		} else {
		    $htAmtMerch = NULL;
		    $htAmtShip = NULL;
		    $htAmtTotal = NULL;
		}

		// create totals objects
		$this->oTotals = new cCartDisplay();
		$this->oTotals->AddItem(new clsCartTotal_admin('merch','Merchandise',$prcCalcSale,$prcTotMerch,$htAmtMerch));
		$this->oTotals->AddItem(new clsCartTotal_admin('ship','Shipping',$prcCalcShip,$prcTotShip,$htAmtShip));
		$this->oTotals->AddItem(new clsCartTotal_admin('final','Total',$prcCalcTotal,$prcTotFinal,$htAmtTotal));
	    } else {
		$this->oTotals = NULL;
	    }
	}
	return $this->oTotals;
    }
    /* DEPRECATED
    public function NextPkgSeq() {
//	$objRows = $this->PackageRecords();
//	$seq = NextSeq($objRows);
	$seq = $this->PackageTable()->NextID();
	return $seq;
    } */

    // -- DATA CALCULATIONS -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: initialization method called by dropin menu
    */
    public function MenuInit(array $arArgs=NULL) {
	return $this->AdminInit();
    }

    // -- DROP-IN API -- //
    // ++ BUSINESS LOGIC ++ //

    /*-----
      RETURNS: array of items; each item's value is a sub-array containing:
	* the quantity ordered for that item, in 'qty-ord'
	* any other fields listed in $iFields are initialized to zero
      USAGE: The resulting array can then be used as input to packages->FigureTotals(),
	which will adjust the remaining fields to reflect packages sent and received.
    */
    function QtysOrdered() {
	$t = $this->LineTable();
	$rs = $t->GetData('ID_Order='.$this->KeyValue());
	$arQtys = $rs->QtyArray();
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
	    $objPkgs = $this->PackageRecords(FALSE);	// don't include inactive records
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
	  array[item ID]['qty-rtn']
	  array[item ID]['qty-kld']
	  array[item ID]['qty-na']
	IF iItem is NOT NULL, then only array[iItem] is returned
      USAGE:
	* iItem is used by the function which displays order status for a given item
    */
    private $arPkgSums;
    public function ItemStats($iItem=NULL) {
	throw new exception('ItemStats() is deprecated; call PackageSums().');
    }
    public function PackageSums($idItem=NULL) {
	if (empty($this->arPkgSums)) {
	    $rcPkgs = $this->PackageRecords(FALSE);	// was Pkgs()
	    $this->arPkgSums = $rcPkgs->FigureTotals();
	}
	if (is_null($idItem)) {
	    return $this->arPkgSums;
	} else {
	    $nItem = (int)$idItem;	// not sure why this is needed
	    return $this->arPkgSums[$nItem];
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

    // -- BUSINESS LOGIC -- //
    // ++ ACTIONS ++ //

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
	$idTrx = $this->Engine()->NewID();
	$this->Data_Trx_uncache();

	$out .= '<br>Transaction ID '.$idTrx.' created.';

	//-- create the charge
	$arIns = array(
	  'ID_Order'	=> $this->ID,
	  'ID_Card'		=> $this->CardID(),
	  'ID_Trxact'	=> $idTrx,
	  'AmtTrx'		=> $dlrChg,
	  'AmtSold'		=> $dlrSale,
	  'AmtShip'		=> $dlrShip,
	  'CardNumExp'	=> SQLValue($objCard->SingleString()),
	  'CardBillAddr'	=> SQLValue($objCard->Address),
	  'WhenEntered'	=> 'NOW()'
	  );
	$this->ChargeTable()->Insert($arIns);
	$idChg = $this->Engine()->NewID();

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
		$rcEv = $this->StartEvent($arEv);
		$this->Update($arUpd);
		$this->Reload();
		$rcEv->Finish();
	    }
	} else {
	    $ftOut .= 'No items found in order!';
	}
	return $ftOut;
    }

    // -- ACTIONS -- //
    // ++ ADMIN INTERFACE ++ //

    static protected function ListingHeader($doCalcStats=FALSE) {
/* wiki version -- delete after successful conversion
	$out = "{| class=sortable \n|-\n! ID || Number || Status || Total Amt || created || closed";
	if ($iCalcStats) {
	    $out .= ' || qty ord || qty ok || qty Xed || QTY OPEN';
	}
*/
	$out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Number</th>
    <th>Status</th>
    <th>Who</th>
    <th>Total Amt</th>
    <th>created</th>
    <th>closed</th>
__END__;
	if ($doCalcStats) {
	    $out .= '<th>qty ord</th><th>qty ok</th><th>qty Xed</th><th>QTY OPEN</th>';
	}
	$out .= '</tr>';
	return $out;
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
    private function CCardLink() {
	$id = $this->CardID();
	if (empty($id)) {
	    return "<i>N/A</i>";
	} else {
	    $objRow = $this->CardTable($id);
	    $txtOut = $objRow->SafeString();
	    $ftOut = $objRow->AdminLink($txtOut);
	    return $ftOut;
	}
    }
    private function CCardChoose() {
	$idCard = $this->CardID();
	if (empty($idCard)) {
	    $idCard = NULL;
	}
	$idBuyer = $this->BuyerID();
	$out = $this->CardTable()->DropDown_forCust($idBuyer,$idCard);
	return $out;
    }
    /*----
      2011-09-18 created -- trying to tidy up order provisioning process
    */
    private function CartList() {

	$htOut = NULL;
	$arErrs = NULL;

	$idCart = $this->CartID();
	$idOrder = $this->KeyValue();

	if (is_null($idCart)) {
	    $arErrs[] = 'no default cart';
	} else {
	    $rcCart = $this->CartRecord();
	    $htOut .= $rcCart->Links_forSetup($idOrder);
	}
	$rsCart = $this->CartTable()->GetData('ID_Order='.$idOrder);
	if ($rsCart->HasRows()) {
	    $arOthers = NULL;
	    $cntOthers = 0;
	    while ($rsCart->NextRow()) {
		$id = $rsCart->KeyValue();
		if ($id != $idCart) {
		    $htOut .= ' ['.$rsCart->Links_forSetup($idOrder).']';
		}
	    }
	} else {
	    $arErrs[] = 'no carts point here';
	}

	$arOut['html'] = $htOut;
	$arOut['errs'] = $arErrs;
	return $arOut;
    }
    protected function RenderBalanceButtons() {
	if ($this->HasLines()) {
	    if ($this->TotalsObject()->FoundMismatch()) {
		$ftRecalcLink = $this->AdminLink(
		  'recalculate',
		  'add up totals for items in order',
		  array('do'=>'recalc')
		  );
		$ftBalBtns = "[$ftRecalcLink]";
	    } else {
		$ftBalBtns = '<font color=gray>Totals are correct</font>';
	    }
	} else {
	    $ftBalBtns = $this->Engine()->App()->Page()->Skin()->ErrorMessage('Order has no item lines.');
	}
	return $ftBalBtns;
    }
    /*----
      ACTION: Displays aggregated stats regarding the line items.
      TODO: Figure out why the display of the lines themselves isn't here too.
    */
    protected function AdminLines_NOT_USED() {
	$rs = $this->LinesData();	// get order lines
	if ($rs->hasRows()) {
	    $arTotItm = $rs->FigureTotals();
	} else {
	    $arTotItm = NULL;
	}

	$prcTotMerch = $this->Value('WebTotal_Merch');
	$prcTotShip = $this->Value('WebTotal_Ship');
	$prcTotFinal = $this->Value('WebTotal_Final');

	$prcCalcShItm = $arTotItm['sh-itm'];
	$prcCalcShPkg = $arTotItm['sh-pkg'];
	$prcCalcShip = $prcCalcShItm + $prcCalcShPkg;
	$prcCalcSale = $arTotItm['cost-sell'];
	$prcCalcTotal = $prcCalcShip + $prcCalcSale;

	if ($this->HasLines()) {
	    if ($this->TotalsObject()->FoundMismatch()) {
		$ftRecalcLink = $this->AdminLink(
		  'recalculate',
		  'add up totals for items in order',
		  array('do'=>'recalc'));
		$ftBalBtns = '['.$ftRecalcLink.']';
	    } else {
		$ftBalBtns = '<font color=gray>Totals are correct</font>';
		$arBalTrx = array(
		  'sale'	=> $prcCalcSale,
		  'ship'	=> $prcCalcShip,
		  'total'	=> $prcCalcTotal
		  );
	    }
	} else {
	    $ftBalBtns = $this->Engine()->App()->Page()->Skin()->ErrorMessage('Order has no item lines.');
	}

	$this->ftBalBtns = $ftBalBtns;
	return NULL;
    }
    /*----
      PURPOSE: Stuff to do before admin page is displayed
    */
    protected function AdminInit() {
	$oPage = $this->Engine()->App()->Page();
	$oPage->TitleString('ord#'.$this->Number());	// browser title
    }
    /*----
      PURPOSE: Starting point for admin page
	This method calls other methods to do the work, depending on input
    */
    protected function AdminPage() {
	$out = NULL;
	$oPage = $this->Engine()->App()->Page();
	$oPage->TitleString('Order #'.$this->Number());	// page title


	$id = $this->KeyValue();
	$strNum = $this->Value('Number');

	// set up titlebar menu
	clsActionLink_option::UseRelativeURL_default(TRUE);	// use relative URLs
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'rcpt',		'do','receipt',	NULL,'receipt for order #'.$strNum),
	  new clsActionLink_option(array(),'email',		'do',NULL,NULL,'send an email'),
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  //new clsActionLink_option(array(),'reload',		'do',NULL,NULL,'reload the page'),	// redundant?
	  );
	$oPage = $this->Engine()->App()->Page();
	$oPage->PageHeaderWidgets($arActs);

	// get user action selections

	$sDo = $oPage->PathArg('do');
	/*
	if ($sDo == 'cart') {
	    $out .= '<h2>Cart Setup</h2>';
	    $out .= $this->DoSetupCart();
	}
	*/
	$doSave = $oPage->ReqArgBool('btnSave');

	// handle actions
	$doEdit 	= FALSE;
	$doReceipt	= FALSE;
	$doEmail	= FALSE;
	switch ($sDo) {
	  case 'edit':		$doEdit = TRUE;		break;
	  case 'receipt':	$doReceipt = TRUE;	break;
	  case 'email':		$doEmail = TRUE;	break;
	  // 2011-09-24 $arBalTrx seems to reflect order totals, not current transaction balance
	  case 'charge':	$ftRecalcStat = $this->DoChargeCard($arTrxBal);	break;
	  case 'recalc':	$ftRecalcStat = $this->DoRecalcBal();			break;
	  default: $ftRecalcStat = NULL;
	}

	if ($doReceipt) {
	    $strTitle = 'Receipt for Order #'.$strNum;
	} else {
	    $strTitle = 'Order ID '.$id.' - #'.$strNum;
	}

	if ($doSave) {
	/* 2014-02-23 old version
	    $this->BuildEditForm();
	    if ($doSave) {
		$this->AdminSave();
		$this->AdminRedirect();		// reload the page
	    }
	    */
	    $this->PageForm()->Save();
	    // return to the list form after saving
	    $this->AdminRedirect();
	    // IF that doesn't work, use this:
	    //$urlReturn = $oPage->SelfURL(array('id'=>FALSE));
	    //clsHTTP::Redirect($urlReturn);
	}
	if (!$doSave) {
	    $this->DoEdit($doEdit);
	    if ($doReceipt) {
		// display order receipt
		$out .= $this->RenderReceipt();
	    } elseif ($doEmail) {
		// manual email confirmation
		$out .= $this->AdminPage_email();
	    } else {
		// regular order admin display
		$out .= $this->AdminPage_basic();
	    }
	}

	return $out;
    }
    /*----
      ACTION: Displays the normal admin page
    */
    protected function AdminPage_basic() {
	$doEdit = $this->DoEdit();
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

	// get order status
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
	$sAddrRecip = $this->RecipAddr();

	/*
	$idAddr = $rcAddrRecip->KeyValue();
	if (is_null($idAddr)) {
	    $htAddrLink = '<i>no address ID</i>';
	} else {
	    $url = clsMenuData_helper::_PageLink_URL(KS_PAGE_KEY_ADDR, $idAddr);
	    $htAddrLink	=
	      '('
	      .clsHTML::BuildLink($url,'addr','view address record')
	      .') '
	      .$rcAddrRecip->AsSingleLine();
	}

	// check the address's "Full" field (later, this will be manually controlled)
	if ($sAddrRecip != $rcAddrRecip->Full) {
	    $rcAddrRecip->UpdateCalc();
	    // TODO: this should probably be replaced with a message indicating address is out of sync
	}
	*/

	// -- actual financial calculations
	$rsTrx = $this->TrxRecords();
	$ftTrxacts = $this->Trxact_RenderTable();
	$arTrxBal = $this->arBalTrx;

	// render form controls
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	$arCtrls['ID'] = $this->AdminLink();
	$arCtrls['Status'] = $txtStatus;
	$arCtrls['QuoSale'] = clsMoney::Format_withSymbol($this->QuotedSaleAmt());
	$arCtrls['QuoShip'] = clsMoney::Format_withSymbol($this->QuotedShipAmt());
	$arCtrls['QuoFinal'] = clsMoney::Format_withSymbol($this->QuotedFinalAmt());

	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    //$oForm = $this->PageForm();

	    //$htNameBuyer = $objForm->RenderControl('ID_BuyerCard').' / Name: '.$objForm->RenderControl('BuyerName');
	    //$htNameRecip = $objForm->RenderControl('ID_RecipAddr').' / Name: '.$objForm->RenderControl('RecipName');
	    //$htAddrShip = $objForm->RenderControl('RecipAddr');

	    $htNameBuyer = '[[ID_BuyerCard]] / Name: [[BuyerName]]';
	    $htNameRecip = '[[ID_RecipAddr]] / Name: [[RecipName]]';

	    $htCard = $this->CCardChoose();

	    $htBalBtnsLine = NULL;
	} else {
	    //$htNameBuyer = 'ID:'.$this->Buyer_Obj_AdminLink().' / Name:'.$this->BuyerName();
	    //$htNameRecip = 'ID:'.$this->Recip_Obj_AdminLink().' / Name:'.$this->RecipName();
	    //$htAddrShip = $this->RecipAddr();

	    $htNameBuyerID = $this->Buyer_Obj_AdminLink();
	    $htNameRecipID = $this->Recip_Obj_AdminLink();
	    $htNameBuyer = "ID: $htNameBuyerID / Name: [[BuyerName]]";
	    $htNameRecip = "ID: $htNameRecipID / Name: [[RecipName]]";

	    $this->AreTotalsOk(FALSE);

	    $htCard = $this->CCardLink();

	    //$out .= $this->AdminLines();	// calculates $ftBalBtns
	    $ftBalBtns = $this->RenderBalanceButtons();

	    // I can't see how $ftBalBtns would ever be NULL
	    $htBalBtnsLine = "\n<tr><td align=center colspan=2>$ftBalBtns</td></tr>";
	}
	$arCtrls['NameBuyer'] = $htNameBuyer;
	$arCtrls['NameRecip'] = $htNameRecip;
	$arCtrls['Card'] = $htCard;
	$arCtrls['BalBtnsLine'] = $htBalBtnsLine;
	
	if ($this->HasLines()) {
	    $oTotals = $this->TotalsObject($doEdit);
	    $htAmtMerch = $oTotals->RenderItems();
	} else {
	    $htAmtMerch = $oPage->Skin()->ErrorMessage('Order has no item lines.');
	}
	$arCtrls['AmtMerch'] = $htAmtMerch;

/*
	$htWhenStarted = $this->Value('WhenStarted');
	$htWhenPrepped = $this->Value('WhenPrepped');
	$htWhenEdited = $this->Value('WhenEdited');
	$htWhenClosed = $this->Value('WhenClosed');
	*/
	if ($this->Pulled()) {
	    $htPullText = $this->PulledText();
	    $htPullLine = "\n<tr><td align=right><b>Pulled</b>:</td><td>".$htPullText.'</td></tr>';
	} else {
	    $htPullLine = NULL;
	}
	$arCtrls['PullInfo'] = $htPullLine;

	if (isset($ftRecalcStat)) {
	    $htRecalcLine = "\n<tr><td align=left colspan=2>$ftRecalcStat</td></tr>";
	} else {
	    $htRecalcLine = NULL;
	}
	$arCtrls['RecalcLine'] = $htRecalcLine;

	$arCart = $this->CartList();
	$arCtrls['Cart'] = $arCart['html'];

	$oTplt->VariableValues($arCtrls);
	$out = $oTplt->RenderRecursive();
	
	/*
	$out .= <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>
  <tr><td align=right><b>Status</b>:</td><td>$txtStatus</td></tr>
  <tr><td align=right><b>Cart</b>:</td><td>$htCart</td></tr>
  <tr><td align=right><b>When Created</b>:</td><td>$htWhenStarted</td></tr>
  $htPullLine
  <tr><td align=right><b>When Prepared</b>:</td><td>$htWhenPrepped</td></tr>
  <tr><td align=right><b>When Edited</b>:</td><td>$htWhenEdited</td></tr>
  <tr><td align=right><b>When Closed</b>:</td><td>$htWhenClosed</td></tr>
  <tr><td align=right><b>Buyer</b>:</td><td>$htNameBuyer</td></tr>
  <tr><td align=right><b>Recipient</b>:</td><td>$htNameRecip</td></tr>
  <tr><td align=right><b>Ship to</b>:</td><td>$htAddrShip</td></tr>
  <tr><td align=right><b>Payment</b>:</td><td>$htCard</td></tr>
  <tr><td align=right><b>Merch $ quoted</b>:</td><td>$htQuoSale</td></tr>
  <tr><td align=right><b>Ship $ quoted</b>:</td><td>$htQuoShip</td></tr>
  <tr><td align=right><b>Final $ quoted</b>:</td><tr>$htQuoFinal</td></tr>
  <tr><td align=center colspan=2>
    <table>
    $htAmtMerch
    $htBalBtnsLine
    $htRecalcLine
    </table>
  </td></tr>
</table>
__END__;
*/
	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'<input type=submit name=btnCancel value="Cancel">'
	      .'</form>';
	}

	$out .= $this->Item_RenderTable();	// items in the order
	$out .= $this->Pkg_RenderTable();
	$out .= $ftTrxacts;
	$out .= $this->Msg_RenderTable();
	$out .= $this->Charge_RenderTable();
	$out .= $this->Event_RenderTable();
	$out .= $this->Pull_RenderTable();
	$out .= '<hr><small>generated by '.__FILE__.' line '.__LINE__.'</small>';
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Status</b>:</td><td>[[Status]]</td></tr>
  <tr><td align=right><b>Cart</b>:</td><td>[[Cart]]</td></tr>
  <tr><td align=right><b>When Created</b>:</td><td>[[WhenStarted]]</td></tr>
  [[PullInfo]]
  <tr><td align=right><b>When Prepared</b>:</td><td>[[WhenPrepped]]</td></tr>
  <tr><td align=right><b>When Edited</b>:</td><td>[[WhenEdited]]</td></tr>
  <tr><td align=right><b>When Closed</b>:</td><td>[[WhenClosed]]</td></tr>
  <tr><td align=right><b>Buyer</b>:</td><td>[[NameBuyer]]</td></tr>
  <tr><td align=right><b>Recipient</b>:</td><td>[[NameRecip]]</td></tr>
  <tr><td align=right><b>Ship to</b>:</td><td>[[AddrShip]]</td></tr>
  <tr><td align=right><b>Payment</b>:</td><td>[[Card]]</td></tr>
  <tr><td align=right><b>Merch $ quoted</b>:</td><td>[[QuoSale]]</td></tr>
  <tr><td align=right><b>Ship $ quoted</b>:</td><td>[[QuoShip]]</td></tr>
  <tr><td align=right><b>Final $ quoted</b>:</td><tr>[[QuoFinal]]</td></tr>
  <tr><td align=center colspan=2>
    <table>
    [[AmtMerch]]
    [[BalBtnsLine]]
    [[RecalcLine]]
    </table>
  </td></tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }    
    protected function ListingRow($sCalcStats,$cssRow) {
	$row = $this->Values();

	$ftID		= $this->AdminLink();
	$strNum		= $row['Number'];
	$strPull	= $this->PulledText();
	$sWho		= $this->WhoDescrip();
	$mnyTotal	= $row['WebTotal_Final'];
	$dtCreate	= $row['WhenStarted'];
	$dtClosed	= $row['WhenClosed'];

	$wtNum = $strNum;
	$strTotal = clsMoney::Format_withSymbol($mnyTotal);
	$strWhenCreated = clsDate::NzDate($dtCreate);
	$strWhenClosed = clsDate::NzDate($dtClosed);

	$out = <<<__END__
  <tr style="$cssRow">
    <td><b>$ftID</b></td>
    <td>$wtNum</td>
    <td>$strPull</td>
    <td>$sWho</td>
    <td align=right>$strTotal</td>
    <td>$strWhenCreated</td>
    <td>$strWhenClosed</td>
__END__;

	if ($sCalcStats) {
	    $arSum = $this->ItemsNeeded_Summary();
	    if (is_array($arSum)) {
		$qtyOrd = $arSum['qty-ord'];
		$qtyOk = nz($arSum['qty-shp']);
		$qtyXed = nz($arSum['qty-kld']) + nz($arSum['qty-na']);
		$out .= <<<__END__
    <td align=center>$qtyOrd</td>
    <td align=center>$qtyOk</td>
    <td align=center>$qtyXed</td>
__END__;
		$qtyOpen = $qtyOrd - $qtyOk - $qtyXed;
		if ($qtyOpen) {
		    $out .= <<<__END__
    <td align=center><b>$qtyOpen</b></td>
__END__;
		}
	    }
	}
	$out .= "\n  </tr>";
	return $out;
    }
    public function Listing($iNoneDescr=NULL,$iCalcStats=FALSE) {
	if ($this->hasRows()) {
	    $out = "\n<table>\n".static::ListingHeader($iCalcStats);
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;
		$out .= $this->ListingRow($iCalcStats,$wtStyle);
	    }
	    $out .= "\n</table>\n";
	} else {
	    if (is_null($iNoneDescr)) {
		$out = 'No order records found.';
	    } else {
		$out = $iNoneDescr;
	    }
	}
	return $out;
    }
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
	2014-02-23 rewritten to return the object; renamed from BuildEditForm() to PageForm()
    */
    private $oPageForm;
    protected function PageForm() {
	if (empty($this->oPageForm)) {
	    // create fields & controls
	    $oForm = new fcForm_DB($this->Table()->ActionKey(),$this);
	      $oField = new fcFormField_Num($oForm,'ID_BuyerCard');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>4));
	      $oField = new fcFormField_Num($oForm,'ID_RecipAddr');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>4));
	      $oField = new fcFormField($oForm,'BuyerName');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>20));
	      $oField = new fcFormField($oForm,'RecipName');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>20));
	      $oField = new fcFormField($oForm,'RecipAddr');
		$oCtrl = new fcFormControl_HTML_TextArea($oForm,$oField,array('width'=>20));
	      $oField = new fcFormField_Num($oForm,'WebTotal_Merch');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>6));
	      $oField = new fcFormField_Num($oForm,'WebTotal_Ship');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>6));
	      $oField = new fcFormField_Num($oForm,'WebTotal_Final');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>6));
	      $oField = new fcFormField_Time($oForm,'WhenCounted');
		$oCtrl = new fcFormControl_HTML($oForm,$oField,array('size'=>10));
	    

	    /* FORMS v1
	    $objForm = new clsForm_recs($this);

	    $objForm->AddField(new clsFieldNum('ID_BuyerCard'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsFieldNum('ID_RecipAddr'),	new clsCtrlHTML(array('size'=>4)));
	    $objForm->AddField(new clsField('BuyerName'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('RecipName'),		new clsCtrlHTML(array('size'=>20)));
	    $objForm->AddField(new clsField('RecipAddr'),		new clsCtrlHTML_TextArea());
	    $objForm->AddField(new clsFieldNum('WebTotal_Merch'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('WebTotal_Ship'),	new clsCtrlHTML(array('size'=>6)));
	    $objForm->AddField(new clsFieldNum('WebTotal_Final'),	new clsCtrlHTML(array('size'=>6)));
	    */
	    $this->oPageForm = $oForm;
	}
	return $this->oPageForm;
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
	$out = $this->PageForm()->Save();
	return $out;
    }
    public function Item_RenderTable() {
	$oPage = $this->Engine()->App()->Page();

	$strAction = $oPage->pathArg('do');
	$doAddItem = ($strAction == 'add-item');
	if ($oPage->ReqArgBool('btnSaveItem')) {
	    $arFields = VCA_OrderItems::CaptureEdit();
	    VCA_OrderItems::SaveEdit($this,$arFields);
	}

/*
	$objPage = new clsWikiFormatter($vgPage);
    	$objSection = new clsWikiSection_std_page($objPage,'Items',3);
	$objLink = $objSection->AddLink_local(new clsWikiSectionLink_option(array(),'add-item','do','add'));
	  $objLink->Popup('add a new item to the order');
	$out = $objSection->Render();
*/
	// set up titlebar menu
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'add-item',		'do','add',	NULL,'add a new item to the order'),
	  );
	$out = $oPage->ActionHeader('Items',$arActs);

	$rsRows = $this->LinesData();
	$rsRows->Want_ShowNewEntry($doAddItem);
	// TODO: Can we use AdminRows() instead?
	$out .= $rsRows->AdminTable_forOrder();

	return $out;
    }
    // PACKAGE subdata
    public function Pkg_RenderTable() {
	$tbl = $this->PackageTable();
	$rs = $tbl->GetOrder($this->KeyValue());

	// Columns to display for Packages table
	$arCols = array(
	  'ID'			=> 'ID',
	  'Seq'			=> '#',
	  'ID_Shipment'		=> 'Shipped in',
	  'ChgShpItm'		=> '$/item',
	  'ChgShipPkg'		=> '$/pkg',
	  'WhenStarted'		=> 'Start',
	  'WhenFinished'	=> 'Finish',
	  'WhenChecked'		=> 'Check',
	  'WhenVoided'		=> 'Void',
	  'isReturn'		=> 'Type',
	  );

	$out = $this->Engine()->App()->Page()->SectionHeader('Packages',NULL,'section-header-sub')
	  .$rs->AdminRows($arCols);	// TODO: make sure new-entry row is displayed if we want one
	return $out;
    }
    /*----
      RETURNS: formatted code of header and table showing transactions for this order
    */
    public function Trxact_RenderTable() {
	$id = $this->KeyValue();
	$oPage = $this->Engine()->App()->Page();
	$rs = $this->TrxRecords();
	//$rs->OrderID($id);	// 2014-09-13 why is this needed?

	// set up section menu
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array('order'=>$id),'add-trx',	'do','add',	NULL,'add a new transaction for this order'),
	  //new clsActionLink_option(array(),'show.open',	NULL,'open',	NULL,'show orders which have not been completed'),
	  //new clsActionLink_option(array(),'show.shut',	NULL,'shut',	NULL,'show orders which have been closed'),
	  );
	$out = $oPage->ActionHeader('Transactions',$arActs);

	$out .= $rs->AdminTable();

	if ($rs->HasBalance()) {
	    $arLink = $oPage->PathArgs(array('page','id'));
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
	$tbl = $this->MessageTable();
	$rs = $tbl->GetData('ID_Ord='.$this->KeyValue());
	return $this->Engine()->App()->Skin()->SectionHeader('Messages',NULL,'section-header-sub')
	  .$rs->AdminTable();
    }
    public function Charge_RenderTable() {
	// get table showing existing charges
	$tbl = $this->ChargeTable();
	$rs = $tbl->GetData('ID_Order='.$this->KeyValue());
	return $this->Engine()->App()->Skin()->SectionHeader('Charges',NULL,'section-header-sub')
	  .$rs->AdminTable();
    }
    public function Event_RenderTable() {
	return $this->Engine()->App()->Skin()->SectionHeader('Events',NULL,'section-header-sub')
	  .$this->EventListing();
    }
    public function Pull_RenderTable() {
	$oPage = $this->Engine()->App()->Page();

	$sForm = $oPage->PathArg('form');

	$out = $oPage->SectionHeader('Pulls',NULL,'section-header-sub');

	$objTable = $this->PullTable();
//	return $objRows->AdminTable($iArgs,$this);

	if ($oPage->ReqArgBool('btnPull')) {
	    $doShowForm = FALSE;

	    $idType = $oPage->ReqArg('type');
	    $strNotes = $oPage->ReqArgBool('notes');
	    $out = "Pulling order as '''".$objTable->Types()->GetItem($idType)->Name."'''";
	    if (!empty($strNotes)) {
		$wtNotes = htmlspecialchars($strNotes);
		$out .= " with note <b>$wtNotes</b>.";
	    }

	    $objTable->Pull($this,$idType,$strNotes);
	    $this->Reload();	// update order's pulled status
	} elseif ($oPage->ReqArgBool('btnFree')) {
	    $doShowForm = FALSE;
	    $stat = $this->PullObj()->UnPull($strNotes);
	    if (!is_null($stat)) {
		$out .= "<b>Error</b>: $stat";
	    }
	} else {
	    $doShowForm = ($sForm == 'pull');	// not tested
	}

	$objRows = $objTable->GetOrder($this->KeyValue());
	if ($objRows->hasRows()) {
	    $out .= <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>What</th>
    <th>When</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($objRows->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $objRows->Row;
		$id = $row['ID'];

		$idType = $row['ID_Type'];
//		$objType = $objRows->Types()->GetItem($idType);
		$strWhat = $objRows->TypeName();

		$htWho = clsVbzData::WhoString_OLD1($row);

		$isRowPulled = $objRows->IsPulled();

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$id</td>
    <td>$strWhat</td>
    <td>{$row['WhenPulled']}</td>
    <td>{$row['NotesPull']}</td>
  </tr>
__END__;
		if (!$isRowPulled) {
		    $out .= <<<__END__
  <tr style="$wtStyle">
    <td><b>released</b></td>
    <td>{$row['WhenFreed']}</td>
    <td>{$row['NotesFree']}</td>
  </tr>
__END__;
		}
	    }
	    $out .= "\n</table>";
	} else {
	    $strDescr = $this->TableArg('descr');
	    $out .= "\nNo pulls $strDescr.";
	}

	$isOrderPulled = $this->Pulled();
	if ($doShowForm) {

	    // this is sloppy and should probably be tidied
	    $arLink = $oPage->PathArgs();
	    unset($arLink['edit']);
	    unset($arLink['form']);
	    $urlForm = $oPage->SelfURL($arLink,TRUE);

	    if ($isOrderPulled) {
		$out .= <<<__END__
<form method=POST action="$urlForm">
  Details: <textarea name=notes width=40 rows=5></textarea>
  <input type=submit name=btnFree value="Release">
  - click to release order pulled at
__END__;
		$out .= ' '.$this->PullObj()->Value('WhenPulled');
	    } else {
		$htTypes = $objTable->Types()->ComboBox('type');
		$out .= <<<__END__
<form method=POST action="$urlForm">
$htTypes
 Details: <textarea name=notes width=40 rows=5></textarea>
<input type=submit name=btnPull value="Pull">
 - click to pull order #
__END__;
		$out .= $this->Value('Number');
	    }
	    $out .= '</form>';
	} else {
	    if ($isOrderPulled) {
		$strMsg = 'Release this order';
	    } else {
		$strMsg = 'Pull this order';
	    }
	    $arLink = $oPage->PathArgs();
	    $arLink['form'] = 'pull';
	    $url = $oPage->SelfURL($arLink);
	    $htLink = clsHTML::BuildLink($url,$strMsg,'pull this order');
	    $out .= '[ '.$htLink.' ]';
	}
	return $out;
    }

    // -- ADMIN INTERFACE -- //
    // ++ DEPRECATED FUNCTIONS ++ //

/* 2014-01-19 this field no longer exists
    private function NameBuyerObj() {
	return  $this->CustNameTable($this->Value('ID_NameBuyer'));
    }
*/
    /*----
      HISTORY:
	2011-11-06 renamed NameBuyerAdminLink -> Buyer_Name_AdminLink
    */
/* 2014-01-19 There is no buyer name ID field anymore
    public function Buyer_Name_AdminLink() {
	$objRow = $this->NameBuyerObj();
	return $objRow->AdminLink_name($this->ID_Buyer);
    }
*/
    /*----
      RETURNS: record-object for the buyer name
    */
    private function BuyerNameRecord() {
	throw new exception('Order records no longer store pointers to name records.');
//	$idName = $this->BuyerNameID();
//	$rc = $this->NamesTable($idName);
//	return $rc;
    }
    /*----
      RETURNS: record-object for the recipient name
    */
    private function NameRecipObj() {
	throw new exception('Order records no longer store pointers to name records.');
    }
    private function RecipNameRecord() {
	throw new exception('Order records no longer store pointers to name records.');
//	$idName = $this->RecipNameID();
//	$rc = $this->NamesTable($idName);
//	return $rc;
    }
    /*----
      HISTORY:
	2011-11-06 renamed NameRecipAdminLink -> Recip_Name_AdminLink
    */
    private function Recip_Name_AdminLink() {
	throw new exception('Order records no longer store pointers to name records.');
//	$objRow = $this->NameRecipObj();
//	return $objRow->AdminLink_name($this->ID_Recip);
    }

    // -- DEPRECATED FUNCTIONS -- //

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
    /* 2014-02-22 this is either obsolete or in need of rewriting
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
    */
    /*----
      ACTION: Either simulate or actually do the import, depending on whether user
	has checked the appropriate option (currently "make the changes")
    */
    /* 2014-02-22 this is either obsolete or in need of rewriting
    protected function DoCart_Import(clsCartVars $objData) {
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
echo 'ACTIMP:'.$actImp->Exec(FALSE);

	// calculate order updates

	// - static stuff
	$arUpd = array('WhenPrepped' => 'NOW()');	// value with which to update order

	// - dynamic stuff
	$actUpd = new Script_Row_Update($arUpd,$this);
	$actShip = $actImp->Get_byName('person.ship',TRUE);
	if ($objData->ShipToSelf()) {
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

// 2014-02-22 this block was commented out
	$acts = new Script_Script();

	$actMe = new Script_Row_Update(array(),$this);
//	    $actMe->Value('WhenPrepped','NOW()');	// mark the order as "prepped"
	$acts->Add($objShip->DoResolve_Script($actMe,$doShipSelf),'resolve.main');

	if (!$doShipSelf) {
	    $acts->Add($objCust->DoResolve_Script($actMe,FALSE),'resolve.aux');
	}
// END block

	// Script: update the order to point to the new customer data records etc.

	//$actOrd = $acts->Get_byName('ord.upd',TRUE);
	//$actOrd->ReplaceWith($actMe);

	$doReally = $wgRequest->GetBool('chkReallyDo');
	$out .= 'MAKING CHANGES: '.NoYes($doReally);
	$out .= '<hr>'.$actImp->Exec($doReally).'<hr>';

	return $out;
    }
    */
    /*----
      ACTION: Check given cart data for matches to existing customer records, and allow
	user to choose which existing customer records to use
      TODO: Rewrite as function that looks for similarities between customer records;
	this is no longer used at cart-to-order conversion time.
    */
    protected function DoCart_Match_NEEDS_REWRITE(clsCartVars $objData) {
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
	return $out;
    }
    /* use clsTotal(s) instead
    static protected function AdminShowTotal($prcCalc,$prcSaved,&$okCalc) {
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
    }
    */

    protected function AdminPage_basic_OLD($doEdit) {
	$oPage = $this->Engine()->App()->Page();
	$out = NULL;

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

	// EDITING HERE

	$rcAddrRecip = $this->AddrRecipObj();
	$idAddr = $rcAddrRecip->KeyValue();

	if (is_null($idAddr)) {
	    $htAddrLink = '<i>no address ID</i>';
	} else {
	    $url = clsMenuData_helper::_PageLink_URL(KS_PAGE_KEY_ADDR, $idAddr);
	    $htAddrLink	=
	      '('
	      .clsHTML::BuildLink($url,'addr','view address record')
	      .') '
	      .$rcAddrRecip->AsSingleLine();
	}

	// check the address's "Full" field (later, this will be manually controlled)
	$strAddrShip = $rcAddrRecip->AsString();
	if ($strAddrShip != $rcAddrRecip->Full) {
	    $rcAddrRecip->UpdateCalc();
	}

	// do financial calculations first so we can mark up the given totals with status information
	// -- have to set up table arguments
	$this->TableArgs(array(
	    'add'	=> $oPage->PathArg('add'),
	    'form'	=> $oPage->PathArg('form'),
	    'descr'	=> ' for this order',
	    'omit'	=> 'order'
	    )
	  );
	// -- actual financial calculations
	$rsTrx = $this->TrxRecords();
	$ftTrxacts = $this->Trxact_RenderTable();
	$arTrxBal = $this->arBalTrx;

	// - editable order data
	$ftBalBtns = NULL;

	if ($doEdit) {
	    $out .= "\n<form method=post>";
	    $objForm = $this->PageForm();

	    $htNameBuyer = $objForm->Render('ID_Buyer').' - name:'.$objForm->Render('ID_NameBuyer');
	    $htNameRecip = $objForm->Render('ID_Recip').' - name:'.$objForm->Render('ID_NameRecip');
	    $htAddrShip = $objForm->Render('RecipAddr');

	    $htAmtMerch	= $objForm->Render('WebTotal_Merch');
	    $htAmtShip =  $objForm->Render('WebTotal_Ship');
	    $htAmtTotal =  $objForm->Render('WebTotal_Final');

	    $htCard = $this->CCardChoose();
	} else {
	    $htNameBuyer = $this->BuyerName().' - cust ID '.$this->Buyer_Obj_AdminLink();
	    $htNameRecip = $this->RecipName().' - cust ID '.$this->Recip_Obj_AdminLink();
	    $htAddrShip = $this->RecipAddr().' ('.$htAddrLink.')';
	    $htCard = $this->CCardLink();
/*
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
*/

	    if ($this->AreTotalsOk()) {
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
	$doAct = $oPage->PathArg('do');
	switch ($doAct) {
	  // 2011-09-24 $arBalTrx seems to reflect order totals, not current transaction balance
	  //case 'charge':	$ftRecalcStat = $this->DoChargeCard($arBalTrx);	break;
	  case 'charge':	$ftRecalcStat = $this->DoChargeCard($arTrxBal);	break;
	  case 'recalc':	$ftRecalcStat = $this->DoRecalcBal();			break;
	  default: $ftRecalcStat = NULL;
	}

	if ($doEdit) {
	    $out .= '<form method=post>';
	}

	$htID = $this->AdminLink();
	$htWhenStarted = $this->Value('WhenStarted');
	$htWhenPrepped = $this->Value('WhenPrepped');
	$htWhenEdited = $this->Value('WhenEdited');
	$htWhenClosed = $this->Value('WhenClosed');
	if ($this->Pulled()) {
	    $htPullText = $this->PulledText();
	    $htPullLine = "\n<tr><td align=right><b>Pulled</b>:</td><td>".$htPullText.'</td></tr>';
	} else {
	    $htPullLine = NULL;
	}
	if (is_null($ftBalBtns)) {
	    $htBalBtnsLine = NULL;
	} else {
	    $htBalBtnsLine = "\n<tr><td align=center colspan=2>$ftBalBtns</td></tr>";
	}
	if (isset($ftRecalcStat)) {
	    $htRecalcLine = "\n<tr><td align=left colspan=2>$ftRecalcStat</td></tr>";
	} else {
	    $htRecalcLine = NULL;
	}

	$out .= <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>$htID</td></tr>
  <tr><td align=right><b>Status</b>:</td><td>$txtStatus</td></tr>
  <tr><td align=right><b>Cart</b>:</td><td>$htCart</td></tr>
  <tr><td align=right><b>When Created</b>:</td><td>$htWhenStarted</td></tr>
  $htPullLine
  <tr><td align=right><b>When Prepared</b>:</td><td>$htWhenPrepped</td></tr>
  <tr><td align=right><b>When Edited</b>:</td><td>$htWhenEdited</td></tr>
  <tr><td align=right><b>When Closed</b>:</td><td>$htWhenClosed</td></tr>
  <tr><td align=right><b>Buyer</b>:</td><td>$htNameBuyer</td></tr>
  <tr><td align=right><b>Recipient</b>:</td><td>$htNameRecip</td></tr>
  <tr><td align=right><b>Ship to</b>:</td><td>$htAddrShip</td></tr>
  <tr><td align=right><b>Payment</b>:</td><td>$htCard</td></tr>
  <tr><td align=center colspan=2>
    <table>
    <tr><td align=right><b>Merchandise</b>: $</td><td align=right>$htAmtMerch</td></tr>
    <tr><td align=right><b>Shipping</b>: $</td><td align=right>$htAmtShip</td></tr>
    <tr><td align=right><b>Total</b>: $</td><td align=right>$htAmtTotal</td></tr>
    $htBalBtnsLine
    $htRecalcLine
    </table>
  </td></tr>
</table>
__END__;
	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'<input type=submit name=btnCancel value="Cancel">'
	      .'</form>';
	}

	$out .= $this->Item_RenderTable();	// items in the order
	$out .= $this->Pkg_RenderTable();
	$out .= $ftTrxacts;
	$out .= $this->Msg_RenderTable();
	$out .= $this->Charge_RenderTable();
	$out .= $this->Event_RenderTable();
	$out .= $this->Pull_RenderTable();
	$out .= '<hr><small>generated by '.__FILE__.' line '.__LINE__.'</small>';
echo __FILE__.' line '.__LINE__.'<br>';
	return $out;
    }
    /* 2014-05-14 This is probably OBSOLETE.
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
    */
    /*----
      ACTION: clears the cache so data will be reloaded on next request
      HISTORY:
	2011-03-31 created so we can refresh transaction data after updating
    */
    protected function Data_Trx_uncache() {
	$cache = $this->objCacheTrx;
	$cache->Clear();
    }
}
