<?php
/*
  FILE: admin.cart.php -- shopping cart administration for VbzCart
  HISTORY:
    2010-10-15 Extracted shopping cart classes from SpecialVbzAdmin.php
    2011-12-24 DataScripting brought closer to sanity; mostly working.
*/
/*
clsLibMgr::Add('vbz.cart.lines',	KFP_LIB_VBZ.'/cart-lines.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsShopCartLines', 'vbz.cart.lines');
*/
/* ****
  SECTION: cart preprocessed data
    This is data from the cart table which has been reorganized and packaged into objects.
    These classes add admin functionality to the classes in shop.php by adding new functions
      and substantiating existing functions that are stubbed-off on the customer side.
*/
class clsCartContact_admin_WHO_USES /* extends clsCartContact */ {
// -- SCRIPTING --

    protected function IsNew() {
	return !$this->Person()->HasID();
    }
    protected function ID() {
	return !$this->Person()->ID();
    }
    /*----
      ACTION: Creates/updates the contact record
	At this point, the user has already chosen whether to update an existing record or create a new one.
    */
    protected function SaveThis() {
	$id = $this->Person()->ID();
	$tbl = $this->Engine()->Custs();
	$act = $tbl->Make_Script($id);
	$act->Name('cont.make');
	$this->Person()->actCont = $act;	// make sure the person has the contact-update script
	return $act;
    }
    /*----
      HISTORY:
	2011-12-17 created -- possibly the last piece in the puzzle?
    */
    public function Save() {
	//$acts = $this->ScriptRoot();
	$acts = $this->ScriptStart();
	//$acts = $iScript;

	// set up script to create/update main record
	$actCustPre = $this->SaveThis();
	$acts->Add($actCustPre,'cont.make');
	// set up scripts to create/update detail records (name, email, phone...)
	$actsDet = $this->SaveSubs();

	$doNew = $this->IsNew();

	$rcCust = $actCustPre->GetRecord();
	if ($doNew) {
	    $actsDet->Add(new Script_Status('Adding new customer...'));
	    $actCustPost = new Script_Row_Update(array(),$rcCust);
	    // set up scripts to copy ID from main record to detail records
	    $actDet = $actsDet->Get_byName('email.data',TRUE);
	    $acts->Add(new Script_SQL_Use_ID($actCustPre,$actDet,'ID_Cust'));

	    $actDet = $actsDet->Get_byName('phone.data',TRUE);
	    $acts->Add(new Script_SQL_Use_ID($actCustPre,$actDet,'ID_Cust'));
	} else {
	    $idPerson = $this->Person()->ID();
	    $actsDet->Add(new Script_Status('Using existing customer ID ['.$idPerson.']...'));
	    // get script for updating existing customer record
	    $actCustPost = new Script_Row_Update(array(),$rcCust);
	}

/* 2012-01-02 this is maybe all redundant now?
	//$actDetAddr = $actsDet->Get_byName('cust.addr.do',TRUE);
	$actDetAddr = $this->Person()->Script_forAddress();
	//$actDetName = $actsDet->Get_byName('name.data',TRUE);
	$actDetName = $this->Person()->Script_forName();

	// this is redundant but harmless when done for existing customer record
	if (!is_null($actCustPost)) {
	    $acts->Add(new Script_SQL_Use_ID($actCustPre,$actDetAddr,'ID_Cust'));
	    $acts->Add(new Script_SQL_Use_ID($actCustPre,$actDetName,'ID_Cust'));
	}
*/
	$acts->Add($actsDet,'subs');

/* 2012-01-02 this is maybe all redundant now?
	$acts->Add(new Script_SQL_Use_ID($actDetAddr,$actCustPost,'ID_Addr'));	// set cust rec's ID_Addr field
	$acts->Add(new Script_SQL_Use_ID($actDetName,$actCustPost,'ID_Name'));	// set cust rec's ID_Name field
*/
	//$acts->Add($actCustPost);
/*
Sequence of events in the script:
  1. create (or update) the customer record
  2. if new customer record:
    2a. copy customer ID to detail record scripts (not yet executed)
  3. create detail records (script generated by SaveSubs())
  4. copy IDs for address and name records back to customer record script
  5. update the customer record
*/
	$this->actSave = $acts;
	return $acts;
    }
}
class clsPerson_Admin_WHO_USES /* extends clsPerson */ {
    protected $ID;

    public function __construct($iName,$iDescr) {
	global $wgRequest;

	parent::__construct($iName,$iDescr);

      // check to see if user has selected an existing customer record
	$strFormName = $this->FormName();
	$strChoice = $wgRequest->GetText($strFormName);
	if (is_numeric($strChoice)) {
	    $id = (int)$strChoice;
	} else {
	    $id = NULL;
	}
	$this->ID = $id;
    }
    public function FormName() {
	return $this->strName;
    }
    public function HasID() {
	return !is_null($this->ID);
    }
    public function ID() {
	return $this->ID;
    }
    /*----
      NOTE: These functions are a bit of a kluge that might ultimately be okay; they just need to
	be more solidly justified and documented.
	Right now, if they can't find the appropriate script stored locally, they look for it
	in the *other* Person object IF APPROPRIATE.
    */
    public function Script_forContact() {
	$act = NULL;
	if (isset($this->actCont)) {
	    $act = $this->actCont;
	} else {
	    $objPerson = $this->Parent()->Node('person.ship');
	    if (isset($objPerson->actCont)) {
		$act = $objPerson->actCont;
	    } else {
		echo '<b>Internal error</b>: cannot access Contact script from '.$objPerson->Name().'<br>';
		throw new exception('data structure error');
	    }
	}
	return $act;
    }
    public function Script_forName() {
	$act = NULL;
	if (isset($this->actName)) {
	    $act = $this->actName;
	} else {
	    $objPerson = $this->Parent()->Node('person.ship');
	    if (isset($objPerson->actName)) {
		$act = $objPerson->actName;
	    } else {
		echo '<b>Internal error</b>: cannot access Name script from '.$objPerson->Name().'<br>';
		throw new exception('data structure error');
	    }
	}
	return $act;
    }
    public function Script_forAddress() {
	$act = NULL;
	if (isset($this->actAddr)) {
	    $act = $this->actAddr;
	} else {
	    $objPerson = $this->Parent()->Node('person.ship');
	    if (isset($objPerson->actAddr)) {
		$act = $objPerson->actAddr;
	    } else {
		echo '<b>Internal error</b>: cannot access Address script from '.$objPerson->Name().'<br>';
		throw new exception('data structure error');
	    }
	}
	return $act;
    }
}
class clsPayment_Admin_WHO_USES /* extends clsPayment */ {
    private $tblCards;
    private $isDone;

