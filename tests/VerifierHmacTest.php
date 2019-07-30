<?php

namespace HttpSignatures\tests;

use GuzzleHttp\Psr7\Request;
use HttpSignatures\KeyStore;
use HttpSignatures\Verifier;

class VerifierHmacTest extends \PHPUnit_Framework_TestCase
{
    const DATE = 'Fri, 01 Aug 2014 13:44:32 -0700';
    const DATE_DIFFERENT = 'Fri, 01 Aug 2014 13:44:33 -0700';

    /**
     * @var Verifier
     */
    private $verifier;

    /**
     * @var Request
     */
    private $message;

    public function setUp()
    {
        $this->setUpHmacVerifier();
        $this->setUpValidHmacMessage();
    }

    private function setUpHmacVerifier()
    {
        $keyStore = new KeyStore(['secret1' => 'secret']);
        $this->verifier = new Verifier($keyStore);
    }

    private function setUpValidHmacMessage()
    {
        $signatureHeader = sprintf(
            'keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            'secret1',
            'hmac-sha256',
            '(request-target) date digest',
            'tcniMTUZOzRWCgKmLNAHag0CManFsj25ze9Skpk4q8c='
        );

        $this->message = new Request('GET', '/path?query=123', [
            'Date' => self::DATE,
            'Signature' => $signatureHeader,
            'Digest' => 'SHA-256=h7gWacNDycTMI1vWH4Z3f3Wek1nNZS8px82bBQEEARI=',
        ], 'Some body (though any body in a GET should be ignored)');
    }

    public function testVerifyValidHmacMessage()
    {
        $this->assertTrue($this->verifier->isValid($this->message));
    }

    public function testVerifyValidDigest()
    {
        $this->assertTrue($this->verifier->isValidDigest($this->message));
    }

    public function testVerifyValidWithDigest()
    {
        $this->assertTrue($this->verifier->isValidWithDigest($this->message));
    }

    public function testRejectBadDigest()
    {
        $message = $this->message->withoutHeader('Digest')
          ->withHeader('Digest', 'SHA-256=xxx');
        $this->assertFalse($this->verifier->isValidDigest($message));
    }

    /**
     * @expectedException \HttpSignatures\DigestException
     */
    public function testRejectBadDigestName()
    {
        $message = $this->message->withoutHeader('Digest')
          ->withHeader('Digest', 'SHA-255=xxx');
        $this->assertFalse($this->verifier->isValidDigest($message));
    }

    /**
     * @expectedException \HttpSignatures\DigestException
     */
    public function testRejectBadDigestLine()
    {
        $message = $this->message->withoutHeader('Digest')
          ->withHeader('Digest', 'h7gWacNDycTMI1vWH4Z3f3Wek1nNZS8px82bBQEEARI=');
        $this->assertFalse($this->verifier->isValidDigest($message));
    }

    public function testVerifyValidMessageAuthorizationHeader()
    {
        $message = $this->message->withHeader('Authorization', "Signature {$this->message->getHeader('Signature')[0]}");
        $message = $message->withoutHeader('Signature');

        $this->assertTrue($this->verifier->isValid($this->message));
    }

    public function testRejectTamperedHmacRequestMethod()
    {
        $message = $this->message->withMethod('POST');
        $this->assertFalse($this->verifier->isValid($message));
    }

    public function testRejectTamperedHmacDate()
    {
        $message = $this->message->withHeader('Date', self::DATE_DIFFERENT);
        $this->assertFalse($this->verifier->isValid($message));
    }

    public function testRejectTamperedHmacSignature()
    {
        $message = $this->message->withHeader(
            'Signature',
            preg_replace('/signature="/', 'signature="x', $this->message->getHeader('Signature')[0])
        );
        $this->assertFalse($this->verifier->isValid($message));
    }

    public function testRejectHmacMessageWithoutSignatureHeader()
    {
        $message = $this->message->withoutHeader('Signature');
        $this->assertFalse($this->verifier->isValid($message));
    }

    public function testRejectHmacMessageWithGarbageSignatureHeader()
    {
        $message = $this->message->withHeader('Signature', 'not="a",valid="signature"');
        $this->assertFalse($this->verifier->isValid($message));
    }

    public function testRejectHmacMessageWithPartialSignatureHeader()
    {
        $message = $this->message->withHeader('Signature', 'keyId="aa",algorithm="bb"');
        $this->assertFalse($this->verifier->isValid($message));
    }

    public function testRejectsHmacMessageWithUnknownKeyId()
    {
        $keyStore = new KeyStore(['nope' => 'secret']);
        $verifier = new Verifier($keyStore);
        $this->assertFalse($verifier->isValid($this->message));
    }

    public function testRejectsHmacMessageMissingSignedHeaders()
    {
        $message = $this->message->withoutHeader('Date');
        $this->assertFalse($this->verifier->isValid($message));
    }
}
