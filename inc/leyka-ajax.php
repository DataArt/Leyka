<?php if( !defined('WPINC') ) die;
/** Different ajax handler functions */

function leyka_ajax_get_campaigns_list() {

    if(empty($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'leyka_get_campaigns_list_nonce')) {
        die(json_encode(array()));
    }

    $_REQUEST['term'] = empty($_REQUEST['term']) ? '' : trim($_REQUEST['term']);

    $campaigns = leyka_get_campaigns_list(array(
        'meta_query' => array(array(
            'key' => 'payment_title', 'value' => $_REQUEST['term'], 'compare' => 'LIKE', 'type' => 'CHAR',
        )),
    ), 0);

    $ids_found = array();
    $count = count($campaigns);
    for($i=0; $i<$count; $i++) {

        $ids_found[] = $campaigns[$i]->ID;
        $campaigns[$i] = array(
            'value' => $campaigns[$i]->ID,
            'label' => $campaigns[$i]->post_title,
            'payment_title' => get_post_meta($campaigns[$i]->ID, 'payment_title', true),
        );
    }

    foreach(leyka_get_campaigns_list(array('s' => $_REQUEST['term']), 0) as $campaign) { // Any criteria search - low priority
        if( !in_array($campaign->ID, $ids_found) ) {
            $campaigns[] = array(
                'value' => $campaign->ID,
                'label' => $campaign->post_title,
                'payment_title' => get_post_meta($campaign->ID, 'payment_title', true),
            );
        }
    }

    die(json_encode($campaigns));

}
add_action('wp_ajax_leyka_get_campaigns_list', 'leyka_ajax_get_campaigns_list');
add_action('wp_ajax_nopriv_leyka_get_campaigns_list', 'leyka_ajax_get_campaigns_list');

function leyka_recalculate_total_funded_action() {

    if( !wp_verify_nonce($_GET['nonce'], 'leyka_recalculate_total_funded_amount') ) {
        wp_die(__('Error: incorrect request parameters', 'leyka'));
    }

    if(empty($_GET['campaign_id'])) {
        wp_die(__('Error: campaign ID is missing', 'leyka'));
    }

    $campaign = new Leyka_Campaign((int)$_GET['campaign_id']);
    $campaign->update_total_funded_amount();

    wp_die($campaign->total_funded);

}
add_action('wp_ajax_leyka_recalculate_total_funded_amount', 'leyka_recalculate_total_funded_action');
add_action('wp_ajax_nopriv_leyka_recalculate_total_funded_amount', 'leyka_recalculate_total_funded_action');


function leyka_get_gateway_redirect_data() {

    leyka()->clear_session_errors(); // Clear all previous submits errors, if there are some

    $form_errors = Leyka_Payment_Form::is_form_fields_valid();
    if(is_array($form_errors) && count($form_errors) > 0) {

        $form_errors = reset($form_errors); // Return only the first error in the list

        /** @var WP_Error $form_errors */
        die(json_encode(array('status' => 1, 'message' => $form_errors->get_error_message(),)));

    }

    $pm = leyka_pf_get_payment_method_value();

    if(empty($_POST['without_form_submission'])) { // Normal donation submit procedure

        $donation_id = leyka()->log_submission();

        if( !is_wp_error($donation_id) ) {

            leyka_remember_donation_data(array('donation_id' => $donation_id));

            do_action(
                'leyka_payment_form_submission-'.$pm['gateway_id'],
                $pm['gateway_id'], implode('-', array_slice($pm, 1)), $donation_id, $_POST
            );

        }

        $payment_vars = array(
            'status' => $donation_id && !is_wp_error($donation_id) ? 0 : 1,
            'payment_url' => apply_filters('leyka_submission_redirect_url-'.$pm['gateway_id'], '', $pm['payment_method_id']),
            'submission_redirect_type' => apply_filters(
                'leyka_submission_redirect_type-'.$pm['gateway_id'],
                'auto', $pm['payment_method_id'], $donation_id
            ),
        );

        if($payment_vars['status'] == 0) {
            $payment_vars['donation_id'] = $donation_id;
        } else {
            $payment_vars['errors'] = $donation_id;
        }

        $payment_vars = array_merge(
            apply_filters('leyka_submission_form_data-'.$pm['gateway_id'], $_POST, $pm['payment_method_id'], $donation_id),
            $payment_vars
        );

    } else { // Get payment vars without donation submit
        $payment_vars = array_merge(
            apply_filters('leyka_submission_form_data-'.$pm['gateway_id'], $_POST, $pm['payment_method_id'], false),
            array(
                'status' => 0,
                'payment_url' => apply_filters('leyka_submission_redirect_url-'.$pm['gateway_id'], '', $pm['payment_method_id']),
            )
        );
    }

    die(json_encode($payment_vars));

}
add_action('wp_ajax_leyka_ajax_get_gateway_redirect_data', 'leyka_get_gateway_redirect_data');
add_action('wp_ajax_nopriv_leyka_ajax_get_gateway_redirect_data', 'leyka_get_gateway_redirect_data');

