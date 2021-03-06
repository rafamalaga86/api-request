#!/usr/local/php5/bin/php
<?php

if (!file_exists(__DIR__ . '/config.php')) {
    die('Copy config.dist.php to config.php and include your data to make the script work' . PHP_EOL);
} else {
    require __DIR__ . '/config.php';
}

$options = getopt('v:jn');

// Check the Verb we will use. Will be GET if no verb specified
if ($argc <= 1) {
    die('You need to pass at least the endpoint as an argument' . PHP_EOL);
}

if (isset($options['v'])) {
    $verb = strtoupper($options['v']);
} else {
    $verb = 'GET';
}

$forceToken = isset($options['n']); // Force to take a new token
$json       = isset($options['j']); // Bool are we passing the body in JSON?
$url        = end($argv);           // The URL should be the last argument

// Check if it is a proper VERB and if the body exist

$validVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
$bodyNeededVerbs = ['POST', 'PUT', 'PATCH'];

if (!in_array($verb, $validVerbs)) {
        die('The verb is not valid' . PHP_EOL);
}

if (in_array($verb, $bodyNeededVerbs) && !file_exists(__DIR__ . '/requestbody.json')) {
        die('The request body should be in a a file called requestbody.json in the same directory' . PHP_EOL);
}

$parsedUrl = parse_url(urlencode($url));

if (!$parsedUrl) {
    die('PHP says that the URL is not proper' . PHP_EOL);
} elseif (array_key_exists('host', $parsedUrl)) {
    $request = $url;
    $host = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ':' . $parsedUrl['port'];
} else {
    $request = $defaultHost . $url;
    $host = $defaultHost;
}

if (!filter_var("$request", FILTER_VALIDATE_URL)) {
    echo $request;
    die('That is not a valid endpoint. Dont forget to start with \'http://\' o \'https://\'.' . PHP_EOL);
}

if (!file_exists(__DIR__ . '/token.txt')) {
    echo "Creating the token file..." . PHP_EOL;
    $handler = fopen(__DIR__ . '/token.txt', "w");
    fwrite($handler, ' ');
    fclose($handler);

    // Go directly to obtain a token
    $needToken = true;
} elseif ($forceToken) {
    $needToken = true;
} else {
    $needToken = false;
}


$repeat = true;
$count = 0;

do {
    if (!$needToken) {
        $token = file_get_contents(__DIR__ . '/token.txt');

        // CURL EXEC
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "$request");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $header = [];
        $header[] = 'Authorization: Bearer ' . $token;

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);

        if ($verb != 'GET' && $verb != 'DELETE') {
            $requestBody = file_get_contents(__DIR__ . '/requestbody.json');
            curl_setopt($curl, CURLOPT_VERBOSE, 1);

            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);

            if ($json) {
                $header[] = 'Content-Type: application/json';
            }

            $header[] = 'Content-Length: ' . strlen($requestBody);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        $result = curl_exec($curl);
        $status = (curl_getinfo($curl)['http_code']);
        $curlErrors = curl_error($curl);

        curl_close($curl);
    }



    // CHECK RESULTS

    if ($needToken || $status === 401) {
        $token = getToken($host, $username, $password);
        file_put_contents(__DIR__ . '/token.txt', $token);
        $needToken = false;
    } elseif ($result === false) {
        echo "HTTP Status Code: $status" . PHP_EOL;
        echo "Curl Error: ";
        print_r($curlErrors);
        echo PHP_EOL;

        if ($status == 0) {
            echo "Check if the vagrant API machine is up.\n";
        }

        die();
    } else {
        echo "HTTP Status Code: $status" . PHP_EOL;
        print_r($result . PHP_EOL);
        $repeat = false;
    }

    ++$count;
} while ($repeat && $count < 4);

if ($count >= 4) {
    die('Too many tries. Something is failing in the script.' . PHP_EOL);
}

function getToken($host, $username, $password)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_USERPWD, "frontend:");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, "grant_type=password&username=$username&password=$password");
    curl_setopt($curl, CURLOPT_URL, "$host/v1/auth/oauth2");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = json_decode(curl_exec($curl));
    $status = (curl_getinfo($curl)['http_code']);
    $curlErrors = curl_error($curl);

    curl_close($curl);

    if ($result === null || $result === false) {
        echo 'HTTP Status Code: ' . $status . PHP_EOL;
        echo 'Error details: ' . $curlErrors . PHP_EOL;
        die();
    } elseif ($result !== null && !isset($result->access_token)) {
        echo 'HTTP Status Code: ' . $result->status . PHP_EOL;
        echo 'Error details: ' . $result->detail . PHP_EOL;
        die();
    }

    $token = $result->access_token;

    if ($status === 200) {
        echo 'New token obtained: ' . $token . PHP_EOL;
    } else {
        die('HTTP Status Code: ' . $status . PHP_EOL . 'Could not get a new token.' . PHP_EOL);
    }

    return $token;
}
