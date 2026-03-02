<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use function is_string;
use function intval;
use TNotifyer\Database\DB;
use TNotifyer\Engine\Storage;
use TNotifyer\Engine\App;
use TNotifyer\Providers\Log;
use TNotifyer\Providers\Scheduler;
use \DateTimeInterface;
use \DateTime;

/**
 * 
 * Provides the bot behavior and some additional actions.
 * 
 */
class Bot extends MAXBot {

	/**
	 * Job is active statuses option name
	 */
	public const ON_ACTIVE_JOBS = 'active-jobs';

	/**
	 * Jobs to do list option name
	 */
	public const ON_JOBS_TODO = 'jobs-todo';
	
	/**
	 * OZON client id option name
	 */
	public const ON_OZON_CLI_ID = 'ozon-id';
	
	/**
	 * OZON api key option name
	 */
	public const ON_OZON_API_KEY = 'ozon-key';
	
	/**
	 * Get the bot option
	 * 
	 * @param string option key
	 * @param mixed option default value (optional)
	 * 
	 * @return mixed option value or default
	 */
	public function getOption($key, $default = false) {
		$option = DB::get_bot_option($this->bot_id, $key);
		if (isset($option['value'])) {
			$value = $option['value'];
			// maybe unserialize
			if ('::S::' == substr($value, 0, 5)) {
				try {
					$value = unserialize(substr($value, 5));
				} catch(\Exception $e) {
					Log::put('error', "Unserialize '{$key}' value fail. " . $e->getMessage());
				}
			}
			return $value;
		}
		// return default value
		return $default;
	}
	
	/**
	 * Set the bot option
	 * 
	 * @param string option key
	 * @param mixed option value
	 * 
	 * @return bool action status
	 */
	public function setOption($key, $value) {
		if (!is_string($value)) $value = '::S::' . serialize($value);
		return false !== DB::save_bot_option($this->bot_id, $key, $value);
	}
	
	/**
	 * Get OZON client id and api key as array
	 * 
	 * @return array token
	 */
	public function getOZONToken() {
		return [$this->getOption(self::ON_OZON_CLI_ID), Storage::get('Crypto')->decrypt($this->getOption(self::ON_OZON_API_KEY))];
	}
	
	/**
	 * Get bot action handlers
	 * 
	 * @return array commands
	 */
	public function getCommands() {
		return [

			'/help' => function($bot) {
				$help = ''
					. '<b>/info</b> <i>- информация о приложении на хостинге</i>' . "\n"
					. '<b>/jobs</b> <i>- список заданий</i>' . "\n"
					. '<b>/mainchats</b> <i>- список привязанных чатов</i>' . "\n"
					. '<b>/test</b> <i>- отправить тестовое сообщение в привязанные чаты</i>' . "\n"
					. '<b>/ozon</b> <i>- информация об OZON аккаунте</i>' . "\n"
					. '<b>/ozonsetid</b> <i>- установить OZON CLIENT ID</i>' . "\n"
					. '<b>/ozonsetkey</b> <i>- установить OZON API KEY</i>';
				$bot->sendToAlarmChat($help, 'HTML');
			},

			'/test' => function($bot) {
				if ($mes_id = $bot->sendToMainChats('<b>Тестовое</b> <i>сообщение</i>', 'HTML'))
					$bot->replyToMainChats($mes_id, '<b>Ответное</b> <i>сообщение</i>', 'HTML');
			},

			'/info' => function($bot) {
				$data = Storage::get('App')->info();
				$bot->sendToAlarmChat('<code>' . Bot::convertToJson($data) . '</code>', 'HTML');
			},

			'/jobs' => function($bot) {
				$data = array_reduce( $this->getJobsList(), function($a, $job) {
					$ico = "<tg-emoji emoji-id='5368324170671202286'>" . ($job['active']? '✔️' : '❌') . "</tg-emoji>";
					$a[0] .= (++$a[1]) . ". {$job['action']} [{$job['schedule']}] {$ico} <i>(<b>/job_{$a[1]}</b>)</i>\n";
					return $a;
				}, ['', 0]);
				$bot->sendToAlarmChat($data[0], 'HTML');
			},

			'/job' => function($bot, $n) {
				if (!empty($i = intval($n ?? 0))) {
					$value = [];
					foreach ($this->getJobsList() as $j => $job)
						$value[$job['action']] = ($i-1 == $j)? !$job['active'] : $job['active'];
					$bot->changeOptionAct(Bot::ON_ACTIVE_JOBS, $value, 'Активность обновлена.');
					$bot->getCommands()['/jobs']($bot);
				}
			},

			'/mainchats' => function($bot) {
				$data = array_reduce( $this->getMainChatsInfo(), function($a, $val) {
					$a[0] .= (++$a[1]) . '. ' . $val . " <i>(<b>/X_{$a[1]}</b> для удаления)</i>\n";
					return $a;
				}, ['', 0]);
				$bot->sendToAlarmChat($data[0], 'HTML');
			},

			'/X' => function($bot, $n) {
				if (!empty($i = intval($n ?? 0))) {
					if (DB::remove_bot_chats($bot->getId(), $bot->getMainChatsIds()[$i - 1])) {
						$bot->sendToAlarmChat('Привязка чата удалена');
						$bot->getCommands()['/mainchats']($bot);
					} else
						$bot->alarm('chat num?', $i);
				}
			},

			'/ozon' => function($bot) {
				$data = Storage::get('OZON')->getInfo();
				$bot->sendToAlarmChat('<code>' . Bot::convertToJson($data) . '</code>', 'HTML');
			},

			'/ozonsetid' => [
				'Передайте пожалуйста OZON CLIENT ID или /cancel для отмены команды.',
				function($bot, $text) {
					$bot->changeOptionAct(Bot::ON_OZON_CLI_ID, $text, 'OZON CLIENT ID обновлен.');
				}
			],
			
			'/ozonsetkey' => [
				'Передайте пожалуйста OZON API KEY или /cancel для отмены команды.',
				function($bot, $text) {
					$text = Storage::get('Crypto')->encrypt($text);
					$bot->changeOptionAct(Bot::ON_OZON_API_KEY, $text, 'OZON API KEY обновлен.');
				}
			],
		];
	}
	
