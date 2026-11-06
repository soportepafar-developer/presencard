<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\JsonBody;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class JsonBodyTest extends TestCase
{
    public function testDecodesJsonObject(): void
    {
        $request = $this->request('{"fullName":"Ana"}', 'application/json; charset=utf-8');

        $this->assertSame(['fullName' => 'Ana'], JsonBody::decode($request));
    }

    public function testRejectsNonJsonContentType(): void
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);

        JsonBody::decode($this->request('{"fullName":"Ana"}', 'text/plain'));
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(BadRequestHttpException::class);

        JsonBody::decode($this->request('{no es json', 'application/json'));
    }

    public function testRejectsScalarJson(): void
    {
        $this->expectException(BadRequestHttpException::class);

        JsonBody::decode($this->request('"texto"', 'application/json'));
    }

    public function testStringHelpersCoerceOnlySafeTypes(): void
    {
        $data = ['a' => 'x', 'n' => 584, 'arr' => ['z'], 'null' => null];

        $this->assertSame('x', JsonBody::string($data, 'a'));
        $this->assertSame('584', JsonBody::string($data, 'n'));
        $this->assertSame('', JsonBody::string($data, 'arr'));
        $this->assertSame('', JsonBody::string($data, 'missing'));
        $this->assertNull(JsonBody::nullableString($data, 'null'));
        $this->assertNull(JsonBody::nullableString($data, 'arr'));
    }

    private function request(string $body, string $contentType): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => $contentType], $body);
    }

    public function testBoolReturnsOnlyRealBooleans(): void
    {
        $data = ['yes' => true, 'no' => false, 'text' => 'true', 'number' => 1];

        $this->assertTrue(JsonBody::bool($data, 'yes'));
        $this->assertFalse(JsonBody::bool($data, 'no'));
        $this->assertNull(JsonBody::bool($data, 'text'));
        $this->assertNull(JsonBody::bool($data, 'number'));
        $this->assertNull(JsonBody::bool($data, 'missing'));
    }
}
