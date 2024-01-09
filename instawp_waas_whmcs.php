<?php

use \WHMCS\Database\Capsule;

if ( ! defined( "WHMCS" ) ) {
    exit( "This file cannot be accessed directly" );
}

function instawp_waas_whmcs_config() {
    $configarray = [
        "name"        => "InstaWP WaaS", 
        "description" => "This module provides a quick and easy way to integrate InstaWP WaaS into your WHMCS installation", 
        "version"     => "1.0", 
        "author"      => "InstaWP", 
        "fields"      => [
            "api_key" => [
                "FriendlyName" => "InstaWP API Key", 
                "Type"         => "text", 
                "Size"         => "35",
            ],
            "app_email" => [
                "FriendlyName" => "Send email through App", 
                "Type"         => "yesno", 
                "Description"  => "Enable / Disable", 
            ]
        ]
    ];
    return $configarray;
}

function instawp_waas_whmcs_output( $vars ) {
    echo "<br /><br />\n<p align=\"center\"><input type=\"button\" value=\"Launch InstaWP Dashboard\" onclick=\"window.open('https://app.instawp.io/dashboard','_blank');\" class=\"btn btn-primary btn-lg\" /></p>\n<br /><br />\n<p>Configuration of the InstaWP WaaS Addon is done via <a href=\"configaddonmods.php\"><b>Setup > Addon Modules</b></a>.</p>";
}

function instawp_waas_whmcs_activate() {
    $query = "ALTER TABLE `tblproducts` ADD COLUMN `instawpwaas` VARCHAR(30) NOT NULL;";
    full_query( $query );

    instawp_waas_whmcs_add_email_template( 'InstaWP WaaS', 'InstaWP WaaS Created', '<p>Dear {$client_name},</p>
    <p><strong>InstaWP WaaS Details:</strong></p>
    <p>{$waas_data}</p>
    <p>Thank you for choosing us.</p>
    <p>{$signature}</p>' );
}

function instawp_waas_whmcs_deactivate() {
    $query = "ALTER TABLE `tblproducts` DROP COLUMN `instawpwaas`";
    full_query( $query );

    instawp_waas_whmcs_delete_email_template( 'InstaWP WaaS' );
}

function instawp_waas_whmcs_add_email_template( $template, $subject, $message ) {
    $existingTemplate = Capsule::table( 'tblemailtemplates' )->where( 'name', $template )->first();

    if ( ! $existingTemplate ) {
        Capsule::table( 'tblemailtemplates' )->insert( [
            'name'    => $template,
            'type'    => 'general',
            'subject' => $subject,
            'message' => $message,
        ] );
    }
}

function instawp_waas_whmcs_delete_email_template( $templateName ) {
    Capsule::table( 'tblemailtemplates' )->where( 'name', $templateName )->delete();
}