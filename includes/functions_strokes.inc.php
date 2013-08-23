<?php


function bo_strikes_delete_duplicate()
{
	return BoDb::query("DELETE a.* FROM ".BO_DB_PREF."strikes a LEFT JOIN ".BO_DB_PREF."strikes b ON a.time=b.time AND a.time_ns=b.time_ns WHERE a.id<b.id");
}


?>