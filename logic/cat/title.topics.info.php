<?php
/*
  PURPOSE: Title-info function specific to single-Topic exhibit pages
  HISTORY:
    2018-02-17 extracted from vtTitles_status (now deleted) as part of tidying things up in order to fix the Department Titles exhibit
*/
class vcqtTitlesInfo_forTopics extends vcqtTitlesInfo {

    /*----
      RETURNS: SQO for Titles with at least one active Item
	SELECT OUTPUT: title ID, quantity active
    */
    protected function SQO_active() {
	$qo = $this->ItemInfoQuery()->SQO_forSale();
	$qo->Select()->Fields(new fcSQL_Fields(array(
	      't.ID',
	      'QtyForSale' => 'SUM(sl.Qty)'	// alias => source
	      )
	    )
	  );
	$qo->Terms()->UseTerm(new fcSQLt_Group(array('i.ID_Title')));
	$qst = new fcSQL_TableSource($this->TableName(),'t');
	$qo->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($qst,'i.ID_Title=t.ID'),
	    )
	  );
	return $qo;
    }
    public function SQO_active_byTopic() {
	$sqoTi = $this->SQO_active();
	$sqosTT = new fcSQL_TableSource('cat_title_x_topic','tt');
	$sqoTo = new fcSQL_Query(		// query
	  new fcSQL_Select(				// +select
	    new fcSQL_JoinSource(array(				// +source
		new fcSQL_JoinElement(new fcSQL_SubQuerySource($sqoTi,'ti')),	// sub-source: SQO_active
		new fcSQL_JoinElement($sqosTT,'ti.ID = tt.ID_Title')		// sub-source: titles_x_topics
	      )),						// -source
	    new fcSQL_Fields(array(				// +fields, +array
		'tt.ID_Topic',
		'QtyForSale'	=> 'SUM(ti.QtyForSale)',
		'QtyTitles'	=> 'COUNT(ti.ID)'
	      ))						// -array, -fields
	    ),							// -select
	    new fcSQL_Terms(array(				// +terms
		new fcSQLt_Group(array('tt.ID_Topic'))
	      ))						// -terms
	    );						// -query
	return $sqoTo;
    }

}