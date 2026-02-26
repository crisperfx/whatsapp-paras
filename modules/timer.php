<?php
// AJAX handler voor match control
add_action('wp_ajax_paras_match_control', function() {
    

    $event_id    = intval($_POST['event_id'] ?? 0);
    $action_type = sanitize_text_field($_POST['action_type'] ?? '');

    if (!$event_id || !$action_type) {
        Waha_Helpers::wedstrijd_debug("MATCH CONTROL: Ongeldige parameters: event_id={$event_id}, action={$action_type}");
        return;
    }

    $now = time();

    switch($action_type) {
        case 'start':
            Waha_Helpers::set_match_meta($event_id, [
                '_match_started_at' => $now,
                '_match_paused_at' => 0,
                '_match_pause_total' => 0,
                '_match_stopped_at' => 0,
            ]);
            Waha_Helpers::wedstrijd_debug("MATCH START: Event $event_id om " . date('H:i:s', $now));
            break;

        case 'pause':
    $time = Waha_Helpers::get_match_time($event_id);
    $minutes = (int) $time['minutes'];

    // Alleen halve tijd als 45+ minuten
    if ($minutes >= 45 && !get_post_meta($event_id, '_halftime_done', true)) {
        do_action('paras_update_halftime_score', $event_id);
        update_post_meta($event_id, '_halftime_done', 1);
        Waha_Helpers::wedstrijd_debug("HALFTIME SCORE automatisch bijgewerkt voor Event $event_id");
    } else {
        Waha_Helpers::wedstrijd_debug("HALFTIME SCORE niet uitgevoerd (minuten < 45) voor Event $event_id");
    }

    // Pauze meta updaten
    Waha_Helpers::set_match_meta($event_id, [
        '_match_paused_at' => $now
    ]);
    Waha_Helpers::wedstrijd_debug("MATCH PAUSE: Event $event_id om " . date('H:i:s', $now));
    break;

        case 'resume':
            $started_at  = (int) get_post_meta($event_id, '_match_started_at', true);
            $paused_at   = (int) get_post_meta($event_id, '_match_paused_at', true);
            $pause_total = (int) get_post_meta($event_id, '_match_pause_total', true);

            if (!$paused_at || !$started_at) {
                Waha_Helpers::wedstrijd_debug("MATCH RESUME: Kan niet hervatten voor Event $event_id");
                break;
            }

            $new_pause_total = $pause_total + ($now - $paused_at);
            Waha_Helpers::set_match_meta($event_id, [
                '_match_pause_total' => $new_pause_total,
                '_match_paused_at' => 0
            ]);

            Waha_Helpers::wedstrijd_debug("MATCH RESUME: Event $event_id om " . date('H:i:s', $now) . " | nieuwe pauzetijd: {$new_pause_total} sec");
            break;

        case 'stop':
    Waha_Helpers::set_match_meta($event_id, [
        '_match_stopped_at' => $now,
        '_match_paused_at' => 0
    ]);
    Waha_Helpers::wedstrijd_debug("MATCH STOP: Event $event_id om " . date('H:i:s', $now));

    // Tweede helft score invullen
    if (!get_post_meta($event_id, '_second_half_done', true)) {
        do_action('paras_update_second_half_score', $event_id);
        update_post_meta($event_id, '_second_half_done', 1);
        Waha_Helpers::wedstrijd_debug("SECOND HALF SCORE automatisch bijgewerkt voor Event $event_id");
    }
    break;
    
    case 'test_scores':
    // Manuele testknop: beide scores invullen ongeacht tijd
    do_action('paras_update_halftime_score', $event_id);
    update_post_meta($event_id, '_halftime_done', 1);
    do_action('paras_update_second_half_score', $event_id);
    update_post_meta($event_id, '_second_half_done', 1);

    Waha_Helpers::wedstrijd_debug("TEST SCORES: beide helften ingevuld voor Event $event_id");
    break;
    }
});

