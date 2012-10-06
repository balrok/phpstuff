<?php
/**
 * based on: https://github.com/sebastianbergmann/php-code-coverage
 * I don't use it since it is slow, requires much memory and isn't serializable
 */
// uses: http://xdebug.org/docs/code_coverage
// TODO: http://www.reddit.com/r/PHP/comments/plufb/an_experimental_code_coverage_analysis_tool/


/**
 * used to create codecoverage from a web application.
 * it is meant to wrap around the code which should be covered.
 * How to use it:
 * $wrapper = new Wrapper();
 * $wrapper->start();
 * // some code
 * $wrappre->dir = 'path/to/coverage/dir';
 * $wrapper->stop();
 */
class Wrapper
{
	public $dir;

	function __construct()
	{
		if (!extension_loaded('xdebug')) {
			throw new Exception('Xdebug is not loaded.');
		}
		if (!ini_get('xdebug.coverage_enable')) {
			throw new Exception(
			  'You need to set xdebug.coverage_enable=On in your php.ini.'
			);
		}
	}

	public function start()
	{
		xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	}

	public function stop()
	{
        $coverage = \codespy\Analyzer::$outputformat = 'html';
		if (!$this->dir)
			throw new Exception('You need to specifiy a directory');

		$codeCoverage = xdebug_get_code_coverage();
		if (!is_dir($this->dir))
			mkdir($this->dir,0777);

		/* enhance the coverage with some metadata: */
		$data = array(
			'time'=>microtime(true),
			'coverage'=>$codeCoverage,
			'request'=>empty($_SERVER['REQUEST_URI'])?'-':$_SERVER['REQUEST_URI'],
			'post'=>!empty($_POST),
		);
		$uniq = microtime(true);
		$file = sprintf('%s%s.json', $this->dir, $uniq);
		file_put_contents($file, json_encode($data));
		chmod($file, 777);

		xdebug_stop_code_coverage();
	}
}


/**
 * Iterator which will read the directory where the wrapper saved all coverage files
 * after that it returns the decoded content of those files
 * it is ordered by the creation date
 */
class Reader implements Iterator
{
	/// filled on initialization: contains a sorted list of all files
	protected $files = array();
	/// directory where coverage files were stored
	protected $dir;

	/**
	 * $dir string directory where the coverage files were stored
	 */
	public function __construct($dir)
	{
        $this->dir = $dir;
		$handle = opendir($dir);
		while ($file = readdir($handle))
			if (substr($file, -5) == '.json')
				$this->files[] = $file;
		sort($this->files);
	}

	/**
	 * returns an array of all decoded files
	 */
	public function asArray()
	{
		$allData = array();
		foreach ($this as $data)
			$allData[] = $data;
		return $allData;
	}

    public function current() {
		$current = current($this->files);
        if ($current===false)
            return $current;
		return json_decode(file_get_contents($this->dir.$current), true);
    }

 	public function rewind() {
        reset($this->files);
    }

    public function key() {
        return key($this->files);
    }

    public function next() {
		return next($this->files);
    }

    public function valid() {
        return $this->current() !== false;
    }
}


/**
 * Will filter the coverage based on some rules
 * minTime the coverage must be minimum as old as this unix timestamp
 * maxTime the coverage must be maximum as old as this unix timestamp
 * excludeFileRegex a regex which matches coverage files which should be excluded
 */
class Filter {
	public $minTime;
	public $maxTime;
	public $excludeFileRegex;

	/**
	 * will filter and return array() or a filtered array
	 */
	public function filterData($data)
	{
		if ($this->minTime)
			if ($data['time'] < $this->minTime)
				return array();
		if ($this->maxTime)
			if ($data['time'] > $this->maxTime)
				return array();
		if ($this->excludeFileRegex)
		{
			foreach ($data['coverage'] as $file=>$lines)
			{
				if (preg_match($this->excludeFileRegex, $file))
                {
					unset($data['coverage'][$file]);
                }
			}
			if ($data['coverage'] == array())
				return array();
		}
		return $data;
	}
}

/**
 * will merge all coverages into one
 */
class Merger {
    public $data = array();
	// expects an array of coverage data
	public function mergeData($data)
	{
        foreach($data['coverage'] as $file=>$coverData)
        {
            if (!isset($this->data[$file]))
                $this->data[$file] = array();
            foreach ($coverData as $line=>$val)
                if ($val > 0)
                    $this->data[$file][$line] = $val;
        }
        return $this->data;
	}
}

class Converter {
    public function toVim($data)
    {
        foreach($data as $file=>$lines)
        {
            if ($file == '/mnt/6/ausgelagert/htdocs/yii/ysicat/protected/components/Controller.php')
            {
                diedump($lines);
                diedump(array_keys($lines));
            }
            // echo PHP_EOL.$file,' :  ',join(',',array_keys($lines));
            echo PHP_EOL.$file,PHP_EOL,'match cursorline /\%',join('l\|\%',array_keys($lines))."l/";
        }
    }

    public function toHtml($data)
    {
        $html = '';
        return $html;
    }
}
