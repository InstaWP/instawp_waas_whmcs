<?php

if ( ! defined( "WHMCS" ) ) {
    exit( "This file cannot be accessed directly" );
}

add_hook( 'AdminProductConfigFields', 1, function( $vars ) {
    $modulevars = instawp_get_module_data();
    if ( ! $modulevars["api_key"] ) {
        return false;
    }

    $items   = instawp_get_waas_list( $modulevars["api_key"] );
    $product = \WHMCS\Product\Product::find( $vars['pid'] );
    $options = '<option value="">None</option>';

    foreach ( $items as $item ) {
        $selected = ( $item['id'] === intval( $product->instawpwaas ) ) ? ' selected="selected"' : '';
        $options .= '<option value="' . $item['id'] . '"' . $selected . '>' . $item['name'] . '</option>';
    }

    return [
        'InstaWP WaaS' => "<select name=\"instawpwaas\" class=\"form-control select-inline\">$options</select>"
    ];
} );

add_hook( 'AdminProductConfigFieldsSave', 1, function( $vars ) {
    $waas_id = intval( $_REQUEST["instawpwaas"] );
    update_query( "tblproducts", [ "instawpwaas" => $waas_id ], [ "id" => $vars['pid'] ] );
} );


add_hook( 'AcceptOrder', 1, function( $vars ) {
    $modulevars = instawp_get_module_data();
    if ( ! $modulevars["api_key"] ) {
        return false;
    }

    $items     = instawp_get_waas_list( $modulevars["api_key"] );
    $waas_list = [];

    foreach ( $items as $item ) {
        $waas_list[ $item['id'] ] = $item['webhookUrl'];
    }

    $order      = \WHMCS\Order\Order::find( $vars['orderid'] );
    $client     = $order->client;
    $product_id = $order->packageid;
    $link_data  = [];
    $content    = 'None';
    $send_email = ( ! empty( $modulevars["app_email"] ) && 'on' === $modulevars["app_email"] );

    $args = [
        'name'  => $client->firstName . ' ' . $client->lastName,
        'email' => $client->email,
    ];

    if ( $send_email ) {
        $args['send_email'] = true;
    }

    foreach ( $order->services()->get( [ "packageid" ] ) as $service ) {
        $line_item = \WHMCS\Product\Product::find( $service->packageid );
        $waas_id   = $line_item->instawpwaas;

        if ( ! $waas_id || ! isset( $waas_list[ $waas_id ] ) ) {
            continue;
        }

        $response      = curlCall( $waas_list[ $waas_id ], $args, $options );
        $response_data = json_decode( $response );

        logModuleCall( "instawp_waas_whmcs", "Generate WaaS Link", [], $response, $response_data );

        if ( $response_data->status && ! empty( $response_data->data->unique_link ) ) {
            $link_data[] = sprintf( 'InstaWP WaaS Unique Link for %s: %s', $line_item->name, $response_data->data->unique_link );
        }
    }

    if ( ! empty( $link_data ) ) {
        $content = '<p>' . join( '</p><p>', $link_data ) . '</p>';
    }

    if ( ! $send_email ) {
        $result = sendMessage( "InstaWP WaaS", $order->userId, array_merge( $vars, [ 'waas_data' => $content ] ) );
        logModuleCall( "instawp_waas_whmcs", "Email WaaS Link", $result, '' );
    }
} );

function instawp_get_module_data() {
    $modulevars = [];
    $result     = select_query( "tbladdonmodules", "", [ "module" => "instawp_waas_whmcs" ] );

    while ( $data = mysql_fetch_array( $result ) ) {
        $value = $data["value"];
        $value = explode( "|", $value );
        $value = trim( $value[0] );
        $modulevars[ $data["setting"] ] = $value;
    }

    return $modulevars;
}

function instawp_get_waas_list( $api_key ) {
    $options = [
        "CURLOPT_HTTPHEADER"     => [
            "Authorization: Bearer " . $api_key
        ], 
        "CURLOPT_TIMEOUT"        => 300, 
        "CURLOPT_RETURNTRANSFER" => 1
    ];

    $response      = curlCall( 'https://app.instawp.io/api/v2/waas', false, $options );
    $response_data = json_decode( $response, true );

    logModuleCall( "instawp_waas_whmcs", "Fetch WaaS List", [], $response, $response_data );

    return $response_data['data'];
}

?>
