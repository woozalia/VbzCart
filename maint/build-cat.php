<?php
/*
 NAME: build-cat
 PURPOSE: maintenance script for building catalog from scources
 AUTHOR: Woozle Staddon
 VERSION:
  2010-06-27 Excerpting relevant code from SpecialVbzAdmin
  2010-11-10 "data_tables" has been renamed "cache_tables"
  2010-12-29 added ID_Supp to 1.3
  2011-01-02 added update of cat_items.Descr
  2016-01-31 Added EOF so this will work with the status updater.
*/
$fErrLevel = E_ALL | E_STRICT;
error_reporting($fErrLevel);
if (!ini_get('display_errors')) {
    ini_set('display_errors', 1);
}

define('EOF',"\x1A"); // should be 26 in hex = ^Z

//require_once '/var/www/vbz/local.php';	// basic library paths
require_once '/home/vbz/v-user.php';		// basic library paths
//require_once(KFP_LIB_VBZ.'/config-libs.php');
require_once(KFS_VBZCART_CONFIG);

fcCodeLibrary::Load_byName('vbzcart');
fcCodeLibrary::Load_byName('ferreteria.login');

$arSQL_CtgBuild = array(
/* STAGE 1: Clear/refill the two temporary tables from which cat_items is updated. */
  '1.1 clear update table 1 of 2'	=> 'DELETE FROM ctg_upd1;',
  '1.2 clear update table 2 of 2'	=> 'DELETE FROM ctg_upd2;',
  /* == Fill temp tables == */
    /* -- generated source data: */
  '1.3 fill update table 1/2 with source data'
   => <<<__END__
INSERT INTO ctg_upd1
SELECT DISTINCT
  CatSfx,
  isCloseOut,
  ID_CTG_Item,
  ID_Supplier AS ID_Supp,
  ID_Title,
  ID_ItTyp,
  ID_ItOpt,
  ID_ShipCost,
  PriceBuy,
  PriceSell,
  PriceList,
  ItOpt_Descr_part,
  NameSng,
  TitleName,
  GrpItmDescr,
  TitleGroupDescr,
  OptionDescr,
  ItOpt_Sort,
  GrpCode,
  GrpDescr,
  GrpSort,
  IDS_Item,
  CatNum,
  ItOpt_Descr
FROM qryCtg_src;
__END__
,
    /* -- existing catalog data indexed for JOINing: */
  '1.4 fill update table 2/2 with pre-join data'
    => 'INSERT INTO ctg_upd2 SELECT *, 0 AS cntDups
      FROM qryCtg_Items_forUpdJoin
      ON DUPLICATE KEY UPDATE cntDups=cntDups+1;',
/* STAGE 2: Calculate stock numbers and set isForSale flag */
   /* -- calculate stock quantities; set for-sale flag if in stock */
  '2.1 calculate stock quantities and status'
    => 'UPDATE ctg_upd2 AS i LEFT JOIN qryStk_items_remaining AS s ON i.ID_Item=s.ID_Item
      SET
	i.qtyInStock=s.QtyForSale,
	i.isForSale=(s.QtyForSale > 0);',
   /* -- also set for-sale flag if available from source */
  '2.2 also update status from catalog source'
    => 'UPDATE ctg_upd2 AS i LEFT JOIN ctg_upd1 AS u ON i.IDS_Item=u.IDS_Item
      SET i.isForSale=i.isForSale OR (u.IDS_Item IS NOT NULL);',
/* STAGE 3: Update cat_items */
   /* -- replace sourced items in cat_items from CTG data (except for fields saved in ctg_upd2) */
  '3.1 update existing item status with calculated items'
    => 'UPDATE cat_items AS i
      LEFT JOIN qryCtg_Upd_join AS iu
      ON i.ID=iu.ID
      SET
        i.cntCtgDup	= i.cntCtgDup+1,
        i.CatNum	= iu.CatNum,
        i.isForSale	= iu.isForSale,
        i.isMaster	= iu.isMaster,
        i.isInPrint	= iu.isInPrint,
        i.isCloseOut	= iu.isCloseOut,
        i.isCurrent	= TRUE,		/* all sourced items are current; others cleared in 3.3 */
        i.isPulled	= FALSE,	/* would this ever get set to anything else? */
        i.isDumped	= FALSE, 	/* same ^ */
        i.ID_ItTyp	= iu.ID_ItTyp,
        i.ID_ItOpt	= iu.ID_ItOpt,
	i.Descr		= iu.Descr,
        i.ItOpt_Descr	= iu.ItOpt_Descr,
        i.ItOpt_Sort	= iu.ItOpt_Sort,
        i.GrpCode	= iu.GrpCode,
	i.GrpDescr	= iu.GrpDescr,
	i.GrpSort	= iu.GrpSort,
	i.ID_ShipCost	= iu.ID_ShipCost,
	i.PriceBuy	= iu.PriceBuy,
	i.PriceSell	= iu.PriceSell,
	i.PriceList	= IFNULL(iu.PriceList,i.PriceList),
	i.QtyIn_Stk	= iu.QtyIn_Stk
      WHERE iu.ID IS NOT NULL;',
  /*-----
    STEP: 3.2
    HISTORY:
      2011-01-02 this seems to also update existing items -- can optimize later
      2011-02-24 qryCtg_Upd_join returns ALL active items, so I have changed the SQL here so that:
	1. It JOINs with cat_items and
	2. only updates rows not already in cat_items
	Note that 3.1 updates all *existing* rows in cat_items. MS Access would have added new rows,
	  but in MySQL you have to use REPLACE INTO for this. Hence the need for 3.2 in the first place.
  */
  '3.2 add any new calculated items'
    => 'REPLACE INTO cat_items
      SELECT j.*,NULL AS cntCtgDup,CONCAT("added by cat-build on ",NOW()) AS Notes
      FROM qryCtg_Upd_join AS j LEFT JOIN cat_items AS i ON j.ID=i.ID WHERE i.ID IS NULL;',
   /* -- clear availability flags in any unused items */
  '3.3 clear availability flags in unused items'
    => 'UPDATE cat_items AS i
      LEFT JOIN qryCtg_Upd_join AS u
	ON i.ID=u.ID
	SET i.isInPrint=NULL, i.isForSale=NULL, i.isCurrent=FALSE WHERE u.ID IS NULL;',
   /* -- set stock and for-sale fields for *all* joinable cat_items from calculations done in ctg_upd2 */
  '3.4 set stock status fields'
    => 'UPDATE cat_items AS i LEFT JOIN ctg_upd2 AS u ON i.ID=u.ID_Item
      SET
	i.isForSale=u.isForSale;',
   /* -- update inactive catalog numbers where title's catnum has changed */
// NOTE: (2011-01-02) this doesn't seem to actually affect i.Descr in any records; does it affect i.CatNum ever?
  '3.5 update changed inactive catalog numbers'
    => 'UPDATE cat_items AS i LEFT JOIN qryCat_Titles AS t ON i.ID_Title=t.ID
      SET
	i.CatNum	= CONCAT_WS("-",t.CatNum,i.CatSfx)
      WHERE (LEFT(i.CatNum,LENGTH(t.CatNum)) != t.CatNum) AND NOT isPulled;',
/* STAGE 4: Housekeeping - update timestamp of cat_items so dependent tables will be recalculated */
  '4. update cache timestamp'
    => 'UPDATE cache_tables
      SET WhenUpdated=NOW() WHERE Name="cat_items";'
  );

function VbzDb() {
  static $objDb;

    if (!isset($objDb)) {
	$oApp = new vcApp();
	//$objDb = new clsDatabase(KS_DB_VBZCART);
	$objDb = $oApp->Data();
	$objDb->Open();
    }
    return $objDb;
}

function Write($iText) {
    echo $iText;
}
function WriteLn($iText) {
    echo $iText."\n<br>";
}

function doCatBuild() {
    global $arSQL_CtgBuild;

    $objDB = VbzDb();

    $intLine = 0;
    foreach($arSQL_CtgBuild as $descr => $sql) {
	$intLine++;
	WriteLn($intLine.') '.$descr);
	$ok = $objDB->Exec($sql);
	if ($ok) {
	    $intRows = $objDB->RowsAffected();
	    $strStat = $intRows.' row'.fcString::Pluralize($intRows).' affected';
	    WriteLn(' - OK - '.$strStat);
	} else {
	    WriteLn(' - ERROR: '.$objDB->getError());
	    $objDB->ClearError();
	}
    }
}
WriteLn('Building catalog...');
doCatBuild();
WriteLn('End of build.');
Write(EOF);
