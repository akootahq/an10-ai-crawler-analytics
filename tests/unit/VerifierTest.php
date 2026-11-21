<?php
use PHPUnit\Framework\TestCase;

/**
 * A real crawler is never called a fake: only a certain miss is Spoofed.
 */
final class VerifierTest extends TestCase {

	const GPTBOT = array( '20.171.206.0/24' );

	const CLOUDFLARE = array( '173.245.48.0/20' );

	public function test_an_address_in_the_list_is_verified_even_through_a_proxy_or_from_an_old_list(): void {
		$this->assertSame( AN10_Store::VERIFIED, AN10_Verifier::verdict( '20.171.206.7', self::GPTBOT, false, false ) );
		$this->assertSame( AN10_Store::VERIFIED, AN10_Verifier::verdict( '20.171.206.7', self::GPTBOT, true, true ) );
	}

	public function test_a_certain_miss_is_spoofed(): void {
		$this->assertSame( AN10_Store::SPOOFED, AN10_Verifier::verdict( '8.8.8.8', self::GPTBOT, false, false ) );
	}

	public function test_a_miss_through_a_proxy_or_against_an_old_list_is_only_unverified(): void {
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( '8.8.8.8', self::GPTBOT, true, false ) );
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( '8.8.8.8', self::GPTBOT, false, true ) );
	}

	public function test_no_address_or_no_list_is_unverified(): void {
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( null, self::GPTBOT, false, false ) );
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( '8.8.8.8', array(), false, false ) );
	}

	public function test_a_direct_request_is_not_proxied(): void {
		$this->assertSame(
			array(
				'ip'      => '8.8.8.8',
				'proxied' => false,
			),
			AN10_Verifier::client_from( array( 'REMOTE_ADDR' => '8.8.8.8' ), '', self::CLOUDFLARE )
		);
	}

	public function test_a_public_proxy_with_a_forwarding_header_is_flagged(): void {
		foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_X_SUCURI_CLIENTIP', 'HTTP_FASTLY_CLIENT_IP' ) as $header ) {
			$client = AN10_Verifier::client_from(
				array(
					'REMOTE_ADDR' => '151.101.1.1',
					$header       => '20.171.206.7',
				),
				'',
				self::CLOUDFLARE
			);
			$this->assertSame( '151.101.1.1', $client['ip'], $header );
			$this->assertTrue( $client['proxied'], $header );
		}
	}

	public function test_the_header_the_owner_trusts_gives_the_client_address(): void {
		$this->assertSame(
			array(
				'ip'      => '20.171.206.7',
				'proxied' => false,
			),
			AN10_Verifier::client_from(
				array(
					'REMOTE_ADDR'          => '10.0.0.2',
					'HTTP_X_FORWARDED_FOR' => '20.171.206.7, 10.0.0.2',
				),
				'HTTP_X_FORWARDED_FOR',
				self::CLOUDFLARE
			)
		);
	}

	public function test_cloudflare_header_is_trusted_only_from_cloudflare(): void {
		$from_cloudflare = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'           => '173.245.48.9',
				'HTTP_CF_CONNECTING_IP' => '20.171.206.7',
			),
			'',
			self::CLOUDFLARE
		);
		$this->assertSame( '20.171.206.7', $from_cloudflare['ip'] );
		$this->assertFalse( $from_cloudflare['proxied'] );

		$forged = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'           => '8.8.8.8',
				'HTTP_CF_CONNECTING_IP' => '20.171.206.7',
			),
			'',
			self::CLOUDFLARE
		);
		$this->assertSame( '8.8.8.8', $forged['ip'] );
		$this->assertTrue( $forged['proxied'] );
	}

	public function test_a_private_address_cannot_be_seen_through(): void {
		$this->assertNull( AN10_Verifier::client_from( array( 'REMOTE_ADDR' => '172.18.0.1' ), '', self::CLOUDFLARE )['ip'] );
	}

	/**
	 * A fake GPTBot writes an OpenAI address into X-Forwarded-For; its proxy
	 * then adds the real address after it.
	 */
	public function test_an_address_written_by_the_visitor_never_passes_through_the_chosen_header(): void {
		$client = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'          => '10.0.0.2',
				'HTTP_X_FORWARDED_FOR' => '20.171.206.7, 8.8.8.8',
			),
			'HTTP_X_FORWARDED_FOR',
			self::CLOUDFLARE
		);
		$this->assertSame( '8.8.8.8', $client['ip'] );
		$this->assertSame( AN10_Store::SPOOFED, AN10_Verifier::verdict( $client['ip'], self::GPTBOT, $client['proxied'], false ) );
	}

	/**
	 * Behind Cloudflare with X-Forwarded-For chosen in Settings, the case a
	 * tester reported.
	 */
	public function test_cloudflare_is_read_before_the_chosen_header(): void {
		$server = array(
			'REMOTE_ADDR'           => '173.245.48.9',
			'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
			'HTTP_X_FORWARDED_FOR'  => '20.171.206.7, 8.8.8.8',
		);
		$client = AN10_Verifier::client_from( $server, 'HTTP_X_FORWARDED_FOR', self::CLOUDFLARE );
		$this->assertSame(
			array(
				'ip'      => '8.8.8.8',
				'proxied' => false,
			),
			$client
		);
		$this->assertSame( AN10_Store::SPOOFED, AN10_Verifier::verdict( $client['ip'], self::GPTBOT, $client['proxied'], false ) );

		// Before Cloudflare's ranges are downloaded, the chosen header still gives the real address.
		$this->assertSame( '8.8.8.8', AN10_Verifier::client_from( $server, 'HTTP_X_FORWARDED_FOR', array() )['ip'] );
	}

	public function test_a_real_crawler_behind_cloudflare_and_the_chosen_header_is_verified(): void {
		$client = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'           => '173.245.48.9',
				'HTTP_CF_CONNECTING_IP' => '20.171.206.7',
				'HTTP_X_FORWARDED_FOR'  => '20.171.206.7',
			),
			'HTTP_X_FORWARDED_FOR',
			self::CLOUDFLARE
		);
		$this->assertSame( AN10_Store::VERIFIED, AN10_Verifier::verdict( $client['ip'], self::GPTBOT, $client['proxied'], false ) );
	}

	public function test_the_nearest_client_steps_over_known_proxies_only(): void {
		$this->assertSame( '8.8.8.8', AN10_Verifier::nearest_client( '20.171.206.7, 8.8.8.8, 173.245.48.9, 10.0.0.2', self::CLOUDFLARE ) );
		$this->assertSame( '20.171.206.7', AN10_Verifier::nearest_client( ' 20.171.206.7 ', self::CLOUDFLARE ) );
		$this->assertNull( AN10_Verifier::nearest_client( '20.171.206.7, unknown', self::CLOUDFLARE ) );
		$this->assertNull( AN10_Verifier::nearest_client( '10.0.0.3, 10.0.0.2', self::CLOUDFLARE ) );
	}
}
