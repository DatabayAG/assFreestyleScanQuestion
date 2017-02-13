<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/TestQuestionPool/classes/class.ilQuestionsPlugin.php';

/**
 * Class ilassFreestyleScanQuestionPlugin
 */
class ilassFreestyleScanQuestionPlugin extends ilQuestionsPlugin
{
	private static $PLUGIN_NAME = 'assFreestyleScanQuestion';
	private static $PLUGIN_ID = 'fssqst';

	/**
	 * Static function for returning the QuestionTypeName
	 *
	 * @static
	 * @return string
	 */
	public static function getName()
	{
		return self::$PLUGIN_NAME;
	}

	/**
	 * Get the ID for the Plugin
	 *
	 * @static
	 * @return string
	 */
	public static function getPluginId()
	{
		return self::$PLUGIN_ID;
	}

	/**
	 * Get the directory location of the plugin the the ilias structur
	 *
	 * @static
	 * @return string
	 */
	final static function getLocation()
	{
		return './Customizing/global/plugins/Modules/TestQuestionPool/Questions/assFreestyleScanQuestion/';
	}

	/**
	 * Get the pluginname
	 *
	 * @return string
	 */
	final function getPluginName()
	{
		return self::$PLUGIN_NAME;
	}

	/**
	 * Get the question type
	 *
	 * @return string
	 */
	final function getQuestionType()
	{
		return self::$PLUGIN_NAME;
	}

	/**
	 * Get the translated name of the question type
	 *
	 * @return string
	 */
	final function getQuestionTypeTranslation()
	{
		return $this->txt(self::$PLUGIN_NAME);
	}
}
