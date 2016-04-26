#!/usr/local/php5/bin/php
<?php

if (!file_exists(__DIR__ . '/config.php')) {
    die('Copy config.dist.php to config.php and include your data to make the script work' . PHP_EOL);
}

require __DIR__ . '/config.php';

// Check the Verb we will use. Will be GET if no verb specified
if ($argc <= 1) {
    die('You need to pass the endpoint as an argument' . PHP_EOL);
} elseif ($argc == 2) {
    $verb = 'GET';
    $url = $argv[1];
} elseif ($argc == 3) {
    $verb = strtoupper($argv[1]);
    $url = $argv[2];
} else {
    die('Unexpected number of arguments' . PHP_EOL);
}


// Check if it is a proper VERB and if the body exist

if ($verb != 'GET') {
    if ($verb != 'POST' && $verb != 'PUT' && $verb != 'PATCH' && $verb != 'DELETE') {
        die('The verb is not valid' . PHP_EOL);
    } elseif (!file_exists(__DIR__ . '/requestbody.json')) {
        die('The request body should be in a a file called requestbody.json in the same directory' . PHP_EOL);
    }
}



$parsedUrl = parse_url(urlencode($url));

if (!$parsedUrl) {
    die('PHP indica que la URL esta mal formada' . PHP_EOL);
} elseif (array_key_exists('host', $parsedUrl)) {
    $request = $url;
    $host = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ':' . $parsedUrl['port'];
} else {
    $request = $defaultHost . $url;
    $host = $defaultHost;
}

if (!filter_var("$request", FILTER_VALIDATE_URL)) {
    die('That is not a valid endpoint. Dont forget to start with \'http://\' o \'https://\'.' . PHP_EOL);
}

if (!file_exists(__DIR__ . '/token.txt')) {
    echo "Creating the token file..." . PHP_EOL;
    $handler = fopen(__DIR__ . '/token.txt', "w");
    fwrite($handler, ' ');
    fclose($handler);

    // Go directly to obtain a token
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

        if ($verb != 'GET' && $verb != 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
            $requestBody = file_get_contents(__DIR__ . '/requestbody.json');
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($requestBody)
                ]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
        } else {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        }

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
            echo "Connection refused. Check if the vagrant API machine is up.\n";
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

    curl_close($curl);

    $token = $result->access_token;

    if ($status === 200) {
        // file_put_contents(, data)
        echo 'New token obtained: ' . $token . PHP_EOL;
    } else {
        die('Could not get a new token.' . PHP_EOL . 'Status: ' . $status . PHP_EOL);
    }

    return $token;
}
