<?
/*
  HISTORY:
    2018-02-09 extracted vctCatSuppliers_queryable from title.info.php
      Not sure this is actually needed.
    2018-02-14 Yes, we need it for access to SQO_Source() (in ftQueryableTable).
*/
class vctCatSuppliers_queryable extends vctSuppliers {
    use ftQueryableTable;
}