    public function __construct($iNodes=NULL) {
	parent::__construct($iNodes);
	$this->tblCards = NULL;
	$this->isDone = FALSE;
    }
    /*----
      PURPOSE: caching of customer cards table-object
    */
    private function TblCards() {
	if (is_null($this->tblCards)) {
	    $this->tblCards = $this->Engine()->CustCards();
	}
	return $this->tblCards;
    }
/* 2011-12-18 this seems to be no longer needed
    protected function SaveThis() {
	if (!$this->isDone) {
	    $acts = $this->SaveThis_core();
	    $this->isDone = TRUE;
	} else {
	    $acts = NULL;
	}
	return $acts;
    }
*/
    /*----
      NOTES: We want to create the payment record *after* we have created all the detail records.
	The detail records don't depend on the payment record, but the payment record does link
	  to some of the detail records.
      HISTORY:
	2011-12-18 renamed from SaveThis_core() to SavePost()
    */
    protected function SavePost() {
	$acts = new Script_Script();

	$objPerson = $this->Person();
	//$tblCCards = $this->Engine()->CustCards();

	if ($objPerson->HasID()) {
	    // customer record previously existed, so we can use a known customer ID
	    $idPerson = $objPerson->ID();

	    $acts->Add(new Script_Status('Updating ccard '.$this->SafeDisplay()));
	    $act = $this->Make_Script($idPerson);
	    $acts->Add($act);
/*
	    $actOrd = $this->ScriptRoot()->Get_byName('ord.upd',TRUE);

	    $actIns = $act->Get_byName('ccard.make',TRUE);
	    $actCopy = new Script_SQL_Use_ID($actIns,$actOrd,'ID_ChargeCard');
	    $acts->Add($actCopy);
*/
	} else {
	    $acts->Add(new Script_Status('Adding ccard - '.$this->SafeDisplay()));
	    $acts->Add($this->Make_Script());
	}
	return $acts;
    }
    protected function Person() {
	return $this->Parent();
    }
    /*----
      ACTION: Create or update credit card record from a clsPayment
      RETURNS: Script object for changes to make
      INPUT:
	$iCustID: ID of customer record being handled
	$iData: credit card data which needs to be associated with that customer
      HISTORY:
	2011-09-23 Created so we can inspect SQL before executing
	2011-11-21 Renamed from Script_forMake() to Make_Script()
	2011-11-29
	    Moved from clsCustCards_dyn (base.cust.php) to clsPayment_admin (admin.cart.php)
	2011-12-18 Is this a duplicate of functionality in base.cust.php clsCustCards_dyn::MakeScript()?
    */
    public function Make_Script($iCustID=NULL) {
	$tblCards = $this->TblCards();

	$ar = array(
	  'cont'	=> $iCustID,
	  'num'		=> $this->Num()->Value(),
	  'exp'		=> $this->Exp()->Value(),
	  'name'	=> $this->CustName()->Value(),
	  'addr'	=> $this->Addr()->Value(),
	  );

	$objPerson = $this->Person();

	$actCont = $objPerson->Script_forContact();
	$actName = $objPerson->Script_forName();
	$actAddr = $objPerson->Script_forAddress();
	// this is the script to create/update the contact
	// now we just need to use its information, I forget how, this is where I was working...

//echo $actCont->Exec(FALSE); die();
//throw new exception('What was the path again?');
//	return new Script_Status('Where does this go?');

//	$actCust = $actCont->Get_byName('cust.data',TRUE);
//echo $actCust->Exec(FALSE); die();

	$acts = $tblCards->Script_Make($ar,$actCont,$actName,$actAddr);
//echo $acts->Exec(FALSE); die();
/*
	$sqlFilt = $this->Cards_MakeFilt_Cust($iCustID);
	$objRows = $tblCards->GetData($sqlFilt);

	$ar = $this->Cards_MakeArray_base();
	$acts = new Script_Script();
	if ($objRows->HasRows()) {
	    // action to UPDATE the address
	    $ar['WhenUpd'] = 'NOW()';
	    $cnt = $objRows->RowCount();
	    $acts->Add(new Script_Status('FOUND '.$cnt.' existing card'.Pluralize($cnt).'; updating...'));
	    $objRows->FirstRow();	// load the first row (should be only one anyway)
	    $acts->Add(new Script_Row_Update($ar,$objRows),'ccard.make');
	} else {
	    // action to CREATE the address
	    $ar['ID_Cust'] = $iCustID;
	    $ar['WhenEnt'] = 'NOW()';
	    $act = new Script_Tbl_Insert($ar,$tblCards);

echo $this->DumpHTML();



	    $actAddr = $this->Engine()->CustAddrs()->Make_Script($this->Addr(),NULL,$act);
	    $actAddrDo = $actAddr->Get_byName('do');
	    $acts->Add(new Script_SQL_Use_ID($actAddrDo,$ar,'ID_Addr'));	// $actAddr might not be the right script
	    $acts->Add(new Script_Status('2011-10-05 This ^ was not working for either ID_Cust or ID_Addr or anything, actually.'));
	    $acts->Add(new Script_Tbl_Insert($ar,$this),'ccard.make');

	}
*/
	return $acts;
    }
    protected function Cards_MakeFilt_Cust($idCust) {
	$strNum = $this->Num()->Value();
	return $this->TblCards()->MakeFilt_val_strip($idCust,$strNum);
    }
    /*----
      RETURNS: an array of SQL values compatible with Insert() or Update()
	Some values may need to be filled in before using.
      HISTORY:
	2011-11-29 split off from clsCustCards_dyn::MakeArray_base() (formerly Add_SQL_base())
	  This part extracts object fields into a simple array; clsCustCards_dyn massages them into the SQL array.
    */
    protected function Cards_MakeArray_base() {
	$arData = array(
	  'num'		=> $this->Num()->Value(),
	  'exp'		=> $this->Exp()->Value(),
	  'addr'	=> $this->Addr()->AsText(),
	  'name'	=> $this->Addr()->Name()->Value(),
	  );
	$arSQL = $this->TblCards()->MakeArray_base($arData);
	return $arSQL;
    }
}
class clsCartAddr_Admin_WHO_USES /* extends clsCartAddr */ {
    private $isDone;

