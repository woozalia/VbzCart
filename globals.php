<?php
/*
  PURPOSE: adds globals specific to VbzCart
  HISTORY:
    2017-03-14 started
    2017-05-12 shopping nav icons
  NOTE: see ferreteria/globals.php MakeWebPath_forAppPath() for a discussion of beginning/ending slashes.
*/
define('KSF_CART_ITEM_ARRAY_NAME','item');
define('KSF_CART_ITEM_PFX',KSF_CART_ITEM_ARRAY_NAME.'[');
define('KSF_CART_ITEM_SFX',']');

// All of these can be overridden by the site config globals.
abstract class vcGlobals extends fcGlobals {

    // non-paths

    protected function GetItemControlNamePrefix() {
	return 'item';
    }
    public function MakeItemControlName($sItemTag) {
	return $this->GetItemControlNamePrefix()."[$sItemTag]";
    }
    // 2017-05-14 There's probably a more consistent way of doing this, but for now...
    public function GetCartItemsInput() {
	return $_POST[$this->GetItemControlNamePrefix()];
    }
    public function GetButtonName_AddToCart() {
	return 'btnAddItems';
    }
    public function FoundInputButton_AddToCart() {
	return fcHTTP::Request()->GetBool($this->GetButtonName_AddToCart());
    }
    
    // web paths

    /*----
      NOTE: Static files don't *have* to be all under one folder, but
	it seems like a good starting assumption.
	Can be overridden by app globals class.
    */
    protected function GetWebPath_forStaticFiles() {
	return $this->GetWebPath_forAppBase().'static/';
    }
      protected function GetWebPath_forIcons() {
	  return $this->GetWebPath_forStaticFiles().'img/icons/';
      }
      public function GetWebPath_forStyleSheets() {
	  return $this->GetWebPath_forStaticFiles().'css/';
      }
      protected function GetWebPath_JavaScript() {
	  return $this->GetWebPath_forStaticFiles().'js/';
      }
	public function GetWebPath_DTree() {
	    return $this->GetWebPath_JavaScript().'dtree/';
	}
    public function GetWebPath_forCatalogPages() {
	return $this->GetWebPath_forAppBase().'cat/';
    }
    public function GetWebPath_forStockPages() {
	return $this->GetWebPath_forAppBase().'stock/';
    }
    public function GetWebPath_forTopicPages() {
	return $this->GetWebPath_forAppBase().'topic/';
    }
    public function GetWebPath_forSearchPages() {
	return $this->GetWebPath_forAppBase().'search/';
    }
    public function GetWebPath_forCartPage() {
	return $this->GetWebPath_forAppBase().'cart/';
    }
    public function GetWebPath_forCheckoutPage() {
	$uriPath = $this->GetWebPath_forAppBase().'checkout/';
	if ($_SERVER['HTTPS'] === 'on') {
	    return $uriPath;
	} else {
	    // 2018-02-25 This has not been tested.
	    return KURL_STORE_SECURE.$uriPath;
	}
    }
    protected function GetWebPath_forPublicWikiPages() {
	return $this->GetWebPath_forAppBase().'wiki/';
    }
      public function GetWebPath_forPublicWikiPage($sTitle) {
	  return $this->GetWebPath_forPublicWikiPages().$sTitle;
      }
      public function GetWebPath_forHelpPage() {
	  return $this->GetWebPath_forPublicWikiPage('help');
      }
      public function GetWebPath_forContactPage() {
	  return $this->GetWebPath_forPublicWikiPage('contact');
      }
    protected function GetWebPath_forPrivateWikiPages() {
	return $this->GetWebPath_forAppBase().'corp/wiki/';
    }
      public function GetWebPath_forPrivateWikiPage($sTitle) {
	  return $this->GetWebPath_forPrivateWikiPages().$sTitle;
      }
      protected function GetWebPath_forAdminDocs($sTopic) {
	  return $this->GetWebPath_forPrivateWikiPage('VbzCart/'.$sTopic);
      }
      public function GetWebPath_forAdminDocs_TransactionType($sType) {
	  return $this->GetWebPath_forAdminDocs('transaction/types/'.$sType);
      }
    public function GetWebPath_forAdminPages() {
	return $this->GetWebPath_forAppBase().'admin/';
    }
    // These could be anywhere -- ideally the site would define them...
    // ...but I'm planning to obsolete these soon, so hard-wiring for now.
    /* 2017-05-13 If these are being used, they shouldn't be
    public function GetWebPath_forPrivateWiki() {
	return '/corp/wiki/';
    }
    public function GetWebPath_forPublicWiki() {
	return '/wiki/';
    }
    */
    
    // individual files

    public function GetWebPath_forSiteLogo() {
	return $this->GetWebPath_forStaticFiles().'logos/v/';
    }
    // status icons
    public function GetWebSpec_forSuccessIcon() {
	return $this->GetWebPath_forIcons().'button-green-check-20px.png';
    }
    public function GetWebSpec_forWarningIcon() {
	return $this->GetWebPath_forIcons().'button-yellowish-i-20px.png';
    }
    public function GetWebSpec_forErrorIcon() {
	return $this->GetWebPath_forIcons().'button-red-X.20px.png';
    }
    // widget icons
    public function GetWebSpec_RightPointer() {
    	return $this->GetWebPath_forIcons().'arr1-r.gif';
    }
    public function GetWebSpec_DownPointer() {
	return $this->GetWebPath_forIcons().'arr1-d.gif';
    }
    // site nav icons
    public function GetWebSpec_forHomeIcon() {
    	return $this->GetWebPath_forIcons().'home.050pxh.png';
    }
    public function GetWebSpec_forSearchIcon() {
    	return $this->GetWebPath_forIcons().'search.050pxh.png';
    }
    public function GetWebSpec_forCartIcon() {
    	return $this->GetWebPath_forIcons().'cart.050pxh.png';
    }
    public function GetWebSpec_forHelpIcon() {
    	return $this->GetWebPath_forIcons().'/help.050pxh.png';
    }

    // DISPLAYED MESSAGES
    
    // This replaces KHTML_TITLE_EXISTS_NO_ITEMS
    public function GetMarkup_forNoItems() {
	// This could eventually be an "info" icon
	$wpIcon = $this->GetWebSpec_forWarningIcon();
	return <<<__END__
<div class=warning-message>
  <img src="$wpIcon" alt="alert"> This title exists in our catalog, but no items are currently available for it.
</div>
__END__;
    }
}
