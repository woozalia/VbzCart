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

    /*----
      RETURNS: SQL to generate a record for each line of stock that still exists (Bin and Bin's Place both active/valid)
	Does not check further up hierarchy for invalid parent-places.
      HISTORY:
	2017-03-23 written to replace qryStk_lines_remaining (which wasn't quite right anyway)
	2017-04-22 changed isActive (should have been isEnabled) to isActivated
    */
    static public function SQL_forLineStatus() {
	return <<<__END__
 SELECT
    st.ID,
    st.ID_Bin,
    st.ID_Item,
    IF(sb.isForSale,st.Qty,0) AS QtyForSale,
    IF(sb.isForShip,st.Qty,0) AS QtyForShip,
    st.Qty AS QtyExisting,
    st.CatNum,
    st.WhenAdded,
    st.WhenChanged,
    st.WhenCounted,
    st.Notes,
    sb.Code AS BinCode,
    sb.ID_Place,
    sp.Name AS WhName
    FROM
      (
        stk_lines AS st
        LEFT JOIN stk_bins AS sb
          ON sb.ID=st.ID_Bin
       )
       LEFT JOIN stk_places AS sp
         ON sb.ID_Place=sp.ID
    WHERE (sb.WhenVoided IS NULL) AND (st.Qty <> 0) AND (sp.isActivated)
__END__;
    }
    
    /*----
      2017-03-23 Reorganizing things so they make sense.
    */
    static public function SQL_forItemStatus($sqlFilt=NULL) {
	throw new exception('2017-03-23 This is deprecated. Call vcqtStockItemsInfo::SQL_forItemStatus() instead.');
	return vcqtStockItemInfo::GenerateSQL($sqlFilt);
	/*
	$sqlClause = is_null($sqlFilt)?'':"AND $sqlFilt";
	return <<<__END__
SELECT 
    si.ID_Item, SUM(si.Qty) AS QtyForSale
FROM
    stk_lines AS sl
        JOIN
    stk_bins AS sb ON sl.ID_Bin = sb.ID
WHERE
    (sl.Qty > 0)
        AND (sb.isForSale)
        AND (sb.isEnabled)
        AND (sb.WhenVoided IS NULL)
        $sqlClause
GROUP BY sl.ID_Item
__END__;*/
    }

    /*----
      RETURNS: Just the filter object for retrieving item stock-status information
    */
    static protected function FieldArray_ItemStatus_byItem() {
	return array(
	    'ID_Item'	=> 'sl.ID_Item',
	    'QtyForSale' => 'SUM(sl.Qty)'
	    );
    }
    static protected function FieldArray_ItemStatus_byItem_wBin() {
	return array(
	    'ID_Item'	=> 'sl.ID_Item',
	    'ID_Bin'	=> 'sl.ID_Bin',
	    'sl.Qty',
	    'sb.isForSale',
	    'sb.isForShip'
	    );
    }
    static protected function SQOF_ItemStatus() {
	return new fcSQLt_Filt('AND',
	      array(
		'sl.Qty>0',
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
	$qtSI = new fcSQL_TableSource('stk_lines','sl');
	$qtSB = new fcSQL_TableSource('stk_bins','sb');
	$arJT = array(
	  new fcSQL_JoinElement($qtSI),
	  new fcSQL_JoinElement($qtSB,'sl.ID_Bin = sb.ID')
	  );
	return new fcSQL_JoinSource($arJT);
    }
    /*----
      RETURNS: SQO to get stock lines with bins
      HISTORY:
	2017-03-16 written
    */
    static protected function SQO_SELECT_forLines_wBins() {
	$qj = self::SQOJ_ItemStatus();
	$qs = new fcSQL_Select($qj);
	return $qs;
    }
    // 2017-03-16
    static public function SQO_SELECT_forLines_wBins_andItems() {
	$qjs = new fcSQL_TableSource('cat_items','ci');
	$qje = new fcSQL_JoinElement($qjs,'ci.ID = sl.ID_Item');
    
	$qs = self::SQO_SELECT_forLines_wBins();
	$qs->Source()->AddElement($qje);
	return $qs;
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
	    new fcSQLt_Group(array('sl.ID_Item'))	// fcSQL_Terms(array(fcSQL_Filt(),*fcSQL_Group()*...
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
