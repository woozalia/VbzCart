<?php
/*
  PURPOSE: as yet not documented
  HISTORY:
    2016-02-05 Discovered that this is still in use by Topic exhibit pages.
      It seems to be some sort of cache for expensive SQL calculations, but
      I'm not sure if it makes sense or not.
    2018-02-07 Moved the stats manager (now fcTreeStatsMgr) to ferreteria:util/tree-stats.php
      Remaining: vctItemsStat, which needs renaming (it's not really a table, and can be used for
	Titles as well as Items.
*/

/*
class vctItemsStat {
    private $qItemsForSale;

    public function __construct() {
	$this->qItemsForSale = NULL;
	throw new exception('2018-02-07 Does anything still call this?');
    }
    protected function SumItem(vcrItem $rc) {
	$this->qItemsForSale += ($rc->IsForSale()?1:0);
    }
    public function SumItems(vcrItem $rs) {
	while ($rs->NextRow()) {
	    $this->SumItem($rs);
	}
    }
    public function ItemsForSale() {
	return $this->qItemsForSale;
    }
}
*/