<?php
/**
 * Title: Services grid
 * Slug: eastern-standard/services-grid
 * Categories: eastern-standard
 * Description: Query loop over the service post type, with lead time bound from meta.
 *
 * @package EasternStandard
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--60)">
	<!-- wp:heading {"level":2,"fontSize":"xx-large"} -->
	<h2 class="wp-block-heading has-xx-large-font-size">What the studio does</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"textColor":"muted","fontSize":"large"} -->
	<p class="has-muted-color has-text-color has-large-font-size">Most projects use two or three of these together. The lead times are the ones we actually hold to.</p>
	<!-- /wp:paragraph -->

	<!-- wp:query {"queryId":3,"query":{"perPage":6,"pages":0,"offset":0,"postType":"ess_service","order":"asc","orderBy":"title","inherit":false},"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-query" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:post-template {"layout":{"type":"grid","minimumColumnWidth":"19rem"}} -->
			<!-- wp:group {"className":"ess-service-card","style":{"spacing":{"blockGap":"var:preset|spacing|20","padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"color":"var:preset|color|line","width":"1px","radius":"12px"}},"backgroundColor":"surface","layout":{"type":"default"}} -->
			<div class="wp-block-group ess-service-card has-surface-background-color has-background" style="border-color:var(--wp--preset--color--line);border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30)">
				<!-- wp:post-title {"isLink":true,"level":3,"fontSize":"large"} /-->
				<!-- wp:post-excerpt {"excerptLength":24,"textColor":"muted","fontSize":"small"} /-->
				<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"ess_lead_time"}}}},"className":"ess-leadtime","textColor":"accent","fontSize":"x-small"} -->
				<p class="ess-leadtime has-accent-color has-text-color has-x-small-font-size">—</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
