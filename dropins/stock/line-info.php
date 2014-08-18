<?php
/*
PURPOSE: Class for handling specialized stock item query
HISTORY:
  2014-05-12 Created
*/

class VCR_StkLine_info extends clsVbzRecs {

    // ++ DATA FIELD ACCESS ++ //

    public function QtyForSale() {
	return $this->Value('QtyForSale');
    }
    public function QtyForShip() {
	return $this->Value('QtyForShip');
    }
    public function QtyExisting() {
	return $this->Value('QtyExisting');
    }
    public function BinID() {
	return $this->Value('ID_Bin');
    }
    public function PlaceID() {
	return $this->Value('ID_Place');
    }
    public function BinCode() {
	return $this->Value('BinCode');
    }
    public function WhereString() {
	return $this->Value('WhName');
    }
    public function Notes() {
	return $this->Value('Notes');
    }
}
