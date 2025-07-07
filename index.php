<?php

//Steadfast


// === Steadfast CONFIG ===
$email = 'YOUR_steadfast_email@gmail.com'; // Replace with env or config if needed
$password = 'YOUR_steadfast_password';
$phoneNumber = (isset($_GET['mobile'])) ? ($_GET['mobile']) : (0); //pass the customer phone number on get parameter

// $_GET['mobile']; //'01705927257'; // You can pass this from $_GET or $_POST

// === Validate phone number ===
if (!preg_match('/^01[3-9][0-9]{8}$/', $phoneNumber)) {
    die("Invalid phone number.");
}

// === Setup cookie jar file ===
$cookieFile = tempnam(sys_get_temp_dir(), 'steadfast_cookie');

// === Step 1: GET login page ===
$ch = curl_init('https://steadfast.com.bd/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);
$loginPage = curl_exec($ch);
curl_close($ch);

// === Step 2: Extract CSRF token ===
if (!preg_match('/name="_token" value="(.*?)"/', $loginPage, $match)) {
    die("CSRF token not found.");
}
$csrfToken = $match[1];

// === Step 3: Login POST ===
$postFields = http_build_query([
    '_token' => $csrfToken,
    'email' => $email,
    'password' => $password
]);

$ch = curl_init('https://steadfast.com.bd/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);
$loginResult = curl_exec($ch);
curl_close($ch);

// === Step 4: Access fraud check page ===
$fraudUrl = "https://steadfast.com.bd/user/frauds/check/" . urlencode($phoneNumber);
$ch = curl_init($fraudUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    unlink($cookieFile);
    die("Failed to retrieve fraud data.");
}

$data = json_decode($response, true);
if (!isset($data['total_delivered']) || !isset($data['total_cancelled'])) {
    unlink($cookieFile);
    die("Unexpected response structure.");
}

$steadfastResult = [
    'name'=>'Steadfast',
    'logo'=>'https://i.ibb.co.com/tM68nWR/stead-fast.png',
    'success' => $data['total_delivered'],
    'cancel'  => $data['total_cancelled'],
    'total'   => $data['total_delivered'] + $data['total_cancelled'],
];

// === Step 5: Logout (optional cleanup) ===
// $ch = curl_init("https://steadfast.com.bd/user/frauds/check");
// curl_setopt_array($ch, [
//     CURLOPT_RETURNTRANSFER => true,
//     CURLOPT_COOKIEFILE => $cookieFile,
//     CURLOPT_USERAGENT => 'Mozilla/5.0',
// ]);
// $logoutPage = curl_exec($ch);
// curl_close($ch);

// if (preg_match('/<meta name="csrf-token" content="(.*?)"/', $logoutPage, $match)) {
//     $logoutToken = $match[1];
//     $logoutFields = http_build_query(['_token' => $logoutToken]);

//     $ch = curl_init("https://steadfast.com.bd/logout");
//     curl_setopt_array($ch, [
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_POST => true,
//         CURLOPT_POSTFIELDS => $logoutFields,
//         CURLOPT_COOKIEFILE => $cookieFile,
//         CURLOPT_USERAGENT => 'Mozilla/5.0',
//     ]);
//     curl_exec($ch);
//     curl_close($ch);
// }

// unlink($cookieFile);



//Steadfast End


//RedX Start


function check_required_env(array $requiredKeys)
{
    foreach ($requiredKeys as $key) {
        if (!getenv($key)) {
            exit("Missing required environment variable: $key\n");
        }
    }
}

function validate_phone_number($phone)
{
    if (!preg_match('/^01[0-9]{9}$/', $phone)) {
        exit("Invalid phone number format: $phone\n");
    }
}

function get_cached_token($cacheFile, $validMinutes)
{
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (isset($data['token'], $data['timestamp'])) {
            $age = (time() - $data['timestamp']) / 60;
            if ($age < $validMinutes) {
                return $data['token'];
            }
        }
    }
    return null;
}

function save_token_to_cache($cacheFile, $token)
{
    $data = [
        'token' => $token,
        'timestamp' => time(),
    ];
    file_put_contents($cacheFile, json_encode($data));
}

function get_access_token()
{
    $cacheFile = __DIR__ . '/redx_token_cache.json';
    $cacheMinutes = 50;

    $token = get_cached_token($cacheFile, $cacheMinutes);
    if ($token) {
        return $token;
    }

    $phone = '88' . getenv('REDX_PHONE');
    $password = getenv('REDX_PASSWORD');

    $ch = curl_init('https://api.redx.com.bd/v4/auth/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0',
            'Accept: application/json, text/plain, */*',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'phone' => $phone,
            'password' => $password,
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    $token = $data['data']['accessToken'] ?? null;

    if ($token) {
        save_token_to_cache($cacheFile, $token);
    }

    return $token;
}

function get_customer_delivery_stats($queryPhone)
{
    check_required_env(['REDX_PHONE', 'REDX_PASSWORD']);
    validate_phone_number($queryPhone);

    $accessToken = get_access_token();

    if (!$accessToken) {
        return ['error' => 'Login failed or unable to get access token'];
    }

    $url = "https://redx.com.bd/api/redx_se/admin/parcel/customer-success-return-rate?phoneNumber=88$queryPhone";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0',
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken"
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $delivered = (int)($data['data']['deliveredParcels'] ?? 0);
        $total = (int)($data['data']['totalParcels'] ?? 0);
        $cancel = $total - $delivered;

        return [
            'success' => $delivered,
            'cancel' => $cancel,
            'total' => $total,
        ];
    } elseif ($httpCode === 401) {
        unlink(__DIR__ . '/redx_token_cache.json');
        return ['error' => 'Access token expired or invalid. Please retry.', 'status' => 401];
    }

    return [
        'success' => 'Threshold hit, wait a minute',
        'cancel' => 'Threshold hit, wait a minute',
        'total' => 'Threshold hit, wait a minute',
    ];
}


// === REDX CONFIG ===
putenv('REDX_PHONE=YOUR_Redx_phone_number');
putenv('REDX_PASSWORD=YOUR_Redx_Password');

// Call the function
$redxResult = get_customer_delivery_stats($phoneNumber);
// print_r($redxResult);

$redxResult = [
    'name'=>'RedX',
    'logo'=>'https://i.ibb.co.com/NWL7Tr4/redx.png',
    'success' => $redxResult['success'],
    'cancel'  => $redxResult['cancel'],
    'total'   => $redxResult['success'] + $redxResult['cancel'],
];
//RedX End




// === Output the result ===
header('Content-Type: application/json');



// Merge all courier arrays
$couriers = [
    $steadfastResult,
    $redxResult
];


// Compose main data array
$result = [
    "phoneNumber" => $phoneNumber,
    "totalOrders" => $steadfastResult['total']+$redxResult['total'],
    "totalDeliveries" => $steadfastResult['success']+$redxResult['success'],
    "totalCancellations" => $steadfastResult['cancel']+$redxResult['cancel'],
    "successRatio" => ($steadfastResult['success']+$redxResult['success'])/($steadfastResult['total']+$redxResult['total'])*100,
    "message" => "ভালো কাস্টমার! ক্যাশ অন ডেলিভারিতে পার্সেল পাঠানো যাবে।", //pass custom message upon success rates
    "couriers" => $couriers,
    "reports" => [],
    "errors" => []
];

echo json_encode($result);
