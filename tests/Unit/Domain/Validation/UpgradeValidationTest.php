<?php

declare(strict_types=1);

use App\Domain\Content\Dto\StoreContentData;
use App\Domain\Content\Validator\StoreContentValidator;
use App\Domain\Product\Dto\StoreProductData;
use App\Domain\Product\Validator\StoreProductValidator;
use App\Domain\User\Model\User;
use App\Domain\User\Validator\UpdateUserProfileValidator;
use App\Domain\User\ValueObject\UserId;
use Qubus\Http\ServerRequest;
use Qubus\Injector\ServiceContainer;
use Qubus\Validation\Factories\ValidationFactory;

class AuthorizedProductInput extends StoreProductValidator
{
    public function authorize(): bool { return true; }
}

class AuthorizedContentInput extends StoreContentValidator
{
    public function authorize(): bool { return true; }
}

class ProfileInputForTest extends UpdateUserProfileValidator
{
    public User|false $user = false;
    protected function authenticatedUser(): User|false { return $this->user; }
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['id' => 'required|ulid', 'role' => 'required|string', 'status' => 'required|string'];
    }
}

it('retains normalized dates and currency through validation into DTOs', function (): void {
    $input = [
        'id' => (string) new UserId(), 'author' => (string) new UserId(),
        'title' => 'Example title', 'slug' => 'example-title', 'status' => 'draft',
        'published' => '2026-09-24 10:00:00', 'publishedGmt' => '2026-09-24 17:00:00',
        'created' => '2026-09-23 10:00:00', 'createdGmt' => '2026-09-23 17:00:00',
        'currency' => 'USD', 'sku' => 'EXAMPLE', 'price' => '1200', 'parent' => 'NULL', 'type' => 'post',
        'untrusted' => 'discard me',
    ];
    $request = new ServerRequest()->withParsedBody($input);
    $product = AuthorizedProductInput::make($request);
    $product->setValidator(ValidationFactory::make($input, $product->rules()));
    $dto = StoreProductData::fromValidatedData($product);
    expect($product->validated())->not->toHaveKey('untrusted')
        ->and($product->validated()['currency'])->toBe('USD')
        ->and($dto->createdGmt->format('Y-m-d H:i:s'))->toBe($input['createdGmt']);

    $content = AuthorizedContentInput::make($request);
    $content->setValidator(ValidationFactory::make($input, $content->rules()));
    $dto = StoreContentData::fromValidatedData($content);
    expect($content->validated())->not->toHaveKey('untrusted')
        ->and($dto->publishedGmt->format('Y-m-d H:i:s'))->toBe($input['publishedGmt'])
        ->and($dto->parent)->toBeNull();
});

it('overrides submitted profile identities and privilege fields before validation', function (): void {
    $user = new ReflectionClass(User::class)->newInstanceWithoutConstructor();
    $user->id = (string) new UserId();
    $user->role = 'editor';
    $user->status = 'A';
    $validator = ProfileInputForTest::make(new ServerRequest()->withParsedBody([
        'id' => (string) new UserId(), 'role' => 'super', 'status' => 'B', 'pass' => 'injected-password',
    ]));
    $validator->user = $user;
    $container = $this->createMock(ServiceContainer::class);
    $container->method('make')->willReturn(new ValidationFactory());
    $validator->setContainer($container);
    expect($validator->validated())->toBe(['id' => $user->id, 'role' => 'editor', 'status' => 'A']);
});
