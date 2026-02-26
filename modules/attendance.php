<?php
/**
 * Class and Function List:
 * Function list:
 * - whatsapp_paras_attendance_page()
 * Classes list:
 * Aanwezigheidspagina: lijst per event whatsapp
 */
function whatsapp_paras_attendance_page()
{
    global $wpdb;

    if (!current_user_can("manage_options")) {
        wp_die("Geen toegang.");
    }

    echo '<div class="wrap">';
    echo "<h1>Aanwezigheden per Wedstrijd</h1>";

    // Haal huidig seizoen op
    $current_season_id = $wpdb->get_var(
        "SELECT option_value FROM fc_options WHERE option_name = 'sportspress_season'"
    );
    $selected_season_id = isset($_GET["season"])
        ? intval($_GET["season"])
        : intval($current_season_id);

    // Haal alle seizoenen op
    $seasons = $wpdb->get_results("
        SELECT t.term_id, t.name 
        FROM {$wpdb->prefix}terms t
        INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'sp_season'
        ORDER BY t.name DESC
    ");

    // Haal events voor dit seizoen op
    $event_ids = $wpdb->get_col(
        $wpdb->prepare(
            "
        SELECT object_id FROM fc_term_relationships
        WHERE term_taxonomy_id = %d
    ",
            $selected_season_id
        )
    );

    $events = [];
    if (!empty($event_ids)) {
        $events = get_posts([
            "post_type" => "sp_event",
            "posts_per_page" => -1,
            "post_status" => ["publish", "future"],
            "orderby" => "date",
            "order" => "ASC",
            "post__in" => $event_ids,
        ]);
    }

    // Haal geldige spelers op
    $valid_players = [];
    $players_query = get_posts([
        "post_type" => "sp_player",
        "posts_per_page" => -1,
        "post_status" => "publish",
    ]);
    foreach ($players_query as $p) {
        $valid_players[$p->ID] = $p->post_title;
    }

    // Vind eerstvolgende wedstrijd
    // Vind eerstvolgende wedstrijd met sp_status = ok
    $now = current_time("timestamp");
    $next_event = null;
    foreach ($events as $event) {
        if (strtotime($event->post_date) >= $now) {
            $status = get_post_meta($event->ID, "sp_status", true);

            // sp_status kan array of string zijn
            if (
                (is_array($status) && in_array("ok", $status)) ||
                $status === "ok"
            ) {
                $next_event = $event;
                break;
            }
        }
    }
    // Begin tabs
    ?>
    <style>
        .tabs { margin-top: 20px; }
        .tab-button {
            background: #eee;
            border: 1px solid #ccc;
            padding: 8px 16px;
            cursor: pointer;
            display: inline-block;
            margin-right: 5px;
        }
        .tab-button.active {
            background: #fff;
            border-bottom: none;
            font-weight: bold;
        }
        .tab-content {
            border: 1px solid #ccc;
            padding: 15px;
            background: #fff;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.tab-button');
            const contents = document.querySelectorAll('.tab-content');

            buttons.forEach(btn => {
                btn.addEventListener('click', function () {
                    buttons.forEach(b => b.classList.remove('active'));
                    contents.forEach(c => c.style.display = 'none');

                    this.classList.add('active');
                    const tab = this.dataset.tab;
                    document.getElementById(tab).style.display = 'block';
                });
            });

            // Init: toon tab1 standaard
            document.querySelector('.tab-button[data-tab="tab1"]').click();
        });
    </script>

    <div class="tabs">
        <div class="tab-button active" data-tab="tab1">Volgende Wedstrijd</div>
        <div class="tab-button" data-tab="tab2">Alle Wedstrijden</div>
    </div>

    <div id="tab1" class="tab-content">
        <?php if ($next_event) {
            $responses = Waha_Helpers::get_meta(
                $event->ID,
                "_whatsapp_event_responses"
            );

            $going = [];
            $not_going = [];

            if (is_array($responses)) {
                foreach ($responses as $player_id => $status) {
                    if (!isset($valid_players[$player_id])) {
                        continue;
                    }
                    $name = $valid_players[$player_id];
                    if ($status === "going") {
                        $going[] = $name;
                    } elseif ($status === "not_going") {
                        $not_going[] = $name;
                    }
                }
            }

            echo "<details open>";
            echo '<summary style="font-size: 1.2em; font-weight:bold;">' .
                esc_html($next_event->post_title) .
                " | " .
                esc_html(date("d-m-Y H:i", strtotime($next_event->post_date))) .
                "</summary>";
            echo '<table class="widefat striped" style="margin-top:10px; max-width:600px;">';
            echo "<thead><tr><th>Gaat</th><th>Gaat niet</th></tr></thead><tbody>";
            $max_rows = max(count($going), count($not_going));
            for ($i = 0; $i < $max_rows; $i++) {
                echo "<tr>";
                echo "<td>" . esc_html($going[$i] ?? "") . "</td>";
                echo "<td>" . esc_html($not_going[$i] ?? "") . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            echo "</details>";
        } else {
            echo "<p>Geen aankomende wedstrijden gevonden.</p>";
        } ?>
    </div>

    <div id="tab2" class="tab-content" style="display:none;">
        <form method="GET">
            <input type="hidden" name="page" value="<?php echo esc_attr(
                $_GET["page"]
            ); ?>" />
            <label for="season">Selecteer seizoen: </label>
            <select name="season" id="season" onchange="this.form.submit()">
                <?php foreach ($seasons as $season) {
                    $selected =
                        $season->term_id == $selected_season_id
                            ? "selected"
                            : "";
                    echo "<option value='{$season->term_id}' $selected>" .
                        esc_html($season->name) .
                        "</option>";
                } ?>
            </select>
        </form>
        <br>
        <?php if (empty($events)) {
            echo "<p>Geen wedstrijden gevonden voor dit seizoen.</p>";
        } else {
            foreach ($events as $event) {
                $responses = Waha_Helpers::get_meta(
                    $event->ID,
                    "_whatsapp_event_responses"
                );

                $going = [];
                $not_going = [];

                if (is_array($responses)) {
                    foreach ($responses as $player_id => $status) {
                        if (!isset($valid_players[$player_id])) {
                            continue;
                        }
                        $name = $valid_players[$player_id];
                        if ($status === "going") {
                            $going[] = $name;
                        } elseif ($status === "not_going") {
                            $not_going[] = $name;
                        }
                    }
                }

                echo '<details style="margin-bottom:1em;">';
                echo '<summary style="font-size: 1.2em; font-weight:bold;">' .
                    esc_html($event->post_title) .
                    " | " .
                    esc_html(date("d-m-Y H:i", strtotime($event->post_date))) .
                    "</summary>";
                echo '<table class="widefat striped" style="margin-top:10px; max-width:600px;">';
                echo "<thead><tr><th>Gaat</th><th>Gaat niet</th></tr></thead><tbody>";
                $max_rows = max(count($going), count($not_going));
                for ($i = 0; $i < $max_rows; $i++) {
                    echo "<tr>";
                    echo "<td>" . esc_html($going[$i] ?? "") . "</td>";
                    echo "<td>" . esc_html($not_going[$i] ?? "") . "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
                echo "</details>";
            }
        } ?>
    </div>

    <?php echo "</div>"; // .wrap
}
