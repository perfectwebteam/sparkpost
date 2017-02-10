<?php
/**
 * @package    Sparkpost
 *
 * Based on the idea of CMandrill by Daniel Dimitrov of Compojoom.com
 *
 * @author     Perfect Web Team <hallo@perfectwebteam.nl>
 * @copyright  Copyright (C) 2017 Perfect Web Team. All rights reserved.
 * @license    GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 * @link       https://perfectwebteam.nl
 */

/**
 * @version        $Id: mail.php 14401 2010-01-26 14:10:00Z louis $
 * @package        Joomla.Framework
 * @subpackage     Mail
 * @copyright      Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
 * @license        GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('phpmailer.phpmailer');
jimport('joomla.mail.helper');

use Joomla\Registry\Registry;
use SparkPost\SparkPost;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;

/**
 * Email Class.  Provides a common interface to send email from the Joomla! Platform
 *
 * @package     Joomla.Platform
 * @subpackage  Mail
 * @since       11.1
 */
class JMail extends PHPMailer
{
	/**
	 * @var    array  JMail instances container.
	 * @since  11.3
	 */
	protected static $instances = array();

	/**
	 * @var    string  Charset of the message.
	 * @since  11.1
	 */
	public $CharSet = 'utf-8';

	/**
	 * Sparkpost adapter.
	 *
	 * @var    SparkPost
	 * @since  1.0
	 */
	private $sparky;

	/**
	 * Constructor
	 */
	/**
	 * Class constructor.
	 *
	 * @since  1.0
	 */
	public function __construct()
	{
		// Phpmailer has an issue using the relative path for it's language files
		$this->SetLanguage('joomla', JPATH_PLATFORM . '/joomla/mail/language/');

		// Load the plugin settings
		$plugin = JPluginHelper::getPlugin('system', 'sparkpost');
		$pluginParams = new Registry($plugin->params);

		require JPATH_SITE . '/plugins/system/sparkpost/library/vendor/autoload.php';

		$httpClient   = new GuzzleAdapter(new Client());
		$this->sparky = new SparkPost($httpClient, ['key' => $pluginParams->get('apiKey')]);

		// Initialize the logger class
		jimport('joomla.error.log');
		$date = JFactory::getDate()->format('Y_m');

		// Add the logger.
		JLog::addLogger(
			array(
				'text_file' => 'plg_system_sparkpost.log.' . $date . '.php'
			),
			JLog::ALL,
			array('sparkpost')
		);
	}

	/**
	 * Returns the global email object, only creating it
	 * if it doesn't already exist.
	 *
	 * NOTE: If you need an instance to use that does not have the global configuration
	 * values, use an id string that is not 'Joomla'.
	 *
	 * @param   string  $id  The id string for the JMail instance [optional]
	 *
	 * @return  JMail  The global JMail object
	 *
	 * @since   11.1
	 */
	public static function getInstance($id = 'Joomla')
	{
		if (empty(self::$instances[$id]))
		{
			self::$instances[$id] = new JMail;
		}

		return self::$instances[$id];
	}

	/**
	 * Sends the email -> either trough PHPMailer or through Sparkpost
	 *
	 * @return mixed True if successful, a JError object otherwise
	 */
	/**
	 * Sends the email -> either trough PHPMailer or through Sparkpost.
	 *
	 * @return  boolean  True if successful | False otherwise.
	 *
	 * @since   1.0
	 */
	public function Send()
	{
		try
		{
			return $this->sparkPostSend();
		}
		catch (Exception $e)
		{
			return $this->phpMailerSend();
		}

	}

	/**
	 * Send the mail with phpMailer
	 *
	 * @return  mixed  True if successful; JError if using legacy tree (no exception thrown in that case).
	 *
	 * @since   11.1
	 * @throws  RuntimeException
	 */
	public function phpMailerSend()
	{
		if (JFactory::getConfig()->get('mailonline', 1))
		{
			if (($this->Mailer == 'mail') && !function_exists('mail'))
			{
				throw new RuntimeException(sprintf('%s::Send mail not enabled.', get_class($this)));
			}

			$result = parent::send();

			if ($result == false)
			{
				throw new RuntimeException(sprintf('%s::Send failed: "%s".', get_class($this), $this->ErrorInfo));
			}

			return $result;
		}
		else
		{
			JFactory::getApplication()->enqueueMessage(JText::_('JLIB_MAIL_FUNCTION_OFFLINE'));

			return false;
		}
	}

