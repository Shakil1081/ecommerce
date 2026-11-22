<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-editable storefront content page (About, Contact, Privacy, Terms, Returns, Shipping).
 *
 * The body is plain text with a tiny markup ("## " heading, "- " bullet, blank line = paragraph),
 * rendered by StorePageBodyParser into structured blocks so the storefront never needs v-html.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $meta_description
 * @property string $body
 * @property bool $is_published
 */
class StorePage extends Model
{
    /**
     * Public URL path for each built-in page (footer + sitemap link to these).
     */
    public const SYSTEM_SLUGS = ['about', 'contact', 'privacy', 'terms', 'returns', 'shipping'];

    protected $fillable = [
        'slug',
        'title',
        'meta_description',
        'body',
        'is_published',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @param  Builder<StorePage>  $query
     * @return Builder<StorePage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Insert any missing built-in pages with starter content (never overwrites admin edits).
     */
    public static function seedDefaults(): void
    {
        foreach (self::defaults() as $slug => $page) {
            self::query()->firstOrCreate(['slug' => $slug], $page + ['is_published' => true]);
        }
    }

    /**
     * Starter content. Store owners must review it (contact details, BIN, return window) before launch.
     *
     * @return array<string, array{title: string, meta_description: string, body: string}>
     */
    public static function defaults(): array
    {
        $store = (string) config('app.name', 'Pick & Pearl');

        return [
            'about' => [
                'title' => 'About Us',
                'meta_description' => "Learn about {$store}: genuine jewelry, bags and fashion accessories delivered across Bangladesh.",
                'body' => <<<TEXT
{$store} is a Bangladesh-based online store for genuine jewelry, luxury bags and fashion accessories.

## What we promise
- 100% genuine products, sourced directly from brands, authorised distributors and verified importers.
- Official NBR Mushak 6.3 VAT invoice with every order.
- Fast delivery to all 64 districts with Cash on Delivery, bKash, Nagad and card payments.

## Our company
Trade name: {$store}
Address: [Your registered business address], Dhaka, Bangladesh
Trade licence: [Trade licence number]
VAT BIN: [Your 13-digit BIN]
TEXT,
            ],
            'contact' => [
                'title' => 'Contact Us',
                'meta_description' => "Contact {$store} customer care by phone, email or WhatsApp.",
                'body' => <<<'TEXT'
We are happy to help with orders, delivery, returns and product questions.

## Customer care
- Phone / WhatsApp: [+880 1XXX-XXXXXX]
- Email: [support@yourdomain.com]
- Hours: 10:00 AM – 10:00 PM, every day

## Office address
[Your business address], Dhaka, Bangladesh

## Order help
To check delivery status, use the Track Order page with your order number and phone number.
TEXT,
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'meta_description' => "How {$store} collects, uses and protects your personal information.",
                'body' => <<<TEXT
This policy explains what information {$store} collects when you use our website and how we use it.

## Information we collect
- Name, mobile number, email and delivery address you provide at checkout or when creating an account.
- Order history, payment status and delivery records.
- Device and usage data such as IP address and browser type, used for security and fraud prevention.

## How we use it
- To process, deliver and support your orders.
- To send order updates and verification codes by SMS, WhatsApp or email.
- To prevent fraud and fake orders, which may include checking delivery history with courier partners.

## Payments
Online payments are processed by bKash, Nagad or SSLCommerz. We never see or store your card number or wallet PIN.

## Sharing
We share only what is needed with courier partners (name, phone, address) and payment providers. We do not sell your data.

## Your choices
You can update your account details at any time, or contact us to request deletion of your account.

## Contact
Questions about this policy: [support@yourdomain.com]
TEXT,
            ],
            'terms' => [
                'title' => 'Terms & Conditions',
                'meta_description' => "Terms and conditions for shopping at {$store}.",
                'body' => <<<TEXT
By placing an order on {$store} you agree to these terms.

## Orders
- An order is confirmed after we verify it. We may call or send an SMS code to confirm your order.
- We may cancel an order if a product is out of stock, the price was shown incorrectly, or the order looks fraudulent.

## Prices and payment
- All prices are in Bangladeshi Taka (BDT) and include VAT where applicable.
- Delivery charges are shown at checkout before you place the order.
- Some orders may require an advance delivery charge before dispatch.

## Delivery
Delivery times are estimates. Please check the parcel in front of the delivery person before accepting it.

## Returns and refunds
Returns are handled under our Return & Refund Policy.

## Governing law
These terms are governed by the laws of Bangladesh.
TEXT,
            ],
            'returns' => [
                'title' => 'Return & Refund Policy',
                'meta_description' => "How to return a product and get a refund from {$store}.",
                'body' => <<<'TEXT'
We want you to be happy with your purchase. If something is wrong, you can request a return.

## Return window
You can request a return within 7 days of delivery.

## Eligible reasons
- The product is defective or damaged.
- You received the wrong item.
- The product was damaged in shipping.
- You changed your mind (the product must be unused, with original tags and packaging).

## How to request a return
- Log in to My Account, open the order and choose Request a Return.
- Or contact customer care with your order number.

## Refunds
- After we receive and inspect the product, refunds are sent within 7–10 working days.
- Refunds go to the original payment method, or to bKash/Nagad for Cash on Delivery orders.
- Delivery charges are refundable only when the product was defective or wrong.
TEXT,
            ],
            'shipping' => [
                'title' => 'Shipping & Delivery',
                'meta_description' => "Delivery areas, times and charges for {$store} orders across Bangladesh.",
                'body' => <<<'TEXT'
We deliver to all 64 districts of Bangladesh through our courier partners.

## Delivery times
- Inside Dhaka: 1–2 days
- Dhaka suburbs: 2–3 days
- Outside Dhaka: 3–5 working days

## Delivery charges
The exact delivery charge for your area is shown at checkout before you place the order.

## Cash on Delivery
Cash on Delivery is available for most orders. Some orders may require an advance delivery charge.

## Tracking
Use the Track Order page with your order number and phone number to see your delivery status.
TEXT,
            ],
        ];
    }
}