    public function __construct($iNodes=NULL) {
	parent::__construct($iNodes);
	$this->isDone = FALSE;
    }
    protected function Person() {
	return $this->Parent()->Parent();	// that's how it's structured now, but could change later
    }
    protected function SaveThis() {
/* only needed if there's more than one step to add
	$acts = new Script_Script();
	//$actCust = $this->Engine()->Custs()->Make_fromCartAddr_SQL($this);
	$actCust = $this->Make_Script();
	$acts->Add(new Script_Status('Next step CHECKS CONTACT ADDRESS (check this!)'));
	$acts->Add($actCust,'cust');
*/
	if (!$this->isDone) {
	    // different execution paths may lead here more than once, but no need to repeat save
	    $acts = $this->Make_Script();
	    $this->isDone = TRUE;

// 2012-01-02 this is still needed so we know the address assigned to this Person
	    $actAddr = $acts->Get_byName('cust.addr.do',TRUE);
	    $this->Person()->actAddr = $actAddr;	// make sure the Person has the update script
	} else {
	    $acts = NULL;
	}

/* 2011-11-26 not sure if this will be needed
	// there needs to be a less script-structure-dependent way to do this:
	//$actCustIns = $actCust->Trial();	// get the insert action
	$actCustIns = $actCust->Get_byName('cust.ins',TRUE);
	$actCustXfer = $actCust->Get_byName('cust.id.xfer',TRUE);
*/
	//$iScript->Add($acts,'addr');
	return $acts;
    }
    /*----
      LATER: if idCust is not known, can skip the searching
      NOTE: This is complicated because it also needs to update fields in the
	order record, but they may be different fields depending on which address
	this is (shipping or payment).
      INPUT:
	$iAddr: TreeNode-descended address object to import (from cart data)
	$idCust: ID of customer record to which this address belongs
	$oarOrdGen: generic fields for updating order
	  We may need to update fields in the order record, but we don't know
	    which ones -- because that depends on the type of contact information
	    being imported. So we stash the result in a generic array which the
	    caller will plug into the appropriate fields.
      HISTORY:
	2011-09-23 written for script-based import process
	2011-11-29 moved from clsCustAddrs (base.cust.php) to clsCartAddr_Admin (admin.cart.php)
	2011-12-15 this couldn't have been working right, because it referred to an object that didn't exist
    */
    public function Make_Script() {
//throw new exception('How do we get here? DO we get here?');
	$acts = new Script_Script();

	$tblAddrs = $this->Engine()->CustAddrs();
	//$actOrder = $this->ScriptRoot()->Get_byName('ord.upd',TRUE);
	
	// check to see if we're updating or inserting
	if ($this->Person()->HasID()) {
	    $arUpd = array('WhenUpdated' => 'NOW()');
	    $idCust = $this->Person()->ID();
	} else {
	    $arIns = array('WhenCreated' => 'NOW()');
	    $actCust = new Script_Tbl_Insert($arIns,$tblAddrs);
	    $idCust = NULL;
	}

	$isCust = !is_null($idCust);

	if ($isCust) {
	    $strKey = $this->AsSearchable();
	    $objAddr = $tblAddrs->Find($strKey,$idCust);
	    $isAddr = $objAddr->HasRows();	// matching address found?
	} else {
	    $isAddr = FALSE;			// new customer, so no address match either
	}

	$objPerson = $this->Person();
	$actCont = $objPerson->Script_forContact();
	if ($isAddr) {
	    $objAddr->FirstRow();
	    $idAddr = $objAddr->KeyValue();
	    $acts->Add(new Script_Status('SAME as existing address ID='.$idAddr));
	    $arAct = $tblAddrs->Update_SQL($this);
	    $act = new Script_Row_Update($arAct,$objAddr);
	    $acts->Add(new Script_SQL_Use_ID($actCont,$act,'ID_Cust'));
	    $acts->Add($act,'cust.addr.do');	// we used to need to find this, but not anymore
	    //$actOrder->Name('addr',$idAddr);	// using existing record
	} else {
	    $arAct = $tblAddrs->Create_SQL($idCust,$this);
	    $acts->Add(new Script_Status('New address record needed; idCust=['.$idCust.']'));
	    $act = new Script_Tbl_Insert($arAct,$tblAddrs);
	    $acts->Add(new Script_SQL_Use_ID($actCont,$act,'ID_Cust'));
	    $acts->Add($act,'cust.addr.do');	// we used to need to find this, but not anymore
	    //$acts->Add(new Script_SQL_Use_ID($act,$actOrder,'addr'));
	}
	return $acts;
    }

