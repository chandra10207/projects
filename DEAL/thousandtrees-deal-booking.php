<?php
/*
Plugin Name: Thousand Trees Deal Booking
Plugin URI: http://nirmal.com.au/
Version: 1.0
Author: Nirmal Web Studio
Description: Deal Booking System Compactible with woocommerce and multi-vendor plugins
Text Domain: thtrsdealbooking
 */

class thousandtrees_deal_booking
{

    public function __construct()
    {

        //enqueue frontend style/script
        add_action('wp_enqueue_scripts', array($this, 'thtrsdeal_frontend_script_style'));
        //enqueue admin style/script
        add_action('admin_enqueue_scripts', array($this, 'thtrsdeal_admin_script_style'));

        //add adminstrator woo metaboxes
        add_action('add_meta_boxes', array($this, 'thtrsdeal_adminwoo_dealbasicfield'));
        //save administrator woo metabox
        add_action('save_post', array($this, 'thtrsdeal_adminwoo_dealbasicfield_save'));
        //load state according to country selected
        add_action('wp_ajax_thtrsdeal_ajax_load_state', array($this, 'thtrsdeal_ajax_load_state'));
        add_action('wp_ajax_nopriv_thtrsdeal_ajax_load_state', array($this, 'thtrsdeal_ajax_load_state'));

        //add custom corn jobs
        register_activation_hook(__FILE__, array($this, 'thtrsdeal_cronstarter_activation'));
        //unschedule event upon plugin deactivation
        register_deactivation_hook(__FILE__, array($this, 'thtrsdeal_cronstarter_deactivate'));
        // check epiration date function we'd like to call with our cron job
        add_action('thousandtree_hourly_event', array($this, 'thtrsdeal_unpublish_product'));

        //Display location single woo page
        // add_action('woocommerce_single_product_summary', array($this, 'thtrsdeal_singlewoo_location'));
        //Display rules in single woo page
        add_action('woocommerce_product_meta_start', array($this, 'thtrsdeal_singlewoo_rules'));
        //Display location map in single woo page
        add_action('woocommerce_product_meta_start', array($this, 'thtrsdeal_singlewoo_locationmap'));

        //Custom field in add to cart form
        add_action('woocommerce_before_add_to_cart_button', array($this, 'thtrsdeal_singlewoo_booknowfield'));

        //Custom field to save cart item meta
        add_action('woocommerce_add_cart_item_data', array($this, 'thtrsdeal_save_cartitem_meta'), 11, 2);
        //Display custom field in cart session
        add_filter('woocommerce_cart_item_name', array($this, 'thtrsdeal_display_cart_session'), 20, 3);
        //Display custom fied in order
        add_action('woocommerce_add_order_item_meta', array($this, 'wdm_add_values_to_order_item_meta'), 1, 2);


        // add the filter
        add_filter('woocommerce_cart_item_price', array($this, 'filter_woocommerce_cart_item_price'), 10, 3);

        // add the filter
        add_filter('woocommerce_cart_item_quantity', array($this, 'filter_woocommerce_cart_item_quantity'), 10, 2);

        // add the filter
        /*add_filter('woocommerce_cart_item_subtotal', array($this, 'filter_woocommerce_cart_item_subtotal'), 10, 3);
*/    }

    public function thtrsdeal_admin_script_style()
    {

        //enqueue style script in product post type
        $product_screen = get_current_screen();
        if ('product' == $product_screen->post_type) {

            wp_enqueue_style('thtrsdeal-product-admin.css', plugin_dir_url(__FILE__) . 'css/thtrsdeal-product-admin.css', array(), '20176453');

            //enqueue date picker
            wp_enqueue_script('jquery-ui-datepicker');
            //datepicker css from CDN
            wp_enqueue_style('jquery-ui', 'http://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css', array(), '20171106');
            //Booking system js
            wp_enqueue_script('thtrsdeal-product-admin.js', plugin_dir_url(__FILE__) . 'js/thtrsdeal-product-admin.js', array(), '20171129', true);

            wp_localize_script('thtrsdeal-product-admin.js', 'thtrsdeal_obj', array(

                'ajaxurl' => admin_url('admin-ajax.php'),

            ));
        }

    }