	/**
	 * 
	 * Check an update from MAX bot (overrides parent).
	 * Provides some bot actions via messages.
	 * 
	 * @param mixed incoming API update
	 */
	public function checkUpdate($update) {
		parent::checkUpdate($update);

		if (empty($update)) return;

		// do not throw exception on bot update
		try {
			$commands = $this->getCommands();

			// inspecting message update
			$message = $update['message'] ?? $update['edited_message'] ?? [];
			$r_text = &$message['body']['text'];
			$r_chat_id = &$message['recipient']['chat_id'];
			if (!empty($r_text) && !empty($r_chat_id)) {

				// get bot status on this chat
				$status_option_name = "chat_{$r_chat_id}_status";
				$chat_status = $this->getOption($status_option_name);

				if (empty($chat_status)) {
					// no specific status then try to find a command
					$args = explode('_', $r_text);
					$cmd = array_shift($args);
					$r_command = &$commands[$cmd];
					if (!empty($r_command) && $this->checkCommandAccess($r_chat_id)) {
						if (is_callable($r_command)) {
							$r_command($this, ...$args);
						} elseif (is_array($r_command)) {
							// two steps command
							$this->changeOptionAct($status_option_name, $cmd, $r_command[0]);
						}
					}

				} elseif ($r_text == '/cancel') {
					// bot status is not empty and we got the cancel command
					$this->changeOptionAct($status_option_name, '', 'Отмена команды');

				} else {
					// determine a command by bot status (two steps command)
					$r_command = &$commands[$chat_status][1];
					if (!empty($r_command) && is_callable($r_command) && $this->checkCommandAccess($r_chat_id)) {
						$r_command($this, $r_text);
					}
					// clear the status
					$this->changeOptionAct($status_option_name, '');
				}
			}

		} catch(\Exception $e) {
			Log::put('error', "Fail update check. " . $e->getMessage());
		}
	}
	
	/**
	 * 
	 * Check an access from the chat to bot commands
	 * 
	 * @param string chat id
	 */
	public function checkCommandAccess($r_chat_id) {
		if ($r_chat_id != $this->admin_chat_id) {
			Log::put('warning', "Access forbidden from the chat {$r_chat_id}");
			return false;
		}
		return true;
	}
	
	/**
	 * 
	 * Change the bot option and sending success/fail message
	 * 
	 * @param string option name
	 * @param string new value
	 * @param string success message text (optional)
	 * @param string fail message text (optional)
	 */
	public function changeOptionAct($option_name, $value, $success_msg = '', $fail_msg = 'Ошибка изменения!') {
		if ($this->setOption($option_name, $value)) {
			if (!empty($success_msg))
				$this->sendToAlarmChat($success_msg, 'HTML');
		} else {
			$this->sendToAlarmChat($fail_msg, 'HTML');
		}
	}
	