    /*----
      ACTION: Ensures that the given customer data is recorded.
	The following tables may be affected:
	  customers
	  customer names
	  customer addresses
      RETURNS: SQL script for Make_fromCartAddr()
      HISTORY:
	2011-10-08 finally figured out that I was INSERTing the customer record twice
	2011-11-18 fixed more bugs (did it work last time? cuz I don't see how it could have)
	2011-11-29
	  * moved from clsCusts to clsCartAddr_Admin (which was basically created for it)
	  * renamed from Make_fromCartAddr_SQL() to Contact_Make_Script()
	  * code modified to handle new class context
    */
/* 2011-11-29 this was take 1
    public function Contact_Make_Script() {
	$tblCusts = $this->Engine()->Custs();

	// check to see if we're updating or inserting
	if ($this->Person()->HasID()) {
	    $arUpd = array('WhenUpdated' => 'NOW()');
	} else {
	    $arIns = array('WhenCreated' => 'NOW()');
	    $actCust = new Script_Tbl_Insert($arIns,$tblCusts);
	}

	// is this really needed? commenting out for now.
	//$actCust_ins->Name('cust.ins');	// name it so it can be retrieved
	// do this if that works:

	$tblNames = $this->Engine()->CustNames();
	$tblAddrs = $this->Engine()->CustAddrs();

	// get base arrays for creating name and address records
	$arNameCreate = $tblNames->Create_SQL_init($this->Name()->Value());
	$arAddrCreate = $tblAddrs->Create_SQL_init($this);

	// set up the sequence to create name and address & then update the customer
	$actCre_Name = new Script_Tbl_Insert($arNameCreate,$tblNames);
	$actCre_Addr = new Script_Tbl_Insert($arAddrCreate,$tblAddrs);

	global $sql;
	if (!is_object($actCre_Name)) {
	    echo '<b>INTERNAL ERROR</b>: script not created for customer name.';
	    echo '<br><b>SQL</b>: '.$sql;
	    throw new exception('Script not created.');
	}
	if (!is_object($actCre_Addr)) {
	    echo '<b>INTERNAL ERROR</b>: script not created for customer name.';
	    echo '<br><b>SQL</b>: '.$sql;
	    throw new exception('Script not created.');
	}

	// update newly-created Name and Addr records with ID of newly-created Customer record
	$actFill_ID_toName = new Script_SQL_Use_ID($actCust_ins,$actCre_Name,'ID_Cust');	// source, destination, field name
	$actFill_ID_toAddr = new Script_SQL_Use_ID($actCust_ins,$actCre_Addr,'ID_Cust');

	// update new Customer record with IDs from newly-created Name and Addr records
	$actCust_upd = new Script_Row_Update_fromInsert(array(),$actCust_ins);
	$actFill_ID_frName = new Script_SQL_Use_ID($actCre_Name,$actCust_upd,'ID_Name');
	$actFill_ID_frAddr = new Script_SQL_Use_ID($actCre_Addr,$actCust_upd,'ID_Addr');

	// create script for conditional execution:
	$actMain = new Script_Script();
	  // placeholder for ID-copying actions - needed by caller:
	  //$actMain->Add(new Script_RowObj_scratch('cust.id.xfer'));	// or maybe not needed anymore?
	  // add cust ID to new Name data:
	  $actMain->Add($actFill_ID_toName,'init.cust.name');
	  // add cust ID to new Addr data:
	  $actMain->Add($actFill_ID_toAddr,'init.cust.addr');
	  // create name
	  $actMain->Add($actCre_Name,'cust.name');	// this name is significant
	  // create address
	  $actMain->Add($actCre_Addr,'cust.addr');	// this name is significant
	  // copy ID of new name back to customer update
	  $actMain->Add($actFill_ID_frName);
	  // copy ID of new addr back to customer update
	  $actMain->Add($actFill_ID_frAddr);
	  // do the customer update
	  $actMain->Add($actUpd_Cust);
	  $actMain->Add(new Script_Status('2011-10-08 This ^ was not updating ID_Name and ID_Addr.'));

	// try to create cust record ($actIns_Cust); if ok, then do everything else ($actMain)
	$actIf_ok = new Script_IF_Ok($actIns_Cust,$actMain);
	return $actIf_ok;	// and that's the script
    }
*/
}
abstract class clsCartField_admin_WHO_USES /* extends clsCartField */ {
    private $objPerson;

