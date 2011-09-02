QueryBuilder - PDO based MySQL query builder
============================================

Requirements
------------

* PHP 5.0+
* PDO_MYSQL

Getting started
---------------

Connect to MySQL

	Query::connect(host, user, password, database);

Executes SELECT statement

	$result = Query::select()
	 ->from('table t')
	  ->where('id = ?', $id)
	   ->orderBy('updated desc')
	    ->limit(20)
	     ->fetchAll();
	//returns array

Executes INSERT statement

	Query::insert()
	 ->into('table')
	  ->columns(array('id', 'title', 'desc'))
	   ->values(array(1, 'foo', 'bar'))
	    ->execute();
	//returns boolean

Executes UPDATE statement

	Query::update()
	 ->table('table')
	  ->columns(array('id', 'title', 'desc'))
	   ->values(array(1, 'foo', 'bar'))
	    ->where('id = ?', $id)
	     ->execute();
	//returns boolean

Executes DELETE statement

	Query::delete()
	 ->table('table')
	  ->where('id = ?', $id)
	   ->andWhere('status = ?', 'trash')
	    ->execute();
	//returns boolean

Executes SELECT statement for pagination

	Query::select()
	 ->calcFoundRows()
	  ->from('posts p')
	   ->columns('*, c.name')
	    ->leftJoin('category c on p.category_id = c.id')
         ->where('p.pubdate < now()')
	      ->page($page, $limit)
	       ->fetchAll();
	//returns array

Transactions

	Query::begin();

	Query::insert()
	 ->into('table')
	  ->columns(array_keys($data))
	   ->values(array_values($data))
	    ->execute();

	Query::commit();
	//or
	Query::rollback();

Count rows

	Query::select()
	 ->from('table')
	  ->count();
	//return integer

Execute SQL manually

	Query::sql('desc mydb.posts');
