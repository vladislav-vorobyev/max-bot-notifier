<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

/**
* Crypto
*
* Description: Library functions to encrypt or decrypt a plain text string
* initialization vector(IV) has to be the same when encrypting and decrypting
*
* Example of usage:
* - Encoding
* $crypto = new Crypto(SECURE_CRYPT_KEY, SECURE_CRYPT_SALT);
* $encoded = $crypto->encrypt( $value );
* - Decoding
* $crypto = new Crypto(SECURE_CRYPT_KEY, SECURE_CRYPT_SALT);
* $value = $crypto->decrypt( $value );
* - Test case
* $crypto = new Crypto(SECURE_CRYPT_KEY, SECURE_CRYPT_SALT);
* $passed = $crypto->test( $value );
*
*/
class Crypto {

	const METHOD = 'AES-256-CBC';
	const HASH_METHOD = 'sha256';
	private $_key;
	private $_iv;

	function __construct($secret_key, $secret_iv) {
		// hash
		$this->_key = hash(self::HASH_METHOD, $secret_key);
		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$this->_iv = substr( hash(self::HASH_METHOD, $secret_iv), 0, 16 );
	}

	public function encrypt($string) {
		return base64_encode( openssl_encrypt( $string, self::METHOD, $this->_key, 0, $this->_iv ) );
	}

	public function decrypt($string) {
		return openssl_decrypt( base64_decode($string), self::METHOD, $this->_key, 0, $this->_iv );
	}

	public function test($out = false, $plain_txt = 'Test example string') {
		if ($out) echo "Plain Text: {$plain_txt}\n";
		$encrypted_txt = $this->encrypt($plain_txt);
		if ($out) echo "Encrypted Text: {$encrypted_txt}\n";
		$decrypted_txt = $this->decrypt($encrypted_txt);
		if ($out) echo "Decrypted Text: {$decrypted_txt}\n";
		if ( $plain_txt === $decrypted_txt ) {
			if ($out) echo "SUCCESS\n";
			return true;
		} else {
			if ($out) echo "FAILED\n";
			return false;
		}
	}
}
