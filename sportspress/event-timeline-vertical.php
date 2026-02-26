<?php
/**
 * Timeline
 *
 * @author 		ThemeBoy
 * @package 	SportsPress_Timelines
 * @version   2.6
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! isset( $id ) )
	$id = get_the_ID();

// Get linear timeline from event
$event = new SP_Event( $id );
$timeline = $event->timeline( false, true );
	// Bepaal tijdsstatus vóór alle weergave
	$event_time = strtotime( get_the_time( 'Y-m-d H:i:s', $id ) );
	$current_time = current_time( 'timestamp' );
	$event_end_time = $event_time + ( 90 * 60 ); // 90 minuten duurtijd
    // Bepaal status uit custom field (sp_status) naast de tijdsstatus
    $sp_status = get_post_meta( $id, 'sp_status', true );

    if ( $sp_status === 'cancelled' ) {
        $status = esc_attr__( 'Geannuleerd', 'sportspress' );
    } elseif ( $current_time < $event_time ) {
        $status = esc_attr__( 'Preview', 'sportspress' );
    } elseif ( $current_time >= $event_time && $current_time <= $event_end_time ) {
        $status = esc_attr__( 'Bezig', 'sportspress' );
    } else {
        $status = esc_attr__( 'Gespeeld', 'sportspress' ); // Na 90 minuten
    }


if ( empty( $timeline ) ) {
    echo '<div class="sp-timeline-not-started">De wedstrijd is nog niet gestart</div>';
    return;
}

// Get players link option
$link_players = get_option( 'sportspress_link_players', 'no' ) == 'yes' ? true : false;

// Get full time of event
$minutes = $event->minutes();

?>
<div class="sp-template sp-template-timeline sp-template-event-timeline sp-template-vertical-timeline">
	<div class="sp-header-row">
	<h4 class="sp-table-caption"><?php _e( 'Timeline', 'sportspress' ); ?>:</h4>
	<h6 class="sp-status-text">
		<?php echo esc_html( apply_filters( 'sportspress_event_logos_status', $status, $id ) ); ?>
	</h6>
</div>

<?php do_action( 'sportspress_before_vertical_timeline', $id ); ?>
<?php


// Toon melding bij geannuleerde match
if ( $sp_status === 'cancelled' ) : ?>
    <div class="live-info-message">
        De wedstrijd werd geannuleerd.
    </div>

<?php elseif ( $status === 'Aankomend' ) : ?>
    <div class="live-info-message">
        Geen matchinfo, match moet nog gestart / gespeeld worden.
    </div>
<?php endif; // Teams ophalen
$teams = get_post_meta( $id, 'sp_team', false );

$home_team = isset( $teams[0] ) ? $teams[0] : 0;
$away_team = isset( $teams[1] ) ? $teams[1] : 0;

$home_logo = '';
$away_logo = '';

if ( $home_team && has_post_thumbnail( $home_team ) ) {
    $home_logo = get_the_post_thumbnail(
        $home_team,
        'sportspress-fit-icon',
        array(
            'class' => 'sp-team-logo sp-team-logo-home',
            'itemprop' => 'logo'
        )
    );
}

if ( $away_team && has_post_thumbnail( $away_team ) ) {
    $away_logo = get_the_post_thumbnail(
        $away_team,
        'sportspress-fit-icon',
        array(
            'class' => 'sp-team-logo sp-team-logo-away',
            'itemprop' => 'logo'
        )
    );
} 
$home_name = get_the_title( $home_team );
$away_name = get_the_title( $away_team );?>
	<div class="sp-table-wrapper">
		<table class="sp-vertical-timeline sp-data-table">
		    <thead>
    <tr class="sp-timeline-logos">
        <th style="text-align:right;" width="48%">
            <?php echo $home_logo ?: esc_html( $home_name ); ?>
        </th>
        <th width="4%"><span
        id="sp-live-match-clock"
        data-started-at="<?php echo (int) get_post_meta(get_the_ID(), '_match_started_at', true); ?>"
        data-pause-total="<?php echo (int) get_post_meta(get_the_ID(), '_match_pause_total', true); ?>"
        data-paused-at="<?php echo (int) get_post_meta(get_the_ID(), '_match_paused_at', true); ?>"
        data-stopped-at="<?php echo (int) get_post_meta(get_the_ID(), '_match_stopped_at', true); ?>"
        style="font-weight:bold; font-size:14px;"
    >
        0:00
    </span></th>
        <th style="text-align:left;" width="48%">
            <?php echo $away_logo ?: esc_html( $away_name ); ?>
        </th>
    </tr>
</thead>
			<tbody>
			<?php $x=0;
				foreach ( $timeline as $minute => $details ) {
					$class = ( $x % 2 == 0 ) ? 'odd': 'even';
					$x++;

					$time = sp_array_value( $details, 'time', false );
					
					if ( false === $time || $time <= 0 ) continue;

					$icon = sp_array_value( $details, 'icon', '' );
					$side = sp_array_value( $details, 'side', 'home' );
					$key = sp_array_value( $details, 'key', '' );
					$number = sp_array_value( $details, 'number', '' );

					if ( $link_players ) {
						$name = '<a href="' . esc_url( get_permalink( sp_array_value( $details, 'id', '' ) ) ) . '">' . sp_array_value( $details, 'name', __( 'Player', 'sportspress' ) ) . '</a>';
					}else{
						$name = sp_array_value( $details, 'name', __( 'Player', 'sportspress' ) );
					}
					

					if ( 'sub' == $key ) {
						if ( $link_players ) {
							$subname = '<a href="' . esc_url( get_permalink( sp_array_value( $details, 'sub', '' ) ) ) . '">' . sp_array_value( $details, 'sub_name', '') . '</a>';
						} else {
							$subname = sp_array_value( $details, 'sub_name', '');
						}

						if( 'home' == $side ) {
							$name = $subname.'<i class="dashicons dashicons-undo" style="color:red;" title="' . __( 'Sub out', 'sportspress' ) . '"></i><br/>' . $name . '<i class="dashicons dashicons-redo" style="color:green;" title="' . __( 'Sub in', 'sportspress' ) . '"></i>';
						} elseif ( 'away' == $side ) {
							$name = '<i class="dashicons dashicons-redo" style="color:red;" title="' . __( 'Sub out', 'sportspress' ).'"></i>' . $subname . '<br/><i class="dashicons dashicons-undo" style="color:green;" title="' . __( "Sub in", "sportspress" ) . '"></i>'.$name;
						}
					} else {
						if ( '' !== $number ) $name = $number . '. ' . $name;

						if( 'home' == $side ) {
							$name = $name.' '.$icon;
						} elseif ( 'away' == $side ) {
							$name = $icon.' '.$name;
						}
					}
					?>
					<?php if( $side=='home' ) { ?> 
					<tr class="<?php echo $class; ?>">
						<td class="sp-vertical-timeline-minute sp-vertical-timeline-minute-home" style="text-align: right;" width="48%"><?php echo $name; ?></td>
						<td class="home_event_minute" width="4%">
    <span class="minute-circle"><?php echo $time; ?>'</span>
</td><td class="away_event" width="48%">&nbsp;</td>
					</tr>
					<?php }else{ ?>
					<tr class="<?php echo $class; ?>">
						<td class="sp-vertical-timeline-minute sp-vertical-timeline-minute-away" width="48%">&nbsp;</td>
						<td class="home_event_minute" width="4%">
    <span class="minute-circle"><?php echo $time; ?>'</span>
</td>
						<td class="away_event" style="text-align: left;" width="48%"><?php echo $name; ?></td>
					</tr>
				<?php }
				}
			?>
			</tbody>
		</table>
	</div>
	<?php do_action( 'sportspress_after_vertical_timeline', $id ); ?>
</div>
<script>
(function () {
    const el = document.getElementById('sp-live-match-clock');
    if (!el) return;

    const startedAt  = parseInt(el.dataset.startedAt || 0);
    const pauseTotal = parseInt(el.dataset.pauseTotal || 0);
    const pausedAt   = parseInt(el.dataset.pausedAt || 0);
    const stoppedAt  = parseInt(el.dataset.stoppedAt || 0);

    if (!startedAt) {
        el.textContent = '0:00';
        return;
    }

    function tick() {
        let now = Math.floor(Date.now() / 1000);

        if (stoppedAt) {
            now = stoppedAt;
        } else if (pausedAt) {
            now = pausedAt;
        }

        let elapsed = now - startedAt - pauseTotal;
        if (elapsed < 0) elapsed = 0;

        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;

        el.textContent =
            minutes + ':' + String(seconds).padStart(2, '0');
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
