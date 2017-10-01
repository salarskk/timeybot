<?php
/* This file checks if a pull_request was merged. If so, it updates all files
 * on the server.
 * It is supposed to be used as a webhook for pull_requests.
 */

require __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = json_decode(file_get_contents('../config.json'), TRUE);

$owner = $config['github']['owner'];
$repository = $config['github']['repository'];
$github_api_token = $config['github']['api_token'];
$hook_secret = $config['github']['hook_secret'];
$github_api_repo_url = "https://api.github.com/repos/$owner/$repository";
$context = "continuous-integration/auto-deploy";


// Check validity of request
if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
    die('No X-Hub-Signature found.');
}
// Verify sha1 signature
if ('sha1=' . hash_hmac('sha1',
                        file_get_contents('php://input'),
                        $hook_secret)
    != $_SERVER['HTTP_X_HUB_SIGNATURE']) {
    die('Signature comparison failed.');
}
// Check request type
if (!isset($_SERVER['HTTP_X_GITHUB_EVENT'])
    || $_SERVER['HTTP_X_GITHUB_EVENT'] != 'pull_request') {
    die('No GitHub pull request event.');
}


// Extract relevant information
$payload = json_decode($_POST['payload'], TRUE);
$merge_status = (bool) $payload['pull_request']['merged'];
$merge_base = $payload['pull_request']['base']['ref'];

if ($merge_base != 'master') {
    die('Not merged to master.');
}
if ($merge_status !== TRUE) {
    die('PR not merged.');
}


// Initialize Guzzle client
$client = new GuzzleHttp\Client();
$headers = ['User-Agent' => "$owner/$repository-ci",
            'Authorization' => "token $github_api_token"];

$commit = $payload['pull_request']['head']['sha'];
try {
    $response = $client->request('GET',
        "$github_api_repo_url/git/trees/$commit?recursive=1",
        ['headers' => $headers]
    );
} catch (GuzzleHttp\Exception\RequestException $e) {
    die($e);
}
$tree = json_decode($response->getBody(), TRUE);


// Check for truncation (should never happen in the near future -- thus
// sending emails if it happens)
if ((bool) $tree['truncated']) {
    try {
        $userresponse = $client->request('GET',
            $payload['pull_request']['base']['repo']['owner']['url'],
            ['headers' => $headers]
        );
    } catch (GuzzleHttp\Exception\RequestException $e) {
        die($e);
    }
    $user = json_decode($userresponse->getBody(), TRUE);
    $to = '"' . $user['name'] . '" <' . $user['email'] . '>';
    $subject = "$owner/$repository needs an update!";
    $message = "Dear " . $user['name'] . ",\n\n"
        . "Timey auto deploy is no longer working properly!\n"
        . "Messages are truncated, check https://developer.github.com/v3/git/trees/#response-1 !\n\n"
        . "Cheers,\n  Basti from late 2017";
    $additional_headers = 'From: "Sebastian Höffner" <info@sebastian-hoeffner.de>'.PHP_EOL
                        . 'Content-Type: text/plain; charset=utf-8'.PHP_EOL;
    mail($to, $subject, $message, $additional_headers);
    die('Can not retrieve recursively. Sent mail to admin.');
}


$errors = '';
$status = '';
// Create backup directory to roll back if needed
$backup_path = '../__BACKUP/';
if (!file_exists($backup_path)) {
    $status .= "Creating __BACKUP" . PHP_EOL;
    mkdir($backup_path, 0777, TRUE);
}
foreach ($tree['tree'] as $file) {
    $path = '../' . $file['path'];
    $backup = $backup_path . $file['path'];
    switch ($file['type']) {
        case 'blob':  // normal file
            if (is_file($path)) {
                $status .= "Backup of " . $file['path'] . PHP_EOL;
                copy($path, $backup);
                $status .= "Updating " . $file['path'] . PHP_EOL;
            } else {
                $status .= "New file " . $file['path'] . PHP_EOL;
            }
            try {
                $filecontents = $client->request('GET',
                    $file['url'], ['headers' => $headers]);
            } catch (GuzzleHttp\Exception\RequestException $e) {
                $errors .= "Can not download " . $file['path'] . PHP_EOL;
            }
            $content = base64_decode(
                json_decode($filecontents->getBody(), TRUE)['content']);
            file_put_contents($path, $content);
            break;

        case 'tree':  // directory
            if (!file_exists($path)) {
                $status .= "Creating " . $file['path'] . PHP_EOL;
                mkdir($path, 0777, TRUE);
            }
            if (!file_exists($backup)) {
                $status .= "Creating __BACKUP/" . $file['path'] . PHP_EOL;
                mkdir($backup, 0777, TRUE);
            }
            break;

        case 'commit':  // ignore commit
            break;
    }
}

$config['bot']['version'] = $commit;
file_put_contents('../config.json', json_encode($config));
if ($errors == '') {
    $status .= PHP_EOL . "Status: Success." . PHP_EOL;
} else {
    $status .= PHP_EOL . "Status: Failure." . PHP_EOL;
    $status .= PHP_EOL . "Errors: " . PHP_EOL . $errors . PHP_EOL;
}
echo $status;

try {
    $client->request('POST',
        $payload['pull_request']['comments_url'],
        ['headers' => $headers,
         'body' => json_encode(['body' =>
           'Deployed new version with this pull request.' . PHP_EOL
           . 'New version is: ' . $commit
           . PHP_EOL . PHP_EOL . '**Log:** ' . PHP_EOL
           . '```changelog'
           . $status
           . '```'])]);
} catch (GuzzleHttp\Exception\RequestException $e) {
    die($e);
}
