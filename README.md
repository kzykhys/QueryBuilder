QueryBuilder - PDO based MySQL query builder
============================================

Getting started
---------------

	Query::connect(host, user, password, database);
	$result = Query::select()
	  ->from('table t')
	  ->where('id = ?', $id)
	  ->orderBy('updated desc')
	  ->limit(20)
	  ->fetchAll();
	