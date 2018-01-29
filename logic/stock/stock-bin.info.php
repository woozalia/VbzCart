<?php
/*
  PURPOSE: Stock Bin query classes
  HISTORY:
    2017-03-23 split off from stock.info.php
*/
class vcqtStockBinsInfo extends fcTable_wSource_wRecords {

    // ++ SETUP ++ //

    // CEMENT
    public function GetKeyName() {
	return 'ID_Bin';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcqrStockBinInfo';
    }

    // -- SETUP -- //
    // ++ SQL ++ //

    /* // 2017-09-05 I don't think anything uses this since I fixed SQL_forBins_wStatus()
    static public function SQL_forBinStatus($sqlFilt=NULL,$sqlSort=NULL) {

	$sqlFiltClause = is_null($sqlFilt)?'':(' WHERE '.$sqlFilt);
	$sqlSortClause = is_null($sqlSort)?'':(' ORDER BY '.$sqlSort);
    
	$sqlLines = vcqtStockLinesInfo::SQL_forLineStatus();
	$sql = <<<__END__
SELECT
  ID_Bin,
  SUM(sl.QtyForSale) AS QtyForSale,
  SUM(sl.QtyForShip) AS QtyForShip,
  SUM(sl.QtyExisting) AS QtyExisting
FROM ($sqlLines) AS sl
$sqlFiltClause
GROUP BY ID_Bin
$sqlSortClause
__END__;
	return $sql;
    } */
    /*----
      PURPOSE: Bin records with stock line sum
      HISTORY:
	2017-03-23 created as part of replacing stored queries
	2017-09-05 Purpose said: <paste> joins with Bins to pull up Bin record fields
	  This is rather inefficient, since Bins were accessed during the inner query... fix later.</paste>
	  This has now been fixed -- but list of fields may be subject to further revision, because not sure
	    which Bin fields are expected and I only indluded ID and Code.
    */
    static public function SQL_forBins_wStatus($sqlFilt=NULL,$sqlSort=NULL) {
	$sqlFiltClause = is_null($sqlFilt)?'':(' WHERE '.$sqlFilt);
	$sqlSortClause = is_null($sqlSort)?'':(' ORDER BY '.$sqlSort);
	$sql = <<<__END__
SELECT 
    sb.*,
    sbi.QtyTotal
FROM
    stk_bins AS sb
        LEFT JOIN
    (SELECT 
        ID_Bin, 
        SUM(Qty) AS QtyTotal
    FROM
        stk_lines
    GROUP BY ID_Bin) AS sbi ON sbi.ID_Bin = sb.ID
$sqlFiltClause
$sqlSortClause
__END__;
    /*
	$sqlInfo = self::SQL_forBinStatus($sqlFilt,$sqlSort);
	$sql = <<<__END__
SELECT
  sb.*, sbi.*
FROM ($sqlInfo) AS sbi LEFT JOIN stk_bins AS sb ON sbi.ID_Bin=sb.ID
__END__; */
	return $sql;
    }
    
    // -- SQL -- //
    // ++ RECORDS ++ //

    // USED BY: Admin listings of Bins
    public function SelectStatusRecords($sqlFilt=NULL,$sqlSort=NULL) {
	$sql = self::SQL_forBins_wStatus($sqlFilt,$sqlSort);
	$rs = $this->FetchRecords($sql);
	return $rs;
    }

    // -- RECORDS -- //

}
class vcqrStockBinInfo extends vcrStockBin {
}