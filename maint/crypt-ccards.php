<?php
/*
  NAME: crypt-ccards
  PURPOSE: maintenance script for managing encryption of credit card information
  HISTORY:
    2014-12-25 started adapting from old MW admin script
    2015-01-20 more or less finalized; renamed from ccards-crypt to crypt-ccards
*/
require_once '/var/www/vbz/local.php';				// basic library paths
require_once(KFP_LIB_VBZ.'/config-libs.php');

// UTILITY FX

function LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere) {
    global $oDB;

    $oDB->LogEvent($iWhere,$iParams,$iDescr,$iCode,$iIsError,$iIsSevere);
}

// CLASSES (main code is after)

class clsCustCards_maint extends clsCustCards_dyn {
    protected function PublicKey_FileSpec() {
	$fsKey = $this->Engine()->PublicKey_FileSpec();
	echo "Retrieving public key from [$fsKey].\n";
	return $fsKey;
    }
    protected function PublicKey_String() {
	$fsKey = $this->Engine()->PublicKey_string();
	return $fsKey;
    }

    /*----
      ACTION: Encrypt data in all rows and save to Encrypted field
    */
    public function DoAdminEncrypt() {
	LogEvent(__METHOD__,NULL,'encrypting sensitive data in ccard records',NULL,FALSE,FALSE);

	$rsCC = $this->GetData();
	if ($rsCC->hasRows()) {
	    $qDone = 0;
	    $qDiff = 0;
	    $qFail = 0;
	    $qRows = $rsCC->RowCount();

	    $oCrypt = &$rsCC->CryptObj();
	    $oCrypt->PublicKey($this->PublicKey_String());
	    echo "Encrypting $qRows rows of credit card records:\n";
	    while ($rsCC->NextRow()) {
		$qDone++;

		$sCryptNew = $rsCC->Encrypt(TRUE,FALSE);
		if ($sCryptNew === FALSE) {
		    $qFail++;
		}
		echo "$qDone updated, $qFail not saved\r";
	    }
	    $strStats = $qDone.' row'.clsString::Pluralize($qDone).' processed, ';
	    $strStats .= $qDiff.' row'.clsString::Pluralize($qDiff).' altered';
	    LogEvent(__METHOD__,NULL,$strStats,NULL,FALSE,FALSE);

	    echo "\n* $qDone row".clsString::Pluralize($qDone).' processed';
	    echo "\n* $qDiff row".clsString::Pluralize($qDiff).' altered';
/*
	    $sPlain = $oCrypt->LastPlain();
	    echo "\n* LAST DATA: [$sPlain]";
	    echo "\n* LAST OLD ENCRYPT: [".Cipher_pubkey::Textify($sCryptOld).']';
	    echo "\n* LAST NEW ENCRYPT: [".Cipher_pubkey::Textify($sCryptNew).']';
	    echo "\n* TEST ENCRYPT 1: [".Cipher_pubkey::Textify($oCrypt->encrypt($sPlain)).']';
	    echo "\n* TEST ENCRYPT 2: [".Cipher_pubkey::Textify($oCrypt->encrypt($sPlain)).']';
	    */
	} else {
	    LogEvent(__METHOD__,NULL,'CustCards: No records found to process',NULL,FALSE,FALSE);
	    echo 'No credit cards currently in database.';
	}
    }
    public function DoAdminDecrypt($iPvtKey) {
	LogEvent(__METHOD__,NULL,'decrypting data',NULL,FALSE,FALSE);

	$rsCC = $this->GetData();
	if ($rsCC->hasRows()) {
	    $out = "\n\nDecrypting cards: ";
	    $intFound = 0;
	    while ($rsCC->NextRow()) {
		$intFound++;
		$rsCC->Decrypt(TRUE,$iPvtKey);	// decrypt and save
		$strNumEncrNew = $rsCC->Encrypted;
	    }
	    $intRows = $rsCC->RowCount();
	    $intMissing = $intRows - $intFound;
	    if ($intMissing) {
		$strStat = $intFound.' row'.Pluralize($intFound).' out of '.$intRows.' not decrypted!';
		echo " ERROR - $strStat!";
		LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
	    } else {
		$strStat = $intRows.' row'.Pluralize($intRows);
		echo " OK - $strStat decrypted successfully";
		LogEvent(__METHOD__,NULL,$strStat.' decrypted successfully',NULL,FALSE,FALSE);
	    }
	} else {
	    echo "No credit cards to decrypt!\n";
	}
    }
    /*----
      ACTION: Verify that decrypting the encrypted data matches the plaintext data
    	This was part of AdminCrypt, but it took too long to execute
    */
    public function DoCheckDecrypt($sPvtKey) {
	LogEvent(__METHOD__,NULL,'decrypt test on encrypted data',NULL,FALSE,FALSE);

	$rsCC = $this->GetData('Encrypted IS NOT NULL');
	$oCrypt = &$rsCC->CryptObj();
	$oCrypt->PrivateKey($sPvtKey);
	if ($rsCC->hasRows()) {
	    $qRows = $rsCC->RowCount();
	    echo $qRows.' encrypted row'.clsString::Pluralize($qRows)." found.\n";
	    $qSame = 0;
	    $qDone = 0;
	    $sBad = NULL;
	    while ($rsCC->NextRow()) {
		$qDone++;
		$sPlainCalc = $rsCC->SingleString();	// calculated
		$sPlainDecr = $rsCC->Decrypt(FALSE);	// decrypt encrypted; don't overwrite plaintext
		if ($sPlainCalc == $sPlainDecr) {
		    $qSame++;
		} else {
		    $sBad .= "\n * ".$rsCC->KeyValue().": PLAIN=[$sPlainCalc] DECRYPTED=[$sPlainDecr]";
		}
		echo "$qDone: $qSame match\r";
		$sEncrypted = $rsCC->Encrypted();	// save one row for end report
	    }
	    echo "\n$qSame card".Pluralize($qSame).' match';
	    $qBad = $qRows - $qSame;
	    if ($qBad) {
		$strStat = $qBad.' card'.Pluralize($qBad);
		echo "\n\nERROR - $strStat did NOT match!$sBad\n";
		LogEvent(__METHOD__,NULL,$strStat.' did not match',NULL,FALSE,FALSE);
		// TO DO: If this ever happens, give list of failed cards and some sort of way to figure out what went wrong
	    } else {
		if ($qRows == $qDone) {
		    $strStat = $qRows.' row'.Pluralize($qRows);
		    echo "\n\nOK - $strStat matched";
		    echo "\nSAMPLE -\n * PLAIN: [$sPlainCalc]\n * ENCRYPTED: [".Cipher_pubkey::Textify($sEncrypted)."]";
		    LogEvent(__METHOD__,NULL,$strStat.' matched',NULL,FALSE,FALSE);
		} else {
		    $strStat = $qRows.' row'.Pluralize($qRows).' detected, but only '.$qDone.' row'.Pluralize($qDone).' checked';
		    echo "\n\nERROR - $strStat!";
		    LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
		}
	    }
	} else {
	    echo 'No encrypted credit cards currently in database.';
	    LogEvent(__METHOD__,NULL,'no data to encrypt',NULL,FALSE,FALSE);
	}
    }
    public function DoAdminPlainClear() {
    // ACTION: Clear plaintext data for all rows that have encrypted data
	LogEvent(__METHOD__,NULL,'clearing unencrypted card data',NULL,FALSE,FALSE);

	$arUpd = array(
	  'CardNum' => 'NULL',
	  'CardExp' => 'NULL',
	  'CardCVV' => 'NULL'
	  );
	$this->Update($arUpd,'Encrypted IS NOT NULL');
	$intRows = $this->Engine()->RowsAffected();
	$strStat = $intRows.' row'.Pluralize($intRows).' modified';
	echo "\n\n OK: $strStat";

	LogEvent(__METHOD__,NULL,'plaintext data cleared from card records, '.$strStat,NULL,FALSE,FALSE);
    }
}

