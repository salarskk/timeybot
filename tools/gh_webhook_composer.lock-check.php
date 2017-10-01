<?php
/* This file checks if the composer.lock file on the server and for the pull
 * request are the same. If they are not, the vendor directory on the server
 * has to be updated to match the new required versions.
 *
 * It is supposed to be used as a Webhook for pull_requests and
 * sets status reports.
 */

require __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = json_decode(file_get_contents('../config.json'), TRUE);

$owner = $config['github']['owner'];
$repository = $config['github']['repository'];
$github_api_token = $config['github']['api_token'];
$hook_secret = $config['github']['hook_secret'];
$github_api_repo_url = "https://api.github.com/repos/$owner/$repository";
$context = "continuous-integration/composer.lock-check";


// Create beautiful report page
$write_report = function ($status, $commit_payload)
                     use ($owner, $repository) {
    // TODO(shoeffner): Use database instead of static files.
    $commit = $commit_payload['pull_request']['head']['sha'];
    $pr_title = $commit_payload['pull_request']['title'];
    $pr_num = $commit_payload['pull_request']['number'];
    $pr_url = $commit_payload['pull_request']['html_url'];
    $avatar = $commit_payload['pull_request']['user']['avatar_url'];
    $author = $commit_payload['pull_request']['user']['login'];
    $pr_text = $commit_payload['pull_request']['body'];
    $created = $commit_payload['pull_request']['created_at'];
    $updated = $commit_payload['pull_request']['updated_at'];
    $template = file_get_contents('../ci_reports/template.thtml');

    $status_messages = ['success' => 'unchanged', 'failure' => 'changed',
                        'pending' => 'checking', 'error' => 'error'];
    $color = ['success' => '2cbe4e', 'failure' => 'cb2431',
              'pending' => 'dbab09', 'error' => 'cb2431'];
    $favicoon = ['success' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAL0lEQVR42u3OMQEAAAQAMPrIIrJqxPBsCZY1vfEoBQQEBAQEBAQEBAQEBAQEvgMHtGNHAdZ/v/8AAAAASUVORK5CYII=',
                 'failure' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAMElEQVR42u3OMQEAAAQAMLoooH8EoYjh2RIsp3rjUQoICAgICAgICAgICAgICHwHDt7sRAGYJdY4AAAAAElFTkSuQmCC',
                 'pending' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAMElEQVR42u3OMQEAAAQAME79Y4gkDDE8W4LldG08SgEBAQEBAQEBAQEBAQEBge/AAS3oUeHCNBX5AAAAAElFTkSuQmCC',
                 'error' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAMElEQVR42u3OMQEAAAQAMLoooH8EoYjh2RIsp3rjUQoICAgICAgICAgICAgICHwHDt7sRAGYJdY4AAAAAElFTkSuQmCC'];


    $template = str_replace('[[BADGE_COLOR]]', $color[$status], $template);
    $template = str_replace('[[COLOR]]', $color[$status], $template);
    $template = str_replace('[[COMMIT]]', $commit, $template);
    $template = str_replace('[[AVATAR]]', $avatar, $template);
    $template = str_replace('[[AUTHOR]]', $author, $template);
    $template = str_replace('[[PR_TEXT]]', $pr_text, $template);
    $template = str_replace('[[CREATED]]', $created, $template);
    $template = str_replace('[[UPDATED]]', $updated, $template);
    $template = str_replace('[[CONTEXT]]', $context, $template);
    $template = str_replace('[[FAVICON]]', $favicon[$status], $template);
    $template = str_replace('[[OWNER]]', $owner, $template);
    $template = str_replace('[[PR#]]', $pr_num, $template);
    $template = str_replace('[[PR_TITLE]]', $pr_title, $template);
    $template = str_replace('[[PR_URL]]', $pr_url, $template);
    $template = str_replace('[[REPOSITORY]]', $repository, $template);
    $template = str_replace('[[STATUS]]', strtoupper($status), $template);
    $template = str_replace('[[STATUS_MESSAGE]]', $status_messages[$status], $template);

    file_put_contents("../ci_reports/$commit.html", $template);
};


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
$commit = $payload['pull_request']['head']['sha'];
$statuses_url = $payload['pull_request']['statuses_url'];
$diff_url = $config['web']['base_url'] . '/ci_reports/' . $commit . '.html';


// Initialize Guzzle client
$client = new GuzzleHttp\Client();
$headers = ['User-Agent' => "$owner/$repository-ci",
            'Authorization' => "token $github_api_token"];

// Prepare description messages
$get_state_body = function ($state)
                       use ($diff_url, $context) {
    switch ($state) {
        case 'success':
            $description = 'composer.lock unchanged.';
            break;
        case 'pending':
            $description = 'Checking if composer.lock changed.';
            break;
        case 'failure':
            $description = 'composer.lock changed.';
            break;
        case 'error':
            $description = 'Error while checking composer.lock.';
            break;
    }
    return json_encode(['state' => $state,
                         'target_url' => $diff_url,
                         'description' => $description,
                         'context' => $context]);
};


// Send pending response
$write_report('pending', $payload);
try {
    $response = $client->request('POST', $statuses_url,
        ['headers' => $headers,
         'body' => $get_state_body('pending')]
    );
} catch (GuzzleHttp\Exception\RequestException $e) {
    die($e);
}


// Get composer.lock file from commit
try {
    $response = $client->request('GET',
        "$github_api_repo_url/contents/composer.lock?ref=$commit",
        ['headers' => $headers]
    );
} catch (GuzzleHttp\Exception\RequestException $e) {
    die($e);
}

// Fail on wrong status code
if ($response->getStatusCode() != 200) {
    $write_report('error', $payload);
    try {
        $response = $client->request('POST', $statuses_url,
            ['headers' => $headers,
             'body' => $get_state_body('error')]
        );
    } catch (GuzzleHttp\Exception\RequestException $e) {
        die($e);
    }
    echo "Status Code: " . $response->getStatusCode(), PHP_EOL;
    die($get_state_body('error'));
}


// Get file contents from git and server
$content = $response->getBody();
$commit_composer_content =
    base64_decode(json_decode($content, TRUE)['content']);

$server_composer_content = file_get_contents('../composer.lock');


// Compare and submit status accordingly
if ($server_composer_content == $commit_composer_content) {
    $write_report('success', $payload);
    try {
        $response = $client->request('POST', $statuses_url,
            ['headers' => $headers,
             'body' => $get_state_body('success')]
        );
    } catch (GuzzleHttp\Exception\RequestException $e) {
        die($e);
    }
    echo $get_state_body('success');
} else {
    $write_report('failure', $payload);
    try {
        $response = $client->request('POST', $statuses_url,
            ['headers' => $headers,
             'body' => $get_state_body('failure')]
        );
    } catch (GuzzleHttp\Exception\RequestException $e) {
        die($e);
    }
    echo $get_state_body('failure');
}
