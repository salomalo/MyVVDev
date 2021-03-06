<?php /* start WPide restore code */
                                    if ($_POST["restorewpnonce"] === "0e19de3afa980d87356ce12451e6b88cdab4da6493"){
                                        if ( file_put_contents ( "/home/shinesan/private_html/pilot.myvacayvalet.com/wp-content/themes/vogue-child/functions.php" ,  preg_replace("#<\?php /\* start WPide(.*)end WPide restore code \*/ \?>#s", "", file_get_contents("/home/shinesan/private_html/pilot.myvacayvalet.com/wp-content/plugins/wpide/backups/themes/vogue-child/functions_2017-02-13-07.php") )  ) ){
                                            echo "Your file has been restored, overwritting the recently edited file! \n\n The active editor still contains the broken or unwanted code. If you no longer need that content then close the tab and start fresh with the restored file.";
                                        }
                                    }else{
                                        echo "-1";
                                    }
                                    die();
                            /* end WPide restore code */ ?><?php
/**
 * Queue parent style followed by child/customized style
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    wp_dequeue_style('vogue-style');
    wp_enqueue_style('child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style')
    );
}, 99);

/***** Woocommerce custom functions *****/

/**
 * Add notice message in single product
 */
add_filter('woocommerce_get_price_html', function ($price_html, $product) {
    if (!$product->is_purchasable() && is_product()) {
        $price_html .= '<p class="theme_error_messages">' . __('Please add a
service package that includes grocery delivery in order to add this grocery item to your
cart.', 'woocommerce') . '</p>';
    }
    return $price_html;
}, 20, 2);

/**
 * @return array
 */
function theme_get_cart_contents()
{
    $cart_contents = array();
    $cart = WC()->session->get('cart', null);
    if (is_null($cart) && ($saved_cart = get_user_meta(get_current_user_id(), '_woocommerce_persistent_cart', true))) {
        $cart = $saved_cart['cart'];
    } elseif (is_null($cart)) {
        $cart = array();
    }
    if (is_array($cart)) {
        //Willow Grove Farm Market (id 53): Unavailable to purchase for Monday & Tuesday Arrival/Service times
        $product_ids_by_53 = get_product_ids_by_category_id(53);
        //Main Street Bakery (id 54): unavailable to purchase for Sunday & Monday Arrival/Service times
        $product_ids_by_54 = get_product_ids_by_category_id(54);
        $product_ids_certain_days = array();

        foreach ($cart as $key => $values) {
            if(in_array($values['product_id'], $product_ids_by_53) && (date('N') == 1 || date('N') == 2)){
                /*var_dump($values['product_id']);*/
                $product_ids_certain_days[] = $values['product_id'];
            }
            if(in_array($values['product_id'], $product_ids_by_54) && (date('N') == 1 || date('N') == 7)){
                /*var_dump('1111');*/
                $product_ids_certain_days[] = $values['product_id'];
            }
            $_product = wc_get_product($values['variation_id'] ? $values['variation_id'] : $values['product_id']);
            if (!empty($_product) && $_product->exists() && $values['quantity'] > 0) {
                if ($_product->is_purchasable()) {
                    $session_data = array_merge($values, array('data' => $_product));
                    $cart_contents[$key] = apply_filters('woocommerce_get_cart_item_from_session', $session_data, $values, $key);
                }
            }
        }
        add_notice_message_product_ids_certain_days($product_ids_certain_days);
    }
    return $cart_contents;
}

/**
 * @param null $product_ids_certain_days
 */
function add_notice_message_product_ids_certain_days($product_ids_certain_days = null){
    global $woocommerce;
    $product_titles = array();
    if($product_ids_certain_days){
        foreach($product_ids_certain_days as $cart_product_id){
            $product = get_product( $cart_product_id );
            $product_titles[] = $product->post->post_title;
        }
    }
    if(!empty($product_titles)){
        wc_clear_notices();
        $text = implode(",<br>",$product_titles).'!';
        wc_add_notice( sprintf( __( "Please remove the following items from your cart, they are not available on the delivery date you have selected.<br> ".$text) ) ,'error' );
    }

}

add_action('wp_loaded', function () {
    if (!is_object(WC()->session)) {
        return;
    }
    global $compare_cart_items;
    foreach (theme_get_cart_contents() as $item) {
        $compare_cart_items[] = $item['data']->id;
    }
}, 20);

/**
 * @param $category_id
 * @return array
 */
function get_product_ids_by_category_id($category_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix;
    $string = "
SELECT   {$table_name}posts.ID FROM {$table_name}posts
LEFT JOIN {$table_name}term_relationships
ON ({$table_name}posts.ID = {$table_name}term_relationships.object_id)
INNER JOIN {$table_name}postmeta
ON ( {$table_name}posts.ID = {$table_name}postmeta.post_id ) WHERE 1=1
AND ( {$table_name}term_relationships.term_taxonomy_id IN ({$category_id}))
AND (
  ( {$table_name}postmeta.meta_key = '_visibility' AND {$table_name}postmeta.meta_value IN ('catalog','visible') )
)
AND {$table_name}posts.post_type = 'product'
AND (({$table_name}posts.post_status = 'publish')) GROUP BY {$table_name}posts.ID ORDER BY {$table_name}posts.post_date ASC";

    $results = $wpdb->get_results($string, ARRAY_A);

    $array_ids = array();
    if (!empty($results)) {
        foreach ($results as $value) {
            $array_ids[] = $value['ID'];
        }
    }
    return $array_ids;
}

add_filter('woocommerce_is_purchasable', function ($is_purchasable, $product) {
    global $compare_cart_items;
    global $product_cart_items;
    // Service Category ID
    $category_id = 14;
    //All services
    $product_cart_items = get_product_ids_by_category_id($category_id);
    $intersect = @array_intersect($compare_cart_items, $product_cart_items);
    $product_unic_ids = explode(',', get_option('services_unique_ids'));
    $intersect_unic = @array_intersect($compare_cart_items, $product_unic_ids);
    if (!empty($compare_cart_items)) {
        if (!empty($intersect)) {
            if (@in_array($product->id, $product_cart_items)) {
                $is_purchasable = in_array($product->id, $compare_cart_items);
            } else {
                if ($intersect_unic) {
                    $is_purchasable = FALSE;
                }
            }
        } else {
            if (in_array($product->id, $product_unic_ids)) {
                $is_purchasable = FALSE;
            }
        }
    } elseif (empty(WC()->cart->cart_contents)) {
        $is_purchasable = in_array($product->id, $product_cart_items);
    }
    return $is_purchasable;
}, 20, 2);

/**
 *  Remove cart items
 */
add_action('template_redirect', function () {
    global $product_cart_items;
    $cart_ids = array();
    foreach (WC()->cart->cart_contents as $prod_in_cart) {
        // Get the Product ID
        $cart_ids[] = $prod_in_cart['product_id'];
    }
    $intersect = @array_intersect($cart_ids, $product_cart_items);
    if (empty($intersect)) {
        WC()->cart->empty_cart(true);
        wc_clear_notices();
        cart_unset_all_notice();
    }
});

function cart_unset_all_notice()
{
    $notices = WC()->session->get('wc_notices', array());
    unset($notices['success'], $notices['error']);
    wc_add_notice('Your cart is empty. Please select a service.', 'error');
}

/**
 * Add Options Page in Settings
 */
add_action('admin_menu', function () {
    add_options_page(__('Services Unique Page', 'vague'), 'Services Unique Page', 'manage_options', 'functions', 'global_custom_options');
});

function global_custom_options()
{ ?>
    <div class="wrap">
        <h2><?php echo __('Unique Ids of Services','vogue'); ?></h2>
        <form method="post" action="options.php">
            <?php wp_nonce_field('update-options') ?>
            <p><label for="services_unique_ids"><strong><?php echo __('Services ID:','vogue'); ?></strong></label><br/>
                <input type="text" name="services_unique_ids" id="services_unique_ids" size="45"
                       value="<?php echo get_option('services_unique_ids'); ?>"/>
            </p>
            <p><b><i><?php echo __('Example: 123,986,568','vogue'); ?></i></b></p><br><hr><br>
            <p>
                <label for="checkout_cabin_pre_arrival_message"><b><?php echo __('Checkout Cabin Pre Arrival Message:','vogue'); ?></b></label>
            </p>
            <p>
                <textarea name="checkout_cabin_pre_arrival_message" id="checkout_cabin_pre_arrival_message" rows="10" cols="50"><?php echo get_option('checkout_cabin_pre_arrival_message'); ?></textarea>
            </p><br><hr><br>
            <p>
                <label for="unique_pa_text"><b><?php echo __('Unique Text Cabins:','vogue'); ?></b></label>
            </p>
            <p>
                <input type="text" name="unique_pa_text" id="unique_pa_text" size="45"  value="<?php echo get_option('unique_pa_text'); ?>"/>
            </p>
            <p><b><i><?php echo __('Example: Pre-Arrival','vogue'); ?></i></b></p><br><hr><br>
            <p><input type="submit" name="Submit" value="Save"/></p>
            <input type="hidden" name="action" value="update"/>
            <input type="hidden" name="page_options" value="services_unique_ids"/>
            <input type="hidden" name="page_options" value="checkout_cabin_pre_arrival_message"/>
            <input type="hidden" name="page_options" value="unique_pa_text"/>
        </form>
    </div>
<?php }

function add_message_in_checkout_form() { ?>
    <script>
        var $ = jQuery.noConflict();
        var messageText = '<span class="messageText"><?php echo get_option('checkout_cabin_pre_arrival_message'); ?></span>';
        //var messageText = '<span class="messageText">Good News! Your cabin is eligible for pre-arrival delivery. Your groceries will be waiting for you when you arrive!</span>';
        $('#cabin_selection').after(messageText);
        $(document).ready(function(){
            $('#cabin_selection').click(function(){
                var selectValue = $('#cabin_selection :selected').val();
                if(selectValue !== null &&  selectValue.search("<?php echo get_option('unique_pa_text'); ?>") > 0){
                    $('span.messageText').addClass('active');
                }
                else{
                    $('span.messageText').removeClass('active');
                }
            });
        });
    </script>

<?php
}

add_action( 'woocommerce_after_checkout_form', 'add_message_in_checkout_form');



add_action('init', 'mvv_remove_woocommerce_template_loop_product_thumbnail', 10);

function mvv_remove_woocommerce_template_loop_product_thumbnail() {
	remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
}

add_action('init', 'mvv_add_woocommerce_template_loop_product_thumbnail', 10);

function mvv_add_woocommerce_template_loop_product_thumbnail() {
	add_action('woocommerce_before_shop_loop_item_title', 'mvv_woocommerce_template_loop_product_thumbnail', 10);
}

if ( ! function_exists( 'mvv_woocommerce_template_loop_product_thumbnail' ) ) {

	function mvv_woocommerce_template_loop_product_thumbnail() {
		
		global $post;

		$terms = get_the_terms( $post->ID, 'product_tag' );
		
		$term_array = array();
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
            foreach ( $terms as $term ) {
                $term_array[] = $term->name;
            }
        }

		if( in_array('local', $term_array) ) {
			echo '<div class="local-flag">';
		}
		else {
			echo '<div>';
		}
		
		echo woocommerce_get_product_thumbnail();
		
		echo '</div>';
	}
}

add_filter('woocommerce_single_product_image_html','mvv_add_local_flag');

if ( ! function_exists( 'mvv_add_local_flag' ) ) {
	
	function mvv_add_local_flag($html, $postID) {

		$terms = get_the_terms( $postID, 'product_tag' );
		
		$term_array = array();
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
            foreach ( $terms as $term ) {
                $term_array[] = $term->name;
            }
        }

        if( in_array('local', $term_array) ) {
        	$html = str_replace( "class=\"woocommerce-main-image", "class=\"local-flag woocommerce-main-image", $html );
        }

		return $html;
	}
}