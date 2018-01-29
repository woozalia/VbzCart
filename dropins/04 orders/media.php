<?php
/*
  PURPOSE: admin class for handling message media types
    Making these web-editable is a really low priority; I just needed a recordset type
      to automate the drop-down, for now.
    2017-01-06 It would probably make more sense to store this in a flat file. We could
      have a DataSource type for that (it could either be tabbed columns or a serialized array).
  HISTORY:
    2016-08-07 Created.
    2017-01-06 updated slightly
*/

class vctaOrderMessageMedia extends vcAdminTable {
    use ftExecutableTwig;

    // ++ SETUP ++ //

    protected function TableName() {
	return 'ord_msg_media';
    }
    protected function SingularName() {
	return 'vcraOrderMessageMedium';
    }
    public function GetActionKey() {
	return KS_PAGE_KEY_ORDER_MSG_MEDIUM;
    }

    // -- SETUP -- //
    // ++ EVENTS ++ //
 
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Message Media');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();	// TODO: to be written
    }

    // -- EVENTS -- //

}
class vcraOrderMessageMedium extends vcAdminRecordset {

    // ++ CALLBACKS ++ //
    
    public function ListItem_Text() {
	return $this->DescriptionText();
    }
    public function ListItem_Link() {	// for now, no admin interface
	return $this->DescriptionText();
    }
    
    // -- CALLBACKS -- //
    // ++ FIELD VALUES ++ //
    
    protected function DescriptionText() {
	return $this->GetFieldValue('Descr');
    }
    
    // -- FIELD VALUES -- //

}