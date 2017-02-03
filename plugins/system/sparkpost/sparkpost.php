<?php
/**
 * @package    Sparkpost
 *
 * @author     Perfect Web Team <hallo@perfectwebteam.nl>
 * @copyright  Copyright (C) 2017 Perfect Web Team. All rights reserved.
 * @license    GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://perfectwebteam.nl
 */

defined('_JEXEC') or die;

/**
 * SparkPost plugin
 *
 * @since  1.0
 */
class PlgSystemSparkpost extends JPlugin
{
	/**
	 * Here we will override the JMail class.
	 *
	 * @return bool  Always returns true.
	 *
	 * @since  1.0
	 */
	public function onAfterInitialise()
	{
		$this->loadLanguage('plg_system_sparkpost.sys');

		$path = JPATH_ROOT . '/plugins/system/sparkpost/mailer/mail.php';

		JLoader::register('JMail', $path);
		JLoader::load('JMail');

		return true;
	}
}
