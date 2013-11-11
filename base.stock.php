<?php
/*
  FILE: base.stock.php -- VbzCart stock classes (no UI)
  HISTORY:
    2012-05-12 split off from base.cat.php
*/

class clsStkItems extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('stk_items');
	  $this->KeyName('ID');
    }
    /*-----
      RETURNS: Recordset containing list of stock for the given item (qtys, bin, bin name, place, place name, notes)
    */
    public function List_forItem($iItemID) {
	$sql = 'SELECT '
	  .'ID, QtyForSale, QtyForShip, QtyExisting, ID_Bin, ID_Place, BinCode, WhName, Notes '
	  .'FROM qryStk_lines_remaining WHERE (ID_Item='.$iItemID.');';
	$objStock = $this->objDB->DataSet($sql,$this->ClassSng());
	$objStock->Table = $this;
	return $objStock;
    }
    /*----
      RETURNS: number of items in stock
    */
    public function Count_inStock() {
	$sql = 'SELECT SUM(QtyForSale) AS Qty FROM qryStkItms_for_sale';
	$rc = $this->Engine()->Make('clsRecs_generic');
	$rc->Query($sql);
	$rc->NextRow();
	return $rc->Value('Qty');
    }
/* (2010-06-15) This doesn't seem to be used anywhere, and possibly does not work.
    public function QtyInStock_forItem($iItemID) {
	$sql = 'SELECT SUM(s.Qty) AS Qty FROM stk_items AS s LEFT JOIN stk_bins AS sb ON s.ID_Bin=sb.ID WHERE (s.ID_Item='.$iItemID.') AND (s.WhenRemoved IS NULL) AND (sb.WhenVoided IS NULL) AND (sb.isForSale) GROUP BY s.ID_Item';

	$objStock = new clsDataItem($this);
	$objStock->Query($sql);
	if ($objStock->NextRow()) {
	    assert('is_resource($objStock->Res)');
	    if ($objStock->RowCount()) {
		assert('$objStock->RowCount() == 1');
		return $objStock->Qty;
	    }
	} else {
	    return NULL;
	}
    }
*/
}
