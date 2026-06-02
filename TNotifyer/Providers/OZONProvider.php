<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use function is_array;
use function in_array;
use TNotifyer\Engine\Storage;
use TNotifyer\Database\DB;
use TNotifyer\Providers\Log;
use TNotifyer\Exceptions\ExternalRequestException;
use \DateTimeInterface;
use \DateTime;
use \DateInterval;

/**
 * 
 * Provides communication with OZON API
 * 
 */
class OZONProvider {

	/**
	 * OZON API base url
	 */
	public const API_URL = 'https://api-seller.ozon.ru';

	/**
	 * Статус отправления (status)
	 */
	public const STATUS_TEXT = [
		'acceptance_in_progress' => '🔔 идёт приёмка',
		'arbitration' => '❗ арбитраж',
		'awaiting_approve' => '🔔 ожидает подтверждения',
		'awaiting_deliver' => '🔔 ожидает отгрузки',
		'awaiting_packaging' => '🔔 ожидает упаковки',
		'awaiting_registration' => '🔔 ожидает регистрации',
		'awaiting_verification' => '🔔 создано',
		'cancelled' => 'отменено',
		'cancelled_from_split_pending' => 'отменён из-за разделения отправления',
		'client_arbitration' => '❗ клиентский арбитраж доставки',
		'delivering' => '🚛 доставляется',
		'driver_pickup' => 'у водителя',
		'not_accepted' => '⛔ не принято на сортировочном центре',
		'delivered' => '✅ доставлено',
	];

	/**
	 * Подстатус отправления (substatus)
	 */
	public const SUBSTATUS_TEXT = [
		'posting_acceptance_in_progress' => '🔔 идёт приёмка',
		'posting_in_arbitration' => '❗ арбитраж',
		// 'posting_created' => 'создано',
		'posting_in_carriage' => 'в перевозке',
		'posting_not_in_carriage' => '❗ не добавлено в перевозку',
		'posting_registered' => 'зарегистрировано',
		'posting_transferring_to_delivery' => 'передаётся в доставку',
		// 'posting_transferring_to_delivery' => 'передаётся курьеру',
		'posting_awaiting_passport_data' => 'ожидает паспортных данных',
		'posting_awaiting_registration' => 'ожидает регистрации',
		'posting_registration_error' => '❗ ошибка регистрации',
		'posting_split_pending' => 'создано',
		'posting_canceled' => 'отменено',
		'posting_in_client_arbitration' => '❗ клиентский арбитраж доставки',
		'posting_delivered' => 'доставлено',
		'posting_received' => 'получено',
		'posting_conditionally_delivered' => 'условно доставлено',
		'posting_in_courier_service' => 'курьер в пути',
		'posting_in_pickup_point' => 'в пункте выдачи',
		'posting_on_way_to_city' => 'в пути в город назначения',
		'posting_on_way_to_pickup_point' => 'в пути в пункт выдачи',
		'posting_returned_to_warehouse' => 'возвращено на склад',
		'posting_transferred_to_courier_service' => 'передаётся в службу доставки',
		'posting_driver_pick_up' => 'у водителя',
		'posting_not_in_sort_center' => '⛔ не принято на сортировочном центре',
		'ship_failed' => '❗ сборка не удалась'
	];
	
	/**
	 * @var string last error message
	 */
	protected $last_error_message = '';

	/**
	 * @var string OZON client id
	 */
	protected $client_id;

	/**
	 * @var string OZON api key
	 */
	protected $api_key;

	/**
	 * @var CURL api request instance
	 */
	protected $curl;

	/**
	 * 
	 * Constructor
	 * 
	 * @param string OZON client id
	 * @param string OZON api key
	 */
	public function __construct($client_id, $api_key) {
		$this->client_id = $client_id;
		$this->api_key = $api_key;
	}

