<?php declare(strict_types=1);
namespace Timey;

require __DIR__ . '/../vendor/autoload.php';


abstract class CIReporter
{
    protected $config;
    protected $client;
    protected $headers;

    public function __construct()
    {
        $this->config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
        $this->client = new GuzzleHttp\Client();
        $this->headers = [
            'User-Agent' => "{$config['github']['owner']}/{$config['github']['repository']}-ci",
            'Authorization' => "token {$config['github']['api_token']}"
        ];
    }

    public function assureValidRequestOrDie(array $handled_requests=['pull_request'])
    {
        // Check if all variables are available
        if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE']) || !isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
            die();
        }

        // Check integrity hash
        $hash = hash_hmac('sha1', file_get_contents('php://input'), $this->config['github']['hook_secret']);
        if ("sha1={$hash}" != $_SERVER['HTTP_X_HUB_SIGNATURE']) {
            die();
        }

        // Check if this CI tool manages this request
        if (!in_array($_SERVER['HTTP_X_GITHUB_EVENT'], $handled_requests)) {
            die();
        }
    }

    abstract protected function getStateBody(string $state, array $commit_payload) : string;

    protected function postGitHubStatus(string $state, array $commit_payload) {
        if (!array_key_exists('pull_request', $commit_payload)) {
            return;
        }

        $body = $this->getStateBody($state, $commit_payload);
        try {
            $this->client->request(
                'POST',
                $commit_payload['pull_request']['statuses_url'],
                [
                    'headers' => $this->headers,
                    'body' => $body
                ]
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            // Usually we don't care if this fails
        }
    }
}
