<?php
/**
 * Template for displaying single SportsPress Calendar
 * Theme: Kester (aangepast)
 */

get_header();

global $kester_option;

$post_id   = get_the_ID();
$author_id = get_post_field('post_author', $post_id);
$display_name = get_the_author_meta('display_name', $author_id);

// Ophalen ingestelde format
$format = get_post_meta($post_id, 'format', true);
if (empty($format)) {
    $format = 'list'; // fallback
}

// Mobiele check: wp_is_mobile()
if (wp_is_mobile()) {
    $format = 'blocks';
}

// Layout settings
$page_layout = $kester_option['blog-single-layout'];
$col_side = '12';
$col_left = '';

if ($page_layout === '2left' && is_active_sidebar('sidebar-1')) {
    $col_side = '8';
    $col_left = 'left-sidebar';
} elseif ($page_layout === '2right' && is_active_sidebar('sidebar-1')) {
    $col_side = '8';
}

?>

<!-- Calendar Detail Start -->
<div class="reactheme-blog-details pt-70 pb-70" style='padding-top: 0.5em;'>
    <div class="row padding-<?php echo esc_attr($col_left); ?>">
        <div class="col-lg-<?php echo esc_attr($col_side) . ' ' . esc_attr($col_left); ?>">
            <div class="news-details-inner">
                <?php
                if (have_posts()) :
                    while (have_posts()) : the_post();
                        ?>

                        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                            <?php
                            if (has_post_thumbnail()) {
                                the_post_thumbnail('full');
                            }

                            
                            ?>

                            <!-- Output SportsPress Calendar -->
                            <div class="sportspress-calendar-output">
                                <?php echo do_shortcode('[event_' . esc_attr($format) . ' id="' . esc_attr($post_id) . '"]'); ?>
                            </div>

                        </article>

                        <?php
                        // Author bio block (optioneel)
                        $author_meta = get_the_author_meta('description');
                        if (!empty($author_meta)) :
                            ?>
                            <div class="author-block">
                                <div class="author-img">
                                    <?php echo get_avatar($author_id, 200); ?>
                                </div>
                                <div class="author-desc">
                                    <h3 class="author-title"><?php the_author(); ?></h3>
                                    <?php echo wpautop($author_meta); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Comments
                        if (isset($kester_option['blog-comments']) && $kester_option['blog-comments'] === 'show') {
                            if (comments_open() || get_comments_number()) {
                                comments_template();
                            }
                        } elseif (comments_open() || get_comments_number()) {
                            comments_template();
                        }

                    endwhile;
                endif;
                ?>
            </div>
        </div>

        <?php if ($page_layout === '2left' || $page_layout === '2right') : ?>
            <?php get_sidebar('single'); ?>
        <?php endif; ?>
    </div>
</div>
<!-- Calendar Detail End -->
<?php
    $permalink = get_permalink($post_id);
    $webcal_url = str_replace('https://', 'webcal://', $permalink) . '?feed=sp-ical';
    $google_url = 'https://www.google.com/calendar/render?cid=' . rawurlencode($webcal_url);
?>
<?php if ( get_the_ID() === 4953 ) : ?>
    <div class="calendar-floating-bar">
        <div class="calendar-icons">
            <a href="<?php echo esc_url($webcal_url); ?>" class="calendar-icon" title="Toevoegen aan Apple / Outlook">
                <i class="fab fa-apple"></i>
            </a>
            <a href="<?php echo esc_url($google_url); ?>" class="calendar-icon" title="Toevoegen aan Google Kalender" target="_blank">
                <i class="fab fa-google"></i>
            </a>
        </div>
        <span class="calendar-label">Toevoegen aan kalender</span>
    </div>
<?php endif; ?>
<?php get_footer(); ?>
