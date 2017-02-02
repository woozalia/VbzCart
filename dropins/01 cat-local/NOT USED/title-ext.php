<?php
/*
  PURPOSE: more complex Title functions not often used
  HISTORY:
    2015-09-07 started, for fixing Topic Tree building process
    2016-03-05 It looks like some of this may still be in use, but it needs to be rewritten/tidied --
      so moving it into NOT USED and rewriting as needed.
*/

class vcSQL_Stock {

    static protected function LinesRemaining() {
	return <<<__END__
	  SELECT
	      `st`.`ID` AS `ID`,
	      `st`.`ID_Bin` AS `ID_Bin`,
	      `st`.`ID_Item` AS `ID_Item`,
	      IF(`sb`.`isForSale`, `st`.`Qty`, 0) AS `QtyForSale`,
	      IF(`sb`.`isForShip`, `st`.`Qty`, 0) AS `QtyForShip`,
	      `st`.`Qty` AS `QtyExisting`,
	      `st`.`CatNum` AS `CatNum`,
	      `st`.`WhenAdded` AS `WhenAdded`,
	      `st`.`WhenChanged` AS `WhenChanged`,
	      `st`.`WhenCounted` AS `WhenCounted`,
	      `st`.`Notes` AS `Notes`,
	      `sb`.`Code` AS `BinCode`,
	      `sb`.`ID_Place` AS `ID_Place`,
	      `sp`.`Name` AS `WhName`
	  FROM
	      ((`stk_items` `st`
	      LEFT JOIN `stk_bins` `sb` ON ((`sb`.`ID` = `st`.`ID_Bin`)))
	      LEFT JOIN `stk_places` `sp` ON ((`sb`.`ID_Place` = `sp`.`ID`)))
	  WHERE
	      (ISNULL(`st`.`WhenRemoved`)
		  AND ISNULL(`sb`.`WhenVoided`)
		  AND (`st`.`Qty` > 0))
__END__;
    }
    // SQL FOR: one record for each Item still remaining in stock
    static public function ItemStock() {
	$sqlLinesRemaining = self::LinesRemaining();
 	return <<<__END__
	  SELECT
	      `ID_Item`,
	      SUM(`QtyForSale`) AS `QtyForSale`,
	      SUM(`QtyForShip`) AS `QtyForShip`,
	      SUM(`QtyExisting`) AS `QtyExisting`
	  FROM
	      ($sqlLinesRemaining) AS ir
	  GROUP BY ir.`ID_Item`
__END__;
    }
    // SQL FOR: one record for each Title still remaining in stock
    static public function TitleStock() {
	$sqlItemStock = self::ItemStock();
 	return <<<__END__
	  SELECT
	    i.`ID_Title`,
	    SUM(tr.`QtyForSale`) AS `QtyForSale`,
	    SUM(tr.`QtyForShip`) AS `QtyForShip`,
	    SUM(tr.`QtyExisting`) AS `QtyExisting`
	  FROM
	      ($sqlItemStock) AS tr
	      LEFT JOIN cat_items AS i
		ON tr.ID_Item = i.ID
	  GROUP BY i.`ID_Title`
__END__;

    }
    // SQL FOR: one record for each Title currently not assigned to any Topic
    // NOTE: Filtering for QtyForSale>0 seems to be ignored by outer query, so I have removed it.
    static public function Titles_noTopic() {
	$sqlTitleStock = self::TitleStock();
 	return <<<__END__
	  SELECT tc.*
	    FROM (($sqlTitleStock) AS tc
	      LEFT JOIN cat_title_x_topic AS tt
		ON tc.ID_Title=tt.ID_Title)
	    WHERE (tt.ID_Topic IS NULL)
__END__;
    }
}

