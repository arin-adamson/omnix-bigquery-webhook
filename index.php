<?php

require 'vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Google\Cloud\BigQuery\BigQueryClient;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Register the Cloud Function for handling Zoho Webhooks
 */
FunctionsFramework::http('myFunction', function (ServerRequestInterface $request) { // Function name must match the function name in the Cloud Function
    // Load allowed IP from environment variable
    $allowedIp = getenv('ALLOWED_IP'); // Set this in your Cloud Function environment variables

    // Ensure the environment variable is set
    if (!$allowedIp) {
        http_response_code(500);
        error_log("Missing ALLOWED_IP environment variable");
        return json_encode(["error" => "Missing ALLOWED_IP environment variable"]);
    }

    // Get raw POST data and headers
    $input = (string) $request->getBody();
    $headers = $request->getHeaders();

    // Extract client IP from X-Forwarded-For header
    $clientIp = isset($headers['X-Forwarded-For'][0]) ? explode(',', $headers['X-Forwarded-For'][0])[0] : null;
    if (!$clientIp) {
        // Fallback to remote address if header is missing (less reliable in serverless)
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    }

    // Check if the client IP matches the allowed IP
    if ($clientIp !== $allowedIp) {
        http_response_code(403);
        error_log("Unauthorized IP: " . $clientIp);
        return json_encode(["error" => "Request from unauthorized IP: " . $clientIp]);
    }

    // Decode the JSON request body
    $data = json_decode($input, true);
    if (!$data) {
        http_response_code(400);
        error_log("Invalid JSON: " . $input);
        return json_encode(["error" => "Invalid JSON"]);
    }

    // Prepare data for BigQuery
    $bigquery_data = []; // Insert your field mapping here

    // Insert into BigQuery
    return insertIntoBigQuery($bigquery_data);
});

/**
 * Prevent undefined index error by checking if key is set
 */
function getIfIsset($array, $keys)
{
    //Check if is array and then check to see if any listed keys are not set. If they are not set, return null.
    if (is_array($keys)) {
        foreach ($keys as $key) {
            if (!isset($array[$key]) || empty($array[$key])) {
                return null; // If key is not set, return null
            }
    
            // Check if array is sequential and if so set to the first item in the sequential array
            if (is_array($array[$key]) && array_is_list($array[$key])) {
                // Ensure you're not overwriting $array if you don't need to
                $array = $array[$key][0]; // Set to the first element if it's sequential
            } else {
                // Otherwise, continue to access the next level in the array
                $array = $array[$key];
            }
        }
        return $array; // Return the final value after the loop
    }else{
        return isset($array[$keys]) ? $array[$keys] : null;
    }
}

/**
 * Insert data into BigQuery
 */
function insertIntoBigQuery($data)
{
    $projectId = ''; // Your Google Cloud Project ID
    $datasetId = ''; // Your BigQuery Dataset ID
    $tableId = ''; // Your BigQuery Table ID

    try {
        $bigQuery = new BigQueryClient(['projectId' => $projectId]);
        $dataset = $bigQuery->dataset($datasetId);
        $table = $dataset->table($tableId);

        $insertResponse = $table->insertRows([['data' => $data]]);

        if ($insertResponse->isSuccessful()) {
            http_response_code(200);
            return json_encode(["success" => "Data inserted into BigQuery"]);
        } else {
            http_response_code(500);
            error_log("Failed to insert data: " . $data);
            return json_encode(["error" => "Failed to insert data "]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Exception: " . $e->getMessage());
        return json_encode(["error" => "Exception: " . $e->getMessage()]);
    }
}