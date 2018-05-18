<?php
/*
  FILE: dropins/orders/order.php -- customer order administration dropin for VbzCart
  HISTORY:
    2010-10-16 Extracted order management classes from SpecialVbzAdmin.php
    2013-12-15 Adapting for drop-in module system.
    2017-06-05 updated stuff earlier; fixed title to describe filtering
  TODO:
  * allow for multiple filters
  * tidy up filter description calculation, maybe?
  ** We're currently calculating it two different ways.
*/

// Transaction Types
define('KI_ORD_TXTYPE_ITEM_SOLD',11);	// total cost of items sold
define('KI_ORD_TXTYPE_PERITM_SH',1);	// per-item shipping charge total
define('KI_ORD_TXTYPE_PERPKG_SH',2);	// per-package shipping charge
define('KI_ORD_TXTYPE_PAID_CC',6);	// payment: credit card

class vctAdminOrders extends vctOrders implements fiEventAware, fiLinkableTable {
    use ftLinkableTable;
    use ftExecutableTwig;

    // ++ SETUP ++ //
    
    // OVERRIDE
    protected function SingularName() {
	return 'vcrAdminOrder';
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {    }
    protected function OnRunCalculations() {
	$this->AdminPageMenu();
	$sOption = $this->GetOption_Description();
	$sTitle = $sOption.' Orders';
	$htTitle = 'Orders: '.$sOption;
    
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle('Order Management');
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: execution method called by dropin menu
    */ /*
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminPage();
	return $out;
    } */

    // -- EVENTS -- //
    // ++ CLASSES ++ //

    protected function LinesClass() {
	return KS_CLASS_ORDER_LINES;
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    public function LineTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->LinesClass(),$id);
    }

    // -- TABLES -- //
    // ++ ARRAY ++ //

    /*-----
      RETURNS: A list of all items needed to fill open orders.
    */
    public function ItemsNeeded() {
	throw new exception('2017-06-05 This will need rewriting, if it is still used.');
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

    // -- ARRAYS -- //
    // ++ INPUT ++ //

    private $doShowOpen;
    protected function SetOption_ShowOpen($b) {
	$this->doShowOpen = $b;
    }
    protected function GetOption_ShowOpen() {
	return $this->doShowOpen;
    }
    private $doShowShut;
    protected function SetOption_ShowShut($b) {
	$this->doShowShut = $b;
    }
    protected function GetOption_ShowShut() {
	return $this->doShowShut;
    }
    private $doShowHeld;
    protected function SetOption_ShowHeld($b) {
	$this->doShowHeld = $b;
    }
    protected function GetOption_ShowHeld() {
	return $this->doShowHeld;
    }
    private $doShowUncon;
    protected function SetOption_ShowUnconfirmed($b) {
	$this->doShowUncon = $b;
    }
    protected function GetOption_ShowUnconfirmed() {
	return $this->doShowUncon;
    }
    protected function GetOption_Description() {
	$s = NULL;
	if ($this->GetOption_ShowOpen()) {
	    $s = 'open';
	}
	if ($this->GetOption_ShowShut()) {
	    $s = fcString::Concat(', ',$s,'shut');
	}
	if ($this->GetOption_ShowHeld()) {
	    $s = fcString::Concat(', ',$s,'on-hold');
	}
	if ($this->GetOption_ShowUnconfirmed()) {
	    $s = fcString::Concat(', ',$s,'unconfirmed');
	}
	return $s;
    }

    // -- INPUT -- //
    // ++ OUTPUT ++ //

    protected function RenderSearch() {
	$oFormIn = fcHTTP::Request();

	$sPfx = $this->GetActionKey();
	$htSearchOut = NULL;

	$sSearchName = $sPfx.'-needle';
	$sInput = $oFormIn->GetString($sSearchName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forText($sInput);
	    $htSearchOut .= $rs->Listing('No matching order records.');
	}
	$htFind = '"'.fcString::EncodeForHTML($sInput).'"';

	$sOrderName = $sPfx.'-order';
	$sInput = $oFormIn->GetString($sOrderName);
	$doSearch = (!empty($sInput));
	if ($doSearch) {
	    $rs = $this->Search_forOrdNum($sInput);
	    $htSearchOut .= $rs->Listing('No matching order records.');
	}
	$htOrd = '"'.fcString::EncodeForHTML($sInput).'"';

	// build forms

	$oHdr = new fcSectionHeader('Search');
	$htSearchHdr = $oHdr->Render();
	$htSearchForm = <<<__END__
<div class=content>
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
</div>
__END__;

	$out = $htSearchHdr.$htSearchForm;
	if (!is_null($htSearchOut)) {
	    $oHdr = new fcSectionHeader('Search Results');
	    $out .= $oHdr->Render()
	      .$htSearchOut
	      ;
	}

	return $out;
    }
    protected function AdminPageMenu() {
	$oMenu = fcApp::Me()->GetHeaderMenu();

	// eventually we need a fcHeaderToggleGroup so we can have multiple selections active
	$oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Show'));
//	  $sShow = $oGrp->GetChoiceValue();
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	  $oGrp->SetChoice($ol = new fcHeaderChoice('open','show only confirmed orders which have not been filled'));
	    $this->SetOption_ShowOpen( $ol->GetIsSelected() );
	  $oGrp->SetChoice($ol = new fcHeaderChoice('shut','show only orders which have been closed'));
	    $this->SetOption_ShowShut( $ol->GetIsSelected() );
	  $oGrp->SetChoice($ol = new fcHeaderChoice('pulled','show only pulled orders'));
	    $this->SetOption_ShowHeld( $ol->GetIsSelected() );
	  $oGrp->SetChoice($ol = new fcHeaderChoice('uncon','show only unconfirmed orders','tentative'));
	    $this->SetOption_ShowUnconfirmed( $ol->GetIsSelected() );
    }
    public function AdminPage() {
    
	$doShowHeld = $this->GetOption_ShowHeld();
	$doShowShut = $this->GetOption_ShowShut();
	$doShowOpen = $this->GetOption_ShowOpen();
	$doShowUncon = $this->GetOption_ShowUnconfirmed();
	
	$doShow = FALSE;
	$arDescr = array();
	if ($doShowUncon) {
	    $arFilt[] = '(WhenPlaced IS NULL)';
	    $arDescr[] = 'unconfirmed';
	    $doShow = TRUE;
	    $doCalc = TRUE;	// do additional calculations to get order's status
	}
	if ($doShowOpen) {
	    $arFilt[] = '(WhenHeld IS NULL) AND (WhenClosed IS NULL) AND (WhenPlaced IS NOT NULL)';
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
	if ($doShowHeld) {
	    $arFilt[] = '(WhenHeld IS NOT NULL)';
	    $arDescr[] = 'pulled';
	    $doShow = TRUE;
	    $doCalc = FALSE;
	}
	
	$out = $this->RenderSearch();
	if ($doShow) {
	    $qof = new fcSQLt_Filt('AND',$arFilt);
	    $sqlFilt = $qof->RenderValue();
	    $rs = $this->SelectRecords($sqlFilt,'SortPfx, Number DESC');
	    $qRows = $rs->RowCount();
	    $sDescr = fcString::ConcatArray(', ',$arDescr);
	    
	    $oHdr = new fcSectionHeader('All Orders that are '.$sDescr);
	    $out .= $oHdr->Render()
	      ."<div class=content>Showing $qRows order".fcString::Pluralize($qRows)
	      .'</div>:'
	      .$rs->Listing("No records found.",$doCalc)
	      .'<span class=line-stats>('.$rs->sql.')</span>'
	      ;
	} else {
	    $out .= '<div class=content>No filter selected; not displaying any orders.</div>';
	}
	return $out;
    }

    // -- OUTPUT -- //
}
/*::::
  HISTORY:
    2010-11-26 copied AdminRedirect() from VbzAdminCatalog to clsRstkRcd
    2011-01-02 copied AdminRedirect() from clsRstkRcd to VbzAdminOrderTrxact
    2011-09-18 boilerplate logging methods added - copied from VbzAdminOrderItem
    2011-12-20 copied AdminRedirect() from VbzAdminOrderTrxact to VbzAdminOrder
    2013-01-14 adapted as dropin module
*/
class vcrAdminOrder extends vcrOrder implements fiLinkableRecord, fiEventAware, fiEditableRecord {
    use ftLinkableRecord;
    use ftSaveableRecord;
    use ftLoggableRecord;
    use ftExecutableTwig;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	$this->ResetLineRecords();
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$sNum = $this->NumberString();
	$id = $this->GetKeyValue();
	
	$htTitle = "Retail Order #$sNum";
	$sTitle = "ord $sNum ($id)";

	$oPage = fcApp::Me()->GetPageObject();	
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*----
      PURPOSE: initialization method called by dropin menu
    */
    public function MenuInit(array $arArgs=NULL) {
	throw new exception('2017-04-11 Does anything actually call this anymore?');
	return $this->AdminInit();
    }

    // -- EVENTS -- //
    // ++ LOCAL STATUS ++ //

    private $doEdit;
    protected function SetDoEdit($fOn) {
	$this->doEdit = $fOn;
    }
    protected function GetDoEdit() {
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
	return $this->NumberString();
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
    // ++ CLASSES ++ //

    protected function CustomersClass() {
	return KS_CLASS_ADMIN_CUSTOMERS;
    }
    protected function CustAddrsClass() {
	return KS_CLASS_MAIL_ADDRS_ADMIN;
    }
    public function TrxactsClass() {
	return KS_CLASS_ORDER_TRXS;
    }
    public function CardsClass() {
	return KS_CLASS_CUST_CARDS_ADMIN;
    }
    protected function CartsClass() {
	if (fcApp::Me()->GetDropinManager()->HasModule('vbz.carts')) {
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
    /*protected function PullsClass() {
	return KS_CLASS_ORDER_PULLS;
    }*/
    protected function HoldsClass() {
	return KS_CLASS_ORDER_HOLDS;
    }
    protected function CustomerEmailsClass() {
	return KS_CLASS_EMAIL_ADDRS_ADMIN;
    }
    protected function CustomerPhonesClass() {
	return KS_CLASS_CUST_PHONES_ADMIN;
    }
    // TODO: will probably want a special subclass for Order Events
    /* just get the event table from the App object
    protected function GetEventsClass() {
	return fcApp::Me()->GetEventsClass();
    } */

    // -- CLASSES -- //
    // ++ TABLES ++ //

    // TODO: make these protected or document why they are public

    /*----
      PUBLIC to minimize what other classes need to know about internals
      USED BY package class
    */
    public function LineTable($id=NULL) {
	return $this->GetTableWrapper()->LineTable($id);
    }
    protected function CustTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ADMIN_CUSTOMERS,$id);
    }
    protected function CustAddrTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CustAddrsClass(),$id);
    }
    public function CardTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CardsClass(),$id);
    }
    public function ChargeTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->ChargesClass(),$id);
    }
    public function MessageTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->MessagesClass(),$id);
    }
    public function TrxactTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TrxactsClass(),$id);
    }
    protected function PackageTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->PackagesClass(),$id);
    }
    /*
    protected function PullTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->PullsClass(),$id);
    }*/
    protected function HoldTable() {
	return $this->GetConnection()->MakeTableWrapper($this->HoldsClass());
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    public function PullRecord() {
	throw new exception('2017-06-03 Call HoldingRecords() instead.');
	$rcPull = $this->PullTable($this->PullID());
	return $rcPull;
    }
    // ACTION: get holding records for this Order, newest first
    protected function HoldingRecords() {
	return $this->HoldTable()->Records_forOrder($this->GetKeyValue());
//	return $this->HoldingTable()->SelectRecords('ID_Order='.$this->GetKeyValue(),'WhenStart DESC');
    }
    
    private $rsLines;
    protected function ResetLineRecords() {
	$this->rsLines = NULL;
    }
    protected function SetLineRecords($rs) {	// TODO: class for param!
	$this->rsLines = $rs;
    }
    /*-----
      RETURNS: Recordset of lines for this order, sorted by Seq
      HISTORY:
	2017-04-11 does not appear to be called from outside, so making it PROTECTED
    */
    protected function GetLineRecords(bool $bRefresh=FALSE) {
	if ($bRefresh || is_null($this->rsLines)) {
	    $tbl = $this->LineTable();
	    $this->rsLines = $tbl->SelectRecords('ID_Order='.$this->GetKeyValue(),'Seq');
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
	$rs = $this->PackageTable()->SelectRecords($sqlFilt);
	return $rs;
    }
    public function CardRecord() {
	$idCard = $this->CardID();
	return $this->CardTable($idCard);
    }
    protected function CardRecords() {
	if ($this->HasBuyer()) {
	    $idCust = $this->GetBuyerID();
	    $rsCards = $this->CardTable()->SelectRecords('ID_Cust='.$idCust);
	    return $rsCards;
	} else {
	    return NULL;
	}
    }
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
	$out = $this->GetFieldValue('BuyerName');
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
	$out = $this->GetFieldValue('RecipName');
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
    // ++ FIELD VALUES ++ //

    // CALLED BY Order Item
    // TODO: Rename to WhenCreated()
    public function WhenStarted() {
    	return $this->GetFieldValue('WhenCreated');
    }
    protected function WhenHeld() {
    	return $this->GetFieldValue('WhenHeld');
    }
    protected function PullID() {
	throw new exception('2017-06-03 Not how it works anymore...');
	return $this->GetFieldValue('ID_Pull');
    }
    protected function CardID() {
	return $this->GetFieldValue('ID_BuyerCard');
    }
    //--
    protected function QuotedSaleAmt() {
	return $this->GetFieldValue('WebTotal_Merch');
    }
    protected function QuotedShipAmt() {
	return $this->GetFieldValue('WebTotal_Ship');
    }
    protected function QuotedFinalAmt() {
	return $this->GetFieldValue('WebTotal_Final');
    }
    public function NameString() {
	return $this->GetFieldValue('Number');
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
	return $this->IsPlaced() && !$this->IsOnHold() && !$this->IsClosed();
    }
    protected function IsOnHold() {
	return !is_null($this->WhenHeld());
    }
    protected function IsPulled() {
	throw new exception('2017-06-03 Call IsOnHold() instead.');
	echo 'PULLED?=['.!is_null($this->PullID()).'] PULL ID=['.$this->PullID().']<br>';
	return !is_null($this->PullID());
    }
    protected function HoldingText() {
	$rs = $this->HoldingRecords();
	$sAbout = $rs->ListActiveHolds();
	return $sAbout;
    }
    public function PulledText() {
	throw new exception('2017-06-03 Call HoldingText() instead.');
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
	    return $rc->GetSafeString();
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
	$rsLines = $this->GetLineRecords();
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
    }
    private $oTotals;
    protected function TotalsObject() {
	if (empty($this->oTotals)) {
	    $doEdit = $this->GetDoEdit();
	    // calculate line totals
	    $rs = $this->GetLineRecords();	// get order lines
	    if ($rs->hasRows()) {
		$arTotItm = $rs->FigureTotals();

		$prcCalcShItm = $arTotItm['sh-itm'];
		$prcCalcShPkg = $arTotItm['sh-pkg'];
		$prcCalcShip = $prcCalcShItm + $prcCalcShPkg;
		$prcCalcSale = $arTotItm['cost-sell'];
		$prcCalcTotal = $prcCalcShip + $prcCalcSale;

		// get web totals
		$prcTotMerch = $this->QuotedSaleAmt();
		$prcTotShip = $this->QuotedShipAmt();
		$prcTotFinal = $this->QuotedFinalAmt();

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
	$rs = $t->SelectRecords('ID_Order='.$this->GetKeyValue());
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
	throw new exception('2017-04-29 Order Events need refactoring, so this will need some rewriting.');
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
	$rs = $this->GetLineRecords();	// get order lines
	if ($rs->hasRows()) {
	    $ar = $rs->FigureTotals();
	    $prcNewShItm = $ar['sh-itm'];
	    $prcNewShPkg = $ar['sh-pkg'];
	    $prcNewShip = $prcNewShItm + $prcNewShPkg;
	    $prcNewSale = $ar['cost-sell'];
	    $prcNewTotal = $prcNewShip + $prcNewSale;

	    $prcOldShip = $this->QuotedShipAmt();
	    $prcOldSale = $this->QuotedSaleAmt();
	    $prcOldTotal = $this->QuotedFinalAmt();

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

	$idCart = $this->GetCartID();
	$idOrder = $this->GetKeyValue();

	if (is_null($idCart)) {
	    $arErrs[] = 'no default cart';
	} else {
	    $rcCart = $this->CartRecord();
	    $htOut .= $rcCart->SelfLink();
	}
	$rsCart = $this->CartTable()->SelectRecords('ID_Order='.$idOrder);
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
      USED ONLY BY AdminInit(), which may itself be unused
    */
    protected function AdminInit() {
	$this->PageObject()->Skin()->SetBrowserTitle('ord#'.$this->Number());	// browser title
    }
    /*----
      PURPOSE: Starting point for admin page
	This method calls other methods to do the work, depending on input
    */
    protected function AdminPage() {
	$oFormIn = fcHTTP::Request();
	$doSave = $oFormIn->GetBool('btnSave');
	if ($doSave) {
	    $this->PageForm()->Save();
	    // return to the list form after saving
	    $this->SelfRedirect();
	}
	
	$sNum = $this->NumberString();

	$oMenu = fcApp::Me()->GetHeaderMenu();

	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('do','Manage'));
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('rcpt',"receipt for order #$sNum",'receipt'));
	    $oGrp->SetChoice($ol = new fcHeaderChoice('email','sent an email'));
	    $oGrp->SetChoice($ol = new fcHeaderChoice('edit',"edit order #$sNum"));
 
	    $sShow = $oGrp->GetChoiceValue();
 
	/*
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
	*/

	// handle actions
	$out = NULL;
	$this->SetDoEdit(FALSE);
	switch ($sShow) {
	  case 'edit':
	    $this->SetDoEdit(TRUE);
	    break;
	  case 'receipt':
	    $out .= $this->RenderReceipt();
	    break;
	  case 'email':
	    $out .= $this->AdminPage_email();
	    break;
	  case 'charge':
	    $ftRecalcStat = $this->DoChargeCard_forBalance();
	    break;	// to be written - use DoChargeCard() with balance from trx recordset
	  case 'recalc':
	    $ftRecalcStat = $this->DoRecalcBal();
	    break;
	  default:
	    $ftRecalcStat = NULL;
	}

	$out .= $this->AdminPage_basic()
	  .$ftRecalcStat
	  ;

	return $out;
    }
    /*----
      ACTION: Displays the normal admin page
    */
    protected function AdminPage_basic() {
	$doEdit = $this->GetDoEdit();
	$out = NULL;

	$isOnHold = FALSE;
	// get order status
	if ($this->NeedSetup()) {
	    $txtStatus = 'not set up yet';
	} else {
	    $isOnHold = $this->IsOnHold();
	    if ($isOnHold) {
		$sMsg = 'This order is on hold.';
		fcApp::Me()->GetPageObject()->AddWarningMessage($sMsg);
		$txtStatus = 'on hold';
	    } else {
		$sMsg = 'This order is set up and ready to process.';
		fcApp::Me()->GetPageObject()->AddSuccessMessage($sMsg);
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

	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	
	$arCtrls['ID'] = $this->SelfLink();
	$arCtrls['Status'] = $txtStatus;
	$arCtrls['QuoSale'] = fcMoney::Format_withSymbol($this->QuotedSaleAmt());
	$arCtrls['QuoShip'] = fcMoney::Format_withSymbol($this->QuotedShipAmt());
	$arCtrls['QuoFinal'] = fcMoney::Format_withSymbol($this->QuotedFinalAmt());

	if ($doEdit) {
	    $out .= "\n<form method=post>";

	    $htBalBtnsLine = NULL;
	} else {
	    $arCtrls['ID_Buyer'] = $this->BuyerLink();
	    $arCtrls['ID_BuyerCard'] = $this->BuyerCardLink();
	    $arCtrls['ID_Recip'] = $this->RecipLink();
	    $arCtrls['ID_RecipAddr'] = $this->RecipAddrLink();
	    
	    $out .= $this->AdminHolds_form();	// display/process Holds management form as needed

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
	    $s = 'Order has no item lines.';
	    fcApp::Me()->GetPageObject()->AddWarningMessage($s);
	}
	$arCtrls['AmtMerch'] = $htAmtMerch;

	if ($isOnHold) {
	    $htHoldText = $this->HoldingText();
	    $htHoldLine = "\n<tr><td align=right><b>On Hold</b>:</td><td>".$htHoldText.'</td></tr>';
	} else {
	    $htHoldLine = NULL;
	}
	$arCtrls['PullInfo'] = $htHoldLine;

	if (isset($ftRecalcStat)) {
	    $htRecalcLine = "\n<tr><td align=left colspan=2>$ftRecalcStat</td></tr>";
	} else {
	    $htRecalcLine = NULL;
	}
	$arCtrls['RecalcLine'] = $htRecalcLine;

	$arCart = $this->CartList();
	$arCtrls['Cart'] = $arCart['html'];

	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	if ($doEdit) {
	    $out .=
	      '<input type=submit name=btnSave value="Save">'
	      .'<input type=reset value="Revert">'
	      .'<input type=submit name=btnCancel value="Cancel">'
	      .'</form>';
	}
	
	$oMenu = new fcHeaderMenu();
	  if ($this->IsActive()) {
	      // ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	      $oMenu->SetNode($ol = new fcMenuOptionLink('do','pkg.stk',NULL,'find stock',NULL,'find stock for this order'));
	  }

	/*
	if ($this->IsActive()) {
	    $arMenuItm = array(
	      // 			array $arData,$sLinkKey,	$sGroupKey,$sDispOff,$sDispOn,$sDescr
	      new clsActionLink_option(array(),'pkg.stk',		'do','find stock',	NULL,"find stock for this order"),
	      );
	} else {
	    $arMenuItm = NULL;	// no package actions on a closed order
	}*/

	$out .= 
	  $this->Lines_RenderTable($oMenu)	// order lines
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
<table class=form-record>
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
		$oCtrl->SetRecords($this->CardRecords());
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
	$htOdd = $isOdd?'class=odd':'class=even';

	$ftID		= $this->SelfLink();
	$strNum		= $this->NumberString();
	$strPull	= $this->HoldingText();
	$sWho		= $this->WhoDescrip();
	$mnyTotal	= $this->QuotedFinalAmt();
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
    protected function Lines_RenderTable(fcHeaderMenu $oMenu) {
	$oFormIn = fcHTTP::Request();
	if ($oFormIn->GetBool('btnSaveItem')) {
	    $arFields = vctAdminOrderLines::CaptureEdit();
	    vctAdminOrderLines::SaveEdit($this,$arFields);
	    // 2017-04-11 Should there be a redirect here?
	}
	
	$oMenu->SetNode($ol = new fcMenuOptionLink('do','add-item','add',NULL,'add a new item to the order'));
	  $doAddItem = $ol->GetIsSelected();

	//$strAction = $oPage->pathArg('do');
	//$doAddItem = ($strAction == 'add-item');

	// set up titlebar menu
	// ($sGroupKey,$sKeyValue=TRUE,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL)
	/*
	$arLocalMenu = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array(),'add-item',		'do','add',	NULL,'add a new item to the order'),
	  );
	$arMenu = array_merge($arLocalMenu,$arInputMenu);
	$out = $oPage->ActionHeader('Items',$arMenu);
	*/
	
	$oHdr = new fcSectionHeader('Items',$oMenu);

	$rsRows = $this->GetLineRecords();
	$rsRows->Want_ShowNewEntry($doAddItem);
	// TODO: Can we use AdminRows() instead?
	$out = $oHdr->Render()
	  .$rsRows->AdminTable_forOrder()
	  ;

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
	/*
	$id = $this->GetKeyValue();
	$arActs = array(
	  // 			array $iarData,$iLinkKey,	$iGroupKey,$iDispOff,$iDispOn,$iDescr
	  new clsActionLink_option(array('order'=>$id),'add-trx',	'do','add',	NULL,'add a new transaction for this order'),
	  //new clsActionLink_option(array(),'show.open',	NULL,'open',	NULL,'show orders which have not been completed'),
	  //new clsActionLink_option(array(),'show.shut',	NULL,'shut',	NULL,'show orders which have been closed'),
	  );
	$out = $oPage->ActionHeader('Transactions',$arActs);
	*/
	
	$oMenu = new fcHeaderMenu();

	  $oMenu->SetNode($oGrp = new fcHeaderChoiceGroup('show','Show'));
						// ($sKeyValue,$sPopup=NULL,$sDispOff=NULL,$sDispOn=NULL)
	    $oGrp->SetChoice($ol = new fcHeaderChoice('open','show orders which have not been completed'));
	    $oGrp->SetChoice($ol = new fcHeaderChoice('shut','show orders which have been closed out'));

	$oHdr = new fcSectionHeader('Transactions',$oMenu);	

	// put the output bits together
	$rs = $this->TransactionRecords();
	$out = $oHdr->Render()
	  .$rs->AdminTable($this)
	  ;

	return $out;
    }
    // MESSAGE subdata
    public function Msg_RenderTable() {
	$tbl = $this->MessageTable();
	$rs = $tbl->SelectRecords('ID_Ord='.$this->GetKeyValue());
	$oHdr = new fcSectionHeader('Messages');
	return $oHdr->Render()
	  .$rs->AdminTable()
	  ;
    }
    public function Charge_RenderTable() {
	// get table showing existing charges
	$tbl = $this->ChargeTable();
	$rs = $tbl->SelectRecords('ID_Order='.$this->GetKeyValue());
	$oHdr = new fcSectionHeader('Charges');
	return $oHdr->Render()
	  .$rs->AdminTable()
	  ;
    }
    public function Event_RenderTable() {
	return $this->EventListing();
    }
    private $frmHold=NULL;
    protected function OrderHoldsForm() {
	if (is_null($this->frmHold)) {
	    $this->frmHold = new vcOrderHoldsForm($this);
	}
	return $this->frmHold;
    }
    /*----
      ACTION: Shows any Pulls for this Order, and the link to modify them, but does not display
	the form for pulling/releasing.
      HISTORY:
	2016-01-09 breaking up Pull_RenderTable() for code tidiness and so we can display the form
	  near the top even though the Pull table is displayed further down.
    */
    protected function AdminPulls() {
    
	/*
	$rs = $this->PullTable()->GetOrder($this->GetKeyValue());
	$oPage = $this->PageObject();
	$oHdr = new fcSectionHeader('Pulls');
	$out = $oHdr->Render();

	// build "pull" control
	if ($this->IsPulled()) {
	    $sMsg = 'Release this order';
	} else {
	    $sMsg = 'Pull this order';
	}
	$arLink['form'] = 'pull';
	$url = $this->SelfURL($arLink);		// 2017-04-11 not tested
	$htLink = fcHTML::BuildLink($url,$sMsg);
	
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

		$row = $rs->GetFieldValues();
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
		$out .= "\n<tr><td colspan=4>[ $htLink ]</td></tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out .= "\n<div class=content>No pulls. [ $htLink ]</div>";
	}
		
	return $out;
	*/
	
	$frm = $this->OrderHoldsForm();
	return $frm->AdminRows();
    }
    /*----
      PURPOSE: where appropriate, displays the Hold-management form and handles any user input from it
    */
    protected function AdminHolds_form() {
	$frm = $this->OrderHoldsForm();
	return $frm->AdminRequest();
	/*
	
	$oPage = $this->PageObject();
	$tHolds = $this->HoldingTable();
	
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
	*/
    }
    /*----
      PURPOSE: determines whether the Pull-editing form should be displayed
      NOTE: We don't bother to check whether form data has been submitted, because when that happens
	the form data is processed and then the page is reloaded, so we never get this far.
    */
    /*
    protected function AdminPulls_form_isNeeded() {
	return (fcApp::Me()->GetKioskObject()->GetInputObject()->GetString('form') == 'pull');
    }*/
    /*----
      PURPOSE: determines whether we need to process data from the Pull-editing form
    */ /*
    protected function AdminPulls_form_isSubmitted() {
	return $this->AdminPulls_form_wantsPull()
	  || $this->AdminPulls_form_wantsFree()
	  ;
    }
    protected function AdminPulls_form_wantsPull() {
	return fcHTTP::Request()->GetBool('btnPull');
    }
    protected function AdminPulls_form_wantsFree() {
	return fcHTTP::Request()->GetBool('btnFree');
    } */

    // -- ADMIN INTERFACE -- //

}