    public function __construct($iCart, $iIndex, $iCtrlName) {
	parent::__construct($iCart, $iIndex, $iCtrlName);
	$this->objPerson = NULL;
    }
    protected function SaveThis() {
	$act = $this->Make_Script();
	return $act;
    }
    protected function Person() {
	if (is_null($this->objPerson)) {
	    $objParent = $this->Parent();
	    do {
		$done = TRUE;
		if (!is_subclass_of($objParent,'clsPerson')) {
		    if (!is_null($objParent)) {
			$objParent = $objParent->Parent();
			$done = FALSE;	// keep going up
		    }
		}
	    } while (!$done);

	    $this->objPerson = $objParent;
	}
	return $this->objPerson;
    }
    protected abstract function Table();
    protected function Make_Script() {
	if ($this->Person()->HasID()) {
	    $idCont = $this->Person()->ID();
	} else {
	    $idCont = NULL;
	}
	$act = $this->Table()->Make_Script($idCont,$this->Value());
//throw new exception('How did we get here?');
	return $this->Wrap_Script($act);
    }
    protected function Wrap_Script(Script_Element $iScript) { return $iScript; }	// default: add nothing
}
class clsEmail_admin_WHO_USES /* extends clsCartField_admin */ {
    protected function Table() {
	return $this->Engine()->CustEmails();
    }
}
class clsPhone_admin_WHO_USES /* extends clsCartField_admin */ {
    protected function Table() {
	return $this->Engine()->CustPhones();
    }
}
class clsName_admin_WHO_USES /* extends clsCartField_admin */ {
    protected function Table() {
	return $this->Engine()->CustNames();
    }
    protected function SaveThis() {
	$act = parent::SaveThis();
	$actName = $act->Get_ByName('name.data',TRUE);
	$this->Person()->actName = $actName;	// make sure the Person has the update script
	return $act;
    }
    /*----
      ACTION: fetches the action that created the contact record,
	and uses it to fill in the ID_Cust field
      HISTORY:
	2012-01-02 created
    */
    protected function Wrap_Script(Script_Element $iScript) {
	$acts = new Script_Script();
	$objPerson = $this->Person();
	$actCont = $objPerson->Script_forContact();
	$acts->Add(new Script_SQL_Use_ID($actCont,$iScript,'ID_Cust'));
	$acts->Add($iScript);

	return $acts; 
    }
}
/* ****
  SECTION: shopping cart classes
*/
class VbzAdminCarts extends clsShopCarts {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCart');
    }
