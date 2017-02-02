<?php
/*
  FILE: dropins/misc/cashflow.php -- calculates cashflow by year
  HISTORY:
    2016-02-02 started
*/

class vctqCashflow extends fcConnectedData {

    // CEMENT
    protected function SingularName() {
	// Maybe a class-structure revision is needed?
	throw new exception('2017-01-06 I will be surprised if this actually gets called.');
    }
    
    // ++ CALLBACKS ++ //

    public function MenuExec() {
	return $this->AdminPage();
    }
    
    // -- CALLBACKS -- //
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      RETURNS: SQL
      DATA: restock purchases
    */
    protected function Purchases_SQL() {
	return <<<__END__
SELECT 
    YEAR(IFNULL(WhenDebited,
                IFNULL(WhenReceived, WhenShipped))) AS Time,
    SUM(TotalInvMerch) AS TotalMerch,
    SUM(TotalInvFinal - TotalInvMerch) AS TotalShip,
    SUM(TotalInvFinal) AS TotalSpent
FROM
    rstk_rcd
GROUP BY Time
__END__;
    }
    /*----
      RETURNS: SQL
      DATA: customer charges/refunds (order transactions of "cash" type)
    */
    protected function Revenue_SQL() {
	return <<<__END__
SELECT 
    YEAR(IFNULL(ot.WhenDone, o.WhenPlaced)) AS Time,
    SUM(ot.Amount) AS Revenue
FROM
    ord_trxact AS ot
        LEFT JOIN
    orders AS o ON ot.ID_Order = o.ID
    LEFT JOIN ord_trx_type AS ott
    ON ot.ID_Type=ott.ID
WHERE
    WhenVoid IS NULL AND ott.isCash
GROUP BY Time
__END__;
    }
    /*----
      RETURNS: SQL
      DATA: order shipping expenses
    */
    protected function Shipping_SQL() {
	return <<<__END__
SELECT 
    YEAR(WhenClosed) AS Time,
    SUM(IFNULL(SupplCost, 0) + IFNULL(OrderCost,
            (IFNULL(ReceiptCost, 0) - IFNULL(OutsideCost, 0)))) AS Cost
FROM
    ord_shipmt
WHERE
    WhenClosed IS NOT NULL
GROUP BY Time
__END__;
    }
    
    // -- SQL CALCULATIONS -- //
    // ++ ARRAY CALCULATIONS ++ //
    
    protected function AdminObject() {
	$oData = new vctCashflow();
    
	// purchases
	$rs = $this->DataSQL($this->Purchases_SQL());
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$sWhen = $rs->Value('Time');
		$oRow = $oData->Element($sWhen);
		$oRow->LoadPurchases($rs);
	    }
	}
	
	// revenue
	$rs = $this->DataSQL($this->Revenue_SQL());
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$sWhen = $rs->Value('Time');
		$oRow = $oData->Element($sWhen);
		$oRow->LoadRevenue($rs);
	    }
	}
	
	// shipping
	$rs = $this->DataSQL($this->Shipping_SQL());
	if ($rs->HasRows()) {
	    while ($rs->NextRow()) {
		$sWhen = $rs->Value('Time');
		$oRow = $oData->Element($sWhen);
		$oRow->LoadShipping($rs);
	    }
	}
	
	return $oData;
    }
    
    // -- ARRAY CALCULATIONS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$oData = $this->AdminObject();
	$out = $oData->RenderAll();
	return $out;
    }

    // ++ WEB UI ++ //

}

class vctCashflow {
    private $ar;
    
    public function __construct() {
	$this->ar = NULL;
    }

    public function Element($sRow) {
	if (!clsArray::Exists($this->ar,$sRow)) {
	    $this->ar[$sRow] = new vcrCashflowElement();
	}
	return $this->ar[$sRow];
    }
    protected function HasData() {
	return (count($this->ar) > 0);
    }
    public function RenderAll() {
	if ($this->HasData()) {
	    krsort($this->ar);
	    $out = vcrCashflowElement::RenderHeader();
	    foreach ($this->ar as $sWhen => $oRow) {
		$out .= $oRow->Render($sWhen);
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'no data';
	}
	return $out;
    }
}

class vcrCashflowElement {
    private $arRow;	// represents one time-span

    public function __construct() {
	$this->arRow = NULL;
    }
    
    // ++ RECORD PROCESSING ++ //
    
    public function LoadPurchases(clsRecs_generic $rs) {
	$this->SetMerchValue($rs->Value('TotalMerch'));
	$this->SetShipValue($rs->Value('TotalShip'));
	$this->SetSpentValue($rs->Value('TotalSpent'));
    }
    public function LoadRevenue(clsRecs_generic $rs) {
	// debt-based polarity - charges to customer are negative, so subtract from zero
	$this->SetRevenueValue(0-$rs->Value('Revenue'));
    }
    public function LoadShipping(clsRecs_generic $rs) {
	$this->SetShipCostValue($rs->Value('Cost'));
    }
    