    public function thtrsdeal_frontend_script_style()
    {

        //enqueue style script in product single page
        if (is_product()) {

            //enqueue date picker
            wp_enqueue_script('jquery-ui-datepicker');
            //datepicker css from CDN
            wp_enqueue_style('jquery-ui', 'http://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css', array(), '20171106');
            //Booking system js
            wp_enqueue_script('thtrsdeal-product.js', plugin_dir_url(__FILE__) . 'js/thtrsdeal-product.js', array(), '20171129', true);
        }

    }

    //Metabox of basic deals
    public function thtrsdeal_adminwoo_dealbasicfield()
    {
        //Add deal basic settings
        add_meta_box(
            'thtrs_deal_basic_fields',
            __('Thousandtrees Deal Settings', 'thtrsdealbooking'),
            array($this, 'thtrsdeal_adminwoo_dealbasicfield_callback'),
            'product',
            'normal',
            'high'

        );

        //Add rules metabox
        add_meta_box(
            'thtrs_deal_rules_field',
            __('Deal Rules', 'thtrsdealbooking'),
            array($this, 'thtrs_deal_rules_field_callback'),
            'product',
            'normal',
            'high'
        );

    }

    //ajax to load state as per selected countries
    public function thtrsdeal_ajax_load_state()
    {
        global $woocommerce;
        $countires_obj = new WC_Countries();
        $state         = $_POST['country_key'];

        if (!empty($state)) {

            $deafult_country_state = $countires_obj->get_states($state);

            if (!empty($deafult_country_state)) {

                $state_list .= '';
                $state_list .= '<select id="deal_state">';
                foreach ($deafult_country_state as $view_state) {
                    $state_list .= '<option>' . $view_state . '</option>';
                }
                $state_list .= '</select>';

            } else {

                $state_list = "!!Oops No States Found";
            }

            echo $state_list;
            die;

        }
    }

