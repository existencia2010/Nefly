<?php

/**
 * Ajax post scipts for single post
 *
 * @return bool
 * @author  @sameast
 */
function streamium_single_video_scripts() {

    if( is_single() )
    {

    	global $post;

    	$nonce = wp_create_nonce( 'single_nonce' );
    	$s3videoid = get_post_meta( $post->ID, 's3bubble_video_code_meta_box_text', true );
    	$youtube = false;
    	$youtubeCode = get_post_meta( $post->ID, 's3bubble_video_youtube_code_meta_box_text', true );
    	$stream = get_post_meta( $post->ID, 'streamium_live_stream_meta_box_text', true );
    	$poster   = wp_get_attachment_image_src( get_post_thumbnail_id(), 'streamium-home-slider' ); 
    	
    	if(is_user_logged_in()){
    		$userId = get_current_user_id();
    		$percentageWatched = get_post_meta( $post->ID, 'user_' . $userId, true );
    	}

    	if(pathinfo($s3videoid, PATHINFO_DIRNAME) !== "."){
		    $s3videoid = pathinfo($s3videoid, PATHINFO_BASENAME);
		}

		// Setup a array for codes
		$codes = [];

		// Check for resume
		$resume = !empty($percentageWatched) ? $percentageWatched : 0;

		$title = $post->post_title;
		$excerpt = wp_trim_words( strip_tags($post->post_excerpt), $num_words = 21, $more = null ); 
		$count = 0;
		$back = false;

		// Not a trailer continue logic
		$episodes = get_post_meta(get_the_ID(), 'repeatable_fields' , true);
		$id = isset($_GET['v']) ? $_GET['v'] : 0;
		if( $episodes ){

			if(!empty($episodes[$id]['service'])){
				$youtube = true;
				$codes[] = $episodes[$id]['service'];
			}else{
				$codes[] = $episodes[$id]['codes'];
			}

			// Grab synopsis
			$title = $episodes[$id]['titles'];
			$excerpt = wp_trim_words( strip_tags($episodes[$id]['descriptions']), $num_words = 21, $more = null );
			$resume = 0;
			$count = count($episodes);
			$back = get_site_url();

		}else{

			if(!empty($youtubeCode)){
				$youtube = true;
				$codes[] = $youtubeCode;
			}else{
				$codes[] = $s3videoid;
			}

		}

		// Check if global adverts are setup
		$globalAdvertisements = "";
		if(get_theme_mod( 'streamium_advertisement_enabled' )){
			$globalAdvertisements = get_theme_mod( 'streamium_advertisement_vpaid_url' );
		}

		// Setup premium
        wp_localize_script( 'streamium-production', 'video_post_object', 
            array( 
                'post_id' => $post->ID,
                'index' => $id,
                'count' => $count,
                'back' => $back,
                'subTitle' => "You're watching",
                'title' => $title,
                'para' => $excerpt,
                'percentage' => $resume,
                'codes' => $codes,
                'stream' => $stream,
                'youtube' => $youtube,
                'vpaid' => $globalAdvertisements,
                'poster' => esc_url($poster[0]),
                'nonce' => $nonce
            )
        ); 

    } 

}

add_action('wp_enqueue_scripts', 'streamium_single_video_scripts');

/**
 * Ajax post scipts for content
 *
 * @return bool
 * @author  @sameast
 */
