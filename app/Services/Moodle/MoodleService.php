<?php

namespace App\Services\Moodle;

use App\Contracts\Services\MoodleServiceInterface;
use App\Exceptions\Moodle\MoodleAuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Handles Moodle authentication and authenticated page fetching via Guzzle.
 *
 * Uses a CookieJar to maintain session state across requests within
 * a single service instance (sync command lifecycle).
 */
class MoodleService implements MoodleServiceInterface
{
    private readonly Client $client;

    private readonly CookieJar $cookieJar;

    private bool $authenticated = false;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();

        $this->maxRetries = (int) config('moodle.max_retries', 2);
        $this->client = new Client([
            'base_uri' => rtrim((string) config('moodle.base_url'), '/') . '/',
            'cookies' => $this->cookieJar,
            'timeout' => (int) config('moodle.timeout', 90),
            'connect_timeout' => (int) config('moodle.connect_timeout', 10),
            'headers' => [
                'User-Agent' => (string) config('moodle.user_agent'),
            ],
            'allow_redirects' => true,
        ]);
    }

    private int $maxRetries;

    /**
     * {@inheritDoc}
     */
    public function authenticate(): void
    {
        $username = config('moodle.username');
        $password = config('moodle.password');

        if (empty($username) || empty($password)) {
            throw new MoodleAuthenticationException(
                'Moodle credentials are not configured. Set MOODLE_USERNAME and MOODLE_PASSWORD in .env.'
            );
        }

        $loginPageResponse = $this->requestWithRetries('GET', 'login/index.php');
        $loginPageHtml = (string) $loginPageResponse->getBody();
        $loginToken = $this->extractLoginToken($loginPageHtml);

        $loginResponse = $this->requestWithRetries('POST', 'login/index.php', [
            'form_params' => [
                'username' => $username,
                'password' => $password,
                'logintoken' => $loginToken,
            ],
        ]);

        $loginResultHtml = (string) $loginResponse->getBody();

        if (! $this->isLoginSuccessful($loginResultHtml)) {
            throw new MoodleAuthenticationException(
                'Moodle login failed. Verify MOODLE_BASE_URL, username, and password.'
            );
        }

        $this->authenticated = true;

        Log::info('Moodle authentication successful.');
    }

    /**
     * {@inheritDoc}
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchDashboard(): string
    {
        if (! $this->authenticated) {
            $this->authenticate();
        }

        $response = $this->requestWithRetries('GET', 'my/');
        $html = (string) $response->getBody();

        if ($this->isLoginPage($html)) {
            $this->authenticated = false;

            throw new MoodleAuthenticationException(
                'Moodle session expired or dashboard is unreachable.'
            );
        }

        return $html;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchCoursePage(int $courseId): string
    {
        if (! $this->authenticated) {
            $this->authenticate();
        }

        $response = $this->requestWithRetries('GET', 'course/view.php', [
            'query' => ['id' => $courseId],
        ]);

        $html = (string) $response->getBody();

        if ($this->isLoginPage($html)) {
            $this->authenticated = false;

            throw new MoodleAuthenticationException(
                sprintf('Moodle session expired while fetching course %d.', $courseId)
            );
        }

        return $html;
    }

    /**
     * Perform an HTTP request with simple retry/backoff for transient errors.
     *
     * @throws \RuntimeException on persistent failure
     */
    private function requestWithRetries(string $method, string $uri, array $options = []): ResponseInterface
    {
        $attempt = 0;

        while (true) {
            try {
                $attempt++;

                if (strtoupper($method) === 'GET') {
                    return $this->client->request('GET', $uri, $options);
                }

                if (strtoupper($method) === 'POST') {
                    return $this->client->request('POST', $uri, $options);
                }

                return $this->client->request($method, $uri, $options);
            } catch (GuzzleException $e) {
                Log::warning('Moodle HTTP request failed', [
                    'uri' => $uri,
                    'method' => $method,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt > $this->maxRetries) {
                    throw new RuntimeException(sprintf('HTTP request to %s failed after %d attempts: %s', $uri, $attempt, $e->getMessage()));
                }

                // simple exponential backoff (1s, 2s, 4s...)
                $backoff = (int) pow(2, $attempt - 1);
                sleep($backoff);
                continue;
            }
        }
    }

    /**
     * Extract the CSRF logintoken from the Moodle login form.
     */
    private function extractLoginToken(string $html): string
    {
        $crawler = new Crawler($html);
        $tokenInput = $crawler->filter('input[name="logintoken"]');

        if ($tokenInput->count() === 0) {
            throw new MoodleAuthenticationException(
                'Login token not found. The Moodle login page structure may have changed.'
            );
        }

        $token = $tokenInput->attr('value');

        if (empty($token)) {
            throw new MoodleAuthenticationException('Moodle login token is empty.');
        }

        return $token;
    }

    /**
     * Determine whether the POST-login response indicates a successful session.
     */
    private function isLoginSuccessful(string $html): bool
    {
        if ($this->hasLoginError($html)) {
            return false;
        }

        $crawler = new Crawler($html);

        return $crawler->filter('a[href*="logout"]')->count() > 0
            || $crawler->filter('[data-region="usermenu"]')->count() > 0
            || ! $this->isLoginPage($html);
    }

    /**
     * Detect Moodle login error alerts on the login page.
     */
    private function hasLoginError(string $html): bool
    {
        $crawler = new Crawler($html);

        return $crawler->filter('.alert-danger, .loginerrors')->count() > 0;
    }

    /**
     * Detect whether the HTML belongs to the Moodle login page.
     */
    private function isLoginPage(string $html): bool
    {
        $crawler = new Crawler($html);

        return $crawler->filter('form#login')->count() > 0
            || $crawler->filter('input[name="logintoken"]')->count() > 0;
    }
}
