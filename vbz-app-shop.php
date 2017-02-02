<?php
/*
  PURPOSE: App-classes (and helper classes) for the shopping UI
  HISTORY:
    2016-12-30 created
      Reworking Ferreteria's App class a bit means it makes more sense to have
      a descendant type for shopping as well as the admin one that already existed.
*/
class vcMenuKiosk_shop extends fcMenuKiosk {
    public function GetBasePath() {
	return KWP_PAGE_BASE;	// index.php for each Page type should define this
    }
}
/*::::
  ABSTRACT: n/i - GetPageClass()
*/
abstract class vcAppShop extends vcApp {
    protected function GetKioskClass() {
	return 'vcMenuKiosk_shop';
    }
    protected function GetSessionsClass() {
	return 'cVbzSessions';
    }
    protected function GetCartsClass() {
	return 'vctCarts_ShopUI';
    }
}
class vcAppShop_catalog extends vcAppShop {
    protected function GetPageClass() {
	return 'vcCatalogPage';
    }
}
class vcAppShop_search extends vcAppShop {
    protected function GetPageClass() {
	return 'clsPageSearch';
    }
}