	/**
	 * Set the email sender
	 *
	 * @param   array  $from  email address and Name of sender
	 *                        <code>array([0] => email Address [1] => Name)</code>
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function setSender($from)
	{
		if (is_array($from))
		{
			// If $from is an array we assume it has an address and a name
			if (isset($from[2]))
			{
				// If it is an array with entries, use them
				$this->setFrom(JMailHelper::cleanLine($from[0]), JMailHelper::cleanLine($from[1]), (bool) $from[2]);
			}
			else
			{
				$this->setFrom(JMailHelper::cleanLine($from[0]), JMailHelper::cleanLine($from[1]));
			}
		}
		elseif (is_string($from))
		{
			// If it is a string we assume it is just the address
			$this->setFrom(JMailHelper::cleanLine($from));
		}
		else
		{
			// If it is neither, we log a message and throw an exception
			$this->logMessage(JText::sprintf('JLIB_MAIL_INVALID_EMAIL_SENDER', $from), JLog::WARNING);

			throw new UnexpectedValueException(sprintf('Invalid email Sender: %s, JMail::setSender(%s)', $from));
		}

		return $this;
	}

	/**
	 * Set the email subject
	 *
	 * @param   string  $subject  Subject of the email
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function setSubject($subject)
	{
		$this->Subject = JMailHelper::cleanLine($subject);

		return $this;
	}

	/**
	 * Set the email body
	 *
	 * @param   string  $content  Body of the email
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function setBody($content)
	{
		/*
		 * Filter the Body
		 * TODO: Check for XSS
		 */
		$this->Body = JMailHelper::cleanText($content);

