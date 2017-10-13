<?php

function fetch($url) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url
    ));

    // Send the request & save response to $resp
    $resp = curl_exec($curl);

    /* Check for 404 (file not found). */
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($httpCode == 404) {
        return "Error: 404";
    }

    // Close request to clear up some resources
    curl_close($curl);

    return $resp;

}