	/**
	 * Get information about main chats
	 * 
	 * @return array list of chats info
	 */
	public function getMainChatsInfo() {
		return array_map( function($chat_id){ return $this->getChatTitle($chat_id); }, $this->getMainChatsIds() );
	}

	/**
	 * 
	 * Run jobs activity of the bot
	 * 
	 */
	public function runJobs() {
		// run stored jobs todo list
		$this->runJobsTodo( $this->getOption(self::ON_JOBS_TODO, []), new DateTime('now') );

		// store next jobs todo list
		$this->setOption( self::ON_JOBS_TODO, $this->getNextJobsTodo(new DateTime('now')) );
	}

	/**
	 * 
	 * Run jobs todo list actions
	 * 
	 * @param mixed jobs todo list
	 * @param DateTime current time
	 */
	public function runJobsTodo($jobs_todo, $now) {
		// get option to check is job active
		$active_jobs = $this->getOption(self::ON_ACTIVE_JOBS);

		// run jobs todo list
		foreach ($jobs_todo as $action => $time)
			if ($active_jobs[$action] ?? true) {
				$job_time = new DateTime($time);
				if ($job_time <= $now) {
					// run the action
					try {
						App::execute( Storage::get('Router')->getExecutor('GET', $action) );
					} catch(\Exception $e) {
						Log::put('error', "Fail run '{$action}'. " . $e->getMessage());
					}
				}
			}
	}

	/**
	 * 
	 * Prepare next jobs todo list
	 * 
	 * @param DateTime current time
	 * 
	 * @return mixed jobs todo list ['action 1' => 'datetime', 'action 2' => ...]
	 */
	public function getNextJobsTodo($now) {
		$next_jobs_todo = [];

		// for each job from bot jobs list
		foreach (explode('|', App::env('BOT_JOBS', '')) as $job)
			if (!empty($job)) {
				[$action, $schedule] = explode(':', $job, 2);

				// calc the job schedule
				$next_time = (new Scheduler($schedule))->next($now);

				$next_jobs_todo[$action] = $next_time->format(DateTimeInterface::ATOM);
			}

		return $next_jobs_todo;
	}
	
	/**
	 * 
	 * Get bot jobs list with activity status
	 * 
	 * @return array jobs list [{'action', 'schedule', 'active'},...]
	 */
	public function getJobsList() {
		$jobs = [];

		// get option to check is job active
		$active_jobs = $this->getOption(self::ON_ACTIVE_JOBS);

		// for each job from bot jobs list
		foreach (explode('|', App::env('BOT_JOBS', '')) as $job)
			if (!empty($job)) {
				[$action, $schedule] = explode(':', $job, 2);
				$active = $active_jobs[$action] ?? true;

				$jobs[] = compact('action', 'schedule', 'active');
			}

		return $jobs;
	}
	
	/**
	 * 
	 * Send a MAXBot day activity message to alarm MAX chat
	 * 
	 */
	public function sendTbotDayActivity() {
		// get activity statistic from DB
		$sql = 'SELECT count(*) FROM a_log WHERE created BETWEEN DATE_SUB(NOW(),INTERVAL 1 DAY) AND NOW() AND type=\'check\' AND bot_id=' . $this->getId();
		$count = ($result = DB::fetch_row($sql))? $result[0] : 0;

		// send a message
		$msg = "<b>TODAY:</b> bot [{$this->getId()}] checks count = $count";
		$this->sendToAlarmChat($msg, 'HTML');
	}
	
	/**
	 * 
	 * Check a status of websites (by the list from DB)
	 * 
	 */
	public function pingWebsites() {
		$result = [];

		// get a list from DB
		$sql = 'SELECT * FROM a_websites';
		$websites = DB::fetch_all($sql);

		// check status for each website
		foreach ($websites as $website) {
			$url = $website['url'];

			// get site status
			$active = Storage::get('CURL')->pingUrl($url);

			// compare the status with stored
			if ($website['active'] != $active) {
				// send a message about status change to alarm chat
				$url_str = str_replace('https://', '', $url);
				$msg = "[{$this->getHostId()}] <code>{$url_str}</code> is <b>" . ($active? 'UP' : 'DOWN') . "</b>";
				$this->sendMessage($this->getAdminChatId(), $msg, 'HTML', false);
				
				// update stored data
				$sql = 'UPDATE a_websites SET active=' . ($active? 1 : 0) . ', updated=NOW() WHERE id=' . $website['id'];
				if (DB::query($sql) != 1) {
					Log::put('error', 'DB: wrong update', $sql);
				}
			}

			// response
			$result[] = ['url' => $url, 'active' => $active];
		}

		return $result;
	}
}
