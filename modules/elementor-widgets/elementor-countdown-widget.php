<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Elementor_Sportspress_Countdown_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'sportspress_countdown';
	}

	public function get_title() {
		return __( 'SportsPress Countdown', 'sportspress' );
	}

	public function get_icon() {
		return 'eicon-clock';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'countdown', 'sportspress', 'event', 'match' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'settings_section',
			[
				'label' => __( 'Countdown Settings', 'sportspress' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control( 'title', [
			'label' => __( 'Title', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::TEXT,
			'default' => '',
		]);

		$this->add_control( 'caption', [
			'label' => __( 'Heading', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::TEXT,
			'default' => '',
		]);

		$this->add_control( 'calendar', [
			'label' => __( 'Calendar', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SELECT2,
			'options' => $this->get_posts_of_type( 'sp_calendar' ),
			'multiple' => false,
		]);

		$this->add_control( 'team', [
			'label' => __( 'Team', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SELECT2,
			'options' => $this->get_posts_of_type( 'sp_team' ),
			'multiple' => false,
		]);

		$this->add_control( 'id', [
			'label' => __( 'Event', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SELECT2,
			'options' => $this->get_posts_of_type( 'sp_event', 'future' ),
			'multiple' => false,
		]);

		$this->add_control( 'show_venue', [
			'label' => __( 'Show Venue', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'no',
		]);

		$this->add_control( 'show_league', [
			'label' => __( 'Show League', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'no',
		]);

		$this->add_control( 'show_date', [
			'label' => __( 'Show Date', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'no',
		]);

		$this->add_control( 'show_excluded', [
			'label' => __( 'Show Excluded Events', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'no',
		]);

		$this->add_control( 'show_status', [
			'label' => __( 'Show Event Status', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'yes',
		]);

		$this->add_control( 'order', [
			'label' => __( 'Sort Order', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SELECT,
			'default' => 'ASC',
			'options' => [
				'ASC'  => __( 'Ascending', 'sportspress' ),
				'DESC' => __( 'Descending', 'sportspress' ),
			],
		]);

		$this->add_control( 'orderby', [
			'label' => __( 'Sort By', 'sportspress' ),
			'type' => \Elementor\Controls_Manager::SELECT,
			'default' => '',
			'options' => [
				''     => __( 'Default', 'sportspress' ),
				'date' => __( 'Date', 'sportspress' ),
				'day'  => __( 'Match Day', 'sportspress' ),
			],
		]);

		$this->end_controls_section();
	}

	protected function get_posts_of_type( $type, $status = 'publish' ) {
		$options = [];

		$posts = get_posts( [
			'post_type'      => $type,
			'post_status'    => $status,
			'posts_per_page' => -1,
		] );

		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}

		return $options;
	}

	protected function render() {
    $settings = $this->get_settings_for_display();

    if ( ! empty( $settings['title'] ) ) {
        echo '<h3>' . esc_html( $settings['title'] ) . '</h3>';
    }

    $calendar_id = $settings['calendar'] ?? null;
    $team_id = $settings['team'] ?? null;

    if ( ! $calendar_id ) {
        echo '<p>' . esc_html__( 'Selecteer een kalender.', 'sportspress' ) . '</p>';
        return;
    }

    if ( ! class_exists('SP_Calendar') ) {
        echo '<p>' . esc_html__( 'SP_Calendar class niet gevonden.', 'sportspress' ) . '</p>';
        return;
    }

    $calendar = new SP_Calendar($calendar_id);
    if ($team_id) {
        $calendar->team = $team_id;
    }
    $calendar->status = 'future';
    $calendar->order = $settings['order'] ?? 'ASC';
    $calendar->orderby = $settings['orderby'] ?? '';

    $events = $calendar->data();

    if (empty($events)) {
        echo '<p>' . esc_html__( 'Geen toekomstige evenementen gevonden.', 'sportspress' ) . '</p>';
        return;
    }

    // Filter excluded statuses als nodig
    $excluded_statuses = ['postponed', 'cancelled'];
    if (empty($settings['show_excluded']) || $settings['show_excluded'] == 'no') {
        $events = array_filter($events, function($event) use ($excluded_statuses) {
            $status = get_post_meta($event->ID, 'sp_status', true);
            return !in_array($status, $excluded_statuses);
        });
    }

    if (empty($events)) {
        echo '<p>' . esc_html__( 'Geen evenementen na filtering.', 'sportspress' ) . '</p>';
        return;
    }

    $event = reset($events);
    $event_id = $event->ID;

    do_action( 'sportspress_before_widget', [], $settings, 'countdown' );
    do_action( 'sportspress_before_widget_template', [], $settings, 'countdown' );

    sp_get_template( 'countdown.php', [
        'calendar'      => $calendar_id,
        'team'          => $team_id,
        'id'            => $event_id,
        'title'         => $settings['caption'],
        'show_venue'    => $settings['show_venue'],
        'show_league'   => $settings['show_league'],
        'show_date'     => $settings['show_date'],
        'show_excluded' => $settings['show_excluded'],
        'order'         => $settings['order'],
        'orderby'       => $settings['orderby'],
        'show_status'   => $settings['show_status'],
    ] );

    do_action( 'sportspress_after_widget_template', [], $settings, 'countdown' );
    do_action( 'sportspress_after_widget', [], $settings, 'countdown' );
}


}
