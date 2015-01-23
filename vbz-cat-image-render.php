<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for rendering images
  HISTORY:
    2014-08-18 started -- need to be able to store images and their metadata in a nice package so they can
      be rendered nicely after being split into active and inactive arrays.
*/

class clsVC_Image_data {
    private $isActive;
    private $arImgRow;
    private $db;

    public function __construct(clsImage $rcImage) {
	$this->db = $rcImage->Engine();
	$this->arImgRow = $rcImage->Values();
    }
}