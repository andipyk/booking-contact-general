<?php
/**
 * Title: Project showcase
 * Slug: eastern-standard/project-showcase
 * Categories: eastern-standard
 * Description: The three most recent projects, with location bound from meta.
 *
 * @package EasternStandard
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--60)">
	<!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"level":2,"fontSize":"xx-large"} -->
		<h2 class="wp-block-heading has-xx-large-font-size">Recent work</h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><a href="/projects/">All projects →</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":4,"query":{"perPage":3,"pages":0,"offset":0,"postType":"ess_project","order":"desc","orderBy":"date","inherit":false},"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-query" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:post-template {"className":"ess-grid","layout":{"type":"grid","minimumColumnWidth":"21rem"}} -->
			<!-- wp:group {"className":"ess-card","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
			<div class="wp-block-group ess-card">
				<!-- wp:post-featured-image {"isLink":true,"sizeSlug":"ess-card","style":{"border":{"radius":"12px"}},"className":"ess-card__media"} /-->
				<!-- wp:post-terms {"term":"ess_sector","fontSize":"x-small"} /-->
				<!-- wp:post-title {"isLink":true,"level":3,"fontSize":"large"} /-->
				<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"ess_location"}}}},"textColor":"muted","fontSize":"small"} -->
				<p class="has-muted-color has-text-color has-small-font-size">—</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
