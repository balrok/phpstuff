<?php
/**
 * PropertyCommand tries to add all properties from the database to your CActiveRecord classes
 * also it will add some @method and will add the relations as properties
 *
 * Assumptions which are made:
 * all use the same database
 * every class has a "function tableName" with a default return '{table}' or return '{{table}}' inside
 * the @property elements inside this defintion can all be deleted (protect yourself with appending (internal)
 * the @method   elements inside this defintion can all be deleted (protect yourself with appending (internal)
 */
class PropertyCommand extends CConsoleCommand
{
	public function getHelp()
	{
		return <<<EOD
USAGE
  yiic property <config-file>
EOD;
	}

	/**
	 * Execute the action.
	 * @param array command line parameters specific for this command
	 */
	public function run($args)
	{
		if(!isset($args[0]))
			$this->usageError('the configuration file is not specified.');
		if(!is_file($args[0]))
			$this->usageError("the configuration file {$args[0]} does not exist.");

		$config = require_once($args[0]);

		// setup database
		$connection = new CDbConnection($config['db_dsn'],$config['db_user'],$config['db_pass']);
		$connection->active=true;
		$connection->tablePrefix = $config['db_prefix'];
		$schema = $connection->getSchema();

		// iterate all files
		$files = CFileHelper::findFiles(realpath($config['sourcePath']), $config['pathOptions']);
		foreach($files as $file)
		{
			// only look at files inside /models/ directory
			if (strpos($file, '/models/') === false)
				continue;
			$className = str_replace('.php', '', basename($file));
			$content = file_get_contents($file);
			$tableName = $this->extractTableName($content, $config['db_prefix']);
			if (!$tableName)
			{
				echo $file.' couldn\'t find tableName'."\n";
				continue;
			}
			$table = $schema->getTable($tableName);
			$properties = $this->getProperties($table->columns);
			$relationProperties = $this->getRelationProperties($className, $content);

			$this->checkProperties($className, $properties, $relationProperties);

			$docstring = '';
			$docstring.= $this->getPropertiesDocstring($properties);
			$docstring.= $this->getPropertiesDocstring($relationProperties);
			$docstring.= $this->getMethodsDocstring($className);

			$content = $this->applyDocstring($className, $content, $docstring);

			file_put_contents($file, $content);
		}
	}

	// returns the tablename from a class
	protected function extractTableName($content)
	{
		// find the function for the tableName
		$pos = strpos($content, 'function tableName');
		// extract the function body
		$funcBody = substr($content, $pos, strpos($content, ';', $pos));
		if (preg_match('/(\'|")(\{{1,2}([^\}]+)\}{1,2})(\'|")/', $funcBody, $matches))
		{
			$tablename = $matches[2];
			return $tablename;
		}
		return '';
	}

	// maps all db columns to name=>type array
	protected function getProperties($columns)
	{
		$allMaps = array(
			'dbType'=>array(
				'tinyint(1)'=>'bool', // this guess could be wrong sometimes
			),
			'type'=>array(
				'integer'=>'int',
				'string'=>'string',
				'double'=>'float',
				'float'=>'float',
			),
		);
		$properties = array();
		foreach ($columns as $col)
		{
			$found = false;
			foreach ($allMaps as $type=>$map)
			{
				if (isset($map[$col->$type]))
				{
					$properties[$col->name] = $map[$col->$type];
					$found = true;
					break;
				}
			}
			if (!$found)
				die(print_r($col));
		}
		return $properties;
	}

	protected function getPropertiesDocstring($properties)
	{
		$docstring = '';
		foreach ($properties as $name=>$type)
			$docstring .= sprintf(' * @property %s %s'."\n", $type, $name);
		return $docstring;
	}

