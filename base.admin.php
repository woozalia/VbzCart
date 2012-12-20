<?php
/*====
  HISTORY:
    2011-12-21 split clsVbzAdminData off from SpecialVbzAdmin.php so it could be used from command line
    2012-04-19 restructuring classes -- data classes separate from page classes now
*/

class clsVbzAdminData extends clsVbzData {
  // parental overrides
    protected function CacheMgr_empty() {
	return new clsAdminCacheMgr($this);
    }
    public function Events($iID=NULL) {
	return $this->Make('clsAdminEvents',$iID);
    }
    public function Suppliers($iID=NULL) {
	return $this->Make('VbzAdminSuppliers',$iID);
    }
    public function Depts($iID=NULL) {
	return $this->Make('VbzAdminDepts',$iID);
    }
    public function Titles($iID=NULL) {
	return $this->Make('VbzAdminTitles',$iID);
    }
  // new classes
    public function Titles_Cat() {
	return $this->Make('VbzAdminTitles_info_Cat');
    }
    public function Titles_Item() {
	return $this->Make('VbzAdminTitles_info_Item');
    }
    public function TitleTopics() {
	return $this->Make('clsTitleTopics_base');
    }
    public function TitleTopic_Titles() {
	return $this->Make('clsAdminTitleTopic_Titles');
    }
    public function TitleTopic_Topics() {
	return $this->Make('clsAdminTitleTopic_Topics');
    }
    public function Items($iID=NULL) {
	return $this->Make('VbzAdminItems',$iID);
    }
    public function Items_Cat($iID=NULL) {
	return $this->Make('clsAdminItems_info_Cat',$iID);
    }
    public function Images($iID=NULL) {
	return $this->Make('clsAdminImages',$iID);
    }
/* TO DO: Rename all these as CM*() */
    public function CtgSrcs() {
	return $this->Make('VbzAdminCatalogs');
    }
    public function CtgGrps($iID=NULL) {
	return $this->Make('clsCtgGroups',$iID);
    }
    public function CtgTitles() {
	return $this->Make('clsCtgTitles');
    }
    public function CMItems() {
	return $this->Make('clsCMITems');
    }
/***/
    public function Topics($iID=NULL) {
	return $this->Make('clsAdminTopics',$iID);
    }
    public function Places($iID=NULL) {
	return $this->Make('VbzStockPlaces',$iID);
    }
    public function Bins($iID=NULL) {
	return $this->Make('VbzStockBins',$iID);
    }
    public function BinLog() {
	return $this->Make('VbzStockBinLog');
    }
    public function StkItems($iID=NULL) {
	return $this->Make('VbzAdminStkItems',$iID);
    }
    public function StkLog() {
	return $this->Make('clsStkLog');
    }
    public function Sessions() {
	return $this->Make('VbzAdminSessions');
    }
    public function Carts() {
	return $this->Make('VbzAdminCarts');
    }
    public function CartLines() {
	return $this->Make('VbzAdminCartLines');
    }
    public function CartLog() {
	return $this->Make('VbzAdminCartLog');
    }
    public function Orders($iID=NULL) {
	return $this->Make('VbzAdminOrders',$iID);
    }
    public function OrdItems() {
	return $this->Make('VbzAdminOrderItems');
    }
    public function OrdPulls() {
	return $this->Make('VbzAdminOrderPulls');
    }
    public function OrdPullTypes() {
	return $this->Make('clsOrderPullTypes');
    }
    public function OrdTrxacts($iID=NULL) {
	return $this->Make('VbzAdminOrderTrxacts',$iID);
    }
    public function OrdTrxTypes($iID=NULL) {
	return $this->Make('VbzAdminOrderTrxTypes',$iID);
    }
    public function Pkgs() {
	return $this->Make('clsPackages');
    }
    public function PkgLines() {
	return $this->Make('clsPkgLines');
    }
    public function Shipmts() {
	return $this->Make('clsShipments');
    }
    public function RstkReqs() {
	return $this->Make('clsAdminRstkReqs');
    }
    public function RstkReqItems() {
	return $this->Make('clsAdminRstkReqItems');
    }
    public function RstkRcds($iID=NULL) {
	return $this->Make('clsRstkRcds',$iID);
    }
    public function RstkRcdLines() {
	return $this->Make('clsRstkRcdLines');
    }
    public function Custs($iID=NULL) {	// override
	return $this->Make('VbzAdminCusts',$iID);
    }
    public function CustAddrs() {
	return $this->Make('clsAdminCustAddrs');
    }
    public function CustNames() {
	return $this->Make('clsAdminCustNames');
    }
    public function CustCards() {
	return $this->Make('VbzAdminCustCards');
    }
    public function CustEmails() {
	return $this->Make('clsAdminCustEmails');
    }
    public function CustPhones() {
	return $this->Make('clsAdminCustPhones');
    }
    public function CustCharges() {
	return $this->Make('VbzAdminOrderChgs');
    }
    public function Catalogs() {
	return $this->Make('VbzAdminCatalogs');
    }
}
