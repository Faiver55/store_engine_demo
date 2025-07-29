<?php
/**
 * Encryption Utility.
 *
 * Secure sensitive data for the plugin to store in database.
 *
 * @package StoreEngine\Utils
 *
 * @since 1.0.0-beta-6
 * @version 1.0.0
 */

namespace StoreEngine\Utils;

use Exception;
use SodiumException;
use StoreEngine\Classes\Exceptions\StoreEngineException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Crypto
 *
 * Provides secure encryption and decryption using XChaCha20-Poly1305 AEAD (libsodium).
 * Uses a 256-bit key, with random nonces per message and base64-encoded output.
 */
class Crypto {
	/**
	 * Length of nonce for XChaCha20-Poly1305 (24 bytes).
	 */
	private const NONCE_LEN = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

	/**
	 * Cached binary key to avoid repeated conversion from hex.
	 *
	 * @var string|null
	 */
	private static ?string $key = null;

	/**
	 * Generates a new 256-bit key for encryption (hex-encoded).
	 *
	 * @return string Hexadecimal-encoded encryption key.
	 * @throws StoreEngineException
	 */
	public static function generateKey(): string {
		try {
			return sodium_bin2hex( sodium_crypto_aead_xchacha20poly1305_ietf_keygen() );
		} catch ( SodiumException | Exception $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-generate-encryption-key' );
		}
	}

	/**
	 * Retrieves and caches the encryption key from environment variables.
	 *
	 * @return string Binary-safe encryption key.
	 * @throws StoreEngineException If the key is missing or invalid.
	 */
	private static function getKey(): string {
		if ( ! self::$key ) {
			if ( function_exists( 'getenv' ) && getenv( 'STOREENGINE_ENCRYPTION_KEY' ) ) {
				try {
					$hex = sodium_hex2bin( getenv( 'STOREENGINE_ENCRYPTION_KEY' ) );
				} catch ( Exception $e ) {
					throw new StoreEngineException(
						__( 'Failed to decode encryption key.', 'storeengine' ),
						'failed-to-decode-encryption-key',
						[
							'error' => $e->getMessage(),
							'via'   => 'getenv(STOREENGINE_ENCRYPTION_KEY)',
						],
						0,
						$e
					);
				}
			} elseif ( defined( 'STOREENGINE_ENCRYPTION_KEY' ) && STOREENGINE_ENCRYPTION_KEY ) {
				try {
					$hex = sodium_hex2bin( STOREENGINE_ENCRYPTION_KEY );
				} catch ( Exception $e ) {
					throw new StoreEngineException(
						__( 'Failed to decode encryption key.', 'storeengine' ),
						'failed-to-decode-encryption-key',
						[
							'error' => $e->getMessage(),
							'via'   => 'STOREENGINE_ENCRYPTION_KEY',
						],
						0,
						$e
					);
				}
			} else {
				// Fallback to hashed SECURE_AUTH_KEY if no key is defined
				$hex = hash( 'sha256', SECURE_AUTH_KEY, true );
			}

			if ( ! $hex ) {
				throw new StoreEngineException( __( 'Encryption key is missing.', 'storeengine' ), 'encryption-key-missing' );
			}

			self::$key = $hex;
		}

		return self::$key;
	}

	/**
	 * Encrypts the given data using XChaCha20-Poly1305.
	 *
	 * @param string|int|float|bool|array|object $data
	 *        The data to encrypt. Arrays/objects are JSON-encoded.
	 *
	 * @return string Base64-encoded ciphertext (nonce + encrypted data).
	 * @throws StoreEngineException
	 */
	public static function encrypt( $data ): string {
		try {
			// Serialize arrays/objects
			$data = maybe_serialize( $data );

			// Generate a unique random nonce per encryption
			$nonce = random_bytes( self::NONCE_LEN );

			// Perform authenticated encryption (AEAD), without associated data
			$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $data, '', $nonce, self::getKey() );

			// Return base64 encoded (nonce + ciphertext)
			return base64_encode( $nonce . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		} catch ( Exception $e ) {
			throw new StoreEngineException(
				__( 'Failed to encrypt data.', 'storeengine' ),
				'failed-to-encrypt-data',
				[ 'error' => $e->getMessage() ],
				0,
				$e
			);
		}
	}

	/**
	 * Decrypts data encrypted with encrypt().
	 *
	 * @param ?string $data Base64-encoded string (nonce + ciphertext).
	 *
	 * @return string|array|false The decrypted value, auto-decoded from JSON if applicable. False on failure.
	 * @throws StoreEngineException
	 */
	public static function decrypt( ?string $data ) {
		try {
			if ( ! $data ) {
				return false;
			}

			// Decode base64 input
			$decoded = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $decoded ) {
				return $data; // Maybe not encrypted.
			}

			if ( strlen( $decoded ) <= self::NONCE_LEN ) {
				return false; // Maybe not encrypted data.
			}

			// Extract nonce and ciphertext
			$nonce      = substr( $decoded, 0, self::NONCE_LEN );
			$ciphertext = substr( $decoded, self::NONCE_LEN );

			// Decrypt the ciphertext, without associated data.
			$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( $ciphertext, '', $nonce, self::getKey() );

			return maybe_unserialize( $plaintext );
		} catch ( Exception $e ) {
			throw new StoreEngineException(
				__( 'Failed to decrypt data.', 'storeengine' ),
				'failed-to-decrypt-data',
				[ 'error' => $e->getMessage() ],
				0,
				$e
			);
		}
	}

	/**
	 * Retrieves the value of a transient.
	 *
	 * If the transient does not exist, does not have a value, or has expired,
	 * then the return value will be false.
	 *
	 * @param string $transient Transient name. Expected to not be SQL-escaped.
	 *
	 * @return array|false|string Value of transient.
	 * @throws StoreEngineException
	 */
	public static function get_transient( string $transient ) {
		$value = get_transient( $transient );
		if ( false === $value ) {
			return false;
		}

		return self::decrypt( $value );
	}

	/**
	 * Sets/updates the value of a transient.
	 *
	 * You do not need to serialize values. If the value needs to be serialized,
	 * then it will be serialized before it is set.
	 *
	 * @param string $transient Transient name. Expected to not be SQL-escaped.
	 *                           Must be 172 characters or fewer in length.
	 * @param mixed $value Transient value. Must be serializable if non-scalar.
	 *                           Expected to not be SQL-escaped.
	 * @param int $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
	 *
	 * @return bool True if the value was set, false otherwise.
	 * @throws StoreEngineException
	 */
	public static function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		return set_transient( $transient, self::encrypt( $value ), $expiration );
	}
}

// End of file crypto.php
