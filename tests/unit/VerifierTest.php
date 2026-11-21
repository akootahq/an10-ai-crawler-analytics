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

		// Before Cloudflare's ranges are downloaded, nothing says the request
		// came from Cloudflare, so neither header is read.
		$this->assertNull( AN10_Verifier::client_from( $server, 'HTTP_X_FORWARDED_FOR', array() )['ip'] );
	}

	/**
	 * The owner trusts X-Forwarded-For, and someone sends it straight to the
	 * server, past the proxy, with an OpenAI address in it.
	 */
	public function test_the_chosen_header_sent_straight_to_the_server_is_ignored(): void {
		$client = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'          => '8.8.8.8',
				'HTTP_X_FORWARDED_FOR' => '20.171.206.7',
			),
			'HTTP_X_FORWARDED_FOR',
			self::CLOUDFLARE
		);
		$this->assertSame(
			array(
				'ip'      => '8.8.8.8',
				'proxied' => true,
			),
			$client
		);
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( $client['ip'], self::GPTBOT, $client['proxied'], false ) );
	}

	public function test_the_chosen_header_is_read_from_the_owners_public_proxy(): void {
		$proxy  = array( '203.0.113.0/24' );
		$client = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'          => '203.0.113.5',
				'HTTP_X_FORWARDED_FOR' => '20.171.206.7, 203.0.113.9',
			),
			'HTTP_X_FORWARDED_FOR',
			self::CLOUDFLARE,
			$proxy
		);
		$this->assertSame( '20.171.206.7', $client['ip'] );
		$this->assertSame( AN10_Store::VERIFIED, AN10_Verifier::verdict( $client['ip'], self::GPTBOT, $client['proxied'], false ) );

		// The same proxy without its addresses in Settings: unknown, never verified.
		$unlisted = AN10_Verifier::client_from(
			array(
				'REMOTE_ADDR'          => '203.0.113.5',
				'HTTP_X_FORWARDED_FOR' => '20.171.206.7',
			),
			'HTTP_X_FORWARDED_FOR',
			self::CLOUDFLARE
		);
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( $unlisted['ip'], self::GPTBOT, $unlisted['proxied'], false ) );
	}

	/**
	 * A real Claude-User fetch a tester saw came from 160.79.104.0/23, which
	 * Anthropic's own network announces and bots.json did not list.
	 */
	public function test_anthropics_own_network_counts_as_its_list(): void {
		$bots    = 'https://claude.com/crawling/bots.json';
		$network = AN10_Verifier::OPERATOR_NETWORKS[ $bots ];
		$now     = 1795300000;
		$lists   = array(
			$bots    => array(
				'ranges'     => array( '216.73.216.0/22' ),
				'fetched_at' => $now - 3600,
			),
			$network => array(
				'ranges'     => array( '160.79.104.0/23', '2607:6bc0::/48' ),
				'fetched_at' => $now - 3600,
			),
		);
		$known = AN10_Verifier::known_addresses( $bots, $lists, $now );
		$this->assertFalse( $known['stale'] );
		$this->assertSame( AN10_Store::VERIFIED, AN10_Verifier::verdict( '160.79.104.20', $known['ranges'], false, $known['stale'] ) );
		$this->assertSame( AN10_Store::VERIFIED, AN10_Verifier::verdict( '216.73.216.9', $known['ranges'], false, $known['stale'] ) );
		$this->assertSame( AN10_Store::SPOOFED, AN10_Verifier::verdict( '8.8.8.8', $known['ranges'], false, $known['stale'] ) );

		// Until the network's ranges are in, a miss is only Unverified.
		unset( $lists[ $network ] );
		$partial = AN10_Verifier::known_addresses( $bots, $lists, $now );
		$this->assertTrue( $partial['stale'] );
		$this->assertSame( AN10_Store::UNVERIFIED, AN10_Verifier::verdict( '160.79.104.20', $partial['ranges'], false, $partial['stale'] ) );
	}

	public function test_an_operator_without_its_own_network_uses_its_list_alone(): void {
		$url   = 'https://openai.com/gptbot.json';
		$known = AN10_Verifier::known_addresses(
			$url,
			array(
				$url => array(
					'ranges'     => self::GPTBOT,
					'fetched_at' => 1795300000,
				),
			),
			1795300000
		);
		$this->assertSame(
			array(
				'ranges' => self::GPTBOT,
				'stale'  => false,
			),
			$known
		);
	}

	public function test_every_operator_network_completes_a_list_the_plugin_downloads(): void {
		foreach ( AN10_Verifier::OPERATOR_NETWORKS as $list => $network ) {
			$this->assertContains( $list, AN10_Verifier::list_urls() );
			$this->assertContains( $network, AN10_Verifier::list_urls() );
		}
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
