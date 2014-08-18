<?php
/*
  FILE: vbz-page.php
  PURPOSE: VbzCart page-rendering classes for catalog browsing
  HISTORY:
    2013-09-17 clsVbzPage_Standard renamed clsVbzPage_Browse
    2013-11-22 clsVbzPage_Browse extracted from vbz-page.php into vbz-page-browse.php
*/
/* %%%%
  CLASS: clsVbzPage_Browse
  PURPOSE: Standard browsing page class
*/
abstract class clsVbzPage_Browse extends clsVbzPage {
    private $sName;	// short title: {item name} (goes into html title, prefixed with store name)
    private $sTitle;	// longer, descriptive title: {"item name" by Supplier} (goes at top of page)
    private $sCtxt;	// context of title, if any (typically displayed above it)

    public function __construct() {
	parent::__construct();
	//$this->strSideXtra = NULL;
	$this->Skin()->Sheet('browse');
    }

    // OPTIONS (more or less)
    protected function NewSkin() {
	return new clsVbzSkin_browse($this);	// this will later be a user option
    }

    // SHORTCUTS

/*
    public function NewSection($iTitle) {
	$obj = $this->Doc()->NewSection($iTitle,'hdr-sub');
    }
*/
    public function NewTable($iClass='content') {
	$objDoc = $this->Doc();
	$obj = $objDoc->NewTable();
	$obj->ClassName($iClass);
	return $obj;
    }
    protected function DoSepBar() {
	echo $this->Skin()->HLine();
    }
}

