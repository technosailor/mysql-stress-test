<?php

/*
Plugin Name: Mysql Stress Test
Plugin URI: https://github.com/technosailor/mysql-stress-test
Description: Specific Stress Test Results
Version: 1.0
Author: Aaron Brazell
Author URI: http://10up.com
License: MIT
*/

class AB_MySQL_Stress_Test {

	public $count;

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		$this->hooks();
	}

	public function activate() {
		for( $i = 0; $i < 10000; $i++ ) {
			$id = wp_insert_post( array(
				'post_title' => sprintf( 'Test Post %s', $i ),
				'post_status' => 'publish',
			) );

			$hash = hash( 'sha256', $id );
			add_post_meta( $id, '_mysql_stress_test_hash', $hash );
		}
		$this->count = $i;
	}

	public static function deactivate() {

		$posts = new WP_Query( array(
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_key' => '_mysql_stress_test_hash'
		) );

		foreach( $posts->posts as $id ) {
			wp_delete_post( $id, true );
		}
	}

	public function hooks() {
		add_action( 'admin_init', array( $this, 'count' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function count() {
		$posts = new WP_Query( array(
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_key' => '_mysql_stress_test_hash'
		) );

		$this->count = $posts->found_posts;
	}

	public function menu() {
		add_options_page( 'MySQL Test', 'MySQL Test', 'manage_options', 'mysql-stress-test', array( $this, 'html' ) );
	}

	public function html() {
		global $wpdb;

		$ids = $this->random_posts(0, 10000, 5000 );
		$hashes = array();
		foreach( $ids as $id ) {
			$hashes[] = hash( 'sha256', $id );;
		}

		$wpdb->timer_start();
		$scenario1 = new WP_Query( array(
			'posts_per_page'        => -1,
			'meta_query'            => array(
				array(
					'key'           => '_mysql_stress_test_hash',
					'value'         => $hashes,
					'compare'       => 'NOT IN'
				),
			),
		) );
		$scenario1_time = $wpdb->timer_stop();

		$wpdb->timer_start();
		$scneario2_ids = new WP_Query( array(
			'fields'                => 'ids',
			'posts_per_page'        => $this->count / 2,
			'orderby'               => 'rand',
			'meta_key'              => '_mysql_stress_test_hash',
		) );

		$ids = array();
		foreach( $scneario2_ids->posts as $id ) {
			$ids[] = $id;
		}

		$scenario2 = new WP_Query( array(
			'post__in'              => $ids,
		) );
		$scenario2_time = $wpdb->timer_stop();
		?>
		<div class="wrap">
			<h2>MySQL Stress Test</h2>

			<h3>Scenario Prep</h3>
			<ul>
				<li><code><?php echo $this->count ?></code> Dummy Posts have been created. On plugin deactivation, they will be deleted safely.</li>
				<li>All posts have <code>_mysql_stress_test_hash</code> meta that contains a SHA256 hash of the post ID.</li>
			</ul>

			<h3>Scenario 1</h3>
			<p>Retrieve all posts in table that have the <code>_mysql_stress_test_hash</code> hash but are not in the <code>$hashes</code> array. The hash array is randomly generated of 5000 hashes. Key area of concern is the <code>'posts_per_page' => -1</code> argument which generates an <code>SQL_CALC_FOUND_ROWS</code> in the SQL.</p>
			<pre><code>$posts = new WP_Query( array(
	'posts_per_page'        => -1,
	'meta_query'            => array(
		array(
			'key'           => '_mysql_stress_test_hash',
			'value'         => [f5ca38f748a1d6eaf726b8a42fb575c3c71f1864a8143301782de13da2d9202b], // This is an array of video IDs
			'compare'       => 'NOT IN'
		),
	),
) );</code></pre>

			<p>Execution Time: <strong><?php echo $scenario1_time ?></strong></p>

			<h3>Scenario 2</h3>
			<p>Perform a <code>WP_Query</code> with <code>'fields' => 'ids'</code> matching the same meta above. Do not use <code>'posts_per_page' => -1</code>. Take list of IDs returned and use them in a second query with <code>include</code></p>
			<pre><code>$posts_ids = new WP_Query( array(
	'fields'                => 'ids',
	'posts_per_page'        => 5000,
	'orderby'               => 'rand',
	'meta_key'              => '_mysql_stress_test_hash',
) );</code></pre>
			<p>We then take <code>$post_ids->posts</code> array of IDs and feed it to a second query:</p>
			<pre><code>$posts = new WP_Query( array(
	'post__in'              => $ids,
) );</code></pre>

			<p>Execution Time: <strong><?php echo $scenario2_time ?></strong></p>
		</div>
		<?php
	}

	public function random_posts($min, $max, $quantity) {
		$numbers = range($min, $max);
		shuffle($numbers);
		return array_slice($numbers, 0, $quantity);
	}
}
new AB_MySQL_Stress_Test;