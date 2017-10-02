<?php declare(strict_types=1);
/* This file checks if the composer.lock file on the server and for the pull
 * request are the same. If they are not, the vendor directory on the server
 * has to be updated to match the new required versions.
 *
 * It is supposed to be used as a Webhook for pull_requests and
 * sets status reports.
 */
namespace Timey;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/CIReporter.php';


class LockCIReporter extends CIReporter
{
    private function writeReport(string $status, array $commit_payload) {
        // TODO(shoeffner): Use database instead of static files.
        $template = file_get_contents(__DIR__ . '/../ci_reports/template.thtml');
        $status_messages = [
            'success' => 'unchanged',
            'failure' => 'changed',
            'pending' => 'checking',
            'error' => 'error'
        ];
        $color = [
            'success' => '2cbe4e',
            'failure' => 'cb2431',
            'pending' => 'dbab09',
            'error' => 'cb2431'
        ];
        $favicon = [
            'success' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAL0lE'
                       . 'QVR42u3OMQEAAAQAMPrIIrJqxPBsCZY1vfEoBQQEBAQEBAQEBAQE'
                       . 'BAQEvgMHtGNHAdZ/v/8AAAAASUVORK5CYII=',
            'failure' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAMElE'
                       . 'QVR42u3OMQEAAAQAMLoooH8EoYjh2RIsp3rjUQoICAgICAgICAgI'
                       . 'CAgICHwHDt7sRAGYJdY4AAAAAElFTkSuQmCC',
            'pending' => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAMElE'
                       . 'QVR42u3OMQEAAAQAME79Y4gkDDE8W4LldG08SgEBAQEBAQEBAQEB'
                       . 'AQEBge/AAS3oUeHCNBX5AAAAAElFTkSuQmCC',
            'error'   => 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAMElE'
                       . 'QVR42u3OMQEAAAQAMLoooH8EoYjh2RIsp3rjUQoICAgICAgICAgI'
                       . 'CAgICHwHDt7sRAGYJdY4AAAAAElFTkSuQmCC'
        ];
        $commit = $commit_payload['pull_request']['head']['sha'];
        $map = [
            '[[BADGE_COLOR]]' => $color[$status],
            '[[COLOR]]' => $color[$status],
            '[[COMMIT]]' => $commit,
            '[[AVATAR]]' => $commit_payload['pull_request']['user']['avatar_url'],
            '[[AUTHOR]]' => $commit_payload['pull_request']['user']['login'],
            '[[PR_TEXT]]' => $commit_payload['pull_request']['body'],
            '[[CREATED]]' => $commit_payload['pull_request']['created_at'],
            '[[UPDATED]]' => $commit_payload['pull_request']['updated_at'],
            '[[FAVICON]]' => $favicon[$status],
            '[[OWNER]]' => $this->config['github']['owner'],
            '[[PR#]]' => $commit_payload['pull_request']['number'],
            '[[PR_TITLE]]' => $commit_payload['pull_request']['title'],
            '[[PR_URL]]' => $commit_payload['pull_request']['html_url'],
            '[[REPOSITORY]]' => $this->config['github']['repository'],
            '[[STATUS]]' => strtoupper($status),
            '[[STATUS_MESSAGE]]' => $$status_messages[$status],
        ];

        foreach ($map as $key => $value) {
            $template = str_replace($key, $value, $template);
        }

        file_put_contents(__DIR__ . "/../ci_reports/{$commit}.html", $template);
    }

    protected function getStateBody(string $state, array $commit_payload) : string {
        $description = [
            'success' => 'composer.lock unchanged.',
            'pending' => 'Checking if composer.lock changed.',
            'failure' => 'composer.lock changed.',
            'error' => 'Error while checking composer.lock.'
        ];
        return json_encode([
            'state' => $state,
            'target_url' => "{$this->config['web']['base_url']}/ci_reports/"
                          . "{$commit_payload['pull_request']['head']['sha']}.html",
            'description' => $description[$state],
            'context' => "continuous-integration/composer.lock-check"
        ]);
    }

    private function writeAndNotify(string $state, array $commit_payload) {
        $this->writeReport($state, $commit_payload);
        $this->postGitHubStatus($state, $commit_payload);
    }

    public function compareLockFiles(array $commit_payload) {
        $this->writeAndNotify('pending', $commit_payload);

        $repo_url = "https://api.github.com/repos/"
                  . "{$this->config['github']['owner']}/{$this->config['github']['repository']}";
        $commit = $commit_payload['pull_request']['head']['sha'];

        try {
            $response = $client->request(
                'GET',
                "{$repo_url}/contents/composer.lock?ref={$commit}",
                [
                    'headers' => $headers
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $this->writeAndNotify('error', $commit_payload);
            return;
        }

        if ($response->getStatusCode() != 200) {
            $this->writeAndNotify('error', $commit_payload);
            return;
        }

        // Get file contents from git and server
        $content = $response->getBody();
        $commit_composer_content =
            base64_decode(json_decode($content, true)['content']);

        $server_composer_content = file_get_contents(__DIR__ . '/../composer.lock');

        $result = $server_composer_content == $commit_composer_content;
        $this->writeAndNotify($result ? 'success' : 'failure', $payload);
    }
}


function main()
{
    $reporter = new LockCIReporter();
    $reporter->assureValidRequestOrDie(['pull_request']);
    $reporter->compareLockFiles(json_decode($_POST['payload'], true));
}


if (realpath(__FILE__) ==
        realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) {
    main();
}