	/**
	 * Make request to OZON API
	 * 
	 * @param string url to make a post request
	 * @param mixed request content
	 * 
	 * @return mixed response (OZON API)
	 */
	public function post($url, $postfields) {
		$headers = [
			'Client-Id: ' . $this->client_id,
			'Api-Key: ' . $this->api_key,
			'Content-Type: application/json'
		];
		
		Log::debug("POST {$url}\n<i>{$postfields}</i>\n");
		$this->curl = clone Storage::get('CURL');
		$data = $this->curl->post($url, $headers, $postfields);
		
		return $data;
	}

	/**
	 * Requesting a roles
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getRoles() {
		$url = self::API_URL . '/v1/roles';
		return $this->post($url, '');
	}

	/**
	 * Requesting a seller info
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getInfo() {
		$url = self::API_URL . '/v1/seller/info';
		return $this->post($url, '');
	}

	/**
	 * Requesting a list of unfulfilled FBS postings
	 * 
	 * @param DateTime requesting period from datetime
	 * @param DateTime requesting period to datetime
	 * @param int limit to get (optional, 100 by default, 1..100)
	 * @param int cursor to get next page (optional)
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getFBSUnfulfilledList($datetime_from, $datetime_to, $limit = 100, $cursor = null) {
		$url = self::API_URL . '/v4/posting/fbs/unfulfilled/list';
		$postfields = json_encode([
			'sort_dir' => 'ASC',
			'limit' => $limit,
			'filter' => [
				'cutoff_from' => $datetime_from->format(DateTimeInterface::RFC3339),
				'cutoff_to' => $datetime_to->format(DateTimeInterface::RFC3339)
			]
		]);
		if ($cursor !== null)
			$postfields['cursor'] = $cursor;
		return $this->post($url, $postfields);
	}

	/**
	 * Requesting a list of FBS postings
	 * 
	 * @param DateTime requesting period from datetime
	 * @param DateTime requesting period to datetime
	 * @param int limit to get (optional, 100 by default, 1..100)
	 * @param int cursor to get next page (optional)
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getFBSList($datetime_from, $datetime_to, $limit = 100, $cursor = null) {
		$url = self::API_URL . '/v4/posting/fbs/list';
		$postfields = json_encode([
			'sort_dir' => 'ASC',
			'limit' => $limit,
			'filter' => [
				'since' => $datetime_from->format(DateTimeInterface::RFC3339),
				'to' => $datetime_to->format(DateTimeInterface::RFC3339)
			]
		]);
		if ($cursor !== null)
			$postfields['cursor'] = $cursor;
		return $this->post($url, $postfields);
	}

	/**
	 * Requesting a list of cancelled FBS postings
	 * 
	 * @param DateTime requesting period from datetime
	 * @param DateTime requesting period to datetime
	 * @param int limit to get (optional, 100 by default, 1..100)
	 * @param int cursor to get next page (optional)
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getCancelledFBSList($datetime_from, $datetime_to, $limit = 100, $cursor = null) {
		$url = self::API_URL . '/v4/posting/fbs/list';
		$postfields = json_encode([
			'sort_dir' => 'ASC',
			'limit' => $limit,
			'filter' => [
				'status' => 'cancelled',
				'since' => $datetime_from->format(DateTimeInterface::RFC3339),
				'to' => $datetime_to->format(DateTimeInterface::RFC3339)
			]
		]);
		if ($cursor !== null)
			$postfields['cursor'] = $cursor;
		return $this->post($url, $postfields);
	}

	/**
	 * Get posting
	 * 
	 * @param string posting_number
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getPosting($posting_number) {
		$url = self::API_URL . '/v3/posting/fbs/get';
		$postfields = json_encode([
			'posting_number' => $posting_number,
		]);
		return $this->post($url, $postfields);
	}

	/**
	 * Check postings
	 * 
	 * @param string period to check new (optional)
	 */
	public function doCheck($period_new = '') {
		$this->doCheckNew($period_new);
		$this->doCheckCancelled();
	}

