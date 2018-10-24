<?php


namespace ZendSentry\Http\Header;

use Zend\Http\Header\Exception;
use Zend\Http\Header\ContentSecurityPolicy as ZendCSP;
use Zend\Http\Header\MultipleHeaderInterface;

/**
 * Content Security Policy Header
 *
 * @link http://www.w3.org/TR/CSP/
 */
class ContentSecurityPolicy extends ZendCSP implements MultipleHeaderInterface
{
    public const KEY_CSP = 'csp';

    public const DIRECTIVE_DEFAULT_SRC     = 'default-src';
    public const DIRECTIVE_SCRIPT_SRC      = 'script-src';
    public const DIRECTIVE_STYLE_SRC       = 'style-src';
    public const DIRECTIVE_FONT_SRC        = 'font-src';
    public const DIRECTIVE_IMG_SRC         = 'img-src';
    public const DIRECTIVE_FRAME_ANCESTORS = 'frame-ancestors';
    public const DIRECTIVE_BASE_URI        = 'base-uri';
    public const DIRECTIVE_FORM_ACTION     = 'form-action';
    public const DIRECTIVE_CONNECT_SRC     = 'connect-src';
    public const DIRECTIVE_REPORT_URI      = 'report-uri';

    public const SOURCE_ALL  = '*';
    public const SOURCE_SELF = "'self'";
    public const SOURCE_DATA = 'data:';

    // These UNSAFE directives should be avoided
    public const SOURCE_UNSAFE_INLINE = "'unsafe-inline'";
    public const SOURCE_UNSAFE_EVAL   = "'unsafe-eval'";

    /**
     * @var string
     */
    private static $nonce;

    /**
     * ContentSecurityPolicy constructor.
     *
     * @param Config $config
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Returns a new nonce for each request.
     *
     * @return string
     */
    public static function getNonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(20));
        }
        return self::$nonce;
    }

    /**
     * @param array $headers
     *
     * @return string
     */
    public function toStringMultipleHeaders(array $headers): string
    {
        $headerLine = $this->toString();
        /* @var $header ZendCSP */
        foreach ($headers as $header) {
            if (! $header instanceof ZendCSP) {
                throw new Exception\RuntimeException(
                    'The ContentSecurityPolicy multiple header implementation can only accept an array of ContentSecurityPolicy headers'
                );
            }
            $headerLine .= "\n" . $header->toString();
        }
        return $headerLine;
    }

    /**
     * Set the needed CSP directives for ZendSentry
     */
    public function init(): void
    {
        $csp = [
            self::DIRECTIVE_SCRIPT_SRC => [
                'cdn.ravenjs.com',
                sprintf("'nonce-%s'", self::getNonce()),
            ]
        ];

        foreach ($csp as $directive => $sources) {
            $this->setDirective($directive, $sources);
        }
    }
}