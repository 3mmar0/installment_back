# SOLID Principles Implementation Guide

This document explains how SOLID principles are implemented in the Installment Manager API.

## Table of Contents

1. [Single Responsibility Principle (SRP)](#single-responsibility-principle)
2. [Open/Closed Principle (OCP)](#openclosed-principle)
3. [Liskov Substitution Principle (LSP)](#liskov-substitution-principle)
4. [Interface Segregation Principle (ISP)](#interface-segregation-principle)
5. [Dependency Inversion Principle (DIP)](#dependency-inversion-principle)

---

## Single Responsibility Principle

> A class should have only one reason to change.

### Implementation

#### Controllers

Controllers handle **only** HTTP request/response concerns:

```php
class CustomerController extends Controller
{
    use ApiResponse; // Consistent response formatting

    public function __construct(
        private readonly CustomerServiceInterface $customerService
    ) {}

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        // Only handles HTTP layer
        $customer = $this->customerService->createCustomer(
            $request->validated(),
            $request->user()
        );

        return $this->createdResponse(
            new CustomerResource($customer),
            'Customer created successfully'
        );
    }
}
```

#### Services

Services contain **only** business logic:

```php
class CustomerService implements CustomerServiceInterface
{
    public function createCustomer(array $data, User $user): Customer
    {
        // Only business logic
        return DB::transaction(function () use ($data, $user) {
            return Customer::create([
                'user_id' => $user->id,
                ...$data
            ]);
        });
    }
}
```

#### Resources

Resources handle **only** data transformation:

```php
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Only data transformation
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // ...
        ];
    }
}
```

#### Traits

Traits provide **only** reusable helpers:

```php
trait ApiResponse
{
    // Only response formatting
    protected function successResponse($data, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
}
```

---

## Open/Closed Principle

> Software entities should be open for extension but closed for modification.

### Implementation

#### Service Interfaces

New functionality can be added by implementing interfaces without modifying existing code:

```php
// Original interface - CLOSED for modification
interface CustomerServiceInterface
{
    public function createCustomer(array $data, User $user): Customer;
}

// Extended with new features - OPEN for extension
interface CustomerServiceInterface
{
    public function createCustomer(array $data, User $user): Customer;
    public function getCustomerStats(Customer $customer): array; // New method
}

// Implementation adapts without breaking existing code
class CustomerService implements CustomerServiceInterface
{
    public function createCustomer(array $data, User $user): Customer
    {
        // Original implementation unchanged
    }

    public function getCustomerStats(Customer $customer): array
    {
        // New functionality
    }
}
```

#### Adding New Services

```php
// 1. Create interface
interface PaymentServiceInterface
{
    public function processPayment(array $data): Payment;
}

// 2. Implement service
class PaymentService implements PaymentServiceInterface
{
    public function processPayment(array $data): Payment
    {
        // Implementation
    }
}

// 3. Register in ServiceBindingProvider
public $bindings = [
    PaymentServiceInterface::class => PaymentService::class,
];
```

---

## Liskov Substitution Principle

> Objects should be replaceable with instances of their subtypes without altering correctness.

### Implementation

Any service implementing an interface can be substituted without breaking the application:

```php
// Original implementation
class CustomerService implements CustomerServiceInterface
{
    public function createCustomer(array $data, User $user): Customer
    {
        return Customer::create(['user_id' => $user->id, ...$data]);
    }
}

// Alternative implementation (e.g., with caching)
class CachedCustomerService implements CustomerServiceInterface
{
    public function createCustomer(array $data, User $user): Customer
    {
        $customer = Customer::create(['user_id' => $user->id, ...$data]);
        Cache::put("customer:{$customer->id}", $customer);
        return $customer;
    }
}

// Controller works with BOTH implementations
class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerServiceInterface $customerService
    ) {}

    // Works regardless of which implementation is bound
}
```

To switch implementations:

```php
// In ServiceBindingProvider
public $bindings = [
    CustomerServiceInterface::class => CachedCustomerService::class, // Just change this
];
```

---

## Interface Segregation Principle

> No client should be forced to depend on methods it does not use.

### Implementation

We create **focused, specific interfaces** instead of one large interface:

#### ❌ Bad (Violates ISP)

```php
interface ServiceInterface
{
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id);
    public function find(int $id);
    public function getAll();
    public function getStats();
    public function export();
    public function import();
}
```

#### ✅ Good (Follows ISP)

```php
// Separate interfaces for different concerns
interface AuthServiceInterface
{
    public function login(array $credentials): array;
    public function logout(User $user): bool;
    public function register(array $data): array;
    public function refreshToken(User $user): string;
}

interface CustomerServiceInterface
{
    public function getCustomersForUser(User $user): LengthAwarePaginator;
    public function createCustomer(array $data, User $user): Customer;
    public function updateCustomer(int $id, array $data, User $user): Customer;
    public function deleteCustomer(int $id, User $user): bool;
    public function getCustomerStats(Customer $customer): array;
}

interface InstallmentServiceInterface
{
    public function getInstallmentsForUser(User $user): LengthAwarePaginator;
    public function createInstallment(array $data, User $user): Installment;
    public function markItemPaid(InstallmentItem $item, array $data, User $user): InstallmentItem;
    public function getDashboardAnalytics(User $user): array;
}
```

Each interface has a **single, focused purpose**.

---

## Dependency Inversion Principle

> High-level modules should not depend on low-level modules. Both should depend on abstractions.

### Implementation

#### Controllers Depend on Interfaces (Abstractions)

```php
// ✅ Good: Depends on interface
class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerServiceInterface $customerService // Interface
    ) {}
}

// ❌ Bad: Depends on concrete class
class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService // Concrete class
    ) {}
}
```

#### Dependency Injection Configuration

```php
// ServiceBindingProvider.php
class ServiceBindingProvider extends ServiceProvider
{
    public $bindings = [
        // Abstraction => Concrete Implementation
        AuthServiceInterface::class => AuthService::class,
        UserServiceInterface::class => UserService::class,
        CustomerServiceInterface::class => CustomerService::class,
        InstallmentServiceInterface::class => InstallmentService::class,
    ];
}
```

#### Benefits

1. **Easy Testing**: Mock interfaces instead of concrete classes

```php
// In tests
$mockService = Mockery::mock(CustomerServiceInterface::class);
$this->app->instance(CustomerServiceInterface::class, $mockService);
```

2. **Easy Implementation Switching**:

```php
// Development
CustomerServiceInterface::class => CustomerService::class

// Production with caching
CustomerServiceInterface::class => CachedCustomerService::class

// Testing
CustomerServiceInterface::class => FakeCustomerService::class
```

3. **Decoupled Code**: Controllers don't know or care about implementation details

---

## Real-World Example: Adding a New Feature

Let's add a notification feature following SOLID principles:

### Step 1: Create Interface (DIP + ISP)

```php
// app/Contracts/Services/NotificationServiceInterface.php
interface NotificationServiceInterface
{
    public function sendPaymentReminder(InstallmentItem $item): bool;
    public function sendPaymentConfirmation(InstallmentItem $item): bool;
}
```

### Step 2: Implement Service (SRP + LSP)

```php
// app/Services/NotificationService.php
class NotificationService implements NotificationServiceInterface
{
    public function sendPaymentReminder(InstallmentItem $item): bool
    {
        // Only notification logic
        $customer = $item->installment->customer;
        // Send email/SMS
        return true;
    }

    public function sendPaymentConfirmation(InstallmentItem $item): bool
    {
        // Only notification logic
        $customer = $item->installment->customer;
        // Send email/SMS
        return true;
    }
}
```

### Step 3: Register Binding (DIP)

```php
// app/Providers/ServiceBindingProvider.php
public $bindings = [
    // ...existing bindings
    NotificationServiceInterface::class => NotificationService::class,
];
```

### Step 4: Use in Controller (DIP + SRP)

```php
class InstallmentController extends Controller
{
    public function __construct(
        private readonly InstallmentServiceInterface $installmentService,
        private readonly NotificationServiceInterface $notificationService
    ) {}

    public function markItemPaid(InstallmentItem $item, Request $request): JsonResponse
    {
        $updatedItem = $this->installmentService->markItemPaid($item, $data, $user);
        $this->notificationService->sendPaymentConfirmation($updatedItem);

        return $this->successResponse(
            new InstallmentItemResource($updatedItem),
            'Payment recorded successfully'
        );
    }
}
```

### Step 5: Easy to Extend (OCP)

```php
// Want to add Slack notifications? Just create a new implementation
class SlackNotificationService implements NotificationServiceInterface
{
    public function sendPaymentReminder(InstallmentItem $item): bool
    {
        // Send to Slack
    }
}

// Switch by changing one line in ServiceBindingProvider
NotificationServiceInterface::class => SlackNotificationService::class,
```

---

## Benefits Summary

1. **Maintainability**: Each class has one clear purpose
2. **Testability**: Easy to mock interfaces
3. **Flexibility**: Switch implementations without changing consumers
4. **Scalability**: Add features without modifying existing code
5. **Readability**: Clear separation of concerns

---

## Testing Example

```php
class CustomerControllerTest extends TestCase
{
    public function test_can_create_customer()
    {
        // Mock the service interface
        $mockService = Mockery::mock(CustomerServiceInterface::class);
        $mockService->shouldReceive('createCustomer')
            ->once()
            ->andReturn(new Customer(['id' => 1, 'name' => 'Test']));

        // Bind mock
        $this->app->instance(CustomerServiceInterface::class, $mockService);

        // Test controller (which depends on interface, not implementation)
        $response = $this->postJson('/api/customers', [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(201);
    }
}
```

This is only possible because we follow the Dependency Inversion Principle!
