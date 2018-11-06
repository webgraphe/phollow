<?php

namespace Webgraphe\Phollow\Contracts;

interface ErrorContract extends \JsonSerializable
{
    /** @var string[] */
    const E_STRINGS = [
        E_ERROR => 'ERROR',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_STRICT => 'STRICT',
        E_DEPRECATED => 'DEPRECATED',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
    ];

    /**
     * @param string $id
     * @return static
     */
    public function withSessionId($id);

    /**
     * @return string
     */
    public function __toString();

    /**
     * @return array
     */
    public function toArray();

    /**
     * @return string
     */
    public function getMessage();

    /**
     * @return int
     */
    public function getSeverity();

    /**
     * @return string
     */
    public function getFile();

    /**
     * @return int
     */
    public function getLine();

    /**
     * @return string[]
     */
    public function getTrace();

    /**
     * @return null|string
     */
    public function getHost();

    /**
     * @return null|string
     */
    public function getServerIp();

    /**
     * @return null|string
     */
    public function getRemoteIp();

    /**
     * @return null|string
     */
    public function getSessionId();

    /**
     * @return int
     */
    public function getId();
}
