
<?php
/**
 * Plugin Name: WooCommerce to Shopify Exporter
 * Description: Export WooCommerce products to a Shopify-compatible CSV.
 * Version: 1.0
 * Author: Cesare Capobianco
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add Admin Menu
add_action('admin_menu', 'woo_to_shopify_menu');

function woo_to_shopify_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Export to Shopify',
        'Export to Shopify',
        'manage_options',
        'woo-shopify-export',
        'woo_to_shopify_export_page'
    );
}

// Render Export Page
function woo_to_shopify_export_page() {
    ?>
    <div class="wrap">
        <h2>Export WooCommerce Products to Shopify</h2>
        <p>Click the button below to export your products as a Shopify-compatible CSV file.</p>
        <form method="post">
            <input type="submit" name="woo_to_shopify_export" class="button button-primary" value="Export to Shopify CSV">
        </form>
    </div>
    <?php

    if (isset($_POST['woo_to_shopify_export'])) {
        woo_to_shopify_export();
    }
}

function woo_to_shopify_export() {
    global $wpdb;

    // WooCommerce → Shopify field mapping (Italian)
    $field_mapping = [
        "ID" => "Handle",
        "post_title" => "Title",
        "post_content" => "Body (HTML)",
        "_sku" => "SKU",
        "_regular_price" => "Price",
        "_sale_price" => "Compare At Price",
        "_stock" => "Variant Inventory Qty",
        "_stock_status" => "Variant Inventory Tracker",
        "_weight" => "Variant Grams",
    ];

    // Fetch WooCommerce products
    $query = "SELECT p.ID, p.post_title, p.post_content, pm.meta_key, pm.meta_value 
              FROM {$wpdb->posts} p 
              LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
              WHERE p.post_type = 'product' AND p.post_status = 'publish'";
    
    $results = $wpdb->get_results($query);

    // Process data
    $products = [];
    foreach ($results as $row) {
        $id = $row->ID;
        if (!isset($products[$id])) {
            $products[$id] = [
                "Handle" => sanitize_title($row->post_title),
                "Title" => $row->post_title,
                "Body (HTML)" => $row->post_content,
                "SKU" => "",
                "Price" => "",
                "Compare At Price" => "",
                "Variant Inventory Qty" => "",
                "Variant Inventory Tracker" => "",
                "Variant Grams" => "",
            ];
        }
        
        // Map meta fields
        if (isset($field_mapping[$row->meta_key])) {
            $products[$id][$field_mapping[$row->meta_key]] = $row->meta_value;
        }
    }

    // Convert values for Shopify
    foreach ($products as &$product) {
        // Convert stock status
        if ($product["Variant Inventory Tracker"] == "instock") {
            $product["Variant Inventory Tracker"] = "true";
        } elseif ($product["Variant Inventory Tracker"] == "outofstock") {
            $product["Variant Inventory Tracker"] = "false";
        }

        // Convert weight to grams
        if (!empty($product["Variant Grams"])) {
            $product["Variant Grams"] = floatval($product["Variant Grams"]) * 1000;
        }

        // Empty sale price fix
        if (empty($product["Compare At Price"])) {
            $product["Compare At Price"] = "";
        }
    }

    // Prepare CSV
    $filename = "shopify_export_" . date("Y-m-d") . ".csv";
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=$filename");

    $output = fopen("php://output", "w");
    fputcsv($output, array_keys(reset($products))); // Headers
    foreach ($products as $product) {
        fputcsv($output, $product);
    }
    fclose($output);
    exit;
}
