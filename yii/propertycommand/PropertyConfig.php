<?php

return array(
	// the path from where the script should search everything
	// following command would search inside protected/
	'sourcePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	// some options like exclude you might want to specify
	'pathOptions'=>array(),

	// your db connection
	'db_dsn'=>'mysql:host=localhost;dbname=your_db_name',
	'db_user'=>'root',
	'db_pass'=>'',
	'db_prefix'=>'your_prefix_if_exists_',
);
