<?php
/*
  PURPOSE: Handles queries that combine Topics and availability info
  HISTORY:
    2016-02-18 started - so we can return Topic lists separated by whether they have any available Titles
      This file was in vbzcart/cat at that time, and the classes had no actual code in them.
    2016-03-23 moved to dropins/cat-local and added methods to get titles-with-no-topics
*/

class vcqtTopicsInfo extends vctShopTopics {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcqrTopicInfo');
    }
    
    // -- SETUP -- //
    // ++ TABLES ++ //
    
/*    protected function TitleTable() {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES);
    }//*/
    protected function TitleInfoQuery() {
	return $this->Engine()->Make(KS_CLASS_JOIN_TITLES);
    }
    
    // -- TABLES -- //
    // ++ SQL ++ //
    
    /*----
      TODO:
	* This may need some fixing after stk_items->stk_lines rename.
	* draw pieces of this from other places in order to standardize; eventually build with SQO
    */
    public function SQL_Titles_active_noTopic() {
	return <<<__END__
SELECT 
    t.ID,
    t.Name,
    UPPER(CONCAT_WS('-', s.CatKey, d.CatKey, t.CatKey)) AS CatNum,
    LOWER(CONCAT_WS('/', s.CatKey, d.PageKey, t.CatKey)) AS CatPath,
    ti.QtyInStock,
    ti.CountForSale,
    ti.PriceMin,
    ti.PriceMax
FROM
    `cat_titles` AS t
        LEFT JOIN
    `cat_depts` AS d ON t.ID_Dept = d.ID
        JOIN
    `cat_supp` AS s ON t.ID_Supp = s.ID
        LEFT JOIN
    (SELECT 
        i.ID_Title,
            SUM(si.QtyInStock) AS QtyInStock,
            COUNT((si.QtyInStock > 0) OR isAvail) AS CountForSale,
            MIN(i.PriceSell) AS PriceMin,
            MAX(i.PriceSell) AS PriceMax
    FROM
        cat_items AS i
    LEFT JOIN cat_title_x_topic AS tt ON i.ID_Title = tt.ID_Title
    LEFT JOIN (

	SELECT 
	    sl.ID_Item, SUM(sl.Qty) AS QtyInStock
	FROM
	    `stk_lines` AS sl
	LEFT JOIN `stk_bins` AS sb ON sl.ID_Bin = sb.ID
	WHERE
	    (sl.Qty > 0)
		AND (sb.isForSale)
		AND (sb.isEnabled)
		AND (sb.WhenVoided IS NULL)
	GROUP BY ID_Item

    ) AS si ON si.ID_Item = i.ID
    WHERE
        tt.ID_Topic IS NULL
    GROUP BY i.ID_Title) AS ti ON ti.ID_Title = t.ID
WHERE
    (QtyInStock > 0) OR (CountForSale > 0)
ORDER BY CatNum
__END__;
    }

    // -- SQL -- //
    // ++ RECORDS ++ //
    
    public function TitleRecords_active_noTopic() {
	$sql = $this->SQL_Titles_active_noTopic();
	return $this->TitleInfoQuery()->DataSQL($sql);
    }
    
    // -- RECORDS -- //
}
class vcqrTopicInfo extends vcrShopTopic {
}