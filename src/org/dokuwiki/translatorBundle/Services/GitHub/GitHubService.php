<?php

namespace org\dokuwiki\translatorBundle\Services\GitHub;

use Github\Client;
use Github\Exception\RuntimeException;
use Github\HttpClient\CachedHttpClient;

class GitHubService {

    private $token;
    private $client;
    private $gitHubUrl;

    function __construct($gitHubApiToken, $dataFolder, $gitHubUrl, $autoStartup = true) {
        $this->gitHubUrl = $gitHubUrl;
        if (!$autoStartup) {
            return;
        }
        $this->token = $gitHubApiToken;
        $this->client = new Client(
            new CachedHttpClient(array('cache_dir' => "$dataFolder/githubcache"))
        );
        $this->client->authenticate($gitHubApiToken, null, Client::AUTH_URL_TOKEN);
    }

    /**
     * @param string $url GitHub URL to create the fork from
     * @throws GitHubForkException
     * @return string Git URL of the fork
     */
    public function createFork($url) {
        list($user, $repository) = $this->getUsernameAndRepositoryFromURL($url);
        try {
            $result = $this->client->api('repo')->forks()->create($user, $repository);
        } catch (RuntimeException $e) {
            throw new GitHubForkException($e->getMessage()." $user/$repository", 0, $e);
        }
        return $this->gitHubUrlHack($result['ssh_url']);
    }

    /**
     * @param $url
     * @return array|mixed
     * @throws GitHubServiceException
     */
    public function getUsernameAndRepositoryFromURL($url) {
        $result = preg_replace('#^(https://github.com/|git@.*?github.com:|git://github.com/)(.*)\.git$#', '$2', $url, 1, $counter);
        if ($counter === 0) {
            throw new GitHubServiceException('Invalid GitHub clone URL: ' . $url);
        }
        $result = explode('/', $result);

        return $result;
    }

    public function gitHubUrlHack($url) {
        if ($this->gitHubUrl === 'github.com') return $url;
        return str_replace('github.com', $this->gitHubUrl, $url);
    }

    /**
     * @param string $patchBranch   name of branch with language update
     * @param string $branch        name of branch at remote
     * @param string $languageCode
     * @param string $url           remote url
     * @param string $patchUrl      remote url
     * @throws GitHubCreatePullRequestException
     */
    public function createPullRequest($patchBranch, $branch, $languageCode, $url, $patchUrl) {
        list($user, $repository) = $this->getUsernameAndRepositoryFromURL($url);
        list($repoName, $ignored) = $this->getUsernameAndRepositoryFromURL($patchUrl);

        try {
            $this->client->api('pull_request')->create($user, $repository, array(
                'base'  => $branch,
                'head'  => $repoName.':'.$patchBranch,
                'title' => 'Translation update ('.$languageCode.')',
                'body'  => 'This pull request contains some translation updates.'
            ));
        } catch (RuntimeException $e) {
            throw new GitHubCreatePullRequestException('', 0, $e);
        }
    }

    /**
     * Get information about the open pull requests i.e. url and count
     *
     * @param string $url remote url
     * @param string $languageCode
     *
     * @return array
     */
    public function getOpenPRlistInfo($url, $languageCode) {
        list($user, $repository) = $this->getUsernameAndRepositoryFromURL($url);

        $info = [
            'listURL' => '',
            'count' => 0
        ];

        try {
            $q = 'Translation update ('.$languageCode.') in:title repo:'.$user.'/'.$repository.' type:pr state:open';
            $results = $this->client->api('search')->issues($q, $sort = 'updated', $order = 'desc');

            $info = [
                'listURL' => 'https://github.com/'.$user.'/'.$repository.'/pulls?q=is%3Apr+is%3Aopen+Translation+update+%28'.$languageCode.'%29',
                'count' => (int) $results['total_count']
            ];
        } catch (\Exception $e) {
        }

        return $info;
    }
}