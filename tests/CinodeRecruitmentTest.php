<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for cinode_recruitment_create_candidate_user(),
 * cinode_recruitment_multipart_post(), and
 * cinode_recruitment_get_company_user_id().
 *
 * WordPress HTTP calls are intercepted via a global callback
 * ($GLOBALS['__pre_http_request']) that mirrors WP's own
 * pre_http_request filter pattern.
 */
class CinodeRecruitmentTest extends TestCase
{
    /** @var list<array{url: string, args: array}> */
    private array $httpLog = [];

    protected function setUp(): void
    {
        $this->httpLog = [];

        // Sensible option defaults
        $GLOBALS['__wp_options'] = [
            'cinode_recruitment_options' => [
                'option_companyId' => '42',
                'option_apiKey'    => 'test-token',
                'option_subcontractor_default_language_id' => '714',
            ],
            'cinode_recruitment_options_sendmail' => [
                'option_subject' => 'Application received',
                'option_message' => 'Thank you for your application.',
            ],
        ];

        $GLOBALS['__wp_transients'] = [];
        $GLOBALS['__wp_mail_log'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__pre_http_request']);
    }

    /* ------------------------------------------------------------------
     * Helper: install a fake HTTP responder and log every request.
     * ----------------------------------------------------------------*/
    private function fakeHttp(int $statusCode, array $body = []): void
    {
        $log = &$this->httpLog;
        $GLOBALS['__pre_http_request'] = function (string $url, array $args) use ($statusCode, $body, &$log) {
            $log[] = ['url' => $url, 'args' => $args];
            return [
                'response' => ['code' => $statusCode],
                'body'     => json_encode($body),
            ];
        };
    }

    /**
     * Install a responder that returns a WP_Error.
     */
    private function fakeHttpError(string $code = 'http_request_failed', string $message = 'cURL error'): void
    {
        $log = &$this->httpLog;
        $GLOBALS['__pre_http_request'] = function (string $url, array $args) use ($code, $message, &$log) {
            $log[] = ['url' => $url, 'args' => $args];
            return new WP_Error($code, $message);
        };
    }

    /**
     * Install a responder that returns successive responses from a list.
     * Each entry: [statusCode, body?]
     */
    private function fakeHttpSequence(array $responses): void
    {
        $log = &$this->httpLog;
        $index = 0;
        $GLOBALS['__pre_http_request'] = function (string $url, array $args) use (&$responses, &$log, &$index) {
            $log[] = ['url' => $url, 'args' => $args];
            $resp = $responses[$index] ?? end($responses);
            $index++;
            return [
                'response' => ['code' => $resp[0]],
                'body'     => json_encode($resp[1] ?? []),
            ];
        };
    }

    /**
     * Run a callback while capturing error_log output.
     */
    private function captureErrorLog(callable $fn): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpunit_log_');
        $prev = ini_set('error_log', $tmp);
        try {
            $fn();
        } finally {
            ini_set('error_log', $prev);
        }
        $contents = file_get_contents($tmp);
        unlink($tmp);
        return $contents !== false ? $contents : '';
    }

    /* ==================================================================
     * 1. cinode_recruitment_create_candidate_user()
     * ================================================================*/

    #[Test]
    public function create_candidate_user_sends_correct_url_and_json_body(): void
    {
        $this->fakeHttp(201, ['companyUserId' => 99]);

        $postData = [
            'firstName' => 'Jane',
            'lastName'  => 'Doe',
            'email'     => 'jane@example.com',
        ];

        $result = cinode_recruitment_create_candidate_user($postData, 7, '42', 'my-token');

        // Returned user ID
        $this->assertSame(99, $result);

        // Exactly one HTTP call
        $this->assertCount(1, $this->httpLog);

        $req = $this->httpLog[0];

        // URL includes company + candidate IDs
        $this->assertSame(
            'https://api.cinode.app/v0.1/companies/42/candidates/7/user',
            $req['url']
        );

        // Headers
        $this->assertSame('application/json', $req['args']['headers']['Content-Type']);
        $this->assertSame('Bearer my-token', $req['args']['headers']['Authorization']);

        // Body payload
        $body = json_decode($req['args']['body'], true);
        $this->assertSame('Jane', $body['firstName']);
        $this->assertSame('Doe', $body['lastName']);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertTrue($body['createProfile']);
        $this->assertSame(714, $body['languageId']);
        $this->assertArrayNotHasKey('profileLanguageId', $body);
        // Password pair must match
        $this->assertSame($body['password'], $body['confirmPassword']);
        $this->assertNotEmpty($body['password']);
    }