    // -- RECORD PROCESSING -- //
    // ++ VALUES ++ //
    
    protected function SetMerchValue($v) {
	$this->ar['Merch'] = $v;
    }
    protected function GetMerchValue() {
	return clsArray::Nz($this->ar,'Merch');
    }
    protected function GetMerchText() {
	$n = clsArray::Nz($this->ar,'Merch');
	if (is_null($n)) {
	    return '-';
	} else {
	    return clsMoney::Format_number($n);
	}
    }

    protected function SetShipValue($v) {
	$this->ar['Ship'] = $v;
    }
    protected function GetShipValue() {
	return clsArray::Nz($this->ar,'Ship');
    }
    protected function GetShipText() {
	$n = clsArray::Nz($this->ar,'Ship');
	if (is_null($n)) {
	    return '-';
	} else {
	    return clsMoney::Format_number($n);
	}
    }

    protected function SetSpentValue($v) {
	$this->ar['Spent'] = $v;
    }
    protected function GetSpentValue() {
	return clsArray::Nz($this->ar,'Spent');
    }
    protected function GetSpentText() {
	$n = clsArray::Nz($this->ar,'Spent');
	if (is_null($n)) {
	    return '-';
	} else {
	    return clsMoney::Format_number($n);
	}
    }
    
    protected function SetRevenueValue($v) {
	$this->ar['Revenue'] = $v;
    }
    protected function GetRevenueValue() {
	return clsArray::Nz($this->ar,'Revenue');
    }
    protected function GetRevenueText() {
	$n = clsArray::Nz($this->ar,'Revenue');
	if (is_null($n)) {
	    return '-';
	} else {
	    return clsMoney::Format_number($n);
	}
    }

    protected function SetShipCostValue($v) {
	$this->ar['ShipCost'] = $v;
    }
    protected function GetShipCostValue() {
	return clsArray::Nz($this->ar,'ShipCost');
    }
    protected function GetShipCostText() {
	$n = clsArray::Nz($this->ar,'ShipCost');
	if (is_null($n)) {
	    return '-';
	} else {
	    return clsMoney::Format_number($n);
	}
    }
    
    protected function GetTotalLossesValue() {
	return $this->GetSpentValue()
	  + $this->GetShipCostValue()
	  ;
    }
    protected function GetTotalLossesText() {
	return clsMoney::Format_number($this->GetTotalLossesValue());
    }
    protected function GetTotalGainsValue() {
	return $this->GetRevenueValue();
    }
    protected function GetTotalGainsText() {
	return clsMoney::Format_number($this->GetTotalGainsValue());
    }
    protected function GetNetGainText() {
	$nLoss =$this-> GetTotalGainsValue()
	  - $this->GetTotalLossesValue()
	  ;
	return clsMoney::Format_number($nLoss);
    }
    
    // -- VALUES -- //
    // ++ WEB UI ++ //
    
    static public function RenderHeader() {
	return <<<__END__
<table class=listing>
  <tr>
    <th></th>
    <th colspan=4>Expenses</th>
    <th>Revenues</th>
    <th colspan=3>TOTALS</th>
  </tr>
  <tr>
    <th>When</th>
    <th>Merch</th>
    <th>s/h In</th>
    <th>Inv Tot</th>
    <th>s/h Out</th>
    <th>Sales</th>
    <th>Gains</th>
    <th>Losses</th>
    <th>NET GAIN</th>
  </tr>
__END__;
    }
    public function Render($sWhen) {
	static $isOdd=FALSE;
    
	$nMerch = $this->GetMerchText();
	$nShip = $this->GetShipText();
	$nSpent = $this->GetSpentText();
	$nRevenue = $this->GetRevenueText();
	$nShCost = $this->GetShipCostText();
	
	$nGains = $this->GetTotalGainsText();
	$nLosses = $this->GetTotalLossesText();
	$nNet = $this->GetNetGainText();
	
	$cssClass = $isOdd?'odd':'even';
	
	$out = <<<__END__
  <tr class=$cssClass>
    <td>$sWhen</td>
    <td align=right>$nMerch</td>
    <td align=right>$nShip</td>
    <td align=right>$nSpent</td>
    <td align=right>$nShCost</td>
    <td align=right>$nRevenue</td>
    <td align=right>$nGains</td>
    <td align=right>$nLosses</td>
    <td align=right>$nNet</td>
  </tr>
__END__;
	return $out;
    }
    
    // -- WEB UI -- //
}