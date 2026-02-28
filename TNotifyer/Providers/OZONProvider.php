<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

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
	const API_URL = 'https://api-seller.ozon.ru';
	
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
		$data = Storage::get('CURL')->post($url, $headers, $postfields);
		
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
	 * Requesting a list of unfulfilled postings
	 * 
	 * @param DateTime requesting period from datetime
	 * @param DateTime requesting period to datetime
	 * @param int limit to get (optional, 100 by default)
	 * @param int offset to start (optional, 0 by default)
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getFBSUnfulfilledList($datetime_from, $datetime_to, $limit = 100, $offset = 0) {
		$url = self::API_URL . '/v3/posting/fbs/unfulfilled/list';
		$postfields = json_encode([
			'dir' => 'ASC',
			'limit' => $limit,
			'offset' => $offset,
			'filter' => [
				'cutoff_from' => $datetime_from->format(DateTimeInterface::RFC3339),
				'cutoff_to' => $datetime_to->format(DateTimeInterface::RFC3339)
			]
		]);
		return $this->post($url, $postfields);
	}

	/**
	 * Requesting a list of all postings
	 * 
	 * @param DateTime requesting period from datetime
	 * @param DateTime requesting period to datetime
	 * @param int limit to get (optional, 100 by default)
	 * @param int offset to start (optional, 0 by default)
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getFBSList($datetime_from, $datetime_to, $limit = 100, $offset = 0) {
		$url = self::API_URL . '/v3/posting/fbs/list';
		$postfields = json_encode([
			'dir' => 'ASC',
			'limit' => $limit,
			'offset' => $offset,
			'filter' => [
				'since' => $datetime_from->format(DateTimeInterface::RFC3339),
				'to' => $datetime_to->format(DateTimeInterface::RFC3339)
			]
		]);
		return $this->post($url, $postfields);
	}

	/**
	 * Requesting a list of cancelled postings
	 * 
	 * @param DateTime requesting period from datetime
	 * @param DateTime requesting period to datetime
	 * @param int limit to get (optional, 100 by default)
	 * @param int offset to start (optional, 0 by default)
	 * 
	 * @return mixed response (OZON API)
	 */
	public function getCancelledFBSList($datetime_from, $datetime_to, $limit = 100, $offset = 0) {
		$url = self::API_URL . '/v3/posting/fbs/list';
		$postfields = json_encode([
			'dir' => 'ASC',
			'limit' => $limit,
			'offset' => $offset,
			'filter' => [
				'status' => 'cancelled',
				'since' => $datetime_from->format(DateTimeInterface::RFC3339),
				'to' => $datetime_to->format(DateTimeInterface::RFC3339)
			]
		]);
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
	 * @param int offset to start (optional, 0 by default)
	 */
	public function doCheckStatus($period = '', $offset = 0) {
		if (empty($period)) {
			// determine a period from statuses records
			$days = $this->getUnfinishedPeriod();
			$period = !empty($days)? ($days + 1) . ' days' : '1 month';
		}

		// get postings in period
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString(empty($period)? '1 month' : $period) );
		$datetime_to = new DateTime('now');
		$data = $this->getFBSList($datetime_from, $datetime_to, 1000, $offset);

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

		if (empty($offset))
			Log::put('check-status', 'OZON');

		// if has next then call recursively
		$r_has_next = &$data['result']['has_next'];
		if (!empty($r_has_next)) {
			$this->doCheckStatus($period, $offset + 1000);
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
	 * @param int offset to start (optional, 0 by default)
	 */
	public function doCheckCancelled($period = '', $offset = 0) {
		// get postings after last check but not far then 24 hours
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString(empty($period)? '7 days' : $period) );
		$datetime_to = new DateTime('now');
		$data = $this->getCancelledFBSList($datetime_from, $datetime_to, 100, $offset);

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
		$r_has_next = &$data['result']['has_next'];
		if (!empty($r_has_next)) {
			$this->doCheckCancelled($period, $offset + 100);
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
	 * @param int offset to start (optional, 0 by default)
	 */
	public function doCheckNew($period = '', $offset = 0) {
		if (empty($period)) {
			// determine a time from last check but not far then 24 hours
			$time = $this->getLastCheckTime();
			$period = (!empty($time) && ($time < 23*60*60))? ($time + 300) . ' seconds' : '24 hours';
		}

		// get postings in period
		$datetime_from = ( new DateTime('now') )->sub( DateInterval::createFromDateString($period) );
		$datetime_to = new DateTime('now');
		$data = $this->getFBSList($datetime_from, $datetime_to, 100, $offset);

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

		if (empty($offset))
			Log::put('check', 'OZON');

		// if has next then call recursively
		$r_has_next = &$data['result']['has_next'];
		if (!empty($r_has_next)) {
			$this->doCheckNew($period, $offset + 100);
		}
	}

	/**
	 * Verify postings response
	 * 
	 * @param mixed OZON API response
	 * 
	 * @return mixed reference to postings array
	 */
	public function verifyPostingsResponse(&$data) {
		$r_postings = &$data['result']['postings'];

		if (empty($data)) {
			$this->last_error_message = 'OZON empty response or JSON error';
			Log::put('error', $this->last_error_message, ['request' => Storage::get('CURL')->last_request]);
			throw new ExternalRequestException($this->last_error_message);

		} elseif (!isset($r_postings)) {
			$this->last_error_message = 'OZON wrong response';
			Log::put('error', $this->last_error_message, ['request' => Storage::get('CURL')->last_request, 'response' => $data]);
			throw new ExternalRequestException($this->last_error_message);
		}

		return $r_postings;
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
		$r_posting_number = $posting['posting_number'];
		$r_status = $posting['status'];
		if (!isset($r_posting_number) || !isset($r_status)) {
			Log::put('error', 'Wrong OZON posting format.', $posting);
			return false;
		}

		// get bot internal id
		$tbot_id = Storage::get('Bot')->getId();

		// check posting status in DB
		$old = DB::get_last_postings(1, $tbot_id, 'ozon', $r_posting_number);

		// if new posting or in test mode then send notification
		$message_id = [];
		if (empty($old) || Storage::get('App')->var('test-mode', false)) {
			if (in_array($r_status, ['cancelled', 'delivering', 'delivered'])) {
				Log::debug("<b>(!) Status is not for notify: {$r_status}</b>");
			} else {
				// notify about new posting
				$message_id = $this->sendNewPostingInfo($posting);
				if (empty($message_id)) {
					Log::debug("Can't notify!");
				}
			}
		}

		// if new posting or new status then save it to DB
		if (empty($old) || ($old[0]['status'] ?? '') != $r_status) {
			// store the posting
			DB::insert_posting($tbot_id, 'ozon', $r_posting_number, $r_status, $posting);
			// store the status
			DB::save_posting_status($tbot_id, 'ozon', $r_posting_number, $r_status, '', '', $message_id, ($posting['in_process_at'] ?? ''));
	
			// if cancelled is new status of the posting
			if (!empty($old) && 'cancelled' == $r_status) {
				// notify about cancelled posting
				if (empty($this->sendCancelledPostingInfo($posting))) {
					Log::debug("Can't notify!");
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
	public function getOrderText($posting) {
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
	 * Notify about new posting
	 * 
	 * @param mixed posting data
	 * 
	 * @return bool is done
	 */
	public function sendNewPostingInfo($posting) {
		// prepare message text
		$text = "<b>OZON</b>\nНовый заказ: " . $this->getOrderText($posting);

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
		$order_text = $this->getOrderText($posting);
		$text .= "<tg-emoji emoji-id='5368324170671202286'>❌</tg-emoji> Отменен заказ: {$order_text}";
		$r_cancel_reason = &$posting['cancellation']['cancel_reason'];
		if (isset($r_cancel_reason)) {
			$text .= "\n<i>{$r_cancel_reason}</i>";
		}

		// send message
		return Storage::get('Bot')->replyToMainChats($message_id, $text, 'HTML');
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
