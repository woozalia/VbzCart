<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Shipping Costs
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-ship-cost.php from base.cat.php
*/
class clsShipCosts extends vcShopTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cat_ship_cost';
    }
    protected function SingularName() {
	return 'clsShipCost';
    }

    // -- CEMENTING -- //
    // ==BOILERPLATE - cache

    protected $objCache;
    protected function Cache() {
	throw new exception('2016-11-01 Is anything still calling this?');
	if (!isset($this->objCache)) {
	    $this->objCache = new clsCache_Table($this);
	}
	return $this->objCache;
    }
    public function GetItem_Cached($iID=NULL,$iClass=NULL) {
	return $this->Cache()->GetItem($iID,$iClass);
    }
    
    // ==/BOILERPLATE
}
class clsShipCost extends vcShopRecordset {

    protected function Description() {
	return $this->GetFieldValue('Descr');
    }
    public function PerPkg() {
	return $this->GetFieldValue('PerPkg');
    }
    public function PerUnit() {
	return $this->GetFieldValue('PerItem');
    }
}
