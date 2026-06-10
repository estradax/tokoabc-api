<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\task;

class DemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with demo data: admin user, products, customers, and orders spread across the last two months.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Seeding demo data...');

        $this->createAdminUser();
        $products = $this->createProducts();
        $customers = $this->createCustomers();
        $this->createOrders($customers, $products);

        $this->info('Demo data seeded successfully.');

        return self::SUCCESS;
    }

    /**
     * Create the admin user (or update if already exists).
     */
    private function createAdminUser(): void
    {
        task('Creating admin user', function (): void {
            User::updateOrCreate(
                ['email' => 'admin@mail.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('12345678'),
                ],
            );
        });
    }

    /**
     * Realistic Indonesian toko (shop) product names.
     *
     * @var list<string>
     */
    private const PRODUCT_NAMES = [
        'Beras Premium Pandan Wangi 5kg',
        'Minyak Goreng Bimoli 2 Liter',
        'Gula Pasir Gulaku 1kg',
        'Kopi Kapal Api Special Mix',
        'Teh Celup Sariwangi isi 25',
        'Indomie Goreng Original',
        'Indomie Kuah Soto',
        'Sabun Mandi Lifebuoy Total 10',
        'Shampo Pantene Anti Rontok',
        'Pasta Gigi Pepsodent White',
        'Susu Ultra Jaya Full Cream 1L',
        'Biskuit Khong Guan Assorted',
        'Kecap Manis ABC 600ml',
        'Saus Sambal ABC Extra Pedas',
        'Deterjen Rinso Anti Noda',
        'Tisu Paseo Soft Pack 250',
        'Air Mineral Aqua 600ml',
        'Snack Chitato Sapi Panggang',
        'Permen Kopiko Coffee Candy',
        'Kopi Good Day Cappuccino',
        'Sarden ABC Saus Tomat',
        'Mie Sedaap Goreng',
        'Sabun Cuci Soklin Lantai',
        'Pocari Sweat 500ml',
    ];

    /**
     * Create a sparse set of products dated within the last two months.
     *
     * @return Collection<int, Product>
     */
    private function createProducts(): Collection
    {
        return task('Creating products', function (): Collection {
            $products = new Collection;

            $names = collect(self::PRODUCT_NAMES)->shuffle()->take(18);

            // Sparse: 18 products spread over ~60 days
            foreach ($names as $name) {
                $product = Product::factory()->create(['name' => $name]);
                $createdAt = $this->randomDateWithinTwoMonths();
                $product->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $products->push($product);
            }

            return $products;
        });
    }

    /**
     * Create a sparse set of customers dated within the last two months.
     *
     * @return Collection<int, Customer>
     */
    private function createCustomers(): Collection
    {
        return task('Creating customers', function (): Collection {
            $customers = new Collection;

            // Sparse: 12 customers spread over ~60 days
            for ($i = 0; $i < 12; $i++) {
                $customer = Customer::factory()->create();
                $createdAt = $this->randomDateWithinTwoMonths();
                $customer->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $customers->push($customer);
            }

            return $customers;
        });
    }

    /**
     * Create a sparse set of orders with items, dated within the last two months.
     *
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $products
     */
    private function createOrders(Collection $customers, Collection $products): void
    {
        task('Creating orders', function () use ($customers, $products): void {
            // Sparse: 25 orders spread over ~60 days
            for ($i = 0; $i < 25; $i++) {
                $customer = $customers->random();
                $createdAt = $this->randomDateWithinTwoMonths();

                $order = Order::factory()->create([
                    'customer_id' => $customer->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Each order gets 1-4 random items
                $itemCount = fake()->numberBetween(1, 4);
                $orderProducts = $products->random(min($itemCount, $products->count()));
                $totalAmount = 0;

                foreach ($orderProducts as $product) {
                    $quantity = fake()->numberBetween(1, 3);
                    $unitPrice = $product->price;

                    OrderItem::factory()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_json' => $product->toArray(),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    $totalAmount += $quantity * $unitPrice;
                }

                $order->update(['total_amount' => $totalAmount]);
            }
        });
    }

    /**
     * Get a random Carbon date within the last 60 days.
     */
    private function randomDateWithinTwoMonths(): Carbon
    {
        return now()->subDays(fake()->numberBetween(1, 60))
            ->setHour(fake()->numberBetween(6, 22))
            ->setMinute(fake()->numberBetween(0, 59))
            ->setSecond(fake()->numberBetween(0, 59));
    }
}
