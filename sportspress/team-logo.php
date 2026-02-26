<?php
/**
 * Team Logo
 *
 * @author      ThemeBoy
 * @package     SportsPress/Templates
 * @version     1.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( get_option( 'sportspress_team_show_logo', 'yes' ) === 'no' ) {
	return;
}

if ( ! isset( $id ) ) {
	$id = get_the_ID();
}

if ( has_post_thumbnail( $id ) ) :
	?>
	<div class="sp-template sp-template-team-logo sp-template-logo sp-team-logo">
		<?php
if ( has_post_thumbnail( $id ) ) {
    echo get_the_post_thumbnail( $id, 'full' );
} else {
    echo '<img src="https://fc-deparas.be/wp-content/uploads/2025/06/team-logo-standard.png" alt="Standaar-logo">';
}
?>
	</div>
	<?php
endif;