class clsCarts_maint extends clsShopCarts {
    public function DoAdminEncrypt() {
	LogEvent(__METHOD__,NULL,'encrypting sensitive data in cart records',NULL,FALSE,FALSE);

	$rsCC = $this->GetData('ID_Type=');
	if ($rsCC->hasRows()) {
    }
}

function GetPrivateKey() {
    echo "Enter private key for decryption. This will be multiple lines ending with a line containing '-END'.\n";
    $isDone = FALSE;
    $sKey = NULL;
    do {
	$sLine = trim(fgets(STDIN));
	$sKey .= $sLine;
	if (strpos($sLine,'-END') !== FALSE) {
	    $isDone = TRUE;
	}
	$sKey .= "\n";
    } while (!$isDone);

    // debugging only:
    //echo "\nRESULT:\n$sKey\n=====\n";
    $fh = fopen(KFP_KEYS.'/test.txt', 'w+');
    fwrite($fh,$sKey);
    fclose($fh);

    return $sKey;
}

function GenerateKeys() {
    global $oDB;

    echo "** KEY GENERATION **\n";
    // see http://us.php.net/manual/en/book.openssl.php
    // Create the keypair
    $res = openssl_pkey_new();

    // Get private key
    openssl_pkey_export($res, $privatekey);

    // Get public key
    $publickey = openssl_pkey_get_details($res);
    $publickey = $publickey["key"];

    // generate filename for storing public key:
    echo "_SERVER:\n".print_r($_SERVER,TRUE);

    $sUser = $_SERVER['LOGNAME'];
    //$sUser = MWX_User::Current()->ShortText();
    $fn = date('Y-m-d His').' '.$sUser.'.public.key';
    // Username is included so we know at a glance who generated the public key
      // and was therefore responsible for saving the private key.

    $fs = KFP_KEYS.'/'.$fn;
    // save the public key
    $nRes = file_put_contents($fs,$publickey);
    if ($nRes > 0) {
	$oDB->VarsGlobal()->Val('public_key.fspec',$fn);

	echo "PRIVATE KEY - save this in a secure location:\n$privatekey\n";
	echo "PUBLIC KEY - this has been saved in $fs:\n$publickey\n";
    } else {
	echo "ERROR: The generated public key could not be saved to the file $fs. Please check folder permissions.\n";
    }
    echo "** DONE. **\n";
}

// === MAIN ENTRY POINT === //

// get data objects

    $oDB = new clsVbzData(KS_DB_VBZCART);
    $oDB->Open();
    $tCC = $oDB->Make('clsCustCards_maint');	// charge cards
    $tSC = $oDB->Make('clsCarts_maint');	// shopping carts

// handle command-line input

foreach ($argv as $idx => $sArg) {
    if ($idx > 0) {
	$sArgTrunc = substr($sArg,0,4);
	switch ($sArgTrunc) {
	  case 'gene':	// generate new key pair
	    GenerateKeys();
	    break;
	  case 'encr':	// encrypt plaintext data
	    $tCC->DoAdminEncrypt();
	    break;
	  case 'decr':	// decrypt encrypted data and save
	    $sKey = GetPrivateKey();
	    $tCC->DoAdminDecrypt($sKey);
	    break;
	  case 'chec': // decrypt encrypted data and compare (don't save)
	    $sKey = GetPrivateKey();
	    $tCC->DoCheckDecrypt($sKey);
	    break;
	  case 'clea':	// clear plaintext data
	    $tCC->DoAdminPlainClear();
	    break;
	  default:
	    echo "Command '$sArgTrunc' not recognized.";
	}
    }
}
echo "\nProcessing complete.\n";