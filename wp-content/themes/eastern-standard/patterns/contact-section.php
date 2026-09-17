<?php
/**
 * Title: Contact section
 * Slug: eastern-standard/contact-section
 * Categories: eastern-standard
 * Description: Contact block beside the studio's address and direct lines.
 *
 * @package EasternStandard
 */

?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"},"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns alignwide" style="margin-top:var(--wp--preset--spacing--50)">
	<!-- wp:column {"width":"38%"} -->
	<div class="wp-block-column" style="flex-basis:38%">
		<!-- wp:heading {"level":2,"fontSize":"x-large"} -->
		<h2 class="wp-block-heading has-x-large-font-size">Talk to the studio</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"textColor":"muted"} -->
		<p class="has-muted-color has-text-color">If you would rather book a time than write a message, the consultation calendar is on its own page.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><a href="/book-a-consultation/">Book a consultation →</a></p>
		<!-- /wp:paragraph -->

		<!-- wp:separator {"className":"is-style-wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
		<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"/>
		<!-- /wp:separator -->

		<!-- wp:paragraph {"className":"ess-label","textColor":"muted"} -->
			<p class="ess-label has-muted-color has-text-color">Studio</p>
			<!-- /wp:paragraph -->
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">168 Wythe Avenue<br>Brooklyn, NY 11249</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"ess-label","textColor":"muted","style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->
		<p class="ess-label has-muted-color has-text-color" style="margin-top:var(--wp--preset--spacing--20)">Direct</p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">studio@easternstandard.test<br>(718) 555-0143</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:ess/contact-form {"heading":"Tell us about the project","intro":"Drawings, a site address or a rough square footage all help. We reply within one business day."} /-->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
