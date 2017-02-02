<?php
/*
  PURPOSE: business logic for managing Warehouses
  HISTORY:
    2016-01-08 started
*/

class vctlWarehouses extends vcAdminTable {
    // CEMENT
    protected function TableName() {
	return 'stk_whse';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrlWarehouse';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_STOCK_WAREHOUSE;
    }
}

class vcrlWarehouse extends vcAdminRecordset {

    // PUBLIC so Restocks can display it
    public function NameString() {
	return $this->GetFieldValue('Name');
    }
}