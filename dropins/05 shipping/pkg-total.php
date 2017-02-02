<?php
/*
  PURPOSE: class for summing package totals
    so we don't end up duplicating the same logic in multiple places
  HISTORY:
    2014-05-14
*/

class clsPackageTotal {
    private $dlrSale,$dlrPerItem,$dlrPerPkg;

    public function __construct() {
	$this->dlrSale = 0;
	$this->dlrPerItem = 0;
	$this->dlrPerPkg = 0;
    }
    public function Add($qty,$dlrSale,$dlrPerItem,$dlrPerPkg) {
	$this->dlrSale += $dlrSale;
	$this->dlrPerItem += $dlrPerItem * $qty;
	if ($dlrPerPkg > $this->dlrPerPkg) {
	    $this->dlrPerPkg = $dlrPerPkg;
	}
    }
    public function SaleAmt() {
	return $this->dlrSale;
    }
    public function PerItemAmt() {
	return $this->dlrPerItem;
    }
    public function PerPkgAmt() {
	return $this->dlrPerPkg;
    }
    public function FinalTotal() {
	return $this->SaleAmt() + $this->PerItemAmt() + $this->PerPkgAmt();
    }
}