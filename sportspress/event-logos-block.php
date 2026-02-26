<div class="sp-template sp-template-event-logos sp-template-event-blocks sp-template-event-logos-block" style="margin-bottom: 0em;">
	<div class="sp-event-row">
		<div class="sp-event-top">
	<?php
	$j = 0;
	foreach ( $teams as $team ) :
		$j++;

		if ( has_post_thumbnail( $team ) ) {
			$logo = get_the_post_thumbnail( $team, 'medium' );
		} else {
			$logo = '';
		}

		$team_html = '<span class="team-logo logo-' . ( $j % 2 ? 'odd' : 'even' ) . '" title="' . get_the_title( $team ) . '">' . $logo . '</span>';

		if ( $j === 1 ) {
			echo '<div class="sp-team sp-team-left">' . wp_kses_post( $team_html ) . '</div>';
		}
	endforeach;
	?>

<div class="sp-result">
	<?php
	// Bepaal tijdsstatus vóór alle weergave
	$event_time = strtotime( get_the_time( 'Y-m-d H:i:s', $id ) );
	$current_time = current_time( 'timestamp' );
	$event_end_time = $event_time + ( 90 * 60 ); // 90 minuten duurtijd

	if ( $current_time < $event_time ) {
		$status = esc_attr__( 'Preview', 'sportspress' );
	} elseif ( $current_time >= $event_time && $current_time <= $event_end_time ) {
		$status = esc_attr__( 'Bezig', 'sportspress' );
	} else {
		$status = esc_attr__( 'Gespeeld', 'sportspress' ); // Na 90 minuten
	}

	// Toon resultaat of tijd
	if ( $show_results && ! empty( $results ) ) {
		echo '<span class="sp-result-text">' . wp_kses_post( implode( ' – ', apply_filters( 'sportspress_event_blocks_team_result_or_time', $results, $id ) ) ) . '</span>';
		$status = esc_attr__( 'Fulltime', 'sportspress' ); // Overschrijft 'Bezig'
	} elseif ( $show_time ) {
		echo '<span class="sp-result">' . wp_kses_post( apply_filters( 'sportspress_event_time', sp_get_time( $id ), $id ) ) . '</span>';
	}
	?>
</div>


	<?php
	// tweede team
	if ( isset( $teams[1] ) ) {
		$team = $teams[1];
		if ( has_post_thumbnail( $team ) ) {
			$logo = get_the_post_thumbnail( $team, 'medium' );
		} else {
			$logo = '';
		}
		$team_html = '<span class="team-logo logo-even" title="' . get_the_title( $team ) . '">' . $logo . '</span>';
		echo '<div class="sp-team sp-team-right">' . wp_kses_post( $team_html ) . '</div>';
	}
	?>
</div>

		<div class="sp-event-bottom">
	<h4 class="sp-event-title"><?php echo esc_html( get_the_title( $id ) ); ?></h4>
	<div class="sp-event-meta">
		<h3 class="sp-event-date" datetime="<?php echo esc_attr( get_the_time( 'Y-m-d H:i:s', $id ) ); ?>">
			<?php echo esc_html( get_the_time( get_option( 'date_format' ), $id ) ); ?> | <?php echo esc_html( apply_filters( 'sportspress_event_logos_status', $status, $id ) ); ?>
		</h3>
	
</div>
</div>
</div>
</div>
