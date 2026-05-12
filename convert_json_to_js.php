<?php
// Script to convert the JSON data to JavaScript format for register.php

// Read the JSON file
$jsonData = file_get_contents('state-city-api-data-2026-05-12.json');
$data = json_decode($jsonData, true);

// Extract states data
$states = $data['states'];
$cities = $data['cities'];

// Generate JavaScript states array
echo "// Complete states data from API download\n";
echo "const statesData = [\n";
foreach ($states as $state) {
    echo "    {\n";
    echo "        id: \"{$state['id']}\",\n";
    echo "        name: \"{$state['name']}\",\n";
    echo "        iso2: \"{$state['iso2']}\",\n";
    echo "        latitude: \"{$state['latitude']}\",\n";
    echo "        longitude: \"{$state['longitude']}\"\n";
    echo "    },\n";
}
echo "];\n\n";

// Generate JavaScript cities object
echo "// Complete cities data from API download\n";
echo "const citiesData = {\n";
foreach ($cities as $stateCode => $stateCities) {
    echo "    '{$stateCode}': [\n";
    foreach ($stateCities as $city) {
        $cityName = addslashes($city['name']); // Escape quotes
        echo "        { id: \"{$city['id']}\", name: \"{$cityName}\" },\n";
    }
    echo "    ],\n";
}
echo "};\n\n";

echo "// Statistics\n";
echo "console.log('Loaded " . count($states) . " states and " . $data['metadata']['totalCities'] . " cities from local database');\n";
?>