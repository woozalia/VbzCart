<?php
/*
  PURPOSE: shopping-related trait used by admin class
    The admin class descends from the logic class, avoiding most of the shop class's baggage;
    putting this trait in a separate file means we don't have to load supp.shop.php unless
    we're actually using it.
  HISTORY:
    2017-01-17 created
*/
/*::::
  PURPOSE: Shop-related methods also needed by admin class
*/
trait vtrSupplierShop {
    /*----
      RETURNS: relative URL for this Supplier's catalog page
    */
    public function ShopURL() {
	return vcGlobals::Me()->GetWebPath_forCatalogPages()
	  .strtolower($this->CatKey()).'/'
	  ;
    }

    public function ShopLink($iText=NULL) {
	if (is_null($iText)) {
	    $strText = $this->NameString();
	} else {
	    $strText = $iText;
	}
	$out = '<a href="'.$this->ShopURL().'">'.$strText.'</a>';
	return $out;
    }
    public function ShopLink_name() {
	return $this->ShopLink($this->NameString());
    }
}