		return $this;
	}

	/**
	 * Add recipients to the email.
	 *
	 * @param   mixed   $recipient  Either a string or array of strings [email address(es)]
	 * @param   mixed   $name       Either a string or array of strings [name(s)]
	 * @param   string  $method     The parent method's name.
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 * @throws  InvalidArgumentException
	 */
	protected function add($recipient, $name = '', $method = 'addAddress')
	{
		$method = lcfirst($method);

		// If the recipient is an array, add each recipient... otherwise just add the one
		if (is_array($recipient))
		{
			if (is_array($name))
			{
				$combined = array_combine($recipient, $name);

				if ($combined === false)
				{
					throw new InvalidArgumentException("The number of elements for each array isn't equal.");
				}

				foreach ($combined as $recipientEmail => $recipientName)
				{
					$recipientEmail = JMailHelper::cleanLine($recipientEmail);
					$recipientName = JMailHelper::cleanLine($recipientName);
					call_user_func('parent::' . $method, $recipientEmail, $recipientName);
				}
			}
			else
			{
				$name = JMailHelper::cleanLine($name);

				foreach ($recipient as $to)
				{
					$to = JMailHelper::cleanLine($to);
					call_user_func('parent::' . $method, $to, $name);
				}
			}
		}
		else
		{
			$recipient = JMailHelper::cleanLine($recipient);
			call_user_func('parent::' . $method, $recipient, $name);
		}

		return $this;
	}

	/**
	 * Add recipients to the email
	 *
	 * @param   mixed  $recipient  Either a string or array of strings [email address(es)]
	 * @param   mixed  $name       Either a string or array of strings [name(s)]
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addRecipient($recipient, $name = '')
	{
		$this->add($recipient, $name, 'addAddress');

		return $this;
	}

	/**
	 * Add carbon copy recipients to the email
	 *
	 * @param   mixed  $cc    Either a string or array of strings [email address(es)]
	 * @param   mixed  $name  Either a string or array of strings [name(s)]
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addCC($cc, $name = '')
	{
		// If the carbon copy recipient is an array, add each recipient... otherwise just add the one
		if (isset($cc))
		{
			$this->add($cc, $name, 'addCC');
		}

		return $this;
	}

	/**
	 * Add blind carbon copy recipients to the email
	 *
	 * @param   mixed  $bcc   Either a string or array of strings [email address(es)]
	 * @param   mixed  $name  Either a string or array of strings [name(s)]
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addBCC($bcc, $name = '')
	{
		// If the blind carbon copy recipient is an array, add each recipient... otherwise just add the one
		if (isset($bcc))
		{
			$this->add($bcc, $name, 'addBCC');
		}

		return $this;
	}

	/**
	 * Add file attachment to the email
	 *
	 * @param   mixed   $path         Either a string or array of strings [filenames]
	 * @param   mixed   $name         Either a string or array of strings [names]
	 * @param   mixed   $encoding     The encoding of the attachment
	 * @param   mixed   $type         The mime type
	 * @param   string  $disposition  The disposition of the attachment
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   12.2
	 * @throws  InvalidArgumentException
	 */
	public function addAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream', $disposition = 'attachment')
	{
		// If the file attachments is an array, add each file... otherwise just add the one
		if (isset($path))
		{
			if (is_array($path))
			{
				if (!empty($name) && count($path) != count($name))
				{
					throw new InvalidArgumentException("The number of attachments must be equal with the number of name");
				}

				foreach ($path as $key => $file)
				{
					if (!empty($name))
					{
						parent::addAttachment($file, $name[$key], $encoding, $type);
					}
					else
					{
						parent::addAttachment($file, $name, $encoding, $type);
					}
				}
			}
			else
			{
				parent::addAttachment($path, $name, $encoding, $type);
			}
		}

		return $this;
	}

	/**
	 * Add Reply to email address(es) to the email
	 *
	 * @param   array         $replyto  Either an array or multi-array of form
	 *                                  <code>array([0] => email Address [1] => Name)</code>
	 * @param   array|string  $name     Either an array or single string
	 *
	 * @return  JMail  Returns this object for chaining.
	 *
	 * @since   11.1
	 */
	public function addReplyTo($replyto, $name = '')
	{
		$this->add($replyto, $name, 'addReplyTo');

		return $this;
	}

	/**
	 * Use sendmail for sending the email
	 *
	 * @param   string  $sendmail  Path to sendmail [optional]
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function useSendmail($sendmail = null)
	{
		$this->Sendmail = $sendmail;

		if (!empty($this->Sendmail))
		{
			$this->IsSendmail();

			return true;
		}
		else
		{
			$this->IsMail();

			return false;
		}
	}

	/**
	 * Use SMTP for sending the email
	 *
	 * @param   string   $auth    SMTP Authentication [optional]
	 * @param   string   $host    SMTP Host [optional]
	 * @param   string   $user    SMTP Username [optional]
	 * @param   string   $pass    SMTP Password [optional]
	 * @param   string   $secure  Use secure methods
	 * @param   integer  $port    The SMTP port
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function useSMTP($auth = null, $host = null, $user = null, $pass = null, $secure = null, $port = 25)
	{
		$this->SMTPAuth = $auth;
		$this->Host = $host;
		$this->Username = $user;
		$this->Password = $pass;
		$this->Port = $port;

		if ($secure == 'ssl' || $secure == 'tls')
		{
			$this->SMTPSecure = $secure;
		}

		if (($this->SMTPAuth !== null && $this->Host !== null && $this->Username !== null && $this->Password !== null)
			|| ($this->SMTPAuth === null && $this->Host !== null))
		{
			$this->IsSMTP();

			return true;
		}
		else
		{
			$this->IsMail();

			return false;
		}
	}

	/**
	 * Function to send an email
	 *
	 * @param   string    $from         From email address
	 * @param   string    $fromName     From name
	 * @param   mixed     $recipient    Recipient email address(es)
	 * @param   string    $subject      email subject
	 * @param   string    $body         Message body
	 * @param   bool|int  $mode         false = plain text, true = HTML
	 * @param   mixed     $cc           CC email address(es)
	 * @param   mixed     $bcc          BCC email address(es)
	 * @param   mixed     $attachment   Attachment file name(s)
	 * @param   mixed     $replyTo      Reply to email address(es)
	 * @param   mixed     $replyToName  Reply to name(s)
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function sendMail($from, $fromName, $recipient, $subject, $body, $mode = false, $cc = null, $bcc = null, $attachment = null,
	                         $replyTo = null, $replyToName = null)
	{
		$this->setSubject($subject);
		$this->setBody($body);

		// Are we sending the email as HTML?
		if ($mode)
		{
			$this->IsHTML(true);
		}

		$this->addRecipient($recipient);
		$this->addCC($cc);
		$this->addBCC($bcc);
		$this->addAttachment($attachment);

		// Take care of reply email addresses
		if (is_array($replyTo))
		{
			$numReplyTo = count($replyTo);

			for ($i = 0; $i < $numReplyTo; $i++)
			{
				$this->addReplyTo($replyTo[$i], $replyToName[$i]);
			}
		}
		elseif (isset($replyTo))
		{
			$this->addReplyTo($replyTo, $replyToName);
		}

		// Add sender to replyTo only if no replyTo received
		$autoReplyTo = (empty($this->ReplyTo)) ? true : false;
		$this->setSender(array($from, $fromName, $autoReplyTo));

		return $this->Send();
	}

	/**
	 * Sends mail to administrator for approval of a user submission
	 *
	 * @param   string  $adminName   Name of administrator
	 * @param   string  $adminEmail  Email address of administrator
	 * @param   string  $email       [NOT USED TODO: Deprecate?]
	 * @param   string  $type        Type of item to approve
	 * @param   string  $title       Title of item to approve
	 * @param   string  $author      Author of item to approve
	 * @param   string  $url         A URL to included in the mail
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public function sendAdminMail($adminName, $adminEmail, $email, $type, $title, $author, $url = null)
	{
		$subject = JText::sprintf('JLIB_MAIL_USER_SUBMITTED', $type);

		$message = sprintf(JText::_('JLIB_MAIL_MSG_ADMIN'), $adminName, $type, $title, $author, $url, $url, 'administrator', $type);
		$message .= JText::_('JLIB_MAIL_MSG') . "\n";

		$this->addRecipient($adminEmail);
		$this->setSubject($subject);
		$this->setBody($message);

		return $this->Send();
	}

	/**
	 * Send mail through the SparkPost API.
	 *
	 * @return  bool  True if successful | False otherwise.
	 *
	 * @since   1.0
	 */
	private function sparkPostSend()
	{
	    // Get the attachments to send
		$attachments = $this->GetAttachments();
		$messageAttachments = array();

		if (count($attachments) > 0)
		{
			foreach ($attachments as $attachment)
			{
				if ($attachment[6] == 'inline')
				{
					// Inline attachment (normally image)
					$messageAttachments[] = array(
						'name' => $attachment[7],
						'type' => $this->filenameToType($attachment[1]),
						'data' => $this->EncodeFile($attachment[0])
					);
				}
				else
				{
					// Normal attachment
					$messageAttachments[] = array(
						'name' => $attachment[2],
						'type' => $this->filenameToType($attachment[1]),
						'data' => $this->EncodeFile($attachment[0])
					);
				}
			}
		}

		// Set all the recipients
		$to  = array();
		$cc  = array();
		$bcc = array();

		// Add the recipients
		foreach ($this->to as $value)
		{
			$to[] = array(
				'address' => array(
					'email' => $value[0],
					'name'  => $value[1],
				)
			);
		}

		// Add the cc
		foreach ($this->cc as $value)
		{
			$cc[] = array(
				'address' => array(
					'email' => $value[0],
					'name'  => $value[1],
				)
			);
		}

		// Add the bcc
		foreach ($this->bcc as $value)
		{
			$bcc[] = array(
				'address' => array(
					'email' => $value[0],
					'name'  => $value[1],
				)
			);
		}

		// Create the body text
		$html = $this->Body;

		if ($this->ContentType === 'text/plain')
		{
			$html = nl2br(htmlspecialchars($html));

			// Replace multiple spaces with single spaces
			$html = preg_replace('/\s\s+/', ' ', $html);

			// Replace URLs with <a href...> elements
			$html = $this->makeClickableUrls($html);
		}

		// Get the reply-to address
		$replyTos         = $this->getReplyToAddresses();
		$replyToAddresses = array();

		foreach ($replyTos as $replyTo)
		{
			$replyToAddresses[] = $this->addrFormat($replyTo);
		}

		// Build the payload
		$payload = [
			'content' => [
				'from'        => [
					'name'  => $this->FromName,
					'email' => $this->From,
				],
				'subject'     => $this->Subject,
				'reply_to'    => implode(',', $replyToAddresses),
				'html'        => $html,
				'text'        => $this->Body,
				'attachments' => $messageAttachments,
			],
		];

		if (!empty($to))
		{
			$payload['recipients'] = $to;
		}

		if (!empty($cc))
		{
			$payload['cc'] = $cc;
		}

		if (!empty($bcc))
		{
			$payload['bcc'] = $bcc;
		}

		// Post the payload
		$promise = $this->sparky->transmissions->post($payload);

		try
		{
		    // Get the response from the mail service
			$response = $promise->wait();

			if ($response->getStatusCode() !== 200)
			{
				$this->logMessage('Received status code ' . $response->getStatusCode(), JLog::WARNING);

				return false;
			}

		} catch (Exception $e)
		{
			$this->logMessage('Subject: ' . $this->Subject, JLog::INFO);
			$this->logMessage('Sender: ' . $this->From, JLog::INFO);

			foreach ($to as $address)
			{
				$this->logMessage('Recipient: ' . $address['address']['email'], JLog::INFO);
			}

		    $this->logMessage($e->getMessage(), JLog::ERROR);

			return false;
		}

		return true;
	}

	/**
	 * Function that will search plain-text for urls in it and will add the
	 * html <a> tag.
	 *
	 * @author  Daniel Dimitrov <daniel@compojoom.com>
	 *
	 * @param   string  $text  - the message text
	 *
	 * @return  mixed
	 *
	 * @since  1.0
	 */
	private function makeClickableUrls($text)
	{
		return preg_replace_callback(
			'#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#',
			create_function(
				'$matches',
				'return "<a href=\'{$matches[0]}\'>{$matches[0]}</a>";'
			),
			$text
		);
	}

	/**
	 * Write a message to the log file.
	 *
	 * @param   string  $message  The message to write to the log file.
	 * @param   string  $level    The error level.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function logMessage($message, $level)
	{
		JLog::add($message, $level, 'sparkpost');
	}
}
