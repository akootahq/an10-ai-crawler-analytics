<?php
use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase {

	public function test_facts_not_verdicts(): void {
		$html = '<p>One two three.</p><h2>What is AEO?</h2><p>Four five</p><h4>How it works</h4><script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebPage"},{"@type":["Organization"]}]}</script>';
		$facts = AN10_Content::analyze( $html, $html );
		$this->assertSame( 3, $facts['words_before_first_h2'] );
		$this->assertSame( 0, $facts['h1_count'] );
		$this->assertSame( 2, $facts['question_headings'] );
		$this->assertSame( array( array( 'index' => 1, 'from' => 2, 'to' => 4 ) ), $facts['skips'] );
		$this->assertSame( array( 'WebPage', 'Organization' ), $facts['jsonld_types'] );
		$this->assertSame( 0, $facts['jsonld_invalid'] );
		$this->assertSame( 10, $facts['word_count'] );
	}

	public function test_no_h2_is_unknown_not_zero(): void {
		$this->assertNull( AN10_Content::analyze( '<p>Just text</p>', '<p>Just text</p>' )['words_before_first_h2'] );
	}

	public function test_invalid_jsonld_is_counted(): void {
		$this->assertSame( array( array(), 1 ), AN10_Content::jsonld( '<script type="application/ld+json">{oops</script>' ) );
	}

	public function test_proposal_removes_skips_and_content_h1_keeping_nesting(): void {
		$this->assertSame( array( 2, 3, 3, 2 ), AN10_Content::propose_levels( array( 2, 4, 4, 2 ) ) );
		$this->assertSame( array( 2, 2, 3 ), AN10_Content::propose_levels( array( 1, 2, 3 ) ) );
		$this->assertSame( array( 2, 3, 4 ), AN10_Content::propose_levels( array( 3, 4, 6 ) ) );
		$this->assertSame( array( 2, 3, 2 ), AN10_Content::propose_levels( array( 2, 3, 2 ) ) );
		$this->assertSame( array( 2, 3, 4, 3, 2 ), AN10_Content::propose_levels( array( 2, 4, 6, 4, 2 ) ) );
	}

	public function test_rewrites_block_markup_and_classic_html(): void {
		$blocks = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">A</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:heading {\"level\":4,\"className\":\"x\"} -->\n<h4 class=\"wp-block-heading x\">B</h4>\n<!-- /wp:heading -->\n"
			. "<p>tail</p><h5>C</h5>";
		$out    = AN10_Content::rewrite_headings( $blocks, array( 2, 3, 2 ) );
		$this->assertStringContainsString( "<!-- wp:heading {\"level\":3,\"className\":\"x\"} -->\n<h3 class=\"wp-block-heading x\">B</h3>", $out );
		$this->assertStringContainsString( '<h2>C</h2>', $out );
		$this->assertStringContainsString( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">A</h2>", $out );
		$this->assertStringContainsString( '<p>tail</p>', $out );
	}

	public function test_level_two_drops_the_attribute(): void {
		$in  = "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">B</h4>\n<!-- /wp:heading -->";
		$out = AN10_Content::rewrite_headings( $in, array( 2 ) );
		$this->assertSame( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">B</h2>\n<!-- /wp:heading -->", $out );
	}

	public function test_refuses_when_headings_changed_since_the_proposal(): void {
		$this->assertNull( AN10_Content::rewrite_headings( '<h2>A</h2>', array( 2, 3 ) ) );
	}

	/**
	 * A tester's post: a Kadence "Quick Answer" box whose h3 sits right after
	 * the title, before the first h2.
	 */
	private const KADENCE = "<!-- wp:kadence/advancedheading {\"level\":3,\"uniqueID\":\"_qa1\"} -->\n"
		. "<h3 class=\"kt-adv-heading_qa1 wp-block-kadence-advancedheading\">What is a virtual homelab?</h3>\n"
		. "<!-- /wp:kadence/advancedheading -->\n\n"
		. "<!-- wp:paragraph -->\n<p>A short answer.</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Why build one</h2>\n<!-- /wp:heading -->";

	public function test_a_heading_in_another_plugins_block_is_named_and_not_applied(): void {
		$facts = AN10_Content::analyze( self::KADENCE, self::KADENCE );
		$this->assertSame( array( array( 'index' => 0, 'from' => 1, 'to' => 3 ) ), $facts['skips'] );
		$this->assertSame(
			array( 'text' => 'What is a virtual homelab?', 'from' => 1, 'to' => 3, 'skipped' => 'h2', 'count' => 1 ),
			AN10_Content::first_skip( $facts['headings'], $facts['skips'] )
		);

		$proposed = AN10_Content::propose_levels( array_column( $facts['headings'], 'level' ) );
		$this->assertSame( array( 2, 2 ), $proposed );
		$this->assertSame(
			array(
				array( 'index' => 0, 'text' => 'What is a virtual homelab?', 'block' => 'kadence/advancedheading', 'from' => 3, 'to' => 2 ),
			),
			AN10_Content::manual_changes( self::KADENCE, $proposed )
		);
		$this->assertNull( AN10_Content::rewrite_headings( self::KADENCE, $proposed ) );
	}

	public function test_core_classic_and_html_headings_are_still_rewritten(): void {
		$core = "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">A</h4>\n<!-- /wp:heading -->";
		$this->assertSame( array(), AN10_Content::manual_changes( $core, array( 2 ) ) );
		$this->assertStringContainsString( '<h2 class="wp-block-heading">A</h2>', (string) AN10_Content::rewrite_headings( $core, array( 2 ) ) );

		$this->assertSame( '<p>x</p><h2>B</h2>', AN10_Content::rewrite_headings( '<p>x</p><h4>B</h4>', array( 2 ) ) );

		$html = "<!-- wp:html -->\n<h4>C</h4>\n<!-- /wp:html -->";
		$this->assertSame( "<!-- wp:html -->\n<h2>C</h2>\n<!-- /wp:html -->", AN10_Content::rewrite_headings( $html, array( 2 ) ) );

		// A core heading nested in a group is still a core heading block.
		$nested = "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">D</h4>\n<!-- /wp:heading --></div>\n<!-- /wp:group -->";
		$this->assertSame( array( 'core/heading' ), AN10_Content::heading_blocks( $nested ) );
		$this->assertStringContainsString( '<h2 class="wp-block-heading">D</h2>', (string) AN10_Content::rewrite_headings( $nested, array( 2 ) ) );
	}

	public function test_only_core_headings_change_and_the_kadence_one_is_left_alone(): void {
		$mixed = "<!-- wp:kadence/advancedheading {\"level\":2,\"uniqueID\":\"_k\"} -->\n<h2 class=\"kt-adv-heading_k\">Overview</h2>\n<!-- /wp:kadence/advancedheading -->\n\n"
			. "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">Details</h4>\n<!-- /wp:heading -->";
		$proposed = AN10_Content::propose_levels( array( 2, 4 ) );
		$this->assertSame( array( 2, 3 ), $proposed );
		$this->assertSame( array(), AN10_Content::manual_changes( $mixed, $proposed ) );
		$out = (string) AN10_Content::rewrite_headings( $mixed, $proposed );
		$this->assertStringContainsString( "<!-- wp:kadence/advancedheading {\"level\":2,\"uniqueID\":\"_k\"} -->\n<h2 class=\"kt-adv-heading_k\">Overview</h2>", $out );
		$this->assertStringContainsString( "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Details</h3>", $out );
	}

	public function test_skip_after_an_h2_names_both_levels(): void {
		$headings = array(
			array( 'level' => 2, 'text' => 'Intro' ),
			array( 'level' => 5, 'text' => 'Deep' ),
		);
		$this->assertSame(
			array( 'text' => 'Deep', 'from' => 2, 'to' => 5, 'skipped' => 'h3 to h4', 'count' => 2 ),
			AN10_Content::first_skip( $headings, AN10_Content::skips( array( 2, 5 ) ) )
		);
		$this->assertNull( AN10_Content::first_skip( $headings, array() ) );
	}
}
