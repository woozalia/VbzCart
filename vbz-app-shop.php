<?php
/*
  PURPOSE: App-classes (and helper classes) for the shopping UI
  HISTORY:
    2016-12-30 created
      Reworking Ferreteria's App class a bit means it makes more sense to have
      a descendant type for shopping as well as the admin one that already existed.
    2017-04-17 Removing vtLoggableShopObject because the only content was SystemEventsClass() and I'm also removing that.
      We're going to go to the App object to retrieve the event log now.
*/
class vcMenuKiosk_catalog extends fcMenuKiosk {
    public function GetBasePath() {
	return vcGlobals::Me()->GetWebPath_forCatalogPages();
    }
}
class vcMenuKiosk_topic extends fcMenuKiosk {
    public function GetBasePath() {
	return vcGlobals::Me()->GetWebPath_forTopicPages();
    }
}
/*::::
  ABSTRACT: n/i - GetPageClass()
*/
abstract class vcAppShop extends vcApp {
    protected function GetSessionsClass() {
	return 'vcUserSessions';
    }
    protected function GetCartsClass() {
	return 'vctShopCarts';
    }
}
class vcAppShop_cart extends vcAppShop {
    protected function GetPageClass() {
	return 'vcPageBrowse_Cart';
    }
    // TODO: not sure a Kiosk is needed for this class
    protected function GetKioskClass() {
	return 'vcMenuKiosk_cart';
    }
}
class vcAppShop_catalog extends vcAppShop {
    protected function GetPageClass() {
	return 'vcCatalogPage';
    }
    protected function GetKioskClass() {
	return 'vcMenuKiosk_catalog';
    }
}
class vcAppShop_search extends vcAppShop {
    protected function GetPageClass() {
	return 'vcPageSearch';
    }
    // TODO: not sure a Kiosk is needed for this class
    protected function GetKioskClass() {
	return 'vcMenuKiosk_search';
    }
}
class vcAppShop_topic extends vcAppShop {
    protected function GetPageClass() {
	return 'vcPageTopic';
    }
    protected function GetKioskClass() {
	return 'vcMenuKiosk_topic';
    }
}