	// returns all relations which it could transform into a property
	protected function getRelationProperties($className, $content)
	{
		// find the function for relations
		$pos = strpos($content, 'function relations(');
		// extract the function body (it is expected that the function just contains the return array(.. ); in the default style
		// also each relation must be on one line
		$startArray = strpos($content, 'return', $pos);
		$endArray = strpos($content, ');', $startArray);
		if ($pos === false || $startArray === false || $endArray === false)
		{
			echo $className.": No relations found\n";
			return array();
		}

		$funcBody = substr($content, $startArray, $endArray);
		// only 'users' => array(self::MANY_MANY, 'User',
		// is matched
		if (preg_match_all('/(\'|")([^\'"]+)(\'|")\s*=>\s*array\(\s*self::([a-zA-Z_]+)\s*,\s*(\'|")([^\'"]+)(\'|")/', $funcBody, $matches))
		{
			$properties = array();
			// mapping maps to a type or "match" which says, it should take the object from the matches
			$mapping = array(
				'MANY_MANY'=>'array',
				'HAS_MANY'=>'array',
				'HAS_ONE'=>'match',
				'BELONGS_TO'=>'match',
				'STAT'=>'int', // TODO is that right?
			);
			foreach (array_keys($matches[0]) as $k)
			{
				$rel = $matches[4][$k];
				if (!isset($mapping[$rel]))
					die("mapping $rel doesnt exist");
				$type = $mapping[$rel];
				if ($type == 'match')
					$type = $matches[6][$k];
				$properties[$matches[2][$k]] = $type;
			}
			return $properties;
		}
		echo $className.": Strange relations Format or empty\n";
		return array();
	}

	// a small helper which looks if your relations overlap the db fields
	protected function checkProperties($className, $propA, $propB)
	{
		foreach ($propB as $name=>$type)
			if (isset($propA[$name]))
				echo $className.' has duplicate db and relations properties'."\n";
	}

	// apply a docstring - would remove if @method/@property exists before
	protected function applyDocstring($className, $content, $docstring)
	{
		// 1. search where class definition begins
		// 2. search above for a docstring
			// true) search for existing @property and delete them
			// false) create one
		// 3. insert properties

		if (!preg_match('/(class [^ ]+ extends )/m', $content, $matches))
			die("could not find a class definition for ".$className);

		$classString = $matches[1]; // contains something like "class User extends "
		$pos = strpos($content, $classString);
		// now look above if we have a docstring or comment
		$endComment = strrpos($content, '*/', (-1)*(strlen($content)-$pos));
		if ($endComment === false)
		{
			$content = substr_replace($content, "/**\n * Autogenerated for $className\n*/\n", $pos, 0);
		}
		else
		{
			// look if there are already @property or @method inside and remove them
			$startComment = strrpos($content, '/*', (-1)*(strlen($content)-$endComment));
			$commentBlock = substr($content, $startComment, $endComment);
			$commentBlock = $this->removePropertiesAndMethods($commentBlock);
			$content = substr_replace($content, $commentBlock, $startComment, $endComment);
		}
		// it is quite possible that the position has changed - so search again
		$pos = strpos($content, $classString);
		$endComment = strrpos($content, '*/', (-1)*(strlen($content)-$pos));

		if (substr($content, $endComment-1, 2) == ' *')
			$endComment--;
		// add docstring before endComment
		$content = substr_replace($content, $docstring, $endComment, 0);
		return $content;
	}

	// removes all @property and @method from a docstring
	protected function removePropertiesAndMethods($commentBlock)
	{
		if (preg_match_all('/\\n(\s*\*\s*@(property|method) [^ ]+\s(.*))/', $commentBlock, $matches))
		{
			$replaces = array();
			foreach ($matches[1] as $match)
			{
				// an identifier to make some properties immutable
				if (strpos($match, '(internal)') !== false)
					continue;
				// else remove the line
				$replaces[] = $match."\n";
			}
			$commentBlock = str_replace($replaces, '', $commentBlock);
		}
		return $commentBlock;
	}

	// returns some CActiveRecord methods I use often with correct class return
	protected function getMethodsDocstring($className)
	{
		$docstring = '';
		$docstring.= ' * @method '.$className.'|CActiveRecord find()'."\n";
		$docstring.= ' * @method '.$className.'|CActiveRecord findByPk()'."\n";
		$docstring.= ' * @method '.$className.'|CActiveRecord findByAttributes()'."\n";
		return $docstring;
	}
}