// Voeg knoppen + timer inline in tab-menu toe
add_action('wp_footer', function() {
    

    global $post;
    $event_id = $post->ID;

    $started_at  = (int) get_post_meta($event_id, '_match_started_at', true);
    $paused_at   = (int) get_post_meta($event_id, '_match_paused_at', true);
    $pause_total = (int) get_post_meta($event_id, '_match_pause_total', true);
    $stopped_at  = (int) get_post_meta($event_id, '_match_stopped_at', true);
    ?>
    <script>
    jQuery(function($){
        let matchStarted = <?php echo $started_at ? 'true' : 'false'; ?>;
        let matchPaused  = <?php echo $paused_at ? 'true' : 'false'; ?>;
        let matchStopped = <?php echo $stopped_at ? 'true' : 'false'; ?>;
        let startedAt    = <?php echo $started_at ?: 0; ?>;
        let pausedAt     = <?php echo $paused_at ?: 0; ?>;
        let stoppedAt    = <?php echo $stopped_at ?: 0; ?>;
        let pauseTotal   = <?php echo $pause_total ?: 0; ?>;

        // Voeg inline li toe in tab-menu
        const li = $(`<li class="sp-tab-menu-item match-controls-inline" style="display:flex; gap:5px; align-items:center; margin-left:10px;"></li>`);
        $('.sp-tab-menu').append(li);

        function getMatchTime(){
            if(!matchStarted) return '0:00';

            let now = Math.floor(Date.now() / 1000);

            if(matchStopped && stoppedAt) now = stoppedAt;
            if(matchPaused && pausedAt) now = pausedAt;

            let elapsed = now - startedAt - pauseTotal;
            if(elapsed < 0) elapsed = 0;

            let min = Math.floor(elapsed / 60);
            let sec = elapsed % 60;

            return min + ':' + String(sec).padStart(2, '0');
        }

        function renderMatchButton(){
            let html = '';
            if(matchStopped){
                html = '<span class="match-action">⏹ Gestopt</span>';
            } else if(!matchStarted){
                html = '<a href="#" class="match-action" data-action="start">▶ Start</a>';
            } else if(matchPaused){
                html = '<a href="#" class="match-action" data-action="resume">▶ Hervat</a>';
            } else {
                html = '<a href="#" class="match-action" data-action="pause">⏸ Pauze</a>';
            }

            if(!matchStopped){
                html += ' <a href="#" class="match-action" data-action="stop">⏹ Stop</a>';
            }

            html += ' <span class="match-minutes" style="margin-left:8px; font-weight:600;">' + getMatchTime() + 'min.</span>';
            html += ' <a href="#" class="match-action" data-action="test_scores">���� Test Scores</a>';
            $('.match-controls-inline').html(html);
        }

        setInterval(function(){
            if(!matchStarted || matchStopped) return;
            $('.match-minutes').text(getMatchTime());
        }, 1000);

        renderMatchButton();

        $(document).on('click', '.match-action[data-action]', function(e){
            e.preventDefault();
            let $btn = $(this);
            let actionType = $btn.data('action');

            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'paras_match_control',
                action_type: actionType,
                event_id: <?php echo (int) $event_id; ?>
            }, function(){
                 const now = Math.floor(Date.now()/1000);
                    switch(actionType){
                        case 'start':
                            matchStarted = true; matchPaused = false; matchStopped = false;
                            startedAt = now; pauseTotal = 0; pausedAt = 0;
                            break;
                        case 'pause':
                            matchPaused = true; pausedAt = now;
                            break;
                        case 'resume':
                            matchPaused = false; pauseTotal += now - pausedAt; pausedAt = 0;
                            break;
                        case 'stop':
                            matchStopped = true; matchPaused = false;
                            break;
                    }
                    renderMatchButton();                })
            });
        });
    </script>

    <style>
    .match-controls-inline {
        display: inline-block;
        padding: 0.3rem 0.5rem;
        background: #eee;
        border-radius: 6px;
        color: #333;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #ff0000;
        transition: background 0.2s ease;
    }
    .match-controls-inline:hover {
        background: #ddd;
    }
    </style>
    <?php
});