function streamium_get_dynamic_content() {

	global $wpdb;

	// Get params
	$cat = $_REQUEST['cat'];
	$postId = (int) $_REQUEST['post_id'];
 
    if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'streamium_likes_nonce' ) || ! isset( $_REQUEST['nonce'] ) ) {
       	
       	echo json_encode(
	    	array(
	    		'error' => true,
	    		'message' => 'We could not find this post.'
	    	)
	    );

    }

    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

    	$post_object = get_post( $postId );

    	if(!empty($post_object)){

    		$like_text = '';
	    	$buildMeta = '<ul>';
  
			// Tags
			$posttags = get_the_tags($postId);
			$staring = __( 'Cast', 'streamium' )  . ': ';
			if ($posttags) {
				$numItems = count($posttags);
				$i = 0;
			  	foreach($posttags as $tag) {

				  	$staring .= '<a href="' . esc_url(get_tag_link ($tag->term_id)) . '">' . ucwords($tag->name) . '</a>';
				  	if(++$i !== $numItems) {
			    		$staring .= ', ';
			  		}
 
			    }
			    $buildMeta .= '<li class="synopis-meta-spacer">' . $staring . '</li>';
			}
			
			// Cats
			$query = get_post_taxonomies( $postId );
			$tax = isset($query[1]) ? $query[1] : "";

			// Get the taxonomy name
			$taxName  = get_theme_mod( 'streamium_section_input_taxonomy_' . $tax, $tax );

			// Get the terms which is the taxonomies
			$categories = get_the_terms( $postId, $tax );
			if ($categories) {

				$genres = ucfirst($taxName) . ': ';
				$numItems = count($categories);
				$g = 0;
			  	foreach($categories as $cats) {

			  		$genres .= '<a href="' . esc_url( get_category_link( $cats->term_id ) ) . '">' . ucwords($cats->name) . '</a>';
			  		if(++$g !== $numItems) {
			    		$genres .= ', ';
			  		}

			  	}
			  	$buildMeta .= '<li class="synopis-meta-spacer">' . $genres . '</li>';
			}

			// If its a tv list episodes
			$episodes = orderCodes($postId);
			if($episodes) {

				$buildMeta .= '<li class="synopis-meta-spacer">' .  __( 'Seasons', 'streamium' ) . ': ' . $episodes['seasons'] . ', Episodes: ' . $episodes['episodes'] .'</li>';

			}

			// Release date
			$buildMeta .= '<li class="synopis-meta-spacer">' .  __( 'Released', 'streamium' ) . ': <a href="/?s=all&date=' . get_the_date('Y/m/d', $postId) . '">' . get_the_date('l, F j, Y', $postId) . '</a></li></ul>';
            
            // Only allow like/reviews for premium users
			if ( get_theme_mod( 'streamium_enable_premium' ) ) {

				// Likes and reviews
		        $nonce = wp_create_nonce( 'streamium_likes_nonce' );
		    	$link = admin_url('admin-ajax.php?action=streamium_likes&post_id='. $postId .'&nonce='.$nonce);

		        $like_text = '<div class="synopis-premium-meta hidden-xs">
		        				<a id="like-count-' . $postId . '" class="streamium-review-like-btn streamium-btns streamium-reviews-btns" data-toggle="tooltip" title="' .  __( 'CLICK TO LIKE!', 'streamium' ) . '" data-id="' . $postId . '" data-nonce="' . $nonce . '">' . get_streamium_likes($postId) . '</a>
		        				<a class="streamium-list-reviews streamium-btns streamium-reviews-btns" data-id="' . $postId . '" data-nonce="' . $nonce . '">' .  __( 'Read reviews', 'streamium' ) . '</a>
							</div>';

		    }
 
	    	$streamiumVideoTrailer = get_post_meta( $postId, 'streamium_video_trailer_meta_box_text', true );

	    	$fullImage  = wp_get_attachment_image_url( get_post_thumbnail_id( $postId ), 'streamium-video-tile-large-expanded' );
	    	// Allow a extra image to be added
            if (class_exists('MultiPostThumbnails')) {                              
                
                if (MultiPostThumbnails::has_post_thumbnail( get_post_type( $postId ), 'large-landscape-image', $postId)) { 

                    $image_id = MultiPostThumbnails::get_post_thumbnail_id( get_post_type( $postId ), 'large-landscape-image', $postId );  
                    $fullImage = wp_get_attachment_image_url( $image_id,'streamium-video-tile-large-expanded' ); 

                }                            
             
            }; // end if MultiPostThumbnails 

            // Setup content
            $content = strip_tags($post_object->post_content);

            // Watch preview
            $streamiumVideoTrailer = get_post_meta( $postId, 'streamium_video_trailer_meta_box_text', true );
            if(get_post_meta( $postId, 's3bubble_video_trailer_youtube_code_meta_box_text', true )){
            	 $streamiumVideoTrailer = get_post_meta( $postId, 's3bubble_video_trailer_youtube_code_meta_box_text', true );
            }


            // Trailer button text
            $streamiumVideoTrailerBtnText = __( 'Watch Trailer', 'streamium' );
            if(get_post_meta( $postId, 's3bubble_video_trailer_button_text_meta_box_text', true )){
            	 $streamiumVideoTrailerBtnText = get_post_meta( $postId, 's3bubble_video_trailer_button_text_meta_box_text', true );
            }

	    	echo json_encode(
		    	array(
		    		'error' => false,
		    		'cat' => $cat,
		    		'title' => $post_object->post_title,
		    		'content' => $content,
		    		'meta' => $buildMeta,
		    		'reviews' => $like_text,
		    		'bgimage' =>  isset($fullImage) ? $fullImage : "",
		    		'trailer' => $streamiumVideoTrailer,
		    		'href' => get_permalink($postId),
		    		'preview' => $streamiumVideoTrailer,
		    		'trailer_btn_text' => $streamiumVideoTrailerBtnText,
		    		'post' => $post_object
		    	)
		    );

	    }else{

	    	echo json_encode(
		    	array(
		    		'error' => true,
		    		'message' => 'We could not find this post.'
		    	)
		    );

	    }

        die();

    }
    else {
        
        wp_redirect( get_permalink( $_REQUEST['post_id'] ) );
        exit();

    }

}

