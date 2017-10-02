<?php declare(strict_types=1);
/* This file checks if a pull_request was merged. If so, it updates all files
 * on the server.
 * It is supposed to be used as a webhook for pull_requests.
 */
namespace Timey;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/CIReporter.php';


class AutoDeployCIReporter extends CIReporter
{
    private $status = '';

    public function assureMergedToMasterOrDie(array $commit_payload) {
        if ($commit_payload['pull_request']['base']['ref'] != 'master') {
            die();
        }
        if ((bool) $commit_payload['pull_request']['merged'] !== true) {
            die();
        }
    }

    private function notifyOwnerAboutTruncationAndDie(array $commit_payload) {
        try {
            $userresponse = $this->client->request(
                'GET',
                $commit_payload['pull_request']['base']['repo']['owner']['url'],
                [
                    'headers' => $this->headers
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            die();
        }
        $user = json_decode($userresponse->getBody(), true);
        $to = '{"}' . $user['name'] . '" <' . $user['email'] . '>';
        $subject = "{$this->config['github']['owner']}/{$this->config['github']['repository']} needs an update!";
        $message = "Dear {$user['name']},\n\n"
                 . "Timey auto deploy is no longer working properly!\n"
                 . "Messages are truncated, check https://developer.github.com/v3/git/trees/#response-1 !\n\n"
                 . "Cheers,\n  Basti from late 2017";
        $additional_headers = 'From: "Sebastian Höffner" <timey-dev@sebastian-hoeffner.de>' . PHP_EOL
                            . 'Content-Type: text/plain; charset=utf-8' . PHP_EOL;
        mail($to, $subject, $message, $additional_headers);
        die();
    }

    private function getTree(array $commit_payload) : array {
        $repo_url = "https://api.github.com/repos/"
                  . "{$this->config['github']['owner']}/{$this->config['github']['repository']}";
        $commit = $commit_payload['pull_request']['head']['sha'];
        try {
            $response = $this->client->request(
                'GET',
                "{$repo_url}/git/trees/{$commit}?recursive=1",
                [
                    'headers' => $this->headers
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            return [];
        }
        $tree = json_decode($response->getBody(), true);
        if ((bool) $tree['truncated']) {
            $this->notifyOwnerAboutTruncationAndDie();
        }
        return $tree['tree'];
    }

    public function deploy(array $commit_payload) : bool{
        $tree = $this->getTree($commit_payload);
        // TODO(shoeffner): This is very crude, should be a bit more elaborate.
        // Examples:
        // - Back up all files
        // - Restore on error ?

        $errors = '';
        $status = '';

        // Create backup directory to roll back if needed
        $backup_path = __DIR__ . '../__BACKUP/';
        if (!file_exists($backup_path)) {
            $status .= "Creating __BACKUP" . PHP_EOL;
            mkdir($backup_path, 0777, TRUE);
        }

        foreach ($tree as $file) {
            $path = __DIR__ . '/../' . $file['path'];
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

        if ($errors == '') {
            $status .= PHP_EOL . "Status: Success." . PHP_EOL;
        } else {
            $status .= PHP_EOL . "Status: Failure." . PHP_EOL;
            $status .= PHP_EOL . "Errors: " . PHP_EOL . $errors . PHP_EOL;
        }
        echo $status;
        $this->status = $status;
        return $error == '';
    }

    public function updateVersion(array $commit_payload) {
        $this->config['bot']['version'] = $commit_payload['pull_request']['head']['sha'];
        file_put_contents(__DIR__ . '/../config.json', json_encode($this->config));
    }

    protected function getStateBody(string $state, array $commit_payload) : string {
        return json_encode(
            [
                'body' => 'Deployed new version with this pull request.' . PHP_EOL
                        . 'New version is: ' . $commit_payload['pull_request']['head']['sha']
                        . PHP_EOL . PHP_EOL . '**Log:** ' . PHP_EOL
                        . '```changelog' . PHP_EOL
                        . $state . PHP_EOL
                        . '```'
            ]
        );
    }

    public function writeComment(array $commit_payload) {
        try {
            $client->request(
                'POST',
                $commit_payload['pull_request']['comments_url'],
                [
                    'headers' => $headers,
                    'body' => $this->getStateBody($this->status, $commit_payload)
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
        }
    }
}

function main()
{
    $reporter = new AutoDeployCIReporter();
    $reporter->assureValidRequestOrDie(['pull_request']);
    $payload = json_decode($_POST['payload'], true);
    $reporter->assureMergedToMasterOrDie($payload);
    if ($reporter->deploy($payload)) {
        $reporter->updateVersion($payload);
        $reporter->writeComment($payload);
    }
}


if (realpath(__FILE__) ==
        realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) {
    main();
}
