<?php
/*
  PURPOSE: skins for VbzCart browsing pages
  HISTORY:
    2013-11-15 split off from vbz-skin.php (formerly skins.php)
*/
class clsVbzSkin_browse extends clsVbzSkin {
    private $sCtxt;
//    private $sName;

    // ++ ACTION ++ //

    /*----
      ACTION: initialize variables
    */
    protected function Init() {
	parent::Init();
	$this->oNavBar = new clsNavbar_flat();
    }
    /*----
      ACTION: Fill in the pieces.
      CALLED BY: Page object
    */
    public function Build() {
	parent::Build();
	$this->arPieces['cont.ftr'] = $this->ContentFooter();
    }

    // -- ACTION -- //
    // ++ ACCESS METHODS ++ //

    public function TitleContext($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->sCtxt = $iVal;
	}
	return $this->sCtxt;
    }

    // -- ACCESS METHODS -- //
    // ++ OUTPUT PIECES ++ //

    public function SectionHdr($iText,$iType=2) {
	if (is_numeric($iType)) {
	    $intLevel = (int)$iType;
	    $txt = "<h$intLevel class='section-header'>$iText</h$intLevel>";
	} else {
	    $txt = '<p class="'.$iType.'">'.$iText.'</p>';
	}
	return $txt;
    }
    protected function Toolbar() {
	global $fpPages;

	$out = NULL;
	$out .= $this->ToolbarItem($fpPages.'/','home',KS_SITE_NAME.' home page','home page');
	$out .= $this->ToolbarItem($fpPages.'/search/','search','search page','search page');
	$out .= $this->ToolbarItem(KWP_CART_REL,'cart','shopping cart','shopping cart');
	$out .= $this->ToolbarItem(KWP_HELP_HOME,'help','help!','help');
	return $out;
    }
    private function ToolbarItem($iURL,$iIcon,$iTitle,$iAlt) {
	return '<a href="'.$iURL.'"><img border=0 src="'.$this->ImageSpec('/icons/'.$iIcon.'.050pxh.png').'" title="'.$iTitle.'" alt="'.$iAlt.'"></a>';
    }

    // -- OUTPUT PIECES -- //
    // ++ SIDEBAR ++ //

    protected function NavBarObj() {
	return $this->oNavBar;
    }
    /*----
      USAGE: called both internally and by Page object
    */
    public function AddNavItem($sLabel,$sValue,$sURL=NULL,$sPopup=NULL) {
	$oi = new clsNav_LabeledLink($this->NavBarObj(),$sLabel,$sValue);
	if (!is_null($sURL)) {
	    $oi->URL($sURL);
	}
	if (!is_null($sPopup)) {
	    $oi->Popup($sPopup);
	}
    }
    protected function SideBar() {
	$oNav = $this->NavBarObj();
	$oNav->Decorate("\n<br> ...",'','');
	$htBar = $oNav->Render();

	if (is_null($htBar)) {
	    $htThisPage = NULL;
	} else {
	    $htThisPage = '<b>This Page</b>'.$htBar.'<br>';
	}

	$kwpShopSupp = KWP_SHOP_SUPP;
	$kwpShopStock = KWP_SHOP_STOCK;
	$kwpShopTpix = KWP_SHOP_TOPICS;
	$kwpWiki = KWP_WIKI_PUBLIC;
	$kwpHelpHome = KWP_HELP_HOME;
	$kwpHelpAbout = KWP_HELP_ABOUT;
	$kwpHelpCont = KWP_HELP_CONTACT;
	$kwpCommunity = KWP_WIKI_PUBLIC.'VBZwiki_talk:Community_portal';
	// TODO: work out a good way of compiling stats, and get numbers from there
	//$statsQtyTitlesAvail = $this->Data()->StkItems()->Count_inStock();
	$statsQtyTitlesAvail = 'all';	// kluge

	// all of the following should eventually be part of the navbar, except maybe the search form

	$urlEmail = KWP_EMAIL;
	$urlCart = KWP_CART;

	$ht = <<<__END__
<!-- BEGIN NAVBAR -->
<table class=border align=left cellpadding=3 bgcolor="#000000">
  <tr><td>
    <table class=sidebar bgcolor="#ffffff" cellpadding=5>
      <tr><td>
	<table border=0 class=menu-title width="100%">
	  <tr><td class=menu-title><a href="/">Home</a></td></tr>
	</table>
	<span class=menu-text>
	  <form action="/search/">
	    Search $statsQtyTitlesAvail items:<br>
	    <input size=10 name=search><input type=submit value="Go"><br>
	    <small><a href="/search/">advanced</a></small>
	  </form>
	  $htThisPage
	  <b>Indexes</b>
	  <br> ...<a href="$kwpShopSupp" title="list of suppliers and what we carry from each one">Suppliers</a>
	  <br> ...<a href="$kwpShopStock" title="what\'s currently in stock">Stock</a>
	  <br> ...<a href="$kwpShopTpix" title="topic master index (topics are like category tags)">Topics</a>
	  <p></p>
	  [[ <a href="$kwpWiki" title="vbz wiki homepage"><b>wiki</b></a> ]]<br>
	  -- [[ <a href="$kwpHelpHome" title="help main index"><b>Help</b></a> ]]<br>
	  -- [[ <a href="$kwpHelpAbout" title="about vbz.net (probably more than you want to know)"><b>About</b></a> ]]<br>
	  -- [[ <a href="$kwpHelpCont" title="contact vbz.net (several different methods)"><b>Contact</b></a> ]]<br>
	  -- [[ <a href="$kwpCommunity" title="leave your comments and suggestions"><b>Comments</b></a> ]]<br>
	  <p></p>
	  <a href="$urlEmail" title="web form for sending us email">email form</a><br>
	  <a href="$urlCart" title="your shopping cart">shopping cart</a>
	</span>
      </td></tr>
    </table>
  </td></tr>
</table>
<!-- END NAVBAR -->
__END__;

	return $ht;
    }

    // -- SIDEBAR -- //
    // ++ SUBSTANCE ++ //

    public function ContentHeader() {
	$out = parent::ContentHeader();
      // begin content table
	$urlHome = KWP_HOME_ABS;
	$urlLogo = KWP_LOGO_HEADER;
	$sStore = KS_SITE_NAME;
	$sLogoAlt = KS_SMALL_LOGO_ALT;
	$sTitle = $this->PageTitle();
	$sTCtxt = $this->TitleContext();
	if (!is_null($sTCtxt)) {
	    $htCtxt = '<span class=pretitle><b><a href="/">'.KS_SITE_NAME.'</a></b>: '.$sTCtxt.'</span><br>';
	} else {
	    $htCtxt = NULL;
	}
	$htToolBar = $this->Toolbar();
	$htSideBar = $this->SideBar();
	$sClass = __CLASS__;

	$out .= <<<__END__
<!-- BEGIN ContentHeader in $sClass -->
<table width="100%" id="cont-header-outer" class=border cellpadding=5><tr><td>
<table width="100%" id="cont-header-inner" class=hdr cellpadding=2><tr>
<!-- LEFT OF HEADER (Title etc.) -->
<td>
<a href="$urlHome"><img align=left border=0 src="$urlLogo" title="$sStore home" alt="$sLogoAlt"></a>
$htCtxt
<span class=page-title>$sTitle</span></td>
<!-- END LEFT OF HEADER -->

<!-- RIGHT HEADER: nav icons -->
<td align=right>
$htToolBar
</td>
<!-- END RIGHT HEADER -->
</tr></table>
</td></tr></table>
$htSideBar
<!-- END ContentHeader in $sClass -->
__END__;

	return $out;
    }
    public function ContentFooter() {
	return "\n<!-- ContentFooter in ".__CLASS__." -->";
    }
    protected function PageFooter() {
	$sServer = $_SERVER['SERVER_SOFTWARE'];
	$dat = getrusage();
	$fltUserTime = $dat['ru_utime.tv_usec']/1000000;
	$fltSysTime = $dat['ru_stime.tv_usec']/1000000;
	$fltExecTime = $this->ExecTime();
	$htHLine = $this->HLine();
	$sVersion = phpversion();
	$sDate = date('Y-m-d H:i:s');

	$out = "\n<!-- BEGIN PageFooter in ".__CLASS__." -->" . <<<__END__
$htHLine
<div class="footer-stats">
$sServer .. PHP $sVersion .. execution time in seconds:
<b>$fltUserTime</b> (user) /
<b>$fltSysTime</b> (system) /
<b>$fltExecTime</b> (calcuated) ..
$sDate
</div>
__END__;
	$out .= "\n<!-- END PageFooter in ".__CLASS__." -->"
	  .parent::PageFooter();
	return $out;
    }

    // -- SUBSTANCE -- //
/*
    protected function RenderLinkList() {
	$out = NULL;
	$arLinks = $this->Page()->NavArray();
	if (!is_null($arLinks)) {
	    $iPfx = '<dt><b>';
	    $iSep = '</b>: ';
	    $iSfx = '';
	    foreach ($arLinks as $key => $val) {
		if (is_null($val)) {
		    $out .= $iPfx.$iSep.$key.$iSfx;
		} else {
		    $out .= $iPfx.$key.$iSep.$val.$iSfx;
		}
	    }
	}
	return $out;
    }
*/
}
