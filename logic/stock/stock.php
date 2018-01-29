<?php
/*
  HISTORY:
    2016-03-03 created so stock.info classes could have a base class
      ...but actually, this may not be needed.
*/
class vctStockLines extends vcBasicTable {

    // ++ SETUP ++ //

    // CEMENT
    protected function TableName() {
	return 'stk_lines';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrStockLine';
    }

    // -- SETUP -- //
}
class vcrStockLine extends vcBasicRecordset {
}
