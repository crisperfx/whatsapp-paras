<?php

//** Aanpassen van namen en icoontjes op tabs

    add_filter( 'sportspress_event_templates', function( $templates ) {
        foreach ( $templates as $key => &$template ) {
            if ( is_array( $template ) && isset( $template['title'] ) ) {
                switch ( $key ) {
                    case 'logos':
                        $template['title'] = 'Toon Clubs';
                        $template['label'] = '<i class="fas fa-shield-alt"></i>';
                        break;
                    case 'details':
                        $template['title'] = 'Toon details';
                        $template['label'] = '<i class="fa-solid fa-info"></i>';
                        break;
                    case 'timeline':
                        $template['title'] = 'Live scores';
                        $template['label'] = '<i class="fa-solid fa-tower-broadcast"></i>';
                        break;
                    case 'performance':
                        $template['title'] = 'Statsistieken';
                        $template['label'] = '<i class="fas fa-chart-bar"></i>';
                        break;
                    case 'formation_ve':
                        $template['title'] = 'Opstelling';
                        $template['label'] = '<i class="fa-solid fa-users-between-lines"></i>';
                        break;
                    case 'user_scores':
                        $template['title'] = 'Mijn Scores';
                        $template['label'] = '<i class="fa-solid fa-clipboard-list"></i>';
                        break;
                    case 'lineup_switcher':
                        $template['title'] = 'Vervangingen';
                        $template['label'] = '<i class="fa-solid fa-arrow-down-up-across-line"></i>';
                        break;
                    case 'venue':
                        $template['title'] = 'Locatie';
                        $template['label'] = '<i class="fa-solid fa-map-location"></i>';
                        break;
                    case 'stopwatch':
                        $template['title'] = 'Timer';
                        $template['label'] = '<i class="fa-solid fa-arrow-down-up-across-line"></i>';
                        break;
                }
            }
        }
        return $templates;
    }, 100); // hogere prioriteit zodat het later wordt uitgevoerd
    
    //** Aanpassen van namen en icoontjes op tabs spelers

    add_filter( 'sportspress_player_templates', function( $templates ) {
        foreach ( $templates as $key => &$template ) {
            if ( is_array( $template ) && isset( $template['title'] ) ) {
                switch ( $key ) {
                    case 'statistics':
                        $template['title'] = 'Statistieken';
                        $template['label'] = '<i class="fa-solid fa-chart-simple"></i>';
                        break;
                    case 'details':
                        $template['title'] = 'Details';
                        $template['label'] = '<i class="fa-solid fa-info"></i>';
                        break;
                    case 'events':
                        $template['title'] = 'Wedstrijden';
                        $template['label'] = '<i class="fa-solid fa-calendar"></i>';
                        break;
                }
            }
        }
        return $templates;
    }, 100); // hogere prioriteit zodat het later wordt uitgevoerd

//** icoontjes voor user-scores

add_filter('sportspress_event_performance_labels', function($labels, $event) {
    if (is_admin()) {
        // In backend geen wijzigingen toepassen
        return $labels;
    }

    foreach ($labels as $key => &$label) {
        $label = match ( strtolower($key) ) {
            'goals' => '<span style="color:black; font-size:1.2em;" title="Goals"><i class="fas fa-futbol"></i></span>',
            'gelekaart' => '<span style="color:#FFFF00; font-size:1.2em;" title="Gele kaart"><i class="sp-icon-card" style="color:#FFFF00 !important;"></i></span>',
            'rodekaart' => '<span style="color:#FF0000; font-size:1.2em;" title="Rode kaart"><i class="sp-icon-card" style="color:#FF0000 !important;"></i></span>',
            default => $label,
        };
    }
    return $labels;
}, 10, 2);




//** Aanpassen van "to top" icoon als we de tabs-menu onderaan plaatsen

add_action('wp_footer', function() {
    if ( ! is_singular(['sp_event', 'sp_player']) ) return;
    ?>
        <style>
    #top-to-bottom {
        bottom: 70px !important;
    }
    </style>
    <script>
    jQuery(document).ready(function($) {
        $('.sp-tab-menu').appendTo('.sp-tab-group');
    });
    </script>
    <?php
});