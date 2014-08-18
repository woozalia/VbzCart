<?php
/*
  PURPOSE: page classes for administration stuff
    i.e. pages that should look sensible and safe
    This will probably just be one abstract base class for now.
  HISTORY:
    2013-09-16 created
    2013-12-01 the inheritance structure here probably needs to be reorganized
      Should probably be renamed something like "clsVbzPage_sober"
*/

abstract class clsVbzPage_Admin extends clsVbzPage {
    protected $objSess;
    protected $objCart;

    public function __construct() {
	parent::__construct();
	$this->Skin()->Sheet('ckout');	// for now
    }

    protected function NewSkin() {
	return new clsVbzSkin_admin($this);
    }
}