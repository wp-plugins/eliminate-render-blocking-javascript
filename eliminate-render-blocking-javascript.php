<?php
/**
 * @package Eliminate Render-Blocking JavaScript - Wordpress Plugin
 */
/*
Plugin Name: Eliminate Render-Blocking JavaScript
Plugin URI: erbj
Description: Render-Blocking JavaScript is keeping your page from loading faster. This affects both google ranking and loading speed. Remove Render-Blocking JavaScript and fix Google PageSpeed Insights error.
Version: 1.0.0
Author: erbj
Author URI: erbj
*/

if ( !is_admin() && !strpos ( $_SERVER['REQUEST_URI'], 'wp-login.php' ) )
{
	$memstring = ini_get ( 'memory_limit' );
	if ( !empty ( $memstring ) )
	{
		$memory = substr ( $memstring, 0, -1 );
		$usedm = round( memory_get_usage() / 1024 / 1024 );
		$leftm = $memory - $usedm;
	}
	if ( !empty ( $leftm ) && $leftm > 16 )
	{
		function search_render_scripts()
		{
			ob_start( 'eliminate_render_blocking_javascript' );
		}
		
		function eliminate_render_blocking_javascript( $html )
		{
			if ( !function_exists ( 'curl_version' ) )
			{
				return $html;
			}
			if ( is_404() )
			{
				return $html;
			}
		
			$renderjs = get_option ( 'erbj_on' );
			$cache = get_option ( 'erbj_cache' );
			$minifyjs = get_option ( 'erbj_minify' );
		
			if ( !empty ( $renderjs ) )
			{
				$themedir = get_template_directory();
				$themearray = explode( '/', $themedir );
				$themename = strtolower ( trim( end( $themearray ) ) );
					
				$dbtheme = get_option ( 'erbj_theme_name' );
				if ( $dbtheme )
				{
					if ( $dbtheme != $themename )
					{
						update_option ( 'erbj_theme_name', $themename );
						global $wpdb;
						$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'erbj_p_id_%'");
					}
				}
				else
				{
					add_option ( 'erbj_theme_name', $themename );
				}
					
				global $wp_query;
				$pageid = $wp_query->get_queried_object_id();
				$dbcache = 'erbj_p_id_' . $pageid;
		
				$jsarray = explode( "<script", $html );
				$rawhtml = '';
				$renderblocks = array();
					
				foreach ( $jsarray as $key => $row )
				{
					if ( !empty ( $row ) && 0 != $key )
					{
						$portion = explode( '</script>', $row );
						$scriptstart = strpos( $portion[0], '>' ) + 1;
						$script = substr( $portion[0], $scriptstart );
						if( !empty ( $script ) )
						{
							if ( !strpos ( $script, 'document.write' ) )
							{
								$renderblocks[] = array( 'code' => $script, 'type' => 'inline' );
							}
							else
							{
								$rawhtml .= '<script type="text/javascript">';
								$rawhtml .= $script;
								$rawhtml .= '</script>';
							}
						}
						else
						{
							$srcstart = strpos( strtolower( $portion[0]), 'src=' );
							if( $srcstart )
							{
								$srcstart += 5;
							}
							if( !$srcstart )
							{
								$srcstart = strpos( strtolower($portion[0] ), 'src =' );
								if( $srcstart )
								{
									$srcstart += 6;
								}
							}
							if( $srcstart )
							{
								$srcstring = substr( $portion[0], $srcstart );
								$srcstop = strpos( $srcstring, '"' );
								if( !$srcstop )
								{
									$srcstop = strpos( $srcstring, "'" );
								}
								if( $srcstop )
								{
									$renderblocks[] = array( 'code' => substr( $srcstring, 0, $srcstop ), 'type' => 'src' );
								}
							}
						}
						$rawhtml .= $portion[1];
					}
					elseif ( 0 == $key )
					{
						$rawhtml .= $row;
					}
					unset( $jsarray[$key] );
				}
					
				if ( !get_option ( $dbcache ) || 0 == $cache )
				{
					if( count( $renderblocks) > 0 )
					{
						$minjs = '';
						$inlinejs = '';
						foreach( $renderblocks as $block )
						{
							if( 'src' == $block['type'] )
							{
								$jsurl = html_entity_decode( $block['code'] );
									
								if ( 0 == strpos ( $jsurl, '//' ) )
								{
									$jsurl = substr ( $jsurl , 2 );
								}
									
								$ch = curl_init();
								$timeout = 5;
								curl_setopt ( $ch, CURLOPT_URL, $jsurl );
								curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
								curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
								curl_setopt ( $ch, CURLOPT_USERAGENT, 'Eliminate-Render-Blocking-Javascript' );
								$content = curl_exec ( $ch );
								curl_close ( $ch );
								if ( $content )
								{
									if ( is_array( $content ) )
									{
										$content = implode ( $content );
									}
									if ( strpos ( $content, 'document.write' ) )
									{
										$inlinejs .= '<script src="' . $jsurl . '">';
										$inlinejs .= '</script>';
									}
									else
									{
										$minjs .= $content . PHP_EOL;
									}
								}
							}
							elseif( 'inline' == $block['type'] )
							{
								$minjs .= $block['code'] . PHP_EOL;
							}
						}
		
						if( !empty ( $minjs ) )
						{
							if ( !empty ( $minifyjs ) )
							{
								require_once 'min/JSMin.php';
								$minjs = JSMin::minify ( $minjs );
							}
								
							if ( !get_option ( $dbcache ) )
							{
								add_option ( $dbcache, $minjs );
							}
							else
							{
								update_option ( $dbcache, $minjs );
							}
						}
					}
				}
					
				session_start();
				$_SESSION['erbj_cache'] = get_option ( $dbcache );
					
				$completejs = '';
				$inlinecache = $dbcache.'_inline';
		
				if ( !empty ( $inlinejs ) )
				{
					if ( get_option ( $inlinecache ) )
					{
						update_option ( $inlinecache, $inlinejs );
					}
					else
					{
						add_option ( $inlinecache, $inlinejs );
					}
				}
		
				if ( get_option ( $inlinecache ) )
				{
					$completejs .= get_option ( $inlinecache );
				}
					
				$srcjs = plugins_url() . '/eliminate-render-blocking-javascript/deferred.php';
				$completejs .= '<script type="text/javascript">';
				$completejs .= 'function downloadJSAtOnload() {';
				$completejs .= 'var element = document.createElement("script");';
				$completejs .= 'element.src = "' . $srcjs . '";';
				$completejs .= 'document.body.appendChild(element);';
				$completejs .= '};';
				$completejs .= 'if (window.addEventListener)';
				$completejs .= 'window.addEventListener("load", downloadJSAtOnload, false);';
				$completejs .= 'else if (window.attachEvent)';
				$completejs .= 'window.attachEvent("onload", downloadJSAtOnload);';
				$completejs .= 'else window.onload = downloadJSAtOnload;';
				$completejs .= '</script>';
				$rawhtml = str_replace ( "</body>", $completejs . "</body>", $rawhtml );
				return $rawhtml;
			}
			else
			{
				return $html;
			}
		}
		add_action( 'wp_loaded', 'search_render_scripts' );
	}
}
else
{
	function eliminate_render_blocking_javascript_activate() 
	{
		add_option ( 'erbj_on', 1 );
		add_option ( 'erbj_cache', 1 );
		add_option ( 'erbj_minify', 1 );
	}
	register_activation_hook( __FILE__, 'eliminate_render_blocking_javascript_activate' );
	
	function eliminate_render_blocking_javascript_menu() 
	{
		add_options_page( 'Eliminate Render Blocking Javascript Options', 'Eliminate Render Blocking Javascript', 'manage_options', 'eliminate_render_blocking_javascript', 'eliminate_render_blocking_javascript_options' );
	}
	
	function eliminate_render_blocking_javascript_options() 
	{
		if ( !current_user_can( 'manage_options' ) )  
		{
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		if ( !empty ( $_POST['erbj_update'] ) )
		{
			$erbj_on = !empty ( $_POST['erbj_on'] ) ? 1 : 0;
			$erbj_cache = !empty ( $_POST['erbj_cache'] ) ? 1 : 0;
			$erbj_minify = !empty ( $_POST['erbj_minify'] ) ? 1 : 0;
			if( ( get_option ( 'erbj_minify' ) != $erbj_minify ) )
			{
				global $wpdb;
				$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'erbj_p_id_%'");
				$deleted = 1;
			}			
			update_option ( 'erbj_on', $erbj_on );
			update_option ( 'erbj_cache', $erbj_cache );
			update_option ( 'erbj_minify', $erbj_minify );
			$updated = 1;
		}
		if ( !empty ( $_POST['erbj_clearcache'] ) )
		{
			global $wpdb;
			$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'erbj_p_id_%'");
			$deleted = 1;
		}
		
		?>
		<div class="wrap">
			<h3>Eliminate Render-Blocking JavaScript Settings</h3>
			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST">
				<input type="hidden" value="1" name="erbj_update">
				<ul>
					<li>
			    		<input type="checkbox" value="1" name="erbj_on" <?php if ( 1 == get_option ( 'erbj_on' ) ) { echo 'checked'; } ?> /> Enabled
			    	</li>
					<li>
			    		<input type="checkbox" value="1" name="erbj_cache" <?php if ( 1 == get_option ( 'erbj_cache' ) ) { echo 'checked'; } ?> /> Cache Deferred JavaScript
			    	</li>
			    	<li>
			    		<input type="checkbox" value="1" name="erbj_minify" <?php if ( 1 == get_option ( 'erbj_minify' ) ) { echo 'checked'; } ?> /> Minify Deferred JavaScript
			    	</li>
				</ul>
	   			<input type="submit" class="button-primary" value="Save Settings">
			</form>
			<?php if ( !empty ( $updated ) ): ?>
   				<p>Settings were updated successfully!</p>
   			<?php else : ?>
   				<br>
   			<?php endif; ?>
			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST">
				<input type="hidden" value="1" name="erbj_clearcache">
	   			<input type="submit" class="button" value="Empty Deferred Cache" name="">
	   			<?php if ( !empty( $deleted ) ) : ?>
	   				<p>Cache was emptied!</p>
	   			<?php endif; ?>
			</form>
			<?php if ( !function_exists('curl_version') ) : ?>
				<p>Php extension curl must be loaded!</p>
			<?php endif; ?>
		</div>
		<?php 
	}
	
	add_action( 'admin_menu', 'eliminate_render_blocking_javascript_menu' );
}

?>