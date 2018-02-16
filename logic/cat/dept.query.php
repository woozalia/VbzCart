<?php
/*
  HISTORY:
    2018-02-09 Split off vctCatDepartments_queryable from title.info.php
      Not sure this is actually needed.
    2018-02-14 At this point, we at least need it for access to SQO_Source() (in ftQueryableTable).
*/
class vctCatDepartments_queryable extends vctDepts {
    use ftQueryableTable;
    use vtTableAccess_Supplier;

    // ++ CLASSES ++ //
    
    protected function SuppliersClass() {
	return 'vctCatSuppliers_queryable';
    }
    
    // -- CLASSES -- //
    // ++ SQO PIECES ++ //

    public function SourceSuppliers() {
	return $this->SupplierTable()->SQO_Source('s');
	//return new fcSQL_TableSource($this->SupplierTable()->Name(),'s');
    }

    // -- SQO PIECES -- //
}
