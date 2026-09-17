<?php
/**
 * Custom post types.
 *
 * Projects and services are public content the site renders and search engines
 * index. Bookings and enquiries are private studio records: they are never
 * queryable from the front end and are not exposed on the default REST
 * namespace — the plugin serves its own scoped routes instead.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Post_Types {

	public const PROJECT = 'ess_project';
	public const SERVICE = 'ess_service';
	public const BOOKING = 'ess_booking';
	public const INQUIRY = 'ess_inquiry';

	public const TAX_SECTOR     = 'ess_sector';
	public const TAX_DISCIPLINE = 'ess_discipline';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		self::register_taxonomies();
		self::register_projects();
		self::register_services();
		self::register_bookings();
		self::register_inquiries();
	}

	private static function register_taxonomies(): void {
		register_taxonomy(
			self::TAX_SECTOR,
			[ self::PROJECT ],
			[
				'label'             => __( 'Sectors', 'ess-core' ),
				'labels'            => [
					'singular_name' => __( 'Sector', 'ess-core' ),
					'menu_name'     => __( 'Sectors', 'ess-core' ),
				],
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => 'sector' ],
			]
		);

		register_taxonomy(
			self::TAX_DISCIPLINE,
			[ self::PROJECT, self::SERVICE ],
			[
				'label'             => __( 'Disciplines', 'ess-core' ),
				'labels'            => [
					'singular_name' => __( 'Discipline', 'ess-core' ),
					'menu_name'     => __( 'Disciplines', 'ess-core' ),
				],
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => 'discipline' ],
			]
		);
	}

	private static function register_projects(): void {
		register_post_type(
			self::PROJECT,
			[
				'label'        => __( 'Projects', 'ess-core' ),
				'labels'       => [
					'singular_name' => __( 'Project', 'ess-core' ),
					'add_new_item'  => __( 'Add Project', 'ess-core' ),
					'edit_item'     => __( 'Edit Project', 'ess-core' ),
					'menu_name'     => __( 'Projects', 'ess-core' ),
				],
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-building',
				'menu_position' => 20,
				'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
				'has_archive'  => 'projects',
				'rewrite'      => [ 'slug' => 'projects', 'with_front' => false ],
				'taxonomies'   => [ self::TAX_SECTOR, self::TAX_DISCIPLINE ],
			]
		);
	}

	private static function register_services(): void {
		register_post_type(
			self::SERVICE,
			[
				'label'        => __( 'Services', 'ess-core' ),
				'labels'       => [
					'singular_name' => __( 'Service', 'ess-core' ),
					'add_new_item'  => __( 'Add Service', 'ess-core' ),
					'edit_item'     => __( 'Edit Service', 'ess-core' ),
					'menu_name'     => __( 'Services', 'ess-core' ),
				],
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-hammer',
				'menu_position' => 21,
				'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ],
				'has_archive'  => 'services',
				'rewrite'      => [ 'slug' => 'services', 'with_front' => false ],
				'taxonomies'   => [ self::TAX_DISCIPLINE ],
			]
		);
	}

	private static function register_bookings(): void {
		register_post_type(
			self::BOOKING,
			[
				'label'               => __( 'Bookings', 'ess-core' ),
				'labels'              => [
					'singular_name' => __( 'Booking', 'ess-core' ),
					'menu_name'     => __( 'Bookings', 'ess-core' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				// Studio records stay off the default REST namespace; the
				// plugin's own scoped routes decide what may be read or written.
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-calendar-alt',
				'menu_position'       => 22,
				'supports'            => [ 'title', 'custom-fields' ],
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			]
		);
	}

	private static function register_inquiries(): void {
		register_post_type(
			self::INQUIRY,
			[
				'label'               => __( 'Enquiries', 'ess-core' ),
				'labels'              => [
					'singular_name' => __( 'Enquiry', 'ess-core' ),
					'menu_name'     => __( 'Enquiries', 'ess-core' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-email-alt',
				'menu_position'       => 23,
				'supports'            => [ 'title', 'custom-fields' ],
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			]
		);
	}
}
