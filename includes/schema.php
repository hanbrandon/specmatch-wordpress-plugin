<?php

if (!defined('ABSPATH')) {
    exit;
}

function pc_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . 'pc_' . $name;
}

function pc_install_schema(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $devices = pc_table('devices');
    $specs = pc_table('specs');
    $offers = pc_table('offers');
    $ai = pc_table('ai_content');
    $events = pc_table('events');

    dbDelta("CREATE TABLE {$devices} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint unsigned NOT NULL,
        source_id bigint unsigned NOT NULL,
        brand varchar(120) NOT NULL,
        model varchar(240) NOT NULL,
        source_url text NOT NULL,
        image_url text NULL,
        announced varchar(160) NULL,
        announced_date date NULL,
        release_year smallint unsigned NULL,
        status varchar(240) NULL,
        os varchar(240) NULL,
        chipset varchar(240) NULL,
        display varchar(240) NULL,
        camera varchar(240) NULL,
        battery varchar(240) NULL,
        ram varchar(120) NULL,
        storage varchar(160) NULL,
        content_hash char(64) NOT NULL,
        source_updated_at datetime NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY source_id (source_id),
        UNIQUE KEY post_id (post_id),
        KEY brand (brand),
        KEY announced (announced),
        KEY announced_date (announced_date),
        KEY release_year (release_year)
    ) {$charset};");

    dbDelta("CREATE TABLE {$specs} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        device_id bigint unsigned NOT NULL,
        section_order smallint unsigned NOT NULL DEFAULT 0,
        row_order smallint unsigned NOT NULL DEFAULT 0,
        section_name varchar(120) NOT NULL,
        field_name varchar(160) NULL,
        field_value longtext NULL,
        data_spec varchar(120) NULL,
        PRIMARY KEY  (id),
        KEY device_id (device_id),
        KEY spec_lookup (section_name, field_name)
    ) {$charset};");

    dbDelta("CREATE TABLE {$offers} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        device_id bigint unsigned NOT NULL,
        merchant varchar(120) NOT NULL,
        title varchar(255) NOT NULL,
        price decimal(14,2) NULL,
        currency char(3) NOT NULL DEFAULT 'KRW',
        availability varchar(40) NULL,
        affiliate_url text NOT NULL,
        image_url text NULL,
        checked_at datetime NOT NULL,
        active tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        KEY device_active (device_id, active),
        KEY price (price)
    ) {$charset};");

    dbDelta("CREATE TABLE {$ai} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        device_id bigint unsigned NOT NULL,
        content_type varchar(60) NOT NULL,
        content longtext NOT NULL,
        model varchar(120) NULL,
        prompt_version varchar(40) NULL,
        status varchar(20) NOT NULL DEFAULT 'draft',
        facts_hash char(64) NULL,
        created_at datetime NOT NULL,
        reviewed_at datetime NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY device_content (device_id, content_type),
        KEY status (status)
    ) {$charset};");

    dbDelta("CREATE TABLE {$events} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint unsigned NOT NULL DEFAULT 0,
        post_type varchar(20) NOT NULL,
        event_type varchar(20) NOT NULL,
        session_hash char(64) NOT NULL,
        event_day date NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY daily_event (post_id, event_type, session_hash, event_day),
        KEY popularity (post_type, event_type, event_day, post_id)
    ) {$charset};");

    update_option('pc_schema_version', PC_VERSION);
}