    //Callback of basic deals
    public function thtrsdeal_adminwoo_dealbasicfield_callback()
    {
        global $post, $woocommerce;

        //current user country
        $user_ip = getenv('REMOTE_ADDR');
        $geo     = unserialize(file_get_contents("http://www.geoplugin.net/php.gp?ip=$user_ip"));
        $country = $geo["geoplugin_countryName"];

        //get list of countries using Woocommerce API
        $countries_obj         = new WC_Countries();
        $countries             = $countries_obj->__get('countries');
        $default_county_states = $countries_obj->get_states('NP');

        $values           = get_post_custom($post->ID);
        $discount_tagline = isset($values['wcv_discount_tagline']) ? esc_attr($values['wcv_discount_tagline'][0]) : '';
        $date             = isset($values['wcv_expiry_date']) ? esc_attr($values['wcv_expiry_date'][0]) : '';
        $startdate        = isset($values['wcv_start_date']) ? esc_attr($values['wcv_start_date'][0]) : '';

        // for location
        $np_booking_location = isset($values['np_booking_location']) ? esc_attr($values['np_booking_location'][0]) : '';

        $projectmaplat            = isset($values['projectmaplat']) ? esc_attr($values['projectmaplat'][0]) : '';
        $projectmaplng            = isset($values['projectmaplng']) ? esc_attr($values['projectmaplng'][0]) : '';
        $project_city             = isset($values['project_city']) ? esc_attr($values['project_city'][0]) : '';
        $project_state            = isset($values['project_state']) ? esc_attr($values['project_state'][0]) : '';
        $project_country          = isset($values['project_country']) ? esc_attr($values['project_country'][0]) : '';
        $project_display_location = isset($values['project_display_location']) ? esc_attr($values['project_display_location'][0]) : '';

        // location end

        // Deal type

        $deal_package = isset($values['deal_package']) ? esc_attr($values['deal_package'][0]) : '';

        /*$deal_per_night = isset( $values['deal_per_night'] ) ? esc_attr( $values['deal_per_night'][0] ) : '';

        $deal_per_person = isset( $values['deal_per_person'] ) ? esc_attr( $values['deal_per_person'][0] ) : '';*/

        // Deal type end

        $days_numb    = isset($values['wcv_number_of_days']) ? esc_attr($values['wcv_number_of_days'][0]) : '';
        $adult        = isset($values['wcv_number_of_adult']) ? esc_attr($values['wcv_number_of_adult'][0]) : '';
        $kids         = isset($values['wcv_number_of_kids']) ? esc_attr($values['wcv_number_of_kids'][0]) : '';
        $adults_price = isset($values['wcv_adults_price']) ? esc_attr($values['wcv_adults_price'][0]) : '';
        $kids_price   = isset($values['wcv_kids_price']) ? esc_attr($values['wcv_kids_price'][0]) : '';
        $smoking_allowed   = isset($values['wcv_custom_product_smoking_deal']) ? esc_attr($values['wcv_custom_product_smoking_deal'][0]) : '';
        $pet_allowed   = isset($values['wcv_custom_product_pets_allowed']) ? esc_attr($values['wcv_custom_product_pets_allowed'][0]) : '';

        wp_nonce_field('thtrsdeal_adminwoo', 'thtrsdeal_adminwoo_field');
        ?>


        <table class="form-table">
        <tr>
            <th>I am adding a Deal</th>
            <td><input id="deal_package" class="deal_" type="checkbox" <?php if (isset($deal_package) && $deal_package == 1) {echo 'checked';} else {echo '';}?> name="deal_package" value="1" onchange="valueChanged()" />
            </td>
        </tr>
        <tr>
            <th>I am adding a per night price</th>
            <td>
                <input id="deal_per_night" class="deal_" type="checkbox" <?php if (isset($deal_package) && $deal_package == 2) {echo 'checked';} else {echo '';}?>  name="deal_package" value="2" onchange="valueChanged()"/>
            </td>
        </tr>
        <tr>
            <th>I am adding a per person price</th>
            <td>
                <input id="deal_per_person" class="deal_" type="checkbox" <?php if (isset($deal_package) && $deal_package == 3) {echo 'checked';} else {echo '';}?>  name="deal_package" value="3" onchange="valueChanged()" />
            </td>
        </tr>

        <tr>
            <th>Discount Tagline</th>
            <td>
                <input type="text" name="wcv_discount_tagline" id="wcv_discount_tagline" value="<?php echo $discount_tagline; ?>"/>
            </td>
            <td class="description"><small>Enter in % . Displays discount tagline in product listing</small></td>
        </tr>

        <tr>
            <th>Start Date</th>
            <td>
                <input type="text" name="wcv_start_date" id="wcv_start_date" value="<?php echo $startdate; ?>" required/>
            </td>
            <td class="description"><small>Add Start date for your deal</small></td>
        </tr>

        <tr>
            <th>Expiry Date</th>
            <td>
                <input type="text" name="wcv_expiry_date"  id="wcv_expiry_date" value="<?php echo $date; ?>" required/>
            </td>
            <td class="description"><small>Your deal won't be displayed in website after crossing expire date</small></td>
        </tr>


        <tr>
            <th>Number of days</th>
            <td>
                <input type="number" name="wcv_number_of_days" id="wcv_number_of_days" value="<?php echo $days_numb ?>" />
            </td>
            <td class="description"><small>Enter number of days of the deal.</small></td>
        </tr>
<tr>
<th>Location Detail:</th>
</tr>
        <tr>
            <th>Location</th>
            <td>
                <input type="text" style="width: 300px; position: absolute;" name="np_booking_location" id="np_booking_location" value="<?php echo $np_booking_location; ?>" required/>
        <input type="hidden" name="projectmaplat" id="projectmaplat" value="<?php echo $projectmaplat; ?>" />
        <input type="hidden" name="projectmaplng" id="projectmaplng" value="<?php echo $projectmaplng; ?>" />


        <input type="hidden" name="project_display_location" id="project_display_location" value="<?php echo $project_display_location; ?>" />
            </td>

        </tr>

        <tr>
        <th>City</th>
        <td>
        <input type="text" name="project_city" id="project_city" value="<?php echo $project_city; ?>" />
        </td>
        </tr>

        <tr>
        <th>State</th>
        <td>
        <input type="text" name="project_state" id="project_state" value="<?php echo $project_state; ?>" />
        </td>
        </tr>

        <tr>
        <th>Country</th>
        <td>
        <input type="text" name="project_country" id="project_country" value="<?php echo $project_country; ?>" />
        </td>
        </tr>

<tr><th>Number Of People:</th></tr>
        <tr>
            <td>Adults</td>
            <td>
             <small class="description"><small>Number of Adults.</small>
                <input type="number" name="wcv_number_of_adult" id="wcv_number_of_adult" value="<?php echo $adult; ?>" required/>
            </td>
            <td>Adult Price</td>
            <td>
                <small class="description"><small>Adult Price.</small>
                <input type="text"  name="wcv_adults_price" id="wcv_adults_price" value="<?php echo $adults_price; ?>"/>
            </td>

        </tr>

        <tr>
            <td>Kids</td>
            <td>

                <input type="number" name="wcv_number_of_kids" id="thtrs_kids" value="<?php echo $kids; ?>" />
          </td>
          <td>Kid Price </td>
          <td>
          <small class="description"><small>Kids Price.</small>
            <input type="text"  name="wcv_kids_price" id="wcv_kids_price" value="<?php echo $kids_price; ?>"/>
              </td>

        </tr>
        <tr>
        <th>Smoking Allowedss</th>
        <td>
        <select id="wcv_custom_product_smoking_deal" name="wcv_custom_product_smoking_deal" class="select2" tabindex="-1" title="Smoking Allowed">
           <option value="<?php echo $smoking_allowed;?>"><?php echo $smoking_allowed;?></option>
           <option value="<?php if($smoking_allowed == 'No'){
            echo 'Yes';
           }
           else{
            echo 'No';
           }?>"><?php if($smoking_allowed == 'No'){
            echo 'Yes';
           }
           else{
            echo 'No';
           }?></option>
    </select>
        <?php print_r($smoking_allowed);?>
        </td>
        </tr>
           <tr>
        <th>Pet allowed</th>
        <td>
        <select id="wcv_custom_product_pets_allowed" name="wcv_custom_product_pets_allowed" class="select2"  tabindex="-1" title="Pets Allowed">
           <option value="<?php echo $pet_allowed;?>"><?php echo $pet_allowed;?></option>
           <option value="<?php if($pet_allowed == 'No'){
            echo 'Yes';
           }
           else{
            echo 'No';
           }?>"><?php if($pet_allowed == 'No'){
            echo 'Yes';
           }
           else{
            echo 'No';
           }?></option>
    </select>
        <?php print_r($pet_allowed);?>
        </td>
        </tr>



        </table>

        <p>

        <?php

    }

    //Callback for rules field
    public function thtrs_deal_rules_field_callback()
    {
        global $post;
        $value   = get_post_custom($post->ID);
        $content = isset($value['wcv_deal_rules']) ? $value['wcv_deal_rules'][0] : '';
        wp_nonce_field('thtrsdeal_adminwoo', 'thtrsdeal_adminwoo_field');
        wp_editor(
            $content,
            'wcv_deal_rules'
        );
    }

    //Save basic fields
    public function thtrsdeal_adminwoo_dealbasicfield_save($post_id)
    {
        // Bail if we're doing an auto save
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // if our nonce isn't there, or we can't verify it, bail
        if (!isset($_POST['thtrsdeal_adminwoo_field']) || !wp_verify_nonce($_POST['thtrsdeal_adminwoo_field'], 'thtrsdeal_adminwoo')) {
            return;
        }

        // if our current user can't edit this post, bail
        if (!current_user_can('edit_post')) {
            return;
        }

        //save deal package
        if ($_POST['deal_package']) {
            update_post_meta($post_id, 'deal_package', $_POST['deal_package']);
        }

        /*//save deal per night
        if( $_POST['deal_per_night'] ){
        update_post_meta( $post_id, 'deal_per_night', $_POST['deal_per_night'] );
        }

        //save deal per person
        if( $_POST['deal_per_person'] ){
        update_post_meta( $post_id, 'deal_per_person', $_POST['deal_per_person'] );
        }*/

        //save discount percentage for tagline
        if (isset($_POST['wcv_discount_tagline'])) {
            update_post_meta($post_id, 'wcv_discount_tagline', $_POST['wcv_discount_tagline']);
        }

        //save deal expiry date
        if ($_POST['wcv_expiry_date']) {
            update_post_meta($post_id, 'wcv_expiry_date', $_POST['wcv_expiry_date']);
        }
        //save deal expiry date
        if ($_POST['wcv_start_date']) {
            update_post_meta($post_id, 'wcv_start_date', $_POST['wcv_start_date']);
        }

        //save deal location
        if ($_POST['np_booking_location']) {
            update_post_meta($post_id, 'np_booking_location', $_POST['np_booking_location']);
        }
        if ($_POST['projectmaplat']) {
            update_post_meta($post_id, 'projectmaplat', $_POST['projectmaplat']);
        }
        if ($_POST['projectmaplng']) {
            update_post_meta($post_id, 'projectmaplng', $_POST['projectmaplng']);
        }
        if ($_POST['project_city']) {
            update_post_meta($post_id, 'project_city', $_POST['project_city']);
        }
        if ($_POST['project_state']) {
            update_post_meta($post_id, 'project_state', $_POST['project_state']);
        }
        if ($_POST['project_country']) {
            update_post_meta($post_id, 'project_country', $_POST['project_country']);
        }
        if ($_POST['project_display_location']) {
            update_post_meta($post_id, 'project_display_location', $_POST['project_display_location']);
        }

        //save deal running days
        if ($_POST['wcv_number_of_days']) {
            update_post_meta($post_id, 'wcv_number_of_days', $_POST['wcv_number_of_days']);
        }

        //save number of people adult, kids
        if (isset($_POST['wcv_number_of_adult'])) {
            update_post_meta($post_id, 'wcv_number_of_adult', $_POST['wcv_number_of_adult']);
        }
        if (isset($_POST['wcv_number_of_kids'])) {
            update_post_meta($post_id, 'wcv_number_of_kids', $_POST['wcv_number_of_kids']);
        }
        if (isset($_POST['wcv_adults_price'])) {
            update_post_meta($post_id, 'wcv_adults_price', $_POST['wcv_adults_price']);
        }
        if (isset($_POST['wcv_kids_price'])) {
            update_post_meta($post_id, 'wcv_kids_price', $_POST['wcv_kids_price']);
        }
         if (isset($_POST['wcv_custom_product_smoking_deal'])) {
            update_post_meta($post_id, 'wcv_custom_product_smoking_deal', $_POST['wcv_custom_product_smoking_deal']);
        } 
        if (isset($_POST['wcv_custom_product_pets_allowed'])) {
            update_post_meta($post_id, 'wcv_custom_product_pets_allowed', $_POST['wcv_custom_product_pets_allowed']);
        }


        //save rules data
        if ($_POST['wcv_deal_rules']) {
            update_post_meta($post_id, 'wcv_deal_rules', $_POST['wcv_deal_rules']);
        }
    }

    //Display location on woo single after product title
    // public function thtrsdeal_singlewoo_location()
    // {
    //     global $post;
    //     $value               = get_post_custom($post->ID);
    //     $np_booking_location = isset($value['np_booking_location']) ? $value['np_booking_location'][0] : '';

    //     if (!empty($np_booking_location)) {
    //         echo '<br /><p>Location: ' . $np_booking_location . '</p>';
    //     }
    // }
    //Display rules on woo single product
    public function thtrsdeal_singlewoo_rules()
    {
        global $post;
        $value   = get_post_custom($post->ID);
        $content = isset($value['wcv_deal_rules']) ? $value['wcv_deal_rules'][0] : '';

        if (!empty($content)) {
            echo '<h2>Rules</h2>';
            echo '<div><p>' . $content . '</p></div>';
        }

    }

    //Display location map woo single product
    public function thtrsdeal_singlewoo_locationmap()
    {
        global $post;
        $value               = get_post_custom($post->ID);
        $np_booking_location = isset($value['np_booking_location']) ? $value['np_booking_location'][0] : '';

        echo '<h2>Location Map</h2>';

    }

    //Custom field in add to cart form
    public function thtrsdeal_singlewoo_booknowfield()
    {
        global $post;

        /*echo "<pre>";
        print_r($post);
        echo "</pre>";*/
        $value     = get_post_custom($post->ID);
        $adult     = isset($value['wcv_number_of_adult']) ? esc_attr($value['wcv_number_of_adult'][0]) : '';
        $kids      = isset($value['wcv_number_of_kids']) ? esc_attr($value['wcv_number_of_kids'][0]) : '';
        $days_numb = isset($value['wcv_number_of_days']) ? esc_attr($value['wcv_number_of_days'][0]) : '';

        $deal_package = isset($value['deal_package']) ? esc_attr($value['deal_package'][0]) : '';
        /*$deal_per_night = isset( $value['deal_per_night'] ) ? esc_attr( $value['deal_per_night'][0] ) : '';
        $deal_per_person = isset( $value['deal_per_person'] ) ? esc_attr( $value['deal_per_person'][0] ) : '';*/

        /*echo  "package".$deal_package; echo "</br>";
        echo "night".$deal_per_night; echo "</br>";
        echo "person".$deal_per_person; echo "</br>";*/

        ?>
        <?php if (!empty($days_numb) && $deal_package != 2 ): ?>
        <p>
            Total Days: <?php echo $days_numb; ?>
        </p>
        <?php endif;?>
        


            <?php
global $product;
        $product_ID = $product->get_id();

        $pro = new WC_Product($product_ID);
        echo "Available: ";
        if ($pro->get_stock_quantity() != 0) {
            echo $pro->get_stock_quantity(); //Get number of  availability
            echo "<b>availability Status: </b>" . $pro->is_in_stock(); //Get availability Status
        }
        ?>

            <?php if (!empty($adult || $kids)): ?>
                <div class="row">
                    
                </div>
                            <div class="no-of-people">Number of People Allowed:
                            <?php if (!empty($adult)): ?>
                            <span class="no"><?php echo $adult; ?> Adult/s</span>
                            <?php endif;?>

            <?php if (!empty($kids)): ?>
            <span class="no">&amp; <?php echo $kids; ?> Kid/s</span>

            </div>
            <?php endif;?>
        <?php endif;?>
        <div class="form-group">
            <input type="text" class="form-control" name="user_checkin_date" id="user_checkin_date_updated" placeholder="Check In" required/>
        </div>
        <div class="form-group">
            <input type="text" class="form-control" name="user_checkout_date" id="user_checkout_date_updated"  placeholder="Check Out" required/>
        </div>
        <div class="form-group form-select">
            <select name="number_of_adults" id="number_of_adults" required="" class="form-control">
                <option value="">Adults</option>
                <?php for ($i = 1; $i <= $adult; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor;?>
            </select>
            <select name="number_of_kids" id="number_of_kids" class="form-control">
                <option value="">Kids</option>
                 <?php for ($i = 1; $i <= $kids; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor;?>
            </select>
        </div>
        <p>
         <span id="calc-price" class="price">
         </span>
        </p>

        <!-- <p>
            Days between selected dates : <input type="text" id="totaldays" value="">
        </p> -->

        <?php
// to pass expiry date in js file
        $meta = get_post_meta($product_ID);

        $date1   = $meta['wcv_expiry_date']['0'];
        $newDate = date("M m/d/Y", strtotime(str_replace('/','-',$date1)));
        //if ($deal_package) : ?>

        <p>
            <span id="message-box">
            </span>

        <script>
            var deal_package = '<?php echo $deal_package; ?>';
            var vatrate = '<?php echo $days_numb; ?>';
            var url = "<?php echo site_url(); ?>";
            var expiry_date = "<?php echo $newDate; ?>";


        </script>

        </p>

        <!-- <p>Kids
        <input type="number" name="kids_number" id="kids_number"  />
        </p> -->

    <?php
//endif;
    }

    //Save cart item meta
    public function thtrsdeal_save_cartitem_meta($cart_item_meta, $product_id)
    {

        $cart_item_meta['user_checkin_date']  = $_POST['user_checkin_date'];
        $cart_item_meta['user_checkout_date'] = $_POST['user_checkout_date'];
        $cart_item_meta['number_of_adults']   = $_POST['number_of_adults'];
        $cart_item_meta['number_of_kids']     = $_POST['number_of_kids'];
        /*$cart_item_meta['kids_number'] = $_POST['kids_number'];*/
        return $cart_item_meta;

    }

    //Display in cart session
    public function thtrsdeal_display_cart_session($product_name, $values, $cart_item_key)
    {

        //  echo '<pre>';
        // print_r($values);
        // die();

        $return_string = $product_name . "</a><dl class='variation'>";
        $return_string .= "<table class='wdm_options_table aaa'>";
        if (!empty($values['user_checkin_date'])) {
            $return_string .= "<tr><td> Check in date: " . $values['user_checkin_date'] . "</td></tr>";
            print_r($value);
        }
        if (!empty($values['user_checkout_date'])) {
            $return_string .= "<tr><td> Check out date: " . $values['user_checkout_date'] . "</td></tr>";
            print_r($value);
        }

        // if (!empty($values['number_of_adults'])) {
        //     $return_string .= "<tr><td> Adults: " . $values['number_of_adults'] . "</td></tr>";
        // }

        // if (!empty($values['number_of_kids'])) {
        //     $return_string .= "<tr><td> Kids: " . $values['number_of_kids'] . "</td></tr>";
        // }
        $return_string .= "</table></dl>";
        return $return_string;
    }


   




    // define the woocommerce_cart_item_price callback
    public function filter_woocommerce_cart_item_price($price, $cart_item, $cart_item_key)
    {
        $price_adults = get_post_meta($cart_item['product_id'], 'wcv_adults_price')[0];
        $price_kids   = get_post_meta($cart_item['product_id'], 'wcv_kids_price')[0];
        $product      = wc_get_product($cart_item['product_id']);

        $price = $product_name . "</a><dl class='variation'>";
        $price .= "<table class='wdm_options_table'>";
        // print_r($cart_item['data']);
        $data = $cart_item['data']->data;
        if (isset($price_adults) && !empty($price_adults) && $price_adults != ' ') {
            $price .= "<tr><td> Adults: " . get_woocommerce_currency_symbol() . $price_adults . "</td></tr>";
            $price .= "<tr><td> Kids: " . get_woocommerce_currency_symbol() . $price_kids . "</td></tr>";
        } else {
            $price .= "<tr><td> " . $product->get_price() . "</td></tr>";
        }

        $price .= "</table></dl>";
        return $price;
    }

    // define the woocommerce_cart_item_quantity callback
    public function filter_woocommerce_cart_item_quantity($product_quantity, $cart_item_key)
    {
        // make filter magic happen here...
        $price = $product_quantity . "</a><dl class='variation'>";
        $product_quantity .= "<table class='wdm_options_table'>";
        $cart_item = WC()->cart->cart_contents[$cart_item_key];
        $adults    = $cart_item['number_of_adults'];
        $kids      = $cart_item['number_of_kids'];
        if (isset($adults) || isset($kids)) {
            // $data = $cart_item['data']->data;
            if (isset($adults)) {
                $product_quantity .= "<tr><td> Adults: " . $adults . "</td></tr>";

            }
            if ($kids) {
                $product_quantity .= "<tr><td> Kids: " . $kids . "</td></tr>";
            }

            $product_quantity .= "</table></dl>";
        }
        $price .= "</table></dl>";
        return $product_quantity;
    }

    // define the woocommerce_cart_item_subtotal callback
/*    public function filter_woocommerce_cart_item_subtotal($total, $cart_item, $cart_item_key)
    {
        // make filter magic happen here...
        $discount = get_post_meta($cart_item['product_id'], 'wcv_discount_tagline', true);
        $discount = get_post_meta($cart_item['product_id'], 'wcv_discount_tagline', true);
        $subtotal = $cart_item["line_subtotal"];
        $price = $subtotal-($discount/100)*$subtotal;

        if (!empty($discount)) {
            $total = $total . "</a><dl class='variation'>";
            $total .= "<table class='wdm_options_table'>";
            $total .= "<tr><td>Discount: ".$discount."% <br> After Discount: ".get_woocommerce_currency_symbol().$price."</td></tr>";

            $total .= "</table></dl>";
        }

        return $total;
    }*/

    // create a scheduled event (if it does not exist already)-----------------------------------
    public function thtrsdeal_cronstarter_activation()
    {
        wp_schedule_event(current_time('timestamp'), 'daily', 'thousandtree_hourly_event');
    }

    //unschedule event upon plugin deactivation
    public function thtrsdeal_cronstarter_deactivate()
    {
        wp_clear_scheduled_hook('thousandtree_hourly_event');
    }

    //unpublish product after crossing expire date
    public function thtrsdeal_unpublish_product()
    {
        // post_status = draft
        global $wpdb;
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
        );
        $query = new WP_Query($args);
        while ($query->have_posts()): $query->the_post();

            //get expire date of product
            $expire_date = get_post_meta(get_the_ID(), 'wcv_expiry_date', true);
            if (!empty($expire_date)) {
                //check if product is expire
                if (strtotime(date("Y-m-d")) > strtotime($expire_date)) {

                    //update posts table value
                    $wpdb->update(
                        $wpdb->prefix . 'posts',
                        array(
                            'post_status' => 'draft',
                        ),
                        array('ID' => get_the_ID())
                    );
                }
            }

        endwhile;
        wp_reset_postdata();

    }


  function wdm_add_values_to_order_item_meta($item_id, $values)
  {
        global $woocommerce,$wpdb;

       /* echo '<pre>';
        print_r($values); 
             */
        $user_checkin_date = $values['user_checkin_date'];
        if(!empty($user_checkin_date))
        {
            wc_add_order_item_meta($item_id,' Check In Date',$user_checkin_date); 
             
        } 
         $user_checkout_date = $values['user_checkout_date'];
        if(!empty($user_checkout_date))
        {
            wc_add_order_item_meta($item_id,' Check Out Date',$user_checkout_date); 

        }
        $user_number_of_adult = $values['number_of_adults'];
        if(!empty($user_number_of_adult))
        {
            wc_add_order_item_meta($item_id,' No. of Adult',$user_number_of_adult); 

        }
        $user_number_of_kids = $values['number_of_kids'];
        if(!empty($user_number_of_kids))
        {
            wc_add_order_item_meta($item_id,' No. of Kids',$user_number_of_kids);

        }

        
        
        
  }

  


}
$thoudantrees_deal_booking = new thousandtrees_deal_booking;

?>




