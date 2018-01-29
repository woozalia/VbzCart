<?php
/*
  PURPOSE: business logic for managing Warehouses
  HISTORY:
    2016-01-08 started
    2017-03-27 now descending from vcBasic* classes instead of vcAdmin*
*/

class vctlWarehouses extends vcBasicTable {

    // CEMENT
    protected function SingularName() {
	return 'vcrlWarehouse';
    }
    // CEMENT
    protected function TableName() {
	return 'stk_whse';
    }

}

class vcrlWarehouse extends vcBasicRecordset {

    // PUBLIC so Restocks can display it
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
}