/*
    public function ActionKey($iName=NULL) {
	if (!is_null($iName)) {
	    $this->ActionKey = $iName;
	}
	return $this->ActionKey;
    }
*/
    public function AdminPage() {
	global $wgOut;
	global $vgPage;

	$vgPage->UseWiki();

	$out = '==Carts==';
	$wgOut->addWikiText($out,TRUE);	$out = '';
	$this->Name('qryCarts_info');
	$objRecs = $this->GetData(NULL,NULL,'ID DESC');
	if ($objRecs->HasRows()) {
	    $out = "'''S'''=Session | '''O'''=Order | '''C'''=Customer | '''#D'''= # of Data lines | '''#I''' = # of Items in cart";
	    $out .= "\n{| class=sortable\n|-\n! ID || Created || Ordered || Updated || S || O || C || #D || #I";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$id = $objRecs->ID;
//		$wtID = SelfLink_Page('cart','id',$id,$id);
		$wtID = $objRecs->AdminLink();

		$wtWhenCre = $objRecs->WhenCreated;
		$wtWhenOrd = $objRecs->WhenOrdered;
		$wtWhenUpd = $objRecs->WhenUpdated;
		$objSess = $objRecs->SessObj();
		$objOrd = $objRecs->OrderObj();
//		$wtSess = SelfLink_Page(KS_URL_PAGE_SESSION,'id',$objRecs->ID_Sess);
//		$wtOrd = SelfLink_Page(KS_URL_PAGE_ORDER,'id',$objRecs->ID_Order);
		$wtSess = $objSess->AdminLink();
		$wtOrd = $objOrd->AdminLink();
		$wtCust = SelfLink_Page('cust','id',$objRecs->ID_Cust);
		$out .= 
		  "\n|- style=\"$wtStyle\"\n"
		  ."| $wtID || $wtWhenCre || $wtWhenOrd || $wtWhenUpd || $wtSess || $wtOrd || $wtCust"
		  ."|| {$objRecs->DataCount} || {$objRecs->ItemCount}";
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'No carts have been created yet.';
	}
	$wgOut->addWikiText($out,TRUE);	$out = '';
    }
}
class VbzAdminCart extends clsShopCart {
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function SessObj() {
	$idSess = $this->ID_Sess;
	$objSess = $this->objDB->Sessions()->GetItem($idSess);
	return $objSess;
    }
    public function OrderObj() {
	$idOrder = $this->ID_Order;
	$objOrder = $this->objDB->Orders()->GetItem($idOrder);
	return $objOrder;
    }
/* obsolete 2011-03-27
    public function FinishEvent() {
	$this->objDB->Events()->FinishEvent($this->idEvent);
    }
*/
    /*----
      HISTORY:
	2011-03-27 renamed StartEvent() -> StartEvent_old
    */
    public function StartEvent_old($iWhere,$iCode,$iDescr) {
	$arEvent = array(
	  'where'	=> $iWhere,
	  'code'	=> $iCode,
	  'descr'	=> $iDescr,
	  'type'	=> $this->Table->ActionKey(),
	  'id'		=> $this->ID
	  );
	$this->idEvent = $this->objDB->Events()->StartEvent($arEvent);
    }
    public function SetupLinks() {
	global $vgPage;

	$id = $this->KeyValue();
	$arLink = array('cart'=>$id,'do'=>'cart');
	$htUse = $vgPage->SelfLink($arLink,'use');
	$htView = $this->AdminLink('view');
	$out = "#$id [$htUse] [$htView]";
	return $out;
    }
    public function AdminPage() {
	global $wgOut;
	global $vgPage,$vgOut;

	if ($this->KeyValue() == 0) {
	    throw new exception('Object has no ID');
	}

	$strDo = $vgPage->Arg('do');
	$doText = ($strDo == 'text');

	if ($doText) {
	    $out = '<pre>'.$this->RenderOrder_Text().'</pre>';
	} else {
	    $vgPage->UseWiki();	// for now...
	    $out = NULL;

	    if ($strDo == 'find-ord') {
	    // find the order which was created from this cart
		$out .= 'Looking up order...';
		$sql = 'ID_Cart='.$this->ID;
		$rs = $this->Engine()->Orders()->GetData($sql);
		if ($rs->HasRows()) {
		    $rc = $rs->RowCount();
		    if ($rc > 1) {
			$out .= $rc.' rows found, should be only 1. SQL='.$this->Engine->sqlExec;
		    } else {
			$rs->NextRow();	// get the first (and only) row
			$idOrd = $rs->ID;
			$arEv = array(
			  'code'	=> 'ord-fnd',
			  'descr'	=> 'found order for cart',
			  'params'	=> '\ID='.$idOrd,
			  'where'	=> __METHOD__
			  );
			$this->StartEvent($arEv);
			$this->Update(array('ID_Order' => $idOrd));
			$this->Reload();
			$this->FinishEvent();
		    }
		} else {
		    $out .= ' no orders found! SQL='.$this->Engine->sqlExec;
		}
	    }

	    //$wtSess = SelfLink_Page(KS_URL_PAGE_SESSION,'id',$this->ID_Sess);
	    //$wtOrd = SelfLink_Page(KS_URL_PAGE_ORDER,'id',$this->ID_Order);
	    $objSess = $this->SessObj();
	    $wtSess = $objSess->AdminLink();

	    if (is_null($this->ID_Order)) {
		$wtOrd = $vgPage->SelfLink(array('do'=>'find-ord'),'find order!');
	    } else {
		$objOrder = $this->OrderObj();
		$wtOrd = $objOrder->AdminLink_name();
	    }

	    $wtCust = SelfLink_Page('cust','id',$this->ID_Cust);

	    $arSelf_Text = array(
		'page'	=> 'cart',
		'id'	=> $this->ID,
		'do'	=> 'text'
		);

	    $out = '==Cart=='."\n";
	    $out .= $vgOut->SelfLink($arSelf_Text,'text','show the cart as plain text');
	    $out .= "\n* '''ID''': ".$this->ID;
	    $out .= "\n* '''When Created''': ".$this->WhenCreated;
	    $out .= "\n* '''When Ordered''': ".$this->WhenOrdered;
	    $out .= "\n* '''Session''': ".$wtSess;
	    $out .= "\n* '''Order''': ".$wtOrd;
	    $out .= "\n* '''Customer''': ".$wtCust;
	    $wgOut->addWikiText($out,TRUE);	$out = '';
	    $out .= "\n===Items===";
	    $out .= $this->ItemTable();
	    $wgOut->addWikiText($out,TRUE);	$out = '';
/* wikitext version
	    $out .= "\n===Data===";
	    $out .= $this->DataTable();
	    $wgOut->addWikiText($out,TRUE);	$out = '';
*/
	    $out .= "\n<h3>Data</h3>";
	    $out .= $this->DataTable();
	    $wgOut->addHTML($out); $out = '';
	    $out .= "\n===Events===\n";
	    $out .= "\n====general log====\n";
	    $out .= $this->EventListing();
	    $out .= "\n====special log====\n";
	    $out .= $this->EventTable();
	}
	$wgOut->addWikiText($out,TRUE);	$out = '';
    }
    /*-----
      PURPOSE: Display the cart's items as a table
    */
    public function ItemTable() {
	//$objItems = new VbzAdminCartLines($this->objDB);
	$objTbl = $this->objDB->CartLines();
	return $objTbl->Table_forCart($this->ID);
    }
    /*---
      PURPOSE: Display the cart's data lines as a table
    */
    public function DataTable() {
	$objItems = new VbzAdminCartData($this->objDB);
	return $objItems->Table_forCart($this->ID);
    }
    /*---
      PURPOSE: Display the cart's events as a table
    */
    public function EventTable() {
	$objTbl = $this->objDB->CartLog();
	$objRows = $objTbl->GetData('ID_Cart='.$this->ID);
	return $objRows->AdminTable();
    }
    public function SpawnContact($iNodes=NULL) {
	return new clsCartContact_admin($iNodes);
    }
    public function SpawnPerson($iName,$iDescr) {
	return new clsPerson_Admin($iName,$iDescr);
    }
    public function SpawnPayment($iNodes=NULL) {
	return new clsPayment_Admin($iNodes);
    }
    public function SpawnAddress($iNodes=NULL) {
	return new clsCartAddr_Admin($iNodes);
    }
    public function SpawnEmail($iIndex, $iCtrlName) {
	return new clsEmail_admin($this, $iIndex, $iCtrlName);
    }
    public function SpawnPhone($iIndex, $iCtrlName) {
	return new clsPhone_admin($this, $iIndex, $iCtrlName);
    }
    public function SpawnName($iIndex, $iCtrlName) {
	return new clsName_admin($this, $iIndex, $iCtrlName);
    }
}
// SHOPPING CART ITEMS
class VbzAdminCartLines extends clsShopCartLines {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCartLine');
    }
    public function Table_forCart($iCart) {
	$objRecs = $this->GetData('ID_Cart='.$iCart,NULL,'Seq');
	if ($objRecs->HasRows()) {
	    $out = "\n{| class=sortable\n|-\n! ID || # || Item || Qty || Added || Changed";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$id = $objRecs->ID;
		//$wtID = SelfLink_Page('cart','id',$id,$id);
		$wtID = $objRecs->AdminLink();
		//$wtItem = $objRecs->Item()->DescLong();
		$rcItem = $objRecs->Item();
		//$wtItem = $rcItem->DescLong();
		$wtItem = $rcItem->AdminLink_name();

		$wtAdded = $objRecs->WhenAdded;
		$wtEdited = $objRecs->WhenEdited;

		$out .= 
		  "\n|- style=\"$wtStyle\"\n"
		  ."| $wtID "
		  ."|| {$objRecs->Seq}"
		  ."|| $wtItem"
		  ."|| {$objRecs->Qty}"
		  ."|| $wtAdded"
		  ."|| $wtEdited"
		  ;
	    }
	} else {
	    $out = "''No items in cart''";
	}
	return $out;
    }
}