function leyka_process_success_form() {

    if(empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'leyka_donor_subscription')) {
        die(json_encode(array(
            'status' => 1,
            'message' => __('Wrong nonce in the submitted data', 'leyka'),
        )));
    } else if(empty($_POST['leyka_donation_id'])) {
        die(json_encode(array(
            'status' => 1,
            'message' => __('No donation ID found in the submitted data', 'leyka'),
        )));
    }

    $donation = new Leyka_Donation((int)$_POST['leyka_donation_id']);
    if( !$donation ) {
        die(json_encode(array(
            'status' => 1,
            'message' => __('Wrong donation ID in the submitted data', 'leyka'),
        )));
    }

    if(isset($_POST['leyka_donor_name']) && leyka_validate_donor_name($_POST['leyka_donor_name'])) {
        $donation->donor_name = $_POST['leyka_donor_name'];
    }

    if(isset($_POST['leyka_donor_email']) && leyka_validate_email($_POST['leyka_donor_email'])) {

        $donation->donor_email = $donation->donor_email ? $donation->donor_email : $_POST['leyka_donor_email'];
        $donation->donor_subscription_email = $_POST['leyka_donor_email'];
        $donation->donor_subscribed = $donation->campaign_id;

    }

    leyka_remembered_data('donation_id', false, true); // Delete the donor data cookie

    die(json_encode(array(
        'status' => 0,
    )));

}
add_action('wp_ajax_leyka_donor_subscription', 'leyka_process_success_form');
add_action('wp_ajax_nopriv_leyka_donor_subscription', 'leyka_process_success_form');

function leyka_set_campaign_photo() {
    
    if(empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'set-campaign-photo')) {
        die(json_encode(array(
            'status' => 'error',
            'message' => __('Wrong nonce in the submitted data', 'leyka'),
        )));
    } else if(empty($_POST['campaign_id'])) {
        die(json_encode(array(
            'status' => 'error',
            'message' => __('Error: campaign ID is missing', 'leyka'),
        )));
    }
    
    $attachment_id = (int)$_POST['attachment_id'];
    $campaign_id = (int)$_POST['campaign_id'];
    
    update_post_meta($campaign_id, '_thumbnail_id', $attachment_id);
    sleep(1);
    
    die(json_encode(array(
        'status' => 'ok',
        'post' => $_POST,
    )));
    
}
add_action('wp_ajax_leyka_set_campaign_photo', 'leyka_set_campaign_photo');

function leyka_set_campaign_template() {

    if(empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'set-campaign-template')) {
        die(json_encode(array('status' => 'error', 'message' => __('Wrong nonce in the submitted data', 'leyka'),)));
    } else if(empty($_POST['campaign_id'])) {
        die(json_encode(array('status' => 'error', 'message' => __('Error: campaign ID is missing', 'leyka'),)));
    }

    update_post_meta((int)$_POST['campaign_id'], 'campaign_template', $_POST['template']);

    die(json_encode(array('status' => 'ok', 'post' => $_POST,)));

}
add_action('wp_ajax_leyka_set_campaign_template', 'leyka_set_campaign_template');

function leyka_edit_campaign_slug() {

    if(empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'leyka-edit-campaign-slug')) {
        die(json_encode(array('status' => 'error', 'message' => __('Wrong nonce in the submitted data', 'leyka'),)));
    } else if(empty($_POST['campaign_id']) || empty($_POST['slug'])) {
        die(json_encode(array('status' => 'error', 'message' => __('Error: the campaign data needed are missing', 'leyka'),)));
    }

    $campaign = get_post($_POST['campaign_id']);
    if( !$campaign || $campaign->post_type !== Leyka_Campaign_Management::$post_type ) {
        die(json_encode(array('status' => 'error', 'message' => __('Error: wrong campaign ID given', 'leyka'),)));
    }

    $_POST['slug'] = wp_unique_post_slug(sanitize_title($_POST['slug']), $_POST['campaign_id'], $campaign->post_status, $campaign->post_type, null);

    $res = wp_update_post(array(
        'ID' => (int)$_POST['campaign_id'],
        'post_name' => $_POST['slug'],
    ));

    if($res) {
        die(json_encode(array('status' => 'ok', 'slug' => $_POST['slug'],)));
    } else {
        die(json_encode(array('status' => 'error', 'message' => __("Error: the campaign slug wasn't updated", 'leyka'),)));
    }

}
add_action('wp_ajax_leyka_edit_campaign_slug', 'leyka_edit_campaign_slug');
