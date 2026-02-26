<?php
    get_header();
    global $kester_option;
    $post_id      = get_the_id();
    $author_id    = get_post_field ('post_author', $post_id);
    $display_name = get_the_author_meta( 'display_name' , $author_id );
    //checking page layout 
    $page_layout = $kester_option['blog-single-layout'];
    $col_side = '';
    $col_letf = '';
    if($page_layout == '2left' && is_active_sidebar( 'sidebar-1' )){
        $col_side = '8';
        $col_letf = 'left-sidebar';
    }elseif($page_layout == '2right' && is_active_sidebar( 'sidebar-1' )){
        $col_side = '8';}
    else{
        $col_side = '12';
    }
    ?>
    <!-- Blog Detail Start -->
    <div class="reactheme-blog-details pt-70 pb-70">
        <div class="row padding-<?php echo esc_attr( $col_letf) ?>">
            <div class="col-lg-<?php echo esc_attr( $col_side). ' ' .esc_attr( $col_letf) ?>">
                <div class="news-details-inner">
                    <?php
                        while ( have_posts() ) : the_post();
                    ?>             
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>             
                        <?php if(has_post_thumbnail()){ ?>
                        
                        <?php } ?>
                       
                        <?php
                          get_template_part( 'template-parts/post/content', get_post_format() );         
                        ?>
                        <div class="clear-fix"></div>      
                    </article> 
                
                    <?php                    
                        $author_meta = get_the_author_meta('description'); 
                        if( !empty($author_meta) ){
                        ?>
                            <div class="author-block">
                                <div class="author-img"> <?php echo get_avatar(get_the_author_meta( 'ID' ), 200);?> </div>
                                <div class="author-desc">
                                    <h3 class="author-title">
                                        <?php the_author();?>
                                    </h3>
                                    <p>
                                        <?php   
                                            echo wpautop( get_the_author_meta( 'description' ) );
                                        ?>
                                    </p>
                                    
                                </div>
                            </div>
                            <!-- .author-block -->
                    <?php }              
                    if(!empty($kester_option['blog-comments'])){
                        if ($kester_option['blog-comments'] == 'show'){
                            if ( comments_open() || get_comments_number() ) :
                                comments_template();
                            endif;
                        }
                    }else{
                        if ( comments_open() || get_comments_number() ) :
                            comments_template();
                        endif;
                    }
                endwhile; // End of the loop.
                ?>
            </div>
        </div>
          <?php
            if( $page_layout == '2left' || $page_layout == '2right'):
                get_sidebar('single');
            endif;
          ?>    
        </div>
    </div>
    <!-- Blog Detail End --> 
      
<?php
get_footer();