/*====
  HISTORY:
    2010-11-15 created
*/
class VbzAdminCartLine extends clsShopCartLine {
    /*----
      HISTORY:
	2010-11-15 created
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
}
// SHOPPING CART DATA
class VbzAdminCartData extends clsTable {
    public function __construct($iDB) {
      parent::__construct($iDB);
	$this->Name('shop_cart_data');
	$this->ClassSng('VbzAdminCartDatum');
    }

    public function Table_forCart($iCart) {
	global $wgRequest;
	global $vgPage,$vgOut;

	$vgPage->UseHTML();
	$out = '';

	if ($wgRequest->getBool('btnSaveDatum')) {
	    $idEdit = $wgRequest->getText('edit-datum');
	    $txtVal = $wgRequest->getText('val');
	    $objCart = $this->objDB->Carts()->GetItem($iCart);
	    $htDescr = '['.$idEdit.']: ['.$objCart->GetDataItem($idEdit).'] &rarr; ['.$txtVal.']';
	    $sqlDescr = '['.$idEdit.']: ['.$objCart->GetDataItem($idEdit).'] => ['.$txtVal.']';

	    $out .= 'Updating '.$htDescr;
	    $objCart->StartEvent(__METHOD__,'ED-D','admin edited '.$sqlDescr);
	    $objCart->PutDataItem($idEdit,$txtVal);
	    $objCart->FinishEvent();
	} else {
	    $idEdit = $vgPage->Arg('edit-datum');
	}


	$objRecs = $this->GetData('ID_Cart='.$iCart);
	if ($objRecs->HasRows()) {
	    $arLink = array(
	      'page'	=> 'cart',
	      'id'	=> $iCart
	      );
	    if (!empty($idEdit)) {
//		$out .= '<form action="'.$vgOut->SelfURL($arLink).'">';
		$out = '<form method=post>';
	    }
	    $out .= "\n<table class=sortable>\n<tr><th>Type</th><th>Value</th></tr>";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$txtType = $objRecs->ValueText();
		$idType = $objRecs->Type;
		$htType = htmlspecialchars($txtType);
		$arLink['edit-datum'] = $idType;
		$ftType = $vgOut->SelfLink($arLink,$htType,'edit &ldquo;'.$htType.'&rdquo;');
		$ftVal = htmlspecialchars($objRecs->Val);
 
		$doEdit = ($idType == $idEdit);
		if ($doEdit) {
		    $ftTypeCtrl = '<b>'.$ftType.'</b><br><input type=submit name=btnSaveDatum value="Save">';
		    $ftValCtrl = '<input type=hidden name=edit-datum value="'.$idType.'">'
		      .'<textarea name=val width=30 height=2>'.$ftVal.'</textarea>';
      		} else {
		    $ftTypeCtrl = $ftType;
		    $ftValCtrl = $ftVal;
		}
		$out .= "\n<tr style=\"$ftStyle\"><td>$ftTypeCtrl</td><td>$ftValCtrl</td></tr>";
		
	    }
	    if (!empty($idEdit)) {
		$out .= '</form>';
	    }
	    $out .= "\n</table>";
	} else {
	    $out = "\n<i>No data in cart</i>";
	}
	return $out;
    }
}
class VbzAdminCartDatum extends clsDataSet {
    static $arTypeNames = array (
    KSI_SHIP_ZONE		=> 'ship zone',
    KSI_ADDR_SHIP_NAME		=> 'ship-to name',
    KSI_ADDR_SHIP_STREET	=> 'ship-to street',
    KSI_ADDR_SHIP_CITY		=> 'ship-to city',
    KSI_ADDR_SHIP_STATE		=> 'ship-to state',
    KSI_ADDR_SHIP_ZIP		=> 'ship-to zipcode',
    KSI_ADDR_SHIP_COUNTRY	=> 'ship-to country',
    KSI_SHIP_MESSAGE		=> 'ship-to message',
    KSI_SHIP_TO_SELF		=> 'ship to self?',
    KSI_SHIP_IS_CARD		=> 'ship to = card?',
    KSI_CUST_SHIP_EMAIL		=> 'ship-to email',
    KSI_CUST_SHIP_PHONE		=> 'ship-to phone',
//    KSI_SHIP_MISSING		=> 'ship-to missing info',
// -- payment
    KSI_CUST_CARD_NUM		=> 'card number',
    KSI_CUST_CARD_EXP		=> 'card expiry',
    KSI_ADDR_CARD_NAME		=> 'card owner',
    KSI_ADDR_CARD_STREET	=> 'card street address',
    KSI_ADDR_CARD_CITY		=> 'card address city',
    KSI_ADDR_CARD_STATE		=> 'card address state',
    KSI_ADDR_CARD_ZIP		=> 'card zipcode',
    KSI_ADDR_CARD_COUNTRY	=> 'card country',
    KSI_CUST_CHECK_NUM		=> 'check number',
    KSI_CUST_PAY_EMAIL		=> 'customer email',
    KSI_CUST_PAY_PHONE		=> 'customer phone',

    KSI_ITEM_TOTAL		=> 'item total',
    KSI_PER_ITEM_TOTAL		=> 's/h per-item total',
    KSI_PER_PKG_TOTAL		=> 's/h package total',
    );

    public function ValueText() {
	$strType = $this->Type;
	$ar = self::$arTypeNames;
	if (isset($ar[$strType])) {
	    $wtType = $ar[$strType];
	} else {
	    $wtType = "'''?'''$strType";
	}
	return $wtType;
    }
}
/*====
  EVENT CLASSES
  NOTES:
    2010-10-16 Should this eventually be folded into the universal event log, or not?
*/
class VbzAdminCartLog extends clsTable {
    const TableName='shop_cart_event';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminCartEvent');	// override parent
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }
    /*=====
      NOTES:
      * Adapted from clsOrderLog::Add()
      * Should the order log be merged into the global event log?
    */
    public function Add(clsShopCart $iCart,$iCode,$iDescr,$iUser) {
	global $vgUserName;

	$arIns = array(
	  'ID_Cart'	=> $iCart->ID,
	  'WhenDone'	=> 'NOW()',
	  'WhatCode'	=> SQLValue($iCode),
	  'WhatDescr'	=> SQLValue($iDescr),
	  'ID_Sess'	=> ($iCart->HasSession())?($iCart->ID_Sess):'NULL',
	  'VbzUser'	=> SQLValue($vgUserName),
	  //'SysUser'	=> SQLValue($_SERVER["SERVER_NAME"]),
	  'Machine'	=> SQLValue($_SERVER['REMOTE_ADDR'])
	  );
	$this->Insert($arIns);
    }
    /*----
      HISTORY:
	2010-10-16 Created
      ACTION: By default, shows event counts by day and by IP address
    */
    public function AdminPage() {
	global $wgOut;
	global $vgOut,$vgPage;

	$vgPage->UseHTML();

	$out = "\n<b>Show</b>: ";
	$arMenu = array(
	  'date'	=> '\by date\show how many events on each date',
	  'addr'	=> '\by IP\show how many events for each IP address',
	  'list'	=> '\*list*\list cart events in chronological order');
	$strShow = $vgPage->Arg('show');
	$out .= $vgPage->SelfLinkMenu('show',$arMenu,$strShow);

	switch ($strShow) {
	  case 'list':
	      $objRows = $this->GetData();
	      $out .= $objRows->AdminTable();
	    break;
	  case 'date':
	    $strDate = $vgPage->Arg('date');
	    if (is_string($strDate)) {
		$objRows = $this->GetData('DATE(WhenDone)="'.$strDate.'"');
		$out .= $objRows->AdminTable();
	    } else {
		$arFldsDate = array(
		  'DateDone'	=> 'DATE(WhenDone)',
		  'HowMany'	=> 'COUNT(ID)');
		$objRows = $this->DataSetGroup($arFldsDate,'DateDone','DateDone DESC');

		if ($objRows->hasRows()) {
		    $isOdd = TRUE;
		    $out .= "\n<table>";
		    while ($objRows->NextRow()) {
			$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
			$isOdd = !$isOdd;

			$row = $objRows->Row;
			$strDate = $row['DateDone'];
			$intCount = $row['HowMany'];
			$arLink['date'] = $strDate;
			$ftDate = $vgPage->SelfLink($arLink,$strDate);
			$out .= "\n<tr style=\"$wtStyle\"\n><td>$ftDate</td><td align=right>$intCount</td></tr>";
		    }
		    $out .= "\n</table>";
		} else {
		    $out = 'No cart events in database yet.';
		}
	    }
	  break;

	  case 'addr':
	    $strAddr = $vgPage->Arg('addr');
	    if (is_string($strAddr)) {
		$objRows = $this->GetData('Machine="'.$strAddr.'"');
		$out .= $objRows->AdminTable();
	    } else {
		$arFldsAddr = array(
		  'Addr'	=> 'Machine',
		  'AddrNum'	=> 'INET_ATON(Machine)',
		  'HowMany'	=> 'COUNT(ID)');
		$objRows = $this->DataSetGroup($arFldsAddr,'Machine','HowMany DESC');
		if ($objRows->hasRows()) {
		    $isOdd = TRUE;
		    $out .= "\n<table>";
		    while ($objRows->NextRow()) {
			$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
			$isOdd = !$isOdd;

			$row = $objRows->Row;
			$strAddr = $row['Addr'];
			$intCount = $row['HowMany'];
			$arLink['addr'] = $strAddr;
			$ftAddr = $vgPage->SelfLink($arLink,$strAddr);
			$out .= "\n<tr style=\"$wtStyle\"\n><td>$ftAddr</td><td align=right>$intCount</td></tr>";
		    }
		    $out .= "\n</table>";
		} else {
		    $out = 'No cart events in database yet.';
		}
	    }
	  break;
	}
	$wgOut->AddHTML($out); $out = '';
	return $out;
    }
}
class VbzAdminCartEvent extends clsDataSet {
    public function AdminTable() {
	if ($this->hasRows()) {
	    //$htUnknown = '<span style="color: #888888;">?</span>';
	    $out = "\n<table>";
	    $out .= "\n<tr>\n<th>ID</th><th>Cart</th><th>Sess</th><th>When</th><th>Who/How</th><th>Code</th><th>Description</th></tr>";
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;

		$htWho		= WhoString_wt($row);

		$htCode		= $row['WhatCode'];

		$htDescr	= $row['WhatDescr'];
		$strNotes	= $row['Notes'];
		if (!is_null($strNotes)) {
		    $htDescr .= " ''$strNotes''";
		}

		$id		= $row['ID'];
		$idCart		= $row['ID_Cart'];
		$idSess		= $row['ID_Sess'];
		$strWhen	= $row['WhenDone'];

		$dtWhen		= strtotime($strWhen);
		$strDate	= date('Y-m-d',$dtWhen);
		$strTime = date('H:i:s',$dtWhen);
		if ($strDate != $strDateLast) {
		    $strDateLast = $strDate;
		    $out .= "\n<tr style=\"background: #444466; color: #ffffff;\"\n><td colspan=5><b>$strDate</b></td></tr>";
		}
		
		$out .= 
		   "\n<tr style=\"$wtStyle\">"
		  ."\n<td>$id</td>"
		  ."<td>$idCart</td>"
		  ."<td>$idSess</td>"
		  ."<td>$strTime</td>"
		  ."<td>$htWho</td>"
		  ."<td>$htCode</td>"
		  ."<td>$htDescr</td>"
		  ."</tr>";
	    }
	    $out .= "\n</table>";
	} else {
	    $out = 'No events logged yet.';
	}
	return $out;
    }
}