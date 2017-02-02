<?php
/*
  PURPOSE: as yet not documented
  HISTORY:
    2016-02-05 Discovered that this is still in use by Topic exhibit pages.
      It seems to be some sort of cache for expensive SQL calculations, but
      I'm not sure if it makes sense or not.
*/

class clsStatsMgr {
    private $arStat;
    private $sStatClass;

    public function __construct($sStatClass) {
	$this->arStat = array();
	$this->sStatClass = $sStatClass;
    }
    public function IndexExists($id) {
	return array_key_exists($id,$this->arStat);
    }
    public function StatFor($id) {
	if (!$this->IndexExists($id)) {
	    $obj = new $this->sStatClass;
	    $this->arStat[$id] = $obj;
	}
	return $this->arStat[$id];
    }
}

class clsItemsStat {
    private $qItemsForSale;

    public function __construct() {
	$this->qItemsForSale = NULL;
    }
    protected function SumItem(clsItem $rc) {
	$this->qItemsForSale += ($rc->IsForSale()?1:0);
    }
    public function SumItems(clsItem $rs) {
	while ($rs->NextRow()) {
	    $this->SumItem($rs);
	}
    }
    public function ItemsForSale() {
	return $this->qItemsForSale;
    }
}