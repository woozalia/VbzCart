<?php
/*
  HISTORY:
    2016-01-23 created as stock.info.php - for now, just generaes SQL; may make it the base of StockLines classes later
    2016-03-03 moved *Info classes into stock.info.php; made stock.php for straight-table base classes
      ...but now I'm not sure those will ever actually be needed.
*/

class vcqtStockLinesInfo extends vctStockLines {
    
    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'vcrStockLineInfo';
    }

    // -- OVERRIDES -- //
    // ++ STATIC ++ //

    static public function SQL_forItemStatus($sqlFilt=NULL) {
	$sqlClause = is_null($sqlFilt)?'':"AND $sqlFilt";
	return <<<__END__
SELECT 
    si.ID_Item, SUM(si.Qty) AS QtyForSale
FROM
    stk_items AS si
        JOIN
    stk_bins AS sb ON si.ID_Bin = sb.ID
WHERE
    (si.WhenRemoved IS NULL)
	AND (si.Qty > 0)
        AND (sb.isForSale)
        AND (sb.isEnabled)
        AND (sb.WhenVoided IS NULL)
        $sqlClause
GROUP BY si.ID_Item
__END__;
    }

    /*----
      RETURNS: Just the filter object for retrieving item stock-status information
    */
    static protected function FieldArray_ItemStatus_byItem() {
	return array(
	    'ID_Item'	=> 'si.ID_Item',
	    'QtyForSale' => 'SUM(si.Qty)'
	    );
    }
    static protected function FieldArray_ItemStatus_byItem_wBin() {
	return array(
	    'ID_Item'	=> 'si.ID_Item',
	    'ID_Bin'	=> 'si.ID_Bin',
	    'si.Qty',
	    'sb.isForSale',
	    'sb.isForShip'
	    );
    }
    static protected function SQOF_ItemStatus() {
	return new fcSQLt_Filt('AND',
	      array(
		'si.WhenRemoved IS NULL',
		'si.Qty>0',
		'sb.isEnabled',
		'sb.WhenVoided IS NULL'
		)
	      );
    }
    static protected function SQOF_ItemStatus_onlyForSale() {
	$oFilt = self::SQOF_ItemStatus();
	$oFilt->AddCond('sb.isForSale');
	return $oFilt;
    }
    static protected function SQOJ_ItemStatus() {
	$qtSI = new fcSQL_TableSource('stk_items','si');
	$qtSB = new fcSQL_TableSource('stk_bins','sb');
	$arJT = array(
	  new fcSQL_JoinElement($qtSI),
	  new fcSQL_JoinElement($qtSB,'si.ID_Bin = sb.ID')
	  );
	return new fcSQL_JoinSource($arJT);
    }
    // NOTE: Only includes items for sale. Should probably be renamed later.
    static public function SQO_forItemStatus() {
	$qJoin = self::SQOJ_ItemStatus();
	$qjSel = new fcSQL_Select($qJoin);
	$qjSel->Fields()->ClearFields();
	$qjSel->Fields()->SetFields(self::FieldArray_ItemStatus_byItem());
	$qTerms = new fcSQL_Terms(
	  array(
	    self::SQOF_ItemStatus_onlyForSale(),
	    new fcSQLt_Group(array('si.ID_Item'))	// fcSQL_Terms(array(fcSQL_Filt(),*fcSQL_Group()*...
	    )		// fcSQL_Terms(*array(fcSQL_Filt(),fcSQL_Group())*...
	  );	// *fcSQL_Terms(array(fcSQL_Filt(),fcSQL_Group()))*;
	$qry = new fcSQL_Query($qjSel,$qTerms);
	return $qry;
    }
    /*----
      PRODUCES: items in stock (for sale), with quantities, grouped by item and bin
	Also includes items not for sale (because some items are available for shipping only).
    */
    static public function SQO_forItemStatus_wBin() {
	$qJoin = self::SQOJ_ItemStatus();
	$qjSel = new fcSQL_Select($qJoin);
	$qjSel->Fields()->ClearFields();
	$qjSel->Fields()->SetFields(self::FieldArray_ItemStatus_byItem_wBin());
	$qTerms = new fcSQL_Terms(
	  array(
	    self::SQOF_ItemStatus(),
	    )
	  );
	$qry = new fcSQL_Query($qjSel,$qTerms);
	return $qry;
    }
    
    // -- STATIC -- //
    // ++ RECORDS ++ //
    
    public function ItemStatusRecords_wBin($idItem) {
	$sqo = self::SQO_forItemStatus_wBin();
	$sqo->Terms()->Filters()->AddCond('ID_Item='.$idItem);
	$sql = $sqo->Render();
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    public function ItemStatusRecords_active() {
	$sql = self::SQL_forItemStatus().' HAVING QtyForSale > 0';
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ARRAYS ++ //
    
    // -- ARRAYS -- //

}

class vcrStockLineInfo extends vcrStockLine {

    // ++ FIELD VALUES ++ //

    public function Qty() {
	return $this->Value('Qty');
    }
    public function BinID() {
	return $this->Value('ID_Bin');
    }
    protected function IsForShip() {
	return $this->Value('isForShip');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    public function QtyForShip() {
	return $this->IsForShip()?$this->Qty():0;
    }

    // -- FIELD CALCULATIONS -- //

}