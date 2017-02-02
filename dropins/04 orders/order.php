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

class VCM_Orders extends vctOrders {
    use ftLinkableTable;

    // ++ SETUP ++ //
    
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VC_Order');	// override parent
	  $this->ActionKey(KS_PAGE_KEY_ORDER);
    }

    // -- SETUP -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    }

    // -- CALLBACKS -- //
    // ++ CLASS NAMES ++ //

    protected function LinesClass() {
	return KS_CLASS_ORDER_LINES;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

    public function LineTable($id=NULL) {
	return $this->Engine()->Make($this->LinesClass(),$id);
    }

    // -- TABLES -- //
    // ++ WEB UI ++ //

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
	$htFind = '"'.fcString::EncodeForHTML($sInput).'"';

	$sOrderName = $sPfx.'-order';
	$sInput = $oPage->ReqArgText($sOrderName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forOrdNum($sInput);
	    $htSearchOut .= $rs->Listing('No matching order records.');
	}
	$htOrd = '"'.fcString::EncodeForHTML($sInput).'"';

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
	/* old setup
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option($arPage,'show.pulled',	NULL,'pulled',	NULL,'show pulled orders'),
	  new clsActionLink_option($arPage,'show.open',	NULL,'open',	NULL,'show orders which have not been completed'),
	  new clsActionLink_option($arPage,'show.shut',	NULL,'shut',	NULL,'show orders which have been closed'),
	  ); */
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  $miPull = new clsActionLink_option($arPage,'pulled',	'show',NULL,	NULL,'show only pulled orders'),
	  $miShut = new clsActionLink_option($arPage,'shut',	'show',NULL,	NULL,'show only orders which have been closed'),
	  $miOpen = new clsActionLink_option($arPage,'open',	'show',NULL,	NULL,'show only confirmed orders which have not been filled'),
	  $miUncon = new clsActionLink_option($arPage,'uncon',	'show','unconfirmed',	NULL,'show only unconfirmed orders'),
	  );
	$oPage->PageHeaderWidgets($arActs);

	// get current menu selections
	
	$doShowPulls = $miPull->Selected();
	$doShowShut = $miShut->Selected();
	$doShowOpen = $miOpen->Selected();
	$doShowUncon = $miUncon->Selected();
	
	$doShow = FALSE;
	$arDescr = array();
	if ($doShowUncon) {
	    $arFilt[] = '(WhenPlaced IS NULL)';
	    $arDescr[] = 'unconfirmed';
	    $doShow = TRUE;
	    $doCalc = TRUE;	// do additional calculations to get order's status
	}
	if ($doShowOpen) {
	    $arFilt[] = '(ID_Pull IS NULL) AND (WhenClosed IS NULL) AND (WhenPlaced IS NOT NULL)';
	    $arDescr[] = 'open';
	    $doShow = TRUE;
	    $doCalc = TRUE;	// do additional calculations to get order's status
	}
	if ($doShowShut) {
	    $arFilt[] = '(WhenClosed IS NOT NULL)';
	    $arDescr[] = 'closed';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	if ($doShowPulls) {
	    $arFilt[] = '(ID_Pull IS NOT NULL)';
	    $arDescr[] = 'pulled';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	$out = NULL;
	$out .= $this->RenderSearch();
	if ($doShow) {
	    $qof = new fcSQLt_Filt('AND',$arFilt);
	    $sqlFilt = $qof->RenderValue();
	    $rs = $this->SelectRecords($sqlFilt,'SortPfx, Number DESC');
	    $qRows = $rs->RowCount();
	    $sDescr = fcString::ConcatArray(', ',$arDescr);
	    $out .= $oPage->ActionHeader('All orders that are '.$sDescr);
	    $out .= 
	      'Showing '.$qRows.' order'.fcString::Pluralize($qRows)
	      .':'
	      .$rs->Listing("No records found.",$doCalc)
	      .'<span class=line-stats>('.$rs->sqlMake.')</span>'
	      ;
	} else {
	    $out .= 'No filter selected; not displaying any orders.';
	}
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

			$dtWhen = $objOrd->WhenStarted();
			$dtComp = nz($arNeed[$idItem]['earliest']);
			if (($dtWhen < $dtComp) || is_null($dtComp)) {
			    $arNeed[$idItem]['oldest'] = $dtWhen;
			}
			if ($dtWhen > nz($arNeed[$idItem]['latest'])) {
			    $arNeed[$idItem]['newest'] = $dtWhen;
			}

			$dtWhen = $objOrd->WhenNeeded();
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
class VC_Order extends vcrOrder {
    use ftLinkableRecord;
    use ftLoggableRecord;

    private $objPull;
    // object cache
    private $rsLines;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->rsLines = NULL;
    }

    // -- SETUP -- //
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
    // ++ LOCAL STATUS ++ //

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

    // -- LOCAL STATUS -- //
    // ++ TRAIT HELPERS ++ //

    /*----
      CALLED BY: $this->SelfLink_name()
    */
    protected function AdminName() {
	return $this->Number();
    }
    /*----
      CALLED BY: Cart admin object
    */
    public function SelfLink_name($iPopup=NULL,array $iarArgs=NULL) {
	if ($this->IsNew()) {
	    return NULL;
	} else {
	    $txt = $this->AdminName();
	    return $this->SelfLink($txt,$iPopup,$iarArgs);
	}
    }

    // -- TRAIT HELPERS -- //
    // ++ APP FRAMEWORK ++ //
    
    protected function PageObject() {
	return $this->Engine()->App()->Page();
    }
    
    // -- APP FRAMEWORK -- //
    // ++ CLASS NAMES ++ //

    protected function CustomersClass() {
	return KS_CLASS_ADMIN_CUSTOMERS;
    }
    protected function CustAddrsClass() {
	return KS_CLASS_MAIL_ADDRS;
    }
    public function TrxactsClass() {
	return KS_CLASS_ORDER_TRXS;
    }
    public function CardsClass() {
	return KS_CLASS_CUST_CARDS;
    }
    protected function CartsClass() {
	if (fcDropInManager::IsReady('vbz.carts')) {
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
    protected function CustomerEmailsClass() {
	return KS_CLASS_EMAIL_ADDRS;
    }
    protected function CustomerPhonesClass() {
	return KS_CLASS_CUST_PHONES;
    }

    // -- CLASS NAMES -- //
    // ++ TABLES ++ //

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
    protected function CustAddrTable($id=NULL) {
	return $this->Engine()->Make($this->CustAddrsClass(),$id);
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

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function PullRecord() {
	$rcPull = $this->PullTable($this->PullID());
	return $rcPull;
    }
    /*-----
      RETURNS: Recordset of lines for this order, sorted by Seq
    */
    public function LinesData($iRefresh=FALSE) {
	if ($iRefresh || is_null($this->rsLines)) {
	    $tbl = $this->LineTable();
	    $this->rsLines = $tbl->GetData('ID_Order='.$this->GetKeyValue(),NULL,'Seq');
	}
	return $this->rsLines;
    }
    public function LineRecord_forItem($idItem) {
	$tbl = $this->LineTable();
	$rc = $tbl->GetData('(ID_Order='.$this->GetKeyValue().') AND (ID_Item='.$idItem.')');
	$rc->FirstRow();
	return $rc;
    }
    /*----
      INPUT:
	$useVoid: if TRUE, include voided packages in the recordset
    */
    public function PackageRecords($useVoid) {
	$sqlFilt = 'ID_Order='.$this->GetKeyValue();
	if (!$useVoid) {
	    $sqlFilt = "($sqlFilt) AND (WhenVoided IS NULL)";
	}
	$rs = $this->PackageTable()->GetData($sqlFilt);
	return $rs;
    }
    public function CardRecord() {
	$idCard = $this->CardID();
	return $this->CardTable($idCard);
    }
    protected function CardRecords() {
	if ($this->HasBuyer()) {
	    $idCust = $this->BuyerID();
	    $rsCards = $this->CardTable()->SelectRecords('ID_Cust='.$idCust);
	    return $rsCards;
	} else {
	    return NULL;
	}
    }
    /*----
      RETURNS: customer object for buyer, or NULL if buyer is not set
      HISTORY:
	2011-11-06 now checks to make sure buyer is set
    */ /* 2016-07-10 duplicates function defined in parent
    protected function BuyerRecord() {
	if ($this->HasBuyer()) {
	    $idCust = $this->BuyerID();
	    return $this->CustTable($idCust);
	} else {
	    return NULL;
	}
    } */
    /*----
      HISTORY:
	2016-06-13 Removed the RecipObj() alias of RecipRecord(); updated code
    */ /* 2016-07-10 duplicates function defined in parent
    protected function RecipRecord() {
	$idCust = $this->RecipID();
	$rcCust = $this->CustTable($idCust);
	return $rcCust;
    } */
    protected function RecipAddrRecord() {
	$idAddr = $this->RecipAddrID();
	$rcAddr = $this->CustAddrTable($idAddr);
	return $rcAddr;
    }
    /*----
      RETURNS: recordset of transaction lines for this order
      PUBLIC so Package record can access it
    */
    private $rsTrx;
    public function TransactionRecords($sqlFilt=NULL) {
	$id = $this->GetKeyValue();
	if (is_null($sqlFilt)) {
	    $sqlFilt = "ID_Order=$id";
	} else {
	    $sqlFilt = "($sqlFilt) AND (ID_Order=$id)";
	}
	if (empty($this->rsTrx)) {
	    $tbl = $this->TrxactTable();
	    $rs = $tbl->SelectRecords($sqlFilt,'WhenDone, ID');
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

    // -- RECORDS -- //
    // ++ LOOKUPS ++ //

    /* 2016-06-13 This appears to be unused, which is good because it is probably slow.
    public function LineID_forItem($idItem) {
	$rc = $this->LineRecord_forItem($idItem);
	if ($rc->FirstRow()) {
	    return $rc->KeyValue();
	} else {
	    return NULL;
	}
    }*/

    // -- LOOKUPS -- //
    // ++ FIELD VALUES ++ //

    // CALLED BY Order Item
    // TODO: Rename to WhenCreated()
    public function WhenStarted() {
    	return $this->Value('WhenCreated');
    }
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
    public function NameString() {
	return $this->Value('Number');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /* implemented in parent:
      protected function HasRecip()
    */

    protected function IsClosed() {
	return !is_null($this->WhenClosed());
    }
    /*----
      RETURNS: TRUE if order is in a state where it can be processed,
	FALSE otherwise (i.e. pulled or closed).
    */
    protected function IsActive() {
	return $this->IsPlaced() && !$this->IsPulled() && !$this->IsClosed();
    }
    // 2016-08-09 DEPRECATED
    public function Pulled($iPull=NULL) {
	if (!is_null($iPull)) {
	    throw new exception('We should not be passing an argument to this.');
	}
	throw new exception('Call IsPulled() instead. Also, may need to make IsPulled() public.');
    }
    protected function IsPulled() {
	return !is_null($this->PullID());
    }
    public function PulledText() {
	if ($this->IsPulled()) {
	    return $this->PullRecord()->TypeName();
	} else {
	    return NULL;
	}
    }
    protected function HasRecipAddr() {
	return !is_null($this->RecipAddrID());
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
	    $rc = $this->CardTable($id);
	    return $rc->SafeString();
	}
    }
    
      //++admin++//
    
    /*----
      HISTORY:
	2011-11-06 written for legacy orders that don't have ID_Buyer set
	2016-06-13 renamed from Buyer_Obj_AdminLink() to BuyerLink(); changed link to name-style; updated
    */
    public function BuyerLink($sNone='n/a') {
	if ($this->HasBuyer()) {
	    $rc = $this->BuyerRecord();
	    return $rc->SelfLink_name();
	} else {
	    return $sNone;
	}
    }
    /*----
      HISTORY:
	2016-06-13 Renamed from CCardLink() to BuyerCardLink(); updated
    */
    private function BuyerCardLink() {
	$id = $this->CardID();
	if (empty($id)) {
	    return "<i>none</i>";
	} else {
	    $rc = $this->CardTable($id);
	    return $rc->SelfLink_name();
	}
    }
    /*----
      HISTORY:
	2011-11-06 written for legacy orders that don't have ID_Recip set
	2016-06-13 renamed from Recip_Obj_AdminLink() to RecipLink(); changed link to name-style; updated
    */
    public function RecipLink($sNone='n/a') {
	if ($this->HasRecip()) {
	    $rc = $this->RecipRecord();
	    return $rc->SelfLink_name();
	} else {
	    return $sNone;
	}
    }
    public function RecipAddrLink() {
	if ($this->HasRecipAddr()) {
	    $rc = $this->RecipAddrRecord();
	    return $rc->SelfLink_name();
	} else {
	    return '<i>none</i>';
	}
    }
	    
      //--admin--//

    // -- FIELD CALCULATIONS -- //
    // ++ DATA CALCULATIONS ++ //

    public function HasLines() {
	$rsLines = $this->LinesData();
	if (is_null($rsLines)) {
	    return FALSE;
	} else {
	    return $rsLines->hasRows();
	}
    }
    /*----
      RETURNS: The number to use for Seq in the next Package to be created
    */
    public function NextPackageSeq() {
	$nSeq = $this->PackageTable()->NextSeq_forOrder($this->GetKeyValue());
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
		    $frm = $this->PageForm();
		    $htAmtMerch	= $frm->GetControlObject('WebTotal_Merch')->Render(TRUE);
		    $htAmtShip =  $frm->GetControlObject('WebTotal_Ship')->Render(TRUE);
		    $htAmtTotal = $frm->GetControlObject('WebTotal_Final')->Render(TRUE);
		} else {
		    $htAmtMerch = NULL;
		    $htAmtShip = NULL;
		    $htAmtTotal = NULL;
		}

		// create totals objects
		$this->oTotals = new vcCartDisplay();
		$this->oTotals->AddItem(new vcCartTotal_admin('merch','Merchandise',$prcCalcSale,$prcTotMerch,$htAmtMerch));
		$this->oTotals->AddItem(new vcCartTotal_admin('ship','Shipping',$prcCalcShip,$prcTotShip,$htAmtShip));
		$this->oTotals->AddItem(new vcCartTotal_admin('final','Total',$prcCalcTotal,$prcTotFinal,$htAmtTotal));
	    } else {
		$this->oTotals = NULL;
	    }
	}
	return $this->oTotals;
    }

    // -- DATA CALCULATIONS -- //
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
	$rs = $t->GetData('ID_Order='.$this->GetKeyValue());
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
		$arPkgSum = clsArray::Nz($arPkgSums,$idItem);
		$qtySum = $qtyOrd;
		if (is_array($arPkgSum)) {
		    $qtySum += clsArray::Nz($arPkgSum,'qty-ord',0);
		}
		$arPkgSums[$idItem]['qty-ord'] = $qtySum;
		self::ItemStats_update_line($arPkgSums[$idItem]);
	    }
	    return $arPkgSums;
	} else {
	    return NULL;
	}
    }
    public function ItemStats($iItem=NULL) {
	throw new exception('ItemStats() is deprecated; call PackageSums().');
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
	$qtyUsed	= (int)clsArray::Nz($iStat,'qty-used',0);
	$intQtyShp	= (int)clsArray::Nz($iStat,'qty-shp',0)-$qtyUsed;
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
		    fcArray::NzSum($arSum,$name,$qty);
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
	2016-01-09 This had been deprecated and gutted - we called PullTable()->Pull(), which
	  called back $this->Mark_asPulled(), but that now seems awkward. Reinstating this function
	  to call PullTable()->MakePull() and then $this->Mark_asPulled(). The Pull table object is now
	  only to be called by an Order object. If someone else needs to pull an order, $this->Pull()
	  should be called (make it public if this is actually needed).
    */
    protected function Pull($idType,$sNotes) {
	$idPull = $this->PullTable()->AddPull($this->GetKeyValue(),$idType,$sNotes);
	$this->Mark_asPulled($idPull);
    }
    protected function UnPull($sNotes) {
	$sErr = $this->PullRecord()->UnPull($sNotes);
	$this->Mark_asReleased();
	return $sErr;
    }
    protected function Mark_asPulled($idPull=NULL) {
	if ($idPull != $this->PullID()) {
	    if (!is_null($idPull)) {
		$sqlVal = $idPull;
	    } else {
		$sqlVal = 'NULL';
	    }
	    $this->Update(array('ID_Pull'=>$sqlVal));
	}
    }
    protected function Mark_asReleased() {
	$arUpd = array('ID_Pull' => 'NULL');
	$this->Update($arUpd);
    }
    protected function DoChargeCard_forBalance() {
	$rs = $this->TransactionRecords();
	$rs->CalculateBalances();
	$nBal = $rs->GetBalance_Final();
	$nShip = $rs->GetBalance_Ship();
	$out = $this->DoChargeCard($nBal,$nShip);
	return $out;
    }
    /*----
      ACTION: create a credit card charge from the current transaction balance
      INPUT:
	$dlrTotal -- the dollar amount to be charged. Includes sale, shipping, and any amounts already paid/credited (deducted)
	$dlrShip -- the dollar portion of $dlrTotal which is for shipping/handling
      HISTORY:
	2011-03-31 extracted into separate function
	2016-09-13 now expects "total" and "ship" amount arguments instead of a single array with "sale" and "ship" elements.
    */
    protected function DoChargeCard($dlrTotal,$dlrShip) {
	$db = $this->Engine();

	$rcCard = $this->CardRecord();
	$sCard = $rcCard->ShortDescr();

	//-- do the calculations
	$dlrSale = $dlrTotal - $dlrShip;
	//-- log the attempt
	$sEvent = 'charging ccard '.$sCard.' $'.$dlrTotal.' (includes $'.$dlrShip.' s/h)';
	$db->TransactionOpen();
	$this->StartEvent_Simple('CHG',$sEvent,__METHOD__);

	$out = $sEvent;

	//-- add a Transaction record to count the payment (should zero the balance)
	$sTrxact = 'debited '.$sCard.' on '.date('M j');

	$arIns = array(
	  'ID_Order'	=> $this->GetKeyValue(),
	  'ID_Package'	=> 'NULL',
	  'ID_Type'	=> KI_ORD_TXTYPE_PAID_CC,
	  'WhenDone'	=> 'NOW()',
	  'Descr'	=> $db->SanitizeAndQuote($sTrxact),
	  'Amount'	=> -$dlrTotal
	  );
	$idTrx = $this->TrxactTable()->Insert($arIns);
	$out .= '<br>Transaction ID '.$idTrx.' created.';

	//-- create the charge
	$arIns = array(
	  'ID_Order'	=> $this->GetKeyValue(),
	  'ID_Card'	=> $this->CardID(),
	  'ID_Trxact'	=> $idTrx,
	  'AmtTrx'	=> $dlrTotal,
	  'AmtSold'	=> $dlrSale,
	  'AmtShip'	=> $dlrShip,
	  'CardNumExp'	=> $db->SanitizeAndQuote($rcCard->SingleString()),
	  'CardBillAddr'=> $db->SanitizeAndQuote($rcCard->AddrString()),
	  'WhenEntered'	=> 'NOW()'
	  );
	$idChg = $this->ChargeTable()->Insert($arIns);

	global $sql;
	$out .= '<br>Charge ID '.$idChg.' added - SQL: '.$sql;

	// log event completion
	$arParams = array(
	  'TrxID'	=> $idTrx,
	  'ChgID'	=> $idChg,
	  );
	$arEv = array('params' => serialize($arParams));
	$this->FinishEvent($arEv);
	$db->TransactionSave();

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
		$rcEv->Finish();
		$this->AdminRedirect();
	    }
	} else {
	    $ftOut .= 'No items found in order!';
	}
	return $ftOut;
    }

    // -- ACTIONS -- //
    // ++ ADMIN INTERFACE ++ //

    static protected function ListingHeader($doCalcStats=FALSE) {
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
      TODO: document what this actually returns
      USED BY: admin page
      HISTORY:
	2011-09-18 created -- trying to tidy up order provisioning process
    */
    private function CartList() {

	$htOut = NULL;
	$arErrs = NULL;

	$idCart = $this->CartID();
	$idOrder = $this->GetKeyValue();

	if (is_null($idCart)) {
	    $arErrs[] = 'no default cart';
	} else {
	    $rcCart = $this->CartRecord();
	    $htOut .= $rcCart->SelfLink();
	}
	$rsCart = $this->CartTable()->GetData('ID_Order='.$idOrder);
	if ($rsCart->HasRows()) {
	    $arOthers = NULL;
	    $cntOthers = 0;
	    while ($rsCart->NextRow()) {
		$id = $rsCart->GetKeyValue();
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
		$ftRecalcLink = $this->SelfLink(
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
      PURPOSE: Stuff to do before admin page is displayed
    */
    protected function AdminInit() {
	$this->PageObject()->Skin()->SetBrowserTitle('ord#'.$this->Number());	// browser title
    }
    /*----
      PURPOSE: Starting point for admin page
	This method calls other methods to do the work, depending on input
    */
    protected function AdminPage() {
	$out = NULL;
	$oPage = $this->PageObject();
	$sNum = $this->NumberString();
	$oPage->Skin()->SetPageTitle("Order #$sNum");	// page title

	$id = $this->GetKeyValue();

	// set up titlebar menu
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'rcpt',		'do','receipt',	NULL,"receipt for order #$sNum"),
	  new clsActionLink_option(array(),'email',		'do',NULL,NULL,'send an email'),
	  new clsActionLink_option(array(),'edit',		'do',NULL,NULL,'edit this order'),
	  );
	$oPage->PageHeaderWidgets($arActs);

	// get user action selections

	$sDo = $oPage->PathArg('do');
	$doSave = $oPage->ReqArgBool('btnSave');

	// handle actions
	$doEdit 	= FALSE;
	$doReceipt	= FALSE;
	$doEmail	= FALSE;
	switch ($sDo) {
	  case 'edit':		$doEdit = TRUE;		break;
	  case 'receipt':	$doReceipt = TRUE;	break;
	  case 'email':		$doEmail = TRUE;	break;
	  case 'charge':	$ftRecalcStat = $this->DoChargeCard_forBalance();	break;	// to be written - use DoChargeCard() with balance from trx recordset
	  case 'recalc':	$ftRecalcStat = $this->DoRecalcBal();			break;
	  default: $ftRecalcStat = NULL;
	}

	if ($doSave) {
	    $this->PageForm()->Save();
	    // return to the list form after saving
	    $this->AdminRedirect();
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
	$oPage = $this->PageObject();
	$out = NULL;

	// get order status
	if ($this->NeedSetup()) {
	    $txtStatus = 'not set up yet';
	} else {
	    if ($this->IsPulled()) {
		$out .= 'This order has been pulled.';
		$txtStatus = 'pulled';
	    } else {
		$out .= 'This order is set up and ready to process.';
		$txtStatus = 'ready to process';
	    }
	}
	$sAddrRecip = $this->RecipAddrText();

	// -- actual financial calculations
	$rsTrx = $this->TransactionRecords();
	$ftTrxacts = $this->Trxact_RenderTable();

	// render form controls
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
    //echo 'RECORD:'.fcArray::Render($this->Values());
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
    //echo 'CTRLS:'.fcArray::Render($arCtrls);
	$arCtrls['ID'] = $this->SelfLink();
	$arCtrls['Status'] = $txtStatus;
	$arCtrls['QuoSale'] = clsMoney::Format_withSymbol($this->QuotedSaleAmt());
	$arCtrls['QuoShip'] = clsMoney::Format_withSymbol($this->QuotedShipAmt());
	$arCtrls['QuoFinal'] = clsMoney::Format_withSymbol($this->QuotedFinalAmt());

	if ($doEdit) {
	    $out .= "\n<form method=post>";

	    $htBalBtnsLine = NULL;
	} else {
	    $arCtrls['ID_Buyer'] = $this->BuyerLink();
	    $arCtrls['ID_BuyerCard'] = $this->BuyerCardLink();
	    $arCtrls['ID_Recip'] = $this->RecipLink();
	    $arCtrls['ID_RecipAddr'] = $this->RecipAddrLink();
	    
	    $out .= $this->AdminPulls_form();	// display/process Pull-edit form if needed

	    $this->AreTotalsOk(FALSE);

	    //$htCard = $this->CCardLink();

	    $ftBalBtns = $this->RenderBalanceButtons();

	    // I can't see how $ftBalBtns would ever be NULL
	    $htBalBtnsLine = "\n<tr><td align=center colspan=2>$ftBalBtns</td></tr>";
	}
	$arCtrls['BalBtnsLine'] = $htBalBtnsLine;

	if ($this->HasLines()) {
	    $oTotals = $this->TotalsObject($doEdit);
	    $htAmtMerch = $oTotals->RenderItems();
	} else {
	    $htAmtMerch = $oPage->Skin()->ErrorMessage('Order has no item lines.');
	}
	$arCtrls['AmtMerch'] = $htAmtMerch;

	if ($this->IsPulled()) {
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
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'<input type=submit name=btnCancel value="Cancel">'
	      .'</form>';
	}
	
	if ($this->IsActive()) {
	    $arMenuItm = array(
	      // 			array $arData,$sLinkKey,	$sGroupKey,$sDispOff,$sDispOn,$sDescr
	      new clsActionLink_option(array(),'pkg.stk',		'do','find stock',	NULL,"find stock for this order"),
	      );
	} else {
	    $arMenuItm = NULL;	// no package actions on a closed order
	}

	$out .= 
	  $this->Lines_RenderTable($arMenuItm)	// order lines
	  .$this->Pkg_RenderTable(NULL)
	  .$ftTrxacts
	  .$this->Msg_RenderTable()
	  .$this->Charge_RenderTable()
	  .$this->AdminPulls()
	  .$this->Event_RenderTable()
	  ;
	$out .= '<hr><span class="line-stats">generated by '.__FILE__.' line '.__LINE__.'</span>';
	return $out;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table class=listing>
  <tr><td align=right><b>ID</b>:</td><td>[[ID]]</td></tr>
  <tr><td align=right><b>Status</b>:</td><td>[[Status]]</td></tr>
  <tr><td align=right><b>Cart</b>:</td><td>[[Cart]]</td></tr>
  [[PullInfo]]
  <tr class=table-section-header><td align=left><b>Timestamps</b>:</td></tr>
  <tr><td align=right><b>Carted</b>:</td><td>[[WhenCarted]]</td>
    <td>when the cart record was created</td>
    </tr>
  <tr><td align=right><b>Created</b>:</td><td>[[WhenCreated]]</td>
    <td>when the order record was created (blank)</td>
    </tr>
  <tr><td align=right><b>Ported</b>:</td><td>[[WhenPorted]]</td>
    <td>when cart data was ported to the order record</td>
    </tr>
  <tr><td align=right><b>Placed</b>:</td><td>[[WhenPlaced]]</td>
    <td>when the order was officially submitted by the customer</td>
    </tr>
  <tr><td align=right><b>Revised</b>:</td><td>[[WhenRevised]]</td>
    <td>when the order was revised by the customer</td>
    </tr>
  <tr><td align=right><b>Edited</b>:</td><td>[[WhenEdited]]</td>
    <td>when the order record was last modified by anyone</td>
    </tr>
  <tr><td align=right><b>Needed by</b>:</td><td>[[WhenNeeded]]</td>
    <td>date by which customer needs to have received the order (blank = no deadline)</td>
    </tr>
  <tr><td align=right><b>Closed</b>:</td><td>[[WhenClosed]]</td>
    <td>when the order was completed (all items shipped, cancelled, or unavailable)</td>
    </tr>

  <tr class=table-section-header><td align=left><b>Buyer</b>:</td></tr>
  <tr><td align=right><b>ID</b>:</td><td>[[ID_Buyer]]</td></tr>
  <tr><td align=right><b>Name Text</b>:</td><td>[[BuyerName]]</td></tr>
  <tr><td align=right><b>Card ID</b>:</td><td>[[ID_BuyerCard]]</td></tr>

  <tr class=table-section-header><td align=left><b>Recipient</b>:</td></tr>
  <tr><td align=right><b>ID</b>:</td><td>[[ID_Recip]]</td></tr>
  <tr><td align=right><b>Name Text</b>:</td><td>[[RecipName]]</td></tr>
  <tr><td align=right><b>Address Text</b>:</td><td>[[RecipAddr]]</td></tr>
  <tr><td align=right><b>Address ID</b>:</td><td>[[ID_RecipAddr]]</td></tr>

  <tr class=table-section-header><td align=left><b>$ Amounts quoted</b>:</td></tr>
  <tr><td align=right><b>Merchandise</b>:</td><td>[[WebTotal_Merch]]</td></tr>
  <tr><td align=right><b>Shipping</b>:</td><td>[[WebTotal_Ship]]</td></tr>
  <tr><td align=right><b>Final total</b>:</td><td>[[WebTotal_Final]]</td></tr>
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
    /*----
      HISTORY:
	2011-03-23 adapted from VbzAdminItem to VbzAdminOrderItem
	2014-02-23 rewritten to return the object; renamed from BuildEditForm() to PageForm()
    */
    private $oPageForm;
    protected function PageForm() {
	if (empty($this->oPageForm)) {
	    // create fields & controls
 	    $oForm = new fcForm_DB($this);
 	    
	      $oField = new fcFormField_Num($oForm,'ID_Buyer');
	      $oField = new fcFormField_Num($oForm,'ID_BuyerCard');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->CardRecords());
	      $oField = new fcFormField_Text($oForm,'BuyerName');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>30));
		
	      $oField = new fcFormField_Num($oForm,'ID_Recip');
	      $oField = new fcFormField_Num($oForm,'ID_RecipAddr');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>4));
	      $oField = new fcFormField_Text($oForm,'RecipName');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>30));
	      $oField = new fcFormField_Text($oForm,'RecipAddr');
		$oCtrl = new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60));

	      $oField = new fcFormField_Num($oForm,'WebTotal_Merch');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));

	      $oField = new fcFormField_Num($oForm,'WebTotal_Ship');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));

	      $oField = new fcFormField_Num($oForm,'WebTotal_Final');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>6));

	      $oField = new fcFormField_Time($oForm,'WhenCarted');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		$oCtrl->Editable(FALSE);	// auto-update field

	      $oField = new fcFormField_Time($oForm,'WhenCreated');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		$oCtrl->Editable(FALSE);	// auto-update field

	      $oField = new fcFormField_Time($oForm,'WhenPorted');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		$oCtrl->Editable(FALSE);	// auto-update field

	      $oField = new fcFormField_Time($oForm,'WhenPlaced');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		$oCtrl->Editable(FALSE);	// auto-update field

	      $oField = new fcFormField_Time($oForm,'WhenRevised');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		$oCtrl->Editable(FALSE);	// auto-update field

	      $oField = new fcFormField_Time($oForm,'WhenEdited');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		$oCtrl->Editable(FALSE);	// auto-update field

	      $oField = new fcFormField_Time($oForm,'WhenNeeded');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));

	      $oField = new fcFormField_Time($oForm,'WhenClosed');
		$oCtrl = new fcFormControl_HTML_Timestamp($oField,array('size'=>14));
		// TODO: need a "close now" link, make the control read-only

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
    protected function ListingRow($bCalcStats,$isOdd) {
	$row = $this->Values();
	$htOdd = $isOdd?'class=odd':'class=even';

	$ftID		= $this->SelfLink();
	$strNum		= $this->NumberString();
	$strPull	= $this->PulledText();
	$sWho		= $this->WhoDescrip();
	$mnyTotal	= $row['WebTotal_Final'];
	$dtCreate	= $this->WhenCreated();
	$dtClosed	= $this->WhenPlaced();

	$wtNum = $strNum;
	$strTotal = fcMoney::Format_withSymbol($mnyTotal);
	$strWhenCreated = fcDate::NzDate($dtCreate);
	$strWhenClosed = fcDate::NzDate($dtClosed);

	$out = <<<__END__
  <tr $htOdd>
    <td><b>$ftID</b></td>
    <td>$wtNum</td>
    <td>$strPull</td>
    <td>$sWho</td>
    <td align=right>$strTotal</td>
    <td>$strWhenCreated</td>
    <td>$strWhenClosed</td>
__END__;

	if ($bCalcStats) {
	    $arSum = $this->ItemsNeeded_Summary();
	    if (is_array($arSum)) {
		$qtyOrd = $arSum['qty-ord'];
		$qtyOk = fcArray::Nz($arSum,'qty-shp');
		$qtyXed = fcArray::Nz($arSum,'qty-kld') + fcArray::Nz($arSum,'qty-na');
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
	    $out = static::ListingHeader($iCalcStats);
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		//$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$out .= $this->ListingRow($iCalcStats,$isOdd);
		$isOdd = !$isOdd;
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
	$htSubj = fcString::EncodeForHTML($txtSubj);
	$htPre = fcString::EncodeForHTML($txtMsgPre);

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
    protected function Lines_RenderTable(array $arInputMenu=NULL) {
	$oPage = $this->Engine()->App()->Page();

	$strAction = $oPage->pathArg('do');
	$doAddItem = ($strAction == 'add-item');
	if ($oPage->ReqArgBool('btnSaveItem')) {
	    $arFields = VCA_OrderLines::CaptureEdit();
	    VCA_OrderLines::SaveEdit($this,$arFields);
	}

	// set up titlebar menu
	$arLocalMenu = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'add-item',		'do','add',	NULL,'add a new item to the order'),
	  );
	$arMenu = array_merge($arLocalMenu,$arInputMenu);
	$out = $oPage->ActionHeader('Items',$arMenu);

	$rsRows = $this->LinesData();
	$rsRows->Want_ShowNewEntry($doAddItem);
	// TODO: Can we use AdminRows() instead?
	$out .= $rsRows->AdminTable_forOrder();

	return $out;
    }
    // PACKAGE subdata
    public function Pkg_RenderTable(array $arMenu=NULL) {
	$tbl = $this->PackageTable();
	$rs = $tbl->GetOrder($this->GetKeyValue());

	// Columns to display for Packages table
/*	$arCols = array(
	  'ID'			=> 'ID',
	  'Seq'			=> '#',
	  'ID_Shipment'		=> 'Shipped in',
	  'ChgShipItm'		=> '$/item',
	  'ChgShipPkg'		=> '$/pkg',
	  'WhenStarted'		=> 'Start',
	  'WhenFinished'	=> 'Finish',
	  'WhenChecked'		=> 'Check',
	  'WhenVoided'		=> 'Void',
	  'isReturn'		=> 'Type',
	  ); //*/

	$oPage = $this->PageObject();
	/* 2016-08-09 Package object now renders header/menu
	$out = $oPage->ActionHeader('Packages',$arMenu)
	  .$rs->AdminRows($arCols)
	  ; */
	$out = $rs->AdminRows();
	return $out;
    }
    /*----
      RETURNS: formatted code of header and table showing transactions for this order
    */
    protected function Trxact_RenderTable() {
	$id = $this->GetKeyValue();
	$oPage = $this->PageObject();
	
	// display transactions
	$rs = $this->TransactionRecords();
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array('order'=>$id),'add-trx',	'do','add',	NULL,'add a new transaction for this order'),
	  //new clsActionLink_option(array(),'show.open',	NULL,'open',	NULL,'show orders which have not been completed'),
	  //new clsActionLink_option(array(),'show.shut',	NULL,'shut',	NULL,'show orders which have been closed'),
	  );
	$out = $oPage->ActionHeader('Transactions',$arActs);
	$out .= $rs->AdminTable($this);

	return $out;
    }
    // MESSAGE subdata
    public function Msg_RenderTable() {
	$tbl = $this->MessageTable();
	$rs = $tbl->GetData('ID_Ord='.$this->GetKeyValue());
	return $this->Engine()->App()->Skin()->SectionHeader('Messages',NULL,'section-header-sub')
	  .$rs->AdminTable();
    }
    public function Charge_RenderTable() {
	// get table showing existing charges
	$tbl = $this->ChargeTable();
	$rs = $tbl->GetData('ID_Order='.$this->GetKeyValue());
	return $this->Engine()->App()->Skin()->SectionHeader('Charges',NULL,'section-header-sub')
	  .$rs->AdminTable();
    }
    public function Event_RenderTable() {
	return $this->EventListing();
    }
    /*----
      ACTION: Shows any Pulls for this Order, and the link to modify them, but does not display
	the form for pulling/releasing.
      HISTORY:
	2016-01-09 breaking up Pull_RenderTable() for code tidiness and so we can display the form
	  near the top even though the Pull table is displayed further down.
    */
    protected function AdminPulls() {
	$rs = $this->PullTable()->GetOrder($this->GetKeyValue());
	$oPage = $this->PageObject();
	$out = $oPage->SectionHeader('Pulls',NULL,'section-header-sub');
	
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
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		$row = $rs->Values();
		$id = $row['ID'];

		$idType = $row['ID_Type'];
		$strWhat = $rs->TypeName();

		$htWho = clsVbzData::WhoString_OLD1($row);

		$isRowPulled = $rs->IsPulled();

		$out .= <<<__END__
  <tr class="$cssClass">
    <td>$id</td>
    <td>$strWhat</td>
    <td>{$row['WhenPulled']}</td>
    <td>{$row['NotesPull']}</td>
  </tr>
__END__;
		if (!$isRowPulled) {
		    $out .= <<<__END__
  <tr class="$cssClass">
    <td colspan=2 align=right><b>released</b></td>
    <td>{$row['WhenFreed']}</td>
    <td>{$row['NotesFree']}</td>
  </tr>
__END__;
		}
	    }
	    $out .= "\n</table>";
	} else {
	    /* TableArg() is never set, and this is the only place that reads it.
	    $strDescr = $this->TableArg('descr');
	    $out .= fcString::ConcatArray(' ',array("\nNo pulls",$strDescr)).'.';
	    */
	    $out .= "\nNo pulls.";
	}
	
	// TODO: should this be in the action header?
	if ($this->IsPulled()) {
	    $strMsg = 'Release this order';
	} else {
	    $strMsg = 'Pull this order';
	}
	$arLink = $oPage->PathArgs();
	$arLink['form'] = 'pull';
	$url = $oPage->SelfURL($arLink);
	$htLink = clsHTML::BuildLink($url,$strMsg,'pull this order');
	$out .= ' [ '.$htLink.' ]';
	
	return $out;
    }
    /*----
      PURPOSE: where appropriate, displays the Pull-editing form and handles any user input from it
    */
    protected function AdminPulls_form() {
	$oPage = $this->PageObject();
	$tPulls = $this->PullTable();
	
	if ($this->AdminPulls_form_isSubmitted()) {
	    $sNotes = $oPage->ReqArgText('notes');
	    
	    if ($this->AdminPulls_form_wantsPull()) {
		$idType = $oPage->ReqArgInt('type');
		$sTypeName = $tPulls->Name_forType($idType);
		$sMsg = "Pulled order as <b>$sTypeName</b>";
		if (!empty($sNotes)) {
		    $ftNotes = fcString::EncodeForHTML($sNotes);
		    $sMsg .= " with note &ldquo;<b>$ftNotes</b>&rdquo;.";
		}
		$this->Pull($idType,$sNotes);
	    } elseif ($this->AdminPulls_form_wantsFree()) {
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
	    
	if ($this->AdminPulls_form_isNeeded()) {
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
      PURPOSE: determines whether the Pull-editing form should be displayed
      NOTE: We don't bother to check whether form data has been submitted, because when that happens
	the form data is processed and then the page is reloaded, so we never get this far.
    */
    protected function AdminPulls_form_isNeeded() {
	$oPage = $this->Engine()->App()->Page();
	return ($oPage->PathArg('form') == 'pull');
    }
    /*----
      PURPOSE: determines whether we need to process data from the Pull-editing form
    */
    protected function AdminPulls_form_isSubmitted() {
	return $this->AdminPulls_form_wantsPull()
	  || $this->AdminPulls_form_wantsFree()
	  ;
    }
    protected function AdminPulls_form_wantsPull() {
	return $this->PageObject()->ReqArgBool('btnPull');
    }
    protected function AdminPulls_form_wantsFree() {
	return $this->PageObject()->ReqArgBool('btnFree');
    }

    // -- ADMIN INTERFACE -- //

}