class vcSQL_Catalog {
    static public function Depts() {
	return <<<__END__
	  SELECT
	      `d`.`ID` AS `ID`,
	      `d`.`Name` AS `Name`,
	      `d`.`Sort` AS `Sort`,
	      `d`.`CatKey` AS `CatKey`,
	      UCASE(IFNULL(`d`.`PageKey`, `d`.`CatKey`)) AS `CatKey_def`,
	      `d`.`isActive` AS `isActive`,
	      `d`.`ID_Supplier` AS `ID_Supplier`,
	      UCASE(CONCAT_WS('-', `s`.`CatKey`, `d`.`CatKey`)) AS `CatNum`,
	      LCASE(CONCAT_WS('/',`s`.`CatKey`,IFNULL(`d`.`PageKey`, `d`.`CatKey`))) AS `CatWeb_Dept`,
	      LCASE(CONCAT_WS('/', `s`.`CatKey`, `d`.`CatKey`)) AS `CatWeb_Title`
	    FROM (`cat_depts` `d`
	      LEFT JOIN `cat_supp` `s`
		ON ((`d`.`ID_Supplier` = `s`.`ID`)))
__END__;
    }
    static public function Titles() {
	$sqlDepts = self::Depts();
	return <<<__END__
	  SELECT
	      `t`.`ID` AS `ID`,
	      `t`.`Name` AS `Name`,
	      UCASE(CONCAT_WS('-', `d`.`CatNum`, `t`.`CatKey`)) AS `CatNum`,
	      LCASE(CONCAT_WS('/', `d`.`CatWeb_Title`, `t`.`CatKey`)) AS `CatWeb`
	    FROM (`cat_titles` `t`
	      LEFT JOIN ($sqlDepts) `d`
		ON ((`t`.`ID_Dept` = `d`.`ID`)))
__END__;
/* more fields -- use only as needed (maybe we need a feature to allow the caller to request fields):
	return <<<__END__
	  SELECT
	      `t`.`ID` AS `ID`,
	      `t`.`Name` AS `Name`,
	      `t`.`Desc` AS `Descr`,
	      `t`.`Search` AS `Search`,
	      UCASE(CONCAT_WS('-', `d`.`CatNum`, `t`.`CatKey`)) AS `CatNum`,
	      LCASE(CONCAT_WS('/', `d`.`CatWeb_Title`, `t`.`CatKey`)) AS `CatWeb`,
	      `t`.`CatKey` AS `CatKey`,
	      `d`.`ID_Supplier` AS `ID_Supplier`,
	      `t`.`ID_Dept` AS `ID_Dept`,
	      `t`.`DateAdded` AS `DateAdded`,
	      `t`.`RstkMin` AS `QtyMin_Rstk`,
	      `t`.`Notes` AS `Notes`,
	      `t`.`Supplier_CatNum` AS `Supp_CatNum`
	    FROM (`cat_titles` `t`
	      LEFT JOIN ($sqlDepts) `d`
		ON ((`t`.`ID_Dept` = `d`.`ID`)))
__END__;
*/
    }
    static public function NoTopic_moreInfo() {
	$sqlNoTopic = vcSQL_Stock::Titles_noTopic();
	$sqlBase = self::Titles();
	return <<<__END__
	  SELECT
	      tb.ID,
	      tb.Name,
	      tb.CatNum,
	      tb.CatWeb,
	      ts.QtyForSale
	    FROM ($sqlBase) AS tb
	      LEFT JOIN ($sqlNoTopic) AS ts
		ON tb.ID=ts.ID_Title
	    WHERE ts.QtyForSale > 0
__END__;
    }
}

/*%%%%
  PURPOSE: handles data from above SQL classes
*/
class vctStockInfo extends clsTable {
    use ftLinkableTable;

    // ++ SETUP ++ //

    protected function InitVars() {
	parent::InitVars();
	  $this->KeyName('ID');
	  $this->ClassSng('vcrStockInfo');
	  $this->ActionKey(KS_ACTION_CATALOG_TITLE);
    }

    // -- SETUP -- //
    // ++ DATA RECORDSETS ++ //

    // RETURN: recordset of Titles not currently assigned to any Topic, with additional Title information
    public function Titles_noTopic() {
	$sql = vcSQL_Catalog::NoTopic_moreInfo();
	$rs = $this->DataSQL($sql);
	return $rs;
    }

    // ++ DATA RECORDSETS ++ //
}

class vcrStockInfo extends clsDataSet {
    use ftLinkableRecord;

    // ++ DATA FIELD ACCESS ++ //

    public function NameString() {
	return $this->Value('Name');
    }
    public function CatNum() {
	return $this->Value('CatNum');
    }
}