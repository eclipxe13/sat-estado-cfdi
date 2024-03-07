<?php

declare(strict_types=1);

namespace PhpCfdi\SatEstadoCfdi\Tests\Unit\Clients\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use PhpCfdi\SatEstadoCfdi\Clients\Http\HttpConsumerClient;
use PhpCfdi\SatEstadoCfdi\Clients\Http\HttpConsumerFactory;
use PhpCfdi\SatEstadoCfdi\Clients\Http\HttpConsumerFactoryInterface;
use PhpCfdi\SatEstadoCfdi\Contracts\Constants;
use PhpCfdi\SatEstadoCfdi\Contracts\ConsumerClientInterface;
use PhpCfdi\SatEstadoCfdi\Tests\HttpTestCase as TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpConsumerClientTest extends TestCase
{
    public function testConstructor(): void
    {
        $factory = $this->createHttpConsumerFactory();
        $client = new HttpConsumerClient($factory);
        $this->assertInstanceOf(ConsumerClientInterface::class, $client);
        $this->assertSame($factory, $client->factory);
    }

    public function testCreateSoapHttpRequest(): void
    {
        $expectedFile = $this->filePath('soap-request-foobar.xml');
        $expectedUri = 'http://example.com/';
        $expectedContentType = 'text/xml; charset=utf-8';
        $expectedSoapAction = Constants::SOAP_ACTION;

        $factory = $this->createHttpConsumerFactory();
        $client = new HttpConsumerClient($factory);

        $request = $client->createHttpRequest($expectedUri, '?a=foo&b=bar');

        $this->assertSame($expectedUri, strval($request->getUri()));
        $this->assertSame($expectedContentType, implode('', $request->getHeader('Content-Type')));
        $this->assertSame($expectedSoapAction, implode('', $request->getHeader('SOAPAction')));
        $this->assertXmlStringEqualsXmlFile($expectedFile, $request->getBody()->getContents());
    }

    public function testCreateConsumerClientResponse(): void
    {
        $sourceFile = $this->fileContentPath('soap-response.xml');

        $factory = $this->createHttpConsumerFactory();
        $client = new HttpConsumerClient($factory);

        $container = $client->createConsumerClientResponse($sourceFile);

        $this->assertSame('S - Comprobante obtenido satisfactoriamente.', $container->get('CodigoEstatus'));
        $this->assertSame('Cancelable con aceptación', $container->get('EsCancelable'));
        $this->assertSame('Vigente', $container->get('Estado'));
        $this->assertSame('Solicitud rechazada', $container->get('EstatusCancelacion'));
    }

    public function testConsume(): void
    {
        $httpFactory = new HttpFactory();
        $xmlContent = $this->fileContentPath('soap-response.xml');
        $preparedResponse = $httpFactory->createResponse(200)
            ->withBody($httpFactory->createStream($xmlContent));
        $httpClient = new Client(['handler' => HandlerStack::create(
            new MockHandler([$preparedResponse]),
        )]);
        $factory = new HttpConsumerFactory($httpClient, $httpFactory, $httpFactory);
        $client = new HttpConsumerClient($factory);

        $container = $client->consume('https://example.com/', '');

        $this->assertSame('S - Comprobante obtenido satisfactoriamente.', $container->get('CodigoEstatus'));
    }

    public function testMethodSendRequest(): void
    {
        /** @var RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        /** @var ResponseInterface $response */
        $response = $this->createMock(ResponseInterface::class);

        /** @var HttpClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        /** @var HttpConsumerFactoryInterface&MockObject $factory */
        $factory = $this->createMock(HttpConsumerFactoryInterface::class);
        $factory->expects($this->once())
            ->method('httpClient')
            ->willReturn($httpClient);

        $httpConsumetClient = new HttpConsumerClient($factory);

        $this->assertSame($response, $httpConsumetClient->sendRequest($request));
    }
}