    #[Test]
    public function create_candidate_user_uses_configured_language_id(): void
    {
        $GLOBALS['__wp_options']['cinode_recruitment_options']['option_subcontractor_default_language_id'] = '123';
        $this->fakeHttp(200, ['companyUserId' => 5]);

        $result = cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.com'],
            1, '42', 'tok'
        );

        $body = json_decode($this->httpLog[0]['args']['body'], true);
        $this->assertSame(123, $body['languageId']);
        $this->assertArrayNotHasKey('profileLanguageId', $body);
    }

    #[Test]
    public function create_candidate_user_defaults_language_to_714_when_missing(): void
    {
        unset($GLOBALS['__wp_options']['cinode_recruitment_options']['option_subcontractor_default_language_id']);
        $this->fakeHttp(200, ['companyUserId' => 5]);

        cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.com'],
            1, '42', 'tok'
        );

        $body = json_decode($this->httpLog[0]['args']['body'], true);
        $this->assertSame(714, $body['languageId']);
    }

    #[Test]
    public function create_candidate_user_returns_null_on_non_200_response(): void
    {
        $this->fakeHttp(400, ['error' => 'bad request']);

        $result = cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.com'],
            1, '42', 'tok'
        );

        $this->assertNull($result);
    }

    #[Test]
    public function create_candidate_user_returns_null_on_500(): void
    {
        $this->fakeHttp(500);

        $result = cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'x@y.com'],
            1, '42', 'tok'
        );

        $this->assertNull($result);
    }

    #[Test]
    public function create_candidate_user_returns_null_and_logs_on_wp_error(): void
    {
        $this->fakeHttpError('http_request_failed', 'Connection timed out');

        $logged = $this->captureErrorLog(function () {
            $result = cinode_recruitment_create_candidate_user(
                ['firstName' => 'A', 'lastName' => 'B', 'email' => 'x@y.com'],
                1, '42', 'tok'
            );
            $this->assertNull($result);
        });

        $this->assertStringContainsString('candidate user create failed', $logged);
        $this->assertStringContainsString('Connection timed out', $logged);
    }

    #[Test]
    public function create_candidate_user_extracts_nested_company_user_id(): void
    {
        // Some API responses nest the ID under companyUser.id
        $this->fakeHttp(201, [
            'companyUser' => ['id' => 77],
        ]);

        $result = cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.com'],
            1, '42', 'tok'
        );

        $this->assertSame(77, $result);
    }

    #[Test]
    public function create_candidate_user_accepts_200_as_success(): void
    {
        $this->fakeHttp(200, ['companyUserId' => 12]);

        $result = cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.com'],
            1, '42', 'tok'
        );

        $this->assertSame(12, $result);
    }

    #[Test]
    public function create_candidate_user_intcasts_candidate_id_in_url(): void
    {
        $this->fakeHttp(201, ['companyUserId' => 1]);

        cinode_recruitment_create_candidate_user(
            ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.com'],
            '9abc', // non-numeric suffix should be stripped by intval()
            '42',
            'tok'
        );

        $this->assertStringContainsString('/candidates/9/user', $this->httpLog[0]['url']);
    }

    /* ==================================================================
     * 2. cinode_recruitment_get_company_user_id()
     * ================================================================*/

    #[Test]
    public function get_company_user_id_returns_null_for_non_array(): void
    {
        $this->assertNull(cinode_recruitment_get_company_user_id(null));
        $this->assertNull(cinode_recruitment_get_company_user_id('string'));
    }

    #[Test]
    public function get_company_user_id_from_top_level_key(): void
    {
        $this->assertSame(42, cinode_recruitment_get_company_user_id(['companyUserId' => 42]));
    }

    #[Test]
    public function get_company_user_id_from_nested_company_user(): void
    {
        $this->assertSame(7, cinode_recruitment_get_company_user_id([
            'companyUser' => ['companyUserId' => 7],
        ]));
    }

    #[Test]
    public function get_company_user_id_falls_back_to_nested_id(): void
    {
        $this->assertSame(3, cinode_recruitment_get_company_user_id([
            'companyUser' => ['id' => 3],
        ]));
    }

    #[Test]
    public function get_company_user_id_returns_null_for_zero(): void
    {
        $this->assertNull(cinode_recruitment_get_company_user_id(['companyUserId' => 0]));
    }

    /* ==================================================================
     * 3. cinode_recruitment_multipart_post()
     * ================================================================*/

    #[Test]
    public function multipart_post_body_has_closing_boundary(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [['name' => 'title', 'data' => 'hello']]
        );

        $body = $this->httpLog[0]['args']['body'];
        // RFC 2046: closing boundary is "--<boundary>--"
        $this->assertMatchesRegularExpression('/^--[A-Za-z0-9]+--\r\n$/m', substr($body, strrpos($body, "\r\n--") + 2));
    }

    #[Test]
    public function multipart_post_formats_plain_field_without_filename(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [['name' => 'ImportSkills', 'data' => 'true']]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('Content-Disposition: form-data; name="ImportSkills"', $body);
        $this->assertStringNotContainsString('filename', $body);
        $this->assertStringContainsString("true\r\n", $body);
    }

    #[Test]
    public function multipart_post_formats_file_part_with_content_type(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [[
                'name'     => 'File',
                'filename' => 'cv.pdf',
                'type'     => 'application/pdf',
                'data'     => '%PDF-fake-content',
            ]]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('Content-Disposition: form-data; name="File"; filename="cv.pdf"', $body);
        $this->assertStringContainsString('Content-Type: application/pdf', $body);
        $this->assertStringContainsString('%PDF-fake-content', $body);
    }

    #[Test]
    public function multipart_post_sends_bearer_token_and_correct_content_type(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'my-secret-token',
            [['name' => 'title', 'data' => 'y']]
        );

        $headers = $this->httpLog[0]['args']['headers'];
        $this->assertSame('Bearer my-secret-token', $headers['Authorization']);
        $this->assertStringStartsWith('multipart/form-data; boundary=', $headers['Content-Type']);

        // Extract boundary from Content-Type header and verify body uses it
        preg_match('/boundary=(.+)$/', $headers['Content-Type'], $m);
        $boundary = $m[1];
        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('--' . $boundary, $body);
        $this->assertStringContainsString('--' . $boundary . '--', $body);
    }

    #[Test]
    public function multipart_post_includes_multiple_parts_in_order(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'files', 'filename' => 'cv.pdf', 'type' => 'application/pdf', 'data' => 'pdfdata'],
                ['name' => 'title', 'data' => 'My CV'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];

        // file part must come before title part
        $filePos  = strpos($body, 'name="files"');
        $titlePos = strpos($body, 'name="title"');
        $this->assertNotFalse($filePos);
        $this->assertNotFalse($titlePos);
        $this->assertLessThan($titlePos, $filePos);
    }

    #[Test]
    public function multipart_post_sets_timeout_when_positive(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [['name' => 'title', 'data' => 'y']],
            30
        );

        $this->assertSame(30, $this->httpLog[0]['args']['timeout']);
    }

    #[Test]
    public function multipart_post_omits_timeout_when_zero(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [['name' => 'title', 'data' => 'y']],
            0
        );

        $this->assertArrayNotHasKey('timeout', $this->httpLog[0]['args']);
    }

    /* ==================================================================
     * 4. Header-injection prevention
     * ================================================================*/

    #[Test]
    public function multipart_post_strips_quotes_and_crlf_from_filename(): void
    {
        $this->fakeHttp(200);

        $malicious = "cv\".pdf\r\nX-Injected: true";

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [[
                'name'     => 'files',
                'filename' => $malicious,
                'type'     => 'application/pdf',
                'data'     => 'data',
            ]]
        );

        $body = $this->httpLog[0]['args']['body'];

        // No double-quote inside the filename value
        // The pattern captures the filename="..." value
        preg_match('/filename="([^"]*)"/', $body, $m);
        $this->assertNotEmpty($m, 'filename should be present in Content-Disposition');
        $sanitised = $m[1];
        $this->assertStringNotContainsString('"', $sanitised);
        $this->assertStringNotContainsString("\r", $sanitised);
        $this->assertStringNotContainsString("\n", $sanitised);

        // The injected text must not appear as its own header line
        // (it may be folded into the filename value, which is safe)
        $this->assertDoesNotMatchRegularExpression('/^X-Injected:/m', $body);
    }

    #[Test]
    public function multipart_post_strips_null_bytes_from_filename(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [[
                'name'     => 'files',
                'filename' => "cv\0.pdf",
                'type'     => 'application/pdf',
                'data'     => 'data',
            ]]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringNotContainsString("\0", $body);
    }

    #[Test]
    public function multipart_post_strips_crlf_from_content_type(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [[
                'name'     => 'files',
                'filename' => 'cv.pdf',
                'type'     => "application/pdf\r\nX-Injected: true",
                'data'     => 'data',
            ]]
        );

        $body = $this->httpLog[0]['args']['body'];
        // Must not appear as a separate header line (CRLF injection neutralised)
        $this->assertDoesNotMatchRegularExpression('/^X-Injected:/m', $body);
        // Verify CR/LF are gone from the Content-Type value itself
        preg_match('/^Content-Type: (.+?)\r?$/m', $body, $ct);
        $this->assertNotEmpty($ct);
        $this->assertStringNotContainsString("\r", $ct[1]);
        $this->assertStringNotContainsString("\n", $ct[1]);
    }

    #[Test]
    public function multipart_post_drops_parts_with_unknown_name(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'files', 'filename' => 'cv.pdf', 'type' => 'application/pdf', 'data' => 'pdfdata'],
                ['name' => 'evil_field', 'data' => 'should be dropped'],
                ['name' => 'title', 'data' => 'My CV'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('name="files"', $body);
        $this->assertStringContainsString('name="title"', $body);
        $this->assertStringNotContainsString('evil_field', $body);
        $this->assertStringNotContainsString('should be dropped', $body);
    }

    #[Test]
    public function multipart_post_accepts_all_known_name_values(): void
    {
        $this->fakeHttp(200);

        // All four known names used across call sites
        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'files', 'filename' => 'a.pdf', 'type' => 'application/pdf', 'data' => '1'],
                ['name' => 'File',  'filename' => 'b.pdf', 'type' => 'application/pdf', 'data' => '2'],
                ['name' => 'title', 'data' => 'T'],
                ['name' => 'ImportSkills', 'data' => 'true'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('name="files"', $body);
        $this->assertStringContainsString('name="File"', $body);
        $this->assertStringContainsString('name="title"', $body);
        $this->assertStringContainsString('name="ImportSkills"', $body);
    }

    #[Test]
    public function sanitize_multipart_filename_handles_path_traversal(): void
    {
        $result = cinode_recruitment_sanitize_multipart_filename('../../etc/passwd');
        $this->assertStringNotContainsString('..', $result);
        $this->assertStringNotContainsString('/', $result);
    }

    /* ==================================================================
     * 5. Part validation & optional content-type for non-file fields
     * ================================================================*/

    #[Test]
    public function multipart_post_skips_part_missing_data(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'title'],                         // no 'data' key
                ['name' => 'ImportSkills', 'data' => 'true'], // valid
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringNotContainsString('name="title"', $body);
        $this->assertStringContainsString('name="ImportSkills"', $body);
    }

    #[Test]
    public function multipart_post_skips_file_part_missing_data(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'files', 'filename' => 'cv.pdf', 'type' => 'application/pdf'],
                ['name' => 'title', 'data' => 'My CV'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringNotContainsString('name="files"', $body);
        $this->assertStringContainsString('name="title"', $body);
    }

    #[Test]
    public function multipart_post_file_part_defaults_type_when_missing(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'files', 'filename' => 'cv.pdf', 'data' => 'pdfdata'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('Content-Type: application/octet-stream', $body);
    }

    #[Test]
    public function multipart_post_non_file_part_emits_content_type_when_set(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'ImportSkills', 'data' => 'true', 'type' => 'text/plain'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('name="ImportSkills"', $body);
        $this->assertStringContainsString('Content-Type: text/plain', $body);
        // Must not have a filename
        $this->assertStringNotContainsString('filename', $body);
    }

    #[Test]
    public function multipart_post_non_file_part_omits_content_type_by_default(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['name' => 'title', 'data' => 'hello'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringContainsString('name="title"', $body);
        $this->assertStringNotContainsString('Content-Type:', $body);
    }

    #[Test]
    public function multipart_post_skips_part_missing_name(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_multipart_post(
            'https://example.com/upload',
            'tok',
            [
                ['data' => 'orphan value'],
                ['name' => 'title', 'data' => 'ok'],
            ]
        );

        $body = $this->httpLog[0]['args']['body'];
        $this->assertStringNotContainsString('orphan value', $body);
        $this->assertStringContainsString('name="title"', $body);
    }

    /* ==================================================================
     * 6. WP_Error handling in candidate/subcontractor post flows
     * ================================================================*/

    private function makePostData(array $overrides = []): FakeWPRestRequest
    {
        return new FakeWPRestRequest(array_merge([
            'firstName'   => 'Jane',
            'lastName'    => 'Doe',
            'email'       => 'jane@example.com',
            'phone'       => '555-1234',
            'description' => 'Hello',
            'linkedInUrl' => 'https://linkedin.com/in/jane',
            'state'       => '0',
            'currencyId'  => '1',
        ], $overrides));
    }

    #[Test]
    public function candidate_post_returns_500_on_wp_error(): void
    {
        $this->fakeHttpError('http_request_failed', 'DNS resolution failed');

        $request = $this->makePostData();

        $logged = $this->captureErrorLog(function () use ($request) {
            $response = cinodeRecruitmentPost($request);
            $this->assertInstanceOf(WP_REST_Response::class, $response);
            $this->assertSame(500, $response->status);
        });

        $this->assertStringContainsString('candidate create failed', $logged);
        $this->assertStringContainsString('DNS resolution failed', $logged);
    }

    #[Test]
    public function subcontractor_post_returns_500_on_wp_error(): void
    {
        $this->fakeHttpError('http_request_failed', 'SSL handshake failed');

        $request = $this->makePostData(['recipient_type' => 'subcontractor']);
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        $logged = $this->captureErrorLog(function () use ($request, $options) {
            $response = cinode_recruitment_create_subcontractor(
                $request, '42', 'test-token', $options
            );
            $this->assertInstanceOf(WP_REST_Response::class, $response);
            $this->assertSame(500, $response->status);
        });

        $this->assertStringContainsString('subcontractor create failed', $logged);
        $this->assertStringContainsString('SSL handshake failed', $logged);
    }

    #[Test]
    public function candidate_post_does_not_send_mail_on_wp_error(): void
    {
        $this->fakeHttpError();

        $request = $this->makePostData();
        $this->captureErrorLog(function () use ($request) {
            cinodeRecruitmentPost($request);
        });

        // Only one HTTP call (the failed post); no mail-related calls follow
        $this->assertCount(1, $this->httpLog);
    }

    /* ==================================================================
     * 7. cinodeRecruitmentPost() – candidate success path
     * ================================================================*/

    #[Test]
    public function candidate_post_success_sends_correct_url_and_body(): void
    {
        $this->fakeHttp(201, ['id' => 5]);

        $request = $this->makePostData();
        $response = cinodeRecruitmentPost($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(201, $response->status);

        $req = $this->httpLog[0];
        $this->assertSame('https://api.cinode.app/v0.1/companies/42/candidates', $req['url']);
        $this->assertSame('Bearer test-token', $req['args']['headers']['Authorization']);

        $body = json_decode($req['args']['body'], true);
        $this->assertSame('Jane', $body['firstName']);
        $this->assertSame('Doe', $body['lastName']);
        $this->assertSame('Hello', $body['description']);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame('555-1234', $body['phone']);
        $this->assertSame('https://linkedin.com/in/jane', $body['linkedInUrl']);
    }

    #[Test]
    public function candidate_post_includes_optional_int_fields_only_when_positive(): void
    {
        $this->fakeHttp(201, ['id' => 5]);

        $request = $this->makePostData([
            'pipelineId'           => '3',
            'pipelineStageId'      => '0',
            'recruitmentManagerId' => '7',
            'teamId'               => '0',
            'companyAddressId'     => '2',
            'recruitmentSourceId'  => '9',
        ]);
        cinodeRecruitmentPost($request);

        $body = json_decode($this->httpLog[0]['args']['body'], true);
        $this->assertSame(3, $body['pipelineId']);
        $this->assertArrayNotHasKey('pipelineStageId', $body);
        $this->assertSame(7, $body['recruitmentManagerId']);
        $this->assertArrayNotHasKey('teamId', $body);
        $this->assertSame(2, $body['companyAddressId']);
        $this->assertSame(9, $body['recruitmentSourceId']);
    }

    #[Test]
    public function candidate_post_includes_optional_string_fields_when_set(): void
    {
        $this->fakeHttp(201, ['id' => 5]);

        $request = $this->makePostData([
            'availableFrom' => '2025-06-01',
            'campaignCode'  => 'SUMMER25',
        ]);
        cinodeRecruitmentPost($request);

        $body = json_decode($this->httpLog[0]['args']['body'], true);
        $this->assertSame('2025-06-01', $body['availableFromDate']);
        $this->assertSame('SUMMER25', $body['campaignCode']);
    }

    #[Test]
    public function candidate_post_excludes_optional_string_fields_when_empty(): void
    {
        $this->fakeHttp(201, ['id' => 5]);

        $request = $this->makePostData();
        cinodeRecruitmentPost($request);

        $body = json_decode($this->httpLog[0]['args']['body'], true);
        $this->assertArrayNotHasKey('availableFromDate', $body);
        $this->assertArrayNotHasKey('campaignCode', $body);
    }

    #[Test]
    public function candidate_post_sends_confirmation_email_on_success(): void
    {
        $this->fakeHttp(201, ['id' => 5]);

        $request = $this->makePostData();
        cinodeRecruitmentPost($request);

        $this->assertCount(1, $GLOBALS['__wp_mail_log']);
        $this->assertSame('jane@example.com', $GLOBALS['__wp_mail_log'][0][0]);
        $this->assertSame('Application received', $GLOBALS['__wp_mail_log'][0][1]);
    }

    #[Test]
    public function candidate_post_does_not_send_email_on_non_201(): void
    {
        $this->fakeHttp(400);

        $request = $this->makePostData();
        $response = cinodeRecruitmentPost($request);

        $this->assertSame(400, $response->status);
        $this->assertEmpty($GLOBALS['__wp_mail_log']);
    }

    #[Test]
    public function candidate_post_returns_415_for_invalid_cv(): void
    {
        // is_uploaded_file() returns false in test context → CV validation fails
        $request = new FakeWPRestRequest(
            ['firstName' => 'Jane', 'lastName' => 'Doe', 'email' => 'jane@example.com'],
            ['files' => ['tmp_name' => '/tmp/fake.pdf', 'name' => 'test.pdf']]
        );

        $response = cinodeRecruitmentPost($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(415, $response->status);
        $this->assertEmpty($this->httpLog);
    }

    #[Test]
    public function candidate_post_routes_subcontractor_to_correct_endpoint(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // welcome email
        ]);

        $request = $this->makePostData(['recipient_type' => 'subcontractor']);
        cinodeRecruitmentPost($request);

        $this->assertStringContainsString('/subcontractors', $this->httpLog[0]['url']);
        $this->assertStringNotContainsString('/candidates', $this->httpLog[0]['url']);
    }

    /* ==================================================================
     * 8. cinode_recruitment_create_subcontractor() – success path
     * ================================================================*/

    #[Test]
    public function subcontractor_success_sends_correct_url_and_body(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // welcome email
        ]);

        $request = $this->makePostData();
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        $response = cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(201, $response->status);

        $req = $this->httpLog[0];
        $this->assertSame('https://api.cinode.app/v0.1/companies/42/subcontractors', $req['url']);

        $body = json_decode($req['args']['body'], true);
        $this->assertSame('Jane', $body['firstName']);
        $this->assertSame('Doe', $body['lastName']);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame(714, $body['languageId']);
        $this->assertSame(714, $body['profileLanguageId']);
        $this->assertTrue($body['createProfile']);
        $this->assertSame($body['password'], $body['passwordConfirm']);
    }

    #[Test]
    public function subcontractor_defaults_language_to_714_when_not_configured(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // welcome email
        ]);

        $request = $this->makePostData();
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];
        unset($options['option_subcontractor_default_language_id']);

        cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        $body = json_decode($this->httpLog[0]['args']['body'], true);
        $this->assertSame(714, $body['languageId']);
    }

    #[Test]
    public function subcontractor_success_sends_welcome_email_and_confirmation(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // welcome email
        ]);

        $request = $this->makePostData();
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        // Second HTTP call is the welcome email
        $this->assertCount(2, $this->httpLog);
        $this->assertSame(
            'https://api.cinode.app/v0.1/companies/42/subcontractors/10/send-welcome-email',
            $this->httpLog[1]['url']
        );

        // wp_mail confirmation
        $this->assertCount(1, $GLOBALS['__wp_mail_log']);
        $this->assertSame('jane@example.com', $GLOBALS['__wp_mail_log'][0][0]);
    }

    #[Test]
    public function subcontractor_success_adds_to_groups_from_csv(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // group 5
            [200], // group 8
            [200], // welcome email
        ]);

        $request = $this->makePostData(['subcontractorGroupIds' => '5,8']);
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        // Calls: create, group 5, group 8, welcome email
        $this->assertCount(4, $this->httpLog);
        $this->assertStringContainsString('/groups/5/members', $this->httpLog[1]['url']);
        $this->assertStringContainsString('/groups/8/members', $this->httpLog[2]['url']);

        $group5Body = json_decode($this->httpLog[1]['args']['body'], true);
        $this->assertSame(20, $group5Body['companyUserSubcontractorId']);
    }

    #[Test]
    public function subcontractor_success_adds_to_groups_from_array(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // group 3
            [200], // welcome email
        ]);

        $request = $this->makePostData(['subcontractorGroupIds' => [3]]);
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        $this->assertCount(3, $this->httpLog);
        $this->assertStringContainsString('/groups/3/members', $this->httpLog[1]['url']);
    }

    #[Test]
    public function subcontractor_skips_zero_group_ids(): void
    {
        $this->fakeHttpSequence([
            [201, ['id' => 10, 'companyUserId' => 20]],
            [200], // welcome email
        ]);

        $request = $this->makePostData(['subcontractorGroupIds' => '0,0']);
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        // Only create + welcome email; no group calls
        $this->assertCount(2, $this->httpLog);
    }

    #[Test]
    public function subcontractor_non_201_skips_emails_and_groups(): void
    {
        $this->fakeHttp(400);

        $request = $this->makePostData(['subcontractorGroupIds' => '5']);
        $options = $GLOBALS['__wp_options']['cinode_recruitment_options'];

        $response = cinode_recruitment_create_subcontractor($request, '42', 'test-token', $options);

        $this->assertSame(400, $response->status);
        $this->assertCount(1, $this->httpLog);
        $this->assertEmpty($GLOBALS['__wp_mail_log']);
    }

    /* ==================================================================
     * 9. cinode_recruitment_add_to_subcontractor_group()
     * ================================================================*/

    #[Test]
    public function add_to_group_sends_correct_url_body_and_headers(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_add_to_subcontractor_group('42', 'my-token', 55, 7);

        $this->assertCount(1, $this->httpLog);
        $req = $this->httpLog[0];

        $this->assertSame(
            'https://api.cinode.app/v0.1/companies/42/subcontractors/groups/7/members',
            $req['url']
        );

        $body = json_decode($req['args']['body'], true);
        $this->assertSame(55, $body['companyUserSubcontractorId']);
        $this->assertSame('Bearer my-token', $req['args']['headers']['Authorization']);
    }

    #[Test]
    public function add_to_group_casts_ids_to_int(): void
    {
        $this->fakeHttp(200);

        cinode_recruitment_add_to_subcontractor_group('42', 'tok', '99', '3');

        $req = $this->httpLog[0];
        $this->assertStringContainsString('/groups/3/members', $req['url']);

        $body = json_decode($req['args']['body'], true);
        $this->assertSame(99, $body['companyUserSubcontractorId']);
    }

    /* ==================================================================
     * 10. cinode_recruitment_uploaded_cv_is_valid()
     * ================================================================*/

    #[Test]
    public function cv_valid_returns_true_when_no_files(): void
    {
        $request = new FakeWPRestRequest([], []);
        $this->assertTrue(cinode_recruitment_uploaded_cv_is_valid($request));
    }

    #[Test]
    public function cv_valid_returns_true_when_files_key_empty(): void
    {
        $request = new FakeWPRestRequest([], ['files' => []]);
        $this->assertTrue(cinode_recruitment_uploaded_cv_is_valid($request));
    }

    #[Test]
    public function cv_valid_returns_false_when_missing_tmp_name(): void
    {
        $request = new FakeWPRestRequest([], ['files' => ['name' => 'test.pdf']]);
        $this->assertFalse(cinode_recruitment_uploaded_cv_is_valid($request));
    }

    #[Test]
    public function cv_valid_returns_false_when_missing_name(): void
    {
        $request = new FakeWPRestRequest([], ['files' => ['tmp_name' => '/tmp/x.pdf']]);
        $this->assertFalse(cinode_recruitment_uploaded_cv_is_valid($request));
    }

    #[Test]
    public function cv_valid_returns_false_for_non_uploaded_file(): void
    {
        // is_uploaded_file() always returns false in test context
        $request = new FakeWPRestRequest([], [
            'files' => ['tmp_name' => '/tmp/fake.pdf', 'name' => 'resume.pdf'],
        ]);
        $this->assertFalse(cinode_recruitment_uploaded_cv_is_valid($request));
    }

    /* ==================================================================
     * 11. cinode_recruitment_apiTokenCheck()
     * ================================================================*/

    #[Test]
    public function api_token_check_returns_true_for_valid_response(): void
    {
        $this->fakeHttp(200, ['name' => 'Test Company']);

        $result = cinode_recruitment_apiTokenCheck();

        $this->assertTrue($result);
    }

    #[Test]
    public function api_token_check_returns_false_for_empty_response(): void
    {
        $this->fakeHttp(200);

        $result = cinode_recruitment_apiTokenCheck();

        $this->assertFalse($result);
    }

    #[Test]
    public function api_token_check_sends_correct_url_and_headers(): void
    {
        $this->fakeHttp(200, ['name' => 'X']);

        cinode_recruitment_apiTokenCheck();

        $this->assertCount(1, $this->httpLog);
        $req = $this->httpLog[0];
        $this->assertSame('https://api.cinode.app/v0.1/companies/42', $req['url']);
        $this->assertSame('Bearer test-token', $req['args']['headers']['Authorization']);
    }

    #[Test]
    public function api_token_check_caches_result(): void
    {
        $this->fakeHttp(200, ['name' => 'Test Company']);

        $first  = cinode_recruitment_apiTokenCheck();
        $second = cinode_recruitment_apiTokenCheck();

        $this->assertTrue($first);
        $this->assertTrue($second);
        // Only one HTTP call; second used the transient cache
        $this->assertCount(1, $this->httpLog);
    }

    /* ==================================================================
     * 12. cinode_recruitment_boundary()
     * ================================================================*/

    #[Test]
    public function boundary_returns_64_char_alphanumeric_string(): void
    {
        $boundary = cinode_recruitment_boundary();

        $this->assertSame(64, strlen($boundary));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $boundary);
    }

    #[Test]
    public function boundary_returns_different_values_on_successive_calls(): void
    {
        $a = cinode_recruitment_boundary();
        $b = cinode_recruitment_boundary();

        $this->assertNotSame($a, $b);
    }
}
