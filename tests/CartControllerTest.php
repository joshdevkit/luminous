<?php

namespace Test;

use App\Controllers\Ecom\CartController;
use App\Repositories\CartServiceRepository;
use PHPUnit\Framework\TestCase;
use Core\Http\Request;
use Core\Http\Response;
use Mockery;

class CartControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testAddProductToCart()
    {
        // Mock CartServiceRepository
        $mockCart = Mockery::mock(CartServiceRepository::class);
        $mockCart->shouldReceive('add')
            ->once()
            ->with(1, 1);

        $controller = new CartController($mockCart);

        // Mock request
        $mockRequest = Mockery::mock(Request::class);

        $mockRequest->shouldReceive('input')
            ->once()
            ->with('product_id')
            ->andReturn(1);

        $mockRequest->shouldReceive('input')
            ->once()
            ->with('quantity')
            ->andReturn(1);

        // Call controller method with both args
        $response = $controller->add($mockRequest, $mockCart);
        $response->send();

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(['success' => true], $data);
    }
}