add_action( 'wp_ajax_nopriv_streamium_get_dynamic_content', 'streamium_get_dynamic_content' );
add_action( 'wp_ajax_streamium_get_dynamic_content', 'streamium_get_dynamic_content' );


/**
 * Ajax post scipts for content
 *
 * @return bool
 * @author  @sameast
 */
function streamium_get_more_content() {

	global $wpdb;

	// Get params
	$postId = (int) $_REQUEST['postId'];

	error_log(print_r($_REQUEST,true));
 
    if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'extra_api_nonce' ) || ! isset( $_REQUEST['nonce'] ) ) {
       	
       	echo json_encode(
	    	array(
	    		'error' => true,
	    		'message' => 'We could not find this post.'
	    	)
	    );

    }

    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

    	$post_object = get_post( $postId );

    	if(!empty($post_object)){

	    	$fullImage  = wp_get_attachment_image_url( get_post_thumbnail_id( $postId ), 'streamium-video-tile-large-expanded' );
	    	// Allow a extra image to be added
            if (class_exists('MultiPostThumbnails')) {                              
                
                if (MultiPostThumbnails::has_post_thumbnail( get_post_type( $postId ), 'large-landscape-image', $postId)) { 

                    $image_id = MultiPostThumbnails::get_post_thumbnail_id( get_post_type( $postId ), 'large-landscape-image', $postId );  
                    $fullImage = wp_get_attachment_image_url( $image_id,'streamium-video-tile-large-expanded' ); 

                }                            
             
            }; // end if MultiPostThumbnails 

	    	echo json_encode(
		    	array(
		    		'error' => false,
		    		'title' => $post_object->post_title,
		    		'content' => $post_object->post_content,
		    		'bgimage' =>  isset($fullImage) ? $fullImage : "",
		    		'href' => get_permalink($postId)
		    	)
		    );

	    }else{

	    	echo json_encode(
		    	array(
		    		'error' => true,
		    		'message' => 'We could not find this post.'
		    	)
		    );

	    }

        die();

    }
    else {
        
        wp_redirect( get_permalink( $_REQUEST['post_id'] ) );
        exit();

    }

}

add_action( 'wp_ajax_nopriv_streamium_get_more_content', 'streamium_get_more_content' );
add_action( 'wp_ajax_streamium_get_more_content', 'streamium_get_more_content' );

function streamium_custom_post_types_general( $hook_suffix ){

    if( in_array($hook_suffix, array('post.php', 'post-new.php') ) ){
        
        $screen = get_current_screen();

        if( is_object( $screen ) && in_array($screen->post_type, array('movie', 'tv','sport','kid'))){

            // Register, enqueue scripts and styles here
            wp_enqueue_script( 'streamium-admin-custom-post-type-general', get_template_directory_uri() . '/production/js/custom.post.type.general.min.js', array( 'jquery' ),'1.1', true );

        }

        if( is_object( $screen ) && in_array($screen->post_type, array('stream'))){

            // Register, enqueue scripts and styles here
            wp_enqueue_script( 'streamium-admin-custom-post-type-stream', get_template_directory_uri() . '/production/js/custom.post.type.stream.min.js', array( 'jquery' ),'1.1', true );

        }
    }
}

add_action( 'admin_enqueue_scripts', 'streamium_custom_post_types_general');


// ONLY MOVIE CUSTOM TYPE POSTS
add_filter('manage_posts_columns', 'streamium_columns_main_slider', 1);
add_action('manage_posts_custom_column', 'streamium_columns_main_slider_content', 10, 2);
 
// CREATE TWO FUNCTIONS TO HANDLE THE COLUMN
function streamium_columns_main_slider($columns) { 
    
    $new = array();
  	foreach($columns as $key => $title) {
    	if ($key=='author') // Put the Thumbnail column before the Author column
      	$new['main_slider'] = 'Main Slider';
    	$new[$key] = $title;
  	}
  	return $new;
  	

}
function streamium_columns_main_slider_content($column_name, $post_ID) {

    if ($column_name == 'main_slider') {

        $main_slider = get_post_meta( $post_ID, 'streamium_slider_featured_checkbox_value', true );
        echo '<b>' . ucfirst($main_slider) . '</b>';

    }

}