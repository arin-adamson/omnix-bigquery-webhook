<?php

require 'vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Google\Cloud\BigQuery\BigQueryClient;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Register the Cloud Function for handling Zoho Webhooks
 */
FunctionsFramework::http('omnixBigQueryWebhook', function (ServerRequestInterface $request) { // Function name must match the function name in the Cloud Function
    // Load allowed token from environment variable
    $allowedToken = getenv('ALLOWED_TOKEN'); // Set this in your Cloud Function environment variables as "E02Gp2hKxDcZi1mJcR7kZOBJioCYqGgF"

    // Ensure the environment variable is set
    if (!$allowedToken) {
        http_response_code(500);
        error_log("Missing ALLOWED_TOKEN environment variable");
        return json_encode(["error" => "Missing ALLOWED_TOKEN environment variable"]);
    }

    // Get headers
    $headers = $request->getHeaders();

    // Extract Authorization header
    $authHeader = isset($headers['Authorization'][0]) ? $headers['Authorization'][0] : null;
    $receivedToken = null;
    
    if ($authHeader && preg_match('/Token\s+(.+)/', $authHeader, $matches)) {
        $receivedToken = $matches[1];
    }

    // Check if the token matches the allowed token
    if ($receivedToken !== $allowedToken) {
        http_response_code(403);
        error_log("Unauthorized token: " . ($receivedToken ?: 'none provided'));
        return json_encode(["error" => "Request with unauthorized or missing token"]);
    }

    // Get raw POST data
    $input = (string) $request->getBody();

    // Decode the JSON request body
    $data = json_decode($input, true);
    if (!$data) {
        http_response_code(400);
        error_log("Invalid JSON: " . $input);
        return json_encode(["error" => "Invalid JSON"]);
    }

    // Prepare data for BigQuery
    $bigquery_data = [
        'ssid' => getIfIsset($data, 'ssid'),
        'oxLocationId' => getIfIsset($data, 'oxLocationId'),
        'date' => getIfIsset($data, 'date'),
        'licensePlate' => getIfIsset($data, 'licensePlate'),
        'bay' => getIfIsset($data, 'bay'),
        'eventType' => getIfIsset($data, 'eventType'),
        'receivedDate' =>  time() // Generate current timestamp
    ];

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
    $projectId = 'clientdata-lofxpress'; // Your Google Cloud Project ID
    $datasetId = 'lof_xpress'; // Your BigQuery Dataset ID
    $tableId = 'webhook_data'; // Your BigQuery Table ID

    try {
        $bigQuery = new BigQueryClient(['projectId' => $projectId]);
        $dataset = $bigQuery->dataset($datasetId);
        $table = $dataset->table($tableId);

        $insertResponse = $table->insertRows([['data' => $data]]);

        if ($insertResponse->isSuccessful()) {
            http_response_code(200);
            return json_encode(["success" => "Data inserted into BigQuery"]);
        }else {
            // Get detailed error info from BigQuery response
            $errors = $insertResponse->failedRows();
            $errorDetails = [];
            foreach ($errors as $rowError) {
                $rowIndex = $rowError['rowIndex'] ?? 'unknown';
                $rowErrors = $rowError['errors'] ?? [];
                foreach ($rowErrors as $err) {
                    $errorDetails[] = [
                        'row' => $rowIndex,
                        'reason' => $err['reason'] ?? 'unknown',
                        'message' => $err['message'] ?? 'No message provided'
                    ];
                }
            }
            
            $errorMessage = "Failed to insert data into BigQuery: " . json_encode($errorDetails);
            http_response_code(500);
            error_log($errorMessage . " | Data: " . json_encode($data));
            return json_encode(["error" => $errorMessage]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Exception: " . $e->getMessage());
        return json_encode(["error" => "Exception: " . $e->getMessage()]);
    }
}