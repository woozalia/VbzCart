<?php
/*
  PURPOSE: queries that return useful information about stock by catalog item
  HISTORY:
    2017-05-07 splitting stock.info.php into stock-item.info.php and stock-line.info.php
*/
class vcqtStockItemsInfo extends fcTable_wSource_wRecords {

    // ++ SETUP ++ //

    // CEMENT
    public function GetKeyName() {
	return 'ID_Item';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcqrStockItemInfo';
    }

    // -- SETUP -- //
    // ++ SQL ++ //

    /*----
      HISTORY:
	2017-03-23 (re)written; will probably need more output fields. Not tested.
	2017-04-10 Testing now. Needed a filter argument; for now, I'm making this required --
	  but if there's good reason, it could be optional.
    */
    static public function SQL_forItemStatus($sqlFilt) {
	$sqlLines = vcqtStockLinesInfo::SQL_forLineStatus();
	$sql = <<<__END__
SELECT
  ID_Item,
  SUM(sl.QtyForSale) AS QtyForSale,
  SUM(sl.QtyForShip) AS QtyForShip,
  SUM(sl.QtyExisting) AS QtyExisting
  
FROM (
$sqlLines
) AS sl
WHERE $sqlFilt
GROUP BY sl.ID_Item
__END__;
	return $sql;
    }
    // DEPRECATED; NEW
    static public function GenerateSQL($sqlFilt=NULL) {
	throw new exception('2017-03-23 Call vcqtStockItemsInfo::SQL_forItemStatus() instead.');
	$sqlClause = is_null($sqlFilt)?'':"AND $sqlFilt";
	return <<<__END__
SELECT 
    sl.ID_Item, SUM(sl.Qty) AS QtyForSale
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
__END__;
    }
    
    // -- SQL -- //
    // ++ RECORDS ++ //
    
    // NEW
    public function GetRecords($sqlFilt=NULL) {
	//$sql = self::GenerateSQL($sqlFilt);
	$sql = self::SQL_forItemStatus($sqlFilt);
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    // NEW
    public function GetItemRecord($idItem) {
	$sqlFilt = 'ID_Item='.$idItem;
	return $this->GetRecords($sqlFilt);
    }

    // ++ RECORDS ++ //
    
}
/*----
  NOTES:
  * wrapper for output of vcqtStockLinesInfo::SQL_forItemStatus()
*/
class vcqrStockItemInfo extends fcRecord_keyed_single_integer {
    public function QtyForSale() {
	return $this->GetFieldValue('QtyForSale');
    }
}
