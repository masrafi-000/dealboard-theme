# DealBoard WordPress Theme

## Installation Guide (Step by Step)

### Step 1: Upload Theme
1. WordPress Dashboard → Appearance → Themes → Add New → Upload Theme
2. Choose `dealboard-theme.zip` → Install Now → Activate

### Step 2: Setup Pages (IMPORTANT)
Go to Pages → Add New and create these pages with these EXACT templates:

| Page Title | Slug | Template |
|-----------|------|----------|
| Home | / | (Set as Front Page) |
| Post Ad | post-ad | Post Ad |
| Sign In | sign-in | Sign In |
| Sign Up | sign-up | Sign Up |
| Dashboard | dashboard | Dashboard |

Then go to Settings → Reading → Set "Your homepage displays" to "A static page" → Select "Home"

### Step 3: Setup Categories (39 categories)
Go to Listings → Categories → Add these:
1. Agriculture
2. Apparel
3. Art & Collectibles
4. Books & Magazine
5. Baby & Kids
6. Beauty & Personal Care
7. Boat/Yacht
8. Business Sale/Wanted
9. Construction
10. Event Tickets
11. Events & Entertainment
12. Free Stuff
13. Furniture
14. Food & Dining
15. Health & Wellness
16. Hobbies
17. Home & Garden
18. Household Services
19. IT & Software
20. Jobs & Careers
21. Legal Services
22. Marketing & Advertising
23. Mobile & Electronics
24. Music & Instruments
25. Office Supplies
26. Pets
27. Property Services
28. Property Rentals
29. Property for Sale
30. Services
31. Special Needs
32. Sports & Fitness
33. Shoes & Accessories
34. Toys & Games
35. Travel & Tourism
36. Tools & Equipment
37. Transportation
38. Vehicles & Parts
39. All Others

### Step 4: Set Permalinks
Settings → Permalinks → Post name → Save Changes

### Step 5: Blog name and tagline
Settings → General:
- Site Title: DealBoard (or your name)
- Tagline: Your local marketplace for classified ads and garage sales. Buy, sell, and discover great deals in your community.

### Step 6: Menus (optional)
Appearance → Menus → Create menu with Browse Ads, Garage Sales

### Step 7: Free Listing Rules
The theme enforces:
- Free post: 7 days active
- Max 7 photos per listing
- 1 video (5 seconds max)

These are applied automatically when users submit via frontend.

### Features Included:
✅ Homepage with Hero, Categories, Recent Listings, Garage Sales, CTA
✅ Browse Ads page with category tabs + search
✅ Single listing detail page
✅ Post Ad form (frontend submission)
✅ Garage Sales listing and detail
✅ Sign In / Sign Up pages
✅ User Dashboard
✅ Currency switcher (USD, EUR, GBP, CAD, AUD, BDT)
✅ Mobile responsive
✅ 39 category support
✅ Auto-expiry cron (listings expire after 7 days free / 30 days paid)
✅ View counter
✅ Admin columns for price, city, status, views

### Color Scheme:
- Primary: #10B981 (Teal)
- Orange accent: #F97316
- Dark footer: #111827
- Background: #F9FAFB

### Recommended Additional Plugins:
- Contact Form 7 (for contact messages)
- WooCommerce (for featured/paid listings)
- Yoast SEO (for SEO)
- WP Super Cache (for speed)
- Akismet (for spam protection)

---

## 🆕 American Alley Update — Email OTP, Sender Fix & Stripe Business Subscriptions

### 1. Signup Email OTP Verification
New members now verify their email before the account is created:
1. User fills the Sign Up form → a 6-digit code is emailed (account is **not** created yet).
2. User enters the code on the verification screen → account is created and they're logged in.
- Code expires in 15 minutes, max 5 attempts, with a **Resend code** option.
- No extra setup required. Just make sure the site can send email (see SMTP note below).

### 2. Email Sender Fix (no more "WordPress")
All theme emails (password reset, OTP) now come from your support address instead of the default `WordPress <wordpress@yourdomain>`.
- Default From: **american.alley.support@gmail.com**, From name: your Site Title.
- Change it under **Settings → Payments & Mail → From address**, or in `wp-config.php`:
  `define('DEALBOARD_MAIL_FROM', 'american.alley.support@gmail.com');`
- ⚠️ **Deliverability:** sending *from* a `@gmail.com` address through your own server often fails Gmail's SPF/DKIM checks and may land in spam. For reliable delivery install an SMTP plugin (e.g. **WP Mail SMTP**) and authenticate as that Gmail account (Gmail → App Password). The From address above will then send properly.

### 3. Stripe Business Ad Subscriptions ($2 / 30 days)
Business-plan ads are now paid via Stripe as a recurring subscription:
- Posting a Business ad sends the user to secure **Stripe Checkout** ($2).
- On success the ad goes live for **30 days**.
- Stripe **auto-charges $2 every 30 days**; each paid cycle extends the ad another 30 days.
- The user can **turn auto-payment off** from the Dashboard — the ad stays visible until the current 30 days end, then it **stops showing** (it's hidden from public lists but remains in their Dashboard to re-subscribe). They can **turn it back on** before the period ends.

**Setup (Settings → Payments & Mail):**
1. Enter your Stripe **Secret key** and **Publishable key** (test or live).
2. In the Stripe Dashboard → Developers → **Webhooks**, add an endpoint pointing to the URL shown on the settings page (`/wp-json/dealboard/v1/stripe-webhook`) and subscribe to:
   `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`.
   Paste the **Webhook signing secret** (`whsec_…`) into settings.
3. Price defaults to **200 cents ($2)** per **30-day** cycle — adjustable in settings.

> Even if webhooks aren't set up yet, a built-in **daily reconcile** checks each active subscription against Stripe and keeps the ad's visibility/expiry in sync. Webhooks are still recommended for instant updates.

No Stripe PHP SDK is required — the theme talks to the Stripe REST API directly via the WordPress HTTP API.
