<?php /** The default procedure of doing all active recurring donations of the currenct date. */

require_once 'procedures-common.php';

if( !defined('WPINC') ) die;

// The method should be called no more than once per day:
if(get_transient('leyka_last_active_recurring_date') === date('d.m.Y') && !leyka_options()->opt('plugin_debug_mode')) {
    return;
} else {
    set_transient('leyka_last_active_recurring_date', date('d.m.Y'), 60*60*24);
}

ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', 268435456); // 256 Mb, just in case

function getLeykaPostSearchParams($date)
{
    $timestamp = strtotime($date);
    $current_day = (int)date('j', $timestamp);
    $max_days_in_month = (int)date('t', $timestamp);
    $current_day_param = ['relation' => 'AND',];
    if (!LEYKA_DEBUG) { // In production mode, rebill only subscriptions older than 1 full day
        $current_day_param[] = ['before' => '-1 day'];
    }
    $current_day_param[] = $max_days_in_month < 31 && $max_days_in_month === $current_day

        // Last day of short month
        ? [['day' => $current_day, 'compare' => '>='], ['day' => 31, 'compare' => '<=']]

        // All the other days
        : [['day' => (int)date('j', $timestamp)]];

    $params = [
        'post_type'   => Leyka_Donation_Management::$post_type,
        'nopaging'    => true,
        'post_status' => 'funded',
        'post_parent' => 0,
        'meta_query'  => [
            'relation' => 'AND',
            [
                'key'     => 'leyka_payment_type',
                'value'   => 'rebill',
                'compare' => '=',
            ],
            [
                'key'     => '_rebilling_is_active',
                'value'   => '1',
                'compare' => '=',
            ],
        ],
        'date_query'  => $current_day_param,
    ];

    return $params;
}

// Get all active initial donations for the recurring subscriptions:
$todayDonations = get_posts(getLeykaPostSearchParams('today'));
foreach ($todayDonations as $donation) {

    $donation = new Leyka_Donation($donation);

    $gateway = leyka_get_gateway_by_id($donation->gateway_id);
    if($gateway) {

        $new_recurring_donation = $gateway->do_recurring_donation($donation);
        if($new_recurring_donation && is_a($new_recurring_donation, 'Leyka_Donation')) {
            Leyka_Donation_Management::send_all_recurring_emails($new_recurring_donation);
        } // else if( !$new_recurring_donation || is_wp_error($new_recurring_donation) ) { ... } /** @todo Log & handle error */

    }

}

// Get all active initial donations for the recurring subscriptions:
$tomorrowDonations = get_posts(getLeykaPostSearchParams('tomorrow'));
foreach ($tomorrowDonations as $donation) {

    $donation = new Leyka_Donation($donation);

    $gateway = leyka_get_gateway_by_id($donation->gateway_id);
    if ($gateway) {
        Leyka_Donation_Management::send_donor_thanking_email($donation);
    }

}
