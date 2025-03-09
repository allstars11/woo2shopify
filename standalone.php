<?php

// File paths
$inputFile = 'wooproducts.csv';
$outputFile = 'shopifyproducts.csv';

// Auto-detect delimiter ("," or ";")
$firstLine = fgets(fopen($inputFile, "r"));
$delimiter = (strpos($firstLine, ";") !== false) ? ";" : ",";

// Open input file
$input = fopen($inputFile, "r");
if (!$input) {
    die("Error: Unable to open '$inputFile'.\n");
}

// Read headers
$header = fgetcsv($input, 0, $delimiter, '"', "\\");
if (!$header) {
    die("Error: Could not read headers from '$inputFile'.\n");
}

// Print detected headers (for debugging)
echo "Detected Headers:\n";
print_r($header);

// Field mapping for Italian WooCommerce headers
$fieldMapping = [
    "ID" => "Handle",
    "Nome" => "Title",
    "Descrizione" => "Body (HTML)",
    "SKU" => "SKU",
    "Prezzo di listino" => "Price",
    "Prezzo in offerta" => "Compare At Price",
    "Magazzino" => "Variant Inventory Qty",
    "In stock?" => "Variant Inventory Tracker",
    "Peso (g)" => "Variant Grams",
    "GTIN, UPC, EAN, o ISBN" => "Barcode", // ✅ New barcode mapping
];

// Ensure mapping only includes existing headers
$filteredMapping = array_filter($fieldMapping, function ($wooKey) use ($header) {
    return in_array($wooKey, $header);
}, ARRAY_FILTER_USE_KEY);

// Create output file
$output = fopen($outputFile, "w");
if (!$output) {
    fclose($input);
    die("Error: Unable to create '$outputFile'.\n");
}

// Write Shopify headers
fputcsv($output, array_values($filteredMapping), ",", '"', "\\");

// Process rows
while (($row = fgetcsv($input, 0, $delimiter, '"', "\\")) !== false) {
    $product = array_combine($header, $row);
    $shopifyProduct = [];

    foreach ($filteredMapping as $wooKey => $shopifyKey) {
        // Handle special cases
        if ($shopifyKey == "Body (HTML)") {
            $shopifyProduct[$shopifyKey] = str_replace(["\n", "\\n"], " ", trim($product[$wooKey] ?? ""));
        } elseif ($shopifyKey == "Variant Inventory Tracker") {
            $shopifyProduct[$shopifyKey] = ($product[$wooKey] == "1") ? "true" : "false";
        } elseif ($shopifyKey == "Variant Grams") {
            $shopifyProduct[$shopifyKey] = floatval($product[$wooKey] ?? 0);
        } else {
            $shopifyProduct[$shopifyKey] = trim($product[$wooKey] ?? "");
        }
    }

    fputcsv($output, $shopifyProduct, ",", '"', "\\");
}

// Close files
fclose($input);
fclose($output);

echo "✅ Conversion completed! Output file: $outputFile\n";

?>