	/**
	 * Check postings status
	 * 
	 * @param string period to check (optional, 1 month by default)
	 * @param mixed cursor to get next page (optional)
	 */
	public function doCheckStatus($period = '', $cursor = null) {
		if (empty($period)) {
			// determine a period from statuses records
			$days = $this->getUnfinishedPeriod();
			$period = !empty($days)? ($days + 1) . ' days' : '1 month';
		}

		// get postings in period
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString(empty($period)? '1 month' : $period) );
		$datetime_to = new DateTime('now');
		$data = $this->getFBSList($datetime_from, $datetime_to, 100, $cursor);

		// verify response
		$r_postings = $this->verifyPostingsResponse($data);

		// loop over postings
		if (!empty($r_postings)) {
			// process postings
			foreach ($r_postings as &$posting) {
				if (!$this->checkPosting($posting)) {
					Log::put('error', 'OZON wrong posting data', $posting);
				}
			}
		}

		if ($cursor === null)
			Log::put('check-status', 'OZON');

		// if has next then call recursively
		if (($data['has_next'] ?? '') == true) {
			$this->doCheckStatus($period, $data['cursor']);
		}
	}

	/**
	 * Determine a days period to earliest order that has unfinished status
	 * 
	 * @return int period in days
	 */
	public function getUnfinishedPeriod() {
		$result = DB::get_days_of_status(-1, 'ozon', null, [" NOT IN ('delivered', 'cancelled')"]);
		$r_days = &$result[0]['days'];
		return (!empty($r_days))? intval($r_days) : 0;
	}

	/**
	 * Check cancelled postings
	 * 
	 * @param string period to check (optional)
	 * @param mixed cursor to get next page (optional)
	 */
	public function doCheckCancelled($period = '', $cursor = null) {
		// get postings after last check but not far then 24 hours
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString(empty($period)? '7 days' : $period) );
		$datetime_to = new DateTime('now');
		$data = $this->getCancelledFBSList($datetime_from, $datetime_to, 100, $cursor);

		// verify response
		$r_postings = $this->verifyPostingsResponse($data);

		// loop over postings
		if (!empty($r_postings)) {
			// Log::put('debug', 'OZON postings', $r_postings);
			// process postings
			foreach ($r_postings as &$posting) {
				if (!$this->checkPosting($posting)) {
					Log::put('error', 'OZON wrong posting data', $posting);
				}
			}
		}

		// if has next then call recursively
		if (($data['has_next'] ?? '') == true) {
			$this->doCheckCancelled($period, $data['cursor']);
		}
	}

	/**
	 * Determine a time from last check
	 * 
	 * @return int time in seconds
	 */
	public function getLastCheckTime() {
		$result = DB::get_last_log_time(-1, 'check', 'OZON');
		$r_sec = &$result[0]['sec'];
		return (!empty($r_sec))? intval($r_sec) : 0;
	}

	/**
	 * Check new postings
	 * 
	 * @param string period to check (optional)
	 * @param mixed cursor to get next page (optional)
	 */
	public function doCheckNew($period = '', $cursor = null) {
		if (empty($period)) {
			// determine a time from last check but not far then 24 hours
			$time = $this->getLastCheckTime();
			$period = (!empty($time) && ($time < 23*60*60))? ($time + 300) . ' seconds' : '24 hours';
		}

		// get postings in period
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString($period) );
		$datetime_to = new DateTime('now');
		$data = $this->getFBSList($datetime_from, $datetime_to, 100, $cursor);

		// verify response
		$r_postings = $this->verifyPostingsResponse($data);

		// loop over postings
		if (!empty($r_postings)) {
			Log::put('notice', 'OZON postings', $r_postings);
			// process postings
			foreach ($r_postings as &$posting) {
				if (!$this->checkPosting($posting)) {
					Log::put('error', 'OZON wrong posting data', $posting);
				}
			}
		}

		if ($cursor === null)
			Log::put('check', 'OZON');

		// if has next then call recursively
		if (($data['has_next'] ?? '') == true) {
			$this->doCheckNew($period, $data['cursor']);
		}
	}

	/**
	 * Verify v4 postings response
	 * 
	 * @param mixed OZON API v4 response
	 * 
	 * @return mixed reference to postings array
	 */
	public function verifyPostingsResponse(&$data) {
		if (false === $data) {
			$this->last_error_message = 'OZON JSON error';
			// Log::put('error', $this->last_error_message, ['request' => $this->curl->last_request]); error already sent
			Log::put('warning', $this->last_error_message);
			throw new ExternalRequestException($this->last_error_message);
		}

		if (empty($data)) {
			$this->last_error_message = 'OZON empty response';
			Log::put('warning', $this->last_error_message);
			throw new ExternalRequestException($this->last_error_message);
		}

		if (!is_array($data) || !is_array($data['postings'] ?? '')) {
			$this->last_error_message = 'OZON wrong response';
			Log::put('error', $this->last_error_message, ['request' => $this->curl->last_request, 'response' => $data]);
			throw new ExternalRequestException($this->last_error_message);
		}

		return $data['postings'];
	}

	/**
	 * Check posting
	 * 
	 * @param mixed posting data
	 * 
	 * @return bool is check done
	 */
	public function checkPosting($posting) {
		// check structure
		$posting_number = $posting['posting_number'] ?? '';
		$status = $posting['status'] ?? '';
		$substatus = $posting['substatus'] ?? '';
		$posting_status = "{$status}+{$substatus}";
		if (empty($posting_number) || empty($status)) {
			Log::put('error', 'Wrong OZON posting format.', $posting);
			return false;
		}

		// get bot internal id
		$tbot_id = Storage::get('Bot')->getId();

		// get posting status from DB
		if (false === ($old = DB::get_last_postings(1, $tbot_id, 'ozon', $posting_number)))
			return false;

		// if new posting or in test mode then send notification
		$message_id = [];
		if (empty($old) || Storage::get('App')->var('test-mode', false)) {
			if (in_array($status, ['cancelled', 'delivering', 'delivered'])) {
				Log::debug("<b>(!) Status is not for notify: {$status}</b>");
			} else {
				// notify about new posting
				$message_id = $this->sendNewPostingInfo($posting);
				if (empty($message_id)) {
					Log::debug("Can't notify!");
				}
			}
		}

		// if new posting or new status then save it to DB
		$old_status = (($old ?? [])[0] ?? [])['status'] ?? '';
		if (empty($old) || ($old_status != $posting_status && 'cancelled' != $old_status)) {
			// store the posting
			DB::insert_posting($tbot_id, 'ozon', $posting_number, $posting_status, $posting);
			// store the status
			DB::save_posting_status($tbot_id, 'ozon', $posting_number, $status, $substatus, '', $message_id, ($posting['in_process_at'] ?? ''));

			if (!empty($old)) {
				// update posting status in the message
				$this->sendPostingStatus($posting);

				// if cancelled is new status of the posting
				if ('cancelled' == $status) {
					// notify about cancelled posting
					if (empty($this->sendCancelledPostingInfo($posting))) {
						Log::debug("Can't notify!");
					}
				}
			}
		}

		return true;
	}

	/**
	 * Prepare order text with products lines
	 * 
	 * @param mixed posting data
	 * 
	 * @return string text to show
	 */
	public static function getOrderText($posting) {
		// order number
		$text = "<code>{$posting['posting_number']}</code>";

		// products lines
		if (isset($posting['products'])) {
			foreach ($posting['products'] as &$product) {
				if (isset($product['name'])) {
					$price = round($product['price']);
					$text .= "\n<i>{$product['name']} ({$product['offer_id']}) {$product['quantity']}шт. {$price} ₽</i>";
				}
			}
		}

		return $text;
	}

	/**
	 * Prepare posting status as human readable
	 * 
	 * @param mixed posting data
	 * 
	 * @return string status text
	 */
	public static function getPostingStatusText($posting) {
		$substatus = self::SUBSTATUS_TEXT[$posting['substatus']] ?? '';
		$status = self::STATUS_TEXT[$posting['status'] ?? ''] ?? $substatus;
		return ($status != $substatus && !empty($substatus))? "$status ($substatus)" : $status;
	}

	/**
	 * Notify about new posting
	 * 
	 * @param mixed posting data
	 * 
	 * @return bool is done
	 */
	public function sendNewPostingInfo($posting) {
		// prepare message text
		$status_text = self::getPostingStatusText($posting);
		$text = "<b>OZON</b>\nЗаказ: " . self::getOrderText($posting) . "\n---\n{$status_text}";

		// send message
		return Storage::get('Bot')->sendToMainChats($text, 'HTML');
	}

	/**
	 * Notify about cancelled posting
	 * 
	 * @param mixed posting data
	 * 
	 * @return bool is done
	 */
	public function sendCancelledPostingInfo($posting) {
		// get sent message id
		$rows = DB::get_posting_status(1, -1, 'ozon', $posting['posting_number']);
		$r_message_id = &$rows[0]['message_id'];
		$message_id = (array)(empty($r_message_id)? [] : @json_decode($r_message_id));

		// prepare message text
		$text = empty($message_id)? "<b>OZON</b>\n" : '';
		$order_text = self::getOrderText($posting);
		$text .= "<tg-emoji emoji-id='5368324170671202286'>❌</tg-emoji> Отменен заказ: {$order_text}";
		$r_cancel_reason = &$posting['cancellation']['cancel_reason'];
		if (isset($r_cancel_reason)) {
			$text .= "\n<i>{$r_cancel_reason}</i>";
		}

		// send message
		return Storage::get('Bot')->replyToMainChats($message_id, $text, 'HTML');
	}

	/**
	 * Update posting status in the message
	 * 
	 * @param mixed posting data
	 */
	public function sendPostingStatus($posting) {
		// get sent message id
		$rows = DB::get_posting_status(1, -1, 'ozon', $posting['posting_number']);
		$r_message_id = &$rows[0]['message_id'];
		$message_ids = (array)(empty($r_message_id)? [] : @json_decode($r_message_id));

		// prepare message body
		$status_text = self::getPostingStatusText($posting);
		$text = "<b>OZON</b>\nЗаказ: " . self::getOrderText($posting) . "\n---\n{$status_text}";
		$body = ['text' => $text, 'format' => 'HTML'];

		// modify messages
		foreach ($message_ids as $mid) {
			Storage::get('Bot')->modifyMessage($mid, $body);
		}
	}

	/**
	 * Make fbs list test
	 * 
	 * @param string period to take
	 * 
	 * @return mixed API response
	 */
	public function makeFBSListTest($period = '7 days') { 
		$datetime_to = new DateTime('now');
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString($period) );
		return $this->getFBSList($datetime_from, $datetime_to);
	}

	/**
	 * Make cancelled status fbs list test
	 * 
	 * @param string period to take
	 * 
	 * @return mixed API response
	 */
	public function makeCancelledFBSListTest($period = '7 days') { 
		$datetime_to = new DateTime('now');
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString($period) );
		return $this->getCancelledFBSList($datetime_from, $datetime_to);
	}

	/**
	 * Make unfulfilled fbs list test
	 * 
	 * @param string period to take
	 * 
	 * @return mixed API response
	 */
	public function makeUnfulfilledFBSListTest($period = '7 days') { 
		$datetime_to = new DateTime('now');
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString($period) );
		return $this->getFBSUnfulfilledList($datetime_from, $datetime_to);
	}

	/**
	 * Get last error message
	 * 
	 * @return string error message
	 */
	public function lastErrorMessage() {
		return $this->last_error_message;
	}
}
