<?php
use PHPUnit\Framework\TestCase;

/**
 * CSV export row shaping (pure, no database).
 */
final class StoreCsvTest extends TestCase {

	public function test_header_matches_row_width(): void {
		$header = AN10_Store::csv_header();
		$this->assertSame( array( 'time', 'crawler', 'kind', 'status', 'path', 'http_status' ), $header );
		$row = AN10_Store::csv_row(
			array(
				'created_at'   => '2026-11-22 08:00:00',
				'kind'         => AN10_Store::KIND_CRAWLER,
				'agent'        => 'GPTBot',
				'verification' => AN10_Store::VERIFIED,
				'post_id'      => 12,
				'path'         => '/pour-over-coffee',
				'status'       => 200,
			)
		);
		$this->assertCount( count( $header ), $row );
	}

	public function test_verified_crawler_row(): void {
		$this->assertSame(
			array( '2026-11-22 08:00:00', 'GPTBot', 'crawler', 'verified', '/pour-over-coffee', '200' ),
			AN10_Store::csv_row(
				array(
					'created_at'   => '2026-11-22 08:00:00',
					'kind'         => AN10_Store::KIND_CRAWLER,
					'agent'        => 'GPTBot',
					'verification' => AN10_Store::VERIFIED,
					'post_id'      => 12,
					'path'         => '/pour-over-coffee',
					'status'       => 200,
				)
			)
		);
	}

	public function test_spoofed_and_unverified_labels(): void {
		$this->assertSame( 'spoofed', AN10_Store::csv_row( array( 'kind' => AN10_Store::KIND_CRAWLER, 'verification' => AN10_Store::SPOOFED ) )[3] );
		$this->assertSame( 'unverified', AN10_Store::csv_row( array( 'kind' => AN10_Store::KIND_CRAWLER, 'verification' => AN10_Store::UNVERIFIED ) )[3] );
	}

	public function test_referral_row_has_no_verification_label(): void {
		$row = AN10_Store::csv_row(
			array(
				'created_at'   => '2026-11-22 09:30:00',
				'kind'         => AN10_Store::KIND_REFERRAL,
				'agent'        => 'ChatGPT',
				'verification' => AN10_Store::NOT_APPLICABLE,
				'path'         => '/cold-brew',
				'status'       => 200,
			)
		);
		$this->assertSame( array( '2026-11-22 09:30:00', 'ChatGPT', 'referral', '', '/cold-brew', '200' ), $row );
	}

	public function test_labels_are_pure(): void {
		$this->assertSame( 'crawler', AN10_Store::kind_label( AN10_Store::KIND_CRAWLER ) );
		$this->assertSame( 'referral', AN10_Store::kind_label( AN10_Store::KIND_REFERRAL ) );
		$this->assertSame( 'verified', AN10_Store::verification_label( AN10_Store::VERIFIED ) );
		$this->assertSame( '', AN10_Store::verification_label( AN10_Store::NOT_APPLICABLE ) );
	}

	public function test_missing_fields_default_safely(): void {
		$this->assertSame( array( '', '', 'crawler', '', '', '0' ), AN10_Store::csv_row( array() ) );
	}
}
