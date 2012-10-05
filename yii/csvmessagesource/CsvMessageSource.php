<?php

/**
 * @property string onMissingTranslation
 */
class CsvMessageSource extends CPhpMessageSource
{
	const CACHE_KEY_PREFIX='CsvMessageSource.';

	// if you specify a default translation, this file will alway be merged first
	// for example when multiple categories require the translation for "yes" and "no" this is handy
	public $defaultTranslation = '';
	public $messageDir = ''; // instead of "message" you might use "translation" in contrast to baseDir this is also valid for modules
	/* when you have the language de and at you can share most of the strings
	 * this sharing can be done with this array
	 * just assign an array in which order the languages should be loaded (e.g. 'at'=>array(de, at))
	 */
	public $languages = array();
	public $delimiter = ',';

	protected function getMessageFile($category, $language)
	{
		$messageFile = parent::getMessageFile($category,$language);
		$messageFile = str_replace('.php', '.csv', $messageFile);
		if ($this->messageDir)
			$messageFile = str_replace('messages', $this->messageDir, $messageFile);
		if(!is_file($messageFile))
		{
			Yii::log('Couldn\'t find translation file '.$messageFile, 'error', __CLASS__);
			return false;
		}
		return $messageFile;
	}

	/**
	 * Loads the message translation for the specified language and category.
	 * @param string $message category
	 * @param string $target language
	 * @return array loaded messages
	 */
	protected function loadMessages($category, $language)
	{
		$return = array();
		$baseLanguage = $language;

		// this loop may first look into de and then de_de
		foreach ($this->getAllLanguages($baseLanguage) as $language)
		{
			$messageFile = $this->getMessageFile($category,$language);

			if(!$messageFile)
				continue;

			if($this->cachingDuration>0 && $this->cacheID!==false && ($cache=Yii::app()->getComponent($this->cacheID))!==null)
			{
				$key=self::CACHE_KEY_PREFIX . $messageFile;
				if(($data=$cache->get($key))!==false)
				{
					$return = CMap::mergeArray($return, unserialize($data));
					continue;
				}
			}

			$messages=$this->getFileContent($messageFile);
			if(!is_array($messages))
				$messages=array();
			if(isset($cache))
			{
				$dependency=new CFileCacheDependency($messageFile);
				// TODO $key might not be defined
				$cache->set($key,serialize($messages),$this->cachingDuration,$dependency);
			}
			$return = CMap::mergeArray($return, $messages);
		}
		if ($this->defaultTranslation)
		{
			foreach ($this->getAllLanguages($baseLanguage) as $language)
			{
				if ($messageFile = $this->getMessageFile($this->defaultTranslation, $language))
				{
					$global = $this->getFileContent($messageFile);
					$return = CMap::mergeArray($global, $return);
				}
			}
		}
		return $return;
	}

	protected function getFileContent($file)
	{
		$f = fopen($file, "r");
		$messages = array();
		while ($d = fgetcsv($f, 1024, $this->delimiter))
			if (isset($d[1]))
				$messages[$d[0]] = $d[1];
		return $messages;
	}

	protected function getAllLanguages($language)
	{
		if (isset($this->languages[$language]))
			return $this->languages[$language];
		return array($language);